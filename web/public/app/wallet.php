<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/csrf.php';
require_once __DIR__ . '/../../src/helpers.php';

require_login();
$user  = current_user();
$error   = get_flash('error');
$success = get_flash('success');

// Handle Deposit Request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'deposit') {
    csrf_verify();

    $asset  = strtoupper(trim($_POST['asset_ticker'] ?? ''));
    $amount = (float)($_POST['amount'] ?? 0);
    $txid   = trim($_POST['txid']   ?? '');
    $addr   = trim($_POST['address'] ?? '');

    if ($asset === '' || $amount <= 0) {
        flash('error', 'Asset and a positive amount are required.');
        redirect('/app/wallet.php#deposit');
    }

    try {
        $stmt = db()->prepare(
            'INSERT INTO deposit_requests (user_id, asset_ticker, amount, txid, address, status)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$user['id'], $asset, $amount, $txid ?: null, $addr ?: null, 'pending']);
        flash('success', 'Deposit request submitted! It will be reviewed within 24 hours.');
    } catch (Throwable) {
        flash('error', 'Failed to submit deposit request.');
    }
    redirect('/app/wallet.php#deposit');
}

// Handle Withdrawal Request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'withdraw') {
    csrf_verify();

    $asset   = strtoupper(trim($_POST['asset_ticker'] ?? ''));
    $amount  = (float)($_POST['amount'] ?? 0);
    $address = trim($_POST['address'] ?? '');

    if ($asset === '' || $amount <= 0 || $address === '') {
        flash('error', 'Asset, amount, and wallet address are required.');
        redirect('/app/wallet.php#withdraw');
    }

    if ($amount > (float)$user['balance']) {
        flash('error', 'Insufficient balance.');
        redirect('/app/wallet.php#withdraw');
    }

    try {
        $stmt = db()->prepare(
            'INSERT INTO withdrawal_requests (user_id, asset_ticker, amount, address, status)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$user['id'], $asset, $amount, $address, 'pending']);
        flash('success', 'Withdrawal request submitted! It will be processed within 24 hours.');
    } catch (Throwable) {
        flash('error', 'Failed to submit withdrawal request.');
    }
    redirect('/app/wallet.php#withdraw');
}

// Fetch deposit addresses
$depositAddresses = [];
try {
    $depositAddresses = db()->query('SELECT * FROM deposit_addresses WHERE active = 1 ORDER BY asset_ticker')->fetchAll();
} catch (Throwable) {}

// Fetch user deposit requests
$depositHistory = [];
try {
    $st = db()->prepare(
        'SELECT * FROM deposit_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 20'
    );
    $st->execute([$user['id']]);
    $depositHistory = $st->fetchAll();
} catch (Throwable) {}

