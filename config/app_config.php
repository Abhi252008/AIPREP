<?php
// Load environment variables if available
require_once __DIR__ . '/env.php';
load_env(__DIR__ . '/../.env');

if (!defined('BASE_URL')) {
    $envBase = getenv('BASE_URL');
    if ($envBase !== false && $envBase !== '') {
        define('BASE_URL', rtrim($envBase, '/'));
    } else {
        // Automatic URL base detection that works across all environments:
        // - InfinityFree / Live Cloud Hosting (root or subfolder)
        // - Localhost / XAMPP subfolders (/pw/projectail, /pw/cloud, etc.)
        // - Custom domains
        $appDir = str_replace('\\', '/', realpath(__DIR__ . '/..') ?: dirname(__DIR__));
        $scriptFile = isset($_SERVER['SCRIPT_FILENAME']) ? str_replace('\\', '/', realpath($_SERVER['SCRIPT_FILENAME']) ?: $_SERVER['SCRIPT_FILENAME']) : '';
        $scriptName = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '';

        $base = '';
        if ($appDir && $scriptFile && $scriptName && str_starts_with($scriptFile, $appDir)) {
            $relPath = substr($scriptFile, strlen($appDir));
            if ($relPath !== '' && str_ends_with($scriptName, $relPath)) {
                $base = substr($scriptName, 0, strlen($scriptName) - strlen($relPath));
            }
        }
        define('BASE_URL', rtrim($base, '/'));
    }
}