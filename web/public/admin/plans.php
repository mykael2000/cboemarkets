<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/csrf.php';
require_once __DIR__ . '/../../src/helpers.php';

require_admin();

$error   = get_flash('error');
$success = get_flash('success');

// Handle Add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    csrf_verify();
    $name     = trim($_POST['name'] ?? '');
    $desc     = trim($_POST['description'] ?? '');
    $minD     = (float)($_POST['min_deposit'] ?? 0);
    $maxD     = (float)($_POST['max_deposit'] ?? 0);
    $days     = (int)($_POST['duration_days'] ?? 30);
    $roi      = (float)($_POST['roi_percent'] ?? 0);
    $active   = isset($_POST['active']) ? 1 : 0;

    if ($name === '' || $minD <= 0 || $maxD <= 0 || $days <= 0 || $roi <= 0) {
        flash('error', 'All fields are required and must be positive.');
        redirect($_SERVER['PHP_SELF']);
    }

    try {
        $stmt = db()->prepare(
            'INSERT INTO investment_plans (name, description, min_deposit, max_deposit, duration_days, roi_percent, active)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$name, $desc, $minD, $maxD, $days, $roi, $active]);
        flash('success', 'Plan added successfully.');
    } catch (Throwable) {
        flash('error', 'Failed to add plan.');
    }
    redirect($_SERVER['PHP_SELF']);
}

// Handle Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    csrf_verify();
    $id     = (int)($_POST['id'] ?? 0);
    $name   = trim($_POST['name'] ?? '');
    $desc   = trim($_POST['description'] ?? '');
    $minD   = (float)($_POST['min_deposit'] ?? 0);
    $maxD   = (float)($_POST['max_deposit'] ?? 0);
    $days   = (int)($_POST['duration_days'] ?? 30);
    $roi    = (float)($_POST['roi_percent'] ?? 0);
    $active = isset($_POST['active']) ? 1 : 0;

    if ($id <= 0 || $name === '') {
        flash('error', 'Invalid form data.');
        redirect($_SERVER['PHP_SELF']);
    }

    try {
        $stmt = db()->prepare(
            'UPDATE investment_plans SET name=?, description=?, min_deposit=?, max_deposit=?,
             duration_days=?, roi_percent=?, active=? WHERE id=?'
        );
        $stmt->execute([$name, $desc, $minD, $maxD, $days, $roi, $active, $id]);
        flash('success', 'Plan updated.');
    } catch (Throwable) {
        flash('error', 'Failed to update plan.');
    }
    redirect($_SERVER['PHP_SELF']);
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_verify();
    $id = (int)($_POST['id'] ?? 0);
    try {
        $stmt = db()->prepare('DELETE FROM investment_plans WHERE id = ?');
        $stmt->execute([$id]);
        flash('success', 'Plan deleted.');
    } catch (Throwable) {
        flash('error', 'Failed to delete plan.');
    }
    redirect($_SERVER['PHP_SELF']);
}

$plans = [];
try {
    $plans = db()->query('SELECT * FROM investment_plans ORDER BY created_at DESC')->fetchAll();
} catch (Throwable) {}

