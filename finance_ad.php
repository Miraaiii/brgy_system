<?php
$office_address = 'Brgy. Sta. Rosa 1, Noveleta, Cavite';
$contact_number = '+63 912 000 0000';
$barangay_hotline = 'Emergency Hotline 911';
$emergency_numbers = 'PNP 166, Fire 1555, NDRRMC 825-0000';

// Static placeholder values
$profile_percent       = 80;
$sidebar_missing_summary = 'Complete your profile';
$unread_count          = 3;
$notifications         = [
    ['is_read' => 0, 'link' => '#', 'title' => 'Budget Approved', 'message' => 'Q2 budget has been approved.', 'created_at' => '2025-05-24 09:00:00'],
    ['is_read' => 1, 'link' => '#', 'title' => 'New Payment Recorded', 'message' => 'Business permit fee collected.', 'created_at' => '2025-05-23 14:30:00'],
];
$initials              = 'MT';
$first_name            = 'Maria';
$display_name          = 'Maria Torres';
$user                  = ['email' => 'maria.torres@brgy-starosa1.gov.ph'];
$today_line            = date('l, F j, Y');
$greeting              = 'Good morning';
$pending_count         = '₱ 48,250';
$ready_count           = '₱ 12,800';
$total_count           = '64%';
$document_processing_times = [];

// Role & fiscal year
$role_label            = 'Barangay Treasurer';
$fiscal_year           = 'Fiscal Year ' . date('Y');

