<?php
require_once __DIR__ . '/includes/admin_layout.php';

$user = adm_require_secretary($conn);
$csrf = adm_action_token();
$case_id = (int)($_GET['id'] ?? ($_POST['case_id'] ?? 0));

function secretary_blotter_sql_datetime($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    $sql_value = str_replace('T', ' ', $value);
    if (strlen($sql_value) === 16) {
        $sql_value .= ':00';
    }

    return strtotime($sql_value) ? $sql_value : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? 'update_case');

    if (!adm_verify_action_token($_POST['csrf_token'] ?? '')) {
        adm_set_flash('danger', 'Your session expired. Please refresh and try again.');
    } elseif ($case_id <= 0 || !adm_table_exists($conn, 'blotter_cases')) {
        adm_set_flash('danger', 'The selected blotter case could not be opened.');
    } elseif ($action === 'add_party') {
        if (!adm_table_exists($conn, 'blotter_parties')) {
            adm_set_flash('danger', 'Blotter parties table is not installed.');
        } else {
            $party_type = strtolower(trim((string)($_POST['party_type'] ?? '')));
            $resident_id = (int)($_POST['resident_id'] ?? 0);
            $non_resident_name = trim((string)($_POST['non_resident_name'] ?? ''));
            $address = trim((string)($_POST['address'] ?? ''));
            $contact_number = trim((string)($_POST['contact_number'] ?? ''));
            $statement = trim((string)($_POST['statement'] ?? ''));

            if (!in_array($party_type, ['complainant', 'respondent', 'witness'], true)) {
                adm_set_flash('danger', 'Invalid party type.');
            } elseif ($resident_id <= 0 && $non_resident_name === '') {
                adm_set_flash('danger', 'Select a resident or enter a party name.');
            } else {
                $resident_value = $resident_id > 0 ? $resident_id : null;
                $name_value = $non_resident_name !== '' ? $non_resident_name : null;
                $address_value = $address !== '' ? $address : null;
                $contact_value = $contact_number !== '' ? $contact_number : null;
                $statement_value = $statement !== '' ? $statement : null;
                $stmt = $conn->prepare(
                    'INSERT INTO blotter_parties (case_id, resident_id, party_type, non_resident_name, address, contact_number, statement)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                if ($stmt) {
                    $stmt->bind_param('iisssss', $case_id, $resident_value, $party_type, $name_value, $address_value, $contact_value, $statement_value);
                    $stmt->execute();
                    $party_id = (int)$stmt->insert_id;
                    $stmt->close();
                    adm_log_activity($conn, (int)$user['id'], 'Added blotter party', 'blotter_parties', $party_id, ['case_id' => $case_id, 'party_type' => $party_type]);
                    adm_set_flash('success', 'Party added to the case.');
                } else {
                    adm_set_flash('danger', 'Unable to add party.');
                }
            }
        }
    } elseif ($action === 'schedule_hearing') {
        if (!adm_table_exists($conn, 'blotter_hearings')) {
            adm_set_flash('danger', 'Hearing table is not installed.');
        } else {
            $scheduled_at = secretary_blotter_sql_datetime($_POST['scheduled_at'] ?? '');
            $location = trim((string)($_POST['location'] ?? 'Barangay Hall'));
            if ($scheduled_at === '' || $location === '') {
                adm_set_flash('danger', 'Schedule and location are required.');
            } else {
                $stmt = $conn->prepare(
                    'INSERT INTO blotter_hearings (case_id, scheduled_at, location, presided_by)
                     VALUES (?, ?, ?, ?)'
                );
                if ($stmt) {
                    $presider = (int)$user['id'];
                    $stmt->bind_param('issi', $case_id, $scheduled_at, $location, $presider);
                    $stmt->execute();
                    $hearing_id = (int)$stmt->insert_id;
                    $stmt->close();

                    $status_stmt = $conn->prepare("UPDATE blotter_cases SET status = 'under_mediation', updated_at = NOW() WHERE id = ? AND status = 'open'");
                    if ($status_stmt) {
                        $status_stmt->bind_param('i', $case_id);
                        $status_stmt->execute();
                        $status_stmt->close();
                    }

                    adm_log_activity($conn, (int)$user['id'], 'Scheduled blotter hearing', 'blotter_hearings', $hearing_id, ['case_id' => $case_id]);
                    adm_set_flash('success', 'Hearing scheduled.');
                } else {
                    adm_set_flash('danger', 'Unable to schedule hearing.');
                }
            }
        }
    } elseif ($action === 'update_hearing') {
        if (!adm_table_exists($conn, 'blotter_hearings')) {
            adm_set_flash('danger', 'Hearing table is not installed.');
        } else {
            $hearing_id = (int)($_POST['hearing_id'] ?? 0);
            $status = strtolower(trim((string)($_POST['hearing_status'] ?? '')));
            $minutes = trim((string)($_POST['minutes'] ?? ''));
            if ($hearing_id <= 0 || !in_array($status, ['scheduled', 'held', 'cancelled', 'rescheduled'], true)) {
                adm_set_flash('danger', 'Invalid hearing update.');
            } else {
                $minutes_value = $minutes !== '' ? $minutes : null;
                $presider = (int)$user['id'];
                $stmt = $conn->prepare(
                    'UPDATE blotter_hearings
                     SET status = ?, minutes = ?, presided_by = COALESCE(presided_by, ?), updated_at = NOW()
                     WHERE id = ? AND case_id = ?'
                );
                if ($stmt) {
                    $stmt->bind_param('ssiii', $status, $minutes_value, $presider, $hearing_id, $case_id);
                    $stmt->execute();
                    $updated = $stmt->affected_rows >= 0;
                    $stmt->close();
                    if ($updated) {
                        adm_log_activity($conn, (int)$user['id'], 'Updated blotter hearing', 'blotter_hearings', $hearing_id, ['status' => $status]);
                        adm_set_flash('success', 'Hearing updated.');
                    } else {
                        adm_set_flash('danger', 'Unable to update hearing.');
                    }
                } else {
                    adm_set_flash('danger', 'Unable to update hearing.');
                }
            }
        }
    } else {
        $status = strtolower(trim((string)($_POST['status'] ?? '')));
        $resolution = trim((string)($_POST['resolution'] ?? ''));
        if (!in_array($status, ['open', 'under_mediation', 'settled', 'escalated', 'closed'], true)) {
            adm_set_flash('danger', 'Invalid case status.');
        } else {
            $resolved_at = in_array($status, ['settled', 'closed'], true) ? date('Y-m-d H:i:s') : null;
            $stmt = $conn->prepare(
                'UPDATE blotter_cases
                 SET status = ?, resolution = ?, resolved_at = ?, updated_at = NOW()
                 WHERE id = ?'
            );
            if ($stmt) {
                $stmt->bind_param('sssi', $status, $resolution, $resolved_at, $case_id);
                $stmt->execute();
                $stmt->close();
                adm_log_activity($conn, (int)$user['id'], 'Updated blotter case', 'blotter_cases', $case_id, ['status' => $status]);
                adm_set_flash('success', 'Blotter case updated.');
            } else {
                adm_set_flash('danger', 'Unable to update case.');
            }
        }
    }

    header('Location: blotter-detail.php?id=' . $case_id);
    exit();
}

