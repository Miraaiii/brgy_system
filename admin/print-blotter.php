<?php
require_once __DIR__ . '/includes/admin_helpers.php';

$user = adm_require_admin($conn, ['captain', 'secretary', 'kagawad']);
$case_id = (int)($_GET['id'] ?? 0);
$case = null;

if ($case_id > 0 && adm_table_exists($conn, 'blotter_cases')) {
    $case = adm_fetch_one(
        $conn,
        'SELECT bc.*, recorder.fullname AS recorded_by_name
         FROM blotter_cases bc
         LEFT JOIN users recorder ON recorder.id = bc.recorded_by
         WHERE bc.id = ?
         LIMIT 1',
        'i',
        [$case_id]
    );
}

$parties = [];
if ($case && adm_table_exists($conn, 'blotter_parties')) {
    $parties = adm_fetch_all(
        $conn,
        'SELECT bp.*,
                TRIM(CONCAT(r.first_name, " ", COALESCE(NULLIF(r.middle_name, ""), ""), " ", r.last_name)) AS resident_name
         FROM blotter_parties bp
         LEFT JOIN residents r ON r.id = bp.resident_id
         WHERE bp.case_id = ?
         ORDER BY FIELD(bp.party_type, "complainant", "respondent", "witness"), bp.id ASC',
        'i',
        [$case_id]
    );
}

$hearings = [];
if ($case && adm_table_exists($conn, 'blotter_hearings')) {
    $hearings = adm_fetch_all(
        $conn,
        'SELECT bh.*, presider.fullname AS presided_by_name
         FROM blotter_hearings bh
         LEFT JOIN users presider ON presider.id = bh.presided_by
         WHERE bh.case_id = ?
         ORDER BY bh.scheduled_at ASC',
        'i',
        [$case_id]
    );
}

$captain = adm_table_exists($conn, 'officials')
    ? adm_fetch_one(
        $conn,
        "SELECT u.fullname
         FROM officials o
         INNER JOIN users u ON u.id = o.user_id
         WHERE o.position = 'captain' AND o.is_active = 1
         ORDER BY o.id DESC
         LIMIT 1"
    )
    : null;

function print_blotter_e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$captain_name = $captain['fullname'] ?? 'Punong Barangay';
$prepared_by = $user['fullname'] ?: 'Barangay Secretary';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= print_blotter_e($case['case_number'] ?? 'Blotter Extract') ?> - Print</title>
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

    .print-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 14px;
      font-size: 13px;
    }

    .print-table th,
    .print-table td {
      border: 1px solid #cbd5e1;
      padding: 9px 10px;
      vertical-align: top;
      text-align: left;
    }

    .print-table th {
      background: #f1f5f9;
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: .06em;
    }

    .print-section-title {
      margin: 28px 0 8px;
      font-size: 15px;
      text-transform: uppercase;
      letter-spacing: .06em;
    }
  </style>
</head>
<body>
  <div class="print-toolbar no-print">
    <a class="btn" href="blotter-detail.php?id=<?= print_blotter_e($case_id) ?>"><i class="fa-solid fa-arrow-left"></i> Back to case</a>
    <?php if ($case): ?>
      <button class="btn btn--primary" type="button" onclick="window.print()"><i class="fa-solid fa-print"></i> Print extract</button>
    <?php endif; ?>
  </div>

  <?php if (!$case): ?>
    <section class="print-sheet">
      <div class="empty-state">
        <i class="fa-solid fa-folder-open"></i>
        <strong>Case not found</strong>
        <span>Open a valid blotter case before printing.</span>
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

      <h2 class="document-title">BLOTTER EXTRACT</h2>

      <dl class="definition-list">
        <div><dt>Case No.</dt><dd><?= print_blotter_e($case['case_number']) ?></dd></div>
        <div><dt>Status</dt><dd><?= print_blotter_e(adm_status_label($case['status'])) ?></dd></div>
        <div><dt>Incident Type</dt><dd><?= print_blotter_e($case['incident_type']) ?></dd></div>
        <div><dt>Incident Date</dt><dd><?= print_blotter_e(adm_datetime($case['incident_date'])) ?></dd></div>
        <div><dt>Incident Place</dt><dd><?= print_blotter_e($case['incident_place']) ?></dd></div>
        <div><dt>Recorded By</dt><dd><?= print_blotter_e($case['recorded_by_name'] ?: 'System') ?></dd></div>
      </dl>

      <div class="document-body">
        <p>
          This is to certify that the following entry is an extract from the blotter records
          of Barangay Sta. Rosa 1, Noveleta, Cavite.
        </p>
        <p><strong>Narrative:</strong><br><?= nl2br(print_blotter_e($case['narrative'])) ?></p>
        <?php if (!empty($case['resolution'])): ?>
          <p><strong>Resolution / Notes:</strong><br><?= nl2br(print_blotter_e($case['resolution'])) ?></p>
        <?php endif; ?>
      </div>

      <h3 class="print-section-title">Parties</h3>
      <?php if ($parties): ?>
        <table class="print-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Type</th>
              <th>Address</th>
              <th>Contact</th>
              <th>Statement</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($parties as $party): ?>
              <tr>
                <td><?= print_blotter_e($party['resident_name'] ?: $party['non_resident_name'] ?: 'Unnamed party') ?></td>
                <td><?= print_blotter_e(adm_status_label($party['party_type'])) ?></td>
                <td><?= print_blotter_e($party['address'] ?: 'Not set') ?></td>
                <td><?= print_blotter_e($party['contact_number'] ?: 'Not set') ?></td>
                <td><?= print_blotter_e($party['statement'] ?: 'No statement recorded') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p>No parties recorded.</p>
      <?php endif; ?>

      <h3 class="print-section-title">Hearing History</h3>
      <?php if ($hearings): ?>
        <table class="print-table">
          <thead>
            <tr>
              <th>Schedule</th>
              <th>Location</th>
              <th>Status</th>
              <th>Presider</th>
              <th>Minutes</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($hearings as $hearing): ?>
              <tr>
                <td><?= print_blotter_e(adm_datetime($hearing['scheduled_at'])) ?></td>
                <td><?= print_blotter_e($hearing['location']) ?></td>
                <td><?= print_blotter_e(adm_status_label($hearing['status'])) ?></td>
                <td><?= print_blotter_e($hearing['presided_by_name'] ?: 'Not assigned') ?></td>
                <td><?= print_blotter_e($hearing['minutes'] ?: 'No minutes recorded') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p>No hearings recorded.</p>
      <?php endif; ?>

      <div class="signature-row">
        <div class="signature-block">
          <div class="signature-line"></div>
          <strong><?= print_blotter_e(strtoupper($captain_name)) ?></strong>
          <p>Punong Barangay</p>
        </div>
      </div>

      <dl class="definition-list" style="margin-top: 42px;">
        <div>
          <dt>Prepared By</dt>
          <dd><?= print_blotter_e($prepared_by) ?></dd>
        </div>
        <div>
          <dt>Date Printed</dt>
          <dd><?= print_blotter_e(date('F j, Y, g:i A')) ?></dd>
        </div>
      </dl>
    </article>
  <?php endif; ?>
</body>
</html>
