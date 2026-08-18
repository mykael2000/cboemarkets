<?php
declare(strict_types=1);
header('Location: /web/public/index.php');
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/csrf.php';
require_once __DIR__ . '/../src/helpers.php';

// Already logged in → go to dashboard
$publicBase = '/web/public';

if (is_logged_in()) {
    redirect($publicBase . '/app/index.php');
}

$error = get_flash('error');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $remember = !empty($_POST['remember']);

    if ($email === '' || $password === '') {
        flash('error', 'Email and password are required.');
        redirect($publicBase . '/login.php');
    }

    try {
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            flash('error', 'Invalid email or password.');
            redirect($publicBase . '/login.php');
        }

        if ($user['status'] === 'disabled') {
            flash('error', 'Your account has been disabled. Please contact support.');
            redirect($publicBase . '/login.php');
        }

        if (!$user['email_verified']) {
            require_once __DIR__ . '/../src/email.php';
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
        require_once __DIR__ . '/../src/email.php';
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
        redirect($publicBase . '/login.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/images/favicon.png">
  <title>Login – CBOE Markets</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 min-h-screen flex items-center justify-center p-4">

  <div class="w-full max-w-md">
    <!-- Logo -->
    <div class="text-center mb-8">
      <a href="<?= htmlspecialchars($publicBase . '/index.php', ENT_QUOTES, 'UTF-8') ?>" class="text-3xl font-extrabold text-emerald-400">Cboemarkets</a>
      <p class="text-slate-400 mt-2">Sign in to your account</p>
    </div>

    <!-- Card -->
    <div class="bg-slate-800 border border-slate-700 rounded-2xl p-8 shadow-xl">

      <?php if ($error): ?>
        <div class="bg-red-500/10 border border-red-500/30 text-red-400 text-sm rounded-lg px-4 py-3 mb-6">
          <?= sanitize($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="<?= htmlspecialchars($publicBase . '/login.php', ENT_QUOTES, 'UTF-8') ?>" class="space-y-5">
        <?= csrf_field() ?>

        <div>
          <label class="block text-sm font-medium text-slate-300 mb-1.5" for="email">Email address</label>
          <input id="email" type="email" name="email" required autocomplete="email"
            class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent placeholder-slate-500"
            placeholder="you@example.com">
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-300 mb-1.5" for="password">Password</label>
          <input id="password" type="password" name="password" required autocomplete="current-password"
            class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent placeholder-slate-500"
            placeholder="••••••••">
        </div>

        <div class="flex items-center justify-between text-sm">
          <label class="flex items-center gap-2 text-slate-400 cursor-pointer">
            <input type="checkbox" name="remember" class="w-4 h-4 accent-emerald-500">
            Remember me
          </label>
          <a href="<?= htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/forgot_password.php', ENT_QUOTES, 'UTF-8') ?>" class="text-emerald-400 hover:text-emerald-300 transition">Forgot password?</a>
        </div>

        <button type="submit"
          class="w-full bg-emerald-500 hover:bg-emerald-400 text-white font-bold py-3 rounded-xl transition shadow-lg shadow-emerald-500/20 text-base">
          Sign In
        </button>
      </form>

      <p class="text-center text-slate-400 text-sm mt-6">
        Don't have an account?
        <a href="<?= htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/join_community.php', ENT_QUOTES, 'UTF-8') ?>" class="text-emerald-400 hover:text-emerald-300 transition font-medium">Join community first</a>
      </p>
    </div>
  </div>


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
