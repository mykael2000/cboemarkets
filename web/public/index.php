<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/csrf.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/email.php';

$publicBase = '/web/public';

// Already logged in → go to dashboard
if (is_logged_in()) {
    redirect($publicBase . '/app/index.php');
}

$error = get_flash('error');
$activeAuthTab = $_GET['tab'] ?? 'signin';

if (!in_array($activeAuthTab, ['signin', 'signup'], true)) {
  $activeAuthTab = 'signin';
}

function ensure_email_verification_columns(PDO $pdo): void
{
  $columns = [
    'email_verified'       => "ALTER TABLE users ADD COLUMN email_verified tinyint(1) NOT NULL DEFAULT 0 AFTER status",
    'email_verify_code'    => "ALTER TABLE users ADD COLUMN email_verify_code varchar(10) DEFAULT NULL",
    'email_verify_token'   => "ALTER TABLE users ADD COLUMN email_verify_token varchar(64) DEFAULT NULL",
    'email_verify_expires' => "ALTER TABLE users ADD COLUMN email_verify_expires datetime DEFAULT NULL",
  ];

  $schemaSql = 'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1';
  $schemaStmt = $pdo->prepare($schemaSql);

  foreach ($columns as $column => $sql) {
    $schemaStmt->execute(['users', $column]);
    if (!$schemaStmt->fetchColumn()) {
      $pdo->exec($sql);
    }
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

  $authMode = $_POST['auth_mode'] ?? 'signin';

  if ($authMode === 'signup') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    // Google reCAPTCHA v2 server-side verification
    $recaptchaResponse = trim($_POST['g-recaptcha-response'] ?? '');
    if ($recaptchaResponse === '') {
      flash('error', 'Please complete the reCAPTCHA verification.');
      redirect($publicBase . '/index.php?tab=signup');
    }
    $recaptchaSecret = env('RECAPTCHA_SECRET_KEY', '');
    if ($recaptchaSecret !== '') {
      $verifyResp = @file_get_contents(
        'https://www.google.com/recaptcha/api/siteverify?secret=' . urlencode($recaptchaSecret) .
        '&response=' . urlencode($recaptchaResponse) .
        '&remoteip=' . urlencode($_SERVER['REMOTE_ADDR'] ?? '')
      );
      $verifyData = $verifyResp ? json_decode($verifyResp, true) : null;
      if (!($verifyData['success'] ?? false)) {
        $errCodes = [];
        if (isset($verifyData['error-codes']) && is_array($verifyData['error-codes'])) {
          $errCodes = $verifyData['error-codes'];
        }
        $detail = $errCodes ? (' (' . implode(', ', $errCodes) . ')') : '';
        flash('error', 'reCAPTCHA verification failed' . $detail . '.');
        redirect($publicBase . '/index.php?tab=signup');
      }
    }

    if ($name === '' || $email === '' || $password === '') {
      flash('error', 'All fields are required.');
      redirect($publicBase . '/index.php?tab=signup');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      flash('error', 'Please enter a valid email address.');
      redirect($publicBase . '/index.php?tab=signup');
    }

    if (strlen($password) < 8) {
      flash('error', 'Password must be at least 8 characters long.');
      redirect($publicBase . '/index.php?tab=signup');
    }

    if ($password !== $confirm) {
      flash('error', 'Passwords do not match.');
      redirect($publicBase . '/index.php?tab=signup');
    }

    try {
      $pdo = db();
      ensure_email_verification_columns($pdo);

      $check = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
      $check->execute([$email]);

      if ($check->fetch()) {
        flash('error', 'An account with that email already exists.');
        redirect($publicBase . '/index.php?tab=signup');
      }

      $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
      $stmt = $pdo->prepare('INSERT INTO users (name, email, password, role, status, balance) VALUES (?, ?, ?, ?, ?, ?)');
      $stmt->execute([$name, $email, $hashed, 'user', 'active', 0.0]);
      $userId = (int) $pdo->lastInsertId();

      // Generate email verification code + token
      $code    = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
      $verTok  = bin2hex(random_bytes(32));
      $expires = date('Y-m-d H:i:s', strtotime('+30 minutes'));
      $pdo->prepare('UPDATE users SET email_verify_code=?, email_verify_token=?, email_verify_expires=? WHERE id=?')
          ->execute([$code, $verTok, $expires, $userId]);
      send_verification_email($email, $name, $code, $verTok);
      $_SESSION['pending_verify_user_id'] = $userId;
      redirect($publicBase . '/verify_email.php');
    } catch (Throwable $e) {
      flash('error', 'Registration failed: ' . $e->getMessage());
      redirect($publicBase . '/index.php?tab=signup');
    }
  }

  $email    = trim($_POST['email'] ?? '');
  $password = trim($_POST['password'] ?? '');
  $remember = !empty($_POST['remember']);

    if ($email === '' || $password === '') {
        flash('error', 'Email and password are required.');
    redirect($publicBase . '/index.php?tab=signin');
    }

    try {
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            flash('error', 'Invalid email or password.');
      redirect($publicBase . '/index.php?tab=signin');
        }

        if ($user['status'] === 'disabled') {
            flash('error', 'Your account has been disabled. Please contact support.');
      redirect($publicBase . '/index.php?tab=signin');
        }

        if (!$user['email_verified']) {
            $code    = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $verTok  = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            db()->prepare('UPDATE users SET email_verify_code=?, email_verify_token=?, email_verify_expires=? WHERE id=?')
                ->execute([$code, $verTok, $expires, $user['id']]);
            send_verification_email($user['email'], $user['name'], $code, $verTok);
            $_SESSION['pending_verify_user_id'] = $user['id'];
            flash('error', 'Please verify your email address. A new code has been sent.');
            redirect($publicBase . '/verify_email.php');
        }

        login_user($user);

        // Send login notification email
        $loginTime = gmdate('d-m-Y H:i');
        $ip        = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $ua        = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        send_login_notification_email($user['email'], $user['name'], $ip, $ua, $loginTime);

        if ($remember) {
            // Extend cookie lifetime to 30 days
            $params = session_get_cookie_params();
            setcookie(session_name(), session_id(), time() + 60 * 60 * 24 * 30,
                $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        redirect($publicBase . '/app/index.php');
    } catch (Throwable $e) {
        flash('error', 'A system error occurred. Please try again.');
        redirect($publicBase . '/index.php?tab=signin');
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/images/favicon.png">
  <title>CBOE Markets – Automated Crypto Trading</title>
  <!-- SEO -->
  <meta name="description" content="CBOE Markets is a smart digital trading platform. Access markets, track portfolios, and trade with confidence 24/7.">
  <!-- Open Graph / link preview -->
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://cboemarkets.com/">
  <meta property="og:title" content="CBOE Markets – Smart Trading Platform">
  <meta property="og:description" content="CBOE Markets is a smart digital trading platform. Access markets, track portfolios, and trade with confidence 24/7.">
  <meta property="og:image" content="<?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . htmlspecialchars($_SERVER['HTTP_HOST'], ENT_QUOTES) ?>/images/fav.png">
  <meta property="og:image:type" content="image/png">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">

  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="CBOE Markets – Smart Trading Platform">
  <meta name="twitter:description" content="CBOE Markets is a smart digital trading platform. Access markets, track portfolios, and trade with confidence 24/7.">
  <meta name="twitter:image" content="<?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . htmlspecialchars($_SERVER['HTTP_HOST'], ENT_QUOTES) ?>/images/fav.png">

  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://www.google.com/recaptcha/api.js" async defer></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: '#10b981'
          }
        }
      }
    }
  </script>
