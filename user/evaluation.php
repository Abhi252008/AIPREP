<?php
/**
 * MODULE 6 — AI Evaluation Results Page
 *
 * Displays the Gemini-scored results for a completed interview session.
 * Per-question scores, strengths, improvements, grammar feedback,
 * improved answers, and an overall performance summary.
 *
 * URL: user/evaluation.php?session_id=X
 */
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$isAdmin   = !empty($_SESSION['admin_id']);
$userId    = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0 && !$isAdmin) {
    set_flash('error', 'Please log in to continue.');
    redirect('/login.php');
}

if (!function_exists('render_answer_content')) {
    function render_answer_content(?string $answerText): string {
        if (empty(trim((string)$answerText))) {
            return '<em style="opacity:0.5;">— No answer provided —</em>';
        }
        // Check if it's a code block from Module 13 Code Sandbox
        if (preg_match('/^```([a-zA-Z0-9_\-+]*)\n([\s\S]*?)\n```$/', trim($answerText), $matches)) {
            $lang = htmlspecialchars(strtoupper($matches[1] ?: 'CODE'));
            $code = htmlspecialchars($matches[2]);
            return <<<HTML
            <div class="eval-code-wrapper" style="background:#0f172a; border-radius:8px; border:1px solid #334155; margin-top:8px; overflow:hidden;">
                <div style="display:flex; justify-content:space-between; align-items:center; background:#1e293b; padding:6px 12px; font-size:11px; font-weight:700; color:#94a3b8; letter-spacing:0.5px; border-bottom:1px solid #334155;">
                    <span><i class="bi bi-code-slash"></i> {$lang} SOLUTION (MODULE 13 SANDBOX)</span>
                    <button type="button" onclick="navigator.clipboard.writeText(this.closest('.eval-code-wrapper').querySelector('code').innerText); this.innerText='COPIED!';" style="background:transparent; border:none; color:#38bdf8; font-size:11px; cursor:pointer; font-weight:600;">COPY</button>
                </div>
                <pre style="margin:0; padding:12px 14px; font-family:'Consolas','Monaco',monospace; font-size:12.5px; line-height:1.6; color:#f8fafc; overflow-x:auto;"><code>{$code}</code></pre>
            </div>
HTML;
        }
        return '<p>' . nl2br(htmlspecialchars($answerText)) . '</p>';
    }
}

$sessionId = isset($_GET['session_id']) ? (int) $_GET['session_id'] : 0;

// ── Load session ──
if ($isAdmin) {
    $stmt = $pdo->prepare(
        "SELECT s.*, c.name AS category_name, u.name AS user_name, u.email AS user_email
         FROM interview_sessions s
         JOIN categories c ON c.id = s.category_id
         JOIN users u ON u.id = s.user_id
         WHERE s.id = ?"
    );
    $stmt->execute([$sessionId]);
} else {
    $stmt = $pdo->prepare(
        "SELECT s.*, c.name AS category_name
         FROM interview_sessions s
         JOIN categories c ON c.id = s.category_id
         WHERE s.id = ? AND s.user_id = ?"
    );
    $stmt->execute([$sessionId, $userId]);
}
$session = $stmt->fetch();

if (!$session) {
    set_flash('error', 'Evaluation not found.');
    redirect($isAdmin ? '/admin/sessions.php' : '/user/dashboard.php');
}

// ── Load all Q+A with evaluation data ──
$qaStmt = $pdo->prepare(
    "SELECT q.question_text, q.question_order,
            a.answer_text, a.score, a.strengths, a.improvements,
            a.grammar_feedback, a.confidence_score, a.ai_summary,
            a.improved_answer
     FROM questions q
     LEFT JOIN answers a ON a.question_id = q.id
     WHERE q.session_id = ?
     ORDER BY q.question_order"
);
$qaStmt->execute([$sessionId]);
$results = $qaStmt->fetchAll();

// ── Derived stats — calculated live from answers ──
$totalScore   = 0;
$scoredCount  = 0;
$totalWords   = 0;
$wordAnswers  = 0;

foreach ($results as $r) {
    if ($r['score'] !== null) {
        $totalScore  += (float) $r['score'];
        $scoredCount++;
    }
    // Count words in answered questions (proxy for confidence/detail)
    $answerText = trim((string)($r['answer_text'] ?? ''));
    if ($answerText !== '') {
        $totalWords += str_word_count(strip_tags($answerText));
        $wordAnswers++;
    }
}

$avgScore = $scoredCount > 0 ? round($totalScore / $scoredCount, 2) : 0;

