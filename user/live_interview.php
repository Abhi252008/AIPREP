<?php
/**
 * Live AI Interview - Main Interview Room
 * Integrated into Projectail (Module Live).
 * URL: user/live_interview.php?preparation=Java+Technical+Interview
 *      (or linked from module4/inter4.php with a session)
 */
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

// Pre-fill preparation from URL or session category
$preparation = isset($_GET['preparation']) ? trim(htmlspecialchars_decode($_GET['preparation'])) : '';
$sessionId   = isset($_GET['session_id']) ? (int) $_GET['session_id'] : 0;

// If linked from Module 4, load category+role from interview session
if ($sessionId > 0 && $preparation === '') {
    try {
        $stmt = $pdo->prepare("SELECT s.role_target, c.name AS category_name, s.difficulty_level FROM interview_sessions s JOIN categories c ON c.id = s.category_id WHERE s.id = ? AND s.user_id = ?");
        $stmt->execute([$sessionId, $_SESSION['user_id']]);
        $sess = $stmt->fetch();
        if ($sess) {
            $preparation = trim(($sess['role_target'] ?? '') . ' ' . ($sess['category_name'] ?? '') . ' ' . ($sess['difficulty_level'] ?? ''));
        }
    } catch (\Throwable $e) { /* silently skip */ }
}
if ($preparation === '') $preparation = 'Technical Interview';

$pageTitle  = 'Live AI Interview';
$activePage = 'live-interview';

$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Live AI Interview - AI Interview Prep</title>
<meta name="description" content="Real-time AI-powered voice interview. Have a live conversation with an AI interviewer that adapts to your answers.">

<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<!-- Google MediaPipe Face Mesh (100% Client-Side WebAssembly Face Landmark & Head-Pose Detection) -->
<script src="https://cdn.jsdelivr.net/npm/@mediapipe/face_mesh/face_mesh.js" crossorigin="anonymous"></script>

<!-- Live AI Interview Dedicated Stylesheet -->
<link rel="stylesheet" href="<?= BASE_URL ?>/css/live_interview.css">
</head>
<body>

<!-- =========================================================
     SETUP SCREEN
========================================================= -->
<section id="setupScreen">
    <div class="setup-shell">
        <div class="setup-card-light">

            <a href="<?= BASE_URL ?>/user/dashboard.php" class="setup-back-light">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>

            <div class="setup-icon-badge">
                <i class="bi bi-stars"></i>
            </div>

            <h1>Live AI Interview</h1>
            <p class="subtitle">Have a real-time spoken conversation with an AI interviewer that adapts to your answers. Uses your camera and microphone.</p>

            <label class="setup-label" for="preparationInput">What are you preparing for?</label>
            <textarea
                id="preparationInput"
                class="setup-textarea"
                placeholder="E.g. Java developer interview, final year project viva, DBMS oral exam, HR interview..."
            ><?= e($preparation) ?></textarea>

            <div class="quick-options">
                <button type="button" class="quick-option active" onclick="selectPreparation('Technical Interview', this)">Technical Interview</button>
                <button type="button" class="quick-option" onclick="selectPreparation('HR Interview', this)">HR Interview</button>
                <button type="button" class="quick-option" onclick="selectPreparation('Viva / Oral Exam', this)">Viva / Oral Exam</button>
                <button type="button" class="quick-option" onclick="selectPreparation('Final Year Project Viva', this)">Project Viva</button>
                <button type="button" class="quick-option" onclick="selectPreparation('Java Developer Interview', this)">Java</button>
                <button type="button" class="quick-option" onclick="selectPreparation('Python Developer Interview', this)">Python</button>
                <button type="button" class="quick-option" onclick="selectPreparation('Coding &amp; DSA Interview', this)">Coding &amp; DSA</button>
            </div>

            <button class="start-main-btn" id="startBtn" onclick="beginInterview()">
                <i class="bi bi-mic-fill" style="margin-right:6px;"></i> Start Live Interview
            </button>

            <div class="setup-notice">
                <i class="bi bi-camera-video"></i> Requires camera &amp; microphone permission.
            </div>

        </div>
    </div>
</section>


<!-- =========================================================
     INTERVIEW SCREEN
========================================================= -->
<section id="interviewScreen">

    <!-- Top Bar matching exact screenshot -->
    <header class="topbar">
        <div class="topbar-left">
            <div class="brand">
                <div class="brand-icon"><i class="bi bi-plus-lg"></i></div>
                <div class="brand-name">Live AI Interview</div>
            </div>
            <div class="interview-info">
                Interview for: <strong id="interviewTopic"><?= e($preparation) ?></strong>
            </div>
        </div>
        <div class="topbar-right">
            <div class="live-status">
                <span class="live-dot"></span> Live
            </div>
            <div class="topbar-timer">
                <i class="bi bi-clock"></i> <span id="timer">10:00</span>
            </div>
        </div>
    </header>

    <!-- Main Live Stage -->
    <main class="ai-video">

        <!-- Center 3D Shaded Glowing Orb & Avatar Character -->
        <div class="avatar-orb-container">
            <div class="avatar-orb">
                <div class="avatar-character">
                    <div class="avatar-cap"></div>
                    <div class="avatar-face">
                        <div class="avatar-eyes">
                            <div class="avatar-eye left"></div>
                            <div class="avatar-eye right"></div>
                        </div>
                        <div class="avatar-mouth" id="avatarMouth"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Persistent Question Card -->
        <div id="questionCardLive" class="question-card-live">
            <div class="question-card-header">
                <div style="display:flex;align-items:center;gap:8px;">
                    <span id="questionBadge" class="question-badge"><i class="bi bi-patch-question-fill"></i> Question</span>
                    <div id="turnStatusBadge" class="turn-status-badge">
                        <span id="turnDot" class="turn-dot speaking"></span>
                        <span id="turnStatusText">Connecting with AI Interviewer...</span>
                    </div>
                </div>
                <button type="button" id="replayQuestionBtn" class="q-replay-btn" onclick="replayCurrentQuestion()" title="Replay question audio">
                    <i class="bi bi-volume-up-fill"></i> Replay
                </button>
            </div>
            <div id="currentQuestionDisplay" class="question-text-live">
                <!-- Question will appear word-by-word as AI speaks -->
            </div>
        </div>

        <!-- Live Floating Message -->
        <div id="liveMessage"></div>

        <!-- Bottom-Left AI Interviewer Label (from screenshot) -->
        <div class="ai-label-bottom-left">
            <div class="ai-label-icon-circle">
                <i class="bi bi-stars"></i>
            </div>
            <div>
                <div class="ai-label-title">AI Interviewer</div>
                <div class="ai-label-subtitle" id="aiRole">Your personal interviewer</div>
            </div>
        </div>

        <!-- Candidate Self View PIP Card (Top-Right, from screenshot) -->
        <div class="self-view-pip-wrapper">
            <div class="self-view-pip">
                <video id="candidateVideo" autoplay muted playsinline></video>
                <div id="cameraOffMsg" class="camera-off-msg">
                    Camera is off
                </div>
                <div class="self-pip-you-tag">You</div>
            </div>

            <!-- Client-Side Camera Behavior Monitor -->
            <div id="cameraBehaviorBadge" class="camera-behavior-badge" title="Real-time browser-side camera monitoring">
                <div class="behavior-row">
                    <span class="behavior-label">Camera</span>
                    <span id="behaviorPresence" class="behavior-tag ok">
                        <span class="status-dot"></span> <span id="presenceText">Face Detected</span>
                    </span>
                </div>
                <div class="behavior-row" style="margin-top: 5px;">
                    <span class="behavior-label">Status</span>
                    <span id="behaviorStatus" class="behavior-tag ok">Facing Camera</span>
                </div>
                <div id="behaviorWarning" class="behavior-warning" style="display:none;">
                    <i class="bi bi-exclamation-triangle-fill"></i> <span id="warningMessage">Please face the camera</span>
                </div>
            </div>
        </div>

        <!-- Candidate Spoken Answer & Action Dock -->
        <div id="answerDockLive" class="answer-dock-live">
            <div class="dock-header-row">
                <div class="speaking-state-pill" id="speakingStatePill">
                    <span id="pulseMicDot" class="pulse-mic-dot"></span>
                    <div class="vad-waveform" id="vadWaveform" title="Real-time voice activity meter">
                        <span class="vad-bar" id="vadBar1"></span>
                        <span class="vad-bar" id="vadBar2"></span>
                        <span class="vad-bar" id="vadBar3"></span>
                        <span class="vad-bar" id="vadBar4"></span>
                    </div>
                    <span id="speakingStateText">Waiting: Listening for your answer...</span>
                </div>
                <div id="silenceTimerPill" class="silence-pill" style="display:none;">
                    <i class="bi bi-hourglass-split" id="silenceIcon"></i>
                    <span id="silenceTimerText">Auto-submitting in 7s</span>
                </div>
            </div>

            <textarea
                id="candidateAnswerInput"
                class="candidate-answer-textarea"
                placeholder="Speak your answer freely - your spoken words will appear here in real-time. (You can also type or edit anytime)"
                rows="2"
            ></textarea>

            <div class="dock-actions-row">
                <div class="dock-hint-text">
                    <i class="bi bi-info-circle"></i> Take your time to think (pauses are fine). Press Enter to Submit.
                </div>
                <div class="dock-btn-group">
                    <button type="button" id="skipQuestionBtn" class="dock-btn dock-btn-skip" onclick="skipToNextQuestion()" title="Skip to next question without penalty">
                        <i class="bi bi-skip-forward-fill"></i> Next Question
                    </button>
                    <button type="button" id="submitAnswerBtn" class="dock-btn dock-btn-submit" onclick="submitSpokenAnswerNow()" title="Submit answer immediately (or press Enter)">
                        <i class="bi bi-send-fill"></i> Submit Answer
                    </button>
                </div>
            </div>
        </div>

    </main>

    <!-- Bottom Controls Bar (matching screenshot) -->
    <footer class="controls-area">
        <div class="controls-row">

            <div class="control-item">
                <button id="micButton" class="control-btn-circle" onclick="toggleMicrophone()" title="Toggle microphone">
                    <i class="bi bi-mic-fill"></i>
                </button>
                <span class="control-item-label">Mic</span>
            </div>

            <div class="control-item">
                <button id="cameraButton" class="control-btn-circle" onclick="toggleCamera()" title="Toggle camera">
                    <i class="bi bi-camera-video-fill"></i>
                </button>
                <span class="control-item-label">Camera</span>
            </div>

            <div class="control-item">
                <button id="audioButton" class="control-btn-circle" onclick="toggleAudio()" title="Toggle AI audio">
                    <i class="bi bi-volume-up-fill"></i>
                </button>
                <span class="control-item-label">Audio</span>
            </div>

            <div class="control-item">
                <button id="shareButton" class="control-btn-circle" onclick="toggleScreenShare()" title="Share screen">
                    <i class="bi bi-display"></i>
                </button>
                <span class="control-item-label">Share</span>
            </div>

            <div class="control-item">
                <button id="settingsButton" class="control-btn-circle" onclick="openSettingsModal()" title="Settings">
                    <i class="bi bi-gear-fill"></i>
                </button>
                <span class="control-item-label">Settings</span>
            </div>

            <button class="end-btn-pill" onclick="endInterview()" title="End Interview">
                <i class="bi bi-telephone-x-fill" style="color:#ef4444;margin-right:2px;"></i> End Interview
            </button>

        </div>
    </footer>

