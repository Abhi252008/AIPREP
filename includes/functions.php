<?php
/**
 * Shared helper functions for Module 2 (Authentication) — and reused by
 * every later module. Include this after config/app_config.php and after
 * a session has been started.
 */

/**
 * Shorthand for htmlspecialchars() — use this around every piece of
 * user-supplied data that gets echoed into HTML, to prevent XSS.
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Returns the CSRF token for the current session, generating one the
 * first time it's called. Print this into a hidden field on every form:
 *   <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Checks a submitted csrf_token field against the one in the session.
 * hash_equals() is used instead of === to avoid timing-attack leaks.
 */
function verify_csrf(?string $submittedToken): bool
{
    return !empty($_SESSION['csrf_token'])
        && !empty($submittedToken)
        && hash_equals($_SESSION['csrf_token'], $submittedToken);
}

/**
 * Stores a one-time flash message in the session (survives exactly one
 * redirect). $type should be one of: success, error, info.
 */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Fetches and clears the pending flash message, if any. Called once by
 * includes/flash.php on every page render.
 */
function get_flash(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * Sends a Location header to a BASE_URL-relative path and stops execution.
 * Always call this BEFORE any HTML has been echoed / before header.php
 * is included, since headers can't be sent after output has started.
 */
function redirect(string $path): void
{
    header('Location: ' . BASE_URL . $path);
    exit;
}

/**
 * Simple password strength check: at least 8 characters, at least one
 * letter and one number. Good enough for a student placement-prep
 * project without being annoying to type on a phone.
 */
function is_strong_password(string $password): bool
{
    return strlen($password) >= 8
        && preg_match('/[A-Za-z]/', $password)
        && preg_match('/[0-9]/', $password);
}

/**
 * Returns 1-2 uppercase initials from a full name, for the fallback
 * avatar circle shown when a user hasn't uploaded a profile picture.
 */
function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $initials = strtoupper(substr($parts[0] ?? '', 0, 1) . substr($parts[count($parts) - 1] ?? '', 0, 1));
    return $initials !== '' ? $initials : '?';
}

/**
 * Renders either the user's uploaded profile picture or a fallback
 * initials circle. $profilePicture is the DB value (may be the default
 * placeholder path, which is never actually written to disk).
 */
function render_avatar(?string $profilePicture, string $name, string $size = '44px'): string
{
    $isRealUpload = $profilePicture
        && $profilePicture !== 'images/default-avatar.png'
        && file_exists(__DIR__ . '/../' . $profilePicture);

    if ($isRealUpload) {
        return '<img src="' . BASE_URL . '/' . e($profilePicture) . '" alt="Profile picture" '
            . 'style="width:' . $size . ';height:' . $size . ';border-radius:50%;object-fit:cover;">';
    }

    return '<div class="avatar-circle" style="width:' . $size . ';height:' . $size . ';font-size:calc(' . $size . ' * 0.4);">'
        . e(initials($name)) . '</div>';
}

/**
 * Generic, safe file-upload handler: checks the upload succeeded, the
 * MIME type is on the allow-list, and the size is under the limit, then
 * moves it into $destDir under uploads/ with a random, collision-proof
 * filename (never trusts the client's original filename).
 *
 * @param string $fieldName   Name of the <input type="file"> field.
 * @param array  $allowedMime Map of allowed MIME type => file extension, e.g. ['image/png' => 'png'].
 * @param int    $maxBytes    Maximum allowed file size in bytes.
 * @param string $destDir     Absolute path to the destination directory (must already exist).
 * @param string $publicPrefix Path prefix to store in the DB / use in URLs, e.g. 'uploads/profiles'.
 * @return array ['success' => bool, 'path' => string|null, 'error' => string|null]
 */
function handle_upload(string $fieldName, array $allowedMime, int $maxBytes, string $destDir, string $publicPrefix): array
{
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => false, 'path' => null, 'error' => null]; // nothing submitted — not an error
    }

    $file = $_FILES[$fieldName];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'path' => null, 'error' => 'Upload failed. Please try again.'];
    }

    if ($file['size'] > $maxBytes) {
        return ['success' => false, 'path' => null, 'error' => 'File is too large (max ' . round($maxBytes / 1048576, 1) . ' MB).'];
    }

    // Verify the ACTUAL file content type, never trust the client-sent one.
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowedMime[$mime])) {
        return ['success' => false, 'path' => null, 'error' => 'Unsupported file type.'];
    }

    $extension = $allowedMime[$mime];
    $filename = bin2hex(random_bytes(16)) . '.' . $extension;
    $destPath = rtrim($destDir, '/') . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return ['success' => false, 'path' => null, 'error' => 'Could not save the uploaded file.'];
    }

    return ['success' => true, 'path' => rtrim($publicPrefix, '/') . '/' . $filename, 'error' => null];
}

