<?php
/**
 * MODULE 5 — Interview complete (gateway to Module 6 AI Evaluation).
 *
 * Marks the session as 'completed' and presents the "View My Results"
 * button. Clicking it triggers an AJAX call to api/evaluate_session.php
 * (Module 6), shows a loading overlay while Gemini scores every answer,
 * then redirects to user/evaluation.php for the full scored report.
 */
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

$sessionId = isset($_GET['session_id']) ? (int) $_GET['session_id'] : 0;
$userId    = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare(
    "SELECT s.*, c.name AS category_name
     FROM interview_sessions s
     JOIN categories c ON c.id = s.category_id
     WHERE s.id = ? AND s.user_id = ?"
);
$stmt->execute([$sessionId, $userId]);
$session = $stmt->fetch();

if (!$session) {
    set_flash('error', 'Interview session not found.');
    redirect('/user/dashboard.php');
}

// Mark session complete (idempotent)
if ($session['status'] !== 'completed') {
    $update = $pdo->prepare(
        "UPDATE interview_sessions SET status = 'completed', completed_at = NOW() WHERE id = ?"
    );
    $update->execute([$sessionId]);

    $notify = $pdo->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
    $notify->execute([$userId, "Completed a {$session['category_name']} interview session."]);
}

// Check if already evaluated (scores already saved)
$evalCheck = $pdo->prepare(
    "SELECT COUNT(*) FROM answers a
     JOIN questions q ON q.id = a.question_id
     WHERE q.session_id = ? AND a.score IS NOT NULL"
);
$evalCheck->execute([$sessionId]);
$alreadyEvaluated = (int) $evalCheck->fetchColumn() > 0;

$answeredStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM answers a
     JOIN questions q ON q.id = a.question_id
     WHERE q.session_id = ? AND a.answer_text != ''"
);
$answeredStmt->execute([$sessionId]);
$answeredCount = (int) $answeredStmt->fetchColumn();

$pageTitle = 'Interview Complete';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ── Loading Overlay (shown while Gemini evaluates) ── -->
<div id="finish-overlay" style="
    position:fixed;inset:0;background:rgba(255,255,255,0.95);
    backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);
    z-index:9999;display:flex;flex-direction:column;
    align-items:center;justify-content:center;gap:1.75rem;
    opacity:0;visibility:hidden;transition:opacity .35s ease,visibility .35s ease;">

    <div style="
        width:60px;height:60px;border-radius:50%;
        border:3px solid #e2e8f0;
        border-top-color:#2563eb;
        animation:finSpin .85s linear infinite;">
    </div>

    <div style="text-align:center;">
        <p style="font-family:'Space Grotesk',sans-serif;font-size:1.2rem;font-weight:700;color:#0f172a;margin:0 0 .5rem;">
            Evaluating your answers…
        </p>
        <p style="font-size:.9rem;color:#64748b;margin:0;" id="finLoadSub">
            Analyzing each response &middot; This may take 10–20 seconds
        </p>
    </div>

    <!-- Pulsing dots -->
    <div style="display:flex;gap:.5rem;">
        <span style="width:8px;height:8px;border-radius:50%;background:#2563eb;animation:finDot 1.4s ease-in-out infinite;"></span>
        <span style="width:8px;height:8px;border-radius:50%;background:#0284c7;animation:finDot 1.4s ease-in-out .2s infinite;"></span>
        <span style="width:8px;height:8px;border-radius:50%;background:#2563eb;animation:finDot 1.4s ease-in-out .4s infinite;"></span>
    </div>
</div>

<style>
@keyframes finSpin { to { transform:rotate(360deg); } }
@keyframes finDot  { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.25;transform:scale(.5)} }
</style>

