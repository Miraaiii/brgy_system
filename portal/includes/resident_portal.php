<?php
date_default_timezone_set('Asia/Manila');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../config/auth_helpers.php';

function rp_e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function rp_bind_params($stmt, $types, array $params) {
    if ($types === '') {
        return true;
    }

    $refs = [];
    foreach ($params as $key => $value) {
        $refs[$key] = &$params[$key];
    }

    return $stmt->bind_param($types, ...$refs);
}

function rp_table_exists($conn, $table) {
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }

    $safe_table = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '{$safe_table}'");
    $cache[$table] = $result && $result->num_rows > 0;

    return $cache[$table];
}

function rp_column_exists($conn, $table, $column) {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        return false;
    }

    $safe_column = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$safe_column}'");
    return $result && $result->num_rows > 0;
}

function rp_ensure_document_request_columns($conn) {
    if (!rp_table_exists($conn, 'document_requests')) {
        return;
    }

    if (!rp_column_exists($conn, 'document_requests', 'extra_details')) {
        @$conn->query("ALTER TABLE document_requests ADD COLUMN extra_details LONGTEXT NULL DEFAULT NULL AFTER purpose");
    }
}

function rp_ensure_blotter_evidence_table($conn) {
    if (!rp_table_exists($conn, 'blotter_cases')) {
        return;
    }

    if (!rp_table_exists($conn, 'blotter_evidence')) {
        @$conn->query(
            "CREATE TABLE IF NOT EXISTS blotter_evidence (
              id INT UNSIGNED NOT NULL AUTO_INCREMENT,
              case_id INT UNSIGNED NOT NULL,
              file_name VARCHAR(200) NOT NULL,
              file_path VARCHAR(255) NOT NULL,
              file_type VARCHAR(80) NULL DEFAULT NULL,
              file_size INT UNSIGNED NULL DEFAULT NULL,
              uploaded_by INT UNSIGNED NULL DEFAULT NULL,
              uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_blotter_evidence_case (case_id),
              CONSTRAINT fk_blotter_evidence_case FOREIGN KEY (case_id)
                REFERENCES blotter_cases (id) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT fk_blotter_evidence_user FOREIGN KEY (uploaded_by)
                REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}

function rp_fetch_one($conn, $sql, $types = '', array $params = []) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    rp_bind_params($stmt, $types, $params);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function rp_fetch_all($conn, $sql, $types = '', array $params = []) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    rp_bind_params($stmt, $types, $params);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    $stmt->close();

    return $rows;
}

function rp_scalar($conn, $sql, $types = '', array $params = []) {
    $row = rp_fetch_one($conn, $sql, $types, $params);
    if (!$row) {
        return 0;
    }

    $value = reset($row);
    return (int)$value;
}

function rp_initials($name) {
    $initials = '';
    foreach (preg_split('/\s+/', trim((string)$name)) as $part) {
        if ($part !== '') {
            $initials .= strtoupper(substr($part, 0, 1));
        }
    }

    return substr($initials ?: 'RS', 0, 2);
}

function rp_date($value) {
    $time = strtotime((string)$value);
    return $time ? date('M j, Y', $time) : 'Not set';
}

function rp_date_long($value) {
    $time = strtotime((string)$value);
    return $time ? date('F j, Y', $time) : 'Not set';
}

function rp_datetime($value) {
    $time = strtotime((string)$value);
    return $time ? date('F j, Y, g:i A', $time) : 'Not set';
}

function rp_time_ago($value) {
    $time = strtotime((string)$value);
    if (!$time) {
        return 'Recently';
    }

    $diff = time() - $time;
    if ($diff < 60) {
        return 'Just now';
    }
    if ($diff < 3600) {
        $minutes = (int)floor($diff / 60);
        return $minutes . ' minute' . ($minutes === 1 ? '' : 's') . ' ago';
    }
    if ($diff < 86400) {
        $hours = (int)floor($diff / 3600);
        return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago';
    }
    if ($diff < 604800) {
        $days = (int)floor($diff / 86400);
        return $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
    }

    return rp_date_long($value);
}

function rp_file_size($bytes) {
    $bytes = (int)$bytes;
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }

    return $bytes . ' B';
}

