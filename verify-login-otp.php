<?php
/**
 * Module 2 — Login: Step 2 of 2 — OTP Verification (2FA)
 * Verifies the 6-digit OTP and creates the user session.
 */
require_once __DIR__ . '/config/app_config.php';
require_once __DIR__ . '/config/db_connect.php';
require_once __DIR__ . '/config/mailer.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_SESSION['user_id'])) redirect('/user/dashboard.php');

// Guard: must have completed step 1
if (empty($_SESSION['pending_login'])) {
    set_flash('error', 'Please log in first.');
    redirect('/login.php');
}

$pending     = $_SESSION['pending_login'];
$errors      = [];
$maskedEmail = preg_replace('/(?<=.).(?=[^@]*@)/', '*', $pending['email']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf($_POST['csrf_token'] ?? null))
        $errors[] = 'Session expired. Please try again.';

    $submittedOtp = trim($_POST['otp'] ?? '');

    if (!preg_match('/^\d{6}$/', $submittedOtp))
        $errors[] = 'Please enter the 6-digit OTP.';

    if (empty($errors)) {
        if (!verify_otp($pdo, $pending['email'], $submittedOtp, 'login')) {
            $errors[] = 'Invalid or expired OTP. Please try again.';
        }
    }

    if (empty($errors)) {
        // OTP verified — create the real session
        session_regenerate_id(true);
        $_SESSION['user_id']   = $pending['user_id'];
        $_SESSION['user_name'] = $pending['user_name'];
        unset($_SESSION['pending_login'], $_SESSION['demo_otp_login']);

        set_flash('success', 'Welcome back, ' . $pending['user_name'] . '!');
        redirect('/user/dashboard.php');
    }
}

// ── Resend OTP ────────────────────────────────────────────────────────────
$resent = false;
if (isset($_GET['resend']) && $_GET['resend'] === '1') {
    $otp  = generate_otp();
    store_otp($pdo, $pending['email'], $otp, 'login');
    $sent = send_otp_email($pending['email'], $pending['user_name'], $otp, 'login');
    if (!$sent) $_SESSION['demo_otp_login'] = $otp;
    $resent = true;
}

$pageTitle = "Verify Login";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/flash.php';
?>

<section class="auth-section">
  <div class="auth-card glass" data-reveal style="max-width:440px;">
    <span class="section-eyebrow">Two-Factor Authentication</span>
    <h1>Enter your OTP</h1>
    <p class="auth-sub">
      We sent a <strong>6-digit code</strong> to<br>
      <strong style="color:var(--accent);"><?= e($maskedEmail) ?></strong>
    </p>

    <?php if ($resent): ?>
      <div class="form-success" style="margin-bottom:1rem;">
        <i class="bi bi-check-circle"></i> New OTP sent!
      </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div class="form-error" style="margin-bottom:1rem;">
        <?php foreach ($errors as $err): ?>
          <div><i class="bi bi-exclamation-circle"></i> <?= e($err) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- Demo OTP (shown when SMTP not configured) -->
    <?php if (!empty($_SESSION['demo_otp_login'])): ?>
      <div class="demo-reset-box" style="margin-bottom:1rem;">
        <strong>📧 Demo mode</strong> — SMTP not configured.<br>
        Your OTP: <strong style="font-size:1.4rem;letter-spacing:4px;color:var(--accent);"><?= e($_SESSION['demo_otp_login']) ?></strong>
      </div>
    <?php endif; ?>

    <form method="post" action="<?= BASE_URL ?>/verify-login-otp.php" novalidate>
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

      <label for="otp">6-Digit OTP</label>
      <input type="text" class="form-control otp-input" id="otp" name="otp"
             placeholder="······" maxlength="6" inputmode="numeric"
             pattern="\d{6}" autocomplete="one-time-code" required
             style="font-size:1.6rem;letter-spacing:10px;text-align:center;">

      <button type="submit" class="btn-gradient" style="margin-top:1rem;">Verify &amp; Log In</button>
    </form>

    <div class="auth-links" style="margin-top:1rem;">
      Didn't receive it? <a href="<?= BASE_URL ?>/verify-login-otp.php?resend=1">Resend OTP</a>
      &nbsp;·&nbsp;
      <a href="<?= BASE_URL ?>/login.php">Back to login</a>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
