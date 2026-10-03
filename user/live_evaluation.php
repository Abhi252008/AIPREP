<?php
/**
 * Live AI Interview - Performance, Proctoring & Communication Review
 * URL: user/live_evaluation.php?id=X
 */
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$isAdmin = !empty($_SESSION['admin_id']);
$userId  = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0 && !$isAdmin) {
    set_flash('error', 'Please log in to continue.');
    redirect('/login.php');
}

$interviewId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$preparation = 'Live AI Interview';

if ($interviewId > 0) {
    try {
        if ($isAdmin) {
            $stmt = $pdo->prepare("SELECT role_name FROM live_interview_sessions WHERE id = ?");
            $stmt->execute([$interviewId]);
        } else {
            $stmt = $pdo->prepare("SELECT role_name FROM live_interview_sessions WHERE id = ? AND user_id = ?");
            $stmt->execute([$interviewId, $userId]);
        }
        $row = $stmt->fetch();
        if ($row && !empty($row['role_name'])) {
            $preparation = $row['role_name'];
        }
    } catch (\Throwable $e) {}
}

$pageTitle  = 'Performance Review';
$activePage = 'live-interview';
$csrfToken  = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Performance Review - AI Interview Prep</title>
<meta name="description" content="Your live interview performance review, proctoring concentration analysis, and communication report.">

<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: Inter, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
    background: #060608; color: #e6e6e6;
    min-height: 100vh; padding: 30px 20px; line-height: 1.6;
}
.container { max-width: 1080px; margin: 0 auto; }

