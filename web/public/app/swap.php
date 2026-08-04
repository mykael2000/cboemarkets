<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/csrf.php';
require_once __DIR__ . '/../../src/helpers.php';

require_login();
$user = current_user();

// Coin → DB column map
$COIN_COLS = [
    'USDT' => 'balance',
    'BTC'  => 'btc_balance',
    'ETH'  => 'eth_balance',
    'BNB'  => 'bnb_balance',
    'SOL'  => 'sol_balance',
];

$error   = get_flash('error');
$success = get_flash('success');

// Handle swap POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $fromCurrency = strtoupper(trim($_POST['from_currency'] ?? ''));
    $toCurrency   = strtoupper(trim($_POST['to_currency']   ?? ''));
    $amount       = (float)($_POST['amount'] ?? 0);

    if (!isset($COIN_COLS[$fromCurrency]) || !isset($COIN_COLS[$toCurrency])) {
        flash('error', 'Please select valid currencies.');
        redirect('swap.php');
    }
    if ($fromCurrency === $toCurrency) {
        flash('error', 'From and To currencies must be different.');
        redirect('swap.php');
    }
    if ($amount < 0.00000001) {
        flash('error', 'Please enter a valid amount greater than zero.');
        redirect('swap.php');
    }

    $fromPrice = $fromCurrency === 'USDT' ? 1.0 : price_for_symbol($fromCurrency . 'USDT');
    $toPrice   = $toCurrency   === 'USDT' ? 1.0 : price_for_symbol($toCurrency   . 'USDT');
    $rate      = $toPrice > 0 ? ($fromPrice / $toPrice) : 0.0;
    $toAmount  = round($amount * $rate, 8);

    $fromCol = $COIN_COLS[$fromCurrency];
    $toCol   = $COIN_COLS[$toCurrency];

    // Secondary whitelist check on the resolved column names.
    // PDO cannot parameterize column/table identifiers, so we validate against
    // an explicit static list here. Values come from a hardcoded map above, but
    // this double-check ensures safety if the map is ever changed.
    $allowedCols = ['balance', 'btc_balance', 'eth_balance', 'bnb_balance', 'sol_balance'];
    if (!in_array($fromCol, $allowedCols, true) || !in_array($toCol, $allowedCols, true)) {
        flash('error', 'Invalid currency mapping.');
        redirect('swap.php');
    }

    try {
        $pdo = db();
        $pdo->beginTransaction();

        $st = $pdo->prepare('SELECT ' . $fromCol . ' FROM users WHERE id = ? FOR UPDATE');
        $st->execute([$user['id']]);
        $currentBal = (float)($st->fetchColumn() ?? 0);

        if ($currentBal < $amount) {
            $pdo->rollBack();
            flash('error', 'Insufficient ' . $fromCurrency . ' balance for this swap.');
            redirect('swap.php');
        }

        $pdo->prepare('UPDATE users SET ' . $fromCol . ' = ' . $fromCol . ' - ? WHERE id = ?')
            ->execute([$amount, $user['id']]);
        $pdo->prepare('UPDATE users SET ' . $toCol . ' = ' . $toCol . ' + ? WHERE id = ?')
            ->execute([$toAmount, $user['id']]);
        $pdo->prepare(
            'INSERT INTO swaps (user_id, from_coin, to_coin, from_amount, to_amount, rate_used)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$user['id'], $fromCurrency, $toCurrency, $amount, $toAmount, $rate]);

        $pdo->commit();
        flash('success', 'Swapped ' . rtrim(number_format($amount, 8, '.', ''), '0') . ' ' . $fromCurrency
            . ' → ' . rtrim(number_format($toAmount, 8, '.', ''), '0') . ' ' . $toCurrency . '.');
    } catch (Throwable) {
        try { if (db()->inTransaction()) db()->rollBack(); } catch (Throwable) {}
        flash('error', 'Swap failed. Please try again.');
    }
    redirect('swap.php');
}

// Re-read fresh balances
$user = current_user();
$balances = [
    'USDT' => (float)($user['balance']     ?? 0),
    'BTC'  => (float)($user['btc_balance'] ?? 0),
    'ETH'  => (float)($user['eth_balance'] ?? 0),
    'BNB'  => (float)($user['bnb_balance'] ?? 0),
    'SOL'  => (float)($user['sol_balance'] ?? 0),
];

// Fetch live prices
$prices = [];
foreach (['BTCUSDT', 'ETHUSDT', 'BNBUSDT', 'SOLUSDT'] as $sym) {
    $prices[$sym] = price_for_symbol($sym);
}
$pricesJson = json_encode([
    'USDT' => 1.0,
    'BTC'  => $prices['BTCUSDT'],
    'ETH'  => $prices['ETHUSDT'],
    'BNB'  => $prices['BNBUSDT'],
    'SOL'  => $prices['SOLUSDT'],
], JSON_THROW_ON_ERROR);

