<?php
require_once __DIR__ . '/includes/admin_layout.php';

$user = adm_require_admin($conn, ['captain', 'secretary', 'treasurer']);
$role = strtolower(trim((string)($user['role'] ?? '')));
$is_captain = $role === 'captain';
$report_types = [
    'document_requests' => 'Document Requests Summary',
    'issued_documents' => 'Issued Documents Log',
    'resident_population' => 'Resident Population Report',
    'blotter_cases' => 'Blotter Cases Summary',
    'pending_verifications' => 'Pending Verifications',
];
if ($is_captain) {
    $report_types += [
        'monthly_financial_summary' => 'Monthly Financial Summary',
        'annual_budget_utilization' => 'Annual Budget Utilization',
        'annual_income_statement' => 'Annual Income Statement',
        'case_resolution_rate' => 'Case Resolution Rate',
        'user_activity' => 'User Activity Report',
    ];
} elseif ($role === 'treasurer') {
    $report_types = [
        'monthly_financial_summary' => 'Monthly Financial Summary',
        'annual_budget_utilization' => 'Annual Budget Utilization',
        'annual_income_statement' => 'Annual Income Statement',
    ];
}

$report_type = (string)($_GET['report_type'] ?? 'document_requests');
if (!isset($report_types[$report_type])) {
    $report_type = 'document_requests';
}
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));

function reports_date_where($column, &$types, &$params, $from, $to) {
    $where = [];
    if ($from !== '') {
        $where[] = 'DATE(' . $column . ') >= ?';
        $types .= 's';
        $params[] = $from;
    }
    if ($to !== '') {
        $where[] = 'DATE(' . $column . ') <= ?';
        $types .= 's';
        $params[] = $to;
    }
    return $where;
}

