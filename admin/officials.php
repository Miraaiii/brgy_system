<?php
require_once __DIR__ . '/includes/admin_layout.php';

$user = adm_require_captain($conn);
$csrf = adm_action_token();
$positions = [
    'captain' => 'Punong Barangay',
    'secretary' => 'Barangay Secretary',
    'treasurer' => 'Barangay Treasurer',
    'kagawad' => 'Barangay Kagawad',
    'sk_chair' => 'SK Chairperson',
    'sk_kagawad' => 'SK Kagawad',
];

function officials_upload_photo($field) {
    if (empty($_FILES[$field]['name']) || (int)($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return '';
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $tmp = $_FILES[$field]['tmp_name'];
    $mime = function_exists('mime_content_type') ? mime_content_type($tmp) : ($_FILES[$field]['type'] ?? '');
    if (!isset($allowed[$mime])) {
        return '';
    }

    $dir = __DIR__ . '/../uploads/officials';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $filename = 'official-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $target = $dir . '/' . $filename;
    if (!move_uploaded_file($tmp, $target)) {
        return '';
    }

    return 'uploads/officials/' . $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!adm_verify_action_token($_POST['csrf_token'] ?? '')) {
        adm_set_flash('danger', 'Your session expired. Please refresh and try again.');
    } elseif (!adm_table_exists($conn, 'officials')) {
        adm_set_flash('danger', 'Officials table is not installed.');
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'save_official') {
            $official_id = (int)($_POST['official_id'] ?? 0);
            $official_user_id = (int)($_POST['user_id'] ?? 0);
            $position = strtolower(trim((string)($_POST['position'] ?? '')));
            $committee = trim((string)($_POST['committee'] ?? ''));
            $term_start = trim((string)($_POST['term_start'] ?? ''));
            $term_end = trim((string)($_POST['term_end'] ?? ''));
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $photo_path = officials_upload_photo('photo');

            if ($official_user_id <= 0 || !isset($positions[$position]) || $term_start === '' || $term_end === '') {
                adm_set_flash('danger', 'User, position, term start, and term end are required.');
            } elseif ($official_id > 0) {
                if ($photo_path !== '') {
                    $stmt = $conn->prepare(
                        'UPDATE officials SET user_id = ?, position = ?, committee = ?, photo_path = ?, term_start = ?, term_end = ?, is_active = ? WHERE id = ?'
                    );
                    if ($stmt) {
                        $stmt->bind_param('isssssii', $official_user_id, $position, $committee, $photo_path, $term_start, $term_end, $is_active, $official_id);
                    }
                } else {
                    $stmt = $conn->prepare(
                        'UPDATE officials SET user_id = ?, position = ?, committee = ?, term_start = ?, term_end = ?, is_active = ? WHERE id = ?'
                    );
                    if ($stmt) {
                        $stmt->bind_param('issssii', $official_user_id, $position, $committee, $term_start, $term_end, $is_active, $official_id);
                    }
                }
                if ($stmt && $stmt->execute()) {
                    $stmt->close();
                    adm_log_activity($conn, (int)$user['id'], 'official_term_updated', 'officials', $official_id, ['position' => $position, 'is_active' => $is_active]);
                    adm_set_flash('success', 'Official term record updated.');
                } else {
                    adm_set_flash('danger', 'Unable to update official record.');
                }
            } else {
                $stmt = $conn->prepare(
                    'INSERT INTO officials (user_id, position, committee, photo_path, term_start, term_end, is_active)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                if ($stmt) {
                    $stmt->bind_param('isssssi', $official_user_id, $position, $committee, $photo_path, $term_start, $term_end, $is_active);
                    $stmt->execute();
                    $official_id = (int)$stmt->insert_id;
                    $stmt->close();
                    adm_log_activity($conn, (int)$user['id'], 'official_term_created', 'officials', $official_id, ['position' => $position, 'is_active' => $is_active]);
                    adm_set_flash('success', 'Official term record added.');
                } else {
                    adm_set_flash('danger', 'Unable to add official record.');
                }
            }
        } elseif ($action === 'toggle_official') {
            $official_id = (int)($_POST['official_id'] ?? 0);
            $is_active = (int)($_POST['is_active'] ?? 0);
            $stmt = $conn->prepare('UPDATE officials SET is_active = ? WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('ii', $is_active, $official_id);
                $stmt->execute();
                $stmt->close();
                adm_log_activity($conn, (int)$user['id'], 'official_status_changed', 'officials', $official_id, ['is_active' => $is_active]);
                adm_set_flash('success', 'Official status updated.');
            }
        }
    }

    header('Location: officials.php');
    exit();
}

$edit_id = (int)($_GET['edit'] ?? 0);
$edit_record = $edit_id > 0 && adm_table_exists($conn, 'officials')
    ? adm_fetch_one($conn, 'SELECT * FROM officials WHERE id = ? LIMIT 1', 'i', [$edit_id])
    : null;
$official_users = adm_table_exists($conn, 'users')
    ? adm_fetch_all($conn, "SELECT id, fullname, email, role FROM users WHERE role <> 'resident' ORDER BY fullname ASC")
    : [];
