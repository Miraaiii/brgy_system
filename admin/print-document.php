<?php
require_once __DIR__ . '/includes/admin_helpers.php';

$user = adm_require_admin($conn, ['captain', 'secretary']);
$role = strtolower(trim((string)($user['role'] ?? '')));
$is_captain = $role === 'captain';
$is_preview = isset($_GET['preview']) && $_GET['preview'] === '1';
$request_id = (int)($_GET['id'] ?? 0);
$request = null;
$settings = adm_get_settings($conn, ['barangay_name', 'municipality', 'province', 'barangay_seal', 'captain_signature']);

if ($request_id > 0 && adm_table_exists($conn, 'document_requests') && adm_table_exists($conn, 'document_types') && adm_table_exists($conn, 'residents')) {
    $issued_select = adm_table_exists($conn, 'issued_documents') ? ', issued.doc_number, issued.qr_token, issued.issued_at' : ', NULL AS doc_number, NULL AS qr_token, NULL AS issued_at';
    $issued_join = adm_table_exists($conn, 'issued_documents') ? 'LEFT JOIN issued_documents issued ON issued.request_id = dr.id' : '';
    $request = adm_fetch_one(
        $conn,
        "SELECT dr.id, dr.reference_no, dr.purpose, dr.status, dr.approved_at, dr.released_at,
                dt.name AS document_name,
                r.first_name, r.middle_name, r.last_name, r.birth_date, r.civil_status, r.sex,
                h.house_number, h.street, h.purok,
                processor.fullname AS processed_by_name,
                approver.fullname AS approved_by_name
                {$issued_select}
         FROM document_requests dr
         INNER JOIN document_types dt ON dt.id = dr.doc_type_id
         INNER JOIN residents r ON r.id = dr.resident_id
         LEFT JOIN households h ON h.id = r.household_id
         LEFT JOIN users processor ON processor.id = dr.processed_by
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

$status = $request ? strtolower((string)$request['status']) : '';
$can_preview = $request && $is_preview && $is_captain && $status === 'for_approval';
$can_print = $request && (in_array($status, ['approved', 'released'], true) || $can_preview);
$resident_name = $request
    ? trim($request['first_name'] . ' ' . ($request['middle_name'] ? $request['middle_name'] . ' ' : '') . $request['last_name'])
    : '';
$barangay_name = trim($settings['barangay_name'] ?? '') ?: 'Barangay Sta. Rosa 1';
$municipality = trim($settings['municipality'] ?? '') ?: 'Noveleta';
$province = trim($settings['province'] ?? '') ?: 'Cavite';
$seal_path = trim($settings['barangay_seal'] ?? '') ?: 'assets/images/logo_noveleta.png';
$signature_path = trim($settings['captain_signature'] ?? '');
$active_captain = adm_table_exists($conn, 'officials')
    ? adm_fetch_one(
        $conn,
        "SELECT u.fullname
         FROM officials o
         INNER JOIN users u ON u.id = o.user_id
         WHERE o.position = 'captain' AND o.is_active = 1
         ORDER BY o.term_end DESC
         LIMIT 1"
    )
    : null;
$captain_name = trim((string)($request['approved_by_name'] ?? ''))
    ?: trim((string)($active_captain['fullname'] ?? ''))
    ?: 'Juan A. Reyes';
$address = $request
    ? trim(implode(', ', array_filter([$request['house_number'], $request['street'], $request['purok'], $barangay_name . ', ' . $municipality . ', ' . $province])))
    : '';
$doc_number = $can_preview ? 'PREVIEW' : ($request['doc_number'] ?? ('TEMP-' . date('Y') . '-' . str_pad((string)$request_id, 5, '0', STR_PAD_LEFT)));
$seal_src = '../' . ltrim(str_replace('\\', '/', $seal_path), '/');
$signature_src = $signature_path !== '' ? '../' . ltrim(str_replace('\\', '/', $signature_path), '/') : '';
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
    .signature-image {
      max-width: 210px;
      max-height: 52px;
      object-fit: contain;
      display: block;
      margin: 0 auto -8px;
    }
    .preview-ribbon {
      margin: 0 auto 14px;
      max-width: 850px;
      padding: 10px 12px;
      border: 1px solid #f59e0b;
      border-radius: 8px;
      background: #fffbeb;
      color: #92400e;
      font-weight: 800;
    }
  </style>
</head>
<body>
  <div class="print-toolbar no-print">
    <a class="btn" href="request-detail.php?id=<?= print_doc_e($request_id) ?>"><i class="fa-solid fa-arrow-left"></i> Back to detail</a>
    <?php if ($can_print): ?>
      <?php if (!$can_preview): ?>
        <button class="btn btn--primary" type="button" onclick="window.print()"><i class="fa-solid fa-print"></i> Print document</button>
      <?php endif; ?>
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
    <?php if ($can_preview): ?>
      <div class="preview-ribbon no-print">Preview only. Approval and document number are created after Captain signing.</div>
    <?php endif; ?>
    <article class="print-sheet">
      <header class="print-header">
        <img src="<?= print_doc_e($seal_src) ?>" alt="Barangay seal">
        <div>
          <p>Republic of the Philippines</p>
          <p>Province of <?= print_doc_e($province) ?></p>
          <p>Municipality of <?= print_doc_e($municipality) ?></p>
          <h1><?= print_doc_e($barangay_name) ?></h1>
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
          and a resident of <strong><?= print_doc_e($address ?: $barangay_name . ', ' . $municipality . ', ' . $province) ?></strong>,
          is known to this barangay based on the available records of this office.
        </p>
        <p>
          This certification is issued upon request for the purpose of
          <strong><?= print_doc_e($request['purpose']) ?></strong>.
        </p>
        <p>
          Issued this <?= print_doc_e(date('jS')) ?> day of <?= print_doc_e(date('F Y')) ?>
          at <?= print_doc_e($barangay_name) ?>, <?= print_doc_e($municipality) ?>, <?= print_doc_e($province) ?>.
        </p>
      </div>

      <div class="signature-row">
        <div class="signature-block">
          <?php if ($signature_src !== ''): ?>
            <img class="signature-image" src="<?= print_doc_e($signature_src) ?>" alt="">
          <?php endif; ?>
          <div class="signature-line"></div>
          <strong>HON. <?= print_doc_e(strtoupper($captain_name)) ?></strong>
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
          <dd><?= print_doc_e($request['processed_by_name'] ?: ($user['fullname'] ?: 'Barangay Secretary')) ?></dd>
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
