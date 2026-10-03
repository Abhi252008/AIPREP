<?php
/**
 * Module 4 — Interview Gateway / Fallback
 * Handles requests directed to module4/interview.php, creates the session,
 * and routes directly into the user interview engine (user/interview.php).
 */

require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$rawCategory = strtolower(trim((string)($_GET['category'] ?? '')));
$rawDiff     = strtolower(trim((string)($_GET['difficulty'] ?? 'intermediate')));
$company     = trim((string)($_GET['company'] ?? 'General'));
if ($company === '') {
    $company = 'General';
}

// Map short codes or friendly names to database category names
$map = [
    'aptitude'          => 'Aptitude',
    'hr'                => 'HR Interview',
    'hr interview'      => 'HR Interview',
    'technical'         => 'Technical Interview',
    'technical core'    => 'Technical Interview',
    'coding'            => 'Coding Interview',
    'coding & dsa'      => 'Coding Interview',
    'company specific'  => 'Company Specific',
    'resume ai'         => 'Resume AI',
];

$matchedDbName = $map[$rawCategory] ?? null;

// Normalize difficulty
$difficulty = match ($rawDiff) {
    'beginner', 'junior'   => 'Beginner',
    'advanced', 'senior'   => 'Advanced',
    default                => 'Intermediate',
};

// Look up category in database
$catId = null;
if ($matchedDbName) {
    $stmt = $pdo->prepare('SELECT id FROM categories WHERE name = ? LIMIT 1');
    $stmt->execute([$matchedDbName]);
    $catId = $stmt->fetchColumn();
}

if (!$catId && $rawCategory !== '') {
    $stmt = $pdo->prepare('SELECT id, name FROM categories WHERE name LIKE ? LIMIT 1');
    $stmt->execute(['%' . $rawCategory . '%']);
    $row = $stmt->fetch();
    if ($row) {
        $catId = $row['id'];
        $matchedDbName = $row['name'];
    }
}

// Fallback to first available category if none matched
if (!$catId) {
    $catId = $pdo->query('SELECT id FROM categories ORDER BY id ASC LIMIT 1')->fetchColumn();
    $matchedDbName = 'General';
}

if ($catId) {
    $stmt = $pdo->prepare(
        'INSERT INTO interview_sessions
         (user_id, category_id, role_target, difficulty_level, company_name, total_questions, status)
         VALUES (?, ?, ?, ?, ?, 10, "in_progress")'
    );
    $stmt->execute([
        $userId,
        (int)$catId,
        $matchedDbName ?: 'General',
        $difficulty,
        $company
    ]);

    $sessionId = (int)$pdo->lastInsertId();

    // Log notification
    $notify = $pdo->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
    $notify->execute([$userId, "Started a new {$matchedDbName} ({$difficulty}) mock interview."]);

    // Send directly into the interview runner
    header('Location: ' . BASE_URL . '/user/interview.php?session_id=' . $sessionId);
    exit;
}

// Otherwise redirect to setup page
header('Location: ' . BASE_URL . '/module4/inter4.php');
exit;
