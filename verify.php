<?php
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/config/connection.php';

function v_e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function v_datetime($value) {
    $time = strtotime((string)$value);
    return $time ? date('F j, Y, g:i A', $time) : 'Not set';
}

function v_fetch_one($conn, $sql, $types = '', array $params = []) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    if ($types !== '') {
        $refs = [];
        foreach ($params as $key => $value) {
            $refs[$key] = &$params[$key];
        }
        $stmt->bind_param($types, ...$refs);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

function v_table_exists($conn, $table) {
    $safe = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $result && $result->num_rows > 0;
}

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$document = null;
$searched = $token !== '';

if ($searched && v_table_exists($conn, 'issued_documents')) {
    $document = v_fetch_one(
        $conn,
        'SELECT issued.doc_number, issued.qr_token, issued.issued_at,
                dt.name AS document_name,
                CONCAT(r.first_name, " ", COALESCE(NULLIF(r.middle_name, ""), ""), " ", r.last_name) AS resident_name,
                u.fullname AS issued_by_name
         FROM issued_documents issued
         INNER JOIN document_requests dr ON dr.id = issued.request_id
         INNER JOIN document_types dt ON dt.id = dr.doc_type_id
         INNER JOIN residents r ON r.id = dr.resident_id
         LEFT JOIN users u ON u.id = issued.issued_by
         WHERE issued.qr_token = ?
         LIMIT 1',
        's',
        [$token]
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verify Document - Barangay Sta. Rosa 1</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/resident_dashboard.css">
</head>
<body class="verify-public-body">
  <main class="verify-public-shell">
    <section class="portal-page-header verify-hero">
      <div>
        <p class="page-kicker">Public verification</p>
        <h1>Verify Document</h1>
        <p>Enter or scan the QR code token from an issued barangay document.</p>
      </div>
      <a class="secondary-action" href="index.php"><i class="fa-solid fa-house"></i> Public site</a>
    </section>

    <section class="detail-grid">
      <form class="dashboard-panel portal-form" method="post">
        <div class="panel-header">
          <div>
            <h2>QR token</h2>
            <p>Manual token entry is always available.</p>
          </div>
        </div>
        <div class="form-field">
          <label for="qrToken">Token</label>
          <input id="qrToken" name="token" value="<?= v_e($token) ?>" placeholder="Enter QR token" required>
        </div>
        <div class="form-actions">
          <button class="primary-action" type="submit"><i class="fa-solid fa-shield-check"></i> Verify token</button>
        </div>
      </form>

      <div class="dashboard-panel qr-scan-panel" data-qr-scanner>
        <div class="panel-header">
          <div>
            <h2>Scan with camera</h2>
            <p>Use a supported browser to scan the printed QR code.</p>
          </div>
        </div>
        <video id="qrVideo" class="qr-video" playsinline muted hidden></video>
        <button class="secondary-action" type="button" data-start-qr><i class="fa-solid fa-camera"></i> Start scanner</button>
        <p class="field-help" data-qr-message>Camera scanning uses your browser's built-in QR support when available.</p>
      </div>
    </section>

    <?php if ($searched && $document): ?>
      <section class="dashboard-panel verify-result verify-result--valid">
        <span class="success-panel__icon"><i class="fa-solid fa-circle-check"></i></span>
        <div>
          <p class="page-kicker">Valid document</p>
          <h2><?= v_e($document['document_name']) ?></h2>
          <dl class="summary-list">
            <div><dt>Resident name</dt><dd><?= v_e(trim(preg_replace('/\s+/', ' ', $document['resident_name']))) ?></dd></div>
            <div><dt>Date issued</dt><dd><?= v_e(v_datetime($document['issued_at'])) ?></dd></div>
            <div><dt>Issued by</dt><dd><?= v_e($document['issued_by_name'] ?: 'Barangay Sta. Rosa 1') ?></dd></div>
            <div><dt>Official document number</dt><dd><code class="ref-code"><?= v_e($document['doc_number']) ?></code></dd></div>
          </dl>
        </div>
      </section>
    <?php elseif ($searched): ?>
      <div class="account-alert account-alert--danger" role="alert">
        <i class="fa-solid fa-circle-xmark"></i>
        <span>Document not found or may have been tampered with.</span>
      </div>
    <?php endif; ?>
  </main>
  <script src="assets/js/resident_dashboard.js"></script>
</body>
</html>
