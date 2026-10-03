<?php
/*
|--------------------------------------------------------------------------
| LIVE INTERVIEW - GEMINI CONVERSATION ENGINE
|--------------------------------------------------------------------------
| Integrated into Projectail. Uses main config/gemini_config.php and
| config/db_connect.php. Authenticated via session user_id.
| Mirrors the logic from live_ai_interview/api/interview_ai.php
| but uses the projectail DB tables (live_interview_sessions, etc.)
|--------------------------------------------------------------------------
*/

ini_set('display_errors', '0');
ini_set('log_errors', '1');
set_time_limit(60);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// -- Auth --
require_once __DIR__ . '/../config/app_config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}
$lastSessionModel = $_SESSION['live_ai_last_model'] ?? '';
session_write_close(); // Immediately release PHP session lock to prevent blocking concurrent requests

// -- Config --
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/gemini_config.php';
require_once __DIR__ . '/../includes/functions.php';

function send_live_json(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function clean_live_speech_text(string $text): string
{
    $cleaned = preg_replace('/```[\s\S]*?```/', '', $text);
    $cleaned = preg_replace('/`([^`]+)`/', '$1', $cleaned);
    $cleaned = preg_replace('/(\*\*|__)(.*?)\1/', '$2', $cleaned);
    $cleaned = preg_replace('/(\*|_)(.*?)\1/', '$2', $cleaned);
    $cleaned = preg_replace('/^#+\s+/m', '', $cleaned);
    $cleaned = preg_replace('/^\s*[-*+]\s+/m', '', $cleaned);
    $cleaned = preg_replace('/^\s*\d+\.\s+/m', '', $cleaned);
    $cleaned = preg_replace('/\s+/u', ' ', $cleaned);
    return trim($cleaned);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_live_json(['success' => false, 'message' => 'POST required.'], 405);
}

$rawInput   = file_get_contents('php://input');
$input      = json_decode($rawInput ?: '', true);

if (!is_array($input)) {
    send_live_json(['success' => false, 'message' => 'Invalid JSON.'], 400);
}

$mode                 = isset($input['mode'])           ? trim((string) $input['mode'])           : 'conversation';
$preparation          = isset($input['preparation'])    ? trim((string) $input['preparation'])    : '';
$answer               = isset($input['answer'])         ? trim((string) $input['answer'])         : '';
$history              = (isset($input['history']) && is_array($input['history'])) ? $input['history'] : [];
$interviewSeconds     = isset($input['interview_seconds'])     ? max(0, (int) $input['interview_seconds'])     : 0;
$consecutiveStruggles = isset($input['consecutive_struggles']) ? max(0, (int) $input['consecutive_struggles']) : 0;
$interviewId          = isset($input['interview_id'])   ? (int) $input['interview_id']            : 0;
$answerQuality        = isset($input['answer_quality']) ? trim((string) $input['answer_quality']) : 'normal'; // 'easy' | 'normal' | 'hard'

if ($preparation === '') $preparation = 'General software interview and viva preparation';

// -- Struggle & Early Exit Detection --
$answerLower  = strtolower(trim($answer));
$isStruggling = false;
$explicitEnd  = false;

if ($mode === 'skip') {
    // Deliberate skip or 30s silence skip should NEVER prematurely abort the session
    $isStruggling = false;
    $consecutiveStruggles = max(0, $consecutiveStruggles - 1);
    if ($answer === '') {
        $answer = "I would like to move on to the next question.";
    }
} else {
    if (preg_match('/\b(stop the interview|end the interview|stop interview|end interview|exit|i want to exit|i want to quit|please stop|finish interview)\b/i', $answerLower)) {
        $explicitEnd  = true;
        $isStruggling = true;
    } elseif (
        preg_match('/\b(don\'?t know|do not know|no idea|not sure|can\'?t answer|cannot answer|haven\'?t studied|haven\'?t learned|have not learned|no answer|idk)\b/i', $answerLower)
        || (strlen($answerLower) > 0 && strlen($answerLower) <= 5 && in_array($answerLower, ['no', 'na', 'nil', 'none', 'idk', 'cant']))
    ) {
        $isStruggling = true;
    }

    if ($isStruggling) $consecutiveStruggles++;
    elseif (strlen($answerLower) > 20) $consecutiveStruggles = max(0, $consecutiveStruggles - 1);
}

$shouldConclude = false;
$concludeReason = '';

// Count how many questions were asked
$aiQuestionsCount = 0;
foreach ($history as $h) {
    if (is_array($h) && in_array(strtolower($h['role'] ?? ''), ['model', 'assistant', 'interviewer', 'ai'], true)) {
        $aiQuestionsCount++;
    }
}

if ($explicitEnd)                    { $shouldConclude = true; $concludeReason = 'CANDIDATE_REQUESTED_END'; }
elseif ($interviewSeconds >= 540)    { $shouldConclude = true; $concludeReason = 'TIME_LIMIT_REACHED'; }
elseif ($aiQuestionsCount >= 9)      { $shouldConclude = true; $concludeReason = 'QUESTIONS_LIMIT_REACHED'; }
elseif ($consecutiveStruggles >= 3)  { $shouldConclude = true; $concludeReason = 'STRUGGLE_REPEAT'; }

// -- Interview Type Guide --
$prepLower         = strtolower($preparation);
$interviewTypeGuide = '';

if (strpos($prepLower, 'viva') !== false || strpos($prepLower, 'oral exam') !== false) {
    if (strpos($prepLower, 'project') !== false) {
        $interviewTypeGuide = "INTERVIEW FLAVOR: FINAL YEAR / COLLEGE PROJECT VIVA.\nYou are an academic project examiner. Ask simple, clear questions about the project's purpose, main modules, database, and technologies used.";
    } else {
        $interviewTypeGuide = "INTERVIEW FLAVOR: ACADEMIC SUBJECT VIVA (ORAL EXAM).\nYou are an oral examiner. Test foundational concepts with simple, straightforward questions.";
    }
} elseif (strpos($prepLower, 'hr') !== false || strpos($prepLower, 'behavioral') !== false) {
    $interviewTypeGuide = "INTERVIEW FLAVOR: HR & BEHAVIORAL INTERVIEW.\nYou are a warm HR interviewer. Ask simple, relatable questions about teamwork, motivations, and daily communication.";
} else {
    $interviewTypeGuide = "INTERVIEW FLAVOR: PROFESSIONAL TECHNICAL INTERVIEW.\nYou are a friendly, encouraging technical interviewer. Test fundamental concepts, everyday tools, and practical basics with simple questions that any candidate can comfortably answer.";
}

$questionNumber = isset($input['question_number']) ? (int) $input['question_number'] : $aiQuestionsCount;
$wordCount      = str_word_count($answer);
$isFirstAnswer  = ($questionNumber <= 1 || $aiQuestionsCount <= 1);
$isLowPerformance  = ($answerQuality === 'easy' || $isStruggling || ($answerQuality !== 'hard' && $wordCount < 18));
$isHighPerformance = ($answerQuality === 'hard' || ($wordCount >= 30 && !$isStruggling && $answerQuality !== 'easy'));

// -- Session State Guidance --
$sessionStateGuidance = '';
if ($shouldConclude) {
    if ($concludeReason === 'TIME_LIMIT_REACHED') {
        $sessionStateGuidance = "CRITICAL DIRECTIVE - 10-MINUTE TIME LIMIT REACHED:\nThe interview has reached its allotted 10-minute duration.\n- Conclude the interview now.\n- Deliver a warm, encouraging closing remark evaluating their answers and thanking them.\n- Start your response with [CONCLUDE].\n- DO NOT ask any further questions.";
    } elseif ($concludeReason === 'QUESTIONS_LIMIT_REACHED') {
        $sessionStateGuidance = "CRITICAL DIRECTIVE - 10 QUESTIONS REACHED:\nThe candidate has completed all 10 interview questions.\n- Deliver a warm, encouraging closing remark evaluating their answers and thanking them.\n- Start your response with [CONCLUDE].\n- DO NOT ask any further questions.";
    } elseif ($concludeReason === 'STRUGGLE_REPEAT' || $concludeReason === 'CANDIDATE_REQUESTED_END') {
        $sessionStateGuidance = "CRITICAL DIRECTIVE - CANDIDATE IS STRUGGLING OR REQUESTED END:\n- Do NOT ask another question.\n- Speak with genuine warmth and encouragement.\n- Advise them to study the fundamental concepts of {$preparation} and retry.\n- Start your response with [CONCLUDE].\n- DO NOT ask any further questions.";
    }
} elseif ($mode === 'skip') {
    $sessionStateGuidance = "CRITICAL DIRECTIVE - CANDIDATE REQUESTED NEXT QUESTION:\nThe candidate moved on to the next question.\n- Smoothly acknowledge with a brief 3-5 word transition (e.g., 'Understood, let's move forward.').\n- Do NOT explain or answer the skipped question.\n- Immediately ask your NEXT question: pick an easy, fundamental concept in {$preparation} that is guaranteed simple to answer.\n- Do NOT conclude the interview.";
} elseif ($isFirstAnswer) {
    // ── SPECIFIC RULE: ADAPTIVE 2ND QUESTION BASED ON 1ST ANSWER ──
    if ($isLowPerformance) {
        $sessionStateGuidance = "CRITICAL DIRECTIVE - 1ST ANSWER WAS LOW PERFORMANCE (REDUCE DIFFICULTY FOR 2ND QUESTION):\n" .
            "The candidate's 1st answer/introduction had low performance (brief, hesitant, or uncertain, {$wordCount} words).\n" .
            "For Question 2, you MUST significantly REDUCE the difficulty compared to Question 1:\n" .
            "- Reassure them warmly in 3-5 words (e.g., 'No problem, let us start simple.').\n" .
            "- Ask an ultra-simple, beginner-level Question 2 that is guaranteed easy to answer.\n" .
            "- Focus on the most basic high-level purpose or definition in {$preparation} (e.g., 'In simple words, what is {$preparation} mainly used for?' or 'What is a variable in programming?').\n" .
            "- DO NOT ask multi-part, architectural, or advanced questions.";
    } elseif ($isHighPerformance) {
        $sessionStateGuidance = "CRITICAL DIRECTIVE - 1ST ANSWER WAS STRONG & CONFIDENT:\n" .
            "The candidate gave a strong, detailed introduction.\n" .
            "For Question 2, acknowledge enthusiastically in 3-5 words, and ask a clean, fundamental question directly related to one skill or tool they mentioned in their introduction.";
    } else {
        $sessionStateGuidance = "TRANSITION TO 2ND QUESTION:\n" .
            "The candidate gave a standard introduction. Acknowledge warmly and ask a clear, simple foundational question about {$preparation} to ease them into the interview.";
    }
} elseif ($consecutiveStruggles >= 1 || $isLowPerformance) {
    $sessionStateGuidance = "ADAPTIVE GUIDANCE - LOW PERFORMANCE ANSWER DETECTED (REDUCE DIFFICULTY):\n" .
        "The candidate gave a short, incomplete, or struggling answer.\n" .
        "You MUST REDUCE the difficulty of the next question:\n" .
        "- Gently reassure them in 3-5 words.\n" .
        "- Ask an easier, foundational question that is guaranteed simple to answer.\n" .
        "- Focus on high-level purpose or basic definitions rather than syntax or details.";
} elseif ($answerQuality === 'hard') {
    $sessionStateGuidance = "ADAPTIVE GUIDANCE - GOOD ANSWER:\nThe candidate gave a good response. Acknowledge warmly, and ask another clear, straightforward question on core principles or everyday practical usage in {$preparation}. Keep it simple, clear, and easy to understand.";
} else {
    $sessionStateGuidance = "ADAPTIVE GUIDANCE - NORMAL ANSWER:\nAcknowledge briefly and ask a simple, clear fundamental question.";
}

// -- Master System Prompt --
$systemPrompt = <<<PROMPT
You are a warm, friendly, natural human interviewer conducting a live spoken interview.
You are sitting across from the candidate in a real video interview room.

{$interviewTypeGuide}

CANDIDATE'S PREPARATION FOCUS:
"{$preparation}"

{$sessionStateGuidance}

CRITICAL RULES FOR SPOKEN INTERVIEW:
1. ASK SIMPLE, GUARANTEED-ANSWERABLE QUESTIONS:
   - Ask ONLY simple, fundamental, everyday core concepts in "{$preparation}".
   - Make questions crystal clear, friendly, and easy to understand so the candidate is GUARANTEED to answer with confidence.
   - NEVER ask complex algorithms, tricky edge cases, deep system design, or obscure trivia.
   - Keep questions to practical basics (e.g., "What is the primary purpose of...", "In simple terms, what is...", "What is the basic difference between...", "Can you share a simple example of where you would use...").
2. CONCISE SPOKEN FLOW (1 SHORT PHRASE + 1 SIMPLE QUESTION):
   - First: 1 very short acknowledgement/transition (3-6 words, e.g., "Great point.", "That makes sense.", "Thanks for explaining that.").
   - Second: EXACTLY ONE simple question (1 short sentence, max 15 words).
   - Total output must be under 30 words so it sounds natural when spoken aloud.
3. NEVER EXPLAIN OR TEACH:
   - You are the interviewer, not a tutor. Never say "The correct answer is...", never explain what they missed.
4. NO EXAM FORMATTING:
   - Never say "Question 1", bullet points, markdown bolding, or lists. Talk naturally as a human.
5. IF CONCLUDING UNDER [CONCLUDE]:
   - Deliver an encouraging closing remark and DO NOT ask any question.
PROMPT;

// -- Assemble Conversation History --
$contents    = [];
$trimmedHistory = count($history) > 10 ? array_slice($history, -10) : $history;

foreach ($trimmedHistory as $item) {
    if (!is_array($item)) continue;
    $rawRole = isset($item['role']) ? strtolower(trim((string) $item['role'])) : '';
    $rawText = isset($item['text']) ? trim((string) $item['text']) : (isset($item['content']) ? trim((string) $item['content']) : '');
    if ($rawText === '') continue;
    $geminiRole = in_array($rawRole, ['model', 'interviewer', 'assistant', 'ai']) ? 'model' : 'user';
    $contents[] = ['role' => $geminiRole, 'parts' => [['text' => $rawText]]];
}

$finalReply = '';
$usedModel  = '';

// -- Opening Turn - instant local reply (no API call) --
if ($mode === 'opening') {
    $cleanPrep = trim(htmlspecialchars($preparation, ENT_QUOTES, 'UTF-8'));
    if (strpos($prepLower, 'viva') !== false || strpos($prepLower, 'oral exam') !== false) {
        $finalReply = "Hello and welcome to your viva session. I will be your examiner today. To begin, could you please introduce yourself and provide a brief overview of your work on {$cleanPrep}?";
    } elseif (strpos($prepLower, 'project') !== false) {
        $finalReply = "Welcome to your project evaluation session. To get us started, please introduce yourself and walk me through the high-level architecture of {$cleanPrep}.";
    } elseif (strpos($prepLower, 'hr') !== false || strpos($prepLower, 'behavioral') !== false) {
        $finalReply = "Hello and welcome! I am pleased to meet you today. To get us started, could you please introduce yourself and tell me what brings you to this interview?";
    } else {
        $finalReply = "Hello and welcome to your interview! I'll be your interviewer today. To get us started, could you please introduce yourself and give me an overview of your background and experience with {$cleanPrep}?";
    }
    $usedModel = 'instant-opening';
} else {
    // -- Conversation Turn - Gemini Call --
    if (trim($answer) === '') {
        send_live_json(['success' => false, 'message' => 'Candidate answer was empty.'], 400);
    }

    $contents[] = ['role' => 'user', 'parts' => [['text' => $answer]]];

    // Use the main projectail Gemini keys (from gemini_config.php)
    $apiKeys = getGeminiKeys();
    if (empty($apiKeys)) {
        send_live_json(['success' => false, 'message' => 'No Gemini API keys configured.'], 500);
    }

    // High-speed model order from central config (automatic blocklist filtering & failover)
    $fastModels = getGeminiModels();

    // Check if we already have a known working model from previous turn
    if (!empty($lastSessionModel) && in_array($lastSessionModel, $fastModels, true)) {
        $fastModels = array_values(array_unique(array_merge([$lastSessionModel], $fastModels)));
    }

    $deadline = microtime(true) + 8.0; // Max 8 seconds overall search deadline
    $overloadedModels = [];

    foreach ($fastModels as $model) {
        if (microtime(true) >= $deadline) break;
        if (isset($overloadedModels[$model])) continue;

        foreach ($apiKeys as $apiKey) {
            if (microtime(true) >= $deadline) break;
            if (trim($apiKey) === '') continue;

            $url     = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';
            $payload = [
                'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
                'contents'          => $contents,
                'generationConfig'  => [
                    'temperature'     => 0.6,
                    'topP'            => 0.85,
                    'maxOutputTokens' => 75 // Short spoken response (1 phrase + 1 question) generates in ~300ms
                ]
            ];

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'x-goog-api-key: ' . trim($apiKey)],
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT        => 3,
            ]);

            $response   = curl_exec($ch);
            $status     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError  = curl_error($ch);
            curl_close($ch);

            if ($response === false) continue;
            $json = json_decode($response, true);

            if ($status >= 200 && $status < 300) {
                $reply = '';
                if (isset($json['candidates'][0]['content']['parts']) && is_array($json['candidates'][0]['content']['parts'])) {
                    foreach ($json['candidates'][0]['content']['parts'] as $part) {
                        if (isset($part['text']) && is_string($part['text'])) $reply .= $part['text'];
                    }
                }
                $reply = clean_live_speech_text($reply);
                if ($reply !== '') {
                    $finalReply = $reply;
                    $usedModel = $model;
                    break 2;
                }
            } elseif ($status === 503 || $status === 404) {
                // High demand on Google's model or missing - skip this model for all keys immediately
                $overloadedModels[$model] = true;
                break;
            } elseif ($status === 429 || $status === 401 || $status === 403) {
                // Rate limited on this key, try next key
                continue;
            }
        }
        if ($finalReply !== '') break;
    }

    if ($finalReply === '') {
        // Dynamic smart contextual fallback based on candidate answer & topic
        $ansWords = array_filter(explode(' ', strtolower($answer)));
        $topicClean = trim(htmlspecialchars($preparation, ENT_QUOTES, 'UTF-8'));
        if ($mode === 'skip') {
            $finalReply = "No problem at all, let's move forward. Could you share an experience or key concept you worked with in {$topicClean}?";
        } elseif (strpos(strtolower($answer), 'project') !== false || strpos(strtolower($answer), 'built') !== false) {
            $finalReply = "That sounds like a meaningful implementation. What was the most critical architectural decision or trade-off you had to make while building it?";
        } elseif (strpos(strtolower($answer), 'database') !== false || strpos(strtolower($answer), 'sql') !== false) {
            $finalReply = "Great. How did you structure the schema and ensure optimal query performance and indexing under heavy load?";
        } elseif (strpos(strtolower($answer), 'api') !== false || strpos(strtolower($answer), 'service') !== false) {
            $finalReply = "Understood. How do you handle failure scenarios, retries, and data consistency across your services?";
        } else {
            $finalReply = "Thank you for explaining that. Could you describe a challenging scenario you encountered in {$topicClean} and walk me through your troubleshooting approach?";
        }
        $usedModel = 'fast-adaptive-turn';
    }
}

