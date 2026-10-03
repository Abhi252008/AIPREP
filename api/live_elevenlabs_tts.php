<?php
/*
|--------------------------------------------------------------------------
| LIVE INTERVIEW — ELEVENLABS TTS PROXY (MULTI-KEY & MULTI-MODEL)
|--------------------------------------------------------------------------
| Authenticated proxy for ElevenLabs TTS.
| Supports:
|   - Multi-key rotation (ELEVENLABS_API_KEY_1, _2, _3, ELEVENLABS_API_KEYS)
|   - Automatic failover on 429 quota exhaustion or 401
|   - Multi-model failover (eleven_flash_v2_5, eleven_turbo_v2_5, eleven_multilingual_v2, etc.)
|   - Graceful fallback to browser speech synthesis
|--------------------------------------------------------------------------
*/

ini_set('display_errors', '0');

// Auth
require_once __DIR__ . '/../config/app_config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}
session_write_close(); // Release session lock immediately so concurrent requests don't block

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required.']);
    exit;
}

$input   = json_decode(file_get_contents('php://input') ?: '', true);
$text    = isset($input['text'])     ? trim((string) $input['text'])     : '';
$voiceId = isset($input['voice_id']) ? trim((string) $input['voice_id']) : 'JBFqnCBsd6RMkjVDRZzb';

if ($text === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Text is required.']);
    exit;
}

if (mb_strlen($text) > 4000) $text = mb_substr($text, 0, 4000);

// Load environment variables
require_once __DIR__ . '/../config/env.php';
load_env(__DIR__ . '/../.env');

/**
 * Discovers and returns all available ElevenLabs API keys.
 * Supports:
 *   - ELEVENLABS_API_KEY_1, ELEVENLABS_API_KEY_2, ELEVENLABS_API_KEY_3 ... up to 15
 *   - ELEVENLABS_API_KEYS (comma-separated list)
 *   - ELEVENLABS_API_KEY
 *   - ELEVEN_API_KEY, ELEVEN_API_KEY_1 ...
 *
 * @return string[]
 */
function getElevenLabsKeys(): array
{
    $keys = [];
    $register = function (?string $val) use (&$keys) {
        if (!$val) return;
        $candidates = str_contains($val, ',') ? explode(',', $val) : [$val];
        foreach ($candidates as $cand) {
            $trimmed = trim($cand, " \t\n\r\0\x0B\"'");
            // Skip placeholders or empty keys
            if (
                $trimmed !== '' &&
                !in_array($trimmed, $keys, true) &&
                !str_contains(strtolower($trimmed), 'your_') &&
                !str_contains(strtolower($trimmed), 'key_here') &&
                !str_contains(strtolower($trimmed), 'placeholder')
            ) {
                $keys[] = $trimmed;
            }
        }
    };

    // 1. Check numbered keys: ELEVENLABS_API_KEY_1 to 15
    for ($i = 1; $i <= 15; $i++) {
        $register(getenv("ELEVENLABS_API_KEY_$i"));
        $register($_ENV["ELEVENLABS_API_KEY_$i"] ?? null);
        $register($_SERVER["ELEVENLABS_API_KEY_$i"] ?? null);
    }

    // 2. Check comma-separated variable
    $register(getenv('ELEVENLABS_API_KEYS'));

    // 3. Check singular keys
    $register(getenv('ELEVENLABS_API_KEY'));
    $register(getenv('ELEVEN_API_KEY'));

    // 4. Check alternate numbered keys
    for ($i = 1; $i <= 10; $i++) {
        $register(getenv("ELEVEN_API_KEY_$i"));
    }

    // 5. Scan any remaining environment variables
    foreach (array_merge($_ENV, $_SERVER) as $k => $v) {
        if (is_string($v) && (str_starts_with($k, 'ELEVENLABS_API_KEY') || str_starts_with($k, 'ELEVEN_API_KEY'))) {
            $register($v);
        }
    }

    return $keys;
}

/**
 * Returns prioritized list of ElevenLabs models.
 *
 * @return string[]
 */
function getElevenLabsModels(): array
{
    $models = [];
    $envModel = trim((string) (getenv('ELEVENLABS_MODEL') ?: ''));
    if ($envModel !== '') {
        $models[] = $envModel;
    }

    $fallbacks = [
        'eleven_flash_v2_5',     // Ultra-low latency (~75ms), ideal for live conversational AI
        'eleven_turbo_v2_5',     // Low latency high quality backup
        'eleven_multilingual_v2',// Rich expressive conversational model
        'eleven_flash_v2'        // Fast flash v2
    ];

    foreach ($fallbacks as $m) {
        if (!in_array($m, $models, true)) {
            $models[] = $m;
        }
    }

    return $models;
}

$keys   = getElevenLabsKeys();
$models = getElevenLabsModels();

if (empty($keys)) {
    // No valid key configured — return 404 so JS falls back to browser speech synthesis
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'No active ElevenLabs API keys configured.']);
    exit;
}

$ttsDeadline = microtime(true) + 3.0; // Max 3.0s total search budget

// Failover loop: Iterate through available API keys and models
foreach ($keys as $keyIndex => $apiKey) {
    if (microtime(true) >= $ttsDeadline) break;
    foreach ($models as $modelId) {
        if (microtime(true) >= $ttsDeadline) break;
        $url = 'https://api.elevenlabs.io/v1/text-to-speech/' . rawurlencode($voiceId) . '?optimize_streaming_latency=4';
        $payload = [
            'text'           => $text,
            'model_id'       => $modelId,
            'voice_settings' => [
                'stability'         => 0.5,
                'similarity_boost'  => 0.75,
                'style'             => 0.0,
                'use_speaker_boost' => true
            ]
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: audio/mpeg',
                'xi-api-key: ' . $apiKey
            ],
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_TIMEOUT        => 2,
        ]);

        $response = curl_exec($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($response !== false && $status >= 200 && $status < 300 && strlen($response) > 500) {
            header('Content-Type: audio/mpeg');
            header('Content-Length: ' . strlen($response));
            header('X-ElevenLabs-Key-Index: ' . ($keyIndex + 1));
            header('X-ElevenLabs-Model: ' . $modelId);
            echo $response;
            exit;
        }

        $lastStatus = $status ?: 502;
        $lastError  = $curlErr ?: "HTTP $status on model $modelId";

        // If 401 or 429 (quota exhausted on this key), break inner loop to immediately try next API KEY
        if ($status === 401 || $status === 429) {
            break;
        }

        // If 404 or 400 (model issue), continue inner loop to try next MODEL
        if ($status === 404 || $status === 400) {
            continue;
        }
    }
}

// All keys or models exhausted -> client falls back to browser voice synthesis
http_response_code($lastStatus ?: 502);
echo json_encode([
    'success' => false,
    'message' => 'ElevenLabs unavailable (' . $lastError . '). Falling back to browser voice.'
]);
