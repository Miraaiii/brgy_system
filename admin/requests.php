<?php
require_once __DIR__ . '/includes/admin_layout.php';

$user = adm_require_secretary($conn);
$csrf = adm_action_token();
$statuses = ['all', 'pending', 'processing', 'for_approval', 'approved', 'released', 'rejected'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!adm_verify_action_token($_POST['csrf_token'] ?? '')) {
        adm_set_flash('danger', 'Your session expired. Please refresh and try again.');
    } else {
        [$ok, $message] = adm_handle_request_action(
            $conn,
            (string)($_POST['action'] ?? ''),
            (int)($_POST['request_id'] ?? 0),
            (int)$user['id'],
            (string)($_POST['reason'] ?? '')
        );
        adm_set_flash($ok ? 'success' : 'danger', $message);
    }

    $qs = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
    header('Location: requests.php' . $qs);
    exit();
}

$filter = strtolower(trim((string)($_GET['filter'] ?? 'all')));
if (!in_array($filter, $statuses, true)) {
    $filter = 'all';
}
$q = trim((string)($_GET['q'] ?? ''));
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));

$counts = array_fill_keys($statuses, 0);
if (adm_table_exists($conn, 'document_requests')) {
    $counts['all'] = adm_scalar($conn, 'SELECT COUNT(*) FROM document_requests');
    foreach (array_slice($statuses, 1) as $status) {
        $counts[$status] = adm_scalar($conn, 'SELECT COUNT(*) FROM document_requests WHERE status = ?', 's', [$status]);
    }
}

