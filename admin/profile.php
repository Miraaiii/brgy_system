<?php
require_once __DIR__ . '/includes/admin_layout.php';

$user = adm_require_admin($conn);
$csrf = adm_action_token();

function secretary_upload_profile_photo($field_name, $current = '') {
    if (empty($_FILES[$field_name]) || !is_array($_FILES[$field_name]) || (int)$_FILES[$field_name]['error'] === UPLOAD_ERR_NO_FILE) {
        return $current;
    }

    $file = $_FILES[$field_name];
    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Profile photo upload failed.');
    }
    if ((int)$file['size'] > 2 * 1024 * 1024) {
        throw new Exception('Profile photo must not exceed 2MB.');
    }
    $extension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
        throw new Exception('Profile photo must be JPG or PNG.');
    }

    $upload_dir = __DIR__ . '/../uploads/profile_photos';
    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
        throw new Exception('Unable to prepare profile photo folder.');
    }

    $safe_name = 'secretary_profile_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
    $target = $upload_dir . DIRECTORY_SEPARATOR . $safe_name;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new Exception('Unable to save profile photo.');
    }

    return 'uploads/profile_photos/' . $safe_name;
}

function secretary_get_official($conn, $user_id) {
    if (!adm_table_exists($conn, 'officials')) {
        return null;
    }
    return adm_fetch_one(
        $conn,
        "SELECT * FROM officials WHERE user_id = ? AND position = 'secretary' ORDER BY is_active DESC, id DESC LIMIT 1",
        'i',
        [(int)$user_id]
    );
}

function secretary_save_official($conn, $user_id, $committee, $term_start, $term_end, $photo_path) {
    if (!adm_table_exists($conn, 'officials')) {
        return;
    }

    $existing = secretary_get_official($conn, $user_id);
    if ($existing) {
        $stmt = $conn->prepare(
            "UPDATE officials
             SET committee = ?, photo_path = ?, term_start = ?, term_end = ?, is_active = 1
             WHERE id = ?"
        );
        if (!$stmt) {
            throw new Exception('Unable to update official record.');
        }
        $id = (int)$existing['id'];
        $stmt->bind_param('ssssi', $committee, $photo_path, $term_start, $term_end, $id);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO officials (user_id, position, committee, photo_path, term_start, term_end, is_active)
             VALUES (?, 'secretary', ?, ?, ?, ?, 1)"
        );
        if (!$stmt) {
            throw new Exception('Unable to create official record.');
        }
        $stmt->bind_param('issss', $user_id, $committee, $photo_path, $term_start, $term_end);
        $stmt->execute();
        $stmt->close();
    }
}

$official = secretary_get_official($conn, (int)$user['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!adm_verify_action_token($_POST['csrf_token'] ?? '')) {
        adm_set_flash('danger', 'Your session expired. Please refresh and try again.');
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'update_profile') {
            $fullname = trim((string)($_POST['fullname'] ?? ''));
            $contact = trim((string)($_POST['contact'] ?? ''));
            $purok = trim((string)($_POST['purok'] ?? ''));
            $committee = trim((string)($_POST['committee'] ?? ''));
            $term_start = trim((string)($_POST['term_start'] ?? ''));
            $term_end = trim((string)($_POST['term_end'] ?? ''));

            if ($fullname === '') {
                adm_set_flash('danger', 'Full name is required.');
            } elseif ($term_start === '' || $term_end === '') {
                adm_set_flash('danger', 'Term start and term end are required.');
            } else {
                try {
                    $photo_path = secretary_upload_profile_photo('profile_photo', (string)($official['photo_path'] ?? ''));
                    $stmt = $conn->prepare('UPDATE users SET fullname = ?, contact = ?, purok = ? WHERE id = ?');
                    if (!$stmt) {
                        throw new Exception('Unable to update profile.');
                    }
                    $uid = (int)$user['id'];
                    $stmt->bind_param('sssi', $fullname, $contact, $purok, $uid);
                    $stmt->execute();
                    $stmt->close();
                    secretary_save_official($conn, $uid, $committee, $term_start, $term_end, $photo_path);
                    adm_log_activity($conn, $uid, 'Updated Secretary profile', 'users', $uid);
                    adm_set_flash('success', 'Profile updated.');
                } catch (Throwable $e) {
                    adm_set_flash('danger', $e->getMessage());
                }
            }
        } elseif ($action === 'change_password') {
            $current = (string)($_POST['current_password'] ?? '');
            $new = (string)($_POST['new_password'] ?? '');
            $confirm = (string)($_POST['confirm_password'] ?? '');
            $account = adm_fetch_one($conn, 'SELECT password_hash FROM users WHERE id = ? LIMIT 1', 'i', [(int)$user['id']]);
            if (!$account || !password_verify($current, (string)$account['password_hash'])) {
                adm_set_flash('danger', 'Current password is incorrect.');
            } elseif ($new !== $confirm) {
                adm_set_flash('danger', 'New passwords do not match.');
            } else {
                $password_errors = bms_password_errors($new);
                if ($password_errors) {
                    adm_set_flash('danger', $password_errors[0]);
                } else {
                    $hash = password_hash($new, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                    if ($stmt) {
                        $uid = (int)$user['id'];
                        $stmt->bind_param('si', $hash, $uid);
                        $stmt->execute();
                        $stmt->close();
                        adm_log_activity($conn, $uid, 'Changed Secretary password', 'users', $uid);
                        adm_set_flash('success', 'Password changed.');
                    } else {
                        adm_set_flash('danger', 'Unable to change password.');
                    }
                }
            }
        }
    }
    header('Location: profile.php');
    exit();
}

