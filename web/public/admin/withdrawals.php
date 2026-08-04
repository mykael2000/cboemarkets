<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/csrf.php';
require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/email.php';

require_admin();

$error   = get_flash('error');
$success = get_flash('success');

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    csrf_verify();

    $id     = (int)($_POST['id'] ?? 0);
    $status = in_array($_POST['status'] ?? '', ['approved', 'rejected'], true)
              ? $_POST['status'] : null;
    $note   = trim($_POST['admin_note'] ?? '');

    if ($id <= 0 || !$status) {
        flash('error', 'Invalid request.');
        redirect('/admin/withdrawals');
    }

    try {
        $pdo  = db();
        $stmt = $pdo->prepare('UPDATE withdrawal_requests SET status=?, admin_note=? WHERE id=?');
        $stmt->execute([$status, $note, $id]);

        // Fetch user details for email
        $wReq = $pdo->prepare(
            'SELECT wr.*, u.email, u.name FROM withdrawal_requests wr
             JOIN users u ON u.id = wr.user_id WHERE wr.id = ? LIMIT 1'
        );
        $wReq->execute([$id]);
        $wr = $wReq->fetch();

        if ($wr) {
            try {
                send_withdrawal_status_email(
                    $wr['email'], $wr['name'], $status,
                    (string)$wr['amount'], $wr['asset_ticker'], $note
                );
            } catch (Throwable) {}
        }

        flash('success', 'Withdrawal request ' . $status . '.');
    } catch (Throwable) {
        flash('error', 'Failed to update withdrawal.');
    }
    redirect('/admin/withdrawals');
}

$withdrawals = [];
try {
    $withdrawals = db()->query(
        'SELECT wr.*, u.name AS user_name, u.email AS user_email
         FROM withdrawal_requests wr
         JOIN users u ON u.id = wr.user_id
         ORDER BY wr.status = "pending" DESC, wr.created_at DESC'
    )->fetchAll();
} catch (Throwable) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/images/favicon.png">
  <title>Withdrawal Requests – CBOE Markets Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-800 text-white min-h-screen">
<div class="flex min-h-screen">
  <?php include __DIR__ . '/_sidebar.php'; ?>

  <main class="flex-1 bg-slate-800 p-4 sm:p-6 lg:p-8 pt-20 lg:pt-8">
    <h1 class="text-2xl font-bold text-white mb-6">Withdrawal Requests</h1>

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
              <th class="text-right text-slate-400 font-medium px-4 py-3">Amount</th>
              <th class="text-left text-slate-400 font-medium px-4 py-3">Address</th>
              <th class="text-center text-slate-400 font-medium px-4 py-3">Status</th>
              <th class="text-left text-slate-400 font-medium px-4 py-3">Date</th>
              <th class="text-right text-slate-400 font-medium px-4 py-3">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($withdrawals)): ?>
              <tr><td colspan="7" class="text-center text-slate-400 py-8">No withdrawal requests.</td></tr>
            <?php endif; ?>
            <?php foreach ($withdrawals as $w): ?>
            <?php
              $statusColors = [
                'pending'  => 'text-yellow-400 bg-yellow-500/10',
                'approved' => 'text-emerald-400 bg-emerald-500/10',
                'rejected' => 'text-red-400 bg-red-500/10',
              ];
              $sc = $statusColors[$w['status']] ?? 'text-slate-400';
            ?>
            <tr class="border-t border-slate-600">
              <td class="px-4 py-3">
                <p class="font-semibold text-white text-sm"><?= sanitize($w['user_name']) ?></p>
                <p class="text-xs text-slate-400"><?= sanitize($w['user_email']) ?></p>
              </td>
              <td class="px-4 py-3 font-bold text-emerald-400"><?= sanitize($w['asset_ticker']) ?></td>
              <td class="px-4 py-3 text-right font-bold text-white">$<?= format_currency((float)$w['amount']) ?></td>
              <td class="px-4 py-3 font-mono text-xs text-slate-300 max-w-xs truncate"><?= sanitize($w['address']) ?></td>
              <td class="px-4 py-3 text-center">
                <span class="text-xs px-2 py-0.5 rounded-full <?= $sc ?>"><?= ucfirst($w['status']) ?></span>
              </td>
              <td class="px-4 py-3 text-xs text-slate-400"><?= date('M j, Y', strtotime($w['created_at'])) ?></td>
              <td class="px-4 py-3">
                <?php if ($w['status'] === 'pending'): ?>
                <div class="flex flex-col gap-2 items-end">
                  <form method="POST" action="/admin/withdrawals" class="flex flex-col gap-1 w-48">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?= (int)$w['id'] ?>">
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
                  <p class="text-xs text-slate-500 text-right"><?= $w['admin_note'] ? sanitize($w['admin_note']) : '—' ?></p>
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
