<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/csrf.php';
require_once __DIR__ . '/../../src/helpers.php';

require_login();
$user = current_user();

function ensure_stock_portfolio_table(): void
{
    db()->exec(
        'CREATE TABLE IF NOT EXISTS stock_positions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            symbol VARCHAR(20) NOT NULL,
            company VARCHAR(100) NOT NULL,
            quantity DECIMAL(18,8) NOT NULL DEFAULT 0.00000000,
            avg_cost DECIMAL(18,2) NOT NULL DEFAULT 0.00,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_symbol (user_id, symbol),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB'
    );
}

function ensure_market_stocks_table(): void
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

ensure_stock_portfolio_table();
ensure_market_stocks_table();

$defaultStocks = [
    ['symbol' => 'AAPL', 'name' => 'Apple', 'sector' => 'Technology'],
    ['symbol' => 'MSFT', 'name' => 'Microsoft', 'sector' => 'Technology'],
    ['symbol' => 'NVDA', 'name' => 'NVIDIA', 'sector' => 'Semiconductors'],
    ['symbol' => 'AMZN', 'name' => 'Amazon', 'sector' => 'E-Commerce'],
    ['symbol' => 'GOOGL', 'name' => 'Alphabet', 'sector' => 'Technology'],
    ['symbol' => 'TSLA', 'name' => 'Tesla', 'sector' => 'Automotive'],
];

$stockRows = db()->query('SELECT * FROM market_stocks WHERE active = 1 ORDER BY company ASC')->fetchAll() ?: [];
if (empty($stockRows)) {
    foreach ($defaultStocks as $stock) {
        $quote = stock_price_for_symbol($stock['symbol']);
        db()->prepare('INSERT INTO market_stocks (symbol, company, sector, price, change_pct, active) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE company=VALUES(company), sector=VALUES(sector), price=VALUES(price), change_pct=VALUES(change_pct), active=VALUES(active)')
            ->execute([$stock['symbol'], $stock['name'], $stock['sector'], $quote['price'], $quote['change'], 1]);
    }
    $stockRows = db()->query('SELECT * FROM market_stocks WHERE active = 1 ORDER BY company ASC')->fetchAll() ?: [];
}

$stocks = [];
foreach ($stockRows as $stockRow) {
    $quote = stock_price_for_symbol($stockRow['symbol']);
    $stocks[] = [
        'symbol' => $stockRow['symbol'],
        'name' => $stockRow['company'],
        'sector' => $stockRow['sector'],
        'price' => (float)($stockRow['price'] ?: $quote['price']),
        'change' => (float)($stockRow['change_pct'] ?: $quote['change']),
    ];
}

if (empty($stocks)) {
    $stocks = $defaultStocks;
    foreach ($stocks as &$stock) {
        $quote = stock_price_for_symbol($stock['symbol']);
        $stock['price'] = $quote['price'];
        $stock['change'] = $quote['change'];
    }
    unset($stock);
}

$positions = [];
try {
    $stmt = db()->prepare('SELECT * FROM stock_positions WHERE user_id = ? ORDER BY updated_at DESC');
    $stmt->execute([$user['id']]);
    $positions = $stmt->fetchAll();
} catch (Throwable) {
    $positions = [];
}

$portfolioValue = 0.0;
$portfolioCost = 0.0;
foreach ($positions as $position) {
    $quote = stock_price_for_symbol($position['symbol']);
    $marketValue = (float)$position['quantity'] * $quote['price'];
    $costValue = (float)$position['quantity'] * (float)$position['avg_cost'];
    $portfolioValue += $marketValue;
    $portfolioCost += $costValue;
}

$portfolioGain = $portfolioValue - $portfolioCost;
$portfolioGainPct = $portfolioCost > 0 ? ($portfolioGain / $portfolioCost) * 100 : 0;

$chartSymbol = strtoupper(trim($_GET['chart_symbol'] ?? 'AAPL'));
$allowedChartSymbols = array_map(static function (array $stock): string { return $stock['symbol']; }, $stocks);
if (!in_array($chartSymbol, $allowedChartSymbols, true)) {
    $chartSymbol = 'AAPL';
}

$assetMix = [
    'Technology' => 0.0,
    'Consumer' => 0.0,
    'Semiconductors' => 0.0,
    'Other' => 0.0,
];
foreach ($positions as $position) {
    $quote = stock_price_for_symbol($position['symbol']);
    $marketValue = (float)$position['quantity'] * $quote['price'];
    $sector = match ($position['symbol']) {
        'AAPL', 'MSFT', 'GOOGL', 'META', 'AMZN', 'NFLX' => 'Technology',
        'NVDA' => 'Semiconductors',
        'TSLA' => 'Consumer',
        default => 'Other',
    };
    $assetMix[$sector] += $marketValue;
}
$mixTotal = array_sum($assetMix);
$assetMixPercent = [];
foreach ($assetMix as $sector => $value) {
    $assetMixPercent[$sector] = $mixTotal > 0 ? round(($value / $mixTotal) * 100, 1) : 0.0;
}

