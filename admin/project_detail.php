<?php
require_once __DIR__ . '/includes/admin_layout.php';

$csrf = adm_action_token();
adm_ensure_project_tables($conn);

/* SINGLE SOURCE OF USER (ONLY ONCE) */
$user = adm_user($conn);

$role = strtolower(trim($user['role'] ?? ''));
$user_id = (int)$user['id'];

// ── Validate project_id ──────────────────────────────────────
$project_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($project_id <= 0) {
    header('Location: project_detail.php?error=invalid_id');
    exit;
}

// ── Fetch Program (with role-based committee filter) ─────────
if ($role === 'kagawad') {
    // Kagawad may only see programs under their own committee
    $stmt = $conn->prepare(
        "SELECT *
         FROM projects
         WHERE id = ?
           AND committee = (
               SELECT committee
               FROM officials
               WHERE user_id = ?
               LIMIT 1
           )
         LIMIT 1"
    );
    $stmt->bind_param('ii', $project_id, $user_id);
} else {
    // Captain / admin sees all
    $stmt = $conn->prepare(
        "SELECT *
         FROM projects
         WHERE id = ?
         LIMIT 1"
    );
    $stmt->bind_param('i', $project_id);
}

$stmt->execute();
$result  = $stmt->get_result();
$program = $result->fetch_assoc();
$stmt->close();

if (!$program) {
    header('Location: project_detail.php?error=not_found');
    exit;
}

// ── Fetch Gallery Images ─────────────────────────────────────
$imgs_stmt = $conn->prepare(
    "SELECT id, file_path, uploaded_at
       FROM project_photos
      WHERE project_id = ?
      ORDER BY uploaded_at ASC"
);
$imgs_stmt->bind_param('i', $project_id);
$imgs_stmt->execute();
$gallery = $imgs_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$imgs_stmt->close();

// ── Fetch Activity Timeline ──────────────────────────────────
$timeline_stmt = $conn->prepare(
    "SELECT 
        al.action,
        al.old_values,
        al.new_values,
        al.created_at,
        u.fullname
     FROM audit_logs al
     LEFT JOIN users u ON u.id = al.user_id
     WHERE al.record_id = ?
       AND al.table_name = 'projects'
     ORDER BY al.created_at DESC
     LIMIT 50"
);

$timeline_stmt->bind_param('i', $project_id);
$timeline_stmt->execute();

$result = $timeline_stmt->get_result();
$timeline = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

$timeline_stmt->close();

// ────────────────────────────────────────────────────────────
//  POST HANDLERS
// ────────────────────────────────────────────────────────────

$flash = [];

// ── Handle: Update Progress ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'update_progress') {

    // 1. DEFINE FIRST
    $new_progress = max(0, min(100, (int)($_POST['progress_percent'] ?? 0)));

    // 2. UPDATE
    $upd = $conn->prepare("UPDATE projects SET progress_percent = ? WHERE id = ?");
    $upd->bind_param('ii', $new_progress, $project_id);

    if ($upd->execute()) {

        $program['progress_percent'] = $new_progress;

        // 3. LOG (same scope)
        $new_values = json_encode([
            'progress_percent' => $new_progress
        ]);

        $log = $conn->prepare(
            "INSERT INTO audit_logs (user_id, action, table_name, record_id, new_values, created_at)
             VALUES (?, 'progress_update', 'projects', ?, ?, NOW())"
        );

        $log->bind_param('iis', $user_id, $project_id, $new_values);
        $log->execute();
        $log->close();
    }

    $upd->close();
}

// ── Handle: Update Status ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $allowed_statuses = ['Planning', 'Ongoing', 'Completed', 'On Hold'];
    $new_status = trim($_POST['status'] ?? '');

    if (in_array($new_status, $allowed_statuses, true)) {
        $upd = $conn->prepare("UPDATE projects SET status = ? WHERE id = ?");
        $upd->bind_param('si', $new_status, $project_id);
        if ($upd->execute()) {
            $program['status'] = $new_status;
            $flash = ['type' => 'success', 'msg' => 'Status updated successfully.'];

            $log = $conn->prepare(
                "INSERT INTO audit_logs 
                    (user_id, action, table_name, record_id, new_values, created_at)
                VALUES (?, 'progress_update', 'projects', ?, ?, NOW())"
            );

            $new_values = json_encode([
                'status' => $new_status
            ]);

            $log->bind_param('iis', $user_id, $project_id, $new_values);
            $log->execute();
            $log->close();
        } else {
            $flash = ['type' => 'error', 'msg' => 'Failed to update status.'];
        }
        $upd->close();
    } else {
        $flash = ['type' => 'error', 'msg' => 'Invalid status value.'];
    }
}

