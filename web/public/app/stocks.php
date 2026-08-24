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

function ensure_stock_questionnaire_table(): void
{
  db()->exec(
    'CREATE TABLE IF NOT EXISTS stock_access_questionnaires (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      user_id BIGINT UNSIGNED NOT NULL UNIQUE,
      payload_json LONGTEXT NOT NULL,
      completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB'
  );
}

ensure_stock_portfolio_table();
ensure_market_stocks_table();
ensure_stock_questionnaire_table();

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

$questionnaireErrors = [];
$questionnaireValues = [];

$questionnaireRow = null;
try {
  $st = db()->prepare('SELECT payload_json, completed_at FROM stock_access_questionnaires WHERE user_id = ? LIMIT 1');
  $st->execute([$user['id']]);
  $questionnaireRow = $st->fetch() ?: null;
} catch (Throwable) {
  $questionnaireRow = null;
}

$questionnaireCompleted = $questionnaireRow !== null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_stock_questionnaire') {
  csrf_verify();

  $questionnaireValues = [
    'first_name' => trim((string)($_POST['first_name'] ?? '')),
    'middle_name' => trim((string)($_POST['middle_name'] ?? '')),
    'last_name' => trim((string)($_POST['last_name'] ?? '')),
    'date_of_birth' => trim((string)($_POST['date_of_birth'] ?? '')),
    'residential_address' => trim((string)($_POST['residential_address'] ?? '')),
    'mailing_address' => trim((string)($_POST['mailing_address'] ?? '')),
    'phone_number' => trim((string)($_POST['phone_number'] ?? '')),
    'email_address' => trim((string)($_POST['email_address'] ?? '')),
    'tax_id' => trim((string)($_POST['tax_id'] ?? '')),
    'citizenship_residency' => trim((string)($_POST['citizenship_residency'] ?? '')),
    'government_id_type' => trim((string)($_POST['government_id_type'] ?? '')),
    'employment_status' => trim((string)($_POST['employment_status'] ?? '')),
    'employer_name' => trim((string)($_POST['employer_name'] ?? '')),
    'occupation' => trim((string)($_POST['occupation'] ?? '')),
    'employer_address' => trim((string)($_POST['employer_address'] ?? '')),
    'annual_income_range' => trim((string)($_POST['annual_income_range'] ?? '')),
    'net_worth_range' => trim((string)($_POST['net_worth_range'] ?? '')),
    'liquid_assets_range' => trim((string)($_POST['liquid_assets_range'] ?? '')),
    'source_of_funds' => trim((string)($_POST['source_of_funds'] ?? '')),
    'investment_objective' => trim((string)($_POST['investment_objective'] ?? '')),
    'investment_experience' => trim((string)($_POST['investment_experience'] ?? '')),
    'risk_tolerance' => trim((string)($_POST['risk_tolerance'] ?? '')),
    'time_horizon' => trim((string)($_POST['time_horizon'] ?? '')),
    'account_type' => trim((string)($_POST['account_type'] ?? '')),
    'options_trading' => trim((string)($_POST['options_trading'] ?? '')),
    'other_trading_features' => trim((string)($_POST['other_trading_features'] ?? '')),
  ];

  $selectedProducts = $_POST['investment_products'] ?? [];
  $selectedFundingMethods = $_POST['funding_methods'] ?? [];

  if (!is_array($selectedProducts)) {
    $selectedProducts = [];
  }
  if (!is_array($selectedFundingMethods)) {
    $selectedFundingMethods = [];
  }

  $questionnaireValues['investment_products'] = array_values(array_filter(array_map('strval', $selectedProducts)));
  $questionnaireValues['funding_methods'] = array_values(array_filter(array_map('strval', $selectedFundingMethods)));

  $requiredFields = [
    'first_name' => 'First name is required.',
    'last_name' => 'Last name is required.',
    'date_of_birth' => 'Date of birth is required.',
    'residential_address' => 'Residential address is required.',
    'phone_number' => 'Phone number is required.',
    'email_address' => 'Email address is required.',
    'tax_id' => 'Taxpayer identification information is required.',
    'citizenship_residency' => 'Citizenship or residency information is required.',
    'employment_status' => 'Employment status is required.',
    'annual_income_range' => 'Annual income range is required.',
    'net_worth_range' => 'Net worth range is required.',
    'liquid_assets_range' => 'Liquid assets range is required.',
    'source_of_funds' => 'Source of funds is required.',
    'investment_objective' => 'Investment objective is required.',
    'investment_experience' => 'Investment experience is required.',
    'risk_tolerance' => 'Risk tolerance is required.',
    'time_horizon' => 'Time horizon is required.',
    'account_type' => 'Account type selection is required.',
    'options_trading' => 'Options trading selection is required.',
  ];

  foreach ($requiredFields as $field => $message) {
    if ($questionnaireValues[$field] === '') {
      $questionnaireErrors[] = $message;
    }
  }

  if (!filter_var($questionnaireValues['email_address'] ?: 'invalid', FILTER_VALIDATE_EMAIL)) {
    $questionnaireErrors[] = 'A valid email address is required.';
  }

  if (count($questionnaireValues['investment_products']) === 0) {
    $questionnaireErrors[] = 'Select at least one investment product.';
  }

  if (count($questionnaireValues['funding_methods']) === 0) {
    $questionnaireErrors[] = 'Select at least one funding method.';
  }

  if (empty($questionnaireErrors)) {
    $payload = [
      'answers' => $questionnaireValues,
      'submitted_ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
      'submitted_user_agent' => (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
      'submitted_at_utc' => gmdate('c'),
    ];

    try {
      db()->prepare(
        'INSERT INTO stock_access_questionnaires (user_id, payload_json, completed_at)
         VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE payload_json = VALUES(payload_json), completed_at = NOW()'
      )->execute([$user['id'], json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);

      flash('success', 'Questionnaire completed successfully. Stocks access enabled.');
      redirect('stocks.php');
    } catch (Throwable) {
      $questionnaireErrors[] = 'Unable to save your questionnaire right now. Please try again.';
    }
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'buy_stock') {
    csrf_verify();

  if (!$questionnaireCompleted) {
    flash('error', 'Please complete the stocks access questionnaire before placing trades.');
    redirect('stocks.php');
  }

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
    .questionnaire-backdrop { background: rgba(2, 6, 23, 0.72); backdrop-filter: blur(6px); }
    .questionnaire-scrollbar::-webkit-scrollbar { width: 8px; }
    .questionnaire-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }
  </style>
</head>
<body class="bg-white text-slate-900 min-h-screen pb-28 md:pb-4 antialiased <?= !$questionnaireCompleted ? 'overflow-hidden' : '' ?>">
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

  <main class="max-w-6xl mx-auto px-4 py-6 space-y-6 <?= !$questionnaireCompleted ? 'pointer-events-none select-none blur-[1px]' : '' ?>">
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
                      <button class="bg-emerald-600 text-white px-3 py-1.5 rounded-lg text-xs font-semibold">Buy</button>
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
            <button class="w-full rounded-xl bg-emerald-600 text-white py-2.5 font-semibold">Buy position</button>
          </form>
        </div>

        <div class="glass rounded-2xl p-5">
          <div class="flex items-center justify-between gap-3">
            <h2 class="font-bold text-slate-900">Live stock chart</h2>
            <form method="get" class="w-32" id="chartSymbolForm">
              <select name="chart_symbol" class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm">
                <?php foreach ($allowedChartSymbols as $symbol): ?>
                  <option value="<?= sanitize($symbol) ?>" <?= $symbol === $chartSymbol ? 'selected' : '' ?>><?= sanitize($symbol) ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </div>
          <div class="mt-4 rounded-2xl overflow-hidden border border-slate-200" style="height: 320px;">
            <iframe
              title="Live stock chart"
              class="w-full h-full"
              loading="lazy"
              allowtransparency="true"
              frameborder="0"
              src="https://s.tradingview.com/widgetembed/?symbol=NASDAQ%3A<?= urlencode($chartSymbol) ?>&interval=60&hidesidetoolbar=1&symboledit=1&saveimage=0&toolbarbg=f1f3f6&theme=light&style=1&timezone=Etc%2FUTC&withdateranges=1&hideideas=1"
            ></iframe>
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

  <?php if (!$questionnaireCompleted): ?>
  <section class="fixed inset-0 z-[999] questionnaire-backdrop flex items-center justify-center p-3 sm:p-6" aria-modal="true" role="dialog" aria-labelledby="stocksQuestionnaireTitle">
    <div class="w-full max-w-5xl bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden max-h-[95vh] flex flex-col">
      <div class="px-5 sm:px-7 py-5 border-b border-slate-200 bg-gradient-to-r from-slate-900 via-slate-800 to-slate-700 text-white">
        <p class="text-xs uppercase tracking-[0.2em] text-slate-200">Stocks Account Access</p>
        <h2 id="stocksQuestionnaireTitle" class="mt-1 text-xl sm:text-2xl font-bold">Professional Suitability Questionnaire</h2>
        <p class="mt-2 text-sm text-slate-200 max-w-3xl">Your responses help us verify identity, assess suitability, and configure your account features.</p>
      </div>

      <form method="post" class="flex-1 overflow-y-auto questionnaire-scrollbar" id="stocksQuestionnaireForm">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="submit_stock_questionnaire">

        <?php if (!empty($questionnaireErrors)): ?>
          <div class="mx-5 sm:mx-7 mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-700 text-sm">
            <p class="font-semibold mb-1">Please fix the following:</p>
            <ul class="list-disc pl-5 space-y-0.5">
              <?php foreach ($questionnaireErrors as $msg): ?>
                <li><?= sanitize($msg) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <div class="px-5 sm:px-7 py-5 sm:py-6 space-y-6">
          <section data-step-section="1" class="space-y-4">
            <h3 class="text-lg font-bold text-slate-900">Personal Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <label class="text-sm text-slate-700">First name<input required name="first_name" value="<?= sanitize((string)($questionnaireValues['first_name'] ?? '')) ?>" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2" /></label>
              <label class="text-sm text-slate-700">Middle name (optional)<input name="middle_name" value="<?= sanitize((string)($questionnaireValues['middle_name'] ?? '')) ?>" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2" /></label>
              <label class="text-sm text-slate-700">Last name<input required name="last_name" value="<?= sanitize((string)($questionnaireValues['last_name'] ?? '')) ?>" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2" /></label>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <label class="text-sm text-slate-700">Date of birth<input required type="date" name="date_of_birth" value="<?= sanitize((string)($questionnaireValues['date_of_birth'] ?? '')) ?>" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2" /></label>
              <label class="text-sm text-slate-700">Phone number<input required name="phone_number" value="<?= sanitize((string)($questionnaireValues['phone_number'] ?? '')) ?>" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2" /></label>
            </div>
            <label class="text-sm text-slate-700 block">Residential address<textarea required name="residential_address" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2" rows="2"><?= sanitize((string)($questionnaireValues['residential_address'] ?? '')) ?></textarea></label>
            <label class="text-sm text-slate-700 block">Mailing address (if different)<textarea name="mailing_address" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2" rows="2"><?= sanitize((string)($questionnaireValues['mailing_address'] ?? '')) ?></textarea></label>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <label class="text-sm text-slate-700">Email address<input required type="email" name="email_address" value="<?= sanitize((string)($questionnaireValues['email_address'] ?? ($user['email'] ?? ''))) ?>" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2" /></label>
              <label class="text-sm text-slate-700">SSN or Tax ID<input required name="tax_id" value="<?= sanitize((string)($questionnaireValues['tax_id'] ?? '')) ?>" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2" /></label>
              <label class="text-sm text-slate-700">Citizenship / Residency<input required name="citizenship_residency" value="<?= sanitize((string)($questionnaireValues['citizenship_residency'] ?? '')) ?>" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2" /></label>
            </div>
            <label class="text-sm text-slate-700 block">Government-issued ID type (for verification)<input name="government_id_type" value="<?= sanitize((string)($questionnaireValues['government_id_type'] ?? 'Passport / Driver License')) ?>" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2" /></label>
          </section>

          <section data-step-section="2" class="space-y-4 hidden">
            <h3 class="text-lg font-bold text-slate-900">Employment Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <label class="text-sm text-slate-700">Employment status
                <select required name="employment_status" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2">
                  <?php $employmentOptions = ['Employed', 'Self-Employed', 'Unemployed', 'Student', 'Retired']; ?>
                  <option value="">Select status</option>
                  <?php foreach ($employmentOptions as $opt): ?>
                    <option value="<?= sanitize($opt) ?>" <?= (($questionnaireValues['employment_status'] ?? '') === $opt) ? 'selected' : '' ?>><?= sanitize($opt) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="text-sm text-slate-700">Occupation / Job title<input name="occupation" value="<?= sanitize((string)($questionnaireValues['occupation'] ?? '')) ?>" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2" /></label>
            </div>
            <label class="text-sm text-slate-700 block">Employer name<input name="employer_name" value="<?= sanitize((string)($questionnaireValues['employer_name'] ?? '')) ?>" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2" /></label>
            <label class="text-sm text-slate-700 block">Employer address<textarea name="employer_address" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2" rows="2"><?= sanitize((string)($questionnaireValues['employer_address'] ?? '')) ?></textarea></label>
          </section>

          <section data-step-section="3" class="space-y-4 hidden">
            <h3 class="text-lg font-bold text-slate-900">Financial Profile</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <label class="text-sm text-slate-700">Annual income range<input required name="annual_income_range" value="<?= sanitize((string)($questionnaireValues['annual_income_range'] ?? '')) ?>" placeholder="e.g. $50,000 - $100,000" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2" /></label>
              <label class="text-sm text-slate-700">Net worth range<input required name="net_worth_range" value="<?= sanitize((string)($questionnaireValues['net_worth_range'] ?? '')) ?>" placeholder="e.g. $100,000 - $250,000" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2" /></label>
              <label class="text-sm text-slate-700">Liquid/investable assets<input required name="liquid_assets_range" value="<?= sanitize((string)($questionnaireValues['liquid_assets_range'] ?? '')) ?>" placeholder="e.g. $10,000 - $50,000" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2" /></label>
            </div>
            <label class="text-sm text-slate-700 block">Primary source of funds<textarea required name="source_of_funds" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2" rows="2"><?= sanitize((string)($questionnaireValues['source_of_funds'] ?? '')) ?></textarea></label>
          </section>

          <section data-step-section="4" class="space-y-4 hidden">
            <h3 class="text-lg font-bold text-slate-900">Investment Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <label class="text-sm text-slate-700">Investment objective
                <select required name="investment_objective" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2">
                  <option value="">Select objective</option>
                  <?php foreach (['Capital Appreciation', 'Income Generation', 'Speculation', 'Capital Preservation', 'Balanced Growth'] as $opt): ?>
                    <option value="<?= sanitize($opt) ?>" <?= (($questionnaireValues['investment_objective'] ?? '') === $opt) ? 'selected' : '' ?>><?= sanitize($opt) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="text-sm text-slate-700">Investment experience
                <select required name="investment_experience" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2">
                  <option value="">Select experience</option>
                  <?php foreach (['None', 'Beginner', 'Intermediate', 'Advanced', 'Professional'] as $opt): ?>
                    <option value="<?= sanitize($opt) ?>" <?= (($questionnaireValues['investment_experience'] ?? '') === $opt) ? 'selected' : '' ?>><?= sanitize($opt) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="text-sm text-slate-700">Risk tolerance
                <select required name="risk_tolerance" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2">
                  <option value="">Select risk profile</option>
                  <?php foreach (['Low', 'Moderate', 'High', 'Very High'] as $opt): ?>
                    <option value="<?= sanitize($opt) ?>" <?= (($questionnaireValues['risk_tolerance'] ?? '') === $opt) ? 'selected' : '' ?>><?= sanitize($opt) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="text-sm text-slate-700">Time horizon
                <select required name="time_horizon" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2">
                  <option value="">Select horizon</option>
                  <?php foreach (['Less than 1 year', '1-3 years', '3-5 years', '5+ years'] as $opt): ?>
                    <option value="<?= sanitize($opt) ?>" <?= (($questionnaireValues['time_horizon'] ?? '') === $opt) ? 'selected' : '' ?>><?= sanitize($opt) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
            </div>

            <?php $productValues = $questionnaireValues['investment_products'] ?? []; ?>
            <div>
              <p class="text-sm font-semibold text-slate-700 mb-2">Investment products you intend to trade</p>
              <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-sm text-slate-700">
                <?php foreach (['Stocks', 'ETFs', 'Mutual Funds', 'Options'] as $product): ?>
                  <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2">
                    <input type="checkbox" name="investment_products[]" value="<?= sanitize($product) ?>" <?= in_array($product, $productValues, true) ? 'checked' : '' ?> />
                    <span><?= sanitize($product) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          </section>

          <section data-step-section="5" class="space-y-4 hidden">
            <h3 class="text-lg font-bold text-slate-900">Account Features</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <label class="text-sm text-slate-700">Account type
                <select required name="account_type" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2">
                  <option value="">Select account type</option>
                  <?php foreach (['Cash', 'Margin'] as $opt): ?>
                    <option value="<?= sanitize($opt) ?>" <?= (($questionnaireValues['account_type'] ?? '') === $opt) ? 'selected' : '' ?>><?= sanitize($opt) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="text-sm text-slate-700">Options trading request
                <select required name="options_trading" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2">
                  <option value="">Select option</option>
                  <?php foreach (['No', 'Yes - Basic', 'Yes - Advanced'] as $opt): ?>
                    <option value="<?= sanitize($opt) ?>" <?= (($questionnaireValues['options_trading'] ?? '') === $opt) ? 'selected' : '' ?>><?= sanitize($opt) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
            </div>
            <label class="text-sm text-slate-700 block">Other requested trading features<textarea name="other_trading_features" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2" rows="2" placeholder="Optional"><?= sanitize((string)($questionnaireValues['other_trading_features'] ?? '')) ?></textarea></label>
          </section>

          <section data-step-section="6" class="space-y-4 hidden">
            <h3 class="text-lg font-bold text-slate-900">Funding Information</h3>
            <?php $fundingValues = $questionnaireValues['funding_methods'] ?? []; ?>
            <div>
              <p class="text-sm font-semibold text-slate-700 mb-2">How will you fund your account?</p>
              <div class="grid grid-cols-2 md:grid-cols-3 gap-2 text-sm text-slate-700">
                <?php foreach (['Bitcoin', 'USDT', 'Bank Transfer', 'Wire Transfer', 'From Another Brokerage'] as $method): ?>
                  <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2">
                    <input type="checkbox" name="funding_methods[]" value="<?= sanitize($method) ?>" <?= in_array($method, $fundingValues, true) ? 'checked' : '' ?> />
                    <span><?= sanitize($method) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="rounded-xl border border-sky-100 bg-sky-50 px-4 py-3 text-sm text-sky-900">
              Current standard brokerage account guidance: no account minimum is required, and eligible fractional investing may start from as little as $1.
            </div>
          </section>
        </div>

        <div class="px-5 sm:px-7 py-4 border-t border-slate-200 bg-white flex items-center justify-between gap-3">
          <button type="button" id="questionnairePrev" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 disabled:opacity-40" disabled>Previous</button>
          <div class="flex items-center gap-2">
            <button type="button" id="questionnaireNext" class="rounded-xl bg-slate-900 text-white px-4 py-2 text-sm font-semibold hover:bg-slate-800">Next section</button>
            <button type="submit" id="questionnaireSubmit" class="hidden rounded-xl bg-emerald-600 text-white px-4 py-2 text-sm font-semibold hover:bg-emerald-500">Submit questionnaire</button>
          </div>
        </div>
      </form>
    </div>
  </section>
  <?php endif; ?>

  <?php $activePage = 'stocks.php'; include '_nav.php'; ?>

  <!-- <footer class="border-t border-slate-200 bg-white/90 backdrop-blur-sm mt-8">
    <div class="max-w-7xl mx-auto px-4 py-5 flex flex-col sm:flex-row items-center justify-between gap-3 text-sm text-slate-600">
      <p>© <?= date('Y') ?> CBOE Markets. All rights reserved.</p>
      <div class="flex items-center gap-4">
        <a href="index.php" class="hover:text-emerald-600 transition">Dashboard</a>
        <a href="markets.php" class="hover:text-emerald-600 transition">Markets</a>
        <a href="trading.php" class="hover:text-emerald-600 transition">Trading</a>
        <a href="profile.php" class="hover:text-emerald-600 transition">Profile</a>
      </div>
    </div>
  </footer> -->
  <script>
    (function () {
      const chartForm = document.getElementById('chartSymbolForm');
      if (chartForm) {
        const selector = chartForm.querySelector('select[name="chart_symbol"]');
        if (selector) {
          selector.addEventListener('change', function () {
            chartForm.submit();
          });
        }
      }

      const sections = Array.from(document.querySelectorAll('[data-step-section]'));
      if (!sections.length) {
        return;
      }

      const stepPills = Array.from(document.querySelectorAll('[data-step-pill]'));
      const prevBtn = document.getElementById('questionnairePrev');
      const nextBtn = document.getElementById('questionnaireNext');
      const submitBtn = document.getElementById('questionnaireSubmit');
      let currentStep = 1;

      function updateStepUI() {
        sections.forEach((section) => {
          const step = Number(section.getAttribute('data-step-section') || '0');
          section.classList.toggle('hidden', step !== currentStep);
        });

        stepPills.forEach((pill) => {
          const step = Number(pill.getAttribute('data-step-pill') || '0');
          const isActive = step === currentStep;
          pill.classList.toggle('bg-emerald-50', isActive);
          pill.classList.toggle('text-emerald-700', isActive);
          pill.classList.toggle('border-emerald-200', isActive);
          pill.classList.toggle('bg-slate-50', !isActive);
          pill.classList.toggle('border-slate-200', !isActive);
          pill.classList.toggle('text-slate-500', !isActive);
        });

        if (prevBtn) {
          prevBtn.disabled = currentStep === 1;
        }
        const isLastStep = currentStep === sections.length;
        if (nextBtn) {
          nextBtn.classList.toggle('hidden', isLastStep);
        }
        if (submitBtn) {
          submitBtn.classList.toggle('hidden', !isLastStep);
        }
      }

      function validateCurrentStep() {
        const activeSection = sections.find((section) => !section.classList.contains('hidden'));
        if (!activeSection) {
          return true;
        }

        const requiredInputs = Array.from(activeSection.querySelectorAll('[required]'));
        for (const input of requiredInputs) {
          if (!input.value || !String(input.value).trim()) {
            input.focus();
            input.reportValidity();
            return false;
          }
        }

        if (currentStep === 4) {
          const checkedProducts = activeSection.querySelectorAll('input[name="investment_products[]"]:checked');
          if (!checkedProducts.length) {
            alert('Please select at least one investment product.');
            return false;
          }
        }

        if (currentStep === 6) {
          const checkedFunding = activeSection.querySelectorAll('input[name="funding_methods[]"]:checked');
          if (!checkedFunding.length) {
            alert('Please select at least one funding method.');
            return false;
          }
        }

        return true;
      }

      if (nextBtn) {
        nextBtn.addEventListener('click', function () {
          if (!validateCurrentStep()) {
            return;
          }
          if (currentStep < sections.length) {
            currentStep += 1;
            updateStepUI();
          }
        });
      }

      if (prevBtn) {
        prevBtn.addEventListener('click', function () {
          if (currentStep > 1) {
            currentStep -= 1;
            updateStepUI();
          }
        });
      }

      updateStepUI();
    })();
  </script>
</body>
</html>
