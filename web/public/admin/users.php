<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/csrf.php';
require_once __DIR__ . '/../../src/helpers.php';

require_admin();

$error   = get_flash('error');
$success = get_flash('success');

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
    // Ignore here; fallback behavior will still keep the page usable.
  }

  $ensured = true;
}

ensure_user_dashboard_metric_columns();

// Toggle active/disabled
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_status') {
    csrf_verify();
    $id = (int)($_POST['id'] ?? 0);
    try {
        $pdo  = db();
        $stmt = $pdo->prepare('SELECT status FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $u = $stmt->fetch();
        if ($u) {
            $newStatus = $u['status'] === 'active' ? 'disabled' : 'active';
            $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$newStatus, $id]);
            flash('success', 'User status updated to ' . $newStatus . '.');
        }
    } catch (Throwable) {
        flash('error', 'Failed to update user status.');
    }
    redirect('/admin/users');
}

// Toggle role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_role') {
    csrf_verify();
    $id = (int)($_POST['id'] ?? 0);
    // Prevent self-demotion
    if ($id === (int)($_SESSION['user_id'] ?? 0)) {
        flash('error', 'You cannot change your own role.');
        redirect('/admin/users');
    }
    try {
        $pdo  = db();
        $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $u = $stmt->fetch();
        if ($u) {
            $newRole = $u['role'] === 'admin' ? 'user' : 'admin';
            $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$newRole, $id]);
            flash('success', 'User role updated to ' . $newRole . '.');
        }
    } catch (Throwable) {
        flash('error', 'Failed to update user role.');
    }
    redirect('/admin/users');
}

// Update balance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_balance') {
    csrf_verify();
    $id         = (int)($_POST['id'] ?? 0);
    $balance    = (float)($_POST['balance']     ?? 0);
    $btcBalance = (float)($_POST['btc_balance'] ?? 0);
    $ethBalance = (float)($_POST['eth_balance'] ?? 0);
    $bnbBalance = (float)($_POST['bnb_balance'] ?? 0);
    $solBalance = (float)($_POST['sol_balance'] ?? 0);
  $todayPnl   = (float)($_POST['dashboard_today_pnl'] ?? 0);
  $equity     = (float)($_POST['dashboard_equity'] ?? 0);
  $margin     = (float)($_POST['dashboard_margin'] ?? 0);
  $freeMargin = (float)($_POST['dashboard_free_margin'] ?? 0);

  if ($id <= 0 || $balance < 0 || $btcBalance < 0 || $ethBalance < 0 || $bnbBalance < 0 || $solBalance < 0 || $equity < 0 || $margin < 0 || $freeMargin < 0) {
        flash('error', 'Invalid balance value.');
        redirect('/admin/users');
    }
    try {
        db()->prepare(
      'UPDATE users
       SET balance = ?, btc_balance = ?, eth_balance = ?, bnb_balance = ?, sol_balance = ?,
         dashboard_today_pnl = ?, dashboard_equity = ?, dashboard_margin = ?, dashboard_free_margin = ?
       WHERE id = ?'
    )->execute([$balance, $btcBalance, $ethBalance, $bnbBalance, $solBalance, $todayPnl, $equity, $margin, $freeMargin, $id]);
    flash('success', 'Balances and dashboard stats updated.');
    } catch (Throwable) {
    flash('error', 'Failed to update balances and dashboard stats.');
    }
    redirect('/admin/users');
}

$users = [];
try {
    $users = db()->query('SELECT * FROM users ORDER BY created_at DESC')->fetchAll();
} catch (Throwable) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/images/favicon.png">
  <title>User Management – CBOE Markets Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-800 text-white min-h-screen">
