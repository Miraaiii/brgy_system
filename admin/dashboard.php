<?php
require_once __DIR__ . '/includes/admin_layout.php';

$user = adm_require_secretary($conn);
$csrf = adm_action_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!adm_verify_action_token($_POST['csrf_token'] ?? '')) {
        adm_set_flash('danger', 'Your session expired. Please refresh and try again.');
    } else {
        $action = (string)($_POST['action'] ?? '');
        if (in_array($action, ['process_request', 'approve_issue_request', 'send_for_approval', 'reject_request', 'release_request'], true)) {
            [$ok, $message] = adm_handle_request_action(
                $conn,
                $action,
                (int)($_POST['request_id'] ?? 0),
                (int)$user['id'],
                (string)($_POST['reason'] ?? '')
            );
            adm_set_flash($ok ? 'success' : 'danger', $message);
        } elseif ($action === 'approve_registration') {
            [$ok, $message] = adm_approve_resident_registration($conn, (int)($_POST['registration_id'] ?? 0), (int)$user['id']);
            adm_set_flash($ok ? 'success' : 'danger', $message);
        } elseif ($action === 'reject_registration') {
            [$ok, $message] = adm_reject_resident_registration(
                $conn,
                (int)($_POST['registration_id'] ?? 0),
                (int)$user['id'],
                (string)($_POST['reason'] ?? '')
            );
            adm_set_flash($ok ? 'success' : 'danger', $message);
        }
    }

    header('Location: dashboard.php');
    exit();
}

$pending_requests = adm_table_exists($conn, 'document_requests')
    ? adm_scalar($conn, "SELECT COUNT(*) FROM document_requests WHERE status = 'pending'")
    : 0;
$processing_requests = adm_table_exists($conn, 'document_requests')
    ? adm_scalar($conn, "SELECT COUNT(*) FROM document_requests WHERE status = 'processing'")
    : 0;
$approval_requests = adm_table_exists($conn, 'document_requests')
    ? adm_scalar($conn, "SELECT COUNT(*) FROM document_requests WHERE status = 'for_approval'")
    : 0;
$pending_verifications = adm_table_exists($conn, 'pending_resident_registrations')
    ? adm_scalar($conn, "SELECT COUNT(*) FROM pending_resident_registrations WHERE status = 'pending'")
    : 0;
$open_blotters = adm_table_exists($conn, 'blotter_cases')
    ? adm_scalar($conn, "SELECT COUNT(*) FROM blotter_cases WHERE status IN ('open', 'under_mediation')")
    : 0;

$request_queue = [];
if (adm_table_exists($conn, 'document_requests') && adm_table_exists($conn, 'residents') && adm_table_exists($conn, 'document_types')) {
    $request_queue = adm_fetch_all(
        $conn,
        "SELECT dr.id, dr.reference_no, dr.status, dr.created_at,
                dt.name AS document_name,
                CONCAT(r.first_name, ' ', r.last_name) AS resident_name
         FROM document_requests dr
         INNER JOIN residents r ON r.id = dr.resident_id
         INNER JOIN document_types dt ON dt.id = dr.doc_type_id
         WHERE dr.status IN ('pending', 'processing', 'for_approval')
         ORDER BY FIELD(dr.status, 'pending', 'processing', 'for_approval'), dr.created_at ASC
         LIMIT 8"
    );
}

$pending_registrations = [];
if (adm_table_exists($conn, 'pending_resident_registrations')) {
    $pending_registrations = adm_fetch_all(
        $conn,
        "SELECT id, first_name, middle_name, last_name, email, mobile_number,
                valid_id_path, valid_id_original_name, created_at
         FROM pending_resident_registrations
         WHERE status = 'pending'
         ORDER BY created_at ASC
         LIMIT 5"
    );
}

$recent_blotters = [];
if (adm_table_exists($conn, 'blotter_cases')) {
    $recent_blotters = adm_fetch_all(
        $conn,
        'SELECT id, case_number, incident_type, incident_date, status, created_at
         FROM blotter_cases
         ORDER BY created_at DESC
         LIMIT 5'
    );
}

$activity_logs = [];
if (adm_table_exists($conn, 'audit_logs')) {
    $activity_logs = adm_fetch_all(
        $conn,
        'SELECT al.action, al.table_name, al.record_id, al.created_at, u.fullname
         FROM audit_logs al
         LEFT JOIN users u ON u.id = al.user_id
         ORDER BY al.created_at DESC
         LIMIT 10'
    );
}