$error = get_flash('error');
$success = get_flash('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'buy_stock') {
    csrf_verify();

    $symbol = strtoupper(trim($_POST['symbol'] ?? 'AAPL'));
    $quantity = (float)($_POST['quantity'] ?? 0);
    $company = trim($_POST['company'] ?? '');

    if ($quantity <= 0) {
        flash('error', 'Please enter a valid quantity.');
        redirect('stocks.php');
    }

    $quote = stock_price_for_symbol($symbol);
    if ($company === '') {
        $company = strtoupper($symbol);
    }
    $cost = $quote['price'] * $quantity;
    if ((float)($user['balance'] ?? 0) < $cost) {
        flash('error', 'Insufficient USDT balance.');
        redirect('stocks.php');
    }

    try {
        $pdo = db();
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE users SET balance = balance - ? WHERE id = ?')->execute([$cost, $user['id']]);

        $existing = $pdo->prepare('SELECT id, quantity, avg_cost FROM stock_positions WHERE user_id = ? AND symbol = ? LIMIT 1');
        $existing->execute([$user['id'], $symbol]);
        $row = $existing->fetch();

        if ($row) {
            $newQty = (float)$row['quantity'] + $quantity;
            $newAvg = (((float)$row['quantity'] * (float)$row['avg_cost']) + $cost) / $newQty;
            $pdo->prepare('UPDATE stock_positions SET quantity = ?, avg_cost = ?, updated_at = NOW() WHERE id = ?')
                ->execute([$newQty, $newAvg, $row['id']]);
        } else {
            $pdo->prepare('INSERT INTO stock_positions (user_id, symbol, company, quantity, avg_cost) VALUES (?, ?, ?, ?, ?)')
                ->execute([$user['id'], $symbol, $company, $quantity, $quote['price']]);
        }

        $pdo->commit();
        flash('success', 'Stock purchase completed.');
    } catch (Throwable) {
        if (($pdo ?? null) instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', 'Could not complete stock purchase.');
    }

    redirect('stocks.php');
}

$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/images/favicon.png">
  <title>Stocks – CBOE Markets</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body { background: linear-gradient(180deg, #f6faff 0%, #edf5ff 45%, #f9fbff 100%); }
    .glass { background: rgba(255,255,255,0.9); border:1px solid #dcecff; box-shadow:0 12px 40px rgba(15,102,192,0.08); }
  </style>
</head>
<body class="bg-white text-slate-900 min-h-screen pb-28 md:pb-4 antialiased">
  <header class="sticky top-0 z-40 bg-white/95 backdrop-blur border-b border-slate-200 px-4 py-2.5">
    <div class="flex items-center justify-between max-w-7xl mx-auto gap-4">
      <div class="flex items-center gap-3 flex-shrink-0">
        <a href="index.php" class="flex items-center">
          <span class="text-xl font-extrabold tracking-tight text-emerald-600">CBOE<span class="text-slate-900">Markets</span></span>
        </a>
      </div>

      <nav class="hidden md:flex items-center gap-0.5 flex-1 justify-center">
        <a href="index.php" class="flex items-center gap-1 px-3 py-1.5 rounded-lg text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
          Dashboard
        </a>
        <a href="markets.php" class="flex items-center gap-1 px-3 py-1.5 rounded-lg text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/></svg>
          Markets
        </a>
        <a href="trading.php" class="flex items-center gap-1 px-3 py-1.5 rounded-lg text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
          Trade
        </a>
        <a href="stocks.php" class="flex items-center gap-1 px-3 py-1.5 rounded-lg text-sm font-medium text-emerald-600 bg-emerald-50 transition">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 19h16M7 15l3-4 3 2 4-6"/></svg>
          Stocks
        </a>
        <a href="deposit.php" class="flex items-center gap-1 px-3 py-1.5 rounded-lg text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
          Deposit
        </a>
        <a href="withdraw.php" class="flex items-center gap-1 px-3 py-1.5 rounded-lg text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
          Withdraw
        </a>
        <a href="swap.php" class="flex items-center gap-1 px-3 py-1.5 rounded-lg text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m10 4v12m0 0l-4-4m4 4l4-4"/></svg>
          Swap
        </a>
        <a href="profile.php" class="flex items-center gap-1 px-3 py-1.5 rounded-lg text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
          Profile
        </a>
      </nav>

      <div class="flex items-center gap-3 flex-shrink-0">
        <span class="hidden sm:flex items-center gap-1.5 bg-emerald-500/15 border border-emerald-500/30 px-3 py-1 rounded-full">
          <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full animate-pulse"></span>
          <span class="text-emerald-400 text-xs font-semibold tracking-wide">LIVE</span>
        </span>
        <div class="flex items-center gap-2">
          <?php if (!empty($user['profile_image'])): ?>
            <img src="<?= sanitize($user['profile_image']) ?>" alt="Avatar" class="w-8 h-8 rounded-full object-cover border border-slate-300">
          <?php else: ?>
            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-emerald-500 to-cyan-600 flex items-center justify-center text-slate-900 font-black text-sm flex-shrink-0">
              <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
            </div>
          <?php endif; ?>
          <div class="text-right hidden sm:block">
            <p class="text-sm font-semibold text-slate-800 leading-tight"><?= sanitize($user['name']) ?></p>
            <p class="text-[11px] text-slate-500"><?= sanitize($user['email']) ?></p>
          </div>
        </div>
      </div>
    </div>
  </header>

  <main class="max-w-6xl mx-auto px-4 py-6 space-y-6">
    <?php if ($error): ?>
      <div class="rounded-xl border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm"><?= sanitize($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-700 px-4 py-3 text-sm"><?= sanitize($success) ?></div>
    <?php endif; ?>

    <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <div class="glass rounded-2xl p-5">
        <p class="text-sm text-slate-500">Portfolio value</p>
        <p class="text-3xl font-bold text-slate-900">$<?= format_currency($portfolioValue, 2) ?></p>
        <p class="mt-2 text-sm <?= $portfolioGain >= 0 ? 'text-emerald-600' : 'text-red-600' ?>">
          <?= $portfolioGain >= 0 ? '+' : '' ?>$<?= format_currency($portfolioGain, 2) ?> (<?= $portfolioGain >= 0 ? '+' : '' ?><?= format_currency($portfolioGainPct, 2) ?>%)
        </p>
      </div>
      <div class="glass rounded-2xl p-5">
        <p class="text-sm text-slate-500">Cash balance</p>
        <p class="text-3xl font-bold text-slate-900">$<?= format_currency((float)($user['balance'] ?? 0), 2) ?></p>
        <p class="mt-2 text-sm text-slate-500">Ready for new positions</p>
      </div>
      <div class="glass rounded-2xl p-5">
        <p class="text-sm text-slate-500">Asset mix</p>
        <div class="mt-3 space-y-2">
          <?php foreach ($assetMixPercent as $sector => $percent): ?>
            <div class="flex items-center justify-between text-sm"><span><?= sanitize($sector) ?></span><span class="font-semibold"><?= format_currency((float)$percent, 1) ?>%</span></div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="grid grid-cols-1 xl:grid-cols-[1.2fr_0.8fr] gap-6">
      <div class="glass rounded-2xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
          <div>
            <h2 class="font-bold text-slate-900">Market watch</h2>
            <p class="text-sm text-slate-500">Live prices and quick buy actions</p>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-slate-50/80">
              <tr>
                <th class="text-left px-5 py-3 text-slate-600">Symbol</th>
                <th class="text-left px-5 py-3 text-slate-600">Company</th>
                <th class="text-right px-5 py-3 text-slate-600">Price</th>
                <th class="text-right px-5 py-3 text-slate-600">Change</th>
                <th class="text-right px-5 py-3 text-slate-600">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($stocks as $stock): ?>
                <tr class="border-t border-slate-200 hover:bg-slate-50/70">
                  <td class="px-5 py-3 font-semibold text-slate-900"><?= sanitize($stock['symbol']) ?></td>
                  <td class="px-5 py-3 text-slate-600"><?= sanitize($stock['name']) ?></td>
                  <td class="px-5 py-3 text-right font-semibold text-slate-900">$<?= format_currency((float)$stock['price'], 2) ?></td>
                  <td class="px-5 py-3 text-right <?= (float)$stock['change'] >= 0 ? 'text-emerald-600' : 'text-red-600' ?>">
                    <?= (float)$stock['change'] >= 0 ? '+' : '' ?><?= format_currency((float)$stock['change'], 2) ?>%
                  </td>
                  <td class="px-5 py-3 text-right">
                    <form method="post" class="inline-block">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="buy_stock">
                      <input type="hidden" name="symbol" value="<?= sanitize($stock['symbol']) ?>">
                      <input type="hidden" name="company" value="<?= sanitize($stock['name']) ?>">
                      <input type="hidden" name="quantity" value="1">
                      <button class="bg-blue-600 text-white px-3 py-1.5 rounded-lg text-xs font-semibold">Buy</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="space-y-6">
        <div class="glass rounded-2xl p-5">
          <h2 class="font-bold text-slate-900">Buy stock</h2>
          <p class="text-sm text-slate-500 mt-1">A quick trade form for a single position.</p>
          <form method="post" class="mt-4 space-y-3">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="buy_stock">
            <select name="symbol" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
              <?php foreach ($stocks as $stock): ?>
                <option value="<?= sanitize($stock['symbol']) ?>"><?= sanitize($stock['symbol']) ?> - <?= sanitize($stock['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <input type="text" name="company" placeholder="Company name" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
            <input type="number" name="quantity" min="1" step="1" placeholder="Quantity" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
            <button class="w-full rounded-xl bg-blue-600 text-white py-2.5 font-semibold">Buy position</button>
          </form>
        </div>

        <div class="glass rounded-2xl p-5">
          <div class="flex items-center justify-between gap-3">
            <h2 class="font-bold text-slate-900">Live stock chart</h2>
            <form method="get" class="w-32">
              <select name="chart_symbol" class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm">
                <?php foreach ($allowedChartSymbols as $symbol): ?>
                  <option value="<?= sanitize($symbol) ?>" <?= $symbol === $chartSymbol ? 'selected' : '' ?>><?= sanitize($symbol) ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </div>
          <div class="mt-4 rounded-2xl overflow-hidden border border-slate-200" style="height: 320px;">
            <div class="tradingview-widget-container h-full">
              <div class="tradingview-widget-container__widget h-full"></div>
              <script type="text/javascript" src="https://s3.tradingview.com/external-embedding/embed-widget-advanced-chart.js" async>
              {
                "autosize": true,
                "symbol": "NASDAQ:<?= sanitize($chartSymbol) ?>",
                "interval": "60",
                "timezone": "Etc/UTC",
                "theme": "light",
                "style": "1",
                "locale": "en",
                "enable_publishing": false,
                "hide_top_toolbar": false,
                "save_image": false,
                "backgroundColor": "rgba(255,255,255,1)",
                "gridColor": "rgba(226,232,240,1)"
              }
              </script>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="glass rounded-2xl overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
        <div>
          <h2 class="font-bold text-slate-900">Your stock positions</h2>
          <p class="text-sm text-slate-500">Purchased shares and average cost</p>
        </div>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-slate-50/80">
            <tr>
              <th class="text-left px-5 py-3 text-slate-600">Symbol</th>
              <th class="text-left px-5 py-3 text-slate-600">Company</th>
              <th class="text-right px-5 py-3 text-slate-600">Shares</th>
              <th class="text-right px-5 py-3 text-slate-600">Avg cost</th>
              <th class="text-right px-5 py-3 text-slate-600">Market value</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($positions)): ?>
              <tr><td colspan="5" class="px-5 py-6 text-center text-slate-500">No stock positions yet.</td></tr>
            <?php else: ?>
              <?php foreach ($positions as $position): $quote = stock_price_for_symbol($position['symbol']); $mv = (float)$position['quantity'] * $quote['price']; ?>
                <tr class="border-t border-slate-200 hover:bg-slate-50/70">
                  <td class="px-5 py-3 font-semibold text-slate-900"><?= sanitize($position['symbol']) ?></td>
                  <td class="px-5 py-3 text-slate-600"><?= sanitize($position['company']) ?></td>
                  <td class="px-5 py-3 text-right text-slate-900"><?= format_currency((float)$position['quantity'], 2) ?></td>
                  <td class="px-5 py-3 text-right text-slate-900">$<?= format_currency((float)$position['avg_cost'], 2) ?></td>
                  <td class="px-5 py-3 text-right text-slate-900">$<?= format_currency($mv, 2) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>

  <?php $activePage = 'stocks.php'; include '_nav.php'; ?>

  <footer class="border-t border-slate-200 bg-white/90 backdrop-blur-sm mt-8">
    <div class="max-w-7xl mx-auto px-4 py-5 flex flex-col sm:flex-row items-center justify-between gap-3 text-sm text-slate-600">
      <p>© <?= date('Y') ?> CBOE Markets. All rights reserved.</p>
      <div class="flex items-center gap-4">
        <a href="index.php" class="hover:text-blue-600 transition">Dashboard</a>
        <a href="markets.php" class="hover:text-blue-600 transition">Markets</a>
        <a href="trading.php" class="hover:text-blue-600 transition">Trading</a>
        <a href="profile.php" class="hover:text-blue-600 transition">Profile</a>
      </div>
    </div>
  </footer>
</body>
</html>
