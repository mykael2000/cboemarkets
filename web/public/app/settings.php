<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/csrf.php';
require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/email.php';

require_login();
$user = current_user();
$error = get_flash('error');
$success = get_flash('success');

function ensure_password_change_otps_table(): void
{
    try {
        db()->exec(
            'CREATE TABLE IF NOT EXISTS user_security_otps (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                purpose VARCHAR(50) NOT NULL,
                code_hash VARCHAR(255) NOT NULL,
                expires_at DATETIME NOT NULL,
                used TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_purpose (user_id, purpose),
                INDEX idx_expires (expires_at),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB'
        );
    } catch (Throwable) {
        // Keep request flow alive even if table check fails here.
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_info') {
    csrf_verify();
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($name === '') {
        flash('error', 'Name is required.');
        redirect('settings.php#personal-info');
    }

    try {
        db()->prepare(
            'UPDATE users SET name = ?, phone = ?, country = ?, address = ? WHERE id = ?'
        )->execute([$name, $phone ?: null, $country ?: null, $address ?: null, $user['id']]);
        $_SESSION['name'] = $name;
        flash('success', 'Personal information updated.');
    } catch (Throwable) {
        flash('error', 'Failed to update information. Please try again.');
    }

    redirect('settings.php#personal-info');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request_password_otp') {
    csrf_verify();
    $currentPw = $_POST['current_password'] ?? '';
    $newPw = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!password_verify($currentPw, $user['password'])) {
        flash('error', 'Current password is incorrect.');
        redirect('settings.php#security');
    }
    if (strlen($newPw) < 8) {
        flash('error', 'New password must be at least 8 characters.');
        redirect('settings.php#security');
    }
    if ($newPw !== $confirm) {
        flash('error', 'Passwords do not match.');
        redirect('settings.php#security');
    }

    $otp = (string) random_int(100000, 999999);
    $otpHash = password_hash($otp, PASSWORD_BCRYPT, ['cost' => 10]);
    $expiresAt = date('Y-m-d H:i:s', time() + 600);

    try {
        $pdo = db();
        ensure_password_change_otps_table();

        $pdo->prepare(
            'UPDATE user_security_otps SET used = 1 WHERE user_id = ? AND purpose = ? AND used = 0'
        )->execute([$user['id'], 'password_change']);

        $pdo->prepare(
            'INSERT INTO user_security_otps (user_id, purpose, code_hash, expires_at) VALUES (?, ?, ?, ?)'
        )->execute([$user['id'], 'password_change', $otpHash, $expiresAt]);

        $_SESSION['pending_password_change_hash'] = password_hash($newPw, PASSWORD_BCRYPT, ['cost' => 12]);
        $_SESSION['pending_password_change_expires'] = time() + 900;
        $_SESSION['pending_password_change_requested'] = true;

        $dbg = null;
        send_security_otp_email($user['email'], $user['name'], $otp, 'password change', $dbg);

        flash('success', 'OTP sent to your email. Enter it below to confirm password change.');
    } catch (Throwable) {
        flash('error', 'Failed to send OTP. Please try again.');
    }

    redirect('settings.php#security');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify_password_otp') {
    csrf_verify();
    $otp = trim($_POST['otp_code'] ?? '');

    if (!preg_match('/^\d{6}$/', $otp)) {
        flash('error', 'Enter a valid 6-digit OTP code.');
        redirect('settings.php#security');
    }

    $pendingHash = $_SESSION['pending_password_change_hash'] ?? '';
    $pendingExp = (int) ($_SESSION['pending_password_change_expires'] ?? 0);
    if ($pendingHash === '' || $pendingExp < time()) {
        unset($_SESSION['pending_password_change_hash'], $_SESSION['pending_password_change_expires'], $_SESSION['pending_password_change_requested']);
        flash('error', 'Password change request expired. Request a new OTP.');
        redirect('settings.php#security');
    }

    try {
        ensure_password_change_otps_table();
        $pdo = db();
        $stmt = $pdo->prepare(
            'SELECT * FROM user_security_otps
             WHERE user_id = ? AND purpose = ? AND used = 0 AND expires_at > NOW()
             ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute([$user['id'], 'password_change']);
        $row = $stmt->fetch();

        if (!$row || !password_verify($otp, $row['code_hash'])) {
            flash('error', 'Invalid or expired OTP code.');
            redirect('settings.php#security');
        }

        $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([$pendingHash, $user['id']]);
        $pdo->prepare('UPDATE user_security_otps SET used = 1 WHERE id = ?')->execute([(int) $row['id']]);

        unset($_SESSION['pending_password_change_hash'], $_SESSION['pending_password_change_expires'], $_SESSION['pending_password_change_requested']);
        flash('success', 'Password updated successfully.');
    } catch (Throwable) {
        flash('error', 'Failed to verify OTP. Please try again.');
    }

    redirect('settings.php#security');
}

$user = current_user();
$otpPending = !empty($_SESSION['pending_password_change_requested']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/images/favicon.png">
  <title>Settings - CBOE Markets</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen pb-24 md:pb-6">

  <header class="sticky top-0 z-40 bg-white/95 backdrop-blur border-b border-slate-200 px-4 py-3 flex items-center justify-between md:hidden">
    <span class="text-xl font-extrabold text-emerald-500">Settings</span>
    <a href="profile.php" class="text-slate-600 hover:text-slate-900 transition text-sm">Back</a>
  </header>

  <?php $activePage = 'profile.php'; include '_nav.php'; ?>

  <main class="max-w-2xl mx-auto px-4 py-6 space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-slate-900">Account Settings</h1>
      <a href="profile.php" class="hidden md:inline-flex text-sm text-slate-500 hover:text-slate-900">Back to Profile</a>
    </div>

    <?php if ($error): ?>
      <div class="bg-red-500/10 border border-red-500/30 text-red-600 text-sm rounded-lg px-4 py-3"><?= sanitize($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 text-sm rounded-lg px-4 py-3"><?= sanitize($success) ?></div>
    <?php endif; ?>

    <div id="personal-info" class="bg-white border border-slate-200 rounded-2xl p-6 scroll-mt-20">
      <h2 class="font-bold text-slate-900 text-lg mb-5">Personal Information</h2>
      <form method="POST" action="settings.php#personal-info" class="space-y-4">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_info">

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm text-slate-600 mb-1.5">Full Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" required value="<?= sanitize($user['name']) ?>"
              class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 placeholder-slate-400">
          </div>
          <div>
            <label class="block text-sm text-slate-600 mb-1.5">Email <span class="text-slate-400 text-xs">(cannot change)</span></label>
            <input type="email" value="<?= sanitize($user['email']) ?>" disabled
              class="w-full bg-slate-50 border border-slate-200 text-slate-500 rounded-lg px-4 py-3 cursor-not-allowed">
          </div>
          <div>
            <label class="block text-sm text-slate-600 mb-1.5">Phone</label>
            <input type="tel" name="phone" value="<?= sanitize($user['phone'] ?? '') ?>"
              placeholder="+1 234 567 8900"
              class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 placeholder-slate-400">
          </div>
          <div>
            <label class="block text-sm text-slate-600 mb-1.5">Country</label>
            <input type="text" name="country" value="<?= sanitize($user['country'] ?? '') ?>"
              placeholder="e.g. United States"
              class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 placeholder-slate-400">
          </div>
        </div>
        <div>
          <label class="block text-sm text-slate-600 mb-1.5">Address</label>
          <textarea name="address" rows="2" placeholder="Street, City, Postal Code"
            class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 placeholder-slate-400 resize-none"><?= sanitize($user['address'] ?? '') ?></textarea>
        </div>

        <button type="submit"
          class="bg-emerald-500 hover:bg-emerald-400 text-white font-bold py-3 px-6 rounded-xl transition">
          Save Changes
        </button>
      </form>
    </div>

    <div id="security" class="bg-white border border-slate-200 rounded-2xl p-6 scroll-mt-20 space-y-5">
      <div>
        <h2 class="font-bold text-slate-900 text-lg">Password &amp; Security</h2>
        <p class="text-sm text-slate-500 mt-1">Request OTP first, then verify OTP to complete password change.</p>
      </div>

      <form method="POST" action="settings.php#security" class="space-y-4 border border-slate-200 rounded-xl p-4">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="request_password_otp">

        <div>
          <label class="block text-sm text-slate-600 mb-1.5">Current Password</label>
          <input type="password" name="current_password" required autocomplete="current-password"
            class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 placeholder-slate-400"
            placeholder="Current password">
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm text-slate-600 mb-1.5">New Password</label>
            <input type="password" name="new_password" required autocomplete="new-password"
              class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 placeholder-slate-400"
              placeholder="Min. 8 characters">
          </div>
          <div>
            <label class="block text-sm text-slate-600 mb-1.5">Confirm Password</label>
            <input type="password" name="confirm_password" required autocomplete="new-password"
              class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 placeholder-slate-400"
              placeholder="Repeat new password">
          </div>
        </div>

        <button type="submit"
          class="bg-slate-900 hover:bg-slate-800 text-white font-bold py-3 px-6 rounded-xl transition">
          Send OTP Code
        </button>
      </form>

      <form method="POST" action="settings.php#security" class="space-y-3 border border-slate-200 rounded-xl p-4 <?= $otpPending ? '' : 'opacity-60' ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="verify_password_otp">
        <div>
          <label class="block text-sm text-slate-600 mb-1.5">OTP Code</label>
          <input type="text" name="otp_code" inputmode="numeric" maxlength="6" pattern="\d{6}" required
            class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 tracking-widest text-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 placeholder-slate-400"
            placeholder="000000">
          <p class="text-xs text-slate-500 mt-1">Enter the 6-digit code sent to <?= sanitize($user['email']) ?>.</p>
        </div>
        <button type="submit"
          class="bg-emerald-500 hover:bg-emerald-400 text-white font-bold py-3 px-6 rounded-xl transition">
          Verify OTP &amp; Update Password
        </button>
      </form>
    </div>
  </main>
</body>
</html>
