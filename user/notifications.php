<?php
/**
 * Module 3 — User Dashboard: Notifications
 * Lists every notification for the user, with per-item and
 * mark-all-as-read actions.
 */
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Your session expired. Please try again.');
        redirect('/user/notifications.php');
    }

    if (($_POST['action'] ?? '') === 'mark_all_read') {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$userId]);
    }

    if (($_POST['action'] ?? '') === 'mark_read' && !empty($_POST['notification_id'])) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([(int) $_POST['notification_id'], $userId]);
    }

    redirect('/user/notifications.php');
}

$notifStmt = $pdo->prepare(
    "SELECT id, message, is_read, created_at FROM notifications
     WHERE user_id = ? ORDER BY created_at DESC"
);
$notifStmt->execute([$userId]);
$notifications = $notifStmt->fetchAll();

$activePage = 'notifications';
$pageTitle = "Notifications";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/flash.php';
?>

<section class="dashboard-shell">
  <?php require_once __DIR__ . '/../includes/user_sidebar.php'; ?>

  <div class="dashboard-content">
    <div class="dashboard-header" data-reveal>
      <div>
        <span class="section-eyebrow">Account</span>
        <h2 class="section-title" style="margin-bottom:0;">Notifications</h2>
      </div>
      <?php if (!empty($notifications)): ?>
        <form method="post" action="<?= BASE_URL ?>/user/notifications.php">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="mark_all_read">
          <button type="submit" class="btn-outline-glass">Mark all as read</button>
        </form>
      <?php endif; ?>
    </div>

    <div class="dashboard-panel glass" data-reveal>
      <?php if (empty($notifications)): ?>
        <p class="empty-state">You don't have any notifications yet.</p>
      <?php else: ?>
        <ul class="notif-list">
          <?php foreach ($notifications as $notif): ?>
            <li class="notif-item <?= !$notif['is_read'] ? 'unread' : '' ?>">
              <span class="notif-dot"></span>
              <div style="flex:1;">
                <div class="notif-message"><?= e($notif['message']) ?></div>
                <div class="notif-time"><?= e(date('M j, Y g:i A', strtotime($notif['created_at']))) ?></div>
              </div>
              <?php if (!$notif['is_read']): ?>
                <form method="post" action="<?= BASE_URL ?>/user/notifications.php">
                  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="mark_read">
                  <input type="hidden" name="notification_id" value="<?= (int) $notif['id'] ?>">
                  <button type="submit" class="notif-mark-read">Mark read</button>
                </form>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