function rp_status_label($status) {
    $status = strtolower(trim((string)$status));
    $labels = [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'for_approval' => 'For Approval',
        'approved' => 'Approved',
        'released' => 'Released',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
        'open' => 'Open',
        'under_mediation' => 'Under Mediation',
        'settled' => 'Settled',
        'escalated' => 'Escalated',
        'closed' => 'Closed',
    ];

    return $labels[$status] ?? ucwords(str_replace('_', ' ', $status ?: 'Unknown'));
}

function rp_status_class($status) {
    $status = strtolower(trim((string)$status));
    $classes = [
        'pending' => 'pending',
        'processing' => 'processing',
        'for_approval' => 'approval',
        'approved' => 'ready',
        'released' => 'released',
        'rejected' => 'rejected',
        'cancelled' => 'cancelled',
        'open' => 'open',
        'under_mediation' => 'mediation',
        'settled' => 'settled',
        'escalated' => 'escalated',
        'closed' => 'closed',
    ];

    return $classes[$status] ?? 'neutral';
}

function rp_document_status_flow() {
    return [
        'pending' => [
            'label' => 'Pending',
            'meaning' => 'Request has been submitted and is waiting for Secretary review.',
            'next' => 'Resident waits. No action needed.',
        ],
        'processing' => [
            'label' => 'Processing',
            'meaning' => 'Secretary is verifying the request and eligibility.',
            'next' => 'Resident waits and may be contacted for additional documents.',
        ],
        'for_approval' => [
            'label' => 'For Approval',
            'meaning' => 'Secretary has processed it and sent it to the Captain.',
            'next' => 'Resident waits for Captain approval.',
        ],
        'approved' => [
            'label' => 'Approved',
            'meaning' => 'Captain has approved it and the document is being prepared for release.',
            'next' => 'Resident will be notified when ready for pickup.',
        ],
        'released' => [
            'label' => 'Released',
            'meaning' => 'Document has been picked up or delivered.',
            'next' => 'No action needed. Issued document can be viewed or downloaded.',
        ],
        'rejected' => [
            'label' => 'Rejected',
            'meaning' => 'Request was rejected and the reason is shown in remarks.',
            'next' => 'Resident can read the reason and re-submit if needed.',
        ],
        'cancelled' => [
            'label' => 'Cancelled',
            'meaning' => 'Resident or admin cancelled the request before processing.',
            'next' => 'Resident can submit a new request.',
        ],
    ];
}

function rp_blotter_status_flow() {
    return [
        'open' => [
            'label' => 'Open',
            'meaning' => 'Case has been logged and received by the Secretary.',
            'next' => 'Resident waits for review and hearing schedule.',
        ],
        'under_mediation' => [
            'label' => 'Under Mediation',
            'meaning' => 'Hearing has been scheduled and mediation is in progress.',
            'next' => 'Resident attends the scheduled hearing at the barangay hall.',
        ],
        'settled' => [
            'label' => 'Settled',
            'meaning' => 'Case was resolved through barangay mediation.',
            'next' => 'No further action. Resident can request a blotter extract.',
        ],
        'escalated' => [
            'label' => 'Escalated',
            'meaning' => 'Case could not be settled at barangay level and was referred to court.',
            'next' => 'Resident should seek legal assistance.',
        ],
        'closed' => [
            'label' => 'Closed',
            'meaning' => 'Case is closed, settled, dismissed, or withdrawn.',
            'next' => 'No action needed.',
        ],
    ];
}

function rp_doc_icon($slug) {
    $icons = [
        'barangay-clearance' => 'fa-id-card',
        'certificate-residency' => 'fa-house-user',
        'certificate-indigency' => 'fa-hand-holding-heart',
        'business-clearance' => 'fa-store',
        'barangay-certification' => 'fa-certificate',
        'blotter-certificate' => 'fa-scale-balanced',
    ];

    return $icons[$slug] ?? 'fa-file-lines';
}

function rp_default_requirements($slug) {
    $requirements = [
        'barangay-clearance' => ['Valid government ID', 'Proof of residency'],
        'certificate-residency' => ['Valid government ID', 'Proof of address or utility bill'],
        'certificate-indigency' => ['Valid government ID', 'Proof of residency', 'Supporting document for assistance request if available'],
        'business-clearance' => ['Valid government ID', 'Proof of business address', 'Business registration document if available'],
        'barangay-certification' => ['Valid government ID', 'Supporting document for the certification type'],
        'blotter-certificate' => ['Valid government ID', 'Blotter case reference number'],
    ];

    return $requirements[$slug] ?? ['Valid government ID'];
}

