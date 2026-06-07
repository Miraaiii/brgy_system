<?php
session_start();
include 'config/connection.php';
include_once 'config/auth_helpers.php';
require_once 'includes/public_nav.php';

$response = [
  "status" => "error",
  "message" => "Unknown error"
];

// Google Client ID & reCAPTCHA Keys from Environment Variables
define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: '686708121323-55ipgjmpb122nnmptr2tcn35tr4ukf0b.apps.googleusercontent.com');
define('RECAPTCHA_SITE_KEY', getenv('RECAPTCHA_SITE_KEY') ?: '6LestQctAAAAAPiLpjPpGUxwyduFzk-azuaY32TJ');
define('RECAPTCHA_SECRET_KEY', getenv('RECAPTCHA_SECRET_KEY') ?: '6LestQctAAAAALEbXP8QcVMnr_pp5Qo9MK5YY93l');

function remember_cookie_options($expires) {
    return [
        'expires'  => $expires,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ];
}

function clear_remember_cookie() {
    setcookie('remember_me', '', remember_cookie_options(time() - 3600));
    unset($_COOKIE['remember_me']);
}

function forget_remember_token($conn) {
    if (!empty($_COOKIE['remember_me'])) {
        $parts = explode(':', $_COOKIE['remember_me'], 2);
        $selector = $parts[0] ?? '';

        if (strlen($selector) === 24 && ctype_xdigit($selector)) {
            $stmt = $conn->prepare("DELETE FROM user_tokens WHERE selector = ?");
            if ($stmt) {
                $stmt->bind_param("s", $selector);
                $stmt->execute();
            }
        }
    }

    clear_remember_cookie();
}

function issue_remember_token($conn, $user_id) {
    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $hashed = hash('sha256', $validator);
    $expiry_time = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60));

    $stmt_ins = $conn->prepare("INSERT INTO user_tokens (selector, hashed_validator, user_id, expiry) VALUES (?, ?, ?, ?)");
    if (!$stmt_ins) {
        return false;
    }

    $stmt_ins->bind_param("ssis", $selector, $hashed, $user_id, $expiry_time);
    if (!$stmt_ins->execute()) {
        return false;
    }

    setcookie('remember_me', "$selector:$validator", remember_cookie_options(time() + (30 * 24 * 60 * 60)));
    $_COOKIE['remember_me'] = "$selector:$validator";

    return true;
}

function users_has_column($conn, $column) {
    static $columns = null;
    if ($columns === null) {
        $columns = [];
        $result = $conn->query("SHOW COLUMNS FROM users");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $columns[$row['Field']] = true;
            }
        }
    }

    return isset($columns[$column]);
}

function user_status_select_expression($conn) {
    return users_has_column($conn, 'status') ? "status" : "'active' AS status";
}

function normalize_account_status($status) {
    $status = strtolower(trim((string)$status));
    return $status === '' ? 'active' : $status;
}

function account_status_message($status) {
    $status = normalize_account_status($status);
    if ($status === 'suspended') {
        return "Your account has been suspended. Contact the barangay office.";
    }

    return "Your account is awaiting approval by the Secretary";
}

function account_status_notice_type($status) {
    return normalize_account_status($status) === 'suspended' ? 'suspended' : 'pending';
}

function set_account_status_notice($status, $email = '') {
    $status = account_status_notice_type($status);
    $_SESSION['account_status_notice'] = [
        'status' => $status,
        'email' => $email,
        'message' => account_status_message($status)
    ];
}

function redirect_to_account_status($status, $email = '') {
    set_account_status_notice($status, $email);
    header("Location: account_status.php");
    exit();
}

function json_account_status($status, $email = '') {
    $status = account_status_notice_type($status);
    set_account_status_notice($status, $email);
    echo json_encode([
        "status" => $status,
        "message" => account_status_message($status),
        "redirect" => "account_status.php"
    ]);
    exit();
}

function set_official_login_notice() {
    $_SESSION['admin_login_error'] = "Official accounts must use the official email and password login.";
}

function json_official_login_redirect() {
    set_official_login_notice();
    echo json_encode([
        "status" => "redirect",
        "message" => "Official accounts use the Officials Login portal.",
        "redirect" => "admin/login.php"
    ]);
    exit();
}

