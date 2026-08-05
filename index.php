<?php
// index.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Cboe </title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    @keyframes fadeUp { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fade-up { animation: fadeUp 600ms cubic-bezier(.22,.9,.39,1) both; }
    .animate-fade-up-delay-1 { animation-delay: 80ms; }
    .animate-fade-up-delay-2 { animation-delay: 160ms; }
    .animate-fade-up-delay-3 { animation-delay: 240ms; }
    .animate-fade-up-delay-4 { animation-delay: 320ms; }
    .icon-bg { background: linear-gradient(135deg, rgba(11,21,59,0.06), rgba(46,230,241,0.06)); }

    /* Denser diagonal line patterns used in the hero background */
    .hero-lines-1, .hero-lines-2 {
      position: absolute;
      inset: 0;
      pointer-events: none;
      background: repeating-linear-gradient(-45deg, rgba(255,255,255,0.14) 0 1px, transparent 1px 8px);
      mix-blend-mode: overlay;
    }

    /* shape the stripes into chevron/diamond-like regions */
    .hero-lines-1 { clip-path: polygon(60% 0, 100% 0, 100% 100%, 30% 100%); opacity: 0.95; }
    .hero-lines-2 { clip-path: polygon(0 0, 90% 0, 50% 100%, 0 100%); opacity: 0.85; }

    /* subtle outline to echo the large chevron in the sample */
    .hero-chevron-outline {
      position: absolute;
      border: 1.5px solid rgba(255,255,255,0.32);
      width: 520px;
      height: 520px;
      right: -44px;
      top: -34px;
      transform: rotate(45deg);
      border-radius: 4px;
      opacity: 0.18;
      pointer-events: none;
    }
  </style>
</head>

<body class="bg-white font-sans text-[#0e1d5a] overflow-x-hidden antialiased">

  <!-- HEADER -->
  <header class="relative z-50 w-full bg-[#1b2147] border-b-[3px] border-[#ff4fd8]">
    <div class="max-w-[1500px] mx-auto px-4 sm:px-6 lg:px-10 h-[72px] sm:h-[82px] flex items-center justify-between gap-4">

      <a href="#" class="flex items-center gap-2 shrink-0">
        <div class="text-white font-bold text-[2.2rem] sm:text-[2.6rem] lg:text-[3rem] leading-none tracking-tight">
          C<span class="relative inline-block">b
            <span class="absolute -top-1 left-[7px] text-[0.55rem] sm:text-[0.65rem] text-[#39ff8e] rotate-45">◆</span>
            oe
          </span>
        </div>
      </a>

      <nav id="mobileNav" class="hidden xl:flex items-center gap-8 2xl:gap-10 text-white font-semibold text-[1rem] 2xl:text-[1.05rem]">
        <a href="#" class="hover:text-[#39ff8e] transition">Markets</a>
        <a href="#" class="hover:text-[#39ff8e] transition">Data</a>
        <a href="#" class="hover:text-[#39ff8e] transition">Solutions</a>
        <a href="#" class="hover:text-[#39ff8e] transition">Insights &amp; Education</a>
        <a href="#" class="hover:text-[#39ff8e] transition">About Us</a>
      </nav>

      <div class="flex items-center gap-3 sm:gap-5">
        <button class="text-white hover:text-[#39ff8e] transition" aria-label="Search">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 sm:w-8 sm:h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="7"></circle>
            <path d="M20 20l-3.5-3.5"></path>
          </svg>
        </button>

        <a href="web/public/signin.php" class="hidden sm:inline-flex px-5 lg:px-6 py-2.5 lg:py-3 rounded-full border-2 border-[#39ff8e] text-[#39ff8e] font-semibold text-[0.95rem] lg:text-[1rem] hover:bg-[#39ff8e] hover:text-[#1b2147] transition">
          Sign In
        </a>

        <button
          id="menuToggle"
          class="xl:hidden inline-flex items-center justify-center w-10 h-10 rounded-md border border-white/20 text-white"
          aria-label="Toggle navigation"
          aria-expanded="false"
          aria-controls="mobileMenu">
          ☰
        </button>
      </div>
    </div>

    <!-- Mobile dropdown -->
    <div id="mobileMenu" class="hidden xl:hidden border-t border-white/10 bg-[#171d3f]">
      <div class="max-w-[1500px] mx-auto px-4 sm:px-6 py-4 flex flex-col gap-2 text-white font-semibold">
        <a href="#" class="px-3 py-3 rounded-md hover:bg-white/10 active:bg-white/10">Markets</a>
        <a href="#" class="px-3 py-3 rounded-md hover:bg-white/10 active:bg-white/10">Data</a>
        <a href="#" class="px-3 py-3 rounded-md hover:bg-white/10 active:bg-white/10">Solutions</a>
        <a href="#" class="px-3 py-3 rounded-md hover:bg-white/10 active:bg-white/10">Insights &amp; Education</a>
        <a href="#" class="px-3 py-3 rounded-md hover:bg-white/10 active:bg-white/10">About Us</a>
        <a href="web/public/signin.php" class="mt-2 inline-flex justify-center px-5 py-3 rounded-full border-2 border-[#39ff8e] text-[#39ff8e] hover:bg-[#39ff8e] hover:text-[#1b2147] transition">
          Sign In
        </a>
      </div>
    </div>
  </header>

