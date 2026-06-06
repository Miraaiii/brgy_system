<?php
require_once __DIR__ . '/includes/admin_layout.php';

$user = adm_require_admin($conn, ['captain', 'kagawad', 'sk_chair', 'sk_kagawad']);
$csrf = adm_action_token();
$role = strtolower(trim((string)($user['role'] ?? '')));
$is_captain = $role === 'captain';
adm_ensure_project_tables($conn);

$current_official = adm_table_exists($conn, 'officials')
    ? adm_fetch_one(
        $conn,
        'SELECT committee FROM officials WHERE user_id = ? AND is_active = 1 ORDER BY term_end DESC LIMIT 1',
        'i',
        [(int)$user['id']]
    )
    : null;
$own_committee = trim((string)($current_official['committee'] ?? ''));

function projects_upload_photos($project_id, $user_id) {
    if (empty($_FILES['photos']['name']) || !is_array($_FILES['photos']['name'])) {
        return [];
    }

    $paths = [];
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $dir = __DIR__ . '/../uploads/projects';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    foreach ($_FILES['photos']['name'] as $index => $name) {
        if ((int)($_FILES['photos']['error'][$index] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            continue;
        }
        $tmp = $_FILES['photos']['tmp_name'][$index];
        $mime = function_exists('mime_content_type') ? mime_content_type($tmp) : ($_FILES['photos']['type'][$index] ?? '');
        if (!isset($allowed[$mime])) {
            continue;
        }
        $filename = 'project-' . (int)$project_id . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
        $target = $dir . '/' . $filename;
        if (move_uploaded_file($tmp, $target)) {
            $paths[] = [
                'path' => 'uploads/projects/' . $filename,
                'name' => (string)$name,
                'user_id' => (int)$user_id,
            ];
        }
    }

    return $paths;
}

function projects_user_can_manage($is_captain, $own_committee, $project_committee) {
    return $is_captain || ($own_committee !== '' && strcasecmp($own_committee, (string)$project_committee) === 0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!adm_verify_action_token($_POST['csrf_token'] ?? '')) {
        adm_set_flash('danger', 'Your session expired. Please refresh and try again.');
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'save_project') {
            $project_id = (int)($_POST['project_id'] ?? 0);
            $title = trim((string)($_POST['title'] ?? ''));
            $committee = trim((string)($_POST['committee'] ?? ''));
            $assigned_user_id = (int)($_POST['assigned_user_id'] ?? 0);
            $category = trim((string)($_POST['category'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $status = strtolower(trim((string)($_POST['status'] ?? 'planning')));
            $start_date = trim((string)($_POST['start_date'] ?? ''));
            $target_end_date = trim((string)($_POST['target_end_date'] ?? ''));
            $budget_input = trim((string)($_POST['estimated_budget'] ?? ''));
            $estimated_budget = $budget_input === '' ? null : (float)$budget_input;
            $progress_percent = max(0, min(100, (int)($_POST['progress_percent'] ?? 0)));
            $stmt = null;
            $can_save = true;

            if (!$is_captain && $committee === '') {
                $committee = $own_committee;
            }

            if ($title === '' || $committee === '' || $category === '' || $description === '' || $start_date === '' || $target_end_date === '') {
                adm_set_flash('danger', 'Project title, committee, category, description, start date, and target end date are required.');
                $can_save = false;
            } elseif (!in_array($status, ['planning', 'ongoing', 'completed', 'on_hold'], true)) {
                adm_set_flash('danger', 'Please select a valid project status.');
                $can_save = false;
            } elseif (!projects_user_can_manage($is_captain, $own_committee, $committee)) {
                adm_set_flash('danger', 'You can only manage projects under your committee.');
                $can_save = false;
            } elseif ($project_id > 0) {
                $existing = adm_fetch_one($conn, 'SELECT committee FROM projects WHERE id = ? LIMIT 1', 'i', [$project_id]);
                if (!$existing || !projects_user_can_manage($is_captain, $own_committee, $existing['committee'])) {
                    adm_set_flash('danger', 'Project not found or outside your committee.');
                    $can_save = false;
                } else {
                    $assigned = $assigned_user_id > 0 ? $assigned_user_id : null;
                    $stmt = $conn->prepare(
                        'UPDATE projects SET title = ?, committee = ?, assigned_user_id = ?, category = ?, description = ?, status = ?, start_date = ?, target_end_date = ?, estimated_budget = ?, progress_percent = ?, updated_by = ? WHERE id = ?'
                    );
                    if ($stmt) {
                        $user_id = (int)$user['id'];
                        $stmt->bind_param('ssisssssdiii', $title, $committee, $assigned, $category, $description, $status, $start_date, $target_end_date, $estimated_budget, $progress_percent, $user_id, $project_id);
                    } else {
                        $can_save = false;
                        adm_set_flash('danger', 'Unable to prepare project update.');
                    }
                }
            }

            if ($can_save) {
                if ($project_id > 0 && isset($stmt) && $stmt) {
                    $ok = $stmt->execute();
                    $stmt->close();
                    if ($ok) {
                        adm_log_activity($conn, (int)$user['id'], 'project_updated', 'projects', $project_id, ['status' => $status, 'progress_percent' => $progress_percent]);
                        adm_set_flash('success', 'Project updated.');
                    } else {
                        adm_set_flash('danger', 'Unable to update project.');
                    }
                } elseif ($project_id === 0) {
                    $assigned = $assigned_user_id > 0 ? $assigned_user_id : null;
                    $stmt = $conn->prepare(
                        'INSERT INTO projects (title, committee, assigned_user_id, category, description, status, start_date, target_end_date, estimated_budget, progress_percent, created_by, updated_by)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    if ($stmt) {
                        $user_id = (int)$user['id'];
                        $stmt->bind_param('ssisssssdiii', $title, $committee, $assigned, $category, $description, $status, $start_date, $target_end_date, $estimated_budget, $progress_percent, $user_id, $user_id);
                        $ok = $stmt->execute();
                        $project_id = (int)$stmt->insert_id;
                        $stmt->close();
                        if ($ok) {
                            adm_log_activity($conn, (int)$user['id'], 'project_created', 'projects', $project_id, ['status' => $status, 'committee' => $committee]);
                            adm_set_flash('success', 'Project created.');
                        } else {
                            adm_set_flash('danger', 'Unable to create project.');
                        }
                    } else {
                        adm_set_flash('danger', 'Unable to prepare project save.');
                    }
                }

                if ($project_id > 0 && adm_table_exists($conn, 'project_photos')) {
                    $photos = projects_upload_photos($project_id, (int)$user['id']);
                    foreach ($photos as $photo) {
                        $stmt_photo = $conn->prepare('INSERT INTO project_photos (project_id, file_path, original_name, uploaded_by) VALUES (?, ?, ?, ?)');
                        if ($stmt_photo) {
                            $stmt_photo->bind_param('issi', $project_id, $photo['path'], $photo['name'], $photo['user_id']);
                            $stmt_photo->execute();
                            $stmt_photo->close();
                        }
                    }
                }
            }
        } elseif ($action === 'archive_project') {
            $project_id = (int)($_POST['project_id'] ?? 0);
            $project = adm_fetch_one($conn, 'SELECT committee FROM projects WHERE id = ? LIMIT 1', 'i', [$project_id]);
            if (!$project || !projects_user_can_manage($is_captain, $own_committee, $project['committee'])) {
                adm_set_flash('danger', 'Project not found or outside your committee.');
            } else {
                $stmt = $conn->prepare('UPDATE projects SET archived_at = NOW(), updated_by = ? WHERE id = ?');
                if ($stmt) {
                    $user_id = (int)$user['id'];
                    $stmt->bind_param('ii', $user_id, $project_id);
                    $stmt->execute();
                    $stmt->close();
                    adm_log_activity($conn, (int)$user['id'], 'project_archived', 'projects', $project_id);
                    adm_set_flash('success', 'Project archived.');
                }
            }
        }
    }

    header('Location: projects.php');
    exit();
}

$committee_filter = trim((string)($_GET['committee'] ?? ''));
$status_filter = strtolower(trim((string)($_GET['status'] ?? '')));
$edit_id = (int)($_GET['edit'] ?? 0);

$where = ['p.archived_at IS NULL'];
$types = '';
$params = [];
if (!$is_captain) {
    $where[] = 'p.committee = ?';
    $types .= 's';
    $params[] = $own_committee;
} elseif ($committee_filter !== '') {
    $where[] = 'p.committee = ?';
    $types .= 's';
    $params[] = $committee_filter;
}
if (in_array($status_filter, ['planning', 'ongoing', 'completed', 'on_hold'], true)) {
    $where[] = 'p.status = ?';
    $types .= 's';
    $params[] = $status_filter;
}
$where_sql = 'WHERE ' . implode(' AND ', $where);

$projects = adm_fetch_all(
    $conn,
    "SELECT p.*, assigned.fullname AS assigned_name, creator.fullname AS created_by_name
     FROM projects p
     LEFT JOIN users assigned ON assigned.id = p.assigned_user_id
     LEFT JOIN users creator ON creator.id = p.created_by
     {$where_sql}
     ORDER BY FIELD(p.status, 'ongoing', 'planning', 'on_hold', 'completed'), p.target_end_date ASC
     LIMIT 300",
    $types,
    $params
);
$committees = adm_fetch_all($conn, 'SELECT DISTINCT committee FROM projects WHERE committee <> "" ORDER BY committee ASC');
$official_users = adm_table_exists($conn, 'users')
    ? adm_fetch_all($conn, "SELECT id, fullname, role FROM users WHERE role IN ('kagawad', 'sk_chair', 'sk_kagawad') AND status = 'active' ORDER BY fullname ASC")
    : [];
$edit_project = $edit_id > 0 ? adm_fetch_one($conn, 'SELECT * FROM projects WHERE id = ? LIMIT 1', 'i', [$edit_id]) : null;
if ($edit_project && !projects_user_can_manage($is_captain, $own_committee, $edit_project['committee'])) {
    $edit_project = null;
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=projects-' . date('Ymd-His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Project', 'Committee', 'Assigned', 'Category', 'Status', 'Start', 'Target End', 'Budget', 'Progress']);
    foreach ($projects as $project) {
        fputcsv($out, [$project['title'], $project['committee'], $project['assigned_name'], $project['category'], $project['status'], $project['start_date'], $project['target_end_date'], $project['estimated_budget'], $project['progress_percent']]);
    }
    fclose($out);
    exit();
}

$actions = '<a class="btn btn--primary" href="projects.php?' . adm_e(http_build_query(['committee' => $committee_filter, 'status' => $status_filter, 'export' => 'csv'])) . '"><i class="fa-solid fa-file-csv"></i> Export CSV</a>';

adm_page_start('Projects & Programs', 'projects', $user, 'projects-page');
adm_page_header('Programs', 'Projects & Programs', $is_captain ? 'Manage all barangay development projects and committee programs.' : 'Manage projects assigned to your committee.', $actions);
?>

<form class="filter-panel" method="get">
  <div class="filter-grid">
    <div class="form-field">
      <label for="committee">Committee</label>
      <select id="committee" name="committee" <?= $is_captain ? '' : 'disabled' ?>>
        <option value="">All committees</option>
        <?php foreach ($committees as $committee): ?>
          <option value="<?= adm_e($committee['committee']) ?>" <?= $committee_filter === $committee['committee'] ? 'selected' : '' ?>><?= adm_e($committee['committee']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-field">
      <label for="status">Status</label>
      <select id="status" name="status">
        <option value="">All statuses</option>
        <?php foreach (['planning', 'ongoing', 'completed', 'on_hold'] as $status): ?>
          <option value="<?= adm_e($status) ?>" <?= $status_filter === $status ? 'selected' : '' ?>><?= adm_e(adm_status_label($status)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn--primary" type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
    <a class="btn" href="projects.php"><i class="fa-solid fa-rotate-left"></i> Reset</a>
  </div>
</form>

<section class="details-grid">
  <section class="panel">
    <div class="panel__header"><div><h2>All Projects</h2><p>Progress is updated manually by the assigned official.</p></div></div>
    <?php if ($projects): ?>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Project</th><th>Committee</th><th>Status</th><th>Dates</th><th>Budget</th><th>Progress</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($projects as $project): ?>
              <tr>
                <td><strong><?= adm_e($project['title']) ?></strong><small><?= adm_e($project['category']) ?> - <?= adm_e($project['assigned_name'] ?: 'Unassigned') ?></small></td>
                <td><?= adm_e($project['committee']) ?></td>
                <td><span class="status-badge status-badge--<?= adm_e(adm_status_class($project['status'])) ?>"><?= adm_e(adm_status_label($project['status'])) ?></span></td>
                <td><?= adm_e(adm_date($project['start_date'])) ?><small>Target: <?= adm_e(adm_date($project['target_end_date'])) ?></small></td>
                <td><?= $project['estimated_budget'] !== null ? 'PHP ' . adm_e(number_format((float)$project['estimated_budget'], 2)) : 'N/A' ?></td>
                <td>
                  <div class="progress-meter progress-meter--small"><span style="width: <?= adm_e((int)$project['progress_percent']) ?>%"></span></div>
                  <small><?= adm_e((int)$project['progress_percent']) ?>%</small>
                </td>
                <td>
                  <div class="table-actions">
                    <a class="btn btn--small" href="projects.php?edit=<?= adm_e($project['id']) ?>"><i class="fa-solid fa-pen"></i> Edit</a>
                    <form method="post" data-disable-on-submit>
                      <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
                      <input type="hidden" name="action" value="archive_project">
                      <input type="hidden" name="project_id" value="<?= adm_e($project['id']) ?>">
                      <button class="btn btn--danger btn--small" type="submit"><i class="fa-solid fa-box-archive"></i> Archive</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="empty-state"><i class="fa-solid fa-diagram-project"></i><strong>No projects found</strong><span>Create a project or adjust filters.</span></div>
    <?php endif; ?>
  </section>

  <form class="form-panel" method="post" enctype="multipart/form-data" data-disable-on-submit>
    <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
    <input type="hidden" name="action" value="save_project">
    <input type="hidden" name="project_id" value="<?= adm_e($edit_project['id'] ?? 0) ?>">
    <h2><?= $edit_project ? 'Edit Project' : 'Add New Project' ?></h2>
    <div class="form-section">
      <div class="form-grid" style="grid-template-columns: 1fr;">
        <div class="form-field"><label for="title">Project Title</label><input id="title" name="title" type="text" value="<?= adm_e($edit_project['title'] ?? '') ?>" required></div>
        <div class="form-field"><label for="form_committee">Committee</label><input id="form_committee" name="committee" type="text" value="<?= adm_e($edit_project['committee'] ?? ($is_captain ? '' : $own_committee)) ?>" <?= $is_captain ? '' : 'readonly' ?> required></div>
        <div class="form-field">
          <label for="assigned_user_id">Assigned Kagawad</label>
          <select id="assigned_user_id" name="assigned_user_id">
            <option value="0">Unassigned</option>
            <?php foreach ($official_users as $official_user): ?>
              <option value="<?= adm_e($official_user['id']) ?>" <?= (int)($edit_project['assigned_user_id'] ?? 0) === (int)$official_user['id'] ? 'selected' : '' ?>><?= adm_e($official_user['fullname']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-field">
          <label for="category">Category</label>
          <select id="category" name="category" required>
            <?php foreach (['Infrastructure', 'Health', 'Education', 'Livelihood', 'Environment', 'Peace & Order'] as $category): ?>
              <option value="<?= adm_e($category) ?>" <?= ($edit_project['category'] ?? '') === $category ? 'selected' : '' ?>><?= adm_e($category) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-field"><label for="description">Description</label><textarea id="description" name="description" required><?= adm_e($edit_project['description'] ?? '') ?></textarea></div>
        <div class="form-field">
          <label for="project_status">Status</label>
          <select id="project_status" name="status">
            <?php foreach (['planning', 'ongoing', 'completed', 'on_hold'] as $status): ?>
              <option value="<?= adm_e($status) ?>" <?= ($edit_project['status'] ?? 'planning') === $status ? 'selected' : '' ?>><?= adm_e(adm_status_label($status)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-field"><label for="start_date">Start Date</label><input id="start_date" name="start_date" type="date" value="<?= adm_e($edit_project['start_date'] ?? '') ?>" required></div>
        <div class="form-field"><label for="target_end_date">Target End Date</label><input id="target_end_date" name="target_end_date" type="date" value="<?= adm_e($edit_project['target_end_date'] ?? '') ?>" required></div>
        <div class="form-field"><label for="estimated_budget">Estimated Budget</label><input id="estimated_budget" name="estimated_budget" type="number" min="0" step="0.01" value="<?= adm_e($edit_project['estimated_budget'] ?? '') ?>"></div>
        <div class="form-field"><label for="progress_percent">Progress %</label><input id="progress_percent" name="progress_percent" type="number" min="0" max="100" value="<?= adm_e($edit_project['progress_percent'] ?? 0) ?>"></div>
        <div class="form-field"><label for="photos">Photos</label><input id="photos" name="photos[]" type="file" accept="image/png,image/jpeg,image/webp" multiple></div>
        <button class="btn btn--primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save Project</button>
        <?php if ($edit_project): ?><a class="btn" href="projects.php"><i class="fa-solid fa-plus"></i> Add New Instead</a><?php endif; ?>
      </div>
    </div>
  </form>
</section>

<?php adm_page_end(); ?>