$officials = adm_table_exists($conn, 'officials')
    ? adm_fetch_all(
        $conn,
        "SELECT o.*, u.fullname, u.email, u.role
         FROM officials o
         INNER JOIN users u ON u.id = o.user_id
         ORDER BY o.is_active DESC, FIELD(o.position, 'captain', 'secretary', 'treasurer', 'kagawad', 'sk_chair', 'sk_kagawad'), o.term_end DESC"
    )
    : [];

adm_page_start('Officials Directory Management', 'officials', $user, 'officials-page');
adm_page_header('Captain only', 'Officials Directory Management', 'Manage current and past barangay official term records shown in the public directory.');
?>

<section class="details-grid">
  <section class="panel">
    <div class="panel__header">
      <div>
        <h2>Officials List</h2>
        <p>Only active records appear as current officials on the public site.</p>
      </div>
    </div>
    <?php if ($officials): ?>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Official</th><th>Position</th><th>Committee</th><th>Term</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($officials as $official): ?>
              <tr>
                <td>
                  <strong><?= adm_e($official['fullname']) ?></strong>
                  <small><?= adm_e($official['email']) ?></small>
                </td>
                <td><?= adm_e($positions[$official['position']] ?? adm_role_label($official['position'])) ?></td>
                <td><?= adm_e($official['committee'] ?: 'N/A') ?></td>
                <td><?= adm_e(adm_date($official['term_start'])) ?> - <?= adm_e(adm_date($official['term_end'])) ?></td>
                <td><span class="status-badge status-badge--<?= (int)$official['is_active'] === 1 ? 'approved' : 'neutral' ?>"><?= (int)$official['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td>
                <td>
                  <div class="table-actions">
                    <a class="btn btn--small" href="officials.php?edit=<?= adm_e($official['id']) ?>"><i class="fa-solid fa-pen"></i> Edit</a>
                    <form method="post" data-disable-on-submit>
                      <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
                      <input type="hidden" name="action" value="toggle_official">
                      <input type="hidden" name="official_id" value="<?= adm_e($official['id']) ?>">
                      <input type="hidden" name="is_active" value="<?= (int)$official['is_active'] === 1 ? '0' : '1' ?>">
                      <button class="btn btn--small <?= (int)$official['is_active'] === 1 ? 'btn--danger' : 'btn--success' ?>" type="submit">
                        <i class="fa-solid <?= (int)$official['is_active'] === 1 ? 'fa-user-slash' : 'fa-user-check' ?>"></i>
                        <?= (int)$official['is_active'] === 1 ? 'Deactivate' : 'Activate' ?>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="empty-state"><i class="fa-solid fa-people-roof"></i><strong>No official records</strong><span>Add the first term record from the form.</span></div>
    <?php endif; ?>
  </section>

  <form class="form-panel" method="post" enctype="multipart/form-data" data-disable-on-submit>
    <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
    <input type="hidden" name="action" value="save_official">
    <input type="hidden" name="official_id" value="<?= adm_e($edit_record['id'] ?? 0) ?>">
    <h2><?= $edit_record ? 'Edit Official Record' : 'Add Official Record' ?></h2>
    <div class="form-section">
      <div class="form-grid" style="grid-template-columns: 1fr;">
        <div class="form-field">
          <label for="user_id">Linked Account</label>
          <select id="user_id" name="user_id" required>
            <option value="">Select official account</option>
            <?php foreach ($official_users as $official_user): ?>
              <option value="<?= adm_e($official_user['id']) ?>" <?= (int)($edit_record['user_id'] ?? 0) === (int)$official_user['id'] ? 'selected' : '' ?>>
                <?= adm_e(($official_user['fullname'] ?: $official_user['email']) . ' - ' . adm_role_label($official_user['role'])) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-field">
          <label for="position">Position</label>
          <select id="position" name="position" required>
            <?php foreach ($positions as $value => $label): ?>
              <option value="<?= adm_e($value) ?>" <?= ($edit_record['position'] ?? '') === $value ? 'selected' : '' ?>><?= adm_e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-field"><label for="committee">Committee</label><input id="committee" name="committee" type="text" value="<?= adm_e($edit_record['committee'] ?? '') ?>"></div>
        <div class="form-field"><label for="term_start">Term Start</label><input id="term_start" name="term_start" type="date" value="<?= adm_e($edit_record['term_start'] ?? '') ?>" required></div>
        <div class="form-field"><label for="term_end">Term End</label><input id="term_end" name="term_end" type="date" value="<?= adm_e($edit_record['term_end'] ?? '') ?>" required></div>
        <div class="form-field"><label for="photo">Official Photo</label><input id="photo" name="photo" type="file" accept="image/png,image/jpeg,image/webp"><small class="field-help">Leave blank to keep the current photo when editing.</small></div>
        <label class="check-field"><input type="checkbox" name="is_active" value="1" <?= (int)($edit_record['is_active'] ?? 1) === 1 ? 'checked' : '' ?>><span>Active official</span></label>
        <button class="btn btn--primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save Record</button>
        <?php if ($edit_record): ?><a class="btn" href="officials.php"><i class="fa-solid fa-plus"></i> Add New Instead</a><?php endif; ?>
      </div>
    </div>
  </form>
</section>

<?php adm_page_end(); ?>
