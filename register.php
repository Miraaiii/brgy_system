<?php
session_start();
include 'config/connection.php';
include_once 'config/auth_helpers.php';
require_once 'includes/public_nav.php';

define('VALID_ID_MAX_BYTES', 5 * 1024 * 1024);
define('PENDING_REGISTRATION_TABLE', 'pending_resident_registrations');
define('LEGACY_REGISTRATION_TABLE', 'resident_registration_applications');

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['credential'])) {
    $_SESSION['error_message'] = "Please complete the resident registration form, including your valid ID upload.";
    header("Location: register.php");
    exit();
}

function json_error($message, $field = '') {
    $payload = ["status" => "error", "message" => $message];
    if ($field !== '') {
        $payload["field"] = $field;
    }
    echo json_encode($payload);
    exit();
}

function get_table_columns($conn, $table) {
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }

    $columns = [];
    $result = $conn->query("SHOW COLUMNS FROM `$table`");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[$row['Field']] = true;
        }
    }

    $cache[$table] = $columns;
    return $columns;
}

function table_exists($conn, $table) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS table_count
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = ?
    ");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("s", $table);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return isset($row['table_count']) && (int)$row['table_count'] > 0;
}

function ensure_pending_resident_registrations_table($conn) {
    if (!table_exists($conn, PENDING_REGISTRATION_TABLE) && table_exists($conn, LEGACY_REGISTRATION_TABLE)) {
        if (!$conn->query("RENAME TABLE `" . LEGACY_REGISTRATION_TABLE . "` TO `" . PENDING_REGISTRATION_TABLE . "`")) {
            throw new Exception("Unable to update registration storage.");
        }
    }

    $sql = "
        CREATE TABLE IF NOT EXISTS pending_resident_registrations (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          user_id INT UNSIGNED NOT NULL,
          first_name VARCHAR(60) NOT NULL,
          middle_name VARCHAR(60) NULL DEFAULT NULL,
          last_name VARCHAR(60) NOT NULL,
          email VARCHAR(120) NOT NULL,
          mobile_number VARCHAR(11) NOT NULL,
          birth_date DATE NOT NULL,
          birth_place VARCHAR(120) NOT NULL,
          sex ENUM('male','female') NOT NULL,
          civil_status ENUM('single','married','widowed','separated','annulled') NOT NULL DEFAULT 'single',
          nationality VARCHAR(60) NOT NULL DEFAULT 'Filipino',
          occupation VARCHAR(80) NULL DEFAULT NULL,
          house_number VARCHAR(20) NULL DEFAULT NULL,
          street_name VARCHAR(100) NOT NULL,
          purok_zone VARCHAR(40) NULL DEFAULT NULL,
          valid_id_path VARCHAR(255) NOT NULL,
          valid_id_original_name VARCHAR(200) NOT NULL,
          valid_id_mime_type VARCHAR(80) NOT NULL,
          valid_id_size INT UNSIGNED NOT NULL,
          terms_agreed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
          reviewed_by INT UNSIGNED NULL DEFAULT NULL,
          reviewed_at TIMESTAMP NULL DEFAULT NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY uq_pending_reg_user (user_id),
          KEY idx_pending_reg_status (status),
          KEY idx_pending_reg_email (email),
          CONSTRAINT fk_pending_reg_user FOREIGN KEY (user_id)
            REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
          CONSTRAINT fk_pending_reg_reviewer FOREIGN KEY (reviewed_by)
            REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    if (!$conn->query($sql)) {
        throw new Exception("Unable to prepare registration storage.");
    }
}

function insert_pending_user($conn, $data) {
    $columns = get_table_columns($conn, 'users');
    $insert = [
        'username' => $data['email'],
        'email' => $data['email'],
        'password_hash' => $data['password_hash'],
        'role' => 'resident'
    ];

    if (isset($columns['fullname'])) {
        $insert['fullname'] = $data['fullname'];
    }
    if (isset($columns['contact'])) {
        $insert['contact'] = $data['mobile_number'];
    }
    if (isset($columns['purok'])) {
        $insert['purok'] = $data['purok_zone'];
    }
    if (isset($columns['status'])) {
        $insert['status'] = 'pending';
    }

    $fieldNames = array_keys($insert);
    $quotedFields = array_map(fn($field) => "`$field`", $fieldNames);
    $placeholders = implode(", ", array_fill(0, count($fieldNames), "?"));
    $sql = "INSERT INTO users (" . implode(", ", $quotedFields) . ") VALUES ($placeholders)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Unable to prepare user account.");
    }

    $params = array_values($insert);
    $types = str_repeat("s", count($params));
    $refs = [];
    foreach ($params as $key => $value) {
        $refs[$key] = &$params[$key];
    }
    $stmt->bind_param($types, ...$refs);

    if (!$stmt->execute()) {
        throw new Exception("Unable to create user account.");
    }

    return $conn->insert_id;
}

