<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/helpers.php';

require_login();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/images/favicon.png">
  <title>About Us - CBOE Markets</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen pb-24 md:pb-6">

  <header class="sticky top-0 z-40 bg-white/95 backdrop-blur border-b border-slate-200 px-4 py-3 flex items-center justify-between md:hidden">
    <span class="text-xl font-extrabold text-emerald-500">About Us</span>
    <a href="profile.php" class="text-slate-600 hover:text-slate-900 transition text-sm">Back</a>
  </header>

  <?php $activePage = 'profile.php'; include '_nav.php'; ?>

  <main class="max-w-4xl mx-auto px-4 py-6 space-y-6">
    <div class="bg-white border border-slate-200 rounded-2xl p-6">
      <h1 class="text-2xl font-bold text-slate-900 mb-3">Welcome to 3Commas</h1>
      <p class="text-slate-700 leading-relaxed mb-3">We are a leading developer of crypto trading software, offering AI crypto trading bots that do not require users to know how to code. From Dollar-Cost Averaging (DCA) to GRID and the Signal Bot with TradingView integration, we make professional-level trading accessible to all.</p>
      <p class="text-slate-700 leading-relaxed mb-3">3Commas offers an all-in-one solution for managing crypto assets across major exchanges, offering reliable trade execution, portfolio analytics, and more. With spot, margin, and options markets available, our software provides a comprehensive trading experience.</p>
      <p class="text-slate-700 leading-relaxed">At 3Commas, our commitment revolves around providing customers with an undeniable advantage in the crypto markets, ensuring unmatched value in every trade.</p>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl p-6">
      <h2 class="text-xl font-bold text-slate-900 mb-2">Start trial</h2>
      <p class="text-slate-700 mb-4">Join 117,000+ Active 3Commas Members!</p>
      <p class="text-slate-700 leading-relaxed mb-4">Become part of a thriving community of traders on 3Commas. Share your strategies, get expert insights, and find the support you need to excel in your crypto journey.</p>
      <a href="index.php" class="inline-flex items-center bg-emerald-500 hover:bg-emerald-400 text-white font-bold py-2.5 px-5 rounded-xl transition">Join us!</a>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl p-6">
      <h2 class="text-xl font-bold text-slate-900 mb-4">Trusted worldwide since 2017</h2>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="rounded-xl border border-slate-200 p-4">
          <p class="text-xs uppercase tracking-wide text-slate-500">Traders registered</p>
          <p class="text-sm text-slate-700 mt-1">Growing globally</p>
        </div>
        <div class="rounded-xl border border-slate-200 p-4">
          <p class="text-xs uppercase tracking-wide text-slate-500">Exchange accounts connected</p>
          <p class="text-sm text-slate-700 mt-1">Across major exchanges</p>
        </div>
        <div class="rounded-xl border border-slate-200 p-4">
          <p class="text-xs uppercase tracking-wide text-slate-500">Volume traded</p>
          <p class="text-sm text-slate-700 mt-1">Driven by active strategies</p>
        </div>
      </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl p-6">
      <h2 class="text-xl font-bold text-slate-900 mb-3">Our history</h2>
      <p class="text-slate-700 leading-relaxed mb-3">Initially, the service was created exclusively for personal use, but it quickly gained popularity among friends. Soon after, 3Commas became available to the general public. The concept of developing trading bots emerged, aiming to eliminate the necessity for manual price monitoring around the clock. Subsequently, the automation of trading on crypto exchanges became the fundamental cornerstone of the 3Commas software.</p>
      <p class="text-slate-700 leading-relaxed mb-3">As the software attracted an increasing number of users, the focus naturally shifted towards enhancing the user experience and incorporating valuable community feedback. The benchmark of our development became synonymous with the satisfaction and success of our users. We excel at engaging with the community to gather feedback and offer features that align with their needs, and it is our users who often request the advanced features we develop and deliver. Many of our users are full-time traders, and they know exactly what they want. We are full-time trading software developers, so it is very much a mutually beneficial relationship with our trading community.</p>
      <p class="text-slate-700 leading-relaxed mb-3">Through dedication and innovation, 3Commas has achieved a leading position in the market, boasting the largest user base and forming multiple strategic partnerships. Notably, 3Commas was the first software to integrate with Binance Broker, securing exclusive partnership agreements that further solidified our standing in the industry.</p>
      <p class="text-slate-700 leading-relaxed mb-3">3Commas has cultivated a unique ecosystem where we share a symbiotic relationship with our user community. Their ideas and feedback inspire us to refine existing trading tools and develop new ones. This collaborative approach has earned us a reputation as a company that is not just responsive, but also consistently innovative.</p>
      <p class="text-slate-700 leading-relaxed">Eight years into our journey, we hold steadfast to the principles that have fueled our success. We remain committed to delivering an amazing user experience, whether someone is just entering the world of crypto trading or is a seasoned professional seeking cutting-edge tools.</p>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl p-6">
      <h2 class="text-xl font-bold text-slate-900 mb-3">Our founders</h2>
      <div class="space-y-3 text-slate-700">
        <div>
          <p class="font-semibold text-slate-900">Yuriy Sorokin</p>
          <p class="text-sm">Chief Executive Officer &amp; Co-Founder</p>
        </div>
        <div>
          <p class="font-semibold text-slate-900">Mikhail Goryunov</p>
          <p class="text-sm">Marketing Advisor &amp; Co-Founder</p>
        </div>
        <div>
          <p class="font-semibold text-slate-900">Andres Susi</p>
          <p class="text-sm">Chief Business Development Officer &amp; Co-Founder</p>
        </div>
      </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl p-6">
      <h2 class="text-xl font-bold text-slate-900 mb-3">Key Features of 3Commas</h2>
      <div class="space-y-4">
        <div>
          <h3 class="font-semibold text-slate-900">Cutting-Edge Trading Tools</h3>
          <p class="text-slate-700 text-sm mt-1">Our bots offer advanced features like Multi-Pair trading, Multiple Take Profit/Stop Loss, Trailing Mechanisms, Backtesting, Auto-Reinvesting, and Built-In Indicators for an enriched trading experience.</p>
        </div>
        <div>
          <h3 class="font-semibold text-slate-900">Security Measures</h3>
          <p class="text-slate-700 text-sm mt-1">Our team safeguards your assets with Sign Center's secure API key storage at both the infrastructure and access levels. Enhance your security through IP Whitelisting and streamline your account authorization with Fast Connect for added protection and user convenience.</p>
        </div>
        <div>
          <h3 class="font-semibold text-slate-900">Progressive User Growth</h3>
          <p class="text-slate-700 text-sm mt-1">Grow while you move from beginner trading to professional trading, with the support of comprehensive educational materials. Select from diverse plans designed for users at various levels, ensuring a smooth progression from beginners to advanced crypto traders. Check out our Help Center articles for detailed instructions on how to implement specific strategies.</p>
        </div>
      </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl p-6">
      <h2 class="text-xl font-bold text-slate-900 mb-2">Start Trading on 3Commas Today</h2>
      <p class="text-slate-700 mb-4">Get trial with full-access to all 3Commas trading tools.</p>
      <a href="index.php" class="inline-flex items-center bg-emerald-500 hover:bg-emerald-400 text-white font-bold py-2.5 px-5 rounded-xl transition">Start now</a>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl p-6">
      <h2 class="text-xl font-bold text-slate-900 mb-3">Why do traders choose 3Commas?</h2>
      <div class="space-y-4">
        <blockquote class="border-l-4 border-emerald-400 pl-4 text-slate-700 text-sm leading-relaxed">"I have tried out different bots since 2021 in Arbitrage and Trading with 300hrs of accumulated experience with the top brands like Bitsgap, CryptoHopper, and 3Commas. By far, 3Commas is the best option out there due to its user-friendly interface, transparency in transactions and reports, support 24/7, and vast knowledge available on the ins and outs of the software."<br><span class="text-slate-500">3Commas vs Competitors - Miguel Lora</span></blockquote>
        <blockquote class="border-l-4 border-emerald-400 pl-4 text-slate-700 text-sm leading-relaxed">"Love the software's convenience, allowing me to manage most of my crypto portfolios from one spot. Great software. Great convenience. Amazing uptime. Never had a problem in all my use."<br><span class="text-slate-500">Love the convenience of the platform - Robert Fatcheric</span></blockquote>
        <blockquote class="border-l-4 border-emerald-400 pl-4 text-slate-700 text-sm leading-relaxed">"I've been using 3commas for almost a year now. Mainly for DCA bots and Smart Trades. It has a fairly easy learning curve and you can find a lot of tutorials online. The development team keeps on adding stuff and making quality changes all the time. One of my favorite crypto trading tools. Safe trading and always DYOR."<br><span class="text-slate-500">Excellent product and user experience - Theodore</span></blockquote>
      </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl p-6">
      <h2 class="text-xl font-bold text-slate-900 mb-3">3Commas Media Coverage</h2>
      <ul class="space-y-2 text-sm text-slate-700">
        <li>3Commas Becomes First Binance Broker Partner - bitcoinist.com</li>
        <li>What is automated crypto trading and how does it work? - cointelegraph.com</li>
        <li>Jump Crypto Lead $37M Funding for 3Commas Automated Crypto Trading Platform - coindesk.com</li>
        <li>Largest Crypto Trading Bot and Investment Platform 3Commas Raises $37M - bloomberg.com</li>
        <li>3Commas scores $37m for automated crypto trading bot platform - fintech.global</li>
        <li>3Commas Review: Bitcoin &amp; Cryptocurrency Trading Bot Platform - blockonomi.com</li>
      </ul>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl p-6">
      <h2 class="text-xl font-bold text-slate-900 mb-3">Contacts</h2>
      <div class="space-y-3 text-sm text-slate-700">
        <div>
          <p class="font-semibold text-slate-900">Head office</p>
          <p>3C Trade Tech Ltd.</p>
          <p>Registration number: 2164568</p>
          <p>Address: Geneva Place, 2nd Floor, #333 Waterfront Drive, Road Town Tortola, British Virgin Islands</p>
        </div>
        <div>
          <p class="font-semibold text-slate-900">Business Partnerships</p>
          <p><a href="mailto:partners@cboemarkets.com" class="text-emerald-600 hover:text-emerald-500">partners@cboemarkets.com</a></p>
          <p>Learn more about partnership opportunities</p>
        </div>
      </div>
    </div>
  </main>
</body>
</html>
