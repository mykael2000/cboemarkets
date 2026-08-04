<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/helpers.php';

require_login();
$user = current_user();

$forexPairs = [
    ['pair' => 'EUR/USD', 'price' => '1.0842', 'change' => '+0.24%', 'tone' => 'emerald'],
    ['pair' => 'GBP/USD', 'price' => '1.2678', 'change' => '+0.11%', 'tone' => 'sky'],
    ['pair' => 'USD/JPY', 'price' => '154.81', 'change' => '-0.19%', 'tone' => 'amber'],
    ['pair' => 'AUD/USD', 'price' => '0.6581', 'change' => '+0.08%', 'tone' => 'violet'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/images/favicon.png">
  <title>Forex Trading - CBOE Markets</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen pb-24 md:pb-6">
  <?php $activePage = 'index.php'; include '_nav.php'; ?>

  <main class="max-w-5xl mx-auto px-4 py-6 space-y-6">
    <section class="rounded-3xl border border-slate-200 bg-gradient-to-br from-sky-50 via-white to-cyan-50 p-6">
      <p class="text-xs font-semibold uppercase tracking-[0.25em] text-sky-600">Forex Desk</p>
      <div class="mt-3 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
          <h1 class="text-3xl font-black text-slate-900">Live FX exposure with fast market access</h1>
          <p class="mt-2 max-w-2xl text-sm text-slate-600">Track major currency pairs, review intraday moves, and route into the trading desk when you want directional exposure alongside your crypto positions.</p>
        </div>
        <a href="trading.php?mode=live" class="inline-flex items-center justify-center rounded-2xl bg-sky-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-sky-500">Open Trading Desk</a>
      </div>
    </section>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      <?php foreach ($forexPairs as $pair): ?>
      <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Major Pair</p>
        <h2 class="mt-2 text-xl font-black text-slate-900"><?= sanitize($pair['pair']) ?></h2>
        <p class="mt-3 text-2xl font-black text-slate-800"><?= sanitize($pair['price']) ?></p>
        <p class="mt-1 text-sm font-semibold <?= str_starts_with($pair['change'], '+') ? 'text-emerald-600' : 'text-red-500' ?>"><?= sanitize($pair['change']) ?> today</p>
      </article>
      <?php endforeach; ?>
    </section>

    <section class="grid gap-4 lg:grid-cols-3">
      <article class="rounded-2xl border border-slate-200 bg-white p-5">
        <h2 class="text-lg font-bold text-slate-900">Market Notes</h2>
        <p class="mt-2 text-sm text-slate-600">Pair momentum, macro reaction, and session liquidity are visible here so users can assess whether to trade trends, breakouts, or reversals.</p>
      </article>
      <article class="rounded-2xl border border-slate-200 bg-white p-5">
        <h2 class="text-lg font-bold text-slate-900">Risk Focus</h2>
        <p class="mt-2 text-sm text-slate-600">Use measured position size and combine FX exposure with your live trading workflow. Keep enough free USDT margin before opening additional trades.</p>
      </article>
      <article class="rounded-2xl border border-slate-200 bg-white p-5">
        <h2 class="text-lg font-bold text-slate-900">Account</h2>
        <p class="mt-2 text-sm text-slate-600">Signed in as <span class="font-semibold text-slate-800"><?= sanitize($user['email']) ?></span>. Use the trading desk for execution and the wallet page to monitor funding.</p>
      </article>
    </section>
  </main>
</body>
</html>