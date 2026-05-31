<?php
// ============================================================
// Barangay Sta. Rosa 1 Management System — Homepage
// File: index.php | Stack: HTML + CSS + JS + Bootstrap 5 + PHP
// ============================================================

// --- SAMPLE PHP DATA (replace with real DB queries) ---
$barangay_name    = "Barangay Sta. Rosa 1";
$municipality     = "Noveleta, Cavite";
$captain_name     = "Hon. [Captain Name]";
$captain_title    = "Punong Barangay";
$total_residents  = "4,821";
$total_households = "1,204";
$docs_this_month  = "312";
$years_serving    = "2023–2025";

$announcements = [
  [
    "category"  => "Health",
    "cat_color" => "success",
    "title"     => "Free Medical Mission — May 15, 2025",
    "excerpt"   => "The barangay health center, in partnership with the municipal health office, will conduct a free medical and dental mission for all residents.",
    "date"      => "May 10, 2025",
    "icon"      => "bi-heart-pulse-fill",
  ],
  [
    "category"  => "Notice",
    "cat_color" => "warning",
    "title"     => "Lupon Tagapamayapa Schedule — May 2025",
    "excerpt"   => "Mediation hearings for the month of May are scheduled every Tuesday and Thursday from 9:00 AM to 12:00 NN at the barangay hall.",
    "date"      => "May 8, 2025",
    "icon"      => "bi-calendar2-week-fill",
  ],
  [
    "category"  => "Program",
    "cat_color" => "info",
    "title"     => "4Ps Beneficiary Update & Monitoring",
    "excerpt"   => "All registered 4Ps beneficiaries are required to report to the barangay hall on May 20–22 for the quarterly compliance check.",
    "date"      => "May 5, 2025",
    "icon"      => "bi-people-fill",
  ],
];