</head>
<body class="bg-white text-slate-900 antialiased min-h-screen flex flex-col">

<!-- ============================================================
     NAVBAR
     ============================================================ -->
<header class="sticky top-0 z-50 bg-white/95 backdrop-blur border-b border-slate-200">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16">
    <a href="index.php" class="flex items-center">
      <span class="text-2xl font-extrabold tracking-tight text-emerald-600">CBOE<span class="text-slate-900">Markets</span></span>
    </a>
    <nav class="hidden md:flex items-center gap-6 text-sm font-medium text-slate-600">
      <a href="<?= htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/login.php', ENT_QUOTES, 'UTF-8') ?>" class="hover:text-slate-900 transition">Login</a>
      <button type="button" id="joinCommunityBtn" class="bg-[#2f7fe0] hover:bg-[#2968b5] text-white px-4 py-2 rounded-lg transition font-semibold shadow-sm">Join Community</button>
    </nav>
    <!-- Mobile hamburger -->
    <button id="mobileMenuBtn" class="md:hidden text-slate-700 hover:text-slate-900 focus:outline-none">
      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
      </svg>
    </button>
  </div>
  <!-- Mobile menu -->
  <div id="mobileMenu" class="hidden md:hidden bg-white border-t border-slate-200 px-4 py-3 space-y-2 shadow-sm">
    <a href="<?= htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/login.php', ENT_QUOTES, 'UTF-8') ?>" class="block text-slate-600 hover:text-slate-900 py-1">Login</a>
    <button type="button" data-open-community-modal class="block bg-[#2f7fe0] text-white px-4 py-2 rounded-lg text-center font-semibold w-full">Join Community</button>
  </div>