/**
 * MODULE 5 — AI Mock Interview Engine
 *
 * Generates all of a session's questions in one Gemini call and inserts
 * them into the `questions` table. Called once, the first time a user
 * opens user/interview.php for a session that has no questions yet.
 *
 * @return array ['success' => bool, 'error' => string|null]
 */
function generate_questions_for_session(PDO $pdo, int $sessionId, string $categoryName, string $difficulty, int $totalQuestions): array
{
    require_once __DIR__ . '/../config/gemini_config.php';

    // Use the dedicated GeminiPrompts engine for smart, calibrated questions
    $prompt    = GeminiPrompts::buildQuestionPrompt($categoryName, $difficulty, $totalQuestions);
    $result    = callGemini($prompt, 90, 2, GeminiPrompts::getSystemInstruction(), 0.7);
    $questions = null;

    if ($result['success']) {
        $questions = extractJsonFromGemini($result['text']);
    }

    // Resilient Fallback: If AI API experiences downtime or high demand, use curated questions
    if (!is_array($questions) || count($questions) === 0) {
        $questions = get_fallback_questions($categoryName, $difficulty, $totalQuestions);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO questions (session_id, question_text, question_order) VALUES (?, ?, ?)'
    );

    $order = 1;
    foreach (array_slice($questions, 0, $totalQuestions) as $q) {
        $stmt->execute([$sessionId, trim((string) $q), $order]);
        $order++;
    }

    return ['success' => true, 'error' => null];
}

/**
 * Curated fallback question banks to guarantee uninterrupted mock interviews
 * if external AI providers experience network or capacity outages.
 */
