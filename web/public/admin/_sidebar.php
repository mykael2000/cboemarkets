<?php
/**
 * Shared admin sidebar.
 * Include this file from any /admin/* page.
 * Set $activeAdminPage to the current filename (e.g. 'users.php') before including.
 */
$_adminPage = $activeAdminPage ?? basename($_SERVER['PHP_SELF'] ?? '');
?>
<button
  id="adminSidebarOpen"
  type="button"
  class="lg:hidden fixed top-4 left-4 z-30 inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-700 bg-slate-900/90 text-slate-200 shadow-lg backdrop-blur"
  aria-controls="adminSidebar"
  aria-expanded="false"
  aria-label="Open admin navigation"
>
  <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
  </svg>
</button>

<div id="adminSidebarOverlay" class="hidden fixed inset-0 z-30 bg-slate-950/70 lg:hidden" aria-hidden="true"></div>

<aside id="adminSidebar" class="fixed inset-y-0 left-0 z-40 w-72 max-w-[85vw] -translate-x-full border-r border-slate-800 bg-slate-900/95 p-4 shadow-2xl backdrop-blur transition-transform duration-300 ease-out lg:static lg:z-auto lg:w-64 lg:max-w-none lg:translate-x-0 lg:shadow-none lg:bg-slate-900">
  <div class="mb-6 flex items-center justify-between">
    <div class="text-emerald-400 font-bold text-xl">CBOE Markets Admin</div>
    <button
      id="adminSidebarClose"
      type="button"
      class="lg:hidden inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-700 text-slate-300"
      aria-label="Close admin navigation"
    >
      <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
      </svg>
    </button>
  </div>

  <nav class="space-y-1">
    <?php
      $links = [
        'index.php'         => 'Dashboard',
        'add_balances.php'  => 'Add Balances',
        'plans.php'         => 'Plans',
        'subscriptions.php' => 'Subscriptions',
        'copy_traders.php'  => 'Copy Traders',
        'copy_requests.php' => 'Copy Requests',
        'addresses.php'     => 'Addresses',
        'deposits.php'      => 'Deposits',
        'withdrawals.php'   => 'Withdrawals',
        'kyc.php'           => 'KYC Review',
        'documents.php'     => 'Documents',
        'notices.php'       => 'Notices',
        'mailer.php'        => 'Email Users',
        'users.php'         => 'Users',
        'stocks.php'        => 'Stocks',
      ];
      foreach ($links as $file => $label):
        $isActive = $_adminPage === $file;
    ?>
    <a href="/admin/<?= $file ?>"
      class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition
        <?= $isActive ? 'bg-slate-800 text-emerald-400 ring-1 ring-emerald-500/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
      <?= sanitize($label) ?>
    </a>
    <?php endforeach; ?>
    <hr class="border-slate-700 my-3">
    <a href="/app/index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-slate-400 hover:text-white transition">User Dashboard</a>
  </nav>
</aside>

<script>
(() => {
  const sidebar = document.getElementById('adminSidebar');
  const openBtn = document.getElementById('adminSidebarOpen');
  const closeBtn = document.getElementById('adminSidebarClose');
  const overlay = document.getElementById('adminSidebarOverlay');
  if (!sidebar || !openBtn || !overlay) return;

  const isMobile = () => window.matchMedia('(max-width: 1023px)').matches;

  const closeSidebar = () => {
    if (!isMobile()) return;
    sidebar.classList.add('-translate-x-full');
    overlay.classList.add('hidden');
    openBtn.setAttribute('aria-expanded', 'false');
    document.body.classList.remove('overflow-hidden');
  };

  const openSidebar = () => {
    if (!isMobile()) return;
    sidebar.classList.remove('-translate-x-full');
    overlay.classList.remove('hidden');
    openBtn.setAttribute('aria-expanded', 'true');
    document.body.classList.add('overflow-hidden');
  };

  const syncForViewport = () => {
    if (isMobile()) {
      closeSidebar();
      return;
    }

    sidebar.classList.remove('-translate-x-full');
    overlay.classList.add('hidden');
    openBtn.setAttribute('aria-expanded', 'false');
    document.body.classList.remove('overflow-hidden');
  };

  openBtn.addEventListener('click', () => {
    if (sidebar.classList.contains('-translate-x-full')) {
      openSidebar();
      return;
    }
    closeSidebar();
  });

  if (closeBtn) {
    closeBtn.addEventListener('click', closeSidebar);
  }

  overlay.addEventListener('click', closeSidebar);

  sidebar.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', closeSidebar);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeSidebar();
    }
  });

  window.addEventListener('resize', syncForViewport);
  syncForViewport();
})();
</script>
