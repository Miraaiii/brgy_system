<?php
require_once __DIR__ . '/includes/resident_portal.php';

rp_ensure_blotter_evidence_table($conn);

$ctx = rp_get_resident_context($conn, true);
$has_blotter_tables = rp_table_exists($conn, 'blotter_cases') && rp_table_exists($conn, 'blotter_parties');
$errors = [];
$success_case = null;
$max_evidence_size = 10 * 1024 * 1024;
$allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf', 'mp4'];

if ($has_blotter_tables && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'file_complaint') {
    if (!bms_verify_csrf_token($_POST['csrf_token'] ?? '', 'resident_blotter_csrf')) {
        $errors[] = 'Your session expired. Please refresh and try again.';
    }

    if (!$ctx['is_verified']) {
        $errors[] = 'Your account must be approved first before filing an online complaint.';
    }

    $incident_date = trim((string)($_POST['incident_date'] ?? ''));
    $incident_time = trim((string)($_POST['incident_time'] ?? ''));
    $incident_location = trim((string)($_POST['incident_location'] ?? ''));
    $incident_type = trim((string)($_POST['incident_type'] ?? ''));
    $narrative = trim((string)($_POST['narrative'] ?? ''));
    $respondent_name = trim((string)($_POST['respondent_name'] ?? ''));
    $respondent_address = trim((string)($_POST['respondent_address'] ?? ''));
    $allowed_types = ['Noise Complaint', 'Physical Altercation', 'Property Dispute', 'Theft', 'Threat', 'Other'];

    if ($incident_date === '' || $incident_time === '') {
        $errors[] = 'Please enter the incident date and time.';
    }
    $incident_datetime = $incident_date . ' ' . $incident_time . ':00';
    $incident_timestamp = strtotime($incident_datetime);
    if (!$incident_timestamp) {
        $errors[] = 'Please enter a valid incident date and time.';
    } elseif ($incident_timestamp > time()) {
        $errors[] = 'Incident date and time cannot be in the future.';
    }
    if ($incident_location === '') {
        $errors[] = 'Please enter the incident location.';
    }
    if (!in_array($incident_type, $allowed_types, true)) {
        $errors[] = 'Please select a valid incident type.';
    }
    if (strlen($narrative) < 30) {
        $errors[] = 'Narrative must be at least 30 characters.';
    }
    if ($respondent_name === '') {
        $errors[] = 'Please enter the respondent full name.';
    }

    $evidence = null;
    if (!empty($_FILES['evidence']) && (int)($_FILES['evidence']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if ((int)$_FILES['evidence']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Evidence upload failed. Please try again.';
        } else {
            $original_name = basename((string)$_FILES['evidence']['name']);
            $size = (int)$_FILES['evidence']['size'];
            $tmp_name = (string)$_FILES['evidence']['tmp_name'];
            $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            if ($size > $max_evidence_size) {
                $errors[] = 'Evidence file must be 10MB or smaller.';
            }
            if (!in_array($extension, $allowed_extensions, true)) {
                $errors[] = 'Evidence must be a JPG, PNG, PDF, or MP4 file.';
            }
            if (!$errors) {
                $evidence = [
                    'original_name' => preg_replace('/[^A-Za-z0-9._-]+/', '_', $original_name) ?: ('evidence.' . $extension),
                    'stored_name' => 'evidence_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $extension,
                    'tmp_name' => $tmp_name,
                    'mime' => function_exists('mime_content_type') ? (string)@mime_content_type($tmp_name) : 'application/octet-stream',
                    'size' => $size,
                ];
            }
        }
    }

    if (!$errors) {
        $moved_path = null;
        $transaction_started = false;
        try {
            $conn->begin_transaction();
            $transaction_started = true;

            $case_number = rp_generate_case_number($conn);
            $stmt = $conn->prepare(
                'INSERT INTO blotter_cases (case_number, incident_date, incident_type, incident_place, narrative, status, recorded_by)
                 VALUES (?, ?, ?, ?, ?, "open", ?)'
            );
            if (!$stmt) {
                throw new Exception('Unable to prepare complaint.');
            }
            $stmt->bind_param('sssssi', $case_number, $incident_datetime, $incident_type, $incident_location, $narrative, $ctx['user_id']);
            if (!$stmt->execute()) {
                throw new Exception('Unable to save complaint.');
            }
            $case_id = (int)$stmt->insert_id;
            $stmt->close();

            $complainant_statement = 'Filed online by resident portal.';
            $party_stmt = $conn->prepare(
                'INSERT INTO blotter_parties (case_id, resident_id, party_type, non_resident_name, address, contact_number, statement)
                 VALUES (?, ?, "complainant", NULL, NULL, NULL, ?),
                        (?, NULL, "respondent", ?, ?, NULL, NULL)'
            );
            if (!$party_stmt) {
                throw new Exception('Unable to prepare parties.');
            }
            $party_stmt->bind_param(
                'iisss',
                $case_id,
                $ctx['resident_id'],
                $complainant_statement,
                $case_id,
                $respondent_name,
                $respondent_address
            );
            if (!$party_stmt->execute()) {
                throw new Exception('Unable to save parties.');
            }
            $party_stmt->close();

            if ($evidence && rp_table_exists($conn, 'blotter_evidence')) {
                $upload_dir = __DIR__ . '/../uploads/blotter_evidence/' . date('Y');
                $relative_dir = 'uploads/blotter_evidence/' . date('Y');
                if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
                    throw new Exception('Unable to create evidence folder.');
                }
                $target_path = $upload_dir . DIRECTORY_SEPARATOR . $evidence['stored_name'];
                if (!move_uploaded_file($evidence['tmp_name'], $target_path)) {
                    throw new Exception('Unable to save evidence file.');
                }
                $moved_path = $target_path;
                $relative_path = $relative_dir . '/' . $evidence['stored_name'];
                $evidence_stmt = $conn->prepare(
                    'INSERT INTO blotter_evidence (case_id, file_name, file_path, file_type, file_size, uploaded_by)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                if (!$evidence_stmt) {
                    throw new Exception('Unable to prepare evidence.');
                }
                $evidence_stmt->bind_param(
                    'isssii',
                    $case_id,
                    $evidence['original_name'],
                    $relative_path,
                    $evidence['mime'],
                    $evidence['size'],
                    $ctx['user_id']
                );
                if (!$evidence_stmt->execute()) {
                    throw new Exception('Unable to save evidence record.');
                }
                $evidence_stmt->close();
            }

            $conn->commit();
            rp_notify_complaint_submitted($conn, $ctx, $case_id, $case_number);
            header('Location: blotter.php?submitted=' . $case_id);
            exit();
        } catch (Throwable $e) {
            if ($transaction_started) {
                $conn->rollback();
            }
            if ($moved_path && is_file($moved_path)) {
                @unlink($moved_path);
            }
            $errors[] = 'We could not file the complaint right now. Please try again.';
        }
    }
}

