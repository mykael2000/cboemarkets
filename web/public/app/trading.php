<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/csrf.php';
require_once __DIR__ . '/../../src/helpers.php';

require_login();
$user = current_user();

$symbols = ['BTCUSDT', 'ETHUSDT', 'SOLUSDT', 'BNBUSDT', 'ADAUSDT', 'XRPUSDT'];
$error   = get_flash('error');
$success = get_flash('success');

function ensure_live_trades_table(): void
{
  db()->exec(
    'CREATE TABLE IF NOT EXISTS live_trades (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      user_id BIGINT UNSIGNED NOT NULL,
      symbol VARCHAR(20) NOT NULL,
      side ENUM("buy","sell") NOT NULL,
      qty DECIMAL(18,8) NOT NULL,
      price_open DECIMAL(18,8) NOT NULL,
      price_close DECIMAL(18,8),
      pnl DECIMAL(18,8),
      margin_locked DECIMAL(18,8) NOT NULL DEFAULT 0.00000000,
      status ENUM("open","closed") NOT NULL DEFAULT "open",
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      closed_at DATETIME,
      INDEX idx_user (user_id),
      INDEX idx_status (status),
      FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB'
  );
}

ensure_live_trades_table();

// Demo mode is disabled: keep trading page in live mode only.
$mode = 'live';

// Handle Place Order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'open') {
    csrf_verify();

    $symbol = strtoupper(trim($_POST['symbol'] ?? 'BTCUSDT'));
    $side   = in_array($_POST['side'] ?? '', ['buy', 'sell'], true) ? $_POST['side'] : 'buy';
    $qty    = (float)($_POST['qty'] ?? 0);

    if (!in_array($symbol, $symbols, true) || $qty <= 0) {
        flash('error', 'Invalid symbol or quantity.');
      redirect('/app/trading.php?mode=' . $mode);
    }

    $priceOpen = price_for_symbol($symbol);
    $notional = $priceOpen * $qty;

    try {
      $pdo = db();

      if ($mode === 'live') {
        $collateral = $notional;
        if ((float)$user['balance'] < $collateral) {
          flash('error', 'Insufficient USDT balance for live order collateral.');
          redirect('/app/trading.php?mode=live');
        }

        $pdo->beginTransaction();
        $pdo->prepare('UPDATE users SET balance = balance - ? WHERE id = ?')->execute([$collateral, $user['id']]);

        $stmt = $pdo->prepare(
          'INSERT INTO live_trades (user_id, symbol, side, qty, price_open, margin_locked, status)
           VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$user['id'], $symbol, $side, $qty, $priceOpen, $collateral, 'open']);
        $pdo->commit();

        flash('success', 'Live order opened at $' . number_format($priceOpen, 2));
      } else {
        $stmt = $pdo->prepare(
          'INSERT INTO demo_trades (user_id, symbol, side, qty, price_open, status)
           VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$user['id'], $symbol, $side, $qty, $priceOpen, 'open']);
        flash('success', 'Demo order placed at $' . number_format($priceOpen, 2));
      }
    } catch (Throwable) {
      if (($pdo ?? null) instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
      }
        flash('error', 'Failed to place order. Please try again.');
    }

    redirect('/app/trading.php?mode=' . $mode);
}

// Handle Close Position
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'close') {
    csrf_verify();

    $tradeId = (int)($_POST['trade_id'] ?? 0);

    try {
        $pdo  = db();
    $table = $mode === 'live' ? 'live_trades' : 'demo_trades';
    $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE id = ? AND user_id = ? AND status = ? LIMIT 1");
    $stmt->execute([$tradeId, $user['id'], 'open']);
        $trade = $stmt->fetch();

        if (!$trade) {
            flash('error', 'Trade not found or already closed.');
      redirect('/app/trading.php?mode=' . $mode);
        }

        $priceClose = price_for_symbol($trade['symbol']);
        $pnl = $trade['side'] === 'buy'
            ? ($priceClose - (float)$trade['price_open']) * (float)$trade['qty']
            : ((float)$trade['price_open'] - $priceClose) * (float)$trade['qty'];

    $pdo->beginTransaction();
    $upd = $pdo->prepare(
      "UPDATE {$table} SET status = ?, price_close = ?, pnl = ?, closed_at = NOW() WHERE id = ?"
    );
    $upd->execute(['closed', $priceClose, $pnl, $tradeId]);

    if ($mode === 'live') {
      $release = (float)($trade['margin_locked'] ?? 0) + $pnl;
      $pdo->prepare('UPDATE users SET balance = balance + ? WHERE id = ?')->execute([$release, $user['id']]);
    }

    $pdo->commit();

        flash('success', sprintf('Position closed. P&L: %s$%.2f', $pnl >= 0 ? '+' : '', $pnl));
    } catch (Throwable) {
    if (($pdo ?? null) instanceof PDO && $pdo->inTransaction()) {
      $pdo->rollBack();
    }
        flash('error', 'Failed to close position.');
    }

  redirect('/app/trading.php?mode=' . $mode);
}

