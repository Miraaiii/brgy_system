<?php
session_start();
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

include 'config/connection.php';

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function rd_bind_params($stmt, $types, array $params) {
    if ($types === '') {
        return true;
    }

    $refs = [];
    foreach ($params as $key => $value) {
        $refs[$key] = &$params[$key];
    }

    return $stmt->bind_param($types, ...$refs);
}

function rd_table_exists($conn, $table) {
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }

    $safe_table = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '{$safe_table}'");
    $cache[$table] = $result && $result->num_rows > 0;

    return $cache[$table];
}

function rd_fetch_one($conn, $sql, $types = '', array $params = []) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    rd_bind_params($stmt, $types, $params);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function rd_fetch_all($conn, $sql, $types = '', array $params = []) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    rd_bind_params($stmt, $types, $params);
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

function rd_scalar($conn, $sql, $types = '', array $params = []) {
    $row = rd_fetch_one($conn, $sql, $types, $params);
    if (!$row) {
        return 0;
    }

    $value = reset($row);
    return (int)$value;
}

function rd_initials($name) {
    $initials = '';
    foreach (preg_split('/\s+/', trim((string)$name)) as $part) {
        if ($part !== '') {
            $initials .= strtoupper(substr($part, 0, 1));
        }
    }

    return substr($initials ?: 'RS', 0, 2);
}

function rd_date($value) {
    $time = strtotime((string)$value);
    return $time ? date('M j, Y', $time) : 'Not set';
}

function rd_date_long($value) {
    $time = strtotime((string)$value);
    return $time ? date('F j, Y', $time) : 'Not set';
}

function rd_datetime($value) {
    $time = strtotime((string)$value);
    return $time ? date('F j, Y, g:i A', $time) : 'No hearing scheduled';
}

function rd_relative_time($value) {
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

    return rd_date_long($value);
}

function rd_excerpt($value, $limit = 100) {
    $text = trim(preg_replace('/\s+/', ' ', strip_tags((string)$value)));
    if (strlen($text) <= $limit) {
        return $text;
    }

    return rtrim(substr($text, 0, $limit), " \t\n\r\0\x0B.,") . '...';
}

function rd_status_label($status) {
    $status = strtolower(trim((string)$status));
    $labels = [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'for_approval' => 'For Approval',
        'approved' => 'Ready for Pick-up',
        'released' => 'Released',
        'cancelled' => 'Cancelled',
        'rejected' => 'Rejected',
        'open' => 'Open',
        'under_mediation' => 'Under Mediation',
        'settled' => 'Settled',
        'escalated' => 'Escalated',
        'closed' => 'Closed',
    ];

    return $labels[$status] ?? ucwords(str_replace('_', ' ', $status ?: 'Unknown'));
}

function rd_status_class($status) {
    $status = strtolower(trim((string)$status));
    $classes = [
        'pending' => 'pending',
        'processing' => 'processing',
        'for_approval' => 'processing',
        'approved' => 'ready',
        'released' => 'released',
        'cancelled' => 'cancelled',
        'rejected' => 'cancelled',
        'open' => 'open',
        'under_mediation' => 'mediation',
        'settled' => 'settled',
        'closed' => 'closed',
        'escalated' => 'open',
    ];

    return $classes[$status] ?? 'neutral';
}

function rd_category_class($category) {
    $category = strtolower(trim((string)$category));
    $classes = [
        'health' => 'health',
        'events' => 'events',
        'emergency' => 'emergency',
        'notice' => 'notice',
        'general' => 'general',
        'ordinance' => 'notice',
        'programs' => 'events',
    ];

    return $classes[$category] ?? 'general';
}

$user_id = (int)$_SESSION['user_id'];
$user = rd_fetch_one(
    $conn,
    "SELECT id, fullname, email, role, status, contact, purok FROM users WHERE id = ? LIMIT 1",
    "i",
    [$user_id]
);

if (!$user) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

$role = strtolower(trim((string)($user['role'] ?? 'resident')));
if ($role !== 'resident') {
    header("Location: dashboard.php");
    exit();
}

