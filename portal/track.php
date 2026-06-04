<?php
require_once __DIR__ . '/includes/resident_portal.php';

rp_ensure_document_request_columns($conn);

$ctx = rp_get_resident_context($conn, true);
$has_request_tables = rp_table_exists($conn, 'document_types') && rp_table_exists($conn, 'document_requests');
$initial_search = trim((string)($_GET['ref'] ?? ''));
$requests = [];

if ($has_request_tables && $ctx['resident_id'] > 0) {
    $extra_select = rp_column_exists($conn, 'document_requests', 'extra_details')
        ? ', dr.extra_details'
        : ', NULL AS extra_details';
    $requests = rp_fetch_all(
        $conn,
        "SELECT dr.id, dr.reference_no, dr.purpose, dr.status, dr.created_at, dr.updated_at{$extra_select},
                dt.name AS document_name, dt.processing_days
         FROM document_requests dr
         INNER JOIN document_types dt ON dt.id = dr.doc_type_id
         WHERE dr.resident_id = ?
         ORDER BY dr.created_at DESC",
        'i',
        [(int)$ctx['resident_id']]
    );
}

$filter_groups = [
    'all' => ['label' => 'All', 'statuses' => []],
    'pending' => ['label' => 'Pending', 'statuses' => ['pending']],
    'processing' => ['label' => 'Processing', 'statuses' => ['processing', 'for_approval']],
    'ready' => ['label' => 'Ready', 'statuses' => ['approved']],
    'released' => ['label' => 'Released', 'statuses' => ['released']],
    'rejected' => ['label' => 'Rejected', 'statuses' => ['rejected']],
];

$counts = array_fill_keys(array_keys($filter_groups), 0);
$ready_alert_count = 0;
foreach ($requests as $request) {
    $status = strtolower((string)$request['status']);
    $counts['all']++;
    foreach ($filter_groups as $key => $group) {
        if ($key !== 'all' && in_array($status, $group['statuses'], true)) {
            $counts[$key]++;
        }
    }
    if (in_array($status, ['approved', 'released'], true)) {
        $ready_alert_count++;
    }
}

rp_page_start('Track My Requests', 'track', $ctx, 'track-page');
?>

<section class="portal-page-header">
  <div>
    <p class="page-kicker">My Requests</p>
    <h1>Track My Requests</h1>
    <p>Search by reference number, filter by status, and open any request to view its timeline.</p>
  </div>
  <a class="primary-action" href="request.php"><i class="fa-solid fa-file-circle-plus"></i> Request document</a>
</section>

<?php if (!$has_request_tables): ?>
  <div class="account-alert account-alert--danger" role="alert">
    <i class="fa-solid fa-triangle-exclamation"></i>
    <span>Document request tables are not installed yet. Please import the database schema first.</span>
  </div>
<?php endif; ?>

<?php if ($ready_alert_count > 0): ?>
  <div class="ready-alert" role="status">
    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
    <span><?= rp_e($ready_alert_count) ?> request<?= $ready_alert_count === 1 ? '' : 's' ?> approved or released. Open the timeline for pickup or download details.</span>
  </div>
<?php endif; ?>

<section class="track-toolbar">
  <label class="search-field" for="requestSearch">
    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
    <input id="requestSearch" type="search" placeholder="Search reference number, e.g. BR-2025-00042" value="<?= rp_e($initial_search) ?>" autocomplete="off">
  </label>

  <div class="status-tabs" role="tablist" aria-label="Filter requests by status">
    <?php foreach ($filter_groups as $key => $group): ?>
      <button class="status-tab <?= $key === 'all' ? 'is-active' : '' ?>" type="button" data-track-filter="<?= rp_e($key) ?>">
        <span><?= rp_e($group['label']) ?></span>
        <strong><?= rp_e($counts[$key] ?? 0) ?></strong>
      </button>
    <?php endforeach; ?>
  </div>
</section>

<?php if ($requests): ?>
  <section class="dashboard-panel track-panel">
    <div class="panel-header">
      <div>
        <h2>Request history</h2>
        <p id="trackResultLabel">Showing <?= rp_e(count($requests)) ?> request<?= count($requests) === 1 ? '' : 's' ?>, newest first.</p>
      </div>
    </div>

    <div class="table-wrap">
      <table class="resident-table track-table">
        <thead>
          <tr>
            <th>Reference No.</th>
            <th>Document Type</th>
            <th class="track-col-date">Date Submitted</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody id="trackRequestRows">
          <?php foreach ($requests as $request): ?>
            <?php
              $status = strtolower((string)$request['status']);
              $detail_url = 'request-detail.php?id=' . (int)$request['id'];
            ?>
            <tr data-track-row
                data-status="<?= rp_e($status) ?>"
                data-ref="<?= rp_e(strtolower($request['reference_no'])) ?>"
                data-href="<?= rp_e($detail_url) ?>">
              <td data-label="Reference No."><code class="ref-code"><?= rp_e($request['reference_no']) ?></code></td>
              <td data-label="Document Type">
                <strong><?= rp_e($request['document_name']) ?></strong>
                <span><?= rp_e($request['purpose']) ?></span>
              </td>
              <td class="track-col-date" data-label="Date Submitted"><?= rp_e(rp_date_long($request['created_at'])) ?></td>
              <td data-label="Status">
                <span class="status-badge status-badge--<?= rp_e(rp_status_class($status)) ?>"><?= rp_e(rp_status_label($status)) ?></span>
              </td>
              <td data-label="Action">
                <a class="detail-button" href="<?= rp_e($detail_url) ?>"><i class="fa-solid fa-timeline"></i> View details</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php else: ?>
  <section class="empty-state empty-state--large">
    <i class="fa-solid fa-folder-open" aria-hidden="true"></i>
    <strong>You have not submitted any requests yet.</strong>
    <span>Start a document request and your reference number will appear here.</span>
    <a class="primary-action" href="request.php"><i class="fa-solid fa-file-circle-plus"></i> Request a document</a>
  </section>
<?php endif; ?>

<section class="status-guide-grid" aria-label="Status guides">
  <div class="dashboard-panel">
    <div class="panel-header">
      <div>
        <h2>Document Request Status Flow</h2>
        <p>What each badge means and what the resident should do next.</p>
      </div>
    </div>
    <div class="flow-list">
      <?php foreach (rp_document_status_flow() as $status => $item): ?>
        <article class="flow-item">
          <span class="status-badge status-badge--<?= rp_e(rp_status_class($status)) ?>"><?= rp_e($item['label']) ?></span>
          <div>
            <strong><?= rp_e($item['meaning']) ?></strong>
            <small><?= rp_e($item['next']) ?></small>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="dashboard-panel">
    <div class="panel-header">
      <div>
        <h2>Blotter Case Status Flow</h2>
        <p>How complaint and mediation cases move through the barangay process.</p>
      </div>
    </div>
    <div class="flow-list">
      <?php foreach (rp_blotter_status_flow() as $status => $item): ?>
        <article class="flow-item">
          <span class="status-badge status-badge--<?= rp_e(rp_status_class($status)) ?>"><?= rp_e($item['label']) ?></span>
          <div>
            <strong><?= rp_e($item['meaning']) ?></strong>
            <small><?= rp_e($item['next']) ?></small>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php rp_page_end(); ?>
