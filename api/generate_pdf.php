<?php
/**
 * MODULE 7 — PDF Report Generator
 *
 * Generates a downloadable PDF interview report using FPDF.
 * Requires the user to be logged in and own the session.
 *
 * GET: /api/generate_pdf.php?session_id=X
 * Response: PDF file download
 */

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../libs/fpdf.php';

$sessionId = isset($_GET['session_id']) ? (int) $_GET['session_id'] : 0;
$userId    = (int) $_SESSION['user_id'];

if ($sessionId <= 0) {
    http_response_code(400);
    die('Invalid session ID.');
}

// -- Load session --
$stmt = $pdo->prepare(
    "SELECT s.*, c.name AS category_name, u.name AS user_name, u.email AS user_email
     FROM interview_sessions s
     JOIN categories c ON c.id = s.category_id
     JOIN users u ON u.id = s.user_id
     WHERE s.id = ? AND s.user_id = ?"
);
$stmt->execute([$sessionId, $userId]);
$session = $stmt->fetch();

if (!$session) {
    http_response_code(403);
    die('Session not found or access denied.');
}

// -- Load all Q+A with evaluation --
$qaStmt = $pdo->prepare(
    "SELECT q.question_text, q.question_order,
            a.answer_text, a.score, a.strengths, a.improvements,
            a.grammar_feedback, a.confidence_score, a.ai_summary,
            a.improved_answer
     FROM questions q
     LEFT JOIN answers a ON a.question_id = q.id
     WHERE q.session_id = ?
     ORDER BY q.question_order"
);
$qaStmt->execute([$sessionId]);
$results = $qaStmt->fetchAll();

// -- Aggregate stats --
$totalScore      = 0;
$scoredCount     = 0;
$totalWords      = 0;
$wordAnswers     = 0;
$allStrengths    = [];
$allImprovements = [];

foreach ($results as $r) {
    if ($r['score'] !== null) {
        $totalScore  += (float) $r['score'];
        $scoredCount++;
    }
    $text = trim((string)($r['answer_text'] ?? ''));
    if ($text !== '') {
        $totalWords += str_word_count(strip_tags($text));
        $wordAnswers++;
    }
    if (!empty($r['strengths']))    $allStrengths[]    = trim($r['strengths']);
    if (!empty($r['improvements'])) $allImprovements[] = trim($r['improvements']);
}

$avgScore      = $scoredCount > 0 ? round($totalScore / $scoredCount, 1) : 0;
$avgConfidence = $wordAnswers > 0 ? min(10, round(($totalWords / $wordAnswers) / 8, 1)) : 0;
$answeredCount = count(array_filter($results, fn($r) => !empty(trim((string)($r['answer_text'] ?? '')))));
$questionCount = count($results);

if ($avgScore >= 7.5)      { $perfLabel = 'Good';              $perfColor = [34, 212, 123]; }
elseif ($avgScore >= 5.0)  { $perfLabel = 'Average';           $perfColor = [245, 200, 66]; }
else                       { $perfLabel = 'Needs Improvement'; $perfColor = [255, 95, 109]; }

$dateLabel = date('d F Y', strtotime($session['completed_at'] ?? $session['started_at']));

// -- FPDF Custom Class --
class InterviewReportPDF extends FPDF
{
    public string $reportTitle = 'AI Interview Report';
    public string $userName    = '';
    public string $dateLabel   = '';

    // Color palette
    private array $clrBg     = [8, 9, 15];
    private array $clrPrimary= [122, 72, 255];
    private array $clrCyan   = [62, 207, 255];
    private array $clrText   = [220, 220, 232];
    private array $clrMist   = [140, 140, 160];
    private array $clrCard   = [20, 22, 35];
    private array $clrBorder = [40, 42, 60];
    private array $clrGood   = [34, 212, 123];
    private array $clrAvg    = [245, 200, 66];
    private array $clrBad    = [255, 95, 109];

