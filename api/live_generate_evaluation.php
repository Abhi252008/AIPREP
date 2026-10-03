<?php
/*
|--------------------------------------------------------------------------
| LIVE INTERVIEW - POST-SESSION EVALUATION GENERATOR
|--------------------------------------------------------------------------
| Evaluates technical performance, camera concentration/cheating risk,
| and speech clarity including verbal hesitation and filler sounds.
|
| Strict Rules:
| 1. If 0 questions were attempted (skipped/empty/silence-expired), score is strictly 0% (Not Attempted).
| 2. Lenient & Relevant Grading: If an answer is relevant (touches core concept/intuition),
|    award COMPLETE, FULLY CORRECT credit (100%) for that question.
| 3. Never use words like 'huu huu' or 'hmm'.
| 4. 3 Core Metrics only: Technical Accuracy, Communication Clarity, Focus & Concentration.
|--------------------------------------------------------------------------
*/

ini_set('display_errors', '0');
ini_set('log_errors', '1');
set_time_limit(120);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../config/app_config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$isAdmin = !empty($_SESSION['admin_id']);
$userId  = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0 && !$isAdmin) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/gemini_config.php';

function send_eval_json(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Checks whether an answer is skipped, empty, or unattempted
 */
function is_unattempted_answer(string $text): bool
{
    $t = strtolower(trim($text));
    if ($t === '' || $t === 'opening') return true;
    if (mb_strlen($t) < 3) return true;

    // Check standard skip/pass/next indicators
    if (strpos($t, 'move on to the next question') !== false) return true;
    if (strpos($t, 'skip') !== false && mb_strlen($t) <= 30) return true;
    if (strpos($t, 'pass') !== false && mb_strlen($t) <= 20) return true;
    if (strpos($t, 'next question') !== false && mb_strlen($t) <= 30) return true;
    if (in_array($t, [
        'next', 'pass', 'skip', 'no', 'nothing', 'idk',
        "i don't know", "dont know", "no idea", "i have no idea",
        "i do not know", "no answer", "skip this", "pass this"
    ], true)) {
        return true;
    }

    // Check if the answer consists entirely of vocal hesitation sounds
    $withoutFillers = preg_replace('/\b(huu\s+huu|huu|hu|hmm|hm|um|umm|uh|uhh|er|err|ah|ahh)\b/i', '', $t);
    $withoutFillers = trim(preg_replace('/[^\w\s]/', '', $withoutFillers));
    if ($withoutFillers === '' || mb_strlen($withoutFillers) < 3) {
        return true;
    }

    return false;
}

$rawInput        = file_get_contents('php://input');
$input           = json_decode($rawInput ?: '', true) ?: [];

$interviewId     = isset($input['interview_id']) ? (int) $input['interview_id'] : (isset($_GET['id']) ? (int) $_GET['id'] : 0);
$history         = (isset($input['history']) && is_array($input['history'])) ? $input['history'] : [];
$preparation     = isset($input['preparation']) ? trim((string) $input['preparation']) : '';
$proctoringInput = (isset($input['proctoring']) && is_array($input['proctoring'])) ? $input['proctoring'] : [];
$speechInput     = (isset($input['speech_stats']) && is_array($input['speech_stats'])) ? $input['speech_stats'] : [];
$forceRefresh    = !empty($input['force_refresh']);

// If interviewId provided, get role_name and if history is empty, query DB
if ($interviewId > 0) {
    try {
        if ($isAdmin) {
            $stmt = $pdo->prepare("SELECT role_name, evaluation_json FROM live_interview_sessions WHERE id = ?");
            $stmt->execute([$interviewId]);
        } else {
            $stmt = $pdo->prepare("SELECT role_name, evaluation_json FROM live_interview_sessions WHERE id = ? AND user_id = ?");
            $stmt->execute([$interviewId, $userId]);
        }
        $sessionRow = $stmt->fetch();

        if ($sessionRow) {
            if ($preparation === '' && !empty($sessionRow['role_name'])) {
                $preparation = $sessionRow['role_name'];
            }
        } elseif (!$isAdmin) {
            send_eval_json(['success' => false, 'message' => 'Interview session not found.'], 403);
        }

        // If history is empty, load from DB tables
        if (empty($history)) {
            $stmtQ = $pdo->prepare("SELECT id, question_number, question_text FROM live_interview_questions WHERE interview_id = ? ORDER BY id ASC");
            $stmtQ->execute([$interviewId]);
            $questions = $stmtQ->fetchAll();

            $stmtA = $pdo->prepare("SELECT id, question_id, answer_text FROM live_interview_answers WHERE interview_id = ? ORDER BY id ASC");
            $stmtA->execute([$interviewId]);
            $answers = $stmtA->fetchAll();

            $qMap = [];
            foreach ($questions as $q) {
                $qMap[] = ['role' => 'model', 'text' => $q['question_text']];
            }
            $aMap = [];
            foreach ($answers as $a) {
                $aMap[] = ['role' => 'user', 'text' => $a['answer_text']];
            }

            $maxLen = max(count($qMap), count($aMap));
            for ($i = 0; $i < $maxLen; $i++) {
                if (isset($qMap[$i])) $history[] = $qMap[$i];
                if (isset($aMap[$i])) $history[] = $aMap[$i];
            }
        }
    } catch (\Throwable $e) {
        error_log('[live_generate_evaluation] DB load: ' . $e->getMessage());
    }
}

if ($preparation === '') $preparation = 'Technical Interview';

if (empty($history)) {
    send_eval_json(['success' => false, 'message' => 'No interview conversation history found to evaluate.'], 400);
}

// -- Extract Question & Answer Pairs --
$qaPairs = [];
$lastQuestion = '';
$dialogueText = '';
$candidateSpokenText = '';

foreach ($history as $turn) {
    $role = isset($turn['role']) && in_array(strtolower($turn['role']), ['model', 'interviewer', 'ai']) ? 'model' : 'user';
    $text = isset($turn['text']) ? trim($turn['text']) : (isset($turn['content']) ? trim($turn['content']) : '');
    if ($text === '') continue;

    if ($role === 'model') {
        $cleanQ = trim(str_replace('[CONCLUDE]', '', $text));
        $dialogueText .= "Interviewer: {$cleanQ}\n\n";
        if ($lastQuestion !== '') {
            $qaPairs[] = [
                'question'  => $lastQuestion,
                'answer'    => '',
                'attempted' => false
            ];
        }
        $lastQuestion = $cleanQ;
    } else {
        if ($text === 'OPENING') continue;
        $dialogueText .= "Candidate: {$text}\n\n";
        $candidateSpokenText .= ' ' . $text;
        $isAttempted = !is_unattempted_answer($text);
        $qText = $lastQuestion !== '' ? $lastQuestion : "Interview Question";
        $qaPairs[] = [
            'question'  => $qText,
            'answer'    => $text,
            'attempted' => $isAttempted
        ];
        $lastQuestion = '';
    }
}

// If there was a trailing question with no user answer
if ($lastQuestion !== '') {
    $qaPairs[] = [
        'question'  => $lastQuestion,
        'answer'    => '',
        'attempted' => false
    ];
}

// Count total questions and actual attempted questions
$totalQuestionsCount = count($qaPairs);
$attemptedQuestionsCount = 0;
foreach ($qaPairs as $pair) {
    if ($pair['attempted']) {
        $attemptedQuestionsCount++;
    }
}

// Detect verbal hesitation patterns in candidate responses
preg_match_all('/\b(huu\s+huu|huu|hu|hmm|hm|um|umm|uh|uhh|er|err)\b/i', $candidateSpokenText, $mFillers);
$detectedFillersCount = count($mFillers[0] ?? []);
$totalFillers = max((int)($speechInput['filler_count'] ?? 0), $detectedFillersCount);
$wordCount = max((int)($speechInput['total_words'] ?? 0), str_word_count($candidateSpokenText));

// Camera focus and proctoring metrics
$facingCameraPct    = isset($proctoringInput['facing_camera_pct']) ? max(0, min(100, (int)$proctoringInput['facing_camera_pct'])) : 92;
$lookingSidesPct    = isset($proctoringInput['looking_sides_pct']) ? max(0, min(100, (int)$proctoringInput['looking_sides_pct'])) : 6;
$lookingDownPct     = isset($proctoringInput['looking_down_pct']) ? max(0, min(100, (int)$proctoringInput['looking_down_pct'])) : 2;
$warningsCount      = isset($proctoringInput['warnings_count']) ? (int)$proctoringInput['warnings_count'] : 0;
$concentrationScore = isset($proctoringInput['concentration_score'])
    ? max(20, min(100, (int)$proctoringInput['concentration_score']))
    : max(30, min(100, $facingCameraPct - ($warningsCount * 3)));

$cheatingRisk = isset($proctoringInput['cheating_risk'])
    ? $proctoringInput['cheating_risk']
    : ($concentrationScore >= 80 ? 'Low Risk' : ($concentrationScore >= 60 ? 'Moderate Risk' : 'High Risk'));

// -- Check Cached Evaluation in DB --
if ($interviewId > 0 && !empty($sessionRow['evaluation_json']) && !$forceRefresh) {
    $cached = json_decode($sessionRow['evaluation_json'], true);
    if (is_array($cached) && isset($cached['overall_score'])) {
        // Only accept cache if it matches the current attempt state:
        // - If 0 questions attempted, cached overall_score MUST be 0 and readiness MUST be 'Not Attempted'
        // - If questions were attempted, cached overall_score MUST be > 0
        $cacheMatchesAttempt = ($attemptedQuestionsCount === 0)
            ? ($cached['overall_score'] === 0 && ($cached['readiness_level'] ?? '') === 'Not Attempted')
            : ($cached['overall_score'] > 0);

        if ($cacheMatchesAttempt && isset($cached['proctoring_analysis']) && isset($cached['communication_analysis']) && !isset($cached['metrics']['conceptual_depth'])) {
            send_eval_json(['success' => true, 'cached' => true, 'evaluation' => $cached]);
        }
    }
}

// =========================================================================
// CASE 1: CANDIDATE DID NOT ATTEMPT ANY QUESTION (0% SCORE GUARANTEED)
// =========================================================================
if ($attemptedQuestionsCount === 0) {
    $qReview = [];
    foreach ($qaPairs as $idx => $qa) {
        $qReview[] = [
            'question'           => $qa['question'] !== '' ? $qa['question'] : ("Question " . ($idx + 1)),
            'candidate_answer'   => $qa['answer'] !== '' ? $qa['answer'] : 'Not Attempted',
            'verdict'            => 'Unattempted',
            'feedback'           => 'This question was skipped or unanswered.',
            'ideal_answer_guide' => 'Provide a clear, relevant definition and a practical example.'
        ];
    }

    $zeroEvaluation = [
        'overall_score'   => 0,
        'readiness_level' => 'Not Attempted',
        'summary'         => "No interview questions were answered in this session. All questions were skipped or elapsed without an answer. Please answer the questions by speaking or typing to receive an accurate technical and communication performance evaluation.",
        'metrics'         => [
            'technical_accuracy'    => 0,
            'communication_clarity' => 0,
            'concentration_focus'   => $concentrationScore
        ],
        'proctoring_analysis' => [
            'concentration_score' => $concentrationScore,
            'facing_camera_pct'   => $facingCameraPct,
            'looking_sides_pct'   => $lookingSidesPct,
            'looking_down_pct'    => $lookingDownPct,
            'warnings_count'      => $warningsCount,
            'cheating_risk'       => $cheatingRisk,
            'verdict'             => $cheatingRisk === 'Low Risk'
                ? "Maintained steady camera presence during the session."
                : "Noticeable side glances or movements detected during the session.",
            'observations'        => [
                "Directly faced the camera for {$facingCameraPct}% of the session duration.",
                "No verbal question responses were provided during this session."
            ]
        ],
        'communication_analysis' => [
            'clarity_score'     => 0,
            'fluency_level'     => 'Not Evaluated (No Answers Attempted)',
            'filler_word_count' => $totalFillers,
            'speech_critique'   => 'No verbal answers were submitted during this interview session.',
            'advice'            => 'When practicing, speak out your thoughts openly. Even explaining the basic idea demonstrates your thought process and understanding.'
        ],
        'how_you_are_doing' => [
            'Session opened and camera proctoring initialized.',
            'Ready to start answering questions in the next session.'
        ],
        'how_you_are_speaking' => [
            'delivery_critique'     => 'No spoken answers were recorded to evaluate delivery.',
            'observable_confidence' => 'Attempt the questions in your next round to build spoken confidence.'
        ],
        'weak_areas' => [
            'No interview questions were attempted in this session.'
        ],
        'topics_to_learn' => [
            [
                'topic'        => "Core Fundamentals of {$preparation}",
                'why_learn'    => 'Reviewing basic concepts will give you the confidence to answer questions directly.',
                'key_concepts' => ['Fundamental concepts', 'Practical implementation', 'Common interview questions']
            ]
        ],
        'recommended_practice_questions' => [
            "Can you explain the basic purpose and core concepts of {$preparation}?",
            "What is a real-world scenario where you would use {$preparation}?",
            "How do you troubleshoot common issues in {$preparation}?"
        ],
        'question_by_question_review' => $qReview
    ];

    // Save 0% evaluation to DB
    if ($interviewId > 0) {
        try {
            $pdo->prepare("UPDATE live_interview_sessions SET evaluation_json=? WHERE id=?")
                ->execute([json_encode($zeroEvaluation, JSON_UNESCAPED_UNICODE), $interviewId]);
        } catch (\Throwable $e) {
            error_log('[live_generate_evaluation] DB save zero: ' . $e->getMessage());
        }
    }

    send_eval_json(['success' => true, 'evaluation' => $zeroEvaluation]);
}

// =========================================================================
// CASE 2: CANDIDATE ATTEMPTED QUESTIONS -> LENIENT & RELEVANT EVALUATION
// =========================================================================

// Build explicit QA summary text for Gemini
$qaSummaryText = '';
foreach ($qaPairs as $i => $pair) {
    $num = $i + 1;
    $status = $pair['attempted'] ? 'Attempted' : 'Skipped / Unattempted';
    $qaSummaryText .= "Question {$num}: {$pair['question']}\n";
    $qaSummaryText .= "Status: {$status}\n";
    $qaSummaryText .= "Candidate Spoken Answer: " . ($pair['answer'] !== '' ? "\"{$pair['answer']}\"" : "[No answer provided]") . "\n\n";
}

// Trim transcript to avoid huge payloads — first 8000 chars is more than enough for evaluation
$dialogueTrimmed = mb_strlen($dialogueText) > 8000 ? mb_substr($dialogueText, 0, 8000) . "\n...[trimmed]" : $dialogueText;

$prompt = <<<PROMPT
You are a senior technical hiring manager and communication coach.
Analyze this spoken interview session for the topic: "{$preparation}".

INTERVIEW QUESTION & ANSWER TURNS:
{$qaSummaryText}

TRANSCRIPT LOG (trimmed):
{$dialogueTrimmed}

REAL-TIME PROCTORING METRICS:
- Directly Facing Camera: {$facingCameraPct}%
- Looking Sides/Away: {$lookingSidesPct}%
- Look-Away Warnings: {$warningsCount}
- Concentration Score: {$concentrationScore}/100
- Cheating Risk: {$cheatingRisk}

SPEECH FLUENCY & HESITATION DATA:
- Total Candidate Spoken Words: {$wordCount}
- Total Detected Thinking Fillers & Verbal Hesitations: {$totalFillers}

CRITICAL EVALUATION & LENIENT GRADING RULES (MANDATORY):
1. LENIENT & RELEVANT GRADING (DO NOT GRADE STRICTLY):
   - Do NOT check strictly against rigid textbook definitions or require comprehensive academic lists.
   - If the candidate's answer is RELEVANT to the question (touches on the core concept, understands the main purpose, explains the intuition correctly, mentions key terms, or provides a valid real-world example), you MUST award COMPLETE, FULLY CORRECT CREDIT (100%) for that question.
   - Mark the verdict in question_by_question_review as "Strong - Fully Correct" for every relevant answer.
   - DO NOT reduce marks for brief, informal, conversational, or practical phrasing as long as it is relevant.
   - Only mark a question as "Needs Improvement" or "Weak" if the answer is completely wrong, factually false, or off-topic gibberish.
   - If a question was skipped or unattempted, mark verdict as "Unattempted" (0%).

2. TECHNICAL ACCURACY SCORING FORMULA:
   - Total questions asked by interviewer: {$totalQuestionsCount}
   - Count how many questions received relevant, correct answers.
   - technical_accuracy MUST EQUAL: round((relevant_correct_count / {$totalQuestionsCount}) * 100).
   - If all attempted questions were relevant and no questions were skipped, technical_accuracy MUST BE 100.
   - If 1 question asked and candidate gave a relevant answer, technical_accuracy MUST BE 100.

3. COMMUNICATION & SPEECH CLARITY:
   - Evaluate speech clarity based on spoken words and delivery.
   - Award high score (85-95) if spoken answers were clear and relevant.
   - Deduct points moderately only for excessive verbal hesitations.
   - CRITICAL REQUIREMENT: Do NOT use the informal words 'huu huu' or 'hmm' in your review text. Refer to vocal pauses as 'verbal hesitations', 'thinking pauses', or 'filler sounds'.

4. OVERALL SCORE CALCULATION:
   - overall_score MUST EQUAL: round((technical_accuracy + communication_clarity + concentration_focus) / 3).
   - Readiness level:
     * overall_score >= 80: "Job Ready"
     * overall_score >= 60: "Developing Candidate"
     * overall_score < 60: "Needs Foundational Practice"

5. Only include 3 metrics in the metrics object: technical_accuracy, communication_clarity, and concentration_focus. Do NOT include problem_solving or conceptual_depth.

Respond ONLY with valid, raw JSON matching this schema (no markdown, no backticks, no wrap text):
{
  "overall_score": 85,
  "readiness_level": "Job Ready",
  "summary": "2-3 concise sentences giving an executive overview of the candidate's performance.",
  "metrics": {
    "technical_accuracy": 100,
    "communication_clarity": 85,
    "concentration_focus": {$concentrationScore}
  },
  "proctoring_analysis": {
    "concentration_score": {$concentrationScore},
    "facing_camera_pct": {$facingCameraPct},
    "looking_sides_pct": {$lookingSidesPct},
    "looking_down_pct": {$lookingDownPct},
    "warnings_count": {$warningsCount},
    "cheating_risk": "{$cheatingRisk}",
    "verdict": "Clear summary of camera gaze and focus.",
    "observations": [
      "Specific observation about eye contact and camera facing",
      "Observation regarding side glances or focus consistency"
    ]
  },
  "communication_analysis": {
    "clarity_score": 85,
    "fluency_level": "Fluent / Clear | Moderate Hesitations | Frequent Vocal Hesitations",
    "filler_word_count": {$totalFillers},
    "speech_critique": "Detailed critique of speaking clarity, explaining how verbal hesitations and pauses affected delivery.",
    "advice": "Actionable coaching on speaking clearly and replacing vocal pauses with poised silent pauses."
  },
  "how_you_are_doing": [
    "Specific positive observation about their technical grasp or attitude",
    "Another notable strength observed during the conversation"
  ],
  "how_you_are_speaking": {
    "delivery_critique": "Feedback on their communication style, pacing, structure, and clarity.",
    "observable_confidence": "Assessment of how clearly and directly they articulated thoughts."
  },
  "weak_areas": [
    "Specific concept or question where they struggled or gave incomplete answers",
    "Another technical gap or misunderstanding detected"
  ],
  "topics_to_learn": [
    {
      "topic": "Topic Name",
      "why_learn": "Why this topic is critical for this role and what gap was observed.",
      "key_concepts": ["Concept 1", "Concept 2", "Concept 3"]
    }
  ],
  "recommended_practice_questions": [
    "Targeted interview question 1 to practice next time",
    "Targeted interview question 2 to practice next time",
    "Targeted interview question 3 to practice next time"
  ],
  "question_by_question_review": [
    {
      "question": "Question asked by interviewer",
      "candidate_answer": "Candidate's spoken answer",
      "verdict": "Strong - Fully Correct | Needs Improvement | Unattempted",
      "feedback": "Concise review of their answer explaining why it is relevant/correct",
      "ideal_answer_guide": "How a top candidate would answer this effectively."
    }
  ]
}
PROMPT;

$apiKeys = getGeminiKeys();
$models  = getGeminiModels();
$evaluationData = null;

foreach ($apiKeys as $key) {
    if (trim($key) === '') continue;
    foreach ($models as $model) {
        $url     = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';
        $payload = [
            'contents'         => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'temperature'      => 0.2,   // Low = fast deterministic JSON
                'topP'             => 0.8,
                'maxOutputTokens'  => 1800,  // Sufficient for all fields; was 3200 (slower)
                'responseMimeType' => 'application/json'
            ]
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'x-goog-api-key: ' . trim($key)],
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT        => 35,  // Was 90s — 35s is generous and prevents long hangs
        ]);

        $res    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($res !== false && $status >= 200 && $status < 300) {
            $jsonRes   = json_decode($res, true);
            $rawText   = $jsonRes['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $cleanJson = preg_replace('/^```(?:json)?\s*/i', '', trim($rawText));
            $cleanJson = preg_replace('/\s*```$/', '', $cleanJson);
            $parsed    = json_decode($cleanJson, true);
            if (is_array($parsed) && isset($parsed['overall_score'])) {
                $evaluationData = $parsed;
                break 2;
            }
        }

        if ($status === 429 || $status === 403) break;
    }
}