<div class="flex min-h-screen">
  <?php include __DIR__ . '/_sidebar.php'; ?>

  <main class="flex-1 bg-slate-800 p-4 sm:p-6 lg:p-8 pt-20 lg:pt-8">
    <h1 class="text-2xl font-bold text-white mb-6">Users (<?= count($users) ?>)</h1>

    <?php if ($error): ?>
      <div class="bg-red-500/10 border border-red-500/30 text-red-400 text-sm rounded-lg px-4 py-3 mb-4"><?= sanitize($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm rounded-lg px-4 py-3 mb-4"><?= sanitize($success) ?></div>
    <?php endif; ?>

    <div class="bg-slate-700 rounded-2xl overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-slate-600/50">
            <tr>
              <th class="text-left text-slate-400 font-medium px-4 py-3">User</th>
              <th class="text-center text-slate-400 font-medium px-4 py-3">Role</th>
              <th class="text-center text-slate-400 font-medium px-4 py-3">Status</th>
              <th class="text-right text-slate-400 font-medium px-4 py-3">Balances + Today PnL / Total Deposit / Auto Trading Allocated / Copy Trading Allocated</th>
              <th class="text-left text-slate-400 font-medium px-4 py-3">Joined</th>
              <th class="text-right text-slate-400 font-medium px-4 py-3">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($users)): ?>
              <tr><td colspan="6" class="text-center text-slate-400 py-8">No users found.</td></tr>
            <?php endif; ?>
            <?php foreach ($users as $u): ?>
            <tr class="border-t border-slate-600 hover:bg-slate-600/20 transition">
              <td class="px-4 py-3">
                <p class="font-semibold text-white"><?= sanitize($u['name']) ?></p>
                <p class="text-xs text-slate-400"><?= sanitize($u['email']) ?></p>
              </td>
              <td class="px-4 py-3 text-center">
                <span class="text-xs px-2 py-0.5 rounded-full <?= $u['role']==='admin' ? 'text-purple-400 bg-purple-500/10' : 'text-slate-300 bg-slate-600' ?>">
                  <?= ucfirst($u['role']) ?>
                </span>
              </td>
              <td class="px-4 py-3 text-center">
                <span class="text-xs px-2 py-0.5 rounded-full <?= $u['status']==='active' ? 'text-emerald-400 bg-emerald-500/10' : 'text-red-400 bg-red-500/10' ?>">
                  <?= ucfirst($u['status']) ?>
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <form method="POST" action="/admin/users" class="flex flex-wrap items-center justify-end gap-1">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="update_balance">
                  <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                  <input type="number" name="balance" step="0.00000001" min="0"
                    value="<?= sanitize(number_format((float)$u['balance'], 8, '.', '')) ?>"
                    title="USDT Balance"
                    class="w-24 bg-slate-600 border border-slate-500 text-white rounded px-2 py-1 text-xs text-right focus:outline-none focus:ring-1 focus:ring-teal-500"
                    placeholder="USDT">
                  <input type="number" name="btc_balance" step="0.00000001" min="0"
                    value="<?= sanitize(number_format((float)($u['btc_balance'] ?? 0), 8, '.', '')) ?>"
                    title="BTC Balance"
                    class="w-24 bg-slate-600 border border-slate-500 text-white rounded px-2 py-1 text-xs text-right focus:outline-none focus:ring-1 focus:ring-orange-500"
                    placeholder="BTC">
                  <input type="number" name="eth_balance" step="0.00000001" min="0"
                    value="<?= sanitize(number_format((float)($u['eth_balance'] ?? 0), 8, '.', '')) ?>"
                    title="ETH Balance"
                    class="w-24 bg-slate-600 border border-slate-500 text-white rounded px-2 py-1 text-xs text-right focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    placeholder="ETH">
                  <input type="number" name="bnb_balance" step="0.00000001" min="0"
                    value="<?= sanitize(number_format((float)($u['bnb_balance'] ?? 0), 8, '.', '')) ?>"
                    title="BNB Balance"
                    class="w-24 bg-slate-600 border border-slate-500 text-white rounded px-2 py-1 text-xs text-right focus:outline-none focus:ring-1 focus:ring-yellow-500"
                    placeholder="BNB">
                  <input type="number" name="sol_balance" step="0.00000001" min="0"
                    value="<?= sanitize(number_format((float)($u['sol_balance'] ?? 0), 8, '.', '')) ?>"
                    title="SOL Balance"
                    class="w-24 bg-slate-600 border border-slate-500 text-white rounded px-2 py-1 text-xs text-right focus:outline-none focus:ring-1 focus:ring-purple-500"
                    placeholder="SOL">
                  <input type="number" name="dashboard_today_pnl" step="0.00000001"
                    value="<?= sanitize(number_format((float)($u['dashboard_today_pnl'] ?? 0), 8, '.', '')) ?>"
                    title="Today's PnL"
                    class="w-24 bg-slate-600 border border-slate-500 text-white rounded px-2 py-1 text-xs text-right focus:outline-none focus:ring-1 focus:ring-red-500"
                    placeholder="Today PnL">
                  <input type="number" name="dashboard_equity" step="0.00000001" min="0"
                    value="<?= sanitize(number_format((float)($u['dashboard_equity'] ?? 0), 8, '.', '')) ?>"
                    title="Total Deposit"
                    class="w-24 bg-slate-600 border border-slate-500 text-white rounded px-2 py-1 text-xs text-right focus:outline-none focus:ring-1 focus:ring-emerald-500"
                    placeholder="Total Deposit">
                  <input type="number" name="dashboard_margin" step="0.00000001" min="0"
                    value="<?= sanitize(number_format((float)($u['dashboard_margin'] ?? 0), 8, '.', '')) ?>"
                    title="Auto Trading Allocated"
                    class="w-24 bg-slate-600 border border-slate-500 text-white rounded px-2 py-1 text-xs text-right focus:outline-none focus:ring-1 focus:ring-emerald-500"
                    placeholder="Auto Trading">
                  <input type="number" name="dashboard_free_margin" step="0.00000001" min="0"
                    value="<?= sanitize(number_format((float)($u['dashboard_free_margin'] ?? 0), 8, '.', '')) ?>"
                    title="Copy Trading Allocated"
                    class="w-24 bg-slate-600 border border-slate-500 text-white rounded px-2 py-1 text-xs text-right focus:outline-none focus:ring-1 focus:ring-cyan-500"
                    placeholder="Copy Trading">
                  <button type="submit" class="text-emerald-400 hover:text-emerald-300 text-xs bg-emerald-500/10 px-2 py-1 rounded transition">Set</button>
                </form>
              </td>
              <td class="px-4 py-3 text-xs text-slate-400"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
              <td class="px-4 py-3">
                <div class="flex gap-2 justify-end">
                  <!-- Toggle Status -->
                  <form method="POST" action="/admin/users">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <button type="submit"
                      class="text-xs px-2 py-1 rounded transition <?= $u['status']==='active' ? 'bg-red-500/20 text-red-400 hover:bg-red-500/30' : 'bg-emerald-500/20 text-emerald-400 hover:bg-emerald-500/30' ?>">
                      <?= $u['status']==='active' ? 'Disable' : 'Enable' ?>
                    </button>
                  </form>
                  <!-- Toggle Role -->
                  <?php if ((int)$u['id'] !== (int)($_SESSION['user_id'] ?? 0)): ?>
                  <form method="POST" action="/admin/users">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle_role">
                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <button type="submit"
                      class="text-xs px-2 py-1 rounded transition bg-purple-500/20 text-purple-400 hover:bg-purple-500/30">
                      <?= $u['role']==='admin' ? '→ User' : '→ Admin' ?>
                    </button>
                  </form>
                  <?php endif; ?>
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
</body>
</html>
