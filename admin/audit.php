<?php
require_once __DIR__ . '/includes/admin_layout.php';

$user = adm_require_captain($conn);
$q = trim((string)($_GET['q'] ?? ''));
$user_id = (int)($_GET['user_id'] ?? 0);
$action_filter = trim((string)($_GET['action'] ?? ''));
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));

$users = adm_table_exists($conn, 'users')
    ? adm_fetch_all($conn, "SELECT id, fullname, email, role FROM users WHERE role <> 'resident' ORDER BY fullname ASC")
    : [];
$actions = adm_table_exists($conn, 'audit_logs')
    ? adm_fetch_all($conn, 'SELECT DISTINCT action FROM audit_logs ORDER BY action ASC')
    : [];

$where = [];
$types = '';
$params = [];
if ($q !== '') {
    $like = '%' . $q . '%';
    $where[] = '(al.action LIKE ? OR al.table_name LIKE ? OR u.fullname LIKE ? OR u.email LIKE ?)';
    $types .= 'ssss';
    array_push($params, $like, $like, $like, $like);
}
if ($user_id > 0) {
    $where[] = 'al.user_id = ?';
    $types .= 'i';
    $params[] = $user_id;
}
if ($action_filter !== '') {
    $where[] = 'al.action = ?';
    $types .= 's';
    $params[] = $action_filter;
}
if ($from !== '') {
    $where[] = 'DATE(al.created_at) >= ?';
    $types .= 's';
    $params[] = $from;
}
if ($to !== '') {
    $where[] = 'DATE(al.created_at) <= ?';
    $types .= 's';
    $params[] = $to;
}
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$logs = adm_table_exists($conn, 'audit_logs')
    ? adm_fetch_all(
        $conn,
        "SELECT al.*, u.fullname, u.email, u.role
         FROM audit_logs al
         LEFT JOIN users u ON u.id = al.user_id
         {$where_sql}
         ORDER BY al.created_at DESC
         LIMIT 500",
        $types,
        $params
    )
    : [];

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=audit-trail-' . date('Ymd-His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Timestamp', 'User', 'Role', 'Action', 'Table', 'Record ID', 'IP Address']);
    foreach ($logs as $log) {
        fputcsv($out, [$log['created_at'], $log['fullname'] ?: 'System', $log['role'], $log['action'], $log['table_name'], $log['record_id'], $log['ip_address']]);
    }
    fclose($out);
    exit();
}

function audit_value_label($key) {
    $key = trim((string)$key);
    if ($key === '') {
        return 'Value';
    }

    return ucwords(str_replace(['_', '.'], ' ', $key));
}

function audit_value_text($value) {
    if (is_bool($value)) {
        return $value ? 'Yes' : 'No';
    }
    if ($value === null || $value === '') {
        return 'Not set';
    }
    if (is_array($value)) {
        if (!$value) {
            return 'None';
        }
        $parts = [];
        foreach ($value as $item) {
            $parts[] = audit_value_text($item);
        }
        return implode(', ', array_filter($parts, fn($part) => $part !== ''));
    }

    return (string)$value;
}

function audit_flatten_values($value, $prefix = '') {
    if (!is_array($value)) {
        return [$prefix ?: 'value' => audit_value_text($value)];
    }

    $rows = [];
    foreach ($value as $key => $item) {
        $path = $prefix !== '' ? $prefix . '.' . $key : (string)$key;
        if (is_array($item)) {
            $has_named_keys = $item && array_keys($item) !== range(0, count($item) - 1);
            if ($has_named_keys) {
                $rows += audit_flatten_values($item, $path);
                continue;
            }
        }
        $rows[$path] = audit_value_text($item);
    }

    return $rows;
}

