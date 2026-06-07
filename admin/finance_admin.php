<?php

include '../config/connection.php';
include '../includes/auth_check.php';

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

$typeClause = '';
$searchClause = '';

/* Filters */
$allowed    = ['all','document_fee','business_permit','cedula','other'];
$sourceType = in_array($_GET['type'] ?? 'all', $allowed, true) ? ($_GET['type'] ?? 'all') : 'all';
$dateFrom = !empty($_GET['date_from'])
    ? $_GET['date_from'] . " 00:00:00"
    : date('Y-m-01 00:00:00');

$dateTo = !empty($_GET['date_to'])
    ? $_GET['date_to'] . " 23:59:59"
    : date('Y-m-d 23:59:59');
$search     = trim($_GET['search'] ?? '');

if ($sourceType !== 'all') {
    $typeClause = 'AND c.source_type = :source_type';
    $params[':source_type'] = $sourceType;
}
if ($search !== '') {
    $searchClause = "AND (c.or_number LIKE :search OR CONCAT(r.first_name,' ',r.last_name) LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

$collections = [];

$where = [];
$params = [];

$where[] = "c.collected_at BETWEEN :date_from AND :date_to";
$params[':date_from'] = $dateFrom;
$params[':date_to']   = $dateTo;

if ($sourceType !== 'all') {
    $where[] = "c.source_type = :source_type";
    $params[':source_type'] = $sourceType;
}

if ($search !== '') {
    $where[] = "(c.or_number LIKE :search
              OR CONCAT(r.first_name,' ',r.last_name) LIKE :search)";
    $params[':search'] = "%$search%";
}

$whereSQL = implode(" AND ", $where);

$stmtList = $pdo->prepare("
    SELECT c.id,
           c.or_number,
           c.source_type,
           c.amount,
           c.description,
           c.collected_at,
           c.voided,
           COALESCE(CONCAT(r.first_name, ' ', r.last_name), 'Walk-in') AS resident_name,
           u.username AS collected_by_name
    FROM collections c
    LEFT JOIN residents r ON r.id = c.resident_id
    JOIN users u ON u.id = c.collected_by
    WHERE $whereSQL
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
$exportUrl = 'finance_admin.php?' . http_build_query([
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
$dateFrom = !empty($_GET['date_from'])
    ? $_GET['date_from'] . " 00:00:00"
    : date('Y-m-01 00:00:00');

$dateTo = !empty($_GET['date_to'])
    ? $_GET['date_to'] . " 23:59:59"
    : date('Y-m-d 23:59:59');
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

// ── Config ──────────────────────────────────────────────────────────────
$APPROVAL_THRESHOLD = 5000;          // configurable
$UPLOAD_DIR         = 'uploads/expenditures/';
$MAX_FILE_SIZE      = 5 * 1024 * 1024; // 5 MB

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Sanitise & validate inputs ──────────────────────────────────────
    $category          = trim($_POST['category']          ?? '');
    $description       = trim($_POST['description']       ?? '');
    $amount            = $_POST['amount']                 ?? '';
    $disbursement_date = trim($_POST['disbursement_date'] ?? '');
    $payee             = trim($_POST['payee']             ?? '');
    $notes             = trim($_POST['notes']             ?? '');
    $recorded_by       = $_SESSION['user_id']             ?? null;

    $allowed_categories = ['Personnel','Supplies','Infrastructure','Events','Maintenance','Other'];

    if (!in_array($category, $allowed_categories))           $errors[] = 'Invalid category selected.';
    if ($description === '')                                  $errors[] = 'Description is required.';
    if (!is_numeric($amount) || (float)$amount <= 0)         $errors[] = 'Amount must be a number greater than 0.';
    if (!$disbursement_date || !strtotime($disbursement_date)) $errors[] = 'Invalid disbursement date.';
    if ($payee === '')                                        $errors[] = 'Payee is required.';

    $amount = (float)$amount;

    // ── File upload ─────────────────────────────────────────────────────
    $supporting_doc_path = null;

    if (!empty($_FILES['supporting_doc']['name'])) {
        $file      = $_FILES['supporting_doc'];
        $allowed   = ['image/jpeg','image/png','application/pdf'];
        $ext_map   = ['image/jpeg'=>'jpg','image/png'=>'png','application/pdf'=>'pdf'];
        $mime      = mime_content_type($file['tmp_name']);

        if (!in_array($mime, $allowed)) {
            $errors[] = 'Supporting document must be JPG, PNG, or PDF.';
        } elseif ($file['size'] > $MAX_FILE_SIZE) {
            $errors[] = 'Supporting document must not exceed 5 MB.';
        } else {
            if (!is_dir($UPLOAD_DIR)) mkdir($UPLOAD_DIR, 0755, true);
            $filename            = uniqid('doc_', true) . '.' . $ext_map[$mime];
            $supporting_doc_path = $UPLOAD_DIR . $filename;
            if (!move_uploaded_file($file['tmp_name'], $supporting_doc_path)) {
                $errors[] = 'File upload failed. Please try again.';
                $supporting_doc_path = null;
            }
        }
    }

    // ── Persist to DB ───────────────────────────────────────────────────
    if (empty($errors)) {
        // approved_by left NULL until Captain approves
        $sql = "INSERT INTO expenditures
                    (category, description, amount, disbursement_date, payee, supporting_doc_path, recorded_by)
                VALUES (?,?,?,?,?,?,?)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $category,
            $description,
            $amount,
            $disbursement_date,
            $payee,
            $supporting_doc_path,
            $recorded_by
        ]);

        // ── Approval threshold rule ─────────────────────────────────────
        $new_id = $pdo->lastInsertId();
        if ($amount < $APPROVAL_THRESHOLD) {
            $pdo->prepare("UPDATE expenditures SET status='Approved', approved_by=? WHERE id=?")
                ->execute([$recorded_by, $new_id]);
            $toast_msg  = 'Expenditure recorded and auto-approved.';
            $toast_type = 'success';
        } else {
            $pdo->prepare("UPDATE expenditures SET status='Pending Captain Approval' WHERE id=?")
                ->execute([$new_id]);
            $toast_msg  = 'Expenditure submitted — pending Captain approval.';
            $toast_type = 'pending';
        }

        $success = true;
    }
}

