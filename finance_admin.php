<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Barangay Financial Management System</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --navy: #1a2236;
    --navy-dark: #131929;
    --navy-mid: #1e2a40;
    --navy-hover: #243050;
    --gold: #e8a020;
    --gold-light: #f5b840;
    --gold-dim: rgba(232,160,32,0.15);
    --white: #ffffff;
    --offwhite: #f7f8fc;
    --text-main: #1a2236;
    --text-muted: #6b7a99;
    --text-light: #a0aec0;
    --border: #e8ecf4;
    --green: #22c55e;
    --red: #ef4444;
    --orange: #f97316;
    --blue: #3b82f6;
    --purple: #8b5cf6;
    --sidebar-w: 220px;
    --topbar-h: 64px;
    --radius: 12px;
    --shadow: 0 2px 12px rgba(26,34,54,0.08);
    --shadow-lg: 0 8px 32px rgba(26,34,54,0.12);
    --bg: #f7f8fc;
    /* card/topbar surface */
    --surface: #ffffff;
    /* topbar border */
    --topbar-border: #e8ecf4;
  }

  /* ── DARK MODE ── */
  body.dark {
    --bg: #0d1b2e;
    --surface: #112240;
    --offwhite: #0d1b2e;
    --white: #112240;
    --text-main: #e2e8f4;
    --text-muted: #8da0bc;
    --text-light: #5a7499;
    --border: rgba(255,255,255,0.08);
    --topbar-border: rgba(255,255,255,0.07);
    --shadow: 0 2px 12px rgba(0,0,0,0.3);
    --shadow-lg: 0 8px 32px rgba(0,0,0,0.4);
    --navy: #0b1a30;
    --navy-dark: #070f1e;
  }

  body {
    font-family: 'DM Sans', sans-serif;
    background: linear-gradient(180deg, rgba(11,37,69,0.08) 0, rgba(247,245,240,0) 280px), var(--bg);
    color: var(--text-main);
    min-height: 100vh;
    display: flex;
    transition: background 0.3s, color 0.3s;
  }
  body.dark {
    background: linear-gradient(180deg, rgba(11,37,69,0.08) 0, rgba(247,245,240,0) 280px), var(--bg);
  }

  /* ── SIDEBAR ── */
  .sidebar {
    width: var(--sidebar-w);
    background: var(--navy);
    min-height: 100vh;
    position: fixed;
    top: 0; left: 0;
    display: flex;
    flex-direction: column;
    z-index: 100;
    overflow-y: auto;
    scrollbar-width: none;
    transition: transform 0.3s ease;
  }
  .sidebar::-webkit-scrollbar { display: none; }

  .sidebar-logo {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 18px 16px 16px;
    border-bottom: 1px solid rgba(255,255,255,0.07);
  }
  .logo-icon {
    width: 40px; height: 40px;
    background: var(--gold);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
  }
  .logo-text { line-height: 1.2; }
  .logo-text strong { color: var(--white); font-size: 13px; font-weight: 700; display: block; }
  .logo-text span { color: var(--text-light); font-size: 10px; }

  .sidebar-close {
    display: none;
    background: none; border: none; color: var(--text-light);
    cursor: pointer; font-size: 20px; padding: 4px; margin-left: auto;
  }

  .nav-section-label {
    padding: 14px 16px 6px;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    color: rgba(255,255,255,0.3);
  }

  .nav-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 16px;
    color: rgba(255,255,255,0.6);
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    border-radius: 0;
    transition: all 0.15s;
    text-decoration: none;
    position: relative;
  }
  .nav-item:hover { color: var(--white); background: rgba(255,255,255,0.06); }
  .nav-item.active {
    color: var(--white);
    background: rgba(232,160,32,0.15);
    border-right: 3px solid var(--gold);
  }
  .nav-item.active .nav-icon { color: var(--gold); }
  .nav-icon { font-size: 15px; width: 20px; text-align: center; flex-shrink: 0; }

  .nav-parent {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 16px;
    color: rgba(255,255,255,0.85);
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    justify-content: space-between;
  }
  .nav-parent-left { display: flex; align-items: center; gap: 10px; }
  .nav-chevron { font-size: 10px; color: rgba(255,255,255,0.4); transition: transform 0.2s; }
  .nav-parent.open .nav-chevron { transform: rotate(180deg); }

  .nav-children {
    overflow: hidden;
    max-height: 0;
    transition: max-height 0.3s ease;
  }
  .nav-children.open { max-height: 300px; }
  .nav-children .nav-item {
    padding: 7px 16px 7px 46px;
    font-size: 12px;
    font-weight: 400;
    color: rgba(255,255,255,0.5);
  }
  .nav-children .nav-item::before {
    content: '•';
    position: absolute;
    left: 34px;
    font-size: 8px;
  }
  .nav-children .nav-item:hover { color: rgba(255,255,255,0.9); }

  .sidebar-bottom {
    margin-top: auto;
    border-top: 1px solid rgba(255,255,255,0.07);
    padding: 8px 0;
  }
  .notif-badge {
    background: var(--gold);
    color: var(--navy-dark);
    font-size: 10px;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 20px;
    margin-left: auto;
  }

  /* ── MAIN LAYOUT ── */
  .main-wrap {
    margin-left: var(--sidebar-w);
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
  }

  /* ── TOPBAR ── */
  .topbar {
    height: var(--topbar-h);
    background: var(--surface);
    border-bottom: 1px solid var(--topbar-border);
    display: flex;
    align-items: center;
    padding: 0 24px;
    gap: 16px;
    position: sticky;
    top: 0;
    z-index: 50;
    transition: background 0.3s, border-color 0.3s;
  }
  .topbar-toggle {
    display: none;
    background: none; border: none;
    font-size: 20px; color: var(--text-muted);
    cursor: pointer; padding: 4px;
  }
  .search-box {
    flex: 1;
    max-width: 400px;
    position: relative;
  }
  .search-box input {
    width: 100%;
    padding: 9px 16px 9px 40px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-family: inherit;
    font-size: 13px;
    color: var(--text-main);
    background: var(--offwhite);
    outline: none;
    transition: border-color 0.2s, background 0.3s, color 0.3s;
  }
  .search-box input:focus { border-color: var(--gold); background: var(--surface); }
  .search-box .search-icon {
    position: absolute;
    left: 12px; top: 50%; transform: translateY(-50%);
    color: var(--text-light); font-size: 14px;
  }
  .topbar-right { display: flex; align-items: center; gap: 12px; margin-left: auto; }
  .notif-btn {
    position: relative;
    background: none; border: none;
    font-size: 20px; color: var(--text-muted);
    cursor: pointer; padding: 6px;
  }
  .notif-dot {
    position: absolute;
    top: 4px; right: 4px;
    width: 8px; height: 8px;
    background: var(--gold);
    border-radius: 50%;
    border: 2px solid white;
  }
  .user-chip {
    display: flex; align-items: center; gap: 10px;
    padding: 6px 12px 6px 6px;
    border: 1px solid var(--border);
    border-radius: 24px;
    cursor: pointer;
    transition: background 0.15s;
  }
  .user-chip:hover { background: var(--offwhite); }
  .user-avatar {
    width: 30px; height: 30px;
    background: linear-gradient(135deg, var(--gold), #f97316);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    color: white; font-size: 12px; font-weight: 700;
  }
  .user-info { line-height: 1.2; }
  .user-info strong { font-size: 12px; font-weight: 600; display: block; }
  .user-info span { font-size: 10px; color: var(--text-muted); }

  /* ── PAGE CONTENT ── */
  .page-content {
    padding: 24px;
    flex: 1;
  }

  .welcome-text h1 {
    font-size: 22px;
    font-weight: 700;
    color: var(--text-main);
  }
  .welcome-text p {
    font-size: 13px;
    color: var(--text-muted);
    margin-top: 3px;
  }

  /* ── STAT CARDS ── */
  .stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-top: 20px;
  }
  .stat-card {
    background: var(--white);
    border-radius: var(--radius);
    padding: 18px 20px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
  }
  .stat-icon-wrap {
    width: 44px; height: 44px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px;
    margin-bottom: 12px;
  }
  .stat-icon-wrap.yellow { background: rgba(232,160,32,0.12); }
  .stat-icon-wrap.blue { background: rgba(59,130,246,0.12); }
  .stat-icon-wrap.green { background: rgba(34,197,94,0.12); }
  .stat-icon-wrap.purple { background: rgba(139,92,246,0.12); }
  .stat-label { font-size: 11px; color: var(--text-muted); font-weight: 500; }
  .stat-value { font-size: 22px; font-weight: 700; margin-top: 4px; color: var(--text-main); font-family: 'DM Mono', monospace; }
  .stat-badge {
    display: inline-flex; align-items: center; gap: 3px;
    font-size: 11px; font-weight: 600;
    margin-top: 6px;
    padding: 3px 7px;
    border-radius: 20px;
  }
  .stat-badge.up { background: rgba(34,197,94,0.1); color: var(--green); }
  .stat-badge.down { background: rgba(239,68,68,0.1); color: var(--red); }
  .stat-badge.neutral { background: rgba(34,197,94,0.1); color: var(--green); }
  .progress-bar { height: 4px; background: var(--border); border-radius: 4px; margin-top: 8px; overflow: hidden; }
  .progress-fill { height: 100%; background: var(--green); border-radius: 4px; }
  .stat-link { font-size: 11px; color: var(--gold); font-weight: 600; cursor: pointer; margin-top: 8px; display: inline-block; }

  /* ── TWO COLUMN GRID ── */
  .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 16px; }
  .three-col { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-top: 16px; }

  /* ── CARDS ── */
  .card {
    background: var(--white);
    border-radius: var(--radius);
    padding: 20px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
  }
  .card-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 16px;
  }
  .card-title { font-size: 14px; font-weight: 700; color: var(--text-main); }
  .card-link { font-size: 12px; color: var(--gold); font-weight: 600; cursor: pointer; }

  /* ── TABLE ── */
  table { width: 100%; border-collapse: collapse; }
  th { font-size: 11px; font-weight: 600; color: var(--text-muted); text-align: left; padding: 8px 0; border-bottom: 1px solid var(--border); }
  td { font-size: 12px; padding: 10px 0; border-bottom: 1px solid rgba(232,236,244,0.6); color: var(--text-main); vertical-align: middle; }
  tr:last-child td { border-bottom: none; }
  td.amount { font-family: 'DM Mono', monospace; font-size: 12px; }

  .badge {
    display: inline-block;
    font-size: 10px; font-weight: 600;
    padding: 3px 8px;
    border-radius: 20px;
    white-space: nowrap;
  }
  .badge.collection { background: rgba(59,130,246,0.1); color: var(--blue); }
  .badge.expenditure { background: rgba(249,115,22,0.1); color: var(--orange); }
  .badge.completed { background: rgba(34,197,94,0.1); color: var(--green); }
  .badge.approved { background: rgba(59,130,246,0.1); color: var(--blue); }
  .badge.pending { background: rgba(249,115,22,0.1); color: var(--orange); }

  /* ── CHART ── */
  .chart-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
  .chart-select {
    font-family: inherit;
    font-size: 12px;
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 4px 8px;
    color: var(--text-main);
    background: white;
    cursor: pointer;
  }
  .chart-wrap { position: relative; height: 200px; }

  /* ── DONUT CHART ── */
  .donut-wrap { display: flex; align-items: center; gap: 16px; }
  .donut-canvas-wrap { position: relative; width: 140px; height: 140px; flex-shrink: 0; }
  .donut-center {
    position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%);
    text-align: center; line-height: 1.3;
  }
  .donut-center strong { font-size: 11px; font-weight: 700; display: block; }
  .donut-center span { font-size: 9px; color: var(--text-muted); }
  .donut-legend { flex: 1; }
  .legend-item { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; font-size: 11px; }
  .legend-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
  .legend-label { display: flex; align-items: center; gap: 6px; color: var(--text-muted); }
  .legend-val { font-family: 'DM Mono', monospace; font-size: 11px; font-weight: 500; color: var(--text-main); }
  .legend-pct { color: var(--text-light); font-size: 10px; margin-left: 4px; }

  /* ── NOTIFICATIONS ── */
  .notif-item { display: flex; align-items: flex-start; gap: 12px; padding: 10px 0; border-bottom: 1px solid rgba(232,236,244,0.6); }
  .notif-item:last-child { border-bottom: none; }
  .notif-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
  .notif-icon.doc { background: rgba(232,160,32,0.12); }
  .notif-icon.exp { background: rgba(59,130,246,0.12); }
  .notif-icon.rep { background: rgba(34,197,94,0.12); }
  .notif-text strong { font-size: 12px; font-weight: 600; display: block; }
  .notif-text span { font-size: 11px; color: var(--text-muted); }
  .view-all-btn { display: block; text-align: center; font-size: 12px; color: var(--gold); font-weight: 600; margin-top: 12px; cursor: pointer; }

  /* ── SIDEBAR OVERLAY ── */
  .sidebar-overlay {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.5);
    z-index: 99;
  }

  /* ── RESPONSIVE ── */
  @media (max-width: 1100px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
  }
  @media (max-width: 900px) {
    .three-col { grid-template-columns: 1fr; }
    .two-col { grid-template-columns: 1fr; }
  }
  @media (max-width: 768px) {
    :root { --sidebar-w: 240px; }
    .sidebar {
      transform: translateX(-100%);
    }
    .sidebar.open {
      transform: translateX(0);
    }
    .sidebar-close { display: flex; }
    .sidebar-overlay { display: block; opacity: 0; pointer-events: none; transition: opacity 0.3s; }
    .sidebar-overlay.open { opacity: 1; pointer-events: all; }
    .main-wrap { margin-left: 0; }
    .topbar-toggle { display: flex; }
    .search-box { max-width: none; }
    .user-info { display: none; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
    .page-content { padding: 16px; }
    .stat-value { font-size: 18px; }
    .two-col { grid-template-columns: 1fr; }
    .three-col { grid-template-columns: 1fr; }
    .card { padding: 16px; }
  }
  @media (max-width: 480px) {
    .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
    .stat-card { padding: 14px; }
    .stat-value { font-size: 16px; }
    .welcome-text h1 { font-size: 18px; }
  }
</style>
</head>
<body>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="overlay" onclick="closeSidebar()"></div>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="logo-icon">🏛️</div>
    <div class="logo-text">
      <strong>Barangay</strong>
      <span>Financial Management System</span>
    </div>
    <button class="sidebar-close" onclick="closeSidebar()">✕</button>
  </div>

  <div class="nav-section-label">Main</div>
  <a class="nav-item active"><span class="nav-icon">🏠</span> Dashboard</a>

  <div class="nav-section-label">Collections</div>
  <div class="nav-parent open" onclick="toggleNav(this)">
    <div class="nav-parent-left"><span class="nav-icon">💰</span> Collections</div>
    <span class="nav-chevron">▼</span>
  </div>
  <div class="nav-children open">
    <a class="nav-item">All Collections</a>
    <a class="nav-item">Record Payment</a>
    <a class="nav-item">Document Fees</a>
    <a class="nav-item">Business Permits</a>
    <a class="nav-item">Other Collections</a>
  </div>

  <div class="nav-parent" onclick="toggleNav(this)">
    <div class="nav-parent-left"><span class="nav-icon">🧾</span> Official Receipts</div>
    <span class="nav-chevron">▼</span>
  </div>
  <div class="nav-children">
    <a class="nav-item">All Receipts</a>
    <a class="nav-item">Issue Receipt</a>
  </div>

  <div class="nav-section-label">Expenditures</div>
  <div class="nav-parent" onclick="toggleNav(this)">
    <div class="nav-parent-left"><span class="nav-icon">📤</span> Expenditures</div>
    <span class="nav-chevron">▼</span>
  </div>
  <div class="nav-children">
    <a class="nav-item">All Expenditures</a>
    <a class="nav-item">Add Expenditure</a>
    <a class="nav-item">By Category</a>
    <a class="nav-item">Pending Captain Approval</a>
  </div>

  <div class="nav-section-label">Budget</div>
  <div class="nav-parent" onclick="toggleNav(this)">
    <div class="nav-parent-left"><span class="nav-icon">📊</span> Budget Management</div>
    <span class="nav-chevron">▼</span>
  </div>
  <div class="nav-children">
    <a class="nav-item">Annual Budget Plan</a>
    <a class="nav-item">Budget Utilization</a>
    <a class="nav-item">Add Budget Item</a>
  </div>

  <div class="nav-section-label">Reports</div>
  <div class="nav-parent" onclick="toggleNav(this)">
    <div class="nav-parent-left"><span class="nav-icon">📈</span> Financial Reports</div>
    <span class="nav-chevron">▼</span>
  </div>
  <div class="nav-children">
    <a class="nav-item">Monthly Summary</a>
    <a class="nav-item">Quarterly Report</a>
    <a class="nav-item">Annual Statement</a>
    <a class="nav-item">Export to PDF / Excel</a>
  </div>

  <div class="sidebar-bottom">
    <a class="nav-item"><span class="nav-icon">🔔</span> Notifications <span class="notif-badge">3</span></a>
    <a class="nav-item"><span class="nav-icon">👤</span> My Profile</a>
    <a class="nav-item"><span class="nav-icon">🚪</span> Logout</a>
  </div>
</aside>

<!-- Main -->
<div class="main-wrap">
  <!-- Topbar -->
  <header class="topbar">
    <button class="topbar-toggle" onclick="openSidebar()">☰</button>
    <div class="search-box">
      <span class="search-icon">🔍</span>
      <input type="text" placeholder="Search here...">
    </div>
    <div class="topbar-right">
      <button class="notif-btn">🔔<span class="notif-dot"></span></button>
      <div class="user-chip">
        <div class="user-avatar">JD</div>
        <div class="user-info">
          <strong>Juan Dela Cruz</strong>
          <span>Administrator</span>
        </div>
        <span style="font-size:10px;color:var(--text-muted);margin-left:2px;">▼</span>
      </div>
    </div>
  </header>

  <!-- Page Content -->
  <main class="page-content">
    <div class="welcome-text">
      <h1>Welcome back, Juan Dela Cruz! 👋</h1>
      <p>Here's what's happening in your barangay today.</p>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon-wrap yellow">💵</div>
        <div class="stat-label">Total Collections</div>
        <div class="stat-value">₱ 1,234,567.89</div>
        <div class="stat-badge up">▲ 12.5% vs last month</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon-wrap blue">📤</div>
        <div class="stat-label">Total Expenditures</div>
        <div class="stat-value">₱ 987,654.32</div>
        <div class="stat-badge down">▼ 8.3% vs last month</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon-wrap green">📊</div>
        <div class="stat-label">Budget Utilization</div>
        <div class="stat-value">65.4%</div>
        <div class="progress-bar"><div class="progress-fill" style="width:65.4%"></div></div>
        <div class="stat-badge neutral">✓ On track</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon-wrap purple">👥</div>
        <div class="stat-label">Pending Approvals</div>
        <div class="stat-value">12</div>
        <a class="stat-link">View pending items →</a>
      </div>
    </div>

    <!-- Transactions + Chart -->
    <div class="two-col">
      <div class="card">
        <div class="card-header">
          <span class="card-title">Recent Transactions</span>
          <a class="card-link">View All</a>
        </div>
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

      <div class="card">
        <div class="chart-header">
          <span class="card-title">Monthly Revenue</span>
          <select class="chart-select">
            <option>May 2025</option>
            <option>Apr 2025</option>
            <option>Mar 2025</option>
          </select>
        </div>
        <div class="chart-wrap">
          <canvas id="revenueChart"></canvas>
        </div>
      </div>
    </div>

    <!-- Budget Allocation + Notifications -->
    <div class="two-col">
      <div class="card">
        <div class="card-header">
          <span class="card-title">Budget Allocation</span>
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

      <div class="card">
        <div class="card-header">
          <span class="card-title">Recent Notifications</span>
          <a class="card-link">View All</a>
        </div>
        <div class="notif-item">
          <div class="notif-icon doc">📄</div>
          <div class="notif-text">
            <strong>New document request from Juan Santos</strong>
            <span>2 minutes ago</span>
          </div>
        </div>
        <div class="notif-item">
          <div class="notif-icon exp">💸</div>
          <div class="notif-text">
            <strong>Expenditure "Fuel Expense" is pending your approval</strong>
            <span>1 hour ago</span>
          </div>
        </div>
        <div class="notif-item">
          <div class="notif-icon rep">📋</div>
          <div class="notif-text">
            <strong>Monthly report for April 2025 is now available</strong>
            <span>3 hours ago</span>
          </div>
        </div>
        <a class="view-all-btn">View All Notifications</a>
      </div>
    </div>

    <p style="text-align:center;font-size:11px;color:var(--text-light);margin-top:32px;padding-bottom:16px;">
      © 2025 Barangay Financial Management System. All rights reserved.
    </p>
  </main>
</div>

<script>
// Sidebar toggle
function openSidebar() {
  document.getElementById('sidebar').classList.add('open');
  document.getElementById('overlay').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('overlay').classList.remove('open');
  document.body.style.overflow = '';
}

// Nav accordion
function toggleNav(el) {
  el.classList.toggle('open');
  const children = el.nextElementSibling;
  if (children && children.classList.contains('nav-children')) {
    children.classList.toggle('open');
  }
}

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