</section>


<!-- =========================================================
     END SCREEN
========================================================= -->
<div id="endScreen">
    <div class="end-card">
        <div class="end-icon"><i class="bi bi-check-lg"></i></div>
        <h2 id="endTitle">Interview Ended</h2>
        <p id="endMessage">Your interview session has finished. Your evaluation and performance report can be generated in the next stage.</p>
        <div class="end-actions">
            <button class="end-btn-primary" onclick="goToReport()">
                <i class="bi bi-bar-chart-fill"></i> Review & Develop Skills
            </button>
            <button class="end-btn-secondary" onclick="returnToSetup()">
                <i class="bi bi-arrow-repeat"></i> Practice Again
            </button>
            <a href="<?= BASE_URL ?>/user/dashboard.php" class="end-btn-secondary">
                Dashboard
            </a>
        </div>
    </div>
</div>

<!-- Hidden audio element for AI voice playback -->
<audio id="interviewerAudio" playsinline preload="auto" style="display:none;"></audio>

<script>
/* ============================================================
   LIVE AI INTERVIEW - PROJECTAIL INTEGRATED ENGINE
   APIs: api/live_interview_ai.php + api/live_elevenlabs_tts.php
============================================================ */

const CSRF_TOKEN = "<?= e($csrfToken) ?>";
const BASE_URL   = "<?= BASE_URL ?>";

let stream = null, microphoneEnabled = true, cameraEnabled = true, audioEnabled = true;
let interviewStarted = false, interviewEnding = false;
let preparation = "", interviewSeconds = 0, timerInterval = null;
let conversationHistory = [], recognition = null, recognitionRunning = false;
let isProcessingAnswer = false;
let isAvatarSpeaking = false;
let currentTurnState = "opening";
let currentAudio = null, currentInterviewId = 0, consecutiveStruggles = 0;
let interviewConcluded = false, concludeReason = "";
const MAX_INTERVIEW_DURATION_SECONDS = 600; // 10 minutes strict session duration
let pendingTimeLimitEnd = false;
let audioSessionCounter = 0;
let interviewEnded = false;

// Adaptive difficulty tracking
let lastAnswerQuality = "normal"; // "easy", "normal", "hard" — sent to Gemini for adaptive next Q

function stopAllAudio() {
    isAvatarSpeaking = false;
    showSpeaking(false);
    if (window._audioWatchdog) {
        clearTimeout(window._audioWatchdog);
        window._audioWatchdog = null;
    }
    if (window._ttsFetchController) {
        try { window._ttsFetchController.abort(); } catch(e) {}
        window._ttsFetchController = null;
    }
    if (currentAudio) {
        try {
            currentAudio.pause();
            currentAudio.currentTime = 0;
            currentAudio.src = "";
        } catch(e) {}
        currentAudio = null;
    }
    const audioEl = document.getElementById("interviewerAudio");
    if (audioEl) {
        try {
            audioEl.pause();
            audioEl.currentTime = 0;
            audioEl.src = "";
        } catch(e) {}
    }
    if ("speechSynthesis" in window) {
        try {
            window.speechSynthesis.cancel();
        } catch(e) {}
    }
}

// Question tracking & Speech accumulation
let currentQuestionText = "";
let questionCounter = 0;
let accumulatedAnswer = "";
let currentInterimText = "";

// Silence detection state
let silenceTimerInterval = null;
let silentAutoAdvanceCount = 0;
const MAX_SILENT_AUTO_ADVANCES = 2;
const SILENCE_AUTO_SUBMIT_SECONDS = 7; // 7 seconds of silence = auto-submit

const setupScreen      = document.getElementById("setupScreen");
const interviewScreen  = document.getElementById("interviewScreen");
const endScreen        = document.getElementById("endScreen");
const preparationInput = document.getElementById("preparationInput");
const candidateVideo   = document.getElementById("candidateVideo");
const liveMessage      = document.getElementById("liveMessage");
const interviewTopic   = document.getElementById("interviewTopic");
const timerElement     = document.getElementById("timer");

function selectPreparation(value, btnEl) {
    if (preparationInput) preparationInput.value = value;
    document.querySelectorAll('.quick-option').forEach(b => b.classList.remove('active'));
    if (btnEl) {
        btnEl.classList.add('active');
    }
}

async function beginInterview() {
    preparation = (preparationInput?.value || "").trim();
    if (!preparation) preparation = "General interview practice";

    // Unlock audio immediately in user-gesture context
    const audioEl = document.getElementById("interviewerAudio");
    if (audioEl) {
        audioEl.src = "data:audio/wav;base64,UklGRigAAABXQVZFZm10IBIAAAABAAEARKwAAIhYAQACABAAAABkYXRhAgAAAAEA";
        audioEl.play().then(() => { audioEl.pause(); audioEl.currentTime = 0; }).catch(() => {});
    }
    if ("speechSynthesis" in window) {
        try { window.speechSynthesis.resume(); window.speechSynthesis.getVoices(); } catch(e) {}
    }

    try {
        await startCamera();
    } catch (e) {
        console.error(e);
        showLiveMessage("Camera or microphone permission is required to start.");
        return;
    }

    setupScreen.style.display = "none";
    interviewScreen.style.display = "block";
    if (interviewTopic) interviewTopic.textContent = preparation;
    const aiRole = document.getElementById("aiRole");
    if (aiRole) aiRole.textContent = "Expert in " + (preparation.split(" ").slice(0,3).join(" ") || "your field");

    interviewStarted = true; interviewEnding = false;
    conversationHistory = []; isProcessingAnswer = false;
    currentInterviewId = 0; consecutiveStruggles = 0;
    interviewConcluded = false; concludeReason = "";
    questionCounter = 0; currentQuestionText = "";
    accumulatedAnswer = ""; currentInterimText = "";
    silentAutoAdvanceCount = 0;
    lastAnswerQuality = "normal";
    proctoringStats = { totalChecks: 0, facingCamera: 0, lookingLeft: 0, lookingRight: 0, lookingDown: 0, lookingUp: 0, faceMissing: 0, multiFace: 0, warningsCount: 0 };

    // Clear question box — it will fill word-by-word when AI speaks
    const qDisplay = document.getElementById("currentQuestionDisplay");
    if (qDisplay) qDisplay.textContent = "";
    const ansInput = document.getElementById("candidateAnswerInput");
    if (ansInput) ansInput.value = "";

    const prepLower = preparation.toLowerCase();
    const initialOpening = (prepLower.includes("hr") || prepLower.includes("behavioral"))
        ? "Hello and welcome! I am pleased to meet you today. To get us started, could you please introduce yourself and tell me what brings you to this interview?"
        : (prepLower.includes("viva") || prepLower.includes("oral"))
            ? "Hello and welcome to your viva session. To begin, could you please introduce yourself and provide a brief overview of your work on " + preparation + "?"
            : (prepLower.includes("project"))
                ? "Welcome to your project evaluation session. To get us started, please introduce yourself and walk me through the high-level architecture of " + preparation + "."
                : "Hello and welcome to your interview! I'll be your interviewer today. To get us started, could you please introduce yourself and give me an overview of your background and experience with " + preparation + "?";

    startTimer();
    startBehaviorDetection();
    updateTurnStatus("ai_speaking");
    showLiveMessage("AI interviewer is speaking...");
    if (audioCtx && audioCtx.state === 'suspended') {
        try { audioCtx.resume(); } catch(e) {}
    }

    // Track question internally but DO NOT display yet — speakAI will stream it word-by-word
    currentQuestionText = initialOpening;
    questionCounter = 1;
    conversationHistory.push({ role: "model", text: initialOpening });

    // Asynchronously log session creation in DB in background without blocking UI
    fetch(BASE_URL + "/api/live_interview_ai.php", {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-CSRF-Token": CSRF_TOKEN },
        cache: "no-store",
        body: JSON.stringify({
            mode: "opening",
            preparation: preparation,
            answer: "",
            history: [],
            interview_seconds: 0,
            consecutive_struggles: 0,
            interview_id: 0
        })
    }).then(r => r.json()).then(data => {
        if (data && data.interview_id) currentInterviewId = data.interview_id;
    }).catch(err => console.warn("Background opening session log:", err));

    // DO NOT start recognition yet — wait until AI finishes speaking
    // speakAI will call startRecognition() + startSilenceTimer() when done

    // Speak opening question — word-by-word display happens inside speakAI
    speakAI(initialOpening, false, "");
}

async function startCamera() {
    if (!navigator.mediaDevices?.getUserMedia) throw new Error("Camera/microphone not supported.");
    stream = await navigator.mediaDevices.getUserMedia({
        video: { width: { ideal: 1280 }, height: { ideal: 720 }, facingMode: "user" },
        audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true, channelCount: 1 }
    });
    if (candidateVideo) {
        candidateVideo.srcObject = stream;
        candidateVideo.muted = true;
        try { await candidateVideo.play(); } catch (e) {}
    }
    microphoneEnabled = true; cameraEnabled = true;
    updateMicUI(); updateCameraUI();
    initAudioVAD();
}

