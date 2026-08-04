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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    csrf_verify();
    $name = trim($_POST['name'] ?? '');
    $winRate = (float)($_POST['win_rate'] ?? 0);
    $roi = (float)($_POST['roi_percent'] ?? 0);
    $followers = (int)($_POST['followers_count'] ?? 0);
    $riskType = in_array($_POST['risk_type'] ?? '', ['Low', 'Medium', 'High'], true) ? $_POST['risk_type'] : 'Medium';
    $description = trim($_POST['description'] ?? '');
    $active = isset($_POST['active']) ? 1 : 0;

    if ($name === '') {
        flash('error', 'Trader name is required.');
        redirect('/admin/copy_traders');
    }

    try {
        db()->prepare(
            'INSERT INTO copy_traders (name, win_rate, roi_percent, followers_count, risk_type, description, active)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$name, $winRate, $roi, $followers, $riskType, $description ?: null, $active]);
        flash('success', 'Copy trader created.');
    } catch (Throwable) {
        flash('error', 'Failed to create copy trader.');
    }

    redirect('/admin/copy_traders');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    csrf_verify();
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $winRate = (float)($_POST['win_rate'] ?? 0);
    $roi = (float)($_POST['roi_percent'] ?? 0);
    $followers = (int)($_POST['followers_count'] ?? 0);
    $riskType = in_array($_POST['risk_type'] ?? '', ['Low', 'Medium', 'High'], true) ? $_POST['risk_type'] : 'Medium';
    $description = trim($_POST['description'] ?? '');
    $active = isset($_POST['active']) ? 1 : 0;

    if ($id <= 0 || $name === '') {
        flash('error', 'Invalid trader data.');
        redirect('/admin/copy_traders');
    }

    try {
        db()->prepare(
            'UPDATE copy_traders
             SET name = ?, win_rate = ?, roi_percent = ?, followers_count = ?, risk_type = ?, description = ?, active = ?
             WHERE id = ?'
        )->execute([$name, $winRate, $roi, $followers, $riskType, $description ?: null, $active, $id]);
        flash('success', 'Copy trader updated.');
    } catch (Throwable) {
        flash('error', 'Failed to update copy trader.');
    }

    redirect('/admin/copy_traders');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_verify();
    $id = (int)($_POST['id'] ?? 0);

    try {
        db()->prepare('DELETE FROM copy_traders WHERE id = ?')->execute([$id]);
        flash('success', 'Copy trader deleted.');
    } catch (Throwable) {
        flash('error', 'Failed to delete copy trader.');
    }

    redirect('/admin/copy_traders');
}

$traders = [];
try {
    $traders = db()->query('SELECT * FROM copy_traders ORDER BY created_at DESC')->fetchAll();
} catch (Throwable) {}

