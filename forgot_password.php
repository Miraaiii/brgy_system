<?php
/**
 * forgot_password.php
 * Handles 3 AJAX POST actions for the code-based password reset flow:
 *
 *  action=send_code   → Validates email, generates 6-digit code, emails it
 *  action=verify_code → Validates the 6-digit code against the database
 *  action=reset_pass  → Validates code again + updates the password
 */

session_start();
header('Content-Type: application/json');

include 'config/connection.php';
include 'config/mailer.php';
include_once 'config/auth_helpers.php';

function forgot_json($payload) {
    echo json_encode($payload);
    exit();
}

function ensure_password_reset_attempts_column($conn) {
    $conn->query("
        CREATE TABLE IF NOT EXISTS password_reset_tokens (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          user_id INT UNSIGNED NOT NULL,
          token_hash CHAR(64) NOT NULL,
          expires_at DATETIME NOT NULL,
          used TINYINT(1) NOT NULL DEFAULT 0,
          attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY uq_prt_token (token_hash),
          KEY idx_prt_user (user_id),
          KEY idx_prt_expires (expires_at),
          CONSTRAINT fk_prt_user FOREIGN KEY (user_id)
            REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $result = $conn->query("SHOW COLUMNS FROM password_reset_tokens LIKE 'attempts'");
    if ($result && $result->num_rows > 0) {
        return;
    }

    $conn->query("ALTER TABLE password_reset_tokens ADD COLUMN attempts TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER used");
}

function get_reset_token_row($conn, $email) {
    $stmt = $conn->prepare("
        SELECT prt.id, prt.user_id, prt.token_hash, prt.expires_at, prt.attempts
        FROM password_reset_tokens prt
        INNER JOIN users u ON u.id = prt.user_id
        WHERE u.email = ? AND prt.used = 0
        ORDER BY prt.created_at DESC
        LIMIT 1
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result && $result->num_rows > 0 ? $result->fetch_assoc() : null;
}

function validate_reset_code_or_fail($conn, $email, $code) {
    if (empty($email) || empty($code) || !preg_match('/^\d{6}$/', $code)) {
        forgot_json(['status' => 'error', 'message' => 'Please enter the 6-digit code.', 'field' => 'code']);
    }

    $row = get_reset_token_row($conn, $email);
    if (!$row) {
        forgot_json(['status' => 'error', 'message' => 'Invalid code. Please check and try again.', 'field' => 'code']);
    }

    $tokenId = (int)$row['id'];
    if (strtotime($row['expires_at']) < time()) {
        $conn->query("UPDATE password_reset_tokens SET used = 1 WHERE id = " . $tokenId);
        forgot_json(['status' => 'error', 'message' => 'This code has expired. Please request a new one.', 'field' => 'code']);
    }

    $attempts = (int)$row['attempts'];
    if ($attempts >= 5) {
        $conn->query("UPDATE password_reset_tokens SET used = 1 WHERE id = " . $tokenId);
        forgot_json(['status' => 'error', 'message' => 'Too many incorrect codes. Please request a new code.', 'field' => 'code']);
    }

    if (!hash_equals($row['token_hash'], bms_reset_code_hash($code, $row['user_id']))) {
        $attempts++;
        $used = $attempts >= 5 ? 1 : 0;
        $stmt = $conn->prepare("UPDATE password_reset_tokens SET attempts = ?, used = ? WHERE id = ?");
        $stmt->bind_param("iii", $attempts, $used, $tokenId);
        $stmt->execute();

        if ($used) {
            forgot_json(['status' => 'error', 'message' => 'Too many incorrect codes. Please request a new code.', 'field' => 'code']);
        }

        forgot_json([
            'status' => 'error',
            'message' => 'Invalid code. ' . (5 - $attempts) . ' attempts remaining.',
            'field' => 'code'
        ]);
    }

    return $row;
}

ensure_password_reset_attempts_column($conn);

// ── Only accept POST ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit();
}

$action = isset($_POST['action']) ? trim($_POST['action']) : '';

if (!bms_verify_csrf_token($_POST['csrf_token'] ?? '')) {
    forgot_json([
        'status' => 'error',
        'message' => 'Your session expired. Please refresh the page and try again.',
        'field' => 'csrf_token'
    ]);
}

// ═══════════════════════════════════════════════════════════════
//  ACTION 1: SEND CODE
// ═══════════════════════════════════════════════════════════════
if ($action === 'send_code') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Please enter a valid email address.', 'field' => 'email']);
        exit();
    }

    $lastSendAt = isset($_SESSION['fp_last_send_at']) ? (int)$_SESSION['fp_last_send_at'] : 0;
    $retryAfter = 60 - (time() - $lastSendAt);
    if ($retryAfter > 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Please wait ' . $retryAfter . ' seconds before requesting another code.',
            'retry_after' => $retryAfter,
            'field' => 'email'
        ]);
        exit();
    }

    // ── Rate limiting: max 3 requests per 15 minutes per session ──
    if (!isset($_SESSION['fp_requests'])) {
        $_SESSION['fp_requests'] = [];
    }
    $_SESSION['fp_requests'] = array_filter(
        $_SESSION['fp_requests'],
        fn($t) => $t > (time() - 900)
    );
    if (count($_SESSION['fp_requests']) >= 3) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Too many reset requests. Please wait 15 minutes before trying again.',
            'field' => 'email'
        ]);
        exit();
    }
    $_SESSION['fp_requests'][] = time();
    $_SESSION['fp_last_send_at'] = time();

    // ── Look up user ──────────────────────────────────────────
    $stmt = $conn->prepare("SELECT id, fullname, email FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    // Anti-enumeration: always respond as success
    $generic_success = [
        'status'  => 'success',
        'message' => 'If that email exists, a 6-digit reset code has been sent. Check your inbox.'
    ];

    if (!$result || $result->num_rows === 0) {
        echo json_encode($generic_success);
        exit();
    }

    $user = $result->fetch_assoc();
    $user_id  = $user['id'];
    $fullname = $user['fullname'] ?: 'User';

    // ── Invalidate previous unused codes for this user ─────────
    $cleanup = $conn->prepare("DELETE FROM password_reset_tokens WHERE user_id = ? AND (used = 1 OR expires_at < NOW())");
    $cleanup->bind_param("i", $user_id);
    $cleanup->execute();

    $del = $conn->prepare("DELETE FROM password_reset_tokens WHERE user_id = ? AND used = 0");
    $del->bind_param("i", $user_id);
    $del->execute();

    // ── Generate 6-digit code ─────────────────────────────────
    $code       = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $code_hash  = bms_reset_code_hash($code, $user_id);
    $expires_at = date('Y-m-d H:i:s', time() + 600); // 10 minute expiry

    $ins = $conn->prepare(
        "INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, attempts) VALUES (?, ?, ?, 0)"
    );
    $ins->bind_param("iss", $user_id, $code_hash, $expires_at);

    if (!$ins->execute()) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to process request. Please try again.']);
        exit();
    }

    // ── Build HTML email ──────────────────────────────────────
    $first_name = explode(' ', $fullname)[0];
    $year = date('Y');

    $email_html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0;padding:0;background:#f0f4f8;font-family:'Segoe UI',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4f8;padding:40px 20px;">
    <tr>
      <td align="center">
        <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);max-width:600px;">

          <!-- Header -->
          <tr>
            <td style="background:linear-gradient(135deg,#1a3a6b 0%,#2563eb 100%);padding:40px 40px 32px;text-align:center;">
              <div style="color:#fff;font-size:18px;font-weight:700;letter-spacing:0.5px;">Barangay Sta. Rosa 1</div>
              <div style="color:rgba(255,255,255,0.7);font-size:12px;margin-top:4px;">Noveleta, Cavite — Official Portal</div>
            </td>
          </tr>

          <!-- Body -->
          <tr>
            <td style="padding:40px;">
              <h1 style="margin:0 0 8px;font-size:24px;color:#1e293b;font-weight:700;">Your Reset Code</h1>
              <p style="margin:0 0 24px;color:#475569;font-size:15px;line-height:1.6;">
                Hi <strong style="color:#1e293b;">{$first_name}</strong>, use the code below to reset your password. It expires in <strong>10 minutes</strong>.
              </p>

              <!-- Code Box -->
              <div style="text-align:center;margin:32px 0;">
                <div style="display:inline-block;background:#f1f5f9;border:2px dashed #2563eb;border-radius:12px;padding:20px 48px;letter-spacing:12px;font-size:36px;font-weight:800;color:#1e293b;font-family:'Courier New',monospace;">
                  {$code}
                </div>
              </div>

              <!-- Warning -->
              <div style="background:#fef9c3;border-left:4px solid #eab308;border-radius:8px;padding:14px 18px;margin-bottom:24px;">
                <p style="margin:0;color:#713f12;font-size:13px;line-height:1.6;">
                  ⚠️ If you did not request this code, you can safely ignore this email.
                </p>
              </div>

              <hr style="border:none;border-top:1px solid #e2e8f0;margin:24px 0;">
              <p style="margin:0;color:#94a3b8;font-size:12px;text-align:center;">
                This is an automated message. Please do not reply.
              </p>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="background:#f8fafc;padding:20px 40px;text-align:center;border-top:1px solid #e2e8f0;">
              <p style="margin:0;color:#94a3b8;font-size:12px;">
                © {$year} Barangay Sta. Rosa 1, Noveleta, Cavite.
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

    // ── Send email ────────────────────────────────────────────
    try {
        $mail = createMailer();
        $mail->addAddress($email, $fullname);
        $mail->Subject = "Your Password Reset Code — Barangay Sta. Rosa 1";
        $mail->Body    = $email_html;
        $mail->AltBody = "Hi {$first_name},\n\nYour password reset code is: {$code}\n\nThis code expires in 10 minutes.\n\nIf you did not request this, ignore this email.\n\n— Barangay Sta. Rosa 1 Portal";

        $mail->send();
        echo json_encode($generic_success);

    } catch (\Exception $e) {
        error_log('[BMS] PHPMailer Error: ' . $e->getMessage());
        echo json_encode([
            'status'  => 'error',
            'message' => 'Failed to send email. Please check SMTP settings or try again later.'
        ]);
    }
    exit();
}

