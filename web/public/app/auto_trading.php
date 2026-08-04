<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/csrf.php';
require_once __DIR__ . '/../../src/helpers.php';

require_login();
ensure_trading_feature_tables();

$user = current_user();
$error = get_flash('error');
$success = get_flash('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'allocate') {
    csrf_verify();
    $planId = (int)($_POST['plan_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);

    if ($planId <= 0 || $amount <= 0) {
        flash('error', 'Select a plan and enter a valid allocation amount.');
        redirect('auto_trading.php');
    }

    try {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT * FROM investment_plans WHERE id = ? AND active = 1 LIMIT 1');
        $stmt->execute([$planId]);
        $plan = $stmt->fetch();

        if (!$plan) {
            flash('error', 'Selected plan is unavailable.');
            redirect('auto_trading.php');
        }
        if ($amount < (float)$plan['min_deposit'] || $amount > (float)$plan['max_deposit']) {
            flash('error', 'Allocation must fit the plan limits.');
            redirect('auto_trading.php');
        }
        if ((float)$user['balance'] < $amount) {
            flash('error', 'Insufficient USDT balance for this allocation.');
            redirect('auto_trading.php');
        }

        $pdo->beginTransaction();
        $pdo->prepare('UPDATE users SET balance = balance - ? WHERE id = ?')->execute([$amount, $user['id']]);
        $pdo->prepare(
            'INSERT INTO auto_trading_allocations (user_id, plan_id, amount, roi_percent, duration_days, status, approved_at)
             VALUES (?, ?, ?, ?, ?, "active", NOW())'
        )->execute([$user['id'], $planId, $amount, (float)$plan['roi_percent'], (int)$plan['duration_days']]);
        $pdo->commit();

        flash('success', 'USDT allocation added to auto trading.');
    } catch (Throwable) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', 'Failed to allocate balance. Please try again.');
    }

    redirect('auto_trading.php');
}

$plans = [];
$allocations = [];
$allocatedTotal = 0.0;

try {
    $plans = db()->query('SELECT * FROM investment_plans WHERE active = 1 ORDER BY min_deposit ASC')->fetchAll();
} catch (Throwable) {}

try {
    $stmt = db()->prepare(
        'SELECT ata.*, ip.name AS plan_name, ip.description
         FROM auto_trading_allocations ata
         JOIN investment_plans ip ON ip.id = ata.plan_id
         WHERE ata.user_id = ?
         ORDER BY ata.created_at DESC'
    );
    $stmt->execute([$user['id']]);
    $allocations = $stmt->fetchAll();
    foreach ($allocations as $allocation) {
        if ($allocation['status'] === 'active') {
            $allocatedTotal += (float)$allocation['amount'];
        }
    }
} catch (Throwable) {}

$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/images/favicon.png">
  <title>Auto Trading - CBOE Markets</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen pb-24 md:pb-6">
  <?php $activePage = 'index.php'; include '_nav.php'; ?>

  <main class="max-w-6xl mx-auto px-4 py-6 space-y-6">
    <section class="rounded-3xl border border-slate-200 bg-gradient-to-br from-emerald-50 via-white to-teal-50 p-6">
      <p class="text-xs font-semibold uppercase tracking-[0.25em] text-emerald-600">Auto Trading</p>
      <div class="mt-3 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
          <h1 class="text-3xl font-black text-slate-900">Allocate USDT into managed strategy plans</h1>
          <p class="mt-2 max-w-2xl text-sm text-slate-600">Users transfer balance from their USDT wallet into selected plans. Each allocation is recorded in history with ROI and plan duration snapshots.</p>
        </div>
        <div class="grid grid-cols-2 gap-3 text-sm md:w-[360px]">
          <div class="rounded-2xl border border-slate-200 bg-white p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Wallet USDT</p>
            <p class="mt-2 text-2xl font-black text-slate-900">$<?= format_currency((float)$user['balance']) ?></p>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-white p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Allocated</p>
            <p class="mt-2 text-2xl font-black text-emerald-600">$<?= format_currency($allocatedTotal) ?></p>
          </div>
        </div>
      </div>
    </section>

    <?php if ($error): ?>
      <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600"><?= sanitize($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= sanitize($success) ?></div>
    <?php endif; ?>

    <section>
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-xl font-bold text-slate-900">Available Plans</h2>
        <span class="text-sm text-slate-500"><?= count($plans) ?> active plans</span>
      </div>

      <div class="grid gap-4 lg:grid-cols-3">
        <?php foreach ($plans as $plan): ?>
        <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
          <div class="flex items-start justify-between gap-3">
            <div>
              <h3 class="text-lg font-bold text-slate-900"><?= sanitize($plan['name']) ?></h3>
              <p class="mt-1 text-sm text-slate-500"><?= sanitize((string)($plan['description'] ?? 'Managed allocation plan.')) ?></p>
            </div>
            <div class="rounded-2xl bg-emerald-50 px-3 py-2 text-right">
              <p class="text-xl font-black text-emerald-600"><?= format_currency((float)$plan['roi_percent']) ?>%</p>
              <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-500">ROI</p>
            </div>
          </div>
          <div class="mt-4 flex flex-wrap gap-2 text-xs text-slate-500">
            <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1">$<?= format_currency((float)$plan['min_deposit']) ?> min</span>
            <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1">$<?= format_currency((float)$plan['max_deposit']) ?> max</span>
            <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1"><?= (int)$plan['duration_days'] ?> days</span>
          </div>
          <form method="POST" action="auto_trading.php" class="mt-5 space-y-3">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="allocate">
            <input type="hidden" name="plan_id" value="<?= (int)$plan['id'] ?>">
            <input type="number" name="amount" min="<?= (float)$plan['min_deposit'] ?>" max="<?= (float)$plan['max_deposit'] ?>" step="0.01" required placeholder="Allocate from USDT"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500">
            <button type="submit" class="w-full rounded-2xl bg-emerald-500 px-4 py-3 text-sm font-bold text-white transition hover:bg-emerald-400">Allocate Balance</button>
          </form>
        </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-xl font-bold text-slate-900">Allocation History</h2>
        <span class="text-sm text-slate-500"><?= count($allocations) ?> records</span>
      </div>
      <?php if (empty($allocations)): ?>
        <p class="text-sm text-slate-500">No auto-trading allocations yet.</p>
      <?php else: ?>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="border-b border-slate-200 text-left text-slate-500">
                <th class="px-3 py-3 font-medium">Plan</th>
                <th class="px-3 py-3 font-medium text-right">Amount</th>
                <th class="px-3 py-3 font-medium text-right">ROI</th>
                <th class="px-3 py-3 font-medium text-center">Status</th>
                <th class="px-3 py-3 font-medium">Created</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($allocations as $allocation): ?>
              <tr class="border-b border-slate-100">
                <td class="px-3 py-3">
                  <p class="font-semibold text-slate-900"><?= sanitize($allocation['plan_name']) ?></p>
                  <p class="text-xs text-slate-500"><?= (int)$allocation['duration_days'] ?> days</p>
                </td>
                <td class="px-3 py-3 text-right font-semibold text-slate-900">$<?= format_currency((float)$allocation['amount']) ?></td>
                <td class="px-3 py-3 text-right font-semibold text-emerald-600"><?= format_currency((float)$allocation['roi_percent']) ?>%</td>
                <td class="px-3 py-3 text-center">
                  <span class="rounded-full px-3 py-1 text-xs font-semibold <?= $allocation['status'] === 'active' ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-600' ?>"><?= sanitize(ucfirst($allocation['status'])) ?></span>
                </td>
                <td class="px-3 py-3 text-xs text-slate-500"><?= date('M j, Y H:i', strtotime($allocation['created_at'])) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>