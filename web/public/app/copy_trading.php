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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request_copy') {
    csrf_verify();
    $traderId = (int)($_POST['copy_trader_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);

    if ($traderId <= 0 || $amount <= 0) {
        flash('error', 'Choose a trader and enter a valid request amount.');
        redirect('copy_trading.php');
    }

    try {
        $stmt = db()->prepare('SELECT * FROM copy_traders WHERE id = ? AND active = 1 LIMIT 1');
        $stmt->execute([$traderId]);
        $trader = $stmt->fetch();

        if (!$trader) {
            flash('error', 'Selected trader is unavailable.');
            redirect('copy_trading.php');
        }

        db()->prepare(
            'INSERT INTO copy_trade_requests (user_id, copy_trader_id, amount, status) VALUES (?, ?, ?, "pending")'
        )->execute([$user['id'], $traderId, $amount]);

        flash('success', 'Copy trading request submitted for admin approval.');
    } catch (Throwable) {
        flash('error', 'Failed to submit copy request.');
    }

    redirect('copy_trading.php');
}

$traders = [];
$requests = [];

try {
    $traders = db()->query('SELECT * FROM copy_traders WHERE active = 1 ORDER BY roi_percent DESC, followers_count DESC')->fetchAll();
} catch (Throwable) {}

try {
    $stmt = db()->prepare(
        'SELECT ctr.*, ct.name AS trader_name, ct.win_rate, ct.roi_percent, ct.followers_count, ct.risk_type
         FROM copy_trade_requests ctr
         JOIN copy_traders ct ON ct.id = ctr.copy_trader_id
         WHERE ctr.user_id = ?
         ORDER BY ctr.created_at DESC'
    );
    $stmt->execute([$user['id']]);
    $requests = $stmt->fetchAll();
} catch (Throwable) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/images/favicon.png">
  <title>Copy Trading - CBOE Markets</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen pb-24 md:pb-6">
  <?php $activePage = 'index.php'; include '_nav.php'; ?>

  <main class="max-w-6xl mx-auto px-4 py-6 space-y-6">
    <section class="rounded-3xl border border-slate-200 bg-gradient-to-br from-violet-50 via-white to-fuchsia-50 p-6">
      <p class="text-xs font-semibold uppercase tracking-[0.25em] text-violet-600">Copy Trading</p>
      <div class="mt-3 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
          <h1 class="text-3xl font-black text-slate-900">Browse trader profiles and submit copy requests</h1>
          <p class="mt-2 max-w-2xl text-sm text-slate-600">Admins publish trader statistics and users request to copy them. Approval is controlled by the admin and funded from the user USDT wallet only after approval.</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
          Wallet USDT: <span class="font-bold text-slate-900">$<?= format_currency((float)$user['balance']) ?></span>
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
        <h2 class="text-xl font-bold text-slate-900">Published Traders</h2>
        <span class="text-sm text-slate-500"><?= count($traders) ?> available</span>
      </div>
      <?php if (empty($traders)): ?>
        <div class="rounded-3xl border border-slate-200 bg-white p-6 text-sm text-slate-500">No traders have been published by admin yet.</div>
      <?php else: ?>
      <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
        <?php foreach ($traders as $trader): ?>
        <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
          <div class="flex items-start justify-between gap-3">
            <div>
              <h3 class="text-lg font-bold text-slate-900"><?= sanitize($trader['name']) ?></h3>
              <p class="mt-1 text-sm text-slate-500"><?= sanitize((string)($trader['description'] ?? 'Published trader profile.')) ?></p>
            </div>
            <span class="rounded-full px-3 py-1 text-xs font-semibold <?= $trader['risk_type'] === 'Low' ? 'bg-emerald-50 text-emerald-600' : ($trader['risk_type'] === 'High' ? 'bg-red-50 text-red-500' : 'bg-amber-50 text-amber-600') ?>"><?= sanitize($trader['risk_type']) ?> Risk</span>
          </div>

          <div class="mt-4 grid grid-cols-3 gap-2 text-center text-sm">
            <div class="rounded-2xl bg-slate-50 p-3">
              <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Win Rate</p>
              <p class="mt-1 font-black text-slate-900"><?= format_currency((float)$trader['win_rate']) ?>%</p>
            </div>
            <div class="rounded-2xl bg-slate-50 p-3">
              <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">ROI</p>
              <p class="mt-1 font-black text-emerald-600"><?= format_currency((float)$trader['roi_percent']) ?>%</p>
            </div>
            <div class="rounded-2xl bg-slate-50 p-3">
              <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Followers</p>
              <p class="mt-1 font-black text-slate-900"><?= number_format((int)$trader['followers_count']) ?></p>
            </div>
          </div>

          <form method="POST" action="copy_trading.php" class="mt-5 space-y-3">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="request_copy">
            <input type="hidden" name="copy_trader_id" value="<?= (int)$trader['id'] ?>">
            <input type="number" name="amount" min="50" step="0.01" required placeholder="Request amount in USDT"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-violet-500">
            <button type="submit" class="w-full rounded-2xl bg-violet-600 px-4 py-3 text-sm font-bold text-white transition hover:bg-violet-500">Request to Copy</button>
          </form>
        </article>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </section>

    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-xl font-bold text-slate-900">Request History</h2>
        <span class="text-sm text-slate-500"><?= count($requests) ?> requests</span>
      </div>
      <?php if (empty($requests)): ?>
        <p class="text-sm text-slate-500">No copy-trading requests yet.</p>
      <?php else: ?>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="border-b border-slate-200 text-left text-slate-500">
                <th class="px-3 py-3 font-medium">Trader</th>
                <th class="px-3 py-3 font-medium text-right">Amount</th>
                <th class="px-3 py-3 font-medium text-center">Status</th>
                <th class="px-3 py-3 font-medium">Admin Note</th>
                <th class="px-3 py-3 font-medium">Requested</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($requests as $request): ?>
              <tr class="border-b border-slate-100">
                <td class="px-3 py-3">
                  <p class="font-semibold text-slate-900"><?= sanitize($request['trader_name']) ?></p>
                  <p class="text-xs text-slate-500"><?= format_currency((float)$request['win_rate']) ?>% WR · <?= sanitize($request['risk_type']) ?> risk</p>
                </td>
                <td class="px-3 py-3 text-right font-semibold text-slate-900">$<?= format_currency((float)$request['amount']) ?></td>
                <td class="px-3 py-3 text-center">
                  <span class="rounded-full px-3 py-1 text-xs font-semibold <?= $request['status'] === 'approved' ? 'bg-emerald-50 text-emerald-600' : ($request['status'] === 'rejected' ? 'bg-red-50 text-red-500' : 'bg-amber-50 text-amber-600') ?>"><?= sanitize(ucfirst($request['status'])) ?></span>
                </td>
                <td class="px-3 py-3 text-xs text-slate-500"><?= sanitize((string)($request['admin_note'] ?? 'Pending review')) ?></td>
                <td class="px-3 py-3 text-xs text-slate-500"><?= date('M j, Y H:i', strtotime($request['created_at'])) ?></td>
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