<main>

  <!-- HERO -->
  <section class="relative overflow-hidden bg-[#27e5f0]">
    <div class="absolute inset-0 pointer-events-none z-0">
      <div class="absolute inset-0 bg-[#27e5f0]"></div>

      <video class="absolute inset-0 w-full h-full object-contain lg:object-cover z-10" style="object-position:top center;" autoplay muted loop playsinline preload="metadata" aria-hidden="true">
        <source src="https://cdn.cboe.com/assets/video/home/WEB_HEADER_DESKTOP_V1.webm" type="video/webm" />
      </video>

      <div class="absolute left-1/2 top-1/2 -translate-x-[72%] -translate-y-[52%] w-[540px] h-[540px] sm:w-[700px] sm:h-[700px] lg:w-[900px] lg:h-[900px] rotate-45 opacity-70 z-0">
        <div class="absolute inset-0 hero-lines-1"></div>
        <div class="absolute inset-0 hero-chevron-outline hidden sm:block"></div>
      </div>

      <div class="absolute left-1/2 top-1/2 translate-x-[12%] -translate-y-[42%] w-[540px] h-[540px] sm:w-[700px] sm:h-[700px] lg:w-[900px] lg:h-[900px] rotate-45 opacity-70 z-0">
        <div class="absolute inset-0 hero-lines-2"></div>
      </div>

      <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,rgba(255,255,255,0.12),rgba(255,255,255,0)_55%)] z-0"></div>
    </div>

    <div class="max-w-[1500px] mx-auto px-4 sm:px-6 lg:px-10 pt-4 sm:pt-8 lg:pt-24 pb-48 sm:pb-40 md:pb-28 lg:pb-24 sm:min-h-[calc(100vh-82px)] flex items-start sm:items-center relative z-20">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center w-full">
        <div class="max-w-[560px] mx-0 lg:mx-0 text-left">
          <div class="inline-flex items-center px-4 py-2 rounded-md bg-white text-[#111827] font-semibold text-[0.9rem] sm:text-[0.95rem] mb-6 shadow-sm">
            New
          </div>

          <h1 class="text-[#0e1d5a] font-bold leading-[0.9] tracking-tight text-[3rem] sm:text-[4rem] lg:text-[4.8rem] xl:text-[5.2rem] text-left lg:text-left">
            Trade the<br />
            Market's<br />
            Next Move
          </h1>
        </div>

        <div class="lg:justify-self-end max-w-[760px] mx-0 lg:mx-auto text-left lg:text-left">
          <h2 class="text-[#0e1d5a] font-bold leading-[0.9] tracking-tight text-[3.4rem] sm:text-[4.5rem] lg:text-[6rem] xl:text-[6.7rem]">
            Meet<br />
            S&amp;P 500<sup class="text-[0.35em] align-top">®</sup><br />
            Predictions
          </h2>
        </div>

        <div class="w-full lg:col-start-1 lg:col-end-2 lg:justify-self-start mt-6 lg:mt-0">
          <a href="#" class="inline-flex w-full lg:w-auto items-center justify-center px-6 py-3 rounded-full border-2 border-[#0e1d5a] text-[#0e1d5a] font-semibold text-[1rem] hover:bg-[#0e1d5a] hover:text-white transition">
            Cboe Predictions
          </a>
        </div>
      </div>
    </div>
  </section>
    <!-- VOLUME SNAPSHOT -->
    <section class="bg-[#141c3f] text-white relative -mt-44 sm:-mt-32 md:-mt-12 lg:mt-0 z-20">
      <div class="max-w-[1500px] mx-auto px-4 sm:px-6 lg:px-10 pt-2 sm:pt-4 md:pt-6 lg:pt-8 pb-10 lg:pb-12">
        <div class="flex flex-col sm:flex-row items-start sm:items-baseline gap-1 sm:gap-2 mb-8 lg:mb-10 text-left">
          <p class="text-[1.05rem] font-semibold tracking-tight">VOLUME SNAPSHOT</p>
          <p class="text-[0.95rem] text-[#9ca3c9]">(daily volume for July 20, 2026)</p>
        </div>

        <!-- Mobile stacked snapshot (left-aligned, tighter spacing) -->
        <div class="block sm:hidden">
          <div class="mx-auto w-[94%] max-w-[420px] bg-[#0b153b] rounded-2xl p-3 shadow-lg text-left">
            <div class="text-white font-semibold tracking-widest uppercase text-xs mb-2">VOLUME SNAPSHOT</div>
            <div class="space-y-1">
              <div class="bg-[#0e2348] rounded-md p-2">
                <div class="text-[0.72rem] font-semibold text-[#9ca3c9] uppercase tracking-wider">SPX Index Options</div>
                <div class="mt-1 flex flex-col sm:flex-row sm:items-baseline sm:justify-between items-start gap-0.5">
                  <div class="text-2xl font-bold text-[#2fe676] leading-none">4.71M</div>
                  <div class="text-xs text-[#9ca3c9]">Volume</div>
                </div>
              </div>

              <div class="bg-[#0e2348] rounded-md p-2">
                <div class="text-[0.72rem] font-semibold text-[#9ca3c9] uppercase tracking-wider">VIX Index Options</div>
                <div class="mt-1 flex flex-col sm:flex-row sm:items-baseline sm:justify-between items-start gap-0.5">
                  <div class="text-2xl font-bold text-[#2fe676] leading-none">642.23K</div>
                  <div class="text-xs text-[#9ca3c9]">Volume</div>
                </div>
              </div>

              <div class="bg-[#0e2348] rounded-md p-2">
                <div class="text-[0.72rem] font-semibold text-[#9ca3c9] uppercase tracking-wider">VIX Futures</div>
                <div class="mt-1 flex flex-col sm:flex-row sm:items-baseline sm:justify-between items-start gap-0.5">
                  <div class="text-2xl font-bold text-[#2fe676] leading-none">249.69K</div>
                  <div class="text-xs text-[#9ca3c9]">Volume</div>
                </div>
              </div>

              <div class="bg-[#0e2348] rounded-md p-2">
                <div class="text-[0.72rem] font-semibold text-[#9ca3c9] uppercase tracking-wider">Industry Volume</div>
                <div class="mt-1 flex flex-col items-start">
                  <div class="text-2xl font-bold text-[#2fe676] leading-none">64.24M</div>
                  <div class="text-xs text-[#9ca3c9] mt-1">Total Options Industry Volume in Contracts</div>
                </div>
              </div>
            </div>
            <div class="mt-3 flex justify-start">
              <div class="flex items-center gap-2">
                <span class="w-2 h-2 bg-white rounded-full opacity-90"></span>
                <span class="w-2 h-2 bg-white rounded-full opacity-40"></span>
                <span class="w-2 h-2 bg-white rounded-full opacity-40"></span>
              </div>
            </div>
          </div>
        </div>

        <!-- Desktop / larger screens: original snapshot grid -->
        <div class="hidden sm:grid sm:grid-cols-2 xl:grid-cols-6 gap-6 lg:gap-8 xl:gap-10 items-stretch justify-items-center sm:justify-items-stretch">
          
          <button
            type="button"
            class="hidden xl:flex w-12 h-12 rounded-full bg-[#222b56] items-center justify-center text-[#b3bddf] hover:bg-[#2d3766] hover:text-white transition self-center"
            aria-label="Previous day volume snapshot">
            <span class="text-2xl leading-none -translate-x-[1px]">&lsaquo;</span>
          </button>

          <div class="sm:border-l sm:border-[#2c355f] px-4 sm:pl-6 lg:pl-8 text-center sm:text-left">
            <p class="text-[1.1rem] lg:text-[1.25rem] font-semibold mb-3">SPX Index Options</p>
            <p class="text-[2.2rem] sm:text-[2.5rem] lg:text-[2.6rem] font-bold text-[#2fe676] leading-none mb-1">4.71M</p>
            <p class="text-[0.8rem] sm:text-[0.85rem] tracking-[0.12em] uppercase text-[#9ca3c9]">Volume</p>
          </div>

          <div class="sm:border-l sm:border-[#2c355f] px-4 sm:pl-6 lg:pl-8 text-center sm:text-left">
            <p class="text-[1.1rem] lg:text-[1.25rem] font-semibold mb-3">VIX Index Options</p>
            <p class="text-[2.2rem] sm:text-[2.5rem] lg:text-[2.6rem] font-bold text-[#2fe676] leading-none mb-1">642.23K</p>
            <p class="text-[0.8rem] sm:text-[0.85rem] tracking-[0.12em] uppercase text-[#9ca3c9]">Volume</p>
          </div>

          <div class="sm:border-l sm:border-[#2c355f] px-4 sm:pl-6 lg:pl-8 text-center sm:text-left">
            <p class="text-[1.1rem] lg:text-[1.25rem] font-semibold mb-3">VIX Futures</p>
            <p class="text-[2.2rem] sm:text-[2.5rem] lg:text-[2.6rem] font-bold text-[#2fe676] leading-none mb-1">249.69K</p>
            <p class="text-[0.8rem] sm:text-[0.85rem] tracking-[0.12em] uppercase text-[#9ca3c9]">Volume</p>
          </div>

          <div class="sm:border-l sm:border-[#2c355f] px-4 sm:pl-6 lg:pl-8 text-center sm:text-left">
            <p class="text-[1.1rem] lg:text-[1.25rem] font-semibold mb-3">Industry Volume</p>
            <p class="text-[2.2rem] sm:text-[2.5rem] lg:text-[2.6rem] font-bold text-[#2fe676] leading-none mb-1">64.24M</p>
            <p class="text-[0.8rem] sm:text-[0.85rem] tracking-[0.12em] uppercase text-[#9ca3c9] leading-snug">
              Total Options Industry<br />
              Volume in Contracts
            </p>
          </div>

          <button
            type="button"
            class="hidden xl:flex w-12 h-12 rounded-full bg-[#2fe676] items-center justify-center text-[#0b1935] hover:bg-[#34f08a] transition self-center ml-auto"
            aria-label="Next day volume snapshot">
            <span class="text-2xl leading-none translate-x-[1px]">&rsaquo;</span>
          </button>
        </div>
      </div>
    </section>

    <!-- COMMUNITY -->
    <section id="community" class="bg-white py-14 sm:py-16 lg:py-20">
      <div class="max-w-[1200px] mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-8 lg:mb-12 animate-fade-up">
          <h2 class="text-[#0b153b] font-bold text-[2rem] sm:text-[2.5rem] leading-tight">Welcome to the Cboe Global Markets</h2>
          <p class="mt-2 text-[#475569] text-[1.05rem]">Trade Smarter. Learn Together.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 items-start">
          <div class="animate-fade-up animate-fade-up-delay-1">
            <p class="text-[#24335c] leading-7 mb-4">The Cboe Markets Community is where traders, investors, market professionals, and enthusiasts come together to exchange ideas, explore market trends, and deepen their understanding of today’s global financial markets.</p>

            <p class="text-[#24335c] leading-7 mb-6">Whether you’re just beginning your investment journey or you’re an experienced professional, our community provides a collaborative space to learn, discuss, and grow alongside others who share a passion for the markets.</p>

            <h3 class="text-[#0b153b] font-semibold text-[1.15rem] mb-4">What You’ll Find</h3>

            <ul class="space-y-4 text-[#475569]">
              <li class="flex gap-3 items-start">
                <div class="w-10 h-10 rounded-md bg-[#eef2ff] text-[#1e3a8a] flex items-center justify-center shrink-0 mt-1">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 3v18h18" stroke-linecap="round" stroke-linejoin="round"/><path d="M21 7H9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
                <div>
                  <strong class="text-[#0b153b] block">Market Insights</strong>
                  <span>Stay informed with discussions on equities, options, futures, ETFs, volatility, and emerging market trends.</span>
                </div>
              </li>

              <li class="flex gap-3 items-start">
                <div class="w-10 h-10 rounded-md bg-[#eefbf7] text-[#065f46] flex items-center justify-center shrink-0 mt-1">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 19.5A7.5 7.5 0 0112 12h8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
                <div>
                  <strong class="text-[#0b153b] block">Education &amp; Learning</strong>
                  <span>Access educational content, webinars, tutorials, and community-driven resources designed to help you build confidence and expand your market knowledge.</span>
                </div>
              </li>

              <li class="flex gap-3 items-start">
                <div class="w-10 h-10 rounded-md bg-[#f5f3ff] text-[#6d28d9] flex items-center justify-center shrink-0 mt-1">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                </div>
                <div>
                  <strong class="text-[#0b153b] block">Community Discussions</strong>
                  <span>Connect with fellow members, ask questions, share perspectives, and participate in meaningful conversations about market developments and trading strategies.</span>
                </div>
              </li>

              <li class="flex gap-3 items-start">
                <div class="w-10 h-10 rounded-md bg-[#fff7ed] text-[#b45309] flex items-center justify-center shrink-0 mt-1">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="16" rx="2" /><path d="M16 2v4M8 2v4" /></svg>
                </div>
                <div>
                  <strong class="text-[#0b153b] block">Events &amp; Webinars</strong>
                  <span>Join live sessions featuring industry experts, market updates, and educational events that keep you connected to the latest developments.</span>
                </div>
              </li>

              <li class="flex gap-3 items-start">
                <div class="w-10 h-10 rounded-md bg-[#ecfeff] text-[#055e66] flex items-center justify-center shrink-0 mt-1">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 7h18M3 12h12M3 17h18"/></svg>
                </div>
                <div>
                  <strong class="text-[#0b153b] block">Resources</strong>
                  <span>Discover tools, guides, and reference materials to support your market research and decision-making.</span>
                </div>
              </li>
            </ul>
          </div>

          <div class="animate-fade-up animate-fade-up-delay-2">
            <h3 class="text-[#0b153b] font-semibold text-[1.15rem] mb-4">Why Join Our Community?</h3>

            <ul class="space-y-3 text-[#475569] mb-6">
              <li class="flex items-start gap-3"><span class="text-[#2fe676] mt-1">•</span><span>Engage with a diverse network of market participants.</span></li>
              <li class="flex items-start gap-3"><span class="text-[#2fe676] mt-1">•</span><span>Learn from educational content and community discussions.</span></li>
              <li class="flex items-start gap-3"><span class="text-[#2fe676] mt-1">•</span><span>Stay updated on market news and industry developments.</span></li>
              <li class="flex items-start gap-3"><span class="text-[#2fe676] mt-1">•</span><span>Exchange ideas in a respectful and collaborative environment.</span></li>
              <li class="flex items-start gap-3"><span class="text-[#2fe676] mt-1">•</span><span>Grow your knowledge through continuous learning.</span></li>
            </ul>

            <h3 class="text-[#0b153b] font-semibold text-[1.15rem] mb-3">Our Mission</h3>
            <p class="text-[#475569] leading-7 mb-6">Our mission is to foster an inclusive community where members can learn, collaborate, and share ideas about financial markets in a professional, transparent, and educational environment.</p>

            <div class="flex gap-3">
              <a href="#" class="inline-flex items-center gap-2 px-5 py-3 rounded-full bg-gradient-to-r from-[#0b153b] to-[#24335c] text-white font-semibold shadow-md hover:opacity-95 transition transform hover:-translate-y-0.5">Join the Conversation</a>
              <a href="#" class="inline-flex items-center gap-2 px-5 py-3 rounded-full border border-[#0b153b] text-[#0b153b] font-semibold hover:bg-[#0b153b] hover:text-white transition">Learn More</a>
            </div>
          </div>
        </div>

        <div class="mt-8 text-center text-[#475569]">Learn. Connect. Grow.</div>
      </div>
    </section>

    <!-- VIX + SPX SECTION -->
    <section class="bg-white py-14 sm:py-16 lg:py-20">
      <div class="max-w-[1200px] mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-center text-[#1b2f6f] font-bold text-[2rem] sm:text-[2.5rem] lg:text-[3.1rem] leading-tight max-w-[900px] mx-auto">
          The Home of the VIX<sup class="text-[0.45em] align-super">®</sup> Index &amp; SPX
        </h2>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-12 mt-10 lg:mt-12">
          
          <div>
            <h3 class="text-[#1b2f6f] font-bold text-[1.35rem] sm:text-[1.45rem] mb-4">The VIX Index</h3>
            <p class="text-[#24335c] leading-7 text-[1rem] sm:text-[1.05rem] max-w-[95%]">
              Welcome to your go-to place for information about the VIX complex, including VIX options and futures. Learn to measure, model and trade market moves with the world’s widest array of volatility products and resources.
            </p>

            <div class="mt-8 bg-[#e9e9e9] rounded-lg p-4 sm:p-5">
              <div class="bg-white rounded-md shadow-sm p-4 sm:p-5 lg:p-6">
                <div class="flex items-start justify-between gap-4 mb-2">
                  <div class="text-[#1b2f6f] font-bold text-[1.8rem] sm:text-[2rem] leading-none">VIX</div>
                  <div class="text-right">
                    <div class="text-[#1b2f6f] font-bold text-[1.5rem] sm:text-[1.8rem] leading-none">16.89</div>
                    <div class="text-[#9a2f1f] font-medium text-[0.95rem] sm:text-[1.05rem] mt-1">↘ -9.44%</div>
                  </div>
                </div>

                <div class="mt-3">
                  <img src="assets/vix-chart.png" alt="VIX intraday chart" class="w-full h-auto block" />
                </div>

                <div class="mt-5 flex justify-center">
                  <div class="text-[#1b2f6f] text-[0.9rem] sm:text-[0.95rem] font-medium flex items-center gap-2">
                    <span class="inline-block w-2.5 h-2.5 bg-[#1b2f6f] rounded-sm"></span>
                    Cboe Volatility Index
                  </div>
                </div>

                <div class="mt-6 flex flex-wrap gap-2 bg-[#ececec] rounded-md p-1.5 w-fit max-w-full">
                  <button class="px-4 py-1.5 rounded-md bg-[#1d7f39] text-white font-semibold text-[0.95rem]">Intraday</button>
                  <button class="px-4 py-1.5 rounded-md text-[#1b2f6f] font-semibold text-[0.95rem]">1M</button>
                  <button class="px-4 py-1.5 rounded-md text-[#1b2f6f] font-semibold text-[0.95rem]">3M</button>
                  <button class="px-4 py-1.5 rounded-md text-[#1b2f6f] font-semibold text-[0.95rem]">6M</button>
                  <button class="px-4 py-1.5 rounded-md text-[#1b2f6f] font-semibold text-[0.95rem]">1Y</button>
                </div>

                <div class="mt-6 space-y-4">
                  <a href="#" class="flex items-center gap-3 text-[#1b2f6f] font-semibold hover:text-[#19b35f] transition">
                    <span class="text-[#19b35f] text-[1.2rem]">❯</span>
                    Explore VIX Options
                  </a>
                  <a href="#" class="flex items-center gap-3 text-[#1b2f6f] font-semibold hover:text-[#19b35f] transition">
                    <span class="text-[#19b35f] text-[1.2rem]">❯</span>
                    Discover VIX Futures
                  </a>
                  <a href="#" class="flex items-center gap-3 text-[#1b2f6f] font-semibold hover:text-[#19b35f] transition">
                    <span class="text-[#19b35f] text-[1.2rem]">❯</span>
                    Learn more about Mini VIX™ Futures
                  </a>
                </div>
              </div>
            </div>
          </div>

          <div>
            <h3 class="text-[#1b2f6f] font-bold text-[1.35rem] sm:text-[1.45rem] mb-4">S&amp;P 500 Index Options</h3>
            <p class="text-[#24335c] leading-7 text-[1rem] sm:text-[1.05rem] max-w-[95%]">
              Cboe's SPX® options products provide investors with the tools to gain efficient exposure to the U.S. equity market and execute risk management, hedging, asset allocation, and income generation strategies.
            </p>

            <div class="mt-8 bg-[#e9e9e9] rounded-lg p-4 sm:p-5">
              <div class="bg-white rounded-md shadow-sm p-4 sm:p-5 lg:p-6">
                <div class="flex items-start justify-between gap-4 mb-2">
                  <div class="text-[#1b2f6f] font-bold text-[1.8rem] sm:text-[2rem] leading-none">SPX</div>
                  <div class="text-right">
                    <div class="text-[#1b2f6f] font-bold text-[1.5rem] sm:text-[1.8rem] leading-none">7510.72</div>
                    <div class="text-[#14532d] font-medium text-[0.95rem] sm:text-[1.05rem] mt-1">↗ 0.91%</div>
                  </div>
                </div>

                <div class="mt-3">
                  <img src="assets/spx-chart.png" alt="SPX intraday chart" class="w-full h-auto block" />
                </div>

                <div class="mt-5 flex justify-center">
                  <div class="text-[#1b2f6f] text-[0.9rem] sm:text-[0.95rem] font-medium flex items-center gap-2">
                    <span class="inline-block w-2.5 h-2.5 bg-[#1b2f6f] rounded-sm"></span>
                    S&amp;P 500
                  </div>
                </div>

                <div class="mt-6 flex flex-wrap gap-2 bg-[#ececec] rounded-md p-1.5 w-fit max-w-full">
                  <button class="px-4 py-1.5 rounded-md bg-[#1d7f39] text-white font-semibold text-[0.95rem]">Intraday</button>
                  <button class="px-4 py-1.5 rounded-md text-[#1b2f6f] font-semibold text-[0.95rem]">1M</button>
                  <button class="px-4 py-1.5 rounded-md text-[#1b2f6f] font-semibold text-[0.95rem]">3M</button>
                  <button class="px-4 py-1.5 rounded-md text-[#1b2f6f] font-semibold text-[0.95rem]">6M</button>
                  <button class="px-4 py-1.5 rounded-md text-[#1b2f6f] font-semibold text-[0.95rem]">1Y</button>
                </div>

                <div class="mt-6 space-y-4">
                  <a href="#" class="flex items-center gap-3 text-[#1b2f6f] font-semibold hover:text-[#19b35f] transition">
                    <span class="text-[#19b35f] text-[1.2rem]">❯</span>
                    Learn more about SPX Options
                  </a>
                  <a href="#" class="flex items-center gap-3 text-[#1b2f6f] font-semibold hover:text-[#19b35f] transition">
                    <span class="text-[#19b35f] text-[1.2rem]">❯</span>
                    Get to know XSP Options
                  </a>
                  <a href="#" class="flex items-center gap-3 text-[#1b2f6f] font-semibold hover:text-[#19b35f] transition">
                    <span class="text-[#19b35f] text-[1.2rem]">❯</span>
                    Discover NANOS Options
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- MARKET SNAPSHOT -->
    <section class="bg-white py-14 sm:py-16 lg:py-20">
      <div class="max-w-[1200px] mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-center text-[#1b2f6f] font-bold text-[2rem] sm:text-[2.5rem] lg:text-[2.8rem] leading-tight mb-10">
          Market Snapshot
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6 lg:gap-8 justify-items-center">
          
          <div class="w-full max-w-[320px] min-h-[220px] bg-[#1c2550] rounded-[2px] text-white p-5 shadow-sm">
            <div class="flex items-center justify-between mb-8">
              <h3 class="font-bold text-[1.05rem] leading-none">U.S. Equities</h3>
              <span class="w-5 h-5 rounded-full bg-[#2fe676] flex items-center justify-center text-[#10203f] text-[0.85rem] font-bold">›</span>
            </div>
            <div class="text-[0.6rem] font-semibold uppercase tracking-wide text-white mb-4">Most Active Symbols on BZX</div>
            <div class="space-y-4 text-[0.9rem]">
              <div class="flex justify-between gap-3">
                <span class="text-[#59f0d2] font-medium">DRAM</span>
                <span class="text-white font-semibold">$58.63</span>
                <span class="text-[#9aa7d6]">(10.2M)</span>
              </div>
              <div class="flex justify-between gap-3">
                <span class="text-[#59f0d2] font-medium">NOK</span>
                <span class="text-white font-semibold">$10.65</span>
                <span class="text-[#9aa7d6]">(8.0M)</span>
              </div>
              <div class="flex justify-between gap-3">
                <span class="text-[#59f0d2] font-medium">SNXX</span>
                <span class="text-white font-semibold">$17.87</span>
                <span class="text-[#9aa7d6]">(7.5M)</span>
              </div>
            </div>
          </div>

          <div class="w-full max-w-[320px] min-h-[220px] bg-[#1c2550] rounded-[2px] text-white p-5 shadow-sm">
            <div class="flex items-center justify-between mb-8">
              <h3 class="font-bold text-[1.05rem] leading-none">U.S. Options</h3>
              <span class="w-5 h-5 rounded-full bg-[#2fe676] flex items-center justify-center text-[#10203f] text-[0.85rem] font-bold">›</span>
            </div>
            <div class="border border-[#48d7ff] h-[125px] flex items-center justify-center">
              <div class="text-center">
                <div class="text-[#5cf2ce] font-bold text-[1.5rem] leading-none">14.74M</div>
                <div class="text-[0.62rem] uppercase font-semibold mt-1">Average Daily Contracts</div>
              </div>
            </div>
          </div>

          <div class="w-full max-w-[320px] min-h-[220px] bg-[#1c2550] rounded-[2px] text-white p-5 shadow-sm">
            <div class="flex items-center justify-between mb-8">
              <h3 class="font-bold text-[1.05rem] leading-none">U.S. Futures</h3>
              <span class="w-5 h-5 rounded-full bg-[#2fe676] flex items-center justify-center text-[#10203f] text-[0.85rem] font-bold">›</span>
            </div>
            <div class="text-[0.6rem] font-semibold uppercase tracking-wide text-white mb-4">VIX Futures Settlement and Expiration</div>
            <div class="space-y-4 text-[0.82rem]">
              <div class="grid grid-cols-[1fr_auto_auto] gap-3 items-center">
                <span class="text-[#59f0d2] font-medium">VX/N6</span>
                <span class="text-white font-semibold">17.0397</span>
                <span class="text-[#9aa7d6]">07/22/2026</span>
              </div>
              <div class="grid grid-cols-[1fr_auto_auto] gap-3 items-center">
                <span class="text-[#59f0d2] font-medium">VX30/N6</span>
                <span class="text-white font-semibold">17.0397</span>
                <span class="text-[#9aa7d6]">07/29/2026</span>
              </div>
              <div class="grid grid-cols-[1fr_auto_auto] gap-3 items-center">
                <span class="text-[#59f0d2] font-medium">VX31/Q6</span>
                <span class="text-white font-semibold">17.0397</span>
                <span class="text-[#9aa7d6]">08/05/2026</span>
              </div>
            </div>
          </div>

          <div class="w-full max-w-[320px] min-h-[220px] bg-[#1c2550] rounded-[2px] text-white p-5 shadow-sm">
            <div class="flex items-center justify-between mb-6">
              <h3 class="font-bold text-[1.05rem] leading-none">European Equities</h3>
              <span class="w-5 h-5 rounded-full bg-[#2fe676] flex items-center justify-center text-[#10203f] text-[0.85rem] font-bold">›</span>
            </div>
            <div class="text-[0.6rem] font-semibold uppercase tracking-wide text-white mb-5">Cboe Europe Market Share</div>
            <div class="flex items-end justify-center gap-6 h-[120px] pb-4">
              <div class="flex flex-col items-center">
                <div class="w-5 h-[68px] bg-[#8f7bff] rounded-sm"></div>
                <span class="text-[0.55rem] rotate-[-45deg] origin-top-left mt-3 text-white">Chi-X Europe</span>
              </div>
              <div class="flex flex-col items-center">
                <div class="w-5 h-[54px] bg-[#8f7bff] rounded-sm"></div>
                <span class="text-[0.55rem] rotate-[-45deg] origin-top-left mt-3 text-white">Euronext</span>
              </div>
              <div class="flex flex-col items-center">
                <div class="w-5 h-[22px] bg-[#8f7bff] rounded-sm"></div>
                <span class="text-[0.55rem] rotate-[-45deg] origin-top-left mt-3 text-white">Xetra</span>
              </div>
            </div>
          </div>

          <div class="w-full max-w-[320px] min-h-[220px] bg-[#1c2550] rounded-[2px] text-white p-5 shadow-sm">
            <div class="flex items-center justify-between mb-8">
              <h3 class="font-bold text-[1.05rem] leading-none">Canadian Equities</h3>
              <span class="w-5 h-5 rounded-full bg-[#2fe676] flex items-center justify-center text-[#10203f] text-[0.85rem] font-bold">›</span>
            </div>
            <div class="border border-[#48d7ff] h-[125px] flex items-center justify-center">
              <div class="text-center">
                <div class="text-[#5cf2ce] font-bold text-[1.5rem] leading-none">220M+</div>
                <div class="text-[0.62rem] uppercase font-semibold mt-1">Average Daily Volume</div>
              </div>
            </div>
          </div>

          <div class="w-full max-w-[320px] min-h-[220px] bg-[#1c2550] rounded-[2px] text-white p-5 shadow-sm">
            <div class="flex items-center justify-between mb-8">
              <h3 class="font-bold text-[1.05rem] leading-none">Australian Equities</h3>
              <span class="w-5 h-5 rounded-full bg-[#2fe676] flex items-center justify-center text-[#10203f] text-[0.85rem] font-bold">›</span>
            </div>
            <div class="border border-[#48d7ff] h-[125px] flex items-center justify-center">
              <div class="text-center">
                <div class="text-[#5cf2ce] font-bold text-[1.4rem] leading-none">$5.98B+</div>
                <div class="text-[0.62rem] uppercase font-semibold mt-1">Largest Daily Value Traded</div>
              </div>
            </div>
          </div>

          <div class="w-full max-w-[320px] min-h-[220px] bg-[#1c2550] rounded-[2px] text-white p-5 shadow-sm">
            <div class="flex items-center justify-between mb-8">
              <h3 class="font-bold text-[1.05rem] leading-none">Foreign Exchange</h3>
              <span class="w-5 h-5 rounded-full bg-[#2fe676] flex items-center justify-center text-[#10203f] text-[0.85rem] font-bold">›</span>
            </div>
            <div class="border border-[#48d7ff] h-[125px] flex items-center justify-center">
              <div class="text-center">
                <div class="text-[#5cf2ce] font-bold text-[1.4rem] leading-none">$57.45B</div>
                <div class="text-[0.62rem] uppercase font-semibold mt-1">Spot Average Daily Volume</div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </section>

    <!-- CTA BANNER -->
    <section class="bg-white">
      <div class="max-w-[1200px] mx-auto px-4 sm:px-6 lg:px-8">
        <div class="relative -mt-8 sm:-mt-10 mb-6 flex justify-center">
          <div class="w-full max-w-[820px] bg-[#24e6f1] text-[#0b153b] rounded-3xl sm:rounded-full px-5 sm:px-8 py-5 shadow-lg flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <p class="text-[0.95rem] sm:text-[1rem] font-semibold max-w-[560px]">
              Get real-time and historical data from our markets around the globe.
            </p>
            <a href="#" class="inline-flex px-5 py-2.5 rounded-full bg-[#0b153b] text-white text-[0.9rem] font-semibold hover:bg-[#111b4f] transition whitespace-nowrap">
              Request a Demo
            </a>
          </div>
        </div>
      </div>
    </section>

    <!-- COMMUNITY (removed; relocated after hero) -->

    <!-- INSIGHTS -->
    <section class="bg-[#0b153b] text-white pt-10 pb-16 lg:pt-16 lg:pb-24">
      <div class="max-w-[1200px] mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-14 mb-10 lg:mb-14 items-start">
          <div>
            <p class="text-[1.8rem] sm:text-[2.1rem] lg:text-[2.4rem] font-bold leading-tight mb-3">
              Harness the Knowledge.<br />
              Wield the Power.
            </p>
            <a href="#" class="text-[0.85rem] font-semibold tracking-[0.16em] uppercase text-[#46e7ff] hover:text-[#7af4ff]">
              Discover our Insights
            </a>
          </div>

          <div class="text-[0.95rem] leading-7 text-[#c0c7ff] max-w-[620px]">
            At Cboe, we're dedicated to helping you navigate any market condition and efficiently manage risk. Our world-class innovators, educators and market specialists provide comprehensive resources and insightful guidance on how to use our tools, analyze markets and take your investment outcomes to new heights.
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 lg:gap-8 mb-16">
          <div class="border border-[#38bdf8] rounded-[4px] p-5 lg:p-6 bg-[#0b153b]">
            <p class="text-[0.8rem] font-semibold text-[#59f0d2] mb-1">Insights and Analysis</p>
            <h3 class="text-[1.1rem] font-bold mb-3">Derivatives Market<br />Intelligence</h3>
            <p class="text-[0.9rem] leading-6 text-[#c0c7ff] mb-6">
              Unlock actionable derivatives insights and analysis from Mandy Xu and the market intelligence team.
            </p>
            <a href="#" class="inline-flex items-center gap-2 text-[0.9rem] font-semibold text-[#46e7ff] hover:text-[#7af4ff]">
              <span>Discover Market Intelligence</span>
              <span class="text-[1.1rem]">❯</span>
            </a>
          </div>

          <div class="border border-[#38bdf8] rounded-[4px] p-5 lg:p-6 bg-[#0b153b]">
            <p class="text-[0.8rem] font-semibold text-[#59f0d2] mb-1">Education</p>
            <h3 class="text-[1.1rem] font-bold mb-3">The Options Institute</h3>
            <p class="text-[0.9rem] leading-6 text-[#c0c7ff] mb-6">
              Explore courses, events, research and more from the education arm of Cboe.
            </p>
            <a href="#" class="inline-flex items-center gap-2 text-[0.9rem] font-semibold text-[#46e7ff] hover:text-[#7af4ff]">
              <span>Learn More</span>
              <span class="text-[1.1rem]">❯</span>
            </a>
          </div>

          <div class="border border-[#38bdf8] rounded-[4px] p-5 lg:p-6 bg-[#0b153b]">
            <p class="text-[0.8rem] font-semibold text-[#59f0d2] mb-1">News and Resources</p>
            <h3 class="text-[1.1rem] font-bold mb-3">Insights</h3>
            <p class="text-[0.9rem] leading-6 text-[#c0c7ff] mb-6">
              Stay informed with timely updates and resources for every trader, across asset classes.
            </p>
            <a href="#" class="inline-flex items-center gap-2 text-[0.9rem] font-semibold text-[#46e7ff] hover:text-[#7af4ff]">
              <span>Stay Updated</span>
              <span class="text-[1.1rem]">❯</span>
            </a>
          </div>
        </div>
        <!-- COMMUNITY (moved) -->

        <!-- FOOTER -->
        <footer class="border-t border-[#243061] pt-10 lg:pt-12">
          <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)_minmax(0,3fr)] gap-10 lg:gap-16">
            <div>
              <div class="text-white font-bold text-[2rem] sm:text-[2.2rem] leading-none mb-3">Cboe</div>
              <p class="text-[0.8rem] text-[#8b93d1] mb-4">
                ©2026 Cboe Exchange, Inc.<br />
                All rights reserved.
              </p>

              <div class="flex items-center gap-3 text-[#8b93d1] text-[0.9rem]">
                <a href="#" aria-label="LinkedIn" class="hover:text-[#46e7ff]">in</a>
                <a href="#" aria-label="Twitter" class="hover:text-[#46e7ff]">X</a>
                <a href="#" aria-label="Facebook" class="hover:text-[#46e7ff]">f</a>
                <a href="#" aria-label="YouTube" class="hover:text-[#46e7ff]">▶</a>
              </div>
            </div>

            <div>
              <h4 class="text-[0.9rem] font-semibold mb-3">About</h4>
              <ul class="space-y-1.5 text-[0.85rem] text-[#c0c7ff]">
                <li><a href="#" class="hover:text-[#46e7ff]">About Us</a></li>
                <li><a href="#" class="hover:text-[#46e7ff]">Careers</a></li>
                <li><a href="#" class="hover:text-[#46e7ff]">Cboe Empowers</a></li>
                <li><a href="#" class="hover:text-[#46e7ff]">Corporate Stewardship</a></li>
                <li><a href="#" class="hover:text-[#46e7ff]">Hours &amp; Holidays</a></li>
                <li><a href="#" class="hover:text-[#46e7ff]">Investor Relations</a></li>
                <li><a href="#" class="hover:text-[#46e7ff]">Press Releases</a></li>
                <li><a href="#" class="hover:text-[#46e7ff]">Public Policy</a></li>
              </ul>
            </div>

            <div>
              <h4 class="text-[0.9rem] font-semibold mb-3">Legal</h4>
              <ul class="space-y-1.5 text-[0.85rem] text-[#c0c7ff]">
                <li><a href="#" class="hover:text-[#46e7ff]">Accessibility</a></li>
                <li><a href="#" class="hover:text-[#46e7ff]">Biosimilar Information Privacy Policy</a></li>
                <li><a href="#" class="hover:text-[#46e7ff]">California Notice &amp; Collection</a></li>
                <li><a href="#" class="hover:text-[#46e7ff]">Copyright, Trademark &amp; Patents</a></li>
                <li><a href="#" class="hover:text-[#46e7ff]">Disclaimers</a></li>
                <li><a href="#" class="hover:text-[#46e7ff]">Email Management</a></li>
                <li><a href="#" class="hover:text-[#46e7ff]">Notice of Financial Incentive</a></li>
                <li><a href="#" class="hover:text-[#46e7ff]">Cboe’s Investor Protection</a></li>
                <li><a href="#" class="hover:text-[#46e7ff]">Privacy Statement</a></li>
                <li><a href="#" class="hover:text-[#46e7ff]">Terms &amp; Conditions</a></li>
                <li><a href="#" class="hover:text-[#46e7ff]">Your Privacy Choices</a></li>
                <li><a href="#" class="hover:text-[#46e7ff]">Do Not Sell or Share My Personal Information</a></li>
              </ul>
            </div>
          </div>
        </footer>
      </div>
    </section>

    <!-- COOKIE BUTTON -->
    <button class="fixed bottom-5 left-5 z-30 w-14 h-14 rounded-full bg-[#2b6b52] border-2 border-[#e7f5ea] shadow-lg flex items-center justify-center">
      <span class="text-white text-2xl">🍪</span>
    </button>

  </main>
    <script>
        const menuToggle = document.getElementById('menuToggle');
        const mobileMenu = document.getElementById('mobileMenu');

        menuToggle.addEventListener('click', () => {
            const isHidden = mobileMenu.classList.contains('hidden');
            mobileMenu.classList.toggle('hidden');
            menuToggle.setAttribute('aria-expanded', String(isHidden));
        });

        document.addEventListener('click', (e) => {
            const header = e.target.closest('header');
            if (!header) {
            mobileMenu.classList.add('hidden');
            menuToggle.setAttribute('aria-expanded', 'false');
            }
        });
    </script>
</body>
</html>