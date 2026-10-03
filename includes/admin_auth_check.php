<?php
/**
 * Guard for ADMIN-only pages. Include this at the very top of any page
 * under /admin/ (except admin/login.php itself), BEFORE any HTML output:
 *
 *   require_once __DIR__ . '/../config/app_config.php';
 *   require_once __DIR__ . '/../includes/admin_auth_check.php';
 *   require_once __DIR__ . '/../includes/header.php';
 */
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/app_config.php';
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    require_once __DIR__ . '/functions.php';
    set_flash('error', 'Please log in as an admin to continue.');
    redirect('/admin/login.php');
}