function e($val) { return htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8'); }
function rd_date($val) { return date('M j, Y g:i A', strtotime($val)); }

// --- STATIC DEMO DATA (replace with real DB queries later) ---
$today_collections       = 12450.00;
$month_collections       = 187320.50;
$last_month_collections  = 162800.00;
$month_expenditures      = 134200.75;
$expenditure_threshold   = 150000.00; // threshold for "red" warning
$net_balance             = $month_collections - $month_expenditures;
$annual_budget           = 2000000.00;
$ytd_spent               = 534200.75;
$budget_utilization      = ($annual_budget > 0) ? ($ytd_spent / $annual_budget) * 100 : 0;

// Month-over-month % change for collections
$mom_change = ($last_month_collections > 0)
    ? (($month_collections - $last_month_collections) / $last_month_collections) * 100
    : 0;
$mom_positive = $mom_change >= 0;

// Color logic
$exp_class    = ($month_expenditures >= $expenditure_threshold) ? 'text-danger fw-bold' : 'text-dark';
$net_class    = ($net_balance >= 0) ? 'text-success fw-bold' : 'text-danger fw-bold';
$util_color   = ($budget_utilization >= 90) ? 'danger' : (($budget_utilization >= 70) ? 'warning' : 'success'); 
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
      grid-template-columns: repeat(5, minmax(0, 1fr));
    }
  }
  </style>
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

    <nav class="sidebar-menu" aria-label="Treasurer menu">

      <div class="sidebar-group">
        <a class="sidebar-link is-active" href="admin/dashboard.php">
          <i class="fa-solid fa-house"></i><span>Dashboard</span>
        </a>
      </div>

      <div class="sidebar-group">
        <span class="sidebar-section-label">COLLECTIONS</span>
        <a class="sidebar-link" href="admin/finance.php?tab=collections">
          <i class="fa-solid fa-money-bill-transfer"></i><span>All Collections</span>
        </a>
        <a class="sidebar-link" href="admin/finance.php?tab=record">
          <i class="fa-solid fa-cash-register"></i><span>Record Payment</span>
        </a>
        <a class="sidebar-link" href="admin/finance.php?tab=receipts">
          <i class="fa-solid fa-file-invoice-dollar"></i><span>Official Receipts</span>
        </a>
      </div>

      <div class="sidebar-group">
        <span class="sidebar-section-label">EXPENDITURES</span>
        <a class="sidebar-link" href="admin/finance.php?tab=expenditures">
          <i class="fa-solid fa-money-bill-wave"></i><span>All Expenditures</span>
        </a>
        <a class="sidebar-link" href="admin/finance.php?tab=add-exp">
          <i class="fa-solid fa-circle-plus"></i><span>Add Expenditure</span>
        </a>
      </div>

      <div class="sidebar-group">
        <span class="sidebar-section-label">BUDGET</span>
        <a class="sidebar-link" href="admin/finance.php?tab=budget">
          <i class="fa-solid fa-chart-pie"></i><span>Budget Management</span>
        </a>
      </div>

      <div class="sidebar-group">
        <span class="sidebar-section-label">REPORTS</span>
        <a class="sidebar-link" href="admin/finance.php?tab=reports">
          <i class="fa-solid fa-file-invoice-dollar"></i><span>Financial Reports</span>
        </a>
      </div>

      <div class="sidebar-group">
        <span class="sidebar-section-label">RECORDS</span>
        <a class="sidebar-link" href="admin/issued.php">
          <i class="fa-solid fa-file-lines"></i><span>Doc Issuance Log</span>
        </a>
        <a class="sidebar-link" href="admin/residents.php">
          <i class="fa-solid fa-users"></i><span>Resident List</span>
        </a>
      </div>

      <div class="sidebar-group">
        <span class="sidebar-section-label">ACCOUNT</span>
        <a class="sidebar-link" href="admin/profile.php">
          <i class="fa-solid fa-user"></i><span>My Profile</span>
        </a>
        <a class="sidebar-link sidebar-link--danger" href="../logout.php">
          <i class="fa-solid fa-right-from-bracket"></i><span>Logout</span>
        </a>
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
          <div class="welcome-meta">
            <span class="role-badge">
              <i class="fa-solid fa-id-badge"></i> <?= e($role_label) ?>
            </span>
            <span class="fiscal-badge">
              <i class="fa-solid fa-calendar-check"></i> <?= e($fiscal_year) ?>
            </span>
          </div>
        </div>
      </section>

      <section class="stat-grid priority-high" aria-label="Financial summary">
        <!-- 1. Today's Collections -->
        <div class="status-card status-card--success">
          <span class="status-card__icon"><i class="fa-solid fa-calendar-day"></i></span>
          <span class="finance-card__body">
            <strong class="text-success">₱<?= number_format($today_collections, 2) ?></strong>
            <small>Today's Collections</small>
          </span>
        </div>

        <!-- 2. This Month Collections -->
        <div class="status-card status-card--success">
          <span class="status-card__icon"><i class="fa-solid fa-sack-dollar"></i></span>
          <span class="finance-card__body">
            <strong class="text-success">₱<?= number_format($month_collections, 2) ?></strong>
            <small>
              This Month Collections
              <span class="badge <?= $mom_positive ? 'bg-success' : 'bg-danger' ?> ms-1">
                <i class="fa-solid fa-arrow-<?= $mom_positive ? 'up' : 'down' ?>"></i>
                <?= number_format(abs($mom_change), 1) ?>% vs last month
              </span>
            </small>
          </span>
        </div>

        <!-- 3. This Month Expenditures -->
        <div class="status-card status-card--warning">
          <span class="status-card__icon"><i class="fa-solid fa-money-bill-wave"></i></span>
          <span class="finance-card__body">
            <strong class="<?= $exp_class ?>">₱<?= number_format($month_expenditures, 2) ?></strong>
            <small>This Month Expenditures</small>
          </span>
        </div>

        <!-- 4. Net Balance (Month) -->
        <div class="status-card <?= $net_balance >= 0 ? 'status-card--success' : 'status-card--danger' ?>">
          <span class="status-card__icon"><i class="fa-solid fa-scale-balanced"></i></span>
          <span class="finance-card__body">
            <strong class="<?= $net_class ?>"><?= $net_balance >= 0 ? '+' : '' ?>₱<?= number_format($net_balance, 2) ?></strong>
            <small>Net Balance (This Month)</small>
          </span>
        </div>

        <!-- 5. Budget Utilization -->
        <div class="status-card status-card--info">
          <span class="status-card__icon"><i class="fa-solid fa-chart-pie"></i></span>
          <span class="finance-card__body">
            <strong><?= number_format($budget_utilization, 1) ?>%</strong>
            <small>
              Budget Utilization (YTD)
              <div class="progress mt-1" style="height:6px;">
                <div
                  class="progress-bar bg-<?= $util_color ?>"
                  role="progressbar"
                  style="width: <?= min($budget_utilization, 100) ?>%"
                  aria-valuenow="<?= $budget_utilization ?>"
                  aria-valuemin="0"
                  aria-valuemax="100">
                </div>
              </div>
            </small>
          </span>
        </div>

      </section>

      <section class="panel-grid-full priority-medium">
        <div class="dashboard-panel" id="collections-chart">
          <div class="panel-header">
            <div>
              <h2>Monthly Collections</h2>
              <small style="color: var(--text-muted, #888);">Last 6 months — collections vs expenditures</small>
            </div>
            <a href="admin/finance.php?tab=reports" class="view-all-link">
              View full report <i class="fa-solid fa-arrow-right"></i>
            </a>
          </div>
          <div class="announcement-list">
            <canvas id="revenueChart"></canvas>
          </div>
        </div>
      </section>

      <section class="high-grid priority-high">

        <!-- Recent Collections Table -->
        <div class="dashboard-panel quick-panel" id="recent-collections" style="grid-column: 1 / -1;">
          <div class="panel-header">
            <div>
              <h2>Recent Collections</h2>
              <small style="color: var(--text-muted, #888);">Last 8 transactions</small>
            </div>
            <a class="text-link" href="admin/finance.php?tab=collections">View all collections</a>
          </div>
          <div class="quick-actions">
            <table style="width:100%; table-layout:fixed;">
              <thead>
                <tr>
                  <th>No.</th>
                  <th>Source Type</th>
                  <th>Amount</th>
                  <th>Resident Name</th>
                  <th>Date</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td style="color:var(--text-muted);font-size:11px;">001</td>
                  <td><span class="badge collection">Business Permit Fee</span></td>
                  <td class="amount">₱ 2,500.00</td>
                  <td>Juan dela Cruz</td>
                  <td style="color:var(--text-muted);font-size:11px;">May 24, 2025</td>
                  <td><a href="admin/finance.php?tab=collections&id=1" class="btn-view"><i class="fa-solid fa-eye"></i> View</a></td>
                </tr>
                <tr>
                  <td style="color:var(--text-muted);font-size:11px;">002</td>
                  <td><span class="badge collection">Document Request Fee</span></td>
                  <td class="amount">₱ 150.00</td>
                  <td>Maria Santos</td>
                  <td style="color:var(--text-muted);font-size:11px;">May 23, 2025</td>
                  <td><a href="admin/finance.php?tab=collections&id=2" class="btn-view"><i class="fa-solid fa-eye"></i> View</a></td>
                </tr>
                <tr>
                  <td style="color:var(--text-muted);font-size:11px;">003</td>
                  <td><span class="badge collection">Certificate Fee</span></td>
                  <td class="amount">₱ 300.00</td>
                  <td>Pedro Reyes</td>
                  <td style="color:var(--text-muted);font-size:11px;">May 21, 2025</td>
                  <td><a href="admin/finance.php?tab=collections&id=3" class="btn-view"><i class="fa-solid fa-eye"></i> View</a></td>
                </tr>
                <tr>
                  <td style="color:var(--text-muted);font-size:11px;">004</td>
                  <td><span class="badge collection">Barangay Clearance</span></td>
                  <td class="amount">₱ 200.00</td>
                  <td>Ana Gomez</td>
                  <td style="color:var(--text-muted);font-size:11px;">May 20, 2025</td>
                  <td><a href="admin/finance.php?tab=collections&id=4" class="btn-view"><i class="fa-solid fa-eye"></i> View</a></td>
                </tr>
                <tr>
                  <td style="color:var(--text-muted);font-size:11px;">005</td>
                  <td><span class="badge collection">Business Permit Fee</span></td>
                  <td class="amount">₱ 2,500.00</td>
                  <td>Carlos Mendoza</td>
                  <td style="color:var(--text-muted);font-size:11px;">May 19, 2025</td>
                  <td><a href="admin/finance.php?tab=collections&id=5" class="btn-view"><i class="fa-solid fa-eye"></i> View</a></td>
                </tr>
                <tr>
                  <td style="color:var(--text-muted);font-size:11px;">006</td>
                  <td><span class="badge collection">Indigency Certificate</span></td>
                  <td class="amount">₱ 100.00</td>
                  <td>Rosa Villanueva</td>
                  <td style="color:var(--text-muted);font-size:11px;">May 18, 2025</td>
                  <td><a href="admin/finance.php?tab=collections&id=6" class="btn-view"><i class="fa-solid fa-eye"></i> View</a></td>
                </tr>
                <tr>
                  <td style="color:var(--text-muted);font-size:11px;">007</td>
                  <td><span class="badge collection">Cedula</span></td>
                  <td class="amount">₱ 75.00</td>
                  <td>Jose Bautista</td>
                  <td style="color:var(--text-muted);font-size:11px;">May 17, 2025</td>
                  <td><a href="admin/finance.php?tab=collections&id=7" class="btn-view"><i class="fa-solid fa-eye"></i> View</a></td>
                </tr>
                <tr>
                  <td style="color:var(--text-muted);font-size:11px;">008</td>
                  <td><span class="badge collection">Document Request Fee</span></td>
                  <td class="amount">₱ 150.00</td>
                  <td>Luisa Fernandez</td>
                  <td style="color:var(--text-muted);font-size:11px;">May 16, 2025</td>
                  <td><a href="admin/finance.php?tab=collections&id=8" class="btn-view"><i class="fa-solid fa-eye"></i> View</a></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

      </section>

      <section class="low-grid priority-low">
        <div class="dashboard-panel" id="pending-expenditure-approvals" style="grid-column: 1 / -1;">
          <div class="panel-header">
            <div>
              <h2>Pending Expenditure Approvals</h2>
              <small style="color: var(--text-muted, #888);">Expenditures awaiting Captain approval</small>
            </div>
          </div>

          <div class="alert-note" style="margin: 0 0 16px 0; padding: 10px 16px; background: rgba(234,179,8,0.1); border-left: 4px solid #eab308; border-radius: 6px; color: #eab308; font-size: 13px;">
            <i class="fa-solid fa-circle-info"></i>
            Expenditures over ₱5,000 require Captain approval before disbursement.
          </div>

          <div style="display:block;">
            <table style="width:100%; table-layout:fixed;">
              <thead>
                <tr>
                  <th>Category</th>
                  <th>Amount</th>
                  <th>Description</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($pending_expenditures)): ?>
                  <?php foreach ($pending_expenditures as $expenditure): ?>
                    <tr>
                      <td><?= e($expenditure['category']) ?></td>
                      <td class="amount">₱ <?= e(number_format((float)$expenditure['amount'], 2)) ?></td>
                      <td><?= e($expenditure['description']) ?></td>
                      <td><span class="badge pending">Pending</span></td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="4" style="text-align:center; color:var(--text-muted, #888); padding: 24px 0;">
                      <i class="fa-solid fa-check-circle" style="margin-right:6px;"></i>No pending expenditures for approval.
                    </td>
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
          <div style="display: flex; gap: 16px; flex-wrap: wrap; padding: 8px 0;">
            <a href="admin/finance.php?tab=record" class="quick-action-btn">
              <i class="fa-solid fa-money-bill-wave"></i>
              <span>Record Collection</span>
            </a>
            <a href="admin/finance.php?tab=add-exp" class="quick-action-btn">
              <i class="fa-solid fa-file-invoice-dollar"></i>
              <span>Add Expenditure</span>
            </a>
            <a href="admin/finance.php?tab=reports" class="quick-action-btn">
              <i class="fa-solid fa-chart-bar"></i>
              <span>View Reports</span>
            </a>
            <a href="admin/finance.php?tab=budget" class="quick-action-btn">
              <i class="fa-solid fa-wallet"></i>
              <span>Budget Overview</span>
            </a>
          </div>
        </div>
      </section>
    </main>
  </div>

  <script src="assets/js/resident_dashboard.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>

  <script>
    (function () {
      // --- STATIC DATA (replace with PHP-injected values later) ---
      const labels        = ['January', 'February', 'March', 'April', 'May', 'June'];
      const collections   = [82000, 95000, 110000, 143000, 162800, 187320];
      const expenditures  = [70000, 88000, 105000, 120000, 130000, 134200];

      const ctx = document.getElementById('revenueChart').getContext('2d');

      new Chart(ctx, {
        data: {
          labels,
          datasets: [
            {
              // Bar — Collections
              type: 'bar',
              label: 'Collections',
              data: collections,
              backgroundColor: 'rgba(234, 179, 8, 0.25)',
              borderColor:     'rgba(234, 179, 8, 0.9)',
              borderWidth: 2,
              borderRadius: 6,
              order: 2,
            },
            {
              // Line — Expenditures
              type: 'line',
              label: 'Expenditures',
              data: expenditures,
              borderColor:     'rgba(239, 68, 68, 0.9)',
              backgroundColor: 'rgba(239, 68, 68, 0.08)',
              borderWidth: 2,
              pointBackgroundColor: 'rgba(239, 68, 68, 1)',
              pointRadius: 4,
              tension: 0.4,
              fill: true,
              order: 1,
            },
            {
              // Line — Collections trend
              type: 'line',
              label: 'Collections Trend',
              data: collections,
              borderColor:     'rgba(234, 179, 8, 1)',
              backgroundColor: 'transparent',
              borderWidth: 2,
              borderDash: [5, 4],
              pointBackgroundColor: 'rgba(234, 179, 8, 1)',
              pointRadius: 4,
              tension: 0.4,
              fill: false,
              order: 0,
            }
          ]
        },
        options: {
          responsive: true,
          interaction: { mode: 'index', intersect: false },
          plugins: {
            legend: {
              labels: {
                color: '#ccc',
                font: { size: 12 },
                boxWidth: 14,
              }
            },
            tooltip: {
              callbacks: {
                label: ctx => ' ₱' + ctx.parsed.y.toLocaleString()
              }
            }
          },
          scales: {
            x: {
              ticks: { color: '#aaa' },
              grid:  { color: 'rgba(255,255,255,0.05)' }
            },
            y: {
              ticks: {
                color: '#aaa',
                callback: val => '₱' + (val / 1000) + 'K'
              },
              grid: { color: 'rgba(255,255,255,0.07)' }
            }
          }
        }
      });
    })();

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