$editPlan = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    foreach ($plans as $p) {
        if ((int)$p['id'] === $editId) { $editPlan = $p; break; }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/images/favicon.png">
  <title>Investment Plans – CBOE Markets Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-800 text-white min-h-screen">
<div class="flex min-h-screen">
  <?php include __DIR__ . '/_sidebar.php'; ?>

  <main class="flex-1 bg-slate-800 p-4 sm:p-6 lg:p-8 pt-20 lg:pt-8">
    <div class="mb-6 flex items-center justify-between">
      <h1 class="text-2xl font-bold text-white">Investment Plans</h1>
    </div>

    <?php if ($error): ?>
      <div class="bg-red-500/10 border border-red-500/30 text-red-400 text-sm rounded-lg px-4 py-3 mb-4"><?= sanitize($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm rounded-lg px-4 py-3 mb-4"><?= sanitize($success) ?></div>
    <?php endif; ?>

    <div class="grid lg:grid-cols-3 gap-6">
      <!-- Add / Edit Form -->
      <div class="bg-slate-700 rounded-2xl p-5">
        <h2 class="font-bold text-white mb-4"><?= $editPlan ? 'Edit Plan' : 'Add New Plan' ?></h2>
        <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" class="space-y-3">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="<?= $editPlan ? 'edit' : 'add' ?>">
          <?php if ($editPlan): ?>
            <input type="hidden" name="id" value="<?= (int)$editPlan['id'] ?>">
          <?php endif; ?>

          <div>
            <label class="block text-xs text-slate-400 mb-1">Plan Name</label>
            <input type="text" name="name" required
              value="<?= sanitize($editPlan['name'] ?? '') ?>"
              class="w-full bg-slate-600 border border-slate-500 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
          </div>
          <div>
            <label class="block text-xs text-slate-400 mb-1">Description</label>
            <textarea name="description" rows="2"
              class="w-full bg-slate-600 border border-slate-500 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"><?= sanitize($editPlan['description'] ?? '') ?></textarea>
          </div>
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="block text-xs text-slate-400 mb-1">Min Deposit ($)</label>
              <input type="number" name="min_deposit" min="0" step="0.01" required
                value="<?= (float)($editPlan['min_deposit'] ?? 0) ?>"
                class="w-full bg-slate-600 border border-slate-500 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
            </div>
            <div>
              <label class="block text-xs text-slate-400 mb-1">Max Deposit ($)</label>
              <input type="number" name="max_deposit" min="0" step="0.01" required
                value="<?= (float)($editPlan['max_deposit'] ?? 0) ?>"
                class="w-full bg-slate-600 border border-slate-500 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
            </div>
          </div>
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="block text-xs text-slate-400 mb-1">Duration (days)</label>
              <input type="number" name="duration_days" min="1" required
                value="<?= (int)($editPlan['duration_days'] ?? 30) ?>"
                class="w-full bg-slate-600 border border-slate-500 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
            </div>
            <div>
              <label class="block text-xs text-slate-400 mb-1">ROI (%)</label>
              <input type="number" name="roi_percent" min="0" step="0.01" required
                value="<?= (float)($editPlan['roi_percent'] ?? 0) ?>"
                class="w-full bg-slate-600 border border-slate-500 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
            </div>
          </div>
          <label class="flex items-center gap-2 text-sm text-slate-300 cursor-pointer">
            <input type="checkbox" name="active" value="1" class="accent-emerald-500"
              <?= ($editPlan['active'] ?? 1) ? 'checked' : '' ?>>
            Active
          </label>
          <button type="submit" class="w-full bg-emerald-500 hover:bg-emerald-400 text-white font-bold py-2.5 rounded-xl transition text-sm">
            <?= $editPlan ? 'Update Plan' : 'Add Plan' ?>
          </button>
          <?php if ($editPlan): ?>
            <a href="<?= $_SERVER['PHP_SELF'] ?>" class="block text-center text-slate-400 hover:text-white text-sm mt-1">Cancel</a>
          <?php endif; ?>
        </form>
      </div>

      <!-- Plans Table -->
      <div class="lg:col-span-2 bg-slate-700 rounded-2xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-600">
          <h2 class="font-bold text-white">All Plans (<?= count($plans) ?>)</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-slate-600/50">
              <tr>
                <th class="text-left text-slate-400 font-medium px-4 py-3">Name</th>
                <th class="text-right text-slate-400 font-medium px-4 py-3">ROI</th>
                <th class="text-right text-slate-400 font-medium px-4 py-3">Days</th>
                <th class="text-center text-slate-400 font-medium px-4 py-3">Active</th>
                <th class="text-right text-slate-400 font-medium px-4 py-3">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($plans)): ?>
                <tr><td colspan="5" class="text-center text-slate-400 py-8">No plans yet.</td></tr>
              <?php endif; ?>
              <?php foreach ($plans as $p): ?>
              <tr class="border-t border-slate-600 hover:bg-slate-600/30 transition">
                <td class="px-4 py-3">
                  <p class="font-semibold text-white"><?= sanitize($p['name']) ?></p>
                  <p class="text-xs text-slate-400">$<?= format_currency((float)$p['min_deposit']) ?> – $<?= format_currency((float)$p['max_deposit']) ?></p>
                </td>
                <td class="px-4 py-3 text-right font-bold text-emerald-400"><?= format_currency((float)$p['roi_percent']) ?>%</td>
                <td class="px-4 py-3 text-right text-white"><?= (int)$p['duration_days'] ?></td>
                <td class="px-4 py-3 text-center">
                  <span class="<?= $p['active'] ? 'text-emerald-400 bg-emerald-500/10' : 'text-red-400 bg-red-500/10' ?> text-xs px-2 py-0.5 rounded-full">
                    <?= $p['active'] ? 'Yes' : 'No' ?>
                  </span>
                </td>
                <td class="px-4 py-3 text-right">
                  <a href="<?= $_SERVER['PHP_SELF'] ?>?edit=<?= (int)$p['id'] ?>" class="text-emerald-400 hover:text-emerald-300 text-xs mr-3">Edit</a>
                  <form method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" class="inline" onsubmit="return confirm('Delete this plan?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <button type="submit" class="text-red-400 hover:text-red-300 text-xs">Delete</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>
</body>
</html>
