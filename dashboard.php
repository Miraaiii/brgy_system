<?php
  include 'config/connection.php';
  include 'includes/auth_check.php';

  requireRole(['captain']);

  if (!isset($_SESSION['user_id'])) {
      header("Location: login.php");
      exit();
  }

  $user_id = $_SESSION['user_id'];
  $stmt = $conn->prepare("SELECT username, email, role FROM users WHERE id = ? LIMIT 1");
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $stmt->bind_result($fullname, $email, $role);
  $stmt->fetch();
  $stmt->close();

  // Fallback in case query fails
  if (empty($fullname)) {
      $fullname = "Juan Reyes";
      $role = "Captain";
      $email = "captain@bgystaros1.gov.ph";
  }

  // Calculate initials
  $initials = "";
  $names = explode(" ", $fullname);
  foreach ($names as $n) {
      if (!empty($n)) {
          $initials .= strtoupper($n[0]);
      }
  }
  $initials = substr($initials, 0, 2);
  if (empty($initials)) {
      $initials = "JR";
  }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Barangay Sta. Rosa 1 — Management Information System</title>
  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet"/>
  <!-- DataTables -->
  <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet"/>
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet"/>
  <!-- Custom CSS -->
  <link rel="stylesheet" href="style.css"/>
</head>
<body>

<!-- ===== SIDEBAR ===== -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <div class="sidebar-logo">
      <div class="logo-icon"><i class="fa-solid fa-landmark"></i></div>
      <div class="logo-text">
        <span class="logo-title">Bgy. Sta. Rosa 1</span>
        <span class="logo-sub">MIS Portal</span>
      </div>
    </div>
    <button class="sidebar-close-btn d-lg-none" id="sidebarCloseBtn">
      <i class="fa-solid fa-xmark"></i>
    </button>
  </div>

  <div class="sidebar-section-label">MAIN MENU</div>
  <nav class="sidebar-nav">
    <a href="#" class="nav-item active" data-section="dashboard">
      <span class="nav-icon"><i class="fa-solid fa-gauge-high"></i></span>
      <span class="nav-label">Dashboard</span>
      <span class="nav-badge">Live</span>
    </a>
    <a href="#" class="nav-item" data-section="residents">
      <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
      <span class="nav-label">Resident Records</span>
    </a>
    <a href="#" class="nav-item" data-section="documents">
      <span class="nav-icon"><i class="fa-solid fa-file-lines"></i></span>
      <span class="nav-label">Document Management</span>
      <span class="nav-badge pending">7</span>
    </a>
    <a href="#" class="nav-item" data-section="blotter">
      <span class="nav-icon"><i class="fa-solid fa-scale-balanced"></i></span>
      <span class="nav-label">Blotter Records</span>
    </a>

    <div class="sidebar-section-label">ADMINISTRATION</div>
    <a href="#" class="nav-item" data-section="financial">
      <span class="nav-icon"><i class="fa-solid fa-peso-sign"></i></span>
      <span class="nav-label">Financial Management</span>
    </a>
    <a href="#" class="nav-item" data-section="projects">
      <span class="nav-icon"><i class="fa-solid fa-diagram-project"></i></span>
      <span class="nav-label">Projects & Programs</span>
    </a>

    <div class="sidebar-section-label">SYSTEM</div>
    <a href="#" class="nav-item" data-section="settings">
      <span class="nav-icon"><i class="fa-solid fa-gear"></i></span>
      <span class="nav-label">System Settings</span>
    </a>
    <a href="#" class="nav-item nav-item-danger" id="logoutBtn">
      <span class="nav-icon"><i class="fa-solid fa-right-from-bracket"></i></span>
      <span class="nav-label">Logout</span>
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="user-avatar"><?= htmlspecialchars($initials) ?></div>
      <div class="user-info">
        <span class="user-name"><?= htmlspecialchars($fullname) ?></span>
        <span class="user-role">Barangay <?= htmlspecialchars($role) ?></span>
      </div>
      <i class="fa-solid fa-circle user-status-dot"></i>
    </div>
  </div>
</aside>