function get_fallback_questions(string $categoryName, string $difficulty, int $totalQuestions): array
{
    $cat = strtolower($categoryName);

    if (str_contains($cat, 'aptitude') || str_contains($cat, 'logic')) {
        $bank = [
            "A train 180 meters long is running at a speed of 72 km/h. How many seconds will it take to pass an electric pole?",
            "A sum of money doubles itself in 5 years at simple interest. In how many years will it become 4 times itself at the same rate?",
            "A shopkeeper marks an article 40% above the cost price and allows a discount of 20% on the marked price. What is the overall profit percentage?",
            "Pipe A can fill a tank in 12 hours, while Pipe B can empty it in 18 hours. If both pipes are opened simultaneously, how long will it take to fill the tank completely?",
            "In a group of 60 students, 40 like Mathematics, 30 like Science, and 15 like both. How many students like neither subject?",
            "A boat travels 24 km downstream in 2 hours and takes 4 hours to travel the same distance upstream. What is the speed of the water stream in km/h?",
            "Find the next number in the sequence: 4, 12, 36, 108, __?",
            "If 'A + B' means A is the brother of B, and 'A * B' means A is the father of B, how is P related to R in the expression: P * Q + R?",
            "The average score of 5 batsmen is 64 runs. If a 6th batsman joins and the new average becomes 60, how many runs did the 6th batsman score?",
            "A box contains 5 red, 4 blue, and 3 green marbles. If 2 marbles are drawn at random without replacement, what is the probability that both are blue?"
        ];
    } elseif (str_contains($cat, 'hr') || str_contains($cat, 'behavioral')) {
        $bank = [
            "Walk me through your background and the pivotal project that defined your career direction.",
            "Describe a high-stakes project where requirements changed dramatically midway through. How did you adapt your timeline and deliverables?",
            "Tell me about a time you strongly disagreed with a team lead or colleague on a technical decision. How did you handle the discussion and outcome?",
            "Give an example of a mistake or production bug you were responsible for. How did you resolve it and prevent it from recurring?",
            "How do you prioritize competing deadlines when multiple stakeholders classify their requests as top priority?",
            "Describe a situation where you had to quickly learn an unfamiliar technology or framework under tight delivery deadlines.",
            "Can you share an experience where you received critical or negative feedback? How did you respond and what changes did you make?",
            "Tell me about a time you mentored a junior colleague or helped an underperforming teammate improve.",
            "Where do you see yourself evolving professionally over the next 2 to 3 years, and how does this role align with that vision?",
            "Why are you looking to transition to this role now, and what unique value do you bring to our team culture?"
        ];
    } elseif (str_contains($cat, 'coding') || str_contains($cat, 'dsa') || str_contains($cat, 'algorithm')) {
        $bank = [
            "Two Sum: Given an array of integers nums and an integer target, return indices of the two numbers such that they add up to target.\nExample 1: Input: nums = [2,7,11,15], target = 9 -> Output: [0,1]\nExample 2: Input: nums = [3,2,4], target = 6 -> Output: [1,2]\nConstraints: Aim for O(N) time complexity using a Hash Map and O(N) space.",
            "Valid Parentheses: Given a string s containing just the characters '(', ')', '{', '}', '[' and ']', determine if the input string is valid. Open brackets must be closed by the same type of brackets in the correct order.\nExample 1: Input: s = '()[]{}' -> Output: true\nExample 2: Input: s = '(]' -> Output: false\nConstraints: Target O(N) time and O(N) space using a Stack.",
            "Maximum Subarray (Kadane's Algorithm): Given an integer array nums, find the contiguous subarray with the largest sum and return its sum.\nExample 1: Input: nums = [-2,1,-3,4,-1,2,1,-5,4] -> Output: 6 (from [4,-1,2,1])\nExample 2: Input: nums = [1] -> Output: 1\nConstraints: Algorithm must run in O(N) time with O(1) space.",
            "Valid Palindrome: A phrase is a palindrome if, after converting all uppercase letters into lowercase letters and removing all non-alphanumeric characters, it reads the same forward and backward. Write a function isPalindrome(s).\nExample 1: Input: s = 'A man, a plan, a canal: Panama' -> Output: true\nExample 2: Input: s = 'race a car' -> Output: false\nConstraints: Aim for O(N) time and O(1) extra space.",
            "Longest Substring Without Repeating Characters: Given a string s, find the length of the longest substring without repeating characters.\nExample 1: Input: s = 'abcabcbb' -> Output: 3 ('abc')\nExample 2: Input: s = 'bbbbb' -> Output: 1 ('b')\nConstraints: Optimize using the Sliding Window pattern in O(N) time.",
            "Reverse a Singly Linked List: Given the head of a singly linked list, reverse the list and return the reversed list's head.\nExample 1: Input: head = [1,2,3,4,5] -> Output: [5,4,3,2,1]\nExample 2: Input: head = [] -> Output: []\nConstraints: Implement in O(N) time with O(1) auxiliary space.",
            "Best Time to Buy and Sell Stock: You are given an array prices where prices[i] is the price of a given stock on the ith day. Return the maximum profit you can achieve by choosing a single day to buy and a single day in the future to sell.\nExample 1: Input: prices = [7,1,5,3,6,4] -> Output: 5 (Buy on day 2 at 1, sell on day 5 at 6)\nExample 2: Input: prices = [7,6,4,3,1] -> Output: 0\nConstraints: Single pass O(N) time and O(1) space.",
            "Merge Two Sorted Lists: You are given the heads of two sorted linked lists list1 and list2. Merge the two lists into one sorted list by splicing together the nodes of the first two lists.\nExample 1: Input: list1 = [1,2,4], list2 = [1,3,4] -> Output: [1,1,2,3,4,4]\nConstraints: O(N + M) time and O(1) extra space.",
            "Binary Search: Given an array of integers nums which is sorted in ascending order, and an integer target, write a function to search target in nums. If target exists, return its index. Otherwise, return -1.\nExample 1: Input: nums = [-1,0,3,5,9,12], target = 9 -> Output: 4\nExample 2: Input: nums = [-1,0,3,5,9,12], target = 2 -> Output: -1\nConstraints: Algorithm runtime must be O(log N).",
            "Merge Intervals: Given an array of intervals where intervals[i] = [start_i, end_i], merge all overlapping intervals.\nExample 1: Input: intervals = [[1,3],[2,6],[8,10],[15,18]] -> Output: [[1,6],[8,10],[15,18]]\nExample 2: Input: intervals = [[1,4],[4,5]] -> Output: [[1,5]]\nConstraints: Target O(N log N) time complexity due to sorting."
        ];
    } else {
        $bank = [
            "Explain the architectural differences between Monolithic and Microservices architectures, including data consistency trade-offs.",
            "How do relational databases (RDBMS) maintain ACID compliance, and how does indexing with B-Trees optimize query execution?",
            "Describe how modern session management and JWT authentication work, including strategies for handling token invalidation and refresh.",
            "What is the difference between synchronous and asynchronous processing? How would you use a message queue (like RabbitMQ or Redis) to decouple tasks?",
            "How do you identify and mitigate SQL injection, Cross-Site Scripting (XSS), and Cross-Site Request Forgery (CSRF) in web applications?",
            "Explain horizontal vs. vertical scaling. How does load balancing with health checks distribute traffic across application clusters?",
            "How does database caching with Redis work, and what cache eviction policies (e.g., LRU, LFU, TTL) would you apply for dynamic user data?",
            "Discuss the trade-offs between RESTful APIs and GraphQL. In what scenarios is GraphQL superior, and where does REST remain preferred?",
            "How would you troubleshoot a production incident where an API endpoint's response latency suddenly spikes from 150ms to 4 seconds?",
            "Explain the CAP theorem and provide an example of how modern distributed systems balance availability and consistency during network partitions."
        ];
    }

    return array_slice($bank, 0, $totalQuestions);
}
