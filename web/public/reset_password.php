<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/csrf.php';
require_once __DIR__ . '/../src/helpers.php';

$publicBase = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
$loginHref = $publicBase . '/login.php';
$registerHref = $publicBase . '/register.php';

$token   = trim($_GET['token'] ?? '');
$email   = trim($_GET['email'] ?? '');
$error   = get_flash('error');
$success = get_flash('success');
$valid   = false;
$reset   = null;

try {
  $pdo = db();

  if ($token !== '') {
    // Existing token-based flow
    $stmt = $pdo->prepare(
      'SELECT * FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW() LIMIT 1'
    );
    $stmt->execute([$token]);
    $reset = $stmt->fetch();
    $valid = (bool) $reset;
  } elseif ($email !== '') {
    // Code-based flow: ensure email corresponds to an active user
    $uStmt = $pdo->prepare('SELECT id, name, email FROM users WHERE email = ? AND status = ? LIMIT 1');
    $uStmt->execute([$email, 'active']);
    $user = $uStmt->fetch();
    if ($user) {
      $valid = true;
      $reset = ['email' => $user['email'], 'user_id' => $user['id'], 'name' => $user['name'] ?? null];
    } else {
      $valid = false;
    }
  }
} catch (Throwable) {
  $valid = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();

  // Token-based submission
  if ($token !== '') {
    if (!$valid) {
      flash('error', 'This reset link is invalid or has expired.');
      redirect($publicBase . '/forgot_password.php');
    }

    $newPass = $_POST['password']         ?? '';
    $confirm = $_POST['password_confirm'] ?? '';

    if (strlen($newPass) < 8) {
      flash('error', 'Password must be at least 8 characters.');
      redirect($publicBase . '/reset_password.php?token=' . urlencode($token));
    }

    if ($newPass !== $confirm) {
      flash('error', 'Passwords do not match.');
      redirect($publicBase . '/reset_password.php?token=' . urlencode($token));
    }

    try {
      $pdo    = db();
      $hashed = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);

      $upd = $pdo->prepare('UPDATE users SET password = ? WHERE email = ?');
      $upd->execute([$hashed, $reset['email']]);

      $mark = $pdo->prepare('UPDATE password_resets SET used = 1 WHERE token = ?');
      $mark->execute([$token]);

      flash('success', 'Password updated successfully! Please log in.');
      redirect($publicBase . '/login.php');
    } catch (Throwable) {
      flash('error', 'A system error occurred. Please try again.');
      redirect($publicBase . '/reset_password.php?token=' . urlencode($token));
    }

  } elseif ($email !== '') {
    // Code-based submission
    $code    = trim($_POST['code'] ?? '');
    $newPass = $_POST['password']         ?? '';
    $confirm = $_POST['password_confirm'] ?? '';

    if (strlen($newPass) < 8) {
      flash('error', 'Password must be at least 8 characters.');
      redirect($publicBase . '/reset_password.php?email=' . urlencode($email));
    }
    if ($newPass !== $confirm) {
      flash('error', 'Passwords do not match.');
      redirect($publicBase . '/reset_password.php?email=' . urlencode($email));
    }

    try {
      $pdo = db();
      $uStmt = $pdo->prepare('SELECT id, email FROM users WHERE email = ? AND status = ? LIMIT 1');
      $uStmt->execute([$email, 'active']);
      $user = $uStmt->fetch();
      if (!$user) {
        flash('error', 'Invalid or expired reset code.');
        redirect($publicBase . '/forgot_password.php');
      }

      $otpStmt = $pdo->prepare('SELECT * FROM user_security_otps WHERE user_id = ? AND purpose = ? AND used = 0 AND expires_at > NOW() ORDER BY created_at DESC');
      $otpStmt->execute([$user['id'], 'password_reset']);
      $rows = $otpStmt->fetchAll();

      $match = null;
      foreach ($rows as $r) {
        if (password_verify($code, $r['code_hash'])) {
          $match = $r;
          break;
        }
      }

      if (!$match) {
        flash('error', 'Invalid or expired reset code.');
        redirect($publicBase . '/reset_password.php?email=' . urlencode($email));
      }

      $hashed = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
      $upd = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
      $upd->execute([$hashed, $user['id']]);

      // mark this OTP used
      $markOtp = $pdo->prepare('UPDATE user_security_otps SET used = 1 WHERE id = ?');
      $markOtp->execute([$match['id']]);

      // also mark any token-based resets for this email used
      $markToken = $pdo->prepare('UPDATE password_resets SET used = 1 WHERE email = ?');
      $markToken->execute([$user['email']]);

      flash('success', 'Password updated successfully! Please log in.');
      redirect($publicBase . '/login.php');
    } catch (Throwable $e) {
      flash('error', 'A system error occurred. Please try again.');
      redirect($publicBase . '/reset_password.php?email=' . urlencode($email));
    }
  } else {
    flash('error', 'Invalid request.');
    redirect($publicBase . '/forgot_password.php');
  }
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/images/favicon.png">
  <title>Reset Password – CBOE Markets</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-white text-slate-900 antialiased min-h-screen flex flex-col">

