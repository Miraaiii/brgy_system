<?php
require_once __DIR__ . '/includes/admin_layout.php';

$user = adm_require_admin($conn, ['captain', 'secretary', 'treasurer', 'kagawad']);
$csrf = adm_action_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!adm_verify_action_token($_POST['csrf_token'] ?? '')) {
        adm_set_flash('danger', 'Your session expired. Please refresh and try again.');
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'approve_registration') {
            [$ok, $message] = adm_approve_resident_registration($conn, (int)($_POST['registration_id'] ?? 0), (int)$user['id']);
        } elseif ($action === 'reject_registration') {
            [$ok, $message] = adm_reject_resident_registration(
                $conn,
                (int)($_POST['registration_id'] ?? 0),
                (int)$user['id'],
                (string)($_POST['reason'] ?? '')
            );
        } elseif ($action === 'archive_resident') {
            [$ok, $message] = adm_archive_resident(
                $conn,
                (int)($_POST['resident_id'] ?? 0),
                (int)$user['id'],
                (string)($_POST['status'] ?? '')
            );
        } else {
            [$ok, $message] = [false, 'Unknown action.'];
        }
        adm_set_flash($ok ? 'success' : 'danger', $message);
    }

    $qs = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
    header('Location: residents.php' . $qs);
    exit();
}

$status_filters = ['all', 'active', 'pending', 'deceased', 'transferred'];
$filter = strtolower(trim((string)($_GET['filter'] ?? 'all')));
if (!in_array($filter, $status_filters, true)) {
    $filter = 'all';
}
$q = trim((string)($_GET['q'] ?? ''));
$purok = trim((string)($_GET['purok'] ?? ''));
$voter_only = isset($_GET['voter']) && $_GET['voter'] === '1';

$counts = array_fill_keys($status_filters, 0);
if (adm_table_exists($conn, 'residents')) {
    $counts['all'] = adm_scalar($conn, 'SELECT COUNT(*) FROM residents');
    foreach (['active', 'deceased', 'transferred'] as $status) {
        $counts[$status] = adm_scalar($conn, 'SELECT COUNT(*) FROM residents WHERE status = ?', 's', [$status]);
    }
}
if (adm_table_exists($conn, 'pending_resident_registrations')) {
    $counts['pending'] = adm_scalar($conn, "SELECT COUNT(*) FROM pending_resident_registrations WHERE status = 'pending'");
}

$puroks = [];
if (adm_table_exists($conn, 'households')) {
    $puroks = adm_fetch_all($conn, 'SELECT DISTINCT purok FROM households WHERE purok <> "" ORDER BY purok ASC LIMIT 100');
}

function secretary_resident_rows($conn, $filter, $q, $purok, $voter_only, $limit = 200) {
    if (!adm_table_exists($conn, 'residents')) {
        return [];
    }

    $where = [];
    $types = '';
    $params = [];

    if (in_array($filter, ['active', 'deceased', 'transferred'], true)) {
        $where[] = 'r.status = ?';
        $types .= 's';
        $params[] = $filter;
    }

    if ($q !== '') {
        $like = '%' . $q . '%';
        $where[] = "(r.last_name LIKE ? OR r.first_name LIKE ? OR r.email LIKE ? OR CONCAT(r.first_name, ' ', r.last_name) LIKE ?)";
        $types .= 'ssss';
        array_push($params, $like, $like, $like, $like);
    }

    if ($purok !== '') {
        $where[] = 'h.purok = ?';
        $types .= 's';
        $params[] = $purok;
    }

    if ($voter_only) {
        $where[] = 'r.is_voter = 1';
    }

    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $limit = max(1, min(500, (int)$limit));

    return adm_fetch_all(
        $conn,
        "SELECT r.*, h.house_number, h.street, h.purok, u.status AS account_status
         FROM residents r
         LEFT JOIN households h ON h.id = r.household_id
         LEFT JOIN users u ON u.id = r.user_id
         {$where_sql}
         ORDER BY r.last_name ASC, r.first_name ASC
         LIMIT {$limit}",
        $types,
        $params
    );
}

function secretary_pending_registration_rows($conn, $q, $limit = 100) {
    if (!adm_table_exists($conn, 'pending_resident_registrations')) {
        return [];
    }

    $where = ["status = 'pending'"];
    $types = '';
    $params = [];
    if ($q !== '') {
        $like = '%' . $q . '%';
        $where[] = "(last_name LIKE ? OR first_name LIKE ? OR email LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ?)";
        $types = 'ssss';
        $params = [$like, $like, $like, $like];
    }

    return adm_fetch_all(
        $conn,
        'SELECT *
         FROM pending_resident_registrations
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY created_at ASC
         LIMIT ' . max(1, min(300, (int)$limit)),
        $types,
        $params
    );
}

