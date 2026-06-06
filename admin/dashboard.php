<?php
require_once __DIR__ . '/includes/admin_layout.php';

$user = adm_require_admin($conn);
$csrf = adm_action_token();
$role = strtolower(trim((string)($user['role'] ?? '')));
$is_captain = $role === 'captain';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!adm_verify_action_token($_POST['csrf_token'] ?? '')) {
        adm_set_flash('danger', 'Your session expired. Please refresh and try again.');
    } else {
        $action = (string)($_POST['action'] ?? '');
        if (in_array($action, ['process_request', 'approve_issue_request', 'send_for_approval', 'reject_request', 'release_request', 'captain_approve_request', 'captain_return_request'], true)) {
            [$ok, $message] = adm_handle_request_action(
                $conn,
                $action,
                (int)($_POST['request_id'] ?? 0),
                (int)$user['id'],
                (string)($_POST['reason'] ?? ''),
                $role
            );
            adm_set_flash($ok ? 'success' : 'danger', $message);
        } elseif (in_array($action, ['approve_expenditure', 'reject_expenditure'], true)) {
            if (!$is_captain) {
                adm_set_flash('danger', 'Only the Punong Barangay can approve expenditures.');
            } elseif ($action === 'approve_expenditure') {
                [$ok, $message] = adm_approve_expenditure($conn, (int)($_POST['expenditure_id'] ?? 0), (int)$user['id']);
                adm_set_flash($ok ? 'success' : 'danger', $message);
            } else {
                [$ok, $message] = adm_reject_expenditure(
                    $conn,
                    (int)($_POST['expenditure_id'] ?? 0),
                    (int)$user['id'],
                    (string)($_POST['reason'] ?? '')
                );
                adm_set_flash($ok ? 'success' : 'danger', $message);
            }
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

$captain_term = null;
$approval_queue = [];
$pending_expenditures = [];
$mediation_cases = [];
$month_income = 0.0;
$month_spending = 0.0;
$annual_budget = 0.0;
$ytd_spending = 0.0;
$budget_utilized = 0;
$total_residents = 0;
$issued_ytd = 0;
$published_announcements = 0;
$active_admins = 0;

if ($is_captain) {
    if (adm_table_exists($conn, 'officials')) {
        $captain_term = adm_fetch_one(
            $conn,
            'SELECT term_start, term_end FROM officials WHERE user_id = ? AND is_active = 1 ORDER BY term_end DESC LIMIT 1',
            'i',
            [(int)$user['id']]
        );
    }

    if (adm_table_exists($conn, 'document_requests') && adm_table_exists($conn, 'residents') && adm_table_exists($conn, 'document_types')) {
        $approval_queue = adm_fetch_all(
            $conn,
            "SELECT dr.id, dr.reference_no, dr.status, dr.updated_at, dr.created_at,
                    dt.name AS document_name,
                    CONCAT(r.first_name, ' ', r.last_name) AS resident_name,
                    processor.fullname AS processed_by_name
             FROM document_requests dr
             INNER JOIN residents r ON r.id = dr.resident_id
             INNER JOIN document_types dt ON dt.id = dr.doc_type_id
             LEFT JOIN users processor ON processor.id = dr.processed_by
             WHERE dr.status = 'for_approval'
             ORDER BY dr.updated_at ASC, dr.created_at ASC
             LIMIT 8"
        );
    }

    if (adm_table_exists($conn, 'expenditures')) {
        adm_ensure_expenditure_approval_columns($conn);
        $pending_expenditures = adm_fetch_all(
            $conn,
            "SELECT e.id, e.category, e.description, e.amount, e.payee, e.disbursement_date,
                    e.created_at, recorder.fullname AS recorded_by_name
             FROM expenditures e
             LEFT JOIN users recorder ON recorder.id = e.recorded_by
             WHERE e.approval_status = 'pending'
             ORDER BY e.amount DESC, e.created_at ASC
             LIMIT 5"
        );
    }

    if (adm_table_exists($conn, 'blotter_cases')) {
        $mediation_sql = adm_table_exists($conn, 'blotter_hearings')
            ? "SELECT *
               FROM (
                 SELECT bc.id, bc.case_number, bc.incident_type, bc.status, bc.created_at,
                        MIN(bh.scheduled_at) AS next_hearing_at
                 FROM blotter_cases bc
                 LEFT JOIN blotter_hearings bh ON bh.case_id = bc.id AND bh.status = 'scheduled'
                 WHERE bc.status = 'under_mediation'
                 GROUP BY bc.id, bc.case_number, bc.incident_type, bc.status, bc.created_at
               ) AS mediation
               ORDER BY mediation.next_hearing_at IS NULL, mediation.next_hearing_at ASC, mediation.created_at DESC
               LIMIT 5"
            : "SELECT bc.id, bc.case_number, bc.incident_type, bc.status, bc.created_at, NULL AS next_hearing_at
               FROM blotter_cases bc
               WHERE bc.status = 'under_mediation'
               ORDER BY bc.created_at DESC
               LIMIT 5";
        $mediation_cases = adm_fetch_all($conn, $mediation_sql);
    }

    $month_income = adm_table_exists($conn, 'collections')
        ? (float)(adm_fetch_one($conn, "SELECT COALESCE(SUM(amount), 0) AS total FROM collections WHERE DATE_FORMAT(collected_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')")['total'] ?? 0)
        : 0.0;
    $spending_where = adm_table_exists($conn, 'expenditures') && adm_column_exists($conn, 'expenditures', 'approval_status')
        ? "DATE_FORMAT(disbursement_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m') AND approval_status <> 'rejected'"
        : "DATE_FORMAT(disbursement_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
    $month_spending = adm_table_exists($conn, 'expenditures')
        ? (float)(adm_fetch_one($conn, "SELECT COALESCE(SUM(amount), 0) AS total FROM expenditures WHERE {$spending_where}")['total'] ?? 0)
        : 0.0;
    $annual_budget = adm_table_exists($conn, 'budget_items')
        ? (float)(adm_fetch_one($conn, 'SELECT COALESCE(SUM(allocated_amount), 0) AS total FROM budget_items WHERE fiscal_year = YEAR(CURDATE())')['total'] ?? 0)
        : 0.0;
    $ytd_where = adm_table_exists($conn, 'expenditures') && adm_column_exists($conn, 'expenditures', 'approval_status')
        ? "YEAR(disbursement_date) = YEAR(CURDATE()) AND approval_status <> 'rejected'"
        : "YEAR(disbursement_date) = YEAR(CURDATE())";
    $ytd_spending = adm_table_exists($conn, 'expenditures')
        ? (float)(adm_fetch_one($conn, "SELECT COALESCE(SUM(amount), 0) AS total FROM expenditures WHERE {$ytd_where}")['total'] ?? 0)
        : 0.0;
    $budget_utilized = $annual_budget > 0 ? min(100, (int)round(($ytd_spending / $annual_budget) * 100)) : 0;

    $total_residents = adm_table_exists($conn, 'residents')
        ? adm_scalar($conn, "SELECT COUNT(*) FROM residents WHERE status = 'active'")
        : 0;
    $issued_ytd = adm_table_exists($conn, 'issued_documents')
        ? adm_scalar($conn, 'SELECT COUNT(*) FROM issued_documents WHERE YEAR(issued_at) = YEAR(CURDATE())')
        : 0;
    $published_announcements = adm_table_exists($conn, 'announcements')
        ? adm_scalar($conn, 'SELECT COUNT(*) FROM announcements WHERE is_published = 1')
        : 0;
    $active_admins = adm_table_exists($conn, 'users')
        ? adm_scalar($conn, "SELECT COUNT(*) FROM users WHERE role <> 'resident' AND status = 'active'")
        : 0;
}

$hour = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$display_name = trim((string)($user['fullname'] ?? '')) ?: adm_role_label($role);

$dashboard_title = $is_captain ? 'Captain Dashboard' : adm_role_label($role) . ' Dashboard';
adm_page_start($dashboard_title, 'dashboard', $user, 'dashboard-page');
?>

<?php if ($is_captain): ?>
<section class="welcome-panel">
  <div>
    <h1><?= adm_e($greeting) ?>, Hon. <?= adm_e(adm_first_name($display_name)) ?>!</h1>
    <p><?= adm_e(date('l, F j, Y')) ?> - Barangay Sta. Rosa 1, Noveleta, Cavite.</p>
    <?php if ($captain_term): ?>
      <p>Current term: <?= adm_e(date('Y', strtotime($captain_term['term_start']))) ?>-<?= adm_e(date('Y', strtotime($captain_term['term_end']))) ?></p>
    <?php endif; ?>
  </div>
  <span class="role-badge role-badge--gold"><i class="fa-solid fa-crown" aria-hidden="true"></i> Punong Barangay</span>
</section>

<section class="stat-grid stat-grid--seven" aria-label="Captain dashboard statistics">
  <a class="stat-card stat-card--gold" href="requests.php?filter=for_approval">
    <span class="stat-card__icon"><i class="fa-solid fa-stamp" aria-hidden="true"></i></span>
    <span><strong><?= adm_e($approval_requests) ?></strong><span>Awaiting Approval</span></span>
  </a>
  <a class="stat-card" href="requests.php">
    <span class="stat-card__icon"><i class="fa-solid fa-inbox" aria-hidden="true"></i></span>
    <span><strong><?= adm_e($pending_requests + $processing_requests) ?></strong><span>Pending Requests</span></span>
  </a>
  <a class="stat-card stat-card--amber" href="residents.php?filter=pending">
    <span class="stat-card__icon"><i class="fa-solid fa-user-check" aria-hidden="true"></i></span>
    <span><strong><?= adm_e($pending_verifications) ?></strong><span>Pending Verifications</span></span>
  </a>
  <a class="stat-card stat-card--danger" href="blotter.php?filter=open">
    <span class="stat-card__icon"><i class="fa-solid fa-scale-balanced" aria-hidden="true"></i></span>
    <span><strong><?= adm_e($open_blotters) ?></strong><span>Open Blotter Cases</span></span>
  </a>
  <a class="stat-card stat-card--teal" href="finance.php">
    <span class="stat-card__icon"><i class="fa-solid fa-coins" aria-hidden="true"></i></span>
    <span><strong>PHP <?= adm_e(number_format($month_income, 0)) ?></strong><span>This Month Income</span></span>
  </a>
  <a class="stat-card stat-card--amber" href="finance.php?tab=expenditures">
    <span class="stat-card__icon"><i class="fa-solid fa-receipt" aria-hidden="true"></i></span>
    <span><strong>PHP <?= adm_e(number_format($month_spending, 0)) ?></strong><span>This Month Spending</span></span>
  </a>
  <a class="stat-card" href="finance.php">
    <span class="stat-card__icon"><i class="fa-solid fa-chart-pie" aria-hidden="true"></i></span>
    <span><strong><?= adm_e($budget_utilized) ?>%</strong><span>Budget Utilized</span></span>
  </a>
</section>

<section class="dashboard-grid dashboard-grid--captain">
  <div class="dashboard-stack">
    <section class="panel">
      <div class="panel__header">
        <div>
          <h2>Awaiting Your Approval</h2>
          <p>Oldest requests are listed first for faster signing decisions.</p>
        </div>
        <a class="btn btn--small" href="requests.php?filter=for_approval">View all</a>
      </div>
      <?php if ($approval_queue): ?>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th>Ref No.</th><th>Resident</th><th>Document</th><th>Days Waiting</th><th>Secretary</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach ($approval_queue as $request): ?>
                <?php $days_waiting = adm_days_waiting($request['updated_at'] ?: $request['created_at']); ?>
                <tr class="<?= $days_waiting > 2 ? 'is-overdue' : '' ?>">
                  <td><strong><?= adm_e($request['reference_no']) ?></strong></td>
                  <td><?= adm_e($request['resident_name']) ?></td>
                  <td><?= adm_e($request['document_name']) ?></td>
                  <td><span class="status-badge status-badge--<?= $days_waiting > 2 ? 'danger' : 'pending' ?>"><?= adm_e($days_waiting) ?> day<?= $days_waiting === 1 ? '' : 's' ?></span></td>
                  <td><?= adm_e($request['processed_by_name'] ?: 'Secretary') ?></td>
                  <td>
                    <div class="table-actions">
                      <form method="post" data-disable-on-submit>
                        <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
                        <input type="hidden" name="action" value="captain_approve_request">
                        <input type="hidden" name="request_id" value="<?= adm_e($request['id']) ?>">
                        <button class="btn btn--success btn--small" type="submit"><i class="fa-solid fa-signature"></i> Approve &amp; Sign</button>
                      </form>
                      <details class="inline-reject">
                        <summary class="btn btn--danger btn--small"><i class="fa-solid fa-reply"></i> Send Back</summary>
                        <form class="inline-reject__body" method="post" data-disable-on-submit>
                          <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
                          <input type="hidden" name="action" value="captain_return_request">
                          <input type="hidden" name="request_id" value="<?= adm_e($request['id']) ?>">
                          <div class="form-field">
                            <label for="return-<?= adm_e($request['id']) ?>">Reason</label>
                            <input id="return-<?= adm_e($request['id']) ?>" name="reason" type="text" required>
                          </div>
                          <button class="btn btn--danger btn--small" type="submit">Return</button>
                        </form>
                      </details>
                      <a class="btn btn--small" href="request-detail.php?id=<?= adm_e($request['id']) ?>"><i class="fa-solid fa-eye"></i> View</a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="empty-state"><i class="fa-solid fa-stamp"></i><strong>No documents awaiting approval</strong><span>Requests sent by the Secretary will appear here.</span></div>
      <?php endif; ?>
    </section>

    <section class="panel">
      <div class="panel__header">
        <div>
          <h2>Expenditure Approvals</h2>
          <p>Treasurer-submitted expenditures awaiting Captain decision.</p>
        </div>
        <a class="btn btn--small" href="finance.php?tab=expenditures">Open finance</a>
      </div>
      <div class="panel__body">
        <?php if ($pending_expenditures): ?>
          <div class="activity-list">
            <?php foreach ($pending_expenditures as $expense): ?>
              <article class="activity-item">
                <span class="stat-card__icon"><i class="fa-solid fa-receipt"></i></span>
                <span class="activity-item__body">
                  <strong><?= adm_e($expense['category']) ?> - PHP <?= adm_e(number_format((float)$expense['amount'], 2)) ?></strong>
                  <small><?= adm_e($expense['payee'] ?: 'No payee') ?> - <?= adm_e($expense['description']) ?></small>
                </span>
                <span class="table-actions">
                  <form method="post" data-disable-on-submit>
                    <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
                    <input type="hidden" name="action" value="approve_expenditure">
                    <input type="hidden" name="expenditure_id" value="<?= adm_e($expense['id']) ?>">
                    <button class="btn btn--success btn--small" type="submit"><i class="fa-solid fa-check"></i> Approve</button>
                  </form>
                  <details class="inline-reject">
                    <summary class="btn btn--danger btn--small"><i class="fa-solid fa-xmark"></i> Reject</summary>
                    <form class="inline-reject__body" method="post" data-disable-on-submit>
                      <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
                      <input type="hidden" name="action" value="reject_expenditure">
                      <input type="hidden" name="expenditure_id" value="<?= adm_e($expense['id']) ?>">
                      <div class="form-field">
                        <label for="expense-reject-<?= adm_e($expense['id']) ?>">Reason</label>
                        <input id="expense-reject-<?= adm_e($expense['id']) ?>" name="reason" type="text" required>
                      </div>
                      <button class="btn btn--danger btn--small" type="submit">Reject</button>
                    </form>
                  </details>
                </span>
              </article>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="empty-state"><i class="fa-solid fa-money-check-dollar"></i><strong>No pending expenditures</strong><span>Large expenses submitted for approval will appear here.</span></div>
        <?php endif; ?>
      </div>
    </section>
  </div>

  <div class="dashboard-stack">
    <section class="panel">
      <div class="panel__header">
        <div>
          <h2>Blotter Cases Needing Resolution</h2>
          <p>Mediation cases where the Captain presides.</p>
        </div>
      </div>
      <div class="panel__body">
        <?php if ($mediation_cases): ?>
          <div class="activity-list">
            <?php foreach ($mediation_cases as $case): ?>
              <a class="activity-item" href="blotter-detail.php?id=<?= adm_e($case['id']) ?>">
                <span class="stat-card__icon"><i class="fa-solid fa-scale-balanced"></i></span>
                <span class="activity-item__body">
                  <strong><?= adm_e($case['case_number']) ?> - <?= adm_e($case['incident_type']) ?></strong>
                  <small>Next hearing: <?= adm_e($case['next_hearing_at'] ? adm_datetime($case['next_hearing_at']) : 'Not scheduled') ?></small>
                </span>
              </a>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="empty-state"><i class="fa-solid fa-folder-open"></i><strong>No mediation cases</strong><span>Cases under mediation will be listed here.</span></div>
        <?php endif; ?>
      </div>
    </section>

    <section class="panel">
      <div class="panel__header">
        <div>
          <h2>System Overview</h2>
          <p>Captain-only operating snapshot.</p>
        </div>
      </div>
      <div class="panel__body">
        <div class="summary-list">
          <div class="summary-row"><strong>Total registered residents</strong><span><?= adm_e($total_residents) ?></span></div>
          <div class="summary-row"><strong>Total docs issued (YTD)</strong><span><?= adm_e($issued_ytd) ?></span></div>
          <div class="summary-row"><strong>Published announcements</strong><span><?= adm_e($published_announcements) ?></span></div>
          <div class="summary-row"><strong>Active admin accounts</strong><span><?= adm_e($active_admins) ?></span></div>
        </div>
      </div>
    </section>

    <section class="panel">
      <div class="panel__header">
        <div>
          <h2>Full Audit Feed</h2>
          <p>Last 10 significant actions.</p>
        </div>
        <a class="btn btn--small" href="audit.php">Open audit</a>
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
          <div class="empty-state"><i class="fa-solid fa-list-check"></i><strong>No activity recorded</strong><span>System actions will be logged here.</span></div>
        <?php endif; ?>
      </div>
    </section>
  </div>
</section>

<?php adm_page_end(); return; ?>
<?php endif; ?>

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