    public function Header()
    {
        $this->SetFillColor(...$this->clrBg);
        $this->Rect(0, 0, 210, 28, 'F');
        // Gradient stripe
        $this->SetFillColor(...$this->clrPrimary);
        $this->Rect(0, 0, 105, 3, 'F');
        $this->SetFillColor(...$this->clrCyan);
        $this->Rect(105, 0, 105, 3, 'F');
        // Brand
        $this->SetFont('Helvetica', 'B', 13);
        $this->SetTextColor(...$this->clrText);
        $this->SetXY(14, 8);
        $this->Cell(80, 7, 'AI Interview Prep', 0, 0, 'L');
        // Right metadata
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(...$this->clrMist);
        $this->SetXY(110, 8);
        $this->Cell(86, 4, $this->reportTitle, 0, 0, 'R');
        $this->SetXY(110, 13);
        $this->Cell(86, 4, $this->dateLabel, 0, 0, 'R');
        $this->Ln(16);
    }

    public function Footer()
    {
        $this->SetY(-14);
        $this->SetFillColor(...$this->clrBg);
        $this->Rect(0, $this->GetY() - 2, 210, 20, 'F');
        $this->SetFillColor(...$this->clrPrimary);
        $this->Rect(0, 295, 105, 2, 'F');
        $this->SetFillColor(...$this->clrCyan);
        $this->Rect(105, 295, 105, 2, 'F');
        $this->SetFont('Helvetica', 'I', 7);
        $this->SetTextColor(...$this->clrMist);
        $this->Cell(0, 10, 'AI Interview Prep Platform  |  Page ' . $this->PageNo() . '  |  Confidential Report', 0, 0, 'C');
    }

    public function PageBackground()
    {
        $this->SetFillColor(...$this->clrBg);
        $this->Rect(0, 0, 210, 297, 'F');
    }

