<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/csrf.php';
require_once __DIR__ . '/../../src/helpers.php';

require_admin();

$error   = get_flash('error');
$success = get_flash('success');

function ensure_deposit_addresses_table(): void
{
  db()->exec(
    'CREATE TABLE IF NOT EXISTS deposit_addresses (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      asset_ticker VARCHAR(20) NOT NULL,
      address VARCHAR(255) NOT NULL,
      network VARCHAR(50) NOT NULL,
      active TINYINT(1) NOT NULL DEFAULT 1,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB'
  );
}

ensure_deposit_addresses_table();

$adminBase = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/admin/addresses.php'), '/');
$addressesPage = $adminBase . '/addresses.php';

// Handle Add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    csrf_verify();
    $ticker  = strtoupper(trim($_POST['asset_ticker'] ?? ''));
    $address = trim($_POST['address'] ?? '');
    $network = trim($_POST['network']  ?? '');
    $active  = isset($_POST['active']) ? 1 : 0;

    if ($ticker === '' || $address === '' || $network === '') {
        flash('error', 'All fields are required.');
      redirect($addressesPage);
    }

    try {
        $stmt = db()->prepare(
            'INSERT INTO deposit_addresses (asset_ticker, address, network, active) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$ticker, $address, $network, $active]);
        flash('success', 'Address added.');
    } catch (Throwable) {
        flash('error', 'Failed to add address.');
    }
    redirect($addressesPage);
}

// Handle Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    csrf_verify();
    $id      = (int)($_POST['id'] ?? 0);
    $ticker  = strtoupper(trim($_POST['asset_ticker'] ?? ''));
    $address = trim($_POST['address'] ?? '');
    $network = trim($_POST['network']  ?? '');
    $active  = isset($_POST['active']) ? 1 : 0;

    if ($id <= 0 || $ticker === '' || $address === '') {
        flash('error', 'Invalid form data.');
      redirect($addressesPage);
    }

    try {
        $stmt = db()->prepare(
            'UPDATE deposit_addresses SET asset_ticker=?, address=?, network=?, active=? WHERE id=?'
        );
        $stmt->execute([$ticker, $address, $network, $active, $id]);
        flash('success', 'Address updated.');
    } catch (Throwable) {
        flash('error', 'Failed to update address.');
    }
    redirect($addressesPage);
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_verify();
    $id = (int)($_POST['id'] ?? 0);
    try {
        $stmt = db()->prepare('DELETE FROM deposit_addresses WHERE id = ?');
        $stmt->execute([$id]);
        flash('success', 'Address deleted.');
    } catch (Throwable) {
        flash('error', 'Failed to delete address.');
    }
    redirect($addressesPage);
}

$addresses = [];
try {
    $addresses = db()->query('SELECT * FROM deposit_addresses ORDER BY asset_ticker, created_at DESC')->fetchAll();
} catch (Throwable) {}

