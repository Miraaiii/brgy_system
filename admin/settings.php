<?php
require_once __DIR__ . '/includes/admin_layout.php';

$user = adm_require_captain($conn);
$csrf = adm_action_token();
adm_ensure_settings_tables($conn);

$tabs = [
    'accounts' => 'Admin Accounts',
    'roles' => 'Role Permissions',
    'templates' => 'Document Templates',
    'profile' => 'Barangay Profile',
];
$tab = strtolower(trim((string)($_GET['tab'] ?? 'accounts')));
if (!isset($tabs[$tab])) {
    $tab = 'accounts';
}

$role_options = adm_user_role_values($conn);
$role_options = array_values(array_filter($role_options, fn($role) => $role !== 'resident'));
$position_options = [
    'captain' => 'Punong Barangay',
    'secretary' => 'Barangay Secretary',
    'treasurer' => 'Barangay Treasurer',
    'kagawad' => 'Barangay Kagawad',
    'sk_chair' => 'SK Chairperson',
    'sk_kagawad' => 'SK Kagawad',
];
$permission_modules = [
    'dashboard' => 'Dashboard',
    'requests' => 'Document Requests',
    'residents' => 'Residents',
    'blotter' => 'Blotter',
    'finance' => 'Finance',
    'projects' => 'Projects & Programs',
    'announcements' => 'Announcements',
    'officials' => 'Officials Directory',
    'reports' => 'Reports',
    'settings' => 'System Settings',
    'audit' => 'Audit Trail',
];
$profile_keys = [
    'barangay_name',
    'municipality',
    'province',
    'barangay_seal',
    'captain_signature',
    'office_address',
    'office_hours',
    'contact_number',
    'email_address',
    'smtp_host',
    'smtp_port',
    'smtp_username',
    'smtp_password',
];

function settings_unique_username($conn, $email) {
    $base = strtolower(preg_replace('/[^a-z0-9_]+/', '_', strstr((string)$email, '@', true) ?: 'official'));
    $base = trim($base, '_') ?: 'official';
    $candidate = $base;
    $suffix = 1;
    while (adm_fetch_one($conn, 'SELECT id FROM users WHERE username = ? LIMIT 1', 's', [$candidate])) {
        $suffix++;
        $candidate = $base . $suffix;
    }
    return $candidate;
}

