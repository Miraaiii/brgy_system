<?php
require_once __DIR__ . '/includes/admin_layout.php';

$user = adm_require_secretary($conn);
$csrf = adm_action_token();
$case_id = (int)($_GET['case_id'] ?? ($_POST['case_id'] ?? 0));

function secretary_hearing_sql_datetime($value) {
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
    $action = (string)($_POST['action'] ?? 'schedule_hearing');

    if (!adm_verify_action_token($_POST['csrf_token'] ?? '')) {
        adm_set_flash('danger', 'Your session expired. Please refresh and try again.');
    } elseif (!adm_table_exists($conn, 'blotter_hearings')) {
        adm_set_flash('danger', 'Hearing table is not installed.');
    } elseif ($action === 'update_hearing') {
        $hearing_id = (int)($_POST['hearing_id'] ?? 0);
        $post_case_id = (int)($_POST['case_id'] ?? 0);
        $status = strtolower(trim((string)($_POST['hearing_status'] ?? '')));
        $minutes = trim((string)($_POST['minutes'] ?? ''));
        if ($hearing_id <= 0 || $post_case_id <= 0 || !in_array($status, ['scheduled', 'held', 'cancelled', 'rescheduled'], true)) {
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
                $stmt->bind_param('ssiii', $status, $minutes_value, $presider, $hearing_id, $post_case_id);
                $stmt->execute();
                $stmt->close();
                adm_log_activity($conn, (int)$user['id'], 'Updated blotter hearing', 'blotter_hearings', $hearing_id, ['status' => $status]);
                adm_set_flash('success', 'Hearing updated.');
            } else {
                adm_set_flash('danger', 'Unable to update hearing.');
            }
        }
    } else {
        $post_case_id = (int)($_POST['case_id'] ?? 0);
        $scheduled_at = secretary_hearing_sql_datetime($_POST['scheduled_at'] ?? '');
        $location = trim((string)($_POST['location'] ?? 'Barangay Hall'));
        if ($post_case_id <= 0 || $scheduled_at === '' || $location === '') {
            adm_set_flash('danger', 'Case, schedule, and location are required.');
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO blotter_hearings (case_id, scheduled_at, location, presided_by)
                 VALUES (?, ?, ?, ?)'
            );
            if ($stmt) {
                $presider = (int)$user['id'];
                $stmt->bind_param('issi', $post_case_id, $scheduled_at, $location, $presider);
                $stmt->execute();
                $hearing_id = (int)$stmt->insert_id;
                $stmt->close();

                $status_stmt = $conn->prepare("UPDATE blotter_cases SET status = 'under_mediation', updated_at = NOW() WHERE id = ? AND status = 'open'");
                if ($status_stmt) {
                    $status_stmt->bind_param('i', $post_case_id);
                    $status_stmt->execute();
                    $status_stmt->close();
                }

                adm_log_activity($conn, (int)$user['id'], 'Scheduled blotter hearing', 'blotter_hearings', $hearing_id, ['case_id' => $post_case_id]);
                adm_set_flash('success', 'Hearing scheduled.');
            } else {
                adm_set_flash('danger', 'Unable to schedule hearing.');
            }
        }
    }

    header('Location: hearings.php' . ($case_id ? '?case_id=' . $case_id : ''));
    exit();
}

$cases = adm_table_exists($conn, 'blotter_cases')
    ? adm_fetch_all($conn, "SELECT id, case_number, incident_type FROM blotter_cases WHERE status IN ('open', 'under_mediation', 'escalated') ORDER BY created_at DESC LIMIT 200")
    : [];

