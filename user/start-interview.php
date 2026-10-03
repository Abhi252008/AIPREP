<?php
/**
 * Start Interview — Choice Page
 * Presents both interview modes: Mock (text) and Live AI (voice)
 */
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

$userId = (int) $_SESSION['user_id'];

// Quick stats for both modes
try {
    $mockCount = (int) $pdo->prepare("SELECT COUNT(*) FROM interview_sessions WHERE user_id = ? AND status='completed'")->execute([$userId]) ? $pdo->query("SELECT COUNT(*) FROM interview_sessions WHERE user_id = $userId AND status='completed'")->fetchColumn() : 0;

    $mockStmt = $pdo->prepare("SELECT COUNT(*) FROM interview_sessions WHERE user_id = ? AND status='completed'");
    $mockStmt->execute([$userId]);
    $mockCompleted = (int) $mockStmt->fetchColumn();

    $liveStmt = $pdo->prepare("SELECT COUNT(*) FROM live_interview_sessions WHERE user_id = ? AND status='completed'");
    $liveStmt->execute([$userId]);
    $liveCompleted = (int) $liveStmt->fetchColumn();
} catch (\Throwable $e) {
    $mockCompleted = 0;
    $liveCompleted = 0;
}

$activePage = 'start-interview';
$pageTitle  = 'Start Interview';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.choice-shell {
    min-height: calc(100vh - 80px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
}
.choice-inner {
    width: min(960px, 100%);
}
.choice-header {
    text-align: center;
    margin-bottom: 48px;
}
.choice-header .section-eyebrow { margin-bottom: 10px; }
.choice-header h1 { font-size: clamp(2rem, 4vw, 2.8rem); margin-bottom: 14px; }
.choice-header p { color: var(--mist); font-size: 1rem; max-width: 500px; margin: 0 auto; }

.mode-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 28px;
}
@media (max-width: 700px) { .mode-grid { grid-template-columns: 1fr; } }

