<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../config/auth_helpers.php';

function settings_e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function settings_fetch_one($conn, $sql, $types = '', array $params = []) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    if ($types !== '') {
        $refs = [];
        foreach ($params as $key => $value) {
            $refs[$key] = &$params[$key];
        }
        $stmt->bind_param($types, ...$refs);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$captain = settings_fetch_one(
    $conn,
    "SELECT id, fullname, email, role, status FROM users WHERE id = ? LIMIT 1",
    'i',
    [(int)$_SESSION['user_id']]
);

if (!$captain || strtolower((string)$captain['role']) !== 'captain' || strtolower((string)$captain['status']) !== 'active') {
    header('Location: ../logout.php');
    exit();
}

$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!bms_verify_csrf_token($_POST['csrf_token'] ?? '', 'captain_settings_csrf')) {
        $message = 'Your session expired. Please refresh and try again.';
        $message_type = 'danger';
    } else {
        $fullname = trim((string)($_POST['fullname'] ?? ''));
        $username = trim((string)($_POST['username'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $role = strtolower(trim((string)($_POST['role'] ?? 'secretary')));
        $password = (string)($_POST['password'] ?? '');
        $status = strtolower(trim((string)($_POST['status'] ?? 'active')));

        if ($username === '') {
            $username = $email;
        }

        if ($fullname === '' || $email === '' || $password === '') {
            $message = 'Full name, email, and password are required.';
            $message_type = 'danger';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
            $message_type = 'danger';
        } elseif (!in_array($role, ['captain', 'secretary', 'treasurer', 'kagawad'], true)) {
            $message = 'Please select a valid official role.';
            $message_type = 'danger';
        } elseif (!in_array($status, ['active', 'pending', 'suspended'], true)) {
            $message = 'Please select a valid account status.';
            $message_type = 'danger';
        } else {
            $password_errors = bms_password_errors($password);
            if ($password_errors) {
                $message = $password_errors[0];
                $message_type = 'danger';
            } elseif (settings_fetch_one($conn, 'SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1', 'ss', [$email, $username])) {
                $message = 'Email or username already exists.';
                $message_type = 'danger';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare(
                    'INSERT INTO users (username, fullname, email, password_hash, role, status)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                if ($stmt) {
                    $stmt->bind_param('ssssss', $username, $fullname, $email, $hash, $role, $status);
                    $stmt->execute();
                    $stmt->close();
                    $message = ucfirst($role) . ' account created.';
                    $message_type = 'success';
                } else {
                    $message = 'Unable to create account.';
                    $message_type = 'danger';
                }
            }
        }
    }
}

$admins = [];
$result = $conn->query("SELECT id, fullname, email, role, status, created_at FROM users WHERE role <> 'resident' ORDER BY role ASC, fullname ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $admins[] = $row;
    }
}

$csrf = bms_csrf_token('captain_settings_csrf');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Accounts - Barangay Sta. Rosa 1</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/secretary.css?v=20260605b">
  <style>
    body {
      padding: 24px;
    }
    .settings-shell {
      width: min(1100px, 100%);
      margin: 0 auto;
    }
  </style>
</head>
<body>
  <main class="settings-shell">
    <?php
      $actions = '<a class="btn" href="../dashboard.php"><i class="fa-solid fa-arrow-left"></i> Captain dashboard</a>';
    ?>
    <section class="page-heading">
      <div>
        <p class="eyebrow">Captain settings</p>
        <h1>Admin Accounts</h1>
        <p>Create official accounts manually. There is no public self-registration for barangay officials.</p>
      </div>
      <div class="page-heading__actions"><?= $actions ?></div>
    </section>

    <?php if ($message !== ''): ?>
      <div class="flash flash--<?= settings_e($message_type) ?>" role="status">
        <i class="fa-solid <?= $message_type === 'danger' ? 'fa-circle-exclamation' : 'fa-circle-check' ?>"></i>
        <span><?= settings_e($message) ?></span>
      </div>
    <?php endif; ?>

    <section class="details-grid">
      <div class="panel">
        <div class="panel__header">
          <div>
            <h2>Official Accounts</h2>
            <p>Non-resident users with management access.</p>
          </div>
        </div>
        <?php if ($admins): ?>
          <div class="table-wrap">
            <table class="data-table">
              <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th></tr></thead>
              <tbody>
                <?php foreach ($admins as $admin): ?>
                  <tr>
                    <td><strong><?= settings_e($admin['fullname']) ?></strong></td>
                    <td><?= settings_e($admin['email']) ?></td>
                    <td><?= settings_e(ucfirst($admin['role'])) ?></td>
                    <td><span class="status-badge status-badge--<?= $admin['status'] === 'active' ? 'approved' : ($admin['status'] === 'suspended' ? 'danger' : 'pending') ?>"><?= settings_e(ucfirst($admin['status'])) ?></span></td>
                    <td><?= settings_e(date('M j, Y', strtotime($admin['created_at']))) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="empty-state"><i class="fa-solid fa-users-gear"></i><strong>No official accounts</strong><span>Create the first official account from the form.</span></div>
        <?php endif; ?>
      </div>

      <form class="form-panel" method="post">
        <input type="hidden" name="csrf_token" value="<?= settings_e($csrf) ?>">
        <h2>Create Official Account</h2>
        <div class="form-section">
          <div class="form-grid" style="grid-template-columns: 1fr;">
            <div class="form-field"><label for="fullname">Full name</label><input id="fullname" name="fullname" type="text" required></div>
            <div class="form-field"><label for="username">Username</label><input id="username" name="username" type="text"><small class="field-help">Defaults to email when left blank.</small></div>
            <div class="form-field"><label for="email">Email</label><input id="email" name="email" type="email" required></div>
            <div class="form-field">
              <label for="role">Role</label>
              <select id="role" name="role">
                <option value="secretary">Secretary</option>
                <option value="captain">Captain</option>
                <option value="treasurer">Treasurer</option>
                <option value="kagawad">Kagawad</option>
              </select>
            </div>
            <div class="form-field">
              <label for="status">Status</label>
              <select id="status" name="status">
                <option value="active">Active</option>
                <option value="pending">Pending</option>
                <option value="suspended">Suspended</option>
              </select>
            </div>
            <div class="form-field">
              <label for="password">Temporary password</label>
              <input id="password" name="password" type="password" required>
              <small class="field-help"><?= settings_e(BMS_PASSWORD_RULE_MESSAGE) ?></small>
            </div>
            <button class="btn btn--primary" type="submit"><i class="fa-solid fa-user-shield"></i> Create Account</button>
          </div>
        </div>
      </form>
    </section>
  </main>
</body>
</html>
