<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/csrf.php';
require_once __DIR__ . '/../../src/helpers.php';

require_admin();

$error   = get_flash('error');
$success = get_flash('success');

// ── Add PnL update ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_pnl') {
    csrf_verify();
    $subId  = (int)($_POST['subscription_id'] ?? 0);
    $pnl    = (float)($_POST['pnl_amount']    ?? 0);
    $note   = trim($_POST['note']             ?? '');

    if ($subId <= 0) {
        flash('error', 'Invalid subscription.');
        redirect('/admin/subscriptions');
    }

    try {
        db()->prepare(
            'INSERT INTO vip_pnl_updates (subscription_id, pnl_amount, note) VALUES (?, ?, ?)'
        )->execute([$subId, $pnl, $note ?: null]);
        flash('success', 'P&L update added.');
    } catch (Throwable) {
        flash('error', 'Failed to add P&L update.');
    }
    redirect('/admin/subscriptions');
}

// ── Update subscription status ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    csrf_verify();
    $subId  = (int)($_POST['id']     ?? 0);
    $status = $_POST['status']       ?? '';
    if (!in_array($status, ['active','completed','cancelled'], true)) {
        flash('error', 'Invalid status.');
        redirect('/admin/subscriptions');
    }
    try {
        db()->prepare('UPDATE user_plans SET status = ? WHERE id = ?')->execute([$status, $subId]);
        flash('success', 'Subscription status updated.');
    } catch (Throwable) {
        flash('error', 'Failed to update status.');
    }
    redirect('/admin/subscriptions');
}

// ── Load subscriptions ───────────────────────────────────────────────────────
$filterStatus = $_GET['status'] ?? 'active';
if (!in_array($filterStatus, ['active','completed','cancelled','all'])) $filterStatus = 'active';

$subscriptions = [];
try {
    if ($filterStatus === 'all') {
        $subscriptions = db()->query(
            'SELECT up.*, u.name AS user_name, u.email AS user_email, ip.name AS plan_name, ip.roi_percent
             FROM user_plans up
             JOIN users u ON u.id = up.user_id
             JOIN investment_plans ip ON ip.id = up.plan_id
             ORDER BY up.created_at DESC'
        )->fetchAll();
    } else {
        $stmt = db()->prepare(
            'SELECT up.*, u.name AS user_name, u.email AS user_email, ip.name AS plan_name, ip.roi_percent
             FROM user_plans up
             JOIN users u ON u.id = up.user_id
             JOIN investment_plans ip ON ip.id = up.plan_id
             WHERE up.status = ?
             ORDER BY up.created_at DESC'
        );
        $stmt->execute([$filterStatus]);
        $subscriptions = $stmt->fetchAll();
    }
} catch (Throwable) {}

// PnL totals per subscription
$pnlMap = [];
try {
    if (!empty($subscriptions)) {
        $idList = array_map(fn($s) => (int)$s['id'], $subscriptions);
        $placeholders = implode(',', array_fill(0, count($idList), '?'));
        $stmt = db()->prepare(
            "SELECT subscription_id, SUM(pnl_amount) AS total_pnl
             FROM vip_pnl_updates WHERE subscription_id IN ({$placeholders})
             GROUP BY subscription_id"
        );
        $stmt->execute($idList);
        foreach ($stmt->fetchAll() as $r) {
            $pnlMap[$r['subscription_id']] = (float)$r['total_pnl'];
        }
    }
} catch (Throwable) {}