$hour = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$display_name = trim((string)($user['fullname'] ?? '')) ?: 'Secretary';

adm_page_start('Secretary Dashboard', 'dashboard', $user, 'dashboard-page');
?>

<section class="welcome-panel">
  <div>
    <h1><?= adm_e($greeting) ?>, <?= adm_e(adm_first_name($display_name)) ?>!</h1>
    <p><?= adm_e(date('l, F j, Y')) ?> - Barangay Sta. Rosa 1 operations overview.</p>
  </div>
  <span class="role-badge"><i class="fa-solid fa-user-tie" aria-hidden="true"></i> Barangay Secretary</span>
</section>

<section class="stat-grid" aria-label="Secretary dashboard statistics">
  <a class="stat-card" href="requests.php?filter=pending">
    <span class="stat-card__icon"><i class="fa-solid fa-inbox" aria-hidden="true"></i></span>
    <span>
      <strong><?= adm_e($pending_requests) ?></strong>
      <span>Pending Requests</span>
    </span>
  </a>
  <a class="stat-card stat-card--teal" href="requests.php?filter=processing">
    <span class="stat-card__icon"><i class="fa-solid fa-spinner" aria-hidden="true"></i></span>
    <span>
      <strong><?= adm_e($processing_requests) ?></strong>
      <span>Processing</span>
    </span>
  </a>
  <a class="stat-card" href="requests.php?filter=for_approval">
    <span class="stat-card__icon"><i class="fa-solid fa-stamp" aria-hidden="true"></i></span>
    <span>
      <strong><?= adm_e($approval_requests) ?></strong>
      <span>For Captain Approval</span>
    </span>
  </a>
  <a class="stat-card stat-card--amber" href="residents.php?filter=pending">
    <span class="stat-card__icon"><i class="fa-solid fa-user-check" aria-hidden="true"></i></span>
    <span>
      <strong><?= adm_e($pending_verifications) ?></strong>
      <span>Pending Verifications</span>
    </span>
  </a>
  <a class="stat-card stat-card--danger" href="blotter.php?filter=open">
    <span class="stat-card__icon"><i class="fa-solid fa-scale-balanced" aria-hidden="true"></i></span>
    <span>
      <strong><?= adm_e($open_blotters) ?></strong>
      <span>Open Blotter Cases</span>
    </span>
  </a>
</section>

