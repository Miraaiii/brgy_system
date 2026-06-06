<?php

include 'config/connection.php';
include 'includes/auth_check.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

requireRole(['treasurer']);


$tab = $_GET['tab'] ?? 'dashboard';

/* Collections */
if (!function_exists('col_sanitize')) {
    function col_sanitize(string $v): string {
        return htmlspecialchars(trim($v), ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('col_currency')) {
    function col_currency(float $n): string {
        return '₱ ' . number_format($n, 2);
    }
}
if (!function_exists('col_type_label')) {
    function col_type_label(string $t): string {
        return match($t) {
            'document_fee'    => 'Document Fee',
            'business_permit' => 'Business Permit',
            'cedula'          => 'Cedula',
            default           => 'Other',
        };
    }
}
if (!function_exists('col_type_badge')) {
    function col_type_badge(string $t): string {
        return match($t) {
            'document_fee'    => 'cbadge-doc',
            'business_permit' => 'cbadge-biz',
            'cedula'          => 'cbadge-ced',
            default           => 'cbadge-other',
        };
    }
}

// ── Handle AJAX void POST (returns JSON and exits) ───────────────────────────
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['_col_action'] ?? '') === 'void' &&
    ($_GET['tab'] ?? '') === 'collections'
) {
    header('Content-Type: application/json');
    if (!in_array($currentUser['role'], ['treasurer', 'admin'], true)) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
        exit;
    }
    $vid    = (int) ($_POST['id']     ?? 0);
    $reason = trim($_POST['reason']  ?? '');
    if (!$vid || $reason === '') {
        echo json_encode(['success' => false, 'message' => 'ID and reason are required.']);
        exit;
    }
    $s = $pdo->prepare("
        UPDATE collections
        SET    voided = 1, void_reason = :reason,
               voided_by = :by, voided_at = NOW()
        WHERE  id = :id AND voided = 0
    ");
    $s->execute([':reason' => $reason, ':by' => $currentUser['id'], ':id' => $vid]);
    echo json_encode($s->rowCount()
        ? ['success' => true,  'message' => 'Collection voided successfully.']
        : ['success' => false, 'message' => 'Record not found or already voided.']
    );
    exit;
}

// ── Handle CSV export (streams file and exits) ────────────────────────────────
if (($_GET['tab'] ?? '') === 'collections' && ($_GET['export'] ?? '') === 'csv') {
    $allowed = ['all','document_fee','business_permit','cedula','other'];
    $xType   = in_array($_GET['type'] ?? 'all', $allowed, true) ? ($_GET['type'] ?? 'all') : 'all';
    $xFrom   = !empty($_GET['date_from']) ? date('Y-m-d', strtotime($_GET['date_from'])) : date('Y-m-01');
    $xTo     = !empty($_GET['date_to'])   ? date('Y-m-d', strtotime($_GET['date_to']))   : date('Y-m-d');
    $xSearch = trim($_GET['search'] ?? '');

    $xParams = [':date_from' => $xFrom, ':date_to' => $xTo];
    $xType_q = $xType !== 'all' ? 'AND c.source_type = :source_type' : '';
    if ($xType !== 'all') $xParams[':source_type'] = $xType;
    $xSearch_q = '';
    if ($xSearch !== '') {
        $xSearch_q = "AND (c.or_number LIKE :search OR CONCAT(r.first_name,' ',r.last_name) LIKE :search)";
        $xParams[':search'] = '%' . $xSearch . '%';
    }

    $xs = $pdo->prepare("
        SELECT c.or_number,
               c.source_type,
               CONCAT(COALESCE(r.first_name,''),' ',COALESCE(r.last_name,'')) AS resident,
               c.amount, c.description, c.collected_at,
               u.username AS collected_by,
               IF(c.voided,'VOIDED','Active') AS status,
               c.void_reason
        FROM   collections c
        LEFT JOIN residents r ON r.id = c.resident_id
        JOIN   users u        ON u.id = c.collected_by
        WHERE  DATE(c.collected_at) BETWEEN :date_from AND :date_to
               $xType_q $xSearch_q
        ORDER  BY c.collected_at DESC
    ");
    $xs->execute($xParams);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="collections_' . $xFrom . '_to_' . $xTo . '.csv"');
    header('Pragma: no-cache');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['OR Number','Source Type','Resident','Amount','Description',
                   'Date Collected','Collected By','Status','Void Reason']);
    foreach ($xs->fetchAll(PDO::FETCH_ASSOC) as $xr) {
        $xr['amount'] = number_format((float)$xr['amount'], 2, '.', '');
        fputcsv($out, $xr);
    }
    fclose($out);
    exit;
}

// ── Filters ───────────────────────────────────────────────────────────────────
$allowed    = ['all','document_fee','business_permit','cedula','other'];
$sourceType = in_array($_GET['type'] ?? 'all', $allowed, true) ? ($_GET['type'] ?? 'all') : 'all';
$dateFrom   = !empty($_GET['date_from']) ? date('Y-m-d', strtotime($_GET['date_from'])) : date('Y-m-01');
$dateTo     = !empty($_GET['date_to'])   ? date('Y-m-d', strtotime($_GET['date_to']))   : date('Y-m-d');
$search     = trim($_GET['search'] ?? '');

$params     = [':date_from' => $dateFrom, ':date_to' => $dateTo];
$typeClause = $searchClause = '';

if ($sourceType !== 'all') {
    $typeClause = 'AND c.source_type = :source_type';
    $params[':source_type'] = $sourceType;
}
if ($search !== '') {
    $searchClause = "AND (c.or_number LIKE :search OR CONCAT(r.first_name,' ',r.last_name) LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

// ── Main query ────────────────────────────────────────────────────────────────
$stmtList = $pdo->prepare("
    SELECT   c.id,
             c.or_number,
             c.source_type,
             c.amount,
             c.description,
             c.collected_at,
             r.first_name,
             r.last_name,
             u.username AS collected_by_name
    FROM     collections c
    LEFT JOIN residents r ON r.id = c.resident_id
    JOIN     users u      ON u.id = c.collected_by
    WHERE    DATE(c.collected_at) BETWEEN :date_from AND :date_to
             $typeClause
             $searchClause
    ORDER BY c.collected_at DESC
");
$stmtList->execute($params);
$collections = $stmtList->fetchAll(PDO::FETCH_ASSOC);

// ── Running total ─────────────────────────────────────────────────────────────
$stmtTotal = $pdo->prepare("
    SELECT COALESCE(SUM(c.amount), 0)
    FROM   collections c
    LEFT JOIN residents r ON r.id = c.resident_id
    JOIN   users u        ON u.id = c.collected_by
    WHERE  DATE(c.collected_at) BETWEEN :date_from AND :date_to
           $typeClause
           $searchClause
");
$stmtTotal->execute($params);
$runningTotal = (float) $stmtTotal->fetchColumn();

// ── OR Number generator ───────────────────────────────────────────────────────
function generate_or_number(PDO $pdo): string {
    $last = $pdo->query('SELECT MAX(id) AS lid FROM collections')
                ->fetch(PDO::FETCH_ASSOC)['lid'] ?? 0;
    return 'OR-' . date('Y') . '-' . str_pad(($last + 1), 5, '0', STR_PAD_LEFT);
}

// ── Insert new collection ─────────────────────────────────────────────────────
function insert_collection(PDO $pdo, array $data, int $collected_by): bool {
    $or_number = generate_or_number($pdo);

    $stmt = $pdo->prepare("
        INSERT INTO collections 
            (or_number, request_id, resident_id, source_type, amount, description, collected_by, collected_at)
        VALUES 
            (:or_number, :request_id, :resident_id, :source_type, :amount, :description, :collected_by, :collected_at)
    ");

    return $stmt->execute([
        ':or_number'    => $or_number,
        ':request_id'   => $data['request_id']  ?? null,
        ':resident_id'  => $data['resident_id'] ?? null,
        ':source_type'  => $data['source_type'],
        ':amount'       => $data['amount'],
        ':description'  => $data['description'] ?? '',
        ':collected_by' => $collected_by,
        ':collected_at' => $data['collected_at'] ?? date('Y-m-d H:i:s'),
    ]);
}

// ── Export URL ────────────────────────────────────────────────────────────────
$exportUrl = 'finance_ad.php?' . http_build_query([
    'tab'       => 'collections',
    'export'    => 'csv',
    'type'      => $sourceType,
    'date_from' => $dateFrom,
    'date_to'   => $dateTo,
    'search'    => $search,
]);

// ── Handle Record Payment POST ────────────────────────────────────────────────
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['_rec_action'] ?? '') === 'record_payment' &&
    ($_GET['tab'] ?? '') === 'record'
) {
    header('Content-Type: application/json');

    $rec_source_type  = trim($_POST['source_type']  ?? '');
    $rec_resident_id  = !empty($_POST['resident_id']) ? (int)$_POST['resident_id'] : null;
    $rec_request_id   = !empty($_POST['request_id'])  ? (int)$_POST['request_id']  : null;
    $rec_amount       = (float)($_POST['amount']      ?? 0);
    $rec_description  = trim($_POST['description']   ?? '');
    $rec_collected_at = trim($_POST['collected_at']  ?? date('Y-m-d'));
    $rec_notes        = trim($_POST['notes']         ?? '');

    // ── Validate ──────────────────────────────────────────────────────────────
    $errors = [];
    $allowed_types = ['document_fee','business_permit','cedula','other'];

    if (!in_array($rec_source_type, $allowed_types, true))
        $errors[] = 'Invalid source type.';
    if ($rec_amount <= 0)
        $errors[] = 'Amount must be greater than 0.';
    if ($rec_description === '')
        $errors[] = 'Description is required.';
    if ($rec_collected_at === '')
        $errors[] = 'Date collected is required.';

    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
        exit;
    }

    // ── Generate OR number (as specified) ─────────────────────────────────────
    $last  = $pdo->query('SELECT MAX(id) AS lid FROM collections')
                 ->fetch(PDO::FETCH_ASSOC)['lid'] ?? 0;
    $or_no = 'OR-' . date('Y') . '-' . str_pad(($last + 1), 5, '0', STR_PAD_LEFT);

    // ── Insert (as specified) ─────────────────────────────────────────────────
    $stmt = $pdo->prepare("
        INSERT INTO collections
            (or_number, request_id, resident_id, source_type, amount, description, collected_by, collected_at)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $or_no,
        $rec_request_id,
        $rec_resident_id,
        $rec_source_type,
        $rec_amount,
        $rec_description . ($rec_notes ? ' | Notes: ' . $rec_notes : ''),
        $currentUser['id'],
        $rec_collected_at,
    ]);

    echo json_encode([
        'success'   => true,
        'message'   => 'Payment recorded successfully.',
        'or_number' => $or_no,
    ]);
    exit;
}

