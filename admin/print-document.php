<?php
require_once __DIR__ . '/includes/admin_helpers.php';

$user = adm_require_secretary($conn);
$request_id = (int)($_GET['id'] ?? 0);
$request = null;

if ($request_id > 0 && adm_table_exists($conn, 'document_requests') && adm_table_exists($conn, 'document_types') && adm_table_exists($conn, 'residents')) {
    $issued_select = adm_table_exists($conn, 'issued_documents') ? ', issued.doc_number, issued.qr_token, issued.issued_at' : ', NULL AS doc_number, NULL AS qr_token, NULL AS issued_at';
    $issued_join = adm_table_exists($conn, 'issued_documents') ? 'LEFT JOIN issued_documents issued ON issued.request_id = dr.id' : '';
    $request = adm_fetch_one(
        $conn,
        "SELECT dr.id, dr.reference_no, dr.purpose, dr.status, dr.approved_at, dr.released_at,
                dt.name AS document_name,
                r.first_name, r.middle_name, r.last_name, r.birth_date, r.civil_status, r.sex,
                h.house_number, h.street, h.purok,
                approver.fullname AS approved_by_name
                {$issued_select}
         FROM document_requests dr
         INNER JOIN document_types dt ON dt.id = dr.doc_type_id
         INNER JOIN residents r ON r.id = dr.resident_id
         LEFT JOIN households h ON h.id = r.household_id
         LEFT JOIN users approver ON approver.id = dr.approved_by
         {$issued_join}
         WHERE dr.id = ?
         LIMIT 1",
        'i',
        [$request_id]
    );
}

function print_doc_e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$can_print = $request && in_array(strtolower((string)$request['status']), ['approved', 'released'], true);
$resident_name = $request
    ? trim($request['first_name'] . ' ' . ($request['middle_name'] ? $request['middle_name'] . ' ' : '') . $request['last_name'])
    : '';
$address = $request
    ? trim(implode(', ', array_filter([$request['house_number'], $request['street'], $request['purok'], 'Barangay Sta. Rosa 1, Noveleta, Cavite'])))
    : '';
$doc_number = $request['doc_number'] ?? ('TEMP-' . date('Y') . '-' . str_pad((string)$request_id, 5, '0', STR_PAD_LEFT));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= print_doc_e($request['reference_no'] ?? 'Document') ?> - Print</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/secretary.css?v=20260605b">
  <style>
    body {
      padding: 24px;
    }
    .print-toolbar {
      max-width: 850px;
      margin: 0 auto 14px;
      display: flex;
      justify-content: space-between;
      gap: 10px;
      flex-wrap: wrap;
    }
  </style>
</head>
<body>
  <div class="print-toolbar no-print">
    <a class="btn" href="request-detail.php?id=<?= print_doc_e($request_id) ?>"><i class="fa-solid fa-arrow-left"></i> Back to detail</a>
    <?php if ($can_print): ?>
      <button class="btn btn--primary" type="button" onclick="window.print()"><i class="fa-solid fa-print"></i> Print document</button>
    <?php endif; ?>
  </div>

  <?php if (!$request): ?>
    <section class="print-sheet">
      <div class="empty-state">
        <i class="fa-solid fa-folder-open"></i>
        <strong>Request not found</strong>
        <span>Open a valid approved request before printing.</span>
      </div>
    </section>
  <?php elseif (!$can_print): ?>
    <section class="print-sheet">
      <div class="empty-state">
        <i class="fa-solid fa-lock"></i>
        <strong>Document is not ready for printing</strong>
        <span>Only approved or released requests can be printed. Current status: <?= print_doc_e(adm_status_label($request['status'])) ?>.</span>
      </div>
    </section>
  <?php else: ?>
    <article class="print-sheet">
      <header class="print-header">
        <img src="../assets/images/logo_noveleta.png" alt="Barangay seal">
        <div>
          <p>Republic of the Philippines</p>
          <p>Province of Cavite</p>
          <p>Municipality of Noveleta</p>
          <h1>Barangay Sta. Rosa 1</h1>
          <p>Office of the Punong Barangay</p>
        </div>
        <div></div>
      </header>

      <h2 class="document-title"><?= print_doc_e(strtoupper((string)$request['document_name'])) ?></h2>

      <div class="document-body">
        <p>To whom it may concern:</p>
        <p>
          This is to certify that <strong><?= print_doc_e($resident_name) ?></strong>,
          of legal age, <?= print_doc_e(adm_status_label($request['civil_status'])) ?>,
          and a resident of <strong><?= print_doc_e($address ?: 'Barangay Sta. Rosa 1, Noveleta, Cavite') ?></strong>,
          is known to this barangay based on the available records of this office.
        </p>
        <p>
          This certification is issued upon request for the purpose of
          <strong><?= print_doc_e($request['purpose']) ?></strong>.
        </p>
        <p>
          Issued this <?= print_doc_e(date('jS')) ?> day of <?= print_doc_e(date('F Y')) ?>
          at Barangay Sta. Rosa 1, Noveleta, Cavite.
        </p>
      </div>

      <div class="signature-row">
        <div class="signature-block">
          <div class="signature-line"></div>
          <strong>HON. JUAN A. REYES</strong>
          <p>Punong Barangay</p>
        </div>
      </div>

      <dl class="definition-list" style="margin-top: 42px;">
        <div>
          <dt>Document No.</dt>
          <dd><?= print_doc_e($doc_number) ?></dd>
        </div>
        <div>
          <dt>Reference No.</dt>
          <dd><?= print_doc_e($request['reference_no']) ?></dd>
        </div>
        <div>
          <dt>Prepared By</dt>
          <dd><?= print_doc_e($user['fullname'] ?: 'Barangay Secretary') ?></dd>
        </div>
        <div>
          <dt>Verification Token</dt>
          <dd><?= print_doc_e($request['qr_token'] ? substr((string)$request['qr_token'], 0, 24) . '...' : 'Pending QR token') ?></dd>
        </div>
      </dl>
    </article>
  <?php endif; ?>
</body>
</html>