function reports_build_dataset($conn, $report_type, $from, $to) {
    $headers = [];
    $rows = [];
    $types = '';
    $params = [];

    if ($report_type === 'document_requests' && adm_table_exists($conn, 'document_requests')) {
        $headers = ['Document Type', 'Status', 'Month', 'Total'];
        $where = reports_date_where('dr.created_at', $types, $params, $from, $to);
        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $rows = adm_fetch_all(
            $conn,
            "SELECT dt.name AS document_type, dr.status, DATE_FORMAT(dr.created_at, '%Y-%m') AS month_key, COUNT(*) AS total
             FROM document_requests dr
             INNER JOIN document_types dt ON dt.id = dr.doc_type_id
             {$where_sql}
             GROUP BY dt.name, dr.status, DATE_FORMAT(dr.created_at, '%Y-%m')
             ORDER BY month_key DESC, dt.name ASC, dr.status ASC",
            $types,
            $params
        );
        $rows = array_map(fn($row) => [$row['document_type'], adm_status_label($row['status']), $row['month_key'], $row['total']], $rows);
    } elseif ($report_type === 'issued_documents' && adm_table_exists($conn, 'issued_documents')) {
        $headers = ['Document No.', 'Reference No.', 'Document Type', 'Resident', 'Issued At', 'Issued By'];
        $where = reports_date_where('issued.issued_at', $types, $params, $from, $to);
        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $raw = adm_fetch_all(
            $conn,
            "SELECT issued.doc_number, dr.reference_no, dt.name AS document_type,
                    CONCAT(r.first_name, ' ', r.last_name) AS resident_name,
                    issued.issued_at, issuer.fullname AS issued_by_name
             FROM issued_documents issued
             INNER JOIN document_requests dr ON dr.id = issued.request_id
             INNER JOIN document_types dt ON dt.id = dr.doc_type_id
             INNER JOIN residents r ON r.id = dr.resident_id
             LEFT JOIN users issuer ON issuer.id = issued.issued_by
             {$where_sql}
             ORDER BY issued.issued_at DESC",
            $types,
            $params
        );
        $rows = array_map(fn($row) => [$row['doc_number'], $row['reference_no'], $row['document_type'], $row['resident_name'], $row['issued_at'], $row['issued_by_name'] ?: 'System'], $raw);
    } elseif ($report_type === 'resident_population' && adm_table_exists($conn, 'residents')) {
        $headers = ['Grouping', 'Value', 'Total'];
        $sex = adm_fetch_all($conn, 'SELECT "Sex" AS grouping_name, sex AS value_name, COUNT(*) AS total FROM residents GROUP BY sex ORDER BY sex ASC');
        $civil = adm_fetch_all($conn, 'SELECT "Civil Status" AS grouping_name, civil_status AS value_name, COUNT(*) AS total FROM residents GROUP BY civil_status ORDER BY civil_status ASC');
        $purok = adm_table_exists($conn, 'households')
            ? adm_fetch_all($conn, 'SELECT "Purok" AS grouping_name, COALESCE(h.purok, "Unassigned") AS value_name, COUNT(*) AS total FROM residents r LEFT JOIN households h ON h.id = r.household_id GROUP BY COALESCE(h.purok, "Unassigned") ORDER BY value_name ASC')
            : [];
        $age = adm_fetch_all(
            $conn,
            "SELECT 'Age Bracket' AS grouping_name,
                    CASE
                      WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) < 18 THEN 'Below 18'
                      WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 18 AND 30 THEN '18-30'
                      WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 31 AND 59 THEN '31-59'
                      ELSE '60+'
                    END AS value_name,
                    COUNT(*) AS total
             FROM residents
             GROUP BY value_name
             ORDER BY value_name ASC"
        );
        $raw = array_merge($sex, $civil, $purok, $age);
        $rows = array_map(fn($row) => [$row['grouping_name'], adm_status_label($row['value_name']), $row['total']], $raw);
    } elseif ($report_type === 'blotter_cases' && adm_table_exists($conn, 'blotter_cases')) {
        $headers = ['Status', 'Incident Type', 'Month Filed', 'Total'];
        $where = reports_date_where('created_at', $types, $params, $from, $to);
        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $raw = adm_fetch_all(
            $conn,
            "SELECT status, incident_type, DATE_FORMAT(created_at, '%Y-%m') AS month_key, COUNT(*) AS total
             FROM blotter_cases
             {$where_sql}
             GROUP BY status, incident_type, DATE_FORMAT(created_at, '%Y-%m')
             ORDER BY month_key DESC, status ASC, incident_type ASC",
            $types,
            $params
        );
        $rows = array_map(fn($row) => [adm_status_label($row['status']), $row['incident_type'], $row['month_key'], $row['total']], $raw);
    } elseif ($report_type === 'pending_verifications' && adm_table_exists($conn, 'pending_resident_registrations')) {
        $headers = ['Name', 'Email', 'Mobile', 'Purok', 'Registered At'];
        $where = ["status = 'pending'"];
        $date_where = reports_date_where('created_at', $types, $params, $from, $to);
        $where = array_merge($where, $date_where);
        $raw = adm_fetch_all(
            $conn,
            'SELECT CONCAT(first_name, " ", last_name) AS resident_name, email, mobile_number, purok_zone, created_at
             FROM pending_resident_registrations
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY created_at ASC',
            $types,
            $params
        );
        $rows = array_map(fn($row) => [$row['resident_name'], $row['email'], $row['mobile_number'], $row['purok_zone'], $row['created_at']], $raw);
    } elseif ($report_type === 'monthly_financial_summary') {
        $headers = ['Month', 'Income', 'Expenses', 'Net Balance'];
        $income = adm_table_exists($conn, 'collections')
            ? adm_fetch_all(
                $conn,
                "SELECT DATE_FORMAT(collected_at, '%Y-%m') AS month_key, COALESCE(SUM(amount), 0) AS income
                 FROM collections
                 GROUP BY DATE_FORMAT(collected_at, '%Y-%m')"
            )
            : [];
        $expense = adm_table_exists($conn, 'expenditures')
            ? adm_fetch_all(
                $conn,
                "SELECT DATE_FORMAT(disbursement_date, '%Y-%m') AS month_key, COALESCE(SUM(amount), 0) AS expenses
                 FROM expenditures
                 WHERE " . (adm_column_exists($conn, 'expenditures', 'approval_status') ? "approval_status <> 'rejected'" : '1=1') . "
                 GROUP BY DATE_FORMAT(disbursement_date, '%Y-%m')"
            )
            : [];
        $summary = [];
        foreach ($income as $row) {
            $summary[$row['month_key']]['income'] = (float)$row['income'];
        }
        foreach ($expense as $row) {
            $summary[$row['month_key']]['expenses'] = (float)$row['expenses'];
        }
        krsort($summary);
        foreach ($summary as $month => $values) {
            $income_total = $values['income'] ?? 0;
            $expense_total = $values['expenses'] ?? 0;
            $rows[] = [$month, number_format($income_total, 2), number_format($expense_total, 2), number_format($income_total - $expense_total, 2)];
        }
    } elseif ($report_type === 'annual_budget_utilization' && adm_table_exists($conn, 'budget_items')) {
        $headers = ['Fiscal Year', 'Category', 'Allocated', 'Spent', 'Utilized %'];
        if (adm_table_exists($conn, 'expenditures')) {
            $expense_status = adm_column_exists($conn, 'expenditures', 'approval_status')
                ? "AND e.approval_status <> 'rejected'"
                : '';
            $raw = adm_fetch_all(
                $conn,
                "SELECT b.fiscal_year, b.category, SUM(b.allocated_amount) AS allocated,
                        COALESCE((SELECT SUM(e.amount) FROM expenditures e WHERE YEAR(e.disbursement_date) = b.fiscal_year AND e.category = b.category {$expense_status}), 0) AS spent
                 FROM budget_items b
                 GROUP BY b.fiscal_year, b.category
                 ORDER BY b.fiscal_year DESC, b.category ASC"
            );
        } else {
            $raw = adm_fetch_all(
                $conn,
                'SELECT fiscal_year, category, SUM(allocated_amount) AS allocated, 0 AS spent
                 FROM budget_items
                 GROUP BY fiscal_year, category
                 ORDER BY fiscal_year DESC, category ASC'
            );
        }
        foreach ($raw as $row) {
            $allocated = (float)$row['allocated'];
            $spent = (float)$row['spent'];
            $rows[] = [$row['fiscal_year'], $row['category'], number_format($allocated, 2), number_format($spent, 2), $allocated > 0 ? round(($spent / $allocated) * 100) . '%' : '0%'];
        }
    } elseif ($report_type === 'annual_income_statement') {
        $headers = ['Year', 'Income', 'Expenses', 'Net Balance'];
        $income = adm_table_exists($conn, 'collections')
            ? adm_fetch_all($conn, 'SELECT YEAR(collected_at) AS year_key, COALESCE(SUM(amount), 0) AS total FROM collections GROUP BY YEAR(collected_at)')
            : [];
        $expense_where = adm_table_exists($conn, 'expenditures') && adm_column_exists($conn, 'expenditures', 'approval_status') ? "WHERE approval_status <> 'rejected'" : '';
        $expense = adm_table_exists($conn, 'expenditures')
            ? adm_fetch_all($conn, "SELECT YEAR(disbursement_date) AS year_key, COALESCE(SUM(amount), 0) AS total FROM expenditures {$expense_where} GROUP BY YEAR(disbursement_date)")
            : [];
        $summary = [];
        foreach ($income as $row) {
            $summary[$row['year_key']]['income'] = (float)$row['total'];
        }
        foreach ($expense as $row) {
            $summary[$row['year_key']]['expenses'] = (float)$row['total'];
        }
        krsort($summary);
        foreach ($summary as $year => $values) {
            $income_total = $values['income'] ?? 0;
            $expense_total = $values['expenses'] ?? 0;
            $rows[] = [$year, number_format($income_total, 2), number_format($expense_total, 2), number_format($income_total - $expense_total, 2)];
        }
    } elseif ($report_type === 'case_resolution_rate' && adm_table_exists($conn, 'blotter_cases')) {
        $headers = ['Status', 'Total', 'Share'];
        $raw = adm_fetch_all($conn, 'SELECT status, COUNT(*) AS total FROM blotter_cases GROUP BY status ORDER BY total DESC');
        $total = array_sum(array_map(fn($row) => (int)$row['total'], $raw));
        foreach ($raw as $row) {
            $rows[] = [adm_status_label($row['status']), $row['total'], $total > 0 ? round(((int)$row['total'] / $total) * 100) . '%' : '0%'];
        }
    } elseif ($report_type === 'user_activity' && adm_table_exists($conn, 'audit_logs')) {
        $headers = ['User', 'Role', 'Action Count', 'Last Activity'];
        $raw = adm_fetch_all(
            $conn,
            "SELECT COALESCE(u.fullname, 'System') AS fullname, u.role, COUNT(*) AS total, MAX(al.created_at) AS last_activity
             FROM audit_logs al
             LEFT JOIN users u ON u.id = al.user_id
             GROUP BY al.user_id, u.fullname, u.role
             ORDER BY total DESC, last_activity DESC"
        );
        $rows = array_map(fn($row) => [$row['fullname'], adm_role_label($row['role'] ?? ''), $row['total'], $row['last_activity']], $raw);
    }

    return [$headers, $rows];
}

