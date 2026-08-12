<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/csrf.php';
require_once __DIR__ . '/../../src/helpers.php';

require_admin();

function ensure_stock_market_table(): void
{
    db()->exec(
        'CREATE TABLE IF NOT EXISTS market_stocks (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            symbol VARCHAR(20) NOT NULL UNIQUE,
            company VARCHAR(120) NOT NULL,
            sector VARCHAR(80) NOT NULL DEFAULT "Other",
            price DECIMAL(18,2) NOT NULL DEFAULT 0.00,
            change_pct DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB'
    );
}

ensure_stock_market_table();

$error = get_flash('error');
$success = get_flash('success');

$users = db()->query('SELECT id, name, email FROM users ORDER BY created_at DESC')->fetchAll() ?: [];
$marketStocks = db()->query('SELECT * FROM market_stocks ORDER BY company ASC')->fetchAll() ?: [];
$positions = db()->query(
    'SELECT sp.*, u.name AS user_name, u.email AS user_email
     FROM stock_positions sp
     JOIN users u ON u.id = sp.user_id
     ORDER BY u.name ASC, sp.symbol ASC'
)->fetchAll() ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'add_stock' || $action === 'edit_stock') {
        $id = (int)($_POST['id'] ?? 0);
        $symbol = strtoupper(trim((string)($_POST['symbol'] ?? '')));
        $company = trim((string)($_POST['company'] ?? ''));
        $sector = trim((string)($_POST['sector'] ?? 'Other'));
        $price = (float)($_POST['price'] ?? 0);
        $change = (float)($_POST['change_pct'] ?? 0);
        $active = isset($_POST['active']) ? 1 : 0;

        if ($symbol === '' || $company === '' || $price < 0) {
            flash('error', 'Symbol, company and price are required.');
            redirect('/admin/stocks');
        }

        try {
            if ($action === 'add_stock') {
                db()->prepare(
                    'INSERT INTO market_stocks (symbol, company, sector, price, change_pct, active) VALUES (?, ?, ?, ?, ?, ?)'
                )->execute([$symbol, $company, $sector ?: 'Other', $price, $change, $active]);
                flash('success', 'Stock added successfully.');
            } else {
                db()->prepare(
                    'UPDATE market_stocks SET symbol=?, company=?, sector=?, price=?, change_pct=?, active=? WHERE id=?'
                )->execute([$symbol, $company, $sector ?: 'Other', $price, $change, $active, $id]);
                flash('success', 'Stock updated successfully.');
            }
        } catch (Throwable $e) {
            flash('error', 'Failed to save stock. ' . $e->getMessage());
        }
        redirect('/admin/stocks');
    }

    if ($action === 'delete_stock') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            db()->prepare('DELETE FROM market_stocks WHERE id = ?')->execute([$id]);
            flash('success', 'Stock removed.');
        } catch (Throwable) {
            flash('error', 'Failed to remove stock.');
        }
        redirect('/admin/stocks');
    }

    if ($action === 'add_position' || $action === 'update_position' || $action === 'delete_position') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $symbol = strtoupper(trim((string)($_POST['symbol'] ?? '')));
        $company = trim((string)($_POST['company'] ?? ''));
        $quantity = (float)($_POST['quantity'] ?? 0);
        $avgCost = (float)($_POST['avg_cost'] ?? 0);
        $positionId = (int)($_POST['position_id'] ?? 0);

        if ($action === 'delete_position') {
            try {
                db()->prepare('DELETE FROM stock_positions WHERE id = ?')->execute([$positionId]);
                flash('success', 'Position deleted.');
            } catch (Throwable) {
                flash('error', 'Failed to delete position.');
            }
            redirect('/admin/stocks');
        }

        if ($userId <= 0 || $symbol === '' || $quantity <= 0 || $avgCost < 0) {
            flash('error', 'Please provide a valid user, symbol, quantity and average cost.');
            redirect('/admin/stocks');
        }

        try {
            $pdo = db();
            $pdo->beginTransaction();

            $existing = $pdo->prepare('SELECT id, quantity, avg_cost FROM stock_positions WHERE user_id = ? AND symbol = ? LIMIT 1 FOR UPDATE');
            $existing->execute([$userId, $symbol]);
            $row = $existing->fetch();

            if ($action === 'add_position') {
                if ($row) {
                    $newQty = (float)$row['quantity'] + $quantity;
                    $newAvg = (((float)$row['quantity'] * (float)$row['avg_cost']) + ($quantity * $avgCost)) / $newQty;
                    $pdo->prepare('UPDATE stock_positions SET quantity = ?, avg_cost = ?, company = ?, updated_at = NOW() WHERE id = ?')
                        ->execute([$newQty, $newAvg, $company ?: $row['company'] ?? $symbol, $row['id']]);
                } else {
                    $pdo->prepare('INSERT INTO stock_positions (user_id, symbol, company, quantity, avg_cost) VALUES (?, ?, ?, ?, ?)')
                        ->execute([$userId, $symbol, $company ?: $symbol, $quantity, $avgCost]);
                }
                flash('success', 'User position added successfully.');
            } else {
                $pdo->prepare('UPDATE stock_positions SET quantity = ?, avg_cost = ?, company = ?, updated_at = NOW() WHERE id = ?')
                    ->execute([$quantity, $avgCost, $company ?: $symbol, $positionId]);
                flash('success', 'Position updated successfully.');
            }

            $pdo->commit();
        } catch (Throwable $e) {
            try {
                if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } catch (Throwable) {}
            flash('error', 'Failed to save user position. ' . $e->getMessage());
        }

        redirect('/admin/stocks');
    }
}

