<?php
require_once __DIR__ . '/includes/public_helpers.php';

$office_address = 'Brgy. Sta. Rosa 1, Noveleta, Cavite';
$office_hours = 'Monday-Friday: 8:00 AM-5:00 PM';
$barangay_phone = '+63 912 000 0000';
$barangay_phone_href = '+639120000000';
$barangay_email = 'starosa1@noveleta.gov.ph';
$facebook_url = '';
$map_query = rawurlencode('Barangay Sta. Rosa 1 Noveleta Cavite');
$emergency_hotlines = [
    ['label' => 'PNP', 'number' => '166', 'href' => '166'],
    ['label' => 'Fire', 'number' => '1555', 'href' => '1555'],
    ['label' => 'NDRRMC', 'number' => '8525-0000', 'href' => '85250000'],
    ['label' => 'National Emergency', 'number' => '911', 'href' => '911'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Contact Barangay Sta. Rosa 1, Noveleta, Cavite. Office hours, hotlines, email, and map.">
  <title>Contact - Barangay Sta. Rosa 1</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="stylesheet" href="assets/css/public_layout.css">
</head>
<body class="public-page">
<?php render_public_nav('contact'); ?>

<main class="public-main">
  <section class="public-hero">
    <div class="public-container public-hero__grid">
      <div>
        <span class="public-kicker">Barangay hall</span>
        <h1>Contact</h1>
        <p>Reach Barangay Sta. Rosa 1 for document concerns, community assistance, and emergency coordination.</p>
      </div>
      <div class="public-hero__aside" aria-label="Office status">
        <strong>8 AM-5 PM</strong>
        <span>Office hours, Monday to Friday</span>
      </div>
    </div>
  </section>

  <section class="public-section">
    <div class="public-container contact-layout">
      <article class="contact-card contact-card--address">
        <div class="contact-card__icon" aria-hidden="true"><i class="bi bi-geo-alt-fill"></i></div>
        <div>
          <span class="public-kicker">Office address</span>
          <h2><?= pub_e($office_address) ?></h2>
          <p>Barangay Hall, Sta. Rosa 1, Noveleta, Cavite, Philippines.</p>
        </div>
        <div class="map-frame">
          <iframe
            title="Map of Barangay Sta. Rosa 1, Noveleta, Cavite"
            src="https://www.google.com/maps?q=<?= pub_e($map_query) ?>&output=embed"
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"></iframe>
        </div>
      </article>

      <div class="contact-side-grid">
        <article class="contact-info-tile">
          <i class="bi bi-clock-fill" aria-hidden="true"></i>
          <span>Office hours</span>
          <strong><?= pub_e($office_hours) ?></strong>
          <small>Closed on weekends and holidays.</small>
        </article>

        <article class="contact-info-tile">
          <i class="bi bi-telephone-fill" aria-hidden="true"></i>
          <span>Phone</span>
          <strong><a href="tel:<?= pub_e($barangay_phone_href) ?>"><?= pub_e($barangay_phone) ?></a></strong>
          <small>Tap to call on supported devices.</small>
        </article>

        <article class="contact-info-tile">
          <i class="bi bi-envelope-fill" aria-hidden="true"></i>
          <span>Email</span>
          <strong><a href="mailto:<?= pub_e($barangay_email) ?>"><?= pub_e($barangay_email) ?></a></strong>
          <small>Official barangay email address.</small>
        </article>

        <article class="contact-info-tile">
          <i class="bi bi-facebook" aria-hidden="true"></i>
          <span>Social media</span>
          <?php if ($facebook_url !== ''): ?>
            <strong><a href="<?= pub_e($facebook_url) ?>" target="_blank" rel="noopener">Facebook page</a></strong>
            <small>Opens in a new tab.</small>
          <?php else: ?>
            <strong>Facebook page not configured</strong>
            <small>Add the official page URL when available.</small>
          <?php endif; ?>
        </article>
      </div>
    </div>
  </section>

  <section class="public-section public-section--soft">
    <div class="public-container">
      <div class="emergency-panel" role="alert">
        <div class="emergency-panel__heading">
          <i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>
          <div>
            <span class="public-kicker">Emergency hotlines</span>
            <h2>Call the appropriate hotline immediately</h2>
          </div>
        </div>
        <div class="emergency-hotline-grid">
          <?php foreach ($emergency_hotlines as $hotline): ?>
            <a href="tel:<?= pub_e($hotline['href']) ?>" class="emergency-hotline">
              <span><?= pub_e($hotline['label']) ?></span>
              <strong><?= pub_e($hotline['number']) ?></strong>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>
</main>

<?php render_public_footer(); ?>
<button class="back-to-top" id="backToTop" aria-label="Back to top"><i class="bi bi-chevron-up" aria-hidden="true"></i></button>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/public_layout.js"></script>
</body>
</html>