<!-- ===== SIDEBAR OVERLAY ===== -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ===== MAIN WRAPPER ===== -->
<div class="main-wrapper" id="mainWrapper">

  <!-- TOP NAVBAR -->
  <header class="top-navbar" id="topNavbar">
    <div class="navbar-left">
      <button class="hamburger-btn" id="hamburgerBtn">
        <i class="fa-solid fa-bars"></i>
      </button>
      <div class="navbar-brand-mobile d-lg-none">
        <span>Bgy. Sta. Rosa 1</span>
      </div>
      <div class="search-wrapper d-none d-md-flex">
        <i class="fa-solid fa-magnifying-glass search-icon"></i>
        <input type="text" class="search-input" id="globalSearchInput" placeholder="Search residents, documents, cases..."/>
        <kbd class="search-kbd">⌘K</kbd>
      </div>
    </div>
    <div class="navbar-right">
      <div class="navbar-datetime" id="navDatetime"></div>
      <button class="nav-action-btn" id="notifBtn" title="Notifications">
        <i class="fa-solid fa-bell"></i>
        <span class="badge-dot"></span>
      </button>
      <div class="nav-divider"></div>
      <div class="admin-profile" id="adminProfile">
        <div class="admin-avatar"><?= htmlspecialchars($initials) ?></div>
        <div class="admin-info d-none d-md-block">
          <span class="admin-name"><?= htmlspecialchars($fullname) ?></span>
          <span class="admin-role"><?= htmlspecialchars($role) ?></span>
        </div>
        <i class="fa-solid fa-chevron-down admin-chevron d-none d-md-block"></i>
        <!-- Profile Dropdown -->
        <div class="profile-dropdown" id="profileDropdown">
          <div class="dropdown-header">
            <div class="dh-avatar"><?= htmlspecialchars($initials) ?></div>
            <div>
              <div class="dh-name"><?= htmlspecialchars($fullname) ?></div>
              <div class="dh-email"><?= htmlspecialchars($email) ?></div>
            </div>
          </div>
          <div class="dropdown-divider"></div>
          <a href="#" class="dropdown-item-custom"><i class="fa-solid fa-user-pen"></i> Edit Profile</a>
          <a href="#" class="dropdown-item-custom"><i class="fa-solid fa-lock"></i> Change Password</a>
          <a href="#" class="dropdown-item-custom"><i class="fa-solid fa-bell"></i> Notification Settings</a>
          <div class="dropdown-divider"></div>
          <a href="includes/logout.php" class="dropdown-item-custom text-danger"><i class="fa-solid fa-right-from-bracket"></i> Sign Out</a>
        </div>
      </div>
    </div>
  </header>

  <!-- ===== MAIN CONTENT ===== -->
  <main class="main-content">

    <!-- ==================== DASHBOARD SECTION ==================== -->
    <section class="content-section active" id="section-dashboard">
      <div class="page-header">
        <div>
          <h1 class="page-title">Dashboard Overview</h1>
          <p class="page-subtitle">Welcome back, Hon. <?= htmlspecialchars($fullname) ?>. Here's what's happening today.</p>
        </div>
        <div class="page-actions">
          <button class="btn btn-outline-secondary btn-sm" id="btnRefreshStats"><i class="fa-solid fa-rotate me-1"></i>Refresh</button>
          <button class="btn btn-primary btn-sm" id="btnExportStats"><i class="fa-solid fa-download me-1"></i>Export Report</button>
        </div>
      </div>

      <!-- Stat Cards -->
      <div class="row g-4 mb-4">
        <div class="col-6 col-xl-3">
          <div class="stat-card stat-blue">
            <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
            <div class="stat-body">
              <div class="stat-value" id="cardTotalResidents">0</div>
              <div class="stat-label">Total Residents</div>
              <div class="stat-trend up"><i class="fa-solid fa-arrow-trend-up"></i> +48 this month</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="stat-card stat-amber">
            <div class="stat-icon"><i class="fa-solid fa-clock"></i></div>
            <div class="stat-body">
              <div class="stat-value" id="cardPendingRequests">0</div>
              <div class="stat-label">Pending Requests</div>
              <div class="stat-trend warn"><i class="fa-solid fa-triangle-exclamation"></i> 7 urgent</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="stat-card stat-red">
            <div class="stat-icon"><i class="fa-solid fa-gavel"></i></div>
            <div class="stat-body">
              <div class="stat-value" id="cardActiveBlotters">0</div>
              <div class="stat-label">Active Blotter Cases</div>
              <div class="stat-trend down"><i class="fa-solid fa-arrow-trend-down"></i> -3 from last week</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="stat-card stat-green">
            <div class="stat-icon"><i class="fa-solid fa-peso-sign"></i></div>
            <div class="stat-body">
              <div class="stat-value" id="cardMonthlyRevenue">₱0</div>
              <div class="stat-label">Monthly Revenue</div>
              <div class="stat-trend up"><i class="fa-solid fa-arrow-trend-up"></i> +12.4% vs last month</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Charts Row -->
      <div class="row g-4 mb-4">
        <div class="col-lg-8">
          <div class="chart-card">
            <div class="chart-header">
              <div>
                <h6 class="chart-title">Population Growth</h6>
                <p class="chart-sub">Monthly resident count — 2024</p>
              </div>
              <div class="chart-legend">
                <span class="legend-dot blue"></span><span>Registered</span>
                <span class="legend-dot green ms-3"></span><span>New</span>
              </div>
            </div>
            <div style="position: relative; height: 260px; width: 100%;">
              <canvas id="populationChart"></canvas>
            </div>
          </div>
        </div>
        <div class="col-lg-4">
          <div class="chart-card">
            <div class="chart-header">
              <div>
                <h6 class="chart-title">Document Requests</h6>
                <p class="chart-sub">By type — this month</p>
              </div>
            </div>
            <div style="position: relative; height: 260px; width: 100%;">
              <canvas id="docTypeChart"></canvas>
            </div>
          </div>
        </div>
      </div>

      <!-- Bottom Row -->
      <div class="row g-4">
        <div class="col-lg-4">
          <div class="chart-card h-100">
            <div class="chart-header">
              <h6 class="chart-title">Financial Summary</h6>
              <p class="chart-sub">Q1–Q4 collections</p>
            </div>
            <div style="position: relative; height: 200px; width: 100%;">
              <canvas id="financialChart"></canvas>
            </div>
          </div>
        </div>
        <div class="col-lg-4">
          <div class="activity-card h-100">
            <div class="chart-header">
              <h6 class="chart-title">Recent Activity</h6>
              <button class="btn btn-xs btn-outline-primary" id="btnViewAllActivity">View all</button>
            </div>
            <div class="activity-list" id="activityList">
              <!-- populated by JS -->
            </div>
          </div>
        </div>
        <div class="col-lg-4">
          <div class="chart-card h-100">
            <div class="chart-header">
              <h6 class="chart-title">Alerts Queue</h6>
              <span class="badge bg-danger" id="alertsQueueBadge">3 urgent</span>
            </div>
            <div class="alerts-list" id="alertsList">
              <!-- populated by JS -->
            </div>
            <div class="quick-stats mt-3">
              <div class="qs-row">
                <span>Voter Registration Rate</span>
                <span class="qs-val">72%</span>
              </div>
              <div class="progress mb-2" style="height:6px"><div class="progress-bar bg-primary" style="width:72%"></div></div>
              <div class="qs-row">
                <span>Senior Citizens</span>
                <span class="qs-val">18%</span>
              </div>
              <div class="progress mb-2" style="height:6px"><div class="progress-bar bg-success" style="width:18%"></div></div>
              <div class="qs-row">
                <span>Budget Utilization</span>
                <span class="qs-val">64%</span>
              </div>
              <div class="progress" style="height:6px"><div class="progress-bar bg-warning" style="width:64%"></div></div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ==================== RESIDENTS SECTION ==================== -->
    <section class="content-section" id="section-residents">
      <div class="page-header">
        <div>
          <h1 class="page-title">Resident Records</h1>
          <p class="page-subtitle">Manage and monitor all registered residents of Barangay Sta. Rosa 1.</p>
        </div>
        <div class="page-actions">
          <button class="btn btn-outline-secondary btn-sm" id="btnExportExcel"><i class="fa-solid fa-file-excel me-1"></i>Export Excel</button>
          <button class="btn btn-outline-danger btn-sm" id="btnExportPDF"><i class="fa-solid fa-file-pdf me-1"></i>Export PDF</button>
          <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addResidentModal">
            <i class="fa-solid fa-user-plus me-1"></i>Add Resident
          </button>
        </div>
      </div>

      <!-- Filter Bar -->
      <div class="filter-bar mb-4">
        <div class="row g-2 align-items-center">
          <div class="col-md-3">
            <select class="form-select form-select-sm" id="filterPurok">
              <option value="">All Puroks</option>
              <option>Purok 1 — Sampaloc</option>
              <option>Purok 2 — Narra</option>
              <option>Purok 3 — Makopa</option>
              <option>Purok 4 — Santol</option>
              <option>Purok 5 — Bayabas</option>
            </select>
          </div>
          <div class="col-md-2">
            <select class="form-select form-select-sm" id="filterGender">
              <option value="">All Genders</option>
              <option>Male</option>
              <option>Female</option>
            </select>
          </div>
          <div class="col-md-2">
            <select class="form-select form-select-sm" id="filterVoter">
              <option value="">Voter Status</option>
              <option>Registered Voter</option>
              <option>Non-Voter</option>
            </select>
          </div>
          <div class="col-md-2">
            <select class="form-select form-select-sm" id="filterCivil">
              <option value="">Civil Status</option>
              <option>Single</option>
              <option>Married</option>
              <option>Widowed</option>
            </select>
          </div>
          <div class="col-md-3">
            <button class="btn btn-sm btn-secondary w-100" id="btnResetFilters"><i class="fa-solid fa-filter-circle-xmark me-1"></i>Reset Filters</button>
          </div>
        </div>
      </div>

      <div class="table-card">
        <table id="residentsTable" class="table table-hover align-middle">
          <thead>
            <tr>
              <th>Resident ID</th>
              <th>Full Name</th>
              <th>Address / Purok</th>
              <th>Age</th>
              <th>Civil Status</th>
              <th>Voter Status</th>
              <th>Contact</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="residentsTableBody">
            <!-- populated by JS -->
          </tbody>
        </table>
      </div>
    </section>

    <!-- ==================== DOCUMENTS SECTION ==================== -->
    <section class="content-section" id="section-documents">
      <div class="page-header">
        <div>
          <h1 class="page-title">Document Management</h1>
          <p class="page-subtitle">Process and issue official barangay documents.</p>
        </div>
        <div class="page-actions">
          <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newDocRequestModal">
            <i class="fa-solid fa-plus me-1"></i>New Request
          </button>
        </div>
      </div>

      <!-- Status Tabs -->
      <ul class="nav nav-tabs doc-tabs mb-4" id="docTabs">
        <li class="nav-item">
          <a class="nav-link active" data-bs-toggle="tab" href="#tabPending" id="tabPendingLink">
            Pending <span class="badge bg-warning text-dark ms-1" id="pendingDocsBadge">7</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" data-bs-toggle="tab" href="#tabApproved" id="tabApprovedLink">
            Approved <span class="badge bg-success ms-1" id="approvedDocsBadge">24</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" data-bs-toggle="tab" href="#tabRejected" id="tabRejectedLink">
            Rejected <span class="badge bg-danger ms-1" id="rejectedDocsBadge">3</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" data-bs-toggle="tab" href="#tabHistory" id="tabHistoryLink">
            Issuance History
          </a>
        </li>
      </ul>

      <div class="tab-content">
        <div class="tab-pane fade show active" id="tabPending">
          <div class="table-card">
            <table class="table table-hover align-middle" id="pendingDocsTable">
              <thead>
                <tr>
                  <th>Request #</th>
                  <th>Resident Name</th>
                  <th>Document Type</th>
                  <th>Purpose</th>
                  <th>Date Filed</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="pendingDocsBody">
                <!-- populated by JS -->
              </tbody>
            </table>
          </div>
        </div>
        <div class="tab-pane fade" id="tabApproved">
          <div class="table-card">
            <table class="table table-hover align-middle" id="approvedDocsTable">
              <thead>
                <tr>
                  <th>Request #</th><th>Resident Name</th><th>Document Type</th><th>Approved By</th><th>Date Released</th><th>Actions</th>
                </tr>
              </thead>
              <tbody id="approvedDocsBody">
                <!-- populated by JS -->
              </tbody>
            </table>
          </div>
        </div>
        <div class="tab-pane fade" id="tabRejected">
          <div class="table-card">
            <table class="table table-hover align-middle" id="rejectedDocsTable">
              <thead>
                <tr><th>Request #</th><th>Resident Name</th><th>Document Type</th><th>Reason</th><th>Date</th></tr>
              </thead>
              <tbody id="rejectedDocsBody">
                <!-- populated by JS -->
              </tbody>
            </table>
          </div>
        </div>
        <div class="tab-pane fade" id="tabHistory">
          <div class="timeline-log" id="docHistoryLog">
            <!-- populated by JS -->
          </div>
        </div>
      </div>
    </section>

    <!-- ==================== BLOTTER SECTION ==================== -->
    <section class="content-section" id="section-blotter">
      <div class="page-header">
        <div>
          <h1 class="page-title">Blotter & Incident Records</h1>
          <p class="page-subtitle">Track and manage barangay blotter cases and hearings.</p>
        </div>
        <div class="page-actions">
          <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newBlotterModal">
            <i class="fa-solid fa-plus me-1"></i>New Blotter Entry
          </button>
          <button class="btn btn-outline-secondary btn-sm" id="btnPrintBlotterReport"><i class="fa-solid fa-print me-1"></i>Print Report</button>
        </div>
      </div>

      <!-- Blotter Stats -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
          <div class="mini-stat-card border-warning">
            <div class="mini-val text-warning" id="blotterPendingCount">0</div>
            <div class="mini-label">Pending</div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="mini-stat-card border-primary">
            <div class="mini-val text-primary" id="blotterOngoingCount">0</div>
            <div class="mini-label">Ongoing</div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="mini-stat-card border-success">
            <div class="mini-val text-success" id="blotterResolvedCount">0</div>
            <div class="mini-label">Resolved</div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="mini-stat-card border-secondary">
            <div class="mini-val text-secondary" id="blotterDismissedCount">0</div>
            <div class="mini-label">Dismissed</div>
          </div>
        </div>
      </div>

      <div class="table-card">
        <table id="blotterTable" class="table table-hover align-middle">
          <thead>
            <tr>
              <th>Case #</th>
              <th>Complainant</th>
              <th>Respondent</th>
              <th>Incident Type</th>
              <th>Date Filed</th>
              <th>Hearing Date</th>
              <th>Priority</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="blotterTableBody">
            <!-- populated by JS -->
          </tbody>
        </table>
      </div>
    </section>

    <!-- ==================== FINANCIAL SECTION ==================== -->
    <section class="content-section" id="section-financial">
      <div class="page-header">
        <div>
          <h1 class="page-title">Financial Management</h1>
          <p class="page-subtitle">Monitor barangay collections, expenditures, and budget allocations.</p>
        </div>
        <div class="page-actions">
          <button class="btn btn-outline-secondary btn-sm" id="btnExportFinance"><i class="fa-solid fa-file-excel me-1"></i>Export</button>
          <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTransactionModal">
            <i class="fa-solid fa-plus me-1"></i>Add Transaction
          </button>
        </div>
      </div>

      <!-- Revenue Cards -->
      <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
          <div class="fin-card fin-green">
            <div class="fin-icon"><i class="fa-solid fa-arrow-down"></i></div>
            <div class="fin-val" id="finTotalIncome">₱284,500</div>
            <div class="fin-label">Total Income (YTD)</div>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3">
          <div class="fin-card fin-red">
            <div class="fin-icon"><i class="fa-solid fa-arrow-up"></i></div>
            <div class="fin-val" id="finTotalExpenses">₱182,350</div>
            <div class="fin-label">Total Expenses (YTD)</div>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3">
          <div class="fin-card fin-blue">
            <div class="fin-icon"><i class="fa-solid fa-wallet"></i></div>
            <div class="fin-val" id="finNetBalance">₱102,150</div>
            <div class="fin-label">Net Balance</div>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3">
          <div class="fin-card fin-amber">
            <div class="fin-icon"><i class="fa-solid fa-piggy-bank"></i></div>
            <div class="fin-val">₱450,000</div>
            <div class="fin-label">Annual Budget</div>
          </div>
        </div>
      </div>

      <!-- Charts -->
      <div class="row g-4 mb-4">
        <div class="col-lg-5">
          <div class="chart-card">
            <div class="chart-header">
              <h6 class="chart-title">Budget Allocation</h6>
              <p class="chart-sub">By category</p>
            </div>
            <div style="position: relative; height: 220px; width: 100%;">
              <canvas id="budgetPieChart"></canvas>
            </div>
          </div>
        </div>
        <div class="col-lg-7">
          <div class="chart-card">
            <div class="chart-header">
              <h6 class="chart-title">Monthly Income vs Expenses</h6>
              <p class="chart-sub">January – December 2024</p>
            </div>
            <div style="position: relative; height: 220px; width: 100%;">
              <canvas id="incomeExpChart"></canvas>
            </div>
          </div>
        </div>
      </div>

      <!-- Transactions Table -->
      <div class="table-card">
        <table id="financeTable" class="table table-hover align-middle">
          <thead>
            <tr>
              <th>OR #</th><th>Date</th><th>Description</th><th>Category</th><th>Type</th><th>Amount</th><th>Balance</th>
            </tr>
          </thead>
          <tbody id="financeTableBody">
            <!-- populated by JS -->
          </tbody>
        </table>
      </div>
    </section>

    <!-- ==================== PROJECTS SECTION ==================== -->
    <section class="content-section" id="section-projects">
      <div class="page-header">
        <div>
          <h1 class="page-title">Projects & Programs</h1>
          <p class="page-subtitle">Monitor barangay development projects and community programs.</p>
        </div>
        <div class="page-actions">
          <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addProjectModal">
            <i class="fa-solid fa-plus me-1"></i>Add Project
          </button>
        </div>
      </div>

      <!-- Committee Filter -->
      <div class="committee-filter mb-4" id="projectFilters">
        <button class="btn btn-sm btn-primary committee-btn active" data-committee="all">All</button>
        <button class="btn btn-sm btn-outline-secondary committee-btn" data-committee="Health">Health</button>
        <button class="btn btn-sm btn-outline-secondary committee-btn" data-committee="Education">Education</button>
        <button class="btn btn-sm btn-outline-secondary committee-btn" data-committee="Peace & Order">Peace & Order</button>
        <button class="btn btn-sm btn-outline-secondary committee-btn" data-committee="Environment">Environment</button>
        <button class="btn btn-sm btn-outline-secondary committee-btn" data-committee="Youth & Sports">Youth & Sports</button>
      </div>

      <div class="row g-4" id="projectsGrid">
        <!-- populated by JS -->
      </div>
    </section>

    <!-- ==================== SETTINGS SECTION ==================== -->
    <section class="content-section" id="section-settings">
      <div class="page-header">
        <div>
          <h1 class="page-title">System Settings</h1>
          <p class="page-subtitle">Manage users, roles, barangay profile, and system configurations.</p>
        </div>
        <div class="page-actions">
          <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addAdminModal">
            <i class="fa-solid fa-user-plus me-1"></i>Add Admin User
          </button>
        </div>
      </div>

      <div class="row g-4">
        <!-- Barangay Profile -->
        <div class="col-lg-6">
          <div class="settings-card">
            <div class="settings-card-header">
              <i class="fa-solid fa-building-columns"></i> Barangay Profile
            </div>
            <div class="settings-card-body">
              <div class="mb-3">
                <label class="form-label">Barangay Name</label>
                <input type="text" class="form-control form-control-sm" id="setBgyName" value="Barangay Sta. Rosa 1"/>
              </div>
              <div class="mb-3">
                <label class="form-label">Municipality</label>
                <input type="text" class="form-control form-control-sm" id="setMunicipality" value="Sta. Rosa, Laguna"/>
              </div>
              <div class="mb-3">
                <label class="form-label">Barangay Captain</label>
                <input type="text" class="form-control form-control-sm" id="setBgyCaptain" value="Hon. Juan A. Reyes"/>
              </div>
              <div class="mb-3">
                <label class="form-label">Contact Number</label>
                <input type="text" class="form-control form-control-sm" id="setContact" value="+63 949 123 4567"/>
              </div>
              <button class="btn btn-primary btn-sm" id="btnSaveBgyProfile">Save Changes</button>
            </div>
          </div>
        </div>

        <!-- Admin Accounts -->
        <div class="col-lg-6">
          <div class="settings-card">
            <div class="settings-card-header">
              <i class="fa-solid fa-users-gear"></i> Admin Accounts
            </div>
            <div class="settings-card-body">
              <table class="table table-sm table-hover">
                <thead><tr><th>Name</th><th>Role</th><th>Status</th><th>Action</th></tr></thead>
                <tbody id="adminTableBody">
                  <!-- populated by JS -->
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Role Permissions -->
        <div class="col-lg-6">
          <div class="settings-card">
            <div class="settings-card-header">
              <i class="fa-solid fa-shield-halved"></i> Role Permissions
            </div>
            <div class="settings-card-body">
              <table class="table table-sm">
                <thead><tr><th>Module</th><th>Captain</th><th>Secretary</th><th>Treasurer</th><th>Kagawad</th></tr></thead>
                <tbody>
                  <tr><td>Dashboard</td><td>✅</td><td>✅</td><td>✅</td><td>✅</td></tr>
                  <tr><td>Residents</td><td>✅</td><td>✅</td><td>❌</td><td>👁️</td></tr>
                  <tr><td>Documents</td><td>✅</td><td>✅</td><td>❌</td><td>❌</td></tr>
                  <tr><td>Blotter</td><td>✅</td><td>✅</td><td>❌</td><td>👁️</td></tr>
                  <tr><td>Financial</td><td>✅</td><td>❌</td><td>✅</td><td>❌</td></tr>
                  <tr><td>Projects</td><td>✅</td><td>✅</td><td>❌</td><td>✅</td></tr>
                  <tr><td>Settings</td><td>✅</td><td>❌</td><td>❌</td><td>❌</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Audit Trail -->
        <div class="col-lg-6">
          <div class="settings-card">
            <div class="settings-card-header">
              <i class="fa-solid fa-list-check"></i> Audit Trail
            </div>
            <div class="settings-card-body">
              <div class="audit-log" id="auditLog">
                <!-- populated by JS -->
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

  </main><!-- /main-content -->