$hearings = [];
if (adm_table_exists($conn, 'blotter_hearings')) {
    $where = $case_id > 0 ? 'WHERE bh.case_id = ?' : '';
    $hearings = adm_fetch_all(
        $conn,
        "SELECT bh.*, bc.case_number, bc.incident_type, presider.fullname AS presided_by_name
         FROM blotter_hearings bh
         INNER JOIN blotter_cases bc ON bc.id = bh.case_id
         LEFT JOIN users presider ON presider.id = bh.presided_by
         {$where}
         ORDER BY bh.scheduled_at DESC
         LIMIT 200",
        $case_id > 0 ? 'i' : '',
        $case_id > 0 ? [$case_id] : []
    );
}

$hearing_statuses = ['scheduled', 'held', 'cancelled', 'rescheduled'];

adm_page_start('Hearing Schedule', 'hearings', $user, 'hearings-page');
adm_page_header('Blotter records', 'Hearing Schedule', 'Schedule mediation hearings and record outcomes.');
?>

<section class="details-grid">
  <div class="panel">
    <div class="panel__header">
      <div>
        <h2>Hearings</h2>
        <p><?= $case_id ? 'Filtered to selected case.' : 'All recent scheduled hearings.' ?></p>
      </div>
      <?php if ($case_id): ?><a class="btn btn--small" href="hearings.php">Show all</a><?php endif; ?>
    </div>
    <?php if ($hearings): ?>
      <div class="hearing-list" style="padding: 0 18px 18px;">
        <?php foreach ($hearings as $hearing): ?>
          <article class="hearing-card">
            <div class="hearing-card__header">
              <span>
                <strong><?= adm_e(adm_datetime($hearing['scheduled_at'])) ?></strong>
                <small>
                  <a href="blotter-detail.php?id=<?= adm_e($hearing['case_id']) ?>"><?= adm_e($hearing['case_number']) ?></a>
                  - <?= adm_e($hearing['incident_type']) ?> - <?= adm_e($hearing['location']) ?>
                </small>
                <small>Presider: <?= adm_e($hearing['presided_by_name'] ?: 'Not assigned') ?></small>
              </span>
              <span class="status-badge status-badge--<?= adm_e(adm_status_class($hearing['status'])) ?>"><?= adm_e(adm_status_label($hearing['status'])) ?></span>
            </div>
            <?php if (!empty($hearing['minutes'])): ?>
              <p class="hearing-card__minutes"><?= nl2br(adm_e($hearing['minutes'])) ?></p>
            <?php endif; ?>
            <form method="post" class="compact-form" data-disable-on-submit>
              <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
              <input type="hidden" name="action" value="update_hearing">
              <input type="hidden" name="case_id" value="<?= adm_e($hearing['case_id']) ?>">
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
      <div class="empty-state">
        <i class="fa-solid fa-calendar-check"></i>
        <strong>No hearings scheduled</strong>
        <span>Use the schedule form to add the first hearing.</span>
      </div>
    <?php endif; ?>
  </div>

  <aside class="form-panel">
    <h2>Schedule Hearing</h2>
    <form method="post" class="form-section" data-disable-on-submit>
      <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
      <input type="hidden" name="action" value="schedule_hearing">
      <div class="form-grid" style="grid-template-columns: 1fr;">
        <div class="form-field">
          <label for="case_id">Blotter case</label>
          <select id="case_id" name="case_id" required>
            <option value="">Select case</option>
            <?php foreach ($cases as $case): ?>
              <option value="<?= adm_e($case['id']) ?>" <?= $case_id === (int)$case['id'] ? 'selected' : '' ?>><?= adm_e($case['case_number'] . ' - ' . $case['incident_type']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-field">
          <label for="scheduled_at">Schedule</label>
          <input id="scheduled_at" name="scheduled_at" type="datetime-local" required>
        </div>
        <div class="form-field">
          <label for="location">Location</label>
          <input id="location" name="location" type="text" value="Barangay Hall" required>
        </div>
        <button class="btn btn--primary" type="submit"><i class="fa-solid fa-calendar-plus"></i> Schedule</button>
      </div>
    </form>
  </aside>
</section>

<?php adm_page_end(); ?>
