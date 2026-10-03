<?php
/**
 * MODULE 9 — Admin Dashboard
 * Platform overview: user count, session stats, avg score, recent activity.
 */
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/admin_auth_check.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

// ── Stats ──
$totalUsers     = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$mockSessions   = (int) $pdo->query("SELECT COUNT(*) FROM interview_sessions")->fetchColumn();
$mockCompleted  = (int) $pdo->query("SELECT COUNT(*) FROM interview_sessions WHERE status = 'completed'")->fetchColumn();
$mockInProg     = (int) $pdo->query("SELECT COUNT(*) FROM interview_sessions WHERE status = 'in_progress'")->fetchColumn();

try {
    $liveSessions  = (int) $pdo->query("SELECT COUNT(*) FROM live_interview_sessions")->fetchColumn();
    $liveCompleted = (int) $pdo->query("SELECT COUNT(*) FROM live_interview_sessions WHERE status = 'completed'")->fetchColumn();
    $liveInProg    = (int) $pdo->query("SELECT COUNT(*) FROM live_interview_sessions WHERE status = 'active'")->fetchColumn();
} catch (\Throwable $e) {
    $liveSessions = $liveCompleted = $liveInProg = 0;
}

$totalSessions  = $mockSessions + $liveSessions;
$completedCount = $mockCompleted + $liveCompleted;
$inProgCount    = $mockInProg + $liveInProg;

$avgRaw  = $pdo->query("SELECT AVG(total_score) FROM interview_sessions WHERE status = 'completed'")->fetchColumn();
$avgScore = $avgRaw !== null ? round((float)$avgRaw, 1) : '—';

// ── Recent 10 sessions (Mock + Live AI) ──
try {
    $recentSessions = $pdo->query(
        "(SELECT 'mock' AS session_type, s.id, s.status, s.total_score, NULL AS evaluation_json,
                 s.started_at, s.completed_at, u.name AS user_name, c.name AS category_name
          FROM interview_sessions s
          JOIN users u ON u.id = s.user_id
          JOIN categories c ON c.id = s.category_id)
         UNION ALL
         (SELECT 'live' AS session_type, s.id,
                 CASE WHEN s.status = 'active' THEN 'in_progress' ELSE s.status END AS status,
                 NULL AS total_score, s.evaluation_json,
                 s.started_at, s.completed_at, u.name AS user_name,
                 COALESCE(NULLIF(s.role_name,''), s.category, 'Live AI Session') AS category_name
          FROM live_interview_sessions s
          JOIN users u ON u.id = s.user_id)
         ORDER BY started_at DESC LIMIT 10"
    )->fetchAll();
} catch (\Throwable $e) {
    $recentSessions = $pdo->query(
        "SELECT 'mock' AS session_type, s.id, s.status, s.total_score, NULL AS evaluation_json,
                s.started_at, s.completed_at, u.name AS user_name, c.name AS category_name
         FROM interview_sessions s
         JOIN users u ON u.id = s.user_id
         JOIN categories c ON c.id = s.category_id
         ORDER BY s.started_at DESC LIMIT 10"
    )->fetchAll();
}

// ── Recent 5 users ──
$recentUsers = $pdo->query(
    "SELECT id, name, email, created_at FROM users ORDER BY created_at DESC LIMIT 5"
)->fetchAll();

// ── Sessions today ──
try {
    $todayMock = (int) $pdo->query("SELECT COUNT(*) FROM interview_sessions WHERE DATE(started_at) = CURDATE()")->fetchColumn();
    $todayLive = (int) $pdo->query("SELECT COUNT(*) FROM live_interview_sessions WHERE DATE(started_at) = CURDATE()")->fetchColumn();
    $todayCount = $todayMock + $todayLive;
} catch (\Throwable $e) {
    $todayCount = (int) $pdo->query("SELECT COUNT(*) FROM interview_sessions WHERE DATE(started_at) = CURDATE()")->fetchColumn();
}

$adminActivePage = 'dashboard';
$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/flash.php';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>/admin/admin.css">

