<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/csrf.php';
require_once __DIR__ . '/../../src/helpers.php';

require_admin();

function ensure_user_dashboard_metric_columns(): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    try {
        db()->exec('ALTER TABLE users ADD COLUMN IF NOT EXISTS dashboard_today_pnl DECIMAL(18,8) NOT NULL DEFAULT 0.00000000');
        db()->exec('ALTER TABLE users ADD COLUMN IF NOT EXISTS dashboard_equity DECIMAL(18,8) NOT NULL DEFAULT 0.00000000');
        db()->exec('ALTER TABLE users ADD COLUMN IF NOT EXISTS dashboard_margin DECIMAL(18,8) NOT NULL DEFAULT 0.00000000');
        db()->exec('ALTER TABLE users ADD COLUMN IF NOT EXISTS dashboard_free_margin DECIMAL(18,8) NOT NULL DEFAULT 0.00000000');
    } catch (Throwable) {
        // Keep page usable even if auto-migration cannot run.
    }

    $ensured = true;
}

ensure_user_dashboard_metric_columns();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = trim((string)($_POST['action'] ?? ''));
    $userId = (int)($_POST['user_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
  $assetTicker = strtoupper(trim((string)($_POST['asset_ticker'] ?? 'USDT')));

    $validActions = ['add_deposit', 'add_profit', 'add_auto_trading', 'add_copy_trading'];
  $assetToColumn = [
    'USDT' => 'balance',
    'BTC'  => 'btc_balance',
    'ETH'  => 'eth_balance',
    'BNB'  => 'bnb_balance',
    'SOL'  => 'sol_balance',
  ];
    if (!in_array($action, $validActions, true) || $userId <= 0 || $amount <= 0) {
        flash('error', 'Please choose a valid user and amount greater than zero.');
        redirect('/admin/add_balances');
    }

  if (($action === 'add_deposit' || $action === 'add_profit') && !isset($assetToColumn[$assetTicker])) {
    flash('error', 'Please select a valid coin.');
    redirect('/admin/add_balances');
  }

    try {
        $pdo = db();
        $pdo->beginTransaction();

        $st = $pdo->prepare('SELECT id, name FROM users WHERE id = ? LIMIT 1 FOR UPDATE');
        $st->execute([$userId]);
        $selectedUser = $st->fetch();

        if (!$selectedUser) {
            $pdo->rollBack();
            flash('error', 'User not found.');
            redirect('/admin/add_balances');
        }

        if ($action === 'add_deposit') {
          // Admin enters USD value; convert to coin amount then save.
          $coinColumn  = $assetToColumn[$assetTicker];
          $usdValue    = $amount; // admin typed USD
          $coinAmount  = $assetTicker === 'USDT' ? $amount : ($amount / price_for_symbol($assetTicker . 'USDT'));

          $pdo->prepare('UPDATE users SET ' . $coinColumn . ' = ' . $coinColumn . ' + ?, dashboard_equity = dashboard_equity + ? WHERE id = ?')
            ->execute([$coinAmount, $usdValue, $userId]);

          flash('success', 'Added $' . number_format($usdValue, 2) . ' (' . rtrim(rtrim(number_format($coinAmount, 8, '.', ''), '0'), '.') . ' ' . $assetTicker . ') deposit to ' . $selectedUser['name'] . '.');
        } elseif ($action === 'add_profit') {
          // Profit is entered in USD; also credit selected coin balance.
          $coinColumn  = $assetToColumn[$assetTicker];
          $usdValue    = $amount;
          $coinAmount  = $assetTicker === 'USDT' ? $amount : ($amount / price_for_symbol($assetTicker . 'USDT'));

          $pdo->prepare('UPDATE users SET dashboard_today_pnl = dashboard_today_pnl + ?, ' . $coinColumn . ' = ' . $coinColumn . ' + ? WHERE id = ?')
            ->execute([$usdValue, $coinAmount, $userId]);

          flash('success', 'Added $' . number_format($usdValue, 2) . ' profit and credited ' . rtrim(rtrim(number_format($coinAmount, 8, '.', ''), '0'), '.') . ' ' . $assetTicker . ' to ' . $selectedUser['name'] . '.');
        } elseif ($action === 'add_auto_trading') {
            $pdo->prepare('UPDATE users SET dashboard_margin = dashboard_margin + ? WHERE id = ?')
                ->execute([$amount, $userId]);
            flash('success', 'Added $' . number_format($amount, 2) . ' auto trading balance to ' . $selectedUser['name'] . '.');
        } elseif ($action === 'add_copy_trading') {
            $pdo->prepare('UPDATE users SET dashboard_free_margin = dashboard_free_margin + ? WHERE id = ?')
                ->execute([$amount, $userId]);
            flash('success', 'Added $' . number_format($amount, 2) . ' copy trading balance to ' . $selectedUser['name'] . '.');
        }

        $pdo->commit();
    } catch (Throwable) {
        try {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
        } catch (Throwable) {}
        flash('error', 'Failed to apply update. Please try again.');
    }

    redirect('/admin/add_balances');
}

$error = get_flash('error');
$success = get_flash('success');

$users = [];
try {
    $st = db()->query('SELECT id, name, email, status FROM users ORDER BY created_at DESC');
    $users = $st->fetchAll() ?: [];
} catch (Throwable) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/images/favicon.png">
  <title>Add Balances - CBOE Markets Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-800 text-white min-h-screen">
<div class="flex min-h-screen">
  <?php include __DIR__ . '/_sidebar.php'; ?>

  <main class="flex-1 bg-slate-800 p-4 sm:p-6 lg:p-8 pt-20 lg:pt-8">
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-white">Add Balances</h1>
      <p class="text-slate-400 text-sm mt-1">Use these forms to add deposit, profit, auto trading balance, and copy trading balance.</p>
    </div>

    <?php if ($error): ?>
      <div class="bg-red-500/10 border border-red-500/30 text-red-400 text-sm rounded-lg px-4 py-3 mb-4"><?= sanitize($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm rounded-lg px-4 py-3 mb-4"><?= sanitize($success) ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
      <section class="bg-slate-700 rounded-2xl p-5">
        <h2 class="font-bold text-white mb-1">Add Deposit</h2>
        <p class="text-xs text-slate-400 mb-4">Adds to selected coin balance and updates Total Deposit.</p>
        <form method="POST" action="/admin/add_balances" class="space-y-3">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add_deposit">
          <div>
            <label class="block text-xs text-slate-300 mb-1">User</label>
            <select name="user_id" required class="w-full bg-slate-600 border border-slate-500 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-emerald-500/60">
              <option value="">Select user</option>
              <?php foreach ($users as $u): ?>
              <option value="<?= (int)$u['id'] ?>"><?= sanitize($u['name']) ?> (<?= sanitize($u['email']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-xs text-slate-300 mb-1">Coin</label>
            <select name="asset_ticker" required class="w-full bg-slate-600 border border-slate-500 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-emerald-500/60">
              <option value="USDT" selected>USDT</option>
              <option value="BTC">BTC</option>
              <option value="ETH">ETH</option>
              <option value="BNB">BNB</option>
              <option value="SOL">SOL</option>
            </select>
          </div>
          <div>
            <label class="block text-xs text-slate-300 mb-1">Amount (USD)</label>
            <input type="number" name="amount" min="0.01" step="0.01" required class="w-full bg-slate-600 border border-slate-500 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-emerald-500/60" placeholder="0.00">
            <p class="text-xs text-slate-400 mt-1">Enter value in USD — will be auto-converted to the selected coin.</p>
          </div>
          <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 text-white font-semibold px-4 py-2 rounded-lg transition">Add Deposit</button>
        </form>
      </section>

      <section class="bg-slate-700 rounded-2xl p-5">
        <h2 class="font-bold text-white mb-1">Add Profit</h2>
        <p class="text-xs text-slate-400 mb-4">Adds to Today's PnL and credits selected coin balance.</p>
        <form method="POST" action="/admin/add_balances" class="space-y-3">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add_profit">
          <div>
            <label class="block text-xs text-slate-300 mb-1">User</label>
            <select name="user_id" required class="w-full bg-slate-600 border border-slate-500 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-red-500/60">
              <option value="">Select user</option>
              <?php foreach ($users as $u): ?>
              <option value="<?= (int)$u['id'] ?>"><?= sanitize($u['name']) ?> (<?= sanitize($u['email']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-xs text-slate-300 mb-1">Coin</label>
            <select name="asset_ticker" required class="w-full bg-slate-600 border border-slate-500 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-red-500/60">
              <option value="USDT" selected>USDT</option>
              <option value="BTC">BTC</option>
              <option value="ETH">ETH</option>
              <option value="BNB">BNB</option>
              <option value="SOL">SOL</option>
            </select>
          </div>
          <div>
            <label class="block text-xs text-slate-300 mb-1">Amount (USD)</label>
            <input type="number" name="amount" min="0.00000001" step="0.00000001" required class="w-full bg-slate-600 border border-slate-500 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-red-500/60" placeholder="0.00">
          </div>
          <button type="submit" class="bg-red-600 hover:bg-red-500 text-white font-semibold px-4 py-2 rounded-lg transition">Add Profit</button>
        </form>
      </section>

      <section class="bg-slate-700 rounded-2xl p-5">
        <h2 class="font-bold text-white mb-1">Add Auto Trading Balance</h2>
        <p class="text-xs text-slate-400 mb-4">Adds to Auto Trading Allocated.</p>
        <form method="POST" action="/admin/add_balances" class="space-y-3">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add_auto_trading">
          <div>
            <label class="block text-xs text-slate-300 mb-1">User</label>
            <select name="user_id" required class="w-full bg-slate-600 border border-slate-500 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-emerald-500/60">
              <option value="">Select user</option>
              <?php foreach ($users as $u): ?>
              <option value="<?= (int)$u['id'] ?>"><?= sanitize($u['name']) ?> (<?= sanitize($u['email']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-xs text-slate-300 mb-1">Amount (USD)</label>
            <input type="number" name="amount" min="0.00000001" step="0.00000001" required class="w-full bg-slate-600 border border-slate-500 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-emerald-500/60" placeholder="0.00">
          </div>
          <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 text-white font-semibold px-4 py-2 rounded-lg transition">Add Auto Trading Balance</button>
        </form>
      </section>

      <section class="bg-slate-700 rounded-2xl p-5">
        <h2 class="font-bold text-white mb-1">Add Copy Trading Balance</h2>
        <p class="text-xs text-slate-400 mb-4">Adds to Copy Trading Allocated.</p>
        <form method="POST" action="/admin/add_balances" class="space-y-3">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add_copy_trading">
          <div>
            <label class="block text-xs text-slate-300 mb-1">User</label>
            <select name="user_id" required class="w-full bg-slate-600 border border-slate-500 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-cyan-500/60">
              <option value="">Select user</option>
              <?php foreach ($users as $u): ?>
              <option value="<?= (int)$u['id'] ?>"><?= sanitize($u['name']) ?> (<?= sanitize($u['email']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-xs text-slate-300 mb-1">Amount (USD)</label>
            <input type="number" name="amount" min="0.00000001" step="0.00000001" required class="w-full bg-slate-600 border border-slate-500 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-cyan-500/60" placeholder="0.00">
          </div>
          <button type="submit" class="bg-cyan-600 hover:bg-cyan-500 text-white font-semibold px-4 py-2 rounded-lg transition">Add Copy Trading Balance</button>
        </form>
      </section>
    </div>
  </main>
</div>
</body>
</html>