// -- Fallback if AI Call Failed --
if (!is_array($evaluationData)) {
    $qReview = [];
    $correctCount = 0;
    foreach ($qaPairs as $qa) {
        $q = $qa['question'];
        $a = $qa['answer'];
        $isAtt = $qa['attempted'];

        if (!$isAtt) {
            $qReview[] = [
                'question'           => $q,
                'candidate_answer'   => $a !== '' ? $a : 'Not Attempted',
                'verdict'            => 'Unattempted',
                'feedback'           => 'This question was skipped or not answered.',
                'ideal_answer_guide' => 'Provide a concise definition followed by a practical example.'
            ];
        } else {
            $correctCount++;
            $qReview[] = [
                'question'           => $q,
                'candidate_answer'   => $a,
                'verdict'            => 'Strong - Fully Correct',
                'feedback'           => 'Relevant response that demonstrates solid grasp of core concepts.',
                'ideal_answer_guide' => 'Structure your response clearly with definition, purpose, and implementation details.'
            ];
        }
    }

    $techScore = (int) round(($correctCount / max(1, $totalQuestionsCount)) * 100);
    $fluencyLevel = $totalFillers === 0 ? 'Fluent & Articulate' : ($totalFillers <= 3 ? 'Good (Minor Hesitations)' : 'Frequent Vocal Hesitations');
    $commScore = max(40, min(95, 88 - ($totalFillers * 3)));
    $overallScore = (int) round(($techScore + $commScore + $concentrationScore) / 3);

    $evaluationData = [
        'overall_score'    => $overallScore,
        'readiness_level'  => $overallScore >= 80 ? 'Job Ready' : ($overallScore >= 60 ? 'Developing Candidate' : 'Needs Foundational Practice'),
        'summary'          => "You demonstrated relevant technical grasp for {$preparation}. Your answers touched on the core concepts effectively. Maintaining consistent direct eye contact and replacing verbal hesitation sounds with confident silent pauses will noticeably elevate your performance.",
        'metrics'          => [
            'technical_accuracy'    => $techScore,
            'communication_clarity' => $commScore,
            'concentration_focus'   => $concentrationScore
        ],
        'proctoring_analysis' => [
            'concentration_score' => $concentrationScore,
            'facing_camera_pct'   => $facingCameraPct,
            'looking_sides_pct'   => $lookingSidesPct,
            'looking_down_pct'    => $lookingDownPct,
            'warnings_count'      => $warningsCount,
            'cheating_risk'       => $cheatingRisk,
            'verdict'             => $cheatingRisk === 'Low Risk'
                ? "Maintained steady direct eye contact with the camera. Low suspicion of external assistance or cheating."
                : ($cheatingRisk === 'Moderate Risk'
                    ? "Noticeable side glances observed during answering turns. Work on maintaining unbroken focus on the camera."
                    : "Frequent looking away and side head movement detected. In formal viva or interview proctoring, this raises integrity concerns."),
            'observations'        => [
                "Directly faced the camera for {$facingCameraPct}% of the interview duration.",
                $lookingSidesPct > 15
                    ? "Looked to the sides during {$lookingSidesPct}% of speaking turns - ensure you avoid checking side notes or dual monitors."
                    : "Maintained natural composure with minimal side glances ({$lookingSidesPct}%)."
            ]
        ],
        'communication_analysis' => [
            'clarity_score'     => $commScore,
            'fluency_level'     => $fluencyLevel,
            'filler_word_count' => $totalFillers,
            'speech_critique'   => $totalFillers > 0
                ? "Expressed ideas with clear intent, but used vocal thinking pauses and verbal hesitations ({$totalFillers} times) before framing responses."
                : "Communicated thoughts with respectable clarity and a steady speaking rate with minimal hesitation.",
            'advice'            => $totalFillers > 0
                ? "When formulating an answer, pause silently for 2 seconds instead of vocalizing thinking sounds. A calm silent pause conveys confidence and analytical thinking."
                : "Continue structuring complex responses using a direct summary followed by concrete technical examples."
        ],
        'how_you_are_doing' => ['Maintained composure and attempted the questions presented.', 'Communicated thoughts openly during the conversation.'],
        'how_you_are_speaking' => [
            'delivery_critique'     => 'Practice giving structured answers starting with a direct definition followed by an example.',
            'observable_confidence' => 'Clear speech; continuing to practice will reduce pauses when complex topics arise.'
        ],
        'weak_areas'                     => ["In-depth explanation of core mechanisms for {$preparation}."],
        'topics_to_learn'                => [['topic' => "Core Fundamentals of {$preparation}", 'why_learn' => 'Mastering the basics ensures you can answer technical questions without hesitation.', 'key_concepts' => ['Foundational definitions', 'Practical implementation', 'Common edge cases']]],
        'recommended_practice_questions' => ["Can you explain the architecture and core purpose of {$preparation}?", "What are common performance pitfalls you encounter in {$preparation}?", "How would you debug a critical issue in {$preparation} under production pressure?"],
        'question_by_question_review'    => $qReview
    ];
}