    public function RoundedRect($x, $y, $w, $h, $r, $style = '')
    {
        $k  = $this->k;
        $hp = $this->h;
        $op = ($style === 'F') ? 'f' : (($style === 'FD' || $style === 'DF') ? 'B' : 'S');
        $arc = 4 / 3 * (M_SQRT2 - 1);
        $this->_out(sprintf('%.2F %.2F m', ($x + $r) * $k, ($hp - $y) * $k));
        $xc = $x + $w - $r; $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - $y) * $k));
        $this->_ArcR($xc + $r * $arc, $yc - $r, $xc + $r, $yc - $r * $arc, $xc + $r, $yc);
        $xc = $x + $w - $r; $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - $yc) * $k));
        $this->_ArcR($xc + $r, $yc + $r * $arc, $xc + $r * $arc, $yc + $r, $xc, $yc + $r);
        $xc = $x + $r; $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - ($y + $h)) * $k));
        $this->_ArcR($xc - $r * $arc, $yc + $r, $xc - $r, $yc + $r * $arc, $xc - $r, $yc);
        $xc = $x + $r; $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $x * $k, ($hp - $yc) * $k));
        $this->_ArcR($xc - $r, $yc - $r * $arc, $xc - $r * $arc, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }

    private function _ArcR($x1, $y1, $x2, $y2, $x3, $y3)
    {
        $h = $this->h;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
            $x1 * $this->k, ($h - $y1) * $this->k,
            $x2 * $this->k, ($h - $y2) * $this->k,
            $x3 * $this->k, ($h - $y3) * $this->k));
    }

    public function Dot($x, $y, $r, array $color)
    {
        $this->SetFillColor(...$color);
        // Draw filled circle using a sequence of curves
        $arc = $r * 4 / 3 * (M_SQRT2 - 1);
        $k   = $this->k;
        $h   = $this->h;
        $this->_out(sprintf('%.2F %.2F m', ($x + $r) * $k, ($h - $y) * $k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c', ($x+$r)*$k,($h-($y-$arc))*$k, ($x+$arc)*$k,($h-($y-$r))*$k, $x*$k,($h-($y-$r))*$k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c', ($x-$arc)*$k,($h-($y-$r))*$k, ($x-$r)*$k,($h-($y-$arc))*$k, ($x-$r)*$k,($h-$y)*$k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c', ($x-$r)*$k,($h-($y+$arc))*$k, ($x-$arc)*$k,($h-($y+$r))*$k, $x*$k,($h-($y+$r))*$k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c', ($x+$arc)*$k,($h-($y+$r))*$k, ($x+$r)*$k,($h-($y+$arc))*$k, ($x+$r)*$k,($h-$y)*$k));
        $this->_out('f');
    }

    public function ProgressBar($x, $y, $w, $score, $max = 10)
    {
        $h   = 3.5;
        $pct = min(1.0, max(0.0, ($max > 0 ? $score / $max : 0)));
        $this->SetFillColor(...$this->clrBorder);
        $this->RoundedRect($x, $y, $w, $h, 1.5, 'F');
        if ($pct > 0) {
            if ($score >= 7)     $this->SetFillColor(...$this->clrGood);
            elseif ($score >= 5) $this->SetFillColor(...$this->clrAvg);
            else                 $this->SetFillColor(...$this->clrBad);
            $this->RoundedRect($x, $y, max(3, $w * $pct), $h, 1.5, 'F');
        }
    }

    public function SectionHeading(string $text)
    {
        $this->Ln(3);
        $this->SetFillColor(...$this->clrPrimary);
        $this->Rect(14, $this->GetY() + 1, 4, 5, 'F');
        $this->SetFont('Helvetica', 'B', 11);
        $this->SetTextColor(...$this->clrText);
        $this->SetX(22);
        $this->Cell(0, 7, $text, 0, 1, 'L');
        $this->Ln(1);
    }

    public function ScoreBadge($x, $y, string $label, string $value, string $outof, array $color)
    {
        $w = 43; $h = 28;
        $this->SetFillColor(...$this->clrCard);
        $this->RoundedRect($x, $y, $w, $h, 4, 'F');
        $this->SetFillColor(...$color);
        $this->RoundedRect($x, $y, $w, 3, 2, 'F');
        // Auto-shrink font for long values (e.g. 'Needs Improvement')
        $fontSize = 15;
        if (strlen($value) > 10) $fontSize = 9;
        elseif (strlen($value) > 7) $fontSize = 11;
        $this->SetFont('Helvetica', 'B', $fontSize);
        $this->SetTextColor(...$color);
        // Center vertically: push down a bit more for smaller fonts
        $valueY = $fontSize < 12 ? $y + 4 : $y + 5;
        $this->SetXY($x, $valueY);
        $this->Cell($w, 8, $value, 0, 0, 'C');
        if ($outof) {
            $this->SetFont('Helvetica', '', 7);
            $this->SetTextColor(...$this->clrMist);
            $this->SetXY($x, $y + 13);
            $this->Cell($w, 4, '/' . $outof, 0, 0, 'C');
        }
        $this->SetFont('Helvetica', 'B', 6.5);
        $this->SetTextColor(...$this->clrMist);
        $this->SetXY($x, $y + 19);
        $this->Cell($w, 5, strtoupper($label), 0, 0, 'C');
    }

    public function SafeText(string $text): string
    {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', strip_tags($text)) ?: preg_replace('/[^\x20-\x7E]/', '?', $text);
    }
}

// -- Build PDF --
$pdf = new InterviewReportPDF('P', 'mm', 'A4');
$pdf->reportTitle = 'Interview Evaluation Report';
$pdf->userName    = $session['user_name'];
$pdf->dateLabel   = $dateLabel;
$pdf->SetAuthor('AI Interview Prep Platform');
$pdf->SetTitle('Interview Report - ' . $session['category_name']);
$pdf->SetSubject('AI Evaluation Report Session #' . $sessionId);
$pdf->SetCreator('AI Interview Prep');
$pdf->SetCompression(true);
$pdf->SetMargins(0, 0, 0);
$pdf->SetAutoPageBreak(true, 20);

$pdf->AddPage();
$pdf->PageBackground();

// -- Hero --
$pdf->SetY(32);
$pdf->SetFont('Helvetica', 'B', 20);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 10, 'Interview Evaluation Report', 0, 1, 'C');

$pdf->SetFont('Helvetica', '', 9.5);
$pdf->SetTextColor(122, 72, 255);
$pdf->Cell(0, 6, $pdf->SafeText($session['category_name']) . '  |  Session #' . $sessionId . '  |  ' . $dateLabel, 0, 1, 'C');

$pdf->SetFont('Helvetica', '', 8);
$pdf->SetTextColor(140, 140, 160);
$pdf->Cell(0, 5, 'Prepared for: ' . $pdf->SafeText($session['user_name']) . '   (' . $pdf->SafeText($session['user_email']) . ')', 0, 1, 'C');
$pdf->Ln(5);

