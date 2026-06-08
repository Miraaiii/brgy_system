<?php
require_once __DIR__ . '/includes/admin_layout.php';

$user = adm_require_admin($conn, ['captain', 'secretary', 'treasurer']);
$q = trim((string)($_GET['q'] ?? ''));
$doc_type_id = (int)($_GET['doc_type_id'] ?? 0);
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));

$document_types = adm_table_exists($conn, 'document_types')
    ? adm_fetch_all($conn, 'SELECT id, name FROM document_types WHERE is_active = 1 ORDER BY name ASC')
    : [];

$where = [];
$types = '';
$params = [];

if ($q !== '') {
    $like = '%' . $q . '%';
    $where[] = "(issued.doc_number LIKE ? OR dr.reference_no LIKE ? OR CONCAT(r.first_name, ' ', r.last_name) LIKE ?)";
    $types .= 'sss';
    array_push($params, $like, $like, $like);
}
if ($doc_type_id > 0) {
    $where[] = 'dt.id = ?';
    $types .= 'i';
    $params[] = $doc_type_id;
}
if ($from !== '') {
    $where[] = 'DATE(issued.issued_at) >= ?';
    $types .= 's';
    $params[] = $from;
}
if ($to !== '') {
    $where[] = 'DATE(issued.issued_at) <= ?';
    $types .= 's';
    $params[] = $to;
}
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$issued_documents = [];
if (adm_table_exists($conn, 'issued_documents') && adm_table_exists($conn, 'document_requests')) {
    $issued_documents = adm_fetch_all(
        $conn,
        "SELECT issued.id, issued.doc_number, issued.qr_token, issued.pdf_path, issued.issued_at,
                dr.id AS request_id, dr.reference_no, dr.status,
                dt.name AS document_name,
                CONCAT(r.first_name, ' ', r.last_name) AS resident_name,
                issuer.fullname AS issued_by_name
         FROM issued_documents issued
         INNER JOIN document_requests dr ON dr.id = issued.request_id
         INNER JOIN document_types dt ON dt.id = dr.doc_type_id
         INNER JOIN residents r ON r.id = dr.resident_id
         LEFT JOIN users issuer ON issuer.id = issued.issued_by
         {$where_sql}
         ORDER BY issued.issued_at DESC
         LIMIT 500",
        $types,
        $params
    );
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=issued-documents-' . date('Ymd-His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Document No.', 'Reference No.', 'Document Type', 'Resident', 'Date Issued', 'Issued By', 'Status']);
    foreach ($issued_documents as $issued) {
        fputcsv($out, [
            $issued['doc_number'],
            $issued['reference_no'],
            $issued['document_name'],
            $issued['resident_name'],
            $issued['issued_at'],
            $issued['issued_by_name'],
            adm_status_label($issued['status']),
        ]);
    }
    fclose($out);
    exit();
}

$export_query = $_GET;
$export_query['export'] = 'csv';
$actions = '<a class="btn" href="requests.php"><i class="fa-solid fa-inbox"></i> Request inbox</a> ';
$actions .= '<a class="btn btn--primary" href="issued.php?' . adm_e(http_build_query($export_query)) . '"><i class="fa-solid fa-file-csv"></i> Export CSV</a>';

adm_page_start('Issued Documents', 'issued', $user, 'issued-page');
adm_page_header('Document records', 'Issued Documents Log', 'Search, filter, reprint, and export official document issuances.', $actions);
?>

<form class="filter-panel" method="get">
  <div class="filter-grid">
    <div class="form-field">
      <label for="q">Search</label>
      <input id="q" name="q" type="search" value="<?= adm_e($q) ?>" placeholder="Document no., resident, reference" data-table-search="#issuedTable">
    </div>
    <div class="form-field">
      <label for="doc_type_id">Document type</label>
      <select id="doc_type_id" name="doc_type_id">
        <option value="0">All document types</option>
        <?php foreach ($document_types as $type): ?>
          <option value="<?= adm_e($type['id']) ?>" <?= $doc_type_id === (int)$type['id'] ? 'selected' : '' ?>><?= adm_e($type['name']) ?></option>
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
    <button class="btn btn--primary" type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
    <a class="btn" href="issued.php"><i class="fa-solid fa-rotate-left"></i> Reset</a>
  </div>
</form>

<section class="panel">
  <div class="panel__header">
    <div>
      <h2>Issued Documents</h2>
      <p>Showing up to 500 matching issuance records.</p>
    </div>
  </div>

  <?php if ($issued_documents): ?>
    <div class="table-wrap">
      <table class="data-table" id="issuedTable">
        <thead>
          <tr>
            <th>Doc No.</th>
            <th>Document Type</th>
            <th>Resident Name</th>
            <th>Date Issued</th>
            <th>Issued By</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($issued_documents as $issued): ?>
            <tr>
              <td>
                <strong><?= adm_e($issued['doc_number']) ?></strong>
                <small><?= adm_e($issued['reference_no']) ?></small>
              </td>
              <td><?= adm_e($issued['document_name']) ?></td>
              <td><?= adm_e($issued['resident_name']) ?></td>
              <td><?= adm_e(adm_datetime($issued['issued_at'])) ?></td>
              <td><?= adm_e($issued['issued_by_name'] ?: 'System') ?></td>
              <td>
                <div class="table-actions">
                  <a class="btn btn--small" href="request-detail.php?id=<?= adm_e($issued['request_id']) ?>"><i class="fa-solid fa-eye"></i> View request</a>
                  <a class="btn btn--primary btn--small" href="print-document.php?id=<?= adm_e($issued['request_id']) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-print"></i> Reprint</a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="empty-state">
      <i class="fa-solid fa-file-circle-check"></i>
      <strong>No issued documents found</strong>
      <span>Approved requests will appear here once an issued document record is created.</span>
    </div>
  <?php endif; ?>
</section>

<?php adm_page_end(); ?>
