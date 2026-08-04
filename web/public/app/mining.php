<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/auth.php';

require_login();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/images/favicon.png">
  <title>Crypto Mining - CBOE Markets</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen pb-24 md:pb-6">
  <?php $activePage = 'index.php'; include '_nav.php'; ?>

  <main class="max-w-5xl mx-auto px-4 py-6 space-y-6">
    <section class="rounded-3xl border border-slate-200 bg-gradient-to-br from-amber-50 via-white to-yellow-50 p-6">
      <p class="text-xs font-semibold uppercase tracking-[0.25em] text-amber-600">Mining Hub</p>
      <h1 class="mt-3 text-3xl font-black text-slate-900">Crypto mining overview and pool access</h1>
      <p class="mt-2 max-w-2xl text-sm text-slate-600">Review managed mining themes, mining economics, and operating notes from the platform. This section gives users a clean destination instead of dumping them into a generic documents page.</p>
    </section>

    <section class="grid gap-4 md:grid-cols-3">
      <article class="rounded-2xl border border-slate-200 bg-white p-5">
        <h2 class="text-lg font-bold text-slate-900">Pool Access</h2>
        <p class="mt-2 text-sm text-slate-600">Managed pool onboarding, payout cadence, and operating model can be published here as the mining product expands.</p>
      </article>
      <article class="rounded-2xl border border-slate-200 bg-white p-5">
        <h2 class="text-lg font-bold text-slate-900">Hardware Notes</h2>
        <p class="mt-2 text-sm text-slate-600">Summaries for ASIC and GPU lanes, profitability assumptions, and maintenance windows can be surfaced without cluttering the dashboard.</p>
      </article>
      <article class="rounded-2xl border border-slate-200 bg-white p-5">
        <h2 class="text-lg font-bold text-slate-900">Reports</h2>
        <p class="mt-2 text-sm text-slate-600">Use the documents section for contracts and monthly statements, while this page remains the product landing page for mining-related actions.</p>
      </article>
    </section>

    <a href="documents.php" class="inline-flex rounded-2xl bg-amber-500 px-5 py-3 text-sm font-bold text-white transition hover:bg-amber-400">Open Mining Documents</a>
  </main>
</body>
</html>