// Divider
$pdf->SetDrawColor(40, 42, 60);
$pdf->SetLineWidth(0.4);
$pdf->Line(14, $pdf->GetY(), 196, $pdf->GetY());
$pdf->Ln(6);

// -- Score Badges --
$bw = 43; $gap = 2; $startX = 14;
$badgeY = $pdf->GetY();
$oColor = $avgScore >= 7.5 ? [34,212,123] : ($avgScore >= 5 ? [245,200,66] : [255,95,109]);
$pdf->ScoreBadge($startX,                    $badgeY, 'Overall Score', number_format($avgScore, 1), '10',          $oColor);
$pdf->ScoreBadge($startX + $bw + $gap,       $badgeY, 'Performance',   $perfLabel,                  '',            $perfColor);
$pdf->ScoreBadge($startX + ($bw+$gap) * 2,  $badgeY, 'Confidence',    number_format($avgConfidence,1), '10',     [62,207,255]);
$pdf->ScoreBadge($startX + ($bw+$gap) * 3,  $badgeY, 'Answered',      $answeredCount . '/' . $questionCount, 'Qs',[122,72,255]);
$pdf->SetY($badgeY + 32);

// -- AI Suggestions --
$pdf->SectionHeading('AI Suggestions');
$aiSuggestions = array_filter(array_column($results, 'ai_summary'));
$suggText = !empty($aiSuggestions)
    ? $pdf->SafeText(implode(' ', array_slice($aiSuggestions, 0, 3)))
    : 'Review your detailed feedback below and practice regularly to improve your interview performance.';

$pdf->SetFillColor(14, 16, 28);
$pdf->RoundedRect(14, $pdf->GetY(), 182, 10, 3, 'F');
$pdf->SetFillColor(122, 72, 255);
$pdf->Rect(14, $pdf->GetY(), 3, 10, 'F');
$pdf->SetFont('Helvetica', 'I', 8);
$pdf->SetTextColor(200, 200, 220);
$pdf->SetXY(20, $pdf->GetY() + 1);
$pdf->MultiCell(172, 4, $suggText, 0, 'L');
$pdf->Ln(3);

// -- Key Strengths --
$pdf->SectionHeading('Key Strengths');
if (!empty($allStrengths)) {
    foreach (array_slice($allStrengths, 0, 4) as $s) {
        $y = $pdf->GetY();
        $pdf->SetFillColor(16, 30, 22);
        $pdf->RoundedRect(14, $y, 182, 8, 3, 'F');
        $pdf->Dot(21, $y + 4, 1.5, [34, 212, 123]);
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(180, 240, 200);
        $pdf->SetXY(26, $y + 1.5);
        $pdf->MultiCell(166, 4, $pdf->SafeText($s), 0, 'L');
        $pdf->Ln(1.5);
    }
} else {
    $pdf->SetFont('Helvetica', 'I', 8);
    $pdf->SetTextColor(140,140,160);
    $pdf->SetX(18);
    $pdf->Cell(0, 5, 'No strengths data available.', 0, 1);
}
$pdf->Ln(2);

// -- Areas to Improve --
$pdf->SectionHeading('Areas to Improve');
if (!empty($allImprovements)) {
    foreach (array_slice($allImprovements, 0, 4) as $s) {
        $y = $pdf->GetY();
        $pdf->SetFillColor(30, 25, 12);
        $pdf->RoundedRect(14, $y, 182, 8, 3, 'F');
        $pdf->Dot(21, $y + 4, 1.5, [245, 200, 66]);
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(245, 230, 160);
        $pdf->SetXY(26, $y + 1.5);
        $pdf->MultiCell(166, 4, $pdf->SafeText($s), 0, 'L');
        $pdf->Ln(1.5);
    }
} else {
    $pdf->SetFont('Helvetica', 'I', 8);
    $pdf->SetTextColor(140,140,160);
    $pdf->SetX(18);
    $pdf->Cell(0, 5, 'No improvement data available.', 0, 1);
}
$pdf->Ln(4);


// -- Tips Page --
if ($pdf->GetY() + 60 > 260) {
    $pdf->AddPage();
    $pdf->PageBackground();
}