function audit_values_html($value) {
    if ($value === null || $value === '') {
        return '<p class="audit-values__empty">No values recorded.</p>';
    }

    $decoded = json_decode((string)$value, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        return '<p class="audit-values__empty">' . adm_e((string)$value) . '</p>';
    }

    $rows = audit_flatten_values($decoded);
    if (!$rows) {
        return '<p class="audit-values__empty">No values recorded.</p>';
    }

    $html = '<dl class="audit-values">';
    foreach ($rows as $key => $item) {
        $html .= '<div><dt>' . adm_e(audit_value_label($key)) . '</dt><dd>' . adm_e($item) . '</dd></div>';
    }
    $html .= '</dl>';

    return $html;
}

$export_query = $_GET;
$export_query['export'] = 'csv';
$actions_html = '<a class="btn btn--primary" href="audit.php?' . adm_e(http_build_query($export_query)) . '"><i class="fa-solid fa-file-csv"></i> Export CSV</a>';

adm_page_start('Audit Trail', 'audit', $user, 'audit-page');
adm_page_header('Captain only', 'Audit Trail', 'Review significant system actions. Logs are append-only and cannot be deleted from this page.', $actions_html);
?>

<form class="filter-panel" method="get">
  <div class="filter-grid">
    <div class="form-field">
      <label for="q">Search</label>
      <input id="q" name="q" type="search" value="<?= adm_e($q) ?>" placeholder="User, action, or table" data-table-search="#auditTable">
    </div>
    <div class="form-field">
      <label for="user_id">User</label>
      <select id="user_id" name="user_id">
        <option value="0">All users</option>
        <?php foreach ($users as $admin_user): ?>
          <option value="<?= adm_e($admin_user['id']) ?>" <?= $user_id === (int)$admin_user['id'] ? 'selected' : '' ?>><?= adm_e($admin_user['fullname'] ?: $admin_user['email']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-field">
      <label for="action">Action</label>
      <select id="action" name="action">
        <option value="">All actions</option>
        <?php foreach ($actions as $action): ?>
          <option value="<?= adm_e($action['action']) ?>" <?= $action_filter === $action['action'] ? 'selected' : '' ?>><?= adm_e($action['action']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-field"><label for="from">From</label><input id="from" name="from" type="date" value="<?= adm_e($from) ?>"></div>
    <div class="form-field"><label for="to">To</label><input id="to" name="to" type="date" value="<?= adm_e($to) ?>"></div>
    <button class="btn btn--primary" type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
    <a class="btn" href="audit.php"><i class="fa-solid fa-rotate-left"></i> Reset</a>
  </div>
</form>

<section class="panel">
  <div class="panel__header">
    <div>
      <h2>Activity Log</h2>
      <p>Showing up to 500 matching records.</p>
    </div>
  </div>

  <?php if ($logs): ?>
    <div class="table-wrap">
      <table class="data-table" id="auditTable">
        <thead><tr><th>Timestamp</th><th>User</th><th>Action</th><th>Module</th><th>Record</th><th>IP Address</th><th>Details</th></tr></thead>
        <tbody>
          <?php foreach ($logs as $log): ?>
            <tr>
              <td><?= adm_e(adm_datetime($log['created_at'])) ?></td>
              <td><strong><?= adm_e($log['fullname'] ?: 'System') ?></strong><small><?= adm_e(adm_role_label($log['role'] ?? '')) ?></small></td>
              <td><span class="status-badge status-badge--neutral"><?= adm_e($log['action']) ?></span></td>
              <td><?= adm_e($log['table_name'] ?: 'System') ?></td>
              <td><?= adm_e($log['record_id'] ?: '-') ?></td>
              <td><?= adm_e($log['ip_address'] ?: '-') ?></td>
              <td>
                <details class="inline-reject">
                  <summary class="btn btn--small"><i class="fa-solid fa-code-compare"></i> View</summary>
                  <div class="json-diff">
                    <strong>Old values</strong>
                    <?= audit_values_html($log['old_values']) ?>
                    <strong>New values</strong>
                    <?= audit_values_html($log['new_values']) ?>
                  </div>
                </details>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="empty-state"><i class="fa-solid fa-shield-halved"></i><strong>No audit records found</strong><span>Try clearing filters or perform an auditable action.</span></div>
  <?php endif; ?>
</section>

<?php adm_page_end(); ?>