$case = null;
if ($case_id > 0 && adm_table_exists($conn, 'blotter_cases')) {
    $case = adm_fetch_one(
        $conn,
        'SELECT bc.*, recorder.fullname AS recorded_by_name
         FROM blotter_cases bc
         LEFT JOIN users recorder ON recorder.id = bc.recorded_by
         WHERE bc.id = ?
         LIMIT 1',
        'i',
        [$case_id]
    );
}

$parties = [];
if ($case && adm_table_exists($conn, 'blotter_parties')) {
    $parties = adm_fetch_all(
        $conn,
        'SELECT bp.*,
                TRIM(CONCAT(r.first_name, " ", COALESCE(NULLIF(r.middle_name, ""), ""), " ", r.last_name)) AS resident_name
         FROM blotter_parties bp
         LEFT JOIN residents r ON r.id = bp.resident_id
         WHERE bp.case_id = ?
         ORDER BY FIELD(bp.party_type, "complainant", "respondent", "witness"), bp.id ASC',
        'i',
        [$case_id]
    );
}

$hearings = [];
if ($case && adm_table_exists($conn, 'blotter_hearings')) {
    $hearings = adm_fetch_all(
        $conn,
        'SELECT bh.*, presider.fullname AS presided_by_name
         FROM blotter_hearings bh
         LEFT JOIN users presider ON presider.id = bh.presided_by
         WHERE bh.case_id = ?
         ORDER BY bh.scheduled_at DESC',
        'i',
        [$case_id]
    );
}

