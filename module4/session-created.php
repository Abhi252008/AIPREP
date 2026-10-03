<?php
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

$sessionId = filter_input(INPUT_GET, 'session_id', FILTER_VALIDATE_INT);
if (!$sessionId) {
    redirect('/module4/inter4.php');
}

$stmt = $pdo->prepare(
    'SELECT s.id, s.role_target, s.difficulty_level, s.company_name,
            s.status, s.started_at, c.name AS category_name
     FROM interview_sessions s
     INNER JOIN categories c ON c.id = s.category_id
     WHERE s.id = ? AND s.user_id = ?
     LIMIT 1'
);
$stmt->execute([$sessionId, (int)$_SESSION['user_id']]);
$session = $stmt->fetch();

if (!$session) {
    http_response_code(404);
    exit('Interview session not found.');
}

$interviewUrl = BASE_URL . '/user/interview.php?session_id=' . (int)$session['id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Session Created | AI Interview Prep</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@600;700&display=swap');

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    min-height: 100vh;
    display: grid; place-items: center;
    background: #f8fafc; color: #0f172a;
    font-family: 'Inter', sans-serif;
}

/* ── Card ── */
.card {
    width: min(580px, 92%);
    padding: 40px;
    border: 1px solid #e2e8f0; border-radius: 20px;
    background: #ffffff;
    box-shadow: 0 10px 30px -5px rgba(15,23,42,.06), 0 4px 6px -2px rgba(15,23,42,.03);
}
.ok {
    font-size: 28px; color: #059669;
    width: 64px; height: 64px; border-radius: 16px;
    background: #ecfdf5; display: flex; align-items: center;
    justify-content: center; margin-bottom: 20px;
}
h1 {
    font-family: 'Space Grotesk', sans-serif;
    font-size: 24px; font-weight: 700;
    margin: 0 0 8px; letter-spacing: -.02em; color: #0f172a;
}
.muted { color: #64748b; font-size: 15px; margin: 0 0 24px; }
.row {
    display: flex; justify-content: space-between;
    padding: 14px 0; border-bottom: 1px solid #f1f5f9; font-size: 14px;
}
.row span { color: #64748b; }
.row strong { color: #0f172a; font-weight: 600; }
.actions { display: flex; gap: 12px; margin-top: 28px; flex-wrap: wrap; }

/* ── Buttons ── */
.btn-primary {
    display: inline-flex; align-items: center; justify-content: center;
    padding: 12px 24px; border-radius: 10px;
    border: none; cursor: pointer;
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    color: #fff; font-weight: 600; font-size: 14px;
    font-family: 'Inter', sans-serif;
    box-shadow: 0 4px 14px rgba(37,99,235,.25);
    transition: transform .2s, box-shadow .2s;
}
.btn-primary:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(37,99,235,.35); }
.btn-primary:disabled { opacity: .65; cursor: not-allowed; transform: none; }
.btn-secondary {
    display: inline-flex; align-items: center; justify-content: center;
    padding: 12px 20px; border-radius: 10px;
    background: #fff; border: 1px solid #e2e8f0; color: #475569;
    text-decoration: none; font-weight: 600; font-size: 14px;
    transition: background .2s, border-color .2s;
}
.btn-secondary:hover { background: #f8fafc; border-color: #cbd5e1; color: #0f172a; }

/* ════════════════════════════════════════
   PROGRESS OVERLAY
════════════════════════════════════════ */
#genOverlay {
    display: none;
    position: fixed; inset: 0; z-index: 9999;
    background: rgba(8, 12, 30, 0.80);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    align-items: center; justify-content: center;
}
#genOverlay.active { display: flex; animation: fadeIn .35s ease; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

.gen-card {
    background: rgba(255,255,255,.07);
    border: 1px solid rgba(255,255,255,.13);
    border-radius: 24px; padding: 3rem 3.5rem;
    max-width: 500px; width: 90%; text-align: center;
    box-shadow: 0 40px 100px rgba(0,0,0,.5);
    animation: slideUp .4s cubic-bezier(.34,1.56,.64,1);
}
@keyframes slideUp {
    from { transform: translateY(44px) scale(.94); opacity: 0; }
    to   { transform: translateY(0)    scale(1);   opacity: 1; }
}

/* Pulsing icon ring */
.gen-ring {
    width: 84px; height: 84px; border-radius: 50%;
    background: linear-gradient(135deg, rgba(37,99,235,.25), rgba(109,40,217,.25));
    border: 2px solid rgba(37,99,235,.5);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1.6rem;
    font-size: 2.1rem; color: #60a5fa;
    animation: pulse 2s ease-in-out infinite;
}
@keyframes pulse {
    0%,100% { box-shadow: 0 0 0 0    rgba(37,99,235,.45); }
    50%      { box-shadow: 0 0 0 16px rgba(37,99,235,0);   }
}

.gen-title {
    font-family: 'Space Grotesk', sans-serif;
    font-size: 1.5rem; font-weight: 700;
    color: #f1f5f9; margin-bottom: .4rem; letter-spacing: -.02em;
}
.gen-sub {
    font-size: .8rem; color: rgba(148,163,184,.85);
    text-transform: uppercase; letter-spacing: .1em; margin-bottom: 2rem;
}

/* Progress bar */
.gen-track {
    width: 100%; height: 8px;
    background: rgba(255,255,255,.08);
    border-radius: 99px; overflow: hidden; margin-bottom: 1rem;
}
.gen-fill {
    height: 100%; width: 0%; border-radius: 99px;
    background: linear-gradient(90deg, #3b82f6, #8b5cf6, #3b82f6);
    background-size: 200% 100%;
    transition: width .7s cubic-bezier(.4,0,.2,1);
    animation: shimmer 2s linear infinite;
}
@keyframes shimmer {
    0%   { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
.gen-pct {
    font-size: .85rem; color: #60a5fa;
    font-family: 'IBM Plex Mono', monospace, sans-serif;
    margin-bottom: 1.8rem;
}

/* Step list */
.gen-steps { list-style: none; text-align: left; display: flex; flex-direction: column; gap: .7rem; }
.gen-steps li {
    display: flex; align-items: center; gap: .75rem;
    font-size: .88rem; color: rgba(148,163,184,.65); transition: color .3s;
}
.gen-steps li.s-active { color: #f1f5f9; font-weight: 600; }
.gen-steps li.s-done   { color: #6ee7b7; }
.step-dot {
    width: 22px; height: 22px; border-radius: 50%; flex-shrink: 0;
    border: 2px solid rgba(100,116,139,.4);
    display: flex; align-items: center; justify-content: center;
    font-size: .68rem; transition: all .3s;
}
.s-active .step-dot {
    border-color: #60a5fa; background: rgba(96,165,250,.15); color: #60a5fa;
    animation: spin 1s linear infinite;
}
.s-done .step-dot { border-color: #34d399; background: rgba(52,211,153,.15); color: #34d399; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
</head>
<body>

<!-- Session Card -->
<div class="card">
    <div class="ok">✓</div>
    <h1>Interview Setup Saved</h1>
    <p class="muted">Your interview session has been created and is ready to launch.</p>

    <div class="row"><span>Session ID</span><strong>#<?= e((string)$session['id']) ?></strong></div>
    <div class="row"><span>Category</span><strong><?= e($session['category_name']) ?></strong></div>
    <div class="row"><span>Difficulty</span><strong><?= e($session['difficulty_level']) ?></strong></div>
    <div class="row"><span>Company Target</span><strong><?= e($session['company_name']) ?></strong></div>

    <div class="actions">
        <button id="startBtn" class="btn-primary" onclick="launchInterview()">
            Start AI Interview &rarr;
        </button>
        <a href="inter4.php" class="btn-secondary">Back to Setup</a>
    </div>
</div>

<!-- ════ PROGRESS OVERLAY ════ -->
<div id="genOverlay" role="dialog" aria-modal="true" aria-label="Generating interview questions">
    <div class="gen-card">
        <div class="gen-ring"><i class="fa-solid fa-brain"></i></div>
        <h2 class="gen-title">Generating Your Questions</h2>
        <p class="gen-sub">AI is crafting your personalised session&hellip;</p>

        <div class="gen-track"><div class="gen-fill" id="genFill"></div></div>
        <div class="gen-pct" id="genPct">0%</div>

        <ul class="gen-steps">
            <li id="s1" class="s-active">
                <span class="step-dot"><i class="fa-solid fa-rotate"></i></span>
                Loading interview engine
            </li>
            <li id="s2">
                <span class="step-dot"><i class="fa-solid fa-rotate"></i></span>
                Preparing question set
            </li>
            <li id="s3">
                <span class="step-dot"><i class="fa-solid fa-rotate"></i></span>
                Configuring AI evaluator
            </li>
            <li id="s4">
                <span class="step-dot"><i class="fa-solid fa-rotate"></i></span>
                Launching your session
            </li>
        </ul>
    </div>
</div>

<script>
(function () {
    const INTERVIEW_URL = <?= json_encode($interviewUrl) ?>;
    let _launched = false;

    window.launchInterview = function () {
        if (_launched) return;  // block extra clicks
        _launched = true;

        const btn = document.getElementById('startBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" style="margin-right:8px"></i>Starting…';

        document.getElementById('genOverlay').classList.add('active');
        document.body.style.overflow = 'hidden';

        // Step timings: [elementId, progressPct, delayMs]
        const steps = [
            ['s1', 20,  0   ],
            ['s2', 48,  1000],
            ['s3', 74,  2400],
            ['s4', 92,  3900],
        ];

        steps.forEach(function([id, pct, delay], i) {
            setTimeout(function() {
                if (i > 0) markDone(steps[i - 1][0]);
                var el = document.getElementById(id);
                if (el) el.classList.add('s-active');
                setProgress(pct);
            }, delay);
        });

        // Finish at 5 s → complete bar → navigate
        setTimeout(function() {
            steps.forEach(function([id]) { markDone(id); });
            setProgress(100);
            setTimeout(function() { window.location.href = INTERVIEW_URL; }, 450);
        }, 5200);
    };

    function setProgress(pct) {
        var fill = document.getElementById('genFill');
        var txt  = document.getElementById('genPct');
        if (fill) fill.style.width = pct + '%';
        if (txt)  txt.textContent  = pct + '%';
    }

    function markDone(id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.classList.remove('s-active');
        el.classList.add('s-done');
        var dot = el.querySelector('.step-dot');
        if (dot) dot.innerHTML = '<i class="fa-solid fa-check"></i>';
    }
})();
</script>
</body>
</html>
