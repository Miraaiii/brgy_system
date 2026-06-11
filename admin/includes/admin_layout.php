<?php
require_once __DIR__ . '/admin_helpers.php';

function adm_normalize_admin_link($link) {
    $link = trim((string)$link);
    if ($link === '') {
        return '#';
    }
    if (preg_match('/^https?:\/\//i', $link)) {
        return $link;
    }
    if (strpos($link, '../') === 0 || strpos($link, '#') === 0) {
        return $link;
    }
    if (strpos($link, 'portal/') === 0) {
        return '../' . $link;
    }
    if (strpos($link, 'admin/') === 0) {
        return substr($link, 6);
    }
    return $link;
}

function adm_nav_item($active, $key, $href, $icon, $label, $badge = null) {
    $classes = 'admin-nav__link' . ($active === $key ? ' is-active' : '');
    ?>
    <a class="<?= adm_e($classes) ?>" href="<?= adm_e($href) ?>">
      <i class="fa-solid <?= adm_e($icon) ?>" aria-hidden="true"></i>
      <span><?= adm_e($label) ?></span>
      <?php if ($badge !== null && (int)$badge > 0): ?>
        <strong class="admin-nav__badge"><?= adm_e($badge) ?></strong>
      <?php endif; ?>
    </a>
    <?php
}

function adm_avatar_markup($initials, $photo_path = '', $small = false) {
    $class = 'avatar' . ($small ? ' avatar--small' : '');
    $photo_path = trim((string)$photo_path);
    if ($photo_path !== '') {
        $src = '../' . ltrim(str_replace('\\', '/', $photo_path), '/');
        return '<span class="' . adm_e($class) . '"><img src="' . adm_e($src) . '" alt=""></span>';
    }

    return '<span class="' . adm_e($class) . '">' . adm_e($initials) . '</span>';
}

