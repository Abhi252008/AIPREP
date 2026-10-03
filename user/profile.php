<?php
/**
 * Module 3 — User Dashboard: My Profile
 * Two independent forms: (1) profile details + picture + resume upload,
 * (2) change password. Each is identified by a hidden `form_type` field
 * so one page file can handle both without them interfering.
 */
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

$userId = $_SESSION['user_id'];
$errors = [];

$userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$user = $userStmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please refresh and try again.';
    }

    $formType = $_POST['form_type'] ?? '';

    // ---------------- Profile details form ----------------
    if ($formType === 'profile_details' && empty($errors)) {
        $name = trim($_POST['name'] ?? '');
        $education = trim($_POST['education'] ?? '');
        $skills = trim($_POST['skills'] ?? '');

        if ($name === '' || mb_strlen($name) > 100) {
            $errors[] = 'Please enter your name (up to 100 characters).';
        }
        if (mb_strlen($education) > 255) {
            $errors[] = 'Education is too long (max 255 characters).';
        }

        $picturePath = null;
        $pictureUpload = handle_upload(
            'profile_picture',
            ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
            2 * 1024 * 1024, // 2 MB
            __DIR__ . '/../uploads/profiles',
            'uploads/profiles'
        );
        if ($pictureUpload['error']) {
            $errors[] = $pictureUpload['error'];
        } elseif ($pictureUpload['success']) {
            $picturePath = $pictureUpload['path'];
        }

        $resumePath = null;
        $resumeUpload = handle_upload(
            'resume',
            ['application/pdf' => 'pdf'],
            5 * 1024 * 1024, // 5 MB
            __DIR__ . '/../uploads/resumes',
            'uploads/resumes'
        );
        if ($resumeUpload['error']) {
            $errors[] = $resumeUpload['error'];
        } elseif ($resumeUpload['success']) {
            $resumePath = $resumeUpload['path'];
        }

        if (empty($errors)) {
            // Delete old uploaded files only after the new ones saved successfully.
            if ($picturePath && $user['profile_picture'] && $user['profile_picture'] !== 'images/default-avatar.png') {
                @unlink(__DIR__ . '/../' . $user['profile_picture']);
            }
            if ($resumePath && $user['resume_path']) {
                @unlink(__DIR__ . '/../' . $user['resume_path']);
            }

            $update = $pdo->prepare(
                "UPDATE users SET name = ?, education = ?, skills = ?,
                 profile_picture = COALESCE(?, profile_picture),
                 resume_path = COALESCE(?, resume_path)
                 WHERE id = ?"
            );
            $update->execute([$name, $education, $skills, $picturePath, $resumePath, $userId]);

            $_SESSION['user_name'] = $name;
            set_flash('success', 'Your profile has been updated.');
            redirect('/user/profile.php');
        }
    }

    // ---------------- Change password form ----------------
    if ($formType === 'change_password' && empty($errors)) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_new_password'] ?? '';

        if (!password_verify($currentPassword, $user['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        }
        if (!is_strong_password($newPassword)) {
            $errors[] = 'New password must be at least 8 characters and include a letter and a number.';
        }
        if ($newPassword !== $confirmPassword) {
            $errors[] = 'New passwords do not match.';
        }

        if (empty($errors)) {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $update->execute([$newHash, $userId]);

            set_flash('success', 'Your password has been changed.');
            redirect('/user/profile.php');
        }
    }

    // Re-fetch so the form reflects the DB state (or shows entered values on error).
    $userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();
}

$activePage = 'profile';
$pageTitle = "My Profile";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/flash.php';
?>

<section class="dashboard-shell">
  <?php require_once __DIR__ . '/../includes/user_sidebar.php'; ?>

  <div class="dashboard-content">
    <div class="dashboard-header" data-reveal>
      <div>
        <span class="section-eyebrow">Account</span>
        <h2 class="section-title" style="margin-bottom:0;">My Profile</h2>
      </div>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="form-error dashboard-panel glass" style="padding:1.2rem 1.5rem;">
        <?php foreach ($errors as $err): ?>
          <div><i class="bi bi-exclamation-circle"></i> <?= e($err) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="dashboard-grid">
      <div class="dashboard-panel glass" data-reveal>
        <div class="panel-header"><h3>Profile Details</h3></div>

        <div class="profile-avatar-row">
          <?= render_avatar($user['profile_picture'], $user['name'], '72px') ?>
          <div>
            <div style="font-weight:600;"><?= e($user['name']) ?></div>
            <div style="color:var(--mist); font-size:0.88rem;"><?= e($user['email']) ?></div>
          </div>
        </div>

        <form method="post" action="<?= BASE_URL ?>/user/profile.php" enctype="multipart/form-data" novalidate>
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="form_type" value="profile_details">

          <label for="name">Full name</label>
          <input type="text" class="form-control" id="name" name="name" value="<?= e($user['name']) ?>" maxlength="100" required>

          <label for="education">Education</label>
          <input type="text" class="form-control" id="education" name="education"
                 placeholder="e.g. B.Tech CSE, Final Year" value="<?= e($user['education'] ?? '') ?>" maxlength="255">

          <label for="skills">Skills</label>
          <textarea class="form-control" id="skills" name="skills" rows="3"
                    placeholder="e.g. Java, DSA, SQL, Communication"><?= e($user['skills'] ?? '') ?></textarea>

          <label for="profile_picture">Profile picture <span class="field-hint">(JPG/PNG/WebP, max 2MB)</span></label>
          <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/png,image/jpeg,image/webp">

          <label for="resume">Resume <span class="field-hint">(PDF, max 5MB)</span></label>
          <input type="file" class="form-control" id="resume" name="resume" accept="application/pdf">
          <?php if (!empty($user['resume_path'])): ?>
            <div class="field-hint" style="margin:-0.6rem 0 1rem;">
              Current: <a href="<?= BASE_URL . '/' . e($user['resume_path']) ?>" target="_blank">view uploaded resume</a>
            </div>
          <?php endif; ?>

          <button type="submit" class="btn-gradient">Save Changes</button>
        </form>
      </div>

      <div class="dashboard-panel glass" data-reveal>
        <div class="panel-header"><h3>Change Password</h3></div>

        <form method="post" action="<?= BASE_URL ?>/user/profile.php" novalidate>
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="form_type" value="change_password">

          <label for="current_password">Current password</label>
          <input type="password" class="form-control" id="current_password" name="current_password" required>

          <label for="new_password">New password</label>
          <input type="password" class="form-control" id="new_password" name="new_password"
                 placeholder="At least 8 characters, 1 letter, 1 number" minlength="8" required>

          <label for="confirm_new_password">Confirm new password</label>
          <input type="password" class="form-control" id="confirm_new_password" name="confirm_new_password" minlength="8" required>

          <button type="submit" class="btn-gradient">Update Password</button>
        </form>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
