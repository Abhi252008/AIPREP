<?php
/**
 * Module 2 — Forgot Password (Step 1 of 2)
 * Accepts email, sends a 6-digit OTP for password reset.
 */
require_once __DIR__ . '/config/app_config.php';
require_once __DIR__ . '/config/db_connect.php';
require_once __DIR__ . '/config/mailer.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$errors = [];
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf($_POST['csrf_token'] ?? null))
        $errors[] = 'Your session expired. Please try again.';

    $email = trim($_POST['email'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Please enter a valid email address.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $otp = generate_otp();
            store_otp($pdo, $email, $otp, 'reset_password');
            $sent = send_otp_email($email, $user['name'], $otp, 'reset_password');

            // Store email in session so reset page knows who to update
            $_SESSION['pending_reset'] = ['email' => $email, 'name' => $user['name']];

            if (!$sent) $_SESSION['demo_otp_reset'] = $otp;
        }

        // Same message regardless — don't reveal if email is registered
        set_flash('info', 'If an account exists for that email, a reset code has been sent.');
        redirect('/verify-reset-otp.php');
    }
}

$pageTitle = "Forgot Password";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/flash.php';
?>

<section class="auth-section">
  <div class="auth-card glass" data-reveal>
    <span class="section-eyebrow">Account Recovery</span>
    <h1>Reset your password</h1>
    <p class="auth-sub">Enter the email on your account and we'll send you a reset code.</p>

    <?php if (!empty($errors)): ?>
      <div class="form-error" style="margin-bottom:1rem;">
        <?php foreach ($errors as $err): ?>
          <div><i class="bi bi-exclamation-circle"></i> <?= e($err) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="post" action="<?= BASE_URL ?>/forgot-password.php" novalidate>
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

      <label for="fp-email">Email</label>
      <input type="email" class="form-control" id="fp-email" name="email" placeholder="you@example.com"
             value="<?= e($email) ?>" required>

      <button type="submit" class="btn-gradient">Send Reset Code</button>
    </form>

    <div class="auth-links">
      <a href="<?= BASE_URL ?>/login.php">Back to log in</a>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
