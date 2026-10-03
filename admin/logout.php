<?php
/**
 * Module 2 — Authentication: Admin Logout
 * Only clears the admin session keys — does not touch a student session
 * that might exist in the same browser.
 */
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

unset($_SESSION['admin_id'], $_SESSION['admin_name']);

set_flash('success', 'You have been logged out.');
redirect('/admin/login.php');