</div><!-- /main-wrapper -->


<!-- ==================== MODALS ==================== -->

<!-- Add Resident Modal -->
<div class="modal fade" id="addResidentModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-user-plus me-2 text-primary"></i>Add New Resident</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="formAddResident">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-4"><label class="form-label">First Name</label><input type="text" class="form-control" id="resFirstName" placeholder="e.g. Maria" required/></div>
            <div class="col-md-4"><label class="form-label">Middle Name</label><input type="text" class="form-control" id="resMiddleName" placeholder="e.g. Santos"/></div>
            <div class="col-md-4"><label class="form-label">Last Name</label><input type="text" class="form-control" id="resLastName" placeholder="e.g. Dela Cruz" required/></div>
            <div class="col-md-3"><label class="form-label">Birthdate</label><input type="date" class="form-control" id="resBirthdate" required/></div>
            <div class="col-md-3"><label class="form-label">Gender</label>
              <select class="form-select" id="resGender"><option>Male</option><option>Female</option></select>
            </div>
            <div class="col-md-3"><label class="form-label">Civil Status</label>
              <select class="form-select" id="resCivilStatus"><option>Single</option><option>Married</option><option>Widowed</option><option>Separated</option></select>
            </div>
            <div class="col-md-3"><label class="form-label">Voter Status</label>
              <select class="form-select" id="resVoterStatus"><option>Registered Voter</option><option>Non-Voter</option></select>
            </div>
            <div class="col-md-6"><label class="form-label">Address</label><input type="text" class="form-control" id="resAddress" placeholder="House No. & Street" required/></div>
            <div class="col-md-6"><label class="form-label">Purok</label>
              <select class="form-select" id="resPurok">
                <option>Purok 1 — Sampaloc</option><option>Purok 2 — Narra</option><option>Purok 3 — Makopa</option>
                <option>Purok 4 — Santol</option><option>Purok 5 — Bayabas</option>
              </select>
            </div>
            <div class="col-md-6"><label class="form-label">Contact Number</label><input type="text" class="form-control" id="resContact" placeholder="+63 9XX XXX XXXX" required/></div>
            <div class="col-md-6"><label class="form-label">Email Address</label><input type="email" class="form-control" id="resEmail" placeholder="Optional"/></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i>Save Resident</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- New Doc Request Modal -->