function escapeHtml(str) {
    if (!str) return "";
    return String(str)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function updateTurnStatus(status) {
    currentTurnState = status;
    const turnDot       = document.getElementById("turnDot");
    const turnStatusText= document.getElementById("turnStatusText");
    const pulseMicDot   = document.getElementById("pulseMicDot");
    const speakingText  = document.getElementById("speakingStateText");

    if (status === "ai_speaking") {
        if (turnDot) { turnDot.className = "turn-dot speaking"; }
        if (turnStatusText) turnStatusText.textContent = "AI Interviewer is speaking...";
        if (pulseMicDot) { pulseMicDot.className = "pulse-mic-dot"; }
        if (speakingText) speakingText.textContent = "AI Interviewer is speaking...";
        hideSilenceTimer();
    } else if (status === "opening") {
        if (turnDot) { turnDot.className = "turn-dot speaking"; }
        if (turnStatusText) turnStatusText.textContent = "Connecting with AI Interviewer...";
        if (pulseMicDot) { pulseMicDot.className = "pulse-mic-dot"; }
        if (speakingText) speakingText.textContent = "Preparing your opening question...";
        hideSilenceTimer();
    } else if (status === "waiting_answering") {
        if (turnDot) { turnDot.className = "turn-dot waiting"; }
        if (turnStatusText) turnStatusText.textContent = "Waiting: You are answering...";
        if (pulseMicDot) { pulseMicDot.className = "pulse-mic-dot answering"; }
        if (speakingText) speakingText.textContent = "Waiting: You are answering... (take your time)";
        showSilenceTimer();
    } else if (status === "waiting_thinking") {
        if (turnDot) { turnDot.className = "turn-dot waiting"; }
        if (turnStatusText) turnStatusText.textContent = "Waiting: Thinking... take your time";
        if (pulseMicDot) { pulseMicDot.className = "pulse-mic-dot thinking"; }
        if (speakingText) speakingText.textContent = "Waiting: Thinking... take your time";
        showSilenceTimer();
    } else if (status === "candidate_turn") {
        if (turnDot) { turnDot.className = "turn-dot waiting"; }
        if (turnStatusText) turnStatusText.textContent = "Your turn to answer";
        if (pulseMicDot) { pulseMicDot.className = "pulse-mic-dot answering"; }
        if (speakingText) speakingText.textContent = "Waiting: Listening for your answer...";
        showSilenceTimer();
    } else if (status === "evaluating") {
        if (turnDot) { turnDot.className = "turn-dot speaking"; }
        if (turnStatusText) turnStatusText.textContent = "AI is thinking & evaluating...";
        if (pulseMicDot) { pulseMicDot.className = "pulse-mic-dot"; }
        if (speakingText) speakingText.textContent = "Submitting answer to AI interviewer...";
        hideSilenceTimer();
    } else if (status === "skipping") {
        if (turnDot) { turnDot.className = "turn-dot speaking"; }
        if (turnStatusText) turnStatusText.textContent = "Moving to next question...";
        if (pulseMicDot) { pulseMicDot.className = "pulse-mic-dot"; }
        if (speakingText) speakingText.textContent = "Advancing to next question...";
        hideSilenceTimer();
    }
}

let typewriterInterval = null;

function displayCurrentQuestion(text) {
    if (!text) return;
    currentQuestionText = text;
    questionCounter++;
    const qBadge = document.getElementById("questionBadge");
    if (qBadge) qBadge.innerHTML = `<i class="bi bi-patch-question-fill"></i> Question ${questionCounter}`;
    // DO NOT set textContent here — speakAI will stream word-by-word via streamQuestionWordByWord
    // Clear the box so it appears empty until TTS begins streaming
    const qDisplay = document.getElementById("currentQuestionDisplay");
    if (qDisplay) qDisplay.textContent = "";
}

function streamQuestionWordByWord(text, totalDurationMs = 0) {
    if (typewriterInterval) {
        clearInterval(typewriterInterval);
        typewriterInterval = null;
    }
    const qDisplay = document.getElementById("currentQuestionDisplay");
    if (!qDisplay || !text) return;

    const words = text.trim().split(/\s+/);
    if (words.length === 0) return;

    qDisplay.innerHTML = "";

    // Pacing: synchronize word arrival with avatar speech duration
    // Clamped between 120ms and 360ms per word
    let wordIntervalMs = 210;
    if (totalDurationMs && totalDurationMs > 1000) {
        wordIntervalMs = Math.max(120, Math.min(360, Math.floor((totalDurationMs * 0.90) / words.length)));
    }

    let currentIndex = 0;
    qDisplay.innerHTML = `${escapeHtml(words[0])}<span class="streaming-cursor">|</span>`;
    currentIndex = 1;

    typewriterInterval = setInterval(() => {
        if (currentIndex >= words.length) {
            clearInterval(typewriterInterval);
            typewriterInterval = null;
            qDisplay.textContent = text;
            return;
        }

        currentIndex++;
        const currentSlice = words.slice(0, currentIndex).join(" ");
        qDisplay.innerHTML = `${escapeHtml(currentSlice)}<span class="streaming-cursor">|</span>`;
        qDisplay.scrollTop = qDisplay.scrollHeight;
    }, wordIntervalMs);
}

function revealFullQuestionImmediately(text) {
    if (typewriterInterval) {
        clearInterval(typewriterInterval);
        typewriterInterval = null;
    }
    const qDisplay = document.getElementById("currentQuestionDisplay");
    if (qDisplay && text) {
        qDisplay.textContent = text;
    }
}

function replayCurrentQuestion() {
    if (!currentQuestionText || isProcessingAnswer || isAvatarSpeaking || !interviewStarted) return;
    clearSilenceTimer();
    stopRecognition();
    showLiveMessage("Replaying question audio...");
    speakAI(currentQuestionText, false, "");
}

/* ============================================================
   REAL-TIME VOICE ACTIVITY (VAD) & SILENCE DETECTION ENGINE
============================================================ */
let audioCtx = null;
let micAnalyser = null;
let micSource = null;
let vadAnimationId = null;
let isCandidateSpeaking = false;
let lastVoiceActivityTimestamp = Date.now();

// VAD thresholds — tuned to reject fan/AC/keyboard noise
const VAD_THRESHOLD = 62;           // Level to show waveform animation (noise floor reject)
const SPEECH_RESET_THRESHOLD = 82;  // Level that RESETS the silence countdown (must be loud clear speech)
const VAD_SPEECH_FRAMES = 12;       // ~200ms of sustained audio above threshold = real speech
const VAD_SILENCE_FRAMES = 20;      // ~330ms continuous silence = candidate stopped speaking

let consecutiveSpeechFrames = 0;
let consecutiveSilenceFrames = 0;
let _vadPostSpeechLockoutUntil = 0; // Timestamp: ignore noise spikes briefly after speech ends

function initAudioVAD() {
    if (!stream) return;
    try {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (!AudioContextClass) return;
        if (!audioCtx) audioCtx = new AudioContextClass();
        if (audioCtx.state === 'suspended') audioCtx.resume();
        if (micSource) { try { micSource.disconnect(); } catch(e) {} }
        micSource = audioCtx.createMediaStreamSource(stream);
        micAnalyser = audioCtx.createAnalyser();
        micAnalyser.fftSize = 512;
        micAnalyser.smoothingTimeConstant = 0.7; // Higher smoothing = less noise spikes
        micSource.connect(micAnalyser);
        startVADLoop();
    } catch (e) {
        console.warn("VAD initialization notice:", e);
    }
}

function startVADLoop() {
    if (vadAnimationId) cancelAnimationFrame(vadAnimationId);
    if (!micAnalyser) return;
    const dataArray = new Uint8Array(micAnalyser.frequencyBinCount);

    function checkAudio() {
        if (!interviewStarted || interviewEnding) {
            vadAnimationId = null;
            updateMicVisualizer(0);
            return;
        }

        const isCandidateTurn = (currentTurnState === "candidate_turn" || currentTurnState === "waiting_answering" || currentTurnState === "waiting_thinking");

        // CRITICAL: Strictly mute candidate VAD while AI avatar is speaking or answer is being evaluated/processed
        if (!isCandidateTurn || isProcessingAnswer || isAvatarSpeaking || !microphoneEnabled || !micAnalyser) {
            updateMicVisualizer(0);
            vadAnimationId = requestAnimationFrame(checkAudio);
            return;
        }

        micAnalyser.getByteFrequencyData(dataArray);
        let sum = 0;
        // Human voice frequency bins (~300Hz - 3400Hz)
        const startBin = 4;
        const endBin = Math.min(dataArray.length, 100);
        for (let i = startBin; i < endBin; i++) {
            sum += dataArray[i];
        }
        const average = sum / (endBin - startBin);

        updateMicVisualizer(average);

        const now = Date.now();
        const inLockout = now < _vadPostSpeechLockoutUntil;

        if (average > VAD_THRESHOLD && !inLockout) {
            consecutiveSpeechFrames++;
            consecutiveSilenceFrames = 0;
            // Require VAD_SPEECH_FRAMES (~200ms) of sustained audio above threshold
            if (consecutiveSpeechFrames >= VAD_SPEECH_FRAMES) {
                onCandidateSpeakingDetected(average);
            }
        } else {
            consecutiveSilenceFrames++;
            if (consecutiveSpeechFrames > 0) {
                // User just stopped speaking — apply post-speech lockout to absorb echo/reverb
                _vadPostSpeechLockoutUntil = now + 1500;
            }
            consecutiveSpeechFrames = 0;
            if (consecutiveSilenceFrames >= VAD_SILENCE_FRAMES) {
                onCandidateSilenceDetected();
            }
        }

        vadAnimationId = requestAnimationFrame(checkAudio);
    }
    vadAnimationId = requestAnimationFrame(checkAudio);
}

function updateMicVisualizer(level) {
    const wave = document.getElementById("vadWaveform");
    const bar1 = document.getElementById("vadBar1");
    const bar2 = document.getElementById("vadBar2");
    const bar3 = document.getElementById("vadBar3");
    const bar4 = document.getElementById("vadBar4");
    if (!wave || !bar1) return;

    const isCandidateTurn = (currentTurnState === "candidate_turn" || currentTurnState === "waiting_answering" || currentTurnState === "waiting_thinking");
    if (level > VAD_THRESHOLD && microphoneEnabled && !isProcessingAnswer && !isAvatarSpeaking && isCandidateTurn && Date.now() >= _vadPostSpeechLockoutUntil) {
        wave.classList.add("speaking");
        const scale = Math.min(14, Math.max(5, (level / 255) * 45));
        bar1.style.height = Math.round(scale * 0.65) + "px";
        bar2.style.height = Math.round(scale * 1.0) + "px";
        bar3.style.height = Math.round(scale * 0.8) + "px";
        bar4.style.height = Math.round(scale * 0.5) + "px";
    } else {
        wave.classList.remove("speaking");
        bar1.style.height = "4px";
        bar2.style.height = "4px";
        bar3.style.height = "4px";
        bar4.style.height = "4px";
    }
}

function onCandidateSpeakingDetected(level) {
    const isCandidateTurn = (currentTurnState === "candidate_turn" || currentTurnState === "waiting_answering" || currentTurnState === "waiting_thinking");
    if (!interviewStarted || interviewEnding || isProcessingAnswer || isAvatarSpeaking || !microphoneEnabled || !isCandidateTurn) return;

    // CRITICAL: Only reset the silence countdown if audio level is HIGH enough to be real speech.
    // Low-level noise (fan, AC, keyboard) passes VAD_THRESHOLD but NOT SPEECH_RESET_THRESHOLD,
    // so it shows the waveform but does NOT delay auto-submit.
    if (level >= SPEECH_RESET_THRESHOLD) {
        lastVoiceActivityTimestamp = Date.now();
        silenceElapsedSeconds = 0;
    }

    isCandidateSpeaking = true;

    // Only update UI labels when audio is clearly loud speech (not just noise animation)
    if (level >= SPEECH_RESET_THRESHOLD) {
        const turnDot        = document.getElementById("turnDot");
        const turnStatusText = document.getElementById("turnStatusText");
        const pulseMicDot    = document.getElementById("pulseMicDot");
        const speakingText   = document.getElementById("speakingStateText");

        if (pulseMicDot) pulseMicDot.className = "pulse-mic-dot answering";
        if (speakingText) speakingText.textContent = "Speaking: Listening...";
        if (turnDot) turnDot.className = "turn-dot waiting";
        if (turnStatusText) turnStatusText.textContent = "You are speaking...";
    }
}

/* ============================================================
   SILENCE DETECTION SPECIFICATION ENGINE
   0-3 sec:  Quiet / user may be thinking
   3-5 sec:  Small indicator: Thinking...
   5-8 sec:  Visual prompt: "Take your time. You can continue when you're ready."
   8-10 sec: Auto-action countdown (submitting answer / moving to next)
   10+ sec:  Automatically move to next question (or submit answer)
============================================================ */
let silenceElapsedSeconds = 0;

function onCandidateSilenceDetected() {
    if (!interviewStarted || interviewEnding || isProcessingAnswer || isAvatarSpeaking || !microphoneEnabled) return;
    isCandidateSpeaking = false;
}

function startSilenceTimer() {
    clearSilenceTimer();
    lastVoiceActivityTimestamp = Date.now();
    silenceElapsedSeconds = 0;

    silenceTimerInterval = setInterval(() => {
        if (!interviewStarted || interviewEnding || isProcessingAnswer || isAvatarSpeaking) {
            clearSilenceTimer();
            return;
        }

        // Accurately compute seconds since human voice/word activity
        const elapsedSinceVoice = Math.floor((Date.now() - lastVoiceActivityTimestamp) / 1000);
        silenceElapsedSeconds = Math.max(0, elapsedSinceVoice);

        // If candidate paused for >= 2s, mark candidate as not currently speaking
        if (silenceElapsedSeconds >= 2) {
            isCandidateSpeaking = false;
        }

        updateSilenceLadderUI();

        // 7 seconds of silence: automatically submit or advance!
        if (silenceElapsedSeconds >= SILENCE_AUTO_SUBMIT_SECONDS) {
            clearSilenceTimer();
            onSilenceExpired();
        }
    }, 400);
}

function resetSilenceCountdown() {
    lastVoiceActivityTimestamp = Date.now();
    silenceElapsedSeconds = 0;
    const pill = document.getElementById("silenceTimerPill");
    if (pill) pill.style.display = "none";
}

function clearSilenceTimer() {
    if (silenceTimerInterval) {
        clearInterval(silenceTimerInterval);
        silenceTimerInterval = null;
    }
    silenceElapsedSeconds = 0;
    const pill = document.getElementById("silenceTimerPill");
    if (pill) pill.style.display = "none";
}

function showSilenceTimer() {
    // Called by updateTurnStatus — actual pill is controlled by updateSilenceLadderUI
    // Just ensure silence timer is running when it's candidate's turn
    if (!silenceTimerInterval && interviewStarted && !interviewEnding && !isProcessingAnswer && !isAvatarSpeaking) {
        // Don't auto-restart if already running; caller should call startSilenceTimer explicitly
    }
}

function hideSilenceTimer() {
    const pill = document.getElementById("silenceTimerPill");
    if (pill) pill.style.display = "none";
}

function updateSilenceLadderUI() {
    const pill = document.getElementById("silenceTimerPill");
    const txt  = document.getElementById("silenceTimerText");
    const icon = document.getElementById("silenceIcon");
    const speakingText = document.getElementById("speakingStateText");
    if (!pill || !txt) return;

    const input = document.getElementById("candidateAnswerInput");
    const hasText = !!(((input ? input.value : "") || accumulatedAnswer).trim());

    // 0-2 sec: User may be thinking — no indicator
    if (silenceElapsedSeconds < 2) {
        pill.style.display = "none";
        pill.className = "silence-pill";
        if (speakingText && !isCandidateSpeaking) {
            speakingText.textContent = hasText ? "Waiting: You are answering... (take your time)" : "Listening for your voice / answer...";
        }
    }
    // 2-4 sec: Thinking indicator
    else if (silenceElapsedSeconds >= 2 && silenceElapsedSeconds < 4) {
        pill.className = "silence-pill thinking";
        pill.style.display = "inline-flex";
        if (icon) icon.className = "bi bi-lightbulb-fill";
        txt.textContent = "Thinking...";
        if (speakingText && !isCandidateSpeaking) {
            speakingText.textContent = "Waiting: Thinking... take your time";
        }
    }
    // 4-6 sec: Gentle prompt
    else if (silenceElapsedSeconds >= 4 && silenceElapsedSeconds < 6) {
        pill.className = "silence-pill prompting";
        pill.style.display = "inline-flex";
        if (icon) icon.className = "bi bi-info-circle-fill";
        txt.textContent = hasText ? "Take your time or press Submit..." : "Speak your answer or skip...";
        if (speakingText && !isCandidateSpeaking) {
            speakingText.textContent = "Take your time. You can continue when you're ready.";
        }
    }
    // 6-7 sec: Countdown to auto-action
    else if (silenceElapsedSeconds >= 6 && silenceElapsedSeconds < SILENCE_AUTO_SUBMIT_SECONDS) {
        const remaining = Math.max(1, SILENCE_AUTO_SUBMIT_SECONDS - silenceElapsedSeconds);
        pill.className = "silence-pill no-response";
        pill.style.display = "inline-flex";
        if (icon) icon.className = "bi bi-hourglass-bottom";
        if (hasText) {
            txt.textContent = `Auto-submitting in ${remaining}s...`;
            if (speakingText && !isCandidateSpeaking) {
                speakingText.textContent = `Silence detected: Auto-submitting answer in ${remaining}s...`;
            }
        } else {
            txt.textContent = `Moving to next question in ${remaining}s...`;
            if (speakingText && !isCandidateSpeaking) {
                speakingText.textContent = `No response: Moving to next question in ${remaining}s...`;
            }
        }
    }
}

function onSilenceExpired() {
    clearSilenceTimer();
    if (!interviewStarted || interviewEnding || isProcessingAnswer || isAvatarSpeaking) return;
    const pill = document.getElementById("silenceTimerPill");
    if (pill) pill.style.display = "none";

    const input = document.getElementById("candidateAnswerInput");
    const currentAns = ((input ? input.value : "") || accumulatedAnswer).trim();

    if (currentAns.length > 0) {
        showLiveMessage(`${SILENCE_AUTO_SUBMIT_SECONDS}s silence: Auto-submitting your answer...`);
        // Assess answer quality for adaptive difficulty
        assessAnswerQuality(currentAns);
        submitSpokenAnswerNow();
    } else {
        showLiveMessage(`${SILENCE_AUTO_SUBMIT_SECONDS}s silence: Moving to next question...`);
        skipToNextQuestion(true);
    }
}

// Assess answer quality to drive adaptive easy/hard next question
function assessAnswerQuality(ans) {
    const words = ans.trim().split(/\s+/).filter(Boolean).length;
    const hasKeyTerms = /\b(because|since|therefore|however|implement|architecture|design|algorithm|complexity|optimize|trade.off|approach|solution|framework|database|api|system|concept|principle)\b/i.test(ans);
    const isFirstQuestion = (questionCounter <= 1);
    const lowPerfThreshold = isFirstQuestion ? 25 : 18;

    if (words <= lowPerfThreshold || /\b(don'?t know|no idea|not sure|idk|skip|pass|nothing|cant answer|cannot answer)\b/i.test(ans)) {
        lastAnswerQuality = "easy"; // Low performance detected → reduce difficulty for next question
    } else if (words >= (isFirstQuestion ? 50 : 40) && hasKeyTerms) {
        lastAnswerQuality = "hard"; // Strong performance
    } else {
        lastAnswerQuality = "normal";
    }
}

/* ============================================================
   ANSWER SUBMISSION & QUESTION NAVIGATION
============================================================ */
function submitSpokenAnswerNow() {
    if (!interviewStarted || interviewEnding || isProcessingAnswer) return;
    stopAllAudio();
    stopRecognition();
    silentAutoAdvanceCount = 0;
    clearSilenceTimer();
    showSpeaking(false);

    const input = document.getElementById("candidateAnswerInput");
    const answer = ((input ? input.value : "") || accumulatedAnswer).trim();

    if (!answer) {
        showLiveMessage("Please speak or type your answer, or click 'Next Question' to skip.");
        // Restart listening
        startRecognition();
        startSilenceTimer();
        return;
    }

    // Assess quality before clearing
    assessAnswerQuality(answer);

    if (input) input.value = "";
    accumulatedAnswer = "";
    currentInterimText = "";
    const submitBtn = document.getElementById("submitAnswerBtn");
    if (submitBtn) submitBtn.classList.remove("highlight");

    updateTurnStatus("evaluating");
    askGemini(answer, "conversation");
}

async function skipToNextQuestion(isAutoFromSilence = false) {
    if (!interviewStarted || interviewEnding || isProcessingAnswer) return;
    stopAllAudio();
    if (!isAutoFromSilence) {
        silentAutoAdvanceCount = 0;
    }
    clearSilenceTimer();
    showSpeaking(false);

    updateTurnStatus("skipping");
    showLiveMessage(isAutoFromSilence ? "Silence elapsed: Advancing to next question..." : "Moving to next question...");

    accumulatedAnswer = "";
    currentInterimText = "";
    const input = document.getElementById("candidateAnswerInput");
    if (input) input.value = "";
    const submitBtn = document.getElementById("submitAnswerBtn");
    if (submitBtn) submitBtn.classList.remove("highlight");

    await askGemini("I would like to move on to the next question.", "skip");
}

async function askGemini(candidateAnswer, mode = "conversation") {
    if (!interviewStarted || interviewEnding || isProcessingAnswer) return;
    isProcessingAnswer = true;
    stopAllAudio();
    stopRecognition();
    clearSilenceTimer();

    // Ensure mouth does not talk while evaluating and fetching next question from AI
    showSpeaking(false);
    updateTurnStatus(mode === "skip" ? "skipping" : "evaluating");
    showLiveMessage(
        candidateAnswer === "OPENING"
            ? "AI interviewer is preparing..."
            : (mode === "skip" ? "AI is preparing next question..." : "AI interviewer is evaluating your answer...")
    );

    // Show loading indicator in question box
    const qDisplay = document.getElementById("currentQuestionDisplay");
    if (qDisplay) {
        qDisplay.innerHTML = `<span style="opacity:0.7;font-style:italic;"><i class="bi bi-soundwave"></i> Formulating next question...</span>`;
    }

    try {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 8000); // 8s strict ceiling

        const response = await fetch(BASE_URL + "/api/live_interview_ai.php", {
            method: "POST",
            headers: { "Content-Type": "application/json", "X-CSRF-Token": CSRF_TOKEN },
            cache: "no-store",
            signal: controller.signal,
            body: JSON.stringify({
                mode: candidateAnswer === "OPENING" ? "opening" : (mode === "skip" ? "skip" : "conversation"),
                preparation: preparation,
                answer: candidateAnswer === "OPENING" ? "" : candidateAnswer,
                history: conversationHistory,
                interview_seconds: interviewSeconds,
                consecutive_struggles: consecutiveStruggles,
                interview_id: currentInterviewId,
                answer_quality: lastAnswerQuality, // "easy" | "normal" | "hard" for adaptive difficulty
                question_number: questionCounter
            })
        });
        clearTimeout(timeoutId);

        const data = await response.json();
        if (!response.ok || !data.success || !data.reply) throw new Error(data.message || "Gemini did not return a response.");

        if (data.interview_id)                                 currentInterviewId  = data.interview_id;
        if (typeof data.consecutive_struggles === "number")    consecutiveStruggles = data.consecutive_struggles;
        const timeExceeded = (interviewSeconds >= MAX_INTERVIEW_DURATION_SECONDS);
        interviewConcluded = !!data.interview_ended || (questionCounter >= 10) || timeExceeded;
        concludeReason     = data.conclude_reason || (timeExceeded ? "TIME_LIMIT_REACHED" : (questionCounter >= 10 ? "QUESTIONS_LIMIT_REACHED" : ""));

        const reply = String(data.reply).trim();
        if (candidateAnswer !== "OPENING") conversationHistory.push({ role: "user",  text: candidateAnswer });
        conversationHistory.push({ role: "model", text: reply });
        if (conversationHistory.length > 20) conversationHistory = conversationHistory.slice(-20);

        // displayCurrentQuestion sets currentQuestionText and clears the box
        // speakAI will stream it word-by-word as audio plays
        displayCurrentQuestion(reply);
        speakAI(reply, interviewConcluded, concludeReason);

    } catch (error) {
        console.error("Gemini interview error:", error);
        isProcessingAnswer = false;
        showSpeaking(false);
        const fallback = candidateAnswer === "OPENING"
            ? "Hello and welcome! I'll be your interviewer today. To get us started, please introduce yourself and share an overview of your background in " + (preparation || "this topic") + "."
            : (mode === "skip"
                ? "No problem at all, let's move forward. Could you share another key concept or project you have worked on in " + (preparation || "your field") + "?"
                : (lastAnswerQuality === "easy"
                    ? "That's okay, let's try a simpler question. Can you explain a basic concept you know well in " + (preparation || "your field") + "?"
                    : "Thank you for that. Could you describe a challenging scenario you solved recently in " + (preparation || "your field") + " and how you approached it?"));
        conversationHistory.push({ role: "model", text: fallback });
        displayCurrentQuestion(fallback);
        speakAI(fallback, false, "");
        return; // isProcessingAnswer already reset above
    }
    isProcessingAnswer = false; // Always unblock after success
}

// Press Enter to submit answer (Shift+Enter for new line)
window.addEventListener("keydown", (e) => {
    if (e.key === "Enter" && !e.shiftKey && interviewStarted && !interviewEnding && !isProcessingAnswer) {
        const input = document.getElementById("candidateAnswerInput");
        const ans = ((input ? input.value : "") || accumulatedAnswer).trim();
        if (ans) {
            e.preventDefault();
            submitSpokenAnswerNow();
        }
    }
});

function setupRecognition() {
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRecognition) {
        showLiveMessage("Speech recognition unavailable. Use Chrome for voice input.");
        return null;
    }
    const r = new SpeechRecognition();
    r.lang = (navigator.language && navigator.language.length >= 2) ? navigator.language : "en-US";
    r.continuous = true;
    r.interimResults = true;
    r.maxAlternatives = 1;

    r.onstart = () => {
        recognitionRunning = true;
        if (!isAvatarSpeaking && !isProcessingAnswer) {
            updateTurnStatus("candidate_turn");
        }
    };

    r.onspeechstart = () => {
        if (!isProcessingAnswer && !isAvatarSpeaking) {
            // Speech recognition confirmed speech — treat as high-confidence and reset silence timer
            lastVoiceActivityTimestamp = Date.now();
            silenceElapsedSeconds = 0;
            isCandidateSpeaking = true;
            const speakingText = document.getElementById("speakingStateText");
            if (speakingText) speakingText.textContent = "Speaking: Listening...";
        }
    };

    r.onspeechend = () => {
        if (!isProcessingAnswer && !isAvatarSpeaking) {
            onCandidateSilenceDetected();
            // Apply lockout so post-speech room echo doesn't re-trigger VAD
            _vadPostSpeechLockoutUntil = Date.now() + 1200;
        }
    };

    r.onresult = (event) => {
        if (isProcessingAnswer) return;

        // If candidate speaks while avatar audio is playing, gracefully yield to candidate
        if (isAvatarSpeaking) {
            if (currentAudio) { try { currentAudio.pause(); } catch(e) {} }
            try { window.speechSynthesis.cancel(); } catch(e) {}
            isAvatarSpeaking = false;
            showSpeaking(false);
            revealFullQuestionImmediately(currentQuestionText);
        }

        // ANY spoken words reset the silence countdown
        lastVoiceActivityTimestamp = Date.now();
        resetSilenceCountdown();

        let interimText = "";
        let newFinalChunk = "";

        for (let i = event.resultIndex; i < event.results.length; i++) {
            const text = event.results[i][0].transcript;
            if (event.results[i].isFinal) newFinalChunk += text + " ";
            else interimText += text;
        }

        if (newFinalChunk.trim()) {
            accumulatedAnswer = (accumulatedAnswer ? accumulatedAnswer.trim() + " " : "") + newFinalChunk.trim();
        }
        currentInterimText = interimText;

        const displayAnswer = (accumulatedAnswer + (interimText ? " " + interimText : "")).trim();
        const input = document.getElementById("candidateAnswerInput");
        if (input) {
            input.value = displayAnswer;
            input.scrollTop = input.scrollHeight;
        }

        // Highlight submit button when user has spoken
        const submitBtn = document.getElementById("submitAnswerBtn");
        if (submitBtn) {
            submitBtn.classList.toggle("highlight", displayAnswer.length > 0);
        }

        updateTurnStatus("waiting_answering");
    };

    r.onerror = (event) => {
        console.warn("Speech recognition error:", event.error);
        recognitionRunning = false;
        if (event.error === "not-allowed" || event.error === "service-not-allowed") {
            showLiveMessage("Microphone access is blocked in browser settings. Please allow microphone access.");
            return;
        }
        if (interviewStarted && !interviewEnding && !isProcessingAnswer) {
            setTimeout(startRecognition, 150);
        }
    };

    r.onend = () => {
        recognitionRunning = false;
        if (interviewStarted && !interviewEnding && microphoneEnabled && !isProcessingAnswer) {
            setTimeout(startRecognition, 100);
        }
    };
    return r;
}