// ── Default date = today ────────────────────────────────────────────────
$default_date = date('Y-m-d');

/* ── Fiscal year ─────────────────────────────────────────────────── */
$fiscal_year = isset($_GET['fy']) ? (int)$_GET['fy'] : (int)date('Y');
$min_year    = 2020;
$max_year    = (int)date('Y') + 1;
 
/* ── AJAX / POST actions ─────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_bud_action'])) {
    header('Content-Type: application/json');
 
    $action = $_POST['_bud_action'];
 
    /* ── Add budget item ─────────────────────────────────────────── */
    if ($action === 'add') {
        $category         = trim($_POST['category']         ?? '');
        $description      = trim($_POST['description']      ?? '');
        $allocated_amount = (float)($_POST['allocated_amount'] ?? 0);
        $fy               = (int)($_POST['fiscal_year']     ?? date('Y'));
 
        $allowed = ['Personnel','Supplies','Infrastructure','Events','Maintenance','Other'];
        if (!in_array($category, $allowed))          { echo json_encode(['success'=>false,'message'=>'Invalid category.']); exit; }
        if ($allocated_amount <= 0)                   { echo json_encode(['success'=>false,'message'=>'Amount must be greater than 0.']); exit; }
 
        // Prevent duplicate category in same fiscal year
        $chk = $pdo->prepare("SELECT id FROM budget_items WHERE category=? AND fiscal_year=?");
        $chk->execute([$category, $fy]);
        if ($chk->fetch()) { echo json_encode(['success'=>false,'message'=>'This category already has a budget line for '.$fy.'.']); exit; }
 
        $stmt = $pdo->prepare("INSERT INTO budget_items (category, description, allocated_amount, fiscal_year) VALUES (?,?,?,?)");
        $stmt->execute([$category, $description, $allocated_amount, $fy]);
        echo json_encode(['success'=>true,'message'=>'Budget item added.','id'=>$pdo->lastInsertId()]);
        exit;
    }
 
    /* ── Edit budget item ────────────────────────────────────────── */
    if ($action === 'edit') {
        $id               = (int)($_POST['id']               ?? 0);
        $allocated_amount = (float)($_POST['allocated_amount'] ?? 0);
        $description      = trim($_POST['description']       ?? '');
 
        if ($id <= 0)             { echo json_encode(['success'=>false,'message'=>'Invalid item.']); exit; }
        if ($allocated_amount <= 0){ echo json_encode(['success'=>false,'message'=>'Amount must be greater than 0.']); exit; }
 
        $stmt = $pdo->prepare("UPDATE budget_items SET allocated_amount=?, description=? WHERE id=?");
        $stmt->execute([$allocated_amount, $description, $id]);
        echo json_encode(['success'=>true,'message'=>'Budget item updated.']);
        exit;
    }
 
    /* ── Delete budget item ──────────────────────────────────────── */
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['success'=>false,'message'=>'Invalid item.']); exit; }
 
        // Only delete if no expenditures are linked
        $row   = $pdo->prepare("SELECT category, fiscal_year FROM budget_items WHERE id=?");
        $row->execute([$id]);
        $item  = $row->fetch(PDO::FETCH_ASSOC);
        if (!$item) { echo json_encode(['success'=>false,'message'=>'Item not found.']); exit; }
 
        $spent = $pdo->prepare("SELECT COUNT(*) FROM expenditures WHERE category=? AND YEAR(disbursement_date)=?");
        $spent->execute([$item['category'], $item['fiscal_year']]);
        if ($spent->fetchColumn() > 0) {
            echo json_encode(['success'=>false,'message'=>'Cannot delete: expenditures are linked to this budget line.']);
            exit;
        }
 
        $pdo->prepare("DELETE FROM budget_items WHERE id=?")->execute([$id]);
        echo json_encode(['success'=>true,'message'=>'Budget item deleted.']);
        exit;
    }
 
    echo json_encode(['success'=>false,'message'=>'Unknown action.']);
    exit;
}
 
