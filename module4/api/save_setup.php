<?php
/**
 * Module 4 — Save Interview Setup
 *
 * Receives category + difficulty + company from inter4.php and creates
 * an interview_sessions row for the currently logged-in user.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/app_config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST method required.']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
    exit;
}

if (!verify_csrf($data['csrf_token'] ?? null)) {
    http_response_code(419);
    echo json_encode(['success' => false, 'message' => 'Security token expired. Refresh the page and try again.']);
    exit;
}

$category = trim((string)($data['category'] ?? ''));
$difficulty = trim((string)($data['difficulty'] ?? ''));
$company = trim((string)($data['company'] ?? ''));
$userId = (int)($_SESSION['user_id'] ?? 0);

if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in first.']);
    exit;
}

$allowedDifficulties = ['Beginner', 'Intermediate', 'Advanced'];
$allowedCompanies = [
    'General',
    'Google', 'Microsoft', 'Amazon', 'Apple', 'Meta', 'OpenAI', 'Adobe',
    'Netflix', 'TCS', 'Infosys', 'Deloitte', 'Accenture', 'IBM', 'Oracle'
];

if ($category === '' || $difficulty === '' || $company === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Category, difficulty and company are required.']);
    exit;
}

if (!in_array($difficulty, $allowedDifficulties, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid difficulty selected.']);
    exit;
}

if (!in_array($company, $allowedCompanies, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid company selected.']);
    exit;
}

/*
 * Frontend labels intentionally stay short. These map to the existing
 * database category names from database/schema.sql.
 */
$categoryMap = [
    'HR Interview' => 'HR Interview',
    'Technical' => 'Technical Interview',
    'Coding' => 'Coding Interview',
    'Aptitude' => 'Aptitude',
    'Company Specific' => 'Company Specific',
    'Resume AI' => 'Resume AI',
];

if (!isset($categoryMap[$category])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid category selected.']);
    exit;
}

$categoryDbName = $categoryMap[$category];

try {
    // Resume AI is a Module 4 option; the migration adds it if missing.
    $stmt = $pdo->prepare('SELECT id FROM categories WHERE name = ? LIMIT 1');
    $stmt->execute([$categoryDbName]);
    $categoryId = $stmt->fetchColumn();

    if (!$categoryId) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Selected category is not available in the database. Run database/module4_backend.sql first.'
        ]);
        exit;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO interview_sessions
         (user_id, category_id, role_target, difficulty_level, company_name, total_questions, status)
         VALUES (?, ?, ?, ?, ?, 10, "in_progress")'
    );

    $stmt->execute([
        $userId,
        (int)$categoryId,
        $category,
        $difficulty,
        $company
    ]);

    $sessionId = (int)$pdo->lastInsertId();

    // Store a lightweight notification so the dashboard can later show activity.
    $notify = $pdo->prepare(
        'INSERT INTO notifications (user_id, message) VALUES (?, ?)'
    );
    $notify->execute([
        $userId,
        "Interview setup created: {$category} • {$difficulty} • {$company}"
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Interview setup saved successfully.',
        'session_id' => $sessionId
    ]);
} catch (PDOException $e) {
    error_log('Module 4 save_setup error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error while creating the interview session.'
    ]);
}
