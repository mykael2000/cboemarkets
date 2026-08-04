<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/helpers.php';

require_login();
$user = current_user();

$symbols = ['BTCUSDT', 'ETHUSDT', 'SOLUSDT', 'BNBUSDT', 'ADAUSDT', 'XRPUSDT'];
$selected = strtoupper(trim($_GET['symbol'] ?? 'BTCUSDT'));
if (!in_array($selected, $symbols, true)) {
    $selected = 'BTCUSDT';
}

$prices = [];
foreach ($symbols as $sym) {
    $prices[$sym] = price_for_symbol($sym);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/images/favicon.png">
  <title>Markets – CBOE Markets</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-white text-slate-900 min-h-screen pb-20">

  <header class="sticky top-0 z-40 bg-white/95 backdrop-blur border-b border-slate-200 px-4 py-3 flex items-center justify-between">
    <span class="text-xl font-extrabold text-emerald-400">Markets</span>
  </header>

  <main class="max-w-2xl mx-auto py-6 space-y-6">

    <!-- Symbol Selector -->
    <div class="flex gap-2 flex-wrap px-4">
      <?php foreach ($symbols as $sym): ?>
      <a href="markets.php?symbol=<?= urlencode($sym) ?>"
         class="px-3 py-1.5 rounded-lg text-sm font-medium transition
                <?= $sym === $selected ? 'bg-emerald-500 text-slate-900' : 'bg-white text-slate-700 hover:bg-slate-100' ?>">
        <?= $sym ?>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- TradingView Chart Widget -->
    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden" style="height:420px;">
      <div class="tradingview-widget-container h-full">
        <div class="tradingview-widget-container__widget h-full"></div>
        <script type="text/javascript"
          src="https://s3.tradingview.com/external-embedding/embed-widget-advanced-chart.js" async>
        {
          "autosize": true,
          "symbol": "BINANCE:<?= sanitize($selected) ?>",
          "interval": "60",
          "timezone": "Etc/UTC",
          "theme": "light",
          "style": "1",
          "locale": "en",
          "enable_publishing": false,
          "hide_top_toolbar": false,
          "hide_legend": false,
          "save_image": false,
          "backgroundColor": "rgba(255, 255, 255, 1)",
          "gridColor": "rgba(226, 232, 240, 1)"
        }
        </script>
      </div>
    </div>

    <!-- Market Overview Table -->
    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-200">
        <h2 class="font-bold text-slate-900">Market Overview</h2>
      </div>
      <table class="w-full text-sm">
        <thead class="bg-slate-100/80">
          <tr>
            <th class="text-left text-slate-600 font-medium px-5 py-3">Asset</th>
            <th class="text-right text-slate-600 font-medium px-5 py-3">Price (USDT)</th>
            <th class="text-right text-slate-600 font-medium px-5 py-3">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($symbols as $sym): ?>
          <?php $base = str_replace('USDT', '', $sym); ?>
          <tr class="border-t border-slate-200 hover:bg-slate-50 transition">
            <td class="px-5 py-3">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-white rounded-full flex items-center justify-center text-xs font-bold"><?= $base[0] ?></div>
                <div>
                  <p class="font-semibold text-slate-900"><?= $base ?></p>
                  <p class="text-slate-600 text-xs"><?= $sym ?></p>
                </div>
              </div>
            </td>
            <td class="px-5 py-3 text-right font-bold text-slate-900">
              $<?= number_format($prices[$sym], 2) ?>
            </td>
            <td class="px-5 py-3 text-right">
              <a href="trading.php?symbol=<?= urlencode($sym) ?>"
                 class="bg-emerald-500/10 text-emerald-700 hover:bg-emerald-500/30 px-3 py-1 rounded-lg text-xs font-medium transition">
                Trade
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  </main>

  <!-- Navigation -->
  <?php $activePage = 'markets.php'; include '_nav.php'; ?>

</body>
</html>


