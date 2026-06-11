<?php
include '../config/connection.php';
include '../includes/auth_check.php';

$tab = $_GET['tab'] ?? 'events';

// Get kagawad's committee
$committee = $_SESSION['committee'] ?? '';

// Filter status (optional)
$filter_status = $_GET['status'] ?? 'all';

// Build query
$sql = "
    SELECT
        id,
        title AS name,
        event_date AS date,
        location,
        status
    FROM events
    WHERE committee = ?
";

$params = [$committee];
$types = "s";

// Apply status filter
if ($filter_status !== 'all') {
    $sql .= " AND status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

$sql .= " ORDER BY event_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();

$result = $stmt->get_result();
$events = [];

while ($row = $result->fetch_assoc()) {
    $events[] = $row;
}

$stmt->close();

$editEvent = [];

if (
    ($_GET['tab'] ?? '') === 'event_form' &&
    ($_GET['mode'] ?? '') === 'edit' &&
    !empty($_GET['id'])
) {
    $eventId = (int) $_GET['id'];

    $stmt = $conn->prepare("
        SELECT
            id,
            title AS name,
            committee,
            event_date AS date,
            location,
            description,
            status
        FROM events
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $eventId);
    $stmt->execute();

    $result = $stmt->get_result();
    $editEvent = $result->fetch_assoc() ?: [];

    $stmt->close();
}

if (
  $_SERVER['REQUEST_METHOD'] === 'POST' &&
  ($_GET['action'] ?? '') === 'create_event'
) {
  $title       = trim($_POST['title']);
  $committee   = trim($_POST['committee']);
  $date        = $_POST['date'];
  $location    = trim($_POST['location']);
  $description = trim($_POST['description']);
  $status      = $_POST['status'];
  $start_time = $_POST['start_time'];
  $expected_attendees = (int) $_POST['expected_attendees'];

  $stmt = $conn->prepare("
    INSERT INTO events (
      title,
      committee,
      event_date,
      start_time,
      location,
      description,
      status,
      expected_attendees,
      created_by
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
  ");

  $stmt->bind_param(
    "sssssssii",
    $title,
    $committee,
    $date,
    $start_time,
    $location,
    $description,
    $status,
    $expected_attendees,
    $_SESSION['user_id']
  );

  $stmt->execute();
  $stmt->close();

  header("Location: events.php?tab=events");
  exit;
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_GET['action'] ?? '') === 'update_event'
) {
  $eventId     = (int) $_POST['event_id'];
  $title       = trim($_POST['title']);
  $date        = $_POST['date'];
  $location    = trim($_POST['location']);
  $description = trim($_POST['description']);
  $status      = $_POST['status'];
  $start_time = $_POST['start_time'];
  $expected_attendees = (int) $_POST['expected_attendees'];

  $stmt = $conn->prepare("
    UPDATE events
    SET
      title = ?,
      event_date = ?,
      start_time = ?,
      location = ?,
      description = ?,
      status = ?,
      expected_attendees = ?,
      updated_at = NOW()
    WHERE id = ?
  ");

  $stmt->bind_param(
    "ssssssii",
    $title,
    $date,
    $start_time,
    $location,
    $description,
    $status,
    $expected_attendees,
    $eventId
  );

  $stmt->execute();
  $stmt->close();

  header("Location: events.php?tab=events");
  exit;
}

require_once __DIR__ . '/includes/admin_layout.php';

$user = adm_require_admin($conn, ['captain', 'secretary', 'kagawad']);

adm_page_start('Events Calendar', 'events', $user, 'events-page');
adm_page_header('Sprint 3', 'Events Calendar', 'Calendar management is reserved for the next sprint because the current schema does not include an events table.');
?>

<?php if ($tab === 'events'): ?>
  <div class="events-main" id="events">
    <section class="panel">

      <!-- ── Header ── -->
      <div class="col-header">
        <h2><i class="fa-solid fa-calendar-days"></i> Events</h2>
        <div class="col-header-actions">
          <a href="?tab=events&export=csv<?= !empty($eventStatus) ? '&status='.urlencode($eventStatus) : '' ?>"
            class="col-btn col-btn-outline">
            <i class="fa-solid fa-file-csv"></i> Export CSV
          </a>
          <a href="?tab=event_form&mode=add" class="col-btn col-btn-primary">
            <i class="fa-solid fa-plus"></i> Add New Event
          </a>
        </div>
      </div>

      <!-- ── Status Filter Tabs ── -->
      <?php $eventStatus = $_GET['status'] ?? 'all'; ?>
      <div class="col-tabs">
        <?php
        $eventTabs = [
          'all'       => ['All',       'fa-list'],
          'upcoming'  => ['Upcoming',  'fa-clock'],
          'ongoing'   => ['Ongoing',   'fa-circle-play'],
          'completed' => ['Completed', 'fa-circle-check'],
          'cancelled' => ['Cancelled', 'fa-ban'],
        ];
        foreach ($eventTabs as $k => [$lbl, $ico]):
          $href = '?tab=events&status=' . $k;
        ?>
          <a href="<?= $href ?>"
            class="col-tab <?= $eventStatus === $k ? 'active' : '' ?>">
            <i class="fa-solid <?= $ico ?>"></i> <?= $lbl ?>
          </a>
        <?php endforeach; ?>
      </div>

      <!-- ── Stats Bar ── -->
      <div class="col-stats">
        <span class="col-count">
          Showing <strong><?= count($events) ?></strong>
          event<?= count($events) !== 1 ? 's' : '' ?>
          <?php if ($eventStatus !== 'all'): ?>
            &nbsp;·&nbsp; <strong><?= ucfirst($eventStatus) ?></strong>
          <?php endif; ?>
        </span>
      </div>

      <!-- ── Table ── -->
      <div class="col-table-wrap">
        <table>
          <thead>
            <tr>
              <th>Event Name</th>
              <th>Date</th>
              <th>Location</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($events)): ?>
              <tr><td colspan="5">
                <div class="col-empty">
                  <i class="fa-solid fa-calendar-days"></i>
                  <strong>No events found</strong>
                  <p style="margin-top:5px; font-size:13px;">Try a different status filter or add a new event.</p>
                </div>
              </td></tr>
            <?php else: ?>
              <?php
              $eventBadges = [
                'upcoming'  => 'col-badge-blue',
                'ongoing'   => 'col-badge-amber',
                'completed' => 'col-badge-green',
                'cancelled' => 'col-badge-red',
              ];
              foreach ($events as $event):
                $badgeCls = $eventBadges[$event['status']] ?? 'col-badge-neutral';
              ?>
              <tr>
                <td><strong><?= htmlspecialchars($event['name']) ?></strong></td>
                <td class="col-date">
                  <?= date('M d, Y', strtotime($event['date'])) ?><br>
                  <small><?= !empty($event['start_time']) ? date('h:i A', strtotime($event['start_time'])) : '—' ?></small>
                </td>
                <td style="color:var(--muted); font-size:13px;">
                  <i class="fa-solid fa-location-dot" style="margin-right:4px; color:var(--faint);"></i>
                  <?= htmlspecialchars($event['location']) ?>
                </td>
                <td>
                  <span class="col-badge <?= $badgeCls ?>">
                    <?= ucfirst($event['status']) ?>
                  </span>
                </td>
                <td>
                  <div class="col-actions">

                    <!-- Edit -->
                    <a href="?tab=event_form&mode=edit&id=<?= (int)$event['id'] ?>"
                      class="col-btn-icon" title="Edit Event">
                      <i class="fa-solid fa-pen-to-square"></i>
                    </a>

                    <!-- Upload Photos -->
                    <a href="?tab=event_photos&id=<?= (int)$event['id'] ?>"
                      class="col-btn-icon" title="Upload Photos">
                      <i class="fa-solid fa-images"></i>
                    </a>

                    <!-- Mark as Completed -->
                    <?php if (!in_array($event['status'], ['completed', 'cancelled'])): ?>
                      <form method="POST" style="display:inline;"
                            onsubmit="return confirm('Mark «<?= htmlspecialchars($event['name'], ENT_QUOTES) ?>» as Completed?')">
                        <input type="hidden" name="_method"  value="PATCH">
                        <input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">
                        <input type="hidden" name="status"   value="completed">
                        <button type="submit" class="col-btn-icon" title="Mark as Completed"
                                style="color:var(--success);">
                          <i class="fa-solid fa-circle-check"></i>
                        </button>
                      </form>

                      <!-- Cancel Event -->
                      <form method="POST" style="display:inline;"
                            onsubmit="return confirm('Cancel «<?= htmlspecialchars($event['name'], ENT_QUOTES) ?>»? This cannot be undone.')">
                        <input type="hidden" name="_method"  value="PATCH">
                        <input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">
                        <input type="hidden" name="status"   value="cancelled">
                        <button type="submit" class="col-btn-icon" title="Cancel Event"
                                style="color:var(--danger);">
                          <i class="fa-solid fa-ban"></i>
                        </button>
                      </form>
                    <?php endif; ?>

                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    </section>
  </div>
<?php elseif ($tab === 'event_form'): ?>
  <div class="events-main" id="event_form">
    <section class="pf-card">

      <!-- ── Header ── -->
      <?php $formMode = $_GET['mode'] ?? 'add'; ?>
      <div class="col-header">
        <h2>
          <i class="fa-solid <?= $formMode === 'edit' ? 'fa-pen-to-square' : 'fa-calendar-plus' ?>"></i>
          <?= $formMode === 'edit' ? 'Edit Event' : 'Add New Event' ?>
        </h2>
        <div class="col-header-actions">
          <a href="?tab=events" class="col-btn col-btn-outline">
            <i class="fa-solid fa-arrow-left"></i> Back to Events
          </a>
        </div>
      </div>

      <!-- ── Form ── -->
      <form method="POST" action="<?= $formMode === 'edit' ? '?tab=events&action=update_event' : '?tab=events&action=create_event' ?>">
        <input type="hidden" name="_method"  value="<?= $formMode === 'edit' ? 'PATCH' : 'POST' ?>">
        <?php if ($formMode === 'edit'): ?>
          <input type="hidden" name="event_id" value="<?= (int)($editEvent['id'] ?? 0) ?>">
        <?php endif; ?>

        <div class="col-filter-card">
          <div class="col-filter-row" style="flex-wrap:wrap; gap:18px;">

            <div class="col-fg" style="flex:1; min-width:260px;">
              <label>Event Title <span style="color:var(--danger);">*</span></label>
              <input type="text" name="title" class="col-input" required
                    placeholder="e.g. Barangay Cleanup Drive"
                    value="<?= htmlspecialchars($editEvent['name'] ?? '') ?>">
            </div>

            <div class="col-fg" style="flex:1; min-width:260px;">
              <label>Committee</label>
              <input type="text" name="committee" class="col-input" readonly
                    style="background:var(--surface-soft); color:var(--muted); cursor:not-allowed;"
                    value="<?= htmlspecialchars($editEvent['committee'] ?? $committee ?? '') ?>">
            </div>

            <div class="col-fg" style="min-width:180px;">
              <label>Event Date <span style="color:var(--danger);">*</span></label>
              <input type="date" name="date" class="col-input" required
                    value="<?= htmlspecialchars($editEvent['date'] ?? '') ?>">
            </div>

            <div class="col-fg" style="min-width:150px;">
              <label>Start Time</label>
              <input type="time" name="start_time" class="col-input"
                    value="<?= htmlspecialchars($editEvent['start_time'] ?? '') ?>">
            </div>

            <div class="col-fg" style="flex:1; min-width:260px;">
              <label>Location <span style="color:var(--danger);">*</span></label>
              <input type="text" name="location" class="col-input" required
                    placeholder="e.g. Barangay Hall, Covered Court"
                    value="<?= htmlspecialchars($editEvent['location'] ?? '') ?>">
            </div>

            <div class="col-fg" style="min-width:160px;">
              <label>Expected Attendees</label>
              <input type="number" name="expected_attendees" class="col-input" min="0"
                    placeholder="0"
                    value="<?= htmlspecialchars($editEvent['expected_attendees'] ?? '') ?>">
            </div>

            <div class="col-fg" style="flex:1 1 100%;">
              <label>Description</label>
              <textarea name="description" class="col-input" rows="4"
                        placeholder="Brief description of the event..."
                        style="resize:vertical;"><?= htmlspecialchars($editEvent['description'] ?? '') ?></textarea>
            </div>

            <div class="col-fg" style="min-width:200px;">
              <label>Status <span style="color:var(--danger);">*</span></label>
              <select name="status" class="col-input" required>
                <?php foreach(['upcoming' => 'Upcoming', 'ongoing' => 'Ongoing', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $v => $l): ?>
                  <option value="<?= $v ?>" <?= ($editEvent['status'] ?? 'upcoming') === $v ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
              </select>
            </div>

          </div>
        </div>

        <!-- ── Footer Actions ── -->
        <div style="display:flex; gap:10px; margin-top:24px;">
          <button type="submit" class="col-btn col-btn-primary">
            <i class="fa-solid fa-floppy-disk"></i> Save Event
          </button>
          <a href="?tab=events" class="col-btn col-btn-outline">Cancel</a>
        </div>

      </form>

    </section>
  </div>
<?php endif; ?>

<script src="assets/js/kagawad.js"></script>

<?php adm_page_end(); ?>
