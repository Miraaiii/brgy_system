<?php
session_start();
include 'config/connection.php';

// Google Client ID & reCAPTCHA Keys from Environment Variables
define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com');
define('RECAPTCHA_SITE_KEY', getenv('RECAPTCHA_SITE_KEY') ?: '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI');
define('RECAPTCHA_SECRET_KEY', getenv('RECAPTCHA_SECRET_KEY') ?: '6LeIxAcTAAAAAGG-vFI1qg6CQ7CV9Fgr27glJ0O0');

// 1. Google OAuth Callback (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['credential'])) {
    $id_token = $_POST['credential'];
    
    // Live verification with Google tokeninfo API
    $verify_url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($id_token);
    $options = [
        'http' => ['method' => 'GET', 'timeout' => 10],
        'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false]
    ];
    $context  = stream_context_create($options);
    $response = @file_get_contents($verify_url, false, $context);
    $payload  = json_decode($response, true);
    
    if ($payload && isset($payload['email'])) {
        $email = $payload['email'];
        $fullname = isset($payload['name']) ? $payload['name'] : 'Google User';
    } else {
        $_SESSION['error_message'] = "Google token validation failed.";
        header("Location: login.php");
        exit();
    }
    
    if (isset($email)) {
        $stmt_g = $conn->prepare("SELECT user_id, email, role FROM users WHERE email = ? LIMIT 1");
        $stmt_g->bind_param("s", $email);
        $stmt_g->execute();
        $res_g = $stmt_g->get_result();
        
        if ($res_g && $res_g->num_rows > 0) {
            $user_data = $res_g->fetch_assoc();
            $_SESSION['user_id'] = $user_data['user_id'];
            $_SESSION['email']   = $user_data['email'];
            $_SESSION['role']    = $user_data['role'];
        } else {
            // Auto-register new Google user
            $role = "Resident";
            $purok = null;
            $contact = "";
            $random_pass = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
            
            $stmt_reg = $conn->prepare("INSERT INTO users (fullname, email, password, role, purok, contact) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_reg->bind_param("ssssss", $fullname, $email, $random_pass, $role, $purok, $contact);
            
            if ($stmt_reg->execute()) {
                $_SESSION['user_id'] = $conn->insert_id;
                $_SESSION['email']   = $email;
                $_SESSION['role']    = $role;
            } else {
                $_SESSION['error_message'] = "Failed to register Google account.";
                header("Location: login.php");
                exit();
            }
        }
        header("Location: dashboard.php");
        exit();
    }
}