if ($has_blotter_tables && isset($_GET['submitted'])) {
    $success_case = rp_fetch_one(
        $conn,
        'SELECT bc.id, bc.case_number, bc.incident_type, bc.created_at
         FROM blotter_cases bc
         INNER JOIN blotter_parties bp ON bp.case_id = bc.id
         WHERE bc.id = ? AND bp.resident_id = ? AND bp.party_type = "complainant"
         LIMIT 1',
        'ii',
        [(int)$_GET['submitted'], (int)$ctx['resident_id']]
    );
}

$csrf_token = bms_csrf_token('resident_blotter_csrf');
$today = date('Y-m-d');

rp_page_start('File a Complaint', 'blotter', $ctx, 'blotter-page');
?>

<?php if ($success_case): ?>
  <section class="success-panel">
    <span class="success-panel__icon"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span>
    <div>
      <p class="page-kicker">Complaint logged</p>
      <h1>Your case number is <code class="ref-code"><?= rp_e($success_case['case_number']) ?></code></h1>
      <p>Your complaint has been logged and is under review by the Barangay Secretary.</p>
      <div class="success-actions">
        <a class="primary-action" href="my-blotter.php?id=<?= rp_e($success_case['id']) ?>"><i class="fa-solid fa-scale-balanced"></i> View case</a>
        <a class="secondary-action" href="my-blotter.php"><i class="fa-solid fa-folder-open"></i> My cases</a>
        <a class="secondary-action" href="blotter.php"><i class="fa-solid fa-pen-to-square"></i> File another</a>
      </div>
    </div>
  </section>
