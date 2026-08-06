<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/csrf.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/email.php';

$success = get_flash('success');
$error = get_flash('error');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim($_POST['email'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Please enter a valid email address.');
        redirect('join_community.php');
    }

    $subject = 'Welcome to the CBOE Markets community';
    $body = <<<HTML
<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#07111f;color:#f8fafc;padding:32px;border-radius:12px;">
  <h2 style="color:#4dd3ff;margin-bottom:8px;">You're on the list</h2>
  <p>Thanks for joining the CBOE Markets community.</p>
  <p>We will keep you updated with market insights, product news, and community invites.</p>
  <p style="margin-top:18px;">If you already have an account, you can sign in and continue from the dashboard.</p>
</div>
HTML;

    $debug = null;
    $sent = send_email($email, $subject, $body, $debug);
    if (!$sent) {
        flash('error', 'We could not send the welcome email right now. Please try again later.');
        redirect('join_community.php');
    }

    flash('success', 'Thanks! Please continue to create an account or sign in.');
    redirect('join_community.php?joined=1');
}

$joined = isset($_GET['joined']) && $_GET['joined'] === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Join Community – CBOE Markets</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
  <div class="min-h-screen flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-2xl rounded-3xl border border-slate-800 bg-slate-900/90 shadow-2xl shadow-slate-950/40 overflow-hidden">
      <div class="bg-gradient-to-r from-[#0f2f5c] to-[#2f7fe0] px-8 py-8">
        <p class="text-sm uppercase tracking-[0.35em] text-slate-200/80">Community access</p>
        <h1 class="mt-3 text-3xl font-bold text-white">Join the CBOE Markets community first</h1>
        <p class="mt-3 text-sm text-slate-200">Enter your email and we will send you a welcome note. After that you can continue to sign in or create an account.</p>
      </div>
      <div class="p-8 space-y-6">
        <?php if ($error): ?>
          <div class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300"><?= sanitize($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
          <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300"><?= sanitize($success) ?></div>
        <?php endif; ?>

        <?php if ($joined): ?>
          <div class="rounded-2xl border border-sky-500/30 bg-sky-500/10 p-5">
            <h2 class="text-lg font-semibold text-sky-200">Choose your next step</h2>
            <div class="mt-4 flex flex-col sm:flex-row gap-3">
              <a href="signin.php" class="inline-flex justify-center rounded-xl bg-[#2f7fe0] px-4 py-3 text-sm font-semibold text-white hover:bg-[#2968b5] transition">Sign in</a>
              <a href="index.php?tab=signup" class="inline-flex justify-center rounded-xl border border-slate-700 px-4 py-3 text-sm font-semibold text-slate-100 hover:bg-slate-800 transition">Create account</a>
            </div>
          </div>
        <?php else: ?>
          <form method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <div>
              <label for="email" class="mb-2 block text-sm font-medium text-slate-300">Email address</label>
              <input id="email" name="email" type="email" required autocomplete="email" class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-slate-100 focus:border-sky-500 focus:outline-none" placeholder="you@example.com">
            </div>
            <button type="submit" class="w-full rounded-xl bg-[#2f7fe0] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[#2968b5]">Join community</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>
