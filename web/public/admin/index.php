<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/helpers.php';

require_admin();
$user = current_user();

// Fetch stats
$stats = ['users' => 0, 'pending_withdrawals' => 0, 'pending_deposits' => 0, 'active_plans' => 0];
try {
    $pdo = db();
    $stats['users']               = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $stats['pending_withdrawals'] = (int) $pdo->query("SELECT COUNT(*) FROM withdrawal_requests WHERE status='pending'")->fetchColumn();
    $stats['pending_deposits']    = (int) $pdo->query("SELECT COUNT(*) FROM deposit_requests WHERE status='pending'")->fetchColumn();
    $stats['active_plans']        = (int) $pdo->query("SELECT COUNT(*) FROM user_plans WHERE status='active'")->fetchColumn();
} catch (Throwable) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/images/favicon.png">
  <title>Admin Dashboard – CBOE Markets</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-800 text-white min-h-screen">

  <div class="flex min-h-screen">
    <?php include __DIR__ . '/_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 bg-slate-800 p-4 sm:p-6 lg:p-8 pt-20 lg:pt-8">
      <div class="mb-8">
        <h1 class="text-2xl font-bold text-white">Dashboard</h1>
        <p class="text-slate-400 text-sm mt-1">Welcome back, <?= sanitize($user['name']) ?>!</p>
      </div>

      <!-- Stats Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-slate-700 rounded-2xl p-5">
          <p class="text-slate-400 text-sm mb-1">Total Users</p>
          <p class="text-3xl font-extrabold text-white"><?= $stats['users'] ?></p>
        </div>
        <div class="bg-slate-700 rounded-2xl p-5">
          <p class="text-slate-400 text-sm mb-1">Pending Withdrawals</p>
          <p class="text-3xl font-extrabold <?= $stats['pending_withdrawals'] > 0 ? 'text-red-400' : 'text-white' ?>"><?= $stats['pending_withdrawals'] ?></p>
        </div>
        <div class="bg-slate-700 rounded-2xl p-5">
          <p class="text-slate-400 text-sm mb-1">Pending Deposits</p>
          <p class="text-3xl font-extrabold <?= $stats['pending_deposits'] > 0 ? 'text-yellow-400' : 'text-white' ?>"><?= $stats['pending_deposits'] ?></p>
        </div>
        <div class="bg-slate-700 rounded-2xl p-5">
          <p class="text-slate-400 text-sm mb-1">Active Plans</p>
          <p class="text-3xl font-extrabold text-emerald-400"><?= $stats['active_plans'] ?></p>
        </div>
      </div>

      <!-- Quick Links -->
      <div class="grid md:grid-cols-2 gap-4">
        <a href="/admin/add_balances.php" class="bg-slate-700 hover:bg-slate-600 rounded-2xl p-5 transition block">
          <h3 class="font-bold text-white mb-1">Add Balances</h3>
          <p class="text-slate-400 text-sm">Add deposit, profit, auto and copy trading balances</p>
        </a>
        <a href="/admin/deposits.php" class="bg-slate-700 hover:bg-slate-600 rounded-2xl p-5 transition block">
          <h3 class="font-bold text-white mb-1">Review Deposits</h3>
          <p class="text-slate-400 text-sm"><?= $stats['pending_deposits'] ?> pending requests</p>
        </a>
        <a href="/admin/withdrawals.php" class="bg-slate-700 hover:bg-slate-600 rounded-2xl p-5 transition block">
          <h3 class="font-bold text-white mb-1">Review Withdrawals</h3>
          <p class="text-slate-400 text-sm"><?= $stats['pending_withdrawals'] ?> pending requests</p>
        </a>
        <a href="/admin/users.php" class="bg-slate-700 hover:bg-slate-600 rounded-2xl p-5 transition block">
          <h3 class="font-bold text-white mb-1">Manage Users</h3>
          <p class="text-slate-400 text-sm"><?= $stats['users'] ?> total users</p>
        </a>
        <a href="/admin/plans.php" class="bg-slate-700 hover:bg-slate-600 rounded-2xl p-5 transition block">
          <h3 class="font-bold text-white mb-1">Investment Plans</h3>
          <p class="text-slate-400 text-sm">Create and manage plans</p>
        </a>
        <a href="/admin/addresses.php" class="bg-slate-700 hover:bg-slate-600 rounded-2xl p-5 transition block">
          <h3 class="font-bold text-white mb-1">Deposit Addresses</h3>
          <p class="text-slate-400 text-sm">Manage receiving addresses</p>
        </a>
      </div>
    </main>
  </div>

</body>
</html>
