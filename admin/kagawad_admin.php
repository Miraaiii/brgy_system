<?php
    require_once __DIR__ . '/includes/admin_layout.php';

    include '../config/connection.php';
    include '../includes/auth_check.php';

    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login.php");
        exit();
    }

    requireRole(['kagawad']);

    

    $tab = $_GET['tab'] ?? 'dashboard';

    $current_user = [
        'id'       => $_SESSION['user_id'],
        'email'    => $_SESSION['email'],
        'role'     => $_SESSION['role'],
        'fullname' => $_SESSION['fullname'] ?? 'Kagawad',
        'username' => $_SESSION['username']
    ];

    adm_page_start(
        'Kagawad Management',
        $tab,
        $current_user
    );

    // Greeting
    $hour = (int) date('H');

    if ($hour < 12) {
        $greeting = 'Good Morning';
    } elseif ($hour < 18) {
        $greeting = 'Good Afternoon';
    } else {
        $greeting = 'Good Evening';
    }

    $first_name = explode(' ', trim($_SESSION['fullname'] ?? 'Kagawad'))[0];

    $role_label = ucfirst(str_replace('_', ' ', $_SESSION['role'] ?? 'kagawad'));

    // Escape helper fallback
    if (!function_exists('e')) {
        function e($value) {
            return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        }
    }

    if (!function_exists('adm_e')) {
        function adm_e($value) {
            return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        }
    }

    /* STATS CARD - MY COMMITTEE */
    $committee_name = '';
    $active_programs = 0;
    $completed_programs = 0;
    $upcoming_events = 0;
    $my_announcements = 0;

    /* Get Kagawad committee */
    $stmt = $conn->prepare("
        SELECT o.committee, o.position, u.username
        FROM officials o
        JOIN users u ON u.id = o.user_id
        WHERE o.user_id = ?
        AND o.is_active = 1
        LIMIT 1
    ");

    if ($stmt) {
        $stmt->bind_param('i', $_SESSION['user_id']);
        $stmt->execute();
        $official = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $committee_name = trim($official['committee'] ?? '');
    }

    /* Program stats */
    if ($committee_name !== '') {

        $stmt = $conn->prepare("
            SELECT
                SUM(p.status = 'ongoing') AS active,
                SUM(p.status = 'completed') AS completed
            FROM projects p
            JOIN users u ON u.id = p.created_by
            WHERE p.committee = ?
            AND u.role IN ('captain','kagawad')
        ");

        if ($stmt) {
            $stmt->bind_param('s', $committee_name);
            $stmt->execute();
            $stats = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $active_programs = (int)($stats['active'] ?? 0);
            $completed_programs = (int)($stats['completed'] ?? 0);
        }

        /* Upcoming events */
        $stmt = $conn->prepare("
            SELECT COUNT(*)
            FROM events
            WHERE committee = ?
            AND event_date >= CURDATE()
        ");

        if ($stmt) {
            $stmt->bind_param('s', $committee_name);
            $stmt->execute();
            $stmt->bind_result($upcoming_events);
            $stmt->fetch();
            $stmt->close();
        }
    }

    /* My announcements */
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM announcements
        WHERE created_by = ?
    ");

    if ($stmt) {
        $stmt->bind_param('i', $_SESSION['user_id']);
        $stmt->execute();
        $stmt->bind_result($my_announcements);
        $stmt->fetch();
        $stmt->close();
    }

    /* STATS CARD - BARANGAY OVERVIEW */
    $total_residents = 0;
    $docs_issued_month = 0;
    $open_blotter_cases = 0;
    $monthly_collection = 0;

    /* 1. Total Residents */
    $stmt = $conn->prepare("SELECT COUNT(*) FROM residents");
    if ($stmt) {
        $stmt->execute();
        $stmt->bind_result($total_residents);
        $stmt->fetch();
        $stmt->close();
    }

    /* 2. Documents Issued This Month */
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM issued_documents
        WHERE MONTH(issued_at) = MONTH(CURDATE())
        AND YEAR(issued_at) = YEAR(CURDATE())
    ");
    if ($stmt) {
        $stmt->execute();
        $stmt->bind_result($docs_issued_month);
        $stmt->fetch();
        $stmt->close();
    }

    /* 3. Open Blotter Cases */
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM blotter_cases
        WHERE status IN ('open', 'under_mediation')
    ");
    if ($stmt) {
        $stmt->execute();
        $stmt->bind_result($open_blotter_cases);
        $stmt->fetch();
        $stmt->close();
    }

    /* 4. Monthly Collection */
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(amount), 0)
        FROM collections
        WHERE MONTH(collected_at) = MONTH(CURDATE())
        AND YEAR(collected_at) = YEAR(CURDATE())
    ");
    if ($stmt) {
        $stmt->execute();
        $stmt->bind_result($monthly_collection);
        $stmt->fetch();
        $stmt->close();
    }
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
  <link rel="stylesheet" href="assets/css/kagawad.css">
  <link rel="stylesheet" href="assets/css/secretary.css?v=20260607b">
  
  <style>
    .welcome-meta {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-top: 12px;
      flex-wrap: wrap;
    }

    .role-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: #16a34a;
      color: #fff;
      font-size: 12px;
      font-weight: 600;
      padding: 4px 12px;
      border-radius: 999px;
      letter-spacing: 0.02em;
    }

    .fiscal-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: rgba(255,255,255,0.15);
      color: #fff;
      font-size: 12px;
      font-weight: 500;
      padding: 4px 12px;
      border-radius: 999px;
      border: 1px solid rgba(255,255,255,0.3);
      backdrop-filter: blur(4px);
    }

    .role-badge i,
    .fiscal-badge i {
      font-size: 11px;
    }

    .stat-grid {
      display: grid;
      grid-template-columns: repeat(1, 1fr);
      gap: 0.75rem; /* tighten the gap slightly */
    }

    .status-card {
      min-width: 0; /* prevents grid blowout */
    }

    .quick-action-btn {
      display: flex;
      align-items: center;
      gap: 10px;
      flex: 1;
      min-width: 180px;
      padding: 16px 20px;
      border-radius: 10px;
      background: var(--card-bg, #1e2a3a);
      border: 1px solid var(--border-color, #2e3d50);
      color: var(--text-primary, #fff);
      font-size: 14px;
      font-weight: 600;
      text-decoration: none;
      transition: background 0.2s, border-color 0.2s, transform 0.15s;
    }

    .quick-action-btn:hover {
      background: var(--primary, #eab308);
      color: #000;
      border-color: var(--primary, #eab308);
      transform: translateY(-2px);
    }

    .quick-action-btn i {
      font-size: 18px;
    }

    /* Tablet — 2 per row */
    @media (min-width: 576px) {
      .stat-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }

    /* Large tablet — 3 per row */
    @media (min-width: 768px) {
      .stat-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
      }
    }

    /* Desktop — all 5 in one row */
    @media (min-width: 1200px) {
      .stat-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
      }
    }
  </style>
</head>
<body>

    <div class="kagawad-main" id="dashboard">
        <section class="welcome-panel">
            <div>
                <h1><?= e($greeting) ?>, <?= e($first_name) ?></h1>
                <p><?= adm_e(date('l, F j, Y')) ?> - Barangay Sta. Rosa 1, Noveleta, Cavite.</p>
            </div>

            <div class="welcome-badges">
                <span class="role-badge role-badge--gold">
                    <i class="fa-solid fa-user-tie"></i>
                    <?= e($role_label) ?>
                </span>
            </div>
        </section>

        <section class="stat-grid priority-high" aria-label="Committee summary">

            <!-- Section Header spanning all columns -->
            <div class="stat-grid__header">
                <span class="admin-nav__section">
                    <i class="fa-solid fa-building-columns" aria-hidden="true"></i>
                    My Committee
                </span>
            </div>

            <!-- 1. Active Programs -->
            <a class="status-card status-card--success" href="projects.php?committee=own" aria-label="View active programs">
                <span class="status-card__icon"><i class="fa-solid fa-diagram-project"></i></span>
                <div class="kagawad-card__body">
                    <strong><span class="status-card__value"><?= adm_e($active_programs) ?></span></strong>
                    <span class="status-card__label">Active Programs</span>
                </div>
            </a>

            <!-- 2. Completed Programs -->
            <div class="status-card status-card--info">
                <span class="status-card__icon"><i class="fa-solid fa-circle-check"></i></span>
                <div class="kagawad-card__body">
                    <strong><span class="status-card__value"><?= adm_e($completed_programs) ?></span></strong>
                    <span class="status-card__label">Completed Programs</span>
                </div>
            </div>

            <!-- 3. Upcoming Events -->
            <a class="status-card status-card--warning" href="events.php?committee=own" aria-label="View upcoming events">
                <span class="status-card__icon"><i class="fa-solid fa-calendar-days"></i></span>
                <div class="kagawad-card__body">
                    <strong><span class="status-card__value"><?= adm_e($upcoming_events) ?></span></strong>
                    <span class="status-card__label">Upcoming Events</span>
                </div>
            </a>

            <!-- 4. My Announcements -->
            <a class="status-card status-card--primary" href="announcements.php?author=own" aria-label="View my announcements">
                <span class="status-card__icon"><i class="fa-solid fa-bullhorn"></i></span>
                <div class="kagawad-card__body">
                    <strong><span class="status-card__value"><?= adm_e($my_announcements) ?></span></strong>
                    <span class="status-card__label">My Announcements</span>
                </div>
            </a>

        </section>

        <section class="stat-grid priority-medium" aria-label="Barangay overview">

            <!-- Section Header spanning all columns -->
            <div class="stat-grid__header">
                <span class="admin-nav__section">
                    <i class="fa-solid fa-building-columns" aria-hidden="true"></i>
                    Barangay Overview
                </span>
            </div>

            <!-- 1. Total Residents -->
            <div class="status-card status-card--info" aria-label="Total residents">
                <span class="status-card__icon">
                    <i class="fa-solid fa-users" aria-hidden="true"></i>
                </span>
                <div class="kagawad-card__body">
                    <span class="status-card__value"><?= adm_e(number_format($total_residents)) ?></span>
                    <span class="status-card__label">Total Residents</span>
                </div>
            </div>

            <!-- 2. Docs Issued (This Month) -->
            <div class="status-card status-card--success" aria-label="Documents issued this month">
                <span class="status-card__icon">
                    <i class="fa-solid fa-file-circle-check" aria-hidden="true"></i>
                </span>
                <div class="kagawad-card__body">
                    <span class="status-card__value"><?= adm_e(number_format($docs_issued_month)) ?></span>
                    <span class="status-card__label">Docs Issued (This Month)</span>
                </div>
            </div>

            <!-- 3. Open Blotter Cases -->
            <div class="status-card <?= $open_blotter_cases > 0 ? 'status-card--warning' : 'status-card--success' ?>" aria-label="Open blotter cases">
                <span class="status-card__icon">
                    <i class="fa-solid fa-scale-balanced" aria-hidden="true"></i>
                </span>
                <div class="kagawad-card__body">
                    <span class="status-card__value"><?= adm_e(number_format($open_blotter_cases)) ?></span>
                    <span class="status-card__label">Open Blotter Cases</span>
                </div>
            </div>

            <!-- 4. Monthly Collection -->
            <div class="status-card status-card--success" aria-label="Monthly collection">
                <span class="status-card__icon">
                    <i class="fa-solid fa-peso-sign" aria-hidden="true"></i>
                </span>
                <div class="kagawad-card__body">
                    <span class="status-card__value">₱<?= adm_e(number_format($monthly_collection, 2)) ?></span>
                    <span class="status-card__label">Monthly Collection</span>
                </div>
            </div>

        </section>

        <section class="high-grid priority-high">
            <!-- My Active Programs List -->
            <div class="dashboard-panel quick-panel" id="my-active-programs" style="grid-column: 1 / -1;">
                <div class="panel-header">
                    <div>
                        <h3 class="panel-title">My Active Programs</h3>
                    </div>
                    <a href="admin/projects.php?committee=own" class="btn btn--small">View All Programs</a>
                </div>
                <div class="dash-table-wrap">
                    <?php
                        $committee_name = trim($official['committee'] ?? '');

                        $programs_sql = "
                            SELECT
                                p.id,
                                p.title,
                                p.status,
                                p.start_date,
                                p.progress_percent,
                                p.committee
                            FROM projects p
                            WHERE p.committee = ?
                            ORDER BY p.start_date DESC
                        ";

                        $stmt = $conn->prepare($programs_sql);
                        $stmt->bind_param("s", $committee_name);
                        $stmt->execute();
                        $programs_result = $stmt->get_result();
                    ?>

                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>Program Name</th>
                                <th>Status</th>
                                <th>Start Date</th>
                                <th>Progress</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($programs_result && $programs_result->num_rows > 0): ?>
                                <?php while ($program = $programs_result->fetch_assoc()): ?>
                                    <?php
                                        $progress   = (int) ($program['progress_percent'] ?? 0);
                                        $raw_status = strtolower(trim($program['status'] ?? ''));

                                        // Map status to existing badge classes
                                        switch ($raw_status) {
                                            case 'completed':
                                                $badge_class = 'badge-completed';
                                                break;
                                            case 'ongoing':
                                            case 'in progress':
                                                $badge_class = 'badge-ongoing';
                                                break;
                                            case 'pending':
                                                $badge_class = 'badge-pending';
                                                break;
                                            case 'cancelled':
                                                $badge_class = 'badge-cancelled';
                                                break;
                                            default:
                                                $badge_class = 'badge-default';
                                                break;
                                        }

                                        $start_date  = !empty($program['start_date'])
                                            ? date('M d, Y', strtotime($program['start_date']))
                                            : '—';

                                        $belongs_to_user_committee = (trim($program['committee']) === trim($committee_name));
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($program['title']) ?></td>

                                        <td>
                                            <span class="badge <?= $badge_class ?>">
                                                <?= htmlspecialchars(ucfirst($program['status'])) ?>
                                            </span>
                                        </td>

                                        <td><?= $start_date ?></td>

                                        <td>
                                            <div class="progress-wrap">
                                                <span class="progress-label"><?= $progress ?>%</span>
                                                <div class="progress-track">
                                                    <span style="width: <?= $progress ?>%;"></span>
                                                </div>
                                            </div>
                                        </td>

                                        <td>
                                            <div class="action-btns">
                                                <a href="admin/project-detail.php?id=<?= (int)$program['id'] ?>"
                                                class="btn btn--small">
                                                    View Details
                                                </a>
                                                <?php if ($belongs_to_user_committee): ?>
                                                    <a href="admin/project-form.php?id=<?= (int)$program['id'] ?>"
                                                    class="btn btn--small">
                                                        Edit Program
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="no-data">No active programs found for your committee.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="low-grid priority-low">
            <!-- Upcoming Events -->
            <div class="dashboard-panel" id="upcoming-events" style="grid-column: 1 / -1;">
                <div class="panel-header">
                    <div>
                        <h2>Upcoming Events</h2>
                        <small style="color: var(--text-muted, #888);">Next scheduled events for your committee</small>
                    </div>
                    <a href="admin/events.php?committee=own" class="btn btn--small">View All Events</a>
                </div>
                <div class="dash-table-wrap" style="display: block;">
                    <?php
                        $today = date('Y-m-d');

                        /* get committee from officials */
                        $stmt = $conn->prepare("
                            SELECT committee
                            FROM officials
                            WHERE user_id = ?
                            AND is_active = 1
                            LIMIT 1
                        ");

                        $stmt->bind_param("i", $_SESSION['user_id']);
                        $stmt->execute();
                        $res = $stmt->get_result()->fetch_assoc();

                        $committee_name = trim($res['committee'] ?? '');
                        $stmt->close();

                        $events_sql = "
                            SELECT
                                e.id,
                                e.title,
                                e.event_date,
                                e.location,
                                e.status
                            FROM events e
                            WHERE e.committee = ?
                            AND e.event_date >= ?
                            ORDER BY e.event_date ASC
                            LIMIT 3
                        ";

                        $stmt = $conn->prepare($events_sql);
                        $stmt->bind_param("ss", $committee_name, $today);
                        $stmt->execute();
                        $events_result = $stmt->get_result();
                    ?>

                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>Event Name</th>
                                <th>Event Date</th>
                                <th>Location</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($events_result && $events_result->num_rows > 0): ?>
                                <?php while ($event = $events_result->fetch_assoc()): ?>
                                    <?php
                                        $raw_status = strtolower(trim($event['status'] ?? ''));

                                        switch ($raw_status) {
                                            case 'completed':
                                                $badge_class = 'badge-completed';
                                                break;
                                            case 'ongoing':
                                            case 'in progress':
                                                $badge_class = 'badge-ongoing';
                                                break;
                                            case 'pending':
                                                $badge_class = 'badge-pending';
                                                break;
                                            case 'cancelled':
                                                $badge_class = 'badge-cancelled';
                                                break;
                                            default:
                                                $badge_class = 'badge-default';
                                                break;
                                        }

                                        $event_date = !empty($event['event_date'])
                                            ? date('M d, Y', strtotime($event['event_date']))
                                            : '—';

                                        $location = !empty($event['location'])
                                            ? htmlspecialchars($event['location'])
                                            : '—';
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($event['title']) ?></td>
                                        <td><?= $event_date ?></td>
                                        <td><?= $location ?></td>
                                        <td>
                                            <span class="badge <?= $badge_class ?>">
                                                <?= htmlspecialchars(ucfirst($event['status'])) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="no-data">No upcoming events for your committee.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section id="quick-actions-section" style="padding: 16px 0;">
            <div class="dashboard-panel" style="grid-column: 1 / -1;">
                <div class="panel-header">
                    <div>
                        <h2>Quick Actions</h2>
                    </div>
                </div>
                <div style="display: flex; gap: 16px; flex-wrap: wrap; padding: 8px 8px;">
                    <a href="admin/project-form.php" class="quick-action-btn">
                        <i class="fa-solid fa-diagram-project"></i>
                        <span>Add New Program</span>
                    </a>
                    <a href="admin/events.php" class="quick-action-btn">
                        <i class="fa-solid fa-calendar-plus"></i>
                        <span>Add New Event</span>
                    </a>
                    <a href="admin/announcements.php" class="quick-action-btn">
                        <i class="fa-solid fa-bullhorn"></i>
                        <span>Post Announcement</span>
                    </a>
                </div>
            </div>
        </section>
    </div>

  <script src="../assets/js/resident_dashboard.js"></script>
  <script src="assets/js/secretary.js?v=20260605c"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>

</body>
</html>