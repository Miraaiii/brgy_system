<?php
require_once __DIR__ . '/includes/admin_layout.php';

$user = adm_require_secretary($conn);
$csrf = adm_action_token();

function secretary_generate_case_number($conn) {
    for ($i = 0; $i < 12; $i++) {
        $candidate = 'BL-' . date('Y') . '-' . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        $exists = adm_table_exists($conn, 'blotter_cases')
            ? adm_scalar($conn, 'SELECT COUNT(*) FROM blotter_cases WHERE case_number = ?', 's', [$candidate])
            : 0;
        if ($exists === 0) {
            return $candidate;
        }
    }
    return 'BL-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(2)));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!adm_verify_action_token($_POST['csrf_token'] ?? '')) {
        adm_set_flash('danger', 'Your session expired. Please refresh and try again.');
    } elseif (!adm_table_exists($conn, 'blotter_cases')) {
        adm_set_flash('danger', 'Blotter table is not installed.');
    } else {
        $incident_type = trim((string)($_POST['incident_type'] ?? ''));
        $incident_place = trim((string)($_POST['incident_place'] ?? ''));
        $incident_date = trim((string)($_POST['incident_date'] ?? ''));
        $narrative = trim((string)($_POST['narrative'] ?? ''));
        $complainant_name = trim((string)($_POST['complainant_name'] ?? ''));
        $complainant_address = trim((string)($_POST['complainant_address'] ?? ''));
        $respondent_name = trim((string)($_POST['respondent_name'] ?? ''));
        $respondent_address = trim((string)($_POST['respondent_address'] ?? ''));
        if ($incident_type === '' || $incident_place === '' || $incident_date === '' || $narrative === '') {
            adm_set_flash('danger', 'Incident type, place, date, and narrative are required.');
        } else {
            $incident_date_sql = str_replace('T', ' ', $incident_date);
            if (strlen($incident_date_sql) === 16) {
                $incident_date_sql .= ':00';
            }
            $case_number = secretary_generate_case_number($conn);
            $stmt = $conn->prepare(
                "INSERT INTO blotter_cases (case_number, incident_date, incident_type, incident_place, narrative, recorded_by)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            if ($stmt) {
                $recorded_by = (int)$user['id'];
                $stmt->bind_param('sssssi', $case_number, $incident_date_sql, $incident_type, $incident_place, $narrative, $recorded_by);
                $stmt->execute();
                $case_id = (int)$stmt->insert_id;
                $stmt->close();

                if ($case_id > 0 && adm_table_exists($conn, 'blotter_parties')) {
                    $party_stmt = $conn->prepare(
                        'INSERT INTO blotter_parties (case_id, party_type, non_resident_name, address)
                         VALUES (?, ?, ?, ?)'
                    );
                    if ($party_stmt) {
                        foreach ([
                            ['complainant', $complainant_name, $complainant_address],
                            ['respondent', $respondent_name, $respondent_address],
                        ] as $party) {
                            if ($party[1] !== '') {
                                $party_stmt->bind_param('isss', $case_id, $party[0], $party[1], $party[2]);
                                $party_stmt->execute();
                            }
                        }
                        $party_stmt->close();
                    }
                }

                adm_log_activity($conn, (int)$user['id'], 'Logged blotter case', 'blotter_cases', $case_id, ['case_number' => $case_number]);
                adm_set_flash('success', 'Blotter case logged.');
                header('Location: blotter-detail.php?id=' . $case_id);
                exit();
            }
            adm_set_flash('danger', 'Unable to log blotter case.');
        }
    }
    header('Location: blotter.php');
    exit();
}

$filter = strtolower(trim((string)($_GET['filter'] ?? 'all')));
$allowed = ['all', 'open', 'under_mediation', 'settled', 'escalated', 'closed'];
if (!in_array($filter, $allowed, true)) {
    $filter = 'all';
}
$q = trim((string)($_GET['q'] ?? ''));

$cases = [];
if (adm_table_exists($conn, 'blotter_cases')) {
    $where = [];
    $types = '';
    $params = [];
    if ($filter !== 'all') {
        $where[] = 'bc.status = ?';
        $types .= 's';
        $params[] = $filter;
    }
    if ($q !== '') {
        $like = '%' . $q . '%';
        $where[] = '(bc.case_number LIKE ? OR bc.incident_type LIKE ? OR bc.incident_place LIKE ?)';
        $types .= 'sss';
        array_push($params, $like, $like, $like);
    }
    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $cases = adm_fetch_all(
        $conn,
        "SELECT bc.*, recorder.fullname AS recorded_by_name,
                (SELECT COUNT(*) FROM blotter_parties bp WHERE bp.case_id = bc.id) AS party_count,
                (SELECT GROUP_CONCAT(COALESCE(CONCAT(r.first_name, ' ', r.last_name), bp.non_resident_name) ORDER BY bp.party_type SEPARATOR '; ')
                 FROM blotter_parties bp
                 LEFT JOIN residents r ON r.id = bp.resident_id
                 WHERE bp.case_id = bc.id
                 LIMIT 1) AS party_summary
         FROM blotter_cases bc
         LEFT JOIN users recorder ON recorder.id = bc.recorded_by
         {$where_sql}
         ORDER BY bc.created_at DESC
         LIMIT 200",
        $types,
        $params
    );
}