function rp_split_requirements($requirements, $slug) {
    $requirements = trim((string)$requirements);
    if ($requirements === '') {
        return rp_default_requirements($slug);
    }

    $parts = preg_split('/[\r\n;,]+/', $requirements);
    $clean = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part !== '') {
            $clean[] = $part;
        }
    }

    return $clean ?: rp_default_requirements($slug);
}

function rp_get_resident_context($conn, $allow_pending = true) {
    if (empty($_SESSION['user_id'])) {
        header('Location: ../login.php');
        exit();
    }

    $user_id = (int)$_SESSION['user_id'];
    $user = rp_fetch_one(
        $conn,
        'SELECT id, fullname, email, role, status, contact, purok FROM users WHERE id = ? LIMIT 1',
        'i',
        [$user_id]
    );

    if (!$user) {
        session_destroy();
        header('Location: ../login.php');
        exit();
    }

    $role = strtolower(trim((string)($user['role'] ?? 'resident')));
    if ($role !== 'resident') {
        header('Location: dashboard.php');
        exit();
    }

    $account_status = strtolower(trim((string)($user['status'] ?? 'active')));
    if ($account_status === '') {
        $account_status = 'active';
    }

    if ($account_status === 'suspended' || ($account_status === 'pending' && !$allow_pending)) {
        $_SESSION['account_status_notice'] = [
            'status' => $account_status,
            'email' => $user['email'] ?? '',
            'message' => $account_status === 'suspended'
                ? 'Your account has been suspended. Contact the barangay office.'
                : 'Your account is awaiting approval by the Secretary'
        ];
        unset($_SESSION['user_id'], $_SESSION['email'], $_SESSION['role']);
        header('Location: ../account_status.php');
        exit();
    }

    $resident = null;
    if (rp_table_exists($conn, 'residents')) {
        if (rp_table_exists($conn, 'households')) {
            $resident = rp_fetch_one(
                $conn,
                'SELECT r.*, h.house_number, h.street, h.purok AS household_purok
                 FROM residents r
                 LEFT JOIN households h ON h.id = r.household_id
                 WHERE r.user_id = ?
                 LIMIT 1',
                'i',
                [$user_id]
            );
        } else {
            $resident = rp_fetch_one($conn, 'SELECT * FROM residents WHERE user_id = ? LIMIT 1', 'i', [$user_id]);
        }
    }

    $fullname = trim((string)($user['fullname'] ?? 'Resident'));
    $first_name = trim((string)($resident['first_name'] ?? ''));
    $last_name = trim((string)($resident['last_name'] ?? ''));
    if ($first_name === '') {
        $parts = preg_split('/\s+/', $fullname);
        $first_name = $parts[0] ?? 'Resident';
    }

    $display_name = trim($first_name . ' ' . $last_name);
    if ($display_name === '') {
        $display_name = $fullname ?: 'Resident';
    }

    $resident_id = (int)($resident['id'] ?? 0);
    $is_verified = $account_status === 'active' && $resident_id > 0;
    $initials = rp_initials($display_name);

    $notifications = [];
    $unread_count = 0;
    if (rp_table_exists($conn, 'notifications')) {
        $unread_count = rp_scalar($conn, 'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', 'i', [$user_id]);
        $notifications = rp_fetch_all(
            $conn,
            'SELECT title, message, link, is_read, created_at
             FROM notifications
             WHERE user_id = ?
             ORDER BY is_read ASC, created_at DESC
             LIMIT 5',
            'i',
            [$user_id]
        );
    }

    $profile_checks = [
        'Full name' => trim($display_name) !== '',
        'Email address' => trim((string)($user['email'] ?? '')) !== '',
        'Mobile number' => trim((string)($resident['contact_number'] ?? $user['contact'] ?? '')) !== '',
        'Date of birth' => !empty($resident['birth_date']),
        'Address' => !empty($resident['street']),
        'Valid ID' => !empty($resident['valid_id_path']),
    ];

    $completed_profile_items = 0;
    $missing_profile_items = [];
    foreach ($profile_checks as $label => $complete) {
        if ($complete) {
            $completed_profile_items++;
        } else {
            $missing_profile_items[] = $label;
        }
    }

    $profile_percent = (int)round(($completed_profile_items / max(count($profile_checks), 1)) * 100);
    $sidebar_missing_summary = $missing_profile_items
        ? 'Missing: ' . implode(', ', array_slice($missing_profile_items, 0, 2)) . (count($missing_profile_items) > 2 ? ', and more' : '')
        : 'Ready for resident transactions';

    return [
        'user' => $user,
        'resident' => $resident,
        'user_id' => $user_id,
        'resident_id' => $resident_id,
        'account_status' => $account_status,
        'is_verified' => $is_verified,
        'first_name' => $first_name,
        'display_name' => $display_name,
        'initials' => $initials,
        'notifications' => $notifications,
        'unread_count' => $unread_count,
        'profile_percent' => $profile_percent,
        'sidebar_missing_summary' => $sidebar_missing_summary,
    ];
}

