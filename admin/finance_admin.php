<?php
  require_once __DIR__ . '/includes/admin_layout.php';

  include '../config/connection.php';
  include '../includes/auth_check.php';

  if (!isset($_SESSION['user_id'])) {
      header("Location: ../login.php");
      exit();
  }

  $isRecordAjax = $_SERVER['REQUEST_METHOD'] === 'POST'
      && ($_POST['_rec_action'] ?? '') === 'record_payment';

  if (!$isRecordAjax) {
      require_once __DIR__ . '/includes/admin_layout.php';
  }

  requireRole(['treasurer']);


  $tab = $_GET['tab'] ?? 'dashboard';

  $current_user = [
      'id'       => $_SESSION['user_id'],
      'email'    => $_SESSION['email'],
      'role'     => $_SESSION['role'],
      'fullname' => 'Treasurer',
      'username' => $_SESSION['email']
  ];

  adm_page_start(
      'Finance Management',
      $tab,
      $current_user
  );

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
              $pdo->prepare("UPDATE expenditures SET approval_status='Approved', approved_by=? WHERE id=?")
                  ->execute([$recorded_by, $new_id]);
              $toast_msg  = 'Expenditure recorded and auto-approved.';
              $toast_type = 'success';
          } else {
              $pdo->prepare("UPDATE expenditures SET approval_status='Pending Captain Approval' WHERE id=?")
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
        error_log("POST DATA: " . json_encode($_POST));
          $category         = trim($_POST['category']         ?? '');
          $description      = trim($_POST['description']      ?? '');
          $allocated_amount = (float)($_POST['allocated_amount'] ?? 0);
          if (!isset($_POST['fiscal_year']) || !is_numeric($_POST['fiscal_year'])) {
              echo json_encode(['success'=>false,'message'=>'Invalid fiscal year.']);
              exit;
          }

          $fy = (int) $_POST['fiscal_year'];
  
          $allowed = ['Personnel','Supplies','Infrastructure','Events','Maintenance','Other'];
          if (!in_array($category, $allowed))          { echo json_encode(['success'=>false,'message'=>'Invalid category.']); exit; }
          if ($allocated_amount <= 0)                   { echo json_encode(['success'=>false,'message'=>'Amount must be greater than 0.']); exit; }
  
          // Prevent duplicate category in same fiscal year
          $chk = $pdo->prepare("SELECT id FROM budget_items WHERE category=? AND fiscal_year=?");
          $chk->execute([$category, $fy]);
          if ($chk->fetch()) { echo json_encode(['success'=>false,'message'=>'This category already has a budget line for '.$fy.'.']); exit; }
  
          $created_by = (int)$_SESSION['user_id'];

          $stmt = $pdo->prepare("
              INSERT INTO budget_items
              (
                  category,
                  description,
                  allocated_amount,
                  fiscal_year,
                  created_by
              )
              VALUES (?, ?, ?, ?, ?)
          ");

          $stmt->execute([
              $category,
              $description,
              $allocated_amount,
              $fy,
              $created_by
          ]);
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
  
  /* Total budget vs total spent */
  $stmt = $pdo->prepare("
      SELECT COALESCE(SUM(allocated_amount),0)
      FROM budget_items
      WHERE fiscal_year = ?
  ");
  $stmt->execute([$fiscal_year]);
  $total_budget = (float)$stmt->fetchColumn();

  $stmt = $pdo->prepare("
      SELECT COALESCE(SUM(amount),0)
      FROM expenditures
      WHERE YEAR(disbursement_date) = ?
      AND approval_status = 'approved'
  ");
  $stmt->execute([$fiscal_year]);
  $total_spent = (float)$stmt->fetchColumn();
  
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
  $fiscal_year_label = 'Fiscal Year ' . $fiscal_year;

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

  /* Pending Expenditures */
  $stmt = $pdo->prepare("
      SELECT *
      FROM expenditures
      WHERE approval_status = 'pending'
      ORDER BY created_at DESC
  ");
  $stmt->execute();

  $pending_expenditures = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

  /* REPORTS PHP */
  if ($tab === 'reports' && isset($_GET['export'])) {

    $month  = (int)($_GET['month'] ?? 0);
    $year   = (int)($_GET['year'] ?? 0);
    $export = $_GET['export'] ?? 'preview';

    if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid or missing month/year parameters.']);
        exit;
    }

    // ALL report logic here\
    $fpdfPath = __DIR__ . '/vendor/fpdf/fpdf.php';

    // ── Input validation ─────────────────────────────────────────
    $month  = filter_input(INPUT_GET, 'month',  FILTER_VALIDATE_INT, ['options' => ['min_range' => 1,    'max_range' => 12]]);
    $year   = filter_input(INPUT_GET, 'year',   FILTER_VALIDATE_INT, ['options' => ['min_range' => 2000, 'max_range' => 2100]]);
    $export = trim(filter_input(INPUT_GET, 'export', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'preview');

    if (!$month || !$year) {
      http_response_code(400);
      echo json_encode(['error' => 'Invalid or missing month/year parameters.']);
      exit;
    }

    if (!in_array($export, ['preview', 'pdf', 'csv'], true)) {
      $export = 'preview';
    }

    // ── Helpers ──────────────────────────────────────────────────
    function monthName(int $m): string {
      return date('F', mktime(0, 0, 0, $m, 1));
    }
    
    function peso(float $v): string {
      return '₱ ' . number_format(abs($v), 2);
    }
    
    function pesoSigned(float $v): string {
      $fmt = '₱ ' . number_format(abs($v), 2);
      return $v < 0 ? '(' . $fmt . ')' : $fmt;
    }

    // ── Query 1: Collections + Expenditures breakdown (UNION ALL) ─
    /**
     * Exact SQL preserved from spec.
     * Binds: month, year (collections), month, year (expenditures)
     */
    $sqlBreakdown = "
        SELECT
            source_type,
            COUNT(*)       AS transaction_count,
            SUM(amount)    AS total_amount
        FROM collections
        WHERE MONTH(collected_at)  = ? AND YEAR(collected_at)  = ?
        GROUP BY source_type
    
        UNION ALL
    
        SELECT
            CONCAT('EXP: ', category) AS source_type,
            COUNT(*)                  AS transaction_count,
            SUM(amount)               AS total_amount
        FROM expenditures
        WHERE MONTH(disbursement_date) = ? AND YEAR(disbursement_date) = ?
        GROUP BY category
    ";
    
    $stmtBreakdown = $pdo->prepare($sqlBreakdown);
    $stmtBreakdown->execute([$month, $year, $month, $year]);
    $breakdown = $stmtBreakdown->fetchAll(PDO::FETCH_ASSOC);

    // ── Query 2: Net balance ──────────────────────────────────────
    /**
     * Exact SQL preserved from spec.
     * Binds: month, year (collections), month, year (expenditures)
     */
    $sqlNet = "
        SELECT
            COALESCE(SUM(c.amount), 0) - COALESCE(SUM(e.amount), 0) AS net_balance
        FROM (SELECT 1) d
        LEFT JOIN collections  c ON MONTH(c.collected_at)       = ? AND YEAR(c.collected_at)       = ?
        LEFT JOIN expenditures e ON MONTH(e.disbursement_date)  = ? AND YEAR(e.disbursement_date)  = ?
    ";
    
    $stmtNet = $pdo->prepare($sqlNet);
    $stmtNet->execute([$month, $year, $month, $year]);
    $netRow = $stmtNet->fetch(PDO::FETCH_ASSOC);
    $netBalance = (float)($netRow['net_balance'] ?? 0);

    // ── Derived totals from breakdown ────────────────────────────
    $totalCollections  = 0.0;
    $totalExpenditures = 0.0;
    $collections       = [];
    $expenditures      = [];
    
    foreach ($breakdown as $row) {
        $amount = (float)$row['total_amount'];
        if (str_starts_with($row['source_type'], 'EXP: ')) {
            $totalExpenditures += $amount;
            $expenditures[] = [
                'label' => substr($row['source_type'], 5), // strip 'EXP: '
                'count' => (int)$row['transaction_count'],
                'amount' => $amount,
            ];
        } else {
            $totalCollections += $amount;
            $collections[] = [
                'label' => $row['source_type'],
                'count' => (int)$row['transaction_count'],
                'amount' => $amount,
            ];
        }
    }
    
    $periodLabel   = monthName($month) . ' ' . $year;
    $generatedDate = date('F j, Y');
    $preparedBy    = 'Barangay Treasurer'; // adjust to session value if needed

    // ════════════════════════════════════════════════════════════════
    // EXPORT: CSV
    // ════════════════════════════════════════════════════════════════
    if ($export === 'csv') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="financial_summary_' . $year . '_' . str_pad((string)$month, 2, '0', STR_PAD_LEFT) . '.csv"');
        header('Cache-Control: no-cache, no-store');
    
        $out = fopen('php://output', 'w');
    
        // BOM for Excel UTF-8 compatibility
        fputs($out, "\xEF\xBB\xBF");
    
        fputcsv($out, ['MONTHLY FINANCIAL SUMMARY']);
        fputcsv($out, ['Period', $periodLabel]);
        fputcsv($out, ['Date Generated', $generatedDate]);
        fputcsv($out, ['Prepared By', $preparedBy]);
        fputcsv($out, []);
    
        fputcsv($out, ['--- COLLECTIONS ---']);
        fputcsv($out, ['Source Type', 'Transactions', 'Amount']);
        foreach ($collections as $r) {
            fputcsv($out, [$r['label'], $r['count'], number_format($r['amount'], 2, '.', '')]);
        }
        fputcsv($out, ['TOTAL COLLECTIONS', array_sum(array_column($collections, 'count')), number_format($totalCollections, 2, '.', '')]);
        fputcsv($out, []);
    
        fputcsv($out, ['--- EXPENDITURES ---']);
        fputcsv($out, ['Category', 'Transactions', 'Amount']);
        foreach ($expenditures as $r) {
            fputcsv($out, [$r['label'], $r['count'], number_format($r['amount'], 2, '.', '')]);
        }
        fputcsv($out, ['TOTAL EXPENDITURES', array_sum(array_column($expenditures, 'count')), number_format($totalExpenditures, 2, '.', '')]);
        fputcsv($out, []);
    
        $netLabel = $netBalance >= 0 ? 'NET SURPLUS' : 'NET DEFICIT';
        fputcsv($out, [$netLabel, '', number_format(abs($netBalance), 2, '.', '')]);
    
        fclose($out);
        exit;
    }
    
    // ════════════════════════════════════════════════════════════════
    // EXPORT: PDF  (FPDF)
    // ════════════════════════════════════════════════════════════════
    if ($export === 'pdf') {
        if (!file_exists($fpdfPath)) {
            http_response_code(500);
            echo json_encode(['error' => 'FPDF library not found at: ' . $fpdfPath]);
            exit;
        }
    
        require_once $fpdfPath;
    
        class SummaryPDF extends FPDF {
            public string $barangay  = 'BARANGAY SAMPLE';
            public string $address   = 'Municipality of Sample, Province of Sample';
            public string $period    = '';
            public string $genDate   = '';
            public string $prepBy    = '';
            public string $reportTitle = 'MONTHLY FINANCIAL SUMMARY';
    
            // Brand navy
            private array $navy  = [11, 37, 69];
            private array $gold  = [201, 150, 30];
            private array $white = [255, 255, 255];
            private array $light = [250, 247, 239];
            private array $text  = [32, 48, 71];
            private array $muted = [102, 112, 133];
    
            function Header(): void {
                // Navy header bar
                $this->SetFillColor(...$this->navy);
                $this->Rect(0, 0, 210, 32, 'F');
    
                // Gold accent line
                $this->SetFillColor(...$this->gold);
                $this->Rect(0, 32, 210, 1.5, 'F');
    
                $this->SetTextColor(...$this->white);
                $this->SetFont('Arial', 'B', 14);
                $this->SetY(6);
                $this->Cell(0, 6, $this->barangay, 0, 1, 'C');
    
                $this->SetFont('Arial', '', 8);
                $this->Cell(0, 5, 'Republic of the Philippines  |  ' . $this->address, 0, 1, 'C');
    
                $this->SetFont('Arial', 'B', 10);
                $this->SetTextColor(...$this->gold);
                $this->Cell(0, 6, $this->reportTitle, 0, 1, 'C');
    
                // Meta row
                $this->SetFillColor(...$this->light);
                $this->Rect(10, 36, 190, 12, 'F');
                $this->SetFont('Arial', '', 8);
                $this->SetTextColor(...$this->muted);
                $this->SetY(38);
                $this->SetX(12);
                $this->Cell(63, 4, 'Period Covered: ' . $this->period,     0, 0);
                $this->Cell(63, 4, 'Date Generated: ' . $this->genDate,    0, 0);
                $this->Cell(63, 4, 'Prepared By: '    . $this->prepBy,     0, 0);
                $this->Ln(10);
            }
    
            function Footer(): void {
                $this->SetY(-12);
                $this->SetFont('Arial', 'I', 7);
                $this->SetTextColor(...$this->muted);
                $this->Cell(0, 5, 'Page ' . $this->PageNo() . '  |  ' . $this->barangay . ' — ' . $this->reportTitle, 0, 0, 'C');
            }
    
            /** Section heading */
            function sectionHead(string $title): void {
                $this->SetFillColor(...$this->navy);
                $this->SetTextColor(...$this->white);
                $this->SetFont('Arial', 'B', 9);
                $this->Cell(0, 7, '  ' . strtoupper($title), 0, 1, 'L', true);
                $this->Ln(1);
            }
    
            /** Column header row */
            function tableHead(array $cols, array $widths, array $aligns): void {
                $this->SetFillColor(...$this->navy);
                $this->SetTextColor(...$this->white);
                $this->SetFont('Arial', 'B', 8);
                foreach ($cols as $i => $col) {
                    $this->Cell($widths[$i], 6, $col, 0, 0, $aligns[$i], true);
                }
                $this->Ln();
            }
    
            /** Data row */
            function tableRow(array $cells, array $widths, array $aligns, bool $shade = false): void {
                if ($shade) {
                    $this->SetFillColor(...$this->light);
                } else {
                    $this->SetFillColor(...$this->white);
                }
                $this->SetTextColor(...$this->text);
                $this->SetFont('Arial', '', 8);
                foreach ($cells as $i => $cell) {
                    $this->Cell($widths[$i], 6, $cell, 0, 0, $aligns[$i], true);
                }
                $this->Ln();
                // thin border line
                $this->SetDrawColor(230, 222, 206);
                $this->Line(10, $this->GetY(), 200, $this->GetY());
            }
    
            /** Totals / summary row */
            function totalRow(array $cells, array $widths, array $aligns): void {
                $this->SetFillColor(...$this->light);
                $this->SetTextColor(...$this->navy);
                $this->SetFont('Arial', 'B', 8);
                foreach ($cells as $i => $cell) {
                    $this->Cell($widths[$i], 7, $cell, 0, 0, $aligns[$i], true);
                }
                $this->Ln(9);
            }
    
            /** Net balance highlight box */
            function netBox(string $label, float $amount): void {
                $this->Ln(4);
                $color = $amount >= 0 ? [22, 163, 74] : [220, 38, 38];
                $bg    = $amount >= 0 ? [236, 253, 243] : [254, 242, 242];
                $this->SetFillColor(...$bg);
                $this->SetDrawColor(...$color);
                $this->RoundedRect(10, $this->GetY(), 190, 14, 3, 'DF');
                $this->SetFont('Arial', 'B', 11);
                $this->SetTextColor(...$color);
                $this->SetY($this->GetY() + 3);
                $this->Cell(130, 6, '  ' . strtoupper($label), 0, 0, 'L');
                $this->Cell(60,  6, '₱ ' . number_format(abs($amount), 2), 0, 0, 'R');
                $this->Ln(10);
            }
    
            /** FPDF doesn't have RoundedRect natively — simple polyfill */
            function RoundedRect(float $x, float $y, float $w, float $h, float $r, string $style = ''): void {
                $k  = $this->k;
                $hp = $this->h;
                if ($style === 'F') $op = 'f';
                elseif ($style === 'FD' || $style === 'DF') $op = 'B';
                else $op = 'S';
                $MyArc = 4 / 3 * (sqrt(2) - 1);
                $this->_out(sprintf('%.2F %.2F m', ($x + $r) * $k, ($hp - $y) * $k));
                $xc = $x + $w - $r; $yc = $y + $r;
                $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - $y) * $k));
                $this->_Arc($xc + $r * $MyArc, $yc - $r, $xc + $r, $yc - $r * $MyArc, $xc + $r, $yc);
                $xc = $x + $w - $r; $yc = $y + $h - $r;
                $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - $yc) * $k));
                $this->_Arc($xc + $r, $yc + $r * $MyArc, $xc + $r * $MyArc, $yc + $r, $xc, $yc + $r);
                $xc = $x + $r; $yc = $y + $h - $r;
                $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - ($y + $h)) * $k));
                $this->_Arc($xc - $r * $MyArc, $yc + $r, $xc - $r, $yc + $r * $MyArc, $xc - $r, $yc);
                $xc = $x + $r; $yc = $y + $r;
                $this->_out(sprintf('%.2F %.2F l', $x * $k, ($hp - $yc) * $k));
                $this->_Arc($xc - $r, $yc - $r * $MyArc, $xc - $r * $MyArc, $yc - $r, $xc, $yc - $r);
                $this->_out($op);
            }
    
            function _Arc(float $x1, float $y1, float $x2, float $y2, float $x3, float $y3): void {
                $h = $this->h;
                $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
                    $x1 * $this->k, ($h - $y1) * $this->k,
                    $x2 * $this->k, ($h - $y2) * $this->k,
                    $x3 * $this->k, ($h - $y3) * $this->k));
            }
        }
    
        // Build PDF
        $pdf = new SummaryPDF('P', 'mm', 'A4');
        $pdf->barangay    = 'BARANGAY SAMPLE';
        $pdf->address     = 'Municipality of Sample, Province of Sample';
        $pdf->period      = $periodLabel;
        $pdf->genDate     = $generatedDate;
        $pdf->prepBy      = $preparedBy;
        $pdf->reportTitle = 'MONTHLY FINANCIAL SUMMARY REPORT';
        $pdf->SetMargins(10, 52, 10);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();
    
        // ── Collections section ──────────────────
        $pdf->sectionHead('Income / Collections');
        $pdf->tableHead(
            ['Source Type', 'Transactions', 'Amount (₱)'],
            [110, 35, 45],
            ['L', 'C', 'R']
        );
        foreach ($collections as $i => $r) {
            $pdf->tableRow(
                [$r['label'], $r['count'], number_format($r['amount'], 2)],
                [110, 35, 45],
                ['L', 'C', 'R'],
                $i % 2 === 1
            );
        }
        $pdf->totalRow(
            ['TOTAL COLLECTIONS', array_sum(array_column($collections, 'count')), number_format($totalCollections, 2)],
            [110, 35, 45],
            ['L', 'C', 'R']
        );
    
        // ── Expenditures section ─────────────────
        $pdf->sectionHead('Expenditures');
        $pdf->tableHead(
            ['Category', 'Transactions', 'Amount (₱)'],
            [110, 35, 45],
            ['L', 'C', 'R']
        );
        foreach ($expenditures as $i => $r) {
            $pdf->tableRow(
                [$r['label'], $r['count'], number_format($r['amount'], 2)],
                [110, 35, 45],
                ['L', 'C', 'R'],
                $i % 2 === 1
            );
        }
        $pdf->totalRow(
            ['TOTAL EXPENDITURES', array_sum(array_column($expenditures, 'count')), number_format($totalExpenditures, 2)],
            [110, 35, 45],
            ['L', 'C', 'R']
        );
    
        // ── Net balance box ──────────────────────
        $netLabel = $netBalance >= 0 ? 'Net Surplus' : 'Net Deficit';
        $pdf->netBox($netLabel, $netBalance);
    
        // ── Signature block ──────────────────────
        $pdf->Ln(10);
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(102, 112, 133);
        $pdf->Cell(95, 5, 'Prepared by:', 0, 0, 'C');
        $pdf->Cell(95, 5, 'Noted by:', 0, 1, 'C');
        $pdf->Ln(12);
        $pdf->SetDrawColor(11, 37, 69);
        $pdf->Line(20, $pdf->GetY(), 95, $pdf->GetY());
        $pdf->Line(115, $pdf->GetY(), 190, $pdf->GetY());
        $pdf->Ln(1);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetTextColor(11, 37, 69);
        $pdf->Cell(95, 4, 'JUAN DELA CRUZ', 0, 0, 'C');
        $pdf->Cell(95, 4, 'MARIA SANTOS',   0, 1, 'C');
        $pdf->SetFont('Arial', '', 7);
        $pdf->SetTextColor(102, 112, 133);
        $pdf->Cell(95, 4, 'Barangay Treasurer', 0, 0, 'C');
        $pdf->Cell(95, 4, 'Barangay Captain',   0, 1, 'C');
    
        $filename = 'financial_summary_' . $year . '_' . str_pad((string)$month, 2, '0', STR_PAD_LEFT) . '.pdf';
        $pdf->Output('D', $filename);
        exit;
    }
    
    // ════════════════════════════════════════════════════════════════
    // EXPORT: PREVIEW  (JSON for AJAX)
    // ════════════════════════════════════════════════════════════════
    header('Content-Type: application/json; charset=UTF-8');
    
    echo json_encode([
        'period'      => $periodLabel,
        'generated'   => $generatedDate,
        'prepared_by' => $preparedBy,
        'collections' => array_map(fn($r) => [
            'label'  => $r['label'],
            'count'  => $r['count'],
            'amount' => number_format($r['amount'], 2),
        ], $collections),
        'expenditures' => array_map(fn($r) => [
            'label'  => $r['label'],
            'count'  => $r['count'],
            'amount' => number_format($r['amount'], 2),
        ], $expenditures),
        'totals' => [
            'collections'  => number_format($totalCollections,  2),
            'expenditures' => number_format($totalExpenditures, 2),
            'net_balance'  => number_format(abs($netBalance),   2),
            'net_label'    => $netBalance >= 0 ? 'Net Surplus' : 'Net Deficit',
            'net_positive' => $netBalance >= 0,
        ],
    ]);

  }
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
  <link rel="stylesheet" href="assets/css/finance.css">
  <link rel="stylesheet" href="assets/css/secretary.css?v=20260607b">
  
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

  <!-- <div class="finance-shell"> -->

    <?php if ($tab === 'dashboard'): ?>
      <div class="finance-main" id="dashboard">
        <section class="welcome-panel">
          <div>
            <h1><?= e($greeting) ?>, <?= e($first_name) ?></h1>
            <p><?= adm_e(date('l, F j, Y')) ?> - Barangay Sta. Rosa 1, Noveleta, Cavite.</p>
          </div>

          <div class="welcome-badges">
            <span class="role-badge role-badge--gold">
              <i class="fa-solid fa-coins" aria-hidden="true"></i>
              <?= e($role_label) ?>
            </span>

            <span class="fiscal-badge">
              <i class="fa-solid fa-calendar-check"></i>
              Fiscal Year: <?= e($fiscal_year) ?>
            </span>
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
              <a href="finance_admin.php?tab=reports" class="btn btn--small">
                View full report <i class="fa-solid fa-arrow-right"></i>
              </a>
            </div>
            <div class="announcement-list" style="position:relative; height:320px;">
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
              <a class="btn btn--small" href="finance_admin.php?tab=collections">View all collections</a>
            </div>
            <div class="dash-table-wrap">
              <table class="dash-table" style="width:100%; table-layout:fixed;">
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
                  <?php if (!empty($collections)): ?>
                    <?php foreach (array_slice($collections, 0, 8) as $index => $collection): ?>
                      <tr>
                        <td style="color:var(--text-muted);font-size:11px;">
                          <?= str_pad($index + 1, 3, '0', STR_PAD_LEFT) ?>
                        </td>

                        <td>
                          <span class="badge collection">
                            <?= col_sanitize(col_type_label($collection['source_type'])) ?>
                          </span>
                        </td>

                        <td class="amount">
                          <?= col_currency((float)$collection['amount']) ?>
                        </td>

                        <td>
                          <?= col_sanitize($collection['resident_name']) ?>
                        </td>

                        <td style="color:var(--text-muted);font-size:11px;">
                          <?= date('M d, Y', strtotime($collection['collected_at'])) ?>
                        </td>

                        <td>
                          <a href="finance_admin.php?tab=collections&id=<?= (int)$collection['id'] ?>"
                            class="btn-view">
                            <i class="fa-solid fa-eye"></i> View
                          </a>
                        </td>
                      </tr>
                    <?php endforeach; ?>

                  <?php else: ?>
                    <tr>
                      <td colspan="6"
                          style="text-align:center;padding:24px;color:var(--text-muted);">
                        No collection records found.
                      </td>
                    </tr>
                  <?php endif; ?>
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

            <div class="dash-table-wrap" style="display:block;">
              <table class="dash-table" style="width:100%; table-layout:fixed; text-align: center;">
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
            <div style="display: flex; gap: 16px; flex-wrap: wrap; padding: 8px 8px;">
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
      </div>

    <?php elseif ($tab === 'collections'): ?>
      <div class="finance-main" id="collections">
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
      </div>

    <?php elseif ($tab === 'record'): ?>
      <div class="finance-main" id="record">
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
      </div>

    <?php elseif ($tab === 'expenditures'): ?>
      <div class="finance-main" id="expenditures">
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
      </div>

    <?php elseif ($tab === 'add-exp'): ?>
      <div class="finance-main" id="add-exp">
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
      </div>

    <?php elseif ($tab === 'budget'): ?>
      <div class="finance-main" id="budget">
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
                    <option value="<?= (int)$y ?>"
                      <?= ((int)$y === (int)$fiscal_year) ? 'selected' : '' ?>>
                      <?= $y ?>
                    </option>
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
      </div>

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
    
    <?php elseif ($tab === 'reports'): ?>
      <div class="finance-main" id="reports">
        <!-- ── Page Header ── -->
        <div class="rpt-page-header">
          <div>
            <h1 class="rpt-page-title">
              <i class="ti ti-file-text" aria-hidden="true"></i>
              Financial Reports
            </h1>
            <p class="rpt-page-sub">Generate official statements for Barangay Captain, Municipal Treasurer &amp; DILG.</p>
          </div>
        </div>

        <section class="col-section">
          <!-- ── Generator Card ── -->
          <div class="rpt-card rpt-generator">
      
            <div class="rpt-card-head">
              <span class="rpt-card-label">Report Generator</span>
            </div>
      
            <div class="rpt-form-grid">
      
              <!-- Report Type -->
              <div class="rpt-field rpt-field--span2">
                <label class="rpt-label" for="rpt-type">Report Type</label>
                <div class="rpt-select-wrap">
                  <select class="rpt-select" id="rpt-type" onchange="rptHandleTypeChange(this.value)">
                    <option value="">— Select a report type —</option>
                    <option value="monthly-collections">Monthly Collections Report</option>
                    <option value="monthly-expenditures">Monthly Expenditures Report</option>
                    <option value="monthly-summary">Monthly Financial Summary</option>
                    <option value="annual-budget">Annual Budget Utilization</option>
                    <option value="annual-income">Annual Income Statement</option>
                  </select>
                  <i class="ti ti-chevron-down rpt-select-icon" aria-hidden="true"></i>
                </div>
                <!-- Report description badge -->
                <div class="rpt-desc-badge" id="rpt-desc" style="display:none"></div>
              </div>
      
              <!-- Period selectors (shown/hidden per type) -->
              <div class="rpt-field" id="rpt-month-wrap">
                <label class="rpt-label" for="rpt-month">Month</label>
                <div class="rpt-select-wrap">
                  <?php
                  $currentMonth = (int)date('n');

                  $months = [
                    1=>'January',2=>'February',3=>'March',4=>'April',
                    5=>'May',6=>'June',7=>'July',8=>'August',
                    9=>'September',10=>'October',11=>'November',12=>'December'
                  ];
                  ?>

                  <select class="rpt-select" id="rpt-month">
                  <?php foreach ($months as $num => $name): ?>
                    <option value="<?= $num ?>" <?= $num === $currentMonth ? 'selected' : '' ?>>
                      <?= $name ?>
                    </option>
                  <?php endforeach; ?>
                  </select>
                  <i class="ti ti-chevron-down rpt-select-icon" aria-hidden="true"></i>
                </div>
              </div>
      
              <div class="rpt-field" id="rpt-year-wrap">
                <label class="rpt-label" for="rpt-year">Year</label>
                <div class="rpt-select-wrap">
                  <select class="rpt-select" id="rpt-year">
                    <?php
                      $currentYear = (int)date('Y');
                      for ($y = $currentYear; $y >= $currentYear - 5; $y--) {
                        echo "<option value='$y'>$y</option>";
                      }
                    ?>
                  </select>
                  <i class="ti ti-chevron-down rpt-select-icon" aria-hidden="true"></i>
                </div>
              </div>
      
            </div><!-- /rpt-form-grid -->
      
            <!-- Action bar -->
            <div class="rpt-actions" id="rpt-actions" style="display:none">
              <button class="rpt-btn rpt-btn--primary" onclick="rptPreview()">
                <i class="ti ti-eye" aria-hidden="true"></i> Preview Report
              </button>
              <button class="rpt-btn rpt-btn--outline" id="rpt-pdf-btn" onclick="rptExport('pdf')">
                <i class="ti ti-file-type-pdf" aria-hidden="true"></i> Export PDF
              </button>
              <button class="rpt-btn rpt-btn--outline" id="rpt-csv-btn" onclick="rptExport('csv')">
                <i class="ti ti-table-export" aria-hidden="true"></i> Export CSV
              </button>
              <button class="rpt-btn rpt-btn--ghost" id="rpt-print-btn" onclick="rptPrint()">
                <i class="ti ti-printer" aria-hidden="true"></i> Print
              </button>
            </div>
      
          </div><!-- /rpt-generator -->
      
          <!-- ── Report Type Cards ── -->
          <div class="rpt-types-grid">
      
            <button class="rpt-type-card" onclick="rptSelectType('monthly-collections')">
              <span class="rpt-type-icon rpt-type-icon--teal"><i class="ti ti-coins" aria-hidden="true"></i></span>
              <span class="rpt-type-name">Monthly Collections</span>
              <span class="rpt-type-badge rpt-badge--teal">PDF + CSV</span>
              <span class="rpt-type-desc">OR numbers, amounts &amp; source types</span>
            </button>
      
            <button class="rpt-type-card" onclick="rptSelectType('monthly-expenditures')">
              <span class="rpt-type-icon rpt-type-icon--amber"><i class="ti ti-receipt" aria-hidden="true"></i></span>
              <span class="rpt-type-name">Monthly Expenditures</span>
              <span class="rpt-type-badge rpt-badge--teal">PDF + CSV</span>
              <span class="rpt-type-desc">Category, payee, amount &amp; approval</span>
            </button>
      
            <button class="rpt-type-card" onclick="rptSelectType('monthly-summary')">
              <span class="rpt-type-icon rpt-type-icon--blue"><i class="ti ti-chart-bar" aria-hidden="true"></i></span>
              <span class="rpt-type-name">Monthly Financial Summary</span>
              <span class="rpt-type-badge rpt-badge--blue">PDF only</span>
              <span class="rpt-type-desc">Income vs Expenses &amp; net surplus/deficit</span>
            </button>
      
            <button class="rpt-type-card" onclick="rptSelectType('annual-budget')">
              <span class="rpt-type-icon rpt-type-icon--gold"><i class="ti ti-layout-list" aria-hidden="true"></i></span>
              <span class="rpt-type-name">Annual Budget Utilization</span>
              <span class="rpt-type-badge rpt-badge--blue">PDF only</span>
              <span class="rpt-type-desc">Budget vs actual per category &amp; % utilization</span>
            </button>
      
            <button class="rpt-type-card" onclick="rptSelectType('annual-income')">
              <span class="rpt-type-icon rpt-type-icon--danger"><i class="ti ti-report-analytics" aria-hidden="true"></i></span>
              <span class="rpt-type-name">Annual Income Statement</span>
              <span class="rpt-type-badge rpt-badge--blue">PDF only</span>
              <span class="rpt-type-desc">Full-year collections vs expenditures &amp; net balance</span>
            </button>
      
          </div><!-- /rpt-types-grid -->
      
          <!-- ── Preview Panel (hidden until Preview is clicked) ── -->
          <div class="rpt-preview-panel" id="rpt-preview-panel" style="display:none">
      
            <div class="rpt-card-head rpt-preview-head">
              <span class="rpt-card-label" id="rpt-preview-label">Report Preview</span>
              <button class="rpt-close-btn" onclick="rptClosePreview()" aria-label="Close preview">
                <i class="ti ti-x" aria-hidden="true"></i>
              </button>
            </div>
      
            <!-- Letterhead -->
            <div class="rpt-letterhead" id="rpt-letterhead">
              <div class="rpt-lh-logo">
                <i class="ti ti-building-community" style="font-size:36px" aria-hidden="true"></i>
              </div>
              <div class="rpt-lh-text">
                <p class="rpt-lh-brgy">Republic of the Philippines</p>
                <p class="rpt-lh-name">BARANGAY SAMPLE</p>
                <p class="rpt-lh-address">Municipality of Sample, Province of Sample</p>
              </div>
            </div>
      
            <!-- Report meta row -->
            <div class="rpt-meta-row" id="rpt-meta-row"></div>
      
            <!-- Data table -->
            <div class="rpt-table-wrap">
              <table class="rpt-table" id="rpt-preview-table">
                <thead id="rpt-thead"></thead>
                <tbody id="rpt-tbody"></tbody>
                <tfoot id="rpt-tfoot"></tfoot>
              </table>
            </div>
      
            <!-- Signature block -->
            <div class="rpt-sig-block">
              <div class="rpt-sig-col">
                <div class="rpt-sig-line"></div>
                <p class="rpt-sig-name">JUAN DELA CRUZ</p>
                <p class="rpt-sig-title">Barangay Treasurer</p>
              </div>
              <div class="rpt-sig-col">
                <div class="rpt-sig-line"></div>
                <p class="rpt-sig-name">MARIA SANTOS</p>
                <p class="rpt-sig-title">Barangay Captain</p>
              </div>
            </div>
      
          </div>
        </section><!-- /col-section -->

        <!-- Toast -->
        <div id="colToast"></div>
      </div>
    <?php endif; ?>
  <!-- </div> -->

  <script src="../assets/js/resident_dashboard.js"></script>
  <script src="assets/js/secretary.js?v=20260605c"></script>
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
          maintainAspectRatio: false,
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
