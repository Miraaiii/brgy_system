<?php

/* requireRole(['Resident']); */
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Barangay Sta. Rosa 1 — Resident Self-Service Portal</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&display=swap" rel="stylesheet">

<style>
:root {
  --primary: #0B2545;
  --primary-light: #143669;
  --primary-dark: #071a33;
  --accent: #C9961E;
  --accent-light: #e0aa30;
  --accent-pale: #fdf3de;
  --bg: #F5F7FA;
  --surface: #ffffff;
  --text: #333333;
  --text-muted: #6b7280;
  --text-light: #9ca3af;
  --border: #e5e7eb;
  --shadow-sm: 0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.05);
  --shadow-md: 0 4px 16px rgba(11,37,69,0.10);
  --shadow-lg: 0 8px 32px rgba(11,37,69,0.14);
  --radius: 14px;
  --radius-sm: 8px;
  --radius-lg: 20px;
  --sidebar-w: 265px;
  --topbar-h: 64px;
  --transition: 0.25s cubic-bezier(.4,0,.2,1);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: 'DM Sans', sans-serif;
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
  overflow-x: hidden;
}

/* ── TOPBAR ── */
.topbar {
  position: fixed;
  top: 0; left: 0; right: 0;
  height: var(--topbar-h);
  background: var(--primary);
  display: flex;
  align-items: center;
  padding: 0 20px;
  gap: 12px;
  z-index: 1000;
  box-shadow: 0 2px 12px rgba(0,0,0,0.2);
}

.topbar-brand {
  display: flex;
  align-items: center;
  gap: 10px;
  text-decoration: none;
  flex-shrink: 0;
}

.brand-seal {
  width: 38px; height: 38px;
  background: var(--accent);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  color: var(--primary);
  font-weight: 700;
  flex-shrink: 0;
  box-shadow: 0 0 0 2px rgba(201,150,30,0.3);
}

.brand-text {
  display: flex;
  flex-direction: column;
  line-height: 1.1;
}

.brand-name {
  font-family: 'Fraunces', serif;
  font-size: 15px;
  font-weight: 600;
  color: #fff;
  white-space: nowrap;
}

.brand-sub {
  font-size: 10px;
  color: rgba(255,255,255,0.55);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  white-space: nowrap;
}

.topbar-spacer { flex: 1; }

.hamburger-btn {
  display: none;
  background: none;
  border: none;
  color: #fff;
  font-size: 22px;
  cursor: pointer;
  padding: 6px;
  border-radius: 8px;
  line-height: 1;
  transition: background var(--transition);
}
.hamburger-btn:hover { background: rgba(255,255,255,0.1); }

.topbar-search {
  display: flex;
  align-items: center;
  background: rgba(255,255,255,0.1);
  border-radius: 24px;
  padding: 6px 14px;
  gap: 8px;
  transition: background var(--transition);
  max-width: 240px;
  width: 100%;
}
.topbar-search:focus-within { background: rgba(255,255,255,0.18); }
.topbar-search input {
  background: none;
  border: none;
  outline: none;
  color: #fff;
  font-size: 13px;
  font-family: 'DM Sans', sans-serif;
  width: 100%;
}
.topbar-search input::placeholder { color: rgba(255,255,255,0.5); }
.topbar-search i { color: rgba(255,255,255,0.5); font-size: 14px; }

.topbar-actions { display: flex; align-items: center; gap: 6px; }

.topbar-btn {
  position: relative;
  width: 40px; height: 40px;
  background: none;
  border: none;
  border-radius: 10px;
  color: rgba(255,255,255,0.75);
  font-size: 18px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all var(--transition);
}
.topbar-btn:hover { background: rgba(255,255,255,0.1); color: #fff; }

.notif-badge {
  position: absolute;
  top: 6px; right: 6px;
  width: 8px; height: 8px;
  background: var(--accent);
  border-radius: 50%;
  border: 2px solid var(--primary);
}

.notif-badge-count {
  position: absolute;
  top: 4px; right: 4px;
  min-width: 17px; height: 17px;
  background: var(--accent);
  border-radius: 10px;
  font-size: 10px;
  font-weight: 700;
  color: var(--primary);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 4px;
  border: 2px solid var(--primary);
  line-height: 1;
}

.avatar-btn {
  width: 38px; height: 38px;
  border-radius: 50%;
  background: var(--accent);
  border: 2px solid rgba(255,255,255,0.25);
  color: var(--primary);
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: border-color var(--transition);
}
.avatar-btn:hover { border-color: rgba(255,255,255,0.6); }

/* ── SIDEBAR ── */
.sidebar {
  position: fixed;
  top: var(--topbar-h);
  left: 0;
  bottom: 0;
  width: var(--sidebar-w);
  background: var(--primary);
  overflow-y: auto;
  overflow-x: hidden;
  z-index: 900;
  transition: transform var(--transition);
  display: flex;
  flex-direction: column;
  scrollbar-width: thin;
  scrollbar-color: rgba(255,255,255,0.15) transparent;
}

.sidebar::-webkit-scrollbar { width: 4px; }
.sidebar::-webkit-scrollbar-track { background: transparent; }
.sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 2px; }

.sidebar-user {
  padding: 20px 16px 16px;
  display: flex;
  align-items: center;
  gap: 12px;
  border-bottom: 1px solid rgba(255,255,255,0.08);
  margin-bottom: 8px;
}

.sidebar-avatar {
  width: 44px; height: 44px;
  border-radius: 50%;
  background: var(--accent);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--primary);
  font-size: 16px;
  font-weight: 700;
  flex-shrink: 0;
  border: 2px solid rgba(201,150,30,0.4);
}

.sidebar-user-info { flex: 1; min-width: 0; }
.sidebar-user-name {
  font-size: 13px;
  font-weight: 600;
  color: #fff;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.sidebar-user-role {
  font-size: 11px;
  color: rgba(255,255,255,0.45);
  margin-top: 1px;
}

.profile-progress {
  padding: 0 16px 16px;
  border-bottom: 1px solid rgba(255,255,255,0.08);
  margin-bottom: 8px;
}
.progress-label {
  display: flex;
  justify-content: space-between;
  font-size: 11px;
  color: rgba(255,255,255,0.5);
  margin-bottom: 6px;
}
.progress-bar-outer {
  height: 4px;
  background: rgba(255,255,255,0.12);
  border-radius: 2px;
  overflow: hidden;
}
.progress-bar-inner {
  height: 100%;
  background: var(--accent);
  border-radius: 2px;
  transition: width 0.8s ease;
}

.nav-section {
  padding: 0 10px;
  margin-bottom: 6px;
}
.nav-section-label {
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: rgba(255,255,255,0.3);
  padding: 10px 8px 4px;
  font-weight: 600;
}

.nav-link {
  display: flex;
  align-items: center;
  gap: 11px;
  padding: 10px 12px;
  border-radius: 10px;
  color: rgba(255,255,255,0.65);
  text-decoration: none;
  font-size: 13.5px;
  font-weight: 500;
  transition: all var(--transition);
  position: relative;
  cursor: pointer;
  user-select: none;
}
.nav-link i { font-size: 17px; flex-shrink: 0; width: 20px; text-align: center; }
.nav-link:hover { background: rgba(255,255,255,0.07); color: #fff; }
.nav-link.active { background: rgba(201,150,30,0.18); color: #fff; }
.nav-link.active::before {
  content: '';
  position: absolute;
  left: 0; top: 6px; bottom: 6px;
  width: 3px;
  background: var(--accent);
  border-radius: 0 2px 2px 0;
}
.nav-link .nav-badge {
  margin-left: auto;
  background: var(--accent);
  color: var(--primary);
  font-size: 10px;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 10px;
  line-height: 1.4;
}

.nav-submenu {
  display: none;
  padding-left: 32px;
}
.nav-submenu.open { display: block; }
.nav-submenu .nav-link {
  font-size: 13px;
  padding: 8px 12px;
  color: rgba(255,255,255,0.5);
}
.nav-submenu .nav-link:hover { color: rgba(255,255,255,0.85); }
.nav-submenu .nav-link.active { color: #fff; background: rgba(255,255,255,0.07); }

.nav-chevron {
  margin-left: auto;
  font-size: 12px;
  transition: transform var(--transition);
}
.nav-link.expanded .nav-chevron { transform: rotate(90deg); }

.sidebar-footer {
  margin-top: auto;
  padding: 12px 10px 20px;
  border-top: 1px solid rgba(255,255,255,0.08);
}

/* ── OVERLAY ── */
.sidebar-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.5);
  z-index: 850;
  backdrop-filter: blur(2px);
}
.sidebar-overlay.show { display: block; }

/* ── MAIN ── */
.main-content {
  margin-left: var(--sidebar-w);
  padding-top: var(--topbar-h);
  min-height: 100vh;
  transition: margin-left var(--transition);
}

.page-content {
  padding: 28px 28px 100px;
}

/* ── PAGE HEADER ── */
.page-header {
  margin-bottom: 24px;
}
.page-title {
  font-family: 'Fraunces', serif;
  font-size: 24px;
  font-weight: 600;
  color: var(--primary);
  line-height: 1.2;
}
.page-subtitle {
  font-size: 13.5px;
  color: var(--text-muted);
  margin-top: 4px;
}

/* ── WELCOME CARD ── */
.welcome-card {
  background: linear-gradient(135deg, var(--primary) 0%, #1a4a8a 60%, #0d3060 100%);
  border-radius: var(--radius-lg);
  padding: 28px 32px;
  color: #fff;
  position: relative;
  overflow: hidden;
  margin-bottom: 24px;
  box-shadow: var(--shadow-lg);
}
.welcome-card::before {
  content: '';
  position: absolute;
  top: -60px; right: -60px;
  width: 220px; height: 220px;
  border-radius: 50%;
  background: rgba(255,255,255,0.04);
}
.welcome-card::after {
  content: '';
  position: absolute;
  bottom: -40px; right: 80px;
  width: 140px; height: 140px;
  border-radius: 50%;
  background: rgba(201,150,30,0.12);
}
.welcome-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: rgba(201,150,30,0.2);
  border: 1px solid rgba(201,150,30,0.35);
  color: var(--accent-light);
  font-size: 11px;
  font-weight: 600;
  padding: 4px 12px;
  border-radius: 20px;
  margin-bottom: 12px;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}
.welcome-title {
  font-family: 'Fraunces', serif;
  font-size: 22px;
  font-weight: 600;
  margin-bottom: 6px;
  line-height: 1.25;
}
.welcome-sub {
  font-size: 13px;
  color: rgba(255,255,255,0.65);
  max-width: 460px;
  line-height: 1.5;
}
.welcome-date {
  font-size: 12px;
  color: rgba(255,255,255,0.5);
  margin-top: 14px;
  display: flex;
  align-items: center;
  gap: 6px;
}
.welcome-actions {
  display: flex;
  gap: 10px;
  margin-top: 20px;
  flex-wrap: wrap;
}
.btn-accent {
  background: var(--accent);
  color: var(--primary);
  border: none;
  padding: 10px 20px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 7px;
  min-height: 44px;
  transition: all var(--transition);
  text-decoration: none;
  white-space: nowrap;
}
.btn-accent:hover { background: var(--accent-light); color: var(--primary); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(201,150,30,0.4); }

.btn-outline-white {
  background: rgba(255,255,255,0.1);
  color: #fff;
  border: 1px solid rgba(255,255,255,0.25);
  padding: 10px 20px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 7px;
  min-height: 44px;
  transition: all var(--transition);
  text-decoration: none;
  white-space: nowrap;
}
.btn-outline-white:hover { background: rgba(255,255,255,0.2); color: #fff; }

/* ── STAT CARDS ── */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 24px;
}

.stat-card {
  background: var(--surface);
  border-radius: var(--radius);
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  box-shadow: var(--shadow-sm);
  transition: transform var(--transition), box-shadow var(--transition);
  border: 1px solid var(--border);
  cursor: default;
}
.stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }

.stat-icon {
  width: 46px; height: 46px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
}
.stat-icon.blue { background: #eff6ff; color: #2563eb; }
.stat-icon.amber { background: var(--accent-pale); color: var(--accent); }
.stat-icon.green { background: #f0fdf4; color: #16a34a; }
.stat-icon.red { background: #fef2f2; color: #dc2626; }

.stat-value {
  font-family: 'Fraunces', serif;
  font-size: 28px;
  font-weight: 600;
  color: var(--primary);
  line-height: 1;
}
.stat-label { font-size: 12.5px; color: var(--text-muted); font-weight: 500; }
.stat-trend {
  font-size: 11.5px;
  display: flex;
  align-items: center;
  gap: 4px;
  font-weight: 500;
}
.stat-trend.up { color: #16a34a; }
.stat-trend.down { color: #dc2626; }

/* ── SECTION CARDS ── */
.card {
  background: var(--surface);
  border-radius: var(--radius);
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--border);
  overflow: hidden;
}
.card-header {
  padding: 18px 20px 14px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid var(--border);
}
.card-title {
  font-size: 15px;
  font-weight: 700;
  color: var(--primary);
  display: flex;
  align-items: center;
  gap: 8px;
}
.card-title i { color: var(--accent); font-size: 17px; }
.card-body { padding: 18px 20px; }
.card-link { font-size: 12.5px; color: var(--accent); font-weight: 600; text-decoration: none; }
.card-link:hover { color: var(--accent-light); }

/* ── QUICK ACTIONS ── */
.quick-actions-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
}
.qa-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding: 18px 12px;
  border-radius: 12px;
  background: var(--bg);
  border: 1.5px solid var(--border);
  cursor: pointer;
  transition: all var(--transition);
  text-decoration: none;
  color: var(--text);
  text-align: center;
  min-height: 44px;
}
.qa-item:hover {
  border-color: var(--accent);
  background: var(--accent-pale);
  color: var(--text);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(201,150,30,0.15);
}
.qa-icon {
  width: 44px; height: 44px;
  background: var(--primary);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  color: var(--accent);
  transition: background var(--transition);
}
.qa-item:hover .qa-icon { background: var(--accent); color: var(--primary); }
.qa-label { font-size: 12px; font-weight: 600; line-height: 1.3; }

/* ── ANNOUNCEMENTS ── */
.announcement-item {
  padding: 14px 0;
  border-bottom: 1px solid var(--border);
  display: flex;
  gap: 14px;
}
.announcement-item:last-child { border-bottom: none; padding-bottom: 0; }
.announcement-dot {
  width: 8px; height: 8px;
  border-radius: 50%;
  background: var(--accent);
  margin-top: 6px;
  flex-shrink: 0;
}
.announcement-title { font-size: 13.5px; font-weight: 600; color: var(--text); margin-bottom: 3px; }
.announcement-meta { font-size: 11.5px; color: var(--text-muted); }
.announcement-tag {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 6px;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  margin-left: 6px;
}
.tag-urgent { background: #fef2f2; color: #dc2626; }
.tag-info { background: #eff6ff; color: #2563eb; }
.tag-event { background: var(--accent-pale); color: var(--accent); }

/* ── REQUEST STATUS TABLE ── */
.table-responsive { overflow-x: auto; }
table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
thead th {
  background: var(--bg);
  color: var(--text-muted);
  font-weight: 600;
  font-size: 11.5px;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 10px 14px;
  border-bottom: 1px solid var(--border);
  white-space: nowrap;
}
tbody td {
  padding: 12px 14px;
  border-bottom: 1px solid var(--border);
  color: var(--text);
  vertical-align: middle;
}
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover td { background: rgba(245,247,250,0.7); }

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 700;
  white-space: nowrap;
}
.status-pending { background: #fff8e1; color: #b45309; }
.status-processing { background: #eff6ff; color: #1d4ed8; }
.status-ready { background: #dcfce7; color: #15803d; }
.status-released { background: #f3f4f6; color: #6b7280; }
.status-filed { background: #fdf4ff; color: #7e22ce; }

/* ── GRID LAYOUTS ── */
.two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.three-col-dash { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }

/* ── COMPLAINT STATUS ── */
.complaint-item {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 12px 0;
  border-bottom: 1px solid var(--border);
}
.complaint-item:last-child { border-bottom: none; }
.complaint-num {
  font-size: 11px;
  font-weight: 700;
  color: var(--text-muted);
  font-family: 'DM Sans', sans-serif;
  background: var(--bg);
  padding: 4px 8px;
  border-radius: 6px;
  white-space: nowrap;
}
.complaint-info { flex: 1; min-width: 0; }
.complaint-title {
  font-size: 13px;
  font-weight: 600;
  color: var(--text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.complaint-date { font-size: 11px; color: var(--text-muted); margin-top: 2px; }

/* ── NOTIFICATION DROPDOWN ── */
.notif-dropdown {
  display: none;
  position: absolute;
  top: calc(var(--topbar-h) - 4px);
  right: 60px;
  width: 340px;
  background: var(--surface);
  border-radius: var(--radius);
  box-shadow: var(--shadow-lg);
  border: 1px solid var(--border);
  z-index: 1100;
  overflow: hidden;
  animation: slideDown 0.18s ease;
}
.notif-dropdown.open { display: block; }
@keyframes slideDown {
  from { opacity: 0; transform: translateY(-8px); }
  to { opacity: 1; transform: translateY(0); }
}
.notif-header {
  padding: 14px 16px;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.notif-header-title { font-size: 14px; font-weight: 700; color: var(--primary); }
.notif-mark-read { font-size: 12px; color: var(--accent); cursor: pointer; font-weight: 600; }
.notif-item {
  padding: 12px 16px;
  display: flex;
  gap: 12px;
  border-bottom: 1px solid var(--border);
  cursor: pointer;
  transition: background var(--transition);
}
.notif-item:last-child { border-bottom: none; }
.notif-item:hover { background: var(--bg); }
.notif-item.unread { background: #fffbf0; }
.notif-item-icon {
  width: 36px; height: 36px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  flex-shrink: 0;
}
.notif-item-icon.green { background: #f0fdf4; color: #16a34a; }
.notif-item-icon.amber { background: var(--accent-pale); color: var(--accent); }
.notif-item-icon.blue { background: #eff6ff; color: #2563eb; }
.notif-item-content { flex: 1; min-width: 0; }
.notif-item-title { font-size: 12.5px; font-weight: 600; color: var(--text); }
.notif-item-desc { font-size: 11.5px; color: var(--text-muted); margin-top: 2px; line-height: 1.4; }
.notif-item-time { font-size: 10.5px; color: var(--text-light); margin-top: 3px; }

/* ── PAGE VIEWS ── */
.page-view { display: none; }
.page-view.active { display: block; }

/* ── FORM STYLES ── */
.form-section { margin-bottom: 28px; }
.form-section-title {
  font-size: 14px;
  font-weight: 700;
  color: var(--primary);
  margin-bottom: 16px;
  padding-bottom: 10px;
  border-bottom: 2px solid var(--accent-pale);
  display: flex;
  align-items: center;
  gap: 8px;
}
.form-section-title i { color: var(--accent); }
.form-label { font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 6px; display: block; }
.form-control, .form-select {
  width: 100%;
  padding: 11px 14px;
  border: 1.5px solid var(--border);
  border-radius: 10px;
  font-size: 13.5px;
  font-family: 'DM Sans', sans-serif;
  color: var(--text);
  background: var(--surface);
  transition: border-color var(--transition), box-shadow var(--transition);
  min-height: 44px;
  outline: none;
}
.form-control:focus, .form-select:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(11,37,69,0.08);
}
textarea.form-control { resize: vertical; min-height: 100px; }

.btn-primary {
  background: var(--primary);
  color: #fff;
  border: none;
  padding: 11px 24px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 44px;
  transition: all var(--transition);
  font-family: 'DM Sans', sans-serif;
}
.btn-primary:hover { background: var(--primary-light); transform: translateY(-1px); box-shadow: var(--shadow-md); }

.btn-secondary {
  background: var(--bg);
  color: var(--text);
  border: 1.5px solid var(--border);
  padding: 11px 24px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 44px;
  transition: all var(--transition);
  font-family: 'DM Sans', sans-serif;
}
.btn-secondary:hover { border-color: var(--primary); color: var(--primary); }

/* ── PROFILE COMPLETION ── */
.completion-card {
  background: linear-gradient(135deg, var(--accent-pale) 0%, #fff8e8 100%);
  border: 1.5px solid rgba(201,150,30,0.25);
  border-radius: var(--radius);
  padding: 20px;
  margin-bottom: 20px;
}
.completion-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}
.completion-title { font-size: 14px; font-weight: 700; color: var(--primary); }
.completion-pct { font-family: 'Fraunces', serif; font-size: 22px; font-weight: 600; color: var(--accent); }
.completion-bar-outer {
  height: 8px;
  background: rgba(201,150,30,0.2);
  border-radius: 4px;
  overflow: hidden;
  margin-bottom: 10px;
}
.completion-bar-inner {
  height: 100%;
  background: var(--accent);
  border-radius: 4px;
  transition: width 1s ease;
}
.completion-steps { display: flex; gap: 14px; flex-wrap: wrap; }
.completion-step {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 11.5px;
  color: var(--text-muted);
}
.completion-step i { font-size: 14px; }
.completion-step.done i { color: #16a34a; }
.completion-step.todo i { color: var(--text-light); }

/* ── BOTTOM NAV ── */
.bottom-nav {
  display: none;
  position: fixed;
  bottom: 0; left: 0; right: 0;
  background: var(--surface);
  border-top: 1px solid var(--border);
  z-index: 950;
  padding: 6px 0 calc(6px + env(safe-area-inset-bottom));
  box-shadow: 0 -4px 20px rgba(0,0,0,0.08);
}
.bottom-nav-inner {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
}
.bottom-nav-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 3px;
  padding: 6px 4px;
  cursor: pointer;
  color: var(--text-light);
  transition: color var(--transition);
  text-decoration: none;
  min-height: 52px;
  justify-content: center;
  position: relative;
}
.bottom-nav-item:hover, .bottom-nav-item.active { color: var(--primary); }
.bottom-nav-item.active::before {
  content: '';
  position: absolute;
  top: 0; left: 20%; right: 20%;
  height: 2px;
  background: var(--accent);
  border-radius: 0 0 2px 2px;
}
.bottom-nav-item i { font-size: 22px; }
.bottom-nav-item span { font-size: 10px; font-weight: 600; }
.bottom-nav-badge {
  position: absolute;
  top: 5px;
  right: calc(50% - 20px);
  width: 16px; height: 16px;
  background: var(--accent);
  color: var(--primary);
  font-size: 9px;
  font-weight: 700;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid var(--surface);
}

/* ── FILTER TABS ── */
.filter-tabs {
  display: flex;
  gap: 6px;
  margin-bottom: 18px;
  flex-wrap: wrap;
}
.filter-tab {
  padding: 8px 16px;
  border-radius: 20px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  border: 1.5px solid var(--border);
  background: var(--surface);
  color: var(--text-muted);
  transition: all var(--transition);
  min-height: 44px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.filter-tab:hover { border-color: var(--primary); color: var(--primary); }
.filter-tab.active { background: var(--primary); color: #fff; border-color: var(--primary); }
.filter-tab .tab-count {
  background: var(--accent);
  color: var(--primary);
  font-size: 10px;
  padding: 1px 6px;
  border-radius: 10px;
  font-weight: 700;
}
.filter-tab.active .tab-count { background: rgba(255,255,255,0.25); color: #fff; }

/* ── OFFICIALS ── */
.official-card {
  background: var(--surface);
  border-radius: var(--radius);
  padding: 20px;
  text-align: center;
  border: 1px solid var(--border);
  box-shadow: var(--shadow-sm);
  transition: transform var(--transition), box-shadow var(--transition);
}
.official-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
.official-avatar {
  width: 70px; height: 70px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--primary), #1a4a8a);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 12px;
  font-size: 26px;
  color: var(--accent);
  border: 3px solid var(--accent-pale);
}
.official-name { font-size: 14px; font-weight: 700; color: var(--primary); }
.official-position { font-size: 12px; color: var(--text-muted); margin-top: 3px; }
.official-contact { font-size: 12px; color: var(--accent); margin-top: 6px; text-decoration: none; }

/* ── SERVICES ── */
.service-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 0;
  border-bottom: 1px solid var(--border);
  gap: 12px;
}
.service-item:last-child { border-bottom: none; }
.service-name { font-size: 13.5px; font-weight: 600; color: var(--text); }
.service-desc { font-size: 12px; color: var(--text-muted); margin-top: 2px; }
.service-fee {
  font-family: 'Fraunces', serif;
  font-size: 16px;
  font-weight: 600;
  color: var(--primary);
  white-space: nowrap;
  flex-shrink: 0;
}

/* ── CONTACT ── */
.hotline-item {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px;
  background: var(--bg);
  border-radius: 12px;
  margin-bottom: 10px;
}
.hotline-icon {
  width: 44px; height: 44px;
  background: var(--primary);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--accent);
  font-size: 20px;
  flex-shrink: 0;
}
.hotline-label { font-size: 12px; color: var(--text-muted); }
.hotline-number { font-size: 15px; font-weight: 700; color: var(--primary); }

/* ── EMPTY STATE ── */
.empty-state {
  text-align: center;
  padding: 40px 20px;
}
.empty-icon { font-size: 48px; color: var(--text-light); margin-bottom: 12px; }
.empty-title { font-size: 15px; font-weight: 700; color: var(--text-muted); }
.empty-sub { font-size: 13px; color: var(--text-light); margin-top: 4px; }

/* ── UTILITIES ── */
.d-grid { display: grid; }
.gap-16 { gap: 16px; }
.gap-20 { gap: 20px; }
.mt-16 { margin-top: 16px; }
.mt-20 { margin-top: 20px; }
.mb-0 { margin-bottom: 0; }
.row-gap { row-gap: 20px; }
.text-accent { color: var(--accent); }
.fw-600 { font-weight: 600; }

/* ── RESPONSIVE ── */
@media (max-width: 1200px) {
  .stats-grid { grid-template-columns: repeat(2, 1fr); }
  .quick-actions-grid { grid-template-columns: repeat(4, 1fr); }
}

@media (max-width: 991px) {
  .hamburger-btn { display: flex; }
  .topbar-search { display: none; }
  .sidebar { transform: translateX(-100%); }
  .sidebar.open { transform: translateX(0); }
  .main-content { margin-left: 0; }
  .bottom-nav { display: block; }
  .page-content { padding: 20px 16px 90px; }
  .three-col-dash { grid-template-columns: 1fr; }
  .two-col { grid-template-columns: 1fr; }
  .welcome-card { padding: 22px 20px; }
  .welcome-title { font-size: 19px; }
}

@media (max-width: 767px) {
  .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
  .quick-actions-grid { grid-template-columns: repeat(2, 1fr); }
  .page-title { font-size: 20px; }
  .brand-sub { display: none; }
  .topbar-btn:not(:last-of-type):not(.has-badge) { display: none; }
}

@media (max-width: 480px) {
  .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
  .stat-value { font-size: 22px; }
  .welcome-actions { flex-direction: column; }
  .welcome-actions .btn-accent,
  .welcome-actions .btn-outline-white { width: 100%; justify-content: center; }
  .topbar { padding: 0 12px; gap: 8px; }
}

@media (max-width: 360px) {
  .stats-grid { grid-template-columns: 1fr; }
  .quick-actions-grid { grid-template-columns: repeat(2, 1fr); }
}

/* ── ANIMATIONS ── */
@keyframes fadeInUp {
  from { opacity: 0; transform: translateY(16px); }
  to { opacity: 1; transform: translateY(0); }
}
.anim-fade { animation: fadeInUp 0.4s ease both; }
.anim-delay-1 { animation-delay: 0.05s; }
.anim-delay-2 { animation-delay: 0.10s; }
.anim-delay-3 { animation-delay: 0.15s; }
.anim-delay-4 { animation-delay: 0.20s; }

/* Loading shimmer for skeleton states */
@keyframes shimmer {
  0% { background-position: -200% 0; }
  100% { background-position: 200% 0; }
}
</style>
</head>

<body>

<!-- TOPBAR -->
<nav class="topbar">
  <button class="hamburger-btn" id="hamburgerBtn" aria-label="Toggle menu">
    <i class="bi bi-list"></i>
  </button>

  <a href="#" class="topbar-brand" onclick="showPage('dashboard'); return false;">
    <div class="brand-seal">★</div>
    <div class="brand-text">
      <span class="brand-name">Barangay Sta. Rosa 1</span>
      <span class="brand-sub">Resident Self-Service Portal</span>
    </div>
  </a>

  <div class="topbar-spacer"></div>

  <div class="topbar-search">
    <i class="bi bi-search"></i>
    <input type="text" placeholder="Search services, announcements…">
  </div>

  <div class="topbar-actions">
    <button class="topbar-btn has-badge" id="notifBtn" onclick="toggleNotif()" aria-label="Notifications">
      <i class="bi bi-bell-fill"></i>
      <span class="notif-badge-count">3</span>
    </button>
    <button class="topbar-btn" onclick="showPage('contact')" aria-label="Help">
      <i class="bi bi-question-circle"></i>
    </button>
    <button class="avatar-btn" onclick="showPage('profile')" aria-label="My Account">JD</button>
  </div>
</nav>

<!-- NOTIFICATION DROPDOWN -->
<div class="notif-dropdown" id="notifDropdown">
  <div class="notif-header">
    <span class="notif-header-title">Notifications</span>
    <span class="notif-mark-read" onclick="clearNotifs()">Mark all read</span>
  </div>
  <div class="notif-item unread">
    <div class="notif-item-icon green"><i class="bi bi-check-circle-fill"></i></div>
    <div class="notif-item-content">
      <div class="notif-item-title">Document Ready for Pick-up</div>
      <div class="notif-item-desc">Your Barangay Clearance (REQ-2025-0042) is now ready for pick-up.</div>
      <div class="notif-item-time">Today, 10:32 AM</div>
    </div>
  </div>
  <div class="notif-item unread">
    <div class="notif-item-icon amber"><i class="bi bi-megaphone-fill"></i></div>
    <div class="notif-item-content">
      <div class="notif-item-title">New Announcement</div>
      <div class="notif-item-desc">Community Clean-Up Drive scheduled for June 14, 2025.</div>
      <div class="notif-item-time">Yesterday, 2:15 PM</div>
    </div>
  </div>
  <div class="notif-item unread">
    <div class="notif-item-icon blue"><i class="bi bi-file-earmark-text-fill"></i></div>
    <div class="notif-item-content">
      <div class="notif-item-title">Complaint Update</div>
      <div class="notif-item-desc">Case BLT-2025-018 has been updated — mediation scheduled.</div>
      <div class="notif-item-time">June 1, 9:00 AM</div>
    </div>
  </div>
</div>

<!-- SIDEBAR -->
<nav class="sidebar" id="sidebar">
  <div class="sidebar-user">
    <div class="sidebar-avatar">JD</div>
    <div class="sidebar-user-info">
      <div class="sidebar-user-name">Juan Dela Cruz</div>
      <div class="sidebar-user-role">Resident • Purok 3</div>
    </div>
  </div>

  <div class="profile-progress">
    <div class="progress-label">
      <span>Profile Completion</span>
      <span>75%</span>
    </div>
    <div class="progress-bar-outer">
      <div class="progress-bar-inner" style="width:75%"></div>
    </div>
  </div>

  <!-- HOME -->
  <div class="nav-section">
    <div class="nav-section-label">Home</div>
    <a class="nav-link active" onclick="showPage('dashboard')" id="nav-dashboard">
      <i class="bi bi-grid-1x2-fill"></i> Dashboard
    </a>
  </div>

  <!-- DOCUMENTS -->
  <div class="nav-section">
    <div class="nav-section-label">Documents</div>
    <a class="nav-link" onclick="showPage('request')" id="nav-request">
      <i class="bi bi-file-earmark-plus"></i> Request a Document
    </a>
    <a class="nav-link" onclick="toggleSubmenu('submenu-requests', this)" id="nav-myrequests">
      <i class="bi bi-folder2-open"></i> My Requests
      <i class="bi bi-chevron-right nav-chevron"></i>
    </a>
    <div class="nav-submenu" id="submenu-requests">
      <a class="nav-link" onclick="showPage('requests-all')"><i class="bi bi-list-ul"></i> All Requests</a>
      <a class="nav-link" onclick="showPage('requests-pending')"><i class="bi bi-clock"></i> Pending / Processing</a>
      <a class="nav-link" onclick="showPage('requests-pickup')"><i class="bi bi-bag-check"></i> Ready for Pick-up <span class="nav-badge">1</span></a>
      <a class="nav-link" onclick="showPage('requests-done')"><i class="bi bi-check2-all"></i> Released / Done</a>
    </div>
    <a class="nav-link" onclick="showPage('verify')" id="nav-verify">
      <i class="bi bi-patch-check"></i> Verify My Document
    </a>
  </div>

  <!-- BLOTTER -->
  <div class="nav-section">
    <div class="nav-section-label">Blotter / Complaints</div>
    <a class="nav-link" onclick="showPage('file-complaint')" id="nav-file-complaint">
      <i class="bi bi-exclamation-triangle"></i> File a Complaint
    </a>
    <a class="nav-link" onclick="showPage('my-cases')" id="nav-my-cases">
      <i class="bi bi-journal-text"></i> My Cases
    </a>
  </div>

  <!-- BARANGAY INFO -->
  <div class="nav-section">
    <div class="nav-section-label">Barangay Information</div>
    <a class="nav-link" onclick="showPage('announcements')" id="nav-announcements">
      <i class="bi bi-megaphone"></i> Announcements
    </a>
    <a class="nav-link" onclick="showPage('services')" id="nav-services">
      <i class="bi bi-card-list"></i> Services & Fees
    </a>
    <a class="nav-link" onclick="showPage('officials')" id="nav-officials">
      <i class="bi bi-people"></i> Officials Directory
    </a>
    <a class="nav-link" onclick="showPage('contact')" id="nav-contact">
      <i class="bi bi-telephone"></i> Contact & Hotlines
    </a>
  </div>

  <!-- ACCOUNT -->
  <div class="nav-section">
    <div class="nav-section-label">Account</div>
    <a class="nav-link" onclick="showPage('profile')" id="nav-profile">
      <i class="bi bi-person-circle"></i> My Profile
    </a>
  </div>

  <div class="sidebar-footer">
    <a href="../includes/logout.php" class="nav-link" style="color:rgba(255,255,255,0.4)">
      <i class="bi bi-box-arrow-right"></i> Sign Out
    </a>
  </div>
</nav>

<!-- SIDEBAR OVERLAY -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- MAIN CONTENT -->
<main class="main-content">
<div class="page-content">

<!-- ═══ DASHBOARD ═══ -->
<div class="page-view active" id="page-dashboard">
  <div class="welcome-card anim-fade">
    <div class="welcome-badge"><i class="bi bi-star-fill"></i> Barangay Sta. Rosa 1</div>
    <div class="welcome-title">Good morning, Juan! 👋</div>
    <p class="welcome-sub">Welcome to your self-service portal. Manage your documents, complaints, and stay updated with the latest barangay announcements.</p>
    <div class="welcome-date"><i class="bi bi-calendar3"></i> Tuesday, June 3, 2025 &nbsp;·&nbsp; <i class="bi bi-geo-alt"></i> Purok 3, Sta. Rosa 1</div>
    <div class="welcome-actions">
      <a class="btn-accent" onclick="showPage('request')"><i class="bi bi-file-earmark-plus"></i> Request Document</a>
      <a class="btn-outline-white" onclick="showPage('file-complaint')"><i class="bi bi-exclamation-triangle"></i> File Complaint</a>
    </div>
  </div>

  <div class="completion-card anim-fade anim-delay-1">
    <div class="completion-header">
      <div class="completion-title"><i class="bi bi-person-check text-accent"></i>&nbsp; Complete Your Profile</div>
      <div class="completion-pct">75%</div>
    </div>
    <div class="completion-bar-outer">
      <div class="completion-bar-inner" style="width:75%"></div>
    </div>
    <div class="completion-steps">
      <span class="completion-step done"><i class="bi bi-check-circle-fill"></i> Personal Info</span>
      <span class="completion-step done"><i class="bi bi-check-circle-fill"></i> Contact Details</span>
      <span class="completion-step done"><i class="bi bi-check-circle-fill"></i> Household Members</span>
      <span class="completion-step todo"><i class="bi bi-circle"></i> Upload Valid ID</span>
    </div>
  </div>

  <!-- STAT CARDS -->
  <div class="stats-grid anim-fade anim-delay-2">
    <div class="stat-card">
      <div class="stat-icon amber"><i class="bi bi-file-earmark-text"></i></div>
      <div>
        <div class="stat-value">4</div>
        <div class="stat-label">Document Requests</div>
      </div>
      <div class="stat-trend up"><i class="bi bi-arrow-up-short"></i> 2 this month</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon green"><i class="bi bi-bag-check"></i></div>
      <div>
        <div class="stat-value">1</div>
        <div class="stat-label">Ready for Pick-up</div>
      </div>
      <div class="stat-trend up"><i class="bi bi-dot"></i> Action needed</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon blue"><i class="bi bi-journal-text"></i></div>
      <div>
        <div class="stat-value">2</div>
        <div class="stat-label">Active Cases</div>
      </div>
      <div class="stat-trend down"><i class="bi bi-clock"></i> In mediation</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon red"><i class="bi bi-megaphone"></i></div>
      <div>
        <div class="stat-value">5</div>
        <div class="stat-label">New Announcements</div>
      </div>
      <div class="stat-trend up"><i class="bi bi-dot"></i> Unread</div>
    </div>
  </div>

  <!-- MAIN GRID -->
  <div class="three-col-dash" style="margin-top:20px">
    <div style="display:flex; flex-direction:column; gap:20px">

      <!-- RECENT REQUESTS TABLE -->
      <div class="card anim-fade anim-delay-3">
        <div class="card-header">
          <div class="card-title"><i class="bi bi-file-earmark-text"></i> Document Request Status</div>
          <a class="card-link" onclick="showPage('requests-all')">View all →</a>
        </div>
        <div class="card-body" style="padding:0">
          <div class="table-responsive">
            <table>
              <thead>
                <tr>
                  <th>Reference</th>
                  <th>Document Type</th>
                  <th>Requested</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td><strong>REQ-2025-0042</strong></td>
                  <td>Barangay Clearance</td>
                  <td>May 30, 2025</td>
                  <td><span class="status-badge status-ready"><i class="bi bi-bag-check"></i> Ready for Pick-up</span></td>
                </tr>
                <tr>
                  <td><strong>REQ-2025-0038</strong></td>
                  <td>Certificate of Indigency</td>
                  <td>May 26, 2025</td>
                  <td><span class="status-badge status-processing"><i class="bi bi-arrow-repeat"></i> Processing</span></td>
                </tr>
                <tr>
                  <td><strong>REQ-2025-0031</strong></td>
                  <td>Barangay ID</td>
                  <td>May 18, 2025</td>
                  <td><span class="status-badge status-released"><i class="bi bi-check-all"></i> Released</span></td>
                </tr>
                <tr>
                  <td><strong>REQ-2025-0019</strong></td>
                  <td>Business Clearance</td>
                  <td>May 4, 2025</td>
                  <td><span class="status-badge status-released"><i class="bi bi-check-all"></i> Released</span></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- COMPLAINT STATUS -->
      <div class="card anim-fade anim-delay-4">
        <div class="card-header">
          <div class="card-title"><i class="bi bi-journal-text"></i> Complaint Status</div>
          <a class="card-link" onclick="showPage('my-cases')">View cases →</a>
        </div>
        <div class="card-body">
          <div class="complaint-item">
            <div class="complaint-num">BLT-2025-018</div>
            <div class="complaint-info">
              <div class="complaint-title">Noise Complaint — Purok 3</div>
              <div class="complaint-date">Filed May 28, 2025</div>
            </div>
            <span class="status-badge status-processing">Mediation</span>
          </div>
          <div class="complaint-item">
            <div class="complaint-num">BLT-2025-011</div>
            <div class="complaint-info">
              <div class="complaint-title">Property Boundary Dispute</div>
              <div class="complaint-date">Filed May 10, 2025</div>
            </div>
            <span class="status-badge status-pending">Pending</span>
          </div>
        </div>
      </div>
    </div>

    <!-- QUICK ACTIONS + ANNOUNCEMENTS -->
    <div style="display:flex; flex-direction:column; gap:20px">
      <div class="card">
        <div class="card-header">
          <div class="card-title"><i class="bi bi-lightning-charge"></i> Quick Actions</div>
        </div>
        <div class="card-body">
          <div class="quick-actions-grid" style="grid-template-columns: repeat(2,1fr)">
            <a class="qa-item" onclick="showPage('request')">
              <div class="qa-icon"><i class="bi bi-file-earmark-plus"></i></div>
              <span class="qa-label">Request Document</span>
            </a>
            <a class="qa-item" onclick="showPage('file-complaint')">
              <div class="qa-icon"><i class="bi bi-exclamation-triangle"></i></div>
              <span class="qa-label">File Complaint</span>
            </a>
            <a class="qa-item" onclick="showPage('verify')">
              <div class="qa-icon"><i class="bi bi-patch-check"></i></div>
              <span class="qa-label">Verify Document</span>
            </a>
            <a class="qa-item" onclick="showPage('announcements')">
              <div class="qa-icon"><i class="bi bi-megaphone"></i></div>
              <span class="qa-label">Announcements</span>
            </a>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <div class="card-title"><i class="bi bi-megaphone"></i> Recent Announcements</div>
          <a class="card-link" onclick="showPage('announcements')">All →</a>
        </div>
        <div class="card-body">
          <div class="announcement-item">
            <div class="announcement-dot"></div>
            <div>
              <div class="announcement-title">Community Clean-Up Drive <span class="announcement-tag tag-event">Event</span></div>
              <div class="announcement-meta">June 14, 2025 · Posted Jun 1</div>
            </div>
          </div>
          <div class="announcement-item">
            <div class="announcement-dot"></div>
            <div>
              <div class="announcement-title">Suspension of Services <span class="announcement-tag tag-urgent">Urgent</span></div>
              <div class="announcement-meta">June 10 (Holiday) · Posted May 30</div>
            </div>
          </div>
          <div class="announcement-item">
            <div class="announcement-dot"></div>
            <div>
              <div class="announcement-title">New Senior Citizen Benefits <span class="announcement-tag tag-info">Info</span></div>
              <div class="announcement-meta">Posted May 28, 2025</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ═══ REQUEST A DOCUMENT ═══ -->
<div class="page-view" id="page-request">
  <div class="page-header">
    <div class="page-title">Request a Document</div>
    <div class="page-subtitle">Fill out the form below to request an official barangay document.</div>
  </div>
  <div class="card">
    <div class="card-body" style="padding:28px">
      <div class="form-section">
        <div class="form-section-title"><i class="bi bi-file-earmark-text"></i> Document Information</div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px" class="form-grid-2">
          <div>
            <label class="form-label">Document Type *</label>
            <select class="form-select">
              <option value="">— Select Document Type —</option>
              <option>Barangay Clearance</option>
              <option>Certificate of Indigency</option>
              <option>Certificate of Residency</option>
              <option>Business Clearance</option>
              <option>Barangay ID</option>
              <option>Certificate of Good Moral Character</option>
              <option>Lot Ownership Certification</option>
            </select>
          </div>
          <div>
            <label class="form-label">Purpose *</label>
            <select class="form-select">
              <option value="">— Select Purpose —</option>
              <option>Employment</option>
              <option>Business Permit</option>
              <option>School Requirement</option>
              <option>Government Transaction</option>
              <option>Bank Requirement</option>
              <option>Personal Use</option>
            </select>
          </div>
        </div>
        <div style="margin-top:16px">
          <label class="form-label">Specify Other Purpose (if applicable)</label>
          <input type="text" class="form-control" placeholder="e.g., For PhilHealth application">
        </div>
      </div>

      <div class="form-section">
        <div class="form-section-title"><i class="bi bi-person"></i> Personal Information</div>
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px" class="form-grid-3">
          <div>
            <label class="form-label">First Name *</label>
            <input type="text" class="form-control" value="Juan">
          </div>
          <div>
            <label class="form-label">Middle Name</label>
            <input type="text" class="form-control" value="Santos">
          </div>
          <div>
            <label class="form-label">Last Name *</label>
            <input type="text" class="form-control" value="Dela Cruz">
          </div>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-top:16px" class="form-grid-2">
          <div>
            <label class="form-label">Date of Birth *</label>
            <input type="date" class="form-control">
          </div>
          <div>
            <label class="form-label">Contact Number *</label>
            <input type="tel" class="form-control" placeholder="09XX-XXX-XXXX">
          </div>
        </div>
        <div style="margin-top:16px">
          <label class="form-label">Complete Address *</label>
          <input type="text" class="form-control" placeholder="House No., Street, Purok/Sitio, Barangay Sta. Rosa 1">
        </div>
      </div>

      <div class="form-section">
        <div class="form-section-title"><i class="bi bi-upload"></i> Supporting Documents</div>
        <div style="border:2px dashed var(--border); border-radius:12px; padding:28px; text-align:center; cursor:pointer; transition: border-color var(--transition)" onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
          <div style="font-size:36px; color:var(--accent); margin-bottom:8px"><i class="bi bi-cloud-upload"></i></div>
          <div style="font-weight:600; color:var(--text); margin-bottom:4px">Upload Valid ID</div>
          <div style="font-size:12px; color:var(--text-muted)">PNG, JPG or PDF · Max 5MB</div>
          <input type="file" style="display:none" id="fileUpload">
          <button class="btn-secondary" style="margin-top:12px" onclick="document.getElementById('fileUpload').click()"><i class="bi bi-folder2-open"></i> Browse File</button>
        </div>
      </div>

      <div style="display:flex; gap:12px; justify-content:flex-end; flex-wrap:wrap">
        <button class="btn-secondary" onclick="showPage('dashboard')"><i class="bi bi-x"></i> Cancel</button>
        <button class="btn-primary" onclick="submitRequest()"><i class="bi bi-send"></i> Submit Request</button>
      </div>
    </div>
  </div>
</div>

<!-- ═══ MY REQUESTS ═══ -->
<div class="page-view" id="page-requests-all">
  <div class="page-header">
    <div class="page-title">My Requests</div>
    <div class="page-subtitle">Track the status of all your document requests.</div>
  </div>
  <div class="filter-tabs">
    <div class="filter-tab active">All <span class="tab-count">4</span></div>
    <div class="filter-tab">Pending <span class="tab-count">0</span></div>
    <div class="filter-tab">Processing <span class="tab-count">1</span></div>
    <div class="filter-tab" style="position:relative">Ready for Pick-up <span class="tab-count">1</span></div>
    <div class="filter-tab">Released <span class="tab-count">2</span></div>
  </div>
  <div class="card">
    <div class="table-responsive">
      <table>
        <thead>
          <tr>
            <th>Reference No.</th><th>Document Type</th><th>Purpose</th><th>Date Filed</th><th>Status</th><th>Action</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><strong>REQ-2025-0042</strong></td><td>Barangay Clearance</td><td>Employment</td><td>May 30, 2025</td>
            <td><span class="status-badge status-ready"><i class="bi bi-bag-check"></i> Ready for Pick-up</span></td>
            <td><button class="btn-secondary" style="padding:6px 12px; font-size:12px; min-height:36px"><i class="bi bi-eye"></i> View</button></td>
          </tr>
          <tr>
            <td><strong>REQ-2025-0038</strong></td><td>Certificate of Indigency</td><td>School Req.</td><td>May 26, 2025</td>
            <td><span class="status-badge status-processing"><i class="bi bi-arrow-repeat"></i> Processing</span></td>
            <td><button class="btn-secondary" style="padding:6px 12px; font-size:12px; min-height:36px"><i class="bi bi-eye"></i> View</button></td>
          </tr>
          <tr>
            <td><strong>REQ-2025-0031</strong></td><td>Barangay ID</td><td>Personal Use</td><td>May 18, 2025</td>
            <td><span class="status-badge status-released"><i class="bi bi-check-all"></i> Released</span></td>
            <td><button class="btn-secondary" style="padding:6px 12px; font-size:12px; min-height:36px"><i class="bi bi-eye"></i> View</button></td>
          </tr>
          <tr>
            <td><strong>REQ-2025-0019</strong></td><td>Business Clearance</td><td>Business Permit</td><td>May 4, 2025</td>
            <td><span class="status-badge status-released"><i class="bi bi-check-all"></i> Released</span></td>
            <td><button class="btn-secondary" style="padding:6px 12px; font-size:12px; min-height:36px"><i class="bi bi-eye"></i> View</button></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ═══ FILE COMPLAINT ═══ -->
<div class="page-view" id="page-file-complaint">
  <div class="page-header">
    <div class="page-title">File a Complaint</div>
    <div class="page-subtitle">Submit a formal complaint or blotter report to the Barangay.</div>
  </div>
  <div class="card">
    <div class="card-body" style="padding:28px">
      <div style="background:var(--accent-pale); border:1px solid rgba(201,150,30,0.3); border-radius:12px; padding:14px 16px; margin-bottom:24px; font-size:13px; color:var(--text); display:flex; gap:10px; align-items:flex-start">
        <i class="bi bi-info-circle-fill" style="color:var(--accent); font-size:18px; flex-shrink:0; margin-top:1px"></i>
        <div>All complaints are handled with confidentiality. The Barangay Justice System (BJS) will process your report and schedule mediation/conciliation as needed. For emergencies, call our hotline immediately.</div>
      </div>
      <div class="form-section">
        <div class="form-section-title"><i class="bi bi-exclamation-triangle"></i> Complaint Details</div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px">
          <div>
            <label class="form-label">Complaint Type *</label>
            <select class="form-select">
              <option value="">— Select Type —</option>
              <option>Noise Complaint</option>
              <option>Property Dispute</option>
              <option>Physical Altercation</option>
              <option>Vandalism / Property Damage</option>
              <option>Theft</option>
              <option>Illegal Structures</option>
              <option>Other</option>
            </select>
          </div>
          <div>
            <label class="form-label">Date of Incident *</label>
            <input type="date" class="form-control">
          </div>
        </div>
        <div style="margin-top:16px">
          <label class="form-label">Respondent Name *</label>
          <input type="text" class="form-control" placeholder="Full name of the person you are filing against">
        </div>
        <div style="margin-top:16px">
          <label class="form-label">Incident Location *</label>
          <input type="text" class="form-control" placeholder="Exact address or landmark">
        </div>
        <div style="margin-top:16px">
          <label class="form-label">Narration of Incident *</label>
          <textarea class="form-control" rows="5" placeholder="Provide a clear and detailed account of what happened, including the events leading to the incident…"></textarea>
        </div>
      </div>
      <div style="display:flex; gap:12px; justify-content:flex-end; flex-wrap:wrap">
        <button class="btn-secondary" onclick="showPage('dashboard')"><i class="bi bi-x"></i> Cancel</button>
        <button class="btn-primary" onclick="alert('Complaint submitted successfully!\nReference: BLT-2025-025')"><i class="bi bi-send"></i> Submit Complaint</button>
      </div>
    </div>
  </div>
</div>

<!-- ═══ MY CASES ═══ -->
<div class="page-view" id="page-my-cases">
  <div class="page-header">
    <div class="page-title">My Cases</div>
    <div class="page-subtitle">View all blotter and complaint cases you have filed.</div>
  </div>
  <div class="card">
    <div class="table-responsive">
      <table>
        <thead>
          <tr><th>Case No.</th><th>Type</th><th>Respondent</th><th>Date Filed</th><th>Status</th><th>Action</th></tr>
        </thead>
        <tbody>
          <tr>
            <td><strong>BLT-2025-018</strong></td><td>Noise Complaint</td><td>Pedro Santos</td><td>May 28, 2025</td>
            <td><span class="status-badge status-processing"><i class="bi bi-people"></i> Mediation</span></td>
            <td><button class="btn-secondary" style="padding:6px 12px; font-size:12px; min-height:36px"><i class="bi bi-eye"></i> View</button></td>
          </tr>
          <tr>
            <td><strong>BLT-2025-011</strong></td><td>Property Dispute</td><td>Maria Garcia</td><td>May 10, 2025</td>
            <td><span class="status-badge status-pending"><i class="bi bi-clock"></i> Pending</span></td>
            <td><button class="btn-secondary" style="padding:6px 12px; font-size:12px; min-height:36px"><i class="bi bi-eye"></i> View</button></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ═══ ANNOUNCEMENTS ═══ -->
<div class="page-view" id="page-announcements">
  <div class="page-header">
    <div class="page-title">Announcements</div>
    <div class="page-subtitle">Official notices, events, and updates from Barangay Sta. Rosa 1.</div>
  </div>
  <div style="display:flex; flex-direction:column; gap:16px">
    <div class="card">
      <div class="card-body">
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap">
          <div>
            <span class="announcement-tag tag-event" style="margin-left:0; margin-bottom:8px; display:inline-block">Event</span>
            <div style="font-size:16px; font-weight:700; color:var(--primary)">Community Clean-Up Drive</div>
            <div style="font-size:13px; color:var(--text-muted); margin-top:4px"><i class="bi bi-calendar3"></i> June 14, 2025, 7:00 AM · <i class="bi bi-geo-alt"></i> Basketball Court, Purok 2</div>
            <p style="font-size:13.5px; color:var(--text); margin-top:10px; line-height:1.6">Join our community clean-up drive this June 14. All residents are encouraged to participate. Bring your own cleaning materials. Free breakfast will be provided by the Barangay.</p>
          </div>
          <div style="font-size:11px; color:var(--text-light); white-space:nowrap">Posted Jun 1, 2025</div>
        </div>
      </div>
    </div>
    <div class="card">
      <div class="card-body">
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap">
          <div>
            <span class="announcement-tag tag-urgent" style="margin-left:0; margin-bottom:8px; display:inline-block">Urgent</span>
            <div style="font-size:16px; font-weight:700; color:var(--primary)">Suspension of Barangay Services — June 10 (Holiday)</div>
            <div style="font-size:13px; color:var(--text-muted); margin-top:4px"><i class="bi bi-calendar3"></i> June 10, 2025</div>
            <p style="font-size:13.5px; color:var(--text); margin-top:10px; line-height:1.6">The Barangay Hall will be closed on June 10, 2025 in observance of the Araw ng Kalayaan (Independence Day). Services will resume on June 11.</p>
          </div>
          <div style="font-size:11px; color:var(--text-light); white-space:nowrap">Posted May 30, 2025</div>
        </div>
      </div>
    </div>
    <div class="card">
      <div class="card-body">
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap">
          <div>
            <span class="announcement-tag tag-info" style="margin-left:0; margin-bottom:8px; display:inline-block">Info</span>
            <div style="font-size:16px; font-weight:700; color:var(--primary)">New Senior Citizen Benefits Program</div>
            <div style="font-size:13px; color:var(--text-muted); margin-top:4px"><i class="bi bi-calendar3"></i> Effective June 1, 2025</div>
            <p style="font-size:13.5px; color:var(--text); margin-top:10px; line-height:1.6">The Barangay is now offering additional benefits to senior citizens ages 60 and above, including monthly food packs and free medical check-ups every first Friday of the month. Visit the Barangay Hall for registration.</p>
          </div>
          <div style="font-size:11px; color:var(--text-light); white-space:nowrap">Posted May 28, 2025</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ═══ SERVICES & FEES ═══ -->
<div class="page-view" id="page-services">
  <div class="page-header">
    <div class="page-title">Services & Fees</div>
    <div class="page-subtitle">Official schedule of fees for Barangay Sta. Rosa 1 services.</div>
  </div>
  <div class="two-col">
    <div class="card">
      <div class="card-header"><div class="card-title"><i class="bi bi-file-earmark-text"></i> Document Services</div></div>
      <div class="card-body">
        <div class="service-item">
          <div><div class="service-name">Barangay Clearance</div><div class="service-desc">For employment, business, etc. · 1–2 working days</div></div>
          <div class="service-fee">₱50.00</div>
        </div>
        <div class="service-item">
          <div><div class="service-name">Certificate of Indigency</div><div class="service-desc">For scholarship, hospital, government use</div></div>
          <div class="service-fee">Free</div>
        </div>
        <div class="service-item">
          <div><div class="service-name">Certificate of Residency</div><div class="service-desc">Proof of residence in Barangay Sta. Rosa 1</div></div>
          <div class="service-fee">₱30.00</div>
        </div>
        <div class="service-item">
          <div><div class="service-name">Business Clearance</div><div class="service-desc">Required for business permit renewal · 3–5 days</div></div>
          <div class="service-fee">₱200.00</div>
        </div>
        <div class="service-item">
          <div><div class="service-name">Barangay ID</div><div class="service-desc">Official barangay identification · 5–7 days</div></div>
          <div class="service-fee">₱100.00</div>
        </div>
        <div class="service-item">
          <div><div class="service-name">Good Moral Character Cert.</div><div class="service-desc">For employment and school requirements</div></div>
          <div class="service-fee">₱50.00</div>
        </div>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><div class="card-title"><i class="bi bi-journal-text"></i> Justice Services</div></div>
      <div class="card-body">
        <div class="service-item">
          <div><div class="service-name">Blotter Filing</div><div class="service-desc">Official record of incident</div></div>
          <div class="service-fee">Free</div>
        </div>
        <div class="service-item">
          <div><div class="service-name">Mediation / Conciliation</div><div class="service-desc">Katarungang Pambarangay process</div></div>
          <div class="service-fee">Free</div>
        </div>
        <div class="service-item">
          <div><div class="service-name">Certificate to File Action</div><div class="service-desc">CFA after failed mediation</div></div>
          <div class="service-fee">₱100.00</div>
        </div>
        <div class="service-item">
          <div><div class="service-name">Certified True Copy (Blotter)</div><div class="service-desc">Official certified copy of blotter entry</div></div>
          <div class="service-fee">₱50.00</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ═══ OFFICIALS DIRECTORY ═══ -->
<div class="page-view" id="page-officials">
  <div class="page-header">
    <div class="page-title">Officials Directory</div>
    <div class="page-subtitle">Meet the officials of Barangay Sta. Rosa 1.</div>
  </div>
  <div style="margin-bottom:20px">
    <div class="card" style="padding:22px; display:flex; align-items:center; gap:20px; flex-wrap:wrap">
      <div style="width:80px; height:80px; border-radius:50%; background:linear-gradient(135deg,var(--primary),#1a4a8a); display:flex; align-items:center; justify-content:center; color:var(--accent); font-size:32px; flex-shrink:0; border:4px solid var(--accent-pale)"><i class="bi bi-person-badge"></i></div>
      <div style="flex:1">
        <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.08em; color:var(--accent); font-weight:700; margin-bottom:4px">Punong Barangay</div>
        <div style="font-family:'Fraunces',serif; font-size:22px; font-weight:600; color:var(--primary)">Hon. Roberto C. Santos</div>
        <div style="font-size:13px; color:var(--text-muted); margin-top:4px">Serving Barangay Sta. Rosa 1 since 2019 · <a href="tel:09171234567" style="color:var(--accent)">0917-123-4567</a></div>
      </div>
    </div>
  </div>
  <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(180px,1fr)); gap:16px">
    <div class="official-card"><div class="official-avatar"><i class="bi bi-person"></i></div><div class="official-name">Maria T. Reyes</div><div class="official-position">Barangay Kagawad</div><a href="#" class="official-contact"><i class="bi bi-telephone"></i> Contact</a></div>
    <div class="official-card"><div class="official-avatar"><i class="bi bi-person"></i></div><div class="official-name">Jose A. Cruz</div><div class="official-position">Barangay Kagawad</div><a href="#" class="official-contact"><i class="bi bi-telephone"></i> Contact</a></div>
    <div class="official-card"><div class="official-avatar"><i class="bi bi-person"></i></div><div class="official-name">Ana M. Lim</div><div class="official-position">Barangay Kagawad</div><a href="#" class="official-contact"><i class="bi bi-telephone"></i> Contact</a></div>
    <div class="official-card"><div class="official-avatar"><i class="bi bi-person"></i></div><div class="official-name">Carlos D. Ramos</div><div class="official-position">Barangay Kagawad</div><a href="#" class="official-contact"><i class="bi bi-telephone"></i> Contact</a></div>
    <div class="official-card"><div class="official-avatar"><i class="bi bi-person"></i></div><div class="official-name">Luisa P. Torres</div><div class="official-position">Barangay Kagawad</div><a href="#" class="official-contact"><i class="bi bi-telephone"></i> Contact</a></div>
    <div class="official-card"><div class="official-avatar"><i class="bi bi-person"></i></div><div class="official-name">Ramon G. Flores</div><div class="official-position">Barangay Kagawad</div><a href="#" class="official-contact"><i class="bi bi-telephone"></i> Contact</a></div>
    <div class="official-card"><div class="official-avatar"><i class="bi bi-person"></i></div><div class="official-name">Felicia B. Ong</div><div class="official-position">SK Chairperson</div><a href="#" class="official-contact"><i class="bi bi-telephone"></i> Contact</a></div>
    <div class="official-card"><div class="official-avatar"><i class="bi bi-person"></i></div><div class="official-name">Eduardo N. Bautista</div><div class="official-position">Barangay Secretary</div><a href="#" class="official-contact"><i class="bi bi-telephone"></i> Contact</a></div>
    <div class="official-card"><div class="official-avatar"><i class="bi bi-person"></i></div><div class="official-name">Cynthia V. Dela Cruz</div><div class="official-position">Barangay Treasurer</div><a href="#" class="official-contact"><i class="bi bi-telephone"></i> Contact</a></div>
  </div>
</div>

<!-- ═══ CONTACT & HOTLINES ═══ -->
<div class="page-view" id="page-contact">
  <div class="page-header">
    <div class="page-title">Contact & Hotlines</div>
    <div class="page-subtitle">Get in touch with Barangay Sta. Rosa 1.</div>
  </div>
  <div class="two-col">
    <div>
      <div class="card" style="margin-bottom:20px">
        <div class="card-header"><div class="card-title"><i class="bi bi-telephone-fill"></i> Emergency Hotlines</div></div>
        <div class="card-body">
          <div class="hotline-item"><div class="hotline-icon"><i class="bi bi-telephone-fill"></i></div><div><div class="hotline-label">Barangay Tanod (24/7)</div><div class="hotline-number">0917-111-2233</div></div></div>
          <div class="hotline-item"><div class="hotline-icon"><i class="bi bi-shield-fill"></i></div><div><div class="hotline-label">PNP — Sta. Rosa Police Station</div><div class="hotline-number">(049) 523-1234</div></div></div>
          <div class="hotline-item"><div class="hotline-icon"><i class="bi bi-heart-pulse-fill"></i></div><div><div class="hotline-label">Rural Health Unit</div><div class="hotline-number">(049) 523-5678</div></div></div>
          <div class="hotline-item"><div class="hotline-icon"><i class="bi bi-fire"></i></div><div><div class="hotline-label">Bureau of Fire Protection</div><div class="hotline-number">(049) 523-7890</div></div></div>
        </div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title"><i class="bi bi-clock"></i> Office Hours</div></div>
        <div class="card-body">
          <div style="display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid var(--border); font-size:13.5px"><span>Monday – Friday</span><strong>8:00 AM – 5:00 PM</strong></div>
          <div style="display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid var(--border); font-size:13.5px"><span>Saturday</span><strong>8:00 AM – 12:00 PM</strong></div>
          <div style="display:flex; justify-content:space-between; padding:10px 0; font-size:13.5px"><span>Sunday & Holidays</span><strong style="color:var(--text-muted)">Closed</strong></div>
        </div>
      </div>
    </div>
    <div>
      <div class="card" style="margin-bottom:20px">
        <div class="card-header"><div class="card-title"><i class="bi bi-geo-alt-fill"></i> Location & Address</div></div>
        <div class="card-body">
          <div style="background:var(--bg); border-radius:12px; height:180px; display:flex; align-items:center; justify-content:center; font-size:40px; color:var(--text-light); margin-bottom:16px"><i class="bi bi-map"></i></div>
          <div style="font-weight:700; color:var(--primary); margin-bottom:4px">Barangay Hall, Sta. Rosa 1</div>
          <div style="font-size:13px; color:var(--text-muted)">Purok 1, Brgy. Sta. Rosa 1, Sta. Rosa City, Laguna 4026</div>
          <a href="#" class="btn-primary" style="margin-top:14px; width:100%; justify-content:center"><i class="bi bi-map"></i> Get Directions</a>
        </div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title"><i class="bi bi-envelope-fill"></i> Send a Message</div></div>
        <div class="card-body">
          <input type="text" class="form-control" placeholder="Your name" style="margin-bottom:10px">
          <textarea class="form-control" rows="3" placeholder="Your message…" style="margin-bottom:10px; min-height:80px"></textarea>
          <button class="btn-primary" style="width:100%; justify-content:center"><i class="bi bi-send"></i> Send Message</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ═══ MY PROFILE ═══ -->
<div class="page-view" id="page-profile">
  <div class="page-header">
    <div class="page-title">My Profile</div>
    <div class="page-subtitle">Manage your personal information and account settings.</div>
  </div>
  <div class="completion-card">
    <div class="completion-header">
      <div class="completion-title">Profile Completion</div>
      <div class="completion-pct">75%</div>
    </div>
    <div class="completion-bar-outer">
      <div class="completion-bar-inner" style="width:75%"></div>
    </div>
    <p style="font-size:12.5px; color:var(--text-muted); margin-top:8px">Upload your valid ID to complete your profile and unlock all portal features.</p>
  </div>
  <div class="two-col">
    <div>
      <div class="card" style="margin-bottom:20px">
        <div class="card-header"><div class="card-title"><i class="bi bi-person-circle"></i> Personal Information</div></div>
        <div class="card-body">
          <div style="display:flex; align-items:center; gap:16px; margin-bottom:24px">
            <div style="width:72px; height:72px; border-radius:50%; background:linear-gradient(135deg,var(--primary),#1a4a8a); display:flex; align-items:center; justify-content:center; color:var(--accent); font-size:28px; font-weight:700; flex-shrink:0">JD</div>
            <div>
              <div style="font-size:17px; font-weight:700; color:var(--primary)">Juan Santos Dela Cruz</div>
              <div style="font-size:13px; color:var(--text-muted)">Purok 3 · Resident ID: RES-2025-0421</div>
              <button class="btn-secondary" style="margin-top:8px; padding:6px 12px; font-size:12px; min-height:36px"><i class="bi bi-camera"></i> Change Photo</button>
            </div>
          </div>
          <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px">
            <div><label class="form-label">First Name</label><input type="text" class="form-control" value="Juan"></div>
            <div><label class="form-label">Last Name</label><input type="text" class="form-control" value="Dela Cruz"></div>
            <div><label class="form-label">Date of Birth</label><input type="date" class="form-control" value="1990-03-15"></div>
            <div><label class="form-label">Civil Status</label><select class="form-select"><option>Married</option><option>Single</option><option>Widowed</option></select></div>
            <div style="grid-column:1/-1"><label class="form-label">Complete Address</label><input type="text" class="form-control" value="123 Sampaguita St., Purok 3, Sta. Rosa 1"></div>
            <div><label class="form-label">Mobile Number</label><input type="tel" class="form-control" value="0917-123-4567"></div>
            <div><label class="form-label">Email Address</label><input type="email" class="form-control" value="juan@email.com"></div>
          </div>
          <button class="btn-primary" style="margin-top:18px"><i class="bi bi-floppy"></i> Save Changes</button>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><div class="card-title"><i class="bi bi-key"></i> Change Password</div></div>
        <div class="card-body">
          <div style="margin-bottom:14px"><label class="form-label">Current Password</label><input type="password" class="form-control" placeholder="••••••••"></div>
          <div style="margin-bottom:14px"><label class="form-label">New Password</label><input type="password" class="form-control" placeholder="••••••••"></div>
          <div style="margin-bottom:18px"><label class="form-label">Confirm New Password</label><input type="password" class="form-control" placeholder="••••••••"></div>
          <button class="btn-primary"><i class="bi bi-shield-lock"></i> Update Password</button>
        </div>
      </div>
    </div>

    <div>
      <div class="card" style="margin-bottom:20px">
        <div class="card-header">
          <div class="card-title"><i class="bi bi-people"></i> Household Members</div>
          <button class="btn-accent" style="padding:6px 12px; font-size:12px; min-height:36px"><i class="bi bi-plus"></i> Add</button>
        </div>
        <div class="card-body">
          <div class="complaint-item">
            <div class="sidebar-avatar" style="width:38px; height:38px; font-size:13px">MJ</div>
            <div class="complaint-info">
              <div class="complaint-title">Maria J. Dela Cruz</div>
              <div class="complaint-date">Spouse · F · 42 years old</div>
            </div>
            <button class="btn-secondary" style="padding:4px 10px; font-size:11px; min-height:32px"><i class="bi bi-pencil"></i></button>
          </div>
          <div class="complaint-item">
            <div class="sidebar-avatar" style="width:38px; height:38px; font-size:13px">KD</div>
            <div class="complaint-info">
              <div class="complaint-title">Kevin A. Dela Cruz</div>
              <div class="complaint-date">Son · M · 17 years old</div>
            </div>
            <button class="btn-secondary" style="padding:4px 10px; font-size:11px; min-height:32px"><i class="bi bi-pencil"></i></button>
          </div>
          <div class="complaint-item">
            <div class="sidebar-avatar" style="width:38px; height:38px; font-size:13px">SD</div>
            <div class="complaint-info">
              <div class="complaint-title">Sofia D. Dela Cruz</div>
              <div class="complaint-date">Daughter · F · 14 years old</div>
            </div>
            <button class="btn-secondary" style="padding:4px 10px; font-size:11px; min-height:32px"><i class="bi bi-pencil"></i></button>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><div class="card-title"><i class="bi bi-card-image"></i> Upload Valid ID</div></div>
        <div class="card-body">
          <div style="border:2px dashed var(--accent); border-radius:12px; padding:24px; text-align:center; background:var(--accent-pale)">
            <div style="font-size:36px; color:var(--accent); margin-bottom:8px"><i class="bi bi-id-card"></i></div>
            <div style="font-weight:700; color:var(--primary); margin-bottom:4px">Upload Government-Issued ID</div>
            <div style="font-size:12px; color:var(--text-muted); margin-bottom:14px">PhilSys ID, SSS, GSIS, Driver's License, Passport, Voter's ID</div>
            <button class="btn-accent"><i class="bi bi-cloud-upload"></i> Upload ID</button>
          </div>
          <div style="font-size:12px; color:var(--text-muted); margin-top:12px; display:flex; align-items:flex-start; gap:6px">
            <i class="bi bi-shield-check" style="color:var(--accent); flex-shrink:0; margin-top:1px"></i>
            Your documents are securely stored and only accessed by authorized barangay personnel.
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ═══ VERIFY DOCUMENT ═══ -->
<div class="page-view" id="page-verify">
  <div class="page-header">
    <div class="page-title">Verify My Document</div>
    <div class="page-subtitle">Verify the authenticity of a document issued by Barangay Sta. Rosa 1.</div>
  </div>
  <div class="card" style="max-width:540px; margin:0 auto">
    <div class="card-body" style="padding:32px; text-align:center">
      <div style="font-size:56px; color:var(--accent); margin-bottom:16px"><i class="bi bi-patch-check-fill"></i></div>
      <div style="font-family:'Fraunces',serif; font-size:20px; font-weight:600; color:var(--primary); margin-bottom:8px">Document Verification</div>
      <p style="font-size:13.5px; color:var(--text-muted); margin-bottom:24px; line-height:1.6">Enter the reference number or scan the QR code found on your document to verify its authenticity.</p>
      <div style="text-align:left; margin-bottom:16px">
        <label class="form-label">Document Reference Number</label>
        <input type="text" class="form-control" placeholder="e.g., REQ-2025-0042" style="font-size:15px; text-align:center; letter-spacing:0.05em">
      </div>
      <button class="btn-primary" style="width:100%; justify-content:center; font-size:15px"><i class="bi bi-search"></i> Verify Document</button>
      <div style="margin:20px 0; display:flex; align-items:center; gap:12px; color:var(--text-light); font-size:12px">
        <div style="flex:1; height:1px; background:var(--border)"></div>OR<div style="flex:1; height:1px; background:var(--border)"></div>
      </div>
      <button class="btn-secondary" style="width:100%; justify-content:center"><i class="bi bi-qr-code-scan"></i> Scan QR Code</button>
    </div>
  </div>
</div>

</div><!-- end page-content -->
</main>

<!-- BOTTOM NAVIGATION -->
<nav class="bottom-nav">
  <div class="bottom-nav-inner">
    <a class="bottom-nav-item active" id="bnav-home" onclick="showPage('dashboard'); setBottomNav('home')">
      <i class="bi bi-grid-1x2-fill"></i>
      <span>Home</span>
    </a>
    <a class="bottom-nav-item" id="bnav-docs" onclick="showPage('requests-all'); setBottomNav('docs')">
      <i class="bi bi-folder2-open"></i>
      <span>Documents</span>
      <span class="bottom-nav-badge">1</span>
    </a>
    <a class="bottom-nav-item" id="bnav-complaints" onclick="showPage('my-cases'); setBottomNav('complaints')">
      <i class="bi bi-journal-text"></i>
      <span>Complaints</span>
    </a>
    <a class="bottom-nav-item" id="bnav-profile" onclick="showPage('profile'); setBottomNav('profile')">
      <i class="bi bi-person-circle"></i>
      <span>Profile</span>
    </a>
  </div>
</nav>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script>
// ── PAGE ROUTING ──
const pages = ['dashboard','request','requests-all','requests-pending','requests-pickup','requests-done','verify','file-complaint','my-cases','announcements','services','officials','contact','profile'];

function showPage(id) {
  pages.forEach(p => {
    const el = document.getElementById('page-' + p);
    const navEl = document.getElementById('nav-' + p.replace(/-/g,''));
    if (el) el.classList.toggle('active', p === id);
  });

  // Update sidebar active states
  document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
  const active = document.getElementById('nav-' + id.replace(/-/g,'').replace('requestsall','myrequests').replace('requestspending','myrequests').replace('requestspickup','myrequests').replace('requestsdone','myrequests'));
  if (active) {
    active.classList.add('active');
    if (id.startsWith('requests-')) {
      document.getElementById('submenu-requests').classList.add('open');
      if (active.classList.contains('nav-link')) {
        const parent = document.getElementById('nav-myrequests');
        if (parent) parent.classList.add('expanded', 'active');
      }
    }
  }

  // Scroll to top
  document.querySelector('.main-content').scrollTop = 0;
  window.scrollTo(0, 0);

  // Close sidebar on mobile
  if (window.innerWidth < 992) closeSidebar();
}

// ── SIDEBAR TOGGLE ──
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('sidebarOverlay');

document.getElementById('hamburgerBtn').addEventListener('click', () => {
  sidebar.classList.toggle('open');
  overlay.classList.toggle('show');
});

function closeSidebar() {
  sidebar.classList.remove('open');
  overlay.classList.remove('show');
}

// ── SUBMENU TOGGLE ──
function toggleSubmenu(id, el) {
  const sub = document.getElementById(id);
  const isOpen = sub.classList.toggle('open');
  el.classList.toggle('expanded', isOpen);
}

// ── NOTIFICATIONS ──
function toggleNotif() {
  document.getElementById('notifDropdown').classList.toggle('open');
}
function clearNotifs() {
  document.querySelectorAll('.notif-item').forEach(n => n.classList.remove('unread'));
  document.querySelector('.notif-badge-count').style.display = 'none';
  document.getElementById('notifDropdown').classList.remove('open');
}
document.addEventListener('click', e => {
  if (!e.target.closest('#notifBtn') && !e.target.closest('#notifDropdown')) {
    document.getElementById('notifDropdown').classList.remove('open');
  }
});

// ── BOTTOM NAV ──
function setBottomNav(active) {
  ['home','docs','complaints','profile'].forEach(id => {
    document.getElementById('bnav-' + id)?.classList.remove('active');
  });
  document.getElementById('bnav-' + active)?.classList.add('active');
}

// ── FORM SUBMISSION ──
function submitRequest() {
  const ref = 'REQ-2025-0' + (Math.floor(Math.random() * 90) + 10);
  alert('✅ Request Submitted Successfully!\n\nReference Number: ' + ref + '\nEstimated processing time: 1-2 working days.\nYou will be notified once your document is ready for pick-up.');
  showPage('requests-all');
}

// ── RESPONSIVE FORM GRIDS ──
function adjustFormGrids() {
  const isMobile = window.innerWidth < 600;
  document.querySelectorAll('.form-grid-2').forEach(el => {
    el.style.gridTemplateColumns = isMobile ? '1fr' : '1fr 1fr';
  });
  document.querySelectorAll('.form-grid-3').forEach(el => {
    el.style.gridTemplateColumns = isMobile ? '1fr' : '1fr 1fr 1fr';
  });
}
adjustFormGrids();
window.addEventListener('resize', adjustFormGrids);

// ── FILTER TABS ──
document.querySelectorAll('.filter-tabs').forEach(group => {
  group.querySelectorAll('.filter-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      group.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
    });
  });
});

// ── ENTRANCE ANIMATIONS ──
const observer = new IntersectionObserver((entries) => {
  entries.forEach(e => {
    if (e.isIntersecting) e.target.style.opacity = '1';
  });
}, { threshold: 0.1 });
document.querySelectorAll('.stat-card, .card, .official-card').forEach(el => {
  observer.observe(el);
});
</script>
</body>
</html>