<?php
/**
 * Shared admin sidebar — included on every admin page.
 * Set $adminActivePage before including this file.
 * Values: 'dashboard', 'users', 'categories', 'sessions', 'questions'
 */
$adminActivePage = $adminActivePage ?? '';
?>
<aside class="admin-sidebar">
  <!-- Brand -->
  <div class="admin-sidebar-brand">
    <div class="brand-icon"><i class="bi bi-shield-lock-fill"></i></div>
    <div>
      <span>AI Interview Prep</span>
      <small>Admin Panel</small>
    </div>
  </div>

  <nav class="admin-nav">
    <div class="admin-nav-section">Overview</div>
    <a href="<?= BASE_URL ?>/admin/dashboard.php"
       class="<?= $adminActivePage === 'dashboard' ? 'active' : '' ?>">
      <i class="bi bi-grid-1x2"></i> Dashboard
    </a>

    <div class="admin-nav-section">Manage</div>
    <a href="<?= BASE_URL ?>/admin/users.php"
       class="<?= $adminActivePage === 'users' ? 'active' : '' ?>">
      <i class="bi bi-people"></i> Users
    </a>
    <a href="<?= BASE_URL ?>/admin/categories.php"
       class="<?= $adminActivePage === 'categories' ? 'active' : '' ?>">
      <i class="bi bi-tags"></i> Categories
    </a>
    <a href="<?= BASE_URL ?>/admin/sessions.php"
       class="<?= $adminActivePage === 'sessions' ? 'active' : '' ?>">
      <i class="bi bi-card-list"></i> Sessions
    </a>
    <a href="<?= BASE_URL ?>/admin/questions.php"
       class="<?= $adminActivePage === 'questions' ? 'active' : '' ?>">
      <i class="bi bi-patch-question"></i> Question Bank
    </a>

    <div class="nav-logout" style="margin-top: auto; padding-top: 1rem;">
      <a href="<?= BASE_URL ?>/admin/logout.php" style="color: #dc2626;">
        <i class="bi bi-box-arrow-right"></i> Log Out
      </a>
    </div>
  </nav>
</aside>
