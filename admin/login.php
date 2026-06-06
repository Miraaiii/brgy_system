<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../config/auth_helpers.php';

define('ADMIN_RECAPTCHA_SITE_KEY', getenv('RECAPTCHA_SITE_KEY') ?: '6LestQctAAAAAPiLpjPpGUxwyduFzk-azuaY32TJ');
define('ADMIN_RECAPTCHA_SECRET_KEY', getenv('RECAPTCHA_SECRET_KEY') ?: '6LestQctAAAAALEbXP8QcVMnr_pp5Qo9MK5YY93l');

$admin_roles = ['captain', 'secretary', 'treasurer', 'kagawad', 'sk_chair', 'sk_kagawad'];

if (!empty($_SESSION['user_id']) && in_array(strtolower((string)($_SESSION['role'] ?? '')), $admin_roles, true)) {
    header('Location: dashboard.php');
    exit();
}

function admin_login_verify_recaptcha($token) {
    $token = trim((string)$token);
    if ($token === '') {
        return false;
    }

    $payload = http_build_query([
        'secret' => ADMIN_RECAPTCHA_SECRET_KEY,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);
    $response = false;

    if (function_exists('curl_init')) {
        $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        curl_close($ch);
    }

    if ($response === false || $response === '') {
        $context = stream_context_create([
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => $payload,
                'timeout' => 10,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);
        $response = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $context);
    }

    $decoded = json_decode((string)$response, true);
    return is_array($decoded) && !empty($decoded['success']);
}

function admin_login_e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$error = (string)($_SESSION['admin_login_error'] ?? '');
unset($_SESSION['admin_login_error']);
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (!bms_verify_csrf_token($_POST['csrf_token'] ?? '', 'admin_login_csrf')) {
        $error = 'Your session expired. Please refresh and try again.';
    } elseif ($email === '' || $password === '') {
        $error = 'Email and password are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!admin_login_verify_recaptcha($_POST['g-recaptcha-response'] ?? '')) {
        $error = 'Please complete the reCAPTCHA security check.';
    } else {
        $stmt = $conn->prepare(
            'SELECT id, fullname, email, password_hash, role, status
             FROM users
             WHERE email = ?
             LIMIT 1'
        );
        if ($stmt) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } else {
            $user = null;
        }

        if (!$user || !password_verify($password, (string)$user['password_hash'])) {
            $error = 'Invalid email or password.';
        } elseif (!in_array(strtolower((string)$user['role']), $admin_roles, true)) {
            $error = 'This login is only for active barangay official accounts.';
        } elseif (strtolower((string)$user['status']) !== 'active') {
            $error = 'This official account is not active.';
        } else {
            $role = strtolower((string)$user['role']);
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $role;

            if ($conn && $conn->query("SHOW COLUMNS FROM users LIKE 'last_login_at'")->num_rows > 0) {
                $update = $conn->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
                if ($update) {
                    $user_id = (int)$user['id'];
                    $update->bind_param('i', $user_id);
                    $update->execute();
                    $update->close();
                }
            }
            $audit_table = $conn ? $conn->query("SHOW TABLES LIKE 'audit_logs'") : false;
            if ($audit_table && $audit_table->num_rows > 0) {
                $stmt_log = $conn->prepare(
                    'INSERT INTO audit_logs (user_id, action, table_name, record_id, ip_address, user_agent)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                if ($stmt_log) {
                    $action = 'login_success';
                    $table = 'users';
                    $record_id = (int)$user['id'];
                    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
                    $agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr((string)$_SERVER['HTTP_USER_AGENT'], 0, 255) : null;
                    $stmt_log->bind_param('ississ', $record_id, $action, $table, $record_id, $ip, $agent);
                    $stmt_log->execute();
                    $stmt_log->close();
                }
            }

            header('Location: dashboard.php');
            exit();
        }
    }
}

$csrf = bms_csrf_token('admin_login_csrf');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Official Login - Barangay Sta. Rosa 1</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" />
  <link rel="stylesheet" href="../assets/css/login_register.css?v=20260605a" />
  <link rel="shortcut icon" href="../assets/images/logo_noveleta.png" />
  <script src="https://www.google.com/recaptcha/api.js" async defer></script>
  <style>
    .official-alert {
      display: flex;
      gap: .65rem;
      margin-bottom: 1rem;
      padding: .75rem .9rem;
      border-radius: var(--radius-input);
      border: 1px solid rgba(248, 113, 113, .45);
      background: rgba(127, 29, 29, .26);
      color: #fecaca;
      font-size: .85rem;
      line-height: 1.45;
    }
    .official-note {
      margin: 0 0 1.1rem;
      padding: .8rem .9rem;
      border-radius: var(--radius-input);
      border: 1.5px solid rgba(240, 180, 41, .26);
      background: rgba(201, 150, 30, .1);
      color: rgba(255, 255, 255, .72);
      font-size: .82rem;
      line-height: 1.45;
    }
    .official-note strong {
      display: block;
      margin-bottom: .18rem;
      color: var(--gold-light);
      font-size: .76rem;
      text-transform: uppercase;
      letter-spacing: .04em;
    }
    .official-login-links {
      display: flex;
      justify-content: center;
      gap: .4rem;
      flex-wrap: wrap;
    }
    .official-login-links .switch-link {
      display: inline-flex;
      align-items: center;
      gap: .35rem;
    }
    .card-wrapper.official-login-wrapper {
      width: 100%;
      max-width: 580px;
    }
    .card-wrapper.official-login-wrapper .panel--login {
      position: relative;
      opacity: 1;
      pointer-events: all;
      transform: none;
    }
    .bms-nav .nav-brand[href],
    .bms-nav a[href] {
      cursor: pointer;
    }
  </style>