// ── Handle: Upload Photos ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_photos') {
    $upload_dir = '../uploads/programs/' . $project_id . '/';

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
    $max_size      = 5 * 1024 * 1024; // 5 MB
    $uploaded      = 0;
    $errors        = [];

    if (!empty($_FILES['photos']['name'][0])) {
        $files = $_FILES['photos'];
        $count = count($files['name']);

        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                $errors[] = "File #{$i} had an upload error.";
                continue;
            }

            $tmp  = $files['tmp_name'][$i];
            $size = $files['size'][$i];
            $mime = mime_content_type($tmp);

            if (!in_array($mime, $allowed_types, true)) {
                $errors[] = htmlspecialchars($files['name'][$i]) . ': only JPG, PNG, or WebP allowed.';
                continue;
            }

            if ($size > $max_size) {
                $errors[] = htmlspecialchars($files['name'][$i]) . ': exceeds 5 MB limit.';
                continue;
            }

            $ext      = $mime === 'image/png' ? 'png' : ($mime === 'image/webp' ? 'webp' : 'jpg');
            $filename = uniqid('img_', true) . '.' . $ext;
            $dest     = $upload_dir . $filename;

            if (move_uploaded_file($tmp, $dest)) {
                $rel_path = 'uploads/programs/' . $project_id . '/' . $filename;
                $ins = $conn->prepare(
                    "INSERT INTO program_images (program_id, file_path, uploaded_by, uploaded_at)
                     VALUES (?, ?, ?, NOW())"
                );
                $ins->bind_param('isi', $project_id, $rel_path, $user_id);
                $ins->execute();
                $ins->close();
                $uploaded++;
            } else {
                $errors[] = 'Could not save ' . htmlspecialchars($files['name'][$i]) . '.';
            }
        }
    }

    if ($uploaded > 0) {
        $flash = ['type' => 'success', 'msg' => "{$uploaded} photo(s) uploaded successfully." . ($errors ? ' Some files were skipped.' : '')];
        // Refresh gallery
        $imgs_stmt2 = $conn->prepare(
            "SELECT id, file_path, caption, uploaded_at FROM program_images WHERE program_id = ? ORDER BY uploaded_at ASC"
        );
        $imgs_stmt2->bind_param('i', $project_id);
        $imgs_stmt2->execute();
        $gallery = $imgs_stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
        $imgs_stmt2->close();
    } elseif (!empty($errors)) {
        $flash = ['type' => 'error', 'msg' => implode(' ', $errors)];
    } else {
        $flash = ['type' => 'error', 'msg' => 'No files were uploaded.'];
    }
}

// ── Helpers ──────────────────────────────────────────────────
function status_class(string $status): string {
    return match ($status) {
        'Ongoing'   => 'badge--teal',
        'Completed' => 'badge--success',
        'On Hold'   => 'badge--amber',
        default     => 'badge--neutral',   // Planning
    };
}

function progress_color(int $pct): string {
    if ($pct >= 80) return 'var(--teal)';
    if ($pct >= 40) return 'var(--gold-light)';
    return 'var(--danger)';
}

$progress = (int) ($program['progress_percent'] ?? 0);
$pcolor   = progress_color($progress);