// ═══════════════════════════════════════════════════════════════
//  ACTION 2: VERIFY CODE
// ═══════════════════════════════════════════════════════════════
if ($action === 'verify_code') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $code  = isset($_POST['code'])  ? trim($_POST['code'])  : '';

    validate_reset_code_or_fail($conn, $email, $code);

    echo json_encode(['status' => 'success', 'message' => 'Code verified! Enter your new password.']);
    exit();
}

// ═══════════════════════════════════════════════════════════════
//  ACTION 3: RESET PASSWORD
// ═══════════════════════════════════════════════════════════════
if ($action === 'reset_pass') {
    $email        = isset($_POST['email'])        ? trim($_POST['email'])        : '';
    $code         = isset($_POST['code'])          ? trim($_POST['code'])         : '';
    $new_password = isset($_POST['new_password'])  ? $_POST['new_password']       : '';
    $confirm_pass = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    // Validate inputs
    if (empty($email) || empty($code) || empty($new_password) || empty($confirm_pass)) {
        echo json_encode(['status' => 'error', 'message' => 'All fields are required.', 'field' => 'password']);
        exit();
    }
    $passwordErrors = bms_password_errors($new_password);
    if (!empty($passwordErrors)) {
        echo json_encode(['status' => 'error', 'message' => $passwordErrors[0], 'field' => 'password']);
        exit();
    }
    if ($new_password !== $confirm_pass) {
        echo json_encode(['status' => 'error', 'message' => 'Passwords do not match.', 'field' => 'confirm_password']);
        exit();
    }

    $row = validate_reset_code_or_fail($conn, $email, $code);

    // Update password
    $hashed  = password_hash($new_password, PASSWORD_BCRYPT);
    $user_id = $row['user_id'];

    $upd = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $upd->bind_param("si", $hashed, $user_id);

    if ($upd->execute()) {
        // Mark token as used
        $conn->query("UPDATE password_reset_tokens SET used = 1 WHERE id = " . (int)$row['id']);
        echo json_encode(['status' => 'success', 'message' => 'Password reset successfully! You can now log in.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update password. Please try again.']);
    }
    exit();
}

// Unknown action
echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
exit();