[$headers, $rows] = reports_build_dataset($conn, $report_type, $from, $to);

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $report_type . '-' . date('Ymd-His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit();
}

if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title><?= adm_e($report_types[$report_type]) ?> - Print</title>
      <link rel="stylesheet" href="assets/css/secretary.css?v=20260605b">
      <style>body{padding:24px}.print-toolbar{max-width:980px;margin:0 auto 14px;display:flex;justify-content:space-between}</style>
    </head>
    <body>
      <div class="print-toolbar no-print">
        <a class="btn" href="reports.php?<?= adm_e(http_build_query(['report_type' => $report_type, 'from' => $from, 'to' => $to])) ?>">Back to reports</a>
        <button class="btn btn--primary" onclick="window.print()">Print / Save PDF</button>
      </div>
      <article class="print-sheet" style="max-width:980px;">
        <header class="print-header">
          <img src="../assets/images/logo_noveleta.png" alt="Barangay seal">
          <div>
            <p>Republic of the Philippines</p>
            <p>Municipality of Noveleta, Province of Cavite</p>
            <h1>Barangay Sta. Rosa 1</h1>
            <p><?= adm_e($report_types[$report_type]) ?></p>
          </div>
          <div></div>
        </header>
        <p style="margin-top:18px;">Period: <?= adm_e($from ?: 'Start') ?> to <?= adm_e($to ?: 'Present') ?></p>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><?php foreach ($headers as $header): ?><th><?= adm_e($header) ?></th><?php endforeach; ?></tr></thead>
            <tbody>
              <?php foreach ($rows as $row): ?>
                <tr><?php foreach ($row as $cell): ?><td><?= adm_e($cell) ?></td><?php endforeach; ?></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </article>
    </body>
    </html>
    <?php
    exit();
}

