<?php
/**
 * Centralized AI Prompt Engine for Gemini.
 * 
 * Defines elite personas, difficulty-calibrated rubrics, and structured
 * prompts for question generation, interview evaluation, and career coaching.
 */

class GeminiPrompts
{
    /**
     * Primary system instruction that sets the AI's core persona and standards.
     *
     * @param string|null $context Additional contextual domain (e.g., 'technical', 'behavioral', 'hr')
     * @return string
     */
    public static function getSystemInstruction(?string $context = null): string
    {
        return <<<SYS
You are ApexPrep AI, an elite Senior Technical Hiring Manager and Executive Interview Coach with over 15 years of industry experience across Tier-1 tech companies and fast-paced engineering organizations.

Your core objectives:
1. Conduct realistic, rigorous, and industry-calibrated mock interviews.
2. Demand practical depth, architectural understanding, and trade-off awareness over rote textbook memorization.
3. Provide constructive, high-value feedback that shows candidates precisely how to level up to the top 1% standard.
4. Value structured communication (e.g., STAR framework for situational questions, first-principles reasoning for technical questions).
5. Always strictly adhere to requested output schemas (such as raw JSON without conversational filler or markdown wrapping).
SYS;
    }

    /**
     * Generate an intelligent prompt for creating interview questions.
     *
     * @param string $categoryName Topic or role (e.g., "Full Stack Web Development", "Python", "Data Science")
     * @param string $difficulty   "Beginner", "Intermediate", or "Advanced"
     * @param int    $count        Number of questions to produce
     * @return string
     */
    public static function buildQuestionPrompt(string $categoryName, string $difficulty, int $totalQuestions): string
    {
        $diffLower = strtolower($difficulty);
        $catLower  = strtolower($categoryName);

        $isCoding = str_contains($catLower, 'coding')
                 || str_contains($catLower, 'dsa')
                 || str_contains($catLower, 'algorithm')
                 || str_contains($catLower, 'leetcode')
                 || str_contains($catLower, 'data structure')
                 || str_contains($catLower, 'problem solving');

        // Dedicated, strict prompt for Coding & DSA (LeetCode / HackerRank style problems)
        if ($isCoding) {
            $codingLevelRubric = match ($diffLower) {
                'beginner', 'entry', 'junior' => <<<BLEVEL
Target Level: Beginner-Level Candidate.
Requirements:
- Test basic programming concepts.
- Use simple logic involving variables, conditions, loops, arrays, or strings.
- Do NOT require advanced algorithms or complex data structures.
- Provide a clear problem statement.
- Provide input format and output format.
- Provide 2 sample test cases (Example 1, Example 2) with explanation.
- Do NOT provide the solution or code.

Primary Curated Topic Bank for Beginner:
1. Reverse a string
2. Check whether a number or string is palindrome
3. Find the largest of three numbers
4. Check whether a number is prime
5. Find factorial of a number
6. Generate Fibonacci series (up to N terms)
7. Find sum of elements in an array
8. Count vowels in a string
9. Find the largest and smallest element in an array
10. Check whether a number is Armstrong
11. Remove duplicate elements from an array
12. Calculate the frequency of characters in a string
BLEVEL,
                'advanced', 'senior', 'expert' => <<<ALEVEL
Target Level: Advanced-Level Candidate.
Requirements:
- Test advanced problem-solving, algorithmic thinking, and optimal complexity.
- The problem may involve dynamic programming, graphs, trees, advanced data structures, recursion/backtracking, or optimization.
- The problem should require consideration of time and space complexity under strict constraints.
- Include meaningful constraints and avoid trivial solutions.
- Provide a clear problem statement.
- Provide input format and output format.
- Provide constraints (e.g. array size up to 10^5, target O(N) or O(N log N) runtime, O(1) or O(N) space).
- Provide 2 sample test cases (Example 1, Example 2) with explanation.
- Do NOT provide the solution or code.

Primary Curated Topic Bank for Advanced:
1. Longest substring without repeating characters
2. Longest increasing subsequence (LIS)
3. Merge overlapping intervals
4. Graph traversal (BFS/DFS on unweighted/weighted graphs)
5. Shortest path (Dijkstra's algorithm / Bellman-Ford)
6. Detect a cycle in a directed or undirected graph
7. Dynamic programming problems (0/1 Knapsack, Coin Change, Edit Distance)
8. Backtracking (N-Queens, Sudoku Solver, Word Search)
9. Least Recently Used (LRU) Cache design
10. Trie (Prefix Tree) implementation & search
11. Topological sorting (Course Schedule)
12. Advanced binary tree problems (Lowest Common Ancestor, Binary Tree Maximum Path Sum)
ALEVEL,
                default => <<<MLEVEL
Target Level: Mid-Level (Intermediate) Candidate.
Requirements:
- Test problem-solving and algorithmic thinking.
- The problem may involve arrays, strings, hashing, stacks, queues, searching, sorting, two pointers, sliding window, or basic recursion.
- The candidate should need to think about time and space complexity tradeoffs.
- Avoid both trivial basic syntax problems and overly esoteric competitive algorithms.
- Provide a clear problem statement.
- Provide input format and output format.
- Provide constraints (target time & space bounds).
- Provide 2 sample test cases (Example 1, Example 2) with explanation.
- Do NOT provide the solution or code.

Primary Curated Topic Bank for Mid-Level:
1. Two Sum (target sum with Hash Map)
2. Find the second largest element in an array
3. Remove duplicates from a sorted/unsorted array
4. Find the missing number in an array
5. Check whether two strings are anagrams
6. Find the first non-repeating character in a string
7. Merge two sorted arrays
8. Binary search in sorted or rotated array
9. Sort an array without using built-in sorting (QuickSort / MergeSort logic)
10. Implement or utilize a stack / queue (e.g. Valid Parentheses)
11. Find duplicate elements in an array
12. Maximum subarray sum (Kadane's algorithm)
13. Count frequency using a hash map
MLEVEL,
            };

            return <<<PROMPT
Role: Elite Technical Coding Interviewer & Competitive Programming Specialist.
Category: $categoryName
Difficulty Level: $difficulty
Task: Formulate exactly $totalQuestions distinct, hands-on, executable CODING CHALLENGES for an interactive code sandbox.

$codingLevelRubric

STRICT MANDATORY RULES FOR EACH CODING CHALLENGE:
1. Every question MUST test hands-on algorithmic coding where the candidate writes an executable function.
2. ABSOLUTELY NO CONVERSATIONAL, ESSAY, DISCUSSION, OR TRIVIA QUESTIONS (e.g. NEVER ask "Explain how...", "How would you design...", "Imagine you are writing a function... How should you handle...", "What are the trade-offs...").
3. DO NOT provide the solution or implementation code.
4. Each question string in the JSON array MUST be self-contained and formatted cleanly with:
   [Problem Name]: [Clear problem statement explaining the task].
   Input Format: [Exact parameters and types]
   Output Format: [Expected return value and format]
   Example 1: Input: [sample input] -> Output: [expected output] (Explanation: [concise reason])
   Example 2: Input: [sample input] -> Output: [expected output]
   Constraints: [Target Time Complexity like O(N) or O(N log N), Space Complexity, and bounds].

Output Format:
Return ONLY a valid JSON array of strings containing exactly $totalQuestions coding challenges.
Do NOT include markdown formatting fences (such as ```json), preambles, or commentary.

Example output format for each item in the array:
[
  "Reverse a String: Write a function reverseString(s) that reverses a given string in-place.\\nInput Format: A string s containing characters.\\nOutput Format: The reversed string.\\nExample 1: Input: s = 'hello' -> Output: 'olleh' (Explanation: The characters are mirrored)\\nExample 2: Input: s = 'PrepPro' -> Output: 'orPperP'\\nConstraints: Target O(N) time complexity and O(1) auxiliary space.",
  "Two Sum: Given an array of integers nums and an integer target, write a function twoSum(nums, target) that returns indices of the two numbers such that they add up to target.\\nInput Format: An array of integers nums and an integer target.\\nOutput Format: An array of two indices [i, j].\\nExample 1: Input: nums = [2,7,11,15], target = 9 -> Output: [0,1]\\nExample 2: Input: nums = [3,2,4], target = 6 -> Output: [1,2]\\nConstraints: Target O(N) time complexity using a Hash Map and O(N) space."
]
PROMPT;
        }

        // Standard Technical & Behavioral rubric for non-coding interviews
        $levelGuideline = match ($diffLower) {
            'beginner', 'entry', 'junior' => <<<GUIDE
- Target Audience: Junior / Entry-Level candidates.
- Focus: Core syntax, foundational concepts, memory/data structure basics, standard error handling, practical debugging, and how they approach learning.
- Avoid overly theoretical trivia; prefer practical comprehension (e.g., "Explain how X works and what happens when Y fails").
GUIDE,
            'advanced', 'senior', 'expert' => <<<GUIDE
- Target Audience: Senior / Lead / Staff candidates.
- Focus: High-scale system design, concurrency and race conditions, distributed architecture, database indexing/sharding/caching trade-offs, security vulnerabilities, resiliency, performance profiling, and engineering trade-offs under constraints.
- Emphasize "How would you design/optimize..." and "What are the trade-offs between A and B in production?"
GUIDE,
            default => <<<GUIDE
- Target Audience: Mid-Level candidates.
- Focus: Real-world engineering scenarios, API architecture, database query efficiency, state management, asynchronous workflows, edge cases, and maintainability.
- Include troubleshooting scenarios (e.g., diagnosing slow database queries, handling third-party API downtime).
GUIDE,
        };

        return <<<PROMPT
Role: Senior Technical Interviewer in "$categoryName".
Difficulty Level: $difficulty.
Task: Formulate exactly $totalQuestions distinct, high-impact interview questions.

Difficulty Rubric & Focus:
$levelGuideline

Question Diversity Mix:
1. 40% Practical Problem Solving & Scenario-Based: Real-world debugging or design challenges.
2. 40% Core Technical Depth & Architecture: Deep understanding of underlying mechanics, trade-offs, and best practices.
3. 20% Applied Decision Making & Trade-offs: Comparing technologies, handling edge cases, or performance considerations.

Quality Rules:
- DO NOT generate generic, trivial, or one-word answer questions.
- Every question must test critical thinking and real-world competence.
- Avoid repetitive questions or identical concepts.

Output Format:
Return ONLY a valid JSON array of strings containing exactly $totalQuestions questions.
Do NOT include markdown formatting fences (such as ```json), preambles, or commentary.

Example output:
[
  "First interview question...",
  "Second interview question..."
]
PROMPT;
    }

    /**
     * Generate an intelligent evaluation prompt for candidate answers.
     *
     * @param array  $qaPairs Array of ['question_text' => ..., 'answer_text' => ...]
     * @param string $categoryName Category/topic of the interview
     * @param string $difficulty Difficulty level of the interview
     * @return string
     */
    public static function buildEvaluationPrompt(array $qaPairs, string $categoryName = 'General', string $difficulty = 'Intermediate'): string
    {
        $total = count($qaPairs);
        $promptParts = '';

        foreach ($qaPairs as $i => $row) {
            $num = $i + 1;
            $q   = addslashes(trim((string)($row['question_text'] ?? '')));
            $a   = addslashes(trim((string)($row['answer_text'] ?? '')));
            if ($a === '') {
                $a = '[No answer provided / candidate left blank]';
            }
            $promptParts .= "\n--- Question {$num} ---\nQuestion: \"{$q}\"\nCandidate Answer: \"{$a}\"\n";
        }

        return <<<PROMPT
You are evaluating a candidate interview for "$categoryName" (Difficulty: $difficulty).
Analyze ALL {$total} candidate answers below with high standards, constructive rigor, and professional insight.

$promptParts

--- Scoring Rubric (0 to 10 scale) ---
- 0 to 2: Blank answer, "I don't know", gibberish, or fundamentally incorrect.
- 3 to 4: Weak. Drops buzzwords without understanding, misses core principles, or gives a superficial 1-liner.
- 5 to 6: Average / Passable. Understands the basic premise, but lacks depth, practical nuances, or edge-case consideration.
- 7 to 8: Strong. Clear technical accuracy, good explanation of mechanics, references best practices or real-world application.
- 9 to 10: Exceptional (Top 1%). Demonstrates deep first-principles mastery, proactively addresses trade-offs, performance, security/edge cases, and articulates with structured precision.

--- Required Output Format ---
Return ONLY a valid JSON array with exactly {$total} objects (one per question, strictly matching the question order).
Each object must have these exact keys:
{
  "score": <integer from 0 to 10 based on technical depth and accuracy>,
  "strengths": "<1-2 sentences highlighting specifically what the candidate got right, noting good terminology or reasoning>",
  "improvements": "<1-2 sentences on specific missing technical concepts, edge cases, trade-offs, or architectures they should have addressed>",
  "grammar_feedback": "<1 sentence on communication style, structure, conciseness, and professionalism>",
  "grammar_score": <integer from 0 to 10 measuring communication clarity and sentence structure>,
  "confidence_score": <integer from 0 to 10 measuring assertive, definitive language vs. hesitant/vague phrases>,
  "ai_summary": "<One crisp, punchy sentence delivering the executive hiring verdict for this question>",
  "improved_answer": "<The definitive gold-standard model answer (2-3 crisp sentences) demonstrating how a senior candidate answers this question concisely and authoritatively>"
}

Important Instructions:
- If the candidate wrote nothing or gave a non-answer, assign a score of 0 or 1, explain what was expected in "improvements", and write an exemplary "improved_answer".
- Do NOT wrap in markdown fences (like ```json). Return pure, valid JSON only.
PROMPT;
    }

    /**
     * Generate an AI career roadmap and personalized tips based on past interview performance.
     *
     * @param string $category
     * @param float  $avgScore
     * @param array  $weakAreas
     * @return string
     */
    public static function buildCareerTipPrompt(string $category, float $avgScore, array $weakAreas = []): string
    {
        $weakList = !empty($weakAreas) ? implode(', ', $weakAreas) : 'General technical depth';

        return <<<PROMPT
Candidate Profile:
- Track: $category
- Current Interview Average Score: $avgScore / 10
- Identified Growth Areas: $weakList

Provide a focused, high-impact career coaching plan for this candidate.
Return a valid JSON object with:
{
  "readiness_level": "<Junior / Mid-Level / Senior Ready>",
  "key_strengths": ["<strength 1>", "<strength 2>"],
  "critical_gap_areas": ["<gap 1>", "<gap 2>"],
  "recommended_action_plan": [
    "<Step 1: specific topic or project to master>",
    "<Step 2: architectural or hands-on practice>",
    "<Step 3: interview communication refinement>"
  ],
  "motivational_verdict": "<1-2 inspiring yet realistic sentences from a senior tech coach>"
}
Return ONLY valid JSON.
PROMPT;
    }

    /**
     * MODULE 13 — Code Sandbox AI Analysis Prompt
     *
     * Analyzes candidate code for time/space complexity, edge cases,
     * code cleanliness, and optimization suggestions.
     *
     * @param string $code
     * @param string $language
     * @param string $questionText
     * @return string
     */
    public static function buildCodeAnalysisPrompt(string $code, string $language, string $questionText): string
    {
        return <<<PROMPT
Role: Elite Staff Software Engineer & Technical Interview Reviewer.
Task: Perform an in-depth code review and complexity analysis on the candidate's solution.

Interview Question / Problem:
$questionText

Programming Language: $language
Candidate's Solution Code:
```$language
$code
```

Provide a strict, professional technical code analysis.
Return ONLY a valid JSON object matching this exact structure:
{
  "time_complexity": "O(...)",
  "time_explanation": "Brief explanation of time complexity",
  "space_complexity": "O(...)",
  "space_explanation": "Brief explanation of auxiliary space",
  "code_quality_score": 8, // Integer 1-10
  "clean_code_verdict": "Clear, concise assessment of naming, idiomatic patterns, readability",
  "edge_cases": [
    {
      "case": "Empty or single-element input",
      "status": "Handled" // or "Missed" / "Partial"
    },
    {
      "case": "Negative or boundary values",
      "status": "Handled"
    }
  ],
  "optimizations": [
    "Specific suggestion 1 for better performance or cleaner logic",
    "Specific suggestion 2"
  ],
  "optimal_snippet": "Optional cleaner or more optimal snippet in $language (only if substantial improvement exists)"
}
Return pure JSON only. Do not wrap in markdown or conversational text.
PROMPT;
    }
}
