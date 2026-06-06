<?php
require_once __DIR__ . '/includes/admin_layout.php';

$user = adm_require_admin($conn, ['captain', 'treasurer']);
$csrf = adm_action_token();
$role = strtolower(trim((string)($user['role'] ?? '')));
$is_captain = $role === 'captain';
$tab = strtolower(trim((string)($_GET['tab'] ?? 'overview')));
if (!in_array($tab, ['overview', 'expenditures', 'collections', 'budget'], true)) {
    $tab = 'overview';
}

if (adm_table_exists($conn, 'expenditures')) {
    adm_ensure_expenditure_approval_columns($conn);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!adm_verify_action_token($_POST['csrf_token'] ?? '')) {
        adm_set_flash('danger', 'Your session expired. Please refresh and try again.');
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'approve_expenditure' && $is_captain) {
            [$ok, $message] = adm_approve_expenditure($conn, (int)($_POST['expenditure_id'] ?? 0), (int)$user['id']);
            adm_set_flash($ok ? 'success' : 'danger', $message);
        } elseif ($action === 'reject_expenditure' && $is_captain) {
            [$ok, $message] = adm_reject_expenditure(
                $conn,
                (int)($_POST['expenditure_id'] ?? 0),
                (int)$user['id'],
                (string)($_POST['reason'] ?? '')
            );
            adm_set_flash($ok ? 'success' : 'danger', $message);
        } elseif ($action === 'add_expenditure' && adm_table_exists($conn, 'expenditures')) {
            $category = trim((string)($_POST['category'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $amount = (float)($_POST['amount'] ?? 0);
            $disbursement_date = trim((string)($_POST['disbursement_date'] ?? ''));
            $payee = trim((string)($_POST['payee'] ?? ''));

            if ($category === '' || $description === '' || $amount <= 0 || $disbursement_date === '') {
                adm_set_flash('danger', 'Category, description, amount, and disbursement date are required.');
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO expenditures (category, description, amount, disbursement_date, payee, approval_status, recorded_by)
                     VALUES (?, ?, ?, ?, ?, 'pending', ?)"
                );
                if ($stmt) {
                    $user_id = (int)$user['id'];
                    $stmt->bind_param('ssdssi', $category, $description, $amount, $disbursement_date, $payee, $user_id);
                    $stmt->execute();
                    $expense_id = (int)$stmt->insert_id;
                    $stmt->close();
                    adm_log_activity($conn, (int)$user['id'], 'expenditure_added', 'expenditures', $expense_id, ['amount' => $amount, 'category' => $category]);
                    adm_set_flash('success', 'Expenditure submitted for Captain approval.');
                } else {
                    adm_set_flash('danger', 'Unable to record expenditure.');
                }
            }
        } else {
            adm_set_flash('danger', 'You are not allowed to perform that finance action.');
        }
    }

    header('Location: finance.php?tab=' . urlencode($tab));
    exit();
}

$month_income = adm_table_exists($conn, 'collections')
    ? (float)(adm_fetch_one($conn, "SELECT COALESCE(SUM(amount), 0) AS total FROM collections WHERE DATE_FORMAT(collected_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')")['total'] ?? 0)
    : 0.0;
$month_spending = adm_table_exists($conn, 'expenditures')
    ? (float)(adm_fetch_one($conn, "SELECT COALESCE(SUM(amount), 0) AS total FROM expenditures WHERE DATE_FORMAT(disbursement_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m') AND approval_status <> 'rejected'")['total'] ?? 0)
    : 0.0;
$pending_expenditure_count = adm_table_exists($conn, 'expenditures')
    ? adm_scalar($conn, "SELECT COUNT(*) FROM expenditures WHERE approval_status = 'pending'")
    : 0;
$annual_budget = adm_table_exists($conn, 'budget_items')
    ? (float)(adm_fetch_one($conn, 'SELECT COALESCE(SUM(allocated_amount), 0) AS total FROM budget_items WHERE fiscal_year = YEAR(CURDATE())')['total'] ?? 0)
    : 0.0;