// ── Search document requests (AJAX) ──────────────────────────────────────────
if (
    isset($_GET['_rec_search_req']) &&
    ($_GET['tab'] ?? '') === 'record'
) {
    header('Content-Type: application/json');
    $q = '%' . trim($_GET['q'] ?? '') . '%';
    $rows = $pdo->prepare("
        SELECT   id, reference_number, document_type,
                 CONCAT(r.first_name,' ',r.last_name) AS resident_name,
                 r.id AS resident_id
        FROM     document_requests dr
        JOIN     residents r ON r.id = dr.resident_id
        WHERE    dr.reference_number LIKE :q
           OR    CONCAT(r.first_name,' ',r.last_name) LIKE :q
        LIMIT 10
    ");
    $rows->execute([':q' => $q]);
    echo json_encode($rows->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

/* Pre-generate OR preview for display */
$preview_last  = $pdo->query('SELECT MAX(id) AS lid FROM collections')
                      ->fetch(PDO::FETCH_ASSOC)['lid'] ?? 0;
$preview_or_no = 'OR-' . date('Y') . '-' . str_pad(($preview_last + 1), 5, '0', STR_PAD_LEFT);

// ─── Configuration ───────────────────────────────────────────────────────────
define('LARGE_EXPENDITURE_THRESHOLD', 5000);
 
// ─── Helpers ─────────────────────────────────────────────────────────────────
function formatAmount(float $amount): string {
    return '₱' . number_format($amount, 2);
}
 
function getStatusBadge(string $status): string {
    if ($status === 'Approved') {
        return '<span class="exp-badge exp-badge--approved">Approved</span>';
    }
    return '<span class="exp-badge exp-badge--pending">Pending Captain Approval</span>';
}
 
function getCategoryIcon(string $cat): string {
    $icons = [
        'Personnel'      => '👤',
        'Supplies'       => '📦',
        'Infrastructure' => '🏗️',
        'Events'         => '🎉',
        'Maintenance'    => '🔧',
        'Other'          => '📁',
    ];
    return $icons[$cat] ?? '📁';
}
 
// ─── Filter inputs ────────────────────────────────────────────────────────────
$categoryFilter = $_GET['category']   ?? 'all';
$dateFrom       = $_GET['date_from']  ?? date('Y-m-01');
$dateTo         = $_GET['date_to']    ?? date('Y-m-t');
$searchQuery    = $_GET['search']     ?? '';
 
$validCategories = ['all', 'Personnel', 'Supplies', 'Infrastructure', 'Events', 'Maintenance', 'Other'];
if (!in_array($categoryFilter, $validCategories, true)) {
    $categoryFilter = 'all';
}
 
// ─── DB Query ─────────────────────────────────────────────────────────────────
// SELECT e.id, e.category, e.description, e.amount, e.disbursement_date,
//        e.payee, e.supporting_doc_path,
//        u_rec.username AS recorded_by_name,
//        u_apr.username AS approved_by_name,
//        CASE WHEN e.approved_by IS NOT NULL THEN 'Approved'
//             ELSE 'Pending Approval' END AS approval_status
// FROM expenditures e
// JOIN users u_rec ON u_rec.id = e.recorded_by
// LEFT JOIN users u_apr ON u_apr.id = e.approved_by
// WHERE e.category = ?           -- or ignored when 'all'
//   AND e.disbursement_date BETWEEN ? AND ?
// ORDER BY e.disbursement_date DESC;
 
$expenditures = [];
$subtotals    = [];
$totalAmount  = 0;
 
if (isset($pdo)) {   // $pdo = your PDO connection
    if ($categoryFilter === 'all') {
        $sql  = "SELECT e.id, e.category, e.description, e.amount,
                        e.disbursement_date, e.payee, e.supporting_doc_path,
                        u_rec.username AS recorded_by_name,
                        u_apr.username AS approved_by_name,
                        CASE WHEN e.approved_by IS NOT NULL THEN 'Approved'
                             ELSE 'Pending Approval' END AS approval_status
                 FROM expenditures e
                 JOIN users u_rec ON u_rec.id = e.recorded_by
                 LEFT JOIN users u_apr ON u_apr.id = e.approved_by
                 WHERE e.disbursement_date BETWEEN ? AND ?
                 ORDER BY e.disbursement_date DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$dateFrom, $dateTo]);
    } else {
        $sql  = "SELECT e.id, e.category, e.description, e.amount,
                        e.disbursement_date, e.payee, e.supporting_doc_path,
                        u_rec.username AS recorded_by_name,
                        u_apr.username AS approved_by_name,
                        CASE WHEN e.approved_by IS NOT NULL THEN 'Approved'
                             ELSE 'Pending Approval' END AS approval_status
                 FROM expenditures e
                 JOIN users u_rec ON u_rec.id = e.recorded_by
                 LEFT JOIN users u_apr ON u_apr.id = e.approved_by
                 WHERE e.category = ? AND e.disbursement_date BETWEEN ? AND ?
                 ORDER BY e.disbursement_date DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$categoryFilter, $dateFrom, $dateTo]);
    }
    $expenditures = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
 
// Apply search filter (PHP-side on description/payee)
if ($searchQuery !== '') {
    $q = strtolower($searchQuery);
    $expenditures = array_filter($expenditures, function($row) use ($q) {
        return str_contains(strtolower($row['description']), $q)
            || str_contains(strtolower($row['payee']), $q);
    });
    $expenditures = array_values($expenditures);
}
 
// Compute subtotals
foreach ($expenditures as $row) {
    $cat = $row['category'];
    $subtotals[$cat] = ($subtotals[$cat] ?? 0) + $row['amount'];
    $totalAmount += $row['amount'];
}

$office_address = 'Brgy. Sta. Rosa 1, Noveleta, Cavite';
$contact_number = '+63 912 000 0000';
$barangay_hotline = 'Emergency Hotline 911';
$emergency_numbers = 'PNP 166, Fire 1555, NDRRMC 825-0000';

// Static placeholder values
$profile_percent       = 80;
$sidebar_missing_summary = 'Complete your profile';
$unread_count          = 3;
$notifications         = [
    ['is_read' => 0, 'link' => '#', 'title' => 'Budget Approved', 'message' => 'Q2 budget has been approved.', 'created_at' => '2025-05-24 09:00:00'],
    ['is_read' => 1, 'link' => '#', 'title' => 'New Payment Recorded', 'message' => 'Business permit fee collected.', 'created_at' => '2025-05-23 14:30:00'],
];
$initials              = 'MT';
$first_name            = 'Maria';
$display_name          = 'Maria Torres';
$user                  = ['email' => 'maria.torres@brgy-starosa1.gov.ph'];
$today_line            = date('l, F j, Y');
$greeting              = 'Good morning';
$pending_count         = '₱ 48,250';
$ready_count           = '₱ 12,800';
$total_count           = '64%';
$document_processing_times = [];

// Role & fiscal year
$role_label            = 'Barangay Treasurer';
$fiscal_year           = 'Fiscal Year ' . date('Y');

