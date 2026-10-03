<?php
/**
 * Database connection (PDO) — used by every module.
 */
require_once __DIR__ . '/env.php';
load_env(__DIR__ . '/../.env');

$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_PORT = getenv('DB_PORT') ?: '3306';
$DB_NAME = getenv('DB_NAME') ?: 'ai_interview_prep';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = (getenv('DB_PASS') !== false && getenv('DB_PASS') !== '') ? getenv('DB_PASS') : '';

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // throw exceptions on errors
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false, // real prepared statements = SQL injection protection
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
