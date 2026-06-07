<?php
require_once __DIR__ . '/includes/admin_layout.php';

$user = adm_require_admin($conn, ['captain', 'secretary']);
$csrf = adm_action_token();
$role = strtolower(trim((string)($user['role'] ?? '')));
$is_captain = $role === 'captain';
$request_id = (int)($_GET['id'] ?? ($_POST['request_id'] ?? 0));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!adm_verify_action_token($_POST['csrf_token'] ?? '')) {
        adm_set_flash('danger', 'Your session expired. Please refresh and try again.');
    } else {
        [$ok, $message] = adm_handle_request_action(
            $conn,
            (string)($_POST['action'] ?? ''),
            (int)($_POST['request_id'] ?? 0),
            (int)$user['id'],
            (string)($_POST['reason'] ?? ''),
            $role
        );
        adm_set_flash($ok ? 'success' : 'danger', $message);
    }

    header('Location: request-detail.php?id=' . (int)($_POST['request_id'] ?? $request_id));
    exit();
}

$request = null;
if ($request_id > 0 && adm_table_exists($conn, 'document_requests') && adm_table_exists($conn, 'document_types') && adm_table_exists($conn, 'residents')) {
    $extra_select = adm_column_exists($conn, 'document_requests', 'extra_details') ? ', dr.extra_details' : ', NULL AS extra_details';
    $issued_select = adm_table_exists($conn, 'issued_documents') ? ', issued.doc_number, issued.qr_token, issued.pdf_path, issued.issued_at' : ', NULL AS doc_number, NULL AS qr_token, NULL AS pdf_path, NULL AS issued_at';
    $issued_join = adm_table_exists($conn, 'issued_documents') ? 'LEFT JOIN issued_documents issued ON issued.request_id = dr.id' : '';

    $request = adm_fetch_one(
        $conn,
        "SELECT dr.* {$extra_select},
                dt.name AS document_name, dt.slug AS document_slug, dt.fee, dt.processing_days, dt.requires_approval,
                r.first_name, r.middle_name, r.last_name, r.email AS resident_email, r.contact_number,
                r.birth_date, r.sex, r.civil_status,
                h.house_number, h.street, h.purok,
                processor.fullname AS processed_by_name,
                approver.fullname AS approved_by_name,
                releaser.fullname AS released_by_name
                {$issued_select}
         FROM document_requests dr
         INNER JOIN document_types dt ON dt.id = dr.doc_type_id
         INNER JOIN residents r ON r.id = dr.resident_id
         LEFT JOIN households h ON h.id = r.household_id
         LEFT JOIN users processor ON processor.id = dr.processed_by
         LEFT JOIN users approver ON approver.id = dr.approved_by
         LEFT JOIN users releaser ON releaser.id = dr.released_by
         {$issued_join}
         WHERE dr.id = ?
         LIMIT 1",
        'i',
        [$request_id]
    );
}

$attachments = [];
if ($request && adm_table_exists($conn, 'request_attachments')) {
    $attachments = adm_fetch_all(
        $conn,
        'SELECT file_name, file_path, file_type, file_size, uploaded_at
         FROM request_attachments
         WHERE request_id = ?
         ORDER BY uploaded_at ASC, id ASC',
        'i',
        [$request_id]
    );
}

function admin_request_step_class($step, $status) {
    $status = strtolower((string)$status);
    $order = [
        'pending' => 1,
        'processing' => 2,
        'for_approval' => 3,
        'approved' => 4,
        'released' => 5,
        'rejected' => 2,
        'cancelled' => 1,
    ];
    $current = $order[$status] ?? 1;
    if ($step < $current || $status === 'released') {
        return 'is-done';
    }
    if ($step === $current && !in_array($status, ['rejected', 'cancelled'], true)) {
        return 'is-current';
    }
    return '';
}

adm_page_start('Request Detail', 'requests', $user, 'request-detail-page');
?>

<?php if (!$request): ?>
  <?php adm_page_header('Request detail', 'Request not found', 'The request may have been removed or the reference number is invalid.', '<a class="btn" href="requests.php"><i class="fa-solid fa-arrow-left"></i> Back to inbox</a>'); ?>
  <section class="panel">
    <div class="empty-state">
      <i class="fa-solid fa-folder-open"></i>
      <strong>No matching request</strong>
      <span>Return to the inbox and open a valid request.</span>
    </div>
  </section>
