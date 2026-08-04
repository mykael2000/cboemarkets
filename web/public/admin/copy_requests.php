<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/csrf.php';
require_once __DIR__ . '/../../src/helpers.php';

require_admin();
ensure_trading_feature_tables();

$error = get_flash('error');
$success = get_flash('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'review') {
    csrf_verify();
    $requestId = (int)($_POST['id'] ?? 0);
    $status = in_array($_POST['status'] ?? '', ['approved', 'rejected'], true) ? $_POST['status'] : 'rejected';
    $note = trim($_POST['admin_note'] ?? '');

    try {
        $pdo = db();
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'SELECT ctr.*, u.balance AS user_balance
             FROM copy_trade_requests ctr
             JOIN users u ON u.id = ctr.user_id
             WHERE ctr.id = ? AND ctr.status = "pending"
             LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([$requestId]);
        $request = $stmt->fetch();

        if (!$request) {
            $pdo->rollBack();
            flash('error', 'Request not found or already reviewed.');
            redirect('/admin/copy_requests');
        }

        if ($status === 'approved') {
            if ((float)$request['user_balance'] < (float)$request['amount']) {
                $pdo->rollBack();
                flash('error', 'User has insufficient USDT balance for approval.');
                redirect('/admin/copy_requests');
            }

            $pdo->prepare('UPDATE users SET balance = balance - ? WHERE id = ?')->execute([(float)$request['amount'], (int)$request['user_id']]);
            $pdo->prepare(
                'UPDATE copy_trade_requests SET status = "approved", admin_note = ?, approved_at = NOW() WHERE id = ?'
            )->execute([$note ?: null, $requestId]);
        } else {
            $pdo->prepare(
                'UPDATE copy_trade_requests SET status = "rejected", admin_note = ?, approved_at = NOW() WHERE id = ?'
            )->execute([$note ?: null, $requestId]);
        }

        $pdo->commit();
        flash('success', 'Copy request updated.');
    } catch (Throwable) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', 'Failed to review request.');
    }

    redirect('/admin/copy_requests');
}

$filterStatus = $_GET['status'] ?? 'pending';
if (!in_array($filterStatus, ['pending', 'approved', 'rejected', 'all'], true)) {
    $filterStatus = 'pending';
}

$requests = [];
try {
    if ($filterStatus === 'all') {
        $requests = db()->query(
            'SELECT ctr.*, u.name AS user_name, u.email AS user_email, ct.name AS trader_name, ct.risk_type
             FROM copy_trade_requests ctr
             JOIN users u ON u.id = ctr.user_id
             JOIN copy_traders ct ON ct.id = ctr.copy_trader_id
             ORDER BY ctr.status = "pending" DESC, ctr.created_at DESC'
        )->fetchAll();
    } else {
        $stmt = db()->prepare(
            'SELECT ctr.*, u.name AS user_name, u.email AS user_email, ct.name AS trader_name, ct.risk_type
             FROM copy_trade_requests ctr
             JOIN users u ON u.id = ctr.user_id
             JOIN copy_traders ct ON ct.id = ctr.copy_trader_id
             WHERE ctr.status = ?
             ORDER BY ctr.created_at DESC'
        );
        $stmt->execute([$filterStatus]);
        $requests = $stmt->fetchAll();
    }
} catch (Throwable) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/images/favicon.png">
  <title>Copy Requests - CBOE Markets Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-800 text-white min-h-screen">
<div class="flex min-h-screen">
  <?php include __DIR__ . '/_sidebar.php'; ?>

  <main class="flex-1 bg-slate-800 p-4 sm:p-6 lg:p-8 pt-20 lg:pt-8">
    <h1 class="mb-2 text-2xl font-bold text-white">Copy Requests</h1>

    <?php if ($error): ?>
      <div class="mb-4 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-400"><?= sanitize($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="mb-4 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-400"><?= sanitize($success) ?></div>
    <?php endif; ?>

    <div class="mb-6 flex flex-wrap gap-2">
      <?php foreach (['pending', 'approved', 'rejected', 'all'] as $status): ?>
      <a href="/admin/copy_requests.php?status=<?= $status ?>" class="rounded-lg px-3 py-1.5 text-sm font-medium transition <?= $filterStatus === $status ? 'bg-emerald-500 text-white' : 'bg-slate-700 text-slate-300 hover:bg-slate-600' ?>"><?= ucfirst($status) ?></a>
      <?php endforeach; ?>
    </div>

    <div class="overflow-hidden rounded-2xl bg-slate-700">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-slate-600/50">
            <tr>
              <th class="px-4 py-3 text-left font-medium text-slate-400">User</th>
              <th class="px-4 py-3 text-left font-medium text-slate-400">Trader</th>
              <th class="px-4 py-3 text-right font-medium text-slate-400">Amount</th>
              <th class="px-4 py-3 text-center font-medium text-slate-400">Status</th>
              <th class="px-4 py-3 text-left font-medium text-slate-400">Requested</th>
              <th class="px-4 py-3 text-right font-medium text-slate-400">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($requests)): ?>
              <tr><td colspan="6" class="py-8 text-center text-slate-400">No copy requests found.</td></tr>
            <?php endif; ?>
            <?php foreach ($requests as $request): ?>
            <tr class="border-t border-slate-600 align-top hover:bg-slate-600/20">
              <td class="px-4 py-3">
                <p class="font-semibold text-white"><?= sanitize($request['user_name']) ?></p>
                <p class="text-xs text-slate-400"><?= sanitize($request['user_email']) ?></p>
              </td>
              <td class="px-4 py-3">
                <p class="font-semibold text-white"><?= sanitize($request['trader_name']) ?></p>
                <p class="text-xs text-slate-400"><?= sanitize($request['risk_type']) ?> risk</p>
              </td>
              <td class="px-4 py-3 text-right font-semibold text-white">$<?= format_currency((float)$request['amount']) ?></td>
              <td class="px-4 py-3 text-center">
                <span class="rounded-full px-3 py-1 text-xs font-semibold <?= $request['status'] === 'approved' ? 'bg-emerald-500/10 text-emerald-400' : ($request['status'] === 'rejected' ? 'bg-red-500/10 text-red-400' : 'bg-amber-500/10 text-amber-400') ?>"><?= sanitize(ucfirst($request['status'])) ?></span>
              </td>
              <td class="px-4 py-3 text-xs text-slate-400"><?= date('M j, Y H:i', strtotime($request['created_at'])) ?></td>
              <td class="px-4 py-3 text-right">
                <?php if ($request['status'] === 'pending'): ?>
                <form method="POST" action="/admin/copy_requests" class="ml-auto flex w-56 flex-col gap-2">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="review">
                  <input type="hidden" name="id" value="<?= (int)$request['id'] ?>">
                  <input type="text" name="admin_note" placeholder="Optional note" class="rounded border border-slate-500 bg-slate-600 px-2 py-1 text-xs text-white focus:outline-none">
                  <div class="flex gap-2">
                    <button type="submit" name="status" value="approved" class="flex-1 rounded bg-emerald-500/20 px-2 py-1.5 text-xs text-emerald-400 transition hover:bg-emerald-500/30">Approve</button>
                    <button type="submit" name="status" value="rejected" class="flex-1 rounded bg-red-500/20 px-2 py-1.5 text-xs text-red-400 transition hover:bg-red-500/30">Reject</button>
                  </div>
                </form>
                <?php else: ?>
                  <p class="text-xs text-slate-400"><?= sanitize((string)($request['admin_note'] ?? 'Reviewed')) ?></p>
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