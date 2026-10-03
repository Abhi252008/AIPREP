<?php
/**
 * Google Sign-In & OAuth Handler
 * Supports:
 * 1. Live Google Identity Services (GIS POST credential / One Tap)
 * 2. Standard Google OAuth 2.0 redirect flow (code exchange)
 * 3. Fallback Instant Demo Google Sign-In when GOOGLE_CLIENT_ID is not yet configured
 */
require_once __DIR__ . '/config/app_config.php';
require_once __DIR__ . '/config/db_connect.php';
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/includes/functions.php';

load_env(__DIR__ . '/.env');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If already logged in, send to dashboard
if (isset($_SESSION['user_id'])) {
    redirect('/user/dashboard.php');
}

$googleClientId     = getenv('GOOGLE_CLIENT_ID') ?: ($_ENV['GOOGLE_CLIENT_ID'] ?? '');
$googleClientSecret = getenv('GOOGLE_CLIENT_SECRET') ?: ($_ENV['GOOGLE_CLIENT_SECRET'] ?? '');

// Helper to log in or create user from Google profile
function process_google_user(PDO $pdo, string $googleId, string $email, string $name, string $picture): void
{
    $email = strtolower(trim($email));
    $name  = trim($name) ?: 'Google User';
    
    // Check if user exists by google_id OR email
    $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE (google_id IS NOT NULL AND google_id = ?) OR email = ? LIMIT 1");
    $stmt->execute([$googleId, $email]);
    $user = $stmt->fetch();

    if ($user) {
        $userId = (int) $user['id'];
        $userName = $user['name'] ?: $name;
        // Update google_id and profile picture if missing
        $up = $pdo->prepare("UPDATE users SET google_id = COALESCE(google_id, ?), profile_picture = CASE WHEN profile_picture IS NULL OR profile_picture = '' OR profile_picture = 'images/default-avatar.png' THEN ? ELSE profile_picture END WHERE id = ?");
        $up->execute([$googleId, $picture ?: 'images/default-avatar.png', $userId]);
    } else {
        // Create user
        $dummyPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        $avatar = $picture ?: 'images/default-avatar.png';
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, google_id, profile_picture) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $email, $dummyPassword, $googleId, $avatar]);
        $userId = (int) $pdo->lastInsertId();
        $userName = $name;
    }

    session_regenerate_id(true);
    $_SESSION['user_id']   = $userId;
    $_SESSION['user_name'] = $userName;
    set_flash('success', "Welcome, $userName! Successfully signed in with Google.");
    redirect('/user/dashboard.php');
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. Live Google Identity Services POST (credential = JWT ID Token)
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['credential'])) {
    $jwt = trim($_POST['credential']);
    $parts = explode('.', $jwt);
    if (count($parts) === 3) {
        $payloadJson = base64_decode(strtr($parts[1], '-_', '+/'));
        $payload = json_decode($payloadJson, true);

        if (!empty($payload['email'])) {
            $googleId = (string) ($payload['sub'] ?? '');
            $email    = (string) $payload['email'];
            $name     = (string) ($payload['name'] ?? 'Google User');
            $picture  = (string) ($payload['picture'] ?? '');

            process_google_user($pdo, $googleId, $email, $name, $picture);
        }
    }
    set_flash('error', 'Google authentication failed. Please try again.');
    redirect('/login.php');
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. Google OAuth 2.0 Authorization Code Callback
// ─────────────────────────────────────────────────────────────────────────────
if (isset($_GET['code']) && !empty($googleClientId) && !empty($googleClientSecret)) {
    $code = $_GET['code'];
    $redirectUri = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . BASE_URL . '/auth-google.php';

    // Exchange code for access token
    $tokenUrl = 'https://oauth2.googleapis.com/token';
    $postData = http_build_query([
        'code'          => $code,
        'client_id'     => $googleClientId,
        'client_secret' => $googleClientSecret,
        'redirect_uri'  => $redirectUri,
        'grant_type'    => 'authorization_code',
    ]);

    $ch = curl_init($tokenUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);

    $tokenData = json_decode($response, true);
    if (!empty($tokenData['access_token'])) {
        // Fetch userinfo
        $ch = curl_init('https://www.googleapis.com/oauth2/v3/userinfo');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $tokenData['access_token']]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $userInfo = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (!empty($userInfo['email'])) {
            process_google_user(
                $pdo,
                (string) ($userInfo['sub'] ?? ''),
                (string) $userInfo['email'],
                (string) ($userInfo['name'] ?? 'Google User'),
                (string) ($userInfo['picture'] ?? '')
            );
        }
    }

    set_flash('error', 'Google authentication was not completed. Please try again.');
    redirect('/login.php');
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. Initiate Google OAuth if GOOGLE_CLIENT_ID is set
// ─────────────────────────────────────────────────────────────────────────────
if (!empty($googleClientId) && !isset($_GET['demo'])) {
    $redirectUri = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . BASE_URL . '/auth-google.php';
    $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id'             => $googleClientId,
        'redirect_uri'          => $redirectUri,
        'response_type'         => 'code',
        'scope'                 => 'openid profile email',
        'access_type'           => 'online',
        'prompt'                => 'select_account',
    ]);
    header('Location: ' . $authUrl);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. Test / Demo Google Sign-In Mode (when GOOGLE_CLIENT_ID not configured yet)
