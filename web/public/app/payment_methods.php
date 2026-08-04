<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/csrf.php';
require_once __DIR__ . '/../../src/helpers.php';

require_login();
$user    = current_user();
$error   = get_flash('error');
$success = get_flash('success');

// ── Add payment method ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    csrf_verify();
    $type  = in_array($_POST['type'] ?? '', ['bank', 'crypto']) ? $_POST['type'] : '';
    $label = trim($_POST['label'] ?? '');

    if ($type === '' || $label === '') {
        flash('error', 'Type and label are required.');
        redirect('payment_methods.php');
    }

    $details = [];
    if ($type === 'bank') {
        $details = [
            'account_name'   => trim($_POST['account_name']   ?? ''),
            'account_number' => trim($_POST['account_number'] ?? ''),
            'bank_name'      => trim($_POST['bank_name']      ?? ''),
            'routing'        => trim($_POST['routing']        ?? ''),
        ];
        if ($details['account_name'] === '' || $details['account_number'] === '' || $details['bank_name'] === '') {
            flash('error', 'Account name, number and bank name are required for bank accounts.');
            redirect('payment_methods.php');
        }
    } else {
        $details = [
            'coin'    => strtoupper(trim($_POST['coin']    ?? '')),
            'network' => trim($_POST['network']  ?? ''),
            'address' => trim($_POST['address']  ?? ''),
        ];
        if ($details['coin'] === '' || $details['address'] === '') {
            flash('error', 'Coin and address are required for crypto methods.');
            redirect('payment_methods.php');
        }
    }

    try {
        db()->prepare(
            'INSERT INTO payment_methods (user_id, type, label, details) VALUES (?, ?, ?, ?)'
        )->execute([$user['id'], $type, $label, json_encode($details, JSON_UNESCAPED_UNICODE)]);
        flash('success', 'Payment method added.');
    } catch (Throwable) {
        flash('error', 'Failed to add payment method.');
    }
    redirect('payment_methods.php');
}

// ── Delete payment method ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_verify();
    $id = (int)($_POST['id'] ?? 0);
    try {
        db()->prepare('DELETE FROM payment_methods WHERE id = ? AND user_id = ?')
             ->execute([$id, $user['id']]);
        flash('success', 'Payment method removed.');
    } catch (Throwable) {
        flash('error', 'Failed to remove payment method.');
    }
    redirect('payment_methods.php');
}