// 2. Remember Me Auto-Login Check (GET)
if ($_SERVER["REQUEST_METHOD"] == "GET" && !isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    list($selector, $validator) = explode(':', $_COOKIE['remember_me'] . ':');
    if (!empty($selector) && !empty($validator)) {
        $stmt_tok = $conn->prepare("SELECT id, hashed_validator, user_id, expiry FROM user_tokens WHERE selector = ? LIMIT 1");
        $stmt_tok->bind_param("s", $selector);
        $stmt_tok->execute();
        $result_tok = $stmt_tok->get_result();
        
        if ($result_tok && $result_tok->num_rows > 0) {
            $row_tok = $result_tok->fetch_assoc();
            
            if (strtotime($row_tok['expiry']) >= time()) {
                if (hash_equals($row_tok['hashed_validator'], hash('sha256', $validator))) {
                    $stmt_u = $conn->prepare("SELECT user_id, email, role FROM users WHERE user_id = ? LIMIT 1");
                    $stmt_u->bind_param("i", $row_tok['user_id']);
                    $stmt_u->execute();
                    $result_u = $stmt_u->get_result();
                    
                    if ($result_u && $result_u->num_rows > 0) {
                        $user_data = $result_u->fetch_assoc();
                        $_SESSION['user_id'] = $user_data['user_id'];
                        $_SESSION['email']   = $user_data['email'];
                        $_SESSION['role']    = $user_data['role'];
                        
                        // Rotate tokens
                        $conn->query("DELETE FROM user_tokens WHERE id = " . $row_tok['id']);
                        
                        $new_selector = bin2hex(random_bytes(12));
                        $new_validator = bin2hex(random_bytes(32));
                        $new_hashed = hash('sha256', $new_validator);
                        $expiry_time = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60));
                        
                        $stmt_ins = $conn->prepare("INSERT INTO user_tokens (selector, hashed_validator, user_id, expiry) VALUES (?, ?, ?, ?)");
                        $stmt_ins->bind_param("ssis", $new_selector, $new_hashed, $user_data['user_id'], $expiry_time);
                        $stmt_ins->execute();
                        
                        setcookie('remember_me', "$new_selector:$new_validator", [
                            'expires'  => time() + (30 * 24 * 60 * 60),
                            'path'     => '/',
                            'httponly' => true,
                            'samesite' => 'Lax'
                        ]);
                        
                        header("Location: dashboard.php");
                        exit();
                    }
                }
            }
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Process AJAX login request
    header('Content-Type: application/json');

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
    $recaptcha_response = isset($_POST['g-recaptcha-response']) ? $_POST['g-recaptcha-response'] : '';
    if (empty($recaptcha_response)) {
        echo json_encode([
            "status" => "error",
            "message" => "Please complete the reCAPTCHA security check."
        ]);
        exit();
    }

    $verify_url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = [
        'secret'   => RECAPTCHA_SECRET_KEY,
        'response' => $recaptcha_response,
        'remoteip' => $_SERVER['REMOTE_ADDR']
    ];

    $response = false;

    // Method 1: Try cURL if available (most robust, ignoring SSL verify on localhost)
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $verify_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        curl_close($ch);
    }

    // Method 2: Fallback to stream context file_get_contents
    if ($response === false) {
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

    $response_keys = json_decode($response, true);

    if (!$response_keys || !isset($response_keys["success"]) || !$response_keys["success"]) {
        echo json_encode([
            "status" => "error",
            "message" => "Google reCAPTCHA verification failed. Please try again."
        ]);
        exit();
    }

    $email    = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    if (empty($email) || empty($password)) {
        echo json_encode([
            "status" => "error",
            "message" => "All fields are required."
        ]);
        exit();
    }

    $stmt = $conn->prepare("
        SELECT user_id, email, password, role
        FROM users
        WHERE email = ?
        LIMIT 1
    ");

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($user_id, $db_email, $password, $role);
        $stmt->fetch();

        if (password_verify($password, $password)) {
            // Reset attempts on successful authentication
            $_SESSION['login_attempts'] = 0;
            $_SESSION['login_lockout_time'] = null;

            $_SESSION['user_id'] = $user_id;
            $_SESSION['email'] = $db_email;
            $_SESSION['role'] = $role;

            // Remember me token generation
            if (isset($_POST['remember']) && ($_POST['remember'] === 'true' || $_POST['remember'] === '1' || $_POST['remember'] === true)) {
                $selector = bin2hex(random_bytes(12));
                $validator = bin2hex(random_bytes(32));
                $hashed = hash('sha256', $validator);
                $expiry_time = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)); // 30 days
                
                $stmt_ins = $conn->prepare("INSERT INTO user_tokens (selector, hashed_validator, user_id, expiry) VALUES (?, ?, ?, ?)");
                $stmt_ins->bind_param("ssis", $selector, $hashed, $user_id, $expiry_time);
                $stmt_ins->execute();
                
                setcookie('remember_me', "$selector:$validator", [
                    'expires'  => time() + (30 * 24 * 60 * 60),
                    'path'     => '/',
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            }

            echo json_encode([
                "status" => "success",
                "message" => "Login successful.",
                "role" => $role
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
                    "message" => "Invalid password. (" . $remaining . " attempts remaining)"
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
                "message" => "Email not found. (" . $remaining . " attempts remaining)"
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
  <title>Login — Barangay Sta. Rosa 1</title>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet" />

  <!-- Font Awesome & Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" />

  <link rel="stylesheet" href="assets/css/login_register.css" />
  <link rel="shortcut icon" href="assets/images/logo_noveleta.png" />

  <!-- Google reCAPTCHA v2 API -->
  <script src="https://www.google.com/recaptcha/api.js" async defer></script>

  <!-- Google OAuth v2 API -->
  <script src="https://accounts.google.com/gsi/client" async defer></script>
</head>
<body>

  <!-- INTEGRATED NAVBAR -->
  <nav class="bms-nav" id="mainNav" role="navigation" aria-label="Main navigation">
    <div class="nav-inner">
      <a class="nav-brand" href="index.php" aria-label="Sta. Rosa 1 homepage">
        <div class="nav-seal" aria-hidden="true"><i class="bi bi-shield-fill"></i></div>
        <div class="nav-brand-text">
          <div class="brgy-name">Sta. Rosa 1</div>
          <div class="brgy-place">Noveleta, Cavite</div>
        </div>
      </a>

      <div class="nav-links" role="menubar">
        <a href="index.php" role="menuitem">Home</a>
        <a href="index.php#officials" role="menuitem">Officials</a>
        <a href="index.php#services" role="menuitem">Services</a>
        <a href="index.php#announcements" role="menuitem">News</a>
        <a href="index.php#contact" role="menuitem">Contact</a>
      </div>

      <div class="nav-cta">
        <a href="login.php" class="btn-nav-login active">Log In</a>
        <a href="register.php" class="btn-nav-register"></i> Register</a>
      </div>

      <button class="nav-toggle" id="navToggle" aria-label="Toggle menu" aria-expanded="false">
        <i class="bi bi-list"></i>
      </button>
    </div>

    <!-- Mobile menu -->
    <div class="nav-mobile-menu" id="mobileMenu" role="menu">
      <a href="index.php" role="menuitem">Home</a>
      <a href="index.php#officials" role="menuitem">Officials</a>
      <a href="index.php#services" role="menuitem">Services</a>
      <a href="index.php#announcements" role="menuitem">News</a>
      <a href="index.php#contact" role="menuitem">Contact</a>
      <div class="mobile-cta">
        <a href="login.php" class="btn-nav-login" style="text-align:center" role="menuitem">Log In</a>
        <a href="register.php" class="btn-nav-register" style="text-align:center" role="menuitem">Register</a>
      </div>
    </div>
  </nav>

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

          <form id="loginForm" method="post" onsubmit="return false;">
            
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
                <div class="g-recaptcha" data-sitekey="<?= RECAPTCHA_SITE_KEY ?>"></div>
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
          <div class="panel__header">
            <div class="logo-mark"><img src="assets/images/logo_noveleta.png" alt="logo" /></div>
            <h1 class="panel__title">Reset Password</h1>
            <p class="panel__sub">Retrieve access using your email address</p>
          </div>

          <form id="forgotForm" method="post" onsubmit="return false;">
            <div class="form-group" style="margin-bottom: 1.5rem;">
              <label class="form-label" for="fp-email">
                <i class="fa-regular fa-envelope"></i> Email Address
              </label>
              <input class="form-input" id="fp-email" type="email" placeholder="alex@example.com" autocomplete="email" required />
            </div>

            <button type="submit" class="btn btn--secondary" id="forgotBtn">
              <span>Send Reset Link</span>
              <i class="fa-solid fa-paper-plane"></i>
            </button>
          </form>

          <p class="switch-text">Remembered your password?
            <button class="switch-link" id="backToLogin">Back to Log In</button>
          </p>
        </div>
      </div>

    </div><!-- /card-wrapper -->
  </div><!-- /auth-container -->

  <script>const GOOGLE_CLIENT_ID = "<?= GOOGLE_CLIENT_ID ?>";</script>
  <script src="assets/js/login_register.js"></script>
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