function startRecognition() {
    if (!interviewStarted || interviewEnding || !microphoneEnabled || isProcessingAnswer) return;
    if (!recognition) recognition = setupRecognition();
    if (!recognition || recognitionRunning) return;
    try {
        recognition.start();
        recognitionRunning = true;
    } catch (e) {
        // Recognition already active
    }
}

function stopRecognition() {
    clearSilenceTimer();
    if (recognition && recognitionRunning) { try { recognition.stop(); } catch (e) {} }
    recognitionRunning = false; recognition = null;
}

async function speakAI(text, isEndingTurn = false, reason = "") {
    const sessionId = ++audioSessionCounter;
    stopAllAudio(); // Instantly stops ANY prior audio or browser speech

    // Clear question box — will stream word-by-word as audio plays
    const qDisplayNow = document.getElementById("currentQuestionDisplay");
    if (qDisplayNow) qDisplayNow.textContent = "";

    let speechCompleted = false;
    const completeSpeech = () => {
        if (sessionId !== audioSessionCounter || speechCompleted) return;
        speechCompleted = true;
        stopAllAudio();
        revealFullQuestionImmediately(text); // Show full text once speech ends

        if (isEndingTurn || pendingTimeLimitEnd) {
            showLiveMessage("Interview concluded.");
            setTimeout(() => endInterview(reason || "TIME_LIMIT_REACHED"), 1200);
        } else {
            showLiveMessage("Your turn to speak...");
            updateTurnStatus("candidate_turn");

            accumulatedAnswer = "";
            currentInterimText = "";
            const input = document.getElementById("candidateAnswerInput");
            if (input) input.value = "";
            const submitBtn = document.getElementById("submitAnswerBtn");
            if (submitBtn) submitBtn.classList.remove("highlight");

            if (interviewStarted && !interviewEnding && microphoneEnabled) {
                startRecognition();
                // Start silence timer AFTER a brief delay to avoid instantly triggering
                setTimeout(() => { if (interviewStarted && !interviewEnding) startSilenceTimer(); }, 500);
            }
        }
    };

    if (!audioEnabled || !text) {
        // Even with audio off, stream the text visually
        isAvatarSpeaking = true;
        showSpeaking(true);
        updateTurnStatus("ai_speaking");
        const wordCount = (text.match(/\S+/g) || []).length;
        const dummyDurMs = Math.max(2000, wordCount * 280);
        streamQuestionWordByWord(text, dummyDurMs);
        setTimeout(() => { completeSpeech(); }, dummyDurMs + 200);
        return;
    }

    // Calculate word-based safe duration to ensure full question is heard
    const wordCount = (text.match(/\S+/g) || []).length;
    // ~150 wpm average TTS = 400ms per word. Add 2s buffer for slow connections
    const safeMaxAudioMs = Math.max(4000, wordCount * 420 + 2000);

    // Try ElevenLabs TTS first
    let elevenLabsSuccess = false;
    try {
        window._ttsFetchController = new AbortController();
        // Allow up to 5s for TTS fetch (longer texts need more time to stream)
        const ttsRaceTimeout = setTimeout(() => {
            if (window._ttsFetchController) window._ttsFetchController.abort();
        }, 5000);

        const response = await fetch(BASE_URL + "/api/live_elevenlabs_tts.php", {
            method: "POST",
            headers: { "Content-Type": "application/json", "X-CSRF-Token": CSRF_TOKEN },
            cache: "no-store",
            signal: window._ttsFetchController.signal,
            body: JSON.stringify({ text: text, voice_id: "JBFqnCBsd6RMkjVDRZzb" })
        });
        clearTimeout(ttsRaceTimeout);

        if (sessionId !== audioSessionCounter) return;

        if (response.ok) {
            const audioBlob = await response.blob();
            if (sessionId !== audioSessionCounter) return;

            if (audioBlob && audioBlob.size > 800) {
                const audioEl = document.getElementById("interviewerAudio") || new Audio();
                currentAudio = audioEl;
                const audioUrl = URL.createObjectURL(audioBlob);
                audioEl.src = audioUrl;

                // Watchdog: fire AFTER we expect audio to end (actual duration + buffer)
                // This is a safety net only — onended fires first normally
                window._audioWatchdog = setTimeout(() => {
                    try { URL.revokeObjectURL(audioUrl); } catch(e) {}
                    if (sessionId === audioSessionCounter && !speechCompleted) completeSpeech();
                }, safeMaxAudioMs + 3000); // very generous watchdog — don't cut audio short

                audioEl.onloadedmetadata = () => {
                    if (sessionId !== audioSessionCounter) return;
                    // Once we know actual duration, update watchdog with precise timing
                    if (audioEl.duration && !isNaN(audioEl.duration) && audioEl.duration > 0) {
                        const preciseDurMs = Math.ceil(audioEl.duration * 1000);
                        if (window._audioWatchdog) clearTimeout(window._audioWatchdog);
                        window._audioWatchdog = setTimeout(() => {
                            try { URL.revokeObjectURL(audioUrl); } catch(e) {}
                            if (sessionId === audioSessionCounter && !speechCompleted) completeSpeech();
                        }, preciseDurMs + 1500);
                    }
                };

                audioEl.onplay = () => {
                    if (sessionId !== audioSessionCounter) {
                        try { audioEl.pause(); } catch(e) {}
                        return;
                    }
                    isAvatarSpeaking = true;
                    showSpeaking(true);
                    updateTurnStatus("ai_speaking");
                    showLiveMessage(isEndingTurn ? "AI interviewer is concluding..." : "AI interviewer is speaking...");

                    // Use actual audio duration if known, else estimate
                    const durMs = (audioEl.duration && !isNaN(audioEl.duration) && audioEl.duration > 0)
                        ? Math.ceil(audioEl.duration * 1000)
                        : safeMaxAudioMs;
                    streamQuestionWordByWord(text, durMs);
                };

                audioEl.onended = () => {
                    try { URL.revokeObjectURL(audioUrl); } catch(e) {}
                    completeSpeech();
                };

                audioEl.onerror = () => {
                    try { URL.revokeObjectURL(audioUrl); } catch(e) {}
                    if (sessionId === audioSessionCounter && !speechCompleted) {
                        playBrowserVoice(text, sessionId, completeSpeech, safeMaxAudioMs);
                    }
                };

                await audioEl.play();
                elevenLabsSuccess = true;
            }
        }
    } catch (err) {
        console.warn("ElevenLabs TTS error, falling back to browser speech:", err);
    }

    if (sessionId !== audioSessionCounter) return;

    // Fall back to browser SpeechSynthesis ONLY if ElevenLabs did not play
    if (!elevenLabsSuccess && !speechCompleted) {
        playBrowserVoice(text, sessionId, completeSpeech, safeMaxAudioMs);
    }
}

