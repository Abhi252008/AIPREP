<?php
/**
 * MODULE 5 — API: save (or update) one answer.
 * Called repeatedly from js/interview.js as the user moves between
 * questions, so this must handle both "first save" and "re-save".
 */
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db_connect.php';

header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request.']);
    exit;
}

$sessionId = (int) ($data['session_id'] ?? 0);
$questionId = (int) ($data['question_id'] ?? 0);
$answerText = trim((string) ($data['answer_text'] ?? ''));
$userId = (int) $_SESSION['user_id'];

if ($sessionId <= 0 || $questionId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing session or question id.']);
    exit;
}

// Confirm this session actually belongs to the logged-in user, and that
// the question actually belongs to this session — prevents one user
// from overwriting another user's answers by guessing IDs.
$check = $pdo->prepare(
    "SELECT q.id FROM questions q
     JOIN interview_sessions s ON s.id = q.session_id
     WHERE q.id = ? AND q.session_id = ? AND s.user_id = ?"
);
$check->execute([$questionId, $sessionId, $userId]);

if (!$check->fetch()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized for this question.']);
    exit;
}

// Upsert: update if an answer row already exists for this question, else insert.
$existing = $pdo->prepare('SELECT id FROM answers WHERE question_id = ?');
$existing->execute([$questionId]);
$existingId = $existing->fetchColumn();

if ($existingId) {
    $stmt = $pdo->prepare('UPDATE answers SET answer_text = ? WHERE id = ?');
    $stmt->execute([$answerText, $existingId]);
} else {
    $stmt = $pdo->prepare(
        'INSERT INTO answers (question_id, session_id, answer_text) VALUES (?, ?, ?)'
    );
    $stmt->execute([$questionId, $sessionId, $answerText]);
}

echo json_encode(['success' => true]);
