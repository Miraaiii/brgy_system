<?php
    ob_start();
    require_once __DIR__ . '/includes/admin_layout.php';

    include '../config/connection.php';
    include '../includes/auth_check.php';

    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login.php");
        exit();
    }

    requireRole(['kagawad']);

    // ── Determine mode ──────────────────────────────────────────────────────────
    $program_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $is_edit    = $program_id > 0;
    $page_title = $is_edit ? 'Edit Program' : 'Add Program';

    $tab = $is_edit ? 'project_edit' : 'project_form';

    $current_user = [
        'id'       => $_SESSION['user_id'],
        'email'    => $_SESSION['email'],
        'role'     => $_SESSION['role'],
        'fullname' => $_SESSION['fullname'] ?? 'Kagawad',
        'username' => $_SESSION['username'],
        'committee' => $_SESSION['committee'] ?? ''
    ];

    adm_page_start(
        $page_title,
        $tab,
        $current_user
    );

    // ── Logged-in user info ──────────────────────────────────────────────────────
    $user_id        = (int) $_SESSION['user_id'];
    $user_name      = htmlspecialchars($_SESSION['full_name']  ?? 'Kagawad');
    $user_committee = $_SESSION['committee'] ?? 'General';

    // ── Load existing record for edit mode ──────────────────────────────────────
    $program = [
        'title'               => '',
        'committee'           => $user_committee,
        'category'            => '',
        'description'         => '',
        'status'              => 'Planning',
        'start_date'          => '',
        'target_end_date'     => '',
        'target_beneficiaries'=> '',
        'estimated_budget'    => '',
        'progress'            => '',
        'photos'              => [],
    ];

    if ($is_edit) {
        $stmt = $pdo->prepare('SELECT * FROM projects WHERE id = ? AND created_by = ?');
        $stmt->execute([$program_id, $user_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            header('Location: project_form.php?error=notfound');
            exit;
        }
        $program = array_merge($program, $row);
        $program['photos'] = json_decode($row['photos'] ?? '[]', true) ?: [];
    }

    // ── Constants ────────────────────────────────────────────────────────────────
    $categories = ['Health', 'Education', 'Infrastructure', 'Livelihood',
                    'Environment', 'Peace & Order', 'Social Services'];
    $statuses   = ['Planning', 'Ongoing', 'Completed', 'On Hold'];

    // ── POST handling ────────────────────────────────────────────────────────────
    $errors   = [];
    $success  = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // — Sanitise text fields —
        $f = [
            'title'               => trim($_POST['title']               ?? ''),
            'committee'           => $user_committee,                        // always from session
            'category'            => trim($_POST['category']            ?? ''),
            'description'         => trim($_POST['description']         ?? ''),
            'status'              => trim($_POST['status']              ?? ''),
            'start_date'          => trim($_POST['start_date']          ?? ''),
            'target_end_date'     => trim($_POST['target_end_date']     ?? ''),
            'target_beneficiaries'=> trim($_POST['target_beneficiaries']?? ''),
            'estimated_budget'    => trim($_POST['estimated_budget']    ?? ''),
            'progress'            => trim($_POST['progress']            ?? ''),
        ];

        // — Required field validation —
        if ($f['title'] === '')                              $errors['title']           = 'Program title is required.';
        if (!in_array($f['category'], $categories, true))   $errors['category']        = 'Please select a valid category.';
        if ($f['description'] === '')                        $errors['description']     = 'Description is required.';
        if (!in_array($f['status'], $statuses, true))        $errors['status']          = 'Please select a valid status.';
        if ($f['start_date'] === '')                         $errors['start_date']      = 'Start date is required.';
        if ($f['target_end_date'] === '')                    $errors['target_end_date'] = 'Target end date is required.';

        // — Date logic —
        if (empty($errors['start_date']) && empty($errors['target_end_date'])) {
            if (strtotime($f['target_end_date']) < strtotime($f['start_date'])) {
                $errors['target_end_date'] = 'Target end date must be on or after the start date.';
            }
        }

        // — Numeric fields —
        if ($f['estimated_budget'] !== '' && (!is_numeric($f['estimated_budget']) || $f['estimated_budget'] < 0)) {
            $errors['estimated_budget'] = 'Enter a valid positive number.';
        }
        if ($f['progress'] !== '' && (!ctype_digit($f['progress']) || (int)$f['progress'] < 0 || (int)$f['progress'] > 100)) {
            $errors['progress'] = 'Progress must be between 0 and 100.';
        }

        // — File uploads —
        $saved_photos = $program['photos'];   // keep existing photos on edit
        $upload_dir   = __DIR__ . '/../uploads/programs/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        if (!empty($_FILES['photos']['name'][0])) {
            $allowed_types = ['image/jpeg', 'image/png'];
            $max_size      = 5 * 1024 * 1024; // 5 MB per file
            foreach ($_FILES['photos']['tmp_name'] as $idx => $tmp) {
                if ($_FILES['photos']['error'][$idx] !== UPLOAD_ERR_OK) continue;
                $mime = mime_content_type($tmp);
                if (!in_array($mime, $allowed_types, true)) {
                    $errors['photos'] = 'Only JPG and PNG files are allowed.';
                    break;
                }
                if ($_FILES['photos']['size'][$idx] > $max_size) {
                    $errors['photos'] = 'Each photo must be under 5 MB.';
                    break;
                }
                $ext       = $mime === 'image/png' ? 'png' : 'jpg';
                $filename  = 'prog_' . $user_id . '_' . uniqid('', true) . '.' . $ext;
                if (move_uploaded_file($tmp, $upload_dir . $filename)) {
                    $saved_photos[] = $filename;
                }
            }
        }

        // — Persist if no errors —
        if (empty($errors)) {
            $photos_json = json_encode($saved_photos);

            if ($is_edit) {
                $stmt = $pdo->prepare('
                    UPDATE projects SET
                        title = ?, committee = ?, category = ?, description = ?,
                        status = ?, start_date = ?, target_end_date = ?,
                        target_beneficiaries = ?, estimated_budget = ?,
                        progress_percent = ?, photos = ?, updated_at = NOW()
                    WHERE id = ? AND created_by = ?
                ');
                $stmt->execute([
                    $f['title'], $f['committee'], $f['category'], $f['description'],
                    $f['status'], $f['start_date'], $f['target_end_date'],
                    $f['target_beneficiaries'],
                    $f['estimated_budget'] !== '' ? $f['estimated_budget'] : null,
                    $f['progress']         !== '' ? (int)$f['progress']    : null,
                    $photos_json, $program_id, $user_id,
                ]);
            } else {
                $stmt = $pdo->prepare('
                    INSERT INTO projects
                        (title, committee, category, description, status,
                        start_date, target_end_date, target_beneficiaries,
                        estimated_budget, progress_percent, photos, created_by, created_at)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())
                ');
                $stmt->execute([
                    $f['title'], $f['committee'], $f['category'], $f['description'],
                    $f['status'], $f['start_date'], $f['target_end_date'],
                    $f['target_beneficiaries'],
                    $f['estimated_budget'] !== '' ? $f['estimated_budget'] : null,
                    $f['progress']         !== '' ? (int)$f['progress']    : null,
                    $photos_json, $user_id,
                ]);
            }

            header('Location: project_form.php?saved=1');
            exit;
        }

        // Re-populate form with posted values on validation failure
        $program = array_merge($program, $f, ['photos' => $saved_photos]);
    }

    // Helper: field error markup
    function field_error(array $errors, string $key): string {
        if (isset($errors[$key])) {
            return '<span class="field-error" role="alert">' . htmlspecialchars($errors[$key]) . '</span>';
        }
        return '';
    }
    function has_error(array $errors, string $key): string {
        return isset($errors[$key]) ? ' has-error' : '';
    }
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $page_title ?> — Kagawad Portal</title>