// Confidence = how detailed/verbose the answers were
// 0 words=0, 10 words=2, 25 words=5, 50 words=8, 80+ words=10
$avgWords      = $wordAnswers > 0 ? ($totalWords / $wordAnswers) : 0;
$avgConfidence = min(10, round($avgWords / 8, 1));   // 80 words → 10.0

// Also fix the session row if it still shows 0
if ($avgScore > 0 && (float)$session['total_score'] == 0) {
    $pdo->prepare("UPDATE interview_sessions SET total_score = ?, confidence_score = ? WHERE id = ?")
        ->execute([$avgScore, $avgConfidence, $sessionId]);
}

if ($avgScore >= 7.5)     { $perfLabel = 'Good';               $perfClass = 'perf-good';    $perfIcon = '🟢'; }
elseif ($avgScore >= 5.0) { $perfLabel = 'Average';            $perfClass = 'perf-average'; $perfIcon = '🟡'; }
else                      { $perfLabel = 'Needs Improvement';  $perfClass = 'perf-bad';     $perfIcon = '🔴'; }

$dateLabel = date('d M Y', strtotime($session['completed_at'] ?? $session['started_at']));
$questionCount = count($results);
$answeredCount = count(array_filter($results, fn($r) => !empty(trim((string)($r['answer_text'] ?? '')))));

// ── MODULE 7 — Aggregate strengths, improvements, AI suggestions ──
$allStrengths    = [];
$allImprovements = [];
$allAiSummaries  = [];
foreach ($results as $r) {
    if (!empty($r['strengths']))    $allStrengths[]    = trim($r['strengths']);
    if (!empty($r['improvements'])) $allImprovements[] = trim($r['improvements']);
    if (!empty($r['ai_summary']))   $allAiSummaries[]  = trim($r['ai_summary']);
}

$pageTitle = 'Interview Results';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Evaluation Report — <?= e($session['category_name']) ?></title>
    <meta name="description" content="Your AI-powered interview evaluation report with scores, feedback, and improved answers.">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/[email protected]/font/bootstrap-icons.css">
    <!-- Site stylesheet -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css">
    <!-- Evaluation stylesheet -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/user/evaluation.css">
</head>
<body>

<!-- ── Navbar (reuse site header) ── -->
<header class="navbar-glass glass">
    <a href="<?= BASE_URL ?>/index.php" class="brand">
        <span class="dot"></span> AI Interview Prep
    </a>
    <nav>
        <a href="<?= BASE_URL ?>/user/dashboard.php">Dashboard</a>
    </nav>
    <div class="d-flex align-items-center gap-2">
        <a href="<?= BASE_URL ?>/logout.php" class="btn-outline-glass">Log out</a>
    </div>
</header>