$account_status = strtolower(trim((string)($user['status'] ?? 'active')));
if ($account_status === '') {
    $account_status = 'active';
}

if ($account_status === 'pending' || $account_status === 'suspended') {
    $_SESSION['account_status_notice'] = [
        'status' => $account_status,
        'email' => $user['email'] ?? '',
        'message' => $account_status === 'suspended'
            ? 'Your account has been suspended. Contact the barangay office.'
            : 'Your account is awaiting approval by the Secretary'
    ];
    unset($_SESSION['user_id'], $_SESSION['email'], $_SESSION['role']);
    header("Location: ../account_status.php");
    exit();
}

$has_households = rd_table_exists($conn, 'households');
$resident = null;
if (rd_table_exists($conn, 'residents')) {
    if ($has_households) {
        $resident = rd_fetch_one(
            $conn,
            "SELECT r.*, h.house_number, h.street, h.purok AS household_purok
             FROM residents r
             LEFT JOIN households h ON h.id = r.household_id
             WHERE r.user_id = ?
             LIMIT 1",
            "i",
            [$user_id]
        );
    } else {
        $resident = rd_fetch_one($conn, "SELECT * FROM residents WHERE user_id = ? LIMIT 1", "i", [$user_id]);
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

$initials = rd_initials($display_name);
$resident_id = (int)($resident['id'] ?? 0);
$is_verified = $account_status === 'active';
$hour = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$today_line = 'Barangay Sta. Rosa 1, Noveleta, Cavite | ' . date('l, F j, Y');

$address_parts = [];
if (!empty($resident['house_number'])) {
    $address_parts[] = $resident['house_number'];
}
if (!empty($resident['street'])) {
    $address_parts[] = $resident['street'];
}
$purok = $resident['household_purok'] ?? ($user['purok'] ?? '');
if (!empty($purok)) {
    $address_parts[] = 'Purok/Zone ' . $purok;
}
$address = $address_parts ? implode(', ', $address_parts) : 'Address not completed';

$pending_count = 0;
$ready_count = 0;
$total_count = 0;
$recent_requests = [];
if ($resident_id > 0 && rd_table_exists($conn, 'document_requests')) {
    $pending_count = rd_scalar(
        $conn,
        "SELECT COUNT(*) FROM document_requests WHERE resident_id = ? AND status IN ('pending', 'processing')",
        "i",
        [$resident_id]
    );
    $ready_count = rd_scalar(
        $conn,
        "SELECT COUNT(*) FROM document_requests WHERE resident_id = ? AND status IN ('approved', 'released')",
        "i",
        [$resident_id]
    );
    $total_count = rd_scalar($conn, "SELECT COUNT(*) FROM document_requests WHERE resident_id = ?", "i", [$resident_id]);

    if (rd_table_exists($conn, 'document_types')) {
        $recent_requests = rd_fetch_all(
            $conn,
        "SELECT dr.id, dr.reference_no, dr.purpose, dr.status, dr.created_at, dt.name AS document_name
             FROM document_requests dr
             INNER JOIN document_types dt ON dt.id = dr.doc_type_id
             WHERE dr.resident_id = ?
             ORDER BY dr.created_at DESC
             LIMIT 5",
            "i",
            [$resident_id]
        );
    }
}

$notifications = [];
$unread_count = 0;
if (rd_table_exists($conn, 'notifications')) {
    $unread_count = rd_scalar($conn, "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0", "i", [$user_id]);
    $notifications = rd_fetch_all(
        $conn,
        "SELECT title, message, link, is_read, created_at
         FROM notifications
         WHERE user_id = ?
         ORDER BY is_read ASC, created_at DESC
         LIMIT 5",
        "i",
        [$user_id]
    );
}

$announcements = [];
if (rd_table_exists($conn, 'announcements')) {
    $announcements = rd_fetch_all(
        $conn,
        "SELECT title, category, body, published_at, created_at
         FROM announcements
         WHERE is_published = 1
         ORDER BY COALESCE(published_at, created_at) DESC
         LIMIT 3"
    );
}

$document_processing_times = [];
if (rd_table_exists($conn, 'document_types')) {
    $document_processing_times = rd_fetch_all(
        $conn,
        "SELECT name, fee, processing_days
         FROM document_types
         WHERE is_active = 1
         ORDER BY name ASC
         LIMIT 8"
    );
}

$blotter_cases = [];
if ($resident_id > 0 && rd_table_exists($conn, 'blotter_cases') && rd_table_exists($conn, 'blotter_parties')) {
    $hearing_select = rd_table_exists($conn, 'blotter_hearings')
        ? ", (SELECT MIN(bh.scheduled_at)
              FROM blotter_hearings bh
              WHERE bh.case_id = bc.id
                AND bh.status IN ('scheduled', 'rescheduled')) AS next_hearing_at"
        : ", NULL AS next_hearing_at";

    $blotter_cases = rd_fetch_all(
        $conn,
        "SELECT DISTINCT bc.case_number, bc.incident_type, bc.status, bc.created_at, bc.incident_date, bc.updated_at{$hearing_select}
         FROM blotter_cases bc
         INNER JOIN blotter_parties bp ON bp.case_id = bc.id
         WHERE bp.resident_id = ?
         ORDER BY bc.updated_at DESC
         LIMIT 3",
        "i",
        [$resident_id]
    );
}

$profile_checks = [
    'Full name' => trim($display_name) !== '',
    'Email address' => trim((string)($user['email'] ?? '')) !== '',
    'Mobile number' => trim((string)($resident['contact_number'] ?? $user['contact'] ?? '')) !== '',
    'Date of birth' => !empty($resident['birth_date']),
    'Place of birth' => !empty($resident['birth_place']),
    'Sex' => !empty($resident['sex']),
    'Civil status' => !empty($resident['civil_status']),
    'Nationality' => !empty($resident['nationality']),
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

$profile_display_checks = [
    'Name' => trim($display_name) !== '',
    'Email' => trim((string)($user['email'] ?? '')) !== '',
    'Mobile' => trim((string)($resident['contact_number'] ?? $user['contact'] ?? '')) !== '',
    'Address' => !empty($resident['street']),
    'Date of Birth' => !empty($resident['birth_date']),
    'Valid ID' => !empty($resident['valid_id_path']),
];
$completed_display_items = [];
$missing_display_items = [];
foreach ($profile_display_checks as $label => $complete) {
    if ($complete) {
        $completed_display_items[] = $label;
    } else {
        $missing_display_items[] = $label;
    }
}

$office_address = 'Brgy. Sta. Rosa 1, Noveleta, Cavite';
$contact_number = '+63 912 000 0000';
$barangay_hotline = 'Emergency Hotline 911';
$emergency_numbers = 'PNP 166, Fire 1555, NDRRMC 825-0000';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Barangay Financial Management - Barangay Sta. Rosa 1</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/resident_dashboard.css" />
</head>
<body>
  <aside class="resident-sidebar" id="residentSidebar" aria-label="Resident sidebar">
    <a class="sidebar-brand" href="#" aria-label="Go to dashboard">
      <span class="sidebar-brand__seal"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span>
      <span>
        <strong>Brgy. Sta. Rosa 1</strong>
        <small>Resident Portal</small>
      </span>
    </a>

    <nav class="sidebar-menu" aria-label="Resident menu">
      <div class="sidebar-group">
        <a class="sidebar-link is-active" href="#"><i class="fa-solid fa-house"></i><span>Dashboard</span></a>
      </div>

      <div class="sidebar-group">
        <span class="sidebar-section-label">COLLECTIONS</span>
        <a class="sidebar-link" href="all_collections.php"><i class="fa-solid fa-money-bill-transfer"></i><span>All Collections</span></a>
        <a class="sidebar-link" href="#"><i class="fa-solid fa-cash-register"></i><span>Record Payment</span></a>
        <a class="sidebar-link" href="#"><i class="fa-solid fa-file-invoice-dollar"></i><span>Document Fees</span></a>
        <a class="sidebar-link" href="#"><i class="fa-solid fa-store"></i><span>Business Permits</span></a>
        <a class="sidebar-link" href="#"><i class="fa-solid fa-wallet"></i><span>Other Collections</span></a>
      </div>

      <div class="sidebar-group">
        <span class="sidebar-section-label">EXPENDITURES</span>
        <a class="sidebar-link" href="#"><i class="fa-solid fa-money-bill-wave"></i><span>All Expenditures</span></a>
        <a class="sidebar-link" href="#"><i class="fa-solid fa-plus-circle"></i><span>Add Expenditures</span></a>
        <a class="sidebar-link" href="#"><i class="fa-solid fa-layer-group"></i><span>By Category</span></a>
        <a class="sidebar-link" href="#"><i class="fa-solid fa-user-clock"></i><span>Pending Captain Approval</span></a>
      </div>

      <div class="sidebar-group">
        <span class="sidebar-section-label">BUDGET</span>
        <a class="sidebar-link" href="#"><i class="fa-solid fa-calendar-check"></i><span>Annual Budget Plan</span></a>
        <a class="sidebar-link" href="#"><i class="fa-solid fa-chart-pie"></i><span>Budget Utilization</span></a>
        <a class="sidebar-link" href="#"><i class="fa-solid fa-circle-plus"></i><span>Add Budget Item</span></a>
      </div>

      <div class="sidebar-group">
        <span class="sidebar-section-label">REPORTS</span>
        <a class="sidebar-link" href="#"><i class="fa-solid fa-calendar-days"></i><span>Monthly Summary</span></a>
        <a class="sidebar-link" href="#"><i class="fa-solid fa-chart-line"></i><span>Quarterly Report</span></a>
        <a class="sidebar-link" href="#"><i class="fa-solid fa-file-invoice-dollar"></i><span>Annual Statement</span></a>
        <a class="sidebar-link" href="#"><i class="fa-solid fa-file-export"></i><span>Export to PDF / Excel</span></a>
      </div>

      <div class="sidebar-group">
        <span class="sidebar-section-label">Account</span>
        <a class="sidebar-link" href="#profile"><i class="fa-solid fa-user"></i><span>My Profile</span></a>
        <a class="sidebar-link sidebar-link--danger" href="../logout.php"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></a>
      </div>
    </nav>

    <div class="sidebar-completion" aria-label="Profile completion">
      <div class="sidebar-completion__top">
        <span>Profile completion</span>
        <strong><?= e($profile_percent) ?>%</strong>
      </div>
      <div class="sidebar-progress" aria-hidden="true"><span style="width: <?= e($profile_percent) ?>%"></span></div>
      <small><?= e($sidebar_missing_summary) ?></small>
    </div>

    <div class="sidebar-card">
      <span class="sidebar-card__label">Office Hours</span>
      <strong>Mon-Fri, 8:00 AM - 5:00 PM</strong>
      <small>Barangay Hall, Sta. Rosa 1</small>
    </div>
  </aside>

  <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>

  <div class="resident-shell">
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
            <?php if ($unread_count > 0): ?>
              <span class="notif-badge"><?= e(min($unread_count, 9)) ?></span>
            <?php else: ?>
              <span class="notif-dot" aria-hidden="true"></span>
            <?php endif; ?>
          </button>
          <div class="dropdown-panel notification-panel" id="notificationPanel" aria-label="Notifications">
            <div class="dropdown-panel__header">
              <strong>Notifications</strong>
              <a class="text-link" href="notifications.php"><?= e($unread_count) ?> unread</a>
            </div>
            <div class="notification-list">
              <?php if ($notifications): ?>
                <?php foreach ($notifications as $notice): ?>
                  <a class="notification-item <?= empty($notice['is_read']) ? 'is-unread' : '' ?>" href="<?= e($notice['link'] ?: '#') ?>">
                    <span class="notification-item__icon"><i class="fa-solid fa-circle-info"></i></span>
                    <span>
                      <strong><?= e($notice['title']) ?></strong>
                      <small><?= e($notice['message']) ?></small>
                      <em><?= e(rd_date($notice['created_at'])) ?></em>
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
            <span class="avatar"><?= e($initials) ?></span>
            <span class="profile-button__name"><?= e($first_name) ?></span>
            <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
          </button>
          <div class="dropdown-panel profile-panel" id="profilePanel" aria-label="Profile menu">
            <div class="profile-summary">
              <span class="avatar avatar--large"><?= e($initials) ?></span>
              <strong><?= e($display_name) ?></strong>
              <small><?= e($user['email']) ?></small>
            </div>
            <a href="profile.php"><i class="fa-solid fa-user"></i> My Profile</a>
            <a href="profile.php#account"><i class="fa-solid fa-lock"></i> Change Password</a>
            <a class="danger" href="../logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
          </div>
        </div>
      </div>
    </header>

    <main class="resident-main" id="dashboard">
      <section class="welcome-banner priority-high">
        <div>
          <div class="welcome-eyebrow"><?= e($today_line) ?></div>
          <h1><?= e($greeting) ?>, <?= e($first_name) ?></h1>
          <p>Track your barangay requests, announcements, and case updates in one place.</p>
        </div>
      </section>

      <section class="stat-grid priority-high" aria-label="Request status summary">
        <a class="status-card status-card--warning" href="#my-requests" data-request-filter="pending">
          <span class="status-card__icon"><i class="fa-solid fa-sack-dollar"></i></span>
          <span class="status-card__body">
            <strong><?= e($pending_count) ?></strong>
            <small>Total Collections</small>
          </span>
          <i class="fa-solid fa-arrow-right status-card__arrow"></i>
        </a>
        <a class="status-card status-card--success" href="#my-requests" data-request-filter="ready">
          <span class="status-card__icon"><i class="fa-solid fa-money-bill-wave"></i></span>
          <span class="status-card__body">
            <strong><?= e($ready_count) ?></strong>
            <small>Total Expenditures</small>
          </span>
          <i class="fa-solid fa-arrow-right status-card__arrow"></i>
        </a>
        <a class="status-card status-card--info" href="#my-requests" data-request-filter="all">
          <span class="status-card__icon"><i class="fa-solid fa-chart-pie"></i></span>
          <span class="status-card__body">
            <strong><?= e($total_count) ?></strong>
            <small>Budget Utilization</small>
          </span>
          <i class="fa-solid fa-arrow-right status-card__arrow"></i>
        </a>
        <a class="status-card status-card--info" href="#my-requests" data-request-filter="all">
          <span class="status-card__icon"><i class="fa-solid fa-user-clock"></i></span>
          <span class="status-card__body">
            <strong><?= e($total_count) ?></strong>
            <small>Pending Approvals</small>
          </span>
          <i class="fa-solid fa-arrow-right status-card__arrow"></i>
        </a>
      </section>

      <section class="medium-grid priority-medium">
        <div class="dashboard-panel" id="announcements">
          <div class="panel-header">
            <div>
              <h2>Monthly Revenue</h2>
            </div>
          </div>
          <div class="announcement-list">
            <canvas id="revenueChart"></canvas>
          </div>
        </div>
      </section>

      <section class="high-grid priority-high">
        <div class="dashboard-panel quick-panel" id="request-doc">
          <div class="panel-header">
            <div>
              <h2>Recent Transactions</h2>
            </div>
            <a class="text-link" href="#my-requests" data-request-filter="all">View all</a>
          </div>
          <div class="quick-actions">
            <table>
                <thead>
                    <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="color:var(--text-muted);font-size:11px;">May 24, 2025</td>
                        <td>Business Permit Fee</td>
                        <td><span class="badge collection">Collection</span></td>
                        <td class="amount">₱ 2,500.00</td>
                        <td><span class="badge completed">Completed</span></td>
                    </tr>

                    <tr>
                        <td style="color:var(--text-muted);font-size:11px;">May 23, 2025</td>
                        <td>Office Supplies</td>
                        <td><span class="badge expenditure">Expenditure</span></td>
                        <td class="amount">₱ 1,250.00</td>
                        <td><span class="badge approved">Approved</span></td>
                    </tr>

                    <tr>
                        <td style="color:var(--text-muted);font-size:11px;">May 23, 2025</td>
                        <td>Document Request Fee</td>
                        <td><span class="badge collection">Collection</span></td>
                        <td class="amount">₱ 150.00</td>
                        <td><span class="badge completed">Completed</span></td>
                    </tr>

                    <tr>
                        <td style="color:var(--text-muted);font-size:11px;">May 22, 2025</td>
                        <td>Fuel Expense</td>
                        <td><span class="badge expenditure">Expenditure</span></td>
                        <td class="amount">₱ 3,000.00</td>
                        <td><span class="badge pending">Pending</span></td>
                    </tr>

                    <tr>
                        <td style="color:var(--text-muted);font-size:11px;">May 21, 2025</td>
                        <td>Certificate Fee</td>
                        <td><span class="badge collection">Collection</span></td>
                        <td class="amount">₱ 300.00</td>
                        <td><span class="badge completed">Completed</span></td>
                    </tr>
                </tbody>
            </table>
          </div>
        </div>

        <div class="dashboard-panel requests-panel" id="my-requests">
          <div class="panel-header">
            <div>
              <h2>Budget Allocation</h2>
            </div>
            <a class="text-link" href="#my-requests" data-request-filter="all">View all</a>
          </div>
          <div class="donut-wrap">
          <div class="donut-canvas-wrap">
            <canvas id="donutChart"></canvas>
                <div class="donut-center">
                    <strong>Total Budget</strong>
                    <span>₱2,000,000</span>
                    </div>
                </div>
                <div class="donut-legend">
                    <div class="legend-item">
                    <div class="legend-label"><div class="legend-dot" style="background:#3b82f6"></div>General Admin</div>
                    <div><span class="legend-val">₱800,000</span><span class="legend-pct">40%</span></div>
                    </div>
                    <div class="legend-item">
                    <div class="legend-label"><div class="legend-dot" style="background:#22c55e"></div>Public Services</div>
                    <div><span class="legend-val">₱600,000</span><span class="legend-pct">30%</span></div>
                    </div>
                    <div class="legend-item">
                    <div class="legend-label"><div class="legend-dot" style="background:#e8a020"></div>Social Services</div>
                    <div><span class="legend-val">₱400,000</span><span class="legend-pct">20%</span></div>
                    </div>
                    <div class="legend-item">
                    <div class="legend-label"><div class="legend-dot" style="background:#8b5cf6"></div>Other Services</div>
                    <div><span class="legend-val">₱200,000</span><span class="legend-pct">10%</span></div>
                    </div>
                </div>
            </div>
        </div>
      </section>

      <section class="low-grid priority-low">
        <div class="contact-strip">
          <div>
            <span>Office Address</span>
            <strong><?= e($office_address) ?></strong>
          </div>
          <div>
            <span>Phone / Hotline</span>
            <strong><?= e($contact_number) ?></strong>
            <small><?= e($barangay_hotline) ?></small>
          </div>
          <div>
            <span>Office Hours</span>
            <strong>Monday to Friday, 8:00 AM to 5:00 PM</strong>
          </div>
          <div>
            <span>Emergency Numbers</span>
            <strong><?= e($emergency_numbers) ?></strong>
          </div>
        </div>

        <div class="dashboard-panel faq-panel" id="help">
          <div class="panel-header">
            <div>
              <h2>Help / FAQ</h2>
              <p>Quick answers for resident portal use.</p>
            </div>
          </div>
          <div class="faq-list">
            <button class="faq-item" type="button" aria-expanded="false">
              <span>How do I request a document?</span>
              <i class="fa-solid fa-chevron-down"></i>
            </button>
            <div class="faq-answer">
              <ol>
                <li>Open Request Document from the sidebar or quick actions.</li>
                <li>Select the document type and enter the purpose.</li>
                <li>Review the request details, submit, then track the status in My Requests.</li>
              </ol>
              <a class="faq-link" href="request.php">Go to document request</a>
            </div>

            <button class="faq-item" type="button" aria-expanded="false">
              <span>How long does processing take?</span>
              <i class="fa-solid fa-chevron-down"></i>
            </button>
            <div class="faq-answer">
              <?php if ($document_processing_times): ?>
                <div class="faq-table-wrap">
                  <table class="faq-table">
                    <thead>
                      <tr>
                        <th>Document Type</th>
                        <th>Processing Time</th>
                        <th>Fee</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($document_processing_times as $document_type): ?>
                        <tr>
                          <td><?= e($document_type['name']) ?></td>
                          <td><?= e((int)$document_type['processing_days']) ?> day<?= (int)$document_type['processing_days'] === 1 ? '' : 's' ?></td>
                          <td>PHP <?= e(number_format((float)$document_type['fee'], 2)) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php else: ?>
                <p>Most document requests are processed within 1 to 3 working days after review.</p>
              <?php endif; ?>
            </div>

            <button class="faq-item" type="button" aria-expanded="false">
              <span>What IDs are accepted?</span>
              <i class="fa-solid fa-chevron-down"></i>
            </button>
            <div class="faq-answer">
              <ul class="faq-id-list">
                <li>Philippine National ID</li>
                <li>Passport</li>
                <li>Driver's License</li>
                <li>UMID / SSS ID</li>
                <li>GSIS ID</li>
                <li>PRC ID</li>
                <li>Voter's ID</li>
                <li>Postal ID</li>
                <li>PhilHealth ID</li>
                <li>Senior Citizen or PWD ID</li>
              </ul>
            </div>

            <button class="faq-item" type="button" aria-expanded="false">
              <span>How do I file a complaint?</span>
              <i class="fa-solid fa-chevron-down"></i>
            </button>
            <div class="faq-answer">
              <p>Prepare the incident details, date, location, and names of involved persons if known. Submit the complaint form and wait for barangay staff to review the case.</p>
              <a class="faq-link" href="blotter.php">File a complaint</a>
            </div>

            <button class="faq-item" type="button" aria-expanded="false">
              <span>How do I update my information?</span>
              <i class="fa-solid fa-chevron-down"></i>
            </button>
            <div class="faq-answer">
              <p>Open your profile page to update missing or outdated information. Some verified details may require staff review.</p>
              <a class="faq-link" href="profile.php">Update my profile</a>
            </div>
          </div>
        </div>
      </section>
    </main>
  </div>

  <script src="assets/js/resident_dashboard.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>

  <script>
    // Revenue Line Chart
    const revCtx = document.getElementById('revenueChart').getContext('2d');
    new Chart(revCtx, {
    type: 'line',
    data: {
        labels: ['May 1','May 8','May 15','May 22','May 29'],
        datasets: [{
        label: 'Revenue',
        data: [80000, 130000, 160000, 145000, 175000],
        borderColor: '#e8a020',
        backgroundColor: 'rgba(232,160,32,0.08)',
        borderWidth: 2.5,
        pointBackgroundColor: '#e8a020',
        pointRadius: 4,
        pointHoverRadius: 6,
        tension: 0.4,
        fill: true,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: {
        backgroundColor: '#1a2236',
        titleColor: '#fff',
        bodyColor: '#e8a020',
        padding: 10,
        callbacks: {
            label: ctx => ' ₱ ' + ctx.raw.toLocaleString()
        }
        }},
        scales: {
        x: { grid: { display: false }, ticks: { font: { size: 10 }, color: '#6b7a99' } },
        y: {
            grid: { color: 'rgba(0,0,0,0.04)' },
            ticks: { font: { size: 10 }, color: '#6b7a99', callback: v => v >= 1000 ? (v/1000)+'K' : v }
        }
        }
    }
    });

    // Donut Chart
    const doCtx = document.getElementById('donutChart').getContext('2d');
    new Chart(doCtx, {
    type: 'doughnut',
    data: {
        labels: ['General Admin','Public Services','Social Services','Other Services'],
        datasets: [{
        data: [40, 30, 20, 10],
        backgroundColor: ['#3b82f6','#22c55e','#e8a020','#8b5cf6'],
        borderWidth: 2,
        borderColor: '#ffffff',
        hoverOffset: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '72%',
        plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: '#1a2236',
            titleColor: '#fff',
            bodyColor: '#e8a020',
            callbacks: { label: ctx => ' ' + ctx.label + ': ' + ctx.raw + '%' }
        }
        }
    }
    });
  </script>
</body>
</html>