$ytd_spending = adm_table_exists($conn, 'expenditures')
    ? (float)(adm_fetch_one($conn, "SELECT COALESCE(SUM(amount), 0) AS total FROM expenditures WHERE YEAR(disbursement_date) = YEAR(CURDATE()) AND approval_status <> 'rejected'")['total'] ?? 0)
    : 0.0;
$budget_utilized = $annual_budget > 0 ? min(100, (int)round(($ytd_spending / $annual_budget) * 100)) : 0;

$expenditures = adm_table_exists($conn, 'expenditures')
    ? adm_fetch_all(
        $conn,
        "SELECT e.*, recorder.fullname AS recorded_by_name, approver.fullname AS approved_by_name
         FROM expenditures e
         LEFT JOIN users recorder ON recorder.id = e.recorded_by
         LEFT JOIN users approver ON approver.id = e.approved_by
         ORDER BY FIELD(e.approval_status, 'pending', 'approved', 'rejected'), e.created_at DESC
         LIMIT 300"
    )
    : [];
$collections = adm_table_exists($conn, 'collections')
    ? adm_fetch_all(
        $conn,
        "SELECT c.*, CONCAT(r.first_name, ' ', r.last_name) AS resident_name, collector.fullname AS collected_by_name
         FROM collections c
         LEFT JOIN residents r ON r.id = c.resident_id
         LEFT JOIN users collector ON collector.id = c.collected_by
         ORDER BY c.collected_at DESC
         LIMIT 200"
    )
    : [];
$budget_items = adm_table_exists($conn, 'budget_items')
    ? adm_fetch_all($conn, 'SELECT * FROM budget_items WHERE fiscal_year = YEAR(CURDATE()) ORDER BY category ASC, allocated_amount DESC')
    : [];

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=finance-' . $tab . '-' . date('Ymd-His') . '.csv');
    $out = fopen('php://output', 'w');
    if ($tab === 'collections') {
        fputcsv($out, ['OR Number', 'Source', 'Amount', 'Resident', 'Collected By', 'Collected At']);
        foreach ($collections as $row) {
            fputcsv($out, [$row['or_number'], $row['source_type'], $row['amount'], $row['resident_name'], $row['collected_by_name'], $row['collected_at']]);
        }
    } else {
        fputcsv($out, ['Category', 'Amount', 'Payee', 'Date', 'Status', 'Recorded By', 'Approved By']);
        foreach ($expenditures as $row) {
            fputcsv($out, [$row['category'], $row['amount'], $row['payee'], $row['disbursement_date'], $row['approval_status'], $row['recorded_by_name'], $row['approved_by_name']]);
        }
    }
    fclose($out);
    exit();
}

$export_query = ['tab' => $tab, 'export' => 'csv'];
$actions = '<a class="btn" href="reports.php?report_type=monthly_financial_summary"><i class="fa-solid fa-chart-pie"></i> Finance reports</a> ';
$actions .= '<a class="btn btn--primary" href="finance.php?' . adm_e(http_build_query($export_query)) . '"><i class="fa-solid fa-file-csv"></i> Export CSV</a>';

adm_page_start('Financial Overview', $tab === 'overview' ? 'finance-overview' : 'finance', $user, 'finance-page');
adm_page_header('Barangay finance', $is_captain ? 'Financial Overview & Approvals' : 'Treasurer Finance Workspace', 'Monitor income, spending, budget utilization, and expenditure approval status.', $actions);
?>

<nav class="tabs" aria-label="Finance tabs">
  <?php foreach (['overview' => 'Overview', 'expenditures' => 'Expenditures', 'collections' => 'Collections', 'budget' => 'Budget'] as $key => $label): ?>
    <a class="tab-link <?= $tab === $key ? 'is-active' : '' ?>" href="finance.php?tab=<?= adm_e($key) ?>"><?= adm_e($label) ?><?= $key === 'expenditures' ? '<strong>' . adm_e($pending_expenditure_count) . '</strong>' : '' ?></a>
  <?php endforeach; ?>