// Format currency
function fmt_budget($val): string {
    if ($val === null || $val === '') return '—';
    return '₱ ' . number_format((float) $val, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($program['title']) ?> — Program Detail</title>
  <link rel="stylesheet" href="assets/css/kagawad.css">
  <style>
    /* ── Page-specific styles (piggy-backing on kagawad.css tokens) ── */

    /* Flash banner */
    .flash {
      padding: 12px var(--content-gutter);
      font-size: 14px;
      font-weight: 500;
      border-bottom: 1px solid transparent;
    }
    .flash--success { background: var(--success-soft); color: var(--teal);   border-color: rgba(22,163,74,.2); }
    .flash--error   { background: var(--danger-soft);  color: var(--danger); border-color: rgba(220,38,38,.2); }

    /* ── Main layout ── */
    .detail-wrapper {
      max-width: 1080px;
      margin: 0 auto;
      padding: var(--content-gutter);
      display: grid;
      gap: 20px;
    }

    /* Back link */
    .back-link {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 13px;
      font-weight: 500;
      color: var(--muted);
      text-decoration: none;
      transition: color var(--transition);
    }
    .back-link:hover { color: var(--text); }
    .back-link svg   { flex-shrink: 0; }

    /* ── Card ── */
    .card {
      background: var(--surface);
      border: 1px solid var(--line);
      border-radius: var(--radius);
      padding: var(--card-pad);
      box-shadow: var(--shadow-soft);
    }
    .card-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 12px;
      margin-bottom: 16px;
      flex-wrap: wrap;
    }
    .card-title {
      font-size: 15px;
      font-weight: 700;
      color: var(--text);
      letter-spacing: -.01em;
      margin: 0;
    }

    /* ── Overview card ── */
    .overview-title {
      font-size: clamp(20px, 3vw, 26px);
      font-weight: 800;
      color: var(--text);
      letter-spacing: -.02em;
      margin: 0 0 4px;
      line-height: 1.25;
    }
    .overview-sub {
      color: var(--muted);
      font-size: 13px;
      margin: 0 0 18px;
    }
    .overview-meta {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
      gap: 12px 24px;
      padding-top: 16px;
      border-top: 1px solid var(--line);
    }
    .meta-item label {
      display: block;
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: var(--faint);
      margin-bottom: 3px;
    }
    .meta-item span {
      font-size: 14px;
      font-weight: 600;
      color: var(--text);
    }
    .description-block {
      margin-top: 16px;
      padding-top: 16px;
      border-top: 1px solid var(--line);
      font-size: 14px;
      color: var(--muted);
      line-height: 1.7;
    }
    .description-block strong {
      display: block;
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: var(--faint);
      margin-bottom: 6px;
    }

    /* Badge */
    .badge {
      display: inline-flex;
      align-items: center;
      padding: 3px 10px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      letter-spacing: .02em;
      white-space: nowrap;
    }
    .badge--teal    { background: var(--teal-soft);    color: var(--teal);   }
    .badge--success { background: var(--success-soft); color: var(--success); }
    .badge--amber   { background: var(--amber-soft);   color: var(--amber);  }
    .badge--neutral { background: var(--neutral-soft); color: var(--muted);  }

    /* ── Progress tracker ── */
    .progress-section { display: flex; flex-direction: column; gap: 10px; }
    .progress-label {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .progress-pct {
      font-size: 28px;
      font-weight: 800;
      color: var(--text);
      letter-spacing: -.03em;
      line-height: 1;
    }
    .progress-pct span { font-size: 16px; font-weight: 500; color: var(--muted); }
    .progress-track {
      height: 14px;
      background: var(--surface-soft);
      border: 1px solid var(--line);
      border-radius: 99px;
      overflow: hidden;
    }
    .progress-fill {
      height: 100%;
      border-radius: 99px;
      transition: width .6s cubic-bezier(.4,0,.2,1);
    }
    .progress-hint { font-size: 12px; color: var(--faint); }

    /* ── Gallery ── */
    .gallery-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
      gap: 10px;
    }
    .gallery-item {
      position: relative;
      aspect-ratio: 1;
      border-radius: 6px;
      overflow: hidden;
      cursor: pointer;
      border: 1px solid var(--line);
      background: var(--surface-soft);
    }
    .gallery-item img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
      transition: transform .3s ease;
    }
    .gallery-item:hover img { transform: scale(1.06); }
    .gallery-overlay {
      position: absolute;
      inset: 0;
      background: rgba(11,37,69,.35);
      opacity: 0;
      transition: opacity .2s;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .gallery-item:hover .gallery-overlay { opacity: 1; }
    .gallery-overlay svg { color: #fff; }
    .gallery-empty {
      grid-column: 1/-1;
      text-align: center;
      padding: 36px;
      color: var(--faint);
      font-size: 13px;
    }

    /* ── Lightbox ── */
    .lightbox {
      display: none;
      position: fixed;
      inset: 0;
      z-index: 1000;
      background: var(--overlay);
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .lightbox.open { display: flex; }
    .lightbox-inner {
      position: relative;
      max-width: 860px;
      max-height: 90vh;
      width: 100%;
    }
    .lightbox-inner img {
      width: 100%;
      max-height: 85vh;
      object-fit: contain;
      border-radius: 8px;
      box-shadow: var(--shadow);
      display: block;
    }
    .lightbox-close {
      position: absolute;
      top: -14px;
      right: -14px;
      background: var(--surface);
      border: 1px solid var(--line);
      border-radius: 50%;
      width: 34px;
      height: 34px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      color: var(--text);
      transition: background var(--transition);
    }
    .lightbox-close:hover { background: var(--danger-soft); color: var(--danger); }

    /* ── Upload zone ── */
    .upload-zone {
      border: 2px dashed var(--line-strong);
      border-radius: var(--radius);
      padding: 28px;
      text-align: center;
      cursor: pointer;
      transition: border-color var(--transition), background var(--transition);
      background: var(--surface-soft);
    }
    .upload-zone:hover,
    .upload-zone.dragover {
      border-color: var(--gold);
      background: var(--accent-bg-faint);
    }
    .upload-zone input[type="file"] { display: none; }
    .upload-zone-icon { color: var(--faint); margin-bottom: 8px; }
    .upload-zone p   { margin: 0; font-size: 13px; color: var(--muted); }
    .upload-zone strong { color: var(--text); }
    .upload-preview {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 12px;
    }
    .upload-thumb {
      width: 60px;
      height: 60px;
      border-radius: 6px;
      object-fit: cover;
      border: 1px solid var(--line);
    }

    /* ── Action panels (two-col on wide screens) ── */
    .action-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
    }
    @media (max-width: 680px) {
      .action-grid { grid-template-columns: 1fr; }
    }

    /* ── Form controls ── */
    .form-group      { display: flex; flex-direction: column; gap: 6px; margin-bottom: 14px; }
    .form-group:last-child { margin-bottom: 0; }
    .form-label      { font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .05em; }
    .form-control, .form-select {
      width: 100%;
      padding: 9px 12px;
      border: 1px solid var(--line-strong);
      border-radius: 6px;
      font-size: 14px;
      font-family: inherit;
      background: var(--surface);
      color: var(--text);
      appearance: none;
      transition: border-color var(--transition), box-shadow var(--transition);
    }
    .form-control:focus, .form-select:focus {
      outline: none;
      border-color: var(--gold);
      box-shadow: 0 0 0 3px var(--accent-ring);
    }
    .range-wrap { display: flex; align-items: center; gap: 10px; }
    input[type="range"] {
      flex: 1;
      accent-color: var(--gold-light);
      cursor: pointer;
    }
    .range-val {
      min-width: 44px;
      text-align: center;
      font-size: 15px;
      font-weight: 700;
      color: var(--text);
      background: var(--surface-soft);
      border: 1px solid var(--line);
      border-radius: 6px;
      padding: 4px 8px;
    }

    /* ── Buttons ── */
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 7px;
      padding: 9px 18px;
      border-radius: 7px;
      border: 1px solid transparent;
      font-size: 14px;
      font-weight: 600;
      font-family: inherit;
      cursor: pointer;
      transition: background var(--transition), border-color var(--transition), color var(--transition), box-shadow var(--transition);
      text-decoration: none;
      white-space: nowrap;
    }
    .btn-primary {
      background: var(--primary);
      color: #fff;
      border-color: var(--primary);
    }
    .btn-primary:hover { background: var(--primary-dark); border-color: var(--primary-dark); }
    body.dark-mode .btn-primary { color: var(--brand-dark); }
    body.dark-mode .btn-primary:hover { background: var(--gold-light); border-color: var(--gold-light); }

    .btn-sm { padding: 7px 13px; font-size: 13px; }
    .btn-block { width: 100%; }

    /* ── Timeline ── */
    .timeline { display: flex; flex-direction: column; gap: 0; }
    .timeline-item {
      display: flex;
      gap: 14px;
      position: relative;
      padding-bottom: 20px;
    }
    .timeline-item:last-child { padding-bottom: 0; }
    .timeline-dot-col {
      display: flex;
      flex-direction: column;
      align-items: center;
      flex-shrink: 0;
    }
    .timeline-dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      background: var(--gold);
      border: 2px solid var(--surface);
      outline: 2px solid var(--gold);
      margin-top: 4px;
      flex-shrink: 0;
    }
    .timeline-line {
      width: 1px;
      flex: 1;
      background: var(--line);
      margin-top: 4px;
    }
    .timeline-body { flex: 1; }
    .timeline-meta {
      font-size: 12px;
      color: var(--faint);
      margin-bottom: 2px;
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      align-items: center;
    }
    .timeline-action {
      display: inline-block;
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .05em;
      background: var(--neutral-soft);
      color: var(--muted);
      padding: 2px 7px;
      border-radius: 4px;
    }
    .timeline-notes {
      font-size: 13px;
      color: var(--text);
      margin: 0;
    }
    .timeline-empty {
      text-align: center;
      padding: 28px;
      color: var(--faint);
      font-size: 13px;
    }
    .timeline-placeholder {
      border: 1px dashed var(--line-strong);
      border-radius: var(--radius);
      padding: 20px;
      text-align: center;
      color: var(--faint);
      font-size: 13px;
      margin-top: 4px;
    }
    .timeline-placeholder strong {
      display: block;
      color: var(--muted);
      font-size: 14px;
      margin-bottom: 4px;
    }

    /* Responsive tweaks */
    @media (max-width: 480px) {
      .overview-meta { grid-template-columns: 1fr 1fr; }
      .gallery-grid  { grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); }
    }
  </style>
