<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/csrf.php';
require_once __DIR__ . '/../../src/helpers.php';

require_admin();

$error = get_flash('error');
$success = get_flash('success');

function ensure_user_dashboard_metric_columns(): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    try {
        db()->exec('ALTER TABLE users ADD COLUMN IF NOT EXISTS dashboard_equity DECIMAL(18,8) NOT NULL DEFAULT 0.00000000');
    } catch (Throwable) {
        // Keep admin page functional even if schema update cannot be applied.
    }

    $ensured = true;
}

ensure_user_dashboard_metric_columns();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    csrf_verify();

    $id = (int)($_POST['id'] ?? 0);
    $status = in_array($_POST['status'] ?? '', ['approved', 'rejected'], true)
        ? $_POST['status']
        : null;
    $note = trim($_POST['admin_note'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);

    if ($id <= 0 || $status === null) {
        flash('error', 'Invalid request.');
        redirect('/admin/deposits');
    }

    if ($status === 'approved' && $amount <= 0) {
        flash('error', 'Approved deposits require an amount greater than zero.');
        redirect('/admin/deposits');
    }

    try {
        $pdo = db();
        $pdo->beginTransaction();

        $req = $pdo->prepare('SELECT * FROM deposit_requests WHERE id = ? FOR UPDATE');
        $req->execute([$id]);
        $deposit = $req->fetch();

        if (!$deposit) {
            $pdo->rollBack();
            flash('error', 'Deposit request not found.');
            redirect('/admin/deposits');
        }

        if ($deposit['status'] !== 'pending') {
            $pdo->rollBack();
            flash('error', 'This deposit request was already reviewed.');
            redirect('/admin/deposits');
        }

        if ($status === 'approved') {
            $pdo->prepare('UPDATE deposit_requests SET amount = ?, status = ?, admin_note = ? WHERE id = ?')
                ->execute([$amount, 'approved', $note ?: null, $id]);

            $assetToColumn = [
                'USDT' => 'balance',
                'BTC'  => 'btc_balance',
                'ETH'  => 'eth_balance',
                'BNB'  => 'bnb_balance',
                'SOL'  => 'sol_balance',
            ];
            $assetTicker = strtoupper(trim((string)($deposit['asset_ticker'] ?? 'USDT')));
            $coinColumn  = $assetToColumn[$assetTicker] ?? 'balance';
            $usdValue    = $assetTicker === 'USDT' ? $amount : ($amount * price_for_symbol($assetTicker . 'USDT'));

            $pdo->prepare('UPDATE users SET ' . $coinColumn . ' = ' . $coinColumn . ' + ?, dashboard_equity = dashboard_equity + ? WHERE id = ?')
                ->execute([$amount, $usdValue, (int)$deposit['user_id']]);

            flash('success', 'Deposit approved and user ' . $assetTicker . ' wallet credited.');
        } else {
            $pdo->prepare('UPDATE deposit_requests SET status = ?, admin_note = ? WHERE id = ?')
                ->execute(['rejected', $note ?: null, $id]);

            flash('success', 'Deposit request rejected.');
        }

        $pdo->commit();
    } catch (Throwable) {
        try {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
        } catch (Throwable) {
        }
        flash('error', 'Failed to process deposit request.');
    }

    redirect('/admin/deposits');
}

$deposits = [];
try {
    $deposits = db()->query(
        'SELECT dr.*, u.name AS user_name, u.email AS user_email
         FROM deposit_requests dr
         JOIN users u ON u.id = dr.user_id
         ORDER BY dr.status = "pending" DESC, dr.created_at DESC'
    )->fetchAll();
} catch (Throwable) {
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/images/favicon.png">
  <title>Deposit Requests - CBOE Markets Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-800 text-white min-h-screen">
<div class="flex min-h-screen">
  <?php include __DIR__ . '/_sidebar.php'; ?>

  <main class="flex-1 bg-slate-800 p-4 sm:p-6 lg:p-8 pt-20 lg:pt-8">
    <h1 class="text-2xl font-bold text-white mb-6">Deposit Requests</h1>

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
              <th class="text-left text-slate-400 font-medium px-4 py-3">Asset</th>
              <th class="text-left text-slate-400 font-medium px-4 py-3">Receiving Address</th>
              <th class="text-right text-slate-400 font-medium px-4 py-3">Amount</th>
              <th class="text-center text-slate-400 font-medium px-4 py-3">Status</th>
              <th class="text-left text-slate-400 font-medium px-4 py-3">Date</th>
              <th class="text-right text-slate-400 font-medium px-4 py-3">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($deposits)): ?>
              <tr><td colspan="7" class="text-center text-slate-400 py-8">No deposit requests.</td></tr>
            <?php endif; ?>
            <?php foreach ($deposits as $d): ?>
            <?php
              $statusColors = [
                'pending'  => 'text-yellow-400 bg-yellow-500/10',
                'approved' => 'text-emerald-400 bg-emerald-500/10',
                'rejected' => 'text-red-400 bg-red-500/10',
              ];
              $sc = $statusColors[$d['status']] ?? 'text-slate-400';
            ?>
            <tr class="border-t border-slate-600">
              <td class="px-4 py-3">
                <p class="font-semibold text-white text-sm"><?= sanitize($d['user_name']) ?></p>
                <p class="text-xs text-slate-400"><?= sanitize($d['user_email']) ?></p>
              </td>
              <td class="px-4 py-3 font-bold text-emerald-400"><?= sanitize($d['asset_ticker']) ?></td>
              <td class="px-4 py-3 font-mono text-xs text-slate-300 max-w-xs truncate"><?= sanitize((string)($d['address'] ?? '')) ?></td>
              <td class="px-4 py-3 text-right font-bold text-white">$<?= format_currency((float)$d['amount']) ?></td>
              <td class="px-4 py-3 text-center">
                <span class="text-xs px-2 py-0.5 rounded-full <?= $sc ?>"><?= ucfirst($d['status']) ?></span>
              </td>
              <td class="px-4 py-3 text-xs text-slate-400"><?= date('M j, Y H:i', strtotime($d['created_at'])) ?></td>
              <td class="px-4 py-3">
                <?php if ($d['status'] === 'pending'): ?>
                <div class="flex flex-col gap-2 items-end">
                  <form method="POST" action="/admin/deposits" class="flex flex-col gap-1 w-56">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                    <input type="number" name="amount" min="0.00000001" step="0.00000001"
                      class="bg-slate-600 border border-slate-500 text-white rounded px-2 py-1 text-xs text-right focus:outline-none focus:ring-1 focus:ring-emerald-500"
                      placeholder="Amount to credit" required>
                    <input type="text" name="admin_note"
                      class="bg-slate-600 border border-slate-500 text-white rounded px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-emerald-500"
                      placeholder="Optional note">
                    <div class="flex gap-1">
                      <button type="submit" name="status" value="approved"
                        class="flex-1 bg-emerald-500/20 hover:bg-emerald-500/40 text-emerald-400 text-xs py-1.5 rounded transition">Approve</button>
                      <button type="submit" name="status" value="rejected"
                        class="flex-1 bg-red-500/20 hover:bg-red-500/40 text-red-400 text-xs py-1.5 rounded transition">Reject</button>
                    </div>
                  </form>
                </div>
                <?php else: ?>
                  <p class="text-xs text-slate-500 text-right"><?= $d['admin_note'] ? sanitize($d['admin_note']) : '-' ?></p>
                <?php endif; ?>
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