<!-- Kagawad design system -->
<link rel="stylesheet" href="assets/css/kagawad.css">

<style>
    /* ============================================================
    PROJECT FORM — scoped styles (no external framework)
    All values derived from kagawad.css :root tokens
    ============================================================ */

    /* ── Layout shell ─────────────────────────────────────────── */
    .pf-wrapper {
        max-width: 860px;
        margin: 0 auto;
        padding: var(--content-gutter);
    }

    /* ── Breadcrumb ───────────────────────────────────────────── */
    .pf-breadcrumb {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: .8125rem;
        color: var(--muted);
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    .pf-breadcrumb a {
        color: var(--link);
        text-decoration: none;
        font-weight: 500;
    }
    .pf-breadcrumb a:hover { text-decoration: underline; }
    .pf-breadcrumb svg { flex-shrink: 0; }

    /* ── Page header ──────────────────────────────────────────── */
    .pf-page-header {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        margin-bottom: 24px;
    }
    .pf-page-header .icon-wrap {
        width: 44px;
        height: 44px;
        border-radius: var(--radius);
        background: var(--primary-soft);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        color: var(--primary);
    }
    body.dark-mode .pf-page-header .icon-wrap { background: var(--primary-soft); color: var(--primary); }
    .pf-page-header h1 {
        font-size: 1.375rem;
        font-weight: 700;
        color: var(--text);
        line-height: 1.2;
        margin: 0 0 4px;
    }
    .pf-page-header p {
        font-size: .875rem;
        color: var(--muted);
        margin: 0;
    }

    /* ── Card ─────────────────────────────────────────────────── */
    .pf-card {
        background: var(--surface);
        border: 1px solid var(--line);
        border-radius: calc(var(--radius) + 4px);
        box-shadow: var(--shadow-soft);
        overflow: hidden;
    }

    /* ── Card section header ──────────────────────────────────── */
    .pf-section-head {
        padding: 16px var(--card-pad);
        border-bottom: 1px solid var(--line);
        background: var(--surface-soft);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .pf-section-head .section-icon {
        width: 28px;
        height: 28px;
        border-radius: 6px;
        background: var(--primary-soft);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary);
        flex-shrink: 0;
    }
    body.dark-mode .pf-section-head .section-icon { background: var(--primary-soft); }
    .pf-section-head h2 {
        font-size: .9375rem;
        font-weight: 600;
        color: var(--text);
        margin: 0;
    }

    /* ── Form body ────────────────────────────────────────────── */
    .pf-form-body {
        padding: var(--card-pad);
        display: flex;
        flex-direction: column;
        gap: 0;
    }

    /* ── Section divider within form ─────────────────────────── */
    .pf-group {
        display: grid;
        gap: 20px;
        padding: 20px 0;
        border-bottom: 1px solid var(--line);
    }
    .pf-group:last-of-type { border-bottom: none; padding-bottom: 0; }
    .pf-group.col-2  { grid-template-columns: 1fr 1fr; }
    .pf-group.col-3  { grid-template-columns: 1fr 1fr 1fr; }
    .pf-group.col-1  { grid-template-columns: 1fr; }

    /* ── Field ────────────────────────────────────────────────── */
    .pf-field {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .pf-field label {
        font-size: .8125rem;
        font-weight: 600;
        color: var(--text);
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .pf-field label .req {
        color: var(--danger);
        font-size: .875em;
    }
    .pf-field label .opt-tag {
        font-size: .7rem;
        font-weight: 500;
        color: var(--muted);
        background: var(--neutral-soft);
        border-radius: 4px;
        padding: 1px 5px;
        letter-spacing: .03em;
    }

    /* ── Inputs, selects, textarea ────────────────────────────── */
    .pf-input,
    .pf-select,
    .pf-textarea {
        width: 100%;
        box-sizing: border-box;
        padding: 9px 13px;
        border: 1px solid var(--line);
        border-radius: var(--radius);
        background: var(--surface);
        color: var(--text);
        font-size: .875rem;
        font-family: inherit;
        line-height: 1.5;
        transition: border-color var(--transition), box-shadow var(--transition), background var(--transition);
        -webkit-appearance: none;
        appearance: none;
    }
    .pf-input:focus,
    .pf-select:focus,
    .pf-textarea:focus {
        outline: none;
        border-color: var(--gold);
        box-shadow: 0 0 0 3px var(--accent-ring);
        background: var(--surface);
    }
    .pf-input[readonly] {
        background: var(--surface-soft);
        color: var(--muted);
        cursor: default;
    }
    .pf-input.has-error,
    .pf-select.has-error,
    .pf-textarea.has-error {
        border-color: var(--danger);
        box-shadow: 0 0 0 3px rgba(220, 38, 38, .15);
    }
    .pf-select {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23667085' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 10px center;
        padding-right: 36px;
    }
    .pf-textarea {
        resize: vertical;
        min-height: 110px;
    }

    /* ── Field error message ──────────────────────────────────── */
    .field-error {
        font-size: .775rem;
        color: var(--danger);
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .field-error::before {
        content: '';
        display: inline-block;
        width: 14px;
        height: 14px;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23dc2626' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='12' cy='12' r='10'/%3E%3Cline x1='12' y1='8' x2='12' y2='12'/%3E%3Cline x1='12' y1='16' x2='12.01' y2='16'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        flex-shrink: 0;
    }

    /* ── Progress slider ──────────────────────────────────────── */
    .pf-progress-row {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .pf-range {
        flex: 1;
        height: 6px;
        -webkit-appearance: none;
        appearance: none;
        background: var(--line);
        border-radius: 99px;
        cursor: pointer;
        transition: background var(--transition);
    }
    .pf-range::-webkit-slider-thumb {
        -webkit-appearance: none;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: var(--gold);
        border: 2px solid var(--surface);
        box-shadow: 0 0 0 2px var(--gold);
        cursor: pointer;
        transition: box-shadow var(--transition);
    }
    .pf-range:focus { outline: none; }
    .pf-range:focus::-webkit-slider-thumb { box-shadow: 0 0 0 3px var(--accent-ring); }
    .pf-progress-num {
        width: 64px;
        text-align: right;
        font-size: .875rem;
        font-weight: 600;
        color: var(--text);
        padding: 6px 10px;
        border: 1px solid var(--line);
        border-radius: var(--radius);
        background: var(--surface);
        color: var(--text);
    }

    /* ── Photo upload zone ────────────────────────────────────── */
    .pf-upload-zone {
        border: 2px dashed var(--line-strong);
        border-radius: var(--radius);
        padding: 24px 16px;
        text-align: center;
        cursor: pointer;
        transition: border-color var(--transition), background var(--transition);
        position: relative;
    }
    .pf-upload-zone:hover,
    .pf-upload-zone.drag-over {
        border-color: var(--gold);
        background: var(--accent-bg-faint);
    }
    .pf-upload-zone input[type="file"] {
        position: absolute;
        inset: 0;
        opacity: 0;
        cursor: pointer;
        width: 100%;
        height: 100%;
    }
    .pf-upload-icon {
        width: 36px;
        height: 36px;
        margin: 0 auto 10px;
        color: var(--muted);
    }
    .pf-upload-zone p {
        font-size: .8125rem;
        color: var(--muted);
        margin: 0;
        line-height: 1.6;
    }
    .pf-upload-zone strong { color: var(--text); }
    .pf-upload-zone .hint { font-size: .75rem; margin-top: 4px; color: var(--faint); }

    /* ── Photo previews ───────────────────────────────────────── */
    .pf-photo-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 12px;
    }
    .pf-photo-thumb {
        position: relative;
        width: 80px;
        height: 80px;
        border-radius: var(--radius);
        overflow: hidden;
        border: 1px solid var(--line);
        background: var(--surface-soft);
    }
    .pf-photo-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .pf-photo-thumb .remove-btn {
        position: absolute;
        top: 3px;
        right: 3px;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: rgba(0,0,0,.55);
        color: #fff;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        line-height: 1;
        padding: 0;
        transition: background var(--transition);
    }
    .pf-photo-thumb .remove-btn:hover { background: var(--danger); }

    /* ── Budget input with prefix ─────────────────────────────── */
    .pf-input-prefix {
        display: flex;
        align-items: stretch;
        border: 1px solid var(--line);
        border-radius: var(--radius);
        overflow: hidden;
        transition: border-color var(--transition), box-shadow var(--transition);
    }
    .pf-input-prefix:focus-within {
        border-color: var(--gold);
        box-shadow: 0 0 0 3px var(--accent-ring);
    }
    .pf-input-prefix.has-error { border-color: var(--danger); }
    .pf-input-prefix .prefix {
        padding: 9px 11px;
        background: var(--surface-soft);
        border-right: 1px solid var(--line);
        font-size: .8125rem;
        font-weight: 600;
        color: var(--muted);
        white-space: nowrap;
        display: flex;
        align-items: center;
    }
    .pf-input-prefix input {
        flex: 1;
        border: none;
        border-radius: 0;
        box-shadow: none !important;
        background: var(--surface);
        color: var(--text);
        padding: 9px 13px;
        font-size: .875rem;
        font-family: inherit;
        min-width: 0;
    }
    .pf-input-prefix input:focus { outline: none; }

    /* ── Summary bar (error) ──────────────────────────────────── */
    .pf-error-banner {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        background: var(--danger-soft);
        border: 1px solid rgba(220,38,38,.25);
        border-radius: var(--radius);
        padding: 12px 16px;
        margin-bottom: 20px;
        color: var(--danger);
        font-size: .8125rem;
        font-weight: 500;
    }

    /* ── Action bar ───────────────────────────────────────────── */
    .pf-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        padding: 16px var(--card-pad);
        border-top: 1px solid var(--line);
        background: var(--surface-soft);
    }

    .btn-base {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 9px 20px;
        border-radius: var(--radius);
        font-size: .875rem;
        font-weight: 600;
        font-family: inherit;
        cursor: pointer;
        border: 1px solid transparent;
        transition: background var(--transition), color var(--transition),
                    border-color var(--transition), box-shadow var(--transition),
                    transform 80ms ease;
        text-decoration: none;
        white-space: nowrap;
        line-height: 1;
    }
    .btn-base:active { transform: scale(.97); }

    .btn-cancel {
        background: var(--surface);
        color: var(--text);
        border-color: var(--line-strong);
    }
    .btn-cancel:hover {
        background: var(--surface-soft);
        border-color: var(--muted);
    }

    .btn-save {
        background: var(--primary);
        color: #fff;
        border-color: var(--primary);
    }
    body.dark-mode .btn-save {
        background: var(--gold);
        color: var(--accent-text);
        border-color: var(--gold);
    }
    .btn-save:hover {
        background: var(--primary-dark);
        border-color: var(--primary-dark);
    }
    body.dark-mode .btn-save:hover {
        background: var(--gold-light);
        border-color: var(--gold-light);
    }
    .btn-save:focus-visible,
    .btn-cancel:focus-visible {
        outline: none;
        box-shadow: 0 0 0 3px var(--accent-ring);
    }

    /* ── Responsive ───────────────────────────────────────────── */
    @media (max-width: 680px) {
        .pf-group.col-2,
        .pf-group.col-3 { grid-template-columns: 1fr; }
        .pf-actions { flex-direction: column-reverse; }
        .pf-actions .btn-base { width: 100%; justify-content: center; }
    }
</style>
</head>
<body>

<!-- ── Main content ──────────────────────────────────────────────────────── -->
<main class="main-content" id="mainContent">
    <div class="pf-wrapper">

        <!-- Page header -->
        <div class="pf-page-header">
            <div class="icon-wrap" aria-hidden="true">
                <?php if ($is_edit): ?>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
                <?php else: ?>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/>
                    <line x1="8" y1="12" x2="16" y2="12"/>
                </svg>
                <?php endif; ?>
            </div>
            <div>
                <h1><?= htmlspecialchars($page_title) ?></h1>
                <p><?= $is_edit
                    ? 'Update the details of this community program.'
                    : 'Add a new program under your committee.' ?></p>
            </div>
        </div>

        <!-- Validation error banner -->
        <?php if (!empty($errors)): ?>
        <div class="pf-error-banner" role="alert">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" flex-shrink="0"
                aria-hidden="true">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <span>Please fix <?= count($errors) ?> error<?= count($errors) > 1 ? 's' : '' ?> before saving.</span>
        </div>
        <?php endif; ?>

        <!-- ── Form card ─────────────────────────────────────────────────────── -->
        <form method="POST"
            enctype="multipart/form-data"
            novalidate
            id="programForm">

            <div class="pf-card">

                <!-- ════ SECTION 1 — Basic Info ════ -->
                <div class="pf-section-head">
                    <div class="section-icon" aria-hidden="true">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                            stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="16" y1="13" x2="8" y2="13"/>
                            <line x1="16" y1="17" x2="8" y2="17"/>
                            <polyline points="10 9 9 9 8 9"/>
                        </svg>
                    </div>
                    <h2>Basic Information</h2>
                </div>

                <div class="pf-form-body">

                    <!-- Row: Title (full width) -->
                    <div class="pf-group col-1" style="padding-top:16px;">
                        <div class="pf-field">
                            <label for="title">
                                Program Title <span class="req" aria-label="required">*</span>
                            </label>
                            <input
                                type="text"
                                id="title"
                                name="title"
                                class="pf-input<?= has_error($errors,'title') ?>"
                                value="<?= htmlspecialchars($program['title']) ?>"
                                placeholder="e.g. Livelihood Skills Training – Q3 2025"
                                required
                                maxlength="255"
                                aria-describedby="title-err"
                            >
                            <?= field_error($errors, 'title') ?>
                        </div>
                    </div>

                    <!-- Row: Committee + Category -->
                    <div class="pf-group col-2">
                        <div class="pf-field">
                            <label for="committee">Committee</label>
                            <input
                                type="text"
                                id="committee"
                                name="committee"
                                class="pf-input"
                                value="<?= htmlspecialchars($user_committee) ?>"
                                readonly
                                aria-label="Auto-filled from your account"
                            >
                        </div>

                        <div class="pf-field">
                            <label for="category">
                                Category <span class="req" aria-label="required">*</span>
                            </label>
                            <select
                                id="category"
                                name="category"
                                class="pf-select<?= has_error($errors,'category') ?>"
                                required
                            >
                                <option value="" disabled <?= $program['category'] === '' ? 'selected' : '' ?>>
                                    — Select category —
                                </option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>"
                                        <?= $program['category'] === $cat ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?= field_error($errors, 'category') ?>
                        </div>
                    </div>

                    <!-- Row: Description -->
                    <div class="pf-group col-1">
                        <div class="pf-field">
                            <label for="description">
                                Description <span class="req" aria-label="required">*</span>
                            </label>
                            <textarea
                                id="description"
                                name="description"
                                class="pf-textarea<?= has_error($errors,'description') ?>"
                                rows="5"
                                placeholder="Describe the program's goals, target community, and expected outcomes…"
                                required
                            ><?= htmlspecialchars($program['description']) ?></textarea>
                            <?= field_error($errors, 'description') ?>
                        </div>
                    </div>

                </div><!-- /pf-form-body -->

                <!-- ════ SECTION 2 — Schedule & Status ════ -->
                <div class="pf-section-head">
                    <div class="section-icon" aria-hidden="true">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                            stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                    </div>
                    <h2>Schedule &amp; Status</h2>
                </div>

                <div class="pf-form-body">

                    <!-- Row: Status + Start Date + End Date -->
                    <div class="pf-group col-3" style="padding-top:16px;">
                        <div class="pf-field">
                            <label for="status">
                                Status <span class="req" aria-label="required">*</span>
                            </label>
                            <select
                                id="status"
                                name="status"
                                class="pf-select<?= has_error($errors,'status') ?>"
                                required
                            >
                                <?php foreach ($statuses as $st): ?>
                                <option value="<?= htmlspecialchars($st) ?>"
                                        <?= $program['status'] === $st ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($st) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?= field_error($errors, 'status') ?>
                        </div>

                        <div class="pf-field">
                            <label for="start_date">
                                Start Date <span class="req" aria-label="required">*</span>
                            </label>
                            <input
                                type="date"
                                id="start_date"
                                name="start_date"
                                class="pf-input<?= has_error($errors,'start_date') ?>"
                                value="<?= htmlspecialchars($program['start_date']) ?>"
                                required
                            >
                            <?= field_error($errors, 'start_date') ?>
                        </div>

                        <div class="pf-field">
                            <label for="target_end_date">
                                Target End Date <span class="req" aria-label="required">*</span>
                            </label>
                            <input
                                type="date"
                                id="target_end_date"
                                name="target_end_date"
                                class="pf-input<?= has_error($errors,'target_end_date') ?>"
                                value="<?= htmlspecialchars($program['target_end_date']) ?>"
                                required
                            >
                            <?= field_error($errors, 'target_end_date') ?>
                        </div>
                    </div>

                </div>

                <!-- ════ SECTION 3 — Details ════ -->
                <div class="pf-section-head">
                    <div class="section-icon" aria-hidden="true">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                            stroke-linejoin="round">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                        </svg>
                    </div>
                    <h2>Program Details</h2>
                </div>

                <div class="pf-form-body">

                    <!-- Row: Beneficiaries + Budget -->
                    <div class="pf-group col-2" style="padding-top:16px;">
                        <div class="pf-field">
                            <label for="target_beneficiaries">
                                Target Beneficiaries
                                <span class="opt-tag">Optional</span>
                            </label>
                            <input
                                type="text"
                                id="target_beneficiaries"
                                name="target_beneficiaries"
                                class="pf-input"
                                value="<?= htmlspecialchars($program['target_beneficiaries']) ?>"
                                placeholder="e.g. Senior citizens, PWDs, Solo parents"
                                maxlength="255"
                            >
                        </div>

                        <div class="pf-field">
                            <label for="estimated_budget">
                                Estimated Budget
                                <span class="opt-tag">Optional</span>
                            </label>
                            <div class="pf-input-prefix<?= has_error($errors,'estimated_budget') ?>">
                                <span class="prefix">₱</span>
                                <input
                                    type="number"
                                    id="estimated_budget"
                                    name="estimated_budget"
                                    value="<?= htmlspecialchars($program['estimated_budget']) ?>"
                                    placeholder="0.00"
                                    min="0"
                                    step="0.01"
                                >
                            </div>
                            <?= field_error($errors, 'estimated_budget') ?>
                        </div>
                    </div>

                    <!-- Row: Progress -->
                    <div class="pf-group col-1">
                        <div class="pf-field">
                            <label for="progress">
                                Progress
                                <span class="opt-tag">Optional</span>
                            </label>
                            <div class="pf-progress-row">
                                <input
                                    type="range"
                                    id="progress"
                                    name="progress"
                                    class="pf-range"
                                    min="0"
                                    max="100"
                                    step="1"
                                    value="<?= (int)($program['progress'] ?? 0) ?>"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                    aria-valuenow="<?= (int)($program['progress'] ?? 0) ?>"
                                >
                                <output id="progressDisplay" class="pf-progress-num"
                                        for="progress"
                                        aria-live="polite">
                                    <?= (int)($program['progress'] ?? 0) ?>%
                                </output>
                            </div>
                            <?= field_error($errors, 'progress') ?>
                        </div>
                    </div>

                </div>

                <!-- ════ SECTION 4 — Photos ════ -->
                <div class="pf-section-head">
                    <div class="section-icon" aria-hidden="true">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                            stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                            <circle cx="8.5" cy="8.5" r="1.5"/>
                            <polyline points="21 15 16 10 5 21"/>
                        </svg>
                    </div>
                    <h2>Photos</h2>
                </div>

                <div class="pf-form-body">
                    <div class="pf-group col-1" style="padding-top:16px;">

                        <!-- Existing photo thumbnails (edit mode) -->
                        <?php if (!empty($program['photos'])): ?>
                        <div class="pf-photo-grid" id="existingPhotos" aria-label="Existing photos">
                            <?php
                            $upload_url = '../uploads/programs/';
                            foreach ($program['photos'] as $photo):
                                $safe_photo = htmlspecialchars($photo);
                            ?>
                            <div class="pf-photo-thumb" data-filename="<?= $safe_photo ?>">
                                <img
                                    src="<?= $upload_url . $safe_photo ?>"
                                    alt="Program photo"
                                    loading="lazy"
                                >
                                <button
                                    type="button"
                                    class="remove-btn"
                                    aria-label="Remove photo <?= $safe_photo ?>"
                                    onclick="removeExistingPhoto(this)"
                                >&times;</button>
                                <!-- Hidden input to keep track of which photos to retain -->
                                <input type="hidden" name="existing_photos[]" value="<?= $safe_photo ?>">
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Upload drop zone -->
                        <label class="pf-upload-zone" id="uploadZone" for="photos" aria-describedby="photo-hint">
                            <div class="pf-upload-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="16 16 12 12 8 16"/>
                                    <line x1="12" y1="12" x2="12" y2="21"/>
                                    <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/>
                                </svg>
                            </div>
                            <p><strong>Click to upload</strong> or drag and drop photos here</p>
                            <p class="hint" id="photo-hint">JPG, PNG · Max 5 MB per file · Multiple allowed</p>
                            <input
                                type="file"
                                id="photos"
                                name="photos[]"
                                accept="image/jpeg,image/png"
                                multiple
                                aria-label="Upload program photos"
                            >
                        </label>
                        <?= field_error($errors, 'photos') ?>

                        <!-- New photo previews (JS-generated) -->
                        <div class="pf-photo-grid" id="newPhotoPreview" aria-label="New photo previews" aria-live="polite"></div>

                    </div>
                </div>

                <!-- ════ Action bar ════ -->
                <div class="pf-actions">
                    <a href="project_form.php" class="btn-base btn-cancel" role="button">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                            stroke-linejoin="round" aria-hidden="true">
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                        Cancel
                    </a>
                    <button type="submit" class="btn-base btn-save">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                            stroke-linejoin="round" aria-hidden="true">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        Save Program
                    </button>
                </div>

            </div><!-- /pf-card -->
        </form>

    </div><!-- /pf-wrapper -->
</main>

<script src="../assets/js/resident_dashboard.js"></script>
<script src="assets/js/secretary.js?v=20260605c"></script>

<script>
/* ================================================================
   project_form.php — client-side enhancements
   No external libraries. Vanilla JS only.
   ================================================================ */

// ── Progress slider ──────────────────────────────────────────────
(function () {
    const slider  = document.getElementById('progress');
    const display = document.getElementById('progressDisplay');
    if (!slider || !display) return;

    function updateDisplay() {
        const val = slider.value;
        display.textContent = val + '%';
        slider.setAttribute('aria-valuenow', val);

        // Colour the filled portion
        const pct = (val / 100) * 100;
        slider.style.background =
            `linear-gradient(to right, var(--gold) ${pct}%, var(--line) ${pct}%)`;
    }

    slider.addEventListener('input', updateDisplay);
    updateDisplay(); // initialise on load
})();

// ── Drag-and-drop upload zone ────────────────────────────────────
(function () {
    const zone = document.getElementById('uploadZone');
    if (!zone) return;

    ['dragenter', 'dragover'].forEach(evt =>
        zone.addEventListener(evt, e => { e.preventDefault(); zone.classList.add('drag-over'); }));
    ['dragleave', 'drop'].forEach(evt =>
        zone.addEventListener(evt, () => zone.classList.remove('drag-over')));
})();

// ── New photo preview ─────────────────────────────────────────────
(function () {
    const input   = document.getElementById('photos');
    const preview = document.getElementById('newPhotoPreview');
    if (!input || !preview) return;

    input.addEventListener('change', () => {
        preview.innerHTML = '';
        Array.from(input.files).forEach(file => {
            if (!file.type.match(/image\/(jpeg|png)/)) return;
            const reader = new FileReader();
            reader.onload = e => {
                const thumb = document.createElement('div');
                thumb.className = 'pf-photo-thumb';
                thumb.innerHTML = `
                    <img src="${e.target.result}" alt="Preview of ${escapeHtml(file.name)}">
                    <button type="button" class="remove-btn" aria-label="Remove ${escapeHtml(file.name)}">&times;</button>`;
                thumb.querySelector('.remove-btn').addEventListener('click', () => {
                    thumb.remove();
                    // Note: clearing a FileList entirely resets the input.
                    // For production, replace input with DataTransfer API:
                    // const dt = new DataTransfer();
                    // Array.from(input.files).filter(f => f !== file).forEach(f => dt.items.add(f));
                    // input.files = dt.files;
                });
                preview.appendChild(thumb);
            };
            reader.readAsDataURL(file);
        });
    });
})();

// ── Remove existing photo ────────────────────────────────────────
function removeExistingPhoto(btn) {
    const thumb = btn.closest('.pf-photo-thumb');
    if (thumb) thumb.remove();
}

// ── Utility ──────────────────────────────────────────────────────
function escapeHtml(str) {
    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// ── Client-side date validation ──────────────────────────────────
(function () {
    const form    = document.getElementById('programForm');
    const start   = document.getElementById('start_date');
    const endDate = document.getElementById('target_end_date');
    if (!form || !start || !endDate) return;

    form.addEventListener('submit', e => {
        if (start.value && endDate.value && endDate.value < start.value) {
            endDate.classList.add('has-error');
            endDate.setCustomValidity('Target end date must be on or after the start date.');
            endDate.reportValidity();
            e.preventDefault();
        } else {
            endDate.setCustomValidity('');
        }
    });

    [start, endDate].forEach(el =>
        el.addEventListener('change', () => {
            el.classList.remove('has-error');
            el.setCustomValidity('');
        }));
})();
</script>

</body>
</html>
<?php
ob_end_flush();
?>