<section class="dashboard-grid">
  <div class="dashboard-stack">
    <section class="panel">
      <div class="panel__header">
        <div>
          <h2>Pending Requests Queue</h2>
          <p>Top requests waiting for review or follow-through.</p>
        </div>
        <a class="btn btn--small" href="requests.php">View all requests</a>
      </div>
      <?php if ($request_queue): ?>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>Ref No.</th>
                <th>Resident</th>
                <th>Document</th>
                <th>Submitted</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($request_queue as $request): ?>
                <tr class="<?= adm_request_is_overdue($request['created_at'], $request['status']) ? 'is-overdue' : '' ?>">
                  <td><strong><?= adm_e($request['reference_no']) ?></strong></td>
                  <td><?= adm_e($request['resident_name']) ?></td>
                  <td><?= adm_e($request['document_name']) ?></td>
                  <td>
                    <?= adm_e(adm_date($request['created_at'])) ?>
                    <?php if (adm_request_is_overdue($request['created_at'], $request['status'])): ?>
                      <small>Overdue</small>
                    <?php endif; ?>
                  </td>
                  <td><span class="status-badge status-badge--<?= adm_e(adm_status_class($request['status'])) ?>"><?= adm_e(adm_status_label($request['status'])) ?></span></td>
                  <td>
                    <div class="table-actions">
                      <?php if ($request['status'] === 'pending'): ?>
                        <form method="post" data-disable-on-submit>
                          <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
                          <input type="hidden" name="action" value="process_request">
                          <input type="hidden" name="request_id" value="<?= adm_e($request['id']) ?>">
                          <button class="btn btn--primary btn--small" type="submit"><i class="fa-solid fa-play"></i> Process</button>
                        </form>
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
          <i class="fa-solid fa-inbox"></i>
          <strong>No active document requests</strong>
          <span>New requests submitted by residents will appear here.</span>
        </div>
      <?php endif; ?>
    </section>

    <section class="panel">
      <div class="panel__header">
        <div>
          <h2>Pending Resident Verifications</h2>
          <p>Approve complete accounts or reject with a clear reason.</p>
        </div>
        <a class="btn btn--small" href="residents.php?filter=pending">View all pending</a>
      </div>
      <div class="panel__body">
        <?php if ($pending_registrations): ?>
          <div class="resident-card-list">
            <?php foreach ($pending_registrations as $registration): ?>
              <?php
                $name = trim($registration['first_name'] . ' ' . ($registration['middle_name'] ? $registration['middle_name'] . ' ' : '') . $registration['last_name']);
                $id_href = '../' . ltrim(str_replace('\\', '/', (string)$registration['valid_id_path']), '/');
              ?>
              <article class="resident-card">
                <a class="id-preview" href="<?= adm_e($id_href) ?>" target="_blank" rel="noopener" title="View uploaded ID">
                  <i class="fa-solid fa-id-card" aria-hidden="true"></i>
                </a>
                <div class="resident-card__main">
                  <strong><?= adm_e($name) ?></strong>
                  <small><?= adm_e($registration['email']) ?> - registered <?= adm_e(adm_date($registration['created_at'])) ?></small>
                </div>
                <div class="resident-card__actions">
                  <form method="post" data-disable-on-submit>
                    <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
                    <input type="hidden" name="action" value="approve_registration">
                    <input type="hidden" name="registration_id" value="<?= adm_e($registration['id']) ?>">
                    <button class="btn btn--success btn--small" type="submit"><i class="fa-solid fa-check"></i> Approve</button>
                  </form>
                  <details class="inline-reject">
                    <summary class="btn btn--danger btn--small"><i class="fa-solid fa-xmark"></i> Reject</summary>
                    <form class="inline-reject__body" method="post" data-disable-on-submit>
                      <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
                      <input type="hidden" name="action" value="reject_registration">
                      <input type="hidden" name="registration_id" value="<?= adm_e($registration['id']) ?>">
                      <div class="form-field">
                        <label for="reject-<?= adm_e($registration['id']) ?>">Reason</label>
                        <input id="reject-<?= adm_e($registration['id']) ?>" name="reason" type="text" required>
                      </div>
                      <button class="btn btn--danger btn--small" type="submit">Send rejection</button>
                    </form>
                  </details>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="empty-state">
            <i class="fa-solid fa-user-check"></i>
            <strong>No pending registrations</strong>
            <span>Resident account applications will land here for Secretary review.</span>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </div>

  <div class="dashboard-stack">
    <section class="panel">
      <div class="panel__header">
        <div>
          <h2>Recent Blotter Cases</h2>
          <p>Latest cases recorded in the barangay log.</p>
        </div>
        <a class="btn btn--small" href="blotter.php">View all cases</a>
      </div>
      <div class="panel__body">
        <?php if ($recent_blotters): ?>
          <div class="activity-list">
            <?php foreach ($recent_blotters as $case): ?>
              <a class="activity-item" href="blotter-detail.php?id=<?= adm_e($case['id']) ?>">
                <span class="stat-card__icon"><i class="fa-solid fa-scale-balanced"></i></span>
                <span class="activity-item__body">
                  <strong><?= adm_e($case['case_number']) ?> - <?= adm_e($case['incident_type']) ?></strong>
                  <small><?= adm_e(adm_date($case['incident_date'])) ?></small>
                </span>
                <span class="status-badge status-badge--<?= adm_e(adm_status_class($case['status'])) ?>"><?= adm_e(adm_status_label($case['status'])) ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="empty-state">
            <i class="fa-solid fa-folder-open"></i>
            <strong>No blotter cases yet</strong>
            <span>Cases recorded by the barangay office will appear here.</span>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <section class="panel">
      <div class="panel__header">
        <div>
          <h2>Recent Activity</h2>
          <p>Last 10 system actions.</p>
        </div>
      </div>
      <div class="panel__body">
        <?php if ($activity_logs): ?>
          <div class="activity-list">
            <?php foreach ($activity_logs as $activity): ?>
              <div class="activity-item">
                <span class="stat-card__icon"><i class="fa-solid fa-clock-rotate-left"></i></span>
                <span class="activity-item__body">
                  <strong><?= adm_e($activity['action']) ?></strong>
                  <small><?= adm_e($activity['fullname'] ?: 'System') ?> - <?= adm_e(adm_relative_time($activity['created_at'])) ?></small>
                </span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="empty-state">
            <i class="fa-solid fa-list-check"></i>
            <strong>No activity recorded</strong>
            <span>Secretary actions will be logged here.</span>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </div>
</section>

<?php adm_page_end(); ?>
