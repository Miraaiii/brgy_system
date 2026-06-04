<?php
require_once __DIR__ . '/includes/resident_portal.php';

rp_ensure_document_request_columns($conn);

$ctx = rp_get_resident_context($conn, true);
$request_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($request_id <= 0) {
    header('Location: track.php');
    exit();
}

$has_request_tables = rp_table_exists($conn, 'document_types') && rp_table_exists($conn, 'document_requests');
$errors = [];

if ($has_request_tables && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_request') {
    if (!bms_verify_csrf_token($_POST['csrf_token'] ?? '', 'resident_detail_csrf')) {
        $errors[] = 'Your session expired. Please refresh and try again.';
    } else {
        $owned_request = rp_fetch_one(
            $conn,
            'SELECT id, status FROM document_requests WHERE id = ? AND resident_id = ? LIMIT 1',
            'ii',
            [$request_id, (int)$ctx['resident_id']]
        );

        if (!$owned_request) {
            $errors[] = 'Request not found.';
        } elseif (strtolower((string)$owned_request['status']) !== 'pending') {
            $errors[] = 'Only pending requests can be cancelled.';
        } else {
            $remarks = 'Cancelled by resident on ' . date('F j, Y, g:i A') . '.';
            $stmt = $conn->prepare(
                "UPDATE document_requests
                 SET status = 'cancelled', remarks = ?, updated_at = NOW()
                 WHERE id = ? AND resident_id = ? AND status = 'pending'"
            );
            if ($stmt) {
                $stmt->bind_param('sii', $remarks, $request_id, $ctx['resident_id']);
                $stmt->execute();
                $updated = $stmt->affected_rows > 0;
                $stmt->close();
                if ($updated) {
                    rp_notify_request_status($conn, $request_id, 'cancelled');
                    header('Location: request-detail.php?id=' . $request_id . '&cancelled=1');
                    exit();
                }
            }
            $errors[] = 'Unable to cancel this request. Please try again.';
        }
    }
}

$extra_select = rp_column_exists($conn, 'document_requests', 'extra_details')
    ? ', dr.extra_details'
    : ', NULL AS extra_details';
$issued_select = rp_table_exists($conn, 'issued_documents')
    ? ', issued.pdf_path, issued.doc_number, issued.issued_at'
    : ', NULL AS pdf_path, NULL AS doc_number, NULL AS issued_at';
$issued_join = rp_table_exists($conn, 'issued_documents')
    ? ' LEFT JOIN issued_documents issued ON issued.request_id = dr.id'
    : '';

$request = null;
if ($has_request_tables) {
    $request = rp_fetch_one(
        $conn,
        "SELECT dr.*{$extra_select},
                dt.name AS document_name, dt.slug AS document_slug, dt.fee, dt.processing_days,
                processor.fullname AS processed_by_name,
                approver.fullname AS approved_by_name,
                releaser.fullname AS released_by_name
                {$issued_select}
         FROM document_requests dr
         INNER JOIN document_types dt ON dt.id = dr.doc_type_id
         LEFT JOIN users processor ON processor.id = dr.processed_by
         LEFT JOIN users approver ON approver.id = dr.approved_by
         LEFT JOIN users releaser ON releaser.id = dr.released_by
         {$issued_join}
         WHERE dr.id = ? AND dr.resident_id = ?
         LIMIT 1",
        'ii',
        [$request_id, (int)$ctx['resident_id']]
    );
}

if (!$request) {
    rp_page_start('Request Not Found', 'track', $ctx, 'request-detail-page');
    ?>
    <section class="empty-state empty-state--large">
      <i class="fa-solid fa-folder-open" aria-hidden="true"></i>
      <strong>Request not found</strong>
      <span>The request may not exist or may not belong to your resident account.</span>
      <a class="primary-action" href="track.php"><i class="fa-solid fa-arrow-left"></i> Back to my requests</a>
    </section>
    <?php
    rp_page_end();
    exit();
}

$status = strtolower((string)$request['status']);
$extra_details = [];
if (!empty($request['extra_details'])) {
    $decoded = json_decode((string)$request['extra_details'], true);
    if (is_array($decoded)) {
        $extra_details = $decoded;
    }
}

$attachments = [];
if (rp_table_exists($conn, 'request_attachments')) {
    $attachments = rp_fetch_all(
        $conn,
        'SELECT file_name, file_path, file_type, file_size, uploaded_at
         FROM request_attachments
         WHERE request_id = ?
         ORDER BY uploaded_at ASC, id ASC',
        'i',
        [$request_id]
    );
}

$flow = rp_document_status_flow();
$current_flow = $flow[$status] ?? [
    'label' => rp_status_label($status),
    'meaning' => 'Request status is being updated.',
    'next' => 'Check back later for the next action.',
];

function rd_detail_first_name($name) {
    $parts = preg_split('/\s+/', trim((string)$name));
    return $parts && $parts[0] !== '' ? $parts[0] : 'Not assigned';
}

