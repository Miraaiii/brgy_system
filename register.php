<?php
session_start();
include 'config/connection.php';

define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com');

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
        header("Location: register.php");
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
                header("Location: register.php");
                exit();
            }
        }
        header("Location: dashboard.php");
        exit();
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Process AJAX registration request
    header('Content-Type: application/json');

    $fullname = isset($_POST['fullname']) ? trim($_POST['fullname']) : '';
    $email    = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';
    $contact  = isset($_POST['contact']) ? trim($_POST['contact']) : '';

    if (empty($fullname) || empty($email) || empty($password) || empty($confirm_password)) {
        echo json_encode([
            "status" => "error",
            "message" => "All fields are required."
        ]);
        exit();
    }

    // Expanded credentials validations
    if (strlen($password) < 8) {
        echo json_encode(["status" => "error", "message" => "Password must be at least 8 characters."]);
        exit();
    }
    if (!preg_match('/[A-Z]/', $password)) {
        echo json_encode(["status" => "error", "message" => "Password must contain at least one uppercase letter."]);
        exit();
    }
    if (!preg_match('/[a-z]/', $password)) {
        echo json_encode(["status" => "error", "message" => "Password must contain at least one lowercase letter."]);
        exit();
    }
    if (!preg_match('/[0-9]/', $password) && !preg_match('/[^A-Za-z0-9]/', $password)) {
        echo json_encode(["status" => "error", "message" => "Password must contain at least one number or symbol."]);
        exit();
    }

    if ($password !== $confirm_password) {
        echo json_encode([
            "status" => "error",
            "message" => "Passwords do not match."
        ]);
        exit();
    }

    // Check if email already exists
    $check = "SELECT * FROM users WHERE email = ?";
    $stmt = mysqli_prepare($conn, $check);
    mysqli_stmt_bind_param($stmt, "s", $email);
    $stmt->execute();
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        echo json_encode([
            "status" => "error",
            "message" => "Email already exists."
        ]);
        exit();
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $role = "Resident";
    $purok = null;

    $sql = "INSERT INTO users (fullname, email, password, role, purok, contact) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssssss", $fullname, $email, $hashedPassword, $role, $purok, $contact);

    if (mysqli_stmt_execute($stmt)) {
        echo json_encode([
            "status" => "success",
            "message" => "Account created successfully."
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to create account."
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
  <title>Register — Barangay Sta. Rosa 1</title>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet" />

  <!-- Font Awesome & Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" />

  <link rel="stylesheet" href="assets/css/login_register.css" />
  <link rel="shortcut icon" href="assets/images/logo_noveleta.png" />

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
        <a href="login.php" class="btn-nav-login">Log In</a>
        <a href="register.php" class="btn-nav-register active">Register</a>
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
    <div class="card-wrapper register-wrapper" id="cardWrapper">

      <!-- ══ SIGNUP PANEL ══ -->
      <div class="panel panel--signup" id="signupPanel" style="position: relative; opacity: 1; pointer-events: all; transform: none;">
        <div class="panel__inner">
          <div class="panel__header">
            <div class="logo-mark"><img src="assets/images/logo_noveleta.png" alt="logo" /></div>
            <h1 class="panel__title">Create Account</h1>
            <p class="panel__sub">Join us — registration is quick and simple</p>
          </div>

          <form action="register.php" method="post" onsubmit="return false;">

            <!-- Responsive Grid Layout on Desktop -->
            <div class="form-grid">
              <div class="form-group">
                <label class="form-label" for="su-name">
                  <i class="fa-regular fa-user"></i> Full Name
                </label>
                <input 
                  class="form-input" 
                  id="su-name" 
                  name="fullname"
                  type="text" 
                  placeholder="Alex Johnson" 
                  autocomplete="name" 
                  required
                />
              </div>

              <div class="form-group">
                <label class="form-label" for="su-email">
                  <i class="fa-regular fa-envelope"></i> Email
                </label>
                <input 
                  class="form-input" 
                  id="su-email" 
                  name="email"
                  type="email" 
                  placeholder="alex@example.com" 
                  autocomplete="email" 
                  required
                />
              </div>

              <div class="form-group">
                <label class="form-label" for="su-contact">
                  <i class="fa-solid fa-phone"></i> Contact Number
                </label>
                <input 
                  class="form-input" 
                  id="su-contact" 
                  name="contact"
                  type="tel" 
                  placeholder="09XXXXXXXXX" 
                  autocomplete="tel" 
                  required
                />
              </div>



              <div class="form-group">
                <label class="form-label" for="su-pass">
                  <i class="fa-solid fa-lock"></i> Password
                </label>
                <div class="input-wrap">
                  <input 
                    class="form-input" 
                    id="su-pass" 
                    name="password"
                    type="password" 
                    placeholder="••••••••" 
                    autocomplete="new-password" 
                    required
                  />
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
                  <input 
                    class="form-input" 
                    id="su-confirm-pass" 
                    name="confirm_password"
                    type="password" 
                    placeholder="••••••••" 
                    autocomplete="new-password" 
                    required
                  />
                  <button type="button" class="eye-btn" data-target="su-confirm-pass" aria-label="Toggle password">
                    <i class="fa-regular fa-eye"></i>
                  </button>
                </div>
              </div>

              <!-- Password Requirements Visual Panel -->
              <div class="password-requirements col-span-2" id="passwordRequirements">
                <div class="req-title">Password Requirements</div>
                <ul>
                  <li id="req-length" class="requirement invalid">
                    <i class="fa-solid fa-circle-xmark"></i> At least 8 characters
                  </li>
                  <li id="req-upper" class="requirement invalid">
                    <i class="fa-solid fa-circle-xmark"></i> At least one uppercase letter
                  </li>
                  <li id="req-lower" class="requirement invalid">
                    <i class="fa-solid fa-circle-xmark"></i> At least one lowercase letter
                  </li>
                  <li id="req-symbol" class="requirement invalid">
                    <i class="fa-solid fa-circle-xmark"></i> At least one number or symbol
                  </li>
                  <li id="req-match" class="requirement invalid">
                    <i class="fa-solid fa-circle-xmark"></i> Passwords must match
                  </li>
                </ul>
              </div>
            </div>

            <button class="btn btn--primary" id="signupBtn" type="submit">
              <span>Sign Up</span>
            </button>

            <div class="social-divider">
              <span>or sign up with</span>
            </div>

            <!-- Google Sign-In Native Button -->
            <div id="google-signin-btn" style="width: 100%; display: flex; justify-content: center; min-height: 44px; margin-top: 0.2rem;"></div>

          </form>

          <p class="switch-text">Already have an account?
            <a href="login.php" class="switch-link">Log In</a>
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
