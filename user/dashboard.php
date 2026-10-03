<?php
/**
 * Module 3 — User Dashboard: Overview
 * Shows session stats, recent mock-interview history, and a preview of
 * unread notifications. Session-taking itself (Start Interview) is
 * built out in the Interview Engine module — the button here links to
 * a placeholder for now.
 */
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

$userId = $_SESSION['user_id'];

$userStmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$user = $userStmt->fetch();

// --- Mock Interview Stats ---
$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM interview_sessions WHERE user_id = ? AND status = 'completed'");
$totalStmt->execute([$userId]);
$totalCompleted = (int) $totalStmt->fetchColumn();

$avgStmt = $pdo->prepare("SELECT AVG(total_score) FROM interview_sessions WHERE user_id = ? AND status = 'completed'");
$avgStmt->execute([$userId]);
$avgScoreRaw = $avgStmt->fetchColumn();
$avgScore = $avgScoreRaw !== null ? round((float) $avgScoreRaw, 1) : null;

$inProgressStmt = $pdo->prepare("SELECT COUNT(*) FROM interview_sessions WHERE user_id = ? AND status = 'in_progress'");
$inProgressStmt->execute([$userId]);
$inProgressCount = (int) $inProgressStmt->fetchColumn();

// --- Live Interview Stats ---
$liveCountStmt = null;
$liveCompleted  = 0;
$recentLive     = [];
try {
    $liveCountStmt = $pdo->prepare("SELECT COUNT(*) FROM live_interview_sessions WHERE user_id = ? AND status = 'completed'");
    $liveCountStmt->execute([$userId]);
    $liveCompleted = (int) $liveCountStmt->fetchColumn();

    $liveStmt = $pdo->prepare(
        "SELECT id, role_name, category, status, started_at, completed_at
         FROM live_interview_sessions WHERE user_id = ?
         ORDER BY started_at DESC LIMIT 4"
    );
    $liveStmt->execute([$userId]);
    $recentLive = $liveStmt->fetchAll();
} catch (\Throwable $e) { /* table may not exist yet — silently skip */ }

// --- Recent mock sessions (last 5) ---
$recentStmt = $pdo->prepare(
    "SELECT s.id, s.role_target, s.total_score, s.status, s.started_at, c.name AS category_name
     FROM interview_sessions s
     JOIN categories c ON c.id = s.category_id
     WHERE s.user_id = ?
     ORDER BY s.started_at DESC
     LIMIT 5"
);
$recentStmt->execute([$userId]);
$recentSessions = $recentStmt->fetchAll();

// --- Recent notifications (last 3) ---
$notifStmt = $pdo->prepare(
    "SELECT id, message, is_read, created_at FROM notifications
     WHERE user_id = ? ORDER BY created_at DESC LIMIT 3"
);
$notifStmt->execute([$userId]);
$recentNotifications = $notifStmt->fetchAll();

$activePage = 'dashboard';
$pageTitle = "Dashboard";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/flash.php';
?>