$requests = [];
if (adm_table_exists($conn, 'document_requests') && adm_table_exists($conn, 'residents') && adm_table_exists($conn, 'document_types')) {
    $where = [];
    $types = '';
    $params = [];

    if ($filter !== 'all') {
        $where[] = 'dr.status = ?';
        $types .= 's';
        $params[] = $filter;
    }

    if ($q !== '') {
        $like = '%' . $q . '%';
        $where[] = "(dr.reference_no LIKE ? OR CONCAT(r.first_name, ' ', r.last_name) LIKE ? OR r.email LIKE ?)";
        $types .= 'sss';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    if ($from !== '') {
        $where[] = 'DATE(dr.created_at) >= ?';
        $types .= 's';
        $params[] = $from;
    }

    if ($to !== '') {
        $where[] = 'DATE(dr.created_at) <= ?';
        $types .= 's';
        $params[] = $to;
    }

    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $requests = adm_fetch_all(
        $conn,
        "SELECT dr.id, dr.reference_no, dr.purpose, dr.status, dr.remarks,
                dr.created_at, dr.processed_at, dr.approved_at, dr.released_at,
                dt.name AS document_name, dt.requires_approval,
                CONCAT(r.first_name, ' ', r.last_name) AS resident_name,
                r.email AS resident_email,
                processor.fullname AS processed_by_name
         FROM document_requests dr
         INNER JOIN residents r ON r.id = dr.resident_id
         INNER JOIN document_types dt ON dt.id = dr.doc_type_id
         LEFT JOIN users processor ON processor.id = dr.processed_by
         {$where_sql}
         ORDER BY FIELD(dr.status, 'pending', 'processing', 'for_approval', 'approved', 'released', 'rejected', 'cancelled'),
                  dr.created_at ASC
         LIMIT 200",
        $types,
        $params
    );
}

$actions = '<a class="btn btn--primary" href="issued.php"><i class="fa-solid fa-file-circle-check"></i> Issued log</a>';

adm_page_start('Document Requests', 'requests', $user, 'requests-page');
adm_page_header('Secretary inbox', 'Document Request Inbox', 'Review, process, approve, reject, print, and release resident document requests.', $actions);
?>

<nav class="tabs" aria-label="Request status filters">
  <?php foreach ($statuses as $status): ?>
    <a class="tab-link <?= $filter === $status ? 'is-active' : '' ?>" href="requests.php?filter=<?= adm_e($status) ?>">
      <?= adm_e($status === 'all' ? 'All' : adm_status_label($status)) ?>
      <strong><?= adm_e($counts[$status] ?? 0) ?></strong>
    </a>
  <?php endforeach; ?>
</nav>

<form class="filter-panel" method="get">
  <input type="hidden" name="filter" value="<?= adm_e($filter) ?>">
  <div class="filter-grid">
    <div class="form-field">
      <label for="q">Search</label>
      <input id="q" name="q" type="search" value="<?= adm_e($q) ?>" placeholder="Reference no., resident name, or email" data-table-search="#requestsTable">
    </div>
    <div class="form-field">
      <label for="from">From date</label>
      <input id="from" name="from" type="date" value="<?= adm_e($from) ?>">
    </div>
    <div class="form-field">
      <label for="to">To date</label>
      <input id="to" name="to" type="date" value="<?= adm_e($to) ?>">
    </div>
    <div class="form-field">
      <label for="statusJump">Status</label>
      <select id="statusJump" name="filter">
        <?php foreach ($statuses as $status): ?>
          <option value="<?= adm_e($status) ?>" <?= $filter === $status ? 'selected' : '' ?>><?= adm_e($status === 'all' ? 'All' : adm_status_label($status)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn--primary" type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
    <a class="btn" href="requests.php"><i class="fa-solid fa-rotate-left"></i> Reset</a>
  </div>
</form>

<section class="panel">
  <div class="panel__header">
    <div>
      <h2>Requests</h2>
      <p>Rows older than three days are highlighted until they reach a terminal status.</p>
    </div>
  </div>

  <?php if ($requests): ?>
    <div class="table-wrap">
      <table class="data-table" id="requestsTable">
        <thead>
          <tr>
            <th>Ref No.</th>
            <th>Resident</th>
            <th>Document Type</th>
            <th>Date Submitted</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($requests as $request): ?>
            <?php
              $status = strtolower((string)$request['status']);
              $overdue = adm_request_is_overdue($request['created_at'], $status);
              $needs_approval = (int)$request['requires_approval'] === 1;
            ?>
            <tr class="<?= $overdue ? 'is-overdue' : '' ?>">
              <td>
                <strong><?= adm_e($request['reference_no']) ?></strong>
                <?php if ($overdue): ?><small>Overdue</small><?php endif; ?>
              </td>
              <td>
                <strong><?= adm_e($request['resident_name']) ?></strong>
                <small><?= adm_e($request['resident_email']) ?></small>
              </td>
              <td><?= adm_e($request['document_name']) ?></td>
              <td><?= adm_e(adm_date($request['created_at'])) ?></td>
              <td><span class="status-badge status-badge--<?= adm_e(adm_status_class($status)) ?>"><?= adm_e(adm_status_label($status)) ?></span></td>
              <td>
                <div class="table-actions">
                  <?php if ($status === 'pending'): ?>
                    <form method="post" data-disable-on-submit>
                      <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
                      <input type="hidden" name="action" value="process_request">
                      <input type="hidden" name="request_id" value="<?= adm_e($request['id']) ?>">
                      <button class="btn btn--primary btn--small" type="submit"><i class="fa-solid fa-play"></i> Process</button>
                    </form>
                  <?php endif; ?>

                  <?php if ($status === 'processing' && !$needs_approval): ?>
                    <form method="post" data-disable-on-submit>
                      <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
                      <input type="hidden" name="action" value="approve_issue_request">
                      <input type="hidden" name="request_id" value="<?= adm_e($request['id']) ?>">
                      <button class="btn btn--success btn--small" type="submit"><i class="fa-solid fa-circle-check"></i> Approve &amp; Issue</button>
                    </form>
                  <?php endif; ?>

                  <?php if ($status === 'processing' && $needs_approval): ?>
                    <form method="post" data-disable-on-submit>
                      <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
                      <input type="hidden" name="action" value="send_for_approval">
                      <input type="hidden" name="request_id" value="<?= adm_e($request['id']) ?>">
                      <button class="btn btn--primary btn--small" type="submit"><i class="fa-solid fa-stamp"></i> Send for Approval</button>
                    </form>
                  <?php endif; ?>

                  <?php if (in_array($status, ['pending', 'processing'], true)): ?>
                    <details class="inline-reject">
                      <summary class="btn btn--danger btn--small"><i class="fa-solid fa-ban"></i> Reject</summary>
                      <form class="inline-reject__body" method="post" data-disable-on-submit>
                        <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
                        <input type="hidden" name="action" value="reject_request">
                        <input type="hidden" name="request_id" value="<?= adm_e($request['id']) ?>">
                        <div class="form-field">
                          <label for="reason-<?= adm_e($request['id']) ?>">Reason</label>
                          <input id="reason-<?= adm_e($request['id']) ?>" name="reason" type="text" required>
                        </div>
                        <button class="btn btn--danger btn--small" type="submit">Reject request</button>
                      </form>
                    </details>
                  <?php endif; ?>

                  <?php if ($status === 'approved'): ?>
                    <a class="btn btn--small" href="print-document.php?id=<?= adm_e($request['id']) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-print"></i> Print</a>
                    <form method="post" data-disable-on-submit>
                      <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
                      <input type="hidden" name="action" value="release_request">
                      <input type="hidden" name="request_id" value="<?= adm_e($request['id']) ?>">
                      <button class="btn btn--success btn--small" type="submit"><i class="fa-solid fa-box-open"></i> Mark Released</button>
                    </form>
                  <?php elseif ($status === 'released'): ?>
                    <a class="btn btn--small" href="print-document.php?id=<?= adm_e($request['id']) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-print"></i> Print</a>
                  <?php endif; ?>

                  <a class="btn btn--small" href="request-detail.php?id=<?= adm_e($request['id']) ?>"><i class="fa-solid fa-eye"></i> View</a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="empty-state">
      <i class="fa-solid fa-folder-open"></i>
      <strong>No requests found</strong>
      <span>Try clearing filters or wait for residents to submit new document requests.</span>
    </div>
  <?php endif; ?>
</section>

<?php adm_page_end(); ?>
