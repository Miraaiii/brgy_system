<?php
require_once __DIR__ . '/includes/resident_portal.php';

rp_ensure_blotter_evidence_table($conn);

$ctx = rp_get_resident_context($conn, true);
$has_blotter_tables = rp_table_exists($conn, 'blotter_cases') && rp_table_exists($conn, 'blotter_parties');
$case_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

function mb_role_label($role) {
    return ucwords(str_replace('_', ' ', (string)$role));
}

function mb_role_class($role) {
    $role = strtolower((string)$role);
    if ($role === 'complainant') {
        return 'info';
    }
    if ($role === 'respondent') {
        return 'danger';
    }
    return 'neutral';
}

if ($case_id > 0 && $has_blotter_tables) {
    $case = rp_fetch_one(
        $conn,
        'SELECT bc.*, bp.party_type AS my_role
         FROM blotter_cases bc
         INNER JOIN blotter_parties bp ON bp.case_id = bc.id
         WHERE bc.id = ? AND bp.resident_id = ?
         LIMIT 1',
        'ii',
        [$case_id, (int)$ctx['resident_id']]
    );

    rp_page_start($case ? ('Case ' . $case['case_number']) : 'Case Not Found', 'cases', $ctx, 'blotter-detail-page');

    if (!$case): ?>
      <section class="empty-state empty-state--large">
        <i class="fa-solid fa-folder-open" aria-hidden="true"></i>
        <strong>Case not found</strong>
        <span>The case may not exist or may not be connected to your resident account.</span>
        <a class="primary-action" href="my-blotter.php"><i class="fa-solid fa-arrow-left"></i> Back to my cases</a>
      </section>
      <?php rp_page_end(); exit(); ?>
    <?php endif;

    $parties = rp_fetch_all(
        $conn,
        'SELECT bp.party_type, bp.non_resident_name, bp.address, bp.contact_number, bp.statement,
                r.first_name, r.middle_name, r.last_name
         FROM blotter_parties bp
         LEFT JOIN residents r ON r.id = bp.resident_id
         WHERE bp.case_id = ?
         ORDER BY FIELD(bp.party_type, "complainant", "respondent", "witness"), bp.id',
        'i',
        [$case_id]
    );
    $hearings = rp_table_exists($conn, 'blotter_hearings') ? rp_fetch_all(
        $conn,
        'SELECT bh.*, u.fullname AS presider_name
         FROM blotter_hearings bh
         LEFT JOIN users u ON u.id = bh.presided_by
         WHERE bh.case_id = ?
         ORDER BY bh.scheduled_at ASC',
        'i',
        [$case_id]
    ) : [];
    $evidence = rp_table_exists($conn, 'blotter_evidence') ? rp_fetch_all(
        $conn,
        'SELECT file_name, file_path, file_type, file_size, uploaded_at
         FROM blotter_evidence
         WHERE case_id = ?
         ORDER BY uploaded_at ASC',
        'i',
        [$case_id]
    ) : [];
    ?>

    <nav class="breadcrumb-line" aria-label="Breadcrumb">
      <a href="resident_dashboard.php">Home</a>
      <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
      <a href="my-blotter.php">My Cases</a>
      <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
      <span><?= rp_e($case['case_number']) ?></span>
    </nav>

    <section class="portal-page-header request-detail-header">
      <div>
        <p class="page-kicker">Blotter case</p>
        <h1><code class="ref-code ref-code--large"><?= rp_e($case['case_number']) ?></code></h1>
        <p><?= rp_e($case['incident_type']) ?> filed on <?= rp_e(rp_date_long($case['created_at'])) ?>.</p>
      </div>
      <div class="request-header-actions">
        <span class="role-badge role-badge--<?= rp_e(mb_role_class($case['my_role'])) ?>"><?= rp_e(mb_role_label($case['my_role'])) ?></span>
        <span class="status-badge status-badge--<?= rp_e(rp_status_class($case['status'])) ?>"><?= rp_e(rp_status_label($case['status'])) ?></span>
        <a class="secondary-action" href="my-blotter.php"><i class="fa-solid fa-arrow-left"></i> Back</a>
      </div>
    </section>

    <section class="detail-grid">
      <div class="dashboard-panel">
        <div class="panel-header">
          <div>
            <h2>Incident details</h2>
            <p>Full complaint narrative and location.</p>
          </div>
        </div>
        <dl class="summary-list">
          <div><dt>Incident date and time</dt><dd><?= rp_e(rp_datetime($case['incident_date'])) ?></dd></div>
          <div><dt>Location</dt><dd><?= rp_e($case['incident_place']) ?></dd></div>
          <div><dt>Narrative</dt><dd><?= nl2br(rp_e($case['narrative'])) ?></dd></div>
          <?php if (trim((string)$case['resolution']) !== ''): ?>
            <div><dt>Resolution notes</dt><dd><?= nl2br(rp_e($case['resolution'])) ?></dd></div>
          <?php endif; ?>
        </dl>
      </div>

      <div class="dashboard-panel">
        <div class="panel-header">
          <div>
            <h2>Parties involved</h2>
            <p>Residents and non-residents listed in this case.</p>
          </div>
        </div>
        <div class="party-list">
          <?php foreach ($parties as $party): ?>
            <?php
              $resident_name = trim(($party['first_name'] ?? '') . ' ' . ($party['middle_name'] ?? '') . ' ' . ($party['last_name'] ?? ''));
              $name = $resident_name !== '' ? $resident_name : ($party['non_resident_name'] ?: 'Unnamed party');
            ?>
            <article class="party-item">
              <span class="role-badge role-badge--<?= rp_e(mb_role_class($party['party_type'])) ?>"><?= rp_e(mb_role_label($party['party_type'])) ?></span>
              <div>
                <strong><?= rp_e($name) ?></strong>
                <?php if (!empty($party['address'])): ?><small><?= rp_e($party['address']) ?></small><?php endif; ?>
                <?php if (!empty($party['statement'])): ?><small><?= rp_e($party['statement']) ?></small><?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="detail-grid detail-grid--support">
      <div class="dashboard-panel">
        <div class="panel-header">
          <div>
            <h2>Hearing history</h2>
            <p>Scheduled and completed mediation hearings.</p>
          </div>
        </div>
        <?php if ($hearings): ?>
          <ol class="hearing-list">
            <?php foreach ($hearings as $hearing): ?>
              <li>
                <span class="timeline-icon"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i></span>
                <div>
                  <strong><?= rp_e(rp_datetime($hearing['scheduled_at'])) ?></strong>
                  <small><?= rp_e($hearing['location']) ?> | <?= rp_e(ucwords($hearing['status'])) ?></small>
                  <?php if (!empty($hearing['minutes'])): ?><p><?= nl2br(rp_e($hearing['minutes'])) ?></p><?php endif; ?>
                </div>
              </li>
            <?php endforeach; ?>
          </ol>
        <?php else: ?>
          <div class="empty-state empty-state--compact">
            <i class="fa-solid fa-calendar-days"></i>
            <strong>No hearing scheduled yet</strong>
            <span>The Secretary will update the schedule after review.</span>
          </div>
        <?php endif; ?>
      </div>

      <div class="dashboard-panel">
        <div class="panel-header">
          <div>
            <h2>Evidence files</h2>
            <p>Uploaded files attached to the case.</p>
          </div>
        </div>
        <?php if ($evidence): ?>
          <div class="attachment-list">
            <?php foreach ($evidence as $file): ?>
              <a class="attachment-item" href="../<?= rp_e(ltrim(str_replace('\\', '/', $file['file_path']), '/')) ?>" download>
                <span><i class="fa-solid fa-file-arrow-down" aria-hidden="true"></i></span>
                <strong><?= rp_e($file['file_name']) ?></strong>
                <small><?= rp_e(rp_file_size($file['file_size'])) ?> uploaded <?= rp_e(rp_date($file['uploaded_at'])) ?></small>
              </a>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="empty-state empty-state--compact">
            <i class="fa-solid fa-paperclip"></i>
            <strong>No evidence uploaded</strong>
            <span>Evidence files connected to this case will appear here.</span>
          </div>
        <?php endif; ?>

        <?php if (in_array(strtolower((string)$case['status']), ['settled', 'closed'], true)): ?>
          <div class="danger-zone">
            <a class="primary-action" href="request.php?doc=blotter-certificate&case=<?= rp_e(urlencode((string)$case['case_number'])) ?>"><i class="fa-solid fa-file-circle-plus"></i> Request blotter extract</a>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <?php rp_page_end(); exit();
}