$resident_rows = $filter === 'pending' ? [] : secretary_resident_rows($conn, $filter, $q, $purok, $voter_only);
$pending_rows = in_array($filter, ['all', 'pending'], true) ? secretary_pending_registration_rows($conn, $q, 100) : [];

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=resident-masterlist-' . date('Ymd-His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Last Name', 'First Name', 'Email', 'Mobile', 'Purok', 'Age', 'Sex', 'Voter', 'Status']);
    foreach ($resident_rows as $resident) {
        fputcsv($out, [
            $resident['last_name'],
            $resident['first_name'],
            $resident['email'],
            $resident['contact_number'],
            $resident['purok'],
            adm_age($resident['birth_date']),
            adm_status_label($resident['sex']),
            ((int)$resident['is_voter'] === 1 ? 'Yes' : 'No'),
            adm_status_label($resident['status']),
        ]);
    }
    foreach ($pending_rows as $pending) {
        fputcsv($out, [
            $pending['last_name'],
            $pending['first_name'],
            $pending['email'],
            $pending['mobile_number'],
            $pending['purok_zone'],
            adm_age($pending['birth_date']),
            adm_status_label($pending['sex']),
            '',
            'Pending Verification',
        ]);
    }
    fclose($out);
    exit();
}

$export_query = $_GET;
$export_query['export'] = 'csv';
$actions = '<a class="btn btn--primary" href="resident-form.php"><i class="fa-solid fa-user-plus"></i> Add New Resident</a> ';
$actions .= '<a class="btn" href="residents.php?' . adm_e(http_build_query($export_query)) . '"><i class="fa-solid fa-file-csv"></i> Export CSV</a>';

adm_page_start('Resident Masterlist', 'residents', $user, 'residents-page');
adm_page_header('Resident records', 'Resident Masterlist', 'Maintain the official list of residents and verify new portal registrations.', $actions);
?>

<nav class="tabs" aria-label="Resident status filters">
  <?php foreach ($status_filters as $status): ?>
    <a class="tab-link <?= $filter === $status ? 'is-active' : '' ?>" href="residents.php?filter=<?= adm_e($status) ?>">
      <?= adm_e($status === 'all' ? 'All' : adm_status_label($status)) ?>
      <strong><?= adm_e($counts[$status] ?? 0) ?></strong>
    </a>
  <?php endforeach; ?>
</nav>