</header>

<!-- ============================================================
     AUTH SWITCHER
     ============================================================ -->
<section class="py-3 md:py-4 lg:py-5 min-h-[calc(100vh-8.5rem)] flex items-center">
  <div class="w-full max-w-6xl mx-auto px-4 sm:px-6">
    <div class="bg-white border border-slate-200 rounded-3xl shadow-xl shadow-slate-200/60 overflow-hidden">
      <div class="p-4 sm:p-6 lg:p-7 border-b border-slate-200 bg-slate-50/80">
        <div class="grid grid-cols-2 gap-2 rounded-2xl bg-slate-100 p-1">
          <button type="button" class="auth-tab-btn rounded-xl px-4 py-3 lg:py-3.5 text-sm lg:text-base font-semibold transition" data-auth-tab="signin">Sign in</button>
          <button type="button" class="auth-tab-btn rounded-xl px-4 py-3 lg:py-3.5 text-sm lg:text-base font-semibold transition" data-auth-tab="signup">Sign up</button>
        </div>
      </div>

      <div class="p-2 sm:p-4 lg:p-6">
        <?php if ($error): ?>
          <div class="bg-red-500/10 border border-red-500/30 text-red-600 text-sm rounded-lg px-4 py-3 mb-5">
            <?= sanitize($error) ?>
          </div>
        <?php endif; ?>

        <div id="authPreStep" class="space-y-5">
          <div class="rounded-2xl bg-gradient-to-br from-emerald-50 to-white border border-slate-200 p-4 lg:p-6">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-emerald-700">Start here</p>
            <h2 class="mt-3 text-sm lg:text-base font-bold text-slate-900">Add your email, then choose if you're an existing member or creating a new account.</h2>
          </div>

          <div class="bg-white border border-slate-200 rounded-2xl p-4 sm:p-5 lg:p-6">
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5" for="community_email">Email address</label>
                <input id="community_email" type="email" autocomplete="email" class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent placeholder-slate-400" placeholder="you@example.com">
              </div>

              <button type="button" id="joinCommunityAction" class="w-full bg-[#2f7fe0] hover:bg-[#2968b5] text-white font-bold py-3 rounded-xl transition shadow-lg shadow-[#2f7fe0]/20 text-base">
                Join Community
              </button>
            </div>
          </div>
        </div>

        <div id="memberChoiceStep" class="hidden space-y-4">
          <div class="rounded-2xl bg-gradient-to-br from-sky-50 to-white border border-slate-200 p-4 lg:p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-sky-700">Continue as</p>
            <h3 class="mt-2 text-sm lg:text-base font-bold text-slate-900">We are ready for <span id="selectedCommunityEmailText" class="text-slate-900">your email</span>.</h3>
          </div>

          <div class="grid sm:grid-cols-2 gap-3">
            <button type="button" data-member-choice="signin" class="rounded-2xl border border-slate-200 bg-slate-100 px-4 py-4 text-left transition hover:border-[#2f7fe0] hover:bg-[#edf5ff]">
              <span class="block text-xs uppercase tracking-[0.2em] text-slate-500">Returning</span>
              <span class="mt-2 block text-lg font-bold text-slate-900">Old member</span>
            </button>
            <button type="button" data-member-choice="signup" class="rounded-2xl border border-slate-200 bg-slate-100 px-4 py-4 text-left transition hover:border-[#2f7fe0] hover:bg-[#edf5ff]">
              <span class="block text-xs uppercase tracking-[0.2em] text-slate-500">New</span>
              <span class="mt-2 block text-lg font-bold text-slate-900">New member</span>
            </button>
          </div>
        </div>

        <div id="authPanels">
          <div class="auth-panel hidden" data-auth-panel="signin">
            <div class="grid lg:grid-cols-2 gap-2 items-stretch">
              <div class="rounded-2xl bg-gradient-to-br from-emerald-50 to-white border border-slate-200 p-2 lg:p-6">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-emerald-700">Welcome back</p>
                <h2 class="mt-3 text-sm lg:text-base font-bold text-slate-900">Sign in to manage your trading, portfolios, and automation.</h2>
              </div>

              <div class="bg-white border border-slate-200 rounded-2xl p-2 sm:p-4 lg:p-6">
                <form method="POST" action="" class="space-y-4">
                  <?= csrf_field() ?>
                  <input type="hidden" name="auth_mode" value="signin">

                  <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5" for="signin_email">Email address</label>
                    <input id="signin_email" type="email" name="email" required autocomplete="email"
                      class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent placeholder-slate-400"
                      placeholder="you@example.com">
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5" for="signin_password">Password</label>
                    <input id="signin_password" type="password" name="password" required autocomplete="current-password"
                      class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent placeholder-slate-400"
                      placeholder="••••••••">
                  </div>

                  <div class="flex items-center justify-between text-sm">
                    <label class="flex items-center gap-2 text-slate-600 cursor-pointer">
                      <input type="checkbox" name="remember" class="w-4 h-4 accent-emerald-500">
                      Remember me
                    </label>
                    <a href="<?= htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/forgot_password.php', ENT_QUOTES, 'UTF-8') ?>" class="text-emerald-600 hover:text-emerald-500 transition">Forgot password?</a>
                  </div>

                  <button type="submit" class="w-full bg-[#2f7fe0] hover:bg-[#2968b5] text-white font-bold py-3 rounded-xl transition shadow-lg shadow-[#2f7fe0]/20 text-base">
                    Access Platform
                  </button>
                </form>
              </div>
            </div>
          </div>

          <div class="auth-panel hidden" data-auth-panel="signup">
            <div class="grid lg:grid-cols-2 gap-2 items-stretch">
              <div class="rounded-2xl bg-gradient-to-br from-sky-50 to-white border border-slate-200 p-2 lg:p-6">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-sky-700">Get started</p>
                <h2 class="mt-3 text-sm lg:text-base font-bold text-slate-900">Create your free account and begin trading with automation.</h2>
              </div>

              <div class="bg-white border border-slate-200 rounded-2xl p-2 sm:p-4 lg:p-6">

                <!-- Step 1: reCAPTCHA gate -->
                <div id="recaptchaGate" class="flex flex-col items-center justify-center py-8 gap-5">
                  <div class="text-center">
                    <p class="text-sm font-semibold text-slate-700 mb-1">First, verify you're human</p>
                    <p class="text-xs text-slate-400">Complete the check below to continue with registration</p>
                  </div>
                  <div class="g-recaptcha"
                    data-sitekey="<?= htmlspecialchars(env('RECAPTCHA_SITE_KEY', '6LcEq3gtAAAAAI7_4qLFkVUDlb4Kt1wTnS11BtVT'), ENT_QUOTES) ?>"
                    data-callback="onRecaptchaPassed"
                    data-expired-callback="onRecaptchaExpired">
                  </div>
                </div>

                <!-- Step 2: Registration form (hidden until reCAPTCHA passed) -->
                <form id="signupForm" method="POST" action="" class="space-y-4 hidden">
                  <?= csrf_field() ?>
                  <input type="hidden" name="auth_mode" value="signup">
                  <input type="hidden" name="g-recaptcha-response" id="signupRecaptchaToken" value="">

                  <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5" for="signup_name">Full name</label>
                    <input id="signup_name" type="text" name="name" required autocomplete="name"
                      class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent placeholder-slate-400"
                      placeholder="John Doe">
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5" for="signup_email">Email address</label>
                    <input id="signup_email" type="email" name="email" required autocomplete="email"
                      class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent placeholder-slate-400"
                      placeholder="you@example.com">
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5" for="signup_password">Password</label>
                    <input id="signup_password" type="password" name="password" required autocomplete="new-password"
                      class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent placeholder-slate-400"
                      placeholder="Min. 8 characters">
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5" for="signup_password_confirm">Confirm password</label>
                    <input id="signup_password_confirm" type="password" name="password_confirm" required autocomplete="new-password"
                      class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent placeholder-slate-400"
                      placeholder="Repeat password">
                  </div>

                  <!-- Verified badge -->
                  <div class="flex items-center gap-2 bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-2.5">
                    <svg class="w-4 h-4 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    <span class="text-xs text-emerald-700 font-medium">Human verification passed</span>
                  </div>

                  <button type="submit" class="w-full bg-[#2f7fe0] hover:bg-[#2968b5] text-white font-bold py-3 rounded-xl transition shadow-lg shadow-[#2f7fe0]/20 text-base">
                    Create Account
                  </button>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============================================================
     FOOTER
     ============================================================ -->