</nav>

<section class="stat-grid">
  <div class="stat-card stat-card--teal"><span class="stat-card__icon"><i class="fa-solid fa-coins"></i></span><span><strong>PHP <?= adm_e(number_format($month_income, 0)) ?></strong><span>This Month Income</span></span></div>
  <div class="stat-card stat-card--amber"><span class="stat-card__icon"><i class="fa-solid fa-receipt"></i></span><span><strong>PHP <?= adm_e(number_format($month_spending, 0)) ?></strong><span>This Month Spending</span></span></div>
  <div class="stat-card"><span class="stat-card__icon"><i class="fa-solid fa-hourglass-half"></i></span><span><strong><?= adm_e($pending_expenditure_count) ?></strong><span>Pending Approvals</span></span></div>
  <div class="stat-card"><span class="stat-card__icon"><i class="fa-solid fa-chart-pie"></i></span><span><strong><?= adm_e($budget_utilized) ?>%</strong><span>Budget Utilized</span></span></div>
</section>

<?php if ($tab === 'overview'): ?>
  <section class="details-grid">
    <section class="panel">
      <div class="panel__header"><div><h2>Recent Expenditures</h2><p>Latest spending records and approval decisions.</p></div></div>
      <?php if ($expenditures): ?>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th>Category</th><th>Amount</th><th>Payee</th><th>Date</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach (array_slice($expenditures, 0, 8) as $expense): ?>
                <tr>
                  <td><strong><?= adm_e($expense['category']) ?></strong><small><?= adm_e($expense['description']) ?></small></td>
                  <td>PHP <?= adm_e(number_format((float)$expense['amount'], 2)) ?></td>
                  <td><?= adm_e($expense['payee'] ?: 'Not set') ?></td>
                  <td><?= adm_e(adm_date($expense['disbursement_date'])) ?></td>
                  <td><span class="status-badge status-badge--<?= adm_e(adm_status_class($expense['approval_status'])) ?>"><?= adm_e(adm_status_label($expense['approval_status'])) ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="empty-state"><i class="fa-solid fa-receipt"></i><strong>No expenditures recorded</strong><span>Treasurer submissions will appear here.</span></div>
      <?php endif; ?>
    </section>

    <aside class="form-panel">
      <h2>Budget Utilization</h2>
      <div class="progress-meter" aria-label="Budget utilization">
        <span style="width: <?= adm_e($budget_utilized) ?>%"></span>
      </div>
      <dl class="definition-list" style="grid-template-columns: 1fr;">
        <div><dt>Annual Budget</dt><dd>PHP <?= adm_e(number_format($annual_budget, 2)) ?></dd></div>
        <div><dt>YTD Spending</dt><dd>PHP <?= adm_e(number_format($ytd_spending, 2)) ?></dd></div>
      </dl>
    </aside>
  </section>
