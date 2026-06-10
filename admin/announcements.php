<?php
require_once __DIR__ . '/includes/admin_layout.php';

$user = adm_require_admin($conn, ['captain', 'secretary', 'kagawad']);
$csrf = adm_action_token();
$categories = ['all', 'health', 'events', 'ordinance', 'programs', 'emergency', 'notice', 'general'];

function secretary_slugify($value) {
    $slug = strtolower(trim((string)$value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug !== '' ? $slug : 'announcement';
}

function secretary_unique_slug($conn, $title, $ignore_id = 0) {
    $base = secretary_slugify($title);
    $slug = $base;
    $i = 2;
    while (true) {
        $row = adm_fetch_one(
            $conn,
            'SELECT id FROM announcements WHERE slug = ? AND id <> ? LIMIT 1',
            'si',
            [$slug, (int)$ignore_id]
        );
        if (!$row) {
            return $slug;
        }
        $slug = $base . '-' . $i;
        $i++;
    }
}

function secretary_upload_announcement_thumbnail($field_name, $current = '') {
    if (empty($_FILES[$field_name]) || !is_array($_FILES[$field_name]) || (int)$_FILES[$field_name]['error'] === UPLOAD_ERR_NO_FILE) {
        return $current;
    }

    $file = $_FILES[$field_name];
    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Thumbnail upload failed.');
    }
    if ((int)$file['size'] > 2 * 1024 * 1024) {
        throw new Exception('Thumbnail must not exceed 2MB.');
    }

    $extension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
        throw new Exception('Thumbnail must be JPG or PNG.');
    }

    $upload_dir = __DIR__ . '/../uploads/announcement_thumbnails';
    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
        throw new Exception('Unable to prepare thumbnail folder.');
    }

    $safe_name = 'announcement_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
    $target = $upload_dir . DIRECTORY_SEPARATOR . $safe_name;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new Exception('Unable to save thumbnail.');
    }

    return 'uploads/announcement_thumbnails/' . $safe_name;
}

