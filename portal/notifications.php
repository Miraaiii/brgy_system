<?php
require_once __DIR__ . '/includes/resident_portal.php';

$ctx = rp_get_resident_context($conn, true);
$has_notifications = rp_table_exists($conn, 'notifications');
$errors = [];

if ($has_notifications && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!bms_verify_csrf_token($_POST['csrf_token'] ?? '', 'resident_notifications_csrf')) {
        $errors[] = 'Your session expired. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'mark_all_read') {
            $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
            if ($stmt) {
                $stmt->bind_param('i', $ctx['user_id']);
                $stmt->execute();
                $stmt->close();
                header('Location: notifications.php?read=1');
                exit();
            }
            $errors[] = 'Unable to mark notifications as read.';
        } elseif ($action === 'delete_notification') {
            $notification_id = (int)($_POST['notification_id'] ?? 0);
            $stmt = $conn->prepare('DELETE FROM notifications WHERE id = ? AND user_id = ?');
            if ($stmt) {
                $stmt->bind_param('ii', $notification_id, $ctx['user_id']);
                $stmt->execute();
                $stmt->close();
                header('Location: notifications.php?deleted=1');
                exit();
            }
            $errors[] = 'Unable to delete notification.';
        }
    }
}

$notifications = [];
$unread_count = 0;
if ($has_notifications) {
    $unread_count = rp_scalar($conn, 'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', 'i', [$ctx['user_id']]);
    $notifications = rp_fetch_all(
        $conn,
        'SELECT id, type, title, message, link, is_read, created_at
         FROM notifications
         WHERE user_id = ?
         ORDER BY created_at DESC',
        'i',
        [$ctx['user_id']]
    );
}

function notif_icon($type) {
    $type = strtolower((string)$type);
    if (strpos($type, 'request') !== false) {
        return 'fa-file-lines';
    }
    if (strpos($type, 'blotter') !== false) {
        return 'fa-scale-balanced';
    }
    if (strpos($type, 'announcement') !== false) {
        return 'fa-bullhorn';
    }
    return 'fa-bell';
}

$csrf_token = bms_csrf_token('resident_notifications_csrf');

if (isset($_GET['count'])) {
    header('Content-Type: application/json');
    echo json_encode(['unread' => $unread_count]);
    exit();
}

rp_page_start('My Notifications', '', $ctx, 'notifications-page');
?>

<section class="portal-page-header">
  <div>
    <p class="page-kicker">Resident inbox</p>
    <h1>My Notifications</h1>
    <p>All in-app notifications are listed newest first.</p>
  </div>
  <?php if ($notifications): ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= rp_e($csrf_token) ?>">
      <input type="hidden" name="action" value="mark_all_read">
      <button class="secondary-action" type="submit"><i class="fa-solid fa-check-double"></i> Mark all as read</button>
    </form>
  <?php endif; ?>
</section>

<?php if ($errors): ?>
  <div class="account-alert account-alert--danger" role="alert">
    <i class="fa-solid fa-circle-exclamation"></i>
    <span><?= rp_e(implode(' ', $errors)) ?></span>
  </div>
<?php elseif (isset($_GET['read'])): ?>
  <div class="account-alert" role="status">
    <i class="fa-solid fa-circle-check"></i>
    <span>All notifications marked as read.</span>
  </div>
<?php elseif (isset($_GET['deleted'])): ?>
  <div class="account-alert" role="status">
    <i class="fa-solid fa-circle-check"></i>
    <span>Notification deleted.</span>
  </div>
<?php endif; ?>

<?php if ($notifications): ?>
  <section class="notification-page-list">
    <?php foreach ($notifications as $notice): ?>
      <article class="notification-row <?= empty($notice['is_read']) ? 'is-unread' : 'is-read' ?>">
        <a class="notification-row__link" href="<?= rp_e($notice['link'] ?: '#') ?>">
          <span class="notification-row__icon"><i class="fa-solid <?= rp_e(notif_icon($notice['type'])) ?>" aria-hidden="true"></i></span>
          <span>
            <strong><?= rp_e($notice['title']) ?></strong>
            <small><?= rp_e($notice['message']) ?></small>
            <em><?= rp_e(rp_time_ago($notice['created_at'])) ?></em>
          </span>
        </a>
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= rp_e($csrf_token) ?>">
          <input type="hidden" name="action" value="delete_notification">
          <input type="hidden" name="notification_id" value="<?= rp_e($notice['id']) ?>">
          <button class="icon-button icon-button--danger" type="submit" aria-label="Delete notification"><i class="fa-solid fa-xmark"></i></button>
        </form>
      </article>
    <?php endforeach; ?>
  </section>
<?php else: ?>
  <section class="empty-state empty-state--large">
    <i class="fa-solid fa-bell" aria-hidden="true"></i>
    <strong>You have no notifications yet.</strong>
    <span>Request, announcement, and blotter updates will appear here.</span>
  </section>
<?php endif; ?>

<?php rp_page_end(); ?>