function dashboard_redirect_for_role($role) {
    $role = strtolower(trim((string)$role));
    return match($role) {
        'resident'  => 'portal/resident_dashboard.php',
        'treasurer' => 'finance_ad.php',
        'admin'     => 'dashboard.php',
        'secretary' => 'secretary.php',
        default     => 'dashboard.php'
    };
}

// 1. Google OAuth Callback (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['credential'])) {
    $id_token = $_POST['credential'];

    // Live verification with Google tokeninfo API
    $verify_url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($id_token);

    $response = false;
    $curl_error = '';

    // Method 1: Try cURL if available (ignoring SSL verify on localhost)
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $verify_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);
    }

    // Method 2: Fallback to stream context file_get_contents
    if ($response === false || $response === '' || !empty($curl_error)) {
        $options = [
            'http' => ['method' => 'GET', 'timeout' => 10],
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false]
        ];
        $context  = stream_context_create($options);
        $response = @file_get_contents($verify_url, false, $context);
    }

    $payload = json_decode($response, true);
    
    if ($payload && isset($payload['email'])) {
        $email = $payload['email'];
    } else {
        error_log("[BMS] Google OAuth verification failed. Response: " . print_r($response, true) . " Curl error: " . $curl_error);
        $_SESSION['error_message'] = "Google token validation failed. Please check your network connection.";
        header("Location: login.php");
        exit();
    }
    
    if (isset($email)) {
        $statusSelect = user_status_select_expression($conn);
        $stmt_g = $conn->prepare("SELECT id AS user_id, email, role, $statusSelect FROM users WHERE email = ? LIMIT 1");
        $stmt_g->bind_param("s", $email);
        $stmt_g->execute();
        $res_g = $stmt_g->get_result();
        
        if ($res_g && $res_g->num_rows > 0) {
            $user_data = $res_g->fetch_assoc();
            $role_normalized = strtolower(trim((string)($user_data['role'] ?? '')));
            if ($role_normalized !== 'resident') {
                set_official_login_notice();
                header("Location: admin/login.php");
                exit();
            }
            $account_status = normalize_account_status($user_data['status'] ?? 'active');
            if ($account_status !== 'active') {
                redirect_to_account_status($account_status, $user_data['email']);
            }

            $_SESSION['user_id'] = $user_data['user_id'];
            $_SESSION['email']   = $user_data['email'];
            $_SESSION['role']    = $user_data['role'];
        } else {
            $_SESSION['error_message'] = "No resident account was found for that Google email. Please complete the 3-step registration form.";
            header("Location: register.php");
            exit();
        }
        header("Location: " . dashboard_redirect_for_role($user_data['role']));
        exit();
    }
}

