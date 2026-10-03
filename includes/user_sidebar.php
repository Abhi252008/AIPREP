<?php
/**
 * Shared sidebar for the logged-in student area. Include this inside
 * pages under /user/ AFTER includes/header.php, and set $activePage
 * beforehand to one of: 'dashboard', 'profile', 'notifications'.
 * Expects $pdo and a logged-in $_SESSION['user_id'] to already exist.
 */
$activePage = $activePage ?? '';

$unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$unreadStmt->execute([$_SESSION['user_id']]);
$unreadCount = (int) $unreadStmt->fetchColumn();

$sidebarUserStmt = $pdo->prepare("SELECT name, profile_picture FROM users WHERE id = ?");
$sidebarUserStmt->execute([$_SESSION['user_id']]);
$sidebarUser = $sidebarUserStmt->fetch();
?>
<div class="dashboard-sidebar glass">
  <div class="sidebar-user">
    <?= render_avatar($sidebarUser['profile_picture'] ?? null, $sidebarUser['name'] ?? '', '48px') ?>
    <div class="sidebar-user-name"><?= e($sidebarUser['name'] ?? '') ?></div>
  </div>

  <nav class="dashboard-nav">
    <a href="<?= BASE_URL ?>/user/dashboard.php" class="<?= $activePage === 'dashboard' ? 'active' : '' ?>">
      <i class="bi bi-grid-1x2"></i> Dashboard
    </a>
    <a href="<?= BASE_URL ?>/user/progress.php" class="<?= $activePage === 'progress' ? 'active' : '' ?>">
      <i class="bi bi-graph-up-arrow"></i> My Progress
    </a>
    <a href="<?= BASE_URL ?>/user/start-interview.php" class="<?= $activePage === 'start-interview' ? 'active' : '' ?>">
      <i class="bi bi-play-circle"></i> Start Interview
    </a>
    <a href="<?= BASE_URL ?>/user/profile.php" class="<?= $activePage === 'profile' ? 'active' : '' ?>">
      <i class="bi bi-person-circle"></i> My Profile
    </a>
    <a href="<?= BASE_URL ?>/user/notifications.php" class="<?= $activePage === 'notifications' ? 'active' : '' ?>">
      <i class="bi bi-bell"></i> Notifications
      <?php if ($unreadCount > 0): ?>
        <span class="nav-badge"><?= $unreadCount > 9 ? '9+' : $unreadCount ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= BASE_URL ?>/logout.php">
      <i class="bi bi-box-arrow-right"></i> Log Out
    </a>
  </nav>
</div>