function playBrowserVoice(text, sessionId, onFinish, estimatedDurMs) {
    if (sessionId !== audioSessionCounter) return;
    if (!("speechSynthesis" in window)) {
        revealFullQuestionImmediately(text);
        if (typeof onFinish === "function") onFinish();
        return;
    }
    try {
        window.speechSynthesis.cancel();
        window.speechSynthesis.resume();

        const utterance = new SpeechSynthesisUtterance(text);
        window._activeUtterance = utterance;
        const voices = window.speechSynthesis.getVoices();
        if (voices && voices.length > 0) {
            const v = voices.find(v => v.lang && (v.lang.startsWith("en-IN") || v.lang.startsWith("en-GB") || v.lang.startsWith("en-US")) && (v.name.includes("Natural") || v.name.includes("Google")))
                   || voices.find(v => v.lang && v.lang.startsWith("en")) || voices[0];
            if (v) { utterance.voice = v; utterance.lang = v.lang; }
        }
        utterance.rate = 1.0;
        utterance.pitch = 1.0;

        let done = false;
        const wordCount = (text.match(/\S+/g) || []).length;
        // Use passed estimate or compute fresh; generous buffer so speech isn't cut
        const durMs = estimatedDurMs || Math.max(4000, wordCount * 420 + 1500);

        // Watchdog fires late — only if onend never fired
        window._audioWatchdog = setTimeout(() => {
            safeDone();
        }, durMs + 3000);

        const safeDone = () => {
            if (done || sessionId !== audioSessionCounter) return;
            done = true;
            if (window._audioWatchdog) { clearTimeout(window._audioWatchdog); window._audioWatchdog = null; }
            showSpeaking(false);
            window._activeUtterance = null;
            revealFullQuestionImmediately(text);
            if (typeof onFinish === "function") onFinish();
        };

        utterance.onstart = () => {
            if (sessionId !== audioSessionCounter) {
                try { window.speechSynthesis.cancel(); } catch(e) {}
                return;
            }
            isAvatarSpeaking = true;
            showSpeaking(true);
            updateTurnStatus("ai_speaking");
            showLiveMessage("AI interviewer is speaking...");
            streamQuestionWordByWord(text, durMs);
        };
        utterance.onend = safeDone;
        utterance.onerror = (e) => {
            console.warn("SpeechSynthesis error:", e);
            safeDone();
        };

        window.speechSynthesis.speak(utterance);
    } catch (e) {
        console.error("Browser TTS error:", e);
        showSpeaking(false);
        revealFullQuestionImmediately(text);
        if (typeof onFinish === "function") onFinish();
    }
}