$editStock = null;
if (isset($_GET['edit_stock'])) {
    $editId = (int)$_GET['edit_stock'];
    foreach ($marketStocks as $stock) {
        if ((int)$stock['id'] === $editId) {
            $editStock = $stock;
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
  <title>Stocks – CBOE Markets Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-800 text-white min-h-screen">
<div class="flex min-h-screen">
  <?php include __DIR__ . '/_sidebar.php'; ?>

  <main class="flex-1 bg-slate-800 p-4 sm:p-6 lg:p-8 pt-20 lg:pt-8">
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-white">Stocks</h1>
      <p class="text-slate-400 text-sm mt-1">Manage market stocks, prices, and user stock positions.</p>
    </div>

    <?php if ($error): ?>
      <div class="bg-red-500/10 border border-red-500/30 text-red-400 text-sm rounded-lg px-4 py-3 mb-4"><?= sanitize($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm rounded-lg px-4 py-3 mb-4"><?= sanitize($success) ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
      <section class="bg-slate-700 rounded-2xl p-5">
        <h2 class="font-bold text-white mb-4"><?= $editStock ? 'Edit Stock' : 'Add Stock' ?></h2>
        <form method="POST" action="/admin/stocks" class="space-y-3">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="<?= $editStock ? 'edit_stock' : 'add_stock' ?>">
          <?php if ($editStock): ?>
            <input type="hidden" name="id" value="<?= (int)$editStock['id'] ?>">
          <?php endif; ?>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs text-slate-300 mb-1">Symbol</label>
              <input type="text" name="symbol" required value="<?= sanitize($editStock['symbol'] ?? '') ?>" class="w-full bg-slate-600 border border-slate-500 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
            </div>
            <div>
              <label class="block text-xs text-slate-300 mb-1">Price</label>
              <input type="number" name="price" min="0" step="0.01" required value="<?= number_format((float)($editStock['price'] ?? 0), 2, '.', '') ?>" class="w-full bg-slate-600 border border-slate-500 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
            </div>
          </div>

          <div>
            <label class="block text-xs text-slate-300 mb-1">Company</label>
            <input type="text" name="company" required value="<?= sanitize($editStock['company'] ?? '') ?>" class="w-full bg-slate-600 border border-slate-500 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs text-slate-300 mb-1">Sector</label>
              <input type="text" name="sector" value="<?= sanitize($editStock['sector'] ?? 'Technology') ?>" class="w-full bg-slate-600 border border-slate-500 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
            </div>
            <div>
              <label class="block text-xs text-slate-300 mb-1">Change %</label>
              <input type="number" name="change_pct" step="0.01" value="<?= number_format((float)($editStock['change_pct'] ?? 0), 2, '.', '') ?>" class="w-full bg-slate-600 border border-slate-500 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
            </div>
          </div>

          <label class="flex items-center gap-2 text-sm text-slate-300 cursor-pointer">
            <input type="checkbox" name="active" value="1" class="accent-emerald-500" <?= ($editStock['active'] ?? 1) ? 'checked' : '' ?>>
            Active in market
          </label>

          <button type="submit" class="w-full bg-emerald-500 hover:bg-emerald-400 text-white font-bold py-2.5 rounded-xl transition text-sm">
            <?= $editStock ? 'Update Stock' : 'Add Stock' ?>
          </button>
          <?php if ($editStock): ?>
            <a href="/admin/stocks" class="block text-center text-slate-400 hover:text-white text-sm mt-1">Cancel</a>
          <?php endif; ?>
        </form>
      </section>

      <section class="bg-slate-700 rounded-2xl p-5">
        <h2 class="font-bold text-white mb-4">Add Stock to User</h2>
        <form method="POST" action="/admin/stocks" class="space-y-3">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add_position">

          <div>
            <label class="block text-xs text-slate-300 mb-1">User</label>
            <select name="user_id" required class="w-full bg-slate-600 border border-slate-500 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
              <option value="">Select user</option>
              <?php foreach ($users as $u): ?>
                <option value="<?= (int)$u['id'] ?>"><?= sanitize($u['name']) ?> (<?= sanitize($u['email']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs text-slate-300 mb-1">Symbol</label>
              <select name="symbol" required class="w-full bg-slate-600 border border-slate-500 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                <?php foreach ($marketStocks as $stock): ?>
                  <option value="<?= sanitize($stock['symbol']) ?>"><?= sanitize($stock['symbol']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="block text-xs text-slate-300 mb-1">Quantity</label>
              <input type="number" name="quantity" min="0.00000001" step="0.00000001" required class="w-full bg-slate-600 border border-slate-500 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
            </div>
          </div>

          <div>
            <label class="block text-xs text-slate-300 mb-1">Company</label>
            <input type="text" name="company" placeholder="Optional company override" class="w-full bg-slate-600 border border-slate-500 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
          </div>

          <div>
            <label class="block text-xs text-slate-300 mb-1">Average Cost</label>
            <input type="number" name="avg_cost" min="0" step="0.01" required class="w-full bg-slate-600 border border-slate-500 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
          </div>

          <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-2.5 rounded-xl transition text-sm">
            Add to User Holdings
          </button>
        </form>
      </section>
    </div>

    <section class="mt-6 bg-slate-700 rounded-2xl overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-600">
        <h2 class="font-bold text-white">Market Stocks</h2>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-slate-600/50">
            <tr>
              <th class="text-left px-4 py-3 text-slate-400">Symbol</th>
              <th class="text-left px-4 py-3 text-slate-400">Company</th>
              <th class="text-right px-4 py-3 text-slate-400">Price</th>
              <th class="text-right px-4 py-3 text-slate-400">Change</th>
              <th class="text-center px-4 py-3 text-slate-400">Active</th>
              <th class="text-right px-4 py-3 text-slate-400">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($marketStocks)): ?>
              <tr><td colspan="6" class="text-center text-slate-400 py-8">No market stocks added yet.</td></tr>
            <?php else: ?>
              <?php foreach ($marketStocks as $stock): ?>
                <tr class="border-t border-slate-600 hover:bg-slate-600/20 transition">
                  <td class="px-4 py-3 font-semibold text-white"><?= sanitize($stock['symbol']) ?></td>
                  <td class="px-4 py-3 text-white"><?= sanitize($stock['company']) ?></td>
                  <td class="px-4 py-3 text-right text-white">$<?= format_currency((float)$stock['price'], 2) ?></td>
                  <td class="px-4 py-3 text-right <?= (float)$stock['change_pct'] >= 0 ? 'text-emerald-400' : 'text-red-400' ?>"><?= (float)$stock['change_pct'] >= 0 ? '+' : '' ?><?= format_currency((float)$stock['change_pct'], 2) ?>%</td>
                  <td class="px-4 py-3 text-center">
                    <span class="text-xs px-2 py-0.5 rounded-full <?= (int)$stock['active'] ? 'text-emerald-400 bg-emerald-500/10' : 'text-red-400 bg-red-500/10' ?>">
                      <?= (int)$stock['active'] ? 'Yes' : 'No' ?>
                    </span>
                  </td>
                  <td class="px-4 py-3 text-right">
                    <div class="flex justify-end gap-2">
                      <a href="/admin/stocks?edit_stock=<?= (int)$stock['id'] ?>" class="text-xs bg-slate-600 hover:bg-slate-500 text-white px-2 py-1 rounded transition">Edit</a>
                      <form method="POST" action="/admin/stocks" onsubmit="return confirm('Remove this stock from the market list?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_stock">
                        <input type="hidden" name="id" value="<?= (int)$stock['id'] ?>">
                        <button type="submit" class="text-xs bg-red-500/20 text-red-300 hover:bg-red-500/30 px-2 py-1 rounded transition">Delete</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="mt-6 bg-slate-700 rounded-2xl overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-600">
        <h2 class="font-bold text-white">User Stock Positions</h2>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-slate-600/50">
            <tr>
              <th class="text-left px-4 py-3 text-slate-400">User</th>
              <th class="text-left px-4 py-3 text-slate-400">Symbol</th>
              <th class="text-left px-4 py-3 text-slate-400">Company</th>
              <th class="text-right px-4 py-3 text-slate-400">Qty</th>
              <th class="text-right px-4 py-3 text-slate-400">Avg Cost</th>
              <th class="text-right px-4 py-3 text-slate-400">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($positions)): ?>
              <tr><td colspan="6" class="text-center text-slate-400 py-8">No user positions yet.</td></tr>
            <?php else: ?>
              <?php foreach ($positions as $p): ?>
                <tr class="border-t border-slate-600 hover:bg-slate-600/20 transition">
                  <td class="px-4 py-3 text-white"><?= sanitize($p['user_name']) ?> <span class="text-slate-400 text-xs">(<?= sanitize($p['user_email']) ?>)</span></td>
                  <td class="px-4 py-3 text-white font-semibold"><?= sanitize($p['symbol']) ?></td>
                  <td class="px-4 py-3 text-white"><?= sanitize($p['company']) ?></td>
                  <td class="px-4 py-3 text-right text-white">
                    <form method="POST" action="/admin/stocks" class="flex justify-end gap-2">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="update_position">
                      <input type="hidden" name="position_id" value="<?= (int)$p['id'] ?>">
                      <input type="number" name="quantity" step="0.00000001" min="0" value="<?= number_format((float)$p['quantity'], 8, '.', '') ?>" class="w-24 bg-slate-600 border border-slate-500 text-white rounded px-2 py-1 text-right text-xs focus:outline-none focus:ring-1 focus:ring-emerald-500">
                  </td>
                  <td class="px-4 py-3 text-right text-white">
                      <input type="number" name="avg_cost" step="0.01" min="0" value="<?= number_format((float)$p['avg_cost'], 2, '.', '') ?>" class="w-24 bg-slate-600 border border-slate-500 text-white rounded px-2 py-1 text-right text-xs focus:outline-none focus:ring-1 focus:ring-emerald-500">
                  </td>
                  <td class="px-4 py-3 text-right">
                      <div class="flex justify-end gap-2">
                        <button type="submit" class="text-xs bg-emerald-500/20 text-emerald-300 hover:bg-emerald-500/30 px-2 py-1 rounded transition">Save</button>
                    </form>
                        <form method="POST" action="/admin/stocks" onsubmit="return confirm('Delete this user stock position?');">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="delete_position">
                          <input type="hidden" name="position_id" value="<?= (int)$p['id'] ?>">
                          <button type="submit" class="text-xs bg-red-500/20 text-red-300 hover:bg-red-500/30 px-2 py-1 rounded transition">Delete</button>
                        </form>
                      </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</div>
</body>
</html>
