<?php
session_start();
require_once 'includes/public_nav.php';
require_once 'includes/public_footer.php';

$notice = $_SESSION['account_status_notice'] ?? [];
$status = isset($notice['status']) ? strtolower(trim($notice['status'])) : 'pending';
$status = $status === 'suspended' ? 'suspended' : 'pending';
$email = isset($notice['email']) ? trim($notice['email']) : '';

if ($status === 'suspended') {
    $title = 'Account Suspended';
    $message = 'Your account has been suspended. Contact the barangay office.';
    $icon = 'fa-circle-exclamation';
} else {
    $title = 'Awaiting Approval';
    $message = 'Your account is awaiting approval by the Secretary';
    $icon = 'fa-hourglass-half';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($title) ?> - Barangay Sta. Rosa 1</title>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet" />

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" />
  <link rel="stylesheet" href="assets/css/login_register.css?v=20260605a" />
  <link rel="stylesheet" href="assets/css/public_layout.css" />
  <link rel="shortcut icon" href="assets/images/logo_noveleta.png" />
</head>
<body>
  <?php render_public_nav(); ?>

  <main class="auth-container">
    <section class="status-card" aria-labelledby="statusTitle">
      <div class="status-icon <?= $status === 'suspended' ? 'is-danger' : '' ?>" aria-hidden="true">
        <i class="fa-solid <?= htmlspecialchars($icon) ?>"></i>
      </div>
      <h1 class="panel__title" id="statusTitle"><?= htmlspecialchars($title) ?></h1>
      <p class="status-message"><?= htmlspecialchars($message) ?></p>
      <?php if ($email !== ''): ?>
        <p class="status-email">Account: <?= htmlspecialchars($email) ?></p>
      <?php endif; ?>

      <div class="office-contact" aria-label="Barangay office contact information">
        <div>
          <span>Office</span>
          <strong>Barangay Hall, Sta. Rosa 1, Noveleta, Cavite</strong>
        </div>
        <div>
          <span>Phone / Hotline</span>
          <strong>+63 912 000 0000</strong>
        </div>
        <div>
          <span>Email</span>
          <strong>starosa1@noveleta.gov.ph</strong>
        </div>
        <div>
          <span>Office Hours</span>
          <strong>Mon-Fri, 8:00 AM - 5:00 PM</strong>
        </div>
      </div>

      <div class="status-actions">
        <a class="btn btn--primary" href="index.php#contact">
          <span>Contact Barangay</span>
          <i class="fa-solid fa-arrow-right"></i>
        </a>
        <a class="btn btn--ghost" href="login.php">
          <i class="fa-solid fa-arrow-left"></i>
          <span>Back to Login</span>
        </a>
      </div>
    </section>
  </main>

  <?php render_public_footer(); ?>

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
