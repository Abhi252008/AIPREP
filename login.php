<?php
/**
 * Module 2 — Authentication: Login
 * Verifies email + password directly (no OTP required for login).
 * Clean, modern UI inspired by minimalist SaaS design.
 */
require_once __DIR__ . '/config/app_config.php';
require_once __DIR__ . '/config/db_connect.php';
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/includes/functions.php';

load_env(__DIR__ . '/.env');

if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_SESSION['user_id'])) redirect('/user/dashboard.php');

$errors = [];
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf($_POST['csrf_token'] ?? null))
        $errors[] = 'Your session expired. Please try again.';

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '')
        $errors[] = 'Please enter both your email and password.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id, name, password_hash FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Deliberately vague — never reveal whether email exists
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $errors[] = 'Invalid email or password.';
        } else {
            // Direct login — OTP removed for login flow
            session_regenerate_id(true);
            $_SESSION['user_id']   = (int) $user['id'];
            $_SESSION['user_name'] = $user['name'];

            set_flash('success', 'Welcome back, ' . $user['name'] . '!');
            redirect('/user/dashboard.php');
        }
    }
}

$pageTitle = "Log In";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/flash.php';
?>

<section class="auth-section">
  <div class="auth-card-modern" data-reveal>
    <h1 style="margin-bottom: 1.5rem;">Welcome to AI Interview Prep</h1>

    <?php if (!empty($errors)): ?>
      <div class="form-error" style="margin-bottom:1.25rem;">
        <?php foreach ($errors as $err): ?>
          <div><i class="bi bi-exclamation-circle"></i> <?= e($err) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="post" action="<?= BASE_URL ?>/login.php" novalidate>
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

      <label for="email" class="auth-label-caps">EMAIL</label>
      <input type="email" class="form-control" id="email" name="email"
             placeholder="name@example.com"
             value="<?= e($email) ?>" required autofocus>

      <div class="d-flex justify-content-between align-items-center mb-1">
        <label for="password" class="auth-label-caps mb-0">PASSWORD</label>
        <a href="<?= BASE_URL ?>/forgot-password.php" class="auth-forgot-link">Forgot password?</a>
      </div>
      <input type="password" class="form-control" id="password" name="password"
             placeholder="••••••••••" required>

      <button type="submit" class="btn-auth-primary">Sign In</button>
    </form>

    <div class="auth-footer-text">
      Don't have an account? <a href="<?= BASE_URL ?>/register.php">Sign up</a>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