.mode-card {
    padding: 38px 36px 36px;
    border-radius: 24px;
    position: relative;
    overflow: hidden;
    transition: transform 0.25s, box-shadow 0.25s, border-color 0.25s;
    cursor: pointer;
    text-decoration: none;
    display: block;
    background: #ffffff;
    box-shadow: 0 4px 20px rgba(15, 23, 42, 0.06);
}
.mode-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 40px rgba(15, 23, 42, 0.12);
}
.mode-card.mock {
    background: linear-gradient(180deg, #ffffff 0%, #f8faff 100%);
    border: 1.5px solid #bfdbfe;
}
.mode-card.mock:hover {
    border-color: #3b82f6;
}
.mode-card.live {
    background: linear-gradient(180deg, #ffffff 0%, #f0f9ff 100%);
    border: 1.5px solid #bae6fd;
}
.mode-card.live:hover {
    border-color: #0284c7;
}
.mode-card.live::after {
    content: 'NEW';
    position: absolute;
    top: 22px; right: 22px;
    background: linear-gradient(90deg, #0284c7, #0ea5e9);
    color: #fff; font-size: 10px; font-weight: 800;
    padding: 4px 10px; border-radius: 100px; letter-spacing: 0.8px;
}
.mode-icon {
    width: 64px; height: 64px; border-radius: 18px;
    display: flex; align-items: center; justify-content: center;
    font-size: 26px; margin-bottom: 24px;
}
.mode-card.mock .mode-icon { background: #eff6ff; color: #2563eb; border: 1px solid #dbeafe; }
.mode-card.live .mode-icon { background: #f0f9ff; color: #0284c7; border: 1px solid #e0f2fe; }

.mode-title {
    font-size: 1.45rem; font-weight: 800;
    color: #0f172a; margin-bottom: 10px;
    letter-spacing: -0.3px;
}
.mode-desc {
    color: #475569; font-size: 0.93rem; line-height: 1.7;
    margin-bottom: 24px;
}
.mode-features {
    list-style: none; padding: 0; margin: 0 0 28px;
    display: flex; flex-direction: column; gap: 11px;
}
.mode-features li {
    display: flex; align-items: center; gap: 10px;
    font-size: 0.92rem; color: #1e293b; font-weight: 500;
}
.mode-features li i { font-size: 16px; flex-shrink: 0; }
.mode-card.mock .mode-features li i { color: #2563eb; }
.mode-card.live .mode-features li i { color: #0284c7; }

.mode-stat {
    display: flex; align-items: center; gap: 8px;
    font-size: 0.84rem; color: #64748b;
    padding-top: 18px; border-top: 1px solid #e2e8f0;
    margin-bottom: 24px;
}
.mode-stat strong { color: #0f172a; font-weight: 700; }

.mode-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 10px;
    height: 50px; width: 100%; border-radius: 12px;
    font-size: 15px; font-weight: 700; transition: all 0.2s ease;
    text-decoration: none;
}
.mode-card.mock .mode-btn {
    background: #2563eb; color: #ffffff; border: 1px solid #1d4ed8;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
}
.mode-card.mock:hover .mode-btn {
    background: #1d4ed8;
    box-shadow: 0 6px 18px rgba(37, 99, 235, 0.3);
}
.mode-card.live .mode-btn {
    background: #0284c7; color: #ffffff; border: 1px solid #0369a1;
    box-shadow: 0 4px 12px rgba(2, 132, 199, 0.2);
}
.mode-card.live:hover .mode-btn {
    background: #0369a1;
    box-shadow: 0 6px 18px rgba(2, 132, 199, 0.3);
}

.comparison-footer {
    margin-top: 32px;
    padding: 22px 28px;
    border-radius: 18px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    gap: 20px;
    align-items: center;
    text-align: center;
    font-size: 0.86rem;
    color: #475569;
}
.comparison-footer .vs {
    font-size: 1rem; font-weight: 800; color: #64748b;
    background: #f1f5f9; padding: 4px 12px; border-radius: 8px;
}
.comparison-footer .col strong { color: #0f172a; display: block; font-size: 0.92rem; margin-bottom: 4px; font-weight: 700; }
@media (max-width: 600px) {
    .comparison-footer { grid-template-columns: 1fr; }
    .comparison-footer .vs { display: none; }
}
</style>

<div class="choice-shell">
  <div class="choice-inner">

    <div class="choice-header" data-reveal>
      <span class="section-eyebrow">Interview Modes</span>
      <h1 class="section-title">Choose Your <span class="gradient-text">Interview Style</span></h1>
      <p>Pick the mode that suits your preparation goal. Both use real AI — one is text-based, the other is live voice.</p>
    </div>

    <div class="mode-grid" data-reveal>

      <!-- Mock Interview -->
      <a href="<?= BASE_URL ?>/module4/inter4.php" class="mode-card mock glass">
        <div class="mode-icon"><i class="bi bi-file-text-fill"></i></div>
        <div class="mode-title">Mock Interview</div>
        <div class="mode-desc">Answer questions by typing. AI generates targeted questions, evaluates each answer, and gives a full score report with improvement tips.</div>
        <ul class="mode-features">
          <li><i class="bi bi-check-circle-fill"></i> Text-based Q&amp;A format</li>
          <li><i class="bi bi-check-circle-fill"></i> Full AI scoring (0–10) per answer</li>
          <li><i class="bi bi-check-circle-fill"></i> Grammar &amp; structure feedback</li>
          <li><i class="bi bi-check-circle-fill"></i> PDF performance report</li>
          <li><i class="bi bi-check-circle-fill"></i> Choose category, company &amp; difficulty</li>
        </ul>
        <div class="mode-stat">
          <i class="bi bi-bar-chart-fill"></i>
          <span>You've completed <strong><?= $mockCompleted ?></strong> mock session<?= $mockCompleted != 1 ? 's' : '' ?></span>
        </div>
        <span class="mode-btn">
          <i class="bi bi-play-fill"></i> Start Mock Interview
        </span>
      </a>

      <!-- Live AI Interview -->
      <a href="<?= BASE_URL ?>/user/live_interview.php" class="mode-card live glass">
        <div class="mode-icon"><i class="bi bi-mic-fill"></i></div>
        <div class="mode-title">Live AI Interview</div>
        <div class="mode-desc">Have a real spoken conversation with an AI interviewer. The AI adapts to your answers in real time, just like a real interview. No typing required.</div>
        <ul class="mode-features">
          <li><i class="bi bi-check-circle-fill"></i> Real-time voice conversation</li>
          <li><i class="bi bi-check-circle-fill"></i> Camera + microphone interview room</li>
          <li><i class="bi bi-check-circle-fill"></i> Adaptive AI that detects struggle</li>
          <li><i class="bi bi-check-circle-fill"></i> AI voice via ElevenLabs TTS</li>
          <li><i class="bi bi-check-circle-fill"></i> Post-session evaluation &amp; roadmap</li>
        </ul>
        <div class="mode-stat">
          <i class="bi bi-mic-fill"></i>
          <span>You've completed <strong><?= $liveCompleted ?></strong> live session<?= $liveCompleted != 1 ? 's' : '' ?></span>
        </div>
        <span class="mode-btn">
          <i class="bi bi-mic-fill"></i> Start Live Interview
        </span>
      </a>

    </div>

    <!-- Comparison Footer -->
    <div class="comparison-footer" data-reveal>
      <div class="col">
        <strong>Best for...</strong>
        Practising specific topics, timed Q&amp;A, detailed score analytics
      </div>
      <div class="vs">VS</div>
      <div class="col">
        <strong>Best for...</strong>
        Real interview simulation, speaking confidence, viva &amp; HR prep
      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>