// Selected subscription for PnL history
$selectedId = (int)($_GET['sub'] ?? 0);
$pnlHistory = [];
if ($selectedId > 0) {
    try {
        $stmt = db()->prepare('SELECT * FROM vip_pnl_updates WHERE subscription_id = ? ORDER BY created_at DESC');
        $stmt->execute([$selectedId]);
        $pnlHistory = $stmt->fetchAll();
    } catch (Throwable) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/images/favicon.png">
  <title>VIP Subscriptions – CBOE Markets Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-800 text-white min-h-screen">
<div class="flex min-h-screen">
  <?php include __DIR__ . '/_sidebar.php'; ?>

  <main class="flex-1 bg-slate-800 p-4 sm:p-6 lg:p-8 pt-20 lg:pt-8">
    <h1 class="text-2xl font-bold text-white mb-2">VIP Subscriptions</h1>

    <?php if ($error): ?>
      <div class="bg-red-500/10 border border-red-500/30 text-red-400 text-sm rounded-lg px-4 py-3 mb-4"><?= sanitize($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm rounded-lg px-4 py-3 mb-4"><?= sanitize($success) ?></div>
    <?php endif; ?>

    <!-- Status filter -->
    <div class="flex gap-2 flex-wrap mb-6">
      <?php foreach (['active','completed','cancelled','all'] as $s): ?>
      <a href="/admin/subscriptions.php?status=<?= $s ?>"
        class="px-3 py-1.5 rounded-lg text-sm font-medium transition <?= $filterStatus === $s ? 'bg-emerald-500 text-white' : 'bg-slate-700 text-slate-300 hover:bg-slate-600' ?>">
        <?= ucfirst($s) ?>
      </a>
      <?php endforeach; ?>
    </div>

    <?php if ($selectedId > 0): ?>
    <!-- P&L History Panel -->
    <div class="bg-slate-700 rounded-2xl p-5 mb-6">
      <div class="flex items-center justify-between mb-4">
        <h2 class="font-bold text-white">P&amp;L History for Subscription #<?= $selectedId ?></h2>
        <a href="/admin/subscriptions.php?status=<?= sanitize($filterStatus) ?>" class="text-sm text-slate-400 hover:text-white">← Back</a>
      </div>
      <?php if (empty($pnlHistory)): ?>
        <p class="text-slate-400 text-sm">No P&amp;L updates yet for this subscription.</p>
      <?php else: ?>
      <table class="w-full text-sm">
        <thead class="bg-slate-600/50">
          <tr>
            <th class="text-left text-slate-400 font-medium px-4 py-2">Date</th>
            <th class="text-right text-slate-400 font-medium px-4 py-2">P&amp;L</th>
            <th class="text-left text-slate-400 font-medium px-4 py-2">Note</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pnlHistory as $h): ?>
          <tr class="border-t border-slate-600">
            <td class="px-4 py-2 text-slate-400 text-xs"><?= date('M j, Y H:i', strtotime($h['created_at'])) ?></td>
            <td class="px-4 py-2 text-right font-bold <?= (float)$h['pnl_amount'] >= 0 ? 'text-emerald-400' : 'text-red-400' ?>">
              <?= (float)$h['pnl_amount'] >= 0 ? '+' : '' ?>$<?= format_currency(abs((float)$h['pnl_amount'])) ?>
            </td>
            <td class="px-4 py-2 text-slate-400 text-xs"><?= sanitize($h['note'] ?? '') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Subscriptions Table -->
    <div class="bg-slate-700 rounded-2xl overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-slate-600/50">
            <tr>
              <th class="text-left text-slate-400 font-medium px-4 py-3">User</th>
              <th class="text-left text-slate-400 font-medium px-4 py-3">Plan</th>
              <th class="text-right text-slate-400 font-medium px-4 py-3">Amount</th>
              <th class="text-right text-slate-400 font-medium px-4 py-3">P&amp;L</th>
              <th class="text-center text-slate-400 font-medium px-4 py-3">Status</th>
              <th class="text-left text-slate-400 font-medium px-4 py-3">Period</th>
              <th class="text-right text-slate-400 font-medium px-4 py-3">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($subscriptions)): ?>
              <tr><td colspan="7" class="text-center text-slate-400 py-8">No subscriptions found.</td></tr>
            <?php endif; ?>
            <?php foreach ($subscriptions as $sub): ?>
            <?php
              $totalPnl = $pnlMap[$sub['id']] ?? 0;
              $statusColor = match($sub['status']) {
                'active'    => 'text-emerald-400 bg-emerald-500/10',
                'completed' => 'text-blue-400 bg-blue-500/10',
                'cancelled' => 'text-slate-400 bg-slate-600',
                default     => 'text-slate-400 bg-slate-600',
              };
            ?>
            <tr class="border-t border-slate-600 hover:bg-slate-600/20 transition">
              <td class="px-4 py-3">
                <p class="font-semibold text-white"><?= sanitize($sub['user_name']) ?></p>
                <p class="text-xs text-slate-400"><?= sanitize($sub['user_email']) ?></p>
              </td>
              <td class="px-4 py-3">
                <p class="text-white"><?= sanitize($sub['plan_name']) ?></p>
                <p class="text-xs text-slate-400"><?= format_currency((float)$sub['roi_percent']) ?>% ROI</p>
              </td>
              <td class="px-4 py-3 text-right">
                <p class="font-semibold text-white">$<?= format_currency((float)$sub['amount']) ?></p>
                <p class="text-xs text-slate-400"><?= sanitize($sub['currency'] ?? 'USDT') ?></p>
              </td>
              <td class="px-4 py-3 text-right font-bold <?= $totalPnl >= 0 ? 'text-emerald-400' : 'text-red-400' ?>">
                <?= $totalPnl >= 0 ? '+' : '' ?>$<?= format_currency(abs($totalPnl)) ?>
              </td>
              <td class="px-4 py-3 text-center">
                <form method="POST" action="/admin/subscriptions" class="inline-flex items-center gap-1">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="update_status">
                  <input type="hidden" name="id" value="<?= (int)$sub['id'] ?>">
                  <select name="status" onchange="this.form.submit()"
                    class="bg-slate-600 border border-slate-500 text-white rounded-lg px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    <?php foreach (['active','completed','cancelled'] as $s): ?>
                    <option value="<?= $s ?>" <?= $sub['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                  </select>
                </form>
              </td>
              <td class="px-4 py-3 text-xs text-slate-400">
                <?= date('M j', strtotime($sub['start_date'])) ?> – <?= date('M j, Y', strtotime($sub['end_date'])) ?>
              </td>
              <td class="px-4 py-3 text-right">
                <div class="flex items-center gap-2 justify-end">
                  <a href="/admin/subscriptions.php?status=<?= sanitize($filterStatus) ?>&sub=<?= (int)$sub['id'] ?>"
                    class="text-xs text-slate-400 hover:text-white transition">History</a>
                  <!-- Add PnL inline -->
                  <button onclick="togglePnlForm(<?= (int)$sub['id'] ?>)"
                    class="text-xs bg-emerald-500/20 text-emerald-400 hover:bg-emerald-500/30 px-2 py-1 rounded transition">
                    + P&amp;L
                  </button>
                </div>
                <!-- Inline PnL form -->
                <div id="pnl-form-<?= (int)$sub['id'] ?>" class="hidden mt-2">
                  <form method="POST" action="/admin/subscriptions" class="flex flex-col gap-1">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_pnl">
                    <input type="hidden" name="subscription_id" value="<?= (int)$sub['id'] ?>">
                    <input type="number" name="pnl_amount" step="0.01" required placeholder="P&L (neg = loss)"
                      class="bg-slate-600 border border-slate-500 text-white rounded px-2 py-1 text-xs focus:outline-none w-28">
                    <input type="text" name="note" placeholder="Note (optional)"
                      class="bg-slate-600 border border-slate-500 text-white rounded px-2 py-1 text-xs focus:outline-none w-28">
                    <button type="submit" class="text-xs bg-emerald-500 text-white px-2 py-1 rounded transition">Save</button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<script>
  function togglePnlForm(id) {
    const el = document.getElementById('pnl-form-' + id);
    if (el) el.classList.toggle('hidden');
  }
</script>
</body>
</html>