/* ── Budget items with spent amounts ────────────────────────────── */
$items_stmt = $pdo->prepare("
    SELECT b.id, b.category, b.description, b.allocated_amount,
           COALESCE(SUM(e.amount), 0) AS spent,
           b.allocated_amount - COALESCE(SUM(e.amount), 0) AS remaining,
           ROUND(COALESCE(SUM(e.amount),0) / b.allocated_amount * 100, 1) AS pct_used
    FROM budget_items b
    LEFT JOIN expenditures e
           ON e.category = b.category
          AND YEAR(e.disbursement_date) = b.fiscal_year
    WHERE b.fiscal_year = ?
    GROUP BY b.id
    ORDER BY b.category
");
$items_stmt->execute([$fiscal_year]);
$budget_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
 
/* ── Total budget vs total spent ─────────────────────────────────── */
$totals_stmt = $pdo->prepare("
    SELECT SUM(b.allocated_amount) AS total_budget,
           COALESCE(SUM(e.amount), 0) AS total_spent
    FROM budget_items b
    LEFT JOIN expenditures e
           ON YEAR(e.disbursement_date) = b.fiscal_year
    WHERE b.fiscal_year = ?
");
$totals_stmt->execute([$fiscal_year]);
$totals = $totals_stmt->fetch(PDO::FETCH_ASSOC);
 
$total_budget    = (float)($totals['total_budget'] ?? 0);
$total_spent     = (float)($totals['total_spent']  ?? 0);
$total_remaining = $total_budget - $total_spent;
$total_pct       = $total_budget > 0 ? round($total_spent / $total_budget * 100, 1) : 0;
 
$categories = ['Personnel','Supplies','Infrastructure','Events','Maintenance','Other'];

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

$monthlyCollections = array_fill(0, 12, 0);
$monthlyExpenditures = array_fill(0, 12, 0);

/* Collections */
$stmt = $pdo->query("
    SELECT
        MONTH(collected_at) AS month_num,
        SUM(amount) AS total
    FROM collections
    WHERE YEAR(collected_at) = YEAR(CURDATE())
    GROUP BY MONTH(collected_at)
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $monthlyCollections[$row['month_num'] - 1] = (float)$row['total'];
}

/* Collection Today */
$stmt = $pdo->query("
    SELECT COALESCE(SUM(amount),0)
    FROM collections
    WHERE DATE(collected_at) = CURDATE()
");

$today_collections = (float)$stmt->fetchColumn();

/* Collection Per Month */
$stmt = $pdo->query("
    SELECT COALESCE(SUM(amount),0)
    FROM collections
    WHERE YEAR(collected_at) = YEAR(CURDATE())
      AND MONTH(collected_at) = MONTH(CURDATE())
");

$month_collections = (float)$stmt->fetchColumn();

/* Collection Last Month */
$stmt = $pdo->query("
    SELECT COALESCE(SUM(amount),0)
    FROM collections
    WHERE YEAR(collected_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
      AND MONTH(collected_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
");

$last_month_collections = (float)$stmt->fetchColumn();

if ($last_month_collections > 0) {
    $mom_change =
        (($month_collections - $last_month_collections)
        / $last_month_collections) * 100;
} else {
    $mom_change = 0;
}

$mom_positive = $mom_change >= 0;

/* Expenditures */
$stmt = $pdo->query("
    SELECT
        MONTH(disbursement_date) AS month_num,
        SUM(amount) AS total
    FROM expenditures
    WHERE YEAR(disbursement_date) = YEAR(CURDATE())
    GROUP BY MONTH(disbursement_date)
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $monthlyExpenditures[$row['month_num'] - 1] = (float)$row['total'];
}

/* Expenditures this month */
$stmt = $pdo->query("
    SELECT COALESCE(SUM(amount),0)
    FROM expenditures
    WHERE YEAR(disbursement_date) = YEAR(CURDATE())
      AND MONTH(disbursement_date) = MONTH(CURDATE())
");

$month_expenditures = (float)$stmt->fetchColumn();

$chartLabels = [
    'Jan','Feb','Mar','Apr','May','Jun',
    'Jul','Aug','Sep','Oct','Nov','Dec'
];

$net_balance = $month_collections - $month_expenditures;

$net_class = $net_balance >= 0
    ? 'text-success'
    : 'text-danger';

/* Total Budget Allocation */
$stmt = $pdo->query("
    SELECT COALESCE(SUM(allocated_amount), 0)
    FROM budget_items
    WHERE fiscal_year = YEAR(CURDATE())
");

$total_budget = (float)$stmt->fetchColumn();

/* Expenditures this year */
$stmt = $pdo->query("
    SELECT COALESCE(SUM(amount), 0)
    FROM expenditures
    WHERE YEAR(disbursement_date) = YEAR(CURDATE())
");

$year_expenditures = (float)$stmt->fetchColumn();

/* Budget Utilization % */
$budget_utilization = 0;

if ($total_budget > 0) {
    $budget_utilization = ($year_expenditures / $total_budget) * 100;
}

if ($budget_utilization < 50) {
    $util_color = 'success';
} elseif ($budget_utilization < 80) {
    $util_color = 'warning';
} else {
    $util_color = 'danger';
}

$expenditure_threshold = 50000;

$exp_class = ($month_expenditures >= $expenditure_threshold)
    ? 'text-danger fw-bold'
    : 'text-dark';

$net_class = ($net_balance >= 0)
    ? 'text-success fw-bold'
    : 'text-danger fw-bold';

$util_color = ($budget_utilization >= 90)
    ? 'danger'
    : (($budget_utilization >= 70) ? 'warning' : 'success');

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
  <link rel="stylesheet" href="../assets/css/resident_dashboard.css" />
  <link rel="stylesheet" href="assets/css/finance.css">
  
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
        <a class="sidebar-link <?= ($tab === 'dashboard' || $tab === '') ? 'is-active' : '' ?>" href="finance_admin.php?tab=dashboard">
          <i class="fa-solid fa-house"></i><span>Dashboard</span>
        </a>
      </div>

      <div class="sidebar-group">
        <span class="sidebar-section-label">COLLECTIONS</span>
        <a class="sidebar-link <?= $tab === 'collections' ? 'is-active' : '' ?>" href="finance_admin.php?tab=collections">
          <i class="fa-solid fa-money-bill-transfer"></i><span>All Collections</span>
        </a>
        <a class="sidebar-link <?= $tab === 'record' ? 'is-active' : '' ?>" href="finance_admin.php?tab=record">
          <i class="fa-solid fa-cash-register"></i><span>Record Payment</span>
        </a>
        <a class="sidebar-link <?= $tab === 'receipts' ? 'is-active' : '' ?>" href="finance_admin.php?tab=receipts">
          <i class="fa-solid fa-file-invoice-dollar"></i><span>Official Receipts</span>
        </a>
      </div>

      <div class="sidebar-group">
        <span class="sidebar-section-label">EXPENDITURES</span>
        <a class="sidebar-link <?= $tab === 'expenditures' ? 'is-active' : '' ?>" href="finance_admin.php?tab=expenditures">
          <i class="fa-solid fa-money-bill-wave"></i><span>All Expenditures</span>
        </a>
        <a class="sidebar-link <?= $tab === 'add-exp' ? 'is-active' : '' ?>" href="finance_admin.php?tab=add-exp">
          <i class="fa-solid fa-circle-plus"></i><span>Add Expenditure</span>
        </a>
      </div>

      <div class="sidebar-group">
        <span class="sidebar-section-label">BUDGET</span>
        <a class="sidebar-link <?= $tab === 'budget' ? 'is-active' : '' ?>" href="finance_admin.php?tab=budget">
          <i class="fa-solid fa-chart-pie"></i><span>Budget Management</span>
        </a>
      </div>

      <div class="sidebar-group">
        <span class="sidebar-section-label">REPORTS</span>
        <a class="sidebar-link" href="finance_admin.php?tab=reports">
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
              <a class="col-btn col-btn-primary" href="finance_admin.php?tab=record">
                <i class="fa-solid fa-plus"></i> Record New Payment
              </a>
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
                  <td style="color:#64748b; max-width:180px; font-size:13px;">
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
              <form action="#" method="post"></form>

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

            </div>
          </div>

        </section><!-- /col-section -->

        <!-- Toast -->
        <div id="colToast"></div>
      </main>

    <?php elseif ($tab === 'expenditures'): ?>
      <main class="resident-main" id="expenditures">
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
                                              <span style="font-size:.68rem;color:#b45309;font-weight:600;">⚠ Requires Captain Approval</span>
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

    <?php elseif ($tab === 'add-exp'): ?>
      <main class="resident-main" id="add-exp">
        <section class="col-section">
          <?php if ($success): ?>
          <script>
            document.addEventListener('DOMContentLoaded', function () {
              showToast('<?= addslashes($toast_msg) ?>', '<?= $toast_type ?>');
            });
          </script>
          <?php endif; ?>

          <?php if (!empty($errors)): ?>
            <div class="form-alert" style="
              background: rgba(255,94,94,0.1);
              border: 1px solid rgba(255,94,94,0.3);
              border-radius: 10px;
              padding: 12px 16px;
              margin-bottom: 20px;
              font-size: 13px;
              color: var(--danger);
            ">
              <strong>Please fix the following:</strong>
              <ul style="margin: 8px 0 0; padding-left: 18px;">
                <?php foreach ($errors as $e): ?>
                  <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <div class="form-header">
            <h2>Record Expenditure</h2>
            <p>Fill in all required fields. Supporting documents are recommended for audit purposes.</p>
          </div>

          <div class="threshold-note">
            <span class="icon">⚡</span>
            <span>
              Expenditures below <strong>₱<?= number_format($APPROVAL_THRESHOLD) ?></strong> are <strong>auto-approved</strong>.
              Those at or above <strong>₱<?= number_format($APPROVAL_THRESHOLD) ?></strong> are flagged as
              <em>Pending Captain Approval</em> and routed to the Captain's dashboard.
            </span>
          </div>

          <form method="POST" enctype="multipart/form-data" id="expForm" novalidate>

            <div class="card">
              <div class="card-label">Expenditure Details</div>

              <div class="field">
                <label for="category">Category <span class="req">*</span></label>
                <select id="category" name="category" required>
                  <option value="" disabled <?= empty($_POST['category']) ? 'selected' : '' ?>>Select a category</option>
                  <?php foreach (['Personnel','Supplies','Infrastructure','Events','Maintenance','Other'] as $cat): ?>
                    <option value="<?= $cat ?>" <?= (($_POST['category'] ?? '') === $cat) ? 'selected' : '' ?>>
                      <?= $cat ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="field">
                <label for="description">
                  Description <span class="req">*</span>
                  <span class="hint">e.g. "Office supplies for barangay hall"</span>
                </label>
                <textarea id="description" name="description" placeholder="Be specific about what the expense covers…" required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
              </div>

              <div class="field-row">
                <div class="field">
                  <label for="amount">Amount (₱) <span class="req">*</span></label>
                  <div class="prefix-wrap">
                    <span class="prefix">₱</span>
                    <input type="number" id="amount" name="amount"
                          min="0.01" step="0.01" placeholder="0.00"
                          value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>" required>
                  </div>
                </div>
                <div class="field">
                  <label for="disbursement_date">Disbursement Date <span class="req">*</span></label>
                  <input type="date" id="disbursement_date" name="disbursement_date"
                        value="<?= htmlspecialchars($_POST['disbursement_date'] ?? $default_date) ?>" required>
                </div>
              </div>

              <div class="field">
                <label for="payee">
                  Payee <span class="req">*</span>
                  <span class="hint">e.g. "Juan dela Cruz" or "ABC Supplies"</span>
                </label>
                <input type="text" id="payee" name="payee"
                      placeholder="Name of person or company paid"
                      value="<?= htmlspecialchars($_POST['payee'] ?? '') ?>" required>
              </div>
            </div>

            <div class="card">
              <div class="card-label">Documents & Notes</div>

              <div class="field">
                <label>Supporting Document <span class="hint">Receipt, invoice, or quotation</span></label>
                <div class="upload-zone" id="uploadZone">
                  <input type="file" id="supporting_doc" name="supporting_doc" accept=".jpg,.jpeg,.png,.pdf">
                  <div class="up-icon">📎</div>
                  <div class="up-label"><strong>Click to upload</strong> or drag &amp; drop</div>
                  <div class="up-sub">JPG, PNG, or PDF — max 5 MB</div>
                </div>
                <div id="fileChosen" style="font-size:12px;color:var(--accent2);margin-top:6px;display:none;"></div>
              </div>

              <div class="field">
                <label for="notes">Notes <span class="hint">Optional</span></label>
                <textarea id="notes" name="notes"
                          placeholder="Any additional context or remarks…"
                          style="min-height:66px;"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
              </div>
            </div>

            <div class="approval-badge" id="approvalBadge"></div>

            <div class="submit-row">
              <button type="button" class="btn-cancel" onclick="history.back()">Cancel</button>
              <button type="submit" class="btn-submit" id="submitBtn">Submit Expenditure</button>
            </div>

          </form>
        </section><!-- /col-section -->

        <!-- Toast -->
        <div id="colToast"></div>
      </main>

    <?php elseif ($tab === 'budget'): ?>
      <main class="resident-main" id="budget">
        <!-- paste the <section class="col-section"> block here -->
        <section class="col-section">
          <div class="bud-header">
            <div>
              <h2 class="bud-title">Budget Overview</h2>
              <p class="bud-sub">Annual budget allocation and expenditure tracking</p>
            </div>
            <div class="bud-header-actions">
              <form method="GET" id="fyForm" style="display:flex;align-items:center;gap:8px;">
                <input type="hidden" name="tab" value="budget">
                <label for="fySelect" class="bud-fy-label">Fiscal Year</label>
                <select id="fySelect" name="fy" class="bud-select" onchange="document.getElementById('fyForm').submit()">
                  <?php for ($y = $max_year; $y >= $min_year; $y--): ?>
                    <option value="<?= $y ?>" <?= $y === $fiscal_year ? 'selected' : '' ?>><?= $y ?></option>
                  <?php endfor; ?>
                </select>
              </form>
              <button class="bud-btn-primary" onclick="budOpenAdd()">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M7 1v12M1 7h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                Add Budget Item
              </button>
              <button class="bud-btn-outline" onclick="budExportPDF()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                Export PDF
              </button>
            </div>
          </div>
      
          <?php if (empty($budget_items)): ?>
          <!-- ── Empty state ────────────────────────────────────────── -->
          <div class="bud-empty">
            <div class="bud-empty-icon">📋</div>
            <div class="bud-empty-title">No budget items for <?= $fiscal_year ?></div>
            <div class="bud-empty-sub">Set up the annual budget by adding category allocations.</div>
            <button class="bud-btn-primary" style="margin-top:16px;" onclick="budOpenAdd()">
              <svg width="13" height="13" viewBox="0 0 14 14" fill="none"><path d="M7 1v12M1 7h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
              Add First Budget Item
            </button>
          </div>
          <?php else: ?>
      
          <!-- ── Summary cards ─────────────────────────────────────── -->
          <div class="bud-summary-grid">
            <div class="bud-summary-card">
              <div class="bud-summary-label">Total Allocated</div>
              <div class="bud-summary-val">₱<?= number_format($total_budget, 2) ?></div>
              <div class="bud-summary-sub"><?= $fiscal_year ?> fiscal year</div>
            </div>
            <div class="bud-summary-card bud-summary-card--spent">
              <div class="bud-summary-label">Total Spent</div>
              <div class="bud-summary-val">₱<?= number_format($total_spent, 2) ?></div>
              <div class="bud-summary-sub"><?= $total_pct ?>% of budget used</div>
            </div>
            <div class="bud-summary-card bud-summary-card--rem">
              <div class="bud-summary-label">Remaining Balance</div>
              <div class="bud-summary-val <?= $total_remaining < 0 ? 'bud-over' : '' ?>">
                <?= $total_remaining < 0 ? '-' : '' ?>₱<?= number_format(abs($total_remaining), 2) ?>
              </div>
              <div class="bud-summary-sub"><?= $total_remaining < 0 ? 'Over budget' : 'Available to spend' ?></div>
            </div>
          </div>
      
          <!-- ── Utilization chart ──────────────────────────────────── -->
          <div class="bud-card">
            <div class="bud-card-title">Budget Utilization by Category</div>
            <div class="bud-chart-list">
              <?php foreach ($budget_items as $item):
                $pct     = min((float)$item['pct_used'], 100);
                $over    = (float)$item['remaining'] < 0;
                $barCls  = $over ? 'bud-bar--over' : ($pct >= 85 ? 'bud-bar--warn' : 'bud-bar--ok');
              ?>
              <div class="bud-chart-row">
                <div class="bud-chart-meta">
                  <span class="bud-chart-cat"><?= htmlspecialchars($item['category']) ?></span>
                  <span class="bud-chart-pct <?= $over ? 'bud-over' : '' ?>"><?= $item['pct_used'] ?>%</span>
                </div>
                <div class="bud-bar-track">
                  <div class="bud-bar <?= $barCls ?>" style="width:<?= $pct ?>%"></div>
                </div>
                <div class="bud-chart-amounts">
                  <span>₱<?= number_format($item['spent'], 2) ?> spent</span>
                  <span>of ₱<?= number_format($item['allocated_amount'], 2) ?></span>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
      
          <!-- ── Budget items table ─────────────────────────────────── -->
          <div class="bud-card">
            <div class="bud-card-title">Budget Line Items</div>
            <div class="bud-table-wrap">
              <table class="bud-table">
                <thead>
                  <tr>
                    <th>Category</th>
                    <th>Description</th>
                    <th class="ta-r">Allocated</th>
                    <th class="ta-r">Spent</th>
                    <th class="ta-r">Remaining</th>
                    <th class="ta-r">% Used</th>
                    <th class="ta-c">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($budget_items as $item):
                    $rem_cls = (float)$item['remaining'] < 0 ? 'bud-over' : ((float)$item['remaining'] < ($item['allocated_amount'] * 0.1) ? 'bud-warn' : '');
                  ?>
                  <tr data-bud-id="<?= $item['id'] ?>">
                    <td><span class="bud-cat-badge"><?= htmlspecialchars($item['category']) ?></span></td>
                    <td class="bud-desc"><?= htmlspecialchars($item['description'] ?: '—') ?></td>
                    <td class="ta-r">₱<?= number_format($item['allocated_amount'], 2) ?></td>
                    <td class="ta-r">₱<?= number_format($item['spent'], 2) ?></td>
                    <td class="ta-r <?= $rem_cls ?>">
                      <?= (float)$item['remaining'] < 0 ? '-' : '' ?>₱<?= number_format(abs($item['remaining']), 2) ?>
                    </td>
                    <td class="ta-r">
                      <span class="bud-pct-pill <?= (float)$item['pct_used'] >= 100 ? 'bud-pct-pill--over' : ((float)$item['pct_used'] >= 85 ? 'bud-pct-pill--warn' : '') ?>">
                        <?= $item['pct_used'] ?>%
                      </span>
                    </td>
                    <td class="ta-c">
                      <button class="bud-icon-btn" title="Edit"
                        onclick="budOpenEdit(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['category'])) ?>', <?= $item['allocated_amount'] ?>, '<?= htmlspecialchars(addslashes($item['description'])) ?>')">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                      </button>
                      <button class="bud-icon-btn bud-icon-btn--del" title="Delete"
                        onclick="budOpenDelete(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['category'])) ?>', <?= (float)$item['spent'] ?>)">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                      </button>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
                <tfoot>
                  <tr class="bud-tfoot">
                    <td colspan="2"><strong>Total</strong></td>
                    <td class="ta-r"><strong>₱<?= number_format($total_budget, 2) ?></strong></td>
                    <td class="ta-r"><strong>₱<?= number_format($total_spent, 2) ?></strong></td>
                    <td class="ta-r <?= $total_remaining < 0 ? 'bud-over' : '' ?>">
                      <strong><?= $total_remaining < 0 ? '-' : '' ?>₱<?= number_format(abs($total_remaining), 2) ?></strong>
                    </td>
                    <td class="ta-r"><strong><?= $total_pct ?>%</strong></td>
                    <td></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
          <?php endif; ?>
        </section><!-- /col-section -->

        <!-- Toast -->
        <div id="colToast"></div>
      </main>

      <!-- Add Modal -->
      <div class="col-backdrop" id="budAddModal">
        <div class="bud-modal">
          <div class="bud-modal-hd">
            <span>Add Budget Item</span>
            <button class="bud-modal-close" onclick="colCloseModal('budAddModal')">×</button>
          </div>
          <div class="bud-modal-body">
            <input type="hidden" id="budAddFY" value="<?= $fiscal_year ?>">
            <div class="bud-field">
              <label>Category <span class="req">*</span></label>
              <select id="budAddCategory">
                <option value="" disabled selected>Select category</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?= $cat ?>"><?= $cat ?></option>
                <?php endforeach; ?>
              </select>
              <div class="bud-err" id="budAddErrCat"></div>
            </div>
            <div class="bud-field">
              <label>Description <span class="bud-opt">Optional</span></label>
              <input type="text" id="budAddDescription" placeholder="e.g. Salaries and wages">
            </div>
            <div class="bud-field">
              <label>Allocated Amount (₱) <span class="req">*</span></label>
              <div class="bud-prefix-wrap">
                <span class="bud-prefix">₱</span>
                <input type="number" id="budAddAmount" min="0.01" step="0.01" placeholder="0.00">
              </div>
              <div class="bud-err" id="budAddErrAmt"></div>
            </div>
          </div>
          <div class="bud-modal-ft">
            <button class="bud-btn-ghost" onclick="colCloseModal('budAddModal')">Cancel</button>
            <button class="bud-btn-primary" id="budAddSubmitBtn" onclick="budSubmitAdd()">Add Item</button>
          </div>
        </div>
      </div>
      
      <!-- Edit Modal -->
      <div class="col-backdrop" id="budEditModal">
        <div class="bud-modal">
          <div class="bud-modal-hd">
            <span>Edit Budget Item</span>
            <button class="bud-modal-close" onclick="colCloseModal('budEditModal')">×</button>
          </div>
          <div class="bud-modal-body">
            <input type="hidden" id="budEditId">
            <div class="bud-field">
              <label>Category</label>
              <input type="text" id="budEditCategory" disabled style="opacity:.6;cursor:not-allowed;">
            </div>
            <div class="bud-field">
              <label>Description <span class="bud-opt">Optional</span></label>
              <input type="text" id="budEditDescription" placeholder="e.g. Salaries and wages">
            </div>
            <div class="bud-field">
              <label>Allocated Amount (₱) <span class="req">*</span></label>
              <div class="bud-prefix-wrap">
                <span class="bud-prefix">₱</span>
                <input type="number" id="budEditAmount" min="0.01" step="0.01" placeholder="0.00">
              </div>
              <div class="bud-err" id="budEditErrAmt"></div>
            </div>
          </div>
          <div class="bud-modal-ft">
            <button class="bud-btn-ghost" onclick="colCloseModal('budEditModal')">Cancel</button>
            <button class="bud-btn-primary" id="budEditSubmitBtn" onclick="budSubmitEdit()">Save Changes</button>
          </div>
        </div>
      </div>
      
      <!-- Delete Modal -->
      <div class="col-backdrop" id="budDeleteModal">
        <div class="bud-modal bud-modal--sm">
          <div class="bud-modal-hd">
            <span>Delete Budget Item</span>
            <button class="bud-modal-close" onclick="colCloseModal('budDeleteModal')">×</button>
          </div>
          <div class="bud-modal-body">
            <input type="hidden" id="budDeleteId">
            <p class="bud-confirm-text" id="budDeleteText"></p>
          </div>
          <div class="bud-modal-ft">
            <button class="bud-btn-ghost" onclick="colCloseModal('budDeleteModal')">Cancel</button>
            <button class="bud-btn-danger" id="budDeleteSubmitBtn" onclick="budSubmitDelete()">Delete</button>
          </div>
        </div>
      </div>
      
      <!-- PDF Print area -->
      <div id="budPrintArea" style="display:none;">
        <style>
          @media print {
            body > *:not(#budPrintArea) { display: none !important; }
            #budPrintArea { display: block !important; font-family: sans-serif; padding: 24px; color: #000; }
            #budPrintArea table { width:100%; border-collapse:collapse; margin-top:12px; }
            #budPrintArea th, #budPrintArea td { border:1px solid #ccc; padding:7px 10px; font-size:12px; }
            #budPrintArea th { background:#f1f5f9; font-weight:700; }
            #budPrintArea .ta-r { text-align:right; }
          }
        </style>
        <h2 style="margin:0 0 4px;">Barangay Budget Plan — <?= $fiscal_year ?></h2>
        <p style="margin:0 0 12px;font-size:13px;color:#64748b;">Generated: <?= date('F j, Y') ?></p>
        <table>
          <thead>
            <tr>
              <th>Category</th>
              <th>Description</th>
              <th class="ta-r">Allocated (₱)</th>
              <th class="ta-r">Spent (₱)</th>
              <th class="ta-r">Remaining (₱)</th>
              <th class="ta-r">% Used</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($budget_items as $item): ?>
            <tr>
              <td><?= htmlspecialchars($item['category']) ?></td>
              <td><?= htmlspecialchars($item['description'] ?: '—') ?></td>
              <td class="ta-r"><?= number_format($item['allocated_amount'], 2) ?></td>
              <td class="ta-r"><?= number_format($item['spent'], 2) ?></td>
              <td class="ta-r"><?= number_format($item['remaining'], 2) ?></td>
              <td class="ta-r"><?= $item['pct_used'] ?>%</td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="2"><strong>Total</strong></td>
              <td class="ta-r"><strong><?= number_format($total_budget, 2) ?></strong></td>
              <td class="ta-r"><strong><?= number_format($total_spent, 2) ?></strong></td>
              <td class="ta-r"><strong><?= number_format($total_remaining, 2) ?></strong></td>
              <td class="ta-r"><strong><?= $total_pct ?>%</strong></td>
            </tr>
          </tfoot>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <script src="../assets/js/resident_dashboard.js"></script>
  <script src="assets/js/finance.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>

  <script>
    (function () {

      const canvas = document.getElementById('revenueChart');
      if (!canvas) return;

      const labels = <?= json_encode($chartLabels) ?>;
      const collections = <?= json_encode($monthlyCollections) ?>;
      const expenditures = <?= json_encode($monthlyExpenditures) ?>;

      const ctx = canvas.getContext('2d');

      new Chart(ctx, {
        data: {
          labels,
          datasets: [
            {
              type: 'bar',
              label: 'Collections',
              data: collections,
              backgroundColor: 'rgba(234, 179, 8, 0.25)',
              borderColor: 'rgba(234, 179, 8, 0.9)',
              borderWidth: 2,
              borderRadius: 6,
              order: 2,
            },
            {
              type: 'line',
              label: 'Expenditures',
              data: expenditures,
              borderColor: 'rgba(239, 68, 68, 0.9)',
              backgroundColor: 'rgba(239, 68, 68, 0.08)',
              borderWidth: 2,
              pointBackgroundColor: 'rgba(239, 68, 68, 1)',
              pointRadius: 4,
              tension: 0.4,
              fill: true,
              order: 1,
            },
            {
              type: 'line',
              label: 'Collections Trend',
              data: collections,
              borderColor: 'rgba(234, 179, 8, 1)',
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
                color: '#ccc',
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
              ticks: { color: '#aaa' },
              grid: { color: 'rgba(255,255,255,0.05)' }
            },
            y: {
              ticks: {
                color: '#aaa',
                callback: val => '₱' + (val / 1000) + 'K'
              },
              grid: { color: 'rgba(255,255,255,0.07)' }
            }
          }
        }
      });

    })();

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

    /* Add Expenditures */
    /* (function () {
      const THRESHOLD = <?= (int)$APPROVAL_THRESHOLD ?>;

      // Approval badge
      const amountEl = document.getElementById('amount');
      const badge    = document.getElementById('approvalBadge');

      amountEl.addEventListener('input', function () {
        const val = parseFloat(this.value);
        if (!val || val <= 0) { badge.style.display = 'none'; return; }
        badge.style.display = 'flex';
        if (val < THRESHOLD) {
          badge.className = 'approval-badge auto';
          badge.innerHTML = '✅ This expenditure will be <strong style="margin-left:4px">auto-approved</strong> and recorded immediately.';
        } else {
          badge.className = 'approval-badge pending';
          badge.innerHTML = '🕐 This expenditure requires <strong style="margin-left:4px">Captain approval</strong> before it is recorded.';
        }
      });

      // File validation
      document.getElementById('supporting_doc').addEventListener('change', function () {
        const el = document.getElementById('fileChosen');
        if (!this.files.length) return;
        const f = this.files[0];
        if (f.size > 5 * 1024 * 1024) {
          showToast('File exceeds the 5 MB limit.', 'danger');
          this.value = '';
          el.style.display = 'none';
          return;
        }
        el.textContent = '📄 ' + f.name;
        el.style.display = 'block';
      });

      // Toast
      window.showToast = function (msg, type = 'success') {
        const colors = {
          success: { bg: 'var(--accent2)', color: '#fff' },
          pending: { bg: 'var(--accent)',  color: '#0d0f14' },
          danger:  { bg: 'var(--danger)',  color: '#fff' },
        };
        const c = colors[type] || colors.success;
        const t = document.createElement('div');
        t.style.cssText = `
          background:${c.bg}; color:${c.color};
          padding:12px 18px; border-radius:10px; font-size:13px; font-weight:600;
          margin-top:10px; box-shadow:0 4px 20px rgba(0,0,0,.3);
        `;
        t.textContent = msg;
        document.getElementById('colToast').appendChild(t);
        setTimeout(() => t.remove(), 3800);
      };
    })(); */
  </script>
</body>
</html>
