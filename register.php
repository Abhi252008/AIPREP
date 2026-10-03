<?php
/**
 * Module 2 — Authentication: Registration (Step 1 of 2)
 * Validates form, stores pending data in session, and sends OTP to email.
 * Account is created ONLY after OTP is verified in verify-email-otp.php.
 */
require_once __DIR__ . '/config/app_config.php';
require_once __DIR__ . '/config/db_connect.php';
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/config/mailer.php';
require_once __DIR__ . '/includes/functions.php';

load_env(__DIR__ . '/.env');

if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_SESSION['user_id'])) redirect('/user/dashboard.php');

$errors = [];
$name = $email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    }

    $name            = trim($_POST['name'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $password        = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($name === '' || mb_strlen($name) > 100)
        $errors[] = 'Please enter your name (up to 100 characters).';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150)
        $errors[] = 'Please enter a valid email address.';
    if (!is_strong_password($password))
        $errors[] = 'Password must be at least 8 characters and include a letter and a number.';
    if ($password !== $confirmPassword)
        $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch())
            $errors[] = 'An account with that email already exists. Try logging in instead.';
    }

    if (empty($errors)) {
        // Store pending registration in session (not DB yet — unverified)
        $_SESSION['pending_register'] = [
            'name'          => $name,
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ];

        // Generate + store OTP
        $otp = generate_otp();
        store_otp($pdo, $email, $otp, 'register');

        // Send email
        $sent = send_otp_email($email, $name, $otp, 'register');

        if ($sent) {
            set_flash('info', "We've sent a 6-digit OTP to $email. Enter it below to activate your account.");
            redirect('/verify-email-otp.php');
        } else {
            // Email failed — show OTP on screen for local/demo mode
            $_SESSION['demo_otp'] = $otp;
            set_flash('info', "OTP generated (demo mode — email not sent). Check the box below.");
            redirect('/verify-email-otp.php');
        }
    }
}

$pageTitle = "Create Account";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/flash.php';
?>

<section class="auth-section">
  <div class="auth-card-modern" data-reveal>
    <h1 style="margin-bottom: 1.5rem;">Create your account</h1>

    <?php if (!empty($errors)): ?>
      <div class="form-error" style="margin-bottom:1.25rem;">
        <?php foreach ($errors as $err): ?>
          <div><i class="bi bi-exclamation-circle"></i> <?= e($err) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="post" action="<?= BASE_URL ?>/register.php" novalidate>
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

      <label for="reg-name" class="auth-label-caps">FULL NAME</label>
      <input type="text" class="form-control" id="reg-name" name="name"
             placeholder="Your full name"
             value="<?= e($name) ?>" maxlength="100" required autofocus>

      <label for="reg-email" class="auth-label-caps">EMAIL</label>
      <input type="email" class="form-control" id="reg-email" name="email"
             placeholder="name@example.com"
             value="<?= e($email) ?>" maxlength="150" required>

      <label for="reg-password" class="auth-label-caps">PASSWORD</label>
      <input type="password" class="form-control" id="reg-password" name="password"
             placeholder="••••••••••" minlength="8" required>

      <label for="reg-confirm-password" class="auth-label-caps">CONFIRM PASSWORD</label>
      <input type="password" class="form-control" id="reg-confirm-password" name="confirm_password"
             placeholder="••••••••••" minlength="8" required>

      <div style="font-size:0.8rem; color:#64748b; margin:-0.3rem 0 1.25rem; display:flex; align-items:center; gap:0.4rem;">
        <i class="bi bi-shield-check text-primary"></i> A 6-digit OTP will be sent to your email to verify and activate your account.
      </div>

      <button type="submit" class="btn-auth-primary">
        <i class="bi bi-envelope-check me-1"></i> Send Verification OTP
      </button>
    </form>

    <div class="auth-footer-text">
      Already have an account? <a href="<?= BASE_URL ?>/login.php">Sign in</a>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
