<?php
/**
 * MODULE 9 — Admin Users Management
 * List all users, search by name/email, delete a user.
 */
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/admin_auth_check.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

// ── Handle delete ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_id'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Invalid CSRF token.');
    } else {
        $delId = (int) $_POST['delete_user_id'];
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$delId]);
        set_flash('success', 'User deleted successfully.');
    }
    redirect('/admin/users.php');
}

// ── Search ──
$search = trim($_GET['q'] ?? '');
$searchParam = '%' . $search . '%';

$users = $pdo->prepare(
    "SELECT u.id, u.name, u.email, u.created_at,
            COUNT(s.id) AS session_count
     FROM users u
     LEFT JOIN interview_sessions s ON s.user_id = u.id
     WHERE u.name LIKE ? OR u.email LIKE ?
     GROUP BY u.id
     ORDER BY u.created_at DESC"
);
$users->execute([$searchParam, $searchParam]);
$users = $users->fetchAll();

$totalUsers = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

$adminActivePage = 'users';
$pageTitle = 'Manage Users';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/flash.php';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>/admin/admin.css">

<div class="admin-shell">
  <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

  <main class="admin-content">

    <div class="admin-page-header">
      <div>
        <h1>Users</h1>
        <p><?= $totalUsers ?> registered user<?= $totalUsers !== 1 ? 's' : '' ?> on the platform.</p>
      </div>
    </div>

    <div class="admin-panel">
      <div class="admin-panel-header">
        <h2><i class="bi bi-people" style="margin-right:.4rem;color:var(--navy);"></i>All Users</h2>
        <!-- Search -->
        <form method="get" action="" class="admin-filter-bar">
          <input type="text"
                 name="q"
                 value="<?= e($search) ?>"
                 placeholder="Search name or email…"
                 class="admin-search-input">
          <button type="submit" class="btn-admin-sm primary">
            <i class="bi bi-search"></i> Search
          </button>
          <?php if ($search): ?>
            <a href="<?= BASE_URL ?>/admin/users.php" class="btn-admin-sm">Clear</a>
          <?php endif; ?>
        </form>
      </div>

      <?php if (empty($users)): ?>
        <div class="admin-empty">
          <i class="bi bi-person-x"></i>
          <p>No users found<?= $search ? ' for "' . e($search) . '"' : '' ?>.</p>
        </div>
      <?php else: ?>
        <table class="admin-table">
          <thead>
            <tr>
              <th>#</th>
              <th>User</th>
              <th>Sessions</th>
              <th>Joined</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $i => $u): ?>
            <tr>
              <td style="color:var(--mist);font-size:.8rem;"><?= $i + 1 ?></td>
              <td>
                <div class="user-cell">
                  <div class="mini-avatar"><?= e(strtoupper(substr($u['name'], 0, 1))) ?></div>
                  <div>
                    <div class="user-name"><?= e($u['name']) ?></div>
                    <div class="user-email"><?= e($u['email']) ?></div>
                  </div>
                </div>
              </td>
              <td>
                <span class="badge-pill badge-blue"><?= (int)$u['session_count'] ?> session<?= (int)$u['session_count'] !== 1 ? 's' : '' ?></span>
              </td>
              <td style="font-size:.82rem;color:var(--mist);"><?= e(date('M j, Y', strtotime($u['created_at']))) ?></td>
              <td>
                <form method="post" action="" class="delete-form"
                      onsubmit="return confirm('Delete user \'<?= e(addslashes($u['name'])) ?>\' and all their data? This cannot be undone.');">
                  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="delete_user_id" value="<?= (int)$u['id'] ?>">
                  <button type="submit" class="btn-admin-sm danger">
                    <i class="bi bi-trash"></i> Delete
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

  </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