$selectedSymbol = strtoupper(trim($_GET['symbol'] ?? 'BTCUSDT'));
if (!in_array($selectedSymbol, $symbols, true)) {
    $selectedSymbol = 'BTCUSDT';
}
$currentPrice = price_for_symbol($selectedSymbol);

// Fetch open positions
$openTrades = [];
try {
  $activeTable = $mode === 'live' ? 'live_trades' : 'demo_trades';
  $st = db()->prepare("SELECT * FROM {$activeTable} WHERE user_id = ? AND status = ? ORDER BY created_at DESC");
    $st->execute([$user['id'], 'open']);
    $openTrades = $st->fetchAll();
} catch (Throwable) {}

$demoHistory = [];
$liveHistory = [];

try {
  $st = db()->prepare('SELECT * FROM demo_trades WHERE user_id = ? AND status = ? ORDER BY closed_at DESC, created_at DESC LIMIT 20');
  $st->execute([$user['id'], 'closed']);
  $demoHistory = $st->fetchAll();
} catch (Throwable) {}

try {
  $st = db()->prepare('SELECT * FROM live_trades WHERE user_id = ? AND status = ? ORDER BY closed_at DESC, created_at DESC LIMIT 20');
  $st->execute([$user['id'], 'closed']);
  $liveHistory = $st->fetchAll();
} catch (Throwable) {}