<!-- ── Main content ── -->
<section class="section" style="padding-top:160px;min-height:80vh;display:flex;align-items:center;">
    <div class="glass p-4" style="max-width:580px;margin:0 auto;text-align:center;">

        <!-- Trophy icon -->
        <div style="font-size:3.5rem;margin-bottom:1rem;line-height:1;
                    animation:trophyBounce .7s cubic-bezier(.36,.07,.19,.97) both;">🏆</div>
        <style>
        @keyframes trophyBounce {
            0%  { transform:scale(0); opacity:0; }
            60% { transform:scale(1.15); opacity:1; }
            80% { transform:scale(.95); }
            100%{ transform:scale(1); }
        }
        </style>

        <span class="section-eyebrow">Session #<?= (int) $session['id'] ?></span>
        <h2 class="section-title" style="font-size:1.75rem;margin-bottom:.5rem;">
            Interview Complete!
        </h2>
        <p style="color:var(--mist);font-size:.92rem;margin-bottom:1.75rem;">
            <strong style="color:var(--paper);"><?= (int) $session['total_questions'] ?></strong> questions &nbsp;·&nbsp;
            <strong style="color:var(--paper);"><?= $answeredCount ?></strong> answered &nbsp;·&nbsp;
            <?= e($session['category_name']) ?>
        </p>

        <?php if ($alreadyEvaluated): ?>
        <!-- Already evaluated — go straight to results -->
        <p style="font-size:.87rem;color:var(--mist);margin-bottom:1.5rem;">
            This session was already evaluated. View your full report below.
        </p>
        <a href="<?= BASE_URL ?>/user/evaluation.php?session_id=<?= $sessionId ?>"
           class="btn-gradient" style="display:inline-flex;align-items:center;gap:.5rem;font-size:1rem;padding:.8rem 2rem;">
            <i class="bi bi-bar-chart-fill"></i> View My Results
        </a>

        <?php else: ?>
        <!-- Trigger evaluation -->
        <p style="font-size:.87rem;color:var(--mist);margin-bottom:1.5rem;line-height:1.65;">
            Click below and Gemini will evaluate every answer — scoring it out of 10,
            pointing out strengths, areas to improve, grammar feedback, and providing
            a model answer for anything you got wrong.
        </p>

        <button id="btnEvaluate"
                onclick="startEvaluation()"
                class="btn-gradient"
                style="display:inline-flex;align-items:center;gap:.6rem;font-size:1.05rem;padding:.85rem 2.2rem;cursor:pointer;border:none;">
            <i class="bi bi-stars"></i> View My Results 🚀
        </button>
        <?php endif; ?>

        <div style="margin-top:1.5rem;">
            <a href="<?= BASE_URL ?>/user/dashboard.php"
               style="font-size:.82rem;color:var(--mist);text-decoration:none;opacity:.7;">
                ← Back to Dashboard
            </a>
        </div>

    </div>
</section>

<script>
const SESSION_ID   = <?= $sessionId ?>;
const EVAL_API_URL = '<?= BASE_URL ?>/api/evaluate_session.php';
const overlay      = document.getElementById('finish-overlay');
const subText      = document.getElementById('finLoadSub');

function showOverlay() {
    overlay.style.opacity    = '1';
    overlay.style.visibility = 'visible';
}

// Cycle through informative loading messages
const msgs = [
    'AI is evaluating your answers…',
    'Analyzing key concepts & accuracy…',
    'Assessing grammar and confidence scores…',
    'Generating improved model answers…',
    'Synthesizing personalized feedback…',
    'Finalizing your score report…',
];
let msgIdx = 0;
function cycleMessages() {
    msgIdx = (msgIdx + 1) % msgs.length;
    subText.textContent = msgs[msgIdx];
}

async function startEvaluation() {
    const btn = document.getElementById('btnEvaluate');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Evaluating…';
    }

    showOverlay();
    const interval = setInterval(cycleMessages, 3500);

    try {
        const res = await fetch(EVAL_API_URL, {
            method : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body   : JSON.stringify({ session_id: SESSION_ID }),
        });

        const json = await res.json();
        clearInterval(interval);

        if (json.success && json.redirect) {
            subText.textContent = '✅ Done! Loading your results…';
            window.location.href = json.redirect;
        } else {
            overlay.style.opacity    = '0';
            overlay.style.visibility = 'hidden';
            alert('Evaluation error: ' + (json.error || 'Unknown error. Please try again.'));
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-stars"></i> View My Results 🚀';
            }
        }
    } catch (err) {
        clearInterval(interval);
        overlay.style.opacity    = '0';
        overlay.style.visibility = 'hidden';
        alert('Network error: ' + err.message);
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-stars"></i> View My Results 🚀';
        }
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
