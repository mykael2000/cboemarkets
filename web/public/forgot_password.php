<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/csrf.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/email.php';

if (is_logged_in()) {
    redirect('/app/index');
}

$error   = get_flash('error');
$success = get_flash('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Please enter a valid email address.');
        redirect('/forgot_password.php');
    }

    try {
      $pdo  = db();
      $stmt = $pdo->prepare('SELECT id, name FROM users WHERE email = ? AND status = ? LIMIT 1');
      $stmt->execute([$email, 'active']);
      $user = $stmt->fetch();

      // Always show success to avoid email enumeration
      if ($user) {
        // Generate a 6-digit numeric code for password reset
        $code      = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $codeHash  = password_hash($code, PASSWORD_BCRYPT);
        $expiresAt = date('Y-m-d H:i:s', time() + 600); // 10 minutes

        $ins = $pdo->prepare(
          'INSERT INTO user_security_otps (user_id, purpose, code_hash, expires_at) VALUES (?, ?, ?, ?)'
        );
        $ins->execute([$user['id'], 'password_reset', $codeHash, $expiresAt]);

        // Send OTP email (do not expose failures to the user)
        try {
          $dbg  = null;
          $sent = send_security_otp_email($email, $user['name'] ?? $email, $code, 'password reset', $dbg);
          if (!$sent) {
            $logDir = WEB_ROOT . '/logs'; if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
            $msg = sprintf("[%s] forgot_password send failed for user_id=%s email=%s debug=%s\n", date('c'), $user['id'], $email, $dbg ?? 'no debug');
            error_log($msg, 3, $logDir . '/email_debug.log');
          }
        } catch (Throwable $e) {
          // ignore send failures but log
          $logDir = WEB_ROOT . '/logs'; if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
          $msg = sprintf("[%s] forgot_password send exception for user_id=%s email=%s err=%s\n", date('c'), $user['id'], $email, $e->getMessage());
          error_log($msg, 3, $logDir . '/email_debug.log');
        }
      }

      flash('success', 'If that email exists in our system, a reset code has been sent. Use it on the reset page.');
      redirect('/reset_password.php?email=' . urlencode($email));
    } catch (Throwable $e) {
      flash('error', 'A system error occurred. Please try again.');
      redirect('/forgot_password.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/images/favicon.png">
  <title>Forgot Password – CBOE Markets</title>
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
      <a href="login.php" class="hover:text-slate-900 transition">Login</a>
      <a href="register.php" class="bg-emerald-500 hover:bg-emerald-400 text-white px-4 py-2 rounded-lg transition font-semibold shadow-sm">Get Started</a>
    </nav>
    <button id="mobileMenuBtn" class="md:hidden text-slate-700 hover:text-slate-900 focus:outline-none">
      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
      </svg>
    </button>
  </div>
  <div id="mobileMenu" class="hidden md:hidden bg-white border-t border-slate-200 px-4 py-3 space-y-2 shadow-sm">
    <a href="login.php" class="block text-slate-600 hover:text-slate-900 py-1">Login</a>
    <a href="register.php" class="block bg-emerald-500 text-white px-4 py-2 rounded-lg text-center font-semibold">Get Started</a>
  </div>
</header>

<!-- MAIN -->
<section class="py-3 md:py-4 lg:py-5 min-h-[calc(100vh-8.5rem)] flex items-center">
  <div class="w-full max-w-6xl mx-auto px-4 sm:px-6">
    <div class="bg-white border border-slate-200 rounded-3xl shadow-xl shadow-slate-200/60 overflow-hidden">
      <div class="p-4 sm:p-6 lg:p-7 border-b border-slate-200 bg-slate-50/80">
        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-emerald-700">Account recovery</p>
        <h1 class="mt-2 text-sm lg:text-base font-bold text-slate-900">Reset your password via email.</h1>
      </div>

      <div class="p-4 sm:p-6 lg:p-8">
        <?php if ($error): ?>
          <div class="bg-red-500/10 border border-red-500/30 text-red-600 text-sm rounded-lg px-4 py-3 mb-6">
            <?= sanitize($error) ?>
          </div>
        <?php endif; ?>
        <?php if ($success): ?>
          <div class="bg-emerald-500/10 border border-emerald-200 text-emerald-700 text-sm rounded-lg px-4 py-3 mb-6">
            <?= sanitize($success) ?>
          </div>
        <?php endif; ?>

        <div class="grid lg:grid-cols-2 gap-4 items-stretch">
          <div class="rounded-2xl bg-gradient-to-br from-emerald-50 to-white border border-slate-200 p-6">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-emerald-700">Forgot password?</p>
            <h2 class="mt-3 text-sm lg:text-base font-bold text-slate-900">Enter your email and we'll send you a secure reset link.</h2>
          </div>

          <div class="bg-white border border-slate-200 rounded-2xl p-4 sm:p-6">
            <form method="POST" action="/forgot_password.php" class="space-y-4">
              <?= csrf_field() ?>

              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5" for="email">Email address</label>
                <input id="email" type="email" name="email" required autocomplete="email"
                  class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent placeholder-slate-400"
                  placeholder="you@example.com">
              </div>

              <button type="submit"
                class="w-full bg-emerald-500 hover:bg-emerald-400 text-white font-bold py-3 rounded-xl transition shadow-lg shadow-emerald-500/20 text-base">
                Send Reset Link
              </button>

              <p class="text-center text-sm text-slate-500">
                <a href="login.php" class="text-emerald-600 hover:text-emerald-500 transition">&larr; Back to Login</a>
              </p>
            </form>
          </div>
        </div>
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