if (adm_table_exists($conn, 'announcements')) {
    $conn->query("UPDATE announcements SET is_published = 1 WHERE is_published = 0 AND published_at IS NOT NULL AND published_at <= NOW()");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!adm_verify_action_token($_POST['csrf_token'] ?? '')) {
        adm_set_flash('danger', 'Your session expired. Please refresh and try again.');
    } elseif (!adm_table_exists($conn, 'announcements')) {
        adm_set_flash('danger', 'Announcements table is not installed.');
    } else {
        $action = (string)($_POST['action'] ?? 'save_announcement');
        $announcement_id = (int)($_POST['announcement_id'] ?? 0);

        if ($action === 'toggle_publish') {
            $announcement = adm_fetch_one($conn, 'SELECT id, is_published FROM announcements WHERE id = ? LIMIT 1', 'i', [$announcement_id]);
            if (!$announcement) {
                adm_set_flash('danger', 'Announcement not found.');
            } elseif ((int)$announcement['is_published'] === 1) {
                $stmt = $conn->prepare('UPDATE announcements SET is_published = 0, published_at = NULL WHERE id = ?');
                $stmt->bind_param('i', $announcement_id);
                $stmt->execute();
                $stmt->close();
                adm_log_activity($conn, (int)$user['id'], 'Unpublished announcement', 'announcements', $announcement_id);
                adm_set_flash('success', 'Announcement unpublished.');
            } else {
                $stmt = $conn->prepare('UPDATE announcements SET is_published = 1, published_at = NOW() WHERE id = ?');
                $stmt->bind_param('i', $announcement_id);
                $stmt->execute();
                $stmt->close();
                adm_log_activity($conn, (int)$user['id'], 'Published announcement', 'announcements', $announcement_id);
                adm_set_flash('success', 'Announcement published.');
            }
        } elseif ($action === 'delete_announcement') {
            $stmt = $conn->prepare('DELETE FROM announcements WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('i', $announcement_id);
                $stmt->execute();
                $stmt->close();
                adm_log_activity($conn, (int)$user['id'], 'Deleted announcement', 'announcements', $announcement_id);
                adm_set_flash('success', 'Announcement deleted.');
            } else {
                adm_set_flash('danger', 'Unable to delete announcement.');
            }
        } else {
            $title = trim((string)($_POST['title'] ?? ''));
            $category = strtolower(trim((string)($_POST['category'] ?? 'general')));
            $body = trim((string)($_POST['body'] ?? ''));
            $schedule_at = trim((string)($_POST['schedule_at'] ?? ''));
            $publish_now = isset($_POST['publish_now']);
            $existing = $announcement_id > 0
                ? adm_fetch_one($conn, 'SELECT * FROM announcements WHERE id = ? LIMIT 1', 'i', [$announcement_id])
                : null;

            if ($title === '' || $body === '') {
                adm_set_flash('danger', 'Title and body are required.');
            } elseif (strlen($title) > 200) {
                adm_set_flash('danger', 'Title must be 200 characters or less.');
            } elseif (!in_array($category, array_slice($categories, 1), true)) {
                adm_set_flash('danger', 'Invalid category.');
            } else {
                try {
                    $clean_body = trim(strip_tags($body, '<p><br><strong><b><em><i><ul><ol><li>'));
                    $thumbnail = secretary_upload_announcement_thumbnail('thumbnail', (string)($existing['thumbnail'] ?? ''));
                    $published_at = null;
                    $is_published = 0;

                    if ($publish_now) {
                        $published_at = date('Y-m-d H:i:s');
                        $is_published = 1;
                    } elseif ($schedule_at !== '') {
                        $published_at = str_replace('T', ' ', $schedule_at);
                        if (strlen($published_at) === 16) {
                            $published_at .= ':00';
                        }
                        $is_published = strtotime($published_at) <= time() ? 1 : 0;
                    } elseif ($existing) {
                        $published_at = $existing['published_at'];
                        $is_published = (int)$existing['is_published'];
                    }

                    $slug = secretary_unique_slug($conn, $title, $announcement_id);
                    if ($existing) {
                        $stmt = $conn->prepare(
                            'UPDATE announcements
                             SET title = ?, slug = ?, category = ?, body = ?, thumbnail = ?,
                                 is_published = ?, published_at = ?, updated_at = NOW()
                             WHERE id = ?'
                        );
                        if (!$stmt) {
                            throw new Exception('Unable to prepare announcement update.');
                        }
                        $stmt->bind_param('sssssisi', $title, $slug, $category, $clean_body, $thumbnail, $is_published, $published_at, $announcement_id);
                        $stmt->execute();
                        $stmt->close();
                        adm_log_activity($conn, (int)$user['id'], 'Updated announcement', 'announcements', $announcement_id, ['published' => $is_published]);
                        adm_set_flash('success', 'Announcement updated.');
                    } else {
                        $stmt = $conn->prepare(
                            'INSERT INTO announcements (title, slug, category, body, thumbnail, is_published, published_at, created_by)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                        );
                        if (!$stmt) {
                            throw new Exception('Unable to prepare announcement insert.');
                        }
                        $created_by = (int)$user['id'];
                        $stmt->bind_param('sssssisi', $title, $slug, $category, $clean_body, $thumbnail, $is_published, $published_at, $created_by);
                        $stmt->execute();
                        $new_id = (int)$stmt->insert_id;
                        $stmt->close();
                        adm_log_activity($conn, (int)$user['id'], 'Created announcement', 'announcements', $new_id, ['published' => $is_published]);
                        adm_set_flash('success', 'Announcement saved.');
                    }
                } catch (Throwable $e) {
                    adm_set_flash('danger', $e->getMessage());
                }
            }
        }
    }
    header('Location: announcements.php');
    exit();
}

$filter = strtolower(trim((string)($_GET['category'] ?? 'all')));
if (!in_array($filter, $categories, true)) {
    $filter = 'all';
}
$edit_id = (int)($_GET['edit'] ?? 0);
$preview_id = (int)($_GET['preview'] ?? 0);

$edit_announcement = $edit_id > 0 && adm_table_exists($conn, 'announcements')
    ? adm_fetch_one($conn, 'SELECT * FROM announcements WHERE id = ? LIMIT 1', 'i', [$edit_id])
    : null;
$preview_announcement = $preview_id > 0 && adm_table_exists($conn, 'announcements')
    ? adm_fetch_one($conn, 'SELECT * FROM announcements WHERE id = ? LIMIT 1', 'i', [$preview_id])
    : null;