<?php else: ?>
  <?php
    $status = strtolower((string)$request['status']);
    $resident_name = trim($request['first_name'] . ' ' . ($request['middle_name'] ? $request['middle_name'] . ' ' : '') . $request['last_name']);
    $address = trim(implode(', ', array_filter([$request['house_number'], $request['street'], $request['purok']])));
    $extra_details = [];
    if (!empty($request['extra_details'])) {
        $decoded = json_decode((string)$request['extra_details'], true);
        if (is_array($decoded)) {
            $extra_details = $decoded;
        }
    }
    $needs_approval = (int)$request['requires_approval'] === 1;
    $actions = '<a class="btn" href="requests.php"><i class="fa-solid fa-arrow-left"></i> Back</a>';
    if ($is_captain && $status === 'for_approval') {
        $actions .= ' <a class="btn" href="print-document.php?id=' . adm_e($request_id) . '&preview=1" target="_blank" rel="noopener"><i class="fa-solid fa-file-pdf"></i> Preview PDF</a>';
    }
    if (in_array($status, ['approved', 'released'], true)) {
        $actions .= ' <a class="btn btn--primary" href="print-document.php?id=' . adm_e($request_id) . '" target="_blank" rel="noopener"><i class="fa-solid fa-print"></i> Print document</a>';
    }
    adm_page_header('Reference ' . $request['reference_no'], $request['document_name'], 'Submitted by ' . $resident_name . ' on ' . adm_date_long($request['created_at']) . '.', $actions);
  ?>

  <section class="details-grid">
    <div>
      <section class="detail-panel">
        <div class="action-row" style="justify-content: space-between;">
          <h2>Request Summary</h2>
          <span class="status-badge status-badge--<?= adm_e(adm_status_class($status)) ?>"><?= adm_e(adm_status_label($status)) ?></span>
        </div>
        <dl class="definition-list">
          <div>
            <dt>Reference No.</dt>
            <dd><?= adm_e($request['reference_no']) ?></dd>
          </div>
          <div>
            <dt>Document Type</dt>
            <dd><?= adm_e($request['document_name']) ?></dd>
          </div>
          <div>
            <dt>Purpose</dt>
            <dd><?= nl2br(adm_e($request['purpose'])) ?></dd>
          </div>
          <div>
            <dt>Fee</dt>
            <dd>PHP <?= adm_e(number_format((float)$request['fee'], 2)) ?></dd>
          </div>
          <div>
            <dt>Approval Rule</dt>
            <dd><?= $needs_approval ? 'Captain approval required' : 'Secretary can approve and issue' ?></dd>
          </div>
          <div>
            <dt>Processed By</dt>
            <dd><?= adm_e($request['processed_by_name'] ?: 'Not assigned') ?></dd>
          </div>
          <?php if (!empty($request['remarks'])): ?>
            <div class="form-field--full">
              <dt>Remarks</dt>
              <dd><?= nl2br(adm_e($request['remarks'])) ?></dd>
            </div>
          <?php endif; ?>
        </dl>

        <?php if ($extra_details): ?>
          <div class="form-section">
            <h2>Additional Details</h2>
            <div class="summary-list">
              <?php foreach ($extra_details as $key => $value): ?>
                <?php if (trim((string)$value) !== ''): ?>
                  <div class="summary-row">
                    <strong><?= adm_e(ucwords(str_replace('_', ' ', $key))) ?></strong>
                    <span><?= adm_e($value) ?></span>
                  </div>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </section>

      <section class="detail-panel" id="attachments">
        <h2>Uploaded Attachments</h2>
        <?php if ($attachments): ?>
          <div class="attachment-list" style="margin-top: 12px;">
            <?php foreach ($attachments as $attachment): ?>
              <?php $href = '../' . ltrim(str_replace('\\', '/', (string)$attachment['file_path']), '/'); ?>
              <a class="attachment-item" href="<?= adm_e($href) ?>" target="_blank" rel="noopener">
                <span class="stat-card__icon"><i class="fa-solid fa-file-arrow-down"></i></span>
                <span class="identity">
                  <strong><?= adm_e($attachment['file_name']) ?></strong>
                  <small><?= adm_e(adm_file_size($attachment['file_size'])) ?> uploaded <?= adm_e(adm_date($attachment['uploaded_at'])) ?></small>
                </span>
              </a>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="empty-state">
            <i class="fa-solid fa-paperclip"></i>
            <strong>No attachments</strong>
            <span>Resident-uploaded documents will be listed here.</span>
          </div>
        <?php endif; ?>
      </section>
    </div>

    <aside>
      <section class="detail-panel">
        <h2>Processing Timeline</h2>
        <ol class="timeline" style="margin-top: 14px;">
          <li class="<?= adm_e(admin_request_step_class(1, $status)) ?>">
            <span><i class="fa-solid fa-paper-plane"></i></span>
            <div><strong>Submitted</strong><small><?= adm_e(adm_datetime($request['created_at'])) ?></small></div>
          </li>
          <li class="<?= adm_e(admin_request_step_class(2, $status)) ?>">
            <span><i class="fa-solid fa-spinner"></i></span>
            <div><strong>Processing</strong><small><?= adm_e($request['processed_at'] ? adm_datetime($request['processed_at']) : 'Awaiting Secretary action') ?></small></div>
          </li>
          <li class="<?= adm_e(admin_request_step_class(3, $status)) ?>">
            <span><i class="fa-solid fa-stamp"></i></span>
            <div><strong>For Approval</strong><small><?= $needs_approval ? 'Barangay Captain review' : 'Not required for this document' ?></small></div>
          </li>
          <li class="<?= adm_e(admin_request_step_class(4, $status)) ?>">
            <span><i class="fa-solid fa-circle-check"></i></span>
            <div><strong>Approved</strong><small><?= adm_e($request['approved_at'] ? adm_datetime($request['approved_at']) : 'Not approved yet') ?></small></div>
          </li>
          <li class="<?= adm_e(admin_request_step_class(5, $status)) ?>">
            <span><i class="fa-solid fa-box-open"></i></span>
            <div><strong>Released</strong><small><?= adm_e($request['released_at'] ? adm_datetime($request['released_at']) : 'Not released yet') ?></small></div>
          </li>
        </ol>
      </section>

      <section class="detail-panel">
        <h2>Resident</h2>
        <dl class="definition-list" style="grid-template-columns: 1fr;">
          <div><dt>Name</dt><dd><?= adm_e($resident_name) ?></dd></div>
          <div><dt>Email</dt><dd><?= adm_e($request['resident_email'] ?: 'Not set') ?></dd></div>
          <div><dt>Mobile</dt><dd><?= adm_e($request['contact_number'] ?: 'Not set') ?></dd></div>
          <div><dt>Address</dt><dd><?= adm_e($address ?: 'Not set') ?></dd></div>
        </dl>
      </section>

      <?php if ($is_captain && $status === 'for_approval'): ?>
        <section class="detail-panel no-print">
          <h2>Captain Actions</h2>
          <div class="action-row" style="margin-top: 12px;">
            <form method="post" data-disable-on-submit>
              <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
              <input type="hidden" name="action" value="captain_approve_request">
              <input type="hidden" name="request_id" value="<?= adm_e($request_id) ?>">
              <button class="btn btn--success" type="submit"><i class="fa-solid fa-signature"></i> Approve &amp; Sign</button>
            </form>
          </div>

          <details class="inline-reject" style="margin-top: 12px;">
            <summary class="btn btn--danger"><i class="fa-solid fa-reply"></i> Send back to Secretary</summary>
            <form class="inline-reject__body" method="post" data-disable-on-submit>
              <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
              <input type="hidden" name="action" value="captain_return_request">
              <input type="hidden" name="request_id" value="<?= adm_e($request_id) ?>">
              <div class="form-field">
                <label for="sendBackReason">Reason</label>
                <textarea id="sendBackReason" name="reason" required></textarea>
              </div>
              <button class="btn btn--danger" type="submit">Return for processing</button>
            </form>
          </details>
        </section>
      <?php endif; ?>

      <?php if (!$is_captain): ?>
      <section class="detail-panel no-print">
        <h2>Secretary Actions</h2>
        <div class="action-row" style="margin-top: 12px;">
          <?php if ($status === 'pending'): ?>
            <form method="post" data-disable-on-submit>
              <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
              <input type="hidden" name="action" value="process_request">
              <input type="hidden" name="request_id" value="<?= adm_e($request_id) ?>">
              <button class="btn btn--primary" type="submit"><i class="fa-solid fa-play"></i> Process</button>
            </form>
          <?php endif; ?>

          <?php if ($status === 'processing' && !$needs_approval): ?>
            <form method="post" data-disable-on-submit>
              <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
              <input type="hidden" name="action" value="approve_issue_request">
              <input type="hidden" name="request_id" value="<?= adm_e($request_id) ?>">
              <button class="btn btn--success" type="submit"><i class="fa-solid fa-circle-check"></i> Approve &amp; Issue</button>
            </form>
          <?php endif; ?>

          <?php if ($status === 'processing' && $needs_approval): ?>
            <form method="post" data-disable-on-submit>
              <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
              <input type="hidden" name="action" value="send_for_approval">
              <input type="hidden" name="request_id" value="<?= adm_e($request_id) ?>">
              <button class="btn btn--primary" type="submit"><i class="fa-solid fa-stamp"></i> Send for Approval</button>
            </form>
          <?php endif; ?>

          <?php if ($status === 'approved'): ?>
            <form method="post" data-disable-on-submit>
              <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
              <input type="hidden" name="action" value="release_request">
              <input type="hidden" name="request_id" value="<?= adm_e($request_id) ?>">
              <button class="btn btn--success" type="submit"><i class="fa-solid fa-box-open"></i> Mark as Released</button>
            </form>
          <?php endif; ?>
        </div>

        <?php if (in_array($status, ['pending', 'processing'], true)): ?>
          <details class="inline-reject" style="margin-top: 12px;">
            <summary class="btn btn--danger"><i class="fa-solid fa-ban"></i> Reject request</summary>
            <form class="inline-reject__body" method="post" data-disable-on-submit>
              <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
              <input type="hidden" name="action" value="reject_request">
              <input type="hidden" name="request_id" value="<?= adm_e($request_id) ?>">
              <div class="form-field">
                <label for="rejectReason">Rejection reason</label>
                <textarea id="rejectReason" name="reason" required></textarea>
              </div>
              <button class="btn btn--danger" type="submit">Reject and notify resident</button>
            </form>
          </details>
        <?php endif; ?>
      </section>
      <?php endif; ?>
    </aside>
  </section>
<?php endif; ?>

<?php adm_page_end(); ?>