// Swap history
$swapHistory = [];
try {
    $st = db()->prepare(
        'SELECT * FROM swaps WHERE user_id = ? ORDER BY created_at DESC LIMIT 20'
    );
    $st->execute([$user['id']]);
    $swapHistory = $st->fetchAll();
} catch (Throwable) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/images/favicon.png">
  <title>Swap – CBOE Markets</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-white text-slate-900 min-h-screen pb-20 md:pb-4 antialiased">

  <!-- Header -->
  <header class="sticky top-0 z-40 bg-white/95 backdrop-blur border-b border-slate-200 px-4 py-3 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <a href="index.php" class="text-slate-600 hover:text-slate-900 transition">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
      </a>
      <span class="text-lg font-extrabold text-blue-500">Swap</span>
    </div>
  </header>

  <main class="max-w-lg mx-auto px-4 py-5 space-y-5">

    <?php if ($error): ?>
      <div class="bg-red-500/10 border border-red-500/30 text-red-600 text-sm rounded-xl px-4 py-3"><?= sanitize($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 text-sm rounded-xl px-4 py-3"><?= sanitize($success) ?></div>
    <?php endif; ?>

    <!-- Balances summary -->
    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4">
      <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-3">Your Balances</p>
      <div class="grid grid-cols-5 gap-2 text-center">
        <?php
        $coinColors = ['USDT'=>'teal','BTC'=>'orange','ETH'=>'indigo','BNB'=>'yellow','SOL'=>'purple'];
        foreach ($balances as $coin => $bal):
            $c = $coinColors[$coin] ?? 'slate';
        ?>
        <div>
          <p class="text-xs text-<?= $c ?>-500 font-bold"><?= $coin ?></p>
          <p class="text-xs text-slate-700 font-mono tabular-nums"><?= $coin === 'USDT' ? number_format($bal,2) : rtrim(number_format($bal,8,'.',''),'0') ?></p>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Swap form -->
    <div class="bg-white border border-slate-200 rounded-2xl p-5 space-y-4">
      <h2 class="font-bold text-slate-900 text-base flex items-center gap-2">
        <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m10 4v12m0 0l-4-4m4 4l4-4"/></svg>
        Instant Swap (No Fees)
      </h2>
      <form method="POST" action="swap.php" class="space-y-3">
        <?= csrf_field() ?>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm text-slate-600 mb-1.5">From</label>
            <select name="from_currency" id="swapFrom" required
              class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
              <option value="USDT" selected>$ USDT</option>
              <option value="BTC">₿ BTC</option>
              <option value="ETH">Ξ ETH</option>
              <option value="BNB">◈ BNB</option>
              <option value="SOL">◎ SOL</option>
            </select>
          </div>
          <div>
            <label class="block text-sm text-slate-600 mb-1.5">To</label>
            <select name="to_currency" id="swapTo" required
              class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
              <option value="USDT">$ USDT</option>
              <option value="BTC" selected>₿ BTC</option>
              <option value="ETH">Ξ ETH</option>
              <option value="BNB">◈ BNB</option>
              <option value="SOL">◎ SOL</option>
            </select>
          </div>
        </div>

        <div>
          <label class="block text-sm text-slate-600 mb-1.5">Amount <span id="fromLabel" class="text-blue-500 font-semibold">USDT</span></label>
          <input type="number" name="amount" id="swapAmount" step="0.00000001" min="0.00000001" required
            class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 tabular-nums"
            placeholder="0.00">
        </div>

        <!-- Quick USD chips -->
        <div class="flex gap-2 flex-wrap">
          <span class="text-xs text-slate-500 self-center">Quick fill:</span>
          <button type="button" onclick="setUsdAmount(100)"  class="text-xs px-3 py-1.5 bg-blue-500/10 text-blue-600 rounded-lg hover:bg-blue-500/20 transition font-medium">$100</button>
          <button type="button" onclick="setUsdAmount(500)"  class="text-xs px-3 py-1.5 bg-blue-500/10 text-blue-600 rounded-lg hover:bg-blue-500/20 transition font-medium">$500</button>
          <button type="button" onclick="setUsdAmount(1000)" class="text-xs px-3 py-1.5 bg-blue-500/10 text-blue-600 rounded-lg hover:bg-blue-500/20 transition font-medium">$1,000</button>
          <button type="button" onclick="setUsdAmount(2500)" class="text-xs px-3 py-1.5 bg-blue-500/10 text-blue-600 rounded-lg hover:bg-blue-500/20 transition font-medium">$2,500</button>
        </div>

        <!-- Live rate display -->
        <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
          <p class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider mb-1">Live Rate</p>
          <p class="text-sm font-bold text-slate-900" id="rateDisplay">— loading —</p>
          <p class="text-xs text-slate-500 mt-0.5" id="receiveDisplay"></p>
          <p class="text-xs text-slate-500 mt-0.5" id="usdEquiv"></p>
        </div>

        <button type="submit"
          class="w-full bg-gradient-to-r from-blue-700 to-blue-500 hover:from-blue-600 hover:to-blue-400 text-white font-bold py-3 rounded-xl transition shadow-lg shadow-blue-900/30">
          ⇄ Swap Now
        </button>
      </form>
    </div>

    <!-- Swap history -->
    <?php if (!empty($swapHistory)): ?>
    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-200">
        <h2 class="font-bold text-slate-900 text-sm">Swap History</h2>
      </div>
      <div class="divide-y divide-slate-100">
        <?php foreach ($swapHistory as $s): ?>
        <div class="px-5 py-3 flex items-center justify-between">
          <div>
            <p class="text-sm font-semibold text-slate-900">
              <?= sanitize($s['from_coin']) ?> → <?= sanitize($s['to_coin']) ?>
            </p>
            <p class="text-xs text-slate-500"><?= date('M j, Y H:i', strtotime($s['created_at'])) ?></p>
          </div>
          <div class="text-right">
            <p class="text-sm font-bold text-slate-900">
              <?= rtrim(number_format((float)$s['from_amount'], 8, '.', ''), '0') ?> <?= sanitize($s['from_coin']) ?>
            </p>
            <p class="text-xs text-emerald-600 font-semibold">
              → <?= rtrim(number_format((float)$s['to_amount'], 8, '.', ''), '0') ?> <?= sanitize($s['to_coin']) ?>
            </p>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </main>

  <!-- Navigation -->
  <?php $activePage = 'swap.php'; include '_nav.php'; ?>

  <script>
  (function () {
    const PRICES = <?= $pricesJson ?>;
    const DECIMALS = { USDT: 2, BTC: 8, ETH: 8, BNB: 8, SOL: 8 };

    const fromSel   = document.getElementById('swapFrom');
    const toSel     = document.getElementById('swapTo');
    const amountInp = document.getElementById('swapAmount');
    const fromLabel = document.getElementById('fromLabel');
    const rateDisp  = document.getElementById('rateDisplay');
    const recvDisp  = document.getElementById('receiveDisplay');
    const usdEquiv  = document.getElementById('usdEquiv');

    function fmt(n, d) {
      return n.toLocaleString('en-US', {minimumFractionDigits: d, maximumFractionDigits: d});
    }

    function updateRate() {
      const from   = fromSel.value;
      const to     = toSel.value;
      const amount = parseFloat(amountInp.value) || 0;
      fromLabel.textContent = from;

      if (from === to) {
        rateDisp.textContent = 'Select different currencies';
        recvDisp.textContent = '';
        usdEquiv.textContent = '';
        return;
      }

      const fromUSD = PRICES[from] || 1;
      const toUSD   = PRICES[to]   || 1;
      const rate    = fromUSD / toUSD;
      const dec     = DECIMALS[to] || 8;

      rateDisp.textContent = '1 ' + from + ' = ' + fmt(rate, dec) + ' ' + to;

      if (amount > 0) {
        const recv     = amount * rate;
        const usdValue = amount * fromUSD;
        recvDisp.textContent = 'You receive ≈ ' + fmt(recv, dec) + ' ' + to;
        usdEquiv.textContent = '≈ $' + fmt(usdValue, 2) + ' USD';
      } else {
        recvDisp.textContent = '';
        usdEquiv.textContent = '';
      }
    }

    /**
     * Fill the amount input using a USD value.
     * For USDT: amount = usdVal. For others: amount = usdVal / price.
     */
    function setUsdAmount(usdVal) {
      const from  = fromSel.value;
      const price = PRICES[from] || 1;
      const dec   = DECIMALS[from] || 8;
      const coins = from === 'USDT' ? usdVal : usdVal / price;
      amountInp.value = coins.toFixed(dec);
      updateRate();
    }

    fromSel.addEventListener('change', function () {
      if (fromSel.value === toSel.value) {
        const opts = Array.from(toSel.options).map(o => o.value);
        toSel.value = opts.find(v => v !== fromSel.value) || '';
      }
      updateRate();
    });
    toSel.addEventListener('change', updateRate);
    amountInp.addEventListener('input', updateRate);

    // Expose setUsdAmount globally for inline onclick handlers
    window.setUsdAmount = setUsdAmount;

    updateRate();
  })();
  </script>

</body>
</html>