// ─────────────────────────────────────────────────────────────────────────────
$demoErrors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'demo_google') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $demoErrors[] = 'Session expired. Please try again.';
    } else {
        $demoName  = trim($_POST['demo_name'] ?? 'Google User');
        $demoEmail = trim($_POST['demo_email'] ?? 'google.user@example.com');

        if (!filter_var($demoEmail, FILTER_VALIDATE_EMAIL)) {
            $demoErrors[] = 'Please enter a valid email address.';
        } else {
            $demoGoogleId = 'demo_google_' . substr(md5($demoEmail), 0, 16);
            $demoPicture  = 'https://lh3.googleusercontent.com/a/default-user=s96-c';
            process_google_user($pdo, $demoGoogleId, $demoEmail, $demoName, $demoPicture);
        }
    }
}

$pageTitle = "Google Sign-In";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/flash.php';
?>

<section class="auth-section">
  <div class="auth-card-modern" data-reveal style="max-width: 480px;">
    <div style="text-align:center; margin-bottom: 1.5rem;">
      <svg viewBox="0 0 24 24" width="48" height="48" style="margin-bottom:0.75rem;">
        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
      </svg>
      <h1>Continue with Google</h1>
      <p class="auth-sub" style="margin-bottom:0.5rem;">Fast, secure one-click sign-in for your interview prep account.</p>
    </div>

    <?php if (!empty($demoErrors)): ?>
      <div class="form-error" style="margin-bottom:1rem;">
        <?php foreach ($demoErrors as $err): ?>
          <div><i class="bi bi-exclamation-circle"></i> <?= e($err) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- Demo Google Login Card -->
    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; margin-bottom: 1.25rem;">
      <div class="d-flex align-items-center gap-2 mb-2">
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1" style="font-size:0.75rem; font-weight:600;">⚡ Instant Demo Sign-In</span>
      </div>
      <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 1rem;">
        Test Google authentication directly right now:
      </p>

      <form method="post" action="<?= BASE_URL ?>/auth-google.php">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="demo_google">

        <label for="demo_name" style="font-size:0.8rem; color:#475569;">Google Display Name</label>
        <input type="text" class="form-control" id="demo_name" name="demo_name" value="Google Demo Candidate" required style="font-size:0.88rem; margin-bottom:0.75rem;">

        <label for="demo_email" style="font-size:0.8rem; color:#475569;">Google Email Address</label>
        <input type="email" class="form-control" id="demo_email" name="demo_email" value="google.user@example.com" required style="font-size:0.88rem; margin-bottom:1rem;">

        <button type="submit" class="btn-google" style="background:#1e293b; color:#ffffff; border-color:#0f172a; justify-content:center;">
          <svg viewBox="0 0 24 24" width="18" height="18">
            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
          </svg>
          <span>Sign In as Google User</span>
        </button>
      </form>
    </div>

    <!-- Production Setup Guide -->
    <div style="background: #f1f5f9; border-radius: 8px; padding: 0.9rem; font-size: 0.8rem; color: #475569; line-height: 1.5;">
      <strong><i class="bi bi-info-circle text-primary"></i> For Production Google OAuth:</strong><br>
      Add your credentials to your <code>.env</code> file:
      <pre style="margin: 0.4rem 0 0; background: #e2e8f0; padding: 0.5rem; border-radius: 4px; font-size: 0.76rem; overflow-x:auto;">GOOGLE_CLIENT_ID=your_id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your_secret</pre>
    </div>

    <div class="auth-links" style="margin-top: 1.25rem;">
      <a href="<?= BASE_URL ?>/login.php"><i class="bi bi-arrow-left"></i> Back to Login</a>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
