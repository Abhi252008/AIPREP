<?php
/**
 * Module 2 — Registration: Step 2 of 2 — Email OTP Verification
 * Verifies the 6-digit OTP, then creates the user account.
 */
require_once __DIR__ . '/config/app_config.php';
require_once __DIR__ . '/config/db_connect.php';
require_once __DIR__ . '/config/mailer.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_SESSION['user_id'])) redirect('/user/dashboard.php');

// Guard: must have pending registration data
if (empty($_SESSION['pending_register'])) {
    set_flash('error', 'No pending registration found. Please fill the form again.');
    redirect('/register.php');
}

$pending = $_SESSION['pending_register'];
$errors  = [];
$maskedEmail = preg_replace('/(?<=.).(?=[^@]*@)/', '*', $pending['email']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf($_POST['csrf_token'] ?? null))
        $errors[] = 'Session expired. Please try again.';

    $submittedOtp = trim($_POST['otp'] ?? '');

    if (!preg_match('/^\d{6}$/', $submittedOtp))
        $errors[] = 'Please enter the 6-digit OTP.';

    if (empty($errors)) {
        if (!verify_otp($pdo, $pending['email'], $submittedOtp, 'register')) {
            $errors[] = 'Invalid or expired OTP. Please try again or request a new one.';
        }
    }

    if (empty($errors)) {
        // Double-check email isn't taken (race condition guard)
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$pending['email']]);
        if ($check->fetch()) {
            $errors[] = 'An account with that email already exists.';
        }
    }

    if (empty($errors)) {
        // Create the account
        $pdo->prepare("INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)")
            ->execute([$pending['name'], $pending['email'], $pending['password_hash']]);

        // Log in immediately
        session_regenerate_id(true);
        $_SESSION['user_id']   = (int) $pdo->lastInsertId();
        $_SESSION['user_name'] = $pending['name'];
        unset($_SESSION['pending_register'], $_SESSION['demo_otp']);

        set_flash('success', 'Welcome, ' . $pending['name'] . '! Your account has been verified and created. 🎉');
        redirect('/user/dashboard.php');
    }
}

// ── Resend OTP ────────────────────────────────────────────────────────────
$resent = false;
if (isset($_GET['resend']) && $_GET['resend'] === '1') {
    $otp = generate_otp();
    store_otp($pdo, $pending['email'], $otp, 'register');
    $sent = send_otp_email($pending['email'], $pending['name'], $otp, 'register');
    if (!$sent) $_SESSION['demo_otp'] = $otp;
    $resent = true;
}

$pageTitle = "Verify Your Email";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/flash.php';
?>

<section class="auth-section">
  <div class="auth-card-modern" data-reveal style="max-width:440px;">
    <h1>Check your inbox</h1>
    <p class="auth-sub" style="margin-bottom:1.25rem;">
      We sent a <strong>6-digit OTP</strong> to <strong style="color:#0070f3;"><?= e($maskedEmail) ?></strong>
    </p>

    <?php if ($resent): ?>
      <div class="form-success" style="margin-bottom:1rem; padding:0.75rem 1rem; border-radius:10px; background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; font-size:0.88rem;">
        <i class="bi bi-check-circle"></i> New OTP sent!
      </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div class="form-error" style="margin-bottom:1.25rem;">
        <?php foreach ($errors as $err): ?>
          <div><i class="bi bi-exclamation-circle"></i> <?= e($err) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- Demo OTP box (shown when SMTP is not configured) -->
    <?php if (!empty($_SESSION['demo_otp'])): ?>
      <div class="demo-reset-box" style="margin-bottom:1.25rem; border-radius:10px;">
        <strong>📧 Demo mode</strong> — Email not sent (SMTP not configured).<br>
        Your OTP: <strong style="font-size:1.4rem;letter-spacing:4px;color:#0070f3;"><?= e($_SESSION['demo_otp']) ?></strong>
      </div>
    <?php endif; ?>

    <form method="post" action="<?= BASE_URL ?>/verify-email-otp.php" novalidate>
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

      <label for="otp" class="auth-label-caps">ENTER 6-DIGIT OTP</label>
      <input type="text" class="form-control otp-input" id="otp" name="otp"
             placeholder="······" maxlength="6" inputmode="numeric"
             pattern="\d{6}" autocomplete="one-time-code" required autofocus
             style="font-size:1.6rem;letter-spacing:10px;text-align:center; height:52px; border-radius:10px;">

      <button type="submit" class="btn-auth-primary" style="margin-top:0.5rem;">Verify &amp; Create Account</button>
    </form>

    <div class="auth-footer-text">
      Didn't receive it?
      <a href="<?= BASE_URL ?>/verify-email-otp.php?resend=1">Resend OTP</a>
      &nbsp;·&nbsp;
      <a href="<?= BASE_URL ?>/register.php">Change email</a>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
