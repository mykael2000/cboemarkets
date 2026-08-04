<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/csrf.php';
require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/email.php';

require_login();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name === '' || $email === '' || $subject === '' || $message === '') {
        flash('error', 'All fields are required.');
        redirect('contact_us.php');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Please enter a valid email address.');
        redirect('contact_us.php');
    }

    $to = 'support@cboemarkets.com';
    $safeName = sanitize($name);
    $safeEmail = sanitize($email);
    $safeSubject = sanitize($subject);
    $safeMessage = nl2br(sanitize($message));

    $mailSubject = 'Support Message: ' . $subject;
    $mailBody = <<<HTML
    <div style="font-family:Arial,sans-serif;max-width:640px;margin:0 auto;background:#ffffff;color:#0f172a;padding:24px;border:1px solid #e2e8f0;border-radius:10px;">
      <h2 style="margin:0 0 12px;color:#0f172a;">New Contact Message</h2>
      <p style="margin:0 0 8px;"><strong>Name:</strong> {$safeName}</p>
      <p style="margin:0 0 8px;"><strong>Email:</strong> {$safeEmail}</p>
      <p style="margin:0 0 8px;"><strong>Subject:</strong> {$safeSubject}</p>
      <hr style="border:none;border-top:1px solid #e2e8f0;margin:14px 0;">
      <div style="line-height:1.55;color:#334155;">{$safeMessage}</div>
    </div>
    HTML;

    if (!send_email($to, $mailSubject, $mailBody)) {
        flash('error', 'Message could not be sent right now. Please try again shortly.');
        redirect('contact_us.php');
    }

    flash('success', 'Your message has been sent to support successfully.');
    redirect('contact_us.php');
}

$error = get_flash('error');
$success = get_flash('success');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/images/favicon.png">
  <title>Contact Us - CBOE Markets</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen pb-24 md:pb-6">

  <header class="sticky top-0 z-40 bg-white/95 backdrop-blur border-b border-slate-200 px-4 py-3 flex items-center justify-between md:hidden">
    <span class="text-xl font-extrabold text-emerald-500">Contact Us</span>
    <a href="profile.php" class="text-slate-600 hover:text-slate-900 transition text-sm">Back</a>
  </header>

  <?php $activePage = 'profile.php'; include '_nav.php'; ?>

  <main class="max-w-2xl mx-auto px-4 py-6 space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-slate-900">Contact Support</h1>
      <a href="profile.php" class="hidden md:inline-flex text-sm text-slate-500 hover:text-slate-900">Back to Profile</a>
    </div>

    <?php if ($error): ?>
      <div class="bg-red-500/10 border border-red-500/30 text-red-600 text-sm rounded-lg px-4 py-3"><?= sanitize($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 text-sm rounded-lg px-4 py-3"><?= sanitize($success) ?></div>
    <?php endif; ?>

    <div class="bg-white border border-slate-200 rounded-2xl p-6">
      <p class="text-sm text-slate-600 mb-5">Need help? Send us a message and our support team will get back to you.</p>

      <form method="POST" action="contact_us.php" class="space-y-4">
        <?= csrf_field() ?>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm text-slate-600 mb-1.5">Name</label>
            <input type="text" name="name" required value="<?= sanitize($user['name']) ?>"
              class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 placeholder-slate-400">
          </div>
          <div>
            <label class="block text-sm text-slate-600 mb-1.5">Email</label>
            <input type="email" name="email" required value="<?= sanitize($user['email']) ?>"
              class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 placeholder-slate-400">
          </div>
        </div>

        <div>
          <label class="block text-sm text-slate-600 mb-1.5">Subject</label>
          <input type="text" name="subject" required maxlength="150" placeholder="How can we help?"
            class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 placeholder-slate-400">
        </div>

        <div>
          <label class="block text-sm text-slate-600 mb-1.5">Message</label>
          <textarea name="message" rows="6" required placeholder="Type your message here"
            class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 placeholder-slate-400 resize-y"></textarea>
        </div>

        <button type="submit"
          class="bg-emerald-500 hover:bg-emerald-400 text-white font-bold py-3 px-6 rounded-xl transition">
          Send Message
        </button>
      </form>
    </div>
  </main>
</body>
</html>
