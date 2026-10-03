<?php
/**
 * Lightweight Gmail SMTP mailer — no Composer / PHPMailer needed.
 * Uses PHP stream sockets with STARTTLS upgrade (port 587).
 *
 * Call send_otp_email() from any page to dispatch a 6-digit OTP.
 * Credentials are read from .env via load_env().
 */

require_once __DIR__ . '/env.php';
load_env(__DIR__ . '/../.env');

/* ─────────────────────────────────────────────────────────────────────────
 * Low-level SMTP helper
 * ───────────────────────────────────────────────────────────────────────── */

/**
 * Send one email via SMTP (STARTTLS, port 587) using raw PHP streams.
 * Returns true on success, false on any error.
 */
function smtp_send(string $toEmail, string $toName, string $subject, string $htmlBody): bool
{
    // Use $_ENV first (set by load_env), then fall back to system getenv()
    // This is more reliable on Windows XAMPP where getenv() can sometimes
    // miss values set by putenv() within the same request.
    $env = fn(string $k, string $default = '') => $_ENV[$k] ?? getenv($k) ?: $default;

    $host     = $env('SMTP_HOST', 'smtp.gmail.com');
    $port     = (int) $env('SMTP_PORT', '587');
    $user     = $env('SMTP_USER');
    $pass     = $env('SMTP_PASS');
    $from     = $env('SMTP_FROM') ?: $user;
    $fromName = $env('SMTP_FROM_NAME', 'AI Interview Prep');

    if ($user === '' || $pass === '') {
        error_log('[Mailer] SMTP_USER or SMTP_PASS not set in .env');
        return false;
    }

    $errno = 0; $errstr = '';
    $sock = @fsockopen($host, $port, $errno, $errstr, 15);
    if (!$sock) {
        error_log("[Mailer] Cannot connect to $host:$port — $errstr ($errno)");
        return false;
    }

    /**
     * Read one SMTP response line (or multi-line block).
     * Returns the full response string.
     */
    $read = function () use ($sock): string {
        $out = '';
        while (!feof($sock)) {
            $line = fgets($sock, 512);
            $out .= $line;
            // Last line has a space after the 3-digit code: "250 OK"
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $out;
    };

    /**
     * Send one SMTP command and return the server response.
     */
    $cmd = function (string $command) use ($sock, $read): string {
        fwrite($sock, $command . "\r\n");
        return $read();
    };

    try {
        $read(); // greeting

        // EHLO
        $cmd("EHLO " . ($from ? explode('@', $from)[1] : 'localhost'));

        // Upgrade to TLS
        $resp = $cmd("STARTTLS");
        if (strpos($resp, '220') === false) {
            fclose($sock);
            error_log('[Mailer] STARTTLS refused: ' . $resp);
            return false;
        }

        // Enable crypto on the stream
        if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($sock);
            error_log('[Mailer] TLS handshake failed');
            return false;
        }

        // EHLO again after TLS
        $cmd("EHLO " . ($from ? explode('@', $from)[1] : 'localhost'));

        // AUTH LOGIN
        $cmd("AUTH LOGIN");
        $cmd(base64_encode($user));
        $resp = $cmd(base64_encode($pass));
        if (strpos($resp, '235') === false) {
            fclose($sock);
            error_log('[Mailer] AUTH failed (wrong credentials?): ' . $resp);
            return false;
        }

        // MAIL FROM
        $cmd("MAIL FROM:<$from>");

        // RCPT TO
        $resp = $cmd("RCPT TO:<$toEmail>");
        if (strpos($resp, '250') === false && strpos($resp, '251') === false) {
            fclose($sock);
            error_log('[Mailer] RCPT TO failed: ' . $resp);
            return false;
        }

        // DATA
        $cmd("DATA");

        $boundary = bin2hex(random_bytes(8));
        $date     = date('r');
        $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
        $encodedToName   = '=?UTF-8?B?' . base64_encode($toName)   . '?=';
        $encodedSubject  = '=?UTF-8?B?' . base64_encode($subject)  . '?=';

        $message  = "Date: $date\r\n";
        $message .= "From: $encodedFromName <$from>\r\n";
        $message .= "To: $encodedToName <$toEmail>\r\n";
        $message .= "Subject: $encodedSubject\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
        $message .= "\r\n";

        // Plain text fallback
        $plainText = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));
        $message .= "--$boundary\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        $message .= $plainText . "\r\n";

        // HTML part
        $message .= "--$boundary\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $message .= $htmlBody . "\r\n";

        $message .= "--$boundary--\r\n";
        $message .= "\r\n.";

        $resp = $cmd($message);
        $cmd("QUIT");
        fclose($sock);

        if (strpos($resp, '250') === false) {
            error_log('[Mailer] Message rejected: ' . $resp);
            return false;
        }

        return true;

    } catch (\Throwable $e) {
        fclose($sock);
        error_log('[Mailer] Exception: ' . $e->getMessage());
        return false;
    }
}

/* ─────────────────────────────────────────────────────────────────────────
 * OTP helpers
 * ───────────────────────────────────────────────────────────────────────── */

/**
 * Generate a cryptographically-secure 6-digit OTP string.
 */