<div class="admin-shell">
  <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

  <main class="admin-content">

    <!-- Page Header -->
    <div class="admin-page-header">
      <div>
        <h1>Dashboard</h1>
        <p>Welcome back, <strong><?= e($_SESSION['admin_name']) ?></strong> — here's what's happening on your platform.</p>
      </div>
      <a href="<?= BASE_URL ?>/admin/users.php" class="btn-admin-sm primary">
        <i class="bi bi-people"></i> View All Users
      </a>
    </div>

    <!-- Stat Cards -->
    <div class="admin-stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));">
      <div class="admin-stat-card">
        <div class="admin-stat-icon stat-icon-blue"><i class="bi bi-people-fill"></i></div>
        <div class="admin-stat-info">
          <div class="stat-value"><?= $totalUsers ?></div>
          <div class="stat-label">Registered Users</div>
        </div>
      </div>
      <div class="admin-stat-card">
        <div class="admin-stat-icon stat-icon-green"><i class="bi bi-check-circle-fill"></i></div>
        <div class="admin-stat-info">
          <div class="stat-value"><?= $completedCount ?></div>
          <div class="stat-label">Total Completed</div>
        </div>
      </div>
      <div class="admin-stat-card">
        <div class="admin-stat-icon" style="background:rgba(14,165,233,0.12);color:#0284c7;"><i class="bi bi-camera-video-fill"></i></div>
        <div class="admin-stat-info">
          <div class="stat-value"><?= $liveSessions ?></div>
          <div class="stat-label">Live AI Sessions <small style="color:var(--mist);font-size:.72rem;">(<?= $liveCompleted ?> done)</small></div>
        </div>
      </div>
      <div class="admin-stat-card">
        <div class="admin-stat-icon stat-icon-purple"><i class="bi bi-star-fill"></i></div>
        <div class="admin-stat-info">
          <div class="stat-value"><?= $avgScore ?></div>
          <div class="stat-label">Avg Mock Score / 10</div>
        </div>
      </div>
    </div>

    <!-- Two columns: recent sessions + recent users -->
    <div style="display:grid; grid-template-columns: 1.5fr 1fr; gap: 1.5rem; align-items: start;">

      <!-- Recent Sessions -->
      <div class="admin-panel">
        <div class="admin-panel-header">
          <h2><i class="bi bi-card-list" style="margin-right:.4rem;color:var(--navy);"></i>Recent Sessions</h2>
          <a href="<?= BASE_URL ?>/admin/sessions.php" class="btn-admin-sm">View All</a>
        </div>
        <?php if (empty($recentSessions)): ?>
          <div class="admin-empty"><i class="bi bi-inbox"></i><p>No sessions yet.</p></div>
        <?php else: ?>
          <table class="admin-table">
            <thead>
              <tr>
                <th>Mode</th>
                <th>User</th>
                <th>Topic / Category</th>
                <th>Score</th>
                <th>Status</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentSessions as $s):
                $isLive = ($s['session_type'] ?? '') === 'live';
                $scoreDisplay = '—';
                if ($isLive && !empty($s['evaluation_json'])) {
                    $ev = json_decode($s['evaluation_json'], true);
                    if (!empty($ev['overall_score'])) $scoreDisplay = (int)$ev['overall_score'] . '%';
                } elseif (!$isLive && $s['total_score'] > 0) {
                    $scoreDisplay = round((float)$s['total_score'], 1) . '/10';
                }

                $map = ['completed'=>'badge-green','in_progress'=>'badge-amber','abandoned'=>'badge-red'];
                $cls = $map[$s['status']] ?? 'badge-gray';
              ?>
              <tr>
                <td>
                  <?php if ($isLive): ?>
                    <span class="badge-pill" style="background:rgba(14,165,233,0.12);color:#0284c7;font-size:.7rem;font-weight:700;"><i class="bi bi-camera-video"></i> Live</span>
                  <?php else: ?>
                    <span class="badge-pill badge-gray" style="font-size:.7rem;"><i class="bi bi-chat-text"></i> Mock</span>
                  <?php endif; ?>
                </td>
                <td>
                  <div style="font-weight:600;font-size:.84rem;"><?= e($s['user_name']) ?></div>
                </td>
                <td style="font-size:.82rem;"><?= e($s['category_name']) ?></td>
                <td style="font-weight:600;font-size:.84rem;"><?= e($scoreDisplay) ?></td>
                <td>
                  <span class="badge-pill <?= $cls ?>"><?= e(ucfirst(str_replace('_',' ',$s['status']))) ?></span>
                </td>
                <td style="font-size:.78rem;color:var(--mist);white-space:nowrap;"><?= e(date('M j, Y', strtotime($s['started_at']))) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

      <!-- Recent Users -->
      <div class="admin-panel">
        <div class="admin-panel-header">
          <h2><i class="bi bi-person-plus" style="margin-right:.4rem;color:var(--navy);"></i>New Users</h2>
          <a href="<?= BASE_URL ?>/admin/users.php" class="btn-admin-sm">View All</a>
        </div>
        <?php if (empty($recentUsers)): ?>
          <div class="admin-empty"><i class="bi bi-person-x"></i><p>No users yet.</p></div>
        <?php else: ?>
          <table class="admin-table">
            <thead>
              <tr><th>User</th><th>Joined</th></tr>
            </thead>
            <tbody>
              <?php foreach ($recentUsers as $u): ?>
              <tr>
                <td>
                  <div class="user-cell">
                    <div class="mini-avatar"><?= e(strtoupper(substr($u['name'],0,1))) ?></div>
                    <div>
                      <div class="user-name"><?= e($u['name']) ?></div>
                      <div class="user-email"><?= e($u['email']) ?></div>
                    </div>
                  </div>
                </td>
                <td style="font-size:.8rem;color:var(--mist);"><?= e(date('M j, Y', strtotime($u['created_at']))) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

    </div><!-- /grid -->

    <!-- Summary bar -->
    <div style="margin-top:1.5rem;display:flex;align-items:center;gap:.75rem;background:#fff;border:1px solid var(--border);border-radius:var(--radius-md);padding:1rem 1.4rem;font-size:.88rem;color:var(--mist);">
      <i class="bi bi-activity" style="color:var(--navy);font-size:1.1rem;"></i>
      <span><strong style="color:var(--paper);"><?= $todayCount ?></strong> sessions started today &nbsp;·&nbsp;
            <strong style="color:var(--paper);"><?= $totalSessions ?></strong> total sessions all time</span>
    </div>

  </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