<!-- ── Main evaluation page ── -->
<main class="eval-page">
    <div class="eval-container">

        <!-- ── Hero header ── -->
        <div class="eval-hero">

            <h1>Your Interview Results</h1>
            <p class="eval-hero-meta">
                <?= e($session['category_name']) ?> &nbsp;·&nbsp;
                <?= $questionCount ?> Questions &nbsp;·&nbsp;
                <?= $dateLabel ?>
            </p>
            <!-- PDF Download Button -->
            <div style="margin-top:1.5rem;">
                <a href="<?= BASE_URL ?>/api/generate_pdf.php?session_id=<?= $sessionId ?>"
                   id="btnDownloadPDF"
                   class="btn-pdf-download"
                   target="_blank"
                   onclick="handlePdfClick(this)">
                    <i class="bi bi-file-earmark-pdf-fill"></i>
                    <span class="pdf-btn-text">Download PDF Report</span>
                    <span class="pdf-btn-loader" style="display:none;">
                        <i class="bi bi-hourglass-split"></i> Generating…
                    </span>
                </a>
            </div>
        </div>

        <!-- ── Summary scorecard grid ── -->
        <div class="eval-summary-grid">

            <!-- Overall score ring -->
            <div class="eval-score-card" style="--card-glow: var(--eval-primary); grid-column: span 1;">
                <span class="card-icon">⭐</span>
                <div class="score-ring-wrap">
                    <svg width="90" height="90" viewBox="0 0 90 90">
                        <circle class="score-ring-bg" cx="45" cy="45" r="36"/>
                        <circle class="score-ring-fill" id="overallRing" cx="45" cy="45" r="36"
                            stroke="url(#ringGrad)"
                            data-score="<?= $avgScore ?>"/>
                        <defs>
                            <linearGradient id="ringGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                                <stop offset="0%"   stop-color="#7a48ff"/>
                                <stop offset="100%" stop-color="#3ecfff"/>
                            </linearGradient>
                        </defs>
                    </svg>
                    <span class="score-ring-text" id="overallScore"><?= number_format($avgScore, 1) ?></span>
                </div>
                <div class="card-label">Overall Score / 10</div>
            </div>

            <!-- Performance label -->
            <div class="eval-score-card" style="--card-glow: var(--eval-good);">
                <span class="card-icon"><?= $perfIcon ?></span>
                <div class="card-value" style="font-size:1.3rem; margin-bottom:0.6rem;">
                    <span class="perf-badge <?= $perfClass ?>">
                        <?= $perfIcon ?> <?= $perfLabel ?>
                    </span>
                </div>
                <div class="card-label">Performance Label</div>
            </div>

            <!-- Confidence -->
            <div class="eval-score-card" style="--card-glow: var(--eval-secondary);">
                <span class="card-icon">💡</span>
                <div class="score-ring-wrap">
                    <svg width="90" height="90" viewBox="0 0 90 90">
                        <circle class="score-ring-bg" cx="45" cy="45" r="36"/>
                        <circle class="score-ring-fill" cx="45" cy="45" r="36"
                            stroke="#3ecfff"
                            data-score="<?= $avgConfidence ?>"/>
                    </svg>
                    <span class="score-ring-text"><?= number_format($avgConfidence, 1) ?></span>
                </div>
                <div class="card-label">Confidence Score / 10</div>
            </div>

            <!-- Answered -->
            <div class="eval-score-card" style="--card-glow: var(--eval-good);">
                <span class="card-icon">📝</span>
                <div class="card-value"><?= $answeredCount ?><span style="font-size:1rem;opacity:0.5;">/<?= $questionCount ?></span></div>
                <div class="card-label">Questions Answered</div>
            </div>

        </div><!-- /summary grid -->

        <!-- AI Suggestions, Key Strengths, and Areas to Improve are PDF-only.
             Download the PDF report to see full AI analysis. -->
        <?php if (!empty($allAiSummaries) || !empty($allStrengths) || !empty($allImprovements)): ?>
        <div style="
            background: linear-gradient(135deg, rgba(122,72,255,0.08) 0%, rgba(62,207,255,0.06) 100%);
            border: 1px solid rgba(122,72,255,0.25);
            border-radius: 14px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        ">
            <span style="font-size:2rem;">📄</span>
            <div>
                <div style="font-weight:700;font-size:0.97rem;color:var(--text);margin-bottom:0.25rem;">Full AI Analysis Available in PDF</div>
                <div style="font-size:0.84rem;color:var(--mist);line-height:1.55;">
                    Your <strong style="color:#7a48ff;">AI Suggestions</strong>,
                    <strong style="color:#22d47b;">Key Strengths</strong>, and
                    <strong style="color:#f5c842;">Areas to Improve</strong>
                    are included in your downloadable PDF report, along with personalised next steps.
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── Per-question results ── -->
        <div class="eval-section-title">
            <i class="bi bi-list-check"></i> Detailed Breakdown
        </div>

        <div class="eval-cards-list">
        <?php foreach ($results as $idx => $row):
            $score    = $row['score'] !== null ? (float)$row['score'] : null;
            $answered = !empty(trim((string)($row['answer_text'] ?? '')));

            if ($score === null) {
                $pillClass = 'bad';
            } elseif ($score >= 7) {
                $pillClass = 'good';
            } elseif ($score >= 5) {
                $pillClass = 'average';
            } else {
                $pillClass = 'bad';
            }

            $scoreDisplay = $score !== null ? number_format($score, 1) . '/10' : 'N/A';
        ?>
        <div class="eval-q-card">
            <!-- Card header: Q number + text + score pill -->
            <div class="eval-q-header">
                <div>
                    <div class="eval-q-num">Question <?= (int)$row['question_order'] ?></div>
                    <div class="eval-q-text"><?= e($row['question_text']) ?></div>
                </div>
                <div class="score-pill <?= $pillClass ?>">
                    <i class="bi bi-star-fill" style="font-size:0.75rem;"></i>
                    <?= $scoreDisplay ?>
                </div>
            </div>

            <!-- Your answer -->
            <div class="eval-answer-block">
                <div class="block-label">💬 Your Answer</div>
                <?= render_answer_content($row['answer_text'] ?? '') ?>
            </div>

            <?php if ($row['score'] !== null): ?>

            <!-- Collapsible: Strengths + Improvements + Correct Answer -->
            <button class="improved-toggle" onclick="toggleImproved(this)" type="button">
                <i class="bi bi-chevron-right toggle-arrow"></i>
                ✏️ See Improved Answer
            </button>
            <div class="improved-body">

                <!-- Strengths -->
                <?php if (!empty($row['strengths'])): ?>
                <div style="margin-bottom:0.85rem;">
                    <div class="improved-section-label strengths">💪 Strengths</div>
                    <div class="improved-section-body strengths"><?= e($row['strengths']) ?></div>
                </div>
                <?php endif; ?>

                <!-- What to Improve -->
                <?php if (!empty($row['improvements'])): ?>
                <?php if (!empty($row['strengths'])): ?><hr class="improved-section-divider"><?php endif; ?>
                <div style="margin-bottom:0.85rem;">
                    <div class="improved-section-label improve">⚠️ What to Improve</div>
                    <div class="improved-section-body improve"><?= e($row['improvements']) ?></div>
                </div>
                <?php endif; ?>

                <!-- Correct Answer -->
                <?php if (!empty($row['improved_answer'])): ?>
                <hr class="improved-section-divider">
                <div>
                    <div class="improved-section-label correct">✅ Correct Answer</div>
                    <div class="improved-section-body correct"><?= e($row['improved_answer']) ?></div>
                </div>
                <?php endif; ?>

            </div><!-- /improved-body -->

            <?php else: ?>
            <div class="eval-ai-summary">
                <i class="bi bi-info-circle ai-icon"></i>
                <span>This question was not scored — evaluation data not available.</span>
            </div>
            <?php endif; ?>

        </div><!-- /eval-q-card -->
        <?php endforeach; ?>
        </div><!-- /eval-cards-list -->

        <!-- ── MODULE 7: CTA with PDF button ── -->
        <div class="eval-cta">
            <h3>Ready for another round? 🚀</h3>
            <p>Practice makes perfect. Start a new interview session to keep improving.<br>
               <span style="font-size:.82rem;opacity:.65;">Download your full report to review offline anytime.</span>
            </p>
            <div class="btn-row">
                <a href="<?= BASE_URL ?>/user/start-interview.php" class="btn-eval-primary">
                    <i class="bi bi-play-circle-fill"></i> Start New Interview
                </a>
                <a href="<?= BASE_URL ?>/api/generate_pdf.php?session_id=<?= $sessionId ?>"
                   class="btn-eval-pdf"
                   target="_blank"
                   onclick="handlePdfClick(this)">
                    <i class="bi bi-file-earmark-pdf-fill"></i> Download PDF Report
                </a>
                <a href="<?= BASE_URL ?>/user/dashboard.php" class="btn-eval-ghost">
                    <i class="bi bi-grid"></i> Back to Dashboard
                </a>
            </div>
        </div>

    </div><!-- /eval-container -->
