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

// Supported coins and their balance columns
$coins = [
    'USDT' => 'balance',
    'BTC'  => 'btc_balance',
    'ETH'  => 'eth_balance',
    'BNB'  => 'bnb_balance',
    'SOL'  => 'sol_balance',
];

// ── Subscribe to a plan ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'subscribe') {
    csrf_verify();
    $planId   = (int)($_POST['plan_id']  ?? 0);
    $amount   = (float)($_POST['amount'] ?? 0);
    $currency = strtoupper(trim($_POST['currency'] ?? 'USDT'));

    if (!isset($coins[$currency])) {
        flash('error', 'Invalid currency selected.');
        redirect('vip.php');
    }
    if ($planId <= 0 || $amount <= 0) {
        flash('error', 'Invalid plan or amount.');
        redirect('vip.php');
    }

    try {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT * FROM investment_plans WHERE id = ? AND active = 1 LIMIT 1');
        $stmt->execute([$planId]);
        $plan = $stmt->fetch();
        if (!$plan) {
            flash('error', 'Plan not found or inactive.');
            redirect('vip.php');
        }

        // Convert amount to USD to validate min/max (amount is always in USD)
        if ($amount < (float)$plan['min_deposit'] || $amount > (float)$plan['max_deposit']) {
            flash('error', sprintf(
                'Amount must be between $%s and $%s for this plan.',
                format_currency((float)$plan['min_deposit']),
                format_currency((float)$plan['max_deposit'])
            ));
            redirect('vip.php');
        }

        // Compute coin amount needed
        $balanceCol = $coins[$currency];
        $currentBalance = (float)($user[$balanceCol] ?? 0);

        if ($currency === 'USDT') {
            $coinAmount = $amount;
        } else {
            $price = price_for_symbol($currency . 'USDT');
            $coinAmount = ($price > 0) ? $amount / $price : 0;
        }

        if ($coinAmount <= 0 || $currentBalance < $coinAmount) {
            flash('error', sprintf(
                'Insufficient %s balance. You need %.6f %s.',
                $currency, $coinAmount, $currency
            ));
            redirect('vip.php');
        }

        $pdo->beginTransaction();

        // Build the UPDATE using a hardcoded column mapping to prevent injection
        $updateSql = match($currency) {
            'USDT' => 'UPDATE users SET balance      = balance      - ? WHERE id = ? AND balance      >= ?',
            'BTC'  => 'UPDATE users SET btc_balance  = btc_balance  - ? WHERE id = ? AND btc_balance  >= ?',
            'ETH'  => 'UPDATE users SET eth_balance  = eth_balance  - ? WHERE id = ? AND eth_balance  >= ?',
            'BNB'  => 'UPDATE users SET bnb_balance  = bnb_balance  - ? WHERE id = ? AND bnb_balance  >= ?',
            'SOL'  => 'UPDATE users SET sol_balance  = sol_balance  - ? WHERE id = ? AND sol_balance  >= ?',
        };
        $rowCount = $pdo->prepare($updateSql)->execute([$coinAmount, $user['id'], $coinAmount]);

        $startDate = date('Y-m-d');
        $endDate   = date('Y-m-d', strtotime("+{$plan['duration_days']} days"));

        $pdo->prepare(
            'INSERT INTO user_plans (user_id, plan_id, amount, currency, start_date, end_date, status)
             VALUES (?, ?, ?, ?, ?, ?, "active")'
        )->execute([$user['id'], $planId, $amount, $currency, $startDate, $endDate]);

        $pdo->commit();
        flash('success', "Successfully subscribed to {$plan['name']}! Your plan runs until {$endDate}.");
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', 'Failed to subscribe. Please try again.');
    }
    redirect('vip.php');
}

// Fetch all active plans
$plans = [];
try {
    $plans = db()->query('SELECT * FROM investment_plans WHERE active = 1 ORDER BY min_deposit ASC')->fetchAll();
} catch (Throwable) {}

// Fetch user's subscriptions
$subscriptions = [];
try {
    $stmt = db()->prepare(
        'SELECT up.*, ip.name AS plan_name, ip.roi_percent, ip.duration_days AS plan_days
         FROM user_plans up
         JOIN investment_plans ip ON ip.id = up.plan_id
         WHERE up.user_id = ?
         ORDER BY up.created_at DESC'
    );
    $stmt->execute([$user['id']]);
    $subscriptions = $stmt->fetchAll();
} catch (Throwable) {}

// Fetch PnL for each subscription
$pnlMap = [];
try {
    if (!empty($subscriptions)) {
        $idList = array_map(fn($s) => (int)$s['id'], $subscriptions);
        $placeholders = implode(',', array_fill(0, count($idList), '?'));
        $stmt = db()->prepare(
            "SELECT subscription_id, SUM(pnl_amount) AS total_pnl, COUNT(*) AS updates
             FROM vip_pnl_updates WHERE subscription_id IN ({$placeholders})
             GROUP BY subscription_id"
        );
        $stmt->execute($idList);
        foreach ($stmt->fetchAll() as $r) {
            $pnlMap[$r['subscription_id']] = $r;
        }
    }
} catch (Throwable) {}