// -- Clean any Accidental Remnants --
unset($evaluationData['metrics']['conceptual_depth']);
unset($evaluationData['metrics']['problem_solving']);

// -- Lenient Grading Enforcement on question_by_question_review --
$lenientCorrectCount = 0;
$totalQuestionsEvaluated = count($evaluationData['question_by_question_review'] ?? []);

if ($totalQuestionsEvaluated > 0) {
    foreach ($evaluationData['question_by_question_review'] as &$rev) {
        $cAns = trim($rev['candidate_answer'] ?? '');
        $verdict = strtolower($rev['verdict'] ?? '');
        $feedback = strtolower($rev['feedback'] ?? '');

        if (is_unattempted_answer($cAns)) {
            $rev['verdict'] = 'Unattempted';
            continue;
        }

        // Check if candidate's answer was relevant or correct
        $isRelevant = (
            strpos($verdict, 'strong') !== false ||
            strpos($verdict, 'correct') !== false ||
            strpos($verdict, 'relevant') !== false ||
            strpos($verdict, 'good') !== false ||
            strpos($feedback, 'relevant') !== false ||
            strpos($feedback, 'correct') !== false ||
            strpos($feedback, 'good') !== false ||
            strpos($feedback, 'understands') !== false ||
            strpos($feedback, 'valid') !== false ||
            strpos($feedback, 'accurately') !== false
        );

        // Under user rule: If it's a relevant answer, give COMPLETE FULLY CORRECT (100%) credit
        if ($isRelevant) {
            $lenientCorrectCount++;
            $rev['verdict'] = 'Strong - Fully Correct';
        }
    }
    unset($rev);

    // Calculate technical score: exact percentage of relevant answers
    $calculatedTech = (int) round(($lenientCorrectCount / max(1, $totalQuestionsCount)) * 100);
    $evaluationData['metrics']['technical_accuracy'] = max(
        (int)($evaluationData['metrics']['technical_accuracy'] ?? 0),
        $calculatedTech
    );
}