</main>

<script>
/* ── Animate SVG score rings on page load ── */
document.addEventListener('DOMContentLoaded', () => {
    const rings = document.querySelectorAll('.score-ring-fill');
    rings.forEach(ring => {
        const score     = parseFloat(ring.dataset.score) || 0;
        const r         = parseFloat(ring.getAttribute('r'));
        const circ      = 2 * Math.PI * r;
        const offset    = circ - (score / 10) * circ;
        ring.style.strokeDasharray  = circ;
        ring.style.strokeDashoffset = circ; // start at 0
        requestAnimationFrame(() => {
            ring.style.transition   = 'stroke-dashoffset 1.4s cubic-bezier(0.25,0.8,0.25,1)';
            ring.style.strokeDashoffset = offset;
        });
    });
});

/* ── Toggle improved answer ── */
function toggleImproved(btn) {
    const body = btn.nextElementSibling;
    const isOpen = body.classList.contains('open');
    body.classList.toggle('open', !isOpen);
    btn.classList.toggle('open', !isOpen);
    btn.childNodes[btn.childNodes.length - 1].textContent =
        !isOpen ? ' Hide Improved Answer' : ' See Improved Answer';
}

/* ── MODULE 7: PDF Download handler ── */
function handlePdfClick(el) {
    const textEl   = el.querySelector('.pdf-btn-text');
    const loaderEl = el.querySelector('.pdf-btn-loader');
    if (textEl)   textEl.style.display   = 'none';
    if (loaderEl) loaderEl.style.display = 'inline-flex';
    el.style.pointerEvents = 'none';
    el.style.opacity = '0.7';
    // Re-enable after 6s (PDF should have started downloading)
    setTimeout(() => {
        if (textEl)   textEl.style.display   = '';
        if (loaderEl) loaderEl.style.display = 'none';
        el.style.pointerEvents = '';
        el.style.opacity = '';
    }, 6000);
}
</script>

</body>
</html>