<?php else: ?>
  <section class="portal-page-header">
    <div>
      <p class="page-kicker">Blotter / Complaints</p>
      <h1>File a Complaint</h1>
      <p>Submit a barangay-level complaint or incident report for review by the Secretary.</p>
    </div>
    <a class="secondary-action" href="my-blotter.php"><i class="fa-solid fa-scale-balanced"></i> My cases</a>
  </section>

  <div class="account-alert" role="alert">
    <i class="fa-solid fa-triangle-exclamation"></i>
    <span>This form is for barangay-level complaints only. For emergencies, call 911.</span>
  </div>

  <?php if (!$has_blotter_tables): ?>
    <div class="account-alert account-alert--danger" role="alert">
      <i class="fa-solid fa-circle-exclamation"></i>
      <span>Blotter tables are not installed yet. Please import the database schema first.</span>
    </div>
  <?php endif; ?>

  <?php if (!$ctx['is_verified']): ?>
    <div class="account-alert" role="alert">
      <i class="fa-solid fa-lock"></i>
      <span>Your account must be approved first before filing an online complaint.</span>
    </div>
  <?php endif; ?>

  <?php if ($errors): ?>
    <div class="account-alert account-alert--danger" role="alert">
      <i class="fa-solid fa-circle-exclamation"></i>
      <span><?= rp_e(implode(' ', $errors)) ?></span>
    </div>
  <?php endif; ?>

  <form class="dashboard-panel portal-form" method="post" enctype="multipart/form-data" data-max-file-size="<?= rp_e($max_evidence_size) ?>">
    <input type="hidden" name="action" value="file_complaint">
    <input type="hidden" name="csrf_token" value="<?= rp_e($csrf_token) ?>">
    <fieldset <?= (!$ctx['is_verified'] || !$has_blotter_tables) ? 'disabled' : '' ?>>
      <div class="panel-header">
        <div>
          <h2>Incident information</h2>
          <p>All required fields are on this screen.</p>
        </div>
      </div>

      <div class="form-grid">
        <div class="form-field">
          <label for="incidentDate">Incident date</label>
          <input id="incidentDate" name="incident_date" type="date" max="<?= rp_e($today) ?>" value="<?= rp_e($_POST['incident_date'] ?? '') ?>" required>
        </div>
        <div class="form-field">
          <label for="incidentTime">Incident time</label>
          <input id="incidentTime" name="incident_time" type="time" value="<?= rp_e($_POST['incident_time'] ?? '') ?>" required>
        </div>
        <div class="form-field form-field--full">
          <label for="incidentLocation">Incident location</label>
          <input id="incidentLocation" name="incident_location" type="text" placeholder="Corner Rizal & Mabini Sts." value="<?= rp_e($_POST['incident_location'] ?? '') ?>" required>
        </div>
        <div class="form-field">
          <label for="incidentType">Incident type</label>
          <select id="incidentType" name="incident_type" required>
            <option value="">Select type</option>
            <?php foreach (['Noise Complaint', 'Physical Altercation', 'Property Dispute', 'Theft', 'Threat', 'Other'] as $type): ?>
              <option value="<?= rp_e($type) ?>" <?= (($_POST['incident_type'] ?? '') === $type) ? 'selected' : '' ?>><?= rp_e($type) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-field">
          <label for="respondentName">Respondent full name</label>
          <input id="respondentName" name="respondent_name" type="text" value="<?= rp_e($_POST['respondent_name'] ?? '') ?>" required>
        </div>
        <div class="form-field form-field--full">
          <label for="respondentAddress">Respondent address <span class="field-note">Optional</span></label>
          <input id="respondentAddress" name="respondent_address" type="text" value="<?= rp_e($_POST['respondent_address'] ?? '') ?>">
        </div>
        <div class="form-field form-field--full">
          <label for="narrative">Narrative / description</label>
          <textarea id="narrative" name="narrative" rows="7" minlength="30" placeholder="Write the full account of what happened." required><?= rp_e($_POST['narrative'] ?? '') ?></textarea>
          <small class="field-help">Minimum 30 characters.</small>
        </div>
        <div class="form-field form-field--full">
          <label for="evidenceFile">Evidence file <span class="field-note">Optional, max 10MB</span></label>
          <input id="evidenceFile" name="evidence" type="file" accept=".jpg,.jpeg,.png,.pdf,.mp4">
          <small class="field-help">Accepted: JPG, PNG, PDF, MP4.</small>
        </div>
      </div>
    </fieldset>

    <div class="form-actions">
      <a class="secondary-action" href="resident_dashboard.php"><i class="fa-solid fa-arrow-left"></i> Back home</a>
      <button class="primary-action" type="submit" <?= (!$ctx['is_verified'] || !$has_blotter_tables) ? 'disabled title="Your account must be approved first."' : '' ?>><i class="fa-solid fa-paper-plane"></i> Submit complaint</button>
    </div>
  </form>
<?php endif; ?>

<?php rp_page_end(); ?>