<section class="dashboard-shell">
  <?php require_once __DIR__ . '/../includes/user_sidebar.php'; ?>

  <div class="dashboard-content">
    <div class="dashboard-header" data-reveal>
      <div>
        <span class="section-eyebrow">Dashboard</span>
        <h2 class="section-title" style="margin-bottom:0;">Welcome back, <?= e($user['name']) ?></h2>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a href="<?= BASE_URL ?>/user/live_interview.php" class="btn-outline-glass" style="display:inline-flex;align-items:center;gap:7px;">
          <i class="bi bi-mic-fill"></i> Live Interview
        </a>
        <a href="<?= BASE_URL ?>/user/start-interview.php" class="btn-gradient">
          <i class="bi bi-play-fill"></i> Start Interview
        </a>
      </div>
    </div>

    <div class="stat-grid" data-reveal>
      <div class="stat-card glass">
        <div class="num"><?= $totalCompleted ?></div>
        <div class="label">Mock Sessions Done</div>
      </div>
      <div class="stat-card glass">
        <div class="num"><?= $avgScore !== null ? e((string) $avgScore) : '—' ?></div>
        <div class="label">Avg Mock Score</div>
      </div>
      <div class="stat-card glass">
        <div class="num"><?= $liveCompleted ?></div>
        <div class="label">Live Sessions Done</div>
      </div>
      <div class="stat-card glass">
        <div class="num"><?= $inProgressCount ?></div>
        <div class="label">In Progress</div>
      </div>
    </div>

    <div class="dashboard-grid">
      <div class="dashboard-panel glass" data-reveal>
        <div class="panel-header">
          <h3>Recent Sessions</h3>
        </div>

        <?php if (empty($recentSessions)): ?>
          <p class="empty-state">
            You haven't taken any mock interviews yet.
            <a href="<?= BASE_URL ?>/user/start-interview.php">Start your first one</a>.
          </p>
        <?php else: ?>
          <table class="table-glass">
            <thead>
              <tr>
                <th>Category</th>
                <th>Role</th>
                <th>Score</th>
                <th>Status</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentSessions as $session): ?>
                <tr>
                  <td><?= e($session['category_name']) ?></td>
                  <td><?= e($session['role_target'] ?: '—') ?></td>
                  <td><?= $session['total_score'] !== null ? e((string) round((float) $session['total_score'], 1)) . ' / 10' : '—' ?></td>
                  <td>
                    <span class="status-pill status-<?= e($session['status']) ?>">
                      <?= e(ucfirst(str_replace('_', ' ', $session['status']))) ?>
                    </span>
                  </td>
                  <td><?= e(date('M j, Y', strtotime($session['started_at']))) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

      <div class="dashboard-panel glass" data-reveal>
        <div class="panel-header">
          <h3>Notifications</h3>
          <a href="<?= BASE_URL ?>/user/notifications.php" class="panel-link">View all</a>
        </div>

        <?php if (empty($recentNotifications)): ?>
          <p class="empty-state">No notifications yet.</p>
        <?php else: ?>
          <ul class="notif-list">
            <?php foreach ($recentNotifications as $notif): ?>
              <li class="notif-item <?= !$notif['is_read'] ? 'unread' : '' ?>">
                <span class="notif-dot"></span>
                <div>
                  <div class="notif-message"><?= e($notif['message']) ?></div>
                  <div class="notif-time"><?= e(date('M j, Y g:i A', strtotime($notif['created_at']))) ?></div>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>

    <!-- Live Interview History Panel -->
    <div class="dashboard-panel glass" data-reveal style="margin-top:1.5rem;">
      <div class="panel-header">
        <h3><i class="bi bi-mic-fill" style="color:#60a5fa;margin-right:6px;"></i>Live Interview Sessions</h3>
        <a href="<?= BASE_URL ?>/user/live_interview.php" class="panel-link">+ New Session</a>
      </div>

      <?php if (empty($recentLive)): ?>
        <p class="empty-state">
          You haven't done a live AI interview yet.
          <a href="<?= BASE_URL ?>/user/live_interview.php">Try your first one</a> — it's a real voice conversation!
        </p>
      <?php else: ?>
        <table class="table-glass">
          <thead>
            <tr>
              <th>Topic</th>
              <th>Type</th>
              <th>Status</th>
              <th>Date</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentLive as $ls): ?>
              <tr>
                <td><?= e($ls['role_name'] ?: 'General Interview') ?></td>
                <td style="text-transform:capitalize;color:var(--mist);font-size:0.82rem;"><?= e($ls['category'] ?: 'technical') ?></td>
                <td>
                  <span class="status-pill status-<?= e($ls['status']) ?>">
                    <?= e(ucfirst($ls['status'])) ?>
                  </span>
                </td>
                <td style="color:var(--mist);font-size:0.82rem;"><?= e(date('M j, Y', strtotime($ls['started_at']))) ?></td>
                <td>
                  <?php if ($ls['status'] === 'completed'): ?>
                    <a href="<?= BASE_URL ?>/user/live_evaluation.php?id=<?= (int)$ls['id'] ?>" class="btn-sm-outline">Report</a>
                  <?php else: ?>
                    <a href="<?= BASE_URL ?>/user/live_interview.php" class="btn-sm-outline" style="border-color:#60a5fa;color:#60a5fa;">New</a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
