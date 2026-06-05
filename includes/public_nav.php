<?php

if (!function_exists('public_page_link')) {
    function public_page_link($page, $fallback = '') {
        $root = dirname(__DIR__);
        if (is_file($root . DIRECTORY_SEPARATOR . $page)) {
            return $page;
        }

        return $fallback !== '' ? $fallback : 'index.php';
    }
}

if (!function_exists('render_public_nav')) {
    function render_public_nav($active = '') {
        $links = [
            'home' => ['Home', 'index.php'],
            'officials' => ['Officials', public_page_link('officials.php', 'index.php#officials')],
            'services' => ['Services', public_page_link('services.php', 'index.php#services')],
            'announcements' => ['Announcements', public_page_link('announcements.php', 'index.php#announcements')],
            'contact' => ['Contact', public_page_link('contact.php', 'index.php#contact')]
        ];
        ?>
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
              <?php foreach ($links as $key => [$label, $href]): ?>
                <a href="<?= htmlspecialchars($href) ?>" class="<?= $active === $key ? 'active' : '' ?>" role="menuitem"><?= htmlspecialchars($label) ?></a>
              <?php endforeach; ?>
            </div>

            <div class="nav-cta">
              <a href="login.php" class="btn-nav-login <?= $active === 'login' ? 'active' : '' ?>">Resident Login</a>
              <a href="admin/login.php" class="btn-nav-login btn-nav-staff <?= $active === 'staff-login' ? 'active' : '' ?>"><i class="bi bi-person-badge-fill"></i> Staff Login</a>
              <a href="register.php" class="btn-nav-register <?= $active === 'register' ? 'active' : '' ?>"><i class="bi bi-person-plus-fill"></i> Register</a>
            </div>

            <button class="nav-toggle" id="navToggle" aria-label="Toggle menu" aria-expanded="false">
              <i class="bi bi-list"></i>
            </button>
          </div>

          <div class="nav-mobile-menu" id="mobileMenu" role="menu">
            <?php foreach ($links as $key => [$label, $href]): ?>
              <a href="<?= htmlspecialchars($href) ?>" class="<?= $active === $key ? 'active' : '' ?>" role="menuitem"><?= htmlspecialchars($label) ?></a>
            <?php endforeach; ?>
            <div class="mobile-cta">
              <a href="login.php" class="btn-nav-login <?= $active === 'login' ? 'active' : '' ?>" role="menuitem">Resident Login</a>
              <a href="admin/login.php" class="btn-nav-login btn-nav-staff <?= $active === 'staff-login' ? 'active' : '' ?>" role="menuitem">Staff Login</a>
              <a href="register.php" class="btn-nav-register <?= $active === 'register' ? 'active' : '' ?>" role="menuitem">Register</a>
            </div>
          </div>
        </nav>
        <?php
    }
}
