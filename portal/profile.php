<?php
require_once __DIR__ . '/includes/resident_portal.php';

$ctx = rp_get_resident_context($conn, true);
$resident = $ctx['resident'];
$messages = [];
$errors = [];
$has_profile = $ctx['resident_id'] > 0 && $resident;

function profile_redirect($anchor, $query) {
    header('Location: profile.php?' . $query . '#' . $anchor);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section = $_POST['section'] ?? '';
    if (!bms_verify_csrf_token($_POST['csrf_token'] ?? '', 'resident_profile_csrf')) {
        $errors[] = 'Your session expired. Please refresh and try again.';
    } elseif (!$has_profile && $section !== 'password') {
        $errors[] = 'Your resident profile is not linked yet. Please contact the barangay office.';
    } elseif ($section === 'personal') {
        $first_name = trim((string)($_POST['first_name'] ?? ''));
        $middle_name = trim((string)($_POST['middle_name'] ?? ''));
        $last_name = trim((string)($_POST['last_name'] ?? ''));
        $birth_date = trim((string)($_POST['birth_date'] ?? ''));
        $birth_place = trim((string)($_POST['birth_place'] ?? ''));
        $sex = strtolower(trim((string)($_POST['sex'] ?? '')));
        $civil_status = strtolower(trim((string)($_POST['civil_status'] ?? '')));
        $nationality = trim((string)($_POST['nationality'] ?? 'Filipino'));
        $occupation = trim((string)($_POST['occupation'] ?? ''));
        if ($first_name === '' || $last_name === '' || $birth_date === '' || $birth_place === '') {
            $errors[] = 'Please complete all required personal information fields.';
        }
        if (!in_array($sex, ['male', 'female'], true)) {
            $errors[] = 'Please select a valid sex.';
        }
        if (!in_array($civil_status, ['single', 'married', 'widowed', 'separated', 'annulled'], true)) {
            $errors[] = 'Please select a valid civil status.';
        }
        if (!$errors) {
            $stmt = $conn->prepare(
                'UPDATE residents
                 SET first_name = ?, middle_name = ?, last_name = ?, birth_date = ?, birth_place = ?,
                     sex = ?, civil_status = ?, nationality = ?, occupation = ?
                 WHERE id = ?'
            );
            $stmt->bind_param('sssssssssi', $first_name, $middle_name, $last_name, $birth_date, $birth_place, $sex, $civil_status, $nationality, $occupation, $ctx['resident_id']);
            $stmt->execute();
            $stmt->close();
            $fullname = trim($first_name . ' ' . $last_name);
            $stmt = $conn->prepare('UPDATE users SET fullname = ? WHERE id = ?');
            $stmt->bind_param('si', $fullname, $ctx['user_id']);
            $stmt->execute();
            $stmt->close();
            profile_redirect('personal', 'saved=personal');
        }
    } elseif ($section === 'contact') {
        $contact_number = trim((string)($_POST['contact_number'] ?? ''));
        if ($contact_number === '') {
            $errors[] = 'Please enter your mobile number.';
        }
        if (!$errors) {
            $stmt = $conn->prepare('UPDATE residents SET contact_number = ? WHERE id = ?');
            $stmt->bind_param('si', $contact_number, $ctx['resident_id']);
            $stmt->execute();
            $stmt->close();
            $stmt = $conn->prepare('UPDATE users SET contact = ? WHERE id = ?');
            $stmt->bind_param('si', $contact_number, $ctx['user_id']);
            $stmt->execute();
            $stmt->close();
            profile_redirect('contact', 'saved=contact');
        }
    } elseif ($section === 'address') {
        $house_number = trim((string)($_POST['house_number'] ?? ''));
        $street = trim((string)($_POST['street'] ?? ''));
        $purok = trim((string)($_POST['purok'] ?? ''));
        if ($street === '' || $purok === '') {
            $errors[] = 'Please enter your street and purok.';
        }
        if (!$errors && rp_table_exists($conn, 'households')) {
            $household_id = (int)($resident['household_id'] ?? 0);
            if ($household_id > 0) {
                $stmt = $conn->prepare('UPDATE households SET house_number = ?, street = ?, purok = ? WHERE id = ?');
                $stmt->bind_param('sssi', $house_number, $street, $purok, $household_id);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $conn->prepare('INSERT INTO households (house_number, street, purok) VALUES (?, ?, ?)');
                $stmt->bind_param('sss', $house_number, $street, $purok);
                $stmt->execute();
                $household_id = (int)$stmt->insert_id;
                $stmt->close();
                $stmt = $conn->prepare('UPDATE residents SET household_id = ? WHERE id = ?');
                $stmt->bind_param('ii', $household_id, $ctx['resident_id']);
                $stmt->execute();
                $stmt->close();
            }
            $stmt = $conn->prepare('UPDATE users SET purok = ? WHERE id = ?');
            $stmt->bind_param('si', $purok, $ctx['user_id']);
            $stmt->execute();
            $stmt->close();
            profile_redirect('address', 'saved=address');
        }
    } elseif ($section === 'valid_id') {
        if (empty($_FILES['valid_id']) || (int)$_FILES['valid_id']['error'] === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Please choose a valid ID file.';
        } elseif ((int)$_FILES['valid_id']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Valid ID upload failed. Please try again.';
        } else {
            $extension = strtolower(pathinfo((string)$_FILES['valid_id']['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
            if (!in_array($extension, $allowed, true)) {
                $errors[] = 'Valid ID must be a PDF, JPG, JPEG, or PNG file.';
            }
            if ((int)$_FILES['valid_id']['size'] > 5 * 1024 * 1024) {
                $errors[] = 'Valid ID file must be 5MB or smaller.';
            }
            if (!$errors) {
                $upload_dir = __DIR__ . '/../uploads/valid_ids';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $stored_name = 'valid_id_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
                $target_path = $upload_dir . DIRECTORY_SEPARATOR . $stored_name;
                if (!move_uploaded_file($_FILES['valid_id']['tmp_name'], $target_path)) {
                    $errors[] = 'Unable to save valid ID file.';
                } else {
                    $relative_path = 'uploads/valid_ids/' . $stored_name;
                    $stmt = $conn->prepare('UPDATE residents SET valid_id_path = ? WHERE id = ?');
                    $stmt->bind_param('si', $relative_path, $ctx['resident_id']);
                    $stmt->execute();
                    $stmt->close();
                    profile_redirect('valid-id', 'saved=valid_id');
                }
            }
        }
    } elseif ($section === 'password') {
        $old_password = (string)($_POST['old_password'] ?? '');
        $new_password = (string)($_POST['new_password'] ?? '');
        $confirm_password = (string)($_POST['confirm_password'] ?? '');
        $row = rp_fetch_one($conn, 'SELECT password_hash FROM users WHERE id = ? LIMIT 1', 'i', [$ctx['user_id']]);
        if (!$row || !password_verify($old_password, (string)$row['password_hash'])) {
            $errors[] = 'Old password is incorrect.';
        }
        foreach (bms_password_errors($new_password) as $password_error) {
            $errors[] = $password_error;
            break;
        }
        if ($new_password !== $confirm_password) {
            $errors[] = 'New password and confirmation do not match.';
        }
        if (!$errors) {
            $hash = password_hash($new_password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $stmt->bind_param('si', $hash, $ctx['user_id']);
            $stmt->execute();
            $stmt->close();
            profile_redirect('account', 'saved=password');
        }
    }
}

$ctx = rp_get_resident_context($conn, true);
$resident = $ctx['resident'];
$has_profile = $ctx['resident_id'] > 0 && $resident;
$household_members = [];
if ($has_profile && !empty($resident['household_id'])) {
    $household_members = rp_fetch_all(
        $conn,
        'SELECT first_name, middle_name, last_name, suffix, birth_date, civil_status
         FROM residents
         WHERE household_id = ?
         ORDER BY last_name ASC, first_name ASC',
        'i',
        [(int)$resident['household_id']]
    );
}

$profile_checks = [
    'Name' => $has_profile && trim((string)($resident['first_name'] ?? '')) !== '' && trim((string)($resident['last_name'] ?? '')) !== '',
    'Birth date' => $has_profile && !empty($resident['birth_date']),
    'Mobile' => $has_profile && !empty($resident['contact_number']),
    'Address' => $has_profile && !empty($resident['street']),
    'Valid ID' => $has_profile && !empty($resident['valid_id_path']),
];
$profile_percent = (int)round((count(array_filter($profile_checks)) / max(count($profile_checks), 1)) * 100);
$csrf_token = bms_csrf_token('resident_profile_csrf');

rp_page_start('My Profile', 'profile', $ctx, 'profile-page');
?>

<section class="portal-page-header">
  <div>
    <p class="page-kicker">Resident account</p>
    <h1>My Profile</h1>
    <p>Update your resident record, household address, valid ID, and account password.</p>
  </div>
  <span class="<?= $ctx['is_verified'] ? 'verified-badge' : 'review-badge' ?>"><i class="fa-solid <?= $ctx['is_verified'] ? 'fa-circle-check' : 'fa-clock' ?>"></i> <?= $ctx['is_verified'] ? 'Verified resident' : 'Pending' ?></span>
</section>

<?php if ($errors): ?>
  <div class="account-alert account-alert--danger" role="alert">
    <i class="fa-solid fa-circle-exclamation"></i>
    <span><?= rp_e(implode(' ', $errors)) ?></span>
  </div>
<?php elseif (isset($_GET['saved'])): ?>
  <div class="account-alert" role="status">
    <i class="fa-solid fa-circle-check"></i>
    <span>Profile section saved.</span>
  </div>
<?php endif; ?>

<?php if (!$has_profile): ?>
  <div class="account-alert account-alert--danger" role="alert">
    <i class="fa-solid fa-triangle-exclamation"></i>
    <span>Your resident profile is not linked yet. Please visit the barangay office.</span>
  </div>
<?php endif; ?>

<section class="dashboard-panel profile-overview">
  <div class="panel-header">
    <div>
      <h2>Profile completeness</h2>
      <p>Complete records make document and complaint processing faster.</p>
    </div>
    <strong class="profile-percent"><?= rp_e($profile_percent) ?>%</strong>
  </div>
  <div class="progress profile-progress" role="progressbar" aria-label="Profile completeness" aria-valuenow="<?= rp_e($profile_percent) ?>" aria-valuemin="0" aria-valuemax="100">
    <div class="progress-bar" style="width: <?= rp_e($profile_percent) ?>%"><span><?= rp_e($profile_percent) ?>% complete</span></div>
  </div>
</section>

<section class="profile-tabs" data-profile-tabs>
  <div class="tab-list" role="tablist" aria-label="Profile sections">
    <button class="tab-button is-active" type="button" data-profile-tab="personal">Personal Info</button>
    <button class="tab-button" type="button" data-profile-tab="contact">Contact Details</button>
    <button class="tab-button" type="button" data-profile-tab="address">Address</button>
    <button class="tab-button" type="button" data-profile-tab="household">Household Members</button>
    <button class="tab-button" type="button" data-profile-tab="valid-id">Valid ID</button>
    <button class="tab-button" type="button" data-profile-tab="account">Account &amp; Password</button>
  </div>

  <div class="dashboard-panel tab-panel is-active" id="personal" data-profile-panel="personal">
    <div class="panel-header"><div><h2>Personal Info</h2><p>Name and civil record details.</p></div></div>
    <form method="post" class="portal-form">
      <input type="hidden" name="csrf_token" value="<?= rp_e($csrf_token) ?>">
      <input type="hidden" name="section" value="personal">
      <fieldset <?= !$has_profile ? 'disabled' : '' ?>>
        <div class="form-grid">
          <div class="form-field"><label for="firstName">First name</label><input id="firstName" name="first_name" value="<?= rp_e($resident['first_name'] ?? '') ?>" required></div>
          <div class="form-field"><label for="middleName">Middle name</label><input id="middleName" name="middle_name" value="<?= rp_e($resident['middle_name'] ?? '') ?>"></div>
          <div class="form-field"><label for="lastName">Last name</label><input id="lastName" name="last_name" value="<?= rp_e($resident['last_name'] ?? '') ?>" required></div>
          <div class="form-field"><label for="birthDate">Birth date</label><input id="birthDate" name="birth_date" type="date" value="<?= rp_e($resident['birth_date'] ?? '') ?>" required></div>
          <div class="form-field form-field--full"><label for="birthPlace">Birth place</label><input id="birthPlace" name="birth_place" value="<?= rp_e($resident['birth_place'] ?? '') ?>" required></div>
          <div class="form-field"><label for="sex">Sex</label><select id="sex" name="sex" required>
            <?php foreach (['male' => 'Male', 'female' => 'Female'] as $value => $label): ?><option value="<?= rp_e($value) ?>" <?= (($resident['sex'] ?? '') === $value) ? 'selected' : '' ?>><?= rp_e($label) ?></option><?php endforeach; ?>
          </select></div>
          <div class="form-field"><label for="civilStatus">Civil status</label><select id="civilStatus" name="civil_status" required>
            <?php foreach (['single', 'married', 'widowed', 'separated', 'annulled'] as $value): ?><option value="<?= rp_e($value) ?>" <?= (($resident['civil_status'] ?? '') === $value) ? 'selected' : '' ?>><?= rp_e(ucwords($value)) ?></option><?php endforeach; ?>
          </select></div>
          <div class="form-field"><label for="nationality">Nationality</label><input id="nationality" name="nationality" value="<?= rp_e($resident['nationality'] ?? 'Filipino') ?>" required></div>
          <div class="form-field"><label for="occupation">Occupation</label><input id="occupation" name="occupation" value="<?= rp_e($resident['occupation'] ?? '') ?>"></div>
        </div>
      </fieldset>
      <div class="form-actions"><button class="primary-action" type="submit" <?= !$has_profile ? 'disabled' : '' ?>><i class="fa-solid fa-floppy-disk"></i> Save personal info</button></div>
    </form>
  </div>

  <div class="dashboard-panel tab-panel" id="contact" data-profile-panel="contact">
    <div class="panel-header"><div><h2>Contact Details</h2><p>Email is used for login and cannot be edited here.</p></div></div>
    <form method="post" class="portal-form">
      <input type="hidden" name="csrf_token" value="<?= rp_e($csrf_token) ?>">
      <input type="hidden" name="section" value="contact">
      <fieldset <?= !$has_profile ? 'disabled' : '' ?>>
        <div class="form-grid">
          <div class="form-field"><label for="mobileNumber">Mobile number</label><input id="mobileNumber" name="contact_number" value="<?= rp_e($resident['contact_number'] ?? $ctx['user']['contact'] ?? '') ?>" required></div>
          <div class="form-field"><label for="emailAddress">Email address</label><input id="emailAddress" value="<?= rp_e($ctx['user']['email'] ?? '') ?>" readonly></div>
        </div>
        <div class="account-alert"><i class="fa-solid fa-circle-info"></i><span>To change your email, please visit the barangay office.</span></div>
      </fieldset>
      <div class="form-actions"><button class="primary-action" type="submit" <?= !$has_profile ? 'disabled' : '' ?>><i class="fa-solid fa-floppy-disk"></i> Save contact</button></div>
    </form>
  </div>

  <div class="dashboard-panel tab-panel" id="address" data-profile-panel="address">
    <div class="panel-header"><div><h2>Address</h2><p>Update your household address if you moved within the barangay.</p></div></div>
    <form method="post" class="portal-form">
      <input type="hidden" name="csrf_token" value="<?= rp_e($csrf_token) ?>">
      <input type="hidden" name="section" value="address">
      <fieldset <?= !$has_profile ? 'disabled' : '' ?>>
        <div class="form-grid">
          <div class="form-field"><label for="houseNumber">House no.</label><input id="houseNumber" name="house_number" value="<?= rp_e($resident['house_number'] ?? '') ?>"></div>
          <div class="form-field"><label for="street">Street</label><input id="street" name="street" value="<?= rp_e($resident['street'] ?? '') ?>" required></div>
          <div class="form-field"><label for="purok">Purok</label><input id="purok" name="purok" value="<?= rp_e($resident['household_purok'] ?? $ctx['user']['purok'] ?? '') ?>" required></div>
        </div>
      </fieldset>
      <div class="form-actions"><button class="primary-action" type="submit" <?= !$has_profile ? 'disabled' : '' ?>><i class="fa-solid fa-floppy-disk"></i> Save address</button></div>
    </form>
  </div>

  <div class="dashboard-panel tab-panel" id="household" data-profile-panel="household">
    <div class="panel-header"><div><h2>Household Members</h2><p>Residents sharing the same household record. View only.</p></div></div>
    <?php if ($household_members): ?>
      <div class="table-wrap">
        <table class="resident-table">
          <thead><tr><th>Name</th><th>Birth Date</th><th>Civil Status</th></tr></thead>
          <tbody>
            <?php foreach ($household_members as $member): ?>
              <tr>
                <td data-label="Name"><strong><?= rp_e(trim($member['first_name'] . ' ' . ($member['middle_name'] ?: '') . ' ' . $member['last_name'] . ' ' . ($member['suffix'] ?: ''))) ?></strong></td>
                <td data-label="Birth Date"><?= rp_e(rp_date_long($member['birth_date'])) ?></td>
                <td data-label="Civil Status"><?= rp_e(ucwords($member['civil_status'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="empty-state empty-state--compact"><i class="fa-solid fa-users"></i><strong>No household members listed</strong><span>Household members appear after records are linked.</span></div>
    <?php endif; ?>
  </div>

  <div class="dashboard-panel tab-panel" id="valid-id" data-profile-panel="valid-id">
    <div class="panel-header"><div><h2>Valid ID</h2><p>View or replace your uploaded identification file.</p></div></div>
    <?php if (!empty($resident['valid_id_path'])): ?>
      <a class="attachment-item" href="../<?= rp_e(ltrim(str_replace('\\', '/', $resident['valid_id_path']), '/')) ?>" target="_blank" rel="noopener">
        <span><i class="fa-solid fa-id-card"></i></span>
        <strong>Current valid ID</strong>
        <small><?= rp_e($resident['valid_id_path']) ?></small>
      </a>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="portal-form">
      <input type="hidden" name="csrf_token" value="<?= rp_e($csrf_token) ?>">
      <input type="hidden" name="section" value="valid_id">
      <fieldset <?= !$has_profile ? 'disabled' : '' ?>>
        <div class="form-field"><label for="validId">Upload / replace valid ID</label><input id="validId" name="valid_id" type="file" accept=".pdf,.jpg,.jpeg,.png" required><small class="field-help">Accepted: PDF, JPG, JPEG, PNG. Max 5MB.</small></div>
      </fieldset>
      <div class="form-actions"><button class="primary-action" type="submit" <?= !$has_profile ? 'disabled' : '' ?>><i class="fa-solid fa-upload"></i> Upload valid ID</button></div>
    </form>
  </div>

  <div class="dashboard-panel tab-panel" id="account" data-profile-panel="account">
    <div class="panel-header"><div><h2>Account &amp; Password</h2><p>Change your password. Email cannot be changed here.</p></div></div>
    <form method="post" class="portal-form">
      <input type="hidden" name="csrf_token" value="<?= rp_e($csrf_token) ?>">
      <input type="hidden" name="section" value="password">
      <div class="form-grid">
        <div class="form-field form-field--full"><label for="oldPassword">Old password</label><input id="oldPassword" name="old_password" type="password" required></div>
        <div class="form-field"><label for="newPassword">New password</label><input id="newPassword" name="new_password" type="password" required><small class="field-help"><?= rp_e(BMS_PASSWORD_RULE_MESSAGE) ?></small></div>
        <div class="form-field"><label for="confirmPassword">Confirm new password</label><input id="confirmPassword" name="confirm_password" type="password" required></div>
      </div>
      <div class="form-actions"><button class="primary-action" type="submit"><i class="fa-solid fa-key"></i> Change password</button></div>
    </form>
  </div>
</section>

<?php rp_page_end(); ?>
