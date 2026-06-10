<?php
require_once __DIR__ . '/includes/admin_layout.php';

$user = adm_require_admin($conn, ['captain', 'secretary', 'kagawad']);

adm_page_start('Events Calendar', 'events', $user, 'events-page');
adm_page_header('Sprint 3', 'Events Calendar', 'Calendar management is reserved for the next sprint because the current schema does not include an events table.');
?>

<section class="panel">
  <div class="empty-state">
    <i class="fa-solid fa-calendar-days"></i>
    <strong>Events calendar scaffold is ready</strong>
    <span>Add an events table or migration, then this route can be wired to event CRUD and monthly calendar views.</span>
  </div>
</section>

<?php adm_page_end(); ?>