$user = adm_require_admin($conn);
$official = secretary_get_official($conn, (int)$user['id']);
$term_start = $official['term_start'] ?? date('Y-m-d');
$term_end = $official['term_end'] ?? date('Y-m-d', strtotime('+3 years'));
$photo_path = (string)($official['photo_path'] ?? '');

adm_page_start('My Profile', 'profile', $user, 'profile-page');
adm_page_header('Account', 'My Profile', 'Manage Secretary profile information, official record, photo, and password.');
?>

<section class="details-grid">
  <form class="form-panel" method="post" enctype="multipart/form-data" data-disable-on-submit>
    <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
    <input type="hidden" name="action" value="update_profile">
    <h2>Profile Details</h2>
    <dl class="definition-list" style="margin-bottom: 16px;">
      <div><dt>Position</dt><dd>Barangay Secretary</dd></div>
      <div><dt>Email</dt><dd><?= adm_e($user['email']) ?></dd></div>
      <div><dt>Committee</dt><dd><?= adm_e($official['committee'] ?? 'Not set') ?></dd></div>
      <div><dt>Term</dt><dd><?= adm_e(adm_date($term_start)) ?> to <?= adm_e(adm_date($term_end)) ?></dd></div>
    </dl>
    <div class="form-section">
      <div class="form-grid" style="grid-template-columns: 1fr;">
        <div class="form-field">
          <label for="fullname">Display name</label>
          <input id="fullname" name="fullname" type="text" value="<?= adm_e($user['fullname']) ?>" required>
        </div>
        <div class="form-field">
          <label for="contact">Mobile number</label>
          <input id="contact" name="contact" type="tel" value="<?= adm_e($user['contact']) ?>">
        </div>
        <div class="form-field">
          <label for="purok">Assigned purok / office tag</label>
          <input id="purok" name="purok" type="text" value="<?= adm_e($user['purok']) ?>">
        </div>
        <div class="form-field">
          <label for="committee">Committee</label>
          <input id="committee" name="committee" type="text" value="<?= adm_e($official['committee'] ?? '') ?>">
        </div>
        <div class="form-grid">
          <div class="form-field">
            <label for="term_start">Term start</label>
            <input id="term_start" name="term_start" type="date" value="<?= adm_e($term_start) ?>" required>
          </div>
          <div class="form-field">
            <label for="term_end">Term end</label>
            <input id="term_end" name="term_end" type="date" value="<?= adm_e($term_end) ?>" required>
          </div>
        </div>
        <div class="form-field">
          <label for="profile_photo">Profile photo</label>
          <input id="profile_photo" name="profile_photo" type="file" accept=".jpg,.jpeg,.png">
          <?php if ($photo_path !== ''): ?>
            <small class="field-help">Current: <a href="<?= adm_e('../' . ltrim(str_replace('\\', '/', $photo_path), '/')) ?>" target="_blank" rel="noopener">view photo</a></small>
          <?php else: ?>
            <small class="field-help">Optional JPG or PNG. Max 2MB.</small>
          <?php endif; ?>
        </div>
        <button class="btn btn--primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save Profile</button>
      </div>
    </div>
  </form>

  <form class="form-panel" id="change-password" method="post" data-disable-on-submit>
    <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
    <input type="hidden" name="action" value="change_password">
    <h2>Change Password</h2>
    <div class="form-section">
      <div class="form-grid" style="grid-template-columns: 1fr;">
        <div class="form-field">
          <label for="current_password">Current password</label>
          <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
        </div>
        <div class="form-field">
          <label for="new_password">New password</label>
          <input id="new_password" name="new_password" type="password" autocomplete="new-password" required>
          <small class="field-help"><?= adm_e(BMS_PASSWORD_RULE_MESSAGE) ?></small>
        </div>
        <div class="form-field">
          <label for="confirm_password">Confirm password</label>
          <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required>
        </div>
        <button class="btn btn--primary" type="submit"><i class="fa-solid fa-key"></i> Change Password</button>
      </div>
    </div>
  </form>
</section>

<?php adm_page_end(); ?>
