<?php
/**
 * Gemini API configuration + a single reusable function to call it.
 * Modules 6, 7, and 9 (Interview Engine, Evaluation, AI career tip)
 * all call callGemini() instead of writing their own cURL code.
 */

// Load .env file (safe to call multiple times — skips already-set vars)
require_once __DIR__ . '/env.php';
load_env(__DIR__ . '/../.env');

require_once __DIR__ . '/gemini_prompts.php';

define('GEMINI_MODEL', getenv('GEMINI_MODEL') ?: 'gemini-3.5-flash');
define('GEMINI_URL',   'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent');

/**
 * Return the prioritized list of Gemini models for automatic failover.
 * If one model is experiencing a high-demand spike (HTTP 503), the engine
 * immediately fails over to the next model.
 *
 * @return string[]
 */
function getGeminiModels(): array
{
    $envModel = trim((string)(getenv('GEMINI_MODEL') ?: ''));
    $models   = [];

    // Blocklist of deprecated/shut-down models (do not use these)
    $deprecated = [
        // 2.0 series — shut down June 1, 2026
        'gemini-2.0-flash', 'gemini-2.0-flash-exp', 'gemini-2.0-flash-lite', 'gemini-2.0-pro',
        // 2.5 series — retiring October 20, 2026
        'gemini-2.5-flash', 'gemini-2.5-flash-preview', 'gemini-2.5-pro',
        // 1.x series — deprecated
        'gemini-1.5-flash', 'gemini-1.5-flash-8b', 'gemini-1.5-flash-latest', 'gemini-1.5-pro',
    ];
    if ($envModel !== '' && !in_array($envModel, $deprecated, true)) {
        $models[] = $envModel;
    }

    // Active Google Gemini API models as of October 2026 — fastest/cheapest first
    // Source: Google recommends 3.5-flash & 3.8-flash as replacements for 2.5-flash
    $fallbacks = [
        // --- 3.5 Series (Recommended replacement for 2.5-flash) ---
        'gemini-3.5-flash',           // Primary recommended model — fast & high quality
        'gemini-3.5-flash-lite',      // Lightweight — lowest latency, cheapest
        'gemini-3.5-flash-latest',    // Always points to latest 3.5-flash stable
        'gemini-3.5-flash-preview',   // Preview channel — may have new capabilities

        // --- 3.8 Series (High-throughput workhorse) ---
        'gemini-3.8-flash',           // High-throughput, strong reasoning
        'gemini-3.8-flash-lite',      // Lightweight 3.8 variant
        'gemini-3.8-flash-tts',       // TTS-optimized variant

        // --- 3.1 Series (Stable, widely supported) ---
        'gemini-3.1-flash',           // Stable 3.1 flash
        'gemini-3.1-flash-lite',      // Lightweight 3.1 fallback
        'gemini-3.1-flash-live',      // Live/streaming capable

        // --- Generic latest aliases (Google updates these pointers) ---
        'gemini-flash-latest',        // Always points to latest flash model
        'gemini-flash-lite-latest',   // Always points to latest flash-lite model
    ];
    foreach ($fallbacks as $f) {
        if (!in_array($f, $models, true)) {
            $models[] = $f;
        }
    }
    return $models;
}


/**
 * Build the list of available API keys from environment variables.
 * Automatically discovers:
 *   - GEMINI_API_KEY (standard singular key)
 *   - GEMINI_API_KEY_1, GEMINI_API_KEY_2, ... up to GEMINI_API_KEY_25
 *   - GEMINI_API_KEYS (comma-separated list of multiple keys)
 *   - GOOGLE_API_KEY or GEMINI_KEY
 * De-duplicates and trims all keys so you can add any key easily.
 *
 * @return string[]
 */
function getGeminiKeys(): array
{
    $keys = [];

    // Helper to safely register a key
    $register = function (?string $val) use (&$keys) {
        if (!$val) return;
        // Check for comma-separated multiple keys in one variable
        if (str_contains($val, ',')) {
            foreach (explode(',', $val) as $single) {
                $trimmed = trim($single, " \t\n\r\0\x0B\"'");
                if ($trimmed !== '' && !in_array($trimmed, $keys, true)) {
                    $keys[] = $trimmed;
                }
            }
        } else {
            $trimmed = trim($val, " \t\n\r\0\x0B\"'");
            if ($trimmed !== '' && !in_array($trimmed, $keys, true)) {
                $keys[] = $trimmed;
            }
        }
    };

    // 1. Check standard singular keys
    $register(getenv('GEMINI_API_KEY'));
    $register(getenv('GOOGLE_API_KEY'));
    $register(getenv('GEMINI_KEY'));

    // 2. Check comma-separated variable
    $register(getenv('GEMINI_API_KEYS'));

    // 3. Check numbered keys: GEMINI_API_KEY_1 to GEMINI_API_KEY_25
    for ($i = 1; $i <= 25; $i++) {
        $register(getenv("GEMINI_API_KEY_$i"));
        $register($_ENV["GEMINI_API_KEY_$i"] ?? null);
        $register($_SERVER["GEMINI_API_KEY_$i"] ?? null);
    }

    // 4. Dynamically scan all $_ENV and $_SERVER for any GEMINI key variable
    foreach (array_merge($_ENV, $_SERVER) as $k => $v) {
        if (is_string($v) && (str_starts_with($k, 'GEMINI_API_KEY') || str_starts_with($k, 'GEMINI_KEY'))) {
            $register($v);
        }
    }

    return $keys;
}