<?php elseif ($tab === 'expenditures'): ?>
  <section class="details-grid">
    <section class="panel">
      <div class="panel__header"><div><h2>Expenditure Approvals</h2><p>Pending rows require Captain action before disbursement is considered approved.</p></div></div>
      <?php if ($expenditures): ?>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th>Category</th><th>Amount</th><th>Payee</th><th>Recorded By</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach ($expenditures as $expense): ?>
                <tr>
                  <td><strong><?= adm_e($expense['category']) ?></strong><small><?= adm_e($expense['description']) ?></small></td>
                  <td>PHP <?= adm_e(number_format((float)$expense['amount'], 2)) ?></td>
                  <td><?= adm_e($expense['payee'] ?: 'Not set') ?><small><?= adm_e(adm_date($expense['disbursement_date'])) ?></small></td>
                  <td><?= adm_e($expense['recorded_by_name'] ?: 'Treasurer') ?></td>
                  <td><span class="status-badge status-badge--<?= adm_e(adm_status_class($expense['approval_status'])) ?>"><?= adm_e(adm_status_label($expense['approval_status'])) ?></span></td>
                  <td>
                    <?php if ($is_captain && $expense['approval_status'] === 'pending'): ?>
                      <div class="table-actions">
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
                            <div class="form-field"><label for="reject-exp-<?= adm_e($expense['id']) ?>">Reason</label><input id="reject-exp-<?= adm_e($expense['id']) ?>" name="reason" type="text" required></div>
                            <button class="btn btn--danger btn--small" type="submit">Reject</button>
                          </form>
                        </details>
                      </div>
                    <?php else: ?>
                      <small><?= adm_e($expense['approved_by_name'] ?: ($expense['approval_notes'] ?: 'No action available')) ?></small>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="empty-state"><i class="fa-solid fa-money-check-dollar"></i><strong>No expenditures found</strong><span>Use the form to submit an expense.</span></div>
      <?php endif; ?>
    </section>

    <form class="form-panel" method="post" data-disable-on-submit>
      <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
      <input type="hidden" name="action" value="add_expenditure">
      <h2>Submit Expenditure</h2>
      <div class="form-section">
        <div class="form-grid" style="grid-template-columns: 1fr;">
          <div class="form-field"><label for="category">Category</label><input id="category" name="category" type="text" required></div>
          <div class="form-field"><label for="payee">Payee</label><input id="payee" name="payee" type="text"></div>
          <div class="form-field"><label for="amount">Amount</label><input id="amount" name="amount" type="number" min="0" step="0.01" required></div>
          <div class="form-field"><label for="disbursement_date">Disbursement Date</label><input id="disbursement_date" name="disbursement_date" type="date" value="<?= adm_e(date('Y-m-d')) ?>" required></div>
          <div class="form-field"><label for="description">Description</label><textarea id="description" name="description" required></textarea></div>
          <button class="btn btn--primary" type="submit"><i class="fa-solid fa-paper-plane"></i> Submit for Approval</button>
        </div>
      </div>
    </form>
  </section>
<?php elseif ($tab === 'collections'): ?>
  <section class="panel">
    <div class="panel__header"><div><h2>Collections</h2><p>Document fees and other recorded income.</p></div></div>
    <?php if ($collections): ?>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>OR No.</th><th>Source</th><th>Amount</th><th>Resident</th><th>Collected By</th><th>Date</th></tr></thead>
          <tbody>
            <?php foreach ($collections as $collection): ?>
              <tr><td><strong><?= adm_e($collection['or_number']) ?></strong></td><td><?= adm_e(adm_status_label($collection['source_type'])) ?></td><td>PHP <?= adm_e(number_format((float)$collection['amount'], 2)) ?></td><td><?= adm_e($collection['resident_name'] ?: 'N/A') ?></td><td><?= adm_e($collection['collected_by_name'] ?: 'Official') ?></td><td><?= adm_e(adm_datetime($collection['collected_at'])) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="empty-state"><i class="fa-solid fa-coins"></i><strong>No collections recorded</strong><span>Document approval fees will be recorded automatically when applicable.</span></div>
    <?php endif; ?>
  </section>
<?php else: ?>
  <section class="panel">
    <div class="panel__header"><div><h2>Annual Budget Items</h2><p>Budget allocations for the current fiscal year.</p></div></div>
    <?php if ($budget_items): ?>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Category</th><th>Description</th><th>Allocated Amount</th><th>Created</th></tr></thead>
          <tbody>
            <?php foreach ($budget_items as $item): ?>
              <tr><td><strong><?= adm_e($item['category']) ?></strong></td><td><?= adm_e($item['description'] ?: 'N/A') ?></td><td>PHP <?= adm_e(number_format((float)$item['allocated_amount'], 2)) ?></td><td><?= adm_e(adm_date($item['created_at'])) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="empty-state"><i class="fa-solid fa-chart-pie"></i><strong>No budget items found</strong><span>Budget records can be loaded into the budget_items table for utilization tracking.</span></div>
    <?php endif; ?>
  </section>
<?php endif; ?>

<?php adm_page_end(); ?>
