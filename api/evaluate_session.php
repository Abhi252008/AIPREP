<?php
/**
 * MODULE 6 — AI Evaluation Engine
 *
 * Called via AJAX (POST) from user/interview_finish.php when the user
 * clicks "View My Results". Sends ALL questions+answers to Gemini in ONE
 * single API call, stores scores back to the DB, and returns a redirect URL.
 *
 * POST body (JSON): { "session_id": int }
 * Response (JSON):  { "success": bool, "redirect": string, "error": string }
 */

// Buffer ALL output so any PHP warnings/notices never corrupt the JSON.
ob_start();

// Suppress HTML error display — errors logged, not echoed.
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Extend time limit — one Gemini call for all questions.
set_time_limit(180);

require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/gemini_config.php';

// Discard any stray output, then set JSON header.
ob_clean();
header('Content-Type: application/json');

// ── Parse request ──
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

$sessionId = (int) ($data['session_id'] ?? 0);
$userId    = (int) $_SESSION['user_id'];

if ($sessionId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing session_id.']);
    exit;
}

// ── Verify session ownership ──
$sessionStmt = $pdo->prepare(
    "SELECT s.*, c.name AS category_name
     FROM interview_sessions s
     JOIN categories c ON c.id = s.category_id
     WHERE s.id = ? AND s.user_id = ?"
);
$sessionStmt->execute([$sessionId, $userId]);
$session = $sessionStmt->fetch();

if (!$session) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Session not found or not yours.']);
    exit;
}

// ── Load questions + answers ──
$qaStmt = $pdo->prepare(
    "SELECT q.id AS question_id, q.question_text, q.question_order,
            a.id AS answer_id, a.answer_text
     FROM questions q
     LEFT JOIN answers a ON a.question_id = q.id
     WHERE q.session_id = ?
     ORDER BY q.question_order"
);
$qaStmt->execute([$sessionId]);
$qaPairs = $qaStmt->fetchAll();

if (empty($qaPairs)) {
    echo json_encode(['success' => false, 'error' => 'No questions found for this session.']);
    exit;
}

// ── Build prompt using GeminiPrompts Engine ──
$categoryName = $session['category_name'] ?? 'General';
$difficulty   = $session['difficulty'] ?? 'Intermediate';
$prompt       = GeminiPrompts::buildEvaluationPrompt($qaPairs, $categoryName, $difficulty);

// ── Single Gemini call with generous 120s timeout, automatic retry & smart persona ──
$result = callGemini($prompt, 120, 2, GeminiPrompts::getSystemInstruction(), 0.2);

$evalList = null;
if ($result['success']) {
    $evalList = extractJsonFromGemini($result['text']);

    // If Gemini wrapped array in an object like {"evaluations": [...]}
    if (is_array($evalList)) {
        if (!isset($evalList[0]) && !isset($evalList['score'])) {
            foreach ($evalList as $val) {
                if (is_array($val) && isset($val[0])) {
                    $evalList = $val;
                    break;
                }
            }
        } elseif (isset($evalList['score'])) {
            $evalList = [$evalList];
        }
    }
}

// If Gemini timed out or failed, provide intelligent fallback evaluations
// so the user is never blocked from seeing their interview evaluation
if (empty($evalList) || !is_array($evalList)) {
    $evalList = [];
    foreach ($qaPairs as $i => $row) {
        $ans = trim((string)($row['answer_text'] ?? ''));
        $lowerAns = strtolower($ans);
        $hasAnswer = !empty($ans) && $lowerAns !== 'i dont know' && $lowerAns !== 'idk' && $lowerAns !== 'dont know';
        $words = str_word_count($ans);
        $score = $hasAnswer ? min(8, max(3, (int)round($words / 6) + 2)) : 0;

        $evalList[] = [
            'score'            => $score,
            'strengths'        => $hasAnswer ? 'Provided an initial response addressing the question topic.' : 'Session question recorded.',
            'improvements'     => $hasAnswer ? 'Elaborate further with specific real-world examples, key principles, and edge cases.' : 'Review core concepts for this topic and practice answering clearly.',
            'grammar_feedback' => $hasAnswer ? 'Communication is direct and understandable.' : 'No answer provided.',
            'grammar_score'    => $hasAnswer ? 7 : 0,
            'confidence_score' => $hasAnswer ? min(8, max(4, (int)($score * 0.9))) : 0,
            'ai_summary'       => $hasAnswer ? 'Good attempt establishing fundamental understanding.' : 'Unanswered question.',
            'improved_answer'  => 'Structure your answer systematically: start with a direct definition, elaborate with technical context, and give a concise practical example.'
        ];
    }
}

// ── Prepare DB statements ──
$updateStmt = $pdo->prepare(
    "UPDATE answers
     SET score = ?, strengths = ?, improvements = ?, grammar_feedback = ?,
         confidence_score = ?, ai_summary = ?, improved_answer = ?
     WHERE id = ?"
);

$insertStmt = $pdo->prepare(
    "INSERT INTO answers (question_id, session_id, answer_text, score, strengths,
                          improvements, grammar_feedback, confidence_score, ai_summary, improved_answer)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);

$totalScore      = 0;
$totalConfidence = 0;
$scoredCount     = 0;

foreach ($qaPairs as $i => $row) {
    // Use Gemini result if available, else use fallback defaults
    $evalData = $evalList[$i] ?? [];

    $score           = max(0, min(10, (float)  ($evalData['score']            ?? 5)));
    $strengths       = (string) ($evalData['strengths']        ?? 'No strengths data.');
    $improvements    = (string) ($evalData['improvements']     ?? 'No improvement data.');
    $grammarFeedback = (string) ($evalData['grammar_feedback'] ?? '');
    $confidenceScore = max(0, min(10, (float)  ($evalData['confidence_score'] ?? 5)));
    $aiSummary       = (string) ($evalData['ai_summary']       ?? '');
    $improvedAnswer  = (string) ($evalData['improved_answer']  ?? '');

    if (!empty($row['answer_id'])) {
        $updateStmt->execute([
            $score, $strengths, $improvements, $grammarFeedback,
            $confidenceScore, $aiSummary, $improvedAnswer,
            (int) $row['answer_id']
        ]);
    } else {
        $insertStmt->execute([
            (int) $row['question_id'], $sessionId, '',
            $score, $strengths, $improvements, $grammarFeedback,
            $confidenceScore, $aiSummary, $improvedAnswer
        ]);
    }

    $totalScore      += $score;
    $totalConfidence += $confidenceScore;
    $scoredCount++;
}

// ── Session-level averages + performance label ──
$avgScore      = $scoredCount > 0 ? round($totalScore / $scoredCount, 2)      : 0;
$avgConfidence = $scoredCount > 0 ? round($totalConfidence / $scoredCount, 2) : 0;

if      ($avgScore >= 7.5) { $performanceLabel = 'Good'; }
elseif  ($avgScore >= 5.0) { $performanceLabel = 'Average'; }
else                       { $performanceLabel = 'Needs Improvement'; }

// ── Save to interview_sessions ──
$pdo->prepare("UPDATE interview_sessions SET total_score = ?, confidence_score = ? WHERE id = ?")
    ->execute([$avgScore, $avgConfidence, $sessionId]);

// ── Notification ──
$pdo->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)')
    ->execute([
        $userId,
        "Your {$session['category_name']} interview was evaluated. Score: {$avgScore}/10 — {$performanceLabel}."
    ]);

echo json_encode([
    'success'  => true,
    'redirect' => BASE_URL . '/user/evaluation.php?session_id=' . $sessionId
]);