<div class="modal fade" id="newDocRequestModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-file-circle-plus me-2 text-primary"></i>New Document Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="formNewDocRequest">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Resident Name</label>
            <select class="form-select" id="docReqResidentId" required>
              <!-- Populated by JS -->
            </select>
          </div>
          <div class="mb-3"><label class="form-label">Document Type</label>
            <select class="form-select" id="docReqType">
              <option>Barangay Clearance</option>
              <option>Certificate of Residency</option>
              <option>Indigency Certificate</option>
              <option>Business Permit Clearance</option>
            </select>
          </div>
          <div class="mb-3"><label class="form-label">Purpose</label><input type="text" class="form-control" id="docReqPurpose" placeholder="e.g. Employment, Scholarship, etc." required/></div>
          <div class="mb-3"><label class="form-label">Fee</label><input type="number" class="form-control" id="docReqFee" value="50" min="0" required/></div>
          <div class="mb-3"><label class="form-label">Remarks</label><textarea class="form-control" id="docReqRemarks" rows="2"></textarea></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane me-1"></i>Submit Request</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- New Blotter Modal -->
<div class="modal fade" id="newBlotterModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-gavel me-2 text-danger"></i>New Blotter Entry</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="formNewBlotter">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Complainant Name</label><input type="text" class="form-control" id="blotterComplainant" placeholder="Full name" required/></div>
            <div class="col-md-6"><label class="form-label">Respondent Name</label><input type="text" class="form-control" id="blotterRespondent" placeholder="Full name" required/></div>
            <div class="col-md-6"><label class="form-label">Incident Type</label>
              <select class="form-select" id="blotterType">
                <option>Physical Altercation</option><option>Verbal Abuse</option><option>Property Dispute</option>
                <option>Noise Complaint</option><option>Theft</option><option>Domestic Dispute</option><option>Other</option>
              </select>
            </div>
            <div class="col-md-3"><label class="form-label">Incident Date</label><input type="date" class="form-control" id="blotterDate" required/></div>
            <div class="col-md-3"><label class="form-label">Priority</label>
              <select class="form-select" id="blotterPriority"><option>Normal</option><option>High</option><option>Urgent</option></select>
            </div>
            <div class="col-12"><label class="form-label">Incident Narrative</label><textarea class="form-control" id="blotterNarrative" rows="4" placeholder="Describe the incident in detail..." required></textarea></div>
            <div class="col-md-6"><label class="form-label">Initial Hearing Date</label><input type="date" class="form-control" id="blotterHearing" required/></div>
            <div class="col-md-6"><label class="form-label">Assigned Officer</label>
              <select class="form-select" id="blotterOfficer">
                <option>Kgd. Santos</option><option>Kgd. Mendoza</option><option>Kgd. Torres</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger"><i class="fa-solid fa-gavel me-1"></i>File Blotter</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Add Transaction Modal -->