// Fetch user withdrawal requests
$withdrawHistory = [];
try {
    $st = db()->prepare(
        'SELECT * FROM withdrawal_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 20'
    );
    $st->execute([$user['id']]);
    $withdrawHistory = $st->fetchAll();
} catch (Throwable) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/images/favicon.png">
  <title>Wallet – CBOE Markets</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
  <style>
    body {
      background:
        radial-gradient(circle at top right, rgba(16, 185, 129, 0.14), transparent 28%),
        radial-gradient(circle at bottom left, rgba(59, 130, 246, 0.10), transparent 24%),
        linear-gradient(180deg, #ffffff 0%, #f8fafc 58%, #eefaf5 100%);
    }

    .wallet-panel {
      background: rgba(255, 255, 255, 0.92);
      border: 1px solid rgba(226, 232, 240, 0.95);
      box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
      backdrop-filter: blur(18px);
    }

    .wallet-qr-shell {
      background: linear-gradient(145deg, #ffffff, #f8fafc);
    }
  </style>
</head>
<body class="bg-white text-slate-900 min-h-screen pb-20">

  <header class="sticky top-0 z-40 bg-white/95 backdrop-blur border-b border-slate-200 px-4 py-3 flex items-center justify-between">
    <span class="text-xl font-extrabold text-emerald-400">Wallet</span>
  </header>

  <main class="max-w-lg mx-auto px-4 py-6 space-y-6">

    <?php if ($error): ?>
      <div class="bg-red-500/10 border border-red-500/30 text-red-600 text-sm rounded-lg px-4 py-3"><?= sanitize($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 text-sm rounded-lg px-4 py-3"><?= sanitize($success) ?></div>
    <?php endif; ?>

    <!-- Balance -->
    <div class="bg-gradient-to-br from-white via-emerald-50 to-sky-50 border border-slate-200 rounded-2xl p-6">
      <p class="text-emerald-700 text-sm">Available Balance</p>
      <p class="text-4xl font-extrabold text-slate-900 mt-1">$<?= number_format((float)$user['balance'], 2) ?></p>
      <p class="text-emerald-600 text-sm mt-1">USDT</p>
    </div>

    <!-- Deposit Section -->
    <div id="deposit" class="bg-white border border-slate-200 rounded-2xl p-5 space-y-4">
      <h2 class="font-bold text-slate-900 text-lg">Deposit Funds</h2>

      <?php if (!empty($depositAddresses)): ?>
      <div class="space-y-4">
        <p class="text-slate-600 text-sm">Send crypto to one of the addresses below, then submit your deposit request.</p>
        <div class="grid gap-4 md:grid-cols-2">
        <?php foreach ($depositAddresses as $da): ?>
        <div class="wallet-panel relative overflow-hidden rounded-[24px] p-4">
          <div class="absolute right-0 top-0 h-20 w-20 rounded-full bg-emerald-400/10 blur-2xl"></div>
          <div class="relative flex items-start justify-between gap-3 mb-3">
            <div>
              <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.22em] text-emerald-700"><?= sanitize($da['asset_ticker']) ?></span>
              <p class="mt-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Network</p>
              <p class="mt-1 text-sm font-semibold text-slate-900"><?= sanitize($da['network']) ?></p>
            </div>
            <div class="wallet-qr-shell rounded-2xl border border-slate-200 p-2">
              <div class="wallet-qr h-24 w-24 overflow-hidden rounded-xl bg-white" data-address="<?= sanitize($da['address']) ?>"></div>
            </div>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-slate-50/90 p-3">
            <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Wallet Address</p>
            <p class="mt-2 break-all text-sm font-mono text-slate-900"><?= sanitize($da['address']) ?></p>
          </div>
          <div class="mt-3 flex items-center justify-between gap-3">
            <span class="text-xs text-slate-500">Scan or copy instantly</span>
            <button type="button" class="copy-address inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-950 px-3 py-2 text-xs font-semibold text-white transition hover:bg-emerald-600" data-copy-text="<?= sanitize($da['address']) ?>">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16h8a2 2 0 002-2V6a2 2 0 00-2-2H8a2 2 0 00-2 2v8a2 2 0 002 2zm-2 4h8a2 2 0 002-2"></path></svg>
              Tap Copy
            </button>
          </div>
        </div>
        <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <form method="POST" action="wallet.php" class="space-y-3">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="deposit">

        <div>
        <script>
          (function () {
            const qrNodes = document.querySelectorAll('.wallet-qr');
            qrNodes.forEach((node) => {
              const address = node.dataset.address || '';
              if (!address || typeof QRCode === 'undefined') {
                return;
              }

              new QRCode(node, {
                text: address,
                width: 96,
                height: 96,
                colorDark: '#0f172a',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M,
              });
            });

            async function copyText(value) {
              if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(value);
                return;
              }

              const textarea = document.createElement('textarea');
              textarea.value = value;
              textarea.setAttribute('readonly', 'readonly');
              textarea.style.position = 'absolute';
              textarea.style.left = '-9999px';
              document.body.appendChild(textarea);
              textarea.select();
              document.execCommand('copy');
              document.body.removeChild(textarea);
            }

            document.querySelectorAll('.copy-address').forEach((button) => {
              button.addEventListener('click', async function () {
                const text = this.dataset.copyText || '';
                if (!text) {
                  return;
                }

                const label = this.lastChild;

                try {
                  await copyText(text);
                  this.classList.remove('bg-slate-950');
                  this.classList.add('bg-emerald-600');
                  if (label && label.nodeType === Node.TEXT_NODE) {
                    label.textContent = ' Copied';
                  }
                } catch (error) {
                  if (label && label.nodeType === Node.TEXT_NODE) {
                    label.textContent = ' Failed';
                  }
                }

                window.setTimeout(() => {
                  this.classList.add('bg-slate-950');
                  this.classList.remove('bg-emerald-600');
                  if (label && label.nodeType === Node.TEXT_NODE) {
                    label.textContent = ' Tap Copy';
                  }
                }, 1800);
              });
            });
          })();
        </script>
          <label class="block text-sm text-slate-600 mb-1.5">Asset</label>
          <select name="asset_ticker" class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500">
            <option value="BTC">BTC – Bitcoin</option>
            <option value="ETH">ETH – Ethereum</option>
            <option value="USDT">USDT – Tether</option>
          </select>
        </div>
        <div>
          <label class="block text-sm text-slate-600 mb-1.5">Amount</label>
          <input type="number" name="amount" min="0.00000001" step="0.00000001" required
            class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500"
            placeholder="0.00">
        </div>
        <div>
          <label class="block text-sm text-slate-600 mb-1.5">Transaction ID (TXID)</label>
          <input type="text" name="txid"
            class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 font-mono"
            placeholder="Paste your transaction hash">
        </div>
        <div>
          <label class="block text-sm text-slate-600 mb-1.5">From Address (optional)</label>
          <input type="text" name="address"
            class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 font-mono"
            placeholder="Your sending wallet address">
        </div>
        <button type="submit" class="w-full bg-emerald-500 hover:bg-emerald-400 text-white font-bold py-3 rounded-xl transition">
          Submit Deposit Request
        </button>
      </form>
    </div>

    <!-- Withdraw Section -->
    <div id="withdraw" class="bg-white border border-slate-200 rounded-2xl p-5 space-y-4">
      <h2 class="font-bold text-slate-900 text-lg">Withdraw Funds</h2>

      <form method="POST" action="wallet.php" class="space-y-3">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="withdraw">

        <div>
          <label class="block text-sm text-slate-600 mb-1.5">Asset</label>
          <select name="asset_ticker" class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500">
            <option value="BTC">BTC – Bitcoin</option>
            <option value="ETH">ETH – Ethereum</option>
            <option value="USDT">USDT – Tether</option>
          </select>
        </div>
        <div>
          <label class="block text-sm text-slate-600 mb-1.5">Amount</label>
          <input type="number" name="amount" min="0.00000001" step="0.00000001" required
            max="<?= sanitize(number_format((float)$user['balance'], 2, '.', '')) ?>"
            class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500"
            placeholder="0.00">
          <p class="text-slate-600 text-xs mt-1">Available: $<?= number_format((float)$user['balance'], 2) ?></p>
        </div>
        <div>
          <label class="block text-sm text-slate-600 mb-1.5">Withdrawal Address</label>
          <input type="text" name="address" required
            class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 font-mono"
            placeholder="Your receiving wallet address">
        </div>
        <button type="submit" class="w-full bg-yellow-500 hover:bg-yellow-400 text-white font-bold py-3 rounded-xl transition">
          Request Withdrawal
        </button>
      </form>
    </div>

    <!-- Transaction History -->
    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-200">
        <h2 class="font-bold text-slate-900">Transaction History</h2>
      </div>

      <?php
        $allTx = [];
        foreach ($depositHistory as $d) {
            $allTx[] = ['type'=>'Deposit', 'asset'=>$d['asset_ticker'], 'amount'=>$d['amount'], 'status'=>$d['status'], 'date'=>$d['created_at']];
        }
        foreach ($withdrawHistory as $w) {
            $allTx[] = ['type'=>'Withdrawal', 'asset'=>$w['asset_ticker'], 'amount'=>$w['amount'], 'status'=>$w['status'], 'date'=>$w['created_at']];
        }
        usort($allTx, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));
      ?>

      <?php if (empty($allTx)): ?>
        <div class="px-5 py-8 text-center text-slate-600">No transactions yet.</div>
      <?php else: ?>
        <div class="divide-y divide-slate-700">
          <?php foreach ($allTx as $tx): ?>
          <?php
            $statusColors = ['pending'=>'text-yellow-700 bg-yellow-500/10', 'approved'=>'text-emerald-700 bg-emerald-500/10', 'rejected'=>'text-red-600 bg-red-500/10'];
            $sc = $statusColors[$tx['status']] ?? 'text-slate-600 bg-white';
          ?>
          <div class="px-5 py-3 flex items-center justify-between">
            <div>
              <p class="text-sm font-semibold text-slate-900"><?= $tx['type'] ?> – <?= sanitize($tx['asset']) ?></p>
              <p class="text-xs text-slate-600"><?= sanitize($tx['date']) ?></p>
            </div>
            <div class="text-right">
              <p class="text-sm font-bold text-slate-900"><?= format_currency((float)$tx['amount']) ?></p>
              <span class="text-xs px-2 py-0.5 rounded-full <?= $sc ?>"><?= ucfirst($tx['status']) ?></span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  </main>

  <!-- Navigation -->
  <?php $activePage = 'wallet.php'; include '_nav.php'; ?>

</body>
</html>