</head>
<body>
  <nav class="bms-nav" id="mainNav" role="navigation" aria-label="Main navigation">
    <div class="nav-inner">
      <a class="nav-brand" href="../index.php" aria-label="Sta. Rosa 1 homepage">
        <div class="nav-seal" aria-hidden="true"><i class="bi bi-shield-fill"></i></div>
        <div class="nav-brand-text">
          <div class="brgy-name">Sta. Rosa 1</div>
          <div class="brgy-place">Noveleta, Cavite</div>
        </div>
      </a>

      <div class="nav-links" role="menubar">
        <a href="../index.php" role="menuitem">Home</a>
        <a href="../officials.php" role="menuitem">Officials</a>
        <a href="../services.php" role="menuitem">Services</a>
        <a href="../announcements.php" role="menuitem">Announcements</a>
        <a href="../contact.php" role="menuitem">Contact</a>
      </div>

      <div class="nav-cta">
        <a href="../login.php" class="btn-nav-login">Resident Login</a>
        <a href="login.php" class="btn-nav-register active"><i class="bi bi-person-badge-fill"></i> Official Login</a>
      </div>

      <button class="nav-toggle" id="navToggle" aria-label="Toggle menu" aria-expanded="false">
        <i class="bi bi-list"></i>
      </button>
    </div>

    <div class="nav-mobile-menu" id="mobileMenu" role="menu">
      <a href="../index.php" role="menuitem">Home</a>
      <a href="../officials.php" role="menuitem">Officials</a>
      <a href="../services.php" role="menuitem">Services</a>
      <a href="../announcements.php" role="menuitem">Announcements</a>
      <a href="../contact.php" role="menuitem">Contact</a>
      <div class="mobile-cta">
        <a href="../login.php" class="btn-nav-login" role="menuitem">Resident Login</a>
        <a href="login.php" class="btn-nav-register active" role="menuitem">Official Login</a>
      </div>
    </div>
  </nav>

  <div class="auth-container">
    <div class="card-wrapper official-login-wrapper show-login" id="cardWrapper">
      <div class="panel panel--login" id="loginPanel">
        <div class="panel__inner">
          <div class="panel__header">
            <div class="logo-mark"><img src="../assets/images/logo_noveleta.png" alt="logo" /></div>
            <h1 class="panel__title">Official Login</h1>
            <p class="panel__sub">Captain, Secretary, Treasurer, and Kagawad access</p>
          </div>

          <?php if ($error !== ''): ?>
            <div class="official-alert" role="alert">
              <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
              <span><?= admin_login_e($error) ?></span>
            </div>
          <?php endif; ?>

          <div class="official-note">
            <strong>Official portal</strong>
            Official accounts use email and password only. Google sign-in is for resident accounts.
          </div>

          <form method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= admin_login_e($csrf) ?>" />

            <div class="form-grid">
              <div class="form-group">
                <label class="form-label" for="email">
                  <i class="fa-regular fa-envelope"></i> Email
                </label>
                <input class="form-input" id="email" name="email" type="email" value="<?= admin_login_e($email) ?>" placeholder="official@example.com" autocomplete="email" required />
              </div>

              <div class="form-group">
                <label class="form-label" for="password">
                  <i class="fa-solid fa-lock"></i> Password
                </label>
                <div class="input-wrap">
                  <input class="form-input" id="password" name="password" type="password" placeholder="********" autocomplete="current-password" required />
                  <button type="button" class="eye-btn" data-target="password" aria-label="Toggle password"><i class="fa-regular fa-eye"></i></button>
                </div>
              </div>

              <div class="form-group col-span-2 recaptcha-holder">
                <div class="g-recaptcha" data-sitekey="<?= admin_login_e(ADMIN_RECAPTCHA_SITE_KEY) ?>"></div>
              </div>
            </div>

            <button class="btn btn--secondary" type="submit">
              <span>Log In</span>
            </button>
          </form>

          <p class="switch-text official-login-links">
            <a href="../login.php" class="switch-link"><i class="fa-solid fa-users"></i> Resident portal</a>
            <span aria-hidden="true">&middot;</span>
            <a href="../index.php" class="switch-link"><i class="fa-solid fa-house"></i> Public website</a>
          </p>
        </div>
      </div>
    </div>
  </div>

  <script>
    document.querySelectorAll('.eye-btn').forEach(function (button) {
      button.addEventListener('click', function () {
        var input = document.getElementById(button.dataset.target);
        var icon = button.querySelector('i');
        if (!input || !icon) return;
        var showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        icon.classList.toggle('fa-eye', showing);
        icon.classList.toggle('fa-eye-slash', !showing);
      });
    });

    var navToggle = document.getElementById('navToggle');
    var mobileMenu = document.getElementById('mobileMenu');
    if (navToggle && mobileMenu) {
      navToggle.addEventListener('click', function () {
        var expanded = navToggle.getAttribute('aria-expanded') === 'true';
        navToggle.setAttribute('aria-expanded', String(!expanded));
        mobileMenu.classList.toggle('open', !expanded);
      });
    }
  </script>
</body>
</html>