$cases = [];
$next_hearing = null;
if ($has_blotter_tables && $ctx['resident_id'] > 0) {
    $next_hearing_select = rp_table_exists($conn, 'blotter_hearings')
        ? ', (SELECT MIN(bh.scheduled_at)
             FROM blotter_hearings bh
             WHERE bh.case_id = bc.id
               AND bh.status IN ("scheduled", "rescheduled")
               AND bh.scheduled_at >= NOW()) AS next_hearing_at'
        : ', NULL AS next_hearing_at';
    $cases = rp_fetch_all(
        $conn,
        "SELECT bc.id, bc.case_number, bc.incident_type, bc.status, bc.created_at, bc.updated_at,
                bp.party_type AS my_role{$next_hearing_select}
         FROM blotter_cases bc
         INNER JOIN blotter_parties bp ON bp.case_id = bc.id
         WHERE bp.resident_id = ?
         ORDER BY bc.updated_at DESC, bc.created_at DESC",
        'i',
        [(int)$ctx['resident_id']]
    );
    foreach ($cases as $case_row) {
        if (!empty($case_row['next_hearing_at'])) {
            $next_hearing = $case_row;
            break;
        }
    }
}

$filter_groups = [
    'all' => ['label' => 'All', 'statuses' => []],
    'open' => ['label' => 'Open', 'statuses' => ['open']],
    'under_mediation' => ['label' => 'Under Mediation', 'statuses' => ['under_mediation']],
    'settled' => ['label' => 'Settled', 'statuses' => ['settled']],
    'closed' => ['label' => 'Closed', 'statuses' => ['closed']],
];
$counts = array_fill_keys(array_keys($filter_groups), 0);
foreach ($cases as $case_row) {
    $status = strtolower((string)$case_row['status']);
    $counts['all']++;
    foreach ($filter_groups as $key => $group) {
        if ($key !== 'all' && in_array($status, $group['statuses'], true)) {
            $counts[$key]++;
        }
    }
}