$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/images/favicon.png">
  <title>Trading – CBOE Markets</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-white text-slate-900 min-h-screen pb-20">

  <header class="sticky top-0 z-40 bg-white/95 backdrop-blur border-b border-slate-200 px-4 py-3 flex items-center justify-between">
    <span class="text-xl font-extrabold text-emerald-400">Trading</span>
    <span class="bg-emerald-500/15 text-emerald-700 text-xs font-bold px-3 py-1 rounded-full">
      LIVE - Real Balance
    </span>
  </header>

  <main class="max-w-lg mx-auto px-4 py-6 space-y-6">

    <div class="bg-white border border-slate-200 rounded-2xl p-3">
      <div class="grid grid-cols-1 gap-2">
        <?php if (false): ?>
        <a href="trading.php?mode=demo"
          class="text-center rounded-xl py-2.5 text-sm font-semibold transition <?= $mode === 'demo' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
          Demo Trading
        </a>
        <?php endif; ?>
        <a href="trading.php?mode=live"
          class="text-center rounded-xl py-2.5 text-sm font-semibold transition <?= $mode === 'live' ? 'bg-emerald-500 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
          Live Trading
        </a>
      </div>
      <p class="text-xs text-slate-500 mt-2 px-1">
        Live available USDT: <span class="font-semibold text-slate-700">$<?= format_currency((float)($user['balance'] ?? 0)) ?></span>
      </p>
    </div>

    <?php if ($error): ?>
      <div class="bg-red-500/10 border border-red-500/30 text-red-600 text-sm rounded-lg px-4 py-3"><?= sanitize($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 text-sm rounded-lg px-4 py-3"><?= sanitize($success) ?></div>
    <?php endif; ?>

    <!-- Order Form -->
    <div class="bg-white border border-slate-200 rounded-2xl p-5">
      <h2 class="font-bold text-slate-900 mb-4">Place Live Order</h2>

      <form method="POST" action="trading.php?mode=<?= $mode ?>" id="tradeForm" class="space-y-4">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="open">
        <input type="hidden" name="mode" value="<?= $mode ?>">

        <!-- Symbol -->
        <div>
          <label class="block text-sm text-slate-600 mb-1.5">Symbol</label>
          <select name="symbol" id="symbolSelect"
            class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500">
            <?php foreach ($symbols as $sym): ?>
            <option value="<?= $sym ?>" <?= $sym === $selectedSymbol ? 'selected' : '' ?>><?= $sym ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Current Price -->
        <div class="bg-white rounded-lg px-4 py-3 flex items-center justify-between">
          <span class="text-slate-600 text-sm">Current Price</span>
          <span class="text-emerald-400 font-bold text-lg" id="priceDisplay">
            $<?= number_format($currentPrice, 2) ?>
          </span>
        </div>

        <!-- Side toggle -->
        <div>
          <label class="block text-sm text-slate-600 mb-1.5">Side</label>
          <div class="grid grid-cols-2 gap-2">
            <label class="cursor-pointer">
              <input type="radio" name="side" value="buy" class="sr-only peer" checked>
              <div class="peer-checked:bg-emerald-500 peer-checked:border-emerald-500 bg-white border border-slate-300 text-center py-3 rounded-lg font-semibold text-sm transition">
                Buy / Long
              </div>
            </label>
            <label class="cursor-pointer">
              <input type="radio" name="side" value="sell" class="sr-only peer">
              <div class="peer-checked:bg-red-500 peer-checked:border-red-500 bg-white border border-slate-300 text-center py-3 rounded-lg font-semibold text-sm transition">
                Sell / Short
              </div>
            </label>
          </div>
        </div>

        <!-- Quantity -->
        <div>
          <label class="block text-sm text-slate-600 mb-1.5">Quantity</label>
          <input type="number" name="qty" min="0.00001" step="0.00001" value="0.001" required
            class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500">
        </div>

        <button type="submit"
          class="w-full bg-emerald-500 hover:bg-emerald-400 text-white font-bold py-3 rounded-xl transition">
          Place Live Order
        </button>
      </form>
    </div>

    <!-- Open Positions -->
    <?php if (!empty($openTrades)): ?>
    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-200">
        <h2 class="font-bold text-slate-900">Open Positions</h2>
      </div>
      <?php foreach ($openTrades as $trade): ?>
      <?php
        $cp  = price_for_symbol($trade['symbol']);
        $pnl = $trade['side'] === 'buy'
            ? ($cp - (float)$trade['price_open']) * (float)$trade['qty']
            : ((float)$trade['price_open'] - $cp) * (float)$trade['qty'];
        $pnlClass = $pnl >= 0 ? 'text-emerald-400' : 'text-red-400';
      ?>
      <div class="px-5 py-4 border-b border-slate-200 last:border-0"
           data-trade-row="1"
           data-symbol="<?= sanitize($trade['symbol']) ?>"
           data-side="<?= sanitize($trade['side']) ?>"
           data-open="<?= (float)$trade['price_open'] ?>"
           data-qty="<?= (float)$trade['qty'] ?>">
        <div class="flex items-start justify-between">
          <div>
            <span class="font-semibold text-slate-900"><?= sanitize($trade['symbol']) ?></span>
            <span class="ml-2 text-xs px-2 py-0.5 rounded <?= $trade['side']==='buy' ? 'bg-emerald-500/10 text-emerald-700' : 'bg-red-500/20 text-red-400' ?>">
              <?= strtoupper($trade['side']) ?>
            </span>
            <div class="text-slate-600 text-xs mt-1">
              Open: $<?= number_format((float)$trade['price_open'], 2) ?> &middot;
              Qty: <?= (float)$trade['qty'] ?> &middot;
              Now: <span data-now-price="1">$<?= number_format($cp, 2) ?></span>
            </div>
          </div>
          <div class="text-right">
            <p class="font-bold <?= $pnlClass ?>" data-pnl="1">
              <?= $pnl >= 0 ? '+' : '' ?>$<?= format_currency($pnl) ?>
            </p>
            <form method="POST" action="trading.php?mode=<?= $mode ?>" class="mt-2">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="close">
              <input type="hidden" name="mode" value="<?= $mode ?>">
              <input type="hidden" name="trade_id" value="<?= (int)$trade['id'] ?>">
              <button type="submit"
                class="text-xs bg-red-500/20 hover:bg-red-500/40 text-red-400 px-3 py-1 rounded-lg transition">
                Close
              </button>
            </form>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="bg-white border border-slate-200 rounded-2xl p-8 text-center">
      <p class="text-slate-600">No open positions. Place a live order above.</p>
    </div>
    <?php endif; ?>

    <?php if (false): ?>
    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
        <h2 class="font-bold text-slate-900">Demo History</h2>
        <span class="text-xs text-slate-500"><?= count($demoHistory) ?> recent</span>
      </div>
      <?php if (empty($demoHistory)): ?>
        <p class="px-5 py-5 text-sm text-slate-500">No closed demo trades yet.</p>
      <?php else: ?>
        <?php foreach ($demoHistory as $trade): ?>
          <div class="px-5 py-3 border-b border-slate-200 last:border-0 flex items-center justify-between">
            <div>
              <p class="text-sm font-semibold text-slate-900"><?= sanitize($trade['symbol']) ?> <span class="text-xs text-slate-500">/ <?= strtoupper($trade['side']) ?></span></p>
              <p class="text-xs text-slate-500">Opened $<?= number_format((float)$trade['price_open'], 2) ?> -> Closed $<?= number_format((float)$trade['price_close'], 2) ?></p>
            </div>
            <p class="text-sm font-bold <?= (float)$trade['pnl'] >= 0 ? 'text-emerald-600' : 'text-red-500' ?>">
              <?= (float)$trade['pnl'] >= 0 ? '+' : '' ?>$<?= format_currency((float)$trade['pnl']) ?>
            </p>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
        <h2 class="font-bold text-slate-900">Live History</h2>
        <span class="text-xs text-slate-500"><?= count($liveHistory) ?> recent</span>
      </div>
      <?php if (empty($liveHistory)): ?>
        <p class="px-5 py-5 text-sm text-slate-500">No closed live trades yet.</p>
      <?php else: ?>
        <?php foreach ($liveHistory as $trade): ?>
          <div class="px-5 py-3 border-b border-slate-200 last:border-0 flex items-center justify-between">
            <div>
              <p class="text-sm font-semibold text-slate-900"><?= sanitize($trade['symbol']) ?> <span class="text-xs text-slate-500">/ <?= strtoupper($trade['side']) ?></span></p>
              <p class="text-xs text-slate-500">Opened $<?= number_format((float)$trade['price_open'], 2) ?> -> Closed $<?= number_format((float)$trade['price_close'], 2) ?></p>
            </div>
            <p class="text-sm font-bold <?= (float)$trade['pnl'] >= 0 ? 'text-emerald-600' : 'text-red-500' ?>">
              <?= (float)$trade['pnl'] >= 0 ? '+' : '' ?>$<?= format_currency((float)$trade['pnl']) ?>
            </p>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </main>

  <!-- Navigation -->
  <?php $activePage = 'trading.php'; include '_nav.php'; ?>

  <script>
    (function () {
      const symbolSelect = document.getElementById('symbolSelect');
      const priceDisplay = document.getElementById('priceDisplay');

      function fmtUsd(n) {
        return '$' + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      }

      function fetchOne(symbol) {
        return fetch('https://api.binance.com/api/v3/ticker/price?symbol=' + symbol)
          .then((r) => r.json())
          .then((d) => (d && d.price ? parseFloat(d.price) : null));
      }

      function collectSymbols() {
        const symbols = new Set();
        if (symbolSelect && symbolSelect.value) {
          symbols.add(symbolSelect.value);
        }
        document.querySelectorAll('[data-trade-row="1"]').forEach((row) => {
          const sym = row.dataset.symbol || '';
          if (sym) {
            symbols.add(sym);
          }
        });
        return Array.from(symbols);
      }

      async function refreshPrices() {
        const symbols = collectSymbols();
        if (!symbols.length) {
          return;
        }

        const results = await Promise.all(
          symbols.map((sym) =>
            fetchOne(sym)
              .then((price) => ({ sym, price }))
              .catch(() => ({ sym, price: null }))
          )
        );

        const map = {};
        results.forEach((item) => {
          if (item.price !== null) {
            map[item.sym] = item.price;
          }
        });

        if (symbolSelect && map[symbolSelect.value] !== undefined) {
          priceDisplay.textContent = fmtUsd(map[symbolSelect.value]);
        }

        document.querySelectorAll('[data-trade-row="1"]').forEach((row) => {
          const sym = row.dataset.symbol || '';
          const side = row.dataset.side || 'buy';
          const open = parseFloat(row.dataset.open || '0');
          const qty = parseFloat(row.dataset.qty || '0');
          const now = map[sym];
          if (now === undefined) {
            return;
          }

          const nowNode = row.querySelector('[data-now-price="1"]');
          if (nowNode) {
            nowNode.textContent = fmtUsd(now);
          }

          const pnlNode = row.querySelector('[data-pnl="1"]');
          if (pnlNode) {
            const pnl = side === 'buy' ? (now - open) * qty : (open - now) * qty;
            pnlNode.textContent = (pnl >= 0 ? '+' : '') + fmtUsd(Math.abs(pnl));
            pnlNode.classList.remove('text-emerald-400', 'text-red-400');
            pnlNode.classList.add(pnl >= 0 ? 'text-emerald-400' : 'text-red-400');
          }
        });
      }

      if (symbolSelect) {
        symbolSelect.addEventListener('change', refreshPrices);
      }

      refreshPrices();
      setInterval(refreshPrices, 5000);
    })();
  </script>

</body>
</html>

