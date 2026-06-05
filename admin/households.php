<?php
require_once __DIR__ . '/includes/admin_layout.php';

$user = adm_require_secretary($conn);
$csrf = adm_action_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!adm_verify_action_token($_POST['csrf_token'] ?? '')) {
        adm_set_flash('danger', 'Your session expired. Please refresh and try again.');
    } else {
        $house_number = trim((string)($_POST['house_number'] ?? ''));
        $street = trim((string)($_POST['street'] ?? ''));
        $purok = trim((string)($_POST['purok'] ?? ''));
        if ($street === '' || $purok === '') {
            adm_set_flash('danger', 'Street and purok are required.');
        } else {
            $id = adm_find_or_create_household($conn, $house_number, $street, $purok);
            if ($id) {
                adm_log_activity($conn, (int)$user['id'], 'Created household', 'households', $id);
                adm_set_flash('success', 'Household saved.');
            } else {
                adm_set_flash('danger', 'Unable to save household.');
            }
        }
    }
    header('Location: households.php');
    exit();
}

$households = [];
if (adm_table_exists($conn, 'households')) {
    $households = adm_fetch_all(
        $conn,
        'SELECT h.*, COUNT(r.id) AS resident_count,
                CONCAT(head.first_name, " ", head.last_name) AS head_name
         FROM households h
         LEFT JOIN residents r ON r.household_id = h.id
         LEFT JOIN residents head ON head.id = h.head_resident_id
         GROUP BY h.id
         ORDER BY h.purok ASC, h.street ASC, h.house_number ASC
         LIMIT 300'
    );
}

adm_page_start('Households', 'households', $user, 'households-page');
adm_page_header('Resident records', 'Households', 'Address units used to group residents by home, street, and purok.');
?>

<section class="details-grid">
  <div class="panel">
    <div class="panel__header">
      <div>
        <h2>Household List</h2>
        <p>Showing up to 300 household records.</p>
      </div>
    </div>
    <?php if ($households): ?>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>House No.</th>
              <th>Street</th>
              <th>Purok</th>
              <th>Head</th>
              <th>Residents</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($households as $household): ?>
              <tr>
                <td><?= adm_e($household['house_number'] ?: 'Not set') ?></td>
                <td><?= adm_e($household['street']) ?></td>
                <td><?= adm_e($household['purok']) ?></td>
                <td><?= adm_e(trim((string)$household['head_name']) ?: 'Not assigned') ?></td>
                <td><strong><?= adm_e($household['resident_count']) ?></strong></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="empty-state">
        <i class="fa-solid fa-house-chimney-window"></i>
        <strong>No households found</strong>
        <span>Create a household or add a resident with address details.</span>
      </div>
    <?php endif; ?>
  </div>

  <aside class="form-panel">
    <h2>Add Household</h2>
    <form method="post" class="form-section" data-disable-on-submit>
      <input type="hidden" name="csrf_token" value="<?= adm_e($csrf) ?>">
      <div class="form-grid" style="grid-template-columns: 1fr;">
        <div class="form-field">
          <label for="house_number">House no.</label>
          <input id="house_number" name="house_number" type="text">
        </div>
        <div class="form-field">
          <label for="street">Street</label>
          <input id="street" name="street" type="text" required>
        </div>
        <div class="form-field">
          <label for="purok">Purok</label>
          <input id="purok" name="purok" type="text" required>
        </div>
        <button class="btn btn--primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save Household</button>
      </div>
    </form>
  </aside>
</section>

<?php adm_page_end(); ?>