function toggleMicrophone() {
    microphoneEnabled = !microphoneEnabled;
    if (stream) stream.getAudioTracks().forEach(t => t.enabled = microphoneEnabled);
    if (microphoneEnabled) {
        showLiveMessage("Microphone on.");
        startRecognition();
        if (audioCtx && audioCtx.state === 'suspended') {
            try { audioCtx.resume(); } catch(e) {}
        }
    } else {
        stopRecognition();
        clearSilenceTimer();
        updateMicVisualizer(0);
        showLiveMessage("Microphone off.");
    }
    updateMicUI();
}
function updateMicUI() {
    const btn = document.getElementById("micButton");
    if (btn) {
        btn.classList.toggle("active", microphoneEnabled);
        btn.classList.toggle("muted", !microphoneEnabled);
        btn.innerHTML = microphoneEnabled ? '<i class="bi bi-mic-fill"></i>' : '<i class="bi bi-mic-mute-fill"></i>';
    }
}

function toggleCamera() {
    cameraEnabled = !cameraEnabled;
    if (stream) stream.getVideoTracks().forEach(t => t.enabled = cameraEnabled);
    updateCameraUI();
    if (typeof handleCameraToggleBehavior === "function") {
        handleCameraToggleBehavior(cameraEnabled);
    }
}

function updateCameraUI() {
    const btn = document.getElementById("cameraButton");
    const video = document.getElementById("candidateVideo");
    const offMsg = document.getElementById("cameraOffMsg");

    if (btn) {
        btn.classList.toggle("active", cameraEnabled);
        btn.classList.toggle("muted", !cameraEnabled);
        btn.innerHTML = cameraEnabled ? '<i class="bi bi-camera-video-fill"></i>' : '<i class="bi bi-camera-video-off-fill"></i>';
    }
    if (video) {
        video.classList.toggle("hidden", !cameraEnabled);
    }
    if (offMsg) {
        offMsg.classList.toggle("show", !cameraEnabled);
    }
}

function toggleAudio() {
    audioEnabled = !audioEnabled;
    if (!audioEnabled) {
        if (currentAudio) { try { currentAudio.pause(); currentAudio.src = ""; } catch(e) {} currentAudio = null; }
        window.speechSynthesis.cancel(); showSpeaking(false);
        showLiveMessage("AI audio off."); startRecognition();
    } else showLiveMessage("AI audio on.");
    const btn = document.getElementById("audioButton");
    if (btn) {
        btn.classList.toggle("active", audioEnabled);
        btn.classList.toggle("muted", !audioEnabled);
        btn.innerHTML = audioEnabled ? '<i class="bi bi-volume-up-fill"></i>' : '<i class="bi bi-volume-mute-fill"></i>';
    }
}

let screenStream = null;
async function toggleScreenShare() {
    const btn = document.getElementById("shareButton");
    if (screenStream) {
        screenStream.getTracks().forEach(t => t.stop());
        screenStream = null;
        if (btn) btn.classList.remove("active");
        showLiveMessage("Screen sharing stopped.");
        return;
    }
    if (!navigator.mediaDevices?.getDisplayMedia) {
        showLiveMessage("Screen sharing not supported by your browser.");
        return;
    }
    try {
        screenStream = await navigator.mediaDevices.getDisplayMedia({ video: true });
        if (btn) btn.classList.add("active");
        showLiveMessage("Screen sharing started.");
        screenStream.getVideoTracks()[0].onended = () => {
            screenStream = null;
            if (btn) btn.classList.remove("active");
            showLiveMessage("Screen sharing ended.");
        };
    } catch (e) {
        console.warn("Screen share cancelled:", e);
    }
}

function openSettingsModal() {
    showLiveMessage("Devices: Camera & Microphone active and operational.");
}

function toggleMoreOptions() {
    showLiveMessage("Tips: Take your time to think (pauses are fine). Press Enter or click Submit.");
}

function startTimer() {
    if (timerInterval) clearInterval(timerInterval);
    interviewSeconds = 0;
    pendingTimeLimitEnd = false;
    interviewEnded = false;
    updateTimer();
    timerInterval = setInterval(() => {
        if (interviewStarted && !interviewEnding) {
            interviewSeconds++;
            updateTimer();

            // When exactly 10 minutes (600 seconds) is reached:
            if (interviewSeconds >= MAX_INTERVIEW_DURATION_SECONDS) {
                clearInterval(timerInterval);
                timerInterval = null;
                triggerTimeLimitConclusion();
            }
        }
    }, 1000);
}

function updateTimer() {
    if (!timerElement) return;
    const remaining = Math.max(0, MAX_INTERVIEW_DURATION_SECONDS - interviewSeconds);
    const m = Math.floor(remaining / 60), s = remaining % 60;
    timerElement.textContent = String(m).padStart(2, "0") + ":" + String(s).padStart(2, "0");
    if (remaining <= 60) {
        timerElement.style.color = "#f87171"; // warning indicator in the last 60 seconds
    } else {
        timerElement.style.color = "rgba(255,255,255,0.8)";
    }
}

async function triggerTimeLimitConclusion() {
    if (!interviewStarted || interviewEnding) return;
    interviewEnding = true;

    stopRecognition();
    clearSilenceTimer();
    updateMicVisualizer(0);
    showSpeaking(false);
    stopAllAudio();

    showLiveMessage("10-minute time limit reached! Concluding session...");
    updateTurnStatus("ai_speaking");

    const concludingRemark = "Thank you so much for your time today. We have reached our allotted ten-minute interview limit. You did a great job, and your evaluation report is now ready.";
    displayCurrentQuestion(concludingRemark);

    speakAI(concludingRemark, true, "TIME_LIMIT_REACHED");
    setTimeout(() => {
        endInterview("TIME_LIMIT_REACHED");
    }, 2800);
}

function showSpeaking(active) {
    const mouth = document.getElementById("avatarMouth");
    if (mouth) {
        mouth.classList.toggle("speaking", !!active);
    }
}