<!-- NAVBAR -->
<header class="sticky top-0 z-50 bg-white/95 backdrop-blur border-b border-slate-200">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16">
    <a href="index.php" class="flex items-center">
      <span class="text-2xl font-extrabold tracking-tight text-emerald-600">CBOE<span class="text-slate-900">Markets</span></span>
    </a>
    <nav class="hidden md:flex items-center gap-6 text-sm font-medium text-slate-600">
      <a href="<?= htmlspecialchars($loginHref, ENT_QUOTES, 'UTF-8') ?>" class="hover:text-slate-900 transition">Login</a>
      <a href="<?= htmlspecialchars($registerHref, ENT_QUOTES, 'UTF-8') ?>" class="bg-emerald-500 hover:bg-emerald-400 text-white px-4 py-2 rounded-lg transition font-semibold shadow-sm">Get Started</a>
    </nav>
    <button id="mobileMenuBtn" class="md:hidden text-slate-700 hover:text-slate-900 focus:outline-none">
      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
      </svg>
    </button>
  </div>
  <div id="mobileMenu" class="hidden md:hidden bg-white border-t border-slate-200 px-4 py-3 space-y-2 shadow-sm">
    <a href="<?= htmlspecialchars($loginHref, ENT_QUOTES, 'UTF-8') ?>" class="block text-slate-600 hover:text-slate-900 py-1">Login</a>
    <a href="<?= htmlspecialchars($registerHref, ENT_QUOTES, 'UTF-8') ?>" class="block bg-emerald-500 text-white px-4 py-2 rounded-lg text-center font-semibold">Get Started</a>
  </div>
</header>