</head>
<body>

<?php /* ── Topbar / Sidebar assumed to be an include ── */ ?>
<?php if (file_exists('../includes/topbar.php')) include '../includes/topbar.php'; ?>
<?php if (file_exists('../includes/sidebar.php')) include '../includes/sidebar.php'; ?>

<?php if ($flash): ?>
  <div class="flash flash--<?= $flash['type'] ?>">
    <?= htmlspecialchars($flash['msg']) ?>
  </div>
<?php endif; ?>

<main class="detail-wrapper">

  <!-- Back -->
  <div>
    <a href="projects.php" class="back-link">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
      Back to Programs
    </a>
  </div>

  <!-- ══════════════════════════════════════════════════════
       1. Program Overview Card
       ══════════════════════════════════════════════════════ -->
  <div class="card">
    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:14px;">
      <div>
        <h1 class="overview-title"><?= htmlspecialchars($program['title']) ?></h1>
        <p class="overview-sub"><?= htmlspecialchars($program['committee_name'] ?? '—') ?></p>
      </div>
      <span class="badge <?= status_class($program['status'] ?? 'Planning') ?>">
        <?= htmlspecialchars($program['status'] ?? 'Planning') ?>
      </span>
    </div>

    <div class="overview-meta">
      <div class="meta-item">
        <label>Committee</label>
        <span><?= htmlspecialchars($program['committee_name'] ?? '—') ?></span>
      </div>
      <div class="meta-item">
        <label>Category</label>
        <span><?= htmlspecialchars($program['category'] ?? '—') ?></span>
      </div>
      <div class="meta-item">
        <label>Start Date</label>
        <span><?= $program['start_date'] ? date('M j, Y', strtotime($program['start_date'])) : '—' ?></span>
      </div>
      <div class="meta-item">
        <label>End Date</label>
        <span><?= $program['target_end_date'] ? date('M j, Y', strtotime($program['target_end_date'])) : '—' ?></span>
      </div>
      <div class="meta-item">
        <label>Estimated Budget</label>
        <span><?= fmt_budget($program['budget'] ?? null) ?></span>
      </div>
    </div>

    <?php if (!empty($program['description'])): ?>
    <div class="description-block">
      <strong>Description</strong>
      <?= nl2br(htmlspecialchars($program['description'])) ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- ══════════════════════════════════════════════════════
       2. Progress Tracker
       ══════════════════════════════════════════════════════ -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Progress</h2>
    </div>
    <div class="progress-section">
      <div class="progress-label">
        <div class="progress-pct" id="prog-display">
          <?= $progress ?><span>%</span>
        </div>
        <span class="progress-hint">Overall completion</span>
      </div>
      <div class="progress-track">
        <div class="progress-fill" id="prog-fill"
             style="width:<?= $progress ?>%; background:<?= $pcolor ?>;"></div>
      </div>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════
       3. Photo Gallery
       ══════════════════════════════════════════════════════ -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Photo Gallery</h2>
      <span style="font-size:12px;color:var(--faint);"><?= count($gallery) ?> photo<?= count($gallery) !== 1 ? 's' : '' ?></span>
    </div>
    <div class="gallery-grid" id="galleryGrid">
      <?php if (empty($gallery)): ?>
        <div class="gallery-empty">No photos uploaded yet.</div>
      <?php else: ?>
        <?php foreach ($gallery as $img): ?>
          <div class="gallery-item" onclick="openLightbox('<?= htmlspecialchars('../' . $img['file_path']) ?>')">
            <img src="../<?= htmlspecialchars($img['file_path']) ?>"
                 alt="Program photo"
                 loading="lazy">
            <div class="gallery-overlay">
              <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/>
              </svg>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════
       4 & 5 & 6. Action Panels (Upload, Progress, Status)
       ══════════════════════════════════════════════════════ -->

  <!-- Upload Photos -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Add Photos</h2>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="upload_photos">
      <div class="upload-zone" id="uploadZone" onclick="document.getElementById('photoInput').click()">
        <div class="upload-zone-icon">
          <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/>
            <polyline points="21,15 16,10 5,21"/>
          </svg>
        </div>
        <p><strong>Click to upload</strong> or drag and drop</p>
        <p>JPG, PNG, WebP — max 5 MB each</p>
        <input type="file" id="photoInput" name="photos[]" multiple accept="image/jpeg,image/png,image/webp">
      </div>
      <div class="upload-preview" id="uploadPreview"></div>
      <div style="margin-top:14px;">
        <button type="submit" class="btn btn-primary btn-block">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
          </svg>
          Upload Photos
        </button>
      </div>
    </form>
  </div>

  <!-- Update Progress + Update Status side-by-side -->
  <div class="action-grid">

    <!-- Update Progress -->
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Update Progress</h2>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="update_progress">
        <div class="form-group">
          <label class="form-label" for="progressSlider">Completion (%)</label>
          <div class="range-wrap">
            <input type="range" id="progressSlider" name="progress"
                   min="0" max="100" value="<?= $progress ?>"
                   oninput="syncProgress(this.value)">
            <span class="range-val" id="sliderVal"><?= $progress ?></span>
          </div>
          <input type="number" class="form-control" id="progressNum"
                 name="progress_display" min="0" max="100" value="<?= $progress ?>"
                 placeholder="0–100"
                 oninput="syncProgressNum(this.value)"
                 style="margin-top:8px; display:none;">
        </div>
        <button type="submit" class="btn btn-primary btn-block">Save Progress</button>
      </form>
    </div>

    <!-- Update Status -->
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Update Status</h2>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="update_status">
        <div class="form-group">
          <label class="form-label" for="statusSelect">Current Status</label>
          <select class="form-select" name="status" id="statusSelect">
            <?php
            $statuses = ['Planning', 'Ongoing', 'Completed', 'On Hold'];
            foreach ($statuses as $s): ?>
              <option value="<?= $s ?>" <?= ($program['status'] ?? '') === $s ? 'selected' : '' ?>>
                <?= $s ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Save Status</button>
      </form>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════
       7. Activity Timeline
       ══════════════════════════════════════════════════════ -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Activity Timeline</h2>
    </div>

    <?php if (!empty($timeline)): ?>
      <div class="timeline">
        <?php foreach ($timeline as $idx => $entry): ?>
          <div class="timeline-item">
            <div class="timeline-dot-col">
              <div class="timeline-dot"></div>
              <?php if ($idx < count($timeline) - 1): ?>
                <div class="timeline-line"></div>
              <?php endif; ?>
            </div>
            <div class="timeline-body">
              <div class="timeline-meta">
                <span class="timeline-action"><?= htmlspecialchars(str_replace('_', ' ', $entry['action'] ?? '—')) ?></span>
                <?php if (!empty($entry['full_name'])): ?>
                  <span>by <?= htmlspecialchars($entry['full_name']) ?></span>
                <?php endif; ?>
                <span><?= !empty($entry['created_at']) ? date('M j, Y · g:i A', strtotime($entry['created_at'])) : '' ?></span>
              </div>
              <?php if (!empty($entry['notes'])): ?>
                <p class="timeline-notes"><?= htmlspecialchars($entry['notes']) ?></p>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

    <?php else: ?>
      <div class="timeline-placeholder">
        <strong>No activity recorded yet</strong>
        Activity entries will appear here as the program is updated — progress changes, status updates, and photo uploads are tracked automatically.
      </div>
    <?php endif; ?>
  </div>