$pdf->SectionHeading('Next Steps & Recommendations');

$tips = [];
if ($avgScore < 5) {
    $tips[] = ['Study the Fundamentals',    'Review core concepts. Focus on understanding the "why" behind each topic, not just memorizing answers.', [255,95,109]];
    $tips[] = ['Practice Daily',            'Schedule 30 min of daily mock interview practice. Consistency beats marathon sessions.', [245,200,66]];
    $tips[] = ['Use Model Answers',         'Study the model answers above and practice delivering them in your own words.', [62,207,255]];
} elseif ($avgScore < 7.5) {
    $tips[] = ['Deepen Your Knowledge',     'Your answers are good but need more depth. Add examples, metrics, and technical specifics.', [245,200,66]];
    $tips[] = ['Improve Communication',     'Use the STAR method (Situation, Task, Action, Result) to structure behavioral answers.', [34,212,123]];
    $tips[] = ['Target Weak Areas',         'Focus on questions where you scored below 6/10 and practice those topics intensively.', [62,207,255]];
} else {
    $tips[] = ['Maintain Excellence',       'Great performance! Keep refining with more industry-specific examples.', [34,212,123]];
    $tips[] = ['Practice Edge Cases',       'Try harder questions and different interview categories to broaden your skills.', [122,72,255]];
    $tips[] = ['Company-Specific Mode',     'Try Company Specific interview mode on the platform to prepare for your target company.', [62,207,255]];
}
$tips[] = ['Clear Communication',         'Read answers aloud before interviews. Structured communication is as important as knowledge.', [180,160,240]];
$tips[] = ['Retake This Interview',        'Use AI Interview Prep to retake this category and track your improvement over time.', [122,72,255]];

foreach ($tips as [$title, $desc, $color]) {
    if ($pdf->GetY() + 20 > 270) {
        $pdf->AddPage();
        $pdf->PageBackground();
    }
    $tipY = $pdf->GetY();
    $pdf->SetFillColor(16, 18, 30);
    $pdf->RoundedRect(14, $tipY, 182, 16, 4, 'F');
    $pdf->SetFillColor(...$color);
    $pdf->Rect(14, $tipY, 4, 16, 'F');
    $pdf->SetFont('Helvetica', 'B', 8.5);
    $pdf->SetTextColor(...$color);
    $pdf->SetXY(22, $tipY + 2.5);
    $pdf->Cell(0, 5, $pdf->SafeText($title), 0, 1, 'L');
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetTextColor(190, 190, 210);
    $pdf->SetX(22);
    $pdf->MultiCell(168, 4, $pdf->SafeText($desc), 0, 'L');
    $pdf->Ln(3);
}

$pdf->Ln(4);

// CTA block
if ($pdf->GetY() + 30 > 270) { $pdf->AddPage(); $pdf->PageBackground(); }
$ctaY = $pdf->GetY();
$pdf->SetFillColor(14, 16, 28);
$pdf->RoundedRect(14, $ctaY, 182, 28, 6, 'F');
$pdf->SetFillColor(122, 72, 255);
$pdf->RoundedRect(14, $ctaY, 182, 3, 3, 'F');
$pdf->SetFont('Helvetica', 'B', 12);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetXY(14, $ctaY + 7);
$pdf->Cell(182, 6, 'Ready for another round? Start your next session!', 0, 1, 'C');
$pdf->SetFont('Helvetica', '', 8.5);
$pdf->SetTextColor(140, 140, 160);
$pdf->SetX(14);
$pdf->Cell(182, 5, 'Visit AI Interview Prep and keep improving your skills daily.', 0, 1, 'C');
$pdf->SetFont('Helvetica', 'B', 9);
$pdf->SetTextColor(122, 72, 255);
$pdf->SetX(14);
$siteUrl = defined('BASE_URL') ? BASE_URL : 'AI Interview Prep Platform';
$pdf->Cell(182, 8, $pdf->SafeText($siteUrl), 0, 1, 'C');

// -- Output --
ob_clean();
$filename = 'Interview_Report_Session' . $sessionId . '_' . date('Ymd') . '.pdf';
$pdf->Output('D', $filename);
exit;