// 2. Remember Me Auto-Login Check (GET)
if ($_SERVER["REQUEST_METHOD"] == "GET" && !isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    $remember_parts = explode(':', $_COOKIE['remember_me'], 2);
    $selector = $remember_parts[0] ?? '';
    $validator = $remember_parts[1] ?? '';
    $clear_stale_remember = true;

    if (strlen($selector) === 24 && strlen($validator) === 64 && ctype_xdigit($selector) && ctype_xdigit($validator)) {
        $stmt_tok = $conn->prepare("SELECT id, hashed_validator, user_id, expiry FROM user_tokens WHERE selector = ? LIMIT 1");
        $stmt_tok->bind_param("s", $selector);
        $stmt_tok->execute();
        $result_tok = $stmt_tok->get_result();
        
        if ($result_tok && $result_tok->num_rows > 0) {
            $row_tok = $result_tok->fetch_assoc();
            
            if (strtotime($row_tok['expiry']) >= time()) {
                if (hash_equals($row_tok['hashed_validator'], hash('sha256', $validator))) {
                    $statusSelect = user_status_select_expression($conn);
                    $stmt_u = $conn->prepare("SELECT id AS user_id, email, role, $statusSelect FROM users WHERE id = ? LIMIT 1");
                    $stmt_u->bind_param("i", $row_tok['user_id']);
                    $stmt_u->execute();
                    $result_u = $stmt_u->get_result();
                    
                    if ($result_u && $result_u->num_rows > 0) {
                        $user_data = $result_u->fetch_assoc();
                        $account_status = normalize_account_status($user_data['status'] ?? 'active');

                        if ($account_status !== 'active') {
                            $conn->query("DELETE FROM user_tokens WHERE id = " . (int)$row_tok['id']);
                            clear_remember_cookie();
                            $clear_stale_remember = false;
                            redirect_to_account_status($account_status, $user_data['email']);
                        }

                        if (strtolower(trim((string)($user_data['role'] ?? ''))) !== 'resident') {
                            $conn->query("DELETE FROM user_tokens WHERE id = " . (int)$row_tok['id']);
                            clear_remember_cookie();
                            set_official_login_notice();
                            header("Location: admin/login.php");
                            exit();
                        }

                        $_SESSION['user_id'] = $user_data['user_id'];
                        $_SESSION['email']   = $user_data['email'];
                        $_SESSION['role']    = $user_data['role'];

                        // Rotate tokens
                        $conn->query("DELETE FROM user_tokens WHERE id = " . $row_tok['id']);
                        issue_remember_token($conn, $user_data['user_id']);
                        $clear_stale_remember = false;

                        header("Location: " . dashboard_redirect_for_role($user_data['role']));
                        exit();
                    }
                }
            } else {
                $conn->query("DELETE FROM user_tokens WHERE id = " . (int)$row_tok['id']);
            }
        }
    }

    if ($clear_stale_remember) {
        if (!empty($selector) && ctype_xdigit($selector)) {
            $stmt_del = $conn->prepare("DELETE FROM user_tokens WHERE selector = ?");
            if ($stmt_del) {
                $stmt_del->bind_param("s", $selector);
                $stmt_del->execute();
            }
        }
        clear_remember_cookie();
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Process AJAX login request
    header('Content-Type: application/json');

    if (!bms_verify_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode([
            "status" => "error",
            "message" => "Your session expired. Please refresh the page and try again.",
            "field" => "csrf_token"
        ]);
        exit();
    }

    // 1. Request Spam Throttling (Blocks rapid repeated clicks)
    $currentTime = microtime(true);
    if (isset($_SESSION['last_login_request_time'])) {
        $timeDiff = $currentTime - $_SESSION['last_login_request_time'];
        if ($timeDiff < 1.0) {
            $_SESSION['last_login_request_time'] = $currentTime;
            echo json_encode([
                "status" => "error",
                "message" => "Slow down! Please wait a moment between login attempts."
            ]);
            exit();
        }
    }
    $_SESSION['last_login_request_time'] = $currentTime;

    // 2. Brute-Force Lockout Check (Lockout after 3 fails)
    if (isset($_SESSION['login_lockout_time']) && time() < $_SESSION['login_lockout_time']) {
        $secondsLeft = $_SESSION['login_lockout_time'] - time();
        echo json_encode([
            "status" => "error",
            "message" => "Too many failed attempts. Please try again in " . $secondsLeft . " seconds."
        ]);
        exit();
    }

    // 3. Google reCAPTCHA Validation
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';

    if (empty($recaptcha_response)) {
        echo json_encode([
            "status" => "error",
            "message" => "Please complete the reCAPTCHA security check."
        ]);
        exit();
    }

    $verify_url = "https://www.google.com/recaptcha/api/siteverify";

    $data = [
        'secret' => RECAPTCHA_SECRET_KEY,
        'response' => $recaptcha_response,
        'remoteip' => $_SERVER['REMOTE_ADDR']
    ];

    $response = false;
    $curl_error = '';

    // cURL (preferred)
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $verify_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);
    }

    // Method 2: Fallback to stream context file_get_contents
    if ($response === false || $response === '' || !empty($curl_error)) {
        $response = false; // Reset for fallback
        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data),
                'timeout' => 10
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ];
        $context  = stream_context_create($options);
        $response = @file_get_contents($verify_url, false, $context);
    }

    $response_keys = json_decode((string)$response, true);

    if (!$response_keys || !isset($response_keys["success"]) || !$response_keys["success"]) {
        // Build debug info for troubleshooting
        $debug = '';
        if ($response === false || $response === '') {
            $debug = ' (Network error: could not reach Google servers)';
        } elseif (isset($response_keys['error-codes'])) {
            $codes = implode(', ', $response_keys['error-codes']);
            $debug = " (Error codes: $codes)";
        }
        echo json_encode([
            "status" => "error",
            "message" => "Google reCAPTCHA verification failed. Please try again." . $debug
        ]);
        exit();
    }

    $email    = isset($_POST['email']) ? trim($_POST['email']) : '';
    $inputPassword = isset($_POST['password']) ? trim($_POST['password']) : '';

    if (empty($email) || empty($inputPassword)) {
        echo json_encode([
            "status" => "error",
            "message" => "All fields are required.",
            "field" => empty($email) ? "email" : "password"
        ]);
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            "status" => "error",
            "message" => "Please enter a valid email address.",
            "field" => "email"
        ]);
        exit();
    }

    $statusSelect = user_status_select_expression($conn);
    $stmt = $conn->prepare("
        SELECT id AS user_id, email, password_hash AS password, role, $statusSelect
        FROM users
        WHERE email = ?
        LIMIT 1
    ");

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $user_id = 0;
        $db_email = '';
        $db_password_hash = '';
        $role = '';
        $account_status = 'active';
        $stmt->bind_result($user_id, $db_email, $db_password_hash, $role, $account_status);
        $stmt->fetch();

        $db_password_hash = (string)$db_password_hash;
        $passwordMatches = $db_password_hash !== '' && password_verify($inputPassword, $db_password_hash);

        if ($passwordMatches) {
            // Reset attempts on successful authentication
            $_SESSION['login_attempts'] = 0;
            $_SESSION['login_lockout_time'] = null;

            $account_status = normalize_account_status($account_status);
            if ($account_status !== 'active') {
                forget_remember_token($conn);
                json_account_status($account_status, $db_email);
            }

            if (strtolower(trim((string)$role)) !== 'resident') {
                forget_remember_token($conn);
                json_official_login_redirect();
            }

            $_SESSION['user_id'] = $user_id;
            $_SESSION['email'] = $db_email;
            $_SESSION['role'] = $role;

            // Remember me token generation
            if (isset($_POST['remember']) && ($_POST['remember'] === 'true' || $_POST['remember'] === '1' || $_POST['remember'] === true)) {
                $stmt_del = $conn->prepare("DELETE FROM user_tokens WHERE user_id = ?");
                if ($stmt_del) {
                    $stmt_del->bind_param("i", $user_id);
                    $stmt_del->execute();
                }
                issue_remember_token($conn, $user_id);
            } else {
                forget_remember_token($conn);
            }

            echo json_encode([
                "status" => "success",
                "message" => "Login successful.",
                "role" => $role,
                "redirect" => dashboard_redirect_for_role($role)
            ]);
        } else {
            // Increment failed attempts
            $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
            if ($_SESSION['login_attempts'] >= 3) {
                $_SESSION['login_lockout_time'] = time() + 30; // 30 seconds ban
                $_SESSION['login_attempts'] = 0; // Reset counter for next cycle
                echo json_encode([
                    "status" => "error",
                    "message" => "Too many failed attempts. You are locked out for 30 seconds."
                ]);
            } else {
                $remaining = 3 - $_SESSION['login_attempts'];
                echo json_encode([
                    "status" => "error",
                    "message" => "Invalid password. (" . $remaining . " attempts remaining)",
                    "field" => "password"
                ]);
            }
        }
    } else {
        // Increment attempts on non-existent email as well for standard security
        $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
        if ($_SESSION['login_attempts'] >= 3) {
            $_SESSION['login_lockout_time'] = time() + 30;
            $_SESSION['login_attempts'] = 0;
            echo json_encode([
                "status" => "error",
                "message" => "Too many failed attempts. You are locked out for 30 seconds."
            ]);
        } else {
            $remaining = 3 - $_SESSION['login_attempts'];
            echo json_encode([
                "status" => "error",
                "message" => "Email not found. (" . $remaining . " attempts remaining)",
                "field" => "email"
            ]);
        }
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login - Barangay Sta. Rosa 1</title>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet" />

  <!-- Font Awesome & Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" />

  <link rel="stylesheet" href="assets/css/login_register.css?v=20260605a" />
  <link rel="shortcut icon" href="assets/images/logo_noveleta.png" />

  <!-- Google reCAPTCHA v2 API -->
  <script src="https://www.google.com/recaptcha/api.js" async defer></script>

  <!-- Google OAuth v2 API -->
  <script src="https://accounts.google.com/gsi/client" async defer></script>
</head>
<body>

  <?php render_public_nav('login'); ?>

  <!-- Card Centering Container -->
  <div class="auth-container">
    
    <!-- Card wrapper -->
    <div class="card-wrapper login-wrapper show-login" id="cardWrapper">

      <!-- ══ LOGIN PANEL ══ -->
      <div class="panel panel--login" id="loginPanel">
        <div class="panel__inner">
          <div class="panel__header">
            <div class="logo-mark"><img src="assets/images/logo_noveleta.png" alt="logo" /></div>
            <h1 class="panel__title">Welcome Back</h1>
            <p class="panel__sub">Sign in to continue to the portal</p>
          </div>

          <div class="official-entry" aria-label="Officials login shortcut">
            <span><i class="bi bi-person-badge-fill" aria-hidden="true"></i> Barangay official?</span>
            <a href="admin/login.php">Officials Login</a>
          </div>

          <form id="loginForm" method="post" onsubmit="return false;" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(bms_csrf_token(), ENT_QUOTES, 'UTF-8') ?>" />
            
            <!-- Side-by-side Grid on Desktop -->
            <div class="form-grid">
              <div class="form-group">
                <label class="form-label" for="li-email">
                  <i class="fa-regular fa-envelope"></i> Email
                </label>
                <input class="form-input" id="li-email" name="email" type="email" placeholder="alex@example.com" autocomplete="email" required />
              </div>

              <div class="form-group">
                <label class="form-label" for="li-pass">
                  <i class="fa-solid fa-lock"></i> Password
                </label>
                <div class="input-wrap">
                  <input class="form-input" id="li-pass" name="password" type="password" placeholder="••••••••" autocomplete="current-password" required />
                  <button type="button" class="eye-btn" data-target="li-pass" aria-label="Toggle password"><i class="fa-regular fa-eye"></i></button>
                </div>
              </div>

              <!-- Google reCAPTCHA Container -->
              <div class="form-group col-span-2 recaptcha-holder">
                <div class="g-recaptcha" data-sitekey="6LestQctAAAAAPiLpjPpGUxwyduFzk-azuaY32TJ"></div>
              </div>
            </div>

            <div class="extras">
              <label class="checkbox-wrap">
                <input type="checkbox" id="remember" />
                <span class="checkmark"></span>
                <span class="checkbox-label">Remember me</span>
              </label>
              <button type="button" class="forgot-link" id="goForgot">Forgot password?</button>
            </div>

            <button type="submit" class="btn btn--secondary" id="loginBtn">
              <span>Log In</span>
            </button>

            <div class="social-divider">
              <span>or sign in with</span>
            </div>

            <!-- Google Sign-In Native Button -->
            <div id="google-signin-btn" style="width: 100%; display: flex; justify-content: center; min-height: 44px; margin-top: 0.2rem;"></div>
          </form>

          <p class="switch-text">Don't have an account?
            <a href="register.php" class="switch-link">Sign Up</a>
          </p>
        </div>
      </div>

      <!-- ══ FORGOT PASSWORD PANEL ══ -->
      <div class="panel panel--forgot" id="forgotPanel">
        <div class="panel__inner">

          <!-- ── STEP 1: Enter Email ── -->
          <div id="fpStep1">
            <div class="panel__header">
              <div class="logo-mark"><img src="assets/images/logo_noveleta.png" alt="logo" /></div>
              <h1 class="panel__title">Reset Password</h1>
              <p class="panel__sub">We'll send a 6-digit code to your email</p>
            </div>

            <form id="forgotForm" method="post" onsubmit="return false;" novalidate>
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(bms_csrf_token(), ENT_QUOTES, 'UTF-8') ?>" />
              <div class="form-group" style="margin-bottom: 1.5rem;">
                <label class="form-label" for="fp-email">
                  <i class="fa-regular fa-envelope"></i> Email Address
                </label>
                <input class="form-input" id="fp-email" name="email" type="email" placeholder="alex@example.com" autocomplete="email" required />
              </div>

              <button type="submit" class="btn btn--secondary" id="forgotBtn">
                <span>Send Code</span>
                <i class="fa-solid fa-paper-plane"></i>
              </button>
            </form>

            <p class="switch-text">Remembered your password?
              <button class="switch-link" id="backToLogin">Back to Log In</button>
            </p>
          </div>

          <!-- ── STEP 2: Enter Code ── -->
          <div id="fpStep2" style="display: none;">
            <div class="panel__header">
              <div class="logo-mark"><img src="assets/images/logo_noveleta.png" alt="logo" /></div>
              <h1 class="panel__title">Enter Code</h1>
              <p class="panel__sub">A 6-digit code was sent to <strong id="fpEmailDisplay"></strong></p>
            </div>

            <form id="codeForm" method="post" onsubmit="return false;" novalidate>
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(bms_csrf_token(), ENT_QUOTES, 'UTF-8') ?>" />
              <div class="form-group" style="margin-bottom: 1.5rem;">
                <label class="form-label" for="fp-code">
                  <i class="fa-solid fa-shield-halved"></i> 6-Digit Code
                </label>
                <input class="form-input" id="fp-code" name="code" type="text" inputmode="numeric" maxlength="6" placeholder="000000" autocomplete="one-time-code" required
                       style="text-align:center; letter-spacing:8px; font-size:1.5rem; font-weight:700; font-family:'Courier New',monospace;" />
              </div>

              <button type="submit" class="btn btn--secondary" id="verifyCodeBtn">
                <span>Verify Code</span>
                <i class="fa-solid fa-check-circle"></i>
              </button>
            </form>

            <p class="switch-text">
              <button class="switch-link" id="fpResendCode">Resend Code</button>
              &nbsp;·&nbsp;
              <button class="switch-link" id="fpBackToEmail">Change Email</button>
            </p>
          </div>

          <!-- ── STEP 3: New Password ── -->
          <div id="fpStep3" style="display: none;">
            <div class="panel__header">
              <div class="logo-mark"><img src="assets/images/logo_noveleta.png" alt="logo" /></div>
              <h1 class="panel__title">New Password</h1>
              <p class="panel__sub">Choose a strong password for your account</p>
            </div>

            <form id="resetPassForm" method="post" onsubmit="return false;" novalidate>
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(bms_csrf_token(), ENT_QUOTES, 'UTF-8') ?>" />
              <div class="reset-password-grid">
              <div class="form-group">
                <label class="form-label" for="fp-new-pass">
                  <i class="fa-solid fa-lock"></i> New Password
                </label>
                <div class="input-wrap">
                  <input class="form-input" id="fp-new-pass" name="new_password" type="password" placeholder="••••••••" autocomplete="new-password" required />
                  <button type="button" class="eye-btn" data-target="fp-new-pass" aria-label="Toggle password"><i class="fa-regular fa-eye"></i></button>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label" for="fp-confirm-pass">
                  <i class="fa-solid fa-lock"></i> Confirm Password
                </label>
                <div class="input-wrap">
                  <input class="form-input" id="fp-confirm-pass" name="confirm_password" type="password" placeholder="••••••••" autocomplete="new-password" required />
                  <button type="button" class="eye-btn" data-target="fp-confirm-pass" aria-label="Toggle password"><i class="fa-regular fa-eye"></i></button>
                </div>
              </div>
              </div>

              <div class="password-requirements reset-requirements" id="resetPasswordRequirements">
                <div class="req-title">Password Requirements</div>
                <ul>
                  <li id="fp-req-length" class="requirement invalid">
                    <i class="fa-solid fa-circle-xmark"></i> At least 8 characters
                  </li>
                  <li id="fp-req-upper" class="requirement invalid">
                    <i class="fa-solid fa-circle-xmark"></i> At least one uppercase letter
                  </li>
                  <li id="fp-req-number" class="requirement invalid">
                    <i class="fa-solid fa-circle-xmark"></i> At least one number
                  </li>
                  <li id="fp-req-match" class="requirement invalid">
                    <i class="fa-solid fa-circle-xmark"></i> Passwords must match
                  </li>
                </ul>
              </div>

              <button type="submit" class="btn btn--secondary" id="resetPassBtn">
                <span>Set New Password</span>
                <i class="fa-solid fa-key"></i>
              </button>
            </form>

            <p class="switch-text">
              <button class="switch-link" id="fpBackToCode">Back</button>
            </p>
          </div>

        </div>
      </div>

    </div><!-- /card-wrapper -->
  </div><!-- /auth-container -->

  <script>
    const GOOGLE_CLIENT_ID = "<?= GOOGLE_CLIENT_ID ?>";
    const AUTH_CSRF_TOKEN = "<?= htmlspecialchars(bms_csrf_token(), ENT_QUOTES, 'UTF-8') ?>";
  </script>
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
    // Native Hamburger Menu Toggle Logic
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
