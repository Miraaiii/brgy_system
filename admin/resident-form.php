<?php
require_once __DIR__ . '/includes/admin_layout.php';

$user = adm_require_secretary($conn);
$csrf = adm_action_token();
$resident_id = (int)($_GET['id'] ?? 0);
$is_edit = $resident_id > 0;
$errors = [];

function secretary_resident_upload_valid_id($field_name, $current_path = '') {
    if (empty($_FILES[$field_name]) || !is_array($_FILES[$field_name]) || (int)$_FILES[$field_name]['error'] === UPLOAD_ERR_NO_FILE) {
        return $current_path;
    }

    $file = $_FILES[$field_name];
    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Valid ID upload failed. Please choose another file.');
    }
    if ((int)$file['size'] > 5 * 1024 * 1024) {
        throw new Exception('Valid ID must not exceed 5MB.');
    }

    $extension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ['jpg', 'jpeg', 'png', 'pdf'], true)) {
        throw new Exception('Valid ID must be JPG, PNG, or PDF.');
    }

    $upload_dir = __DIR__ . '/../uploads/valid_ids';
    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
        throw new Exception('Unable to prepare upload folder.');
    }

    $safe_name = 'admin_valid_id_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
    $target = $upload_dir . DIRECTORY_SEPARATOR . $safe_name;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new Exception('Unable to save uploaded valid ID.');
    }

    return 'uploads/valid_ids/' . $safe_name;
}

$resident = null;
if ($is_edit && adm_table_exists($conn, 'residents')) {
    $resident = adm_fetch_one(
        $conn,
        'SELECT r.*, h.house_number, h.street, h.purok
         FROM residents r
         LEFT JOIN households h ON h.id = r.household_id
         WHERE r.id = ?
         LIMIT 1',
        'i',
        [$resident_id]
    );
    if (!$resident) {
        adm_set_flash('danger', 'Resident not found.');
        header('Location: residents.php');
        exit();
    }
}

$defaults = [
    'last_name' => '',
    'first_name' => '',
    'middle_name' => '',
    'suffix' => '',
    'birth_date' => '',
    'birth_place' => '',
    'sex' => 'female',
    'civil_status' => 'single',
    'nationality' => 'Filipino',
    'occupation' => '',
    'contact_number' => '',
    'email' => '',
    'philsys_id' => '',
    'is_voter' => 0,
    'is_pwd' => 0,
    'is_solo_parent' => 0,
    'is_4ps' => 0,
    'is_senior' => 0,
    'household_id' => '',
    'house_number' => '',
    'street' => '',
    'purok' => '',
    'status' => 'active',
    'valid_id_path' => '',
];