function rd_detail_step_state($step, $status) {
    $active_map = [
        1 => ['pending', 'processing', 'for_approval', 'approved', 'released', 'rejected', 'cancelled'],
        2 => ['processing', 'for_approval', 'approved', 'released'],
        3 => ['for_approval', 'approved', 'released'],
        4 => ['approved', 'released'],
        5 => ['released'],
    ];

    if ($step === 1) {
        return 'is-done';
    }

    if (!in_array($status, $active_map[$step], true)) {
        return 'is-waiting';
    }

    $terminal_step = [
        'processing' => 2,
        'for_approval' => 3,
        'approved' => 4,
        'released' => 5,
    ];

    if (($terminal_step[$status] ?? 0) === $step) {
        return 'is-current';
    }

    return 'is-done';
}

function rd_detail_step_time($value, $fallback = '') {
    if (!empty($value)) {
        return rp_datetime($value);
    }

    return $fallback !== '' ? $fallback : 'Awaiting update';
}

$timeline_steps = [
    [
        'step' => 1,
        'label' => 'Submitted',
        'icon' => 'fa-circle-check',
        'time' => rd_detail_step_time($request['created_at']),
    ],
    [
        'step' => 2,
        'label' => 'Processing',
        'icon' => $status === 'processing' ? 'fa-spinner' : 'fa-circle-check',
        'time' => rd_detail_step_time($request['processed_at'], $status === 'processing' ? 'In progress' : ''),
    ],
    [
        'step' => 3,
        'label' => 'For Approval',
        'icon' => 'fa-stamp',
        'time' => rd_detail_step_time($request['processed_at']),
    ],
    [
        'step' => 4,
        'label' => 'Approved',
        'icon' => 'fa-circle-check',
        'time' => rd_detail_step_time($request['approved_at'], $status === 'approved' ? 'Approved, preparing for release' : ''),
    ],
    [
        'step' => 5,
        'label' => 'Released',
        'icon' => 'fa-star',
        'time' => rd_detail_step_time($request['released_at']),
    ],
];

$csrf_token = bms_csrf_token('resident_detail_csrf');

rp_page_start('Request ' . $request['reference_no'], 'track', $ctx, 'request-detail-page');
?>

<nav class="breadcrumb-line" aria-label="Breadcrumb">
  <a href="resident_dashboard.php">Home</a>
  <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
  <a href="track.php">My Requests</a>
  <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
  <span><?= rp_e($request['reference_no']) ?></span>
</nav>

<section class="portal-page-header request-detail-header">
  <div>
    <p class="page-kicker">Reference number</p>
    <h1><code class="ref-code ref-code--large"><?= rp_e($request['reference_no']) ?></code></h1>
    <p><?= rp_e($current_flow['meaning']) ?></p>
  </div>
  <div class="request-header-actions">
    <span class="status-badge status-badge--<?= rp_e(rp_status_class($status)) ?>"><?= rp_e(rp_status_label($status)) ?></span>
    <a class="secondary-action" href="track.php"><i class="fa-solid fa-arrow-left"></i> Back</a>
  </div>
</section>

<?php if (isset($_GET['cancelled'])): ?>
  <div class="account-alert" role="status">
    <i class="fa-solid fa-circle-check"></i>
    <span>Your request has been cancelled.</span>
  </div>
<?php endif; ?>

<?php if ($errors): ?>
  <div class="account-alert account-alert--danger" role="alert">
    <i class="fa-solid fa-circle-exclamation"></i>
    <span><?= rp_e(implode(' ', $errors)) ?></span>
  </div>
<?php endif; ?>

<section class="detail-grid">
  <div class="dashboard-panel summary-card">
    <div class="panel-header">
      <div>
        <h2>Request summary</h2>
        <p><?= rp_e($current_flow['next']) ?></p>
      </div>
    </div>
    <dl class="summary-list">
      <div>
        <dt>Document type</dt>
        <dd><?= rp_e($request['document_name']) ?></dd>
      </div>
      <div>
        <dt>Purpose</dt>
        <dd><?= nl2br(rp_e($request['purpose'])) ?></dd>
      </div>
      <div>
        <dt>Date submitted</dt>
        <dd><?= rp_e(rp_date_long($request['created_at'])) ?></dd>
      </div>
      <div>
        <dt>Expected processing time</dt>
        <dd><?= rp_e((int)$request['processing_days']) ?> working day<?= (int)$request['processing_days'] === 1 ? '' : 's' ?></dd>
      </div>
      <div>
        <dt>Processed by</dt>
        <dd><?= rp_e(rd_detail_first_name($request['processed_by_name'] ?? '')) ?></dd>
      </div>
      <?php if ($extra_details): ?>
        <div>
          <dt>Additional details</dt>
          <dd>
            <?php foreach ($extra_details as $label => $value): ?>
              <?php if (trim((string)$value) !== ''): ?>
                <span class="detail-chip"><?= rp_e(ucwords(str_replace('_', ' ', $label))) ?>: <?= rp_e($value) ?></span>
              <?php endif; ?>
            <?php endforeach; ?>
          </dd>
        </div>
      <?php endif; ?>
    </dl>
  </div>

  <div class="dashboard-panel timeline-card">
    <div class="panel-header">
      <div>
        <h2>Timeline</h2>
        <p>Submitted to Released, updated by the barangay office.</p>
      </div>
    </div>
    <ol class="request-timeline">
      <?php foreach ($timeline_steps as $step): ?>
        <?php $state = rd_detail_step_state((int)$step['step'], $status); ?>
        <li class="<?= rp_e($state) ?>">
          <span class="timeline-icon"><i class="fa-solid <?= rp_e($step['icon']) ?>" aria-hidden="true"></i></span>
          <div>
            <strong><?= rp_e($step['label']) ?></strong>
            <small><?= rp_e($step['time']) ?></small>
          </div>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>

