<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Auth UI — Sign In / Sign Up</title>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet" />

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

  <link rel="stylesheet" href="../../assets/css/login_register.css" />
</head>
<body>

  <!-- Animated canvas background -->
  <canvas id="bg-canvas"></canvas>

  <!-- Dark / Light toggle -->
  <!-- <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark/light mode">
    <i class="fa-solid fa-moon" id="themeIcon"></i>
  </button> -->

  <!-- Card wrapper -->
  <div class="card-wrapper" id="cardWrapper">

    <!-- ══ SIGNUP PANEL ══ -->
    <div class="panel panel--signup" id="signupPanel">
      <!-- Decorative blobs -->
      <span class="blob blob--1"></span>
      <span class="blob blob--2"></span>

      <div class="panel__inner">
        <div class="panel__header">
          <div class="logo-mark"><img src="../../assets/images/logo_noveleta.png" alt="logo" /></div>
          <h1 class="panel__title">Create Account</h1>
          <p class="panel__sub">Join us — it only takes a minute</p>
        </div>

        <form action="../backend/login.php" method="post">
          <div class="form-group">
            <label class="form-label" for="su-name">
              <i class="fa-regular fa-user"></i> Full Name
            </label>
            <input class="form-input" id="su-name" type="text" placeholder="Alex Johnson" autocomplete="name" />
            <span class="input-line"></span>
          </div>

          <div class="form-group">
            <label class="form-label" for="su-email">
              <i class="fa-regular fa-envelope"></i> Email
            </label>
            <input class="form-input" id="su-email" type="email" placeholder="alex@example.com" autocomplete="email" />
            <span class="input-line"></span>
          </div>

          <div class="form-group">
            <label class="form-label" for="su-pass">
              <i class="fa-solid fa-lock"></i> Password
            </label>
            <div class="input-wrap">
              <input class="form-input" id="su-pass" type="password" placeholder="••••••••" autocomplete="new-password" />
              <button class="eye-btn" data-target="su-pass" aria-label="Toggle password"><i class="fa-regular fa-eye"></i></button>
            </div>
            <span class="input-line"></span>
          </div>

          <button class="btn btn--primary" id="signupBtn">
            <span>Sign Up</span>
            <i class="fa-solid fa-arrow-right"></i>
          </button>
        </form>
        

        <p class="switch-text">Already have an account?
          <button class="switch-link" id="goLogin">Log In</button>
        </p>
      </div>
    </div>

    <!-- ══ LOGIN PANEL ══ -->
    <div class="panel panel--login" id="loginPanel">
      <span class="blob blob--3"></span>
      <span class="blob blob--4"></span>

      <div class="panel__inner">
        <div class="panel__header">
          <div class="logo-mark logo-mark--light"><img src="../../assets/images/logo_noveleta.png" alt="logo" /></div>
          <h1 class="panel__title panel__title--dark">Welcome Back</h1>
          <p class="panel__sub panel__sub--dark">Sign in to continue your journey</p>
        </div>

        <div class="form-group">
          <label class="form-label form-label--dark" for="li-email">
            <i class="fa-regular fa-envelope"></i> Email
          </label>
          <input class="form-input form-input--light" id="li-email" type="email" placeholder="alex@example.com" autocomplete="email" />
          <span class="input-line input-line--dark"></span>
        </div>

        <div class="form-group">
          <label class="form-label form-label--dark" for="li-pass">
            <i class="fa-solid fa-lock"></i> Password
          </label>
          <div class="input-wrap">
            <input class="form-input form-input--light" id="li-pass" type="password" placeholder="••••••••" autocomplete="current-password" />
            <button class="eye-btn eye-btn--dark" data-target="li-pass" aria-label="Toggle password"><i class="fa-regular fa-eye"></i></button>
          </div>
          <span class="input-line input-line--dark"></span>
        </div>

        <div class="extras">
          <label class="checkbox-wrap">
            <input type="checkbox" id="remember" />
            <span class="checkmark"></span>
            <span class="checkbox-label">Remember me</span>
          </label>
          <a href="#" class="forgot-link">Forgot password?</a>
        </div>

        <button class="btn btn--secondary" id="loginBtn">
          <span>Log In</span>
          <i class="fa-solid fa-arrow-right"></i>
        </button>

        <p class="switch-text switch-text--dark">Don't have an account?
          <button class="switch-link switch-link--purple" id="goSignup">Sign Up</button>
        </p>
      </div>
    </div>

  </div><!-- /card-wrapper -->

  <script src="../../assets/js/login_register.js"></script>
</body>
</html>