$residents = [];
if ($case && adm_table_exists($conn, 'residents')) {
    $household_join = adm_table_exists($conn, 'households')
        ? 'LEFT JOIN households h ON h.id = r.household_id'
        : '';
    $address_select = adm_table_exists($conn, 'households')
        ? ', h.house_number, h.street, h.purok'
        : ', NULL AS house_number, NULL AS street, NULL AS purok';
    $residents = adm_fetch_all(
        $conn,
        "SELECT r.id, r.first_name, r.middle_name, r.last_name, r.contact_number {$address_select}
         FROM residents r
         {$household_join}
         WHERE r.status = 'active'
         ORDER BY r.last_name ASC, r.first_name ASC
         LIMIT 300"
    );
}

$case_statuses = ['open', 'under_mediation', 'settled', 'escalated', 'closed'];
$party_types = ['complainant', 'respondent', 'witness'];
$hearing_statuses = ['scheduled', 'held', 'cancelled', 'rescheduled'];

adm_page_start('Blotter Detail', 'blotter', $user, 'blotter-detail-page');
?>

<?php if (!$case): ?>
  <?php adm_page_header('Blotter detail', 'Case not found', 'The selected blotter case could not be opened.', '<a class="btn" href="blotter.php"><i class="fa-solid fa-arrow-left"></i> Back to cases</a>'); ?>
  <section class="panel"><div class="empty-state"><i class="fa-solid fa-folder-open"></i><strong>No case found</strong><span>Open a valid blotter case.</span></div></section>
