<?php
/**
 * Module 2 — Authentication: Logout
 * Fully destroys the session, then starts a fresh one just long enough
 * to carry a "you've been logged out" flash message to index.php.
 */
require_once __DIR__ . '/config/app_config.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie('PHPSESSID', '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

session_start();
set_flash('success', 'You have been logged out.');
redirect('/index.php');