function handle_valid_id_upload() {
    if (!isset($_FILES['valid_id']) || !is_array($_FILES['valid_id'])) {
        json_error("Please upload a valid government-issued ID.", "valid_id");
    }

    $file = $_FILES['valid_id'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
            json_error("Valid ID must not exceed 5MB.", "valid_id");
        }
        json_error("Please upload a valid government-issued ID.", "valid_id");
    }

    if ($file['size'] <= 0 || $file['size'] > VALID_ID_MAX_BYTES) {
        json_error("Valid ID must not exceed 5MB.", "valid_id");
    }

    $originalName = $file['name'];
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
    if (!in_array($extension, $allowedExtensions, true)) {
        json_error("Valid ID must be a JPG, PNG, or PDF file.", "valid_id");
    }

    $allowedMimeTypes = ['image/jpeg', 'image/png', 'application/pdf'];
    $mimeType = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        }
    }
    if ($mimeType === '') {
        $mimeType = $file['type'];
    }
    if (!in_array($mimeType, $allowedMimeTypes, true)) {
        json_error("Valid ID must be a JPG, PNG, or PDF file.", "valid_id");
    }

    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'valid_ids';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        json_error("Unable to prepare upload folder.", "valid_id");
    }

    $safeName = 'valid_id_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $safeName;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        json_error("Unable to save uploaded valid ID.", "valid_id");
    }

    return [
        'path' => 'uploads/valid_ids/' . $safeName,
        'absolute_path' => $targetPath,
        'original_name' => $originalName,
        'mime_type' => $mimeType,
        'size' => (int)$file['size']
    ];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    header('Content-Type: application/json');

    if (!bms_verify_csrf_token($_POST['csrf_token'] ?? '')) {
        json_error("Your session expired. Please refresh the page and try again.", "csrf_token");
    }

    $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
    $middle_name = isset($_POST['middle_name']) ? trim($_POST['middle_name']) : '';
    $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $mobile_number = isset($_POST['mobile_number']) ? trim($_POST['mobile_number']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';
    $birth_date = isset($_POST['birth_date']) ? trim($_POST['birth_date']) : '';
    $birth_place = isset($_POST['birth_place']) ? trim($_POST['birth_place']) : '';
    $sex = isset($_POST['sex']) ? strtolower(trim($_POST['sex'])) : '';
    $civil_status = isset($_POST['civil_status']) ? strtolower(trim($_POST['civil_status'])) : '';
    $nationality = isset($_POST['nationality']) ? trim($_POST['nationality']) : 'Filipino';
    $occupation = isset($_POST['occupation']) ? trim($_POST['occupation']) : '';
    $house_number = isset($_POST['house_number']) ? trim($_POST['house_number']) : '';
    $street_name = isset($_POST['street_name']) ? trim($_POST['street_name']) : '';
    $purok_zone = isset($_POST['purok_zone']) ? trim($_POST['purok_zone']) : '';
    $agree_terms = isset($_POST['agree_terms']) ? $_POST['agree_terms'] : '';

    if ($middle_name === '') $middle_name = null;
    if ($occupation === '') $occupation = null;
    if ($house_number === '') $house_number = null;
    if ($purok_zone === '') $purok_zone = null;

    if ($first_name === '' || $last_name === '' || $email === '' || $mobile_number === '' || $password === '' || $confirm_password === '') {
        json_error("Please complete all required account credential fields.");
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_error("Please enter a valid email address.", "email");
    }
    if (!preg_match('/^09\d{9}$/', $mobile_number)) {
        json_error("Mobile number must be 11 digits and start with 09.", "mobile_number");
    }
    $passwordErrors = bms_password_errors($password);
    if (!empty($passwordErrors)) {
        json_error($passwordErrors[0], "password");
    }
    if ($password !== $confirm_password) {
        json_error("Passwords do not match.", "confirm_password");
    }

    if ($birth_date === '' || $birth_place === '' || $sex === '' || $civil_status === '' || $nationality === '') {
        json_error("Please complete all required personal information fields.");
    }

    $birthDate = DateTime::createFromFormat('Y-m-d', $birth_date);
    $dateErrors = DateTime::getLastErrors();
    if (!$birthDate || ($dateErrors && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))) {
        json_error("Please enter a valid date of birth.", "birth_date");
    }
    $today = new DateTime('today');
    if ($birthDate > $today) {
        json_error("Date of birth cannot be in the future.", "birth_date");
    }
    $age = $birthDate->diff($today)->y;
    if ($age < 18) {
        json_error("You must be at least 18 years old to register.", "birth_date");
    }

    if (!in_array($sex, ['male', 'female'], true)) {
        json_error("Please select a valid sex.", "sex");
    }
    if (!in_array($civil_status, ['single', 'married', 'widowed', 'separated', 'annulled'], true)) {
        json_error("Please select a valid civil status.", "civil_status");
    }
    if ($street_name === '') {
        json_error("Street name is required.", "street_name");
    }
    if (!in_array($agree_terms, ['1', 'true', 'on'], true)) {
        json_error("You must agree to the terms before registering.", "agree_terms");
    }

    $check = "SELECT id FROM users WHERE email = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $check);
    mysqli_stmt_bind_param($stmt, "s", $email);
    $stmt->execute();
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        json_error("Email already exists.", "email");
    }

    $validId = handle_valid_id_upload();
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $fullname = trim($first_name . ' ' . ($middle_name ? $middle_name . ' ' : '') . $last_name);
    $terms_agreed_at = date('Y-m-d H:i:s');
    $transactionStarted = false;

    try {
        ensure_pending_resident_registrations_table($conn);
        $conn->begin_transaction();
        $transactionStarted = true;

        $user_id = insert_pending_user($conn, [
            'email' => $email,
            'password_hash' => $hashedPassword,
            'fullname' => $fullname,
            'mobile_number' => $mobile_number,
            'purok_zone' => $purok_zone
        ]);

        $stmt_app = $conn->prepare("
            INSERT INTO pending_resident_registrations (
                user_id, first_name, middle_name, last_name, email, mobile_number,
                birth_date, birth_place, sex, civil_status, nationality, occupation,
                house_number, street_name, purok_zone, valid_id_path,
                valid_id_original_name, valid_id_mime_type, valid_id_size, terms_agreed_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if (!$stmt_app) {
            throw new Exception("Unable to prepare registration application.");
        }

        $valid_id_path = $validId['path'];
        $valid_id_original_name = $validId['original_name'];
        $valid_id_mime_type = $validId['mime_type'];
        $valid_id_size = $validId['size'];
        $types = "i" . str_repeat("s", 17) . "is";
        $stmt_app->bind_param(
            $types,
            $user_id,
            $first_name,
            $middle_name,
            $last_name,
            $email,
            $mobile_number,
            $birth_date,
            $birth_place,
            $sex,
            $civil_status,
            $nationality,
            $occupation,
            $house_number,
            $street_name,
            $purok_zone,
            $valid_id_path,
            $valid_id_original_name,
            $valid_id_mime_type,
            $valid_id_size,
            $terms_agreed_at
        );

        if (!$stmt_app->execute()) {
            throw new Exception("Unable to submit registration application.");
        }

        $conn->commit();
        $_SESSION['account_status_notice'] = [
            'status' => 'pending',
            'email' => $email,
            'message' => 'Your account is awaiting approval by the Secretary'
        ];
        echo json_encode([
            "status" => "success",
            "message" => "Registration submitted successfully. Your account is awaiting approval by the Secretary.",
            "redirect" => "account_status.php"
        ]);
    } catch (Throwable $e) {
        if ($transactionStarted) {
            $conn->rollback();
        }
        if (!empty($validId['absolute_path']) && file_exists($validId['absolute_path'])) {
            @unlink($validId['absolute_path']);
        }
        error_log("[BMS] Registration failed: " . $e->getMessage());
        echo json_encode([
            "status" => "error",
            "message" => "Failed to submit registration. Please try again."
        ]);
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Register - Barangay Sta. Rosa 1</title>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet" />

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" />

  <link rel="stylesheet" href="assets/css/login_register.css?v=20260605a" />
  <link rel="shortcut icon" href="assets/images/logo_noveleta.png" />
</head>
<body>

  <?php render_public_nav('register'); ?>

  <div class="auth-container">
    <div class="card-wrapper register-wrapper" id="cardWrapper">
      <div class="panel panel--signup" id="signupPanel" style="position: relative; opacity: 1; pointer-events: all; transform: none;">
        <div class="panel__inner">
          <div class="panel__header">
            <div class="logo-mark"><img src="assets/images/logo_noveleta.png" alt="logo" /></div>
            <h1 class="panel__title">Create Account</h1>
            <p class="panel__sub">Submit your resident account for Secretary approval</p>
          </div>

          <form id="signupForm" action="register.php" method="post" enctype="multipart/form-data" onsubmit="return false;" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(bms_csrf_token(), ENT_QUOTES, 'UTF-8') ?>" />
            <div class="register-progress" aria-label="Registration progress">
              <button type="button" class="progress-step is-active" data-register-step="1" aria-current="step">
                <span>1</span>
                <strong>Credentials</strong>
              </button>
              <button type="button" class="progress-step" data-register-step="2">
                <span>2</span>
                <strong>Personal</strong>
              </button>
              <button type="button" class="progress-step" data-register-step="3">
                <span>3</span>
                <strong>Verification</strong>
              </button>
            </div>

            <div class="form-alert" id="signupStepError" role="alert" hidden></div>

            <fieldset class="signup-step is-active" data-step-panel="1">
              <legend>Account Credentials</legend>
              <div class="form-grid">
                <div class="form-group">
                  <label class="form-label" for="su-first-name">
                    <i class="fa-regular fa-user"></i> First Name
                  </label>
                  <input class="form-input" id="su-first-name" name="first_name" type="text" autocomplete="given-name" required />
                </div>

                <div class="form-group">
                  <label class="form-label" for="su-middle-name">
                    <i class="fa-regular fa-user"></i> Middle Name
                  </label>
                  <input class="form-input" id="su-middle-name" name="middle_name" type="text" autocomplete="additional-name" />
                </div>

                <div class="form-group">
                  <label class="form-label" for="su-last-name">
                    <i class="fa-regular fa-user"></i> Last Name
                  </label>
                  <input class="form-input" id="su-last-name" name="last_name" type="text" autocomplete="family-name" required />
                </div>

                <div class="form-group">
                  <label class="form-label" for="su-email">
                    <i class="fa-regular fa-envelope"></i> Email Address
                  </label>
                  <input class="form-input" id="su-email" name="email" type="email" placeholder="name@example.com" autocomplete="email" required />
                </div>

                <div class="form-group">
                  <label class="form-label" for="su-contact">
                    <i class="fa-solid fa-phone"></i> Mobile Number
                  </label>
                  <input class="form-input" id="su-contact" name="mobile_number" type="tel" inputmode="numeric" maxlength="11" placeholder="09XXXXXXXXX" autocomplete="tel" required />
                </div>

                <div class="form-group">
                  <label class="form-label" for="su-pass">
                    <i class="fa-solid fa-lock"></i> Password
                  </label>
                  <div class="input-wrap">
                    <input class="form-input" id="su-pass" name="password" type="password" autocomplete="new-password" required />
                    <button type="button" class="eye-btn" data-target="su-pass" aria-label="Toggle password">
                      <i class="fa-regular fa-eye"></i>
                    </button>
                  </div>
                </div>

                <div class="form-group">
                  <label class="form-label" for="su-confirm-pass">
                    <i class="fa-solid fa-lock"></i> Confirm Password
                  </label>
                  <div class="input-wrap">
                    <input class="form-input" id="su-confirm-pass" name="confirm_password" type="password" autocomplete="new-password" required />
                    <button type="button" class="eye-btn" data-target="su-confirm-pass" aria-label="Toggle password">
                      <i class="fa-regular fa-eye"></i>
                    </button>
                  </div>
                </div>

                <div class="password-requirements col-span-2" id="passwordRequirements">
                  <div class="req-title">Password Requirements</div>
                  <ul>
                    <li id="req-length" class="requirement invalid">
                      <i class="fa-solid fa-circle-xmark"></i> At least 8 characters
                    </li>
                    <li id="req-upper" class="requirement invalid">
                      <i class="fa-solid fa-circle-xmark"></i> At least one uppercase letter
                    </li>
                    <li id="req-number" class="requirement invalid">
                      <i class="fa-solid fa-circle-xmark"></i> At least one number
                    </li>
                    <li id="req-match" class="requirement invalid">
                      <i class="fa-solid fa-circle-xmark"></i> Passwords must match
                    </li>
                  </ul>
                </div>
              </div>
            </fieldset>

            <fieldset class="signup-step" data-step-panel="2">
              <legend>Personal Information</legend>
              <div class="form-grid">
                <div class="form-group">
                  <label class="form-label" for="su-birth-date">
                    <i class="fa-regular fa-calendar"></i> Date of Birth
                  </label>
                  <input class="form-input" id="su-birth-date" name="birth_date" type="date" required />
                </div>

                <div class="form-group">
                  <label class="form-label" for="su-age">
                    <i class="fa-solid fa-user-check"></i> Age
                  </label>
                  <input class="form-input" id="su-age" type="text" value="Auto-calculated" readonly />
                </div>

                <div class="form-group col-span-2">
                  <label class="form-label" for="su-birth-place">
                    <i class="fa-solid fa-location-dot"></i> Place of Birth
                  </label>
                  <input class="form-input" id="su-birth-place" name="birth_place" type="text" required />
                </div>

                <div class="form-group">
                  <label class="form-label" for="su-sex">
                    <i class="fa-solid fa-venus-mars"></i> Sex
                  </label>
                  <select class="form-input" id="su-sex" name="sex" required>
                    <option value="">Select sex</option>
                    <option value="female">Female</option>
                    <option value="male">Male</option>
                  </select>
                </div>

                <div class="form-group">
                  <label class="form-label" for="su-civil-status">
                    <i class="fa-solid fa-ring"></i> Civil Status
                  </label>
                  <select class="form-input" id="su-civil-status" name="civil_status" required>
                    <option value="">Select status</option>
                    <option value="single">Single</option>
                    <option value="married">Married</option>
                    <option value="widowed">Widowed</option>
                    <option value="separated">Separated</option>
                    <option value="annulled">Annulled</option>
                  </select>
                </div>

                <div class="form-group">
                  <label class="form-label" for="su-nationality">
                    <i class="fa-solid fa-flag"></i> Nationality
                  </label>
                  <input class="form-input" id="su-nationality" name="nationality" type="text" value="Filipino" required />
                </div>

                <div class="form-group">
                  <label class="form-label" for="su-occupation">
                    <i class="fa-solid fa-briefcase"></i> Occupation/Livelihood
                  </label>
                  <input class="form-input" id="su-occupation" name="occupation" type="text" />
                </div>
              </div>
            </fieldset>

            <fieldset class="signup-step" data-step-panel="3">
              <legend>Address and Verification</legend>
              <div class="form-grid">
                <div class="form-group">
                  <label class="form-label" for="su-house-number">
                    <i class="fa-solid fa-house"></i> House Number
                  </label>
                  <input class="form-input" id="su-house-number" name="house_number" type="text" autocomplete="address-line1" />
                </div>

                <div class="form-group">
                  <label class="form-label" for="su-street-name">
                    <i class="fa-solid fa-road"></i> Street Name
                  </label>
                  <input class="form-input" id="su-street-name" name="street_name" type="text" autocomplete="address-line2" required />
                </div>

                <div class="form-group col-span-2">
                  <label class="form-label" for="su-purok-zone">
                    <i class="fa-solid fa-map-location-dot"></i> Purok/Zone
                  </label>
                  <input class="form-input" id="su-purok-zone" name="purok_zone" type="text" />
                </div>

                <div class="form-group col-span-2">
                  <label class="form-label" for="su-valid-id">
                    <i class="fa-solid fa-id-card"></i> Upload Valid ID
                  </label>
                  <input type="hidden" name="MAX_FILE_SIZE" value="5242880" />
                  <input class="form-input file-input" id="su-valid-id" name="valid_id" type="file" accept=".jpg,.jpeg,.png,.pdf" required />
                  <div class="valid-id-guide" id="validIdGuide">
                    <p class="field-hint">Upload a clear photo or scan of one government-issued ID showing your full name and photo. Accepted file types: JPG, PNG, or PDF up to 5MB.</p>
                    <p class="valid-id-guide__title">Accepted ID examples:</p>
                    <ul class="valid-id-list">
                      <li>Philippine National ID / ePhilID</li>
                      <li>Passport</li>
                      <li>Driver's License</li>
                      <li>UMID, SSS, or GSIS ID</li>
                      <li>Voter's ID or COMELEC Certification</li>
                      <li>PhilHealth ID</li>
                      <li>PRC ID</li>
                      <li>Postal ID</li>
                      <li>Senior Citizen ID or PWD ID</li>
                      <li>NBI or Police Clearance</li>
                    </ul>
                  </div>
                  <p class="field-hint file-meta" id="su-valid-id-meta" aria-live="polite">No file selected.</p>
                </div>

                <div class="terms-box col-span-2">
                  <label class="checkbox-wrap">
                    <input type="checkbox" id="su-terms" name="agree_terms" value="1" required />
                    <span class="checkmark"></span>
                    <span class="checkbox-label">I certify that the information provided is true and agree to the barangay account verification terms.</span>
                  </label>
                </div>
              </div>
            </fieldset>

            <div class="wizard-actions">
              <button class="btn btn--ghost" id="signupBackBtn" type="button" hidden>
                <i class="fa-solid fa-arrow-left"></i>
                <span>Back</span>
              </button>
              <button class="btn btn--primary" id="signupNextBtn" type="button">
                <span>Next</span>
                <i class="fa-solid fa-arrow-right"></i>
              </button>
              <button class="btn btn--primary" id="signupBtn" type="submit" hidden>
                <span>Submit Registration</span>
                <i class="fa-solid fa-paper-plane"></i>
              </button>
            </div>
          </form>

          <p class="switch-text">Already have an account?
            <a href="login.php" class="switch-link">Log In</a>
          </p>
        </div>
      </div>
    </div>
  </div>

  <script>const AUTH_CSRF_TOKEN = "<?= htmlspecialchars(bms_csrf_token(), ENT_QUOTES, 'UTF-8') ?>";</script>
  <script src="assets/js/login_register.js?v=20260603b"></script>
  <?php if (isset($_SESSION['error_message'])): ?>
    <script>
      window.addEventListener('DOMContentLoaded', () => {
        if (typeof showToast === 'function') {
          showToast("<?= addslashes($_SESSION['error_message']) ?>");
        }
      });
    </script>
    <?php unset($_SESSION['error_message']); ?>
  <?php endif; ?>
  <script>
    const navToggle = document.getElementById('navToggle');
    const mobileMenu = document.getElementById('mobileMenu');
    if (navToggle && mobileMenu) {
      navToggle.addEventListener('click', () => {
        mobileMenu.classList.toggle('open');
        const expanded = mobileMenu.classList.contains('open');
        navToggle.setAttribute('aria-expanded', expanded);
      });
    }
  </script>
</body>
</html>