/* Header */
.report-header {
    display: flex; justify-content: space-between; align-items: center;
    flex-wrap: wrap; gap: 15px; margin-bottom: 35px;
    padding-bottom: 25px; border-bottom: 1px solid rgba(255,255,255,0.08);
}
.report-title-group h1 {
    font-size: 26px; font-weight: 700; letter-spacing: -0.4px; color: #fff;
    display: flex; align-items: center; gap: 10px;
}
.back-link {
    display: inline-flex; align-items: center; gap: 6px;
    color: rgba(255,255,255,0.45); font-size: 13px; text-decoration: none;
    margin-bottom: 10px; transition: color 0.2s;
}
.back-link:hover { color: rgba(255,255,255,0.8); }
.badge-tag {
    font-size: 13px; padding: 4px 12px; border-radius: 100px;
    background: rgba(255,255,255,0.08); color: #aaa;
    border: 1px solid rgba(255,255,255,0.12); white-space: nowrap;
}
.header-actions { display: flex; gap: 12px; flex-wrap: wrap; }
.btn {
    padding: 10px 18px; border-radius: 12px; font-size: 14px; font-weight: 600;
    cursor: pointer; text-decoration: none; display: inline-flex; align-items: center;
    gap: 8px; transition: 0.2s; border: none; font-family: inherit;
}
.btn-primary { background: #fff; color: #060608; }
.btn-primary:hover { background: #e0e0e0; }
.btn-secondary { background: rgba(255,255,255,0.06); color: #fff; border: 1px solid rgba(255,255,255,0.12); }
.btn-secondary:hover { background: rgba(255,255,255,0.12); }

/* Loading State */
#loadingState { text-align: center; padding: 80px 20px; }
.spinner {
    width: 48px; height: 48px; border: 4px solid rgba(255,255,255,0.1);
    border-top-color: #fff; border-radius: 50%;
    animation: spin 0.9s linear infinite; margin: 0 auto 20px;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Glass Cards */
.glass-card {
    background: linear-gradient(145deg, rgba(255,255,255,0.05), rgba(255,255,255,0.02));
    border: 1px solid rgba(255,255,255,0.09); border-radius: 20px;
    padding: 28px; margin-bottom: 25px; backdrop-filter: blur(20px);
}
.card-header-flex {
    display: flex; justify-content: space-between; align-items: center;
    flex-wrap: wrap; gap: 10px; margin-bottom: 6px;
}
.section-desc {
    font-size: 13.5px; color: #94a3b8; margin-bottom: 20px; line-height: 1.5;
}

/* Hero Score Card */
.hero-card { display: grid; grid-template-columns: 200px 1fr; gap: 35px; align-items: center; }
@media (max-width: 768px) { .hero-card { grid-template-columns: 1fr; text-align: center; } }
.score-circle {
    width: 170px; height: 170px; border-radius: 50%;
    background: radial-gradient(circle at center, rgba(255,255,255,0.08) 0%, rgba(255,255,255,0.01) 70%);
    border: 3px solid rgba(255,255,255,0.18);
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    margin: 0 auto; box-shadow: 0 10px 40px rgba(0,0,0,0.5);
}
.score-num { font-size: 52px; font-weight: 800; color: #fff; line-height: 1; }
.score-label { font-size: 13px; color: #888; margin-top: 4px; text-transform: uppercase; letter-spacing: 1px; }
.readiness-pill {
    display: inline-block; padding: 6px 16px; border-radius: 100px; font-size: 13px;
    font-weight: 700; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px;
}
.readiness-ready { background: rgba(34,197,94,0.15); color: #4ade80; border: 1px solid rgba(34,197,94,0.3); }
.readiness-developing { background: rgba(234,179,8,0.15); color: #facc15; border: 1px solid rgba(234,179,8,0.3); }
.readiness-foundation { background: rgba(239,68,68,0.15); color: #f87171; border: 1px solid rgba(239,68,68,0.3); }
.readiness-not-attempted { background: rgba(148,163,184,0.15); color: #94a3b8; border: 1px solid rgba(148,163,184,0.3); }
.summary-text { font-size: 16px; color: #cfcfcf; line-height: 1.7; }

/* Verdict tags for turn-by-turn questions */
.verdict-tag {
    font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 100px;
    display: inline-flex; align-items: center; gap: 5px; white-space: nowrap;
}
.tag-correct { background: rgba(34,197,94,0.15); color: #4ade80; border: 1px solid rgba(34,197,94,0.3); }
.tag-unattempted { background: rgba(148,163,184,0.12); color: #94a3b8; border: 1px solid rgba(148,163,184,0.25); }
.tag-improvement { background: rgba(234,179,8,0.15); color: #facc15; border: 1px solid rgba(234,179,8,0.3); }

/* 3 Core Metrics Grid (Cleaned: Technical, Communication, Concentration) */
.metrics-grid-3 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 18px;
    margin-bottom: 25px;
}
.metric-card {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 16px;
    padding: 20px 22px;
}
.metric-header { display: flex; justify-content: space-between; font-size: 14px; color: #b0b0b0; margin-bottom: 10px; }
.metric-bar-bg { width: 100%; height: 8px; background: rgba(255,255,255,0.08); border-radius: 100px; overflow: hidden; }
.metric-bar-fill { height: 100%; border-radius: 100px; background: linear-gradient(90deg, #60a5fa, #38bdf8); transition: width 1s ease; }
.metric-note { font-size: 11.5px; color: #64748b; margin-top: 6px; display: block; }

/* Section Titles */
.section-title { font-size: 18px; font-weight: 700; color: #fff; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }

/* Badges */
.risk-badge {
    padding: 5px 14px; border-radius: 100px; font-size: 12px; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.5px; display: inline-flex; align-items: center; gap: 5px;
}
.risk-low { background: rgba(34,197,94,0.15); color: #4ade80; border: 1px solid rgba(34,197,94,0.35); }
.risk-mod { background: rgba(234,179,8,0.15); color: #facc15; border: 1px solid rgba(234,179,8,0.35); }
.risk-high { background: rgba(239,68,68,0.15); color: #f87171; border: 1px solid rgba(239,68,68,0.35); }

.fluency-badge {
    padding: 5px 14px; border-radius: 100px; font-size: 12px; font-weight: 700;
    background: rgba(167,139,250,0.15); color: #c4b5fd; border: 1px solid rgba(167,139,250,0.3);
    display: inline-flex; align-items: center; gap: 5px;
}

/* Stat Grid Boxes (Proctoring & Communication) */
.proctoring-grid, .comm-stats-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 16px; margin-bottom: 20px;
}
.stat-box {
    background: rgba(255,255,255,0.025); border: 1px solid rgba(255,255,255,0.07);
    border-radius: 14px; padding: 18px; display: flex; flex-direction: column; justify-content: space-between;
}
.stat-box.highlight-box {
    background: rgba(56,189,248,0.06); border-color: rgba(56,189,248,0.22);
}
.stat-box-title { font-size: 12px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; }
.stat-box-val { font-size: 28px; font-weight: 800; color: #fff; margin-top: 4px; }
.stat-subtext { font-size: 11.5px; color: #64748b; margin-top: 6px; display: block; line-height: 1.4; }

/* Filler chips */
.filler-chips-row { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.filler-chip {
    display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px;
    border-radius: 6px; font-size: 12px; font-weight: 600;
    background: rgba(245,158,11,0.12); color: #fbbf24; border: 1px solid rgba(245,158,11,0.25);
}

/* Verdict Banners */
.verdict-banner {
    padding: 12px 16px; border-radius: 10px; font-size: 13.5px; font-weight: 500;
    display: flex; align-items: center; gap: 8px; line-height: 1.5;
    background: rgba(59,130,246,0.08); border: 1px solid rgba(59,130,246,0.2); color: #93c5fd;
}
.verdict-banner.clean { background: rgba(34,197,94,0.08); border-color: rgba(34,197,94,0.2); color: #86efac; }
.verdict-banner.concern { background: rgba(234,179,8,0.08); border-color: rgba(234,179,8,0.2); color: #fde047; }
.verdict-banner.danger { background: rgba(239,68,68,0.08); border-color: rgba(239,68,68,0.2); color: #fca5a5; }

/* Two column */
.two-column-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 25px; }
@media (max-width: 768px) { .two-column-grid { grid-template-columns: 1fr; } }
.insight-list { list-style: none; }
.insight-list li { position: relative; padding-left: 24px; margin-bottom: 12px; color: #d1d1d1; font-size: 14px; line-height: 1.6; }
.insight-list.strengths li::before { content: "\2713"; position: absolute; left: 0; color: #4ade80; font-weight: bold; }
.insight-list.weaknesses li::before { content: "\26A0"; position: absolute; left: 0; color: #f87171; font-weight: bold; }
.insight-list.observations li::before { content: "\2022"; position: absolute; left: 6px; color: #38bdf8; font-size: 18px; }

.speech-critique-box {
    margin-top: 18px; padding: 14px 16px; border-radius: 12px;
    background: rgba(255,255,255,0.025); border: 1px solid rgba(255,255,255,0.06);
    font-size: 13.5px; color: #b5b5b5;
}

/* Roadmap */
.roadmap-item { background: rgba(255,255,255,0.025); border: 1px solid rgba(255,255,255,0.07); border-radius: 14px; padding: 20px; margin-bottom: 16px; }
.roadmap-topic { font-size: 16px; font-weight: 700; color: #fff; margin-bottom: 6px; }
.roadmap-why { font-size: 14px; color: #a8a8a8; margin-bottom: 12px; }
.concept-tags { display: flex; flex-wrap: wrap; gap: 8px; }
.concept-tag { font-size: 12px; padding: 4px 10px; border-radius: 6px; background: rgba(56,189,248,0.1); color: #7dd3fc; border: 1px solid rgba(56,189,248,0.2); }

/* Practice Qs */
.practice-q-card { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 12px; padding: 14px 18px; margin-bottom: 10px; font-size: 14px; color: #e0e0e0; display: flex; gap: 12px; align-items: flex-start; }
.practice-q-num { background: rgba(255,255,255,0.08); color: #fff; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; flex-shrink: 0; }

/* Turn by Turn */
.turn-card { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 14px; padding: 18px 20px; margin-bottom: 16px; }
.turn-q { font-weight: 600; color: #fff; font-size: 15px; margin-bottom: 8px; }
.turn-a { color: #9ca3af; font-size: 14px; margin-bottom: 12px; padding-left: 12px; border-left: 2px solid rgba(255,255,255,0.2); }
.turn-guide { background: rgba(34,197,94,0.05); border: 1px solid rgba(34,197,94,0.15); border-radius: 8px; padding: 10px 14px; font-size: 13px; color: #86efac; }

/* Color utilities */
.text-green { color: #4ade80 !important; }
.text-yellow { color: #fbbf24 !important; }
.text-red { color: #f87171 !important; }
.bg-green { background: linear-gradient(90deg, #22c55e, #4ade80) !important; }
.bg-yellow { background: linear-gradient(90deg, #f59e0b, #fbbf24) !important; }
.bg-purple { background: linear-gradient(90deg, #8b5cf6, #a78bfa) !important; }

@media print {
    body { background: #fff; color: #000; }
    .header-actions, .btn, .back-link { display: none !important; }
    .glass-card, .metric-card, .roadmap-item, .stat-box { border: 1px solid #ddd; background: #fdfdfd !important; color: #000 !important; }
}
</style>
</head>
<body>

<div class="container">

    <a href="<?= BASE_URL ?>/user/dashboard.php" class="back-link">
        <i class="bi bi-arrow-left"></i> Back to Dashboard
    </a>

    <!-- Header -->
    <header class="report-header">
        <div class="report-title-group">
            <h1><i class="bi bi-bar-chart-fill" style="color:#60a5fa;"></i> Performance, Proctoring &amp; Speech Review</h1>
            <span class="badge-tag"><?= e($preparation) ?></span>
        </div>
        <div class="header-actions">
            <button class="btn btn-secondary" onclick="window.print()">
                <i class="bi bi-printer"></i> Print / PDF
            </button>
            <a href="<?= BASE_URL ?>/user/live_interview.php" class="btn btn-primary">
                <i class="bi bi-mic-fill"></i> Practice Again
            </a>
        </div>
    </header>

    <!-- Loading State with animated steps -->
    <div id="loadingState">
        <style>
        #loadingState { text-align:center; padding:70px 20px; }
        .eval-progress-wrap { max-width:420px; margin:0 auto; }
        .eval-spinner { width:52px; height:52px; border:4px solid rgba(255,255,255,0.08); border-top-color:#60a5fa; border-radius:50%; animation:spin 0.85s linear infinite; margin:0 auto 28px; }
        @keyframes spin { to { transform:rotate(360deg); } }
        .eval-title { font-size:20px; font-weight:700; color:#fff; margin-bottom:6px; }
        .eval-subtitle { font-size:13px; color:#64748b; margin-bottom:28px; }
        .eval-steps { list-style:none; text-align:left; margin-bottom:24px; }
        .eval-steps li { display:flex; align-items:center; gap:10px; padding:8px 12px; border-radius:10px; font-size:13.5px; color:#64748b; margin-bottom:6px; transition:all 0.4s; }
        .eval-steps li.active { background:rgba(96,165,250,0.08); color:#93c5fd; border:1px solid rgba(96,165,250,0.15); }
        .eval-steps li.done { color:#4ade80; }
        .eval-steps li .step-icon { font-size:16px; width:22px; flex-shrink:0; }
        .eval-steps li.active .step-icon { animation:pulse-icon 1s ease-in-out infinite alternate; }
        @keyframes pulse-icon { from { opacity:0.6; } to { opacity:1; } }
        .eval-progress-bar-bg { height:5px; background:rgba(255,255,255,0.06); border-radius:100px; overflow:hidden; margin-top:4px; }
        .eval-progress-bar-fill { height:100%; background:linear-gradient(90deg,#3b82f6,#60a5fa); border-radius:100px; width:0%; transition:width 1.5s ease; }
        .eval-eta { font-size:12px; color:#475569; margin-top:10px; }
        </style>
        <div class="eval-progress-wrap">
            <div class="eval-spinner"></div>
            <div class="eval-title">Generating Your Performance Report</div>
            <div class="eval-subtitle">AI is reviewing your answers, camera focus &amp; speech clarity...</div>
            <ul class="eval-steps" id="evalSteps">
                <li id="step1" class="active"><span class="step-icon">🔍</span> Analyzing your answers &amp; technical accuracy</li>
                <li id="step2"><span class="step-icon">📷</span> Reviewing camera focus &amp; concentration data</li>
                <li id="step3"><span class="step-icon">🎙️</span> Evaluating speech clarity &amp; hesitations</li>
                <li id="step4"><span class="step-icon">📊</span> Calculating overall performance score</li>
                <li id="step5"><span class="step-icon">📝</span> Building your personalized learning roadmap</li>
            </ul>
            <div class="eval-progress-bar-bg">
                <div class="eval-progress-bar-fill" id="evalProgressBar"></div>
            </div>
            <div class="eval-eta" id="evalEta">Estimated time: 8–15 seconds...</div>
        </div>
    </div>

    <!-- Report Body -->
    <main id="reportBody" style="display:none;">

        <!-- Hero Overall Performance Card -->
        <div class="glass-card hero-card">
            <div class="score-circle">
                <span class="score-num" id="scoreNum">--</span>
                <span class="score-label">Overall Score</span>
            </div>
            <div>
                <span class="readiness-pill" id="readinessPill">Analyzing</span>
                <p class="summary-text" id="summaryText"></p>
            </div>
        </div>

        <!-- 3 Core Performance Metrics -->
        <div class="metrics-grid-3">
            <div class="metric-card">
                <div class="metric-header"><span>Technical Accuracy</span><strong id="techVal">--%</strong></div>
                <div class="metric-bar-bg"><div class="metric-bar-fill" id="techBar" style="width:0%;"></div></div>
                <span class="metric-note">Correctness of answers &amp; domain knowledge</span>
            </div>
            <div class="metric-card">
                <div class="metric-header"><span>Communication &amp; Speech Clarity</span><strong id="commVal">--%</strong></div>
                <div class="metric-bar-bg"><div class="metric-bar-fill bg-purple" id="commBar" style="width:0%;"></div></div>
                <span class="metric-note">Speech fluency, clarity &amp; verbal hesitation analysis</span>
            </div>
            <div class="metric-card">
                <div class="metric-header"><span>Focus &amp; Concentration</span><strong id="focusVal">--%</strong></div>
                <div class="metric-bar-bg"><div class="metric-bar-fill bg-green" id="focusBar" style="width:0%;"></div></div>
                <span class="metric-note">Direct camera gaze vs side glances</span>
            </div>
        </div>

        <!-- Focus, Concentration & Cheating Detection Card -->
        <div class="glass-card" id="proctoringCard">
            <div class="card-header-flex">
                <h3 class="section-title" style="margin-bottom:0;">
                    <i class="bi bi-shield-check" style="color:#38bdf8;"></i> Focus, Concentration &amp; Cheating Detection
                </h3>
                <span class="risk-badge risk-low" id="cheatingRiskBadge"><i class="bi bi-shield-fill-check"></i> Low Cheating Risk</span>
            </div>
            <p class="section-desc">Real-time analysis of head pose, camera gaze, and whether candidate maintained direct eye contact or looked to the sides (suspicion of notes, dual monitors, or distraction).</p>

            <div class="proctoring-grid">
                <div class="stat-box highlight-box">
                    <div class="stat-box-title">Concentration Score</div>
                    <div class="stat-box-val" id="concentrationVal">--%</div>
                    <div class="metric-bar-bg" style="margin-top:8px;"><div class="metric-bar-fill bg-green" id="concentrationBar" style="width:0%;"></div></div>
                    <span class="stat-subtext">Overall focus &amp; attentiveness score</span>
                </div>

                <div class="stat-box">
                    <div class="stat-box-title">Facing Camera Straight</div>
                    <div class="stat-box-val text-green" id="facingCameraVal">--%</div>
                    <div class="metric-bar-bg" style="margin-top:8px;"><div class="metric-bar-fill bg-green" id="facingCameraBar" style="width:0%;"></div></div>
                    <span class="stat-subtext">Direct eye contact with interviewer</span>
                </div>

                <div class="stat-box">
                    <div class="stat-box-title">Looking Sides / Away</div>
                    <div class="stat-box-val text-yellow" id="lookingSidesVal">--%</div>
                    <div class="metric-bar-bg" style="margin-top:8px;"><div class="metric-bar-fill bg-yellow" id="lookingSidesBar" style="width:0%;"></div></div>
                    <span class="stat-subtext">Side glances or off-camera pauses</span>
                </div>

                <div class="stat-box">
                    <div class="stat-box-title">Look-Away Warnings</div>
                    <div class="stat-box-val" id="warningsVal">0</div>
                    <span class="stat-subtext">Sustained head turns (>2 seconds)</span>
                </div>
            </div>

            <div class="proctoring-details-box">
                <div class="verdict-banner clean" id="proctoringVerdictBanner">
                    <i class="bi bi-info-circle-fill"></i> <span id="proctoringVerdictText">Evaluating camera concentration...</span>
                </div>
                <ul class="insight-list observations" id="proctoringObservationsList" style="margin-top:14px;"></ul>
            </div>
        </div>

        <!-- Communication & Speech Fluency Card -->
        <div class="glass-card" id="communicationCard">
            <div class="card-header-flex">
                <h3 class="section-title" style="margin-bottom:0;">
                    <i class="bi bi-mic-fill" style="color:#a78bfa;"></i> Communication &amp; Speech Clarity Analysis
                </h3>
                <span class="fluency-badge" id="fluencyBadge"><i class="bi bi-chat-dots-fill"></i> Fluent</span>
            </div>
            <p class="section-desc">Auditory clarity assessment evaluating verbal pacing, articulation, and deduction impact of thinking filler sounds and verbal hesitations.</p>

            <div class="comm-stats-grid">
                <div class="stat-box highlight-box">
                    <div class="stat-box-title">Speech Clarity Score</div>
                    <div class="stat-box-val" id="commClarityVal">--%</div>
                    <div class="metric-bar-bg" style="margin-top:8px;"><div class="metric-bar-fill bg-purple" id="commClarityBar" style="width:0%;"></div></div>
                    <span class="stat-subtext">Communication score factoring fluency &amp; verbal hesitation</span>
                </div>

                <div class="stat-box">
                    <div class="stat-box-title">Thinking Fillers &amp; Hesitations</div>
                    <div class="stat-box-val text-yellow" id="fillerCountVal">0</div>
                    <span class="stat-subtext">Audible thinking sounds detected</span>
                    <div class="filler-chips-row" id="fillerChipsRow"></div>
                </div>

                <div class="stat-box">
                    <div class="stat-box-title">Total Words Spoken</div>
                    <div class="stat-box-val" id="totalWordsVal">--</div>
                    <span class="stat-subtext">Verbal volume &amp; response depth</span>
                </div>
            </div>

            <div class="two-column-grid" style="margin-top:18px;margin-bottom:0;">
                <div class="speech-critique-box" style="margin-top:0;">
                    <div style="font-weight:700;color:#fff;margin-bottom:6px;">
                        <i class="bi bi-chat-quote-fill" style="color:#60a5fa;"></i> Speech Delivery &amp; Hesitation Analysis
                    </div>
                    <p id="speechCritiqueText" style="color:#d1d1d1;font-size:14px;line-height:1.6;"></p>
                </div>
                <div class="speech-critique-box" style="margin-top:0;background:rgba(167,139,250,0.06);border-color:rgba(167,139,250,0.18);">
                    <div style="font-weight:700;color:#c4b5fd;margin-bottom:6px;">
                        <i class="bi bi-lightbulb-fill" style="color:#fbbf24;"></i> Fluency Coaching Advice
                    </div>
                    <p id="speechAdviceText" style="color:#e2e8f0;font-size:14px;line-height:1.6;"></p>
                </div>
            </div>
        </div>

        <!-- Strengths vs Weaknesses -->
        <div class="two-column-grid">
            <div class="glass-card">
                <h3 class="section-title"><i class="bi bi-star-fill text-warning"></i> Technical Strengths Observed</h3>
                <ul class="insight-list strengths" id="strengthsList"></ul>
                <div class="speech-critique-box" id="deliveryBox">
                    <strong>Speech &amp; Delivery:</strong>
                    <p id="deliveryText" style="margin-top:4px;"></p>
                </div>
            </div>
            <div class="glass-card">
                <h3 class="section-title"><i class="bi bi-bullseye text-danger"></i> Weak Areas &amp; Technical Gaps</h3>
                <ul class="insight-list weaknesses" id="weaknessList"></ul>
                <div class="speech-critique-box" id="confidenceBox">
                    <strong>Observable Articulation:</strong>
                    <p id="confidenceText" style="margin-top:4px;"></p>
                </div>
            </div>
        </div>

        <!-- Learning Roadmap -->
        <div class="glass-card">
            <h3 class="section-title"><i class="bi bi-journal-bookmark-fill text-primary"></i> Curated Development Roadmap - What to Learn Next</h3>
            <p style="color:#999;font-size:14px;margin-bottom:20px;">Based on where you hesitated or encountered technical limits, master these specific concepts to excel in your next interview.</p>
            <div id="roadmapContainer"></div>
        </div>

        <!-- Practice Questions -->
        <div class="glass-card">
            <h3 class="section-title"><i class="bi bi-lightning-charge-fill text-warning"></i> Recommended Practice Questions for Next Round</h3>
            <div id="practiceQuestionsContainer"></div>
        </div>

        <!-- Q by Q Review -->
        <div class="glass-card" id="qReviewCard">
            <h3 class="section-title"><i class="bi bi-search text-info"></i> Turn-by-Turn Question Analysis</h3>
            <div id="turnsContainer"></div>
        </div>

    </main>

</div>

<script>
const interviewId = <?= json_encode($interviewId) ?>;
const fallbackPrep = <?= json_encode($preparation) ?>;
const BASE_URL = "<?= BASE_URL ?>";
const CSRF_TOKEN = "<?= e($csrfToken) ?>";

async function loadEvaluation() {
    // Start animated progress stepper
    const stepIds = ['step1','step2','step3','step4','step5'];
    const progressBar = document.getElementById('evalProgressBar');
    const etaEl = document.getElementById('evalEta');
    let currentStep = 0;
    const startMs = Date.now();

    function advanceStep() {
        if (currentStep < stepIds.length) {
            // Mark previous step done
            if (currentStep > 0) {
                const prev = document.getElementById(stepIds[currentStep - 1]);
                if (prev) { prev.classList.remove('active'); prev.classList.add('done'); prev.querySelector('.step-icon').textContent = '✅'; }
            }
            const cur = document.getElementById(stepIds[currentStep]);
            if (cur) cur.classList.add('active');
            // Advance progress bar
            const pct = Math.round(((currentStep + 1) / stepIds.length) * 90);
            if (progressBar) progressBar.style.width = pct + '%';
            currentStep++;
        }
        const elapsed = Math.round((Date.now() - startMs) / 1000);
        if (etaEl) etaEl.textContent = `Processing... ${elapsed}s elapsed`;
    }

    advanceStep(); // Start step 1 immediately
    const stepTimer = setInterval(advanceStep, 2200);

    try {
        let history = [];
        let proctoring = {};
        let speechStats = {};

        try {
            const rawHist = sessionStorage.getItem("last_interview_history");
            if (rawHist) history = JSON.parse(rawHist);
        } catch(e) {}

        try {
            const rawProc = sessionStorage.getItem("last_interview_proctoring");
            if (rawProc) proctoring = JSON.parse(rawProc);
        } catch(e) {}

        try {
            const rawSpeech = sessionStorage.getItem("last_interview_speech");
            if (rawSpeech) speechStats = JSON.parse(rawSpeech);
        } catch(e) {}

        const response = await fetch(BASE_URL + "/api/live_generate_evaluation.php", {
            method: "POST",
            headers: { "Content-Type": "application/json", "X-CSRF-Token": CSRF_TOKEN },
            body: JSON.stringify({
                interview_id: interviewId,
                preparation: fallbackPrep,
                history: history,
                proctoring: proctoring,
                speech_stats: speechStats
            })
        });

        const data = await response.json();
        if (!response.ok || !data.success || !data.evaluation) throw new Error(data.message || "Could not generate evaluation.");

        clearInterval(stepTimer);
        if (progressBar) progressBar.style.width = '100%';
        // Mark all steps done instantly
        stepIds.forEach(id => {
            const el = document.getElementById(id);
            if (el) { el.classList.remove('active'); el.classList.add('done'); const ic = el.querySelector('.step-icon'); if(ic) ic.textContent = '✅'; }
        });
        setTimeout(() => renderEvaluation(data.evaluation), 350);

    } catch (err) {
        clearInterval(stepTimer);
        if (progressBar) progressBar.style.width = '100%';
        console.error("Evaluation load error:", err);
        document.getElementById("loadingState").innerHTML = `
            <div style="color:#f87171;font-size:16px;margin-bottom:12px;"><i class="bi bi-exclamation-triangle"></i> Failed to load evaluation report.</div>
            <p style="color:#888;font-size:14px;margin-bottom:20px;">${err.message}</p>
            <a href="${BASE_URL}/user/live_interview.php" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:6px;"><i class="bi bi-arrow-repeat"></i> Practice Again</a>
        `;
    }
}

function cleanTextOfInformalWords(str) {
    if (!str) return "";
    return String(str)
        .replace(/\bhuu\s+huu\b/gi, "verbal hesitations")
        .replace(/\b(huu|hmm)\b/gi, "vocal pauses");
}

function renderEvaluation(ev) {
    document.getElementById("loadingState").style.display  = "none";
    document.getElementById("reportBody").style.display    = "block";

    // 1. Overall Score & Readiness (Properly handle 0 score for unattempted sessions)
    const score = (ev.overall_score !== undefined && ev.overall_score !== null) ? Number(ev.overall_score) : 0;
    document.getElementById("scoreNum").textContent = score;

    const pill = document.getElementById("readinessPill");
    const isZero = score === 0 || (ev.readiness_level && ev.readiness_level.toLowerCase().includes("not attempted"));
    const readiness = isZero ? "Not Attempted" : (ev.readiness_level || (score >= 80 ? "Job Ready" : score >= 60 ? "Developing Candidate" : "Needs Foundational Practice"));
    pill.textContent = readiness;
    pill.className   = "readiness-pill " + (isZero ? "readiness-not-attempted" : (score >= 80 ? "readiness-ready" : score >= 60 ? "readiness-developing" : "readiness-foundation"));
    document.getElementById("summaryText").textContent = cleanTextOfInformalWords(ev.summary || "");

    // 2. Three Core Metrics (Technical, Communication, Focus) - No arbitrary 70 floors on 0
    const m = ev.metrics || {};
    const techAccuracy = (m.technical_accuracy !== undefined && m.technical_accuracy !== null) ? Number(m.technical_accuracy) : 0;
    const commClarity  = (m.communication_clarity !== undefined && m.communication_clarity !== null) ? Number(m.communication_clarity) : 0;
    const focusScore   = (m.concentration_focus !== undefined && m.concentration_focus !== null) ? Number(m.concentration_focus) : (ev.proctoring_analysis?.concentration_score !== undefined ? Number(ev.proctoring_analysis.concentration_score) : 85);

    setMetric("techVal",  "techBar",  techAccuracy);
    setMetric("commVal",  "commBar",  commClarity);
    setMetric("focusVal", "focusBar", focusScore);

    // 3. Proctoring & Concentration Analysis
    const proc = ev.proctoring_analysis || {};
    const concScore = proc.concentration_score !== undefined ? Number(proc.concentration_score) : focusScore;
    const facingPct = proc.facing_camera_pct !== undefined ? Number(proc.facing_camera_pct) : 90;
    const sidesPct  = proc.looking_sides_pct !== undefined ? Number(proc.looking_sides_pct) : 7;
    const warnings  = proc.warnings_count !== undefined ? Number(proc.warnings_count) : 0;
    const risk      = proc.cheating_risk || (concScore >= 80 ? "Low Risk" : concScore >= 60 ? "Moderate Risk" : "High Risk");

    document.getElementById("concentrationVal").textContent = concScore + "%";
    setTimeout(() => { const el = document.getElementById("concentrationBar"); if (el) el.style.width = concScore + "%"; }, 200);

    document.getElementById("facingCameraVal").textContent = facingPct + "%";
    setTimeout(() => { const el = document.getElementById("facingCameraBar"); if (el) el.style.width = facingPct + "%"; }, 200);

    document.getElementById("lookingSidesVal").textContent = sidesPct + "%";
    setTimeout(() => { const el = document.getElementById("lookingSidesBar"); if (el) el.style.width = sidesPct + "%"; }, 200);

    document.getElementById("warningsVal").textContent = warnings;

    const riskBadge = document.getElementById("cheatingRiskBadge");
    if (riskBadge) {
        if (risk.toLowerCase().includes("low")) {
            riskBadge.className = "risk-badge risk-low";
            riskBadge.innerHTML = '<i class="bi bi-shield-fill-check"></i> Low Cheating Risk';
        } else if (risk.toLowerCase().includes("mod")) {
            riskBadge.className = "risk-badge risk-mod";
            riskBadge.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i> Moderate Cheating Concern';
        } else {
            riskBadge.className = "risk-badge risk-high";
            riskBadge.innerHTML = '<i class="bi bi-shield-fill-x"></i> High Cheating Risk';
        }
    }

    const verdictBanner = document.getElementById("proctoringVerdictBanner");
    const verdictText   = document.getElementById("proctoringVerdictText");
    if (verdictText) verdictText.textContent = proc.verdict || "Candidate maintained consistent camera gaze with minimal distractions.";
    if (verdictBanner) {
        verdictBanner.className = "verdict-banner " + (risk.toLowerCase().includes("low") ? "clean" : risk.toLowerCase().includes("mod") ? "concern" : "danger");
    }

    const obsList = document.getElementById("proctoringObservationsList");
    if (obsList) {
        obsList.innerHTML = "";
        (proc.observations || [
            `Faced camera directly during ${facingPct}% of speaking and listening turns.`,
            sidesPct > 15 ? `Looked away to the sides during ${sidesPct}% of turns - avoid external notes or secondary monitors.` : `Minimal side glances (${sidesPct}%); demonstrated calm camera focus.`
        ]).forEach(obs => {
            const li = document.createElement("li");
            li.textContent = cleanTextOfInformalWords(obs);
            obsList.appendChild(li);
        });
    }

    // 4. Communication & Speech Clarity Analysis (Without informal filler words)
    const comm = ev.communication_analysis || {};
    const clarityScore = comm.clarity_score !== undefined ? Number(comm.clarity_score) : commClarity;
    document.getElementById("commClarityVal").textContent = clarityScore + "%";
    setTimeout(() => { const el = document.getElementById("commClarityBar"); if (el) el.style.width = clarityScore + "%"; }, 200);

    const fBadge = document.getElementById("fluencyBadge");
    if (fBadge) fBadge.textContent = cleanTextOfInformalWords(comm.fluency_level || (comm.filler_word_count > 0 ? "Hesitations Detected" : "Fluent & Clear"));

    const fillerTotal = comm.filler_word_count !== undefined ? comm.filler_word_count : 0;
    document.getElementById("fillerCountVal").textContent = fillerTotal;

    const chipsRow = document.getElementById("fillerChipsRow");
    if (chipsRow) {
        chipsRow.innerHTML = "";
        if (fillerTotal > 0) {
            const chip = document.createElement("span");
            chip.className = "filler-chip";
            chip.innerHTML = `<i class="bi bi-soundwave"></i> ${fillerTotal} verbal hesitation pause${fillerTotal === 1 ? '' : 's'} recorded`;
            chipsRow.appendChild(chip);
        } else {
            const chip = document.createElement("span");
            chip.className = "filler-chip";
            chip.style.borderColor = "rgba(34,197,94,0.3)";
            chip.style.color = "#4ade80";
            chip.style.background = "rgba(34,197,94,0.1)";
            chip.innerHTML = `<i class="bi bi-check-circle-fill"></i> Zero hesitation fillers detected`;
            chipsRow.appendChild(chip);
        }
    }

    document.getElementById("totalWordsVal").textContent = comm.total_words !== undefined ? comm.total_words : (ev.speech_stats?.total_words !== undefined ? ev.speech_stats.total_words : 0);
    document.getElementById("speechCritiqueText").textContent = cleanTextOfInformalWords(
        comm.speech_critique || "Spoke with understandable clarity. Minimizing vocal thinking pauses will project greater professional confidence."
    );
    document.getElementById("speechAdviceText").textContent = cleanTextOfInformalWords(
        comm.advice || "When thinking through an answer, pause silently for 2 seconds instead of vocalizing thinking sounds. Silence signals deep composure."
    );

    // 5. Strengths & Weaknesses
    const sl = document.getElementById("strengthsList"); sl.innerHTML = "";
    (ev.how_you_are_doing || []).forEach(item => { const li = document.createElement("li"); li.textContent = cleanTextOfInformalWords(item); sl.appendChild(li); });

    const sp = ev.how_you_are_speaking || {};
    document.getElementById("deliveryText").textContent    = cleanTextOfInformalWords(sp.delivery_critique || "");
    document.getElementById("confidenceText").textContent  = cleanTextOfInformalWords(sp.observable_confidence || "");

    const wl = document.getElementById("weaknessList"); wl.innerHTML = "";
    (ev.weak_areas || []).forEach(item => { const li = document.createElement("li"); li.textContent = cleanTextOfInformalWords(item); wl.appendChild(li); });

    // 6. Learning Roadmap
    const rc = document.getElementById("roadmapContainer"); rc.innerHTML = "";
    (ev.topics_to_learn || []).forEach(t => {
        const d = document.createElement("div"); d.className = "roadmap-item";
        d.innerHTML = `<div class="roadmap-topic">${esc(t.topic||"Core Topic")}</div><div class="roadmap-why">${esc(cleanTextOfInformalWords(t.why_learn||""))}</div><div class="concept-tags">${(t.key_concepts||[]).map(c=>`<span class="concept-tag">${esc(c)}</span>`).join("")}</div>`;
        rc.appendChild(d);
    });

    // 7. Practice Questions
    const pq = document.getElementById("practiceQuestionsContainer"); pq.innerHTML = "";
    (ev.recommended_practice_questions || []).forEach((q,i) => {
        const c = document.createElement("div"); c.className = "practice-q-card";
        c.innerHTML = `<div class="practice-q-num">${i+1}</div><div>${esc(q)}</div>`;
        pq.appendChild(c);
    });

    // 8. Turn-by-Turn Question Analysis with Full Credit (100%) Verdict Badges
    const tc = document.getElementById("turnsContainer"); tc.innerHTML = "";
    const reviews = ev.question_by_question_review || [];
    if (!reviews.length) {
        document.getElementById("qReviewCard").style.display = "none";
    } else {
        document.getElementById("qReviewCard").style.display = "block";
        reviews.forEach((r, idx) => {
            const c = document.createElement("div"); c.className = "turn-card";
            const v = String(r.verdict || "").toLowerCase();
            let verdictBadge = "";

            if (v.includes("fully correct") || v.includes("strong") || v.includes("correct") || v.includes("relevant")) {
                verdictBadge = '<span class="verdict-tag tag-correct"><i class="bi bi-check-circle-fill"></i> Fully Correct (100%)</span>';
            } else if (v.includes("unattempted") || v.includes("skip")) {
                verdictBadge = '<span class="verdict-tag tag-unattempted"><i class="bi bi-dash-circle"></i> Not Attempted (0%)</span>';
            } else {
                verdictBadge = '<span class="verdict-tag tag-improvement"><i class="bi bi-exclamation-circle"></i> Needs Practice</span>';
            }

            const candidateAnswerText = (r.candidate_answer && r.candidate_answer.trim() !== "") ? r.candidate_answer : "No answer provided (Skipped)";

            c.innerHTML = `
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:8px;flex-wrap:wrap;">
                    <div class="turn-q" style="margin-bottom:0;">Q${idx + 1}: ${esc(r.question || "")}</div>
                    ${verdictBadge}
                </div>
                <div class="turn-a"><strong>Your Response:</strong> "${esc(candidateAnswerText)}"</div>
                ${r.feedback ? `<div style="font-size:13.5px;color:#cbd5e1;margin-bottom:10px;padding-left:12px;"><i class="bi bi-chat-left-text" style="color:#38bdf8;margin-right:6px;"></i>${esc(cleanTextOfInformalWords(r.feedback))}</div>` : ""}
                ${r.ideal_answer_guide ? `<div class="turn-guide"><strong><i class="bi bi-lightbulb-fill" style="color:#fbbf24;"></i> Recommended Answer Guide:</strong> ${esc(r.ideal_answer_guide)}</div>` : ""}
            `;
            tc.appendChild(c);
        });
    }
}

function setMetric(valId, barId, pct) {
    const p = Math.max(0, Math.min(100, pct));
    const valEl = document.getElementById(valId);
    if (valEl) valEl.textContent = p + "%";
    setTimeout(() => { const el = document.getElementById(barId); if (el) el.style.width = p + "%"; }, 200);
}
function esc(text) { const d = document.createElement("div"); d.textContent = String(text||""); return d.innerHTML; }

window.addEventListener("DOMContentLoaded", loadEvaluation);
</script>

</body>
</html>