<?php if ($status === 'rejected' && trim((string)$request['remarks']) !== ''): ?>
  <div class="account-alert account-alert--danger" role="alert">
    <i class="fa-solid fa-circle-xmark"></i>
    <span><?= rp_e($request['remarks']) ?></span>
  </div>
<?php elseif (in_array($status, ['processing', 'for_approval', 'cancelled'], true) && trim((string)$request['remarks']) !== ''): ?>
  <div class="account-alert" role="status">
    <i class="fa-solid fa-note-sticky"></i>
    <span><?= rp_e($request['remarks']) ?></span>
  </div>
<?php endif; ?>

<section class="detail-grid detail-grid--support">
  <div class="dashboard-panel">
    <div class="panel-header">
      <div>
        <h2>Uploaded attachments</h2>
        <p>Files submitted with this request.</p>
      </div>
    </div>
    <?php if ($attachments): ?>
      <div class="attachment-list">
        <?php foreach ($attachments as $attachment): ?>
          <?php $download_href = '../' . ltrim(str_replace('\\', '/', (string)$attachment['file_path']), '/'); ?>
          <a class="attachment-item" href="<?= rp_e($download_href) ?>" download>
            <span><i class="fa-solid fa-file-arrow-down" aria-hidden="true"></i></span>
            <strong><?= rp_e($attachment['file_name']) ?></strong>
            <small><?= rp_e(rp_file_size($attachment['file_size'])) ?> uploaded <?= rp_e(rp_date($attachment['uploaded_at'])) ?></small>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty-state empty-state--compact">
        <i class="fa-solid fa-paperclip"></i>
        <strong>No attachments recorded</strong>
        <span>Uploaded files will appear here.</span>
      </div>
    <?php endif; ?>
  </div>

  <div class="dashboard-panel">
    <div class="panel-header">
      <div>
        <h2>Issued document</h2>
        <p>Available after the request is released.</p>
      </div>
    </div>
    <?php if ($status === 'released' && !empty($request['pdf_path'])): ?>
      <?php $pdf_href = '../' . ltrim(str_replace('\\', '/', (string)$request['pdf_path']), '/'); ?>
      <div class="issued-document">
        <span><i class="fa-solid fa-file-pdf" aria-hidden="true"></i></span>
        <div>
          <strong><?= rp_e($request['doc_number'] ?: $request['reference_no']) ?></strong>
          <small>Issued <?= rp_e(rp_datetime($request['issued_at'])) ?></small>
        </div>
        <a class="primary-action" href="<?= rp_e($pdf_href) ?>" download><i class="fa-solid fa-download"></i> Download</a>
      </div>
    <?php else: ?>
      <div class="empty-state empty-state--compact">
        <i class="fa-solid fa-file-lines"></i>
        <strong>No issued document yet</strong>
        <span>The download button appears once the barangay releases the document.</span>
      </div>
    <?php endif; ?>

    <?php if ($status === 'pending'): ?>
      <div class="danger-zone">
        <button class="secondary-action secondary-action--danger" type="button" data-open-modal="cancelRequestModal">
          <i class="fa-solid fa-ban"></i> Cancel request
        </button>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php if ($status === 'pending'): ?>
  <div class="modal-layer" id="cancelRequestModal" hidden>
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="cancelModalTitle">
      <button class="modal-close" type="button" data-close-modal aria-label="Close dialog"><i class="fa-solid fa-xmark"></i></button>
      <span class="modal-card__icon modal-card__icon--danger"><i class="fa-solid fa-ban" aria-hidden="true"></i></span>
      <h2 id="cancelModalTitle">Cancel this request?</h2>
      <p>This can only be done while the request is still pending. You can submit a new request later.</p>
      <form method="post" class="modal-actions">
        <input type="hidden" name="action" value="cancel_request">
        <input type="hidden" name="csrf_token" value="<?= rp_e($csrf_token) ?>">
        <button class="secondary-action" type="button" data-close-modal>Keep request</button>
        <button class="primary-action primary-action--danger" type="submit"><i class="fa-solid fa-ban"></i> Cancel request</button>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php rp_page_end(); ?>