<div class="modal fade" id="addTransactionModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-peso-sign me-2 text-success"></i>Add Transaction</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="formAddTransaction">
        <div class="modal-body">
          <div class="mb-3"><label class="form-label">Transaction Type</label>
            <select class="form-select" id="txType"><option>Income</option><option>Expense</option></select>
          </div>
          <div class="mb-3"><label class="form-label">Category</label>
            <select class="form-select" id="txCategory">
              <option>Document Fees</option><option>IRA Allocation</option><option>Business Permits</option>
              <option>Community Projects</option><option>Emergency Funds</option><option>Maintenance</option>
            </select>
          </div>
          <div class="mb-3"><label class="form-label">Description</label><input type="text" class="form-control" id="txDescription" required/></div>
          <div class="mb-3"><label class="form-label">Amount (₱)</label><input type="number" class="form-control" id="txAmount" min="0" required/></div>
          <div class="mb-3"><label class="form-label">Date</label><input type="date" class="form-control" id="txDate" required/></div>
          <div class="mb-3"><label class="form-label">OR / Reference Number</label><input type="text" class="form-control" id="txReference" required/></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success"><i class="fa-solid fa-save me-1"></i>Save Transaction</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Add Project Modal -->
<div class="modal fade" id="addProjectModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-diagram-project me-2 text-primary"></i>Add New Project</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="formAddProject">
        <div class="modal-body">
          <div class="mb-3"><label class="form-label">Project Title</label><input type="text" class="form-control" id="projTitle" required/></div>
          <div class="mb-3"><label class="form-label">Committee</label>
            <select class="form-select" id="projCommittee">
              <option>Health</option><option>Education</option><option>Peace & Order</option>
              <option>Environment</option><option>Youth & Sports</option>
            </select>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-md-6"><label class="form-label">Start Date</label><input type="date" class="form-control" id="projStart" required/></div>
            <div class="col-md-6"><label class="form-label">End Date</label><input type="date" class="form-control" id="projEnd" required/></div>
          </div>
          <div class="mb-3"><label class="form-label">Budget Allocation (₱)</label><input type="number" class="form-control" id="projBudget" min="0" required/></div>
          <div class="mb-3"><label class="form-label">Status</label>
            <select class="form-select" id="projStatus"><option>Planning</option><option>Ongoing</option><option>Completed</option><option>Delayed</option></select>
          </div>
          <div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" id="projDescription" rows="3" required></textarea></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i>Save Project</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Add Admin Modal -->