rp_page_start('My Blotter Cases', 'cases', $ctx, 'my-blotter-page');
?>

<section class="portal-page-header">
  <div>
    <p class="page-kicker">Blotter / Complaints</p>
    <h1>My Blotter Cases</h1>
    <p>Track complaints where you are listed as complainant, respondent, or witness.</p>
  </div>
  <a class="primary-action" href="blotter.php"><i class="fa-solid fa-pen-to-square"></i> File a complaint</a>
</section>

<?php if ($next_hearing): ?>
  <div class="ready-alert hearing-card" role="status">
    <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>
    <span>Next hearing for <?= rp_e($next_hearing['case_number']) ?> is scheduled on <?= rp_e(rp_datetime($next_hearing['next_hearing_at'])) ?> at the barangay hall. Add it to your calendar.</span>
  </div>
<?php endif; ?>

<section class="track-toolbar">
  <div class="status-tabs status-tabs--five" role="tablist" aria-label="Filter cases by status">
    <?php foreach ($filter_groups as $key => $group): ?>
      <button class="status-tab <?= $key === 'all' ? 'is-active' : '' ?>" type="button" data-case-filter="<?= rp_e($key) ?>">
        <span><?= rp_e($group['label']) ?></span>
        <strong><?= rp_e($counts[$key] ?? 0) ?></strong>
      </button>
    <?php endforeach; ?>
  </div>
</section>

<?php if ($cases): ?>
  <section class="dashboard-panel">
    <div class="panel-header">
      <div>
        <h2>Case list</h2>
        <p id="caseResultLabel">Showing <?= rp_e(count($cases)) ?> case<?= count($cases) === 1 ? '' : 's' ?>.</p>
      </div>
    </div>
    <div class="table-wrap">
      <table class="resident-table track-table">
        <thead>
          <tr>
            <th>Case Number</th>
            <th>Incident Type</th>
            <th>My Role</th>
            <th class="track-col-date">Date Filed</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody id="caseRows">
          <?php foreach ($cases as $case_row): ?>
            <?php $href = 'my-blotter.php?id=' . (int)$case_row['id']; ?>
            <tr data-case-row data-status="<?= rp_e(strtolower((string)$case_row['status'])) ?>" data-href="<?= rp_e($href) ?>">
              <td data-label="Case Number"><code class="ref-code"><?= rp_e($case_row['case_number']) ?></code></td>
              <td data-label="Incident Type"><strong><?= rp_e($case_row['incident_type']) ?></strong></td>
              <td data-label="My Role"><span class="role-badge role-badge--<?= rp_e(mb_role_class($case_row['my_role'])) ?>"><?= rp_e(mb_role_label($case_row['my_role'])) ?></span></td>
              <td class="track-col-date" data-label="Date Filed"><?= rp_e(rp_date_long($case_row['created_at'])) ?></td>
              <td data-label="Status"><span class="status-badge status-badge--<?= rp_e(rp_status_class($case_row['status'])) ?>"><?= rp_e(rp_status_label($case_row['status'])) ?></span></td>
              <td data-label="Action"><a class="detail-button" href="<?= rp_e($href) ?>"><i class="fa-solid fa-eye"></i> View details</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php else: ?>
  <section class="empty-state empty-state--large">
    <i class="fa-solid fa-scale-balanced" aria-hidden="true"></i>
    <strong>You have no active blotter cases.</strong>
    <span>Complaints connected to your resident account will appear here.</span>
    <a class="primary-action" href="blotter.php"><i class="fa-solid fa-pen-to-square"></i> File a new complaint</a>
  </section>
<?php endif; ?>

<?php rp_page_end(); ?>
