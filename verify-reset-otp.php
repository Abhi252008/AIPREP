<?php
/**
 * Module 2 — Forgot Password: Step 2 of 2 — OTP Verification + New Password
 * Verifies the reset OTP and updates the user's password.
 */
require_once __DIR__ . '/config/app_config.php';
require_once __DIR__ . '/config/db_connect.php';
require_once __DIR__ . '/config/mailer.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Guard: must have a pending reset
if (empty($_SESSION['pending_reset'])) {
    set_flash('error', 'No password reset in progress. Please start again.');
    redirect('/forgot-password.php');
}

$pending     = $_SESSION['pending_reset'];
$errors      = [];
$maskedEmail = preg_replace('/(?<=.).(?=[^@]*@)/', '*', $pending['email']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf($_POST['csrf_token'] ?? null))
        $errors[] = 'Session expired. Please try again.';

    $submittedOtp    = trim($_POST['otp'] ?? '');
    $newPassword     = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!preg_match('/^\d{6}$/', $submittedOtp))
        $errors[] = 'Please enter the 6-digit OTP.';
    if (!is_strong_password($newPassword))
        $errors[] = 'Password must be at least 8 characters with a letter and a number.';
    if ($newPassword !== $confirmPassword)
        $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        if (!verify_otp($pdo, $pending['email'], $submittedOtp, 'reset_password')) {
            $errors[] = 'Invalid or expired OTP. Please request a new one.';
        }
    }

    if (empty($errors)) {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expiry = NULL WHERE email = ?")
            ->execute([$hash, $pending['email']]);

        unset($_SESSION['pending_reset'], $_SESSION['demo_otp_reset']);
        set_flash('success', 'Password reset successfully! Please log in with your new password.');
        redirect('/login.php');
    }
}

// ── Resend OTP ────────────────────────────────────────────────────────────
$resent = false;
if (isset($_GET['resend']) && $_GET['resend'] === '1') {
    $otp  = generate_otp();
    store_otp($pdo, $pending['email'], $otp, 'reset_password');
    $sent = send_otp_email($pending['email'], $pending['name'], $otp, 'reset_password');
    if (!$sent) $_SESSION['demo_otp_reset'] = $otp;
    $resent = true;
}

$pageTitle = "Reset Password";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/flash.php';
?>

<section class="auth-section">
  <div class="auth-card glass" data-reveal style="max-width:460px;">
    <span class="section-eyebrow">Set New Password</span>
    <h1>Reset your password</h1>
    <p class="auth-sub">
      Enter the OTP sent to
      <strong style="color:var(--accent);"><?= e($maskedEmail) ?></strong>
      and choose a new password.
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
    <?php if (!empty($_SESSION['demo_otp_reset'])): ?>
      <div class="demo-reset-box" style="margin-bottom:1rem;">
        <strong>📧 Demo mode</strong> — SMTP not configured.<br>
        Your OTP: <strong style="font-size:1.4rem;letter-spacing:4px;color:var(--accent);"><?= e($_SESSION['demo_otp_reset']) ?></strong>
      </div>
    <?php endif; ?>

    <form method="post" action="<?= BASE_URL ?>/verify-reset-otp.php" novalidate>
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

      <label for="otp">6-Digit OTP</label>
      <input type="text" class="form-control otp-input" id="otp" name="otp"
             placeholder="······" maxlength="6" inputmode="numeric"
             pattern="\d{6}" autocomplete="one-time-code" required
             style="font-size:1.6rem;letter-spacing:10px;text-align:center;margin-bottom:1rem;">

      <label for="new-password">New Password</label>
      <input type="password" class="form-control" id="new-password" name="new_password"
             placeholder="At least 8 characters, 1 letter, 1 number" minlength="8" required>

      <label for="confirm-password">Confirm New Password</label>
      <input type="password" class="form-control" id="confirm-password" name="confirm_password"
             placeholder="Re-enter your new password" minlength="8" required>

      <button type="submit" class="btn-gradient" style="margin-top:0.5rem;">Reset Password</button>
    </form>

    <div class="auth-links" style="margin-top:1rem;">
      Didn't receive it? <a href="<?= BASE_URL ?>/verify-reset-otp.php?resend=1">Resend OTP</a>
      &nbsp;·&nbsp;
      <a href="<?= BASE_URL ?>/forgot-password.php">Change email</a>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
