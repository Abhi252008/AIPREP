<?php
/**
 * Module 2 — Authentication: Admin Login
 * Separate session key (admin_id) from the student session (user_id),
 * so an admin and a student can even be logged in on the same browser
 * in theory, and so admin pages never trust a plain user login.
 */
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['admin_id'])) {
    redirect('/admin/dashboard.php');
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    }

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Please enter both email and password.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id, name, password_hash FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            $errors[] = 'Invalid email or password.';
        } else {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $admin['id'];
            $_SESSION['admin_name'] = $admin['name'];

            set_flash('success', 'Welcome back, ' . $admin['name'] . '.');
            redirect('/admin/dashboard.php');
        }
    }
}

$pageTitle = "Admin Login";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/flash.php';
?>

<section class="auth-section">
  <div class="auth-card glass" data-reveal>
    <span class="section-eyebrow">Admin</span>
    <h1>Admin log in</h1>
    <p class="auth-sub">Restricted to platform administrators.</p>

    <?php if (!empty($errors)): ?>
      <div class="form-error" style="margin-bottom:1rem;">
        <?php foreach ($errors as $err): ?>
          <div><i class="bi bi-exclamation-circle"></i> <?= e($err) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="post" action="<?= BASE_URL ?>/admin/login.php" novalidate>
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

      <label for="email">Email</label>
      <input type="email" class="form-control" id="email" name="email" placeholder="[email protected]"
             value="<?= e($email) ?>" required>

      <label for="password">Password</label>
      <input type="password" class="form-control" id="password" name="password"
             placeholder="Your password" required>

      <button type="submit" class="btn-gradient">Log In</button>
    </form>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
