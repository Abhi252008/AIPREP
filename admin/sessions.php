<?php
/**
 * MODULE 9 — Admin Sessions Viewer
 * View all interview sessions (Mock Text & Live AI Voice) across all users.
 * Supports filtering by mode (all / mock / live) and status (all / completed / in_progress / abandoned).
 */
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/admin_auth_check.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

$mode = $_GET['mode'] ?? 'all';
$validModes = ['all', 'mock', 'live'];
if (!in_array($mode, $validModes)) $mode = 'all';

$filterStatus = $_GET['status'] ?? 'all';
$validStatuses = ['all', 'completed', 'in_progress', 'abandoned'];
if (!in_array($filterStatus, $validStatuses)) $filterStatus = 'all';

// ── Counts ──
$mockTotalAll       = (int)$pdo->query("SELECT COUNT(*) FROM interview_sessions")->fetchColumn();
$mockTotalCompleted = (int)$pdo->query("SELECT COUNT(*) FROM interview_sessions WHERE status='completed'")->fetchColumn();
$mockTotalInProg    = (int)$pdo->query("SELECT COUNT(*) FROM interview_sessions WHERE status='in_progress'")->fetchColumn();
$mockTotalAbandoned = (int)$pdo->query("SELECT COUNT(*) FROM interview_sessions WHERE status='abandoned'")->fetchColumn();

try {
    $liveTotalAll       = (int)$pdo->query("SELECT COUNT(*) FROM live_interview_sessions")->fetchColumn();
    $liveTotalCompleted = (int)$pdo->query("SELECT COUNT(*) FROM live_interview_sessions WHERE status='completed'")->fetchColumn();
    $liveTotalInProg    = (int)$pdo->query("SELECT COUNT(*) FROM live_interview_sessions WHERE status='active'")->fetchColumn();
    $liveTotalAbandoned = (int)$pdo->query("SELECT COUNT(*) FROM live_interview_sessions WHERE status='abandoned'")->fetchColumn();
} catch (\Throwable $e) {
    $liveTotalAll = $liveTotalCompleted = $liveTotalInProg = $liveTotalAbandoned = 0;
}

$allTotalAll       = $mockTotalAll + $liveTotalAll;
$allTotalCompleted = $mockTotalCompleted + $liveTotalCompleted;
$allTotalInProg    = $mockTotalInProg + $liveTotalInProg;
$allTotalAbandoned = $mockTotalAbandoned + $liveTotalAbandoned;

// Determine tab badge counts based on active mode
if ($mode === 'mock') {
    $curAll       = $mockTotalAll;
    $curCompleted = $mockTotalCompleted;
    $curInProg    = $mockTotalInProg;
    $curAbandoned = $mockTotalAbandoned;
} elseif ($mode === 'live') {
    $curAll       = $liveTotalAll;
    $curCompleted = $liveTotalCompleted;
    $curInProg    = $liveTotalInProg;
    $curAbandoned = $liveTotalAbandoned;
} else {
    $curAll       = $allTotalAll;
    $curCompleted = $allTotalCompleted;
    $curInProg    = $allTotalInProg;
    $curAbandoned = $allTotalAbandoned;
}

// ── SQL Query Builder ──
$mockWhere = [];
if ($filterStatus === 'completed')   $mockWhere[] = "s.status = 'completed'";
elseif ($filterStatus === 'in_progress') $mockWhere[] = "s.status = 'in_progress'";
elseif ($filterStatus === 'abandoned')   $mockWhere[] = "s.status = 'abandoned'";
$mockWhereSql = !empty($mockWhere) ? "WHERE " . implode(' AND ', $mockWhere) : "";

$liveWhere = [];
if ($filterStatus === 'completed')   $liveWhere[] = "s.status = 'completed'";
elseif ($filterStatus === 'in_progress') $liveWhere[] = "s.status = 'active'";
elseif ($filterStatus === 'abandoned')   $liveWhere[] = "s.status = 'abandoned'";
$liveWhereSql = !empty($liveWhere) ? "WHERE " . implode(' AND ', $liveWhere) : "";

$mockQuery = "SELECT 'mock' AS session_type, s.id, s.user_id, u.name AS user_name, u.email AS user_email,
                     c.name AS category_name, s.difficulty_level, s.total_questions AS questions_count,
                     s.total_score, NULL AS evaluation_json, s.status, s.started_at, s.completed_at
              FROM interview_sessions s
              JOIN users u ON u.id = s.user_id
              JOIN categories c ON c.id = s.category_id
              {$mockWhereSql}";

$liveQuery = "SELECT 'live' AS session_type, s.id, s.user_id, u.name AS user_name, u.email AS user_email,
                     COALESCE(NULLIF(s.role_name,''), s.category, 'Live AI Session') AS category_name,
                     s.difficulty AS difficulty_level,
                     (SELECT COUNT(*) FROM live_interview_answers a WHERE a.interview_id = s.id) AS questions_count,
                     NULL AS total_score, s.evaluation_json,
                     CASE WHEN s.status = 'active' THEN 'in_progress' ELSE s.status END AS status,
                     s.started_at, s.completed_at
              FROM live_interview_sessions s
              JOIN users u ON u.id = s.user_id
              {$liveWhereSql}";