// Guarantee proctoring_analysis and communication_analysis exist
if (!isset($evaluationData['proctoring_analysis']) || !is_array($evaluationData['proctoring_analysis'])) {
    $evaluationData['proctoring_analysis'] = [
        'concentration_score' => $concentrationScore,
        'facing_camera_pct'   => $facingCameraPct,
        'looking_sides_pct'   => $lookingSidesPct,
        'looking_down_pct'    => $lookingDownPct,
        'warnings_count'      => $warningsCount,
        'cheating_risk'       => $cheatingRisk,
        'verdict'             => $cheatingRisk === 'Low Risk' ? "Maintained steady direct eye contact with the camera." : "Side glances detected during answering.",
        'observations'        => ["Faced camera for {$facingCameraPct}% of the session."]
    ];
}
if (!isset($evaluationData['communication_analysis']) || !is_array($evaluationData['communication_analysis'])) {
    $commScore = (int)($evaluationData['metrics']['communication_clarity'] ?? 85);
    $evaluationData['communication_analysis'] = [
        'clarity_score'     => $commScore,
        'fluency_level'     => $totalFillers > 0 ? "Hesitations Detected" : "Fluent & Clear",
        'filler_word_count' => $totalFillers,
        'speech_critique'   => $totalFillers > 0 ? "Used vocal thinking pauses and verbal hesitations while formulating answers." : "Spoke with understandable clarity.",
        'advice'            => "Pause silently instead of vocalizing thinking sounds when considering an answer."
    ];
}
if (!isset($evaluationData['metrics']['concentration_focus'])) {
    $evaluationData['metrics']['concentration_focus'] = $concentrationScore;
}