function rp_sidebar_link_class($active, $name, $extra = '') {
    $classes = trim('sidebar-link ' . $extra);
    if ($active === $name) {
        $classes .= ' is-active';
    }

    return $classes;
}

function rp_render_sidebar($active, array $ctx) {
    ?>
    <aside class="resident-sidebar" id="residentSidebar" aria-label="Resident sidebar">
      <a class="sidebar-brand" href="resident_dashboard.php" aria-label="Go to dashboard">
        <span class="sidebar-brand__seal"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span>
        <span>
          <strong>Brgy. Sta. Rosa 1</strong>
          <small>Resident Portal</small>
        </span>
      </a>

      <nav class="sidebar-menu" aria-label="Resident menu">
        <div class="sidebar-group">
          <a class="<?= rp_e(rp_sidebar_link_class($active, 'dashboard')) ?>" href="resident_dashboard.php"><i class="fa-solid fa-house"></i><span>Home</span></a>
        </div>

        <div class="sidebar-group">
          <span class="sidebar-section-label">Documents</span>
          <a class="<?= rp_e(rp_sidebar_link_class($active, 'request')) ?>" href="request.php"><i class="fa-solid fa-file-circle-plus"></i><span>Request Document</span></a>
          <a class="<?= rp_e(rp_sidebar_link_class($active, 'track')) ?>" href="track.php"><i class="fa-solid fa-folder-open"></i><span>My Requests</span></a>
          <a class="<?= rp_e(rp_sidebar_link_class($active, 'verify')) ?>" href="../verify.php"><i class="fa-solid fa-file-shield"></i><span>Verify Document</span></a>
        </div>

        <div class="sidebar-group">
          <span class="sidebar-section-label">Blotter / Complaints</span>
          <a class="<?= rp_e(rp_sidebar_link_class($active, 'blotter')) ?>" href="blotter.php"><i class="fa-solid fa-pen-to-square"></i><span>File a Complaint</span></a>
          <a class="<?= rp_e(rp_sidebar_link_class($active, 'cases')) ?>" href="my-blotter.php"><i class="fa-solid fa-scale-balanced"></i><span>My Cases</span></a>
        </div>

        <div class="sidebar-group">
          <span class="sidebar-section-label">Barangay Information</span>
          <a class="<?= rp_e(rp_sidebar_link_class($active, 'announcements')) ?>" href="../announcements.php"><i class="fa-solid fa-bullhorn"></i><span>Announcements</span></a>
          <a class="<?= rp_e(rp_sidebar_link_class($active, 'services')) ?>" href="../services.php"><i class="fa-solid fa-receipt"></i><span>Services &amp; Fees</span></a>
          <a class="<?= rp_e(rp_sidebar_link_class($active, 'officials')) ?>" href="../officials.php"><i class="fa-solid fa-users-line"></i><span>Officials Directory</span></a>
          <a class="<?= rp_e(rp_sidebar_link_class($active, 'contact')) ?>" href="../contact.php"><i class="fa-solid fa-phone-volume"></i><span>Contact &amp; Hotlines</span></a>
        </div>

        <div class="sidebar-group">
          <span class="sidebar-section-label">Account</span>
          <a class="<?= rp_e(rp_sidebar_link_class($active, 'profile')) ?>" href="profile.php"><i class="fa-solid fa-user"></i><span>My Profile</span></a>
          <a class="sidebar-link sidebar-link--danger" href="../logout.php"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></a>
        </div>
      </nav>

      <div class="sidebar-completion" aria-label="Profile completion">
        <div class="sidebar-completion__top">
          <span>Profile completion</span>
          <strong><?= rp_e($ctx['profile_percent']) ?>%</strong>
        </div>
        <div class="sidebar-progress" aria-hidden="true"><span style="width: <?= rp_e($ctx['profile_percent']) ?>%"></span></div>
        <small><?= rp_e($ctx['sidebar_missing_summary']) ?></small>
      </div>

      <div class="sidebar-card">
        <span class="sidebar-card__label">Office Hours</span>
        <strong>Mon-Fri, 8:00 AM - 5:00 PM</strong>
        <small>Barangay Hall, Sta. Rosa 1</small>
      </div>
    </aside>
    <?php
}

