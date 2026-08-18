<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/csrf.php';
require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/email.php';

require_login();
$user    = current_user();
$error   = get_flash('error');
$success = get_flash('success');
// Pending withdrawal (OTP) if any
$pendingWithdraw = $_SESSION['pending_withdraw'] ?? null;

// Allow cancelling a pending withdrawal confirmation
if (isset($_GET['cancel_pending'])) {
    unset($_SESSION['pending_withdraw']);
    flash('success', 'Pending withdrawal cancelled.');
    redirect('/app/withdraw');
}

// Fetch live prices for all non-USDT coins (for USD chip conversion)
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

// server-side price map for conversions
$priceMap = [
    'USDT' => 1.0,
    'BTC'  => $prices['BTCUSDT'],
    'ETH'  => $prices['ETHUSDT'],
    'BNB'  => $prices['BNBUSDT'],
    'SOL'  => $prices['SOLUSDT'],
];

// Balances from DB (all 5 coins)
$balances = [
    'USDT' => (float) ($user['balance']     ?? 0),
    'BTC'  => (float) ($user['btc_balance'] ?? 0),
    'ETH'  => (float) ($user['eth_balance'] ?? 0),
    'BNB'  => (float) ($user['bnb_balance'] ?? 0),
    'SOL'  => (float) ($user['sol_balance'] ?? 0),
];