// Fetch all payment methods
$methods = [];
try {
    $stmt = db()->prepare('SELECT * FROM payment_methods WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([$user['id']]);
    $methods = $stmt->fetchAll();
} catch (Throwable) {}

$activeTab = $_GET['tab'] ?? 'bank';
if (!in_array($activeTab, ['bank', 'crypto'])) {
    $activeTab = 'bank';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/images/favicon.png">
  <title>Payment Methods – CBOE Markets</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen pb-24 md:pb-6">

  <header class="sticky top-0 z-40 bg-white/95 backdrop-blur border-b border-slate-200 px-4 py-3 flex items-center gap-3 md:hidden">
    <a href="profile.php" class="text-slate-500 hover:text-slate-700 transition">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    </a>
    <span class="text-lg font-extrabold text-emerald-400">Payment Methods</span>
  </header>

  <?php $activePage = 'profile.php'; include '_nav.php'; ?>

  <main class="max-w-xl mx-auto px-4 py-6 space-y-6">

    <?php if ($error): ?>
      <div class="bg-red-500/10 border border-red-500/30 text-red-600 text-sm rounded-lg px-4 py-3"><?= sanitize($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 text-sm rounded-lg px-4 py-3"><?= sanitize($success) ?></div>
    <?php endif; ?>

    <!-- Tab Selector -->
    <div class="flex gap-2 bg-slate-100 p-1 rounded-xl">
      <a href="payment_methods.php?tab=bank"
        class="flex-1 text-center py-2 rounded-lg text-sm font-semibold transition <?= $activeTab === 'bank' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700' ?>">
        Bank Account
      </a>
      <a href="payment_methods.php?tab=crypto"
        class="flex-1 text-center py-2 rounded-lg text-sm font-semibold transition <?= $activeTab === 'crypto' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700' ?>">
        Crypto Address
      </a>
    </div>

    <!-- Add Form -->
    <div class="bg-white border border-slate-200 rounded-2xl p-6">
      <h3 class="font-bold text-slate-900 mb-4">Add <?= $activeTab === 'bank' ? 'Bank Account' : 'Crypto Address' ?></h3>
      <form method="POST" action="payment_methods.php" class="space-y-4">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="type"   value="<?= sanitize($activeTab) ?>">

        <div>
          <label class="block text-sm text-slate-600 mb-1.5">Label <span class="text-slate-400 text-xs">(e.g. "My Chase Account")</span></label>
          <input type="text" name="label" required placeholder="Nickname for this method"
            class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 placeholder-slate-400">
        </div>

        <?php if ($activeTab === 'bank'): ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm text-slate-600 mb-1.5">Account Holder Name</label>
            <input type="text" name="account_name" required
              class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 placeholder-slate-400"
              placeholder="Full name">
          </div>
          <div>
            <label class="block text-sm text-slate-600 mb-1.5">Account Number / IBAN</label>
            <input type="text" name="account_number" required
              class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 placeholder-slate-400"
              placeholder="e.g. GB12BARC...">
          </div>
          <div>
            <label class="block text-sm text-slate-600 mb-1.5">Bank Name</label>
            <input type="text" name="bank_name" required
              class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 placeholder-slate-400"
              placeholder="e.g. Barclays">
          </div>
          <div>
            <label class="block text-sm text-slate-600 mb-1.5">Routing / SWIFT / Sort Code</label>
            <input type="text" name="routing"
              class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 placeholder-slate-400"
              placeholder="Optional">
          </div>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm text-slate-600 mb-1.5">Coin</label>
            <select name="coin"
              class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500">
              <option value="USDT">USDT</option>
              <option value="BTC">BTC</option>
              <option value="ETH">ETH</option>
              <option value="BNB">BNB</option>
              <option value="SOL">SOL</option>
            </select>
          </div>
          <div>
            <label class="block text-sm text-slate-600 mb-1.5">Network</label>
            <input type="text" name="network"
              class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 placeholder-slate-400"
              placeholder="e.g. TRC20, ERC20">
          </div>
        </div>
        <div>
          <label class="block text-sm text-slate-600 mb-1.5">Wallet Address</label>
          <input type="text" name="address" required
            class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 placeholder-slate-400 font-mono text-xs"
            placeholder="0x... or T...">
        </div>
        <?php endif; ?>

        <button type="submit"
          class="w-full bg-emerald-500 hover:bg-emerald-400 text-white font-bold py-3 rounded-xl transition">
          Add Method
        </button>
      </form>
    </div>

    <!-- Saved Methods List -->
    <?php
      $filtered = array_filter($methods, fn($m) => $m['type'] === $activeTab);
    ?>
    <?php if (!empty($filtered)): ?>
    <div class="space-y-3">
      <h3 class="font-bold text-slate-900">Saved <?= $activeTab === 'bank' ? 'Bank Accounts' : 'Crypto Addresses' ?></h3>
      <?php foreach ($filtered as $m): ?>
        <?php $d = json_decode($m['details'], true) ?? []; ?>
        <div class="bg-white border border-slate-200 rounded-2xl p-4 flex items-start justify-between gap-3">
          <div class="flex-1 min-w-0">
            <p class="font-semibold text-slate-900 text-sm"><?= sanitize($m['label']) ?></p>
            <?php if ($m['type'] === 'bank'): ?>
              <p class="text-xs text-slate-500 mt-0.5"><?= sanitize($d['bank_name'] ?? '') ?> &bull; <?= sanitize($d['account_number'] ?? '') ?></p>
              <p class="text-xs text-slate-400"><?= sanitize($d['account_name'] ?? '') ?></p>
            <?php else: ?>
              <p class="text-xs text-slate-500 mt-0.5"><?= sanitize($d['coin'] ?? '') ?><?= !empty($d['network']) ? ' / ' . sanitize($d['network']) : '' ?></p>
              <p class="text-xs text-slate-400 font-mono truncate"><?= sanitize($d['address'] ?? '') ?></p>
            <?php endif; ?>
            <p class="text-xs text-slate-300 mt-1"><?= date('M j, Y', strtotime($m['created_at'])) ?></p>
          </div>
          <form method="POST" action="payment_methods.php" onsubmit="return confirm('Remove this payment method?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
            <button type="submit" class="text-red-400 hover:text-red-600 transition text-xs">Remove</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="text-center text-slate-400 text-sm py-4">No <?= $activeTab === 'bank' ? 'bank accounts' : 'crypto addresses' ?> saved yet.</p>
    <?php endif; ?>

  </main>

</body>
</html>