<form class="filter-panel" method="get">
  <input type="hidden" name="filter" value="<?= adm_e($filter) ?>">
  <div class="filter-grid">
    <div class="form-field">
      <label for="q">Search</label>
      <input id="q" name="q" type="search" value="<?= adm_e($q) ?>" placeholder="Name or email" data-table-search="#residentsTable">
    </div>
    <div class="form-field">
      <label for="purok">Purok</label>
      <select id="purok" name="purok">
        <option value="">All puroks</option>
        <?php foreach ($puroks as $row): ?>
          <option value="<?= adm_e($row['purok']) ?>" <?= $purok === $row['purok'] ? 'selected' : '' ?>><?= adm_e($row['purok']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <label class="check-field">
      <input type="checkbox" name="voter" value="1" <?= $voter_only ? 'checked' : '' ?>>
      <span>Show voters only</span>
    </label>
    <button class="btn btn--primary" type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
    <a class="btn" href="residents.php"><i class="fa-solid fa-rotate-left"></i> Reset</a>
  </div>
</form>

<?php if ($pending_rows): ?>
  <section class="panel" style="margin-bottom: 16px;">
    <div class="panel__header">
      <div>
        <h2>Pending Account Verifications</h2>
        <p>Review the uploaded ID before approving resident portal access.</p>
      </div>
    </div>
    <div class="panel__body">
      <div class="resident-card-list">
        <?php foreach ($pending_rows as $registration): ?>
          <?php
            $name = trim($registration['first_name'] . ' ' . ($registration['middle_name'] ? $registration['middle_name'] . ' ' : '') . $registration['last_name']);
            $id_href = '../' . ltrim(str_replace('\\', '/', (string)$registration['valid_id_path']), '/');
          ?>
          <article class="resident-card">
            <a class="id-preview" href="<?= adm_e($id_href) ?>" target="_blank" rel="noopener" title="View uploaded ID">
              <i class="fa-solid fa-id-card" aria-hidden="true"></i>
            </a>
            <div class="resident-card__main">
              <strong><?= adm_e($name) ?></strong>
              <small><?= adm_e($registration['email']) ?> - <?= adm_e($registration['mobile_number']) ?> - <?= adm_e(adm_date($registration['created_at'])) ?></small>
            </div>
            <div class="resident-card__actions">
              <form method="post" data-disable-on-submit>
                <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
                <input type="hidden" name="action" value="approve_registration">
                <input type="hidden" name="registration_id" value="<?= adm_e($registration['id']) ?>">
                <button class="btn btn--success btn--small" type="submit"><i class="fa-solid fa-check"></i> Approve</button>
              </form>
              <details class="inline-reject">
                <summary class="btn btn--danger btn--small"><i class="fa-solid fa-xmark"></i> Reject</summary>
                <form class="inline-reject__body" method="post" data-disable-on-submit>
                  <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
                  <input type="hidden" name="action" value="reject_registration">
                  <input type="hidden" name="registration_id" value="<?= adm_e($registration['id']) ?>">
                  <div class="form-field">
                    <label for="reject-<?= adm_e($registration['id']) ?>">Reason</label>
                    <input id="reject-<?= adm_e($registration['id']) ?>" name="reason" type="text" required>
                  </div>
                  <button class="btn btn--danger btn--small" type="submit">Send rejection</button>
                </form>
              </details>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
<?php endif; ?>

<section class="panel">
  <div class="panel__header">
    <div>
      <h2>Residents</h2>
      <p>Showing up to 200 matching residents. Use filters or export for a focused list.</p>
    </div>
  </div>

  <?php if ($resident_rows): ?>
    <div class="table-wrap">
      <table class="data-table" id="residentsTable">
        <thead>
          <tr>
            <th>Last Name</th>
            <th>First Name</th>
            <th>Purok</th>
            <th>Age</th>
            <th>Sex</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($resident_rows as $resident): ?>
            <tr>
              <td>
                <strong><?= adm_e($resident['last_name']) ?></strong>
                <small><?= adm_e($resident['email'] ?: 'No email') ?></small>
              </td>
              <td><?= adm_e($resident['first_name']) ?></td>
              <td>
                <?= adm_e($resident['purok'] ?: 'Not set') ?>
                <small><?= adm_e(trim(($resident['house_number'] ? $resident['house_number'] . ' ' : '') . (string)$resident['street'])) ?></small>
              </td>
              <td><?= adm_e(adm_age($resident['birth_date'])) ?></td>
              <td><?= adm_e(adm_status_label($resident['sex'])) ?></td>
              <td><span class="status-badge status-badge--<?= adm_e(adm_status_class($resident['status'])) ?>"><?= adm_e(adm_status_label($resident['status'])) ?></span></td>
              <td>
                <div class="table-actions">
                  <a class="btn btn--small" href="resident-form.php?id=<?= adm_e($resident['id']) ?>"><i class="fa-solid fa-pen"></i> Edit</a>
                  <?php if (!empty($resident['valid_id_path'])): ?>
                    <a class="btn btn--small" href="<?= adm_e('../' . ltrim(str_replace('\\', '/', (string)$resident['valid_id_path']), '/')) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-id-card"></i> ID</a>
                  <?php endif; ?>
                  <?php if ($resident['status'] === 'active'): ?>
                    <form method="post" data-confirm="Mark this resident as transferred?" data-disable-on-submit>
                      <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
                      <input type="hidden" name="action" value="archive_resident">
                      <input type="hidden" name="resident_id" value="<?= adm_e($resident['id']) ?>">
                      <input type="hidden" name="status" value="transferred">
                      <button class="btn btn--small" type="submit"><i class="fa-solid fa-box-archive"></i> Transfer</button>
                    </form>
                    <form method="post" data-confirm="Mark this resident as deceased?" data-disable-on-submit>
                      <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
                      <input type="hidden" name="action" value="archive_resident">
                      <input type="hidden" name="resident_id" value="<?= adm_e($resident['id']) ?>">
                      <input type="hidden" name="status" value="deceased">
                      <button class="btn btn--danger btn--small" type="submit"><i class="fa-solid fa-circle-minus"></i> Deceased</button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="empty-state">
      <i class="fa-solid fa-users"></i>
      <strong>No residents found</strong>
      <span>Adjust the filters or add a resident record manually.</span>
      <a class="btn btn--primary" href="resident-form.php"><i class="fa-solid fa-user-plus"></i> Add resident</a>
    </div>
  <?php endif; ?>
</section>

<?php adm_page_end(); ?>
