<?php
/**
 * Guard for USER-only pages. Include this at the very top of any page
 * under /user/ (or anywhere else a logged-in student is required),
 * BEFORE includes/header.php and before any HTML is echoed:
 *
 *   require_once __DIR__ . '/../config/app_config.php';
 *   require_once __DIR__ . '/../includes/auth_check.php';
 *   require_once __DIR__ . '/../includes/header.php';
 */
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/app_config.php';
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/functions.php';
    set_flash('error', 'Please log in to continue.');
    redirect('/login.php');
}