let liveMessageTimeout = null;
function showLiveMessage(msg) {
    if (!liveMessage) return;
    liveMessage.textContent = String(msg || "");
    liveMessage.classList.add("show");
    clearTimeout(liveMessageTimeout);
    liveMessageTimeout = setTimeout(() => { if (!interviewEnding) liveMessage.classList.remove("show"); }, 3500);
}
function showTranscript(text) {
    if (!liveTranscript || !transcriptText) return;
    transcriptText.textContent = text; liveTranscript.classList.add("visible");
}
function hideTranscript() {
    if (!liveTranscript || !transcriptText) return;
    liveTranscript.classList.remove("visible"); transcriptText.textContent = "Start speaking...";
}

let evalRedirectTimer = null;

function endInterview(reason = "") {
    if (interviewEnded) return;
    interviewEnded = true;
    interviewEnding = true;
    interviewStarted = false;
    if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
    }
    stopRecognition();
    clearSilenceTimer();
    updateMicVisualizer(0);
    stopBehaviorDetection();
    stopAllAudio();
    if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
    showSpeaking(false);
    if (liveMessage) liveMessage.classList.remove("show");

    // Automatically transition to evaluation report when 10 minutes or interview concludes
    if (reason === "TIME_LIMIT_REACHED" || reason === "QUESTIONS_LIMIT_REACHED") {
        if (interviewScreen) interviewScreen.style.display = "none";
        if (endScreen) {
            const titleEl = document.getElementById("endTitle");
            const msgEl   = document.getElementById("endMessage");
            if (titleEl) titleEl.textContent = "Interview Completed!";
            if (msgEl) {
                msgEl.innerHTML = (reason === "TIME_LIMIT_REACHED")
                    ? `Great job! You completed your 10-minute live interview session.<br><span style="color:#60a5fa;display:inline-block;margin-top:12px;font-weight:600;"><i class="bi bi-hourglass-split"></i> Finalizing your evaluation report and redirecting...</span>`
                    : `Great job! You completed all questions of your live interview session.<br><span style="color:#60a5fa;display:inline-block;margin-top:12px;font-weight:600;"><i class="bi bi-hourglass-split"></i> Finalizing your evaluation report and redirecting...</span>`;
            }
            endScreen.style.display = "flex";
        }
        showLiveMessage("Opening evaluation report...");
        clearTimeout(evalRedirectTimer);
        evalRedirectTimer = setTimeout(() => {
            goToReport();
        }, 1500);
        return;
    }

    if (interviewScreen) interviewScreen.style.display = "none";
    if (endScreen) {
        const titleEl = document.getElementById("endTitle");
        const msgEl   = document.getElementById("endMessage");
        if (titleEl && msgEl) {
            if (reason === "STRUGGLE_REPEAT" || reason === "CANDIDATE_REQUESTED_END") {
                titleEl.textContent = "Practice Session Concluded";
                msgEl.textContent   = "Don't be discouraged! Take time to study the fundamental concepts for " + (preparation || "this topic") + ". Come back and try again anytime!";
            } else {
                titleEl.textContent = "Interview Ended";
                msgEl.textContent   = "Your interview session has finished. Your performance report is ready.";
            }
        }
        endScreen.style.display = "flex";
    }
}

function goToReport() {
    clearTimeout(evalRedirectTimer);
    try {
        const total = Math.max(1, proctoringStats.totalChecks);
        const facingPct = Math.round((proctoringStats.facingCamera / total) * 100);
        const sidesPct  = Math.round(((proctoringStats.lookingLeft + proctoringStats.lookingRight) / total) * 100);
        const downPct   = Math.round((proctoringStats.lookingDown / total) * 100);
        const upPct     = Math.round((proctoringStats.lookingUp / total) * 100);
        const missingPct= Math.round((proctoringStats.faceMissing / total) * 100);
        const multiPct  = Math.round((proctoringStats.multiFace / total) * 100);

        let concentration = Math.max(20, Math.min(100, Math.round(facingPct - (proctoringStats.warningsCount * 3))));
        if (total < 10) concentration = 90; // Default if test was too brief

        let cheatingRisk = "Low Risk";
        if (concentration < 60 || proctoringStats.warningsCount >= 5 || sidesPct >= 35) {
            cheatingRisk = "High Risk";
        } else if (concentration < 80 || proctoringStats.warningsCount >= 2 || sidesPct >= 18) {
            cheatingRisk = "Moderate Risk";
        }

        const proctoringData = {
            total_checks: proctoringStats.totalChecks,
            facing_camera_pct: facingPct,
            looking_sides_pct: sidesPct,
            looking_down_pct: downPct,
            looking_up_pct: upPct,
            missing_face_pct: missingPct,
            multi_face_pct: multiPct,
            warnings_count: proctoringStats.warningsCount,
            concentration_score: concentration,
            cheating_risk: cheatingRisk
        };

        const candidateTurns = conversationHistory.filter(t => t.role === 'user' && t.text && t.text !== 'OPENING');
        const candidateText = candidateTurns.map(t => t.text).join(" ");
        const fillerMatches = (candidateText.match(/\b(huu\s+huu|huu|hu|hmm|hm|um|umm|uh|uhh|er|err)\b/gi) || []).length;
        const words = candidateText.trim().split(/\s+/).filter(Boolean);

        const speechData = {
            total_words: words.length,
            filler_count: fillerMatches
        };

        sessionStorage.setItem("last_interview_history",    JSON.stringify(conversationHistory));
        sessionStorage.setItem("last_interview_id",         String(currentInterviewId));
        sessionStorage.setItem("last_interview_prep",       preparation);
        sessionStorage.setItem("last_interview_proctoring", JSON.stringify(proctoringData));
        sessionStorage.setItem("last_interview_speech",     JSON.stringify(speechData));
    } catch(e) {
        console.error("Error saving evaluation metrics:", e);
    }
    window.location.href = BASE_URL + "/user/live_evaluation.php?id=" + currentInterviewId;
}

function returnToSetup() {
    clearTimeout(evalRedirectTimer);
    if (endScreen) endScreen.style.display = "none";
    if (setupScreen) setupScreen.style.display = "flex";
    interviewEnding = false; interviewStarted = false; interviewEnded = false;
    isProcessingAnswer = false; isAvatarSpeaking = false;
    preparation = ""; conversationHistory = []; currentInterviewId = 0;
    consecutiveStruggles = 0; interviewConcluded = false; concludeReason = "";
    questionCounter = 0; currentQuestionText = "";
    accumulatedAnswer = ""; currentInterimText = "";
    silentAutoAdvanceCount = 0;
    proctoringStats = { totalChecks: 0, facingCamera: 0, lookingLeft: 0, lookingRight: 0, lookingDown: 0, lookingUp: 0, faceMissing: 0, multiFace: 0, warningsCount: 0 };
    clearSilenceTimer();
    updateMicVisualizer(0);
    stopBehaviorDetection();
    resetBehaviorUI();
    stopAllAudio();
    if (timerElement) {
        timerElement.textContent = "10:00";
        timerElement.style.color = "rgba(255,255,255,0.8)";
    }
    const qDisplay = document.getElementById("currentQuestionDisplay");
    if (qDisplay) qDisplay.textContent = "";
    if (preparationInput) preparationInput.value = "";
    const ansInput = document.getElementById("candidateAnswerInput");
    if (ansInput) ansInput.value = "";
}

// Wire up typing and keyboard events on candidateAnswerInput
document.addEventListener("DOMContentLoaded", () => {
    const input = document.getElementById("candidateAnswerInput");
    if (input) {
        input.addEventListener("input", () => {
            resetSilenceCountdown();
            accumulatedAnswer = input.value;
            const submitBtn = document.getElementById("submitAnswerBtn");
            if (submitBtn) submitBtn.classList.toggle("highlight", input.value.trim().length > 0);
            updateTurnStatus("waiting_answering");
        });
        input.addEventListener("keydown", (e) => {
            if (e.key === "Enter" && !e.shiftKey) {
                e.preventDefault();
                submitSpokenAnswerNow();
            }
        });
    }
});

window.addEventListener("beforeunload", () => {
    try { stopRecognition(); } catch(e) {}
    try { clearSilenceTimer(); } catch(e) {}
    try { window.speechSynthesis.cancel(); } catch(e) {}
    try { stopBehaviorDetection(); } catch(e) {}
    if (stream) stream.getTracks().forEach(t => t.stop());
});

/* ============================================================
   REAL-TIME CAMERA BEHAVIOR DETECTION (100% Client-Side)
   Library: Google MediaPipe Face Mesh
   Privacy: 100% Browser-side processing via WebAssembly.
            ZERO frames uploaded, ZERO video stored,
            ZERO API calls, ZERO MySQL tables or DB writes.
============================================================ */

// Configurable thresholds (milliseconds)
const LOOK_AWAY_THRESHOLD   = 2000; // Continuous head turned > 2s triggers warning
const FACE_MISSING_THRESHOLD = 2000; // Continuous face missing > 2s triggers warning
const MULTI_FACE_THRESHOLD   = 1500; // Continuous multiple faces > 1.5s triggers warning
const DETECTION_INTERVAL_MS  = 130;  // ~7 to 8 FPS (low CPU, silky smooth responsiveness)

let faceMesh = null;
let faceMeshReady = false;
let detectionInterval = null;
let isProcessingFrame = false;

// Behavior state tracking
let lookingAwayStartTime = null;
let faceMissingStartTime = null;
let multiFaceStartTime   = null;
let activeWarningState   = null; // null | "LOOK_AWAY" | "FACE_MISSING" | "MULTI_FACE"
let lastRestoredNormal   = false;

// Proctoring & Concentration Metrics accumulator
let proctoringStats = {
    totalChecks: 0,
    facingCamera: 0,
    lookingLeft: 0,
    lookingRight: 0,
    lookingDown: 0,
    lookingUp: 0,
    faceMissing: 0,
    multiFace: 0,
    warningsCount: 0
};

const presenceEl   = document.getElementById("behaviorPresence");
const presenceText = document.getElementById("presenceText");
const statusEl     = document.getElementById("behaviorStatus");
const warningEl    = document.getElementById("behaviorWarning");
const warningMsgEl = document.getElementById("warningMessage");

/**
 * Clean Extensible Behavior Detection Event Hook
 * Emits real-time client events:
 * - "FACING_CAMERA"
 * - "LOOKING_LEFT"
 * - "LOOKING_RIGHT"
 * - "LOOKING_UP"
 * - "LOOKING_DOWN"
 * - "FACE_NOT_DETECTED"
 * - "MULTIPLE_FACES"
 * Currently updates live UI only - NOT connected to PHP or MySQL.
 */
function handleBehaviorDetection(eventType, details = {}) {
    // Extensible event listener system for future proctoring / callbacks
    if (window._cameraBehaviorListeners && Array.isArray(window._cameraBehaviorListeners)) {
        window._cameraBehaviorListeners.forEach(fn => {
            try { fn(eventType, details); } catch(e) { console.error(e); }
        });
    }
}