function rp_render_topbar(array $ctx) {
    ?>
    <header class="resident-topbar">
      <div class="topbar-left">
        <button class="icon-button hamburger-button" id="sidebarToggle" type="button" aria-label="Open sidebar" aria-expanded="false">
          <i class="fa-solid fa-bars" aria-hidden="true"></i>
        </button>
        <a class="topbar-brand" href="resident_dashboard.php">
          <span class="topbar-brand__seal"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span>
          <span>Brgy. Sta. Rosa 1</span>
        </a>
      </div>

      <div class="topbar-actions">
        <button class="icon-button theme-toggle" id="themeToggle" type="button" aria-label="Switch to dark mode" aria-pressed="false">
          <i class="fa-solid fa-moon" aria-hidden="true"></i>
        </button>

        <div class="dropdown-wrap">
          <button class="icon-button notification-button" id="notificationToggle" type="button" aria-label="Open notifications" aria-expanded="false" data-notification-count-url="notifications.php?count=1">
            <i class="fa-solid fa-bell" aria-hidden="true"></i>
            <?php if ((int)$ctx['unread_count'] > 0): ?>
              <span class="notif-badge"><?= rp_e(min((int)$ctx['unread_count'], 9)) ?></span>
            <?php else: ?>
              <span class="notif-dot" aria-hidden="true"></span>
            <?php endif; ?>
          </button>
          <div class="dropdown-panel notification-panel" id="notificationPanel" aria-label="Notifications">
            <div class="dropdown-panel__header">
              <strong>Notifications</strong>
              <a class="text-link" href="notifications.php"><?= rp_e($ctx['unread_count']) ?> unread</a>
            </div>
            <div class="notification-list">
              <?php if ($ctx['notifications']): ?>
                <?php foreach ($ctx['notifications'] as $notice): ?>
                  <a class="notification-item <?= empty($notice['is_read']) ? 'is-unread' : '' ?>" href="<?= rp_e($notice['link'] ?: '#') ?>">
                    <span class="notification-item__icon"><i class="fa-solid fa-circle-info"></i></span>
                    <span>
                      <strong><?= rp_e($notice['title']) ?></strong>
                      <small><?= rp_e($notice['message']) ?></small>
                      <em><?= rp_e(rp_date($notice['created_at'])) ?></em>
                    </span>
                  </a>
                <?php endforeach; ?>
              <?php else: ?>
                <p class="empty-note">No notifications yet.</p>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="dropdown-wrap">
          <button class="profile-button" id="profileToggle" type="button" aria-label="Open profile menu" aria-expanded="false">
            <span class="avatar"><?= rp_e($ctx['initials']) ?></span>
            <span class="profile-button__name"><?= rp_e($ctx['first_name']) ?></span>
            <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
          </button>
          <div class="dropdown-panel profile-panel" id="profilePanel" aria-label="Profile menu">
            <div class="profile-summary">
              <span class="avatar avatar--large"><?= rp_e($ctx['initials']) ?></span>
              <strong><?= rp_e($ctx['display_name']) ?></strong>
              <small><?= rp_e($ctx['user']['email'] ?? '') ?></small>
            </div>
            <a href="profile.php"><i class="fa-solid fa-user"></i> My Profile</a>
            <a href="profile.php#account"><i class="fa-solid fa-lock"></i> Change Password</a>
            <a class="danger" href="../logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
          </div>
        </div>
      </div>
    </header>
    <?php
}