$editTrader = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    foreach ($traders as $trader) {
        if ((int)$trader['id'] === $editId) {
            $editTrader = $trader;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/images/favicon.png">
  <title>Copy Traders - CBOE Markets Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-800 text-white min-h-screen">
<div class="flex min-h-screen">
  <?php include __DIR__ . '/_sidebar.php'; ?>

  <main class="flex-1 bg-slate-800 p-4 sm:p-6 lg:p-8 pt-20 lg:pt-8">
    <div class="mb-6 flex items-center justify-between">
      <h1 class="text-2xl font-bold text-white">Copy Traders</h1>
    </div>

    <?php if ($error): ?>
      <div class="mb-4 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-400"><?= sanitize($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="mb-4 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-400"><?= sanitize($success) ?></div>
    <?php endif; ?>

    <div class="grid gap-6 lg:grid-cols-3">
      <section class="rounded-2xl bg-slate-700 p-5">
        <h2 class="mb-4 font-bold text-white"><?= $editTrader ? 'Edit Trader' : 'Add Trader' ?></h2>
        <form method="POST" action="/admin/copy_traders" class="space-y-3">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="<?= $editTrader ? 'edit' : 'add' ?>">
          <?php if ($editTrader): ?>
            <input type="hidden" name="id" value="<?= (int)$editTrader['id'] ?>">
          <?php endif; ?>

          <input type="text" name="name" required value="<?= sanitize($editTrader['name'] ?? '') ?>" placeholder="Trader name"
            class="w-full rounded-lg border border-slate-500 bg-slate-600 px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
          <textarea name="description" rows="3" placeholder="Description"
            class="w-full rounded-lg border border-slate-500 bg-slate-600 px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-emerald-500"><?= sanitize($editTrader['description'] ?? '') ?></textarea>
          <div class="grid grid-cols-2 gap-2">
            <input type="number" name="win_rate" step="0.01" min="0" value="<?= (float)($editTrader['win_rate'] ?? 0) ?>" placeholder="Win rate %"
              class="w-full rounded-lg border border-slate-500 bg-slate-600 px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
            <input type="number" name="roi_percent" step="0.01" min="0" value="<?= (float)($editTrader['roi_percent'] ?? 0) ?>" placeholder="ROI %"
              class="w-full rounded-lg border border-slate-500 bg-slate-600 px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
          </div>
          <div class="grid grid-cols-2 gap-2">
            <input type="number" name="followers_count" min="0" value="<?= (int)($editTrader['followers_count'] ?? 0) ?>" placeholder="Followers"
              class="w-full rounded-lg border border-slate-500 bg-slate-600 px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
            <select name="risk_type" class="w-full rounded-lg border border-slate-500 bg-slate-600 px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
              <?php foreach (['Low', 'Medium', 'High'] as $risk): ?>
              <option value="<?= $risk ?>" <?= ($editTrader['risk_type'] ?? 'Medium') === $risk ? 'selected' : '' ?>><?= $risk ?> Risk</option>
              <?php endforeach; ?>
            </select>
          </div>
          <label class="flex items-center gap-2 text-sm text-slate-300">
            <input type="checkbox" name="active" value="1" class="accent-emerald-500" <?= ($editTrader['active'] ?? 1) ? 'checked' : '' ?>>
            Active
          </label>
          <button type="submit" class="w-full rounded-xl bg-emerald-500 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-400"><?= $editTrader ? 'Update Trader' : 'Create Trader' ?></button>
          <?php if ($editTrader): ?>
            <a href="/admin/copy_traders.php" class="block text-center text-sm text-slate-400 hover:text-white">Cancel</a>
          <?php endif; ?>
        </form>
      </section>

      <section class="lg:col-span-2 overflow-hidden rounded-2xl bg-slate-700">
        <div class="border-b border-slate-600 px-5 py-4">
          <h2 class="font-bold text-white">Published Traders (<?= count($traders) ?>)</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-slate-600/50">
              <tr>
                <th class="px-4 py-3 text-left font-medium text-slate-400">Trader</th>
                <th class="px-4 py-3 text-right font-medium text-slate-400">Win Rate</th>
                <th class="px-4 py-3 text-right font-medium text-slate-400">ROI</th>
                <th class="px-4 py-3 text-right font-medium text-slate-400">Followers</th>
                <th class="px-4 py-3 text-center font-medium text-slate-400">Risk</th>
                <th class="px-4 py-3 text-center font-medium text-slate-400">Active</th>
                <th class="px-4 py-3 text-right font-medium text-slate-400">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($traders)): ?>
                <tr><td colspan="7" class="py-8 text-center text-slate-400">No copy traders yet.</td></tr>
              <?php endif; ?>
              <?php foreach ($traders as $trader): ?>
              <tr class="border-t border-slate-600 hover:bg-slate-600/20">
                <td class="px-4 py-3">
                  <p class="font-semibold text-white"><?= sanitize($trader['name']) ?></p>
                  <p class="text-xs text-slate-400"><?= sanitize((string)($trader['description'] ?? '')) ?></p>
                </td>
                <td class="px-4 py-3 text-right font-semibold text-white"><?= format_currency((float)$trader['win_rate']) ?>%</td>
                <td class="px-4 py-3 text-right font-semibold text-emerald-400"><?= format_currency((float)$trader['roi_percent']) ?>%</td>
                <td class="px-4 py-3 text-right text-white"><?= number_format((int)$trader['followers_count']) ?></td>
                <td class="px-4 py-3 text-center text-slate-300"><?= sanitize($trader['risk_type']) ?></td>
                <td class="px-4 py-3 text-center">
                  <span class="rounded-full px-2 py-0.5 text-xs <?= $trader['active'] ? 'bg-emerald-500/10 text-emerald-400' : 'bg-red-500/10 text-red-400' ?>"><?= $trader['active'] ? 'Yes' : 'No' ?></span>
                </td>
                <td class="px-4 py-3 text-right">
                  <a href="/admin/copy_traders.php?edit=<?= (int)$trader['id'] ?>" class="mr-3 text-xs text-emerald-400 hover:text-emerald-300">Edit</a>
                  <form method="POST" action="/admin/copy_traders" class="inline" onsubmit="return confirm('Delete this trader?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$trader['id'] ?>">
                    <button type="submit" class="text-xs text-red-400 hover:text-red-300">Delete</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </main>
</div>
</body>
</html>