/**
 * Register external behavior listener without editing core logic
 */
function addCameraBehaviorListener(fn) {
    if (!window._cameraBehaviorListeners) window._cameraBehaviorListeners = [];
    window._cameraBehaviorListeners.push(fn);
}

function initBehaviorDetector() {
    if (typeof FaceMesh === "undefined") {
        console.warn("MediaPipe FaceMesh not loaded from CDN.");
        showBehaviorError("Camera detection unavailable. Check connection.");
        return;
    }
    if (faceMesh) return;

    try {
        faceMesh = new FaceMesh({
            locateFile: (file) => `https://cdn.jsdelivr.net/npm/@mediapipe/face_mesh/${file}`
        });
        faceMesh.setOptions({
            maxNumFaces: 2,
            refineLandmarks: false,
            minDetectionConfidence: 0.5,
            minTrackingConfidence: 0.5
        });
        faceMesh.onResults(onFaceMeshResults);
        faceMeshReady = true;
    } catch (err) {
        console.error("Failed to initialize FaceMesh:", err);
        showBehaviorError("Camera detection unavailable.");
    }
}

function startBehaviorDetection() {
    initBehaviorDetector();
    if (detectionInterval) clearInterval(detectionInterval);

    lookingAwayStartTime = null;
    faceMissingStartTime = null;
    multiFaceStartTime   = null;
    activeWarningState   = null;
    lastRestoredNormal   = false;

    detectionInterval = setInterval(async () => {
        if (!interviewStarted || interviewEnding || !cameraEnabled) return;
        if (!candidateVideo || candidateVideo.readyState < 2 || candidateVideo.paused) return;
        if (!faceMeshReady || !faceMesh || isProcessingFrame) return;

        isProcessingFrame = true;
        try {
            await faceMesh.send({ image: candidateVideo });
        } catch (e) {
            // Silently ignore momentary frame capture skips
        } finally {
            isProcessingFrame = false;
        }
    }, DETECTION_INTERVAL_MS);
}

function stopBehaviorDetection() {
    if (detectionInterval) {
        clearInterval(detectionInterval);
        detectionInterval = null;
    }
    isProcessingFrame = false;
    lookingAwayStartTime = null;
    faceMissingStartTime = null;
    multiFaceStartTime   = null;
    activeWarningState   = null;
}

function handleCameraToggleBehavior(enabled) {
    if (!enabled) {
        setBehaviorUI({
            presenceLabel: "Camera Paused",
            presenceClass: "subtle",
            dotClass: "warn",
            statusLabel: "Camera Off",
            statusClass: "subtle",
            warning: null
        });
    } else {
        resetBehaviorUI();
    }
}

/**
 * MediaPipe FaceMesh Landmark & Head Direction Processor
 */
function onFaceMeshResults(results) {
    if (!interviewStarted || interviewEnding || !cameraEnabled) return;

    proctoringStats.totalChecks++;
    const now = Date.now();
    const numFaces = results.multiFaceLandmarks ? results.multiFaceLandmarks.length : 0;

    // CASE 1: Face Not Detected
    if (numFaces === 0) {
        proctoringStats.faceMissing++;
        lookingAwayStartTime = null;
        multiFaceStartTime   = null;

        if (!faceMissingStartTime) {
            faceMissingStartTime = now;
        }
        const missingDuration = now - faceMissingStartTime;

        if (missingDuration >= FACE_MISSING_THRESHOLD) {
            if (activeWarningState !== "FACE_MISSING") {
                activeWarningState = "FACE_MISSING";
                lastRestoredNormal = false;
                proctoringStats.warningsCount++;
                handleBehaviorDetection("FACE_NOT_DETECTED", { elapsedMs: missingDuration });
            }
            setBehaviorUI({
                presenceLabel: "Face Not Detected",
                presenceClass: "danger",
                dotClass: "danger",
                statusLabel: "Face Not Detected",
                statusClass: "danger",
                warning: "Face not detected",
                warningClass: "danger"
            });
        } else {
            // Natural tolerance under 2 seconds: do not trigger warning banner
            setBehaviorUI({
                presenceLabel: "Face Detected",
                presenceClass: "subtle",
                dotClass: "",
                statusLabel: "Checking...",
                statusClass: "subtle",
                warning: null
            });
        }
        return;
    }

    // Reset missing face tracking once at least 1 face is seen
    faceMissingStartTime = null;

    // CASE 2: Multiple Faces Detected
    if (numFaces > 1) {
        proctoringStats.multiFace++;
        lookingAwayStartTime = null;
        if (!multiFaceStartTime) multiFaceStartTime = now;
        const multiDuration = now - multiFaceStartTime;

        if (multiDuration >= MULTI_FACE_THRESHOLD) {
            if (activeWarningState !== "MULTI_FACE") {
                activeWarningState = "MULTI_FACE";
                lastRestoredNormal = false;
                proctoringStats.warningsCount++;
                handleBehaviorDetection("MULTIPLE_FACES", { count: numFaces, elapsedMs: multiDuration });
            }
            setBehaviorUI({
                presenceLabel: "Multiple Faces (" + numFaces + ")",
                presenceClass: "danger",
                dotClass: "danger",
                statusLabel: "Multiple Faces",
                statusClass: "danger",
                warning: "Multiple faces detected",
                warningClass: "danger"
            });
        }
        return;
    }

    // CASE 3: Single Face Detected -> Compute 3D Head Orientation
    multiFaceStartTime = null;
    const lm = results.multiFaceLandmarks[0];

    // Key Landmark Indices:
    const nose     = lm[1];
    const eyeR     = lm[33];
    const eyeL     = lm[263];
    const forehead = lm[10];
    const chin     = lm[152];

    const spanX = Math.abs(eyeL.x - eyeR.x);
    const spanY = Math.abs(chin.y - forehead.y);

    let direction = "FACING_CAMERA";

    if (spanX > 0.02 && spanY > 0.04) {
        // Horizontal symmetry ratio (Yaw)
        const minX = Math.min(eyeR.x, eyeL.x);
        const maxX = Math.max(eyeR.x, eyeL.x);
        const yawRatio = (nose.x - minX) / (maxX - minX);

        // Vertical symmetry ratio (Pitch)
        const minY = Math.min(forehead.y, chin.y);
        const maxY = Math.max(forehead.y, chin.y);
        const pitchRatio = (nose.y - minY) / (maxY - minY);

        // Relative 3D depth difference across cheeks
        const deltaZ = (lm[454].z || 0) - (lm[234].z || 0);

        // Candidate video is mirrored (transform: scaleX(-1))
        if (yawRatio < 0.36 || deltaZ > 0.085) {
            direction = "LOOKING_LEFT";
        } else if (yawRatio > 0.64 || deltaZ < -0.085) {
            direction = "LOOKING_RIGHT";
        } else if (pitchRatio < 0.32) {
            direction = "LOOKING_UP";
        } else if (pitchRatio > 0.58) {
            direction = "LOOKING_DOWN";
        }
    }

    if (direction === "FACING_CAMERA") {
        proctoringStats.facingCamera++;
        lookingAwayStartTime = null;
        const wasWarning = (activeWarningState !== null);
        activeWarningState = null;

        if (wasWarning || !lastRestoredNormal) {
            lastRestoredNormal = true;
            handleBehaviorDetection("FACING_CAMERA", { restored: wasWarning });
            setBehaviorUI({
                presenceLabel: "Face Detected",
                presenceClass: "ok",
                dotClass: "",
                statusLabel: "Facing Camera",
                statusClass: "ok",
                warning: null
            });
        } else {
            setBehaviorUI({
                presenceLabel: "Face Detected",
                presenceClass: "ok",
                dotClass: "",
                statusLabel: "Facing Camera",
                statusClass: "ok",
                warning: null
            });
        }
    } else {
        // Looking Away (Left, Right, Up, or Down)
        if (direction === "LOOKING_LEFT")  proctoringStats.lookingLeft++;
        else if (direction === "LOOKING_RIGHT") proctoringStats.lookingRight++;
        else if (direction === "LOOKING_DOWN")  proctoringStats.lookingDown++;
        else if (direction === "LOOKING_UP")    proctoringStats.lookingUp++;

        lastRestoredNormal = false;
        if (!lookingAwayStartTime) {
            lookingAwayStartTime = now;
        }
        const awayDuration = now - lookingAwayStartTime;

        const dirHuman = direction === "LOOKING_LEFT"  ? "Looking Left" :
                         direction === "LOOKING_RIGHT" ? "Looking Right" :
                         direction === "LOOKING_UP"    ? "Looking Up" : "Looking Down";

        if (awayDuration >= LOOK_AWAY_THRESHOLD) {
            // Sustained look away (> 2 seconds): Trigger Warning!
            if (activeWarningState !== direction) {
                activeWarningState = direction;
                proctoringStats.warningsCount++;
                handleBehaviorDetection(direction, { elapsedMs: awayDuration });
            }
            setBehaviorUI({
                presenceLabel: "Face Detected",
                presenceClass: "ok",
                dotClass: "warn",
                statusLabel: dirHuman,
                statusClass: "warn",
                warning: "Please face the camera",
                warningClass: "warn"
            });
        } else {
            // Natural brief movement (< 2 seconds): Normal tolerance, no warning banner
            setBehaviorUI({
                presenceLabel: "Face Detected",
                presenceClass: "ok",
                dotClass: "",
                statusLabel: dirHuman,
                statusClass: "subtle",
                warning: null
            });
        }
    }
}

function setBehaviorUI({ presenceLabel, presenceClass, dotClass, statusLabel, statusClass, warning, warningClass }) {
    if (presenceText) presenceText.textContent = presenceLabel;
    if (presenceEl) {
        presenceEl.className = "behavior-tag " + (presenceClass || "ok");
        const dot = presenceEl.querySelector(".status-dot");
        if (dot) dot.className = "status-dot " + (dotClass || "");
    }
    if (statusEl) {
        statusEl.textContent = statusLabel;
        statusEl.className = "behavior-tag " + (statusClass || "ok");
    }
    if (warningEl && warningMsgEl) {
        if (warning) {
            warningMsgEl.textContent = warning;
            warningEl.className = "behavior-warning " + (warningClass || "warn");
            warningEl.style.display = "flex";
        } else {
            warningEl.style.display = "none";
        }
    }
}

function resetBehaviorUI() {
    setBehaviorUI({
        presenceLabel: "Face Detected",
        presenceClass: "ok",
        dotClass: "",
        statusLabel: "Facing Camera",
        statusClass: "ok",
        warning: null
    });
}

function showBehaviorError(msg) {
    setBehaviorUI({
        presenceLabel: "Camera Detection",
        presenceClass: "subtle",
        dotClass: "warn",
        statusLabel: "Unavailable",
        statusClass: "subtle",
        warning: msg,
        warningClass: "warn"
    });
}
</script>

</body>
</html>