$services = [
  ["icon"=>"bi-patch-check-fill",    "title"=>"Barangay Clearance",       "desc"=>"For employment, legal, or general purpose use.",       "fee"=>"₱50–₱100",   "time"=>"Same day"],
  ["icon"=>"bi-house-heart-fill",    "title"=>"Certificate of Residency", "desc"=>"Proof of residence for schools, employers, utilities.", "fee"=>"₱50",         "time"=>"Same day"],
  ["icon"=>"bi-hand-heart-fill",     "title"=>"Certificate of Indigency", "desc"=>"For medical assistance, scholarships, DSWD programs.",  "fee"=>"Free",        "time"=>"Same day"],
  ["icon"=>"bi-shop-window",         "title"=>"Business Clearance",       "desc"=>"Required for all businesses operating in the barangay.","fee"=>"₱200–₱500",   "time"=>"1–2 days"],
  ["icon"=>"bi-file-earmark-text-fill","title"=>"Barangay Certification", "desc"=>"Solo parent, good standing, and other certifications.", "fee"=>"₱50",         "time"=>"Same day"],
  ["icon"=>"bi-journal-text",        "title"=>"Blotter Certificate",      "desc"=>"Official extract from the barangay blotter record.",    "fee"=>"₱100",        "time"=>"1–2 days"],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Official website of <?= $barangay_name ?>, <?= $municipality ?>. Request documents, view announcements, and access barangay services online.">
  <title><?= $barangay_name ?> — Official Website</title>

  <!-- Bootstrap 5.3 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <!-- Google Fonts: DM Serif Display + Plus Jakarta Sans -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <!-- AOS – Animate On Scroll -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css">

  <style>
    /* ===========================
       DESIGN TOKENS
    =========================== */
    :root {
      --navy:        #0B2545;
      --navy-mid:    #13375B;
      --navy-light:  #1A4A7A;
      --emerald:     #0F6B45;
      --emerald-lt:  #17895A;
      --gold:        #C9961E;
      --gold-light:  #F0B429;
      --cream:       #F7F5F0;
      --off-white:   #FAFAF8;
      --text-body:   #2D3748;
      --text-muted:  #718096;
      --border:      #E2E8F0;
      --card-radius: 16px;
      --transition:  all .3s cubic-bezier(.4,0,.2,1);
    }

    /* ===========================
       BASE
    =========================== */
    *, *::before, *::after { box-sizing: border-box; }
    html { scroll-behavior: smooth; }

    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 15px;
      color: var(--text-body);
      background: var(--off-white);
      overflow-x: hidden;
    }

    h1, h2, h3, .display-font {
      font-family: 'DM Serif Display', serif;
    }

    /* ===========================
       NAVBAR
    =========================== */
    .bms-nav {
      background: var(--navy);
      padding: 0;
      position: fixed;
      top: 0; left: 0; right: 0;
      z-index: 1050;
      border-bottom: 2px solid var(--gold);
      transition: var(--transition);
    }
    .bms-nav.scrolled {
      background: rgba(11,37,69,.97);
      backdrop-filter: blur(12px);
      box-shadow: 0 4px 24px rgba(0,0,0,.25);
    }
    .nav-inner {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 2rem;
      height: 68px;
    }
    .nav-brand {
      display: flex;
      align-items: center;
      gap: 10px;
      text-decoration: none;
    }
    .nav-seal {
      width: 44px;
      height: 44px;
      background: var(--gold);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 22px;
      color: var(--navy);
      flex-shrink: 0;
    }
    .nav-brand-text .brgy-name {
      font-family: 'DM Serif Display', serif;
      font-size: 15px;
      color: #fff;
      line-height: 1.1;
      letter-spacing: .01em;
    }
    .nav-brand-text .brgy-place {
      font-size: 11px;
      color: rgba(255,255,255,.55);
      letter-spacing: .04em;
      text-transform: uppercase;
    }
    .nav-links {
      display: flex;
      align-items: center;
      gap: 4px;
    }
    .nav-links a {
      color: rgba(255,255,255,.8);
      text-decoration: none;
      font-size: 13.5px;
      font-weight: 500;
      padding: 6px 14px;
      border-radius: 8px;
      transition: var(--transition);
    }
    .nav-links a:hover { color: #fff; background: rgba(255,255,255,.1); }
    .nav-links a.active { color: var(--gold-light); }
    .nav-cta {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .btn-nav-login {
      font-size: 13px;
      font-weight: 600;
      padding: 7px 18px;
      border-radius: 8px;
      border: 1.5px solid rgba(255,255,255,.3);
      color: #fff;
      background: transparent;
      text-decoration: none;
      transition: var(--transition);
    }
    .btn-nav-login:hover { background: rgba(255,255,255,.12); color: #fff; border-color: rgba(255,255,255,.6); }
    .btn-nav-register {
      font-size: 13px;
      font-weight: 600;
      padding: 7px 18px;
      border-radius: 8px;
      background: var(--gold);
      color: var(--navy);
      text-decoration: none;
      transition: var(--transition);
      border: 1.5px solid var(--gold);
    }
    .btn-nav-register:hover { background: var(--gold-light); color: var(--navy); }

    /* Mobile hamburger */
    .nav-toggle {
      display: none;
      background: none;
      border: none;
      color: #fff;
      font-size: 24px;
      cursor: pointer;
      padding: 4px;
    }
    .nav-mobile-menu {
      display: none;
      flex-direction: column;
      background: var(--navy-mid);
      padding: 12px 1rem 16px;
      gap: 4px;
    }
    .nav-mobile-menu a {
      color: rgba(255,255,255,.85);
      text-decoration: none;
      font-size: 14px;
      font-weight: 500;
      padding: 9px 12px;
      border-radius: 8px;
    }
    .nav-mobile-menu a:hover { background: rgba(255,255,255,.08); }
    .nav-mobile-menu .mobile-cta { display: flex; gap: 8px; margin-top: 8px; }
    .nav-mobile-menu .mobile-cta a { flex: 1; text-align: center; }

    @media (max-width: 960px) {
      .nav-links, .nav-cta { display: none; }
      .nav-toggle { display: block; }
      .nav-mobile-menu.open { display: flex; }
    }

    /* ===========================
       HERO
    =========================== */
    .hero {
      min-height: 100vh;
      background: linear-gradient(rgba(0,0,0,0.45), rgba(0,0,0,0.45)), url('assets/images/system_bg.png');
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
      position: relative;
      display: flex;
      align-items: center;
      overflow: hidden;
      padding-top: 68px;
    }

    /* Geometric background pattern */
    .hero-pattern {
      position: absolute;
      inset: 0;
      background-image:
        radial-gradient(circle at 15% 50%, rgba(201,150,30,.12) 0%, transparent 50%),
        radial-gradient(circle at 85% 20%, rgba(15,107,69,.18) 0%, transparent 45%),
        radial-gradient(circle at 60% 80%, rgba(26,74,122,.4) 0%, transparent 40%);
    }

    /* Diagonal accent line */
    .hero::after {
      content: '';
      position: absolute;
      right: -80px;
      top: 0;
      bottom: 0;
      width: 520px;
      background: var(--navy-mid);
      transform: skewX(-8deg);
      opacity: .5;
    }

    .hero-grid-lines {
      position: absolute;
      inset: 0;
      background-image:
        linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px);
      background-size: 48px 48px;
    }

    .hero-content {
      position: relative;
      z-index: 2;
      padding: 5rem 0 4rem;
    }

    .hero-eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: rgba(201,150,30,.15);
      border: 1px solid rgba(201,150,30,.35);
      color: var(--gold-light);
      font-size: 12px;
      font-weight: 600;
      letter-spacing: .08em;
      text-transform: uppercase;
      padding: 6px 14px;
      border-radius: 100px;
      margin-bottom: 1.5rem;
    }
    .hero-eyebrow i { font-size: 13px; }

    .hero-title {
      font-family: 'DM Serif Display', serif;
      font-size: clamp(2.6rem, 5.5vw, 4.2rem);
      color: #fff;
      line-height: 1.1;
      margin-bottom: 1rem;
      letter-spacing: -.01em;
    }
    .hero-title span {
      color: var(--gold-light);
      font-style: italic;
    }

    .hero-sub {
      font-size: 16.5px;
      color: rgba(255,255,255,.7);
      max-width: 500px;
      line-height: 1.7;
      margin-bottom: 2.5rem;
      font-weight: 400;
    }

    .hero-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      margin-bottom: 3.5rem;
    }

    .btn-hero-primary {
      background: var(--gold);
      color: var(--navy);
      font-weight: 700;
      font-size: 14.5px;
      padding: 13px 28px;
      border-radius: 10px;
      text-decoration: none;
      border: none;
      transition: var(--transition);
      display: inline-flex;
      align-items: center;
      gap: 8px;
      box-shadow: 0 4px 20px rgba(201,150,30,.35);
    }
    .btn-hero-primary:hover {
      background: var(--gold-light);
      color: var(--navy);
      transform: translateY(-2px);
      box-shadow: 0 8px 28px rgba(201,150,30,.45);
    }

    .btn-hero-secondary {
      background: rgba(255,255,255,.1);
      backdrop-filter: blur(8px);
      color: #fff;
      font-weight: 600;
      font-size: 14.5px;
      padding: 13px 28px;
      border-radius: 10px;
      text-decoration: none;
      border: 1.5px solid rgba(255,255,255,.2);
      transition: var(--transition);
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }
    .btn-hero-secondary:hover {
      background: rgba(255,255,255,.18);
      color: #fff;
      border-color: rgba(255,255,255,.45);
      transform: translateY(-2px);
    }

    /* Hero stats strip */
    .hero-stats {
      display: flex;
      flex-wrap: wrap;
      gap: 0;
      border-top: 1px solid rgba(255,255,255,.1);
      padding-top: 2rem;
    }
    .hero-stat {
      flex: 1;
      min-width: 140px;
      padding-right: 2rem;
      padding-left: .5rem;
      border-right: 1px solid rgba(255,255,255,.1);
    }
    .hero-stat:last-child { border-right: none; }
    .hero-stat-num {
      font-family: 'DM Serif Display', serif;
      font-size: 2rem;
      color: var(--gold-light);
      line-height: 1;
      margin-bottom: 4px;
    }
    .hero-stat-label {
      font-size: 12.5px;
      color: rgba(255,255,255,.5);
      font-weight: 500;
      letter-spacing: .03em;
    }

    /* Hero right visual */
    .hero-visual {
      position: relative;
      z-index: 2;
      display: flex;
      justify-content: center;
      align-items: center;
    }
    .hero-card-stack {
      position: relative;
      width: 340px;
      max-width: 100%;
    }
    .hero-card-bg {
      background: rgba(255,255,255,.06);
      border: 1px solid rgba(255,255,255,.12);
      border-radius: var(--card-radius);
      padding: 1.5rem;
      backdrop-filter: blur(10px);
    }
    .hero-card-title {
      font-family: 'DM Serif Display', serif;
      font-size: 1.15rem;
      color: #fff;
      margin-bottom: 1rem;
    }
    .quick-doc-btn {
      display: flex;
      align-items: center;
      gap: 10px;
      background: rgba(255,255,255,.07);
      border: 1px solid rgba(255,255,255,.1);
      border-radius: 10px;
      padding: 10px 14px;
      margin-bottom: 8px;
      text-decoration: none;
      color: rgba(255,255,255,.85);
      font-size: 13.5px;
      font-weight: 500;
      transition: var(--transition);
    }
    .quick-doc-btn:last-child { margin-bottom: 0; }
    .quick-doc-btn:hover { background: rgba(255,255,255,.14); color: #fff; border-color: rgba(201,150,30,.45); transform: translateX(4px); }
    .quick-doc-btn i { color: var(--gold-light); font-size: 16px; width: 20px; text-align: center; }
    .quick-doc-btn .arrow { margin-left: auto; opacity: .4; font-size: 12px; }

    .hero-online-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: rgba(15,107,69,.3);
      border: 1px solid rgba(15,107,69,.5);
      color: #4ADE80;
      font-size: 11.5px;
      font-weight: 600;
      padding: 5px 10px;
      border-radius: 100px;
      margin-bottom: 1rem;
    }
    .dot-live {
      width: 7px; height: 7px;
      border-radius: 50%;
      background: #4ADE80;
      animation: pulse-live 1.8s ease-in-out infinite;
    }
    @keyframes pulse-live {
      0%,100% { opacity:1; transform:scale(1); }
      50% { opacity:.5; transform:scale(1.4); }
    }

    /* ===========================
       ANNOUNCEMENT TICKER
    =========================== */
    .ticker-bar {
      background: var(--gold);
      padding: 10px 0;
      overflow: hidden;
    }
    .ticker-inner {
      display: flex;
      align-items: center;
      gap: 0;
    }
    .ticker-label {
      background: var(--navy);
      color: var(--gold-light);
      font-size: 11px;
      font-weight: 700;
      letter-spacing: .08em;
      text-transform: uppercase;
      padding: 4px 16px;
      border-radius: 4px;
      white-space: nowrap;
      flex-shrink: 0;
      margin-right: 16px;
      display: flex;
      align-items: center;
      gap: 5px;
    }
    .ticker-track {
      flex: 1;
      overflow: hidden;
      position: relative;
    }
    .ticker-text {
      display: inline-block;
      white-space: nowrap;
      animation: ticker-scroll 25s linear infinite;
      color: var(--navy);
      font-size: 13px;
      font-weight: 600;
    }
    @keyframes ticker-scroll {
      0%   { transform: translateX(100%); }
      100% { transform: translateX(-100%); }
    }

    /* ===========================
       SECTION COMMONS
    =========================== */
    section { padding: 80px 0; }
    .section-eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      color: var(--emerald);
      font-size: 12px;
      font-weight: 700;
      letter-spacing: .1em;
      text-transform: uppercase;
      margin-bottom: .6rem;
    }
    .section-eyebrow::before {
      content: '';
      display: block;
      width: 24px;
      height: 2px;
      background: var(--emerald);
      border-radius: 2px;
    }
    .section-title {
      font-family: 'DM Serif Display', serif;
      font-size: clamp(1.8rem, 3vw, 2.6rem);
      color: var(--navy);
      line-height: 1.2;
      margin-bottom: .75rem;
    }
    .section-sub {
      color: var(--text-muted);
      font-size: 15px;
      line-height: 1.7;
      max-width: 560px;
    }

    /* ===========================
       SERVICES SECTION
    =========================== */
    .services-section { background: var(--cream); }
    .service-card {
      background: #fff;
      border: 1px solid var(--border);
      border-radius: var(--card-radius);
      padding: 1.6rem 1.5rem;
      height: 100%;
      position: relative;
      transition: var(--transition);
      overflow: hidden;
    }
    .service-card::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 3px;
      background: linear-gradient(90deg, var(--navy), var(--emerald));
      transform: scaleX(0);
      transform-origin: left;
      transition: var(--transition);
    }
    .service-card:hover {
      box-shadow: 0 12px 40px rgba(11,37,69,.12);
      transform: translateY(-4px);
      border-color: rgba(11,37,69,.15);
    }
    .service-card:hover::before { transform: scaleX(1); }
    .service-icon-wrap {
      width: 52px; height: 52px;
      background: #EBF2FF;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 22px;
      color: var(--navy-light);
      margin-bottom: 1rem;
      transition: var(--transition);
    }
    .service-card:hover .service-icon-wrap {
      background: var(--navy);
      color: var(--gold-light);
    }
    .service-title {
      font-family: 'DM Serif Display', serif;
      font-size: 1.1rem;
      color: var(--navy);
      margin-bottom: .4rem;
    }
    .service-desc {
      font-size: 13.5px;
      color: var(--text-muted);
      line-height: 1.6;
      margin-bottom: 1rem;
    }
    .service-meta {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }
    .meta-badge {
      font-size: 11.5px;
      font-weight: 600;
      padding: 3px 10px;
      border-radius: 100px;
      letter-spacing: .02em;
    }
    .meta-fee { background: #FEF3C7; color: #92400E; }
    .meta-time { background: #D1FAE5; color: #065F46; }
    .service-link {
      margin-top: 1rem;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      font-size: 13px;
      font-weight: 600;
      color: var(--navy-light);
      text-decoration: none;
      transition: var(--transition);
    }
    .service-link:hover { color: var(--emerald); gap: 8px; }

    /* ===========================
       HOW IT WORKS
    =========================== */
    .hiw-section { background: #fff; }
    .step-card {
      text-align: center;
      padding: 2rem 1.5rem;
      border-radius: var(--card-radius);
      border: 1px solid var(--border);
      height: 100%;
      transition: var(--transition);
      position: relative;
    }
    .step-card:hover { box-shadow: 0 8px 32px rgba(11,37,69,.08); transform: translateY(-3px); }
    .step-num {
      width: 56px; height: 56px;
      background: var(--navy);
      color: var(--gold-light);
      font-family: 'DM Serif Display', serif;
      font-size: 1.5rem;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1.25rem;
    }
    .step-icon {
      font-size: 2rem;
      color: var(--emerald);
      margin-bottom: .75rem;
    }
    .step-title {
      font-family: 'DM Serif Display', serif;
      font-size: 1.15rem;
      color: var(--navy);
      margin-bottom: .5rem;
    }
    .step-desc {
      font-size: 13.5px;
      color: var(--text-muted);
      line-height: 1.65;
    }
    .step-connector {
      position: absolute;
      top: 50px;
      right: -20px;
      font-size: 22px;
      color: var(--border);
      z-index: 1;
    }

    /* ===========================
       ANNOUNCEMENTS
    =========================== */
    .ann-section { background: var(--off-white); }
    .ann-card {
      background: #fff;
      border: 1px solid var(--border);
      border-radius: var(--card-radius);
      padding: 1.4rem 1.5rem;
      height: 100%;
      transition: var(--transition);
      display: flex;
      flex-direction: column;
    }
    .ann-card:hover { box-shadow: 0 8px 28px rgba(11,37,69,.09); transform: translateY(-3px); }
    .ann-top {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      margin-bottom: 1rem;
    }
    .ann-icon-wrap {
      width: 42px; height: 42px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      flex-shrink: 0;
    }
    .ann-cat { font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; margin-bottom: 3px; }
    .ann-title {
      font-family: 'DM Serif Display', serif;
      font-size: 1.05rem;
      color: var(--navy);
      margin-bottom: .5rem;
      line-height: 1.3;
    }
    .ann-excerpt { font-size: 13.5px; color: var(--text-muted); line-height: 1.6; flex: 1; }
    .ann-footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 1rem;
      padding-top: .75rem;
      border-top: 1px solid var(--border);
    }
    .ann-date { font-size: 12px; color: var(--text-muted); display: flex; align-items: center; gap: 5px; }
    .ann-read { font-size: 12.5px; font-weight: 600; color: var(--navy-light); text-decoration: none; display: flex; align-items: center; gap: 4px; transition: var(--transition); }
    .ann-read:hover { color: var(--emerald); gap: 6px; }

    /* ===========================
       CAPTAIN / OFFICIALS
    =========================== */
    .officials-section {
      background: linear-gradient(135deg, var(--navy) 0%, var(--navy-mid) 50%, #0D3B25 100%);
      position: relative;
      overflow: hidden;
    }
    .officials-section::before {
      content: '';
      position: absolute;
      inset: 0;
      background-image: radial-gradient(circle at 80% 50%, rgba(201,150,30,.1) 0%, transparent 60%);
    }
    .officials-section .section-eyebrow { color: var(--gold-light); }
    .officials-section .section-eyebrow::before { background: var(--gold-light); }
    .officials-section .section-title { color: #fff; }
    .officials-section .section-sub { color: rgba(255,255,255,.6); }

    .captain-card {
      background: rgba(255,255,255,.07);
      border: 1px solid rgba(255,255,255,.12);
      border-radius: 20px;
      padding: 2rem;
      backdrop-filter: blur(10px);
      text-align: center;
      transition: var(--transition);
    }
    .captain-card:hover { background: rgba(255,255,255,.11); transform: translateY(-4px); }
    .captain-avatar {
      width: 90px; height: 90px;
      background: var(--gold);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 36px;
      color: var(--navy);
      margin: 0 auto 1rem;
      border: 3px solid rgba(255,255,255,.2);
    }
    .captain-name {
      font-family: 'DM Serif Display', serif;
      font-size: 1.3rem;
      color: #fff;
      margin-bottom: 4px;
    }
    .captain-role {
      font-size: 12.5px;
      color: var(--gold-light);
      font-weight: 600;
      letter-spacing: .06em;
      text-transform: uppercase;
    }
    .captain-meta {
      display: flex;
      gap: 10px;
      justify-content: center;
      margin-top: 1rem;
      flex-wrap: wrap;
    }
    .captain-meta-item {
      background: rgba(255,255,255,.08);
      border-radius: 8px;
      padding: 6px 12px;
      font-size: 12px;
      color: rgba(255,255,255,.7);
      display: flex;
      align-items: center;
      gap: 5px;
    }
    .kagawad-list { display: flex; flex-direction: column; gap: 10px; }
    .kagawad-item {
      display: flex;
      align-items: center;
      gap: 12px;
      background: rgba(255,255,255,.06);
      border: 1px solid rgba(255,255,255,.1);
      border-radius: 12px;
      padding: 12px 14px;
      transition: var(--transition);
    }
    .kagawad-item:hover { background: rgba(255,255,255,.1); }
    .kagawad-avatar {
      width: 36px; height: 36px;
      border-radius: 50%;
      background: rgba(255,255,255,.15);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
      color: rgba(255,255,255,.7);
      flex-shrink: 0;
    }
    .kagawad-name { font-size: 13.5px; color: rgba(255,255,255,.85); font-weight: 500; margin-bottom: 2px; }
    .kagawad-committee { font-size: 11.5px; color: rgba(255,255,255,.45); }

    /* ===========================
       CTA SECTION
    =========================== */
    .cta-section {
      background: var(--cream);
      padding: 72px 0;
    }
    .cta-box {
      background: linear-gradient(135deg, var(--navy) 0%, var(--navy-mid) 100%);
      border-radius: 24px;
      padding: 3.5rem 3rem;
      text-align: center;
      position: relative;
      overflow: hidden;
    }
    .cta-box::before {
      content: '';
      position: absolute;
      top: -60px; right: -60px;
      width: 260px; height: 260px;
      border-radius: 50%;
      background: rgba(201,150,30,.1);
    }
    .cta-box::after {
      content: '';
      position: absolute;
      bottom: -40px; left: -40px;
      width: 180px; height: 180px;
      border-radius: 50%;
      background: rgba(15,107,69,.15);
    }
    .cta-title { font-family: 'DM Serif Display', serif; font-size: clamp(1.7rem,2.5vw,2.3rem); color: #fff; margin-bottom: .75rem; position: relative; z-index: 1; }
    .cta-sub { color: rgba(255,255,255,.65); font-size: 15px; max-width: 480px; margin: 0 auto 2rem; line-height: 1.7; position: relative; z-index: 1; }
    .cta-buttons { position: relative; z-index: 1; display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
    .btn-cta-primary { background: var(--gold); color: var(--navy); font-weight: 700; font-size: 14.5px; padding: 13px 28px; border-radius: 10px; text-decoration: none; transition: var(--transition); display: inline-flex; align-items: center; gap: 7px; }
    .btn-cta-primary:hover { background: var(--gold-light); color: var(--navy); transform: translateY(-2px); box-shadow: 0 8px 24px rgba(201,150,30,.35); }
    .btn-cta-secondary { background: rgba(255,255,255,.1); color: #fff; font-weight: 600; font-size: 14.5px; padding: 13px 28px; border-radius: 10px; text-decoration: none; border: 1.5px solid rgba(255,255,255,.2); transition: var(--transition); display: inline-flex; align-items: center; gap: 7px; }
    .btn-cta-secondary:hover { background: rgba(255,255,255,.18); color: #fff; border-color: rgba(255,255,255,.45); }

    /* ===========================
       CONTACT STRIP
    =========================== */
    .contact-strip {
      background: #fff;
      border-top: 1px solid var(--border);
      padding: 48px 0;
    }
    .contact-item {
      display: flex;
      align-items: flex-start;
      gap: 14px;
    }
    .contact-icon {
      width: 46px; height: 46px;
      background: #EBF2FF;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;
      color: var(--navy-light);
      flex-shrink: 0;
    }
    .contact-label { font-size: 11.5px; font-weight: 700; color: var(--text-muted); letter-spacing: .06em; text-transform: uppercase; margin-bottom: 3px; }
    .contact-value { font-size: 14px; color: var(--navy); font-weight: 500; }

    /* ===========================
       FOOTER
    =========================== */
    footer {
      background: var(--navy);
      color: rgba(255,255,255,.65);
      padding: 56px 0 32px;
    }
    .footer-brand { margin-bottom: 1rem; }
    .footer-seal {
      width: 50px; height: 50px;
      background: var(--gold);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      color: var(--navy);
      margin-bottom: .75rem;
    }
    .footer-name { font-family: 'DM Serif Display', serif; font-size: 1.15rem; color: #fff; margin-bottom: 2px; }
    .footer-muni { font-size: 12px; opacity: .5; letter-spacing: .04em; text-transform: uppercase; }
    .footer-desc { font-size: 13px; line-height: 1.7; margin-top: .75rem; max-width: 260px; }
    .footer-heading { color: #fff; font-size: 13px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; margin-bottom: 1rem; }
    .footer-links { list-style: none; padding: 0; margin: 0; }
    .footer-links li { margin-bottom: 7px; }
    .footer-links a { color: rgba(255,255,255,.55); text-decoration: none; font-size: 13.5px; transition: var(--transition); }
    .footer-links a:hover { color: var(--gold-light); padding-left: 4px; }
    .footer-divider { border-color: rgba(255,255,255,.08); margin: 2rem 0 1.25rem; }
    .footer-bottom { display: flex; justify-content: space-between; align-items: center; flex-wrap: gap; gap: 8px; font-size: 12.5px; color: rgba(255,255,255,.35); }
    .footer-bottom a { color: rgba(255,255,255,.4); text-decoration: none; }
    .footer-bottom a:hover { color: var(--gold-light); }
    .footer-socials { display: flex; gap: 10px; }
    .social-btn {
      width: 34px; height: 34px;
      border-radius: 8px;
      background: rgba(255,255,255,.07);
      border: 1px solid rgba(255,255,255,.1);
      display: flex; align-items: center; justify-content: center;
      color: rgba(255,255,255,.6);
      font-size: 15px;
      transition: var(--transition);
      text-decoration: none;
    }
    .social-btn:hover { background: var(--gold); color: var(--navy); border-color: var(--gold); }

    /* ===========================
       BACK TO TOP
    =========================== */
    .back-to-top {
      position: fixed;
      bottom: 28px; right: 24px;
      width: 42px; height: 42px;
      background: var(--navy);
      color: #fff;
      border: none;
      border-radius: 10px;
      font-size: 18px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 0;
      transform: translateY(8px);
      transition: var(--transition);
      z-index: 999;
      box-shadow: 0 4px 16px rgba(0,0,0,.25);
    }
    .back-to-top.visible { opacity: 1; transform: translateY(0); }
    .back-to-top:hover { background: var(--gold); color: var(--navy); }

    /* ===========================
       UTILITIES
    =========================== */
    .text-navy { color: var(--navy) !important; }
    .text-gold  { color: var(--gold) !important; }
    .text-emerald { color: var(--emerald) !important; }
    .bg-navy { background: var(--navy) !important; }
    .bg-cream { background: var(--cream) !important; }

    /* category color helpers */
    .cat-success { background: #D1FAE5; color: #065F46; }
    .cat-warning  { background: #FEF3C7; color: #92400E; }
    .cat-info     { background: #DBEAFE; color: #1E40AF; }
    .ann-icon-success { background: #D1FAE5; color: #059669; }
    .ann-icon-warning { background: #FEF3C7; color: #D97706; }
    .ann-icon-info    { background: #DBEAFE; color: #2563EB; }
    .ann-cat-success  { color: #059669; }
    .ann-cat-warning  { color: #D97706; }
    .ann-cat-info     { color: #2563EB; }

    /* responsive */
    @media (max-width: 767px) {
      .hero-content { padding: 3rem 0 2.5rem; }
      .hero-actions { flex-direction: column; }
      .hero-actions a { text-align: center; justify-content: center; }
      .hero-stat { min-width: 100px; }
      section { padding: 56px 0; }
      .cta-box { padding: 2.5rem 1.5rem; }
      .step-connector { display: none; }
      .footer-bottom { flex-direction: column; text-align: center; }
    }

    @media (max-width: 575px) {
      html, body { width: 100%; box-sizing: border-box; overflow-x: hidden; } 
      .hero-content { padding: 3rem 1.5rem 2.5rem; }
      .hero-actions { flex-direction: column; }
      .hero-actions a { text-align: center; justify-content: center; }
      .hero-stat { min-width: 100px; }
      section { padding: 56px 0; }
      .cta-box { padding: 2.5rem 1.5rem; }
      .step-connector { display: none; }
      .footer-bottom { flex-direction: column; text-align: center; }
    }
  </style>
</head>
<body>

<!-- ============================================================
     NAVBAR
============================================================ -->
<nav class="bms-nav" id="mainNav" role="navigation" aria-label="Main navigation">
  <div class="nav-inner">
    <a class="nav-brand" href="index.php" aria-label="<?= $barangay_name ?> homepage">
      <div class="nav-seal" aria-hidden="true"><i class="bi bi-shield-fill"></i></div>
      <div class="nav-brand-text">
        <div class="brgy-name"><?= $barangay_name ?></div>
        <div class="brgy-place"><?= $municipality ?></div>
      </div>
    </a>

    <div class="nav-links" role="menubar">
      <a href="index.php" class="active" role="menuitem">Home</a>
      <a href="officials.php" role="menuitem">Officials</a>
      <a href="services.php" role="menuitem">Services</a>
      <a href="announcements.php" role="menuitem">News</a>
      <a href="contact.php" role="menuitem">Contact</a>
    </div>

    <div class="nav-cta">
      <a href="login.php" class="btn-nav-login">Log In</a>
      <a href="register.php" class="btn-nav-register"><i class="bi bi-person-plus-fill"></i> Register</a>
    </div>

    <button class="nav-toggle" id="navToggle" aria-label="Toggle menu" aria-expanded="false">
      <i class="bi bi-list"></i>
    </button>
  </div>

  <!-- Mobile menu -->
  <div class="nav-mobile-menu" id="mobileMenu" role="menu">
    <a href="index.php" role="menuitem">Home</a>
    <a href="officials.php" role="menuitem">Officials</a>
    <a href="services.php" role="menuitem">Services</a>
    <a href="announcements.php" role="menuitem">News</a>
    <a href="contact.php" role="menuitem">Contact</a>
    <div class="mobile-cta">
      <a href="login.php" class="btn-nav-login" style="text-align:center" role="menuitem">Log In</a>
      <a href="register.php" class="btn-nav-register" style="text-align:center" role="menuitem">Register</a>
    </div>
  </div>
</nav>

<!-- ============================================================
     HERO
============================================================ -->
<section class="hero" id="home" aria-labelledby="hero-title">
  <div class="hero-pattern" aria-hidden="true"></div>
  <div class="hero-grid-lines" aria-hidden="true"></div>
  <div class="container align-items-center">
    <div class="row  g-5">
      <div class="col-lg-6 hero-content" data-aos="fade-right" data-aos-duration="900">
        <h1 class="hero-title" id="hero-title">
          Barangay Sta.<br>
          <span>Rosa 1</span><br>
          Serves You Online
        </h1>
        <p class="hero-sub">
          Request documents, track your applications, and access all barangay services — anywhere, anytime. Noveleta, Cavite.
        </p>
        <div class="hero-actions">
          <a href="login.php" class="btn-hero-primary">
            <i class="bi bi-person-plus-fill"></i> Create Resident Account
          </a>
          <a href="services.php" class="btn-hero-secondary">
            <i class="bi bi-file-earmark-text"></i> View Services
          </a>
        </div>
        <div class="hero-stats">
          <div class="hero-stat" data-aos="fade-up" data-aos-delay="100">
            <div class="hero-stat-num"><?= $total_residents ?></div>
            <div class="hero-stat-label">Registered Residents</div>
          </div>
          <div class="hero-stat" data-aos="fade-up" data-aos-delay="200">
            <div class="hero-stat-num"><?= $total_households ?></div>
            <div class="hero-stat-label">Households</div>
          </div>
          <div class="hero-stat" data-aos="fade-up" data-aos-delay="300">
            <div class="hero-stat-num"><?= $docs_this_month ?></div>
            <div class="hero-stat-label">Docs Issued This Month</div>
          </div>
        </div>
      </div>

      <div class="col-lg-5 offset-lg-1 hero-visual" data-aos="fade-left" data-aos-duration="900" data-aos-delay="150">
        <div class="hero-card-stack">
          <div class="hero-card-bg">
            <div class="hero-online-badge">
              <span class="dot-live"></span> Online Services Available
            </div>
            <div class="hero-card-title">Quick Document Request</div>
            <a href="login.php?request=clearance" class="quick-doc-btn">
              <i class="bi bi-patch-check-fill"></i>
              Barangay Clearance
              <i class="bi bi-arrow-right arrow"></i>
            </a>
            <a href="login.php?request=residency" class="quick-doc-btn">
              <i class="bi bi-house-heart-fill"></i>
              Certificate of Residency
              <i class="bi bi-arrow-right arrow"></i>
            </a>
            <a href="login.php?request=indigency" class="quick-doc-btn">
              <i class="bi bi-hand-heart-fill"></i>
              Certificate of Indigency
              <i class="bi bi-arrow-right arrow"></i>
            </a>
            <a href="login.php?request=business" class="quick-doc-btn">
              <i class="bi bi-shop-window"></i>
              Business Clearance
              <i class="bi bi-arrow-right arrow"></i>
            </a>
            <div style="margin-top:1rem;padding-top:.75rem;border-top:1px solid rgba(255,255,255,.1);text-align:center">
              <a href="services.php" style="font-size:12.5px;color:rgba(255,255,255,.45);text-decoration:none;">
                View all 6 document types <i class="bi bi-arrow-right" style="font-size:11px"></i>
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============================================================
     ANNOUNCEMENT TICKER
============================================================ -->
<div class="ticker-bar" role="marquee" aria-label="Latest announcements">
  <div class="container">
    <div class="ticker-inner">
      <div class="ticker-label"><i class="bi bi-megaphone-fill"></i> Latest</div>
      <div class="ticker-track">
        <span class="ticker-text">
          &nbsp;&nbsp;&nbsp;&nbsp;
          📢 Free Medical Mission — May 15 at the Barangay Health Center.
          &nbsp;&nbsp;●&nbsp;&nbsp;
          🗓 Lupon Tagapamayapa hearings every Tuesday and Thursday, 9AM.
          &nbsp;&nbsp;●&nbsp;&nbsp;
          📋 4Ps Compliance Check — May 20–22 at the Barangay Hall.
          &nbsp;&nbsp;●&nbsp;&nbsp;
          🏗 Barangay road improvement project starts May 25.
          &nbsp;&nbsp;&nbsp;&nbsp;
        </span>
      </div>
    </div>
  </div>
</div>

<!-- ============================================================
     SERVICES
============================================================ -->
<section class="services-section" id="services" aria-labelledby="services-heading">
  <div class="container">
    <div class="row mb-5" data-aos="fade-up">
      <div class="col-lg-7">
        <div class="section-eyebrow">Our Services</div>
        <h2 class="section-title" id="services-heading">Documents You Can Request Online</h2>
        <p class="section-sub">All documents are processed by the Barangay Secretary and signed by the Punong Barangay. Register for a free account to get started.</p>
      </div>
      <div class="col-lg-5 d-flex align-items-end justify-content-lg-end mt-3 mt-lg-0">
        <a href="services.php" class="btn" style="background:var(--navy);color:#fff;border-radius:10px;padding:11px 22px;font-weight:600;font-size:14px;display:flex;align-items:center;gap:6px;">
          All services <i class="bi bi-arrow-right"></i>
        </a>
      </div>
    </div>

    <div class="row g-4">
      <?php foreach ($services as $i => $svc): ?>
      <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="<?= $i * 80 ?>">
        <div class="service-card">
          <div class="service-icon-wrap">
            <i class="bi <?= $svc['icon'] ?>"></i>
          </div>
          <h3 class="service-title"><?= $svc['title'] ?></h3>
          <p class="service-desc"><?= $svc['desc'] ?></p>
          <div class="service-meta">
            <span class="meta-badge meta-fee"><i class="bi bi-cash-coin" style="margin-right:3px"></i><?= $svc['fee'] ?></span>
            <span class="meta-badge meta-time"><i class="bi bi-clock" style="margin-right:3px"></i><?= $svc['time'] ?></span>
          </div>
          <a href="login.php?request=<?= strtolower(str_replace(' ','-',$svc['title'])) ?>" class="service-link">
            Request now <i class="bi bi-arrow-right"></i>
          </a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ============================================================
     HOW IT WORKS
============================================================ -->
<section class="hiw-section" id="how-it-works" aria-labelledby="hiw-heading">
  <div class="container">
    <div class="row mb-5 justify-content-center text-center" data-aos="fade-up">
      <div class="col-lg-6">
        <div class="section-eyebrow" style="justify-content:center">How It Works</div>
        <h2 class="section-title" id="hiw-heading">Request a Document in 3 Easy Steps</h2>
        <p class="section-sub" style="margin:0 auto">No more long queues at the barangay hall. Process your document requests online, from the comfort of your home.</p>
      </div>
    </div>
    <div class="row g-4 justify-content-center">
      <div class="col-md-4" data-aos="fade-up" data-aos-delay="0">
        <div class="step-card">
          <div class="step-num">1</div>
          <div class="step-icon"><i class="bi bi-person-plus-fill"></i></div>
          <h3 class="step-title">Register & Log In</h3>
          <p class="step-desc">Create your free resident account. Your identity will be verified by the Barangay Secretary before your first request.</p>
        </div>
      </div>
      <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
        <div class="step-card">
          <div class="step-num">2</div>
          <div class="step-icon"><i class="bi bi-file-earmark-plus-fill"></i></div>
          <h3 class="step-title">Submit Your Request</h3>
          <p class="step-desc">Choose the document type, fill out the online form, and upload any required attachments. You'll receive a reference number instantly.</p>
        </div>
      </div>
      <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
        <div class="step-card">
          <div class="step-num">3</div>
          <div class="step-icon"><i class="bi bi-bag-check-fill"></i></div>
          <h3 class="step-title">Pick Up Your Document</h3>
          <p class="step-desc">You'll be notified via email or SMS when your document is ready. Pick it up at the barangay hall or download it if available.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============================================================
     ANNOUNCEMENTS
============================================================ -->
<section class="ann-section" id="announcements" aria-labelledby="ann-heading">
  <div class="container">
    <div class="row mb-5 align-items-end" data-aos="fade-up">
      <div class="col-lg-7">
        <div class="section-eyebrow">News & Updates</div>
        <h2 class="section-title" id="ann-heading">Latest Announcements</h2>
        <p class="section-sub">Stay updated with official notices, programs, events, and emergency advisories from Barangay Sta. Rosa 1.</p>
      </div>
      <div class="col-lg-5 d-flex align-items-end justify-content-lg-end mt-3 mt-lg-0">
        <a href="announcements.php" class="btn" style="background:var(--navy);color:#fff;border-radius:10px;padding:11px 22px;font-weight:600;font-size:14px;display:flex;align-items:center;gap:6px;">
          All announcements <i class="bi bi-arrow-right"></i>
        </a>
      </div>
    </div>

    <div class="row g-4">
      <?php foreach ($announcements as $i => $ann): ?>
      <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="<?= $i * 80 ?>">
        <article class="ann-card">
          <div class="ann-top">
            <div class="ann-icon-wrap ann-icon-<?= $ann['cat_color'] ?>">
              <i class="bi <?= $ann['icon'] ?>"></i>
            </div>
            <div>
              <div class="ann-cat ann-cat-<?= $ann['cat_color'] ?>"><?= $ann['category'] ?></div>
              <h3 class="ann-title"><?= $ann['title'] ?></h3>
            </div>
          </div>
          <p class="ann-excerpt"><?= $ann['excerpt'] ?></p>
          <div class="ann-footer">
            <span class="ann-date"><i class="bi bi-calendar3"></i> <?= $ann['date'] ?></span>
            <a href="announcements.php?id=<?= $i+1 ?>" class="ann-read">Read more <i class="bi bi-arrow-right"></i></a>
          </div>
        </article>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ============================================================
     OFFICIALS
============================================================ -->
<section class="officials-section" id="officials" aria-labelledby="officials-heading">
  <div class="container">
    <div class="row mb-5" data-aos="fade-up">
      <div class="col-lg-6">
        <div class="section-eyebrow">Your Officials</div>
        <h2 class="section-title" id="officials-heading">Meet Your Barangay Leaders</h2>
        <p class="section-sub">Elected to serve the residents of Barangay Sta. Rosa 1 for the term <?= $years_serving ?>.</p>
      </div>
      <div class="col-lg-5 offset-lg-1 d-flex align-items-end justify-content-lg-end mt-3 mt-lg-0">
        <a href="officials.php" class="btn" style="background:rgba(255,255,255,.1);color:#fff;border:1.5px solid rgba(255,255,255,.2);border-radius:10px;padding:11px 22px;font-weight:600;font-size:14px;display:flex;align-items:center;gap:6px;">
          Full directory <i class="bi bi-arrow-right"></i>
        </a>
      </div>
    </div>

    <div class="row g-4 align-items-start">
      <!-- Captain -->
      <div class="col-lg-4" data-aos="fade-up" data-aos-delay="0">
        <div class="captain-card">
          <div class="captain-avatar" aria-label="Punong Barangay avatar">
            <i class="bi bi-person-fill" aria-hidden="true"></i>
          </div>
          <div class="captain-name"><?= $captain_name ?></div>
          <div class="captain-role"><?= $captain_title ?></div>
          <div class="captain-meta">
            <div class="captain-meta-item"><i class="bi bi-calendar3"></i> <?= $years_serving ?></div>
            <div class="captain-meta-item"><i class="bi bi-geo-alt"></i> <?= $municipality ?></div>
          </div>
        </div>
      </div>

      <!-- Kagawads -->
      <div class="col-lg-8" data-aos="fade-up" data-aos-delay="100">
        <div class="kagawad-list">
          <?php
          $kagawads = [
            ["name"=>"Hon. [Kagawad 1]", "committee"=>"Committee on Health & Sanitation"],
            ["name"=>"Hon. [Kagawad 2]", "committee"=>"Committee on Education & Culture"],
            ["name"=>"Hon. [Kagawad 3]", "committee"=>"Committee on Peace, Order & Public Safety"],
            ["name"=>"Hon. [Kagawad 4]", "committee"=>"Committee on Infrastructure & Public Works"],
            ["name"=>"Hon. [Kagawad 5]", "committee"=>"Committee on Livelihood & Economic Affairs"],
            ["name"=>"Hon. [Kagawad 6]", "committee"=>"Committee on Environment & Natural Resources"],
            ["name"=>"Hon. [Kagawad 7]", "committee"=>"Committee on Women, Family & Senior Citizens"],
            ["name"=>"Hon. [SK Chairman]", "committee"=>"Sangguniang Kabataan Chairperson"],
          ];
          foreach ($kagawads as $kag): ?>
          <div class="kagawad-item">
            <div class="kagawad-avatar" aria-hidden="true"><i class="bi bi-person-fill"></i></div>
            <div>
              <div class="kagawad-name"><?= $kag['name'] ?></div>
              <div class="kagawad-committee"><?= $kag['committee'] ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============================================================
     CTA
============================================================ -->
<section class="cta-section" aria-labelledby="cta-heading">
  <div class="container">
    <div class="cta-box" data-aos="zoom-in" data-aos-duration="700">
      <h2 class="cta-title" id="cta-heading">Ready to Access Barangay Services Online?</h2>
      <p class="cta-sub">Create your free resident account today and experience a faster, more convenient way to get your barangay documents.</p>
      <div class="cta-buttons">
        <a href="login.php" class="btn-cta-primary">
          <i class="bi bi-person-plus-fill"></i> Create Free Account
        </a>
        <a href="login.php" class="btn-cta-secondary">
          <i class="bi bi-box-arrow-in-right"></i> I Already Have an Account
        </a>
      </div>
    </div>
  </div>
</section>

<!-- ============================================================
     CONTACT STRIP
============================================================ -->
<div class="contact-strip" id="contact" aria-label="Contact information">
  <div class="container">
    <div class="row g-4">
      <div class="col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="0">
        <div class="contact-item">
          <div class="contact-icon"><i class="bi bi-geo-alt-fill"></i></div>
          <div>
            <div class="contact-label">Address</div>
            <div class="contact-value">Brgy. Sta. Rosa 1,<br>Noveleta, Cavite</div>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="80">
        <div class="contact-item">
          <div class="contact-icon"><i class="bi bi-telephone-fill"></i></div>
          <div>
            <div class="contact-label">Phone / Hotline</div>
            <div class="contact-value">+63 912 XXX XXXX</div>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="160">
        <div class="contact-item">
          <div class="contact-icon"><i class="bi bi-envelope-fill"></i></div>
          <div>
            <div class="contact-label">Email</div>
            <div class="contact-value">starosa1@noveleta.gov.ph</div>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="240">
        <div class="contact-item">
          <div class="contact-icon"><i class="bi bi-clock-fill"></i></div>
          <div>
            <div class="contact-label">Office Hours</div>
            <div class="contact-value">Mon–Fri, 8:00 AM – 5:00 PM</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ============================================================
     FOOTER
============================================================ -->
<footer role="contentinfo">
  <div class="container">
    <div class="row g-5">
      <div class="col-lg-4">
        <div class="footer-brand">
          <div class="footer-seal"><i class="bi bi-shield-fill"></i></div>
          <div class="footer-name"><?= $barangay_name ?></div>
          <div class="footer-muni"><?= $municipality ?></div>
        </div>
        <p class="footer-desc">Committed to serving every household of Barangay Sta. Rosa 1 through transparent, efficient, and technology-enabled governance.</p>
        <div class="footer-socials" style="margin-top:1rem;">
          <a href="#" class="social-btn" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
          <a href="#" class="social-btn" aria-label="Email"><i class="bi bi-envelope-fill"></i></a>
          <a href="#" class="social-btn" aria-label="Phone"><i class="bi bi-telephone-fill"></i></a>
        </div>
      </div>

      <div class="col-sm-6 col-lg-2">
        <div class="footer-heading">Services</div>
        <ul class="footer-links">
          <li><a href="services.php#clearance">Barangay Clearance</a></li>
          <li><a href="services.php#residency">Certificate of Residency</a></li>
          <li><a href="services.php#indigency">Certificate of Indigency</a></li>
          <li><a href="services.php#business">Business Clearance</a></li>
          <li><a href="services.php#certification">Barangay Certification</a></li>
          <li><a href="services.php#blotter">Blotter Certificate</a></li>
        </ul>
      </div>

      <div class="col-sm-6 col-lg-2">
        <div class="footer-heading">Quick Links</div>
        <ul class="footer-links">
          <li><a href="index.php">Home</a></li>
          <li><a href="officials.php">Officials</a></li>
          <li><a href="announcements.php">Announcements</a></li>
          <li><a href="contact.php">Contact Us</a></li>
          <li><a href="login.php">Register</a></li>
          <li><a href="login.php">Resident Login</a></li>
        </ul>
      </div>

      <div class="col-sm-6 col-lg-2">
        <div class="footer-heading">Emergency</div>
        <ul class="footer-links">
          <li><a href="tel:166">PNP Hotline: 166</a></li>
          <li><a href="tel:911">Emergency: 911</a></li>
          <li><a href="tel:1555">BFP (Fire): 1555</a></li>
          <li><a href="tel:8525-0000">NDRRMC: 8525-0000</a></li>
        </ul>
      </div>

      <div class="col-sm-6 col-lg-2">
        <div class="footer-heading">Government</div>
        <ul class="footer-links">
          <li><a href="https://noveleta.gov.ph" target="_blank" rel="noopener">Noveleta LGU</a></li>
          <li><a href="https://caviteprovince.gov.ph" target="_blank" rel="noopener">Province of Cavite</a></li>
          <li><a href="https://dilg.gov.ph" target="_blank" rel="noopener">DILG</a></li>
          <li><a href="https://bagong.pilipinas.gov.ph" target="_blank" rel="noopener">Bagong Pilipinas</a></li>
        </ul>
      </div>
    </div>

    <hr class="footer-divider">
    <div class="footer-bottom">
      <span>&copy; <?= date('Y') ?> <?= $barangay_name ?>, <?= $municipality ?>. All rights reserved.</span>
      <span>
        <a href="privacy.php">Privacy Policy</a> &nbsp;·&nbsp;
        <a href="terms.php">Terms of Use</a> &nbsp;·&nbsp;
        <a href="login.php" style="opacity:.4">Admin</a>
      </span>
    </div>
  </div>
</footer>

<!-- Back to top -->
<button class="back-to-top" id="backToTop" aria-label="Back to top">
  <i class="bi bi-chevron-up"></i>
</button>

<!-- ============================================================
     SCRIPTS
============================================================ -->
<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- AOS -->
<script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>

<script>
  // === AOS Init ===
  AOS.init({ once: true, offset: 60, duration: 700, easing: 'ease-out-cubic' });

  // === Navbar scroll effect ===
  const nav = document.getElementById('mainNav');
  window.addEventListener('scroll', () => {
    nav.classList.toggle('scrolled', window.scrollY > 40);
  }, { passive: true });

  // === Mobile nav toggle ===
  const navToggle = document.getElementById('navToggle');
  const mobileMenu = document.getElementById('mobileMenu');
  navToggle.addEventListener('click', () => {
    const isOpen = mobileMenu.classList.toggle('open');
    navToggle.setAttribute('aria-expanded', isOpen);
    navToggle.innerHTML = isOpen
      ? '<i class="bi bi-x-lg"></i>'
      : '<i class="bi bi-list"></i>';
  });

  // Close mobile menu on link click
  mobileMenu.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', () => {
      mobileMenu.classList.remove('open');
      navToggle.setAttribute('aria-expanded', 'false');
      navToggle.innerHTML = '<i class="bi bi-list"></i>';
    });
  });

  // === Active nav link on scroll ===
  const sections = document.querySelectorAll('section[id], div[id="contact"]');
  const navLinks = document.querySelectorAll('.nav-links a');
  const sectionMap = { home:'/', services:'services.php', 'how-it-works':null, announcements:'announcements.php', officials:'officials.php', contact:'contact.php' };

  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        navLinks.forEach(link => link.classList.remove('active'));
        const target = entry.target.id;
        navLinks.forEach(link => {
          const href = link.getAttribute('href');
          if (
            (target === 'home' && (href === 'index.php' || href === '/')) ||
            (target !== 'home' && href === sectionMap[target])
          ) link.classList.add('active');
        });
      }
    });
  }, { threshold: 0.3 });

  sections.forEach(s => observer.observe(s));

  // === Back to top ===
  const btt = document.getElementById('backToTop');
  window.addEventListener('scroll', () => {
    btt.classList.toggle('visible', window.scrollY > 400);
  }, { passive: true });
  btt.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

  // === Animated counters ===
  function animateCounter(el, target, duration = 1800) {
    const isFormatted = target.includes(',');
    const num = parseInt(target.replace(/,/g, ''));
    const start = performance.now();
    const update = (now) => {
      const elapsed = now - start;
      const progress = Math.min(elapsed / duration, 1);
      const ease = 1 - Math.pow(1 - progress, 3);
      const current = Math.round(num * ease);
      el.textContent = isFormatted ? current.toLocaleString('en-PH') : current;
      if (progress < 1) requestAnimationFrame(update);
    };
    requestAnimationFrame(update);
  }

  const counterObserver = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.querySelectorAll('.hero-stat-num').forEach(el => {
          animateCounter(el, el.textContent.trim());
        });
        counterObserver.unobserve(entry.target);
      }
    });
  }, { threshold: 0.5 });

  document.querySelectorAll('.hero-stats').forEach(el => counterObserver.observe(el));
</script>

</body>
</html>