$form = array_merge($defaults, $resident ?: []);
$form['household_id'] = $resident['household_id'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!adm_verify_action_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please refresh and try again.';
    }

    foreach (array_keys($defaults) as $field) {
        if (array_key_exists($field, $_POST)) {
            $form[$field] = is_string($_POST[$field]) ? trim($_POST[$field]) : $_POST[$field];
        }
    }

    foreach (['is_voter', 'is_pwd', 'is_solo_parent', 'is_4ps', 'is_senior'] as $flag) {
        $form[$flag] = isset($_POST[$flag]) ? 1 : 0;
    }

    $required = [
        'last_name' => 'Last name',
        'first_name' => 'First name',
        'birth_date' => 'Birth date',
        'birth_place' => 'Place of birth',
        'sex' => 'Sex',
        'civil_status' => 'Civil status',
        'nationality' => 'Nationality',
        'street' => 'Street',
        'purok' => 'Purok',
        'status' => 'Status',
    ];
    foreach ($required as $field => $label) {
        if (trim((string)$form[$field]) === '') {
            $errors[] = $label . ' is required.';
        }
    }

    if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (!in_array($form['sex'], ['male', 'female'], true)) {
        $errors[] = 'Please select a valid sex.';
    }
    if (!in_array($form['civil_status'], ['single', 'married', 'widowed', 'separated', 'annulled'], true)) {
        $errors[] = 'Please select a valid civil status.';
    }
    if (!in_array($form['status'], ['active', 'deceased', 'transferred'], true)) {
        $errors[] = 'Please select a valid resident status.';
    }

    if (!$errors) {
        try {
            $form['valid_id_path'] = secretary_resident_upload_valid_id('valid_id', (string)($resident['valid_id_path'] ?? ''));
            $household_id = (int)($form['household_id'] ?: 0);
            if ($household_id <= 0) {
                $household_id = adm_find_or_create_household($conn, $form['house_number'], $form['street'], $form['purok']) ?: 0;
            }

            if ($is_edit) {
                $stmt = $conn->prepare(
                    "UPDATE residents
                     SET household_id = ?, last_name = ?, first_name = ?, middle_name = ?, suffix = ?,
                         birth_date = ?, birth_place = ?, sex = ?, civil_status = ?, nationality = ?,
                         occupation = ?, contact_number = ?, email = ?, philsys_id = ?,
                         is_voter = ?, is_pwd = ?, is_solo_parent = ?, is_4ps = ?, is_senior = ?,
                         valid_id_path = ?, status = ?, updated_at = NOW()
                     WHERE id = ?"
                );
                if (!$stmt) {
                    throw new Exception('Unable to prepare resident update.');
                }
                $household_value = $household_id > 0 ? $household_id : null;
                $middle_name = $form['middle_name'] !== '' ? $form['middle_name'] : null;
                $suffix = $form['suffix'] !== '' ? $form['suffix'] : null;
                $occupation = $form['occupation'] !== '' ? $form['occupation'] : null;
                $contact = $form['contact_number'] !== '' ? $form['contact_number'] : null;
                $email = $form['email'] !== '' ? $form['email'] : null;
                $philsys = $form['philsys_id'] !== '' ? $form['philsys_id'] : null;
                $stmt->bind_param(
                    'isssssssssssssiiiiissi',
                    $household_value,
                    $form['last_name'],
                    $form['first_name'],
                    $middle_name,
                    $suffix,
                    $form['birth_date'],
                    $form['birth_place'],
                    $form['sex'],
                    $form['civil_status'],
                    $form['nationality'],
                    $occupation,
                    $contact,
                    $email,
                    $philsys,
                    $form['is_voter'],
                    $form['is_pwd'],
                    $form['is_solo_parent'],
                    $form['is_4ps'],
                    $form['is_senior'],
                    $form['valid_id_path'],
                    $form['status'],
                    $resident_id
                );
                $stmt->execute();
                $stmt->close();

                if (!empty($resident['user_id'])) {
                    $fullname = trim($form['first_name'] . ' ' . ($form['middle_name'] ? $form['middle_name'] . ' ' : '') . $form['last_name']);
                    $stmt_user = $conn->prepare('UPDATE users SET fullname = ?, contact = ?, purok = ? WHERE id = ?');
                    if ($stmt_user) {
                        $user_id = (int)$resident['user_id'];
                        $stmt_user->bind_param('sssi', $fullname, $contact, $form['purok'], $user_id);
                        $stmt_user->execute();
                        $stmt_user->close();
                    }
                }

                adm_log_activity($conn, (int)$user['id'], 'Updated resident record', 'residents', $resident_id, ['status' => $form['status']]);
                adm_set_flash('success', 'Resident record updated.');
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO residents (
                        household_id, last_name, first_name, middle_name, suffix,
                        birth_date, birth_place, sex, civil_status, nationality,
                        occupation, contact_number, email, philsys_id,
                        is_voter, is_pwd, is_solo_parent, is_4ps, is_senior,
                        valid_id_path, status, verified_by, verified_at
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
                );
                if (!$stmt) {
                    throw new Exception('Unable to prepare resident insert.');
                }
                $household_value = $household_id > 0 ? $household_id : null;
                $middle_name = $form['middle_name'] !== '' ? $form['middle_name'] : null;
                $suffix = $form['suffix'] !== '' ? $form['suffix'] : null;
                $occupation = $form['occupation'] !== '' ? $form['occupation'] : null;
                $contact = $form['contact_number'] !== '' ? $form['contact_number'] : null;
                $email = $form['email'] !== '' ? $form['email'] : null;
                $philsys = $form['philsys_id'] !== '' ? $form['philsys_id'] : null;
                $secretary_id = (int)$user['id'];
                $stmt->bind_param(
                    'isssssssssssssiiiiissi',
                    $household_value,
                    $form['last_name'],
                    $form['first_name'],
                    $middle_name,
                    $suffix,
                    $form['birth_date'],
                    $form['birth_place'],
                    $form['sex'],
                    $form['civil_status'],
                    $form['nationality'],
                    $occupation,
                    $contact,
                    $email,
                    $philsys,
                    $form['is_voter'],
                    $form['is_pwd'],
                    $form['is_solo_parent'],
                    $form['is_4ps'],
                    $form['is_senior'],
                    $form['valid_id_path'],
                    $form['status'],
                    $secretary_id
                );
                $stmt->execute();
                $new_id = (int)$stmt->insert_id;
                $stmt->close();

                adm_log_activity($conn, (int)$user['id'], 'Created resident record', 'residents', $new_id, ['status' => $form['status']]);
                adm_set_flash('success', 'Resident record created.');
            }

            header('Location: residents.php');
            exit();
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$households = adm_table_exists($conn, 'households')
    ? adm_fetch_all($conn, 'SELECT id, house_number, street, purok FROM households ORDER BY purok ASC, street ASC LIMIT 300')
    : [];

$title = $is_edit ? 'Edit Resident' : 'Add Resident';
$actions = '<a class="btn" href="residents.php"><i class="fa-solid fa-arrow-left"></i> Back to masterlist</a>';

adm_page_start($title, $is_edit ? 'residents' : 'resident-form', $user, 'resident-form-page');
adm_page_header('Resident records', $title, 'Keep resident identity, address, and program tags current.', $actions);
?>

<?php if ($errors): ?>
  <div class="flash flash--danger" role="alert">
    <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
    <span><?= adm_e(implode(' ', $errors)) ?></span>
  </div>
<?php endif; ?>

<form class="form-panel" method="post" enctype="multipart/form-data" data-disable-on-submit>
  <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">

  <section class="form-section">
    <h2>Personal Information</h2>
    <div class="form-grid form-grid--three">
      <div class="form-field">
        <label for="last_name">Last name</label>
        <input id="last_name" name="last_name" type="text" value="<?= adm_e($form['last_name']) ?>" required>
      </div>
      <div class="form-field">
        <label for="first_name">First name</label>
        <input id="first_name" name="first_name" type="text" value="<?= adm_e($form['first_name']) ?>" required>
      </div>
      <div class="form-field">
        <label for="middle_name">Middle name</label>
        <input id="middle_name" name="middle_name" type="text" value="<?= adm_e($form['middle_name']) ?>">
      </div>
      <div class="form-field">
        <label for="suffix">Suffix</label>
        <input id="suffix" name="suffix" type="text" value="<?= adm_e($form['suffix']) ?>">
      </div>
      <div class="form-field">
        <label for="birth_date">Birth date</label>
        <input id="birth_date" name="birth_date" type="date" value="<?= adm_e($form['birth_date']) ?>" required>
      </div>
      <div class="form-field">
        <label for="birth_place">Place of birth</label>
        <input id="birth_place" name="birth_place" type="text" value="<?= adm_e($form['birth_place']) ?>" required>
      </div>
      <div class="form-field">
        <label for="sex">Sex</label>
        <select id="sex" name="sex" required>
          <option value="female" <?= $form['sex'] === 'female' ? 'selected' : '' ?>>Female</option>
          <option value="male" <?= $form['sex'] === 'male' ? 'selected' : '' ?>>Male</option>
        </select>
      </div>
      <div class="form-field">
        <label for="civil_status">Civil status</label>
        <select id="civil_status" name="civil_status" required>
          <?php foreach (['single', 'married', 'widowed', 'separated', 'annulled'] as $option): ?>
            <option value="<?= adm_e($option) ?>" <?= $form['civil_status'] === $option ? 'selected' : '' ?>><?= adm_e(adm_status_label($option)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-field">
        <label for="nationality">Nationality</label>
        <input id="nationality" name="nationality" type="text" value="<?= adm_e($form['nationality']) ?>" required>
      </div>
      <div class="form-field">
        <label for="occupation">Occupation</label>
        <input id="occupation" name="occupation" type="text" value="<?= adm_e($form['occupation']) ?>">
      </div>
    </div>
  </section>

  <section class="form-section">
    <h2>Contact, IDs, and Tags</h2>
    <div class="form-grid form-grid--three">
      <div class="form-field">
        <label for="contact_number">Mobile number</label>
        <input id="contact_number" name="contact_number" type="tel" value="<?= adm_e($form['contact_number']) ?>">
      </div>
      <div class="form-field">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="<?= adm_e($form['email']) ?>">
      </div>
      <div class="form-field">
        <label for="philsys_id">PhilSys ID</label>
        <input id="philsys_id" name="philsys_id" type="text" value="<?= adm_e($form['philsys_id']) ?>">
      </div>
      <?php foreach ([
          'is_voter' => 'Voter',
          'is_pwd' => 'PWD',
          'is_solo_parent' => 'Solo Parent',
          'is_4ps' => '4Ps',
          'is_senior' => 'Senior',
      ] as $field => $label): ?>
        <label class="check-field">
          <input type="checkbox" name="<?= adm_e($field) ?>" value="1" <?= (int)$form[$field] === 1 ? 'checked' : '' ?>>
          <span><?= adm_e($label) ?></span>
        </label>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="form-section">
    <h2>Address</h2>
    <div class="form-grid">
      <div class="form-field form-field--full">
        <label for="household_id">Existing household</label>
        <select id="household_id" name="household_id">
          <option value="">Create or find household from address below</option>
          <?php foreach ($households as $household): ?>
            <?php $label = trim(($household['house_number'] ? $household['house_number'] . ' ' : '') . $household['street'] . ' - ' . $household['purok']); ?>
            <option value="<?= adm_e($household['id']) ?>" <?= (string)$form['household_id'] === (string)$household['id'] ? 'selected' : '' ?>><?= adm_e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <small class="field-help">Leave this blank to create or reuse a household matching the address below.</small>
      </div>
      <div class="form-field">
        <label for="house_number">House no.</label>
        <input id="house_number" name="house_number" type="text" value="<?= adm_e($form['house_number']) ?>">
      </div>
      <div class="form-field">
        <label for="street">Street</label>
        <input id="street" name="street" type="text" value="<?= adm_e($form['street']) ?>" required>
      </div>
      <div class="form-field">
        <label for="purok">Purok</label>
        <input id="purok" name="purok" type="text" value="<?= adm_e($form['purok']) ?>" required>
      </div>
    </div>
  </section>

  <section class="form-section">
    <h2>Status and Verification</h2>
    <div class="form-grid">
      <div class="form-field">
        <label for="status">Status</label>
        <select id="status" name="status" required>
          <?php foreach (['active', 'deceased', 'transferred'] as $option): ?>
            <option value="<?= adm_e($option) ?>" <?= $form['status'] === $option ? 'selected' : '' ?>><?= adm_e(adm_status_label($option)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-field">
        <label for="valid_id">Valid ID</label>
        <input id="valid_id" name="valid_id" type="file" accept=".jpg,.jpeg,.png,.pdf">
        <?php if (!empty($form['valid_id_path'])): ?>
          <small class="field-help">Current file: <a href="<?= adm_e('../' . ltrim(str_replace('\\', '/', (string)$form['valid_id_path']), '/')) ?>" target="_blank" rel="noopener">view uploaded ID</a></small>
        <?php else: ?>
          <small class="field-help">Accepted: JPG, PNG, or PDF up to 5MB.</small>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <div class="form-actions">
    <button class="btn btn--primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save Resident</button>
    <a class="btn" href="residents.php">Cancel</a>
  </div>
</form>

<?php adm_page_end(); ?>
