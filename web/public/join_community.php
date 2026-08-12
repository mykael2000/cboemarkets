<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/csrf.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/email.php';

$success = get_flash('success');
$error = get_flash('error');
$communityEmail = trim($_SESSION['community_email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (isset($_POST['community_step']) && $_POST['community_step'] === 'join') {
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

        $_SESSION['community_email'] = $email;
        $communityEmail = $email;
        flash('success', 'Thanks! Please choose whether you are an existing member or a new member.');
        redirect('join_community.php?joined=1');
    }
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
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const choiceButtons = document.querySelectorAll('[data-member-choice]');
      const sections = document.querySelectorAll('[data-member-panel]');

      choiceButtons.forEach((button) => {
        button.addEventListener('click', function () {
          const choice = this.dataset.memberChoice;

          sections.forEach((section) => {
            section.classList.toggle('hidden', section.dataset.memberPanel !== choice);
          });

          choiceButtons.forEach((btn) => {
            const active = btn === this;
            btn.classList.toggle('bg-[#2f7fe0]', active);
            btn.classList.toggle('text-white', active);
            btn.classList.toggle('border-transparent', active);
            btn.classList.toggle('bg-slate-800', !active);
            btn.classList.toggle('text-slate-200', !active);
            btn.classList.toggle('border-slate-700', !active);
          });
        });
      });
    });
  </script>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
  <div class="min-h-screen flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-3xl rounded-3xl border border-slate-800 bg-slate-900/90 shadow-2xl shadow-slate-950/40 overflow-hidden">
      <div class="bg-gradient-to-r from-[#0f2f5c] to-[#2f7fe0] px-8 py-8">
        <p class="text-sm uppercase tracking-[0.35em] text-slate-200/80">Community access</p>
        <h1 class="mt-3 text-3xl font-bold text-white">Join the CBOE Markets community</h1>
        <p class="mt-3 text-sm text-slate-200">Start with your email, then choose whether you already have an account or are creating one.</p>
      </div>

      <div class="p-8 space-y-6">
        <?php if ($error): ?>
          <div class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300"><?= sanitize($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
          <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300"><?= sanitize($success) ?></div>
        <?php endif; ?>

        <?php if (!$joined): ?>
          <form method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="community_step" value="join">
            <div>
              <label for="email" class="mb-2 block text-sm font-medium text-slate-300">Email address</label>
              <input id="email" name="email" type="email" required autocomplete="email" class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-slate-100 focus:border-sky-500 focus:outline-none" placeholder="you@example.com" value="<?= sanitize($communityEmail) ?>">
            </div>
            <button type="submit" class="w-full rounded-xl bg-[#2f7fe0] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[#2968b5]">Join community</button>
          </form>
        <?php else: ?>
          <div class="rounded-2xl border border-sky-500/30 bg-sky-500/10 p-5">
            <h2 class="text-lg font-semibold text-sky-200">Choose your account type</h2>
            <p class="mt-1 text-sm text-sky-100/80">We are ready for <span class="font-semibold"><?= sanitize($communityEmail ?: 'your email') ?></span>.</p>

            <div class="mt-5 grid gap-3 sm:grid-cols-2">
              <button type="button" data-member-choice="old-member" class="border border-slate-700 bg-slate-800 text-slate-200 rounded-xl px-4 py-3 text-sm font-semibold hover:border-sky-500 hover:text-white transition">
                Old member
              </button>
              <button type="button" data-member-choice="new-member" class="border border-slate-700 bg-slate-800 text-slate-200 rounded-xl px-4 py-3 text-sm font-semibold hover:border-sky-500 hover:text-white transition">
                New member
              </button>
            </div>

            <div class="mt-6">
              <div data-member-panel="old-member" class="hidden rounded-2xl border border-slate-700 bg-slate-950/60 p-5">
                <h3 class="text-lg font-semibold text-white">Sign in</h3>
                <form method="POST" action="index.php" class="mt-4 space-y-4">
                  <?= csrf_field() ?>
                  <input type="hidden" name="auth_mode" value="signin">
                  <input type="hidden" name="remember" value="1">
                  <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5" for="signin_email">Email address</label>
                    <input id="signin_email" name="email" type="email" required value="<?= sanitize($communityEmail) ?>" class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-slate-100 focus:border-sky-500 focus:outline-none">
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5" for="signin_password">Password</label>
                    <input id="signin_password" name="password" type="password" required class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-slate-100 focus:border-sky-500 focus:outline-none" placeholder="Enter your password">
                  </div>
                  <button type="submit" class="w-full rounded-xl bg-[#2f7fe0] px-4 py-3 text-sm font-semibold text-white hover:bg-[#2968b5] transition">Access platform</button>
                </form>
              </div>

              <div data-member-panel="new-member" class="hidden rounded-2xl border border-slate-700 bg-slate-950/60 p-5">
                <h3 class="text-lg font-semibold text-white">Create account</h3>
                <form method="POST" action="index.php" class="mt-4 space-y-4">
                  <?= csrf_field() ?>
                  <input type="hidden" name="auth_mode" value="signup">
                  <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5" for="signup_name">Full name</label>
                    <input id="signup_name" name="name" type="text" required class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-slate-100 focus:border-sky-500 focus:outline-none" placeholder="John Doe">
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5" for="signup_email">Email address</label>
                    <input id="signup_email" name="email" type="email" required value="<?= sanitize($communityEmail) ?>" class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-slate-100 focus:border-sky-500 focus:outline-none">
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5" for="signup_password">Password</label>
                    <input id="signup_password" name="password" type="password" required class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-slate-100 focus:border-sky-500 focus:outline-none" placeholder="Min. 8 characters">
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5" for="signup_password_confirm">Confirm password</label>
                    <input id="signup_password_confirm" name="password_confirm" type="password" required class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-slate-100 focus:border-sky-500 focus:outline-none" placeholder="Repeat password">
                  </div>
                  <button type="submit" class="w-full rounded-xl bg-[#2f7fe0] px-4 py-3 text-sm font-semibold text-white hover:bg-[#2968b5] transition">Create account</button>
                </form>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>