$editAddr = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    foreach ($addresses as $a) {
        if ((int)$a['id'] === $editId) { $editAddr = $a; break; }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/images/favicon.png">
  <title>Deposit Addresses – CBOE Markets Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-800 text-white min-h-screen">
<div class="flex min-h-screen">
  <?php include __DIR__ . '/_sidebar.php'; ?>

  <main class="flex-1 bg-slate-800 p-4 sm:p-6 lg:p-8 pt-20 lg:pt-8">
    <h1 class="text-2xl font-bold text-white mb-6">Deposit Addresses</h1>

    <?php if ($error): ?>
      <div class="bg-red-500/10 border border-red-500/30 text-red-400 text-sm rounded-lg px-4 py-3 mb-4"><?= sanitize($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm rounded-lg px-4 py-3 mb-4"><?= sanitize($success) ?></div>
    <?php endif; ?>

    <div class="grid lg:grid-cols-3 gap-6">
      <!-- Add / Edit Form -->
      <div class="bg-slate-700 rounded-2xl p-5">
        <h2 class="font-bold text-white mb-4"><?= $editAddr ? 'Edit Address' : 'Add Address' ?></h2>
        <form method="POST" action="<?= sanitize($addressesPage) ?>" class="space-y-3">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="<?= $editAddr ? 'edit' : 'add' ?>">
          <?php if ($editAddr): ?>
            <input type="hidden" name="id" value="<?= (int)$editAddr['id'] ?>">
          <?php endif; ?>

          <div>
            <label class="block text-xs text-slate-400 mb-1">Asset Ticker (e.g., BTC)</label>
            <input type="text" name="asset_ticker" required maxlength="20"
              value="<?= sanitize($editAddr['asset_ticker'] ?? '') ?>"
              class="w-full bg-slate-600 border border-slate-500 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 uppercase"
              placeholder="BTC">
          </div>
          <div>
            <label class="block text-xs text-slate-400 mb-1">Wallet Address</label>
            <input type="text" name="address" required
              value="<?= sanitize($editAddr['address'] ?? '') ?>"
              class="w-full bg-slate-600 border border-slate-500 text-white rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-emerald-500"
              placeholder="Wallet address">
          </div>
          <div>
            <label class="block text-xs text-slate-400 mb-1">Network</label>
            <input type="text" name="network" required
              value="<?= sanitize($editAddr['network'] ?? '') ?>"
              class="w-full bg-slate-600 border border-slate-500 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
              placeholder="e.g., Bitcoin, TRC20, ERC20">
          </div>
          <label class="flex items-center gap-2 text-sm text-slate-300 cursor-pointer">
            <input type="checkbox" name="active" value="1" class="accent-emerald-500"
              <?= ($editAddr['active'] ?? 1) ? 'checked' : '' ?>>
            Active
          </label>
          <button type="submit" class="w-full bg-emerald-500 hover:bg-emerald-400 text-white font-bold py-2.5 rounded-xl transition text-sm">
            <?= $editAddr ? 'Update Address' : 'Add Address' ?>
          </button>
          <?php if ($editAddr): ?>
            <a href="/admin/addresses.php" class="block text-center text-slate-400 hover:text-white text-sm mt-1">Cancel</a>
          <?php endif; ?>
        </form>
      </div>

      <!-- Addresses Table -->
      <div class="lg:col-span-2 bg-slate-700 rounded-2xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-600">
          <h2 class="font-bold text-white">All Addresses (<?= count($addresses) ?>)</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-slate-600/50">
              <tr>
                <th class="text-left text-slate-400 font-medium px-4 py-3">Asset</th>
                <th class="text-left text-slate-400 font-medium px-4 py-3">Address</th>
                <th class="text-left text-slate-400 font-medium px-4 py-3">Network</th>
                <th class="text-center text-slate-400 font-medium px-4 py-3">Active</th>
                <th class="text-right text-slate-400 font-medium px-4 py-3">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($addresses)): ?>
                <tr><td colspan="5" class="text-center text-slate-400 py-8">No addresses yet.</td></tr>
              <?php endif; ?>
              <?php foreach ($addresses as $a): ?>
              <tr class="border-t border-slate-600 hover:bg-slate-600/30 transition">
                <td class="px-4 py-3 font-bold text-emerald-400"><?= sanitize($a['asset_ticker']) ?></td>
                <td class="px-4 py-3 font-mono text-xs text-white max-w-xs truncate"><?= sanitize($a['address']) ?></td>
                <td class="px-4 py-3 text-slate-300 text-xs"><?= sanitize($a['network']) ?></td>
                <td class="px-4 py-3 text-center">
                  <span class="<?= $a['active'] ? 'text-emerald-400 bg-emerald-500/10' : 'text-red-400 bg-red-500/10' ?> text-xs px-2 py-0.5 rounded-full">
                    <?= $a['active'] ? 'Yes' : 'No' ?>
                  </span>
                </td>
                <td class="px-4 py-3 text-right">
                  <a href="/admin/addresses.php?edit=<?= (int)$a['id'] ?>" class="text-emerald-400 hover:text-emerald-300 text-xs mr-3">Edit</a>
                  <form method="POST" action="<?= sanitize($addressesPage) ?>" class="inline" onsubmit="return confirm('Delete this address?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
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
