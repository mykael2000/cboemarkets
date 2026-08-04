<?php
/**
 * Shared navigation template for /app/* pages.
 * Set $activePage to the current filename (e.g. 'index.php') before including.
 */
declare(strict_types=1);
$activePage = $activePage ?? basename($_SERVER['PHP_SELF'] ?? '');

$_navPage = $activePage;
?>
<!-- ═══════════════════════════════════════════
     DESKTOP NAVIGATION (hidden on mobile)
═══════════════════════════════════════════ -->
<nav class="hidden md:flex items-center gap-0.5 bg-white/95 border-b border-slate-100 px-4 py-1 shadow-sm backdrop-blur">
    <div class="max-w-7xl mx-auto w-full flex items-center gap-0.5 flex-wrap">

        <a href="index.php"
           class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium transition <?= $_navPage === 'index.php' ? 'text-emerald-600 bg-emerald-50' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            <span>Dashboard</span>
        </a>

        <a href="markets.php"
           class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium transition <?= $_navPage === 'markets.php' ? 'text-emerald-600 bg-emerald-50' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/></svg>
            <span>Markets</span>
        </a>

        <a href="trading.php"
           class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium transition <?= $_navPage === 'trading.php' ? 'text-emerald-600 bg-emerald-50' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
            <span>Trade</span>
        </a>

        <a href="deposit.php"
           class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium transition <?= $_navPage === 'deposit.php' ? 'text-emerald-600 bg-emerald-50' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
            <span>Deposit</span>
        </a>

        <a href="withdraw.php"
           class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium transition <?= $_navPage === 'withdraw.php' ? 'text-emerald-600 bg-emerald-50' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            <span>Withdraw</span>
        </a>

        <a href="swap.php"
           class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium transition <?= $_navPage === 'swap.php' ? 'text-emerald-600 bg-emerald-50' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m10 4v12m0 0l-4-4m4 4l4-4"/></svg>
            <span>Swap</span>
        </a>

        <a href="trading.php"
           class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium transition <?= $_navPage === 'trading.php' ? 'text-emerald-600 bg-emerald-50' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
            <span>Trade</span>
        </a>

        <a href="profile.php"
           class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium transition <?= $_navPage === 'profile.php' ? 'text-emerald-600 bg-emerald-50' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            <span>Profile</span>
        </a>

    </div>
</nav>

<!-- ═══════════════════════════════════════════
     MOBILE BOTTOM NAVIGATION
═══════════════════════════════════════════ -->
<nav class="fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur border-t border-slate-200 flex justify-around py-1.5 z-50 md:hidden">
    <a href="index.php" class="flex flex-col items-center gap-0.5 py-1 px-2 <?= $_navPage === 'index.php' ? 'text-emerald-500' : 'text-slate-500 hover:text-emerald-400' ?> transition">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
        <span class="text-[10px] font-semibold">Home</span>
    </a>
    <a href="markets.php" class="flex flex-col items-center gap-0.5 py-1 px-2 <?= $_navPage === 'markets.php' ? 'text-emerald-500' : 'text-slate-500 hover:text-emerald-400' ?> transition">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/></svg>
        <span class="text-[10px] font-semibold">Markets</span>
    </a>
    <a href="swap.php" class="flex flex-col items-center gap-0.5 py-1 px-2 <?= $_navPage === 'swap.php' ? 'text-emerald-500' : 'text-slate-500 hover:text-emerald-400' ?> transition">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m10 4v12m0 0l-4-4m4 4l4-4"/></svg>
        <span class="text-[10px] font-semibold">Swap</span>
    </a>
    <a href="trading.php" class="flex flex-col items-center gap-0.5 py-1 px-2 <?= $_navPage === 'trading.php' ? 'text-emerald-500' : 'text-slate-500 hover:text-emerald-400' ?> transition">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
        <span class="text-[10px] font-semibold">Trade</span>
    </a>
    <a href="profile.php" class="flex flex-col items-center gap-0.5 py-1 px-2 <?= $_navPage === 'profile.php' ? 'text-emerald-500' : 'text-slate-500 hover:text-emerald-400' ?> transition">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
        <span class="text-[10px] font-semibold">Profile</span>
    </a>
</nav>

<!-- Smartsupp Live Chat -->
<script type="text/javascript">
var _smartsupp = _smartsupp || {};
_smartsupp.key = '974526ed39790a589cf4d6ee38fc45e6e627627d';
// On mobile, push the widget above the fixed bottom nav bar (~80px tall)
if (window.innerWidth < 768) {
  _smartsupp.offsetY = 80;
}
window.smartsupp||(function(d) {
  var s,c,o=smartsupp=function(){ o._.push(arguments)};o._=[];
  s=d.getElementsByTagName('script')[0];c=d.createElement('script');
  c.type='text/javascript';c.charset='utf-8';c.async=true;
  c.src='https://www.smartsuppchat.com/loader.js?';s.parentNode.insertBefore(c,s);
})(document);
</script>
<noscript>Powered by <a href="https://www.smartsupp.com" target="_blank">Smartsupp</a></noscript>