function rp_page_start($title, $active, array $ctx, $page_class = '') {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8" />
      <meta name="viewport" content="width=device-width, initial-scale=1.0" />
      <title><?= rp_e($title) ?> - Barangay Sta. Rosa 1</title>
      <link rel="preconnect" href="https://fonts.googleapis.com">
      <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
      <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
      <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
      <link rel="stylesheet" href="../assets/css/resident_dashboard.css" />
    </head>
    <body>
      <?php rp_render_sidebar($active, $ctx); ?>
      <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>
      <div class="resident-shell">
        <?php rp_render_topbar($ctx); ?>
        <main class="resident-main resident-page <?= rp_e($page_class) ?>">
    <?php
}

function rp_page_end() {
    ?>
        </main>
      </div>
      <script src="../assets/js/resident_dashboard.js"></script>
    </body>
    </html>
    <?php
}

function rp_json_attr($value) {
    return rp_e(json_encode($value, JSON_UNESCAPED_SLASHES));
}

function rp_generate_request_reference($conn) {
    $prefix = 'BR-' . date('Y') . '-';
    $like = $prefix . '%';
    $row = rp_fetch_one(
        $conn,
        'SELECT reference_no FROM document_requests WHERE reference_no LIKE ? ORDER BY reference_no DESC LIMIT 1',
        's',
        [$like]
    );

    $next = 1;
    if ($row && !empty($row['reference_no'])) {
        $last = (int)substr((string)$row['reference_no'], strlen($prefix));
        $next = $last + 1;
    }

    return $prefix . str_pad((string)$next, 5, '0', STR_PAD_LEFT);
}