</main>

<!-- ══════════════════════════════════════════════════════
     Lightbox
     ══════════════════════════════════════════════════════ -->
<div class="lightbox" id="lightbox" onclick="closeLightbox(event)">
  <div class="lightbox-inner" id="lightboxInner">
    <button class="lightbox-close" onclick="closeLightbox()" title="Close">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
      </svg>
    </button>
    <img src="" id="lightboxImg" alt="Preview">
  </div>
</div>

<script>
// ── Lightbox ──────────────────────────────────────────────
function openLightbox(src) {
  document.getElementById('lightboxImg').src = src;
  document.getElementById('lightbox').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeLightbox(e) {
  if (e && e.target !== document.getElementById('lightbox') &&
      !e.target.closest('.lightbox-close')) return;
  document.getElementById('lightbox').classList.remove('open');
  document.body.style.overflow = '';
}
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    document.getElementById('lightbox').classList.remove('open');
    document.body.style.overflow = '';
  }
});

// ── Progress slider sync ──────────────────────────────────
function syncProgress(val) {
  val = Math.max(0, Math.min(100, parseInt(val) || 0));
  document.getElementById('sliderVal').textContent   = val;
  document.getElementById('progressSlider').value   = val;
  document.getElementById('progressNum').value      = val;
  // Live-preview the main progress bar
  document.getElementById('prog-fill').style.width  = val + '%';
  document.getElementById('prog-display').innerHTML = val + '<span>%</span>';
}
function syncProgressNum(val) {
  syncProgress(val);
}

// ── Upload preview ────────────────────────────────────────
const photoInput  = document.getElementById('photoInput');
const uploadPreview = document.getElementById('uploadPreview');
const uploadZone  = document.getElementById('uploadZone');

photoInput.addEventListener('change', renderPreviews);

function renderPreviews() {
  uploadPreview.innerHTML = '';
  Array.from(photoInput.files).forEach(file => {
    if (!file.type.startsWith('image/')) return;
    const reader = new FileReader();
    reader.onload = e => {
      const img = document.createElement('img');
      img.src   = e.target.result;
      img.className = 'upload-thumb';
      img.title = file.name;
      uploadPreview.appendChild(img);
    };
    reader.readAsDataURL(file);
  });
}

// Drag-and-drop
uploadZone.addEventListener('dragover', e => { e.preventDefault(); uploadZone.classList.add('dragover'); });
uploadZone.addEventListener('dragleave', () => uploadZone.classList.remove('dragover'));
uploadZone.addEventListener('drop', e => {
  e.preventDefault();
  uploadZone.classList.remove('dragover');
  // Transfer files to the input
  const dt = e.dataTransfer;
  photoInput.files = dt.files;
  renderPreviews();
});
</script>

</body>
</html>