// -- Process Conclusion --
$isConcluded = false;
if (strpos($finalReply, '[CONCLUDE]') !== false || $shouldConclude) {
    $isConcluded = true;
    $finalReply  = str_replace('[CONCLUDE]', '', $finalReply);
    $finalReply  = clean_live_speech_text($finalReply);
}

// -- Database Logging --
try {
    if ($mode === 'opening') {
        $category  = (strpos($prepLower, 'viva') !== false) ? 'viva' : ((strpos($prepLower, 'hr') !== false) ? 'hr' : 'technical');
        $stmtIns   = $pdo->prepare("INSERT INTO live_interview_sessions (user_id, role_name, category, status, started_at, created_at) VALUES (?, ?, ?, 'active', NOW(), NOW())");
        $stmtIns->execute([$userId, mb_substr($preparation, 0, 255), $category]);
        $interviewId = (int) $pdo->lastInsertId();
    }

    if ($interviewId > 0) {
        $qNum   = count($history) + 1;
        $stmtQ  = $pdo->prepare("INSERT INTO live_interview_questions (interview_id, question_number, question_text, created_at) VALUES (?, ?, ?, NOW())");
        $stmtQ->execute([$interviewId, $qNum, $finalReply]);
        $qId    = (int) $pdo->lastInsertId();

        if ($answer !== '' && $mode !== 'opening') {
            $stmtA = $pdo->prepare("INSERT INTO live_interview_answers (interview_id, question_id, answer_text, response_time_seconds, created_at) VALUES (?, ?, ?, 5, NOW())");
            $stmtA->execute([$interviewId, max(1, $qId - 1), $answer]);
        }

        if ($isConcluded) {
            $pdo->prepare("UPDATE live_interview_sessions SET status='completed', completed_at=NOW(), role_name=IFNULL(role_name,?) WHERE id=?")->execute([$preparation, $interviewId]);
        }
    }
} catch (\Throwable $e) {
    error_log('[live_interview_ai] DB: ' . $e->getMessage());
}

send_live_json([
    'success'               => true,
    'reply'                 => $finalReply,
    'interview_id'          => $interviewId,
    'interview_ended'       => $isConcluded,
    'conclude_reason'       => $concludeReason,
    'consecutive_struggles' => $consecutiveStruggles,
    'provider'              => 'gemini',
    'model'                 => $usedModel,
]);
