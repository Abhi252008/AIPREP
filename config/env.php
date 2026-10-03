<?php
/**
 * Lightweight .env loader — no Composer or external libraries needed.
 * Call load_env() once (from gemini_config.php or a bootstrap file).
 * Reads key=value pairs from the root .env file and puts them into
 * $_ENV, $_SERVER, and putenv() — same as real dotenv libraries do.
 */

function load_env(string $filePath): void
{
    if (!file_exists($filePath)) {
        return; // No .env file — fall back to system env vars
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        // Skip comments and blank lines
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        // Split on first '=' only
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        [$key, $value] = $parts;
        $key   = trim($key);
        $value = trim($value);

        // Strip surrounding quotes if present (" or ')
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        // Only set if not already defined by the real environment
        if (!array_key_exists($key, $_ENV) && getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
        }
    }
}
