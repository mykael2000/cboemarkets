<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/csrf.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/email.php';

$publicBase = '/web/public';

if (is_logged_in()) {
    redirect($publicBase . '/app/index.php');
}

$tokenError = null;

// ── Auto-verify via URL token ───────────────────────────────────────────────
if (isset($_GET['token'])) {
    $tok = trim($_GET['token']);
    try {
        $stmt = db()->prepare(
            'SELECT * FROM users WHERE email_verify_token = ? AND email_verify_expires > NOW() LIMIT 1'
        );
        $stmt->execute([$tok]);
        $u = $stmt->fetch();
        if ($u) {
            db()->prepare(
                'UPDATE users SET email_verified=1, email_verify_code=NULL, email_verify_token=NULL, email_verify_expires=NULL WHERE id=?'
            )->execute([$u['id']]);
            login_user($u);
            unset($_SESSION['pending_verify_user_id']);
            redirect($publicBase . '/app/index.php');
        } else {
            $tokenError = 'This confirmation link is invalid or has expired.';
        }
    } catch (Throwable $e) {
        $tokenError = 'Verification failed. Please try again.';
    }
}

// ── Require pending session ─────────────────────────────────────────────────
$userId = $_SESSION['pending_verify_user_id'] ?? null;
if (!$userId && !$tokenError) {
    redirect($publicBase . '/index.php');
}

$pageUser = null;
if ($userId) {
    try {
        $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $pageUser = $stmt->fetch() ?: null;
        if (!$pageUser || $pageUser['email_verified']) {
            unset($_SESSION['pending_verify_user_id']);
            redirect($publicBase . '/index.php');
        }
    } catch (Throwable $e) {
        redirect($publicBase . '/index.php');
    }
}

$error   = get_flash('error');
$success = get_flash('success');

// ── Form handling ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (!$pageUser) {
        redirect($publicBase . '/index.php');
    }

    $action = $_POST['action'] ?? 'verify';

    // Resend code
    if ($action === 'resend') {
        try {
            $code    = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $newTok  = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            db()->prepare(
                'UPDATE users SET email_verify_code=?, email_verify_token=?, email_verify_expires=? WHERE id=?'
            )->execute([$code, $newTok, $expires, $userId]);
            send_verification_email($pageUser['email'], $pageUser['name'], $code, $newTok);
            flash('success', 'A new code has been sent to your email.');
        } catch (Throwable $e) {
            flash('error', 'Failed to resend. Please try again.');
        }
        redirect($publicBase . '/verify_email.php');
    }

    // Verify code
    $inputCode = preg_replace('/\D/', '', trim($_POST['code'] ?? ''));
    if (strlen($inputCode) !== 6) {
        flash('error', 'Please enter the 6-digit code.');
        redirect($publicBase . '/verify_email.php');
    }

    try {
        $stmt = db()->prepare(
            'SELECT * FROM users WHERE id = ? AND email_verify_code = ? AND email_verify_expires > NOW() LIMIT 1'
        );
        $stmt->execute([$userId, $inputCode]);
        $confirmed = $stmt->fetch();
        if ($confirmed) {
            db()->prepare(
            'UPDATE users SET status="active", email_verified=1, email_verify_code=NULL, email_verify_token=NULL, email_verify_expires=NULL WHERE id=?'
            )->execute([$userId]);
            login_user($confirmed);
            unset($_SESSION['pending_verify_user_id']);
            redirect($publicBase . '/app/index.php');
        } else {
            flash('error', 'Invalid or expired code. Please try again.');
            redirect($publicBase . '/verify_email.php');
        }
    } catch (Throwable $e) {
        flash('error', 'Verification failed. Please try again.');
        redirect($publicBase . '/verify_email.php');
    }
}

// Mask email for display
$maskedEmail = '';
if ($pageUser) {
    [$local, $domain] = explode('@', $pageUser['email'], 2);
    $maskedEmail = substr($local, 0, 2) . str_repeat('*', max(strlen($local) - 2, 3)) . '@' . $domain;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/images/favicon.png">
  <title>Verify Email – CBOE Markets</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-white text-slate-900 antialiased min-h-screen flex flex-col items-center justify-center p-4">

  <div class="w-full max-w-md">
    <!-- Logo -->
    <div class="text-center mb-8">
      <a href="<?= htmlspecialchars($publicBase . '/index.php', ENT_QUOTES, 'UTF-8') ?>">
        <span class="text-2xl font-extrabold tracking-tight text-emerald-600">CBOE<span class="text-slate-900">Markets</span></span>
      </a>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl shadow-xl p-8">

      <!-- Icon -->
      <div class="flex justify-center mb-5">
        <div class="w-14 h-14 rounded-full bg-emerald-50 border border-emerald-100 flex items-center justify-center">
          <svg class="w-7 h-7 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
          </svg>
        </div>
      </div>

      <?php if ($tokenError): ?>
        <!-- Expired / invalid token state -->
        <div class="text-center">
          <h1 class="text-xl font-bold text-slate-900 mb-2">Link Expired</h1>
          <p class="text-slate-500 text-sm mb-6"><?= sanitize($tokenError) ?></p>
          <a href="<?= htmlspecialchars($publicBase . '/index.php', ENT_QUOTES, 'UTF-8') ?>" class="text-sm text-emerald-600 hover:underline">← Back to sign in</a>
        </div>

      <?php else: ?>
        <h1 class="text-xl font-bold text-slate-900 text-center mb-1">Check your email</h1>
        <?php if ($maskedEmail): ?>
          <p class="text-slate-500 text-sm text-center mb-6">
            We sent a 6-digit code to <span class="font-medium text-slate-700"><?= sanitize($maskedEmail) ?></span>
          </p>
        <?php endif; ?>

        <?php if ($error): ?>
          <div class="bg-red-500/10 border border-red-500/30 text-red-600 text-sm rounded-lg px-4 py-3 mb-4">
            <?= sanitize($error) ?>
          </div>
        <?php endif; ?>

        <?php if ($success): ?>
          <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 text-sm rounded-lg px-4 py-3 mb-4">
            <?= sanitize($success) ?>
          </div>
        <?php endif; ?>

        <!-- Code entry -->
        <form method="POST" action="" class="space-y-4">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="verify">

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1.5" for="verify_code">Verification code</label>
            <input id="verify_code" type="text" name="code" required
              maxlength="6" inputmode="numeric" autocomplete="one-time-code"
              class="w-full bg-white border border-slate-300 text-slate-900 rounded-xl px-4 py-4 text-center text-3xl font-bold tracking-[0.6em] focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent placeholder-slate-300"
              placeholder="──────">
          </div>

          <button type="submit"
            class="w-full bg-emerald-500 hover:bg-emerald-400 text-white font-bold py-3 rounded-xl transition shadow-lg shadow-emerald-500/20 text-base">
            Confirm email
          </button>
        </form>

        <!-- Resend -->
        <form method="POST" action="" class="mt-4 text-center">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="resend">
          <p class="text-sm text-slate-500">
            Didn't receive it?
            <button type="submit" class="text-emerald-600 hover:underline font-medium">Resend code</button>
          </p>
        </form>

        <p class="mt-5 text-center text-xs text-slate-400 leading-relaxed">
          Code valid for 30 minutes. Do not share it with anybody,<br>including 3Commas team members.
        </p>
      <?php endif; ?>
    </div>

    <p class="mt-6 text-center text-sm text-slate-500">
      <a href="index.php" class="text-emerald-600 hover:underline">← Back to sign in</a>
    </p>
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