<!-- MAIN -->
<section class="py-3 md:py-4 lg:py-5 min-h-[calc(100vh-8.5rem)] flex items-center">
  <div class="w-full max-w-6xl mx-auto px-4 sm:px-6">
    <div class="bg-white border border-slate-200 rounded-3xl shadow-xl shadow-slate-200/60 overflow-hidden">
      <div class="p-4 sm:p-6 lg:p-7 border-b border-slate-200 bg-slate-50/80">
        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-emerald-700">Security</p>
        <h1 class="mt-2 text-sm lg:text-base font-bold text-slate-900">Set a new password for your account.</h1>
      </div>

      <div class="p-4 sm:p-6 lg:p-8">
        <?php if ($error): ?>
          <div class="bg-red-500/10 border border-red-500/30 text-red-600 text-sm rounded-lg px-4 py-3 mb-6">
            <?= sanitize($error) ?>
          </div>
        <?php endif; ?>

        <?php if (!$valid): ?>
          <div class="grid lg:grid-cols-2 gap-4 items-stretch">
            <div class="rounded-2xl bg-gradient-to-br from-red-50 to-white border border-slate-200 p-6">
              <p class="text-xs font-semibold uppercase tracking-[0.24em] text-red-600">Link expired</p>
              <h2 class="mt-3 text-sm lg:text-base font-bold text-slate-900">This reset link is invalid or has expired.</h2>
            </div>
            <div class="bg-white border border-slate-200 rounded-2xl p-6 flex flex-col justify-center gap-4">
              <p class="text-slate-600 text-sm">Please request a new password reset link to continue.</p>
              <a href="<?= htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/forgot_password.php', ENT_QUOTES, 'UTF-8') ?>"
                class="inline-block w-full text-center bg-emerald-500 hover:bg-emerald-400 text-white font-bold py-3 rounded-xl transition shadow-lg shadow-emerald-500/20 text-base">
                Request New Link
              </a>
              <a href="<?= htmlspecialchars($loginHref, ENT_QUOTES, 'UTF-8') ?>" class="text-center text-sm text-emerald-600 hover:text-emerald-500 transition">&larr; Back to Login</a>
            </div>
          </div>
        <?php else: ?>
          <div class="grid lg:grid-cols-2 gap-4 items-stretch">
            <div class="rounded-2xl bg-gradient-to-br from-emerald-50 to-white border border-slate-200 p-6">
              <p class="text-xs font-semibold uppercase tracking-[0.24em] text-emerald-700">Almost there</p>
              <h2 class="mt-3 text-sm lg:text-base font-bold text-slate-900">Choose a strong password to keep your account secure.</h2>
            </div>
            <div class="bg-white border border-slate-200 rounded-2xl p-4 sm:p-6">
              <?php if ($token !== ''): ?>
                <form method="POST" action="/reset_password.php?token=<?= urlencode($token) ?>" class="space-y-4">
                  <?= csrf_field() ?>

                  <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5" for="password">New password</label>
                    <input id="password" type="password" name="password" required autocomplete="new-password"
                      class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent placeholder-slate-400"
                      placeholder="Min. 8 characters">
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5" for="password_confirm">Confirm new password</label>
                    <input id="password_confirm" type="password" name="password_confirm" required autocomplete="new-password"
                      class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent placeholder-slate-400"
                      placeholder="Repeat new password">
                  </div>

                  <button type="submit"
                    class="w-full bg-emerald-500 hover:bg-emerald-400 text-white font-bold py-3 rounded-xl transition shadow-lg shadow-emerald-500/20 text-base">
                    Update Password
                  </button>

                  <p class="text-center text-sm text-slate-500">
                    <a href="<?= htmlspecialchars($loginHref, ENT_QUOTES, 'UTF-8') ?>" class="text-emerald-600 hover:text-emerald-500 transition">&larr; Back to Login</a>
                  </p>
                </form>
              <?php else: ?>
                <form method="POST" action="/reset_password.php?email=<?= urlencode($email) ?>" class="space-y-4">
                  <?= csrf_field() ?>

                  <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5" for="code">Reset code</label>
                    <input id="code" type="text" name="code" required autocomplete="off"
                      class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent placeholder-slate-400"
                      placeholder="Enter the 6-digit code from your email">
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5" for="password">New password</label>
                    <input id="password" type="password" name="password" required autocomplete="new-password"
                      class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent placeholder-slate-400"
                      placeholder="Min. 8 characters">
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5" for="password_confirm">Confirm new password</label>
                    <input id="password_confirm" type="password" name="password_confirm" required autocomplete="new-password"
                      class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent placeholder-slate-400"
                      placeholder="Repeat new password">
                  </div>

                  <button type="submit"
                    class="w-full bg-emerald-500 hover:bg-emerald-400 text-white font-bold py-3 rounded-xl transition shadow-lg shadow-emerald-500/20 text-base">
                    Update Password
                  </button>

                  <p class="text-center text-sm text-slate-500">
                    <a href="<?= htmlspecialchars($loginHref, ENT_QUOTES, 'UTF-8') ?>" class="text-emerald-600 hover:text-emerald-500 transition">&larr; Back to Login</a>
                  </p>
                </form>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer class="border-t border-slate-200 bg-white py-3">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-center gap-3">
      <a href="index.php" class="flex-shrink-0">
        <span class="text-sm font-bold tracking-tight text-emerald-600">CBOE<span class="text-slate-900">Markets</span></span>
      </a>
    </div>
  </div>
</footer>

<script>
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