if ($mode === 'mock') {
    $sql = "{$mockQuery} ORDER BY started_at DESC LIMIT 200";
} elseif ($mode === 'live') {
    $sql = "{$liveQuery} ORDER BY started_at DESC LIMIT 200";
} else {
    $sql = "({$mockQuery}) UNION ALL ({$liveQuery}) ORDER BY started_at DESC LIMIT 200";
}

try {
    $sessions = $pdo->query($sql)->fetchAll();
} catch (\Throwable $e) {
    // Fallback if live table not yet populated or on query error
    $sessions = $pdo->query("{$mockQuery} ORDER BY started_at DESC LIMIT 200")->fetchAll();
}

$adminActivePage = 'sessions';
$pageTitle = 'All Sessions';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/flash.php';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>/admin/admin.css">

<style>
.mode-switch-group {
    display: flex;
    background: #f1f5f9;
    border: 1px solid var(--border);
    padding: 4px;
    border-radius: 10px;
    gap: 4px;
}
.mode-switch-btn {
    padding: 6px 14px;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--mist);
    border-radius: 7px;
    text-decoration: none;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.mode-switch-btn:hover {
    color: var(--paper);
    background: rgba(255, 255, 255, 0.6);
}
.mode-switch-btn.active {
    background: #ffffff;
    color: var(--navy);
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}
.badge-live-type {
    background: rgba(14, 165, 233, 0.12);
    color: #0284c7;
    border: 1px solid rgba(14, 165, 233, 0.25);
    font-size: 0.72rem;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    letter-spacing: 0.3px;
}
.badge-mock-type {
    background: rgba(100, 116, 139, 0.1);
    color: #475569;
    border: 1px solid rgba(100, 116, 139, 0.2);
    font-size: 0.72rem;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
</style>

<div class="admin-shell">
  <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

  <main class="admin-content">

    <div class="admin-page-header">
      <div>
        <h1>Interview Sessions</h1>
        <p>Comprehensive overview of text mock interviews and real-time live AI sessions.</p>
      </div>
    </div>

    <!-- Mode Selector & Status Tabs Bar -->
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.4rem;">
      <!-- Mode Tabs -->
      <div class="mode-switch-group">
        <a href="?mode=all&status=<?= e($filterStatus) ?>" class="mode-switch-btn <?= $mode === 'all' ? 'active' : '' ?>">
          <i class="bi bi-grid-fill"></i> All Sessions (<?= $allTotalAll ?>)
        </a>
        <a href="?mode=live&status=<?= e($filterStatus) ?>" class="mode-switch-btn <?= $mode === 'live' ? 'active' : '' ?>">
          <i class="bi bi-camera-video-fill" style="color:#0284c7;"></i> Live AI Voice (<?= $liveTotalAll ?>)
        </a>
        <a href="?mode=mock&status=<?= e($filterStatus) ?>" class="mode-switch-btn <?= $mode === 'mock' ? 'active' : '' ?>">
          <i class="bi bi-chat-left-text-fill"></i> Mock Text (<?= $mockTotalAll ?>)
        </a>
      </div>

      <!-- Filter tabs -->
      <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
        <?php
        $tabs = [
          'all'         => ['label' => 'All',         'count' => $curAll,       'cls' => 'badge-gray'],
          'completed'   => ['label' => 'Completed',    'count' => $curCompleted, 'cls' => 'badge-green'],
          'in_progress' => ['label' => 'In Progress',  'count' => $curInProg,    'cls' => 'badge-amber'],
          'abandoned'   => ['label' => 'Abandoned',    'count' => $curAbandoned, 'cls' => 'badge-red'],
        ];
        foreach ($tabs as $key => $tab):
          $isActive = $filterStatus === $key;
        ?>
        <a href="?mode=<?= e($mode) ?>&status=<?= $key ?>"
           class="btn-admin-sm <?= $isActive ? 'primary' : '' ?>">
          <?= $tab['label'] ?>
          <span class="badge-pill <?= $tab['cls'] ?>" style="margin-left:.3rem;"><?= $tab['count'] ?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="admin-panel">
      <div class="admin-panel-header">
        <h2>
          <?php if ($mode === 'live'): ?>
            <i class="bi bi-camera-video-fill" style="margin-right:.4rem;color:#0284c7;"></i> Live AI Interview Sessions
          <?php elseif ($mode === 'mock'): ?>
            <i class="bi bi-chat-left-text-fill" style="margin-right:.4rem;color:var(--navy);"></i> Mock Interview Sessions
          <?php else: ?>
            <i class="bi bi-card-list" style="margin-right:.4rem;color:var(--navy);"></i>
            <?= $filterStatus === 'all' ? 'All Sessions' : ucfirst(str_replace('_',' ',$filterStatus)) . ' Sessions' ?>
          <?php endif; ?>
        </h2>
        <span style="font-size:.82rem;color:var(--mist);"><?= count($sessions) ?> displayed</span>
      </div>

      <?php if (empty($sessions)): ?>
        <div class="admin-empty">
          <i class="bi bi-inbox"></i>
          <p>No interview sessions found matching these filters.</p>
        </div>
      <?php else: ?>
        <table class="admin-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Mode</th>
              <th>Candidate</th>
              <th>Category / Role</th>
              <th>Difficulty</th>
              <th>Questions</th>
              <th>Performance</th>
              <th>Status</th>
              <th>Date</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($sessions as $i => $s):
              $statusMap = ['completed' => 'badge-green', 'in_progress' => 'badge-amber', 'abandoned' => 'badge-red'];
              $statusCls = $statusMap[$s['status']] ?? 'badge-gray';
              $isLive = ($s['session_type'] ?? '') === 'live';

              // Extract Live AI Score if available
              $liveScore = null;
              $liveReadiness = null;
              if ($isLive && !empty($s['evaluation_json'])) {
                  $eval = json_decode($s['evaluation_json'], true);
                  if (is_array($eval) && isset($eval['overall_score'])) {
                      $liveScore = (int) $eval['overall_score'];
                      $liveReadiness = $eval['readiness_level'] ?? null;
                  }
              }
            ?>
            <tr>
              <td style="color:var(--mist);font-size:.78rem;"><?= $i + 1 ?></td>
              <td>
                <?php if ($isLive): ?>
                  <span class="badge-live-type"><i class="bi bi-camera-video"></i> Live Voice</span>
                <?php else: ?>
                  <span class="badge-mock-type"><i class="bi bi-chat-text"></i> Mock Text</span>
                <?php endif; ?>
              </td>
              <td>
                <div style="font-weight:600;font-size:.875rem;"><?= e($s['user_name']) ?></div>
                <div style="font-size:.75rem;color:var(--mist);"><?= e($s['user_email']) ?></div>
              </td>
              <td>
                <div style="font-weight:500;"><?= e($s['category_name']) ?></div>
                <?php if ($isLive): ?>
                  <span style="font-size:.72rem;color:#0284c7;"><i class="bi bi-mic"></i> Real-time AI</span>
                <?php endif; ?>
              </td>
              <td style="font-size:.82rem;color:var(--mist);text-transform:capitalize;"><?= e($s['difficulty_level'] ?? 'adaptive') ?></td>
              <td style="text-align:center;font-weight:600;"><?= (int)$s['questions_count'] ?></td>
              <td>
                <?php if ($isLive): ?>
                  <?php if ($liveScore !== null): ?>
                    <strong style="color: <?= $liveScore >= 75 ? '#16a34a' : ($liveScore >= 60 ? '#ca8a04' : '#dc2626') ?>;"><?= $liveScore ?>%</strong>
                    <?php if ($liveReadiness): ?>
                      <div style="font-size:.72rem;color:var(--mist);"><?= e($liveReadiness) ?></div>
                    <?php endif; ?>
                  <?php elseif ($s['status'] === 'completed'): ?>
                    <span style="color:var(--mist);font-size:.8rem;">Ready for review</span>
                  <?php else: ?>
                    <span style="color:var(--mist);">—</span>
                  <?php endif; ?>
                <?php else: ?>
                  <?php if ($s['total_score'] > 0): ?>
                    <strong><?= e((string)round((float)$s['total_score'],1)) ?></strong><span style="color:var(--mist);font-size:.8rem;">/10</span>
                  <?php else: ?>
                    <span style="color:var(--mist);">—</span>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
              <td><span class="badge-pill <?= $statusCls ?>"><?= e(ucfirst(str_replace('_',' ',$s['status']))) ?></span></td>
              <td style="font-size:.8rem;color:var(--mist);white-space:nowrap;"><?= e(date('M j, Y H:i', strtotime($s['started_at']))) ?></td>
              <td>
                <?php if ($isLive): ?>
                  <a href="<?= BASE_URL ?>/user/live_evaluation.php?id=<?= (int)$s['id'] ?>"
                     target="_blank" class="btn-admin-sm primary" style="background:#0284c7;border-color:#0284c7;">
                    <i class="bi bi-award"></i> Review
                  </a>
                <?php else: ?>
                  <?php if ($s['status'] === 'completed'): ?>
                    <a href="<?= BASE_URL ?>/user/evaluation.php?session_id=<?= (int)$s['id'] ?>"
                       target="_blank" class="btn-admin-sm">
                      <i class="bi bi-bar-chart"></i> Results
                    </a>
                  <?php else: ?>
                    <span style="color:var(--mist);font-size:.8rem;">—</span>
                  <?php endif; ?>
                <?php endif; ?>
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