function adm_page_start($title, $active, array $user, $page_class = '') {
    global $conn;

    $display_name = trim((string)($user['fullname'] ?? '')) ?: ($user['username'] ?? 'Secretary');
    $initials = adm_initials($display_name);
    $official_photo = '';
    if (adm_table_exists($conn, 'officials')) {
        $official = adm_fetch_one(
            $conn,
            'SELECT photo_path FROM officials WHERE user_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1',
            'i',
            [(int)$user['id']]
        );
        $official_photo = (string)($official['photo_path'] ?? '');
    }
    $pending_requests = adm_table_exists($conn, 'document_requests')
        ? adm_scalar($conn, "SELECT COUNT(*) FROM document_requests WHERE status = 'pending'")
        : 0;
    $approval_requests = adm_table_exists($conn, 'document_requests')
        ? adm_scalar($conn, "SELECT COUNT(*) FROM document_requests WHERE status = 'for_approval'")
        : 0;
    $pending_verifications = adm_table_exists($conn, 'pending_resident_registrations')
        ? adm_scalar($conn, "SELECT COUNT(*) FROM pending_resident_registrations WHERE status = 'pending'")
        : 0;
    $open_blotters = adm_table_exists($conn, 'blotter_cases')
        ? adm_scalar($conn, "SELECT COUNT(*) FROM blotter_cases WHERE status IN ('open', 'under_mediation')")
        : 0;
    $expenditure_approvals = 0;
    if (adm_table_exists($conn, 'expenditures')) {
        $expenditure_approvals = adm_column_exists($conn, 'expenditures', 'approval_status')
            ? adm_scalar($conn, "SELECT COUNT(*) FROM expenditures WHERE approval_status = 'pending'")
            : adm_scalar($conn, 'SELECT COUNT(*) FROM expenditures WHERE approved_by IS NULL');
    }
    $unread_count = adm_table_exists($conn, 'notifications')
        ? adm_scalar($conn, 'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', 'i', [(int)$user['id']])
        : 0;
    $notifications = adm_table_exists($conn, 'notifications')
        ? adm_fetch_all(
            $conn,
            'SELECT title, message, link, is_read, created_at
             FROM notifications
             WHERE user_id = ?
             ORDER BY is_read ASC, created_at DESC
             LIMIT 5',
            'i',
            [(int)$user['id']]
        )
        : [];
    $flash = adm_pull_flash();
    $role = strtolower(trim((string)($user['role'] ?? 'secretary')));
    $role_label = adm_role_label($role);
    $portal_label = $role === 'captain' ? 'Captain Portal' : ($role_label . ' Portal');
    $body_class = trim('secretary-admin ' . $page_class);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= adm_e($title) ?> - <?= adm_e($portal_label) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link rel="shortcut icon" href="../assets/images/logo_noveleta.png">
  <link rel="stylesheet" href="assets/css/kagawad.css">
  <link rel="stylesheet" href="assets/css/secretary.css?v=20260607b">
  <script>
    (function () {
      try {
        var savedTheme = localStorage.getItem('barangayTheme') || localStorage.getItem('residentTheme');
        if (savedTheme === 'dark' || (!savedTheme && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
          document.documentElement.classList.add('dark-mode-preload');
        }
      } catch (error) {}
    })();
  </script>
</head>
<body class="<?= adm_e($body_class) ?>">
  <a class="skip-link" href="#mainContent">Skip to main content</a>

  <aside class="admin-sidebar" id="adminSidebar" aria-label="<?= adm_e($role_label) ?> navigation">
    <div class="admin-sidebar__brand">
      <a class="brand-lockup" href="dashboard.php" aria-label="Go to dashboard">
        <span class="brand-lockup__mark">
          <img src="../assets/images/logo_noveleta.png" alt="Barangay seal">
        </span>
        <span>
          <strong>Brgy. Sta. Rosa 1</strong>
          <small><?= adm_e($portal_label) ?></small>
        </span>
      </a>
      <button class="icon-button sidebar-close" type="button" data-sidebar-close aria-label="Close navigation">
        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
      </button>
    </div>

    <nav class="admin-nav">
      <?php if ($role === 'captain'): ?>
        <span class="admin-nav__section">Main</span>
        <?php adm_nav_item($active, 'dashboard', 'dashboard.php', 'fa-chart-line', 'Dashboard', $approval_requests); ?>

        <span class="admin-nav__section">Approvals</span>
        <?php adm_nav_item($active, 'requests', 'requests.php?filter=for_approval', 'fa-stamp', 'Document Approvals', $approval_requests); ?>
        <?php adm_nav_item($active, 'finance', 'finance.php?tab=expenditures', 'fa-money-check-dollar', 'Expenditure Approvals', $expenditure_approvals); ?>

        <span class="admin-nav__section">Documents</span>
        <?php adm_nav_item($active, 'all-requests', 'requests.php', 'fa-file-lines', 'All Requests'); ?>
        <?php adm_nav_item($active, 'issued', 'issued.php', 'fa-file-circle-check', 'Issued Documents'); ?>
        <?php adm_nav_item($active, 'verify', '../verify.php', 'fa-qrcode', 'Verify Document'); ?>

        <span class="admin-nav__section">Residents</span>
        <?php adm_nav_item($active, 'residents', 'residents.php', 'fa-users', 'Resident Records', $pending_verifications); ?>
        <?php adm_nav_item($active, 'households', 'households.php', 'fa-house-chimney-window', 'Households'); ?>

        <span class="admin-nav__section">Blotter</span>
        <?php adm_nav_item($active, 'blotter', 'blotter.php', 'fa-scale-balanced', 'Blotter Cases', $open_blotters); ?>
        <?php adm_nav_item($active, 'hearings', 'hearings.php', 'fa-calendar-check', 'Hearing Schedule'); ?>

        <span class="admin-nav__section">Finance</span>
        <?php adm_nav_item($active, 'finance-overview', 'finance.php', 'fa-wallet', 'Financial Overview'); ?>
        <?php adm_nav_item($active, 'reports', 'reports.php', 'fa-chart-pie', 'Reports'); ?>

        <span class="admin-nav__section">Programs</span>
        <?php adm_nav_item($active, 'projects', 'projects.php', 'fa-diagram-project', 'Projects & Programs'); ?>

        <span class="admin-nav__section">Content</span>
        <?php adm_nav_item($active, 'announcements', 'announcements.php', 'fa-bullhorn', 'Announcements'); ?>
        <?php adm_nav_item($active, 'events', 'events.php', 'fa-calendar-days', 'Events Calendar'); ?>

        <span class="admin-nav__section">Officials</span>
        <?php adm_nav_item($active, 'officials', 'officials.php', 'fa-people-roof', 'Officials Directory'); ?>

        <span class="admin-nav__section">System</span>
        <?php adm_nav_item($active, 'settings', 'settings.php', 'fa-gear', 'System Settings'); ?>
        <?php adm_nav_item($active, 'audit', 'audit.php', 'fa-shield-halved', 'Audit Trail'); ?>

      <?php elseif ($role === 'treasurer'): ?>
        <span class="admin-nav__section">Dashboard</span>
        <?php adm_nav_item($active, 'dashboard', 'finance_admin.php?tab=dashboard', 'fa-house', 'Dashboard'); ?>

        <span class="admin-nav__section">Collections</span>
        <?php adm_nav_item($active, 'collections', 'finance_admin.php?tab=collections', 'fa-money-bill-transfer', 'All Collections'); ?>
        <?php adm_nav_item($active, 'record', 'finance_admin.php?tab=record', 'fa-cash-register', 'Record Payment'); ?>
        <?php adm_nav_item($active, 'receipts', 'finance_admin.php?tab=receipts', 'fa-file-invoice-dollar', 'Official Receipts'); ?>

        <span class="admin-nav__section">Expenditures</span>
        <?php adm_nav_item($active, 'expenditures', 'finance_admin.php?tab=expenditures', 'fa-money-bill-wave', 'All Expenditures'); ?>
        <?php adm_nav_item($active, 'add-exp', 'finance_admin.php?tab=add-exp', 'fa-circle-plus', 'Add Expenditure'); ?>

        <span class="admin-nav__section">Budget</span>
        <?php adm_nav_item($active, 'budget', 'finance_admin.php?tab=budget', 'fa-chart-pie', 'Budget Management'); ?>

        <span class="admin-nav__section">Reports</span>
        <?php adm_nav_item($active, 'reports', 'finance_admin.php?tab=reports', 'fa-file-invoice-dollar', 'Financial Reports'); ?>

        <span class="admin-nav__section">Records</span>
        <?php adm_nav_item($active, 'issued', 'issued.php', 'fa-file-lines', 'Doc Issuance Log'); ?>
        <?php adm_nav_item($active, 'residents', 'residents.php', 'fa-users', 'Resident List'); ?>

      <?php elseif ($role === 'kagawad'): ?>
        <span class="admin-nav__section">Dashboard</span>
        <?php adm_nav_item($active, 'dashboard', 'kagawad_admin.php', 'fa-chart-line', 'Dashboard'); ?>

        <span class="admin-nav__section">My Committee</span>
        <?php adm_nav_item($active, 'projects', 'projects.php?committee=own', 'fa-diagram-project', 'My Programs'); ?>
        <?php if ($active === 'project_edit'): ?>
          <?php adm_nav_item($active, 'project_edit', '#', 'fa-pen-to-square', 'Edit Program'); ?>
        <?php else: ?>
          <?php adm_nav_item($active, 'project_form', 'project_form.php', 'fa-circle-plus', 'Add New Program'); ?>
        <?php endif; ?>
        <?php adm_nav_item($active, 'events', 'events.php?committee=own', 'fa-calendar-days', 'Events & Activities'); ?>
        <?php adm_nav_item($active, 'announcements', 'announcements.php?author=own', 'fa-bullhorn', 'My Announcements'); ?>

        <span class="admin-nav__section">Read-Only Records</span>
        <?php adm_nav_item($active, 'residents', 'residents.php', 'fa-users', 'Resident List'); ?>
        <?php adm_nav_item($active, 'blotter', 'blotter.php', 'fa-scale-balanced', 'Blotter Summary'); ?>
        <?php adm_nav_item($active, 'reports', 'reports.php?type=summary', 'fa-chart-pie', 'Statistics'); ?>

      <?php elseif (in_array($role, ['kagawad', 'sk_chair', 'sk_kagawad'], true)): ?>
        <span class="admin-nav__section">Programs</span>
        <?php adm_nav_item($active, 'dashboard', 'dashboard.php', 'fa-chart-line', 'Dashboard'); ?>
        <?php adm_nav_item($active, 'projects', 'projects.php', 'fa-diagram-project', 'Projects & Programs'); ?>
        <?php adm_nav_item($active, 'blotter', 'blotter.php', 'fa-scale-balanced', 'Blotter Cases', $open_blotters); ?>
      <?php else: ?>
        <span class="admin-nav__section">Daily Work</span>
        <?php adm_nav_item($active, 'dashboard', 'dashboard.php', 'fa-chart-line', 'Dashboard'); ?>
        <?php adm_nav_item($active, 'requests', 'requests.php', 'fa-file-lines', 'Document Requests', $pending_requests); ?>
        <?php adm_nav_item($active, 'issued', 'issued.php', 'fa-file-circle-check', 'Issued Documents'); ?>

        <span class="admin-nav__section">Residents</span>
        <?php adm_nav_item($active, 'residents', 'residents.php', 'fa-users', 'Resident Masterlist', $pending_verifications); ?>
        <?php adm_nav_item($active, 'resident-form', 'resident-form.php', 'fa-user-plus', 'Add Resident'); ?>
        <?php adm_nav_item($active, 'households', 'households.php', 'fa-house-chimney-window', 'Households'); ?>

        <span class="admin-nav__section">Barangay Records</span>
        <?php adm_nav_item($active, 'blotter', 'blotter.php', 'fa-scale-balanced', 'Blotter Cases', $open_blotters); ?>
        <?php adm_nav_item($active, 'hearings', 'hearings.php', 'fa-calendar-check', 'Hearings'); ?>
        <?php adm_nav_item($active, 'announcements', 'announcements.php', 'fa-bullhorn', 'Announcements'); ?>
        <?php adm_nav_item($active, 'events', 'events.php', 'fa-calendar-days', 'Events Calendar'); ?>
        <?php adm_nav_item($active, 'reports', 'reports.php', 'fa-chart-pie', 'Reports'); ?>
      <?php endif; ?>

      <span class="admin-nav__section">Account</span>
      <?php adm_nav_item($active, 'notifications', 'notifications.php', 'fa-bell', 'Notifications', $unread_count); ?>
      <?php adm_nav_item($active, 'profile', 'profile.php', 'fa-user-gear', 'My Profile'); ?>
      <a class="admin-nav__link admin-nav__link--danger" href="../logout.php">
        <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
        <span>Logout</span>
      </a>
    </nav>

    <div class="admin-sidebar__user">
      <?= adm_avatar_markup($initials, $official_photo) ?>
      <span>
        <strong><?= adm_e($display_name) ?></strong>
        <small><?= adm_e($role_label) ?></small>
      </span>
    </div>
  </aside>

  <div class="sidebar-scrim" id="sidebarScrim" data-sidebar-close hidden></div>

  <div class="admin-shell">
    <header class="admin-topbar">
      <div class="topbar-left">
        <button class="icon-button sidebar-toggle" type="button" data-sidebar-toggle aria-label="Open navigation">
          <i class="fa-solid fa-bars" aria-hidden="true"></i>
        </button>
        <a class="topbar-brand" href="dashboard.php">
          <img src="../assets/images/logo_noveleta.png" alt="Barangay seal">
          <span>Brgy. Sta. Rosa 1</span>
        </a>
      </div>

      <div class="topbar-actions">
        <span class="topbar-date"><?= adm_e(date('l, F j, Y')) ?></span>

        <button class="icon-button theme-toggle" id="adminThemeToggle" type="button" aria-label="Switch to dark mode" aria-pressed="false">
          <i class="fa-solid fa-moon" aria-hidden="true"></i>
        </button>

        <div class="dropdown-wrap">
          <button class="icon-button notification-toggle" type="button" data-dropdown-toggle="notificationMenu" aria-label="Open notifications" aria-expanded="false">
            <i class="fa-solid fa-bell" aria-hidden="true"></i>
            <?php if ($unread_count > 0): ?>
              <span class="count-badge"><?= adm_e($unread_count) ?></span>
            <?php endif; ?>
          </button>
          <div class="dropdown-panel notification-panel" id="notificationMenu" hidden>
            <div class="dropdown-panel__header">
              <strong>Notifications</strong>
              <span><?= adm_e($unread_count) ?> unread</span>
            </div>
            <?php if ($notifications): ?>
              <div class="notification-list">
                <?php foreach ($notifications as $notification): ?>
                  <a class="notification-item <?= ((int)$notification['is_read'] === 0) ? 'is-unread' : '' ?>" href="<?= adm_e(adm_normalize_admin_link($notification['link'] ?? '#')) ?>">
                    <strong><?= adm_e($notification['title']) ?></strong>
                    <span><?= adm_e($notification['message']) ?></span>
                    <small><?= adm_e(adm_relative_time($notification['created_at'])) ?></small>
                  </a>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="empty-mini">No notifications yet.</div>
            <?php endif; ?>
          </div>
        </div>

        <div class="dropdown-wrap">
          <button class="profile-chip" type="button" data-dropdown-toggle="profileMenu" aria-label="Open profile menu" aria-expanded="false">
            <?= adm_avatar_markup($initials, $official_photo, true) ?>
            <span class="profile-chip__text">
              <strong><?= adm_e(adm_first_name($display_name)) ?></strong>
              <small><?= adm_e($role_label) ?></small>
            </span>
            <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
          </button>
          <div class="dropdown-panel profile-panel" id="profileMenu" hidden>
            <div class="profile-panel__head">
              <?= adm_avatar_markup($initials, $official_photo) ?>
              <span>
                <strong><?= adm_e($display_name) ?></strong>
                <small><?= adm_e($user['email'] ?? '') ?></small>
              </span>
            </div>
            <a href="profile.php"><i class="fa-solid fa-user-pen"></i> My Profile</a>
            <a href="profile.php#change-password"><i class="fa-solid fa-lock"></i> Change Password</a>
            <a class="danger-link" href="../logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
          </div>
        </div>
      </div>
    </header>

    <main class="admin-main" id="mainContent">
      <?php if ($flash): ?>
        <div class="flash flash--<?= adm_e($flash['type'] ?? 'info') ?>" role="status">
          <i class="fa-solid <?= ($flash['type'] ?? '') === 'danger' ? 'fa-circle-exclamation' : 'fa-circle-check' ?>" aria-hidden="true"></i>
          <span><?= adm_e($flash['message'] ?? '') ?></span>
        </div>
      <?php endif; ?>
    <?php
}

function adm_page_header($eyebrow, $title, $subtitle = '', $actions_html = '') {
    ?>
    <section class="page-heading">
      <div>
        <?php if ($eyebrow !== ''): ?>
          <p class="eyebrow"><?= adm_e($eyebrow) ?></p>
        <?php endif; ?>
        <h1><?= adm_e($title) ?></h1>
        <?php if ($subtitle !== ''): ?>
          <p><?= adm_e($subtitle) ?></p>
        <?php endif; ?>
      </div>
      <?php if ($actions_html !== ''): ?>
        <div class="page-heading__actions"><?= $actions_html ?></div>
      <?php endif; ?>
    </section>
    <?php
}

function adm_page_end() {
    ?>
    </main>
  </div>
  <script src="assets/js/secretary.js?v=20260605c"></script>
</body>
</html>
    <?php
}