<div class="modal fade" id="addAdminModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-user-shield me-2 text-primary"></i>Add Admin User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="formAddAdmin">
        <div class="modal-body">
          <div class="mb-3"><label class="form-label">Full Name</label><input type="text" class="form-control" id="admFullName" required/></div>
          <div class="mb-3"><label class="form-label">Username</label><input type="text" class="form-control" id="admUsername" required/></div>
          <div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" id="admEmail" required/></div>
          <div class="mb-3"><label class="form-label">Role</label>
            <select class="form-select" id="admRole"><option>Captain</option><option>Secretary</option><option>Treasurer</option><option>Kagawad</option></select>
          </div>
          <div class="mb-3"><label class="form-label">Password</label><input type="password" class="form-control" id="admPassword" required/></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i>Create Account</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Document Preview Modal -->
<div class="modal fade" id="docPreviewModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-file-lines me-2 text-primary"></i>Document Preview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="doc-preview-paper">
          <div class="doc-preview-header">
            <div class="doc-header-seal">🏛️</div>
            <div>
              <div class="doc-header-title">Republic of the Philippines</div>
              <div class="doc-header-sub">Province of Laguna — Municipality of Sta. Rosa</div>
              <div class="doc-header-bgy">BARANGAY STA. ROSA 1</div>
            </div>
          </div>
          <div class="doc-preview-body" id="docPreviewContent">
            <!-- dynamic content -->
          </div>
          <div class="doc-preview-footer">
            <div class="doc-sig-block">
              <div class="sig-line"></div>
              <div class="sig-name">HON. JUAN A. REYES</div>
              <div class="sig-title">Punong Barangay</div>
            </div>
            <div class="doc-qr-placeholder">
              <i class="fa-solid fa-qrcode" style="font-size:48px;color:#94a3b8"></i>
              <div class="text-muted" style="font-size:10px;margin-top:4px">QR Authentication</div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-primary" id="btnPrintDoc"><i class="fa-solid fa-print me-1"></i>Print Document</button>
      </div>
    </div>
  </div>
</div>

<!-- Toast Notification -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
  <div id="liveToast" class="toast align-items-center border-0" role="alert">
    <div class="d-flex">
      <div class="toast-body" id="toastBody">Action completed.</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="assets/js/script.js"></script>
</body>
</html>