function rp_generate_case_number($conn) {
    $prefix = 'BL-' . date('Y') . '-';
    $like = $prefix . '%';
    $row = rp_fetch_one(
        $conn,
        'SELECT case_number FROM blotter_cases WHERE case_number LIKE ? ORDER BY case_number DESC LIMIT 1',
        's',
        [$like]
    );

    $next = 1;
    if ($row && !empty($row['case_number'])) {
        $last = (int)substr((string)$row['case_number'], strlen($prefix));
        $next = $last + 1;
    }

    return $prefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

function rp_app_link($path) {
    $base = rtrim((string)(getenv('APP_URL') ?: ''), '/');
    if ($base !== '') {
        return $base . '/' . ltrim($path, '/');
    }

    return $path;
}

function rp_send_email($to, $subject, $html_body, $text_body = '') {
    $to = trim((string)$to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $mailer_path = __DIR__ . '/../../config/mailer.php';
    if (!file_exists($mailer_path)) {
        return false;
    }

    if ((string)getenv('MAIL_USERNAME') === '' || (string)getenv('MAIL_PASSWORD') === '') {
        return false;
    }

    try {
        require_once $mailer_path;
        if (!function_exists('createMailer')) {
            return false;
        }

        $mail = createMailer();
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $html_body;
        $mail->AltBody = $text_body !== '' ? $text_body : strip_tags($html_body);
        $mail->send();
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function rp_create_notification($conn, $user_id, $type, $title, $message, $link = null, $email = null, $email_subject = null) {
    if (rp_table_exists($conn, 'notifications')) {
        $stmt = $conn->prepare(
            'INSERT INTO notifications (user_id, type, title, message, link)
             VALUES (?, ?, ?, ?, ?)'
        );
        if ($stmt) {
            $stmt->bind_param('issss', $user_id, $type, $title, $message, $link);
            $stmt->execute();
            $stmt->close();
        }
    }

    if ($email !== null) {
        $subject = $email_subject ?: $title;
        $body = '<p>' . nl2br(rp_e($message)) . '</p>';
        if ($link) {
            $email_link = $link;
            if (!preg_match('/^https?:\/\//i', $email_link)) {
                if (strpos($email_link, '../') === 0) {
                    $email_link = substr($email_link, 3);
                } elseif (strpos($email_link, 'portal/') !== 0) {
                    $email_link = 'portal/' . ltrim($email_link, '/');
                }
            }
            $body .= '<p><a href="' . rp_e(rp_app_link($email_link)) . '">View details</a></p>';
        }
        rp_send_email($email, $subject, $body, $message);
    }
}

function rp_notify_request_submitted($conn, array $ctx, $request_id, $reference_no) {
    $message = 'Your request ' . $reference_no . ' has been submitted and is being reviewed.';
    rp_create_notification(
        $conn,
        (int)$ctx['user_id'],
        'request_submitted',
        'Document request submitted',
        $message,
        'request-detail.php?id=' . (int)$request_id,
        $ctx['user']['email'] ?? null
    );
}

function rp_notify_request_status($conn, $request_id, $status, $reason = '') {
    $request = rp_fetch_one(
        $conn,
        'SELECT dr.id, dr.reference_no, dr.status, dr.remarks, dt.name AS document_name,
                u.id AS user_id, u.email
         FROM document_requests dr
         INNER JOIN document_types dt ON dt.id = dr.doc_type_id
         INNER JOIN residents r ON r.id = dr.resident_id
         INNER JOIN users u ON u.id = r.user_id
         WHERE dr.id = ?
         LIMIT 1',
        'i',
        [(int)$request_id]
    );

    if (!$request) {
        return;
    }

    $status = strtolower(trim((string)$status));
    $reference_no = $request['reference_no'];
    $document_name = $request['document_name'] ?: 'document';
    $reason = trim($reason !== '' ? (string)$reason : (string)($request['remarks'] ?? ''));

    $messages = [
        'pending' => 'Your request ' . $reference_no . ' is now Pending.',
        'processing' => 'Your request ' . $reference_no . ' is now Processing.',
        'for_approval' => 'Your request ' . $reference_no . ' is now For Approval.',
        'approved' => 'Your ' . $document_name . ' is ready! Pick up at the barangay hall.',
        'released' => 'Your request ' . $reference_no . ' has been released.',
        'cancelled' => 'Your request ' . $reference_no . ' was cancelled.',
        'rejected' => 'Your request ' . $reference_no . ' was rejected. Reason: ' . ($reason !== '' ? $reason : 'Please contact the barangay office.'),
    ];

    $message = $messages[$status] ?? ('Your request ' . $reference_no . ' is now ' . rp_status_label($status) . '.');
    rp_create_notification(
        $conn,
        (int)$request['user_id'],
        'request_status',
        'Request status updated',
        $message,
        'request-detail.php?id=' . (int)$request_id,
        $request['email'] ?? null
    );
}

function rp_notify_account_approved($email) {
    rp_send_email(
        $email,
        'Your Barangay Sta. Rosa 1 account has been verified',
        '<p>Your account has been verified! You can now request documents.</p>',
        'Your account has been verified! You can now request documents.'
    );
}

function rp_notify_announcement($conn, $user_id, $title) {
    rp_create_notification(
        $conn,
        (int)$user_id,
        'announcement',
        'New barangay announcement',
        'New announcement: ' . $title . '.',
        '../announcements.php'
    );
}

function rp_notify_blotter_hearing($conn, $user_id, $email, $case_no, $scheduled_at) {
    $message = 'A hearing for Case ' . $case_no . ' is scheduled on ' . rp_datetime($scheduled_at) . '.';
    rp_create_notification(
        $conn,
        (int)$user_id,
        'blotter_hearing',
        'Blotter hearing scheduled',
        $message,
        'resident_dashboard.php#blotter',
        $email
    );
}

function rp_notify_blotter_settled($conn, $user_id, $email, $case_no) {
    $message = 'Case ' . $case_no . ' has been settled.';
    rp_create_notification(
        $conn,
        (int)$user_id,
        'blotter_settled',
        'Blotter case settled',
        $message,
        'resident_dashboard.php#blotter',
        $email
    );
}

function rp_notify_complaint_submitted($conn, array $ctx, $case_id, $case_no) {
    $message = 'Your complaint ' . $case_no . ' has been logged and is under review.';
    rp_create_notification(
        $conn,
        (int)$ctx['user_id'],
        'blotter_submitted',
        'Complaint logged',
        $message,
        'my-blotter.php?id=' . (int)$case_id,
        $ctx['user']['email'] ?? null
    );

    $secretaries = rp_fetch_all(
        $conn,
        "SELECT id, email FROM users WHERE role = 'secretary' AND status = 'active'"
    );
    foreach ($secretaries as $secretary) {
        rp_create_notification(
            $conn,
            (int)$secretary['id'],
            'blotter_new',
            'New blotter complaint',
            'New complaint ' . $case_no . ' has been filed by ' . ($ctx['display_name'] ?? 'a resident') . '.',
            'portal/dashboard.php',
            $secretary['email'] ?? null
        );
    }
}