function generate_otp(): string
{
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Store a new OTP in the database (invalidates any previous unused OTPs
 * for the same email + purpose so the user can't have multiple active ones).
 *
 * @param PDO    $pdo
 * @param string $email
 * @param string $otp
 * @param string $purpose  'register' | 'login' | 'reset_password'
 * @param int    $ttlSecs  Seconds until expiry (default 10 minutes)
 */
function store_otp(PDO $pdo, string $email, string $otp, string $purpose, int $ttlSecs = 600): void
{
    // Invalidate previous OTPs for this email+purpose
    $pdo->prepare("DELETE FROM otp_verifications WHERE email = ? AND purpose = ?")
        ->execute([$email, $purpose]);

    $expiry = date('Y-m-d H:i:s', time() + $ttlSecs);
    $pdo->prepare("INSERT INTO otp_verifications (email, otp, purpose, expires_at) VALUES (?, ?, ?, ?)")
        ->execute([$email, $otp, $purpose, $expiry]);
}

/**
 * Verify a submitted OTP.
 * Returns true if valid + not expired + not already used.
 * Marks the OTP as used on success.
 */
function verify_otp(PDO $pdo, string $email, string $submittedOtp, string $purpose): bool
{
    $stmt = $pdo->prepare(
        "SELECT id, otp, expires_at, used
           FROM otp_verifications
          WHERE email = ? AND purpose = ?
          ORDER BY created_at DESC
          LIMIT 1"
    );
    $stmt->execute([$email, $purpose]);
    $row = $stmt->fetch();

    if (!$row)                              return false;
    if ((int)$row['used'] === 1)            return false;
    if (strtotime($row['expires_at']) < time()) return false;
    if (!hash_equals($row['otp'], $submittedOtp)) return false;

    // Mark as used
    $pdo->prepare("UPDATE otp_verifications SET used = 1 WHERE id = ?")
        ->execute([$row['id']]);

    return true;
}

/* ─────────────────────────────────────────────────────────────────────────
 * Branded email templates
 * ───────────────────────────────────────────────────────────────────────── */

/**
 * Send a branded OTP email for the given purpose.
 * Returns true on success, false on failure.
 */
function send_otp_email(string $toEmail, string $toName, string $otp, string $purpose): bool
{
    $subjects = [
        'register'       => 'Verify your email — AI Interview Prep',
        'login'          => 'Your login OTP — AI Interview Prep',
        'reset_password' => 'Reset your password — AI Interview Prep',
    ];

    $headings = [
        'register'       => '📧 Verify Your Email',
        'login'          => '🔐 Your Login Code',
        'reset_password' => '🔑 Reset Your Password',
    ];

    $messages = [
        'register'       => 'You\'re almost there! Enter the OTP below to verify your email and create your account.',
        'login'          => 'Someone is trying to log in to your account. Enter the OTP below to complete sign-in.',
        'reset_password' => 'We received a request to reset your password. Use the OTP below to proceed.',
    ];

    $subject = $subjects[$purpose]  ?? 'Your OTP — AI Interview Prep';
    $heading = $headings[$purpose]  ?? 'Your OTP';
    $message = $messages[$purpose]  ?? 'Use the OTP below.';

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
</head>
<body style="margin:0;padding:0;background:#f0f4ff;font-family:'Segoe UI',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4ff;padding:40px 16px;">
    <tr><td align="center">
      <table width="520" cellpadding="0" cellspacing="0"
             style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(37,99,235,0.10);">

        <!-- Header -->
        <tr>
          <td style="background:linear-gradient(135deg,#2563eb,#7c3aed);padding:32px 40px;text-align:center;">
            <div style="font-size:28px;font-weight:800;color:#ffffff;letter-spacing:-0.5px;">
              🤖 AI Interview Prep
            </div>
            <div style="color:rgba(255,255,255,0.75);font-size:14px;margin-top:4px;">
              Your personal interview coach
            </div>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td style="padding:40px;">
            <h2 style="margin:0 0 8px;color:#1e293b;font-size:22px;font-weight:700;">$heading</h2>
            <p style="margin:0 0 28px;color:#64748b;font-size:15px;line-height:1.6;">
              Hi <strong>$toName</strong>, $message
            </p>

            <!-- OTP Box -->
            <div style="background:#f0f4ff;border:2px dashed #2563eb;border-radius:12px;
                        padding:24px;text-align:center;margin-bottom:28px;">
              <div style="font-size:13px;color:#64748b;text-transform:uppercase;letter-spacing:2px;margin-bottom:8px;">
                Your One-Time Password
              </div>
              <div style="font-size:48px;font-weight:900;letter-spacing:12px;color:#2563eb;
                          font-family:'Courier New',monospace;">
                $otp
              </div>
              <div style="font-size:12px;color:#94a3b8;margin-top:8px;">
                ⏱ Valid for <strong>10 minutes</strong>
              </div>
            </div>

            <p style="margin:0;color:#94a3b8;font-size:13px;line-height:1.5;">
              If you didn't request this, you can safely ignore this email.<br>
              Never share this OTP with anyone.
            </p>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="background:#f8fafc;padding:20px 40px;text-align:center;border-top:1px solid #e2e8f0;">
            <p style="margin:0;color:#94a3b8;font-size:12px;">
              © 2025 AI Interview Prep &nbsp;·&nbsp; This is an automated message, please do not reply.
            </p>
          </td>
        </tr>

      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

    return smtp_send($toEmail, $toName, $subject, $html);
}