// Handle Withdrawal Request submission (two-step with OTP)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {

        // Resend OTP request
        if (isset($_POST['resend_otp'])) {
            $pending    = $_SESSION['pending_withdraw'] ?? null;
            $cooldown   = (int) env('OTP_RESEND_COOLDOWN', 60);
            $maxResends = (int) env('OTP_MAX_RESENDS', 3);
            if (empty($pending)) {
                flash('error', 'No pending withdrawal to resend code for.');
                redirect('/app/withdraw');
            }
            $now      = time();
            $lastSent = (int) ($pending['otp_sent_at'] ?? ($pending['otp_expires'] - 600));
            $resends  = (int) ($pending['resend_count'] ?? 0);
            if ($resends >= $maxResends) {
                flash('error', 'You have reached the maximum number of resend attempts.');
                redirect('/app/withdraw');
            }
            if ($now < $lastSent + $cooldown) {
                $wait = ($lastSent + $cooldown) - $now;
                flash('error', 'Please wait ' . $wait . " seconds before requesting a new code.");
                redirect('/app/withdraw');
            }

            // Regenerate OTP
            $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $_SESSION['pending_withdraw']['otp']         = $otp;
            $_SESSION['pending_withdraw']['otp_sent_at'] = $now;
            $_SESSION['pending_withdraw']['otp_expires'] = $now + 600;
            $_SESSION['pending_withdraw']['resend_count'] = $resends + 1;
            $dbg  = null;
            $sent = send_security_otp_email($user['email'], $user['name'] ?? $user['email'], $otp, 'withdrawal', $dbg);
            if ($sent) {
                flash('success', 'A new code has been sent to your email.');
            } else {
                // Log debug info for investigation
                $logDir = WEB_ROOT . '/logs';
                if (!is_dir($logDir)) {
                    @mkdir($logDir, 0755, true);
                }
                $msg = sprintf("[%s] resend_otp failed for user_id=%s email=%s debug=%s\n", date('c'), $user['id'] ?? 'unknown', $user['email'] ?? 'unknown', $dbg ?? 'no debug');
                error_log($msg, 3, $logDir . '/withdraw_otp_debug.log');
                flash('error', 'Failed to send OTP email. Please try again later.');
            }
            redirect('/app/withdraw');
        }

        // OTP confirmation step
        if (isset($_POST['otp_code'])) {
            $otpInput = trim($_POST['otp_code'] ?? '');
            $pending  = $_SESSION['pending_withdraw'] ?? null;
            if (empty($pending)) {
                flash('error', 'No pending withdrawal found.');
                redirect('/app/withdraw');
            }
            if (time() > ($pending['otp_expires'] ?? 0)) {
                unset($_SESSION['pending_withdraw']);
                flash('error', 'OTP expired. Please retry the withdrawal.');
                redirect('/app/withdraw');
            }
            if (!hash_equals((string) $pending['otp'], (string) $otpInput)) {
                flash('error', 'Invalid OTP code.');
                redirect('/app/withdraw');
            }

            // Insert into DB and atomically deduct the balance (prevent race conditions)
            $pdo = db();
            try {
                $asset = $pending['asset'];
                $amount = $pending['amount_coin'];

                // Map asset ticker to users table column
                $colMap = [
                    'USDT' => 'balance',
                    'BTC'  => 'btc_balance',
                    'ETH'  => 'eth_balance',
                    'BNB'  => 'bnb_balance',
                    'SOL'  => 'sol_balance',
                ];

                if (!isset($colMap[$asset])) {
                    unset($_SESSION['pending_withdraw']);
                    flash('error', 'Unsupported asset for withdrawal.');
                    redirect('/app/withdraw');
                }

                $col = $colMap[$asset];
                // Format amount as DECIMAL(18,8) string to avoid float precision issues
                $amountStr = number_format((float)$amount, 8, '.', '');

                $pdo->beginTransaction();

                // Atomically decrement the user's balance only if sufficient funds exist
                $upd = $pdo->prepare("UPDATE users SET {$col} = {$col} - ? WHERE id = ? AND {$col} >= ?");
                $upd->execute([$amountStr, $user['id'], $amountStr]);
                if ($upd->rowCount() === 0) {
                    // Insufficient funds (or user not found)
                    $pdo->rollBack();
                    unset($_SESSION['pending_withdraw']);
                    flash('error', 'Insufficient balance at confirmation. Please try again.');
                    redirect('/app/withdraw');
                }

                $ins = $pdo->prepare(
                    'INSERT INTO withdrawal_requests (user_id, asset_ticker, amount, address, status)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $ins->execute([$user['id'], $asset, $amountStr, $pending['address'], 'pending']);

                $pdo->commit();

                unset($_SESSION['pending_withdraw']);
                flash('success', 'Withdrawal request submitted! It will be processed within 24 hours.');

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                // Log detailed error for investigation
                $logDir = WEB_ROOT . '/logs';
                if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
                $msg = sprintf("[%s] withdrawal confirmation error for user_id=%s: %s in %s:%d\n%s\n", date('c'), $user['id'] ?? 'unknown', $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
                error_log($msg, 3, $logDir . '/withdraw_errors.log');
                flash('error', 'Failed to submit withdrawal request.');
            }
            redirect('/app/withdraw');
        }

        // Initial submission - prepare OTP confirmation
        $method     = trim($_POST['withdraw_method'] ?? ''); // 'bank' or 'crypto'
        $asset      = strtoupper(trim($_POST['asset_ticker'] ?? 'USDT'));
        $amountRaw  = trim($_POST['amount'] ?? '');
        $amountType = strtolower(trim($_POST['amount_type'] ?? 'usd')); // 'usd' or 'coin'

        // Require integer input (no decimals)
        if ($amountRaw === '' || !preg_match('/^\d+$/', $amountRaw)) {
            flash('error', 'Please enter a whole-number amount with no decimals.');
            redirect('/app/withdraw');
        }
        $amountInput = (int) $amountRaw;

        // Bank withdrawals are USDT only
        if ($method === 'bank' && $asset !== 'USDT') {
            flash('error', 'Bank withdrawals are only available for USDT.');
            redirect('/app/withdraw');
        }

        if ($amountInput <= 0) {
            flash('error', 'Please enter a valid amount greater than zero.');
            redirect('/app/withdraw');
        }

        // Convert USD input to coin amount if needed
        $coinAmount = 0.0;
        if ($amountType === 'usd') {
            $price = $priceMap[$asset] ?? 1.0;
            if ($price <= 0) {
                $price = 1.0;
            }
            $coinAmount = ($asset === 'USDT') ? $amountInput : ($amountInput / $price);
        } else {
            $coinAmount = $amountInput;
        }

        // Check balance
        if (!isset($balances[$asset]) || $coinAmount > $balances[$asset]) {
            flash('error', 'Insufficient balance for ' . $asset . '.');
            redirect('/app/withdraw');
        }

        if ($method === 'bank') {
            $bankName    = trim($_POST['bank_name']    ?? '');
            $accountName = trim($_POST['account_name'] ?? '');
            $accountNo   = trim($_POST['account_no']   ?? '');
            $routing     = trim($_POST['routing']      ?? '');

            if ($bankName === '' || $accountName === '' || $accountNo === '') {
                flash('error', 'Bank name, account holder name, and account number are required.');
                redirect('/app/withdraw');
            }

            $address = "Bank: {$bankName} | {$accountName} | Acct: {$accountNo}" . ($routing ? " | Routing: {$routing}" : '');
        } else {
            $address = trim($_POST['wallet_address'] ?? '');
            if ($address === '') {
                flash('error', 'Wallet address is required for crypto withdrawal.');
                redirect('/app/withdraw');
            }
        }

        // Generate OTP and store pending withdrawal in session
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $_SESSION['pending_withdraw'] = [
            'asset'        => $asset,
            'amount_coin'  => $coinAmount,
            'amount_input' => $amountInput,
            'amount_type'  => $amountType,
            'method'       => $method,
            'address'      => $address,
            'bank_name'    => $bankName ?? null,
            'account_name' => $accountName ?? null,
            'account_no'   => $accountNo ?? null,
            'routing'      => $routing ?? null,
            'otp'          => $otp,
            'otp_expires'  => time() + 600,
            'otp_sent_at'  => time(),
            'resend_count' => 0,
        ];

        // Send OTP email
        $dbg  = null;
        $sent = send_security_otp_email($user['email'], $user['name'] ?? $user['email'], $otp, 'withdrawal', $dbg);
        if ($sent) {
            flash('success', 'A one-time code has been sent to your email. Enter the code below to confirm.');
        } else {
            $logDir = WEB_ROOT . '/logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            $msg = sprintf("[%s] initial_otp_send failed for user_id=%s email=%s debug=%s\n", date('c'), $user['id'] ?? 'unknown', $user['email'] ?? 'unknown', $dbg ?? 'no debug');
            error_log($msg, 3, $logDir . '/withdraw_otp_debug.log');
            flash('error', 'Failed to send OTP email. Please contact support or try again later.');
        }
        redirect('/app/withdraw');

    } catch (Throwable $e) {
        // Ensure logs directory exists
        $logDir = WEB_ROOT . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $msg = sprintf("[%s] %s in %s:%d\n%s\n\n", date('c'), $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
        error_log($msg, 3, $logDir . '/withdraw_errors.log');
        // Feedback for user: detailed in local, generic otherwise
        $envName = env('APP_ENV', 'production');
        if ($envName === 'local') {
            flash('error', 'Internal error: ' . $e->getMessage());
        } else {
            flash('error', 'An internal error occurred. The error has been logged.');
        }
        redirect('/app/withdraw');
    }
}

// Fetch withdrawal history
$withdrawHistory = [];
try {
    $st = db()->prepare(
        'SELECT * FROM withdrawal_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 10'
    );
    $st->execute([$user['id']]);
    $withdrawHistory = $st->fetchAll();
} catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/images/favicon.png">
    <title>Withdraw – CBOE Markets</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-white text-slate-900 min-h-screen pb-20 md:pb-4">

    <header class="sticky top-0 z-40 bg-white/95 backdrop-blur border-b border-slate-200 px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="index.php" class="text-slate-600 hover:text-slate-900 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <span class="text-lg font-extrabold text-yellow-400">Withdraw</span>
        </div>
    </header>

    <main class="max-w-lg mx-auto px-4 py-5 space-y-5">

        <?php if ($error): ?>
            <div class="bg-red-500/10 border border-red-500/30 text-red-600 text-sm rounded-lg px-4 py-3"><?= sanitize($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 text-sm rounded-lg px-4 py-3"><?= sanitize($success) ?></div>
        <?php endif; ?>

        <?php if (!empty($pendingWithdraw)): ?>
            <?php
                $cooldown   = (int) env('OTP_RESEND_COOLDOWN', 60);
                $maxResends = (int) env('OTP_MAX_RESENDS', 3);
                $lastSent   = (int) ($pendingWithdraw['otp_sent_at'] ?? ($pendingWithdraw['otp_expires'] - 600));
                $resends    = (int) ($pendingWithdraw['resend_count'] ?? 0);
                $remaining  = max(0, ($lastSent + $cooldown) - time());
                $left       = max(0, $maxResends - $resends);
            ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-2xl p-5" id="pendingBox" data-last-sent="<?= $lastSent ?>" data-cooldown="<?= $cooldown ?>" data-left="<?= $left ?>">
                <h3 class="font-bold text-slate-900">Confirm Withdrawal — Enter OTP</h3>
                <p class="text-sm text-slate-700 mt-2">You're requesting to withdraw <strong>
                    <?= htmlspecialchars(number_format((float) $pendingWithdraw['amount_input'], 8), ENT_QUOTES, 'UTF-8') ?>
                    <?= $pendingWithdraw['amount_type'] === 'usd' ? 'USD' : htmlspecialchars($pendingWithdraw['asset'], ENT_QUOTES, 'UTF-8') ?>
                </strong> (≈ <strong><?= htmlspecialchars(number_format((float) $pendingWithdraw['amount_coin'], 8), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($pendingWithdraw['asset'], ENT_QUOTES, 'UTF-8') ?></strong>) to:</p>
                <p class="text-xs font-mono text-slate-600 mt-1"><?= sanitize($pendingWithdraw['address']) ?></p>

                <form method="POST" class="mt-4">
                    <?= csrf_field() ?>
                    <label class="block text-xs text-slate-600 mb-1">One-time code</label>
                    <input name="otp_code" required class="w-full bg-white border border-slate-300 rounded-lg px-3 py-2 text-sm mb-2">
                    <div class="flex gap-2 items-center">
                        <button type="submit" class="px-4 py-2 bg-yellow-500 text-white rounded-lg">Confirm Withdrawal</button>
                        <a href="?cancel_pending=1" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-lg">Cancel</a>
                        <div class="ml-auto text-xs text-slate-600">Resends left: <span id="resendLeft"><?= $left ?></span></div>
                    </div>
                </form>

                <form method="POST" class="mt-3" id="resendForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="resend_otp" value="1">
                    <button type="submit" id="resendBtn" class="px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm" <?= $remaining > 0 || $left <= 0 ? 'disabled' : '' ?>>
                        Resend code <?= $remaining > 0 ? '(' . $remaining . 's)' : '' ?>
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Withdraw Method Selection -->
        <div class="bg-white border border-slate-200 rounded-2xl p-5 space-y-4">
            <h2 class="font-bold text-slate-900 text-base">Select Withdrawal Method</h2>

            <div class="grid grid-cols-2 gap-3">
                <button type="button" id="btnCrypto" onclick="selectMethod('crypto')" class="method-card border-2 border-transparent rounded-xl p-4 text-center cursor-pointer transition bg-white hover:border-yellow-500/60">
                    <div class="w-10 h-10 bg-yellow-500/15 rounded-xl flex items-center justify-center mx-auto mb-2">
                        <svg class="w-5 h-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <p class="text-sm font-bold text-slate-800">Crypto</p>
                    <p class="text-xs text-slate-600 mt-0.5">BTC / ETH / USDT</p>
                </button>

                <button type="button" id="btnBank" onclick="selectMethod('bank')" class="method-card border-2 border-transparent rounded-xl p-4 text-center cursor-pointer transition bg-white hover:border-emerald-500/60">
                    <div class="w-10 h-10 bg-emerald-500/15 rounded-xl flex items-center justify-center mx-auto mb-2">
                        <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    </div>
                    <p class="text-sm font-bold text-slate-800">Bank Transfer</p>
                    <p class="text-xs text-slate-600 mt-0.5">Wire / ACH</p>
                </button>
            </div>
        </div>

        <!-- Withdrawal Form -->
        <form method="POST" action="" id="withdrawForm" class="hidden space-y-5">
            <?= csrf_field() ?>
            <input type="hidden" name="withdraw_method" id="withdrawMethod" value="">

            <!-- Common: Asset + Amount -->
            <div class="bg-white border border-slate-200 rounded-2xl p-5 space-y-4">

                <!-- Balance Source Selector -->
                <div>
                    <label class="block text-sm text-slate-600 mb-1.5">Withdraw from</label>
                    <select name="asset_ticker" id="assetSelect" class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <option value="USDT">USDT – Tether (Available: $<?= number_format($balances['USDT'], 2) ?>)</option>
                        <option value="BTC">BTC – Bitcoin (Available: <?= number_format($balances['BTC'], 8) ?> BTC)</option>
                        <option value="ETH">ETH – Ethereum (Available: <?= number_format($balances['ETH'], 8) ?> ETH)</option>
                        <option value="BNB">BNB – Binance Coin (Available: <?= number_format($balances['BNB'], 8) ?> BNB)</option>
                        <option value="SOL">SOL – Solana (Available: <?= number_format($balances['SOL'], 8) ?> SOL)</option>
                    </select>
                </div>

                <!-- Amount with quick chips -->
                <div>
                    <div class="flex items-center justify-between">
                        <label class="block text-sm text-slate-600 mb-1.5">Amount</label>
                        <div class="flex items-center gap-2">
                            <input type="hidden" name="amount_type" id="amountType" value="usd">
                            <div class="rounded-lg overflow-hidden border border-slate-200">
                                <button type="button" id="amtTypeUSD" onclick="setAmountType('usd')" class="px-3 py-1 text-xs bg-white">USD</button>
                                <button type="button" id="amtTypeCoin" onclick="setAmountType('coin')" class="px-3 py-1 text-xs bg-slate-50">Coin</button>
                            </div>
                            <button type="button" onclick="setMax()" class="px-3 py-1 text-xs bg-slate-100 rounded-lg border border-slate-200">Max</button>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <input type="number" name="amount" id="amountInput" min="1" step="1" required class="flex-1 bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500" placeholder="0">
                        <div id="amountUnit" class="text-sm text-slate-700 px-3 py-2 bg-slate-50 rounded-lg border border-slate-200">USD</div>
                    </div>
                    <p class="text-slate-600 text-xs mt-1" id="balanceHint">Available: $<?= number_format($balances['USDT'], 2) ?> USDT</p>
                    <p id="amountError" class="text-sm text-red-600 mt-2 hidden"></p>
                    <!-- Quick amount chips -->
                    <div class="flex flex-wrap gap-2 mt-2">
                        <button type="button" onclick="setAmount(100)" class="px-3 py-1 bg-white hover:bg-slate-100 text-slate-700 hover:text-slate-900 text-xs rounded-lg border border-slate-300 transition">$100</button>
                        <button type="button" onclick="setAmount(500)" class="px-3 py-1 bg-white hover:bg-slate-100 text-slate-700 hover:text-slate-900 text-xs rounded-lg border border-slate-300 transition">$500</button>
                        <button type="button" onclick="setAmount(1000)" class="px-3 py-1 bg-white hover:bg-slate-100 text-slate-700 hover:text-slate-900 text-xs rounded-lg border border-slate-300 transition">$1,000</button>
                        <button type="button" onclick="setAmount(2500)" class="px-3 py-1 bg-white hover:bg-slate-100 text-slate-700 hover:text-slate-900 text-xs rounded-lg border border-slate-300 transition">$2,500</button>
                    </div>
                </div>
            </div>

            <!-- Bank Fields -->
            <div id="bankFields" class="hidden bg-white border border-slate-200 rounded-2xl p-5 space-y-3">
                <h3 class="font-bold text-slate-900 text-sm flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    Bank Details
                </h3>
                <div>
                    <label class="block text-xs text-slate-600 mb-1">Bank Name</label>
                    <input type="text" name="bank_name" placeholder="e.g. Chase Bank" class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs text-slate-600 mb-1">Account Holder Name</label>
                    <input type="text" name="account_name" placeholder="Full name on account" class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs text-slate-600 mb-1">Account Number / IBAN</label>
                    <input type="text" name="account_no" placeholder="Account number" class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs text-slate-600 mb-1">Routing / SWIFT / BIC (optional)</label>
                    <input type="text" name="routing" placeholder="Routing or SWIFT code" class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>

            <!-- Crypto Fields -->
            <div id="cryptoFields" class="hidden bg-white border border-slate-200 rounded-2xl p-5 space-y-3">
                <h3 class="font-bold text-slate-900 text-sm flex items-center gap-2">
                    <svg class="w-4 h-4 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Destination Wallet
                </h3>
                <div>
                    <label class="block text-xs text-slate-600 mb-1">Wallet Address</label>
                    <input type="text" name="wallet_address" placeholder="Paste your receiving address" class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-yellow-500">
                </div>
            </div>

            <button type="submit" id="submitWithdrawBtn" class="w-full bg-yellow-500 hover:bg-yellow-400 text-white font-bold py-3 rounded-xl transition">Request Withdrawal</button>
        </form>

        <!-- Withdrawal History -->
        <?php if (!empty($withdrawHistory)): ?>
            <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-200">
                    <h2 class="font-bold text-slate-900 text-sm">Recent Withdrawals</h2>
                </div>
                <div class="divide-y divide-slate-700">
                    <?php foreach ($withdrawHistory as $w):
                        $statusColors = ['pending' => 'text-yellow-700 bg-yellow-500/10', 'approved' => 'text-emerald-700 bg-emerald-500/10', 'rejected' => 'text-red-600 bg-red-500/10'];
                        $sc = $statusColors[$w['status']] ?? 'text-slate-600 bg-white';
                    ?>
                        <div class="px-5 py-3 flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold text-slate-900"><?= sanitize($w['asset_ticker']) ?></p>
                                <p class="text-xs text-slate-600"><?= sanitize($w['created_at']) ?></p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-bold text-slate-900"><?= format_currency((float) $w['amount']) ?></p>
                                <span class="text-xs px-2 py-0.5 rounded-full <?= $sc ?>"><?= ucfirst($w['status']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

    </main>

    <!-- Navigation -->
    <?php $activePage = 'withdraw.php'; include '_nav.php'; ?>

    <script>
        const BALANCES = <?= json_encode($balances, JSON_THROW_ON_ERROR) ?>;
        const PRICES   = <?= $pricesJson ?>;

        function el(id) { return document.getElementById(id); }

        function selectMethod(method) {
            const withdrawForm = el('withdrawForm');
            if (!withdrawForm) return;
            el('withdrawMethod').value = method;
            withdrawForm.classList.remove('hidden');

            const btnBank      = el('btnBank');
            const btnCrypto    = el('btnCrypto');
            const bankFields   = el('bankFields');
            const cryptoFields = el('cryptoFields');
            const assetSelect  = el('assetSelect');

            if (method === 'bank') {
                btnBank.classList.add('border-emerald-500', 'bg-emerald-500/10');
                btnBank.classList.remove('border-transparent', 'bg-white');
                btnCrypto.classList.remove('border-yellow-500', 'bg-yellow-500/10');
                btnCrypto.classList.add('border-transparent', 'bg-white');
                bankFields.classList.remove('hidden');
                cryptoFields.classList.add('hidden');
                // Force USDT for bank withdrawals
                if (assetSelect) assetSelect.value = 'USDT';
                if (assetSelect) Array.from(assetSelect.options).forEach(opt => opt.disabled = opt.value !== 'USDT');
            } else {
                btnCrypto.classList.add('border-yellow-500', 'bg-yellow-500/10');
                btnCrypto.classList.remove('border-transparent', 'bg-white');
                btnBank.classList.remove('border-emerald-500', 'bg-emerald-500/10');
                btnBank.classList.add('border-transparent', 'bg-white');
                cryptoFields.classList.remove('hidden');
                bankFields.classList.add('hidden');
                if (assetSelect) Array.from(assetSelect.options).forEach(opt => opt.disabled = false);
            }
            updateBalanceHint();
        }

        function setAmount(usdVal) {
            const asset = el('assetSelect') ? el('assetSelect').value : 'USDT';
            const amountType = el('amountType') ? el('amountType').value : 'usd';
            const price = PRICES[asset] || 1.0;
            const input = el('amountInput');
            if (!input) return;

            if (amountType === 'usd') {
                // quick chips represent USD as whole numbers
                input.value = String(Math.floor(usdVal));
            } else {
                // coin mode: convert USD chip to coin units and floor to whole coins
                const coinAmt = (asset === 'USDT') ? usdVal : (usdVal / price);
                input.value = String(Math.floor(coinAmt));
            }
            updateBalanceHint();
            validateAmount();
        }

        function setAmountType(type) {
            const inUsdBtn = el('amtTypeUSD');
            const inCoinBtn = el('amtTypeCoin');
            const hidden = el('amountType');
            const input = el('amountInput');
            const unit = el('amountUnit');
            if (!hidden) return;
            hidden.value = type;

            // Active styling helpers
            if (inUsdBtn && inCoinBtn) {
                if (type === 'usd') {
                    inUsdBtn.classList.add('bg-emerald-50','border','border-emerald-200','text-emerald-700');
                    inUsdBtn.classList.remove('bg-slate-50','bg-white','text-slate-900');
                    inCoinBtn.classList.remove('bg-emerald-50','border','border-emerald-200','text-emerald-700');
                    inCoinBtn.classList.add('bg-slate-50','text-slate-900');
                } else {
                    inCoinBtn.classList.add('bg-emerald-50','border','border-emerald-200','text-emerald-700');
                    inCoinBtn.classList.remove('bg-slate-50','bg-white','text-slate-900');
                    inUsdBtn.classList.remove('bg-emerald-50','border','border-emerald-200','text-emerald-700');
                    inUsdBtn.classList.add('bg-slate-50','text-slate-900');
                }
            }

            // Adjust input precision and placeholder
            if (input) {
                // enforce integer-only input for both USD and coin modes
                input.step = '1';
                input.placeholder = '0';
            }

            // Unit label
            if (unit) {
                const asset = el('assetSelect') ? el('assetSelect').value : 'USDT';
                unit.textContent = (type === 'usd') ? 'USD' : asset;
            }

            updateBalanceHint();
            validateAmount();
        }

        function setMax() {
            const asset = el('assetSelect') ? el('assetSelect').value : 'USDT';
            const amountType = el('amountType') ? el('amountType').value : 'usd';
            const bal = BALANCES[asset] || 0;
            const price = PRICES[asset] || 1.0;
            const input = el('amountInput');
            if (!input) return;

            if (amountType === 'usd') {
                const usdVal = (asset === 'USDT') ? bal : (bal * price);
                input.value = String(Math.floor(usdVal));
            } else {
                input.value = String(Math.floor(bal));
            }
            updateBalanceHint();
            validateAmount();
        }

        function updateBalanceHint() {
            const asset = el('assetSelect') ? el('assetSelect').value : 'USDT';
            const bal   = BALANCES[asset] ?? 0;
            const price = PRICES[asset] || 1.0;
            const hint  = el('balanceHint');
            const unit  = el('amountUnit');
            if (hint) {
                if (asset === 'USDT') {
                    hint.textContent = 'Available: $' + Number(bal).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' USDT';
                } else {
                    const usdVal = bal * price;
                    hint.textContent = 'Available: ' + Number(bal).toFixed(8) + ' ' + asset + ' ≈ $' + Number(usdVal).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                }
            }
            if (unit) {
                const amountType = el('amountType') ? el('amountType').value : 'usd';
                unit.textContent = (amountType === 'usd') ? 'USD' : asset;
            }
        }

        function showAmountError(msg) {
            const err = el('amountError');
            const submit = el('submitWithdrawBtn');
            if (err) {
                err.textContent = msg;
                err.classList.remove('hidden');
            }
            if (submit) submit.disabled = true;
        }

        function hideAmountError() {
            const err = el('amountError');
            const submit = el('submitWithdrawBtn');
            if (err) {
                err.textContent = '';
                err.classList.add('hidden');
            }
            if (submit) submit.disabled = false;
        }

        function validateAmount() {
            const input = el('amountInput');
            if (!input) return true;
            const raw = (input.value || '').toString().trim();
            if (raw === '') {
                // no input yet — don't show error
                hideAmountError();
                return true;
            }
            if (!/^\d+$/.test(raw)) {
                showAmountError('Amount must be a whole number (no decimals).');
                return false;
            }
            const amt = parseInt(raw, 10);
            if (isNaN(amt) || amt <= 0) {
                showAmountError('Please enter an amount greater than zero.');
                return false;
            }
            const asset = el('assetSelect') ? el('assetSelect').value : 'USDT';
            const amountType = el('amountType') ? el('amountType').value : 'usd';
            const price = PRICES[asset] || 1.0;
            let coinAmount;
            if (amountType === 'usd') {
                coinAmount = (asset === 'USDT') ? amt : (amt / price);
            } else {
                coinAmount = amt;
            }
            const bal = BALANCES[asset] || 0;
            if (coinAmount > bal + 1e-12) {
                showAmountError('Insufficient balance for ' + asset + '.');
                return false;
            }
            hideAmountError();
            return true;
        }

        // Safe event wiring
        const assetSelect = el('assetSelect');
        if (assetSelect) {
            assetSelect.addEventListener('change', function() {
                updateBalanceHint();
                // if currently in coin mode, update unit text to the new asset
                const amtType = el('amountType') ? el('amountType').value : 'usd';
                if (amtType === 'coin') setAmountType('coin');
                validateAmount();
            });
        }

        const amtInput = el('amountInput');
        if (amtInput) {
            // strip non-digits while typing and validate
            amtInput.addEventListener('input', function (e) {
                const v = this.value || '';
                const sanitized = v.toString().replace(/\D+/g, '');
                if (sanitized !== v) this.value = sanitized;
                validateAmount();
            });
            amtInput.addEventListener('blur', validateAmount);
        }

        const withdrawForm = el('withdrawForm');
        if (withdrawForm) {
            withdrawForm.addEventListener('submit', function (e) {
                const input = el('amountInput');
                if (input && (input.value || '').toString().trim() === '') {
                    showAmountError('Please enter an amount.');
                    e.preventDefault();
                    return false;
                }
                if (!validateAmount()) {
                    e.preventDefault();
                    return false;
                }
            });
        }

        // Default to crypto flow and default amount type on first load
        selectMethod('crypto');
        setAmountType(el('amountType') ? el('amountType').value || 'usd' : 'usd');

        // Pending OTP resend countdown (unchanged logic)
        (function() {
            const pendingBox = el('pendingBox');
            if (!pendingBox) return;
            const lastSent = parseInt(pendingBox.dataset.lastSent || '0', 10);
            const cooldown = parseInt(pendingBox.dataset.cooldown || '60', 10);
            const left = parseInt(pendingBox.dataset.left || '0', 10);
            const resendBtn = el('resendBtn');
            const resendLeft = el('resendLeft');
            if (!resendBtn) return;

            function update() {
                const now = Math.floor(Date.now() / 1000);
                const remaining = Math.max(0, (lastSent + cooldown) - now);
                if (left <= 0) {
                    resendBtn.disabled = true;
                    resendBtn.textContent = 'No resends left';
                    if (resendLeft) resendLeft.textContent = '0';
                    return;
                }
                if (remaining > 0) {
                    resendBtn.disabled = true;
                    resendBtn.textContent = 'Resend code (' + remaining + 's)';
                } else {
                    resendBtn.disabled = false;
                    resendBtn.textContent = 'Resend code';
                }
                if (resendLeft) resendLeft.textContent = String(left - 0);
            }

            update();
            setInterval(update, 1000);

            const resendForm = el('resendForm');
            if (resendForm) {
                resendForm.addEventListener('submit', function () {
                    resendBtn.disabled = true;
                    resendBtn.textContent = 'Sending...';
                });
            }
        })();
    </script>

</body>
</html>