<footer class="border-t border-slate-200 bg-white py-3">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-center gap-3">
      <a href="index.php" class="flex-shrink-0">
        <span class="text-sm font-bold tracking-tight text-emerald-600">CBOE<span class="text-slate-900">Markets</span></span>
      </a>
      <!-- <span class="text-xs text-slate-400">&copy; <?= date('Y') ?> Cboemarkets. All rights reserved.</span> -->
    </div>
  </div>
</footer>

<script>
  const authChoiceKey = 'cboe_auth_choice';
  const savedAuthChoice = sessionStorage.getItem(authChoiceKey);
  const initialAuthTab = savedAuthChoice || null;

  function setAuthTab(tab) {
    if (!tab) {
      document.querySelectorAll('[data-auth-panel]').forEach((panel) => panel.classList.add('hidden'));
      document.querySelectorAll('.auth-tab-btn').forEach((button) => {
        button.classList.remove('bg-white', 'text-slate-900', 'shadow-sm');
        button.classList.add('text-slate-500');
      });
      return;
    }

    document.querySelectorAll('[data-auth-panel]').forEach((panel) => {
      panel.classList.toggle('hidden', panel.dataset.authPanel !== tab);
    });

    document.querySelectorAll('.auth-tab-btn').forEach((button) => {
      const isActive = button.dataset.authTab === tab;
      button.classList.toggle('bg-white', isActive);
      button.classList.toggle('text-slate-900', isActive);
      button.classList.toggle('shadow-sm', isActive);
      button.classList.toggle('text-slate-500', !isActive);
    });
  }

  const authPreStep = document.getElementById('authPreStep');
  const memberChoiceStep = document.getElementById('memberChoiceStep');
  const joinCommunityAction = document.getElementById('joinCommunityAction');
  const communityEmailInput = document.getElementById('community_email');
  const selectedCommunityEmailText = document.getElementById('selectedCommunityEmailText');

  function showAuthChoice(choice) {
    if (!choice) {
      return;
    }

    sessionStorage.setItem(authChoiceKey, choice);
    setAuthTab(choice);
    memberChoiceStep.classList.add('hidden');
    authPreStep.classList.add('hidden');

    if (choice === 'signin') {
      const emailField = document.getElementById('signin_email');
      if (emailField) emailField.value = communityEmailInput.value.trim();
    }

    if (choice === 'signup') {
      const emailField = document.getElementById('signup_email');
      if (emailField) emailField.value = communityEmailInput.value.trim();
    }
  }

  function startCommunityFlow() {
    const email = (communityEmailInput.value || '').trim();
    if (!email || !/^\S+@\S+\.\S+$/.test(email)) {
      communityEmailInput.focus();
      return;
    }

    selectedCommunityEmailText.textContent = email;
    authPreStep.classList.add('hidden');
    memberChoiceStep.classList.remove('hidden');
    document.querySelectorAll('[data-auth-panel]').forEach((panel) => panel.classList.add('hidden'));
  }

  if (joinCommunityAction) {
    joinCommunityAction.addEventListener('click', startCommunityFlow);
  }

  communityEmailInput.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      startCommunityFlow();
    }
  });

  document.querySelectorAll('[data-member-choice]').forEach((button) => {
    button.addEventListener('click', () => {
      const choice = button.dataset.memberChoice;
      showAuthChoice(choice);
    });
  });

  document.querySelectorAll('.auth-tab-btn').forEach((button) => {
    button.addEventListener('click', () => {
      if (!authPreStep.classList.contains('hidden') || !memberChoiceStep.classList.contains('hidden')) {
        return;
      }
      const selectedTab = button.dataset.authTab;
      if (selectedTab) {
        sessionStorage.setItem(authChoiceKey, selectedTab);
      }
      setAuthTab(selectedTab);
    });
  });

  authPreStep.classList.remove('hidden');
  memberChoiceStep.classList.add('hidden');
  document.querySelectorAll('[data-auth-panel]').forEach((panel) => panel.classList.add('hidden'));

  if (initialAuthTab) {
    authPreStep.classList.add('hidden');
    setAuthTab(initialAuthTab);
  }

  function onRecaptchaPassed(token) {
    document.getElementById('signupRecaptchaToken').value = token || '';
    document.getElementById('recaptchaGate').classList.add('hidden');
    document.getElementById('signupForm').classList.remove('hidden');
  }

  function onRecaptchaExpired() {
    document.getElementById('signupRecaptchaToken').value = '';
    document.getElementById('signupForm').classList.add('hidden');
    document.getElementById('recaptchaGate').classList.remove('hidden');
  }

  document.getElementById('mobileMenuBtn').addEventListener('click', () => {
    document.getElementById('mobileMenu').classList.toggle('hidden');
  });
</script>

<!-- Smartsupp Live Chat -->
<script type="text/javascript">
var _smartsupp = _smartsupp || {};
_smartsupp.key = '974526ed39790a589cf4d6ee38fc45e6e627627d';
if (window.innerWidth < 768) {
  _smartsupp.offsetY = 80;
}
window.smartsupp||(function(d) {
  var s,c,o=smartsupp=function(){ o._.push(arguments)};o._=[];
  s=d.getElementsByTagName('script')[0];c=d.createElement('script');
  c.type='text/javascript';c.charset='utf-8';c.async=true;
  c.src='https://www.smartsuppchat.com/loader.js?';s.parentNode.insertBefore(c,s);
})(document);
</script>
<noscript>Powered by <a href="https://www.smartsupp.com" target="_blank">Smartsupp</a></noscript>
</body>
</html>