$where = '';
$types = '';
$params = [];
if ($filter !== 'all') {
    $where = 'WHERE a.category = ?';
    $types = 's';
    $params[] = $filter;
}

$announcements = adm_table_exists($conn, 'announcements')
    ? adm_fetch_all(
        $conn,
        "SELECT a.*, creator.fullname AS created_by_name
         FROM announcements a
         LEFT JOIN users creator ON creator.id = a.created_by
         {$where}
         ORDER BY COALESCE(a.published_at, a.created_at) DESC, a.created_at DESC
         LIMIT 100",
        $types,
        $params
    )
    : [];

$form_values = [
    'id' => $edit_announcement['id'] ?? '',
    'title' => $edit_announcement['title'] ?? '',
    'category' => $edit_announcement['category'] ?? 'general',
    'body' => $edit_announcement['body'] ?? '',
    'thumbnail' => $edit_announcement['thumbnail'] ?? '',
    'published_at' => $edit_announcement['published_at'] ?? '',
    'is_published' => (int)($edit_announcement['is_published'] ?? 0),
];

$actions = '<a class="btn btn--primary" href="announcements.php#announcementForm"><i class="fa-solid fa-plus"></i> Create New</a>';

adm_page_start('Announcements', 'announcements', $user, 'announcements-page');
adm_page_header('Public information', 'Announcements Manager', 'Create, preview, schedule, publish, and remove official announcements.', $actions);
?>

<nav class="tabs" aria-label="Announcement category filters">
  <?php foreach ($categories as $category): ?>
    <a class="tab-link <?= $filter === $category ? 'is-active' : '' ?>" href="announcements.php?category=<?= adm_e($category) ?>">
      <?= adm_e($category === 'all' ? 'All' : adm_status_label($category)) ?>
    </a>
  <?php endforeach; ?>
</nav>

<?php if ($preview_announcement): ?>
  <section class="detail-panel" style="margin-bottom: 16px;">
    <div class="action-row" style="justify-content: space-between;">
      <div>
        <p class="eyebrow">Preview</p>
        <h2><?= adm_e($preview_announcement['title']) ?></h2>
      </div>
      <a class="btn btn--small" href="announcements.php"><i class="fa-solid fa-xmark"></i> Close Preview</a>
    </div>
    <?php if (!empty($preview_announcement['thumbnail'])): ?>
      <img src="<?= adm_e('../' . ltrim(str_replace('\\', '/', (string)$preview_announcement['thumbnail']), '/')) ?>" alt="" style="width: 100%; max-height: 260px; object-fit: cover; border-radius: 8px; margin: 14px 0;">
    <?php endif; ?>
    <span class="status-badge status-badge--<?= (int)$preview_announcement['is_published'] === 1 ? 'approved' : 'neutral' ?>"><?= (int)$preview_announcement['is_published'] === 1 ? 'Published' : 'Draft' ?></span>
    <div style="margin-top: 14px;"><?= nl2br(adm_e(strip_tags((string)$preview_announcement['body']))) ?></div>
  </section>
<?php endif; ?>