<?php else: ?>
  <?php
    $actions = '<a class="btn" href="blotter.php"><i class="fa-solid fa-arrow-left"></i> Back</a> ';
    $actions .= '<a class="btn" href="print-blotter.php?id=' . adm_e($case_id) . '" target="_blank" rel="noopener"><i class="fa-solid fa-print"></i> Print Extract</a> ';
    $actions .= '<a class="btn btn--primary" href="#schedule-hearing"><i class="fa-solid fa-calendar-plus"></i> Schedule Hearing</a>';
    adm_page_header('Case ' . $case['case_number'], $case['incident_type'], 'Filed ' . adm_date_long($case['created_at']) . ' by ' . ($case['recorded_by_name'] ?: 'System') . '.', $actions);
  ?>

  <section class="details-grid">
    <div>
      <section class="detail-panel">
        <div class="action-row" style="justify-content: space-between;">
          <h2>Case Header</h2>
          <span class="status-badge status-badge--<?= adm_e(adm_status_class($case['status'])) ?>"><?= adm_e(adm_status_label($case['status'])) ?></span>
        </div>
        <dl class="definition-list">
          <div><dt>Case No.</dt><dd><?= adm_e($case['case_number']) ?></dd></div>
          <div><dt>Incident Date</dt><dd><?= adm_e(adm_datetime($case['incident_date'])) ?></dd></div>
          <div><dt>Incident Place</dt><dd><?= adm_e($case['incident_place']) ?></dd></div>
          <div><dt>Recorded By</dt><dd><?= adm_e($case['recorded_by_name'] ?: 'System') ?></dd></div>
          <div class="form-field--full"><dt>Narrative</dt><dd><?= nl2br(adm_e($case['narrative'])) ?></dd></div>
          <?php if (!empty($case['resolution'])): ?>
            <div class="form-field--full"><dt>Resolution Notes</dt><dd><?= nl2br(adm_e($case['resolution'])) ?></dd></div>
          <?php endif; ?>
        </dl>
      </section>

      <section class="detail-panel">
        <div class="action-row" style="justify-content: space-between;">
          <h2>Parties</h2>
          <a class="btn btn--small" href="#add-party"><i class="fa-solid fa-user-plus"></i> Add Party</a>
        </div>
        <?php if ($parties): ?>
          <div class="table-wrap" style="margin-top: 12px;">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Type</th>
                  <th>Contact</th>
                  <th>Address</th>
                  <th>Statement</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($parties as $party): ?>
                  <tr>
                    <td><strong><?= adm_e($party['resident_name'] ?: $party['non_resident_name'] ?: 'Unnamed party') ?></strong></td>
                    <td><?= adm_e(adm_status_label($party['party_type'])) ?></td>
                    <td><?= adm_e($party['contact_number'] ?: 'Not set') ?></td>
                    <td><?= adm_e($party['address'] ?: 'Not set') ?></td>
                    <td><small><?= adm_e($party['statement'] ?: 'No statement recorded') ?></small></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="empty-state"><i class="fa-solid fa-users"></i><strong>No parties recorded</strong><span>Add complainants, respondents, or witnesses from the side form.</span></div>
        <?php endif; ?>
      </section>

      <section class="detail-panel">
        <h2>Hearing History</h2>
        <?php if ($hearings): ?>
          <div class="hearing-list">
            <?php foreach ($hearings as $hearing): ?>
              <article class="hearing-card">
                <div class="hearing-card__header">
                  <span>
                    <strong><?= adm_e(adm_datetime($hearing['scheduled_at'])) ?></strong>
                    <small><?= adm_e($hearing['location']) ?> - <?= adm_e($hearing['presided_by_name'] ?: 'No presider') ?></small>
                  </span>
                  <span class="status-badge status-badge--<?= adm_e(adm_status_class($hearing['status'])) ?>"><?= adm_e(adm_status_label($hearing['status'])) ?></span>
                </div>
                <?php if (!empty($hearing['minutes'])): ?>
                  <p class="hearing-card__minutes"><?= nl2br(adm_e($hearing['minutes'])) ?></p>
                <?php endif; ?>
                <form method="post" class="compact-form" data-disable-on-submit>
                  <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
                  <input type="hidden" name="case_id" value="<?= adm_e($case_id) ?>">
                  <input type="hidden" name="action" value="update_hearing">
                  <input type="hidden" name="hearing_id" value="<?= adm_e($hearing['id']) ?>">
                  <div class="form-grid" style="grid-template-columns: minmax(150px, .35fr) minmax(220px, 1fr) auto;">
                    <div class="form-field">
                      <label for="hearing_status_<?= adm_e($hearing['id']) ?>">Status</label>
                      <select id="hearing_status_<?= adm_e($hearing['id']) ?>" name="hearing_status" required>
                        <?php foreach ($hearing_statuses as $status): ?>
                          <option value="<?= adm_e($status) ?>" <?= $hearing['status'] === $status ? 'selected' : '' ?>><?= adm_e(adm_status_label($status)) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="form-field">
                      <label for="minutes_<?= adm_e($hearing['id']) ?>">Minutes / notes</label>
                      <textarea id="minutes_<?= adm_e($hearing['id']) ?>" name="minutes" rows="2"><?= adm_e($hearing['minutes']) ?></textarea>
                    </div>
                    <div class="form-field">
                      <label aria-hidden="true">&nbsp;</label>
                      <button class="btn btn--small" type="submit"><i class="fa-solid fa-floppy-disk"></i> Update</button>
                    </div>
                  </div>
                </form>
              </article>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="empty-state"><i class="fa-solid fa-calendar-check"></i><strong>No hearings scheduled</strong><span>Schedule a hearing when mediation is needed.</span></div>
        <?php endif; ?>
      </section>
    </div>

    <aside class="form-panel">
      <h2>Secretary Actions</h2>
      <form method="post" class="form-section" data-disable-on-submit>
        <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
        <input type="hidden" name="case_id" value="<?= adm_e($case_id) ?>">
        <input type="hidden" name="action" value="update_case">
        <div class="form-grid" style="grid-template-columns: 1fr;">
          <div class="form-field">
            <label for="status">Case status</label>
            <select id="status" name="status" required>
              <?php foreach ($case_statuses as $status): ?>
                <option value="<?= adm_e($status) ?>" <?= $case['status'] === $status ? 'selected' : '' ?>><?= adm_e(adm_status_label($status)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-field">
            <label for="resolution">Resolution / notes</label>
            <textarea id="resolution" name="resolution"><?= adm_e($case['resolution']) ?></textarea>
          </div>
          <button class="btn btn--primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save Update</button>
        </div>
      </form>

      <form id="add-party" method="post" class="form-section" data-disable-on-submit>
        <h2>Add Party</h2>
        <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
        <input type="hidden" name="case_id" value="<?= adm_e($case_id) ?>">
        <input type="hidden" name="action" value="add_party">
        <div class="form-grid" style="grid-template-columns: 1fr;">
          <div class="form-field">
            <label for="party_type">Party type</label>
            <select id="party_type" name="party_type" required>
              <?php foreach ($party_types as $type): ?>
                <option value="<?= adm_e($type) ?>"><?= adm_e(adm_status_label($type)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-field">
            <label for="resident_id">Resident record</label>
            <select id="resident_id" name="resident_id">
              <option value="">Non-resident or manual entry</option>
              <?php foreach ($residents as $resident): ?>
                <?php
                  $resident_name = trim($resident['first_name'] . ' ' . ($resident['middle_name'] ? $resident['middle_name'] . ' ' : '') . $resident['last_name']);
                  $resident_address = trim(implode(', ', array_filter([$resident['house_number'], $resident['street'], $resident['purok']])));
                ?>
                <option value="<?= adm_e($resident['id']) ?>"><?= adm_e($resident_name . ($resident_address ? ' - ' . $resident_address : '')) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-field">
            <label for="non_resident_name">Manual name</label>
            <input id="non_resident_name" name="non_resident_name" type="text" placeholder="Use when no resident record is selected">
          </div>
          <div class="form-field">
            <label for="contact_number">Contact number</label>
            <input id="contact_number" name="contact_number" type="text">
          </div>
          <div class="form-field">
            <label for="address">Address</label>
            <input id="address" name="address" type="text">
          </div>
          <div class="form-field">
            <label for="statement">Statement</label>
            <textarea id="statement" name="statement"></textarea>
          </div>
          <button class="btn" type="submit"><i class="fa-solid fa-user-plus"></i> Add Party</button>
        </div>
      </form>

      <form id="schedule-hearing" method="post" class="form-section" data-disable-on-submit>
        <h2>Schedule Hearing</h2>
        <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
        <input type="hidden" name="case_id" value="<?= adm_e($case_id) ?>">
        <input type="hidden" name="action" value="schedule_hearing">
        <div class="form-grid" style="grid-template-columns: 1fr;">
          <div class="form-field">
            <label for="scheduled_at">Schedule</label>
            <input id="scheduled_at" name="scheduled_at" type="datetime-local" required>
          </div>
          <div class="form-field">
            <label for="location">Location</label>
            <input id="location" name="location" type="text" value="Barangay Hall" required>
          </div>
          <button class="btn btn--primary" type="submit"><i class="fa-solid fa-calendar-plus"></i> Schedule Hearing</button>
        </div>
      </form>
    </aside>
  </section>
<?php endif; ?>

<?php adm_page_end(); ?>