adm_page_start('Blotter Cases', 'blotter', $user, 'blotter-page');
adm_page_header('Barangay records', 'Blotter Case Management', 'Log, search, and monitor barangay incident records.');
?>

<section class="details-grid">
  <div>
    <form class="filter-panel" method="get">
      <div class="filter-grid" style="grid-template-columns: minmax(220px, 1fr) minmax(160px, .4fr) auto auto;">
        <div class="form-field">
          <label for="q">Search</label>
          <input id="q" name="q" type="search" value="<?= adm_e($q) ?>" placeholder="Case no., type, place" data-table-search="#blotterTable">
        </div>
        <div class="form-field">
          <label for="filter">Status</label>
          <select id="filter" name="filter">
            <?php foreach ($allowed as $status): ?>
              <option value="<?= adm_e($status) ?>" <?= $filter === $status ? 'selected' : '' ?>><?= adm_e($status === 'all' ? 'All' : adm_status_label($status)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn--primary" type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
        <a class="btn" href="blotter.php"><i class="fa-solid fa-rotate-left"></i> Reset</a>
      </div>
    </form>

    <section class="panel">
      <div class="panel__header">
        <div>
          <h2>Cases</h2>
          <p>Open and mediation cases stay visible in the dashboard attention count.</p>
        </div>
      </div>
      <?php if ($cases): ?>
        <div class="table-wrap">
          <table class="data-table" id="blotterTable">
            <thead>
              <tr>
                <th>Case No.</th>
                <th>Incident Type</th>
            <th>Date Filed</th>
            <th>Parties</th>
            <th>Status</th>
            <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($cases as $case): ?>
                <tr>
                  <td><strong><?= adm_e($case['case_number']) ?></strong></td>
                  <td><?= adm_e($case['incident_type']) ?></td>
                  <td><?= adm_e(adm_date($case['created_at'])) ?></td>
                  <td>
                    <strong><?= adm_e((int)$case['party_count']) ?> recorded</strong>
                    <small><?= adm_e($case['party_summary'] ?: 'No parties listed') ?></small>
                  </td>
                  <td><span class="status-badge status-badge--<?= adm_e(adm_status_class($case['status'])) ?>"><?= adm_e(adm_status_label($case['status'])) ?></span></td>
                  <td>
                    <div class="table-actions">
                      <a class="btn btn--small" href="blotter-detail.php?id=<?= adm_e($case['id']) ?>"><i class="fa-solid fa-eye"></i> View</a>
                      <a class="btn btn--small" href="hearings.php?case_id=<?= adm_e($case['id']) ?>"><i class="fa-solid fa-calendar-plus"></i> Hearing</a>
                      <a class="btn btn--primary btn--small" href="print-blotter.php?id=<?= adm_e($case['id']) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-print"></i> Print</a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="empty-state">
          <i class="fa-solid fa-scale-balanced"></i>
          <strong>No blotter cases found</strong>
          <span>Use the form to log the first case.</span>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <aside class="form-panel">
    <h2>Log Case</h2>
    <form method="post" class="form-section" data-disable-on-submit>
      <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
      <div class="form-grid" style="grid-template-columns: 1fr;">
        <div class="form-field">
          <label for="incident_type">Incident type</label>
          <input id="incident_type" name="incident_type" type="text" required>
        </div>
        <div class="form-field">
          <label for="incident_date">Incident date and time</label>
          <input id="incident_date" name="incident_date" type="datetime-local" required>
        </div>
        <div class="form-field">
          <label for="incident_place">Incident place</label>
          <input id="incident_place" name="incident_place" type="text" required>
        </div>
        <div class="form-field">
          <label for="narrative">Narrative</label>
          <textarea id="narrative" name="narrative" required></textarea>
        </div>
        <div class="form-field">
          <label for="complainant_name">Complainant name</label>
          <input id="complainant_name" name="complainant_name" type="text">
        </div>
        <div class="form-field">
          <label for="complainant_address">Complainant address</label>
          <input id="complainant_address" name="complainant_address" type="text">
        </div>
        <div class="form-field">
          <label for="respondent_name">Respondent name</label>
          <input id="respondent_name" name="respondent_name" type="text">
        </div>
        <div class="form-field">
          <label for="respondent_address">Respondent address</label>
          <input id="respondent_address" name="respondent_address" type="text">
        </div>
        <button class="btn btn--primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Log Case</button>
      </div>
    </form>
  </aside>
</section>

<?php adm_page_end(); ?>
