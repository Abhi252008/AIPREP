<?php
/**
 * Shared header: starts the session and renders the navbar.
 * Every page includes this at the top: require_once 'includes/header.php';
 */
require_once __DIR__ . '/../config/app_config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isLoggedIn = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — ' : '' ?>AI Interview Prep</title>

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- Fonts: Space Grotesk (headings), Inter (body), IBM Plex Mono (data/timer) -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<!-- Site stylesheet -->
<link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css">
<!-- Brand Favicon -->
<link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/images/logo-symbol.svg">
</head>
<body>

<header class="navbar-glass glass">
  <a href="<?= BASE_URL ?>/index.php" class="brand">
    <img src="<?= BASE_URL ?>/images/logo-symbol.svg" alt="AI Interview Prep Logo" class="brand-logo" width="30" height="30">
    <span>AI Interview Prep</span>
  </a>

  <nav id="navLinks">
    <a href="<?= BASE_URL ?>/index.php#features">Features</a>
    <a href="<?= BASE_URL ?>/index.php#about">About</a>
    <a href="<?= BASE_URL ?>/index.php#testimonials">Testimonials</a>
    <a href="<?= BASE_URL ?>/index.php#faq">FAQ</a>
    <a href="<?= BASE_URL ?>/index.php#contact">Contact</a>
  </nav>

  <div class="d-flex align-items-center gap-2">
    <?php if ($isLoggedIn): ?>
      <a href="<?= BASE_URL ?>/user/dashboard.php" class="btn-outline-glass">Dashboard</a>
    <?php else: ?>
      <a href="<?= BASE_URL ?>/login.php" class="btn-outline-glass d-none d-md-inline-block">Log in</a>
      <a href="<?= BASE_URL ?>/register.php" class="btn-gradient">Get Started</a>
    <?php endif; ?>
    <button id="navToggle" class="btn-outline-glass d-md-none" aria-label="Toggle menu">
      <i class="bi bi-list"></i>
    </button>
  </div>
</header>
