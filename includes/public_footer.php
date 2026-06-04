<?php

require_once __DIR__ . '/public_nav.php';

if (!function_exists('render_public_footer')) {
    function render_public_footer() {
        $year = date('Y');
        $servicesHref = public_page_link('services.php', 'index.php#services');
        $officialsHref = public_page_link('officials.php', 'index.php#officials');
        $announcementsHref = public_page_link('announcements.php', 'index.php#announcements');
        $contactHref = public_page_link('contact.php', 'index.php#contact');
        ?>
        <footer class="public-footer" role="contentinfo">
          <div class="public-footer__inner">
            <div class="public-footer__brand">
              <div class="public-footer__seal" aria-hidden="true"><i class="bi bi-shield-fill"></i></div>
              <div>
                <strong>Barangay Sta. Rosa 1</strong>
                <span>Noveleta, Cavite</span>
              </div>
            </div>

            <nav class="public-footer__links" aria-label="Footer navigation">
              <a href="index.php">Home</a>
              <a href="<?= htmlspecialchars($servicesHref) ?>">Services</a>
              <a href="<?= htmlspecialchars($officialsHref) ?>">Officials</a>
              <a href="<?= htmlspecialchars($announcementsHref) ?>">Announcements</a>
              <a href="<?= htmlspecialchars($contactHref) ?>">Contact</a>
              <a href="register.php">Register</a>
              <a href="login.php">Resident Login</a>
              <a href="login.php">Staff Login</a>
            </nav>

            <div class="public-footer__contact" aria-label="Barangay office contact">
              <span><i class="bi bi-geo-alt-fill" aria-hidden="true"></i> Barangay Hall, Sta. Rosa 1, Noveleta, Cavite</span>
              <span><i class="bi bi-telephone-fill" aria-hidden="true"></i> +63 912 000 0000</span>
              <span><i class="bi bi-envelope-fill" aria-hidden="true"></i> starosa1@noveleta.gov.ph</span>
              <span><i class="bi bi-clock-fill" aria-hidden="true"></i> Mon-Fri, 8:00 AM - 5:00 PM</span>
            </div>

            <div class="public-footer__bottom">
              <span>&copy; <?= $year ?> Barangay Sta. Rosa 1. All rights reserved.</span>
              <span>Official resident services portal</span>
            </div>
          </div>
        </footer>
        <?php
    }
}