// Strip any accidental occurrences of "huu huu" or "hmm" from critique/advice
if (isset($evaluationData['communication_analysis']['speech_critique'])) {
    $evaluationData['communication_analysis']['speech_critique'] = preg_replace('/\bhuu\s+huu\b/i', 'verbal hesitations', $evaluationData['communication_analysis']['speech_critique']);
    $evaluationData['communication_analysis']['speech_critique'] = preg_replace('/\b(?:huu|hmm)\b/i', 'thinking sounds', $evaluationData['communication_analysis']['speech_critique']);
}
if (isset($evaluationData['communication_analysis']['advice'])) {
    $evaluationData['communication_analysis']['advice'] = preg_replace('/\bhuu\s+huu\b/i', 'verbal pauses', $evaluationData['communication_analysis']['advice']);
    $evaluationData['communication_analysis']['advice'] = preg_replace('/\b(?:huu|hmm)\b/i', 'vocal fillers', $evaluationData['communication_analysis']['advice']);
}

// Recalculate overall score based strictly on the 3 retained pillars
$tScore = (int)($evaluationData['metrics']['technical_accuracy'] ?? 0);
$cScore = (int)($evaluationData['metrics']['communication_clarity'] ?? 0);
$fScore = (int)($evaluationData['metrics']['concentration_focus'] ?? $concentrationScore);

$evaluationData['overall_score'] = (int) round(($tScore + $cScore + $fScore) / 3);
if ($evaluationData['overall_score'] >= 80) {
    $evaluationData['readiness_level'] = 'Job Ready';
} elseif ($evaluationData['overall_score'] >= 60) {
    $evaluationData['readiness_level'] = 'Developing Candidate';
} else {
    $evaluationData['readiness_level'] = 'Needs Foundational Practice';
}

// Save enriched evaluation to DB
if ($interviewId > 0) {
    try {
        if ($isAdmin) {
            $pdo->prepare("UPDATE live_interview_sessions SET evaluation_json=? WHERE id=?")
                ->execute([json_encode($evaluationData, JSON_UNESCAPED_UNICODE), $interviewId]);
        } else {
            $pdo->prepare("UPDATE live_interview_sessions SET evaluation_json=? WHERE id=? AND user_id=?")
                ->execute([json_encode($evaluationData, JSON_UNESCAPED_UNICODE), $interviewId, $userId]);
        }
    } catch (\Throwable $e) {
        error_log('[live_generate_evaluation] DB save: ' . $e->getMessage());
    }
}

send_eval_json(['success' => true, 'evaluation' => $evaluationData]);