// Compute coin balances with USD value
$balanceUsd = 0.0;
$coinBalances = [];
foreach ($coins as $coin => $col) {
    $amt = (float)($user[$col] ?? 0);
    $usd = $coin === 'USDT' ? $amt : $amt * price_for_symbol($coin . 'USDT');
    $coinBalances[$coin] = ['amount' => $amt, 'usd' => $usd];
    $balanceUsd += $usd;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/images/favicon.png">
  <title>VIP Program – CBOE Markets</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen pb-24 md:pb-6">

  <header class="sticky top-0 z-40 bg-white/95 backdrop-blur border-b border-slate-200 px-4 py-3 flex items-center gap-3 md:hidden">
    <a href="profile.php" class="text-slate-500 hover:text-slate-700 transition">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    </a>
    <span class="text-lg font-extrabold text-emerald-400">VIP Program</span>
  </header>

  <?php $activePage = 'profile.php'; include '_nav.php'; ?>

  <main class="max-w-2xl mx-auto px-4 py-6 space-y-6">

    <?php if ($error): ?>
      <div class="bg-red-500/10 border border-red-500/30 text-red-600 text-sm rounded-lg px-4 py-3"><?= sanitize($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 text-sm rounded-lg px-4 py-3"><?= sanitize($success) ?></div>
    <?php endif; ?>

    <!-- Balance Summary -->
    <div class="bg-emerald-500 rounded-2xl p-5 text-white">
      <p class="text-emerald-100 text-sm mb-0.5">Available Balance</p>
      <p class="text-3xl font-extrabold"><?php
        $totalUsd = 0;
        foreach ($coins as $coin => $col) {
            $amt = (float)($user[$col] ?? 0);
            $totalUsd += $coin === 'USDT' ? $amt : $amt * price_for_symbol($coin . 'USDT');
        }
        echo '$' . format_currency($totalUsd);
      ?></p>
      <div class="flex flex-wrap gap-3 mt-3">
        <?php foreach ($coinBalances as $coin => $cb): ?>
        <div class="text-xs text-emerald-100">
          <?= sanitize($coin) ?>: <span class="font-semibold text-white"><?= number_format($cb['amount'], $coin === 'USDT' ? 2 : 6) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Investment Plans -->
    <div>
      <h2 class="font-bold text-slate-900 text-lg mb-4">Investment Plans</h2>
      <?php if (empty($plans)): ?>
        <p class="text-slate-400 text-sm text-center py-8">No plans available at this time.</p>
      <?php else: ?>
      <div class="grid gap-4 sm:grid-cols-2">
        <?php foreach ($plans as $p): ?>
        <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden hover:border-emerald-300 hover:shadow-sm transition">
          <div class="p-5">
            <div class="flex items-start justify-between mb-3">
              <div>
                <h3 class="font-bold text-slate-900"><?= sanitize($p['name']) ?></h3>
                <p class="text-xs text-slate-500 mt-0.5"><?= sanitize($p['description'] ?? '') ?></p>
              </div>
              <div class="text-right flex-shrink-0 ml-3">
                <p class="text-2xl font-extrabold text-emerald-500"><?= format_currency((float)$p['roi_percent']) ?>%</p>
                <p class="text-xs text-slate-400">ROI</p>
              </div>
            </div>
            <div class="flex gap-3 text-xs text-slate-500 mb-4">
              <span class="bg-slate-50 border border-slate-100 rounded-lg px-2.5 py-1.5">
                 $<?= format_currency((float)$p['min_deposit']) ?> – $<?= format_currency((float)$p['max_deposit']) ?>
              </span>
              <span class="bg-slate-50 border border-slate-100 rounded-lg px-2.5 py-1.5">
                 <?= (int)$p['duration_days'] ?> days
              </span>
            </div>

            <!-- Subscribe Form -->
            <form method="POST" action="vip.php" class="space-y-3"
              onsubmit="return confirm('Subscribe to <?= sanitize(addslashes($p['name'])) ?>?')">
              <?= csrf_field() ?>
              <input type="hidden" name="action"  value="subscribe">
              <input type="hidden" name="plan_id" value="<?= (int)$p['id'] ?>">
              <div class="flex gap-2">
                <input type="number" name="amount" min="<?= (float)$p['min_deposit'] ?>" max="<?= (float)$p['max_deposit'] ?>"
                  step="0.01" required placeholder="Amount (USD)"
                  class="flex-1 bg-white border border-slate-300 text-slate-900 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 placeholder-slate-400">
                <select name="currency"
                  class="bg-white border border-slate-300 text-slate-900 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                  <?php foreach (array_keys($coins) as $coin): ?>
                  <option value="<?= sanitize($coin) ?>"><?= sanitize($coin) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <button type="submit"
                class="w-full bg-emerald-500 hover:bg-emerald-400 text-white font-bold py-2.5 rounded-xl transition text-sm">
                Subscribe Now
              </button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- My Subscriptions -->
    <?php if (!empty($subscriptions)): ?>
    <div>
      <h2 class="font-bold text-slate-900 text-lg mb-4">My Subscriptions</h2>
      <div class="space-y-4">
        <?php foreach ($subscriptions as $sub): ?>
        <?php
          $pnl = $pnlMap[$sub['id']]['total_pnl'] ?? 0;
          $pnlUpdates = $pnlMap[$sub['id']]['updates'] ?? 0;
          $subStatus = $sub['status'];
          $statusClass = match($subStatus) {
            'active'    => 'bg-emerald-50 text-emerald-600',
            'completed' => 'bg-blue-50 text-blue-600',
            'cancelled' => 'bg-slate-100 text-slate-500',
            default     => 'bg-slate-100 text-slate-500',
          };
        ?>
        <div class="bg-white border border-slate-200 rounded-2xl p-5">
          <div class="flex items-start justify-between mb-3">
            <div>
              <h3 class="font-bold text-slate-900"><?= sanitize($sub['plan_name']) ?></h3>
              <p class="text-xs text-slate-500 mt-0.5">
                <?= date('M j, Y', strtotime($sub['start_date'])) ?> → <?= date('M j, Y', strtotime($sub['end_date'])) ?>
              </p>
            </div>
            <span class="text-xs px-2.5 py-1 rounded-full font-medium <?= $statusClass ?> capitalize"><?= sanitize($subStatus) ?></span>
          </div>

          <div class="grid grid-cols-3 gap-3 text-center text-xs mb-3">
            <div class="bg-slate-50 rounded-xl p-2.5">
              <p class="text-slate-500">Invested</p>
              <p class="font-bold text-slate-900 mt-0.5">$<?= format_currency((float)$sub['amount']) ?></p>
              <p class="text-slate-400"><?= sanitize($sub['currency'] ?? 'USDT') ?></p>
            </div>
            <div class="bg-slate-50 rounded-xl p-2.5">
              <p class="text-slate-500">ROI</p>
              <p class="font-bold text-emerald-500 mt-0.5"><?= format_currency((float)$sub['roi_percent']) ?>%</p>
              <p class="text-slate-400"><?= (int)$sub['plan_days'] ?> days</p>
            </div>
            <div class="bg-slate-50 rounded-xl p-2.5">
              <p class="text-slate-500">P&amp;L</p>
              <p class="font-bold mt-0.5 <?= $pnl >= 0 ? 'text-emerald-500' : 'text-red-500' ?>">
                <?= $pnl >= 0 ? '+' : '' ?>$<?= format_currency(abs((float)$pnl)) ?>
              </p>
              <p class="text-slate-400"><?= (int)$pnlUpdates ?> updates</p>
            </div>
          </div>

          <!-- PnL History toggle -->
          <button onclick="togglePnl(<?= (int)$sub['id'] ?>)"
            class="text-xs text-emerald-600 hover:text-emerald-800 font-medium transition">
            View P&amp;L History ▼
          </button>
          <div id="pnl-<?= (int)$sub['id'] ?>" class="hidden mt-3">
            <?php
              $pnlHistory = [];
              try {
                  $hs = db()->prepare('SELECT * FROM vip_pnl_updates WHERE subscription_id = ? ORDER BY created_at DESC');
                  $hs->execute([$sub['id']]);
                  $pnlHistory = $hs->fetchAll();
              } catch (Throwable) {}
            ?>
            <?php if (empty($pnlHistory)): ?>
              <p class="text-xs text-slate-400 py-2">No P&amp;L updates yet.</p>
            <?php else: ?>
            <div class="space-y-1.5">
              <?php foreach ($pnlHistory as $h): ?>
              <div class="flex items-center justify-between text-xs bg-slate-50 rounded-lg px-3 py-2">
                <span class="text-slate-500"><?= date('M j, Y H:i', strtotime($h['created_at'])) ?></span>
                <?php if (!empty($h['note'])): ?>
                <span class="text-slate-500 truncate mx-2"><?= sanitize($h['note']) ?></span>
                <?php endif; ?>
                <span class="font-bold <?= (float)$h['pnl_amount'] >= 0 ? 'text-emerald-600' : 'text-red-500' ?>">
                  <?= (float)$h['pnl_amount'] >= 0 ? '+' : '' ?>$<?= format_currency(abs((float)$h['pnl_amount'])) ?>
                </span>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </main>

  <script>
    function togglePnl(id) {
      const el = document.getElementById('pnl-' + id);
      if (el) el.classList.toggle('hidden');
    }
  </script>

</body>
</html>