$query_base = ['report_type' => $report_type, 'from' => $from, 'to' => $to];
$actions = '<a class="btn" href="reports.php?' . adm_e(http_build_query($query_base + ['export' => 'csv'])) . '"><i class="fa-solid fa-file-csv"></i> Export CSV</a> ';
$actions .= '<a class="btn btn--primary" target="_blank" rel="noopener" href="reports.php?' . adm_e(http_build_query($query_base + ['export' => 'pdf'])) . '"><i class="fa-solid fa-file-pdf"></i> Export PDF</a>';

adm_page_start('Reports', 'reports', $user, 'reports-page');
adm_page_header($is_captain ? 'Captain full reports' : 'Reports', 'Generate Reports', 'Choose a report type, preview the data, then export it for submission.', $actions);
?>

<form class="filter-panel" method="get">
  <div class="filter-grid">
    <div class="form-field">
      <label for="report_type">Report type</label>
      <select id="report_type" name="report_type">
        <?php foreach ($report_types as $key => $label): ?>
          <option value="<?= adm_e($key) ?>" <?= $report_type === $key ? 'selected' : '' ?>><?= adm_e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-field">
      <label for="from">From date</label>
      <input id="from" name="from" type="date" value="<?= adm_e($from) ?>">
    </div>
    <div class="form-field">
      <label for="to">To date</label>
      <input id="to" name="to" type="date" value="<?= adm_e($to) ?>">
    </div>
    <button class="btn btn--primary" type="submit"><i class="fa-solid fa-table"></i> Generate</button>
    <a class="btn" href="reports.php"><i class="fa-solid fa-rotate-left"></i> Reset</a>
  </div>
</form>

<section class="stat-grid">
  <div class="stat-card">
    <span class="stat-card__icon"><i class="fa-solid fa-table-list"></i></span>
    <span><strong><?= adm_e(count($rows)) ?></strong><span>Preview Rows</span></span>
  </div>
  <div class="stat-card stat-card--teal">
    <span class="stat-card__icon"><i class="fa-solid fa-calendar-days"></i></span>
    <span><strong><?= adm_e($from ?: 'Start') ?></strong><span>From</span></span>
  </div>
  <div class="stat-card stat-card--amber">
    <span class="stat-card__icon"><i class="fa-solid fa-calendar-check"></i></span>
    <span><strong><?= adm_e($to ?: 'Today') ?></strong><span>To</span></span>
  </div>
</section>

<section class="panel">
  <div class="panel__header">
    <div>
      <h2><?= adm_e($report_types[$report_type]) ?></h2>
      <p>Preview table before exporting.</p>
    </div>
  </div>
  <?php if ($rows): ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><?php foreach ($headers as $header): ?><th><?= adm_e($header) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr><?php foreach ($row as $cell): ?><td><?= adm_e($cell) ?></td><?php endforeach; ?></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="empty-state">
      <i class="fa-solid fa-chart-pie"></i>
      <strong>No report data found</strong>
      <span>Try another date range or report type.</span>
    </div>
  <?php endif; ?>
</section>

<?php adm_page_end(); ?>