<section class="details-grid">
  <div class="panel">
    <div class="panel__header">
      <div>
        <h2>Announcements</h2>
        <p>Drafts remain private. Scheduled items publish when their publish date arrives and this manager is loaded.</p>
      </div>
    </div>
    <?php if ($announcements): ?>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Title</th>
              <th>Category</th>
              <th>Status</th>
              <th>Published Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($announcements as $announcement): ?>
              <tr>
                <td>
                  <strong><?= adm_e($announcement['title']) ?></strong>
                  <small><?= adm_e($announcement['slug']) ?> - <?= adm_e($announcement['created_by_name'] ?: 'System') ?></small>
                </td>
                <td><?= adm_e(adm_status_label($announcement['category'])) ?></td>
                <td><span class="status-badge status-badge--<?= (int)$announcement['is_published'] === 1 ? 'approved' : 'neutral' ?>"><?= (int)$announcement['is_published'] === 1 ? 'Published' : 'Draft' ?></span></td>
                <td><?= adm_e($announcement['published_at'] ? adm_datetime($announcement['published_at']) : 'Not scheduled') ?></td>
                <td>
                  <div class="table-actions">
                    <a class="btn btn--small" href="announcements.php?edit=<?= adm_e($announcement['id']) ?>#announcementForm"><i class="fa-solid fa-pen"></i> Edit</a>
                    <a class="btn btn--small" href="announcements.php?preview=<?= adm_e($announcement['id']) ?>"><i class="fa-solid fa-eye"></i> Preview</a>
                    <form method="post" data-disable-on-submit>
                      <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
                      <input type="hidden" name="action" value="toggle_publish">
                      <input type="hidden" name="announcement_id" value="<?= adm_e($announcement['id']) ?>">
                      <button class="btn btn--small <?= (int)$announcement['is_published'] === 1 ? '' : 'btn--success' ?>" type="submit">
                        <i class="fa-solid <?= (int)$announcement['is_published'] === 1 ? 'fa-eye-slash' : 'fa-upload' ?>"></i>
                        <?= (int)$announcement['is_published'] === 1 ? 'Unpublish' : 'Publish' ?>
                      </button>
                    </form>
                    <form method="post" data-confirm="Delete this announcement?" data-disable-on-submit>
                      <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
                      <input type="hidden" name="action" value="delete_announcement">
                      <input type="hidden" name="announcement_id" value="<?= adm_e($announcement['id']) ?>">
                      <button class="btn btn--danger btn--small" type="submit"><i class="fa-solid fa-trash"></i> Delete</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="empty-state">
        <i class="fa-solid fa-bullhorn"></i>
        <strong>No announcements found</strong>
        <span>Create one or change the category filter.</span>
      </div>
    <?php endif; ?>
  </div>

  <aside class="form-panel" id="announcementForm">
    <h2><?= $edit_announcement ? 'Edit Announcement' : 'Create Announcement' ?></h2>
    <form method="post" enctype="multipart/form-data" class="form-section" data-disable-on-submit>
      <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
      <input type="hidden" name="action" value="save_announcement">
      <input type="hidden" name="announcement_id" value="<?= adm_e($form_values['id']) ?>">
      <div class="form-grid" style="grid-template-columns: 1fr;">
        <div class="form-field">
          <label for="title">Title</label>
          <input id="title" name="title" type="text" maxlength="200" value="<?= adm_e($form_values['title']) ?>" required>
        </div>
        <div class="form-field">
          <label for="category">Category</label>
          <select id="category" name="category">
            <?php foreach (array_slice($categories, 1) as $category): ?>
              <option value="<?= adm_e($category) ?>" <?= $form_values['category'] === $category ? 'selected' : '' ?>><?= adm_e(adm_status_label($category)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-field">
          <label for="body">Body / Content</label>
          <textarea id="body" name="body" required><?= adm_e($form_values['body']) ?></textarea>
        </div>
        <div class="form-field">
          <label for="thumbnail">Thumbnail</label>
          <input id="thumbnail" name="thumbnail" type="file" accept=".jpg,.jpeg,.png">
          <?php if ($form_values['thumbnail']): ?>
            <small class="field-help">Current: <a href="<?= adm_e('../' . ltrim(str_replace('\\', '/', (string)$form_values['thumbnail']), '/')) ?>" target="_blank" rel="noopener">view thumbnail</a></small>
          <?php else: ?>
            <small class="field-help">Optional JPG or PNG. Max 2MB.</small>
          <?php endif; ?>
        </div>
        <label class="check-field">
          <input type="checkbox" name="publish_now" value="1">
          <span>Publish now</span>
        </label>
        <div class="form-field">
          <label for="schedule_at">Schedule publishing</label>
          <?php $schedule_value = $form_values['published_at'] ? date('Y-m-d\TH:i', strtotime((string)$form_values['published_at'])) : ''; ?>
          <input id="schedule_at" name="schedule_at" type="datetime-local" value="<?= adm_e($schedule_value) ?>">
        </div>
        <button class="btn btn--primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save Announcement</button>
        <?php if ($edit_announcement): ?>
          <a class="btn" href="announcements.php">Cancel edit</a>
        <?php endif; ?>
      </div>
    </form>
  </aside>
</section>

<?php adm_page_end(); ?>
