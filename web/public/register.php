<?php
declare(strict_types=1);
header("location: index.php");
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/csrf.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/email.php';

if (is_logged_in()) {
    redirect(app_dashboard_url());
}

$error   = get_flash('error');
$success = get_flash('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    // Validation
    if ($name === '' || $email === '' || $password === '') {
        flash('error', 'All fields are required.');
        redirect('register.php');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Please enter a valid email address.');
        redirect('register.php');
    }

    if (strlen($password) < 8) {
        flash('error', 'Password must be at least 8 characters long.');
        redirect('register.php');
    }

    if ($password !== $confirm) {
        flash('error', 'Passwords do not match.');
        redirect('register.php');
    }

    try {
        $pdo = db();

        // Check for duplicate email
        $check = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $check->execute([$email]);
        if ($check->fetch()) {
            flash('error', 'An account with that email already exists.');
            redirect('register.php');
        }

        $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt   = $pdo->prepare(
            'INSERT INTO users (name, email, password, role, status, balance) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$name, $email, $hashed, 'user', 'active', 0.0]);
        $userId = (int) $pdo->lastInsertId();

        // Auto-login
        $user = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $user->execute([$userId]);
        login_user($user->fetch());

        // Send welcome email (non-fatal)
        try {
            send_welcome_email($email, $name);
        } catch (Throwable $emailErr) {
            error_log('[register] Welcome email failed: ' . $emailErr->getMessage());
        }

        redirect(app_dashboard_url());
    } catch (Throwable $e) {
        flash('error', 'Registration failed. Please try again.');
        redirect('register.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/images/favicon.png">
  <title>Create Account – CBOE Markets</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 min-h-screen flex items-center justify-center">

  <div class="w-full max-w-md">
    <div class="text-center mb-8">
      <a href="index.php" class="text-3xl font-extrabold text-emerald-400">Cboemarkets</a>
      <p class="text-slate-400 mt-2">Create your free account</p>
    </div>

    <div class="bg-slate-800 border border-slate-700 rounded-2xl p-8 shadow-xl">

      <?php if ($error): ?>
        <div class="bg-red-500/10 border border-red-500/30 text-red-400 text-sm rounded-lg px-4 py-3 mb-6">
          <?= sanitize($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="register.php" class="space-y-5">
        <?= csrf_field() ?>

        <div>
          <label class="block text-sm font-medium text-slate-300 mb-1.5" for="name">Full name</label>
          <input id="name" type="text" name="name" required autocomplete="name"
            class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent placeholder-slate-500"
            placeholder="John Doe">
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-300 mb-1.5" for="email">Email address</label>
          <input id="email" type="email" name="email" required autocomplete="email"
            class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent placeholder-slate-500"
            placeholder="you@example.com">
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-300 mb-1.5" for="password">Password</label>
          <input id="password" type="password" name="password" required autocomplete="new-password"
            class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent placeholder-slate-500"
            placeholder="Min. 8 characters">
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-300 mb-1.5" for="password_confirm">Confirm password</label>
          <input id="password_confirm" type="password" name="password_confirm" required autocomplete="new-password"
            class="w-full bg-slate-700 border border-slate-600 text-white rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent placeholder-slate-500"
            placeholder="Repeat password">
        </div>

        <button type="submit"
          class="w-full bg-emerald-500 hover:bg-emerald-400 text-white font-bold py-3 rounded-xl transition shadow-lg shadow-emerald-500/20 text-base">
          Create Account
        </button>
      </form>

      <p class="text-center text-slate-400 text-sm mt-6">
        Already have an account?
        <a href="<?= htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/login.php', ENT_QUOTES, 'UTF-8') ?>" class="text-emerald-400 hover:text-emerald-300 transition font-medium">Sign in</a>
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
