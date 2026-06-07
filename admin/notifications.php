<?php
require_once __DIR__ . '/includes/admin_layout.php';

$user = adm_require_admin($conn);
$csrf = adm_action_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!adm_verify_action_token($_POST['csrf_token'] ?? '')) {
        adm_set_flash('danger', 'Your session expired. Please refresh and try again.');
    } elseif (adm_table_exists($conn, 'notifications')) {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'mark_all_read') {
            $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
            if ($stmt) {
                $user_id = (int)$user['id'];
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $stmt->close();
                adm_set_flash('success', 'Notifications marked as read.');
            }
        }
    }

    header('Location: notifications.php');
    exit();
}

$notifications = adm_table_exists($conn, 'notifications')
    ? adm_fetch_all(
        $conn,
        'SELECT * FROM notifications WHERE user_id = ? ORDER BY is_read ASC, created_at DESC LIMIT 200',
        'i',
        [(int)$user['id']]
    )
    : [];

$actions = '<form method="post" data-disable-on-submit><input type="hidden" name="csrf_token" value="' . adm_e($csrf) . '"><input type="hidden" name="action" value="mark_all_read"><button class="btn btn--primary" type="submit"><i class="fa-solid fa-check-double"></i> Mark all read</button></form>';

adm_page_start('Notifications', 'notifications', $user, 'notifications-page');
adm_page_header('Account', 'Notifications', 'Recent updates routed to your official account.', $actions);
?>

<section class="panel">
  <div class="panel__header">
    <div><h2>Notification Center</h2><p>Showing up to 200 recent notifications.</p></div>
  </div>
  <div class="panel__body">
    <?php if ($notifications): ?>
      <div class="activity-list">
        <?php foreach ($notifications as $notification): ?>
          <a class="activity-item <?= (int)$notification['is_read'] === 0 ? 'is-unread' : '' ?>" href="<?= adm_e(adm_normalize_admin_link($notification['link'] ?: '#')) ?>">
            <span class="stat-card__icon"><i class="fa-solid <?= (int)$notification['is_read'] === 0 ? 'fa-bell' : 'fa-envelope-open' ?>"></i></span>
            <span class="activity-item__body">
              <strong><?= adm_e($notification['title']) ?></strong>
              <small><?= adm_e($notification['message']) ?></small>
            </span>
            <span class="status-badge status-badge--<?= (int)$notification['is_read'] === 0 ? 'pending' : 'neutral' ?>"><?= adm_e(adm_relative_time($notification['created_at'])) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty-state"><i class="fa-solid fa-bell"></i><strong>No notifications</strong><span>Approval and account updates will appear here.</span></div>
    <?php endif; ?>
  </div>
</section>

<?php adm_page_end(); ?>