/**
 * Send a prompt to Gemini with automatic API key rotation and model failover.
 *
 * - On 429 (rate-limit): switches to the next API key.
 * - On 503 (high demand) / 404: automatically fails over to the next available model.
 * - On transient server errors (5xx): retries with back-off.
 *
 * @param string      $prompt            The full prompt to send.
 * @param int         $timeout           cURL execution timeout in seconds (default: 90).
 * @param int         $maxRetries        Per-key retry count for 5xx / connection errors.
 * @param string|null $systemInstruction Custom system persona/instruction (defaults to ApexPrep coach).
 * @param float|null  $temperature       Model temperature (e.g. 0.3 for strict parsing, 0.7 for creative questions).
 * @return array ['success' => bool, 'text' => string, 'error' => string|null]
 */
function callGemini(
    string $prompt,
    int $timeout = 90,
    int $maxRetries = 2,
    ?string $systemInstruction = null,
    ?float $temperature = null
): array
{
    $keys   = getGeminiKeys();
    $models = getGeminiModels();

    if (empty($keys)) {
        return [
            'success' => false,
            'text'    => '',
            'error'   => 'No Gemini API keys configured. Add GEMINI_API_KEY_1 to your .env file.',
        ];
    }

    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ]
    ];

    // Apply smart persona system instruction
    $effectiveSys = $systemInstruction ?? (class_exists('GeminiPrompts') ? GeminiPrompts::getSystemInstruction() : null);
    if (!empty($effectiveSys)) {
        $payload['system_instruction'] = [
            'parts' => [
                ['text' => trim($effectiveSys)]
            ]
        ];
    }

    if ($temperature !== null) {
        $payload['generationConfig'] = [
            'temperature' => $temperature
        ];
    }

    $jsonPayload = json_encode($payload);
    $lastError   = '';

    // Outer loop: failover across models if a model is unavailable or overloaded (503)
    foreach ($models as $modelName) {
        $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/' . $modelName . ':generateContent';

        // Inner loop: try each API key
        foreach ($keys as $keyIndex => $apiKey) {
            $keyLabel = "[$modelName#Key" . ($keyIndex + 1) . "]";

            try {
                for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                    $ch = curl_init($apiUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST,           true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS,     $jsonPayload);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Content-Type: application/json',
                        'x-goog-api-key: ' . $apiKey,
                    ]);
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 12);
                    curl_setopt($ch, CURLOPT_TIMEOUT,        $timeout);

                    $response  = curl_exec($ch);
                    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curlError = curl_error($ch);
                    curl_close($ch);

                    // Connection error
                    if ($curlError) {
                        $lastError = "$keyLabel Connection error: $curlError";
                        if ($attempt < $maxRetries) {
                            usleep(1_000_000);
                            continue;
                        }
                        break; // Try next key
                    }

                    // 429 Rate Limit on this key -> switch to next key immediately
                    if ($httpCode === 429) {
                        $lastError = "$keyLabel Rate limit reached (429) — trying next key.";
                        break; // Try next key
                    }

                    // 503 Model High Demand -> immediately fail over to next MODEL
                    if ($httpCode === 503) {
                        $lastError = "$keyLabel Model $modelName high demand (503) — trying alternate model.";
                        break 2; // Jump out of key loop, try next model!
                    }

                    // 404 Model Not Found -> immediately fail over to next MODEL
                    if ($httpCode === 404) {
                        $lastError = "$keyLabel Model $modelName unavailable (404) — trying alternate model.";
                        break 2; // Jump out of key loop, try next model!
                    }

                    // Other 4xx errors
                    if ($httpCode >= 400 && $httpCode < 500) {
                        $decoded     = json_decode($response, true);
                        $errorDetail = $decoded['error']['message'] ?? "HTTP $httpCode";
                        $lastError   = "$keyLabel API error: $errorDetail";
                        break; // Try next key
                    }

                    // 5xx Server errors
                    if ($httpCode >= 500) {
                        $lastError = "$keyLabel Server error HTTP $httpCode (attempt $attempt)";
                        if ($attempt < $maxRetries) {
                            usleep(1_000_000);
                            continue;
                        }
                        break; // Try next key
                    }

                    // 200 Success
                    $result = json_decode($response, true);
                    $text   = '';
                    if (!empty($result['candidates'][0]['content']['parts'])) {
                        foreach ($result['candidates'][0]['content']['parts'] as $part) {
                            if (!empty($part['text'])) {
                                $text .= $part['text'];
                            }
                        }
                    }

                    if ($text === '') {
                        $lastError = "$keyLabel Empty content parts returned";
                        break;
                    }

                    return ['success' => true, 'text' => $text, 'error' => null];
                }
            } catch (\Throwable $e) {
                $lastError = "$keyLabel Unexpected error: " . $e->getMessage();
                continue;
            }
        } // end key loop
    } // end model loop

    return [
        'success' => false,
        'text'    => '',
        'error'   => 'All Gemini API keys & model endpoints exhausted. Last error: ' . $lastError,
    ];
}

/**
 * Gemini sometimes wraps JSON answers in ```json ... ``` fences or returns extra text.
 * This strips those and safely decodes the JSON into a PHP array.
 *
 * @param string $rawText
 * @return array|null Decoded array, or null if it wasn't valid JSON.
 */
function extractJsonFromGemini(string $rawText): ?array
{
    // 1. Try stripping markdown fences
    $clean = preg_replace('/^```(?:json)?\s*/i', '', trim($rawText));
    $clean = preg_replace('/\s*```$/i', '', $clean);
    $decoded = json_decode(trim($clean), true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        return $decoded;
    }

    // 2. Try extracting JSON array [ ... ]
    if (preg_match('/\[\s*\{.*\}\s*\]/s', $rawText, $matches)) {
        $decoded = json_decode($matches[0], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }
    }

    // 3. Try extracting JSON object { ... }
    if (preg_match('/\{.*\}/s', $rawText, $matches)) {
        $decoded = json_decode($matches[0], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }
    }

    return null;
}
