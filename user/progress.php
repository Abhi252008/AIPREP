<?php
/**
 * Progress Dashboard — tracks user's interview performance over time.
 * Shows score trend, category breakdown, strength/weakness analysis,
 * streak tracking, and full session history.
 */
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

$userId = $_SESSION['user_id'];

// ── User info ─────────────────────────────────────────────────────────────
$user = $pdo->prepare("SELECT name, created_at FROM users WHERE id = ?");
$user->execute([$userId]);
$user = $user->fetch();

// ── Overall Stats ─────────────────────────────────────────────────────────
$stats = $pdo->prepare("
    SELECT
        COUNT(*)                                                AS total_sessions,
        SUM(status = 'completed')                              AS completed,
        SUM(status = 'in_progress')                            AS in_progress,
        SUM(status = 'abandoned')                              AS abandoned,
        ROUND(AVG(CASE WHEN status='completed' THEN total_score END), 1)  AS avg_score,
        ROUND(MAX(CASE WHEN status='completed' THEN total_score END), 1)  AS best_score,
        ROUND(AVG(CASE WHEN status='completed' THEN confidence_score END),1) AS avg_confidence
    FROM interview_sessions WHERE user_id = ?
");
$stats->execute([$userId]);
$stats = $stats->fetch();

// ── Score trend — last 10 completed sessions ──────────────────────────────
$trend = $pdo->prepare("
    SELECT s.total_score, s.confidence_score, s.started_at,
           c.name AS category_name, s.difficulty_level
    FROM interview_sessions s
    JOIN categories c ON c.id = s.category_id
    WHERE s.user_id = ? AND s.status = 'completed'
    ORDER BY s.started_at DESC LIMIT 10
");
$trend->execute([$userId]);
$trendRows = array_reverse($trend->fetchAll()); // oldest first for chart

// ── Category performance ──────────────────────────────────────────────────
$catPerf = $pdo->prepare("
    SELECT c.name, c.icon,
           COUNT(*)                       AS sessions,
           ROUND(AVG(s.total_score), 1)   AS avg_score,
           ROUND(MAX(s.total_score), 1)   AS best_score
    FROM interview_sessions s
    JOIN categories c ON c.id = s.category_id
    WHERE s.user_id = ? AND s.status = 'completed'
    GROUP BY c.id, c.name, c.icon
    ORDER BY avg_score DESC
");
$catPerf->execute([$userId]);
$categoryPerf = $catPerf->fetchAll();

// ── Score distribution (buckets: 0-3, 4-5, 6-7, 8-9, 10) ─────────────────
$dist = $pdo->prepare("
    SELECT
        SUM(total_score < 4)               AS poor,
        SUM(total_score >= 4 AND total_score < 6)  AS below_avg,
        SUM(total_score >= 6 AND total_score < 8)  AS average,
        SUM(total_score >= 8 AND total_score < 10) AS good,
        SUM(total_score = 10)              AS perfect
    FROM interview_sessions
    WHERE user_id = ? AND status = 'completed'
");
$dist->execute([$userId]);
$distribution = $dist->fetch();

// ── Difficulty breakdown ──────────────────────────────────────────────────
$diff = $pdo->prepare("
    SELECT difficulty_level,
           COUNT(*) AS sessions,
           ROUND(AVG(total_score), 1) AS avg_score
    FROM interview_sessions
    WHERE user_id = ? AND status = 'completed' AND difficulty_level IS NOT NULL
    GROUP BY difficulty_level
    ORDER BY FIELD(difficulty_level,'Beginner','Intermediate','Advanced')
");
$diff->execute([$userId]);
$diffBreakdown = $diff->fetchAll();

// ── All sessions history ──────────────────────────────────────────────────
$history = $pdo->prepare("
    SELECT s.id, s.total_score, s.confidence_score, s.status,
           s.difficulty_level, s.role_target, s.total_questions,
           s.started_at, s.completed_at,
           c.name AS category_name
    FROM interview_sessions s
    JOIN categories c ON c.id = s.category_id
    WHERE s.user_id = ?
    ORDER BY s.started_at DESC
    LIMIT 50
");
$history->execute([$userId]);
$sessions = $history->fetchAll();

// ── Live Interview Stats ──────────────────────────────────────────────────
$liveStats    = ['total' => 0, 'completed' => 0];
$liveSessions = [];
try {
    $lsStmt = $pdo->prepare("SELECT COUNT(*) AS total, SUM(status='completed') AS completed FROM live_interview_sessions WHERE user_id = ?");
    $lsStmt->execute([$userId]);
    $liveStats = $lsStmt->fetch() ?: $liveStats;

    $lsHist = $pdo->prepare(
        "SELECT id, role_name, category, status, evaluation_json, started_at, completed_at
         FROM live_interview_sessions WHERE user_id = ?
         ORDER BY started_at DESC LIMIT 20"
    );
    $lsHist->execute([$userId]);
    $liveSessions = $lsHist->fetchAll();
} catch (\Throwable $e) { /* table may not exist yet */ }

// ── Weekly sessions (last 7 days) ─────────────────────────────────────────
$weekly = $pdo->prepare("
    SELECT DATE(started_at) AS day, COUNT(*) AS cnt
    FROM interview_sessions
    WHERE user_id = ? AND started_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(started_at)
");
$weekly->execute([$userId]);
$weeklyRows = $weekly->fetchAll(PDO::FETCH_KEY_PAIR);

// Build 7-day array
$weeklyData = [];
$weeklyLabels = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $weeklyLabels[] = date('D', strtotime($d));
    $weeklyData[]   = (int)($weeklyRows[$d] ?? 0);
}

// ── Improvement rate (score change first→latest) ──────────────────────────
$improvementRate = null;
if (count($trendRows) >= 2) {
    $first  = (float)$trendRows[0]['total_score'];
    $latest = (float)$trendRows[count($trendRows)-1]['total_score'];
    $improvementRate = round($latest - $first, 1);
}

// ── Prepare chart JSON ────────────────────────────────────────────────────
$chartLabels  = array_map(fn($r) => date('M j', strtotime($r['started_at'])), $trendRows);
$chartScores  = array_map(fn($r) => round((float)$r['total_score'], 1), $trendRows);
$chartConf    = array_map(fn($r) => round((float)$r['confidence_score'], 1), $trendRows);

$activePage = 'progress';
$pageTitle  = 'My Progress';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/flash.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/user/progress.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<section class="dashboard-shell">
  <?php require_once __DIR__ . '/../includes/user_sidebar.php'; ?>

  <div class="dashboard-content progress-page">

    <!-- ── Hero Header ── -->
    <div class="progress-hero" data-reveal>
      <div>
        <span class="section-eyebrow">Progress Tracker</span>
        <h2 class="section-title" style="margin-bottom:0.25rem;">Your Interview Journey 📈</h2>
        <p style="color:var(--mist);font-size:0.9rem;">
          Member since <?= date('F Y', strtotime($user['created_at'])) ?>
        </p>
      </div>
      <a href="<?= BASE_URL ?>/user/start-interview.php" class="btn-gradient">
        <i class="bi bi-play-fill"></i> New Interview
      </a>
    </div>

    <!-- ── KPI Cards ── -->
    <div class="kpi-grid" data-reveal>
      <div class="kpi-card glass">
        <div class="kpi-icon" style="background:rgba(37,99,235,0.15);color:#2563eb;">
          <i class="bi bi-clipboard2-check-fill"></i>
        </div>
        <div class="kpi-body">
          <div class="kpi-num"><?= $stats['completed'] ?? 0 ?></div>
          <div class="kpi-label">Sessions Completed</div>
        </div>
      </div>

      <div class="kpi-card glass">
        <div class="kpi-icon" style="background:rgba(5,150,105,0.15);color:#059669;">
          <i class="bi bi-star-fill"></i>
        </div>
        <div class="kpi-body">
          <div class="kpi-num"><?= $stats['avg_score'] ?? '—' ?><span class="kpi-unit">/10</span></div>
          <div class="kpi-label">Average Score</div>
        </div>
      </div>

      <div class="kpi-card glass">
        <div class="kpi-icon" style="background:rgba(245,200,66,0.15);color:#d97706;">
          <i class="bi bi-trophy-fill"></i>
        </div>
        <div class="kpi-body">
          <div class="kpi-num"><?= $stats['best_score'] ?? '—' ?><span class="kpi-unit">/10</span></div>
          <div class="kpi-label">Best Score</div>
        </div>
      </div>

      <div class="kpi-card glass">
        <div class="kpi-icon" style="background:rgba(124,58,237,0.15);color:#7c3aed;">
          <i class="bi bi-graph-up-arrow"></i>
        </div>
        <div class="kpi-body">
          <div class="kpi-num" style="color:<?= $improvementRate > 0 ? '#059669' : ($improvementRate < 0 ? '#dc2626' : 'inherit') ?>">
            <?php if ($improvementRate !== null): ?>
              <?= $improvementRate > 0 ? '+' : '' ?><?= $improvementRate ?>
            <?php else: ?>—<?php endif; ?>
          </div>
          <div class="kpi-label">Score Change</div>
        </div>
      </div>

      <div class="kpi-card glass">
        <div class="kpi-icon" style="background:rgba(6,182,212,0.15);color:#0891b2;">
          <i class="bi bi-speedometer2"></i>
        </div>
        <div class="kpi-body">
          <div class="kpi-num"><?= $stats['avg_confidence'] ?? '—' ?><span class="kpi-unit">/10</span></div>
          <div class="kpi-label">Avg Confidence</div>
        </div>
      </div>

      <div class="kpi-card glass">
        <div class="kpi-icon" style="background:rgba(239,68,68,0.15);color:#dc2626;">
          <i class="bi bi-bar-chart-steps"></i>
        </div>
        <div class="kpi-body">
          <div class="kpi-num"><?= $stats['total_sessions'] ?? 0 ?></div>
          <div class="kpi-label">Mock Sessions</div>
        </div>
      </div>

      <div class="kpi-card glass">
        <div class="kpi-icon" style="background:rgba(14,165,233,0.15);color:#0284c7;">
          <i class="bi bi-camera-video-fill"></i>
        </div>
        <div class="kpi-body">
          <div class="kpi-num"><?= (int)($liveStats['completed'] ?? 0) ?></div>
          <div class="kpi-label">Live AI Interviews</div>
        </div>
      </div>
    </div>

    <!-- ── Charts Row ── -->
    <div class="charts-row">

      <!-- Score Trend -->
      <div class="chart-card glass" data-reveal>
        <div class="chart-header">
          <h3><i class="bi bi-graph-up"></i> Score Trend</h3>
          <span class="chart-sub">Last <?= count($trendRows) ?> sessions</span>
        </div>
        <?php if (empty($trendRows)): ?>
          <div class="chart-empty">Complete interviews to see your trend</div>
        <?php else: ?>
          <div class="chart-wrap"><canvas id="trendChart"></canvas></div>
        <?php endif; ?>
      </div>

      <!-- Weekly Activity -->
      <div class="chart-card glass" data-reveal>
        <div class="chart-header">
          <h3><i class="bi bi-calendar-week"></i> Weekly Activity</h3>
          <span class="chart-sub">Last 7 days</span>
        </div>
        <div class="chart-wrap"><canvas id="weeklyChart"></canvas></div>
      </div>

    </div>

    <!-- ── Category + Distribution Row ── -->
    <div class="charts-row">

      <!-- Category Performance -->
      <div class="chart-card glass" data-reveal>
        <div class="chart-header">
          <h3><i class="bi bi-pie-chart-fill"></i> Performance by Category</h3>
        </div>
        <?php if (empty($categoryPerf)): ?>
          <div class="chart-empty">No completed sessions yet</div>
        <?php else: ?>
          <div class="cat-list">
            <?php foreach ($categoryPerf as $cat): ?>
              <?php
                $pct = $cat['avg_score'] !== null ? round(($cat['avg_score'] / 10) * 100) : 0;
                $color = $pct >= 70 ? '#059669' : ($pct >= 50 ? '#d97706' : '#dc2626');
              ?>
              <div class="cat-row">
                <div class="cat-info">
                  <i class="bi <?= e($cat['icon']) ?>"></i>
                  <span><?= e($cat['name']) ?></span>
                  <span class="cat-sessions"><?= $cat['sessions'] ?> session<?= $cat['sessions'] != 1 ? 's' : '' ?></span>
                </div>
                <div class="cat-bar-wrap">
                  <div class="cat-bar" style="width:<?= $pct ?>%;background:<?= $color ?>"></div>
                </div>
                <div class="cat-score" style="color:<?= $color ?>"><?= $cat['avg_score'] ?? '—' ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Score Distribution -->
      <div class="chart-card glass" data-reveal>
        <div class="chart-header">
          <h3><i class="bi bi-bar-chart-fill"></i> Score Distribution</h3>
        </div>
        <?php if (!array_sum(array_values(array_intersect_key((array)$distribution, array_flip(['poor','below_avg','average','good','perfect']))))): ?>
          <div class="chart-empty">No completed sessions yet</div>
        <?php else: ?>
          <div class="chart-wrap"><canvas id="distChart"></canvas></div>
        <?php endif; ?>
      </div>

    </div>

    <!-- ── Difficulty Breakdown ── -->
    <?php if (!empty($diffBreakdown)): ?>
    <div class="diff-section glass" data-reveal>
      <h3 class="section-subhead"><i class="bi bi-layers-fill"></i> Performance by Difficulty</h3>
      <div class="diff-grid">
        <?php
          $diffColors = ['Beginner'=>'#059669','Intermediate'=>'#d97706','Advanced'=>'#dc2626'];
          foreach ($diffBreakdown as $d):
            $col = $diffColors[$d['difficulty_level']] ?? '#2563eb';
            $pct = $d['avg_score'] !== null ? round(($d['avg_score']/10)*100) : 0;
        ?>
          <div class="diff-card" style="border-color:<?= $col ?>20;">
            <div class="diff-level" style="color:<?= $col ?>"><?= e($d['difficulty_level']) ?></div>
            <div class="diff-score" style="color:<?= $col ?>"><?= $d['avg_score'] ?? '—' ?><span style="font-size:0.8rem;color:var(--mist)">/10</span></div>
            <div class="diff-sessions"><?= $d['sessions'] ?> session<?= $d['sessions'] != 1 ? 's' : '' ?></div>
            <div class="diff-bar-bg"><div class="diff-bar" style="width:<?= $pct ?>%;background:<?= $col ?>"></div></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── Full Session History ── -->
    <div class="history-section glass" data-reveal>
      <div class="panel-header">
        <h3><i class="bi bi-clock-history"></i> Session History</h3>
        <span style="color:var(--mist);font-size:0.82rem;"><?= count($sessions) ?> sessions</span>
      </div>

      <?php if (empty($sessions)): ?>
        <p class="empty-state">No sessions yet. <a href="<?= BASE_URL ?>/user/start-interview.php">Start your first interview</a>.</p>
      <?php else: ?>
        <div class="history-table-wrap">
          <table class="table-glass">
            <thead>
              <tr>
                <th>#</th>
                <th>Category</th>
                <th>Role</th>
                <th>Difficulty</th>
                <th>Score</th>
                <th>Confidence</th>
                <th>Status</th>
                <th>Date</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($sessions as $i => $s):
                $score = $s['total_score'] !== null ? round((float)$s['total_score'], 1) : null;
                $scoreColor = $score === null ? '' : ($score >= 8 ? 'color:#059669' : ($score >= 5 ? 'color:#d97706' : 'color:#dc2626'));
              ?>
                <tr>
                  <td style="color:var(--mist);font-size:0.8rem;"><?= $i + 1 ?></td>
                  <td><?= e($s['category_name']) ?></td>
                  <td><?= e($s['role_target'] ?: '—') ?></td>
                  <td>
                    <?php if ($s['difficulty_level']): ?>
                      <span class="diff-badge diff-<?= strtolower(e($s['difficulty_level'])) ?>"><?= e($s['difficulty_level']) ?></span>
                    <?php else: ?>—<?php endif; ?>
                  </td>
                  <td style="font-weight:700;<?= $scoreColor ?>">
                    <?= $score !== null ? "$score / 10" : '—' ?>
                  </td>
                  <td style="color:var(--mist)">
                    <?= $s['confidence_score'] ? round((float)$s['confidence_score'],1).'/10' : '—' ?>
                  </td>
                  <td>
                    <span class="status-pill status-<?= e($s['status']) ?>">
                      <?= e(ucfirst(str_replace('_',' ',$s['status']))) ?>
                    </span>
                  </td>
                  <td style="color:var(--mist);font-size:0.82rem;">
                    <?= date('M j, Y', strtotime($s['started_at'])) ?>
                  </td>
                  <td>
                    <?php if ($s['status'] === 'completed'): ?>
                      <a href="<?= BASE_URL ?>/user/evaluation.php?session_id=<?= $s['id'] ?>"
                         class="btn-sm-outline">View</a>
                    <?php elseif ($s['status'] === 'in_progress'): ?>
                      <a href="<?= BASE_URL ?>/user/interview.php?session_id=<?= $s['id'] ?>"
                         class="btn-sm-outline" style="border-color:#d97706;color:#d97706;">Resume</a>
                    <?php else: ?>
                      <span style="color:var(--mist);font-size:0.8rem;">—</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- ── Live AI Interview History ── -->
    <div class="history-section glass" data-reveal style="margin-top:28px;">
      <div class="panel-header" style="display:flex;justify-content:space-between;align-items:center;">
        <h3><i class="bi bi-camera-video-fill" style="color:#0284c7;margin-right:6px;"></i> Live AI Interview History</h3>
        <div>
          <span style="color:var(--mist);font-size:0.82rem;margin-right:12px;"><?= count($liveSessions) ?> voice sessions</span>
          <a href="<?= BASE_URL ?>/user/live_interview.php" class="btn-sm-outline" style="border-color:#0284c7;color:#0284c7;">+ Start Live AI</a>
        </div>
      </div>

      <?php if (empty($liveSessions)): ?>
        <p class="empty-state">No live AI voice interviews recorded yet. <a href="<?= BASE_URL ?>/user/live_interview.php">Launch your first live interview</a> with real-time speech and camera feedback!</p>
      <?php else: ?>
        <div class="history-table-wrap">
          <table class="table-glass">
            <thead>
              <tr>
                <th>#</th>
                <th>Role / Preparation</th>
                <th>Category</th>
                <th>Performance</th>
                <th>Status</th>
                <th>Date</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($liveSessions as $i => $ls):
                $eval = !empty($ls['evaluation_json']) ? json_decode($ls['evaluation_json'], true) : null;
                $score = $eval['overall_score'] ?? null;
                $readiness = $eval['readiness_level'] ?? null;
              ?>
                <tr>
                  <td style="color:var(--mist);font-size:0.8rem;"><?= $i + 1 ?></td>
                  <td style="font-weight:600;"><?= e($ls['role_name'] ?: 'Live Interview') ?></td>
                  <td><span class="diff-badge" style="background:rgba(14,165,233,0.15);color:#0284c7;"><?= e(ucfirst($ls['category'] ?? 'Technical')) ?></span></td>
                  <td>
                    <?php if ($score !== null): ?>
                      <strong style="color:<?= $score >= 75 ? '#059669' : ($score >= 60 ? '#d97706' : '#dc2626') ?>;"><?= (int)$score ?>%</strong>
                      <?php if ($readiness): ?>
                        <span style="font-size:0.75rem;color:var(--mist);">(<?= e($readiness) ?>)</span>
                      <?php endif; ?>
                    <?php elseif ($ls['status'] === 'completed'): ?>
                      <span style="color:var(--mist);font-size:0.8rem;">Ready for review</span>
                    <?php else: ?>
                      <span style="color:var(--mist);">—</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="status-pill status-<?= e($ls['status']) ?>">
                      <?= e(ucfirst($ls['status'])) ?>
                    </span>
                  </td>
                  <td style="color:var(--mist);font-size:0.82rem;">
                    <?= date('M j, Y', strtotime($ls['started_at'])) ?>
                  </td>
                  <td>
                    <?php if ($ls['status'] === 'completed'): ?>
                      <a href="<?= BASE_URL ?>/user/live_evaluation.php?id=<?= (int)$ls['id'] ?>" class="btn-sm-outline" style="border-color:#0284c7;color:#0284c7;">Report</a>
                    <?php else: ?>
                      <a href="<?= BASE_URL ?>/user/live_interview.php" class="btn-sm-outline">New</a>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

  </div><!-- /dashboard-content -->
</section>

<!-- ── Chart.js Scripts ── -->
<script>
const chartDefaults = {
  color: '#94a3b8',
  font: { family: "'Inter', sans-serif" }
};
Chart.defaults.color = chartDefaults.color;
Chart.defaults.font  = chartDefaults.font;

// ── Score Trend Chart ────────────────────────────────────────────────────
<?php if (!empty($trendRows)): ?>
new Chart(document.getElementById('trendChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode($chartLabels) ?>,
    datasets: [
      {
        label: 'Score',
        data: <?= json_encode($chartScores) ?>,
        borderColor: '#2563eb',
        backgroundColor: 'rgba(37,99,235,0.10)',
        pointBackgroundColor: '#2563eb',
        pointRadius: 5,
        tension: 0.4,
        fill: true,
      },
      {
        label: 'Confidence',
        data: <?= json_encode($chartConf) ?>,
        borderColor: '#7c3aed',
        backgroundColor: 'rgba(124,58,237,0.08)',
        pointBackgroundColor: '#7c3aed',
        pointRadius: 4,
        tension: 0.4,
        fill: true,
        borderDash: [5,3],
      }
    ]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    scales: {
      y: { min: 0, max: 10, grid: { color: 'rgba(255,255,255,0.05)' } },
      x: { grid: { color: 'rgba(255,255,255,0.05)' } }
    },
    plugins: { legend: { position: 'bottom' } }
  }
});
<?php endif; ?>

// ── Weekly Activity Chart ─────────────────────────────────────────────────
new Chart(document.getElementById('weeklyChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($weeklyLabels) ?>,
    datasets: [{
      label: 'Sessions',
      data: <?= json_encode($weeklyData) ?>,
      backgroundColor: 'rgba(37,99,235,0.7)',
      borderRadius: 6,
      borderSkipped: false,
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    scales: {
      y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color:'rgba(255,255,255,0.05)' } },
      x: { grid: { display: false } }
    },
    plugins: { legend: { display: false } }
  }
});

// ── Score Distribution Chart ──────────────────────────────────────────────
<?php if (!empty($distribution)): ?>
new Chart(document.getElementById('distChart'), {
  type: 'doughnut',
  data: {
    labels: ['Poor (0–3)', 'Below Avg (4–5)', 'Average (6–7)', 'Good (8–9)', 'Perfect (10)'],
    datasets: [{
      data: [
        <?= (int)$distribution['poor'] ?>,
        <?= (int)$distribution['below_avg'] ?>,
        <?= (int)$distribution['average'] ?>,
        <?= (int)$distribution['good'] ?>,
        <?= (int)$distribution['perfect'] ?>
      ],
      backgroundColor: ['#dc2626','#f59e0b','#3b82f6','#10b981','#7c3aed'],
      borderWidth: 2,
      borderColor: '#0f172a',
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: {
      legend: { position: 'bottom', labels: { padding: 12, boxWidth: 12 } }
    },
    cutout: '65%',
  }
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