function settings_upload_asset($field) {
    if (empty($_FILES[$field]['name']) || (int)($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return '';
    }
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $tmp = $_FILES[$field]['tmp_name'];
    $mime = function_exists('mime_content_type') ? mime_content_type($tmp) : ($_FILES[$field]['type'] ?? '');
    if (!isset($allowed[$mime])) {
        return '';
    }
    $dir = __DIR__ . '/../uploads/settings';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $filename = $field . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $target = $dir . '/' . $filename;
    if (!move_uploaded_file($tmp, $target)) {
        return '';
    }
    return 'uploads/settings/' . $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirect_tab = (string)($_POST['tab'] ?? $tab);
    if (!adm_verify_action_token($_POST['csrf_token'] ?? '')) {
        adm_set_flash('danger', 'Your session expired. Please refresh and try again.');
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'create_account') {
            $fullname = trim((string)($_POST['fullname'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $role = strtolower(trim((string)($_POST['role'] ?? 'secretary')));
            $position = strtolower(trim((string)($_POST['position'] ?? $role)));
            $committee = trim((string)($_POST['committee'] ?? ''));
            $term_start = trim((string)($_POST['term_start'] ?? ''));
            $term_end = trim((string)($_POST['term_end'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $send_welcome = isset($_POST['send_welcome']);

            if ($fullname === '' || $email === '' || $password === '' || $term_start === '' || $term_end === '') {
                adm_set_flash('danger', 'Full name, email, temporary password, and term dates are required.');
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                adm_set_flash('danger', 'Please enter a valid email address.');
            } elseif (!in_array($role, $role_options, true)) {
                adm_set_flash('danger', 'Please select a valid official role for the current users table.');
            } elseif (!isset($position_options[$position])) {
                adm_set_flash('danger', 'Please select a valid official position.');
            } elseif (bms_password_errors($password)) {
                adm_set_flash('danger', bms_password_errors($password)[0]);
            } elseif (adm_fetch_one($conn, 'SELECT id FROM users WHERE email = ? LIMIT 1', 's', [$email])) {
                adm_set_flash('danger', 'Email address already exists.');
            } else {
                $username = settings_unique_username($conn, $email);
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $status = 'active';
                $conn->begin_transaction();
                try {
                    $stmt = $conn->prepare('INSERT INTO users (username, fullname, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?, ?)');
                    if (!$stmt) {
                        throw new Exception('Unable to prepare account creation.');
                    }
                    $stmt->bind_param('ssssss', $username, $fullname, $email, $hash, $role, $status);
                    $stmt->execute();
                    $new_user_id = (int)$stmt->insert_id;
                    $stmt->close();

                    if (adm_table_exists($conn, 'officials')) {
                        $stmt_official = $conn->prepare('INSERT INTO officials (user_id, position, committee, term_start, term_end, is_active) VALUES (?, ?, ?, ?, ?, 1)');
                        if ($stmt_official) {
                            $stmt_official->bind_param('issss', $new_user_id, $position, $committee, $term_start, $term_end);
                            $stmt_official->execute();
                            $stmt_official->close();
                        }
                    }
                    $conn->commit();
                } catch (Throwable $e) {
                    $conn->rollback();
                    adm_set_flash('danger', $e->getMessage());
                    header('Location: settings.php?tab=accounts');
                    exit();
                }

                if ($send_welcome) {
                    adm_send_email(
                        $email,
                        'Barangay official account created',
                        '<p>Your official account is ready.</p><p>Email: ' . adm_e($email) . '<br>Temporary password: ' . adm_e($password) . '</p>',
                        "Your official account is ready.\nEmail: {$email}\nTemporary password: {$password}"
                    );
                }
                adm_log_activity($conn, (int)$user['id'], 'admin_account_created', 'users', $new_user_id, ['role' => $role, 'email' => $email]);
                adm_set_flash('success', adm_role_label($role) . ' account created.');
            }
        } elseif ($action === 'account_status') {
            $account_id = (int)($_POST['account_id'] ?? 0);
            $status = strtolower(trim((string)($_POST['status'] ?? 'active')));
            if ($account_id === (int)$user['id'] && $status !== 'active') {
                adm_set_flash('danger', 'You cannot suspend your own Captain account.');
            } elseif (!in_array($status, ['active', 'pending', 'suspended'], true)) {
                adm_set_flash('danger', 'Invalid account status.');
            } else {
                $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ? AND role <> 'resident'");
                if ($stmt) {
                    $stmt->bind_param('si', $status, $account_id);
                    $stmt->execute();
                    $stmt->close();
                    adm_log_activity($conn, (int)$user['id'], 'admin_account_status_changed', 'users', $account_id, ['status' => $status]);
                    adm_set_flash('success', 'Account status updated.');
                }
            }
        } elseif ($action === 'reset_password') {
            $account_id = (int)($_POST['account_id'] ?? 0);
            $password = (string)($_POST['password'] ?? '');
            $errors = bms_password_errors($password);
            if ($errors) {
                adm_set_flash('danger', $errors[0]);
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ? AND role <> 'resident'");
                if ($stmt) {
                    $stmt->bind_param('si', $hash, $account_id);
                    $stmt->execute();
                    $stmt->close();
                    adm_log_activity($conn, (int)$user['id'], 'admin_account_password_reset', 'users', $account_id);
                    adm_set_flash('success', 'Temporary password saved.');
                }
            }
        } elseif ($action === 'delete_account') {
            $account_id = (int)($_POST['account_id'] ?? 0);
            if ($account_id === (int)$user['id']) {
                adm_set_flash('danger', 'You cannot delete your own Captain account.');
            } else {
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role <> 'resident'");
                if ($stmt) {
                    $stmt->bind_param('i', $account_id);
                    $stmt->execute();
                    $deleted = $stmt->affected_rows > 0;
                    $stmt->close();
                    if ($deleted) {
                        adm_log_activity($conn, (int)$user['id'], 'admin_account_deleted', 'users', $account_id);
                        adm_set_flash('success', 'Admin account deleted.');
                    } else {
                        adm_set_flash('danger', 'Account was not deleted.');
                    }
                }
            }
        } elseif ($action === 'save_permissions') {
            $permissions = $_POST['permissions'] ?? [];
            foreach (array_merge($role_options, ['captain']) as $role_name) {
                foreach ($permission_modules as $module_key => $module_label) {
                    $can_read = $role_name === 'captain' ? 1 : (isset($permissions[$role_name][$module_key]['read']) ? 1 : 0);
                    $can_write = $role_name === 'captain' ? 1 : (isset($permissions[$role_name][$module_key]['write']) ? 1 : 0);
                    $can_delete = $role_name === 'captain' ? 1 : (isset($permissions[$role_name][$module_key]['delete']) ? 1 : 0);
                    $stmt = $conn->prepare(
                        'INSERT INTO role_permissions (role, module, can_read, can_write, can_delete, updated_by)
                         VALUES (?, ?, ?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE can_read = VALUES(can_read), can_write = VALUES(can_write), can_delete = VALUES(can_delete), updated_by = VALUES(updated_by)'
                    );
                    if ($stmt) {
                        $user_id = (int)$user['id'];
                        $stmt->bind_param('ssiiii', $role_name, $module_key, $can_read, $can_write, $can_delete, $user_id);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            }
            adm_log_activity($conn, (int)$user['id'], 'settings_changed', 'role_permissions', null, ['scope' => 'roles']);
            adm_set_flash('success', 'Role permissions saved.');
        } elseif ($action === 'save_template') {
            $doc_type_id = (int)($_POST['doc_type_id'] ?? 0);
            $template_html = (string)($_POST['template_html'] ?? '');
            $stmt = $conn->prepare('UPDATE document_types SET template_html = ? WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('si', $template_html, $doc_type_id);
                $stmt->execute();
                $stmt->close();
                adm_log_activity($conn, (int)$user['id'], 'settings_changed', 'document_types', $doc_type_id, ['scope' => 'template']);
                adm_set_flash('success', 'Document template saved.');
            }
        } elseif ($action === 'reset_template') {
            $doc_type_id = (int)($_POST['doc_type_id'] ?? 0);
            $stmt = $conn->prepare('UPDATE document_types SET template_html = NULL WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('i', $doc_type_id);
                $stmt->execute();
                $stmt->close();
                adm_log_activity($conn, (int)$user['id'], 'settings_changed', 'document_types', $doc_type_id, ['scope' => 'template_reset']);
                adm_set_flash('success', 'Document template reset to default.');
            }
        } elseif ($action === 'save_profile') {
            foreach ($profile_keys as $key) {
                if (in_array($key, ['barangay_seal', 'captain_signature'], true)) {
                    continue;
                }
                adm_save_setting($conn, $key, (string)($_POST[$key] ?? ''), (int)$user['id']);
            }
            foreach (['barangay_seal', 'captain_signature'] as $file_key) {
                $uploaded = settings_upload_asset($file_key);
                if ($uploaded !== '') {
                    adm_save_setting($conn, $file_key, $uploaded, (int)$user['id']);
                }
            }
            adm_log_activity($conn, (int)$user['id'], 'settings_changed', 'system_settings', null, ['scope' => 'barangay_profile']);
            adm_set_flash('success', 'Barangay profile saved.');
        }
    }

    header('Location: settings.php?tab=' . urlencode($redirect_tab));
    exit();
}

$admins = adm_table_exists($conn, 'users')
    ? adm_fetch_all($conn, "SELECT id, username, fullname, email, role, status, last_login_at, created_at FROM users WHERE role <> 'resident' ORDER BY FIELD(role, 'captain', 'secretary', 'treasurer', 'kagawad', 'sk_chair', 'sk_kagawad'), fullname ASC")
    : [];
$permission_rows = adm_fetch_all($conn, 'SELECT role, module, can_read, can_write, can_delete FROM role_permissions');
$permission_map = [];
foreach ($permission_rows as $row) {
    $permission_map[$row['role']][$row['module']] = $row;
}
$document_types = adm_table_exists($conn, 'document_types')
    ? adm_fetch_all($conn, 'SELECT id, name, slug, template_html FROM document_types ORDER BY name ASC')
    : [];
$selected_template_id = (int)($_GET['template_id'] ?? ($document_types[0]['id'] ?? 0));
$selected_template = null;
foreach ($document_types as $doc_type) {
    if ((int)$doc_type['id'] === $selected_template_id) {
        $selected_template = $doc_type;
        break;
    }
}
$profile = adm_get_settings($conn, $profile_keys);
$template_variables = ['resident_full_name', 'address', 'purpose', 'date_issued', 'captain_name', 'doc_number', 'qr_code'];

adm_page_start('System Settings', 'settings', $user, 'settings-page');
adm_page_header('Captain only', 'System Settings', 'Manage admin accounts, role permissions, document templates, and barangay profile settings.');
?>

<nav class="tabs" aria-label="Settings tabs">
  <?php foreach ($tabs as $key => $label): ?>
    <a class="tab-link <?= $tab === $key ? 'is-active' : '' ?>" href="settings.php?tab=<?= adm_e($key) ?>"><?= adm_e($label) ?></a>
  <?php endforeach; ?>
</nav>

<?php if ($tab === 'accounts'): ?>
  <section class="details-grid">
    <section class="panel">
      <div class="panel__header"><div><h2>Admin Accounts</h2><p>All non-resident users with official access.</p></div></div>
      <?php if ($admins): ?>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th>Name</th><th>Role</th><th>Status</th><th>Last Login</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach ($admins as $admin): ?>
                <tr>
                  <td><strong><?= adm_e($admin['fullname']) ?></strong><small><?= adm_e($admin['email']) ?></small></td>
                  <td><span class="status-badge status-badge--<?= adm_e(adm_role_badge_class($admin['role'])) ?>"><?= adm_e(adm_role_label($admin['role'])) ?></span></td>
                  <td><span class="status-badge status-badge--<?= adm_e(adm_status_class($admin['status'])) ?>"><?= adm_e(adm_status_label($admin['status'])) ?></span></td>
                  <td><?= adm_e($admin['last_login_at'] ? adm_datetime($admin['last_login_at']) : 'Never') ?></td>
                  <td>
                    <div class="table-actions">
                      <?php if ((int)$admin['id'] !== (int)$user['id']): ?>
                        <form method="post" data-disable-on-submit>
                          <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
                          <input type="hidden" name="tab" value="accounts">
                          <input type="hidden" name="action" value="account_status">
                          <input type="hidden" name="account_id" value="<?= adm_e($admin['id']) ?>">
                          <input type="hidden" name="status" value="<?= $admin['status'] === 'active' ? 'suspended' : 'active' ?>">
                          <button class="btn btn--small <?= $admin['status'] === 'active' ? 'btn--danger' : 'btn--success' ?>" type="submit"><?= $admin['status'] === 'active' ? 'Suspend' : 'Activate' ?></button>
                        </form>
                        <details class="inline-reject">
                          <summary class="btn btn--small"><i class="fa-solid fa-key"></i> Reset</summary>
                          <form class="inline-reject__body" method="post" data-disable-on-submit>
                            <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
                            <input type="hidden" name="tab" value="accounts">
                            <input type="hidden" name="action" value="reset_password">
                            <input type="hidden" name="account_id" value="<?= adm_e($admin['id']) ?>">
                            <div class="form-field"><label for="reset-<?= adm_e($admin['id']) ?>">Temp password</label><input id="reset-<?= adm_e($admin['id']) ?>" name="password" type="password" required></div>
                            <button class="btn btn--primary btn--small" type="submit">Save password</button>
                          </form>
                        </details>
                        <form method="post" data-disable-on-submit>
                          <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
                          <input type="hidden" name="tab" value="accounts">
                          <input type="hidden" name="action" value="delete_account">
                          <input type="hidden" name="account_id" value="<?= adm_e($admin['id']) ?>">
                          <button class="btn btn--danger btn--small" type="submit"><i class="fa-solid fa-trash"></i> Delete</button>
                        </form>
                      <?php else: ?>
                        <small>Current account</small>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="empty-state"><i class="fa-solid fa-users-gear"></i><strong>No admin accounts</strong><span>Create the first official account from the form.</span></div>
      <?php endif; ?>
    </section>

    <form class="form-panel" method="post" data-disable-on-submit>
      <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
      <input type="hidden" name="tab" value="accounts">
      <input type="hidden" name="action" value="create_account">
      <h2>Create New Account</h2>
      <div class="form-section">
        <div class="form-grid" style="grid-template-columns: 1fr;">
          <div class="form-field"><label for="fullname">Full Name</label><input id="fullname" name="fullname" type="text" required></div>
          <div class="form-field"><label for="email">Email Address</label><input id="email" name="email" type="email" required></div>
          <div class="form-field">
            <label for="role">Role</label>
            <select id="role" name="role" required>
              <?php foreach ($role_options as $role_option): ?>
                <option value="<?= adm_e($role_option) ?>"><?= adm_e(adm_role_label($role_option)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-field">
            <label for="position">Position</label>
            <select id="position" name="position" required>
              <?php foreach ($position_options as $value => $label): ?>
                <option value="<?= adm_e($value) ?>"><?= adm_e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-field"><label for="committee">Committee</label><input id="committee" name="committee" type="text"></div>
          <div class="form-field"><label for="term_start">Term Start</label><input id="term_start" name="term_start" type="date" required></div>
          <div class="form-field"><label for="term_end">Term End</label><input id="term_end" name="term_end" type="date" required></div>
          <div class="form-field"><label for="password">Temp Password</label><input id="password" name="password" type="password" required><small class="field-help"><?= adm_e(BMS_PASSWORD_RULE_MESSAGE) ?></small></div>
          <label class="check-field"><input type="checkbox" name="send_welcome" value="1"><span>Send Welcome Email</span></label>
          <button class="btn btn--primary" type="submit"><i class="fa-solid fa-user-shield"></i> Create Account</button>
        </div>
      </div>
    </form>
  </section>
<?php elseif ($tab === 'roles'): ?>
  <form class="panel" method="post" data-disable-on-submit>
    <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
    <input type="hidden" name="tab" value="roles">
    <input type="hidden" name="action" value="save_permissions">
    <div class="panel__header"><div><h2>Permissions Matrix</h2><p>Captain permissions are always locked on.</p></div><button class="btn btn--primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save permissions</button></div>
    <div class="table-wrap">
      <table class="data-table data-table--matrix">
        <thead><tr><th>Role</th><?php foreach ($permission_modules as $module): ?><th><?= adm_e($module) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
          <?php foreach (array_unique(array_merge(['captain'], $role_options)) as $role_name): ?>
            <tr>
              <td><strong><?= adm_e(adm_role_label($role_name)) ?></strong></td>
              <?php foreach ($permission_modules as $module_key => $module_label): ?>
                <?php $current = $permission_map[$role_name][$module_key] ?? ['can_read' => 0, 'can_write' => 0, 'can_delete' => 0]; ?>
                <td>
                  <label class="mini-check"><input type="checkbox" name="permissions[<?= adm_e($role_name) ?>][<?= adm_e($module_key) ?>][read]" <?= $role_name === 'captain' || (int)$current['can_read'] === 1 ? 'checked' : '' ?> <?= $role_name === 'captain' ? 'disabled' : '' ?>> R</label>
                  <label class="mini-check"><input type="checkbox" name="permissions[<?= adm_e($role_name) ?>][<?= adm_e($module_key) ?>][write]" <?= $role_name === 'captain' || (int)$current['can_write'] === 1 ? 'checked' : '' ?> <?= $role_name === 'captain' ? 'disabled' : '' ?>> W</label>
                  <label class="mini-check"><input type="checkbox" name="permissions[<?= adm_e($role_name) ?>][<?= adm_e($module_key) ?>][delete]" <?= $role_name === 'captain' || (int)$current['can_delete'] === 1 ? 'checked' : '' ?> <?= $role_name === 'captain' ? 'disabled' : '' ?>> D</label>
                </td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </form>
<?php elseif ($tab === 'templates'): ?>
  <section class="details-grid">
    <section class="panel">
      <div class="panel__header"><div><h2>Template List</h2><p>Choose a document type to edit its HTML template.</p></div></div>
      <div class="activity-list panel__body">
        <?php foreach ($document_types as $doc_type): ?>
          <a class="activity-item" href="settings.php?tab=templates&template_id=<?= adm_e($doc_type['id']) ?>">
            <span class="stat-card__icon"><i class="fa-solid fa-file-lines"></i></span>
            <span class="activity-item__body"><strong><?= adm_e($doc_type['name']) ?></strong><small><?= adm_e($doc_type['slug']) ?></small></span>
            <span class="status-badge status-badge--<?= $selected_template_id === (int)$doc_type['id'] ? 'approval' : 'neutral' ?>"><?= $selected_template_id === (int)$doc_type['id'] ? 'Editing' : 'Open' ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </section>

    <form class="form-panel" method="post" data-disable-on-submit>
      <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
      <input type="hidden" name="tab" value="templates">
      <input type="hidden" name="action" value="save_template">
      <input type="hidden" name="doc_type_id" value="<?= adm_e($selected_template['id'] ?? 0) ?>">
      <h2><?= adm_e($selected_template['name'] ?? 'Document Template') ?></h2>
      <div class="form-section">
        <div class="form-field">
          <label for="template_html">Template HTML</label>
          <textarea id="template_html" name="template_html" style="min-height: 320px; font-family: Consolas, monospace;"><?= adm_e($selected_template['template_html'] ?? '') ?></textarea>
          <small class="field-help">Available variables: <?= adm_e('{' . implode('}, {', $template_variables) . '}') ?></small>
        </div>
        <div class="form-actions">
          <button class="btn btn--primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save Template</button>
        </div>
      </div>
    </form>

    <form class="form-panel" method="post" data-disable-on-submit>
      <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
      <input type="hidden" name="tab" value="templates">
      <input type="hidden" name="action" value="reset_template">
      <input type="hidden" name="doc_type_id" value="<?= adm_e($selected_template['id'] ?? 0) ?>">
      <h2>Preview & Variables</h2>
      <div class="summary-list">
        <?php foreach ($template_variables as $variable): ?>
          <div class="summary-row"><strong>{<?= adm_e($variable) ?>}</strong><span>Auto-filled during print generation</span></div>
        <?php endforeach; ?>
      </div>
      <div class="form-actions" style="margin-top: 16px;">
        <button class="btn btn--danger" type="submit"><i class="fa-solid fa-rotate-left"></i> Reset to Default</button>
      </div>
    </form>
  </section>
<?php else: ?>
  <form class="form-panel" method="post" enctype="multipart/form-data" data-disable-on-submit>
    <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
    <input type="hidden" name="tab" value="profile">
    <input type="hidden" name="action" value="save_profile">
    <h2>Barangay Profile</h2>
    <div class="form-section">
      <div class="form-grid">
        <div class="form-field"><label for="barangay_name">Barangay Name</label><input id="barangay_name" name="barangay_name" type="text" value="<?= adm_e($profile['barangay_name'] ?: 'Barangay Sta. Rosa 1') ?>" required></div>
        <div class="form-field"><label for="municipality">Municipality</label><input id="municipality" name="municipality" type="text" value="<?= adm_e($profile['municipality'] ?: 'Noveleta') ?>" required></div>
        <div class="form-field"><label for="province">Province</label><input id="province" name="province" type="text" value="<?= adm_e($profile['province'] ?: 'Cavite') ?>" required></div>
        <div class="form-field"><label for="office_address">Office Address</label><input id="office_address" name="office_address" type="text" value="<?= adm_e($profile['office_address']) ?>" required></div>
        <div class="form-field"><label for="office_hours">Office Hours</label><input id="office_hours" name="office_hours" type="text" value="<?= adm_e($profile['office_hours'] ?: 'Monday-Friday, 8:00 AM-5:00 PM') ?>" required></div>
        <div class="form-field"><label for="contact_number">Contact Number</label><input id="contact_number" name="contact_number" type="tel" value="<?= adm_e($profile['contact_number']) ?>" required></div>
        <div class="form-field"><label for="email_address">Email Address</label><input id="email_address" name="email_address" type="email" value="<?= adm_e($profile['email_address']) ?>" required></div>
        <div class="form-field"><label for="barangay_seal">Barangay Seal</label><input id="barangay_seal" name="barangay_seal" type="file" accept="image/png,image/jpeg,image/webp"><small class="field-help"><?= adm_e($profile['barangay_seal'] ?: 'Using default seal') ?></small></div>
        <div class="form-field"><label for="captain_signature">Captain Signature</label><input id="captain_signature" name="captain_signature" type="file" accept="image/png,image/jpeg,image/webp"><small class="field-help"><?= adm_e($profile['captain_signature'] ?: 'No signature uploaded') ?></small></div>
      </div>
    </div>
    <div class="form-section">
      <h2>SMTP Settings</h2>
      <div class="form-grid">
        <div class="form-field"><label for="smtp_host">Host</label><input id="smtp_host" name="smtp_host" type="text" value="<?= adm_e($profile['smtp_host']) ?>"></div>
        <div class="form-field"><label for="smtp_port">Port</label><input id="smtp_port" name="smtp_port" type="text" value="<?= adm_e($profile['smtp_port']) ?>"></div>
        <div class="form-field"><label for="smtp_username">Username</label><input id="smtp_username" name="smtp_username" type="text" value="<?= adm_e($profile['smtp_username']) ?>"></div>
        <div class="form-field"><label for="smtp_password">Password</label><input id="smtp_password" name="smtp_password" type="password" value="<?= adm_e($profile['smtp_password']) ?>"></div>
      </div>
    </div>
    <div class="form-actions"><button class="btn btn--primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save Profile</button></div>
  </form>
<?php endif; ?>

<?php adm_page_end(); ?>