function e($val) { return htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8'); }
function rd_date($val) { return date('M j, Y g:i A', strtotime($val)); }

// --- STATIC DEMO DATA (replace with real DB queries later) ---
$today_collections       = 12450.00;
$month_collections       = 187320.50;
$last_month_collections  = 162800.00;
$month_expenditures      = 134200.75;
$expenditure_threshold   = 150000.00; // threshold for "red" warning
$net_balance             = $month_collections - $month_expenditures;
$annual_budget           = 2000000.00;
$ytd_spent               = 534200.75;
$budget_utilization      = ($annual_budget > 0) ? ($ytd_spent / $annual_budget) * 100 : 0;

// Month-over-month % change for collections
$mom_change = ($last_month_collections > 0)
    ? (($month_collections - $last_month_collections) / $last_month_collections) * 100
    : 0;
$mom_positive = $mom_change >= 0;

// Color logic
$exp_class    = ($month_expenditures >= $expenditure_threshold) ? 'text-danger fw-bold' : 'text-dark';
$net_class    = ($net_balance >= 0) ? 'text-success fw-bold' : 'text-danger fw-bold';
$util_color   = ($budget_utilization >= 90) ? 'danger' : (($budget_utilization >= 70) ? 'warning' : 'success'); 
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Barangay Financial Management - Barangay Sta. Rosa 1</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/resident_dashboard.css" />
  <link rel="stylesheet" href="assets/css/finance.css">

  <!-- ── Expenditures section: theme-aware overrides ─────────────────────────
       All exp-* colours are mapped to the app's root tokens so they respond
       to body.dark-mode automatically. No hardcoded light-only values here.
  ─────────────────────────────────────────────────────────────────────────── -->
  <style>
  /* ── Remap exp-* onto app tokens ── */
  .exp-wrap,
  .exp-card,
  .exp-modal,
  .exp-subtotals,
  .exp-modal-bg {
    --exp-bg:         var(--bg);
    --exp-surface:    var(--surface);
    --exp-surface2:   var(--surface2);
    --exp-border:     var(--border);
    --exp-ink:        var(--text);
    --exp-muted:      var(--muted, #6b7a99);
    --exp-accent:     var(--accent, #f0c040);
    --exp-accent-lt:  rgba(240,192,64,.12);
    --exp-green:      var(--accent2, #3dd68c);
    --exp-green-lt:   rgba(61,214,140,.13);
    --exp-yellow:     var(--accent, #f0c040);
    --exp-yellow-lt:  rgba(240,192,64,.13);
    --exp-grey:       var(--text2, #a0aabf);
    --exp-grey-lt:    var(--surface2);
    --exp-shadow:     0 1px 4px rgba(0,0,0,.35), 0 4px 16px rgba(0,0,0,.25);
    color:            var(--exp-ink);
    font-family:      "Plus Jakarta Sans", system-ui, sans-serif;
  }

  /* ── Layout ── */
  .exp-wrap      { color: var(--exp-ink); }
  .exp-title     { font-size:1.25rem; font-weight:700; letter-spacing:-.3px; margin:0; color:var(--exp-ink); }
  .exp-title small{ font-size:.8rem; font-weight:500; color:var(--exp-muted); display:block; margin-top:2px; }
  .exp-header    { display:flex; align-items:center; justify-content:space-between;
                   flex-wrap:wrap; gap:12px; margin-bottom:20px; }

  /* ── Controls ── */
  .exp-controls        { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:18px; align-items:flex-end; }
  .exp-control-group   { display:flex; flex-direction:column; gap:4px; }
  .exp-control-group label {
    font-size:.72rem; font-weight:600; color:var(--exp-muted);
    text-transform:uppercase; letter-spacing:.5px;
  }

  .exp-input,
  .exp-select {
    height:38px; padding:0 12px;
    border:1.5px solid var(--exp-border);
    border-radius:6px;
    font-family:inherit; font-size:.875rem;
    color:var(--exp-ink);
    background:var(--exp-surface2);
    transition:border-color .15s, box-shadow .15s;
    outline:none;
  }
  .exp-input:focus,
  .exp-select:focus {
    border-color:var(--exp-accent);
    box-shadow:0 0 0 3px rgba(240,192,64,.18);
  }
  .exp-search {
    width:230px; padding-left:36px;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236b7a99' stroke-width='2'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='m21 21-4.35-4.35'/%3E%3C/svg%3E");
    background-repeat:no-repeat; background-position:10px center;
  }

  /* ── Category tabs ── */
  .exp-cat-tabs  { display:flex; gap:4px; flex-wrap:wrap; }
  .exp-cat-tab   {
    padding:5px 12px; border-radius:20px;
    border:1.5px solid var(--exp-border);
    background:var(--exp-surface2);
    font-size:.8rem; font-weight:600; color:var(--exp-muted);
    cursor:pointer; text-decoration:none; transition:all .15s; white-space:nowrap;
  }
  .exp-cat-tab:hover  { border-color:var(--exp-accent); color:var(--exp-accent); }
  .exp-cat-tab.active { background:var(--exp-accent); border-color:var(--exp-accent); color:#111; }

  /* ── Buttons ── */
  .exp-btn {
    display:inline-flex; align-items:center; gap:6px; height:38px;
    padding:0 16px; border-radius:6px; border:none;
    font-family:inherit; font-size:.875rem; font-weight:600;
    cursor:pointer; text-decoration:none; transition:all .15s; white-space:nowrap;
  }
  .exp-btn--primary { background:var(--exp-accent); color:#111; }
  .exp-btn--primary:hover { filter:brightness(1.1); transform:translateY(-1px); box-shadow:0 4px 14px rgba(240,192,64,.3); }
  .exp-btn--ghost   {
    background:transparent; color:var(--exp-muted);
    border:1.5px solid var(--exp-border);
  }
  .exp-btn--ghost:hover { background:var(--exp-surface2); color:var(--exp-ink); }
  .exp-btn--sm      { height:30px; padding:0 10px; font-size:.78rem; }
  .exp-btn--icon    { padding:0 10px; }

  /* ── Card / Table ── */
  .exp-card        { background:var(--exp-surface); border:1px solid var(--exp-border); border-radius:10px; box-shadow:var(--exp-shadow); overflow:hidden; }
  .exp-table-wrap  { overflow-x:auto; }
  .exp-table       { width:100%; border-collapse:collapse; font-size:.875rem; }
  .exp-table thead tr   { background:var(--exp-surface2); }
  .exp-table th    { padding:11px 14px; text-align:left; font-size:.72rem; font-weight:700;
                     color:var(--exp-muted); text-transform:uppercase; letter-spacing:.6px;
                     border-bottom:1.5px solid var(--exp-border); white-space:nowrap; }
  .exp-table td    { padding:12px 14px; border-bottom:1px solid var(--exp-border); vertical-align:middle; color:var(--exp-ink); }
  .exp-table tbody tr:last-child td { border-bottom:none; }
  .exp-table tbody tr:hover { background:var(--exp-surface2); }
  .exp-table .col-amount  { font-weight:600; text-align:right; white-space:nowrap; }
  .exp-table .col-actions { text-align:right; white-space:nowrap; }
  .exp-table .col-date    { white-space:nowrap; }

  /* ── Badges ── */
  .exp-badge {
    display:inline-flex; align-items:center; gap:5px;
    padding:3px 9px; border-radius:20px; font-size:.72rem; font-weight:700; white-space:nowrap;
  }
  .exp-badge::before { content:''; width:6px; height:6px; border-radius:50%; flex-shrink:0; }
  .exp-badge--approved  { background:var(--exp-green-lt);  color:var(--exp-green); }
  .exp-badge--approved::before  { background:var(--exp-green); }
  .exp-badge--pending   { background:var(--exp-yellow-lt); color:var(--exp-yellow); }
  .exp-badge--pending::before   { background:var(--exp-yellow); }
  .exp-badge--disbursed { background:var(--exp-grey-lt);   color:var(--exp-grey); }
  .exp-badge--disbursed::before { background:var(--exp-grey); }

  /* ── Category pill ── */
  .exp-cat-pill {
    display:inline-flex; align-items:center; gap:5px; padding:3px 8px;
    border-radius:6px; font-size:.75rem; font-weight:600;
    background:var(--exp-accent-lt); color:var(--exp-accent);
  }

  /* ── Subtotals ── */
  .exp-subtotals       { padding:14px 18px; border-top:2px solid var(--exp-border); background:var(--exp-surface2); }
  .exp-subtotals h4    { margin:0 0 10px; font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--exp-muted); }
  .exp-subtotals-grid  { display:flex; flex-wrap:wrap; gap:10px 24px; }
  .exp-sub-item        { display:flex; align-items:baseline; gap:6px; font-size:.85rem; }
  .exp-sub-label       { color:var(--exp-muted); }
  .exp-sub-val         { font-weight:700; color:var(--exp-ink); }
  .exp-sub-total       {
    border-top:1.5px solid var(--exp-border); margin-top:10px; padding-top:10px;
    display:flex; justify-content:flex-end; align-items:center; gap:10px; font-size:.875rem;
  }
  .exp-sub-total strong { font-size:1rem; color:var(--exp-accent); }

  /* ── Meta row ── */
  .exp-meta { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:14px; font-size:.825rem; color:var(--exp-muted); }
  .exp-meta strong { color:var(--exp-ink); }

  /* ── Empty state ── */
  .exp-empty      { padding:60px 20px; text-align:center; color:var(--exp-muted); }
  .exp-empty-icon { font-size:2.5rem; margin-bottom:12px; }
  .exp-empty h3   { margin:0 0 6px; font-size:1rem; color:var(--exp-ink); }
  .exp-empty p    { margin:0; font-size:.875rem; }

  /* ── Modals ── */
  .exp-modal-bg {
    display:none; position:fixed; inset:0;
    background:rgba(0,0,0,.65); backdrop-filter:blur(3px);
    z-index:1000; align-items:center; justify-content:center; padding:20px;
  }
  .exp-modal-bg.open { display:flex; }
  .exp-modal {
    background:var(--exp-surface); border:1px solid var(--exp-border);
    border-radius:10px; box-shadow:var(--exp-shadow);
    width:100%; max-width:560px; max-height:90vh; overflow-y:auto;
    animation:expModalIn .2s ease;
  }
  @keyframes expModalIn {
    from { opacity:0; transform:translateY(16px) scale(.97); }
    to   { opacity:1; transform:translateY(0)    scale(1); }
  }
  .exp-modal-header {
    padding:18px 20px 14px; border-bottom:1px solid var(--exp-border);
    display:flex; align-items:center; justify-content:space-between;
  }
  .exp-modal-header h3 { margin:0; font-size:1rem; font-weight:700; color:var(--exp-ink); }
  .exp-modal-close {
    background:none; border:none; cursor:pointer; font-size:1.3rem;
    color:var(--exp-muted); line-height:1; padding:2px 6px; border-radius:4px;
  }
  .exp-modal-close:hover { background:var(--exp-surface2); color:var(--exp-ink); }
  .exp-modal-body  { padding:20px; }

  /* ── Detail grid inside modal ── */
  .exp-detail-grid  { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
  .exp-detail-item  { display:flex; flex-direction:column; gap:3px; }
  .exp-detail-item.full { grid-column:1/-1; }
  .exp-detail-label { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--exp-muted); }
  .exp-detail-val   { font-size:.9rem; color:var(--exp-ink); word-break:break-word; }
  .exp-detail-val.mono { font-weight:700; font-size:1.1rem; color:var(--exp-accent); }

  /* ── Approval history ── */
  .exp-approval-hist    { margin-top:16px; padding-top:16px; border-top:1px solid var(--exp-border); }
  .exp-approval-hist h4 { margin:0 0 10px; font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--exp-muted); }
  .exp-hist-item        { display:flex; gap:10px; font-size:.85rem; margin-bottom:8px; color:var(--exp-ink); }
  .exp-hist-dot         { width:8px; height:8px; border-radius:50%; margin-top:5px; flex-shrink:0; }
  .exp-hist-dot--green  { background:var(--exp-green); }
  .exp-hist-dot--yellow { background:var(--exp-yellow); }

  /* ── Large-amount warning ── */
  .exp-large-warn {
    background:var(--exp-yellow-lt); border:1px solid rgba(240,192,64,.35);
    border-radius:6px; padding:8px 12px; font-size:.8rem;
    color:var(--exp-yellow); margin-top:12px; display:flex; gap:6px;
  }

  /* ── Add-modal inputs inherit surface ── */
  #expAddModal .exp-input,
  #expAddModal .exp-select {
    background:var(--exp-surface2);
    color:var(--exp-ink);
    border-color:var(--exp-border);
  }

  @media (max-width:640px) {
    .exp-search { width:100%; }
    .exp-detail-grid { grid-template-columns:1fr; }
    .exp-controls { gap:8px; }
  }
  </style>

  <style>
    .welcome-meta {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-top: 12px;
      flex-wrap: wrap;
    }

    .role-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: #16a34a;
      color: #fff;
      font-size: 12px;
      font-weight: 600;
      padding: 4px 12px;
      border-radius: 999px;
      letter-spacing: 0.02em;
    }

    .fiscal-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: rgba(255,255,255,0.15);
      color: #fff;
      font-size: 12px;
      font-weight: 500;
      padding: 4px 12px;
      border-radius: 999px;
      border: 1px solid rgba(255,255,255,0.3);
      backdrop-filter: blur(4px);
    }

    .role-badge i,
    .fiscal-badge i {
      font-size: 11px;
    }

    .stat-grid {
      display: grid;
      grid-template-columns: repeat(1, 1fr);
      gap: 0.75rem; /* tighten the gap slightly */
    }

    .status-card {
      min-width: 0; /* prevents grid blowout */
    }

    .quick-action-btn {
      display: flex;
      align-items: center;
      gap: 10px;
      flex: 1;
      min-width: 180px;
      padding: 16px 20px;
      border-radius: 10px;
      background: var(--card-bg, #1e2a3a);
      border: 1px solid var(--border-color, #2e3d50);
      color: var(--text-primary, #fff);
      font-size: 14px;
      font-weight: 600;
      text-decoration: none;
      transition: background 0.2s, border-color 0.2s, transform 0.15s;
    }

    .quick-action-btn:hover {
      background: var(--primary, #eab308);
      color: #000;
      border-color: var(--primary, #eab308);
      transform: translateY(-2px);
    }

    .quick-action-btn i {
      font-size: 18px;
    }

    /* Tablet — 2 per row */
    @media (min-width: 576px) {
      .stat-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }

    /* Large tablet — 3 per row */
    @media (min-width: 768px) {
      .stat-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
      }
    }

    /* Desktop — all 5 in one row */
    @media (min-width: 1200px) {
      .stat-grid {
        grid-template-columns: repeat(5, minmax(0, 1fr));
      }
    }
  </style>
</head>
<body>
  <aside class="resident-sidebar" id="residentSidebar" aria-label="Resident sidebar">
    <a class="sidebar-brand" href="#" aria-label="Go to dashboard">
      <span class="sidebar-brand__seal"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span>
      <span>
        <strong>Brgy. Sta. Rosa 1</strong>
        <small>Resident Portal</small>
      </span>
    </a>

    <nav class="sidebar-menu" aria-label="Treasurer menu">

      <div class="sidebar-group">
        <a class="sidebar-link <?= ($tab === 'dashboard' || $tab === '') ? 'is-active' : '' ?>" href="finance_ad.php?tab=dashboard">
          <i class="fa-solid fa-house"></i><span>Dashboard</span>
        </a>
      </div>

      <div class="sidebar-group">
        <span class="sidebar-section-label">COLLECTIONS</span>
        <a class="sidebar-link <?= $tab === 'collections' ? 'is-active' : '' ?>" href="finance_ad.php?tab=collections">
          <i class="fa-solid fa-money-bill-transfer"></i><span>All Collections</span>
        </a>
        <a class="sidebar-link <?= $tab === 'record' ? 'is-active' : '' ?>" href="finance_ad.php?tab=record">
          <i class="fa-solid fa-cash-register"></i><span>Record Payment</span>
        </a>
        <a class="sidebar-link <?= $tab === 'receipts' ? 'is-active' : '' ?>" href="finance_ad.php?tab=receipts">
          <i class="fa-solid fa-file-invoice-dollar"></i><span>Official Receipts</span>
        </a>
      </div>

      <div class="sidebar-group">
        <span class="sidebar-section-label">EXPENDITURES</span>
        <a class="sidebar-link" href="finance_ad.php?tab=expenditures">
          <i class="fa-solid fa-money-bill-wave"></i><span>All Expenditures</span>
        </a>
        <a class="sidebar-link" href="finance_ad.php?tab=add-exp">
          <i class="fa-solid fa-circle-plus"></i><span>Add Expenditure</span>
        </a>
      </div>

      <div class="sidebar-group">
        <span class="sidebar-section-label">BUDGET</span>
        <a class="sidebar-link" href="finance_ad.php?tab=budget">
          <i class="fa-solid fa-chart-pie"></i><span>Budget Management</span>
        </a>
      </div>

      <div class="sidebar-group">
        <span class="sidebar-section-label">REPORTS</span>
        <a class="sidebar-link" href="finance_ad.php?tab=reports">
          <i class="fa-solid fa-file-invoice-dollar"></i><span>Financial Reports</span>
        </a>
      </div>

      <div class="sidebar-group">
        <span class="sidebar-section-label">RECORDS</span>
        <a class="sidebar-link" href="admin/issued.php">
          <i class="fa-solid fa-file-lines"></i><span>Doc Issuance Log</span>
        </a>
        <a class="sidebar-link" href="admin/residents.php">
          <i class="fa-solid fa-users"></i><span>Resident List</span>
        </a>
      </div>

      <div class="sidebar-group">
        <span class="sidebar-section-label">ACCOUNT</span>
        <a class="sidebar-link" href="admin/profile.php">
          <i class="fa-solid fa-user"></i><span>My Profile</span>
        </a>
        <a class="sidebar-link sidebar-link--danger" href="../logout.php">
          <i class="fa-solid fa-right-from-bracket"></i><span>Logout</span>
        </a>
      </div>

    </nav>

    <div class="sidebar-completion" aria-label="Profile completion">
      <div class="sidebar-completion__top">
        <span>Profile completion</span>
        <strong><?= e($profile_percent) ?>%</strong>
      </div>
      <div class="sidebar-progress" aria-hidden="true"><span style="width: <?= e($profile_percent) ?>%"></span></div>
      <small><?= e($sidebar_missing_summary) ?></small>
    </div>

    <div class="sidebar-card">
      <span class="sidebar-card__label">Office Hours</span>
      <strong>Mon-Fri, 8:00 AM - 5:00 PM</strong>
      <small>Barangay Hall, Sta. Rosa 1</small>
    </div>
  </aside>

  <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>

  <div class="resident-shell">
    <header class="resident-topbar">
      <div class="topbar-left">
        <button class="icon-button hamburger-button" id="sidebarToggle" type="button" aria-label="Open sidebar" aria-expanded="false">
          <i class="fa-solid fa-bars" aria-hidden="true"></i>
        </button>
        <a class="topbar-brand" href="finance_ad.php">
          <span class="topbar-brand__seal"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span>
          <span>Brgy. Sta. Rosa 1</span>
        </a>
      </div>

      <div class="topbar-actions">
        <button class="icon-button theme-toggle" id="themeToggle" type="button" aria-label="Switch to dark mode" aria-pressed="false">
          <i class="fa-solid fa-moon" aria-hidden="true"></i>
        </button>

        <div class="dropdown-wrap">
          <button class="icon-button notification-button" id="notificationToggle" type="button" aria-label="Open notifications" aria-expanded="false" data-notification-count-url="notifications.php?count=1">
            <i class="fa-solid fa-bell" aria-hidden="true"></i>
            <?php if ($unread_count > 0): ?>
              <span class="notif-badge"><?= e(min($unread_count, 9)) ?></span>
            <?php else: ?>
              <span class="notif-dot" aria-hidden="true"></span>
            <?php endif; ?>
          </button>
          <div class="dropdown-panel notification-panel" id="notificationPanel" aria-label="Notifications">
            <div class="dropdown-panel__header">
              <strong>Notifications</strong>
              <a class="text-link" href="notifications.php"><?= e($unread_count) ?> unread</a>
            </div>
            <div class="notification-list">
              <?php if ($notifications): ?>
                <?php foreach ($notifications as $notice): ?>
                  <a class="notification-item <?= empty($notice['is_read']) ? 'is-unread' : '' ?>" href="<?= e($notice['link'] ?: '#') ?>">
                    <span class="notification-item__icon"><i class="fa-solid fa-circle-info"></i></span>
                    <span>
                      <strong><?= e($notice['title']) ?></strong>
                      <small><?= e($notice['message']) ?></small>
                      <em><?= e(rd_date($notice['created_at'])) ?></em>
                    </span>
                  </a>
                <?php endforeach; ?>
              <?php else: ?>
                <p class="empty-note">No notifications yet.</p>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="dropdown-wrap">
          <button class="profile-button" id="profileToggle" type="button" aria-label="Open profile menu" aria-expanded="false">
            <span class="avatar"><?= e($initials) ?></span>
            <span class="profile-button__name"><?= e($first_name) ?></span>
            <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
          </button>
          <div class="dropdown-panel profile-panel" id="profilePanel" aria-label="Profile menu">
            <div class="profile-summary">
              <span class="avatar avatar--large"><?= e($initials) ?></span>
              <strong><?= e($display_name) ?></strong>
              <small><?= e($user['email']) ?></small>
            </div>
            <a href="profile.php"><i class="fa-solid fa-user"></i> My Profile</a>
            <a href="profile.php#account"><i class="fa-solid fa-lock"></i> Change Password</a>
            <a class="danger" href="../logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
          </div>
        </div>
      </div>
    </header>

    <?php if ($tab === 'dashboard'): ?>
      <main class="resident-main" id="dashboard">
        <section class="welcome-banner priority-high">
          <div>
            <div class="welcome-eyebrow"><?= e($today_line) ?></div>
            <h1><?= e($greeting) ?>, <?= e($first_name) ?></h1>
            <p>Track your barangay requests, announcements, and case updates in one place.</p>
            <div class="welcome-meta">
              <span class="role-badge">
                <i class="fa-solid fa-id-badge"></i> <?= e($role_label) ?>
              </span>
              <span class="fiscal-badge">
                <i class="fa-solid fa-calendar-check"></i> <?= e($fiscal_year) ?>
              </span>
            </div>
          </div>
        </section>

        <section class="stat-grid priority-high" aria-label="Financial summary">
          <!-- 1. Today's Collections -->
          <div class="status-card status-card--success">
            <span class="status-card__icon"><i class="fa-solid fa-calendar-day"></i></span>
            <span class="finance-card__body">
              <strong class="text-success">₱<?= number_format($today_collections, 2) ?></strong>
              <small>Today's Collections</small>
            </span>
          </div>

          <!-- 2. This Month Collections -->
          <div class="status-card status-card--success">
            <span class="status-card__icon"><i class="fa-solid fa-sack-dollar"></i></span>
            <span class="finance-card__body">
              <strong class="text-success">₱<?= number_format($month_collections, 2) ?></strong>
              <small>
                This Month Collections
                <span class="badge <?= $mom_positive ? 'bg-success' : 'bg-danger' ?> ms-1">
                  <i class="fa-solid fa-arrow-<?= $mom_positive ? 'up' : 'down' ?>"></i>
                  <?= number_format(abs($mom_change), 1) ?>% vs last month
                </span>
              </small>
            </span>
          </div>

          <!-- 3. This Month Expenditures -->
          <div class="status-card status-card--warning">
            <span class="status-card__icon"><i class="fa-solid fa-money-bill-wave"></i></span>
            <span class="finance-card__body">
              <strong class="<?= $exp_class ?>">₱<?= number_format($month_expenditures, 2) ?></strong>
              <small>This Month Expenditures</small>
            </span>
          </div>

          <!-- 4. Net Balance (Month) -->
          <div class="status-card <?= $net_balance >= 0 ? 'status-card--success' : 'status-card--danger' ?>">
            <span class="status-card__icon"><i class="fa-solid fa-scale-balanced"></i></span>
            <span class="finance-card__body">
              <strong class="<?= $net_class ?>"><?= $net_balance >= 0 ? '+' : '' ?>₱<?= number_format($net_balance, 2) ?></strong>
              <small>Net Balance (This Month)</small>
            </span>
          </div>

          <!-- 5. Budget Utilization -->
          <div class="status-card status-card--info">
            <span class="status-card__icon"><i class="fa-solid fa-chart-pie"></i></span>
            <span class="finance-card__body">
              <strong><?= number_format($budget_utilization, 1) ?>%</strong>
              <small>
                Budget Utilization (YTD)
                <div class="progress mt-1" style="height:6px;">
                  <div
                    class="progress-bar bg-<?= $util_color ?>"
                    role="progressbar"
                    style="width: <?= min($budget_utilization, 100) ?>%"
                    aria-valuenow="<?= $budget_utilization ?>"
                    aria-valuemin="0"
                    aria-valuemax="100">
                  </div>
                </div>
              </small>
            </span>
          </div>

        </section>

        <section class="panel-grid-full priority-medium">
          <div class="dashboard-panel" id="collections-chart">
            <div class="panel-header">
              <div>
                <h2>Monthly Collections</h2>
                <small style="color: var(--text-muted, #888);">Last 6 months — collections vs expenditures</small>
              </div>
              <a href="admin/finance.php?tab=reports" class="view-all-link">
                View full report <i class="fa-solid fa-arrow-right"></i>
              </a>
            </div>
            <div class="announcement-list">
              <canvas id="revenueChart"></canvas>
            </div>
          </div>
        </section>

        <section class="high-grid priority-high">

          <!-- Recent Collections Table -->
          <div class="dashboard-panel quick-panel" id="recent-collections" style="grid-column: 1 / -1;">
            <div class="panel-header">
              <div>
                <h2>Recent Collections</h2>
                <small style="color: var(--text-muted, #888);">Last 8 transactions</small>
              </div>
              <a class="text-link" href="admin/finance.php?tab=collections">View all collections</a>
            </div>
            <div class="quick-actions">
              <table style="width:100%; table-layout:fixed;">
                <thead>
                  <tr>
                    <th>No.</th>
                    <th>Source Type</th>
                    <th>Amount</th>
                    <th>Resident Name</th>
                    <th>Date</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td style="color:var(--text-muted);font-size:11px;">001</td>
                    <td><span class="badge collection">Business Permit Fee</span></td>
                    <td class="amount">₱ 2,500.00</td>
                    <td>Juan dela Cruz</td>
                    <td style="color:var(--text-muted);font-size:11px;">May 24, 2025</td>
                    <td><a href="admin/finance.php?tab=collections&id=1" class="btn-view"><i class="fa-solid fa-eye"></i> View</a></td>
                  </tr>
                  <tr>
                    <td style="color:var(--text-muted);font-size:11px;">002</td>
                    <td><span class="badge collection">Document Request Fee</span></td>
                    <td class="amount">₱ 150.00</td>
                    <td>Maria Santos</td>
                    <td style="color:var(--text-muted);font-size:11px;">May 23, 2025</td>
                    <td><a href="admin/finance.php?tab=collections&id=2" class="btn-view"><i class="fa-solid fa-eye"></i> View</a></td>
                  </tr>
                  <tr>
                    <td style="color:var(--text-muted);font-size:11px;">003</td>
                    <td><span class="badge collection">Certificate Fee</span></td>
                    <td class="amount">₱ 300.00</td>
                    <td>Pedro Reyes</td>
                    <td style="color:var(--text-muted);font-size:11px;">May 21, 2025</td>
                    <td><a href="admin/finance.php?tab=collections&id=3" class="btn-view"><i class="fa-solid fa-eye"></i> View</a></td>
                  </tr>
                  <tr>
                    <td style="color:var(--text-muted);font-size:11px;">004</td>
                    <td><span class="badge collection">Barangay Clearance</span></td>
                    <td class="amount">₱ 200.00</td>
                    <td>Ana Gomez</td>
                    <td style="color:var(--text-muted);font-size:11px;">May 20, 2025</td>
                    <td><a href="admin/finance.php?tab=collections&id=4" class="btn-view"><i class="fa-solid fa-eye"></i> View</a></td>
                  </tr>
                  <tr>
                    <td style="color:var(--text-muted);font-size:11px;">005</td>
                    <td><span class="badge collection">Business Permit Fee</span></td>
                    <td class="amount">₱ 2,500.00</td>
                    <td>Carlos Mendoza</td>
                    <td style="color:var(--text-muted);font-size:11px;">May 19, 2025</td>
                    <td><a href="admin/finance.php?tab=collections&id=5" class="btn-view"><i class="fa-solid fa-eye"></i> View</a></td>
                  </tr>
                  <tr>
                    <td style="color:var(--text-muted);font-size:11px;">006</td>
                    <td><span class="badge collection">Indigency Certificate</span></td>
                    <td class="amount">₱ 100.00</td>
                    <td>Rosa Villanueva</td>
                    <td style="color:var(--text-muted);font-size:11px;">May 18, 2025</td>
                    <td><a href="admin/finance.php?tab=collections&id=6" class="btn-view"><i class="fa-solid fa-eye"></i> View</a></td>
                  </tr>
                  <tr>
                    <td style="color:var(--text-muted);font-size:11px;">007</td>
                    <td><span class="badge collection">Cedula</span></td>
                    <td class="amount">₱ 75.00</td>
                    <td>Jose Bautista</td>
                    <td style="color:var(--text-muted);font-size:11px;">May 17, 2025</td>
                    <td><a href="admin/finance.php?tab=collections&id=7" class="btn-view"><i class="fa-solid fa-eye"></i> View</a></td>
                  </tr>
                  <tr>
                    <td style="color:var(--text-muted);font-size:11px;">008</td>
                    <td><span class="badge collection">Document Request Fee</span></td>
                    <td class="amount">₱ 150.00</td>
                    <td>Luisa Fernandez</td>
                    <td style="color:var(--text-muted);font-size:11px;">May 16, 2025</td>
                    <td><a href="admin/finance.php?tab=collections&id=8" class="btn-view"><i class="fa-solid fa-eye"></i> View</a></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

        </section>

        <section class="low-grid priority-low">
          <div class="dashboard-panel" id="pending-expenditure-approvals" style="grid-column: 1 / -1;">
            <div class="panel-header">
              <div>
                <h2>Pending Expenditure Approvals</h2>
                <small style="color: var(--text-muted, #888);">Expenditures awaiting Captain approval</small>
              </div>
            </div>

            <div class="alert-note" style="margin: 0 0 16px 0; padding: 10px 16px; background: rgba(234,179,8,0.1); border-left: 4px solid #eab308; border-radius: 6px; color: #eab308; font-size: 13px;">
              <i class="fa-solid fa-circle-info"></i>
              Expenditures over ₱5,000 require Captain approval before disbursement.
            </div>

            <div style="display:block;">
              <table style="width:100%; table-layout:fixed;">
                <thead>
                  <tr>
                    <th>Category</th>
                    <th>Amount</th>
                    <th>Description</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($pending_expenditures)): ?>
                    <?php foreach ($pending_expenditures as $expenditure): ?>
                      <tr>
                        <td><?= e($expenditure['category']) ?></td>
                        <td class="amount">₱ <?= e(number_format((float)$expenditure['amount'], 2)) ?></td>
                        <td><?= e($expenditure['description']) ?></td>
                        <td><span class="badge pending">Pending</span></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="4" style="text-align:center; color:var(--text-muted, #888); padding: 24px 0;">
                        <i class="fa-solid fa-check-circle" style="margin-right:6px;"></i>No pending expenditures for approval.
                      </td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <section id="quick-actions-section" style="padding: 16px 0;">
          <div class="dashboard-panel" style="grid-column: 1 / -1;">
            <div class="panel-header">
              <div>
                <h2>Quick Actions</h2>
              </div>
            </div>
            <div style="display: flex; gap: 16px; flex-wrap: wrap; padding: 8px 0;">
              <a href="admin/finance.php?tab=record" class="quick-action-btn">
                <i class="fa-solid fa-money-bill-wave"></i>
                <span>Record Collection</span>
              </a>
              <a href="admin/finance.php?tab=add-exp" class="quick-action-btn">
                <i class="fa-solid fa-file-invoice-dollar"></i>
                <span>Add Expenditure</span>
              </a>
              <a href="admin/finance.php?tab=reports" class="quick-action-btn">
                <i class="fa-solid fa-chart-bar"></i>
                <span>View Reports</span>
              </a>
              <a href="admin/finance.php?tab=budget" class="quick-action-btn">
                <i class="fa-solid fa-wallet"></i>
                <span>Budget Overview</span>
              </a>
            </div>
          </div>
        </section>
      </main>

    <?php elseif ($tab === 'collections'): ?>
      <main class="resident-main" id="collections">
        <!-- paste the <section class="col-section"> block here -->
        <section class="col-section">

          <!-- Header -->
          <div class="col-header">
            <h2><i class="fa-solid fa-money-bill-transfer"></i> All Collections</h2>
            <div class="col-header-actions">
              <a href="<?= col_sanitize($exportUrl) ?>" class="col-btn col-btn-outline">
                <i class="fa-solid fa-file-csv"></i> Export CSV
              </a>
              <button class="col-btn col-btn-primary" onclick="colOpenRecordModal()">
                <i class="fa-solid fa-plus"></i> Record New Payment
              </button>
            </div>
          </div>

          <!-- Filters -->
          <div class="col-filter-card">
            <form method="GET" id="colFilterForm">
              <input type="hidden" name="tab"  value="collections">
              <input type="hidden" name="type" id="colTypeInput" value="<?= col_sanitize($sourceType) ?>">
              <div class="col-filter-row">
                <div class="col-fg" style="flex:1; min-width:200px;">
                  <label>Search</label>
                  <div class="col-search-wrap">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="search" class="col-input"
                          placeholder="OR number or resident name…"
                          value="<?= col_sanitize($search) ?>">
                  </div>
                </div>
                <div class="col-fg">
                  <label>From</label>
                  <input type="date" name="date_from" class="col-input" value="<?= col_sanitize($dateFrom) ?>">
                </div>
                <div class="col-fg">
                  <label>To</label>
                  <input type="date" name="date_to" class="col-input" value="<?= col_sanitize($dateTo) ?>">
                </div>
                <div class="col-fg" style="justify-content:flex-end;">
                  <label>&nbsp;</label>
                  <button type="submit" class="col-btn col-btn-primary">
                    <i class="fa-solid fa-filter"></i> Apply
                  </button>
                </div>
              </div>
            </form>
          </div>

          <!-- Type tabs -->
          <div class="col-tabs">
            <?php
            $colTabs = [
              'all'             => ['All',             'fa-list'],
              'document_fee'    => ['Document Fees',   'fa-file-lines'],
              'business_permit' => ['Business Permits','fa-store'],
              'cedula'          => ['Cedula',          'fa-id-card'],
              'other'           => ['Other',           'fa-circle-dot'],
            ];
            foreach ($colTabs as $k => [$lbl, $ico]):
            ?>
            <a href="#"
              class="col-tab <?= $sourceType === $k ? 'active' : '' ?>"
              onclick="colSetTab('<?= $k ?>'); return false;">
              <i class="fa-solid <?= $ico ?>"></i> <?= $lbl ?>
            </a>
            <?php endforeach; ?>
          </div>

          <!-- Stats bar -->
          <div class="col-stats">
            <span class="col-count">
              Showing <strong><?= count($collections) ?></strong>
              record<?= count($collections) !== 1 ? 's' : '' ?> &nbsp;·&nbsp;
              <strong><?= date('M d, Y', strtotime($dateFrom)) ?></strong>
              — <strong><?= date('M d, Y', strtotime($dateTo)) ?></strong>
            </span>
            <span class="col-total-chip">
              <i class="fa-solid fa-peso-sign"></i>
              Running Total: <?= col_currency($runningTotal) ?>
            </span>
          </div>

          <!-- Table -->
          <div class="col-table-wrap">
            <table>
              <thead>
                <tr>
                  <th>OR Number</th>
                  <th>Source Type</th>
                  <th>Resident</th>
                  <th>Description</th>
                  <th style="text-align:right">Amount</th>
                  <th>Date</th>
                  <th>Collected By</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
              <?php if (empty($collections)): ?>
                <tr><td colspan="8">
                  <div class="col-empty">
                    <i class="fa-solid fa-inbox"></i>
                    <strong>No collections found</strong>
                    <p style="margin-top:5px;font-size:13px;">Try adjusting your filters or date range.</p>
                  </div>
                </td></tr>
              <?php else: ?>
                <?php foreach ($collections as $row):
                  $voided = (bool) $row['voided'];
                  $resName = trim($row['resident_name']);
                  $receiptJs = json_encode([
                    'or_number'     => $row['or_number'],
                    'source_type'   => col_type_label($row['source_type']),
                    'resident_name' => $resName ?: 'Walk-in',
                    'amount'        => number_format((float)$row['amount'], 2),
                    'description'   => $row['description'] ?? '',
                    'collected_at'  => $row['collected_at'],
                    'collected_by'  => $row['collected_by_name'],
                    'voided'        => $voided,
                    'void_reason'   => $row['void_reason'] ?? '',
                  ], JSON_HEX_APOS | JSON_HEX_TAG);
                ?>
                <tr class="<?= $voided ? 'col-voided' : '' ?>" data-col-id="<?= (int)$row['id'] ?>">
                  <td>
                    <span class="col-or"><?= col_sanitize($row['or_number']) ?></span>
                    <?php if ($voided): ?>
                      <br><span class="col-void-label"><i class="fa-solid fa-ban"></i> VOID</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="col-badge <?= col_type_badge($row['source_type']) ?>">
                      <?= col_type_label($row['source_type']) ?>
                    </span>
                  </td>
                  <td>
                    <?php if ($resName): ?>
                      <strong><?= col_sanitize($resName) ?></strong>
                    <?php else: ?>
                      <span class="col-no-res">— Walk-in —</span>
                    <?php endif; ?>
                  </td>
                  <td style="color:var(--muted,#6b7a99); max-width:180px; font-size:13px;">
                    <?= col_sanitize($row['description'] ?? '—') ?>
                  </td>
                  <td class="col-amt"><?= col_currency((float)$row['amount']) ?></td>
                  <td class="col-date">
                    <?= date('M d, Y', strtotime($row['collected_at'])) ?><br>
                    <small><?= date('h:i A', strtotime($row['collected_at'])) ?></small>
                  </td>
                  <td class="col-by"><?= col_sanitize($row['collected_by_name']) ?></td>
                  <td>
                    <div class="col-actions">
                      <!-- View Receipt -->
                      <button class="col-btn-icon" title="View Receipt"
                              onclick='colViewReceipt(<?= $receiptJs ?>)'>
                        <i class="fa-solid fa-receipt"></i>
                      </button>
                      <!-- Print OR -->
                      <button class="col-btn-icon" title="Print Receipt"
                              onclick='colPrintOR(<?= $receiptJs ?>)'>
                        <i class="fa-solid fa-print"></i>
                      </button>
                      <!-- Void (Treasurer/Admin, not yet voided) -->
                      <?php if (!$voided && in_array($currentUser['role'], ['treasurer','admin'])): ?>
                      <button class="col-btn-icon" title="Void Collection"
                              style="color:#dc2626;"
                              onclick="colOpenVoid(<?= (int)$row['id'] ?>, '<?= col_sanitize($row['or_number']) ?>')">
                        <i class="fa-solid fa-ban"></i>
                      </button>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
              </tbody>
            </table>
          </div>

        </section><!-- /col-section -->

        <!-- ── Toast ──────────────────────────────────────────────────────────────── -->
        <div id="colToast"></div>

        <!-- ── Receipt Modal ──────────────────────────────────────────────────────── -->
        <div class="col-backdrop" id="colReceiptModal">
          <div class="col-modal">
            <div class="col-modal-hd">
              <h3><i class="fa-solid fa-receipt"></i> Official Receipt</h3>
              <button class="col-modal-close" onclick="colCloseModal('colReceiptModal')">
                <i class="fa-solid fa-xmark"></i>
              </button>
            </div>
            <div class="col-modal-bd" id="colReceiptBody"></div>
            <div class="col-modal-ft">
              <button class="col-btn col-btn-outline" onclick="colCloseModal('colReceiptModal')">Close</button>
              <button class="col-btn col-btn-primary" onclick="colPrintFromModal()">
                <i class="fa-solid fa-print"></i> Print
              </button>
            </div>
          </div>
        </div>

        <!-- ── Void Modal ─────────────────────────────────────────────────────────── -->
        <div class="col-backdrop" id="colVoidModal">
          <div class="col-modal">
            <div class="col-modal-hd">
              <h3 style="color:#dc2626"><i class="fa-solid fa-ban"></i> Void Collection</h3>
              <button class="col-modal-close" onclick="colCloseModal('colVoidModal')">
                <i class="fa-solid fa-xmark"></i>
              </button>
            </div>
            <div class="col-modal-bd">
              <div class="col-void-warn">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span>Voiding <strong id="colVoidOrLabel"></strong> is irreversible.
                The record will be kept for audit purposes.</span>
              </div>
              <div class="col-void-form">
                <label>Reason for Voiding *</label>
                <textarea id="colVoidReason" placeholder="e.g. Duplicate entry, data entry error…"></textarea>
              </div>
            </div>
            <div class="col-modal-ft">
              <button class="col-btn col-btn-outline" onclick="colCloseModal('colVoidModal')">Cancel</button>
              <button class="col-btn col-btn-danger" onclick="colSubmitVoid()">
                <i class="fa-solid fa-ban"></i> Confirm Void
              </button>
            </div>
          </div>
        </div>
      </main>

    <?php elseif ($tab === 'record'): ?>
      <main class="resident-main" id="record">
        <!-- paste the <section class="col-section"> block here -->
        <section class="col-section">
          <!-- Header -->
          <div class="col-header">
            <h2><i class="fa-solid fa-cash-register"></i> Record New Payment</h2>
          </div>

          <div class="rec-wrap">

            <!-- OR Preview -->
            <div class="rec-or-preview">
              <span class="rec-or-label">Official Receipt No.</span>
              <span class="rec-or-value" id="recOrPreview"><?= htmlspecialchars($preview_or_no) ?></span>
              <span class="rec-or-note">Auto-generated upon saving</span>
            </div>

            <!-- Form -->
            <div class="rec-form-card">

              <!-- Source Type -->
              <div class="rec-row">
                <div class="rec-field rec-field--full">
                  <label class="rec-label">Source Type <span class="rec-req">*</span></label>
                  <div class="rec-type-grid">
                    <label class="rec-type-opt">
                      <input type="radio" name="rec_source_type" value="document_fee">
                      <span><i class="fa-solid fa-file-lines"></i> Document Fee</span>
                    </label>
                    <label class="rec-type-opt">
                      <input type="radio" name="rec_source_type" value="business_permit">
                      <span><i class="fa-solid fa-store"></i> Business Permit</span>
                    </label>
                    <label class="rec-type-opt">
                      <input type="radio" name="rec_source_type" value="cedula">
                      <span><i class="fa-solid fa-id-card"></i> Cedula</span>
                    </label>
                    <label class="rec-type-opt">
                      <input type="radio" name="rec_source_type" value="other">
                      <span><i class="fa-solid fa-circle-dot"></i> Other</span>
                    </label>
                  </div>
                  <span class="rec-err" id="recErrSourceType"></span>
                </div>
              </div>

              <!-- Linked Request (Document Fee only) -->
              <div class="rec-row" id="recLinkedRequestRow" style="display:none;">
                <div class="rec-field rec-field--full">
                  <label class="rec-label">
                    <i class="fa-solid fa-link"></i> Linked Document Request
                    <span style="color:#94a3b8; font-weight:400;">(optional)</span>
                  </label>
                  <div class="rec-search-wrap">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text"
                          id="recReqSearch"
                          class="rec-input"
                          placeholder="Search by reference no. or resident name…"
                          autocomplete="off">
                    <div class="rec-search-results" id="recReqResults"></div>
                  </div>
                  <div class="rec-linked-preview" id="recLinkedPreview" style="display:none;">
                    <i class="fa-solid fa-file-circle-check" style="color:#16a34a;"></i>
                    <span id="recLinkedText"></span>
                    <button type="button" class="rec-unlink" onclick="recClearLinked()">
                      <i class="fa-solid fa-xmark"></i>
                    </button>
                  </div>
                  <input type="hidden" id="recRequestId"  name="rec_request_id">
                  <input type="hidden" id="recResidentId" name="rec_resident_id">
                </div>
              </div>

              <!-- Resident Name -->
              <div class="rec-row">
                <div class="rec-field rec-field--full">
                  <label class="rec-label">Resident Name <span class="rec-req">*</span></label>
                  <input type="text"
                        id="recResidentName"
                        class="rec-input"
                        placeholder="Auto-filled from linked request, or type manually">
                  <span class="rec-err" id="recErrResident"></span>
                </div>
              </div>

              <!-- Amount + Date -->
              <div class="rec-row">
                <div class="rec-field">
                  <label class="rec-label">Amount (₱) <span class="rec-req">*</span></label>
                  <div class="rec-amount-wrap">
                    <span class="rec-peso">₱</span>
                    <input type="number"
                          id="recAmount"
                          class="rec-input rec-input--amount"
                          placeholder="0.00"
                          min="0.01"
                          step="0.01">
                  </div>
                  <span class="rec-err" id="recErrAmount"></span>
                </div>
                <div class="rec-field">
                  <label class="rec-label">Date Collected <span class="rec-req">*</span></label>
                  <input type="date"
                        id="recDateCollected"
                        class="rec-input"
                        value="<?= date('Y-m-d') ?>">
                  <span class="rec-err" id="recErrDate"></span>
                </div>
              </div>

              <!-- Description -->
              <div class="rec-row">
                <div class="rec-field rec-field--full">
                  <label class="rec-label">Description <span class="rec-req">*</span></label>
                  <input type="text"
                        id="recDescription"
                        class="rec-input"
                        placeholder="e.g. Barangay Clearance Fee">
                  <span class="rec-err" id="recErrDescription"></span>
                </div>
              </div>

              <!-- Notes -->
              <div class="rec-row">
                <div class="rec-field rec-field--full">
                  <label class="rec-label">
                    Notes
                    <span style="color:#94a3b8; font-weight:400;">(optional)</span>
                  </label>
                  <textarea id="recNotes"
                            class="rec-input rec-textarea"
                            placeholder="Any additional notes for this transaction…"
                            rows="3"></textarea>
                </div>
              </div>

              <!-- Actions -->
              <div class="rec-actions">
                <button type="button" class="col-btn col-btn-outline" onclick="recReset()">
                  <i class="fa-solid fa-rotate-left"></i> Reset
                </button>
                <button type="button" class="col-btn col-btn-primary" onclick="recSubmit()" id="recSubmitBtn">
                  <i class="fa-solid fa-floppy-disk"></i> Save Payment
                </button>
              </div>

            </div><!-- /rec-form-card -->
          </div>

        </section><!-- /col-section -->

        <!-- Toast -->
        <div id="colToast"></div>
      </main>

    <?php elseif ($tab === 'receipts'): ?>
      <main class="resident-main" id="receipts">
        <section class="col-section">
          <div class="exp-header">
            <h2 class="exp-title">
                Expenditures
                <small>Barangay funds disbursement register</small>
            </h2>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a href="expenditures_export.php?<?= http_build_query([
                    'category'  => $categoryFilter,
                    'date_from' => $dateFrom,
                    'date_to'   => $dateTo,
                    'search'    => $searchQuery,
                ]) ?>" class="exp-btn exp-btn--ghost">
                    ↓ Export CSV
                </a>
                <button class="exp-btn exp-btn--primary" onclick="openAddModal()">
                    + Add Expenditure
                </button>
            </div>
          </div>
  
          <!-- ── Controls ── -->
          <form method="GET" action="" id="expFilterForm">
              <!-- Category tabs -->
              <div style="margin-bottom:12px;">
                  <div class="exp-cat-tabs">
                      <?php foreach ($validCategories as $cat): ?>
                          <a href="?<?= http_build_query(array_merge($_GET, ['category' => $cat])) ?>"
                            class="exp-cat-tab <?= $categoryFilter === $cat ? 'active' : '' ?>">
                              <?= $cat === 'all' ? 'All Categories' : getCategoryIcon($cat) . ' ' . htmlspecialchars($cat) ?>
                          </a>
                      <?php endforeach; ?>
                  </div>
              </div>
  
              <div class="exp-controls">
                  <!-- Search -->
                  <div class="exp-control-group">
                      <label for="exp-search-input">Search</label>
                      <input type="search" id="exp-search-input" name="search"
                            class="exp-input exp-search"
                            placeholder="Description or payee…"
                            value="<?= htmlspecialchars($searchQuery) ?>"
                            oninput="debounceSubmit()">
                  </div>
  
                  <!-- Date range -->
                  <div class="exp-control-group">
                      <label for="exp-date-from">From</label>
                      <input type="date" id="exp-date-from" name="date_from"
                            class="exp-input" value="<?= htmlspecialchars($dateFrom) ?>"
                            onchange="document.getElementById('expFilterForm').submit()">
                  </div>
                  <div class="exp-control-group">
                      <label for="exp-date-to">To</label>
                      <input type="date" id="exp-date-to" name="date_to"
                            class="exp-input" value="<?= htmlspecialchars($dateTo) ?>"
                            onchange="document.getElementById('expFilterForm').submit()">
                  </div>
  
                  <!-- Hidden category carried over -->
                  <input type="hidden" name="category" value="<?= htmlspecialchars($categoryFilter) ?>">
              </div>
          </form>
  
          <!-- ── Meta row ── -->
          <div class="exp-meta">
              <span>Showing <strong><?= count($expenditures) ?></strong> record<?= count($expenditures) !== 1 ? 's' : '' ?></span>
              <span>·</span>
              <span><?= date('M j, Y', strtotime($dateFrom)) ?> – <?= date('M j, Y', strtotime($dateTo)) ?></span>
              <?php if ($searchQuery): ?>
                  <span>· Filtered by "<strong><?= htmlspecialchars($searchQuery) ?></strong>"</span>
              <?php endif; ?>
          </div>
  
          <!-- ── Table card ── -->
          <div class="exp-card">
              <?php if (empty($expenditures)): ?>
                  <div class="exp-empty">
                      <div class="exp-empty-icon">📭</div>
                      <h3>No expenditures found</h3>
                      <p>Try adjusting your filters or date range.</p>
                  </div>
              <?php else: ?>
                  <div class="exp-table-wrap">
                      <table class="exp-table">
                          <thead>
                              <tr>
                                  <th>Category</th>
                                  <th>Description</th>
                                  <th>Payee</th>
                                  <th>Date</th>
                                  <th style="text-align:right;">Amount</th>
                                  <th>Status</th>
                                  <th style="text-align:right;">Actions</th>
                              </tr>
                          </thead>
                          <tbody>
                              <?php foreach ($expenditures as $row): ?>
                                  <?php
                                      $isLarge    = $row['amount'] >= LARGE_EXPENDITURE_THRESHOLD;
                                      $isPending  = $row['approval_status'] !== 'Approved';
                                      $canEdit    = $isPending; // only editable before Captain approval
                                  ?>
                                  <tr>
                                      <!-- Category -->
                                      <td>
                                          <span class="exp-cat-pill">
                                              <?= getCategoryIcon($row['category']) ?>
                                              <?= htmlspecialchars($row['category']) ?>
                                          </span>
                                      </td>
  
                                      <!-- Description -->
                                      <td style="max-width:220px;">
                                          <span style="display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:200px;"
                                                title="<?= htmlspecialchars($row['description']) ?>">
                                              <?= htmlspecialchars($row['description']) ?>
                                          </span>
                                          <?php if ($isLarge): ?>
                                              <span style="font-size:.68rem;color:var(--accent,#f0c040);font-weight:600;">⚠ Requires Captain Approval</span>
                                          <?php endif; ?>
                                      </td>
  
                                      <!-- Payee -->
                                      <td style="white-space:nowrap;"><?= htmlspecialchars($row['payee']) ?></td>
  
                                      <!-- Date -->
                                      <td class="col-date">
                                          <?= date('M j, Y', strtotime($row['disbursement_date'])) ?>
                                      </td>
  
                                      <!-- Amount -->
                                      <td class="col-amount">
                                          <?= formatAmount($row['amount']) ?>
                                      </td>
  
                                      <!-- Status -->
                                      <td><?= getStatusBadge($row['approval_status']) ?></td>
  
                                      <!-- Actions -->
                                      <td class="col-actions">
                                          <button class="exp-btn exp-btn--ghost exp-btn--sm exp-btn--icon"
                                                  onclick="openDetailModal(<?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>)"
                                                  title="View details">
                                              🔍
                                          </button>
                                          <?php if ($canEdit): ?>
                                              <button class="exp-btn exp-btn--ghost exp-btn--sm"
                                                      onclick="openEditModal(<?= (int)$row['id'] ?>)"
                                                      title="Edit expenditure">
                                                  ✏ Edit
                                              </button>
                                          <?php endif; ?>
                                          <?php if ($row['supporting_doc_path']): ?>
                                              <a href="<?= htmlspecialchars($row['supporting_doc_path']) ?>"
                                                target="_blank" rel="noopener"
                                                class="exp-btn exp-btn--ghost exp-btn--sm exp-btn--icon"
                                                title="View supporting document">
                                                  📄
                                              </a>
                                          <?php endif; ?>
                                      </td>
                                  </tr>
                              <?php endforeach; ?>
                          </tbody>
                      </table>
                  </div>
  
                  <!-- ── Subtotals footer ── -->
                  <div class="exp-subtotals">
                      <h4>Category Subtotals</h4>
                      <div class="exp-subtotals-grid">
                          <?php foreach ($subtotals as $cat => $sub): ?>
                              <div class="exp-sub-item">
                                  <span class="exp-sub-label"><?= getCategoryIcon($cat) ?> <?= htmlspecialchars($cat) ?></span>
                                  <span class="exp-sub-val"><?= formatAmount($sub) ?></span>
                              </div>
                          <?php endforeach; ?>
                      </div>
                      <div class="exp-sub-total">
                          <span style="color:var(--exp-muted);font-size:.8rem;font-weight:600;">TOTAL</span>
                          <strong><?= formatAmount($totalAmount) ?></strong>
                      </div>
                  </div>
              <?php endif; ?>
          </div>
        </section><!-- /col-section -->

        <!-- Toast -->
        <div id="colToast"></div>
      </main>
    <?php endif; ?>
  </div>

  <script src="assets/js/resident_dashboard.js"></script>
  <script src="assets/js/finance.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>

  <script>
    (function () {
      // --- STATIC DATA (replace with PHP-injected values later) ---
      const labels        = ['January', 'February', 'March', 'April', 'May', 'June'];
      const collections   = [82000, 95000, 110000, 143000, 162800, 187320];
      const expenditures  = [70000, 88000, 105000, 120000, 130000, 134200];

      const textColor = getComputedStyle(document.body).getPropertyValue('--text').trim() || '#e8eaf0';
      const mutedColor = getComputedStyle(document.body).getPropertyValue('--muted').trim() || '#6b7a99';
      const gridColor  = 'rgba(255,255,255,0.06)';

      const ctx = document.getElementById('revenueChart').getContext('2d');

      new Chart(ctx, {
        data: {
          labels,
          datasets: [
            {
              // Bar — Collections
              type: 'bar',
              label: 'Collections',
              data: collections,
              backgroundColor: 'rgba(234, 179, 8, 0.25)',
              borderColor:     'rgba(234, 179, 8, 0.9)',
              borderWidth: 2,
              borderRadius: 6,
              order: 2,
            },
            {
              // Line — Expenditures
              type: 'line',
              label: 'Expenditures',
              data: expenditures,
              borderColor:     'rgba(239, 68, 68, 0.9)',
              backgroundColor: 'rgba(239, 68, 68, 0.08)',
              borderWidth: 2,
              pointBackgroundColor: 'rgba(239, 68, 68, 1)',
              pointRadius: 4,
              tension: 0.4,
              fill: true,
              order: 1,
            },
            {
              // Line — Collections trend
              type: 'line',
              label: 'Collections Trend',
              data: collections,
              borderColor:     'rgba(234, 179, 8, 1)',
              backgroundColor: 'transparent',
              borderWidth: 2,
              borderDash: [5, 4],
              pointBackgroundColor: 'rgba(234, 179, 8, 1)',
              pointRadius: 4,
              tension: 0.4,
              fill: false,
              order: 0,
            }
          ]
        },
        options: {
          responsive: true,
          interaction: { mode: 'index', intersect: false },
          plugins: {
            legend: {
              labels: {
                color: textColor,
                font: { size: 12 },
                boxWidth: 14,
              }
            },
            tooltip: {
              callbacks: {
                label: ctx => ' ₱' + ctx.parsed.y.toLocaleString()
              }
            }
          },
          scales: {
            x: {
              ticks: { color: mutedColor },
              grid:  { color: gridColor }
            },
            y: {
              ticks: {
                color: mutedColor,
                callback: val => '₱' + (val / 1000) + 'K'
              },
              grid: { color: gridColor }
            }
          }
        }
      });
    })();

    // Donut Chart
    const doCtx = document.getElementById('donutChart').getContext('2d');
    const doSurface = getComputedStyle(document.body).getPropertyValue('--surface').trim() || '#161a22';
    const doText    = getComputedStyle(document.body).getPropertyValue('--text').trim()    || '#e8eaf0';
    new Chart(doCtx, {
    type: 'doughnut',
    data: {
        labels: ['General Admin','Public Services','Social Services','Other Services'],
        datasets: [{
        data: [40, 30, 20, 10],
        backgroundColor: ['#3b82f6','#22c55e','#e8a020','#8b5cf6'],
        borderWidth: 2,
        borderColor: doSurface,
        hoverOffset: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '72%',
        plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: doSurface,
            titleColor: doText,
            bodyColor: '#e8a020',
            callbacks: { label: ctx => ' ' + ctx.label + ': ' + ctx.raw + '%' }
        }
        }
    }
    });

    function recReset() {
      document.querySelectorAll('input[name="rec_source_type"]').forEach(r => r.checked = false);
      document.getElementById('recLinkedRequestRow').style.display = 'none';
      document.getElementById('recResidentName').value  = '';
      document.getElementById('recAmount').value        = '';
      document.getElementById('recDateCollected').value = '<?= date('Y-m-d') ?>';
      document.getElementById('recDescription').value   = '';
      document.getElementById('recNotes').value         = '';
      recClearLinked();
      recClearErrors();
    }
  </script>
</body>
</html>
