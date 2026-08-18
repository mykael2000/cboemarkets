<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/helpers.php';

require_login();
$user = current_user();

$docs = [];
try {
    $docs = db()->query(
        'SELECT * FROM admin_documents WHERE public_visible = 1 ORDER BY created_at DESC'
    )->fetchAll();
} catch (Throwable) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/images/favicon.png">
  <title>Documents &amp; Reports – CBOE Markets</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen pb-24 md:pb-6">

  <header class="sticky top-0 z-40 bg-white/95 backdrop-blur border-b border-slate-200 px-4 py-3 flex items-center gap-3 md:hidden">
    <a href="profile.php" class="text-slate-500 hover:text-slate-700 transition">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    </a>
    <span class="text-lg font-extrabold text-emerald-400">Documents &amp; Reports</span>
  </header>

  <?php $activePage = 'profile.php'; include '_nav.php'; ?>

  <!-- Legal Information Modals -->
  <?php
  $legalDocs = [
    ['id' => 'rpp_from',       'title' => 'Recurrent Payment Policy as from December 29th 2024'],
    ['id' => 'rpp_until',      'title' => 'Recurrent Payment Policy until December 28th 2024'],
    ['id' => 'tor_from',       'title' => 'Terms of Referral as from December 29th 2024'],
    ['id' => 'tor_until',      'title' => 'Terms of Referral until December 28th 2024'],
    ['id' => 'api_from',       'title' => 'API Terms of Use for Developers as from December 29th 2024'],
    ['id' => 'api_until',      'title' => 'API Terms of Use for Developers until December 28th 2024'],
    ['id' => 'annual_promo',   'title' => 'Annual Promotional Cycle'],
    ['id' => 'trade_timing',   'title' => 'Automated Trade Timing Policy'],
    ['id' => 'sp_from',        'title' => 'Signal Providers Terms of Service as from December 29th 2024'],
    ['id' => 'sp_until',       'title' => 'Signal Providers Terms of Service until December 28th 2024'],
    ['id' => 'bb_from',        'title' => 'Bug Bounty as from December 29th 2024'],
    ['id' => 'bb_until',       'title' => 'Bug Bounty until December 28th 2024'],
    ['id' => 'cp_from',        'title' => 'Complaint Procedure as from December 29th 2024'],
    ['id' => 'cp_until',       'title' => 'Complaint Procedure until December 28th 2024'],
    ['id' => 'aff_from_2025',  'title' => 'Affiliate Program Terms and Conditions as from November 10th 2025'],
    ['id' => 'aff_until_2025', 'title' => 'Affiliate Program Terms and Conditions until November 9th 2025'],
    ['id' => 'aff_until_2024', 'title' => 'Affiliate Program Terms and Conditions until December 28th 2024'],
    ['id' => 'cpa_from',       'title' => 'Affiliate CPA Program Terms and Conditions as from December 29th 2024'],
    ['id' => 'cpa_until',      'title' => 'Affiliate CPA Program Terms and Conditions until December 28th 2024'],
    ['id' => 'gdpr',           'title' => 'GDPR Statement'],
    // ['id' => 'refund',         'title' => 'Refund Policy'],
  ];

  $legalContents = [
    'rpp_from'       => '<p class="text-xs text-slate-400 mb-3 italic">This Recurring Payment Policy is effective as of December 29, 2024. For definitions of capitalized terms, refer to the Terms of Use.</p>
<p><strong>1.</strong> For issues related to payments or refunds, consult the Cboemarkets Help Center. To request assistance, contact Cboemarkets Support through the "Contact Us"/"Support" form or email support[at]Cboemarketsbot.io.</p>
<p><strong>2. Authorization.</strong> By agreeing to this policy, you authorize 3C Trade Tech Ltd. (BVI company number: 2164568, address: Geneva Place, 2nd Floor, #333 Waterfront Drive, Road Town Tortola, British Virgin Islands) or Cboemarkets Technologies OÜ (registration code 14125515, address: Laeva 2, Tallinn, Estonia, 10412) on behalf of 3C Trade Tech Ltd. (each separately "Cboemarkets") to charge your specified default payment method, whether it\'s a Visa, Mastercard, or other payment card, or your PayPal account (including linked cards and bank accounts) every 30 days (also referred to as \'monthly\' or \'on a month-to-month basis\') or every year (also referred to as \'annual\' or \'on a year-to-year basis\'), as chosen when subscribing.</p>
<p><strong>3. Payment Processing:</strong></p>
<ul class="list-disc pl-5 space-y-1">
  <li>Payments made via our merchant record, Paddle.com, will be processed by them. Your relationship with Paddle is governed by the Paddle Checkout Buyer Terms and Conditions.</li>
  <li>Payments made through Apple AppStore or Google Play are processed by the respective platforms. Your relationship with Apple is governed by Apple Media Services Terms and Conditions. Your relationship with Google is governed by Google Play Terms of Service.</li>
</ul>
<p><strong>4. Acceptance.</strong> By agreeing to this policy, you confirm:</p>
<ul class="list-disc pl-5 space-y-1">
  <li>Prior to each subscription purchase, you will be informed about the payment amount, frequency, and the first payment date.</li>
  <li>You are not under a jurisdiction prohibiting the use of Visa, Mastercard, PayPal, Paddle, or other payment institutions.</li>
  <li>You provided correct and full payment information to Cboemarkets.</li>
  <li>You have authorized Cboemarkets to conduct recurring transactions on your behalf.</li>
  <li>You accept responsibility for all recurring payment obligations until the subscription\'s cancellation.</li>
</ul>
<p><strong>5. Payment Method Verification.</strong> You confirm that your specified payment method:</p>
<ul class="list-disc pl-5 space-y-1">
  <li>If the payment card is legally owned by you or you have rights to use it on behalf of an entity.</li>
  <li>If PayPal is an account registered by you or you have the right to use it on behalf of an entity.</li>
  <li>Has adequate credit/funds.</li>
  <li>Is operational and not compromised.</li>
</ul>
<p><strong>6. Automatic Charges.</strong> Payments will be processed on the due date or within a few days thereafter. Initially, you\'ll be notified about upcoming transactions 4 days in advance. A second notification will be sent 2 days prior to the charge. Notifications will be issued by our merchant record, Paddle.com.</p>
<p><strong>7. Payment Failures.</strong> In case of payment failure due to reasons like insufficient funds, card expiration, or account suspension, we\'ll reattempt as follows:</p>
<ul class="list-disc pl-5 space-y-1">
  <li>For general payments and payments via our merchant record Paddle: We\'ll retry after three days.</li>
  <li>For AppStore: We\'ll retry after sixteen days.</li>
  <li>For Google Play: We\'ll retry after seven days.</li>
</ul>
<p>After three consecutive failed attempts within these time frames, recurring payments will be automatically canceled. Access to the Software\'s paid features might require a new subscription for activation.</p>
<p><strong>8. Cancellation.</strong> You can cancel your Software subscription anytime via the Software, Apple, or Google interface. No refunds will be issued for the current subscription period, and the final active day will be the day before the next payment date.</p>',
    'rpp_until'      => '<p class="text-xs text-slate-400 mb-3 italic">This Recurring Payment Policy is effective as of December 10, 2023. For definitions of capitalized terms, refer to the Terms of Use.</p>
<p><strong>1.</strong> For issues related to payments or refunds, consult the Cboemarkets Help Center. To request assistance, contact Cboemarkets Support through the "Contact Us"/"Support" form or email support[at]Cboemarketsbot.io.</p>
<p><strong>2. Authorization.</strong> By agreeing to this policy, you authorize Cboemarkets Technologies OÜ ("Cboemarkets") to charge your specified default payment method, whether it\'s a Visa, Mastercard, or other payment card, or your PayPal account (including linked cards and bank accounts) every 30 days (also referred to as \'monthly\' or \'on a month-to-month basis\') or every year (also referred to as \'annual\' or \'on a year-to-year basis\'), as chosen when subscribing.</p>
<p><strong>3. Payment Processing:</strong></p>
<ul class="list-disc pl-5 space-y-1">
  <li>Payments made via our merchant record, Paddle.com, will be processed by them. Your relationship with Paddle is governed by the Paddle Checkout Buyer Terms and Conditions.</li>
  <li>Payments made through Apple AppStore or Google Play are processed by the respective platforms. Your relationship with Apple is governed by Apple Media Services Terms and Conditions. Your relationship with Google is governed by Google Play Terms of Service.</li>
</ul>
<p><strong>4. Acceptance.</strong> By agreeing to this policy, you confirm:</p>
<ul class="list-disc pl-5 space-y-1">
  <li>Prior to each subscription purchase, you will be informed about the payment amount, frequency, and the first payment date.</li>
  <li>You are not under a jurisdiction prohibiting the use of Visa, Mastercard, PayPal, Paddle, or other payment institutions.</li>
  <li>You provided correct and full payment information to Cboemarkets.</li>
  <li>You have authorized Cboemarkets to conduct recurring transactions on your behalf.</li>
  <li>You accept responsibility for all recurring payment obligations until the subscription\'s cancellation.</li>
</ul>
<p><strong>5. Payment Method Verification.</strong> You confirm that your specified payment method:</p>
<ul class="list-disc pl-5 space-y-1">
  <li>If the payment card is legally owned by you or you have rights to use it on behalf of an entity.</li>
  <li>If PayPal is an account registered by you or you have the right to use it on behalf of an entity.</li>
  <li>Has adequate credit/funds.</li>
  <li>Is operational and not compromised.</li>
</ul>
<p><strong>6. Automatic Charges.</strong> Payments will be processed on the due date or within a few days thereafter. Initially, you\'ll be notified about upcoming transactions 4 days in advance. A second notification will be sent 2 days prior to the charge. Notifications will be issued by our merchant record, Paddle.com.</p>
<p><strong>7. Payment Failures.</strong> In case of payment failure due to reasons like insufficient funds, card expiration, or account suspension, we\'ll reattempt as follows:</p>
<ul class="list-disc pl-5 space-y-1">
  <li>For general payments and payments via our merchant record Paddle: We\'ll retry after three days.</li>
  <li>For AppStore: We\'ll retry after sixteen days.</li>
  <li>For Google Play: We\'ll retry after seven days.</li>
</ul>
<p>After three consecutive failed attempts within these time frames, recurring payments will be automatically canceled. Access to the Software\'s paid features might require a new subscription for activation.</p>
<p><strong>8. Cancellation.</strong> You can cancel your Software subscription anytime via the Software, Apple, or Google interface. No refunds will be issued for the current subscription period, and the final active day will be the day before the next payment date.</p>',
    'tor_from'       => '<p class="text-xs text-slate-400 mb-3 italic">These Terms of Referral are effective as of 29 December 2024.</p>
<p>Cboemarkets team manages online software as a service including website located at https://Cboemarketsbot.io, Cboemarkets mobile application(s), application program interface(s), all together or each separately referred to as the "Software", and provides online services and technical support.</p>
<p>Cboemarkets team is represented by 3C Trade Tech Ltd., a company incorporated under the laws of the British Virgin Islands with BVI company number 2164568 and registered address at Geneva Place, 2nd Floor, #333 Waterfront Drive, Road Town Tortola, British Virgin Islands ("Cboemarkets", "we", "us" or "our").</p>
<p>These Terms of Referral ("Referral Terms") govern your ("you" or the "Client") actions in promoting the proliferation of the Software. In matters not regulated herein, the Client Terms of Use shall apply.</p>
<p class="font-semibold">BY CREATING AND/OR SHARING THE REFERRAL LINK YOU ACCEPT AND ACKNOWLEDGE BEING BOUND BY THE REFERRAL TERMS.</p>
<p><strong>1. DEFINITIONS</strong></p>
<ul class="list-disc pl-5 space-y-1">
  <li><strong>1.1 "Referrer"</strong> – a Client Account holder not banned/blocked, who has accepted these terms and participates in the referral program. Legal entities require manual approval by Cboemarkets.</li>
  <li><strong>1.2 "Referral Link"</strong> – an automatically generated hyperlink to the Software registration scenario, personalized for the Referrer.</li>
  <li><strong>1.3 "Promo Code"</strong> – an automatically generated code personalized for the Referrer to attract new Qualified Clients.</li>
  <li><strong>1.4 "Referrer Account"</strong> – a separately displayed account available to the Referrer upon acceptance of these terms, accounted in USD equivalent.</li>
  <li><strong>1.5 "Qualified Client"</strong> – a person/entity who (a) registered using the Referral Link or Promo Code, (b) purchased and paid for a Subscription in acceptable cryptocurrency or fiat (fiat only if Referrer is a legal entity), and (c) is not the Referrer herself.</li>
</ul>
<p><strong>2. OBLIGATIONS OF THE REFERRER</strong></p>
<p><strong>2.1</strong> To become a Referrer you must: (a) be a registered Client with a completely filled profile; (b) complete AML/KYC verification if applicable; (c) if acting on behalf of a legal entity, obtain manual approval from Cboemarkets; (d) obtain the Referral Link from the "Invite Friends" section of the Software.</p>
<p><strong>2.2</strong> The Referrer may attract any person ensuring they are of legal age, not in a prohibited jurisdiction, and aware that use of the Software is at their own discretion and responsibility.</p>
<p><strong>2.3</strong> The Referrer shall NOT: enter into agreements on behalf of Cboemarkets; introduce themselves as an employee/partner of Cboemarkets; collude with other Clients for illegal benefit; make statements or warranties about the Software; harm Cboemarkets\'s reputation; misuse Cboemarkets intellectual property.</p>
<p><strong>2.4</strong> A new client is a Qualified Client only if they registered via the Referral Link and paid for a Subscription in acceptable cryptocurrency or fiat.</p>
<p><strong>3. PAYMENT TERMS</strong></p>
<p><strong>3.1</strong> For a natural person Referrer, fees are calculated as a percentage of net successful cryptocurrency payments:</p>
<ul class="list-disc pl-5 space-y-1">
  <li>Level 1 Qualified Client (attracted by Referrer): <strong>25%</strong></li>
  <li>Level 2 Qualified Client (attracted by Level 1): <strong>15%</strong></li>
  <li>Level 3 Qualified Client (attracted by Level 2): <strong>10%</strong></li>
</ul>
<p><strong>3.2</strong> For a legal entity Referrer, the fee percentage is agreed between the Referrer and Cboemarkets upon approval of the application.</p>
<p><strong>3.3</strong> Cboemarkets pays fees in USDC cryptocurrency. Cboemarkets may require the Referrer to submit an invoice prior to payment.</p>
<p><strong>3.4</strong> The Referrer\'s fee is credited to the Referrer Account each month proportionally to payments made by the Qualified Client, converted to USD equivalent at the Cboemarkets rate at the time of crediting.</p>
<p><strong>3.5</strong> Cboemarkets is not obliged to transfer fees for payments outside the scope of these terms or transfer funds in currencies other than acceptable cryptocurrency.</p>
<p><strong>3.6</strong> With funds on the Referrer Account, you may: (a) credit them to your Client Account (usable only for Cboemarkets Subscriptions, non-withdrawable); or (b) withdraw to an external wallet subject to Software limitations.</p>
<p><strong>3.7</strong> The Referrer is solely responsible for all taxes, charges and levies on fees received.</p>
<p><strong>4. SANCTIONS COMPLIANCE</strong></p>
<p><strong>4.1</strong> By using Cboemarkets services, you represent that you are not on any trade embargo or economic sanctions lists (including EU, UN, BVI, OFAC, U.S. Commerce Department, and UK OFSI lists), that your use does not violate international sanctions, and that you are not from a sanctioned country or region.</p>
<p><strong>4.2</strong> Cboemarkets reserves the right to restrict or refuse services in certain countries or regions at its sole discretion.</p>
<p><strong>4.3</strong> If you become subject to international sanctions, you must immediately stop using our services and notify us.</p>
<p><strong>4.4</strong> Cboemarkets may terminate, suspend, or restrict services if you become subject to sanctions, if providing services would violate sanctions, if you are assessed as related to a sanctioned territory or person, or per Section 4.2.</p>',
    'tor_until'      => '<p class="text-xs text-slate-400 mb-3 italic">These Terms of Referral are effective as of 13 October 2023.</p>
<p>Cboemarkets team manages online software as a service including website located at https://Cboemarketsbot.io, Cboemarkets mobile application(s), application program interface(s), all together or each separately referred to as the "Software", and provides online services and technical support.</p>
<p>Cboemarkets team is represented by Cboemarkets Technologies OÜ, a limited liability company incorporated under the laws of the Republic of Estonia with registration code 14125515 and registered address at Laeva tn 2, Kesklinna linnaosa, Tallinn, Harju maakond, 10111 ("Cboemarkets", "we", "us" or "our").</p>
<p>These Terms of Referral ("Referral Terms") govern your ("you" or the "Client") actions in promoting the proliferation of the Software. In matters not regulated herein, the Client Terms of Use shall apply.</p>
<p class="font-semibold">BY CREATING AND/OR SHARING THE REFERRAL LINK YOU ACCEPT AND ACKNOWLEDGE BEING BOUND BY THE REFERRAL TERMS.</p>
<p><strong>1. DEFINITIONS</strong></p>
<ul class="list-disc pl-5 space-y-1">
  <li><strong>1.1 "Referrer"</strong> – a Client Account holder not banned/blocked, who has accepted these terms and participates in the referral program. Legal entities require manual approval by Cboemarkets.</li>
  <li><strong>1.2 "Referral Link"</strong> – an automatically generated hyperlink to the Software registration scenario, personalized for the Referrer.</li>
  <li><strong>1.3 "Promo Code"</strong> – an automatically generated code personalized for the Referrer to attract new Qualified Clients.</li>
  <li><strong>1.4 "Referrer Account"</strong> – a separately displayed account available to the Referrer upon acceptance of these terms, accounted in USD equivalent.</li>
  <li><strong>1.5 "Qualified Client"</strong> – a person/entity who (a) registered using the Referral Link or Promo Code, (b) purchased and paid for a Subscription in acceptable cryptocurrency (USDT, ETH or other listed) or fiat (fiat only if Referrer is a legal entity), and (c) is not the Referrer herself.</li>
</ul>
<p><strong>2. OBLIGATIONS OF THE REFERRER</strong></p>
<p><strong>2.1</strong> To become a Referrer you must: (a) be a registered Client with a completely filled profile; (b) complete AML/KYC verification if applicable; (c) if acting on behalf of a legal entity, obtain manual approval from Cboemarkets; (d) obtain the Referral Link from the "Invite Friends" section of the Software.</p>
<p><strong>2.2</strong> The Referrer may attract any person ensuring they are of legal age, not in a prohibited jurisdiction, and aware that use of the Software is at their own discretion and responsibility.</p>
<p><strong>2.3</strong> The Referrer shall NOT: enter into agreements on behalf of Cboemarkets; introduce themselves as an employee/partner of Cboemarkets; collude with other Clients for illegal benefit; make statements or warranties about the Software; harm Cboemarkets\'s reputation; misuse Cboemarkets intellectual property.</p>
<p><strong>2.4</strong> A new client is a Qualified Client only if they registered via the Referral Link and paid for a Subscription in acceptable cryptocurrency (USDT, ETH or other listed) or fiat.</p>
<p><strong>3. PAYMENT TERMS</strong></p>
<p><strong>3.1</strong> For a natural person Referrer, fees are calculated as a percentage of net successful cryptocurrency payments:</p>
<ul class="list-disc pl-5 space-y-1">
  <li>Level 1 Qualified Client (attracted by Referrer): <strong>25%</strong></li>
  <li>Level 2 Qualified Client (attracted by Level 1): <strong>15%</strong></li>
  <li>Level 3 Qualified Client (attracted by Level 2): <strong>10%</strong></li>
</ul>
<p><strong>3.2</strong> For a legal entity Referrer, the fee percentage is agreed between the Referrer and Cboemarkets upon approval of the application.</p>
<p><strong>3.3</strong> Cboemarkets pays fees in USDT cryptocurrency. Cboemarkets may require the Referrer to submit an invoice prior to payment.</p>
<p><strong>3.4</strong> The Referrer\'s fee is credited to the Referrer Account each month proportionally to payments made by the Qualified Client, converted to USD equivalent at the Cboemarkets rate at the time of crediting.</p>
<p><strong>3.5</strong> Cboemarkets is not obliged to transfer fees for payments outside the scope of these terms or transfer funds in currencies other than acceptable cryptocurrency.</p>
<p><strong>3.6</strong> With funds on the Referrer Account, you may: (a) credit them to your Client Account (usable only for Cboemarkets Subscriptions, non-withdrawable); or (b) withdraw to an external wallet subject to Software limitations.</p>
<p><strong>3.7</strong> The Referrer is solely responsible for all taxes, charges and levies on fees received.</p>
<p><strong>4. SANCTIONS COMPLIANCE</strong></p>
<p><strong>4.1</strong> By using Cboemarkets services, you represent that you are not on any trade embargo or economic sanctions lists (including EU, UN, Estonia, OFAC, U.S. Commerce Department, and UK OFSI lists), that your use does not violate international sanctions, and that you are not from a sanctioned country or region.</p>
<p><strong>4.2</strong> Cboemarkets reserves the right to restrict or refuse services in certain countries or regions at its sole discretion.</p>
<p><strong>4.3</strong> If you become subject to international sanctions, you must immediately stop using our services and notify us.</p>
<p><strong>4.4</strong> Cboemarkets may terminate, suspend, or restrict services if you become subject to sanctions, if providing services would violate sanctions, if you are assessed as related to a sanctioned territory or person, or per Section 4.2.</p>',
    'api_from'       => '<p class="text-xs text-slate-400 mb-3 italic">These Terms of Use are effective as of 29 December 2024.</p>
<p><strong>1. DEFINITIONS</strong></p>
<ul class="list-disc pl-5 space-y-1">
  <li><strong>1.1</strong> The Cboemarkets API is made available by 3C Trade Tech Ltd., a company formed under the laws of the British Virgin Islands ("Cboemarkets", "we", "us" or "our") through https://Cboemarketsbot.io.</li>
  <li><strong>1.2 "Application"</strong> – the specialized program developed/integrated by you using our API.</li>
  <li><strong>1.3 "Terms of Service" / "API Terms"</strong> – these Cboemarkets API Terms of Service.</li>
  <li><strong>1.4 "End-User(s)"</strong> – a person who ultimately uses or is intended to use our API or other Cboemarkets products.</li>
</ul>
<p><strong>2. SCOPE</strong></p>
<p>These API Terms, together with related specification documents, our Terms of Use, and Privacy Notice form a binding "Contract" between you and us. BY ACCEPTING THESE TERMS, you confirm you have read and agreed to be bound by them, assume all obligations herein, are of sufficient legal age, are not in a prohibited jurisdiction, and use the API at your own discretion and responsibility.</p>
<p>If entering into these Terms on behalf of a legal entity, you represent you have authority to bind such entity.</p>
<p><strong>3. INTELLECTUAL PROPERTY RIGHTS AND REQUIREMENTS</strong></p>
<p><strong>3.1</strong> Cboemarkets grants you a limited, non-exclusive, non-assignable, non-transferable, revocable license to use our API to develop, test, and support your Application. Violation of these Terms may result in suspension or termination.</p>
<p><strong>3.2</strong> You may NOT: impair or damage Cboemarkets or its services; disrupt or gain unauthorized access to services/networks via the API; reverse engineer, decompile or disassemble the API; exploit or publicly disclose security vulnerabilities; violate any applicable law (including copyright, privacy laws, or laws protecting minors).</p>
<p><strong>3.3</strong> For all content/data you insert or make available via our API, you grant Cboemarkets a free, transferable, sublicensable, non-exclusive, irrevocable, worldwide right to use such content for any purpose including providing the API, research, analytics, product development, and commercial use. You guarantee content complies with these API Terms and does not violate third-party rights.</p>
<p><strong>3.4</strong> Further requirements are included in related specification documents. In case of conflict, specification documents shall prevail.</p>
<p><strong>4. USE OF API</strong></p>
<p><strong>4.1</strong> You must comply with all laws applicable to data accessed through our API, including GDPR and privacy regulations.</p>
<p><strong>4.2</strong> For data obtained through the API you must: obtain all necessary consents; keep locally stored data up to date; implement proper retention/deletion policies; and maintain a written privacy statement available to End Users.</p>
<p><strong>4.3</strong> It is forbidden to manipulate any information or functionalities connected to End User accounts without notifying and obtaining express consent from the affected End User beforehand.</p>
<p><strong>4.4</strong> You must notify End Users of any changes to the Application. End Users must give express consent prior to any manipulation.</p>
<p><strong>4.5</strong> It is forbidden to use the API or connected Applications to offer investment advice of any kind.</p>
<p><strong>5. SUSPENSION OF USE</strong></p>
<p>In case of breach we may immediately and without notice suspend your use of the API. We will monitor Applications for compliance. You must not interfere with such monitoring and must assist us in verifying compliance upon request.</p>
<p><strong>6. CHANGES TO TERMS</strong></p>
<p>We may update these API Terms. Material changes will be communicated via email 30 days in advance. If you do not agree, you may terminate use of the API within those 30 days.</p>
<p><strong>7. LIABILITY AND WARRANTIES</strong></p>
<p>THE API IS PROVIDED "AS IS" WITHOUT WARRANTIES OF ANY KIND. WE EXCLUDE ALL IMPLIED WARRANTIES TO THE EXTENT PERMITTED BY LAW. YOU AGREE TO INDEMNIFY AND HOLD HARMLESS Cboemarkets AND ITS AFFILIATES FROM ANY CLAIMS ARISING FROM YOUR BREACH OF THESE API TERMS OR YOUR APPLICATION(S).</p>
<p><strong>8. TERMINATION</strong></p>
<p>Either party may terminate with 30 days\' written notice. In case of fundamental breach, we may suspend or terminate immediately at our sole discretion.</p>
<p><strong>9. PRICES, PAYMENT TERMS AND REFUNDS</strong></p>
<ul class="list-disc pl-5 space-y-1">
  <li>Payouts are made once per calendar month based on your request in your Cboemarkets profile.</li>
  <li>No commission is charged on the first USD 1,000 generated from subscriptions to your Application.</li>
  <li>After that threshold: <strong>10% commission</strong> on monthly income.</li>
  <li>Six months after reaching the threshold: <strong>30% commission</strong> on monthly income.</li>
  <li>You are solely responsible for all applicable taxes.</li>
</ul>
<p><strong>10. PRIVACY</strong></p>
<p>To use the API you must provide certain Personal Data. Cboemarkets will collect and use it as described in our Privacy Notice at https://Cboemarketsbot.io/privacy-policy. Questions may be sent to support[at]Cboemarketsbot.io.</p>
<p><strong>11. GENERAL</strong></p>
<p>The API is intended for businesses, not consumers. You may not assign your rights hereunder. These API Terms are governed by British Virgin Islands law and disputes settled in relevant BVI courts.</p>
<p><strong>12. SANCTIONS COMPLIANCE</strong></p>
<p>By using Cboemarkets services, you represent you are not on any sanctions list (EU, UN, BVI, OFAC, U.S. Commerce, UK OFSI), your use does not violate international sanctions, and you are not from a sanctioned country. If you become subject to sanctions, you must immediately stop using our services and notify us. Cboemarkets may terminate or restrict services accordingly.</p>
<p class="text-xs text-slate-400 mt-3">3C Trade Tech Ltd. · Geneva Place, 2nd Floor, #333 Waterfront Drive, Road Town Tortola, British Virgin Islands · BVI company number: 2164568</p>',
    'api_until'      => '<p class="text-xs text-slate-400 mb-3 italic">These Terms of Use are effective as of December 10, 2023.</p>
<p><strong>1. DEFINITIONS</strong></p>
<ul class="list-disc pl-5 space-y-1">
  <li><strong>1.1</strong> The Cboemarkets API is made available by Cboemarkets Technologies OÜ, a company formed under the laws of the Republic of Estonia ("Cboemarkets", "we", "us" or "our") through https://Cboemarketsbot.io.</li>
  <li><strong>1.2 "Application"</strong> – the specialized program developed/integrated by you using our API.</li>
  <li><strong>1.3 "Terms of Service" / "API Terms"</strong> – these Cboemarkets API Terms of Service.</li>
  <li><strong>1.4 "End-User(s)"</strong> – a person who ultimately uses or is intended to use our API or other Cboemarkets products.</li>
</ul>
<p><strong>2. SCOPE</strong></p>
<p>These API Terms, together with related specification documents, our Terms of Use, and Privacy Notice form a binding "Contract" between you and us. BY ACCEPTING THESE TERMS, you confirm you have read and agreed to be bound by them, assume all obligations herein, are of sufficient legal age, are not in a prohibited jurisdiction, and use the API at your own discretion and responsibility.</p>
<p>If entering into these Terms on behalf of a legal entity, you represent you have authority to bind such entity.</p>
<p><strong>3. INTELLECTUAL PROPERTY RIGHTS AND REQUIREMENTS</strong></p>
<p><strong>3.1</strong> Cboemarkets grants you a limited, non-exclusive, non-assignable, non-transferable, revocable license to use our API to develop, test, and support your Application. Violation of these Terms may result in suspension or termination.</p>
<p><strong>3.2</strong> You may NOT: impair or damage Cboemarkets or its services; disrupt or gain unauthorized access to services/networks via the API; reverse engineer, decompile or disassemble the API; exploit or publicly disclose security vulnerabilities; violate any applicable law (including copyright, privacy laws, or laws protecting minors).</p>
<p><strong>3.3</strong> For all content/data you insert or make available via our API, you grant Cboemarkets a free, transferable, sublicensable, non-exclusive, irrevocable, worldwide right to use such content for any purpose including providing the API, research, analytics, product development, and commercial use. You guarantee content complies with these API Terms and does not violate third-party rights.</p>
<p><strong>3.4</strong> Further requirements are included in related specification documents. In case of conflict, specification documents shall prevail.</p>
<p><strong>4. USE OF API</strong></p>
<p><strong>4.1</strong> You must comply with all laws applicable to data accessed through our API, including GDPR and privacy regulations.</p>
<p><strong>4.2</strong> For data obtained through the API you must: obtain all necessary consents; keep locally stored data up to date; implement proper retention/deletion policies; and maintain a written privacy statement available to End Users.</p>
<p><strong>4.3</strong> It is forbidden to manipulate any information or functionalities connected to End User accounts without notifying and obtaining express consent from the affected End User beforehand.</p>
<p><strong>4.4</strong> You must notify End Users of any changes to the Application and obtain their express consent prior to any manipulation.</p>
<p><strong>4.5</strong> It is forbidden to use the API or connected Applications to offer investment advice of any kind.</p>
<p><strong>5. SUSPENSION OF USE</strong></p>
<p>In case of breach we may immediately and without notice suspend your use of the API. We will monitor Applications for compliance. You must not interfere with such monitoring and must assist us in verifying compliance upon request.</p>
<p><strong>6. CHANGES TO TERMS</strong></p>
<p>We may update these API Terms. Material changes will be communicated via email 30 days in advance. If you do not agree, you may terminate use of the API within those 30 days.</p>
<p><strong>7. LIABILITY AND WARRANTIES</strong></p>
<p>THE API IS PROVIDED "AS IS" WITHOUT WARRANTIES OF ANY KIND. WE EXCLUDE ALL IMPLIED WARRANTIES TO THE EXTENT PERMITTED BY LAW. YOU AGREE TO INDEMNIFY AND HOLD HARMLESS Cboemarkets AND ITS AFFILIATES FROM ANY CLAIMS ARISING FROM YOUR BREACH OF THESE API TERMS OR YOUR APPLICATION(S).</p>
<p><strong>8. TERMINATION</strong></p>
<p>Either party may terminate with 30 days\' written notice. In case of fundamental breach, we may suspend or terminate immediately at our sole discretion.</p>
<p><strong>9. PRICES, PAYMENT TERMS AND REFUNDS</strong></p>
<ul class="list-disc pl-5 space-y-1">
  <li>Payouts are made once per calendar month based on your request in your Cboemarkets profile.</li>
  <li>No commission is charged on the first USD 1,000 generated from subscriptions to your Application.</li>
  <li>After that threshold: <strong>10% commission</strong> on monthly income.</li>
  <li>Six months after reaching the threshold: <strong>30% commission</strong> on monthly income.</li>
  <li>You are solely responsible for all applicable taxes.</li>
</ul>
<p><strong>10. PRIVACY</strong></p>
<p>To use the API you must provide certain Personal Data. Cboemarkets will collect and use it as described in our Privacy Notice at https://Cboemarketsbot.io/privacy-policy. Questions may be sent to support[at]Cboemarketsbot.io.</p>
<p><strong>11. GENERAL</strong></p>
<p>The API is intended for businesses, not consumers. You may not assign your rights hereunder. These API Terms are governed by Estonian law and disputes settled in Harju County Court (Estonia).</p>
<p><strong>12. SANCTIONS COMPLIANCE</strong></p>
<p>By using Cboemarkets services, you represent you are not on any sanctions list (EU, UN, Estonia, OFAC, U.S. Commerce, UK OFSI), your use does not violate international sanctions, and you are not from a sanctioned country. If you become subject to sanctions, you must immediately stop using our services and notify us. Cboemarkets may terminate or restrict services accordingly.</p>
<p class="text-xs text-slate-400 mt-3">Cboemarkets Technologies OÜ · Laeva 2, Tallinn, Estonia, 10412 · Registration code: 14125515 · VAT: EE101951896</p>',
    'sp_from'        => '<p class="text-xs text-slate-400 mb-3 italic">These Terms of Use are effective as of December 29, 2024.</p>
<p><strong>1. DEFINITIONS</strong></p>
<ul class="list-disc pl-5 space-y-1">
  <li><strong>1.1 "Signal(s)"</strong> – notifications from you to subscribed bots providing information about which coin to buy or sell, acting as a trigger for a bot to trade a crypto pair using your private strategies and algorithms.</li>
  <li><strong>1.2 "Subscriber(s)"</strong> – a person or legal entity who subscribes to your bot template and receives Signals.</li>
  <li><strong>1.3 "Software"</strong> – the service made available by 3C Trade Tech Ltd. (BVI) through https://Cboemarketsbot.io, including mobile apps, APIs, and the Marketplace service.</li>
</ul>
<p><strong>2. SCOPE</strong></p>
<p>These Signallers Terms and the Privacy Notice constitute the entire agreement between you and Cboemarkets regarding the Software. By accepting, you confirm you have read and are bound by these terms, are of sufficient legal age, are not in a prohibited jurisdiction, and use the Software at your own responsibility. If acting on behalf of a legal entity, you represent you have authority to bind that entity.</p>
<p><strong>3. SANCTIONS COMPLIANCE</strong></p>
<p>By using the Software, you represent you are not on any sanctions list (EU, UN, BVI, OFAC, U.S. Commerce, UK OFSI), your use does not violate international sanctions, and you are not from a sanctioned country or region. If you become subject to sanctions, you must immediately stop using our services and notify us. Cboemarkets may terminate or restrict services accordingly.</p>
<p><strong>4. INTELLECTUAL PROPERTY AND LICENSE</strong></p>
<p>Cboemarkets grants you a limited, non-exclusive, non-assignable, non-transferable, revocable license to use the Software for personal, non-commercial use during the term of these terms. You may not rent, sell, reverse engineer, decompile, modify, or create derivative works based on the Software. For all Content you insert via the Software, you grant Cboemarkets a free, worldwide, irrevocable right to use it for any purpose including providing the Software, research, analytics, and commercial use.</p>
<p><strong>5. SIGN-UP</strong></p>
<p>To provide Signals you must be at least 18 years old, have a Cboemarkets account, and submit an application to become a Signal provider. You are solely responsible for ensuring that use of the Software and provision of Signals is permitted by applicable laws in your jurisdiction.</p>
<p><strong>6. USE OF SOFTWARE AND PROVISION OF SIGNALS</strong></p>
<p><strong>6.1</strong> By providing Signals, you conclude an independent agreement with each Subscriber and are responsible for any refund requests from your Subscribers. You accept responsibility for the quality of your Signals.</p>
<p><strong>6.2</strong> Ranking on the Cboemarkets marketplace is based on: signals that are new to the marketplace; signals offered at a discount or free; and signals offered exclusively on the Cboemarkets marketplace.</p>
<p><strong>6.3</strong> You must comply with all laws applicable to data accessed through the Software, including GDPR.</p>
<p><strong>6.4</strong> Signals may only be provided in accordance with these terms. Deviations may be deemed a material breach at Cboemarkets\' sole discretion.</p>
<p><strong>6.5</strong> Material breaches (resulting in immediate removal) include: sending scam/fake signals (e.g. signals after an artificial pump); copying signals from other groups; abusing the Cboemarkets Software; providing signals in violation of applicable laws.</p>
<p><strong>7. COMMISSIONS AND REFUNDS</strong></p>
<ul class="list-disc pl-5 space-y-1">
  <li>You receive <strong>70%</strong> of every subscription amount per month (net of refunds); Cboemarkets retains <strong>30% commission</strong>.</li>
  <li>Submit your payout request by the 27th of the month; Cboemarkets pays by the 7th of the following month.</li>
  <li>Payment is made in <strong>USDC</strong>. You are solely responsible for all applicable taxes.</li>
  <li>Cboemarkets may propose a different commission percentage prior to accepting your application, agreed in writing.</li>
</ul>
<p><strong>8. SUSPENSION</strong></p>
<p>Cboemarkets may suspend or interrupt your use of the Software and provision of Signals without liability if: your actions harm the Software or other users; complaints are received about your Signals; maintenance is required; your credentials are compromised; you breach these terms; you refuse to provide required clarifications; or for any other reason at Cboemarkets\' discretion. Cboemarkets will endeavour to notify you in advance where possible.</p>
<p><strong>9. TERM AND TERMINATION</strong></p>
<p>These terms are for an indefinite term. Either party may terminate with 30 days\' notice. Upon notice, Cboemarkets will stop accepting new subscribers to your Signals. Both parties must continue fulfilling obligations during the notice period. Post-termination, Cboemarkets retains Subscriber data per the Privacy Notice; you will have no access to Subscriber information.</p>
<p><strong>10. DISCLAIMER</strong></p>
<p>Cboemarkets PROVIDES THE SOFTWARE ONLY. Cboemarkets DOES NOT PROVIDE FINANCIAL, INVESTMENT, LEGAL, TAX OR ANY OTHER PROFESSIONAL ADVICE. NOTHING IN THE SOFTWARE CONSTITUTES INVESTMENT ADVICE OR RECOMMENDATIONS. YOU EXPRESSLY AGREE THAT USE OF THE SOFTWARE AND PROVISION OF SIGNALS IS AT YOUR SOLE RISK.</p>
<p><strong>11. LIABILITY AND WARRANTIES</strong></p>
<p>THE SOFTWARE IS PROVIDED "AS IS" WITHOUT WARRANTIES OF ANY KIND. TO THE MAXIMUM EXTENT PERMITTED BY LAW, Cboemarkets IS NOT LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, OR CONSEQUENTIAL DAMAGES ARISING FROM YOUR USE OF THE SOFTWARE. Cboemarkets maximum aggregate liability is limited to fees paid by you in the 12 months preceding any claim.</p>
<p><strong>12. INDEMNIFICATION</strong></p>
<p>You agree to indemnify and hold harmless Cboemarkets and its affiliates from any claims arising from your breach of these terms and/or your provision of Signals to Subscribers.</p>
<p><strong>13. CHANGES TO TERMS</strong></p>
<p>Cboemarkets may update these terms. Material changes will be communicated via email 30 days in advance. If you do not agree, you may terminate within those 30 days.</p>
<p><strong>14. PRIVACY</strong></p>
<p>To provide Signals you must provide certain Personal Data. Cboemarkets will collect and use it as described in the Privacy Notice at https://Cboemarketsbot.io/privacy-policy. Questions may be sent to support[at]Cboemarketsbot.io.</p>
<p><strong>15. GENERAL</strong></p>
<p>These terms are governed by British Virgin Islands law and disputes settled in relevant BVI courts. Cboemarkets may transfer its rights and obligations to a third party with advance notice; you may terminate immediately if you do not agree to the transfer.</p>
<p class="text-xs text-slate-400 mt-3">3C Trade Tech Ltd. · Geneva Place, 2nd Floor, #333 Waterfront Drive, Road Town Tortola, British Virgin Islands · BVI company number: 2164568</p>',
    'sp_until'       => '<p class="text-xs text-slate-400 mb-3 italic">These Terms of Use are effective as of December 10, 2023.</p>
<p><strong>1. DEFINITIONS</strong></p>
<ul class="list-disc pl-5 space-y-1">
  <li><strong>1.1 "Signal(s)"</strong> – notifications from you to subscribed bots providing information about which coin to buy or sell, acting as a trigger for a bot to trade a crypto pair using your private strategies and algorithms.</li>
  <li><strong>1.2 "Subscriber(s)"</strong> – a person or legal entity who subscribes to your bot template and receives Signals.</li>
  <li><strong>1.3 "Software"</strong> – the service made available by Cboemarkets Technologies OÜ (Estonia) through https://Cboemarketsbot.io, including mobile apps, APIs, and the Marketplace service.</li>
</ul>
<p><strong>2. SCOPE</strong></p>
<p>These Signallers Terms and the Privacy Notice constitute the entire agreement between you and Cboemarkets regarding the Software. By accepting, you confirm you have read and are bound by these terms, are of sufficient legal age, are not in a prohibited jurisdiction, and use the Software at your own responsibility. If acting on behalf of a legal entity, you represent you have authority to bind that entity.</p>
<p><strong>3. SANCTIONS COMPLIANCE</strong></p>
<p>By using the Software, you represent you are not on any sanctions list (EU, UN, Estonia, OFAC, U.S. Commerce, UK OFSI), your use does not violate international sanctions applicable in the Republic of Estonia, and you are not from a sanctioned country or region. If you become subject to sanctions, you must immediately stop using our services and notify us. Cboemarkets may terminate or restrict services accordingly.</p>
<p><strong>4. INTELLECTUAL PROPERTY AND LICENSE</strong></p>
<p>Cboemarkets grants you a limited, non-exclusive, non-assignable, non-transferable, revocable license to use the Software for personal, non-commercial use during the term of these terms. You may not rent, sell, reverse engineer, decompile, modify, or create derivative works based on the Software. For all Content you insert via the Software, you grant Cboemarkets a free, worldwide, irrevocable right to use it for any purpose including providing the Software, research, analytics, and commercial use.</p>
<p><strong>5. SIGN-UP</strong></p>
<p>To provide Signals you must be at least 18 years old, have a Cboemarkets account, and submit an application to become a Signal provider. You are solely responsible for ensuring that use of the Software and provision of Signals is permitted by applicable laws in your jurisdiction.</p>
<p><strong>6. USE OF SOFTWARE AND PROVISION OF SIGNALS</strong></p>
<p><strong>6.1</strong> By providing Signals, you conclude an independent agreement with each Subscriber and are responsible for any refund requests. You accept responsibility for the quality of your Signals.</p>
<p><strong>6.2</strong> Ranking on the Cboemarkets marketplace is based on: signals that are new to the marketplace; signals offered at a discount or free; and signals offered exclusively on the Cboemarkets marketplace.</p>
<p><strong>6.3</strong> You must comply with all laws applicable to data accessed through the Software, including GDPR.</p>
<p><strong>6.4</strong> Signals may only be provided in accordance with these terms. Deviations may be deemed a material breach at Cboemarkets\' sole discretion.</p>
<p><strong>6.5</strong> Material breaches (resulting in immediate removal) include: sending scam/fake signals (e.g. signals after an artificial pump); copying signals from other groups; abusing the Cboemarkets Software; providing signals in violation of applicable laws.</p>
<p><strong>7. COMMISSIONS AND REFUNDS</strong></p>
<ul class="list-disc pl-5 space-y-1">
  <li>You receive <strong>70%</strong> of every subscription amount per month (net of refunds); Cboemarkets retains <strong>30% commission</strong>.</li>
  <li>Submit your payout request by the 27th of the month; Cboemarkets pays by the 7th of the following month.</li>
  <li>Payment is made in <strong>USDT</strong>. You are solely responsible for all applicable taxes.</li>
  <li>Cboemarkets may propose a different commission percentage prior to accepting your application, agreed in writing.</li>
</ul>
<p><strong>8. SUSPENSION</strong></p>
<p>Cboemarkets may suspend or interrupt your use of the Software and provision of Signals without liability if: your actions harm the Software or other users; complaints are received about your Signals; maintenance is required; your credentials are compromised; you breach these terms; you refuse to provide required clarifications; or for any other reason at Cboemarkets\' discretion. Cboemarkets will endeavour to notify you in advance where possible.</p>
<p><strong>9. TERM AND TERMINATION</strong></p>
<p>These terms are for an indefinite term. Either party may terminate with 30 days\' notice. Upon notice, Cboemarkets will stop accepting new subscribers to your Signals. Both parties must continue fulfilling obligations during the notice period. Post-termination, Cboemarkets retains Subscriber data per the Privacy Notice; you will have no access to Subscriber information.</p>
<p><strong>10. DISCLAIMER</strong></p>
<p>Cboemarkets PROVIDES THE SOFTWARE ONLY AND DOES NOT PROVIDE FINANCIAL, INVESTMENT, LEGAL, OR TAX ADVICE. NOTHING IN THE SOFTWARE CONSTITUTES INVESTMENT ADVICE OR RECOMMENDATIONS. YOU EXPRESSLY AGREE THAT USE OF THE SOFTWARE AND PROVISION OF SIGNALS IS AT YOUR SOLE RISK.</p>
<p><strong>11. LIABILITY AND WARRANTIES</strong></p>
<p>THE SOFTWARE IS PROVIDED "AS IS" WITHOUT WARRANTIES OF ANY KIND. TO THE MAXIMUM EXTENT PERMITTED BY LAW, Cboemarkets IS NOT LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, OR CONSEQUENTIAL DAMAGES ARISING FROM YOUR USE OF THE SOFTWARE. Cboemarkets maximum aggregate liability is limited to fees paid by you in the 12 months preceding any claim.</p>
<p><strong>12. INDEMNIFICATION</strong></p>
<p>You agree to indemnify and hold harmless Cboemarkets and its affiliates from any claims arising from your breach of these terms and/or your provision of Signals to Subscribers.</p>
<p><strong>13. CHANGES TO TERMS</strong></p>
<p>Cboemarkets may update these terms. Material changes will be communicated via email 30 days in advance. If you do not agree, you may terminate within those 30 days.</p>
<p><strong>14. PRIVACY</strong></p>
<p>To provide Signals you must provide certain Personal Data. Cboemarkets will collect and use it as described in the Privacy Notice at https://Cboemarketsbot.io/privacy-policy. Questions may be sent to support[at]Cboemarketsbot.io.</p>
<p><strong>15. GENERAL</strong></p>
<p>These terms are governed by Estonian law and disputes settled in Harju County Court (Estonia). Cboemarkets may transfer its rights and obligations to a third party with advance notice; you may terminate immediately if you do not agree to the transfer.</p>
<p class="text-xs text-slate-400 mt-3">Cboemarkets Technologies OÜ · Laeva 2, Tallinn, Estonia, 10412 · Registration code: 14125515 · VAT: EE101951896</p>',
    'bb_from'        => '<p class="text-xs text-slate-400 mb-3 italic">This Bug Bounty is effective as of 29 December 2024.</p>
<p>Thank you for your interest in our Bug Bounty program and helping us make our platform stronger and safer. If you discover a bug, we welcome your cooperation in responsibly investigating and reporting it to us.</p>
<p><strong>1. INTRODUCTION</strong></p>
<p><strong>1.1 Purpose:</strong> To leverage the expertise of security researchers to identify and report vulnerabilities in our systems, applications, and network infrastructure.</p>
<p><strong>1.2 Scope — In Scope:</strong></p>
<ul class="list-disc pl-5 space-y-1">
  <li>Customer Cabinet: https://app.Cboemarketsbot.io/</li>
  <li>API Interactions: https://api.Cboemarketsbot.io/</li>
  <li>iOS Application: https://apps.apple.com/app/id6446649413</li>
  <li>Android Application: https://play.google.com/store/apps/details?id=io.threecommas.wallet</li>
</ul>
<p><strong>Excluded from scope:</strong> https://feedback.Cboemarketsbot.io/ (3rd party feedback portal), https://careers.Cboemarketsbot.io/ (careers portal), https://Cboemarkets.tech/ (experimental project).</p>
<p><strong>1.3 Key Definitions:</strong> Bug Bounty Program, Security Researcher, Confidentiality, NDA, Duplicate Report, Vulnerability, CVSS, Proof of Concept (PoC), Responsible Disclosure — standard industry definitions apply. CVSS v3.1 is used for scoring.</p>
<p><strong>2. PROGRAM OVERVIEW</strong></p>
<p>The program is open to all eligible security researchers. Participants must: obtain explicit authorisation before testing; respect the defined scope; report vulnerabilities promptly and responsibly; refrain from actions that could cause harm or disrupt services; and adhere to responsible disclosure principles.</p>
<p><strong>3. ELIGIBILITY</strong></p>
<ul class="list-disc pl-5 space-y-1">
  <li>Must be at least 18 years old.</li>
  <li>Must not be an employee, contractor, or affiliate of our Company.</li>
  <li>Must not be prohibited by law or regulations from participating (no sanctions, etc.).</li>
  <li>Must comply with all applicable laws and regulations.</li>
</ul>
<p><strong>4. VULNERABILITY SUBMISSION PROCESS</strong></p>
<p>Submit a detailed report including: description of the vulnerability; steps to reproduce; affected system/version; proof-of-concept (required for medium severity and above). Reports are submitted via https://Cboemarkets.zapier.app. We acknowledge receipt within 2 business days and provide an initial assessment within 10 business days.</p>
<p><strong>5. SEVERITY LEVELS AND REWARDS</strong></p>
<table class="w-full text-xs border-collapse mt-2 mb-2">
  <thead><tr class="bg-slate-100"><th class="border border-slate-200 px-2 py-1 text-left">Severity</th><th class="border border-slate-200 px-2 py-1 text-left">CVSS Score</th><th class="border border-slate-200 px-2 py-1 text-left">Min</th><th class="border border-slate-200 px-2 py-1 text-left">Max</th></tr></thead>
  <tbody>
    <tr><td class="border border-slate-200 px-2 py-1">Low</td><td class="border border-slate-200 px-2 py-1">0.1–3.9</td><td class="border border-slate-200 px-2 py-1">50 USDC</td><td class="border border-slate-200 px-2 py-1">200 USDC</td></tr>
    <tr><td class="border border-slate-200 px-2 py-1">Medium</td><td class="border border-slate-200 px-2 py-1">4.0–6.9</td><td class="border border-slate-200 px-2 py-1">200 USDC</td><td class="border border-slate-200 px-2 py-1">1,000 USDC</td></tr>
    <tr><td class="border border-slate-200 px-2 py-1">High</td><td class="border border-slate-200 px-2 py-1">7.0–8.9</td><td class="border border-slate-200 px-2 py-1">1,000 USDC</td><td class="border border-slate-200 px-2 py-1">2,500 USDC</td></tr>
    <tr><td class="border border-slate-200 px-2 py-1">Critical</td><td class="border border-slate-200 px-2 py-1">9.0–10.0</td><td class="border border-slate-200 px-2 py-1">2,500 USDC</td><td class="border border-slate-200 px-2 py-1">5,000 USDC</td></tr>
  </tbody>
</table>
<p>Actual payout within the range is at the sole discretion of the Program Administration. Provide your TRC20 wallet address for reward payment.</p>
<p><strong>6. LEGAL AND COMPLIANCE</strong></p>
<p>Participants must comply with all applicable laws. All tax obligations on rewards are the sole responsibility of the participant. Vulnerability data is handled per our Privacy Notice at https://Cboemarketsbot.io/privacy-notice. Participants must not access, exfiltrate, or tamper with personal data belonging to others.</p>
<p><strong>Sanctions:</strong> By participating, you represent you are not on any sanctions list (EU, UN, BVI, OFAC, U.S. Commerce, UK OFSI), your participation does not violate international sanctions, and you are not from a sanctioned country. If you become subject to sanctions, you must immediately stop and notify us.</p>
<p><strong>7. RESPONSIBLE DISCLOSURE</strong></p>
<p>Do not publicly disclose any vulnerability before receiving approval from program administrators. Allow reasonable time for remediation. Participants who report valid vulnerabilities receive acknowledgement, potential swag, and (with consent) public recognition.</p>
<p><strong>8. ISSUES EXCLUDED FROM SCOPE</strong> (reports will not be accepted):</p>
<ul class="list-disc pl-5 space-y-1 text-xs">
  <li>Clickjacking / UI redressing with minimal security impact</li>
  <li>CSRF on unauthenticated or non-sensitive forms</li>
  <li>Application-level crashes without exploit; MITM/SQLi mentions without PoC</li>
  <li>Known vulnerable libraries without working PoC</li>
  <li>Missing best practices in SSL/TLS, CSP, cookies, email (SPF/DKIM/DMARC)</li>
  <li>DoS / rate limiting on non-authentication endpoints</li>
  <li>Content spoofing without demonstrated attack vector</li>
  <li>Outdated browser vulnerabilities, software version disclosure, benign information disclosure</li>
  <li>Tabnabbing, self-XSS, non-technical attacks (phishing, social engineering)</li>
  <li>Session invalidation issues, password/email policy issues</li>
  <li>Blind SSRF without proven business impact</li>
  <li>Broken link hijacking (unless directly embedded in frontend code)</li>
  <li>Vulnerabilities requiring MITM, physical access, jailbroken/rooted environment</li>
  <li>Arbitrary file upload, internal IP/domain exposure, path disclosure</li>
  <li>Auth app secret in APK, binary reverse engineering deficiencies</li>
  <li>Reports from automated scans without working PoCs</li>
  <li>GitHub token/key mentions without proof of production use</li>
  <li>Documentation errors</li>
</ul>
<p class="text-xs text-slate-400 mt-3">Contact: https://Cboemarkets.zapier.app · 3C Trade Tech Ltd. · Geneva Place, 2nd Floor, #333 Waterfront Drive, Road Town Tortola, British Virgin Islands · BVI company number: 2164568</p>',
    'bb_until'       => '<p class="text-xs text-slate-400 mb-3 italic">This Bug Bounty is effective as of April 12, 2024.</p>
<p>Thank you for your interest in our Bug Bounty program and helping us make our platform stronger and safer. If you discover a bug, we welcome your cooperation in responsibly investigating and reporting it to us.</p>
<p><strong>1. INTRODUCTION</strong></p>
<p><strong>1.1 Purpose:</strong> To leverage the expertise of security researchers to identify and report vulnerabilities in our systems, applications, and network infrastructure.</p>
<p><strong>1.2 Scope — In Scope:</strong></p>
<ul class="list-disc pl-5 space-y-1">
  <li>Customer Cabinet: https://app.Cboemarketsbot.io/</li>
  <li>API Interactions: https://api.Cboemarketsbot.io/</li>
  <li>iOS Application: https://apps.apple.com/app/id6446649413</li>
  <li>Android Application: https://play.google.com/store/apps/details?id=io.threecommas.wallet</li>
</ul>
<p><strong>Excluded from scope:</strong> https://feedback.Cboemarketsbot.io/ (3rd party feedback portal), https://careers.Cboemarketsbot.io/ (careers portal), https://Cboemarkets.tech/ (experimental project).</p>
<p><strong>1.3 Key Definitions:</strong> Bug Bounty Program, Security Researcher, Confidentiality, NDA, Duplicate Report, Vulnerability, CVSS, Proof of Concept (PoC), Responsible Disclosure — standard industry definitions apply. CVSS v3.1 is used for scoring.</p>
<p><strong>2. PROGRAM OVERVIEW</strong></p>
<p>The program is open to all eligible security researchers. Participants must: obtain explicit authorisation before testing; respect the defined scope; report vulnerabilities promptly and responsibly; refrain from actions that could cause harm or disrupt services; and adhere to responsible disclosure principles.</p>
<p><strong>3. ELIGIBILITY</strong></p>
<ul class="list-disc pl-5 space-y-1">
  <li>Must be at least 18 years old.</li>
  <li>Must not be an employee, contractor, or affiliate of our Company.</li>
  <li>Must not be prohibited by law or regulations from participating (no sanctions, etc.).</li>
  <li>Must comply with all applicable laws and regulations.</li>
</ul>
<p><strong>4. VULNERABILITY SUBMISSION PROCESS</strong></p>
<p>Submit a detailed report including: description of the vulnerability; steps to reproduce; affected system/version; proof-of-concept (required for medium severity and above). Reports are submitted via https://Cboemarkets.zapier.app. We acknowledge receipt within 2 business days and provide an initial assessment within 10 business days.</p>
<p><strong>5. SEVERITY LEVELS AND REWARDS</strong></p>
<table class="w-full text-xs border-collapse mt-2 mb-2">
  <thead><tr class="bg-slate-100"><th class="border border-slate-200 px-2 py-1 text-left">Severity</th><th class="border border-slate-200 px-2 py-1 text-left">CVSS Score</th><th class="border border-slate-200 px-2 py-1 text-left">Min</th><th class="border border-slate-200 px-2 py-1 text-left">Max</th></tr></thead>
  <tbody>
    <tr><td class="border border-slate-200 px-2 py-1">Low</td><td class="border border-slate-200 px-2 py-1">0.1–3.9</td><td class="border border-slate-200 px-2 py-1">50 USDT</td><td class="border border-slate-200 px-2 py-1">200 USDT</td></tr>
    <tr><td class="border border-slate-200 px-2 py-1">Medium</td><td class="border border-slate-200 px-2 py-1">4.0–6.9</td><td class="border border-slate-200 px-2 py-1">200 USDT</td><td class="border border-slate-200 px-2 py-1">1,000 USDT</td></tr>
    <tr><td class="border border-slate-200 px-2 py-1">High</td><td class="border border-slate-200 px-2 py-1">7.0–8.9</td><td class="border border-slate-200 px-2 py-1">1,000 USDT</td><td class="border border-slate-200 px-2 py-1">2,500 USDT</td></tr>
    <tr><td class="border border-slate-200 px-2 py-1">Critical</td><td class="border border-slate-200 px-2 py-1">9.0–10.0</td><td class="border border-slate-200 px-2 py-1">2,500 USDT</td><td class="border border-slate-200 px-2 py-1">5,000 USDT</td></tr>
  </tbody>
</table>
<p>Actual payout within the range is at the sole discretion of the Program Administration. Provide your TRC20 wallet address for reward payment.</p>
<p><strong>6. LEGAL AND COMPLIANCE</strong></p>
<p>Participants must comply with all applicable laws. All tax obligations on rewards are the sole responsibility of the participant. Vulnerability data is handled per our Privacy Notice at https://Cboemarketsbot.io/privacy-notice. Participants must not access, exfiltrate, or tamper with personal data belonging to others.</p>
<p><strong>Sanctions:</strong> By participating, you represent you are not on any sanctions list (EU, UN, Estonia, OFAC, U.S. Commerce, UK OFSI), your participation does not violate international sanctions applicable in the Republic of Estonia, and you are not from a sanctioned country. If you become subject to sanctions, you must immediately stop and notify us.</p>
<p><strong>7. RESPONSIBLE DISCLOSURE</strong></p>
<p>Do not publicly disclose any vulnerability before receiving approval from program administrators. Allow reasonable time for remediation. Participants who report valid vulnerabilities receive acknowledgement, potential swag, and (with consent) public recognition.</p>
<p><strong>8. ISSUES EXCLUDED FROM SCOPE</strong> (reports will not be accepted):</p>
<ul class="list-disc pl-5 space-y-1 text-xs">
  <li>Clickjacking / UI redressing with minimal security impact</li>
  <li>CSRF on unauthenticated or non-sensitive forms</li>
  <li>Application-level crashes without exploit; MITM/SQLi mentions without PoC</li>
  <li>Known vulnerable libraries without working PoC</li>
  <li>Missing best practices in SSL/TLS, CSP, cookies, email (SPF/DKIM/DMARC)</li>
  <li>DoS / rate limiting on non-authentication endpoints</li>
  <li>Content spoofing without demonstrated attack vector</li>
  <li>Outdated browser vulnerabilities, software version disclosure, benign information disclosure</li>
  <li>Tabnabbing, self-XSS, non-technical attacks (phishing, social engineering)</li>
  <li>Session invalidation issues, password/email policy issues</li>
  <li>Blind SSRF without proven business impact</li>
  <li>Broken link hijacking (unless directly embedded in frontend code)</li>
  <li>Vulnerabilities requiring MITM, physical access, jailbroken/rooted environment</li>
  <li>Arbitrary file upload, internal IP/domain exposure, path disclosure</li>
  <li>Auth app secret in APK, binary reverse engineering deficiencies</li>
  <li>Reports from automated scans without working PoCs</li>
  <li>GitHub token/key mentions without proof of production use</li>
  <li>Documentation errors</li>
</ul>
<p class="text-xs text-slate-400 mt-3">Contact: https://Cboemarkets.zapier.app · Cboemarkets Technologies OÜ · Laeva 2, Tallinn, Estonia, 10412 · Registration code: 14125515</p>',
    'cp_from'        => '<p class="text-xs text-slate-400 mb-3 italic">This Complaint Procedure is effective as of 29 December 2024.</p>
<p><strong>PURPOSE</strong></p>
<p>This Complaint Procedure informs you about how to contact us regarding any complaint about the services we provide, how we will address your complaint, and what options are available should you not be satisfied with our response.</p>
<p><strong>HOW TO CONTACT US</strong></p>
<p>Contact our Customer Service Team by email: complaints[at]Cboemarketsbot.io</p>
<p><strong>HOW TO MAKE A COMPLAINT</strong></p>
<p>To register an official complaint, send an email to complaints[at]Cboemarketsbot.io with a detailed description of your issue. The more detail you provide, the faster we can respond — otherwise we may need to request additional information.</p>
<p>After submitting a complaint, you will receive an acknowledgement within <strong>72 hours</strong> of us receiving it.</p>
<p><strong>RESPONSE TIME</strong></p>
<ul class="list-disc pl-5 space-y-1">
  <li>Our Customer Service Team will use best efforts to resolve your matter promptly.</li>
  <li>A final decision will be given within <strong>10 business days</strong>. If the complaint cannot be resolved within this period, we may extend by an additional 10 business days — you will be informed of the extension and the reasons why.</li>
  <li>If your complaint requires engagement of a third party, the final decision will be given within <strong>8 weeks</strong>.</li>
  <li>For complaints related to personal data processing, we follow the GDPR timeframe and respond within <strong>30 days</strong>.</li>
</ul>
<p>If you are not satisfied with our response regarding Personal Data, or believe we are processing your Personal Data unlawfully, you may submit a claim to the data protection supervisory authority of the country of your residence.</p>',
    'cp_until'       => '<p><strong>PURPOSE</strong></p>
<p>This Complaint Procedure informs you about how to contact us regarding any complaint about the services we provide, how we will address your complaint, and what options are available should you not be satisfied with our response.</p>
<p><strong>HOW TO CONTACT US</strong></p>
<p>Contact our Customer Service Team by email: complaints[at]Cboemarketsbot.io</p>
<p><strong>HOW TO MAKE A COMPLAINT</strong></p>
<p>To register an official complaint, send an email to complaints[at]Cboemarketsbot.io with a detailed description of your issue. The more detail you provide, the faster we can respond — otherwise we may need to request additional information.</p>
<p>After submitting a complaint, you will receive an acknowledgement within <strong>72 hours</strong> of us receiving it.</p>
<p><strong>RESPONSE TIME</strong></p>
<ul class="list-disc pl-5 space-y-1">
  <li>Our Customer Service Team will use best efforts to resolve your matter promptly.</li>
  <li>A final decision will be given within <strong>10 business days</strong>. If the complaint cannot be resolved within this period, we may extend by an additional 10 business days — you will be informed of the extension and the reasons why.</li>
  <li>If your complaint requires engagement of a third party, the final decision will be given within <strong>8 weeks</strong>.</li>
  <li>For complaints related to personal data processing, we follow the GDPR timeframe and respond within <strong>30 days</strong>.</li>
</ul>
<p>If you are not satisfied with our response regarding Personal Data, or believe we are processing your Personal Data unlawfully, you may submit a claim to the Estonian Data Protection Inspectorate (Andmekaitse Inspektsioon) at info[at]aki.ee (https://www.aki.ee/).</p>
<p><strong>ONLINE DISPUTE RESOLUTION</strong></p>
<p>You always have the right to use the EU Online Dispute Resolution (ODR) platform to lodge a consumer complaint: https://ec.europa.eu/consumers/odr/</p>',
    'aff_from_2025'  => '<p class="text-xs text-slate-400 mb-3 italic">Cboemarkets AFFILIATE PROGRAM TERMS AND CONDITIONS — effective as of 10 November, 2025. Represented by 3C Trade Tech Ltd (British Virgin Islands, reg. 2164568).</p>
<p><strong>BY SHARING THE AFFILIATE LINK</strong> you accept these terms. Accepting automatically terminates the Terms of Referral — simultaneous participation in Affiliate and Referral Programs is not permitted.</p>
<p><strong>1. DEFINITIONS</strong></p>
<p><strong>1.1 Affiliate / you</strong> — a natural person or legal entity with a Client Account, not banned/blocked/sanctioned, who has accepted the Affiliate Terms and intends to attract New Users via the Affiliate Link.</p>
<p><strong>1.2 Affiliate Account</strong> — a separately displayed account available upon acceptance; funds accounted in USDC equivalent and accrued per Section 5.</p>
<p><strong>1.3 Affiliate Program</strong> — the system by which the Affiliate earns a bonus for referring customers via Affiliate Link.</p>
<p><strong>1.4 Affiliate Link</strong> — an auto-generated hyperlink to the Software registration scenario, personalized for the Affiliate.</p>
<p><strong>1.5 Client / New User</strong> — a person/entity who (a) registered via Affiliate Link or Tracking Code, (b) purchased and paid for a subscription for the first time, and (c) is not the Affiliate itself.</p>
<p><strong>1.6 Referred User</strong> — a person/entity who (a) registered via Affiliate Link or Tracking Code, (b) currently maintains an active paid subscription, and (c) is not the Affiliate itself.</p>
<p><strong>1.7 Confidential Information</strong> — sensitive/non-public data shared by Cboemarkets (business strategies, financial data, trade secrets, etc.).</p>
<p><strong>1.8 Intellectual Property</strong> — trademarks, logos, trade names, copyrights, and other assets exclusively owned by Cboemarkets.</p>
<p><strong>1.9 Marketing Guidelines</strong> — standards for promoting Cboemarkets products/brand (Annex 1, integral to these Terms).</p>
<p><strong>1.10 Software</strong> — Cboemarkets SaaS, website at https://Cboemarketsbot.io, mobile apps, and APIs.</p>
<p><strong>1.11 Tracking Code</strong> — auto-generated code for the Affiliate to attract new Qualified Clients.</p>
<p><strong>1.12 Privacy laws</strong> — EU and BVI regulations governing personal data.</p>
<p><strong>2. ELIGIBILITY</strong></p>
<p>Apply via contact details at https://Cboemarketsbot.io/affiliate or in the "Invite Friends" section. Applications reviewed at Cboemarkets&rsquo; discretion; Cboemarkets may reject without justification. All provided information must be true and up to date. KYC documentation required; Cboemarkets reserves the right to conduct KYC checks.</p>
<p><strong>3. ADVERTISING SCHEDULE AND GEO-TARGETING</strong></p>
<p>Advertising permitted worldwide from contract date, except comprehensively sanctioned countries per Section 6. <strong>Advertising is prohibited in countries within the European Economic Area (EEA).</strong> Cboemarkets may restrict services in certain regions at its discretion.</p>
<p><strong>4. OBLIGATIONS OF THE AFFILIATE</strong></p>
<p>Comply with all applicable laws, regulations, and marketing/consumer protection rules. The Affiliate shall not: enter agreements on Cboemarkets&rsquo; behalf; present themselves as a Cboemarkets employee/partner; collude with Clients; make warranties about the Software; harm Cboemarkets&rsquo; reputation. Non-Cboemarkets materials must be submitted for approval before publication.</p>
<p><strong>5. COMPENSATION</strong></p>
<p><strong>5.1 Payment Terms.</strong> Cboemarkets pays an Affiliate Bonus (sum without VAT) based on three factors:</p>
<ul class="list-disc pl-5 space-y-1">
  <li><strong>Subscription Revenue Share</strong> — a % of subscription fees paid by Referred Users, depending on total trading volume (TV) and number of New Paid Referred Users. Withdrawal must be requested via the affiliate workspace at https://app.Cboemarketsbot.io.</li>
  <li><strong>Performance Bonuses</strong> — tiered bonus based on total trading volume of Referred Users per month. Paid by the 25th of month X+1.</li>
  <li><strong>New User Payout Bonus</strong> — bonus based on number of New Users per month. Paid by the 25th of month X+1.</li>
</ul>
<p><strong>5.2 General Payment Terms.</strong> Affiliate Bonus paid in USDC via ERC20 network. An invoice may be required before payment.</p>
<p><strong>5.3 Performance Bonuses (monthly trading volume tiers):</strong></p>
<div class="overflow-x-auto">
<table class="w-full text-xs border-collapse border border-slate-600 my-2">
  <thead><tr class="bg-slate-700">
    <th class="border border-slate-600 px-2 py-1">Minimal Performance Level</th>
    <th class="border border-slate-600 px-2 py-1">Performance Bonus</th>
  </tr></thead>
  <tbody>
    <tr><td class="border border-slate-600 px-2 py-1">$0</td><td class="border border-slate-600 px-2 py-1">$0.00</td></tr>
    <tr><td class="border border-slate-600 px-2 py-1">$500,000</td><td class="border border-slate-600 px-2 py-1">$28.00</td></tr>
    <tr><td class="border border-slate-600 px-2 py-1">$1,000,000</td><td class="border border-slate-600 px-2 py-1">$56.00</td></tr>
    <tr><td class="border border-slate-600 px-2 py-1">$2,000,000</td><td class="border border-slate-600 px-2 py-1">$130.00</td></tr>
    <tr><td class="border border-slate-600 px-2 py-1">$5,000,000</td><td class="border border-slate-600 px-2 py-1">$278.00</td></tr>
    <tr><td class="border border-slate-600 px-2 py-1">$10,000,000</td><td class="border border-slate-600 px-2 py-1">$463.00</td></tr>
    <tr><td class="border border-slate-600 px-2 py-1">$15,000,000</td><td class="border border-slate-600 px-2 py-1">$648.00</td></tr>
    <tr><td class="border border-slate-600 px-2 py-1">$20,000,000</td><td class="border border-slate-600 px-2 py-1">$833.00</td></tr>
    <tr><td class="border border-slate-600 px-2 py-1">$25,000,000</td><td class="border border-slate-600 px-2 py-1">$1,018.00</td></tr>
  </tbody>
</table>
</div>
<p>Bonuses may be adjusted based on market, legal, or financial conditions with prior notice.</p>
<p><strong>5.4 Revenue Share Bonus:</strong></p>
<div class="overflow-x-auto">
<table class="w-full text-xs border-collapse border border-slate-600 my-2">
  <thead><tr class="bg-slate-700">
    <th class="border border-slate-600 px-2 py-1">Conditions</th>
    <th class="border border-slate-600 px-2 py-1">Share</th>
  </tr></thead>
  <tbody>
    <tr><td class="border border-slate-600 px-2 py-1">Basic tier (active until higher tier is reached)</td><td class="border border-slate-600 px-2 py-1">30%</td></tr>
    <tr><td class="border border-slate-600 px-2 py-1">5M+ in Performance OR &ge;25 New Users/month</td><td class="border border-slate-600 px-2 py-1">40%</td></tr>
    <tr><td class="border border-slate-600 px-2 py-1">10M+ in Performance OR &ge;50 New Users/month</td><td class="border border-slate-600 px-2 py-1">45%</td></tr>
  </tbody>
</table>
</div>
<p><strong>5.5 New User Payout Bonus:</strong></p>
<p><strong>Starter (first 3 months):</strong> $15 per new user. Auto-promoted to Pro after 3 months, or earlier if 20+ new paid users are reached in one month.</p>
<div class="overflow-x-auto">
<table class="w-full text-xs border-collapse border border-slate-600 my-2">
  <thead><tr class="bg-slate-700">
    <th class="border border-slate-600 px-2 py-1">Pro Tier</th>
    <th class="border border-slate-600 px-2 py-1">CPA Bonus (re-calculated monthly)</th>
  </tr></thead>
  <tbody>
    <tr><td class="border border-slate-600 px-2 py-1">&ge;5 new paid/month</td><td class="border border-slate-600 px-2 py-1">$15 per new user</td></tr>
    <tr><td class="border border-slate-600 px-2 py-1">&ge;20 new paid/month</td><td class="border border-slate-600 px-2 py-1">$17 per new user</td></tr>
    <tr><td class="border border-slate-600 px-2 py-1">&ge;20 new paid/month (higher)</td><td class="border border-slate-600 px-2 py-1">$19 per new user</td></tr>
  </tbody>
</table>
</div>
<p><strong>5.6 Additional Bonuses.</strong> Performance-based bonuses available for reaching milestones (exclusive promotions, early feature access, bonus payments). <strong>5.7 Withdrawals.</strong> Funds withdrawable to external wallet subject to Software-displayed limitations. <strong>5.8 Custom Plans.</strong> Available via a separate signed order form; standard terms apply until signing.</p>
<p><strong>6. SANCTION COMPLIANCE</strong></p>
<p>You warrant you are not on EU, UN, BVI, OFAC, U.S. Commerce, or UK OFSI sanctions lists; your use does not violate sanctions applicable in the BVI; you are not from a comprehensively sanctioned country. Cboemarkets may restrict services and terminate this Agreement if you become subject to sanctions.</p>
<p><strong>7. MARKETING COMPLIANCE</strong></p>
<p>Comply with Marketing Guidelines (Annex 1). Certain materials require prior written approval. Cboemarkets may monitor activities. Breaches may result in warnings, sanctions, suspension, or termination.</p>
<p><strong>8. LIABILITY</strong></p>
<p>Cboemarkets is not liable for indirect, incidental, special, or consequential damages, or loss of revenue or profits.</p>
<p><strong>9. GOVERNING LAW AND JURISDICTION</strong></p>
<p>Governed by the laws of the British Virgin Islands. Disputes settled in the relevant court in the BVI.</p>
<p><strong>10. CONFIDENTIALITY</strong></p>
<p>Preserve Confidential Information; use only for Affiliate Program purposes; take reasonable safeguards against disclosure; notify Cboemarkets of any unauthorized use. All Confidential Information remains Cboemarkets property.</p>
<p><strong>11. INTELLECTUAL PROPERTY</strong></p>
<p>All IP exclusively owned by Cboemarkets. Limited, non-exclusive, revocable license granted solely for marketing. Must not modify IP or register similar trademarks/domains without consent. Cease use upon termination. Unauthorized IP use must be indemnified.</p>
<p><strong>12. INDEMNIFICATION</strong></p>
<p>Affiliate indemnifies Cboemarkets against third-party claims and regulatory fines/penalties caused by Affiliate non-compliance. Does not apply to Cboemarkets&rsquo; own gross negligence or willful misconduct.</p>
<p><strong>13. TERM AND TERMINATION</strong></p>
<p>Cboemarkets may terminate at any time, effective immediately, for any reason including law violations, fraud, or misrepresentation. Fraudulent activity results in automatic termination without payment of Affiliate Bonus.</p>
<p><strong>14. PRIVACY AND PERSONAL DATA</strong></p>
<p>Neither party acts as Controller/Processor for the other. Personal data processed per Cboemarkets Privacy Notice.</p>
<p><strong>Annex 1 — Marketing Guidelines</strong></p>
<p>No false/guaranteed profit claims; no risk-free or expertise claims; no misleading performance claims; no FOMO/FUD. All advertising must be clear, lawful, and properly disclose affiliate status, risks, and advertiser identity.</p>',
    'aff_until_2025' => '<p class="text-xs text-slate-400 mb-3 italic">Cboemarkets AFFILIATE PROGRAM TERMS AND CONDITIONS — effective as of December 29, 2024. Represented by 3C Trade Tech Ltd (British Virgin Islands, reg. 2164568).</p>
<p><strong>BY SHARING THE AFFILIATE LINK</strong> you accept these terms. Accepting automatically terminates the Terms of Referral — simultaneous participation in Affiliate and Referral Programs is not permitted.</p>
<p><strong>1. DEFINITIONS</strong></p>
<p><strong>1.1 Affiliate / you</strong> — a natural person or legal entity with a Client Account, not banned/blocked/sanctioned, who has accepted the Affiliate Terms and intends to attract New Users via the Affiliate Link.</p>
<p><strong>1.2 Affiliate Account</strong> — a separately displayed account available upon acceptance; funds accounted in USDC equivalent and accrued per Section 5.</p>
<p><strong>1.3 Affiliate Program</strong> — the system by which the Affiliate earns a bonus for referring customers via Affiliate Link.</p>
<p><strong>1.4 Affiliate Link</strong> — an auto-generated hyperlink to the Software registration scenario, personalized for the Affiliate.</p>
<p><strong>1.5 Client / New User</strong> — a person/entity who (a) registered via Affiliate Link or Tracking Code, (b) purchased and paid for a subscription for the first time, and (c) is not the Affiliate itself.</p>
<p><strong>1.6 Confidential Information</strong> — sensitive/non-public data shared by Cboemarkets (business strategies, financial data, trade secrets, etc.).</p>
<p><strong>1.7 Intellectual Property</strong> — trademarks, logos, trade names, copyrights, and other assets exclusively owned by Cboemarkets.</p>
<p><strong>1.8 Marketing Guidelines</strong> — standards for promoting Cboemarkets products/brand (Annex 1, integral to these Terms).</p>
<p><strong>1.9 Software</strong> — Cboemarkets SaaS, website at https://Cboemarketsbot.io, mobile apps, and APIs.</p>
<p><strong>1.10 Tracking Code</strong> — auto-generated code for the Affiliate to attract new Qualified Clients.</p>
<p><strong>1.11 Privacy laws</strong> — EU and BVI regulations governing personal data.</p>
<p><strong>2. ELIGIBILITY</strong></p>
<p>Apply at https://Cboemarketsbot.io/affiliate or via the "Invite Friends" section. Applications reviewed at Cboemarkets&rsquo; discretion; Cboemarkets may reject without justification. All provided information must be true and up to date. KYC documentation required; Cboemarkets reserves the right to conduct KYC checks.</p>
<p><strong>3. ADVERTISING SCHEDULE AND GEO-TARGETING</strong></p>
<p>Advertising permitted worldwide from contract date, except comprehensively sanctioned countries per Section 6. Cboemarkets may restrict services in certain regions at its discretion.</p>
<p><strong>4. OBLIGATIONS OF THE AFFILIATE</strong></p>
<p>Comply with all applicable laws, regulations, and marketing/consumer protection rules. The Affiliate shall not: enter agreements on Cboemarkets&rsquo; behalf; present themselves as a Cboemarkets employee/partner; collude with Clients; make warranties about the Software; harm Cboemarkets&rsquo; reputation. Non-Cboemarkets materials must be submitted for approval before publication.</p>
<p><strong>5. COMPENSATION</strong></p>
<p><strong>5.1 Payment Terms.</strong> Cboemarkets pays an Affiliate Bonus (sum without VAT) based on two factors: (1) Subscription Bonus — a % of subscription fees paid by New Users; (2) Trading Volume Bonuses — tiered bonuses on total monthly trading volume of referred users.</p>
<p><strong>5.2 General Payment Terms.</strong> Affiliate Bonus paid in USDC via TRC20 or ERC20 network. An invoice may be required before payment.</p>
<p><strong>5.3 Trading Volume Bonus Tiers (per month):</strong></p>
<div class="overflow-x-auto">
<table class="w-full text-xs border-collapse border border-slate-600 my-2">
  <thead><tr class="bg-slate-700">
    <th class="border border-slate-600 px-2 py-1">Total Referred Trading Volume</th>
    <th class="border border-slate-600 px-2 py-1">Bonus on Subscription</th>
    <th class="border border-slate-600 px-2 py-1">Bonus on Trading Volume</th>
  </tr></thead>
  <tbody>
    <tr><td class="border border-slate-600 px-2 py-1">&lt;$500,000</td><td class="border border-slate-600 px-2 py-1">30%</td><td class="border border-slate-600 px-2 py-1">No trading volume bonuses</td></tr>
    <tr><td class="border border-slate-600 px-2 py-1">$500,000+</td><td class="border border-slate-600 px-2 py-1">30%</td><td class="border border-slate-600 px-2 py-1">$28.00</td></tr>
    <tr><td class="border border-slate-600 px-2 py-1">$1,000,000+</td><td class="border border-slate-600 px-2 py-1">30%</td><td class="border border-slate-600 px-2 py-1">$56.00</td></tr>
    <tr><td class="border border-slate-600 px-2 py-1">$2,000,000+</td><td class="border border-slate-600 px-2 py-1">40%</td><td class="border border-slate-600 px-2 py-1">$130.00</td></tr>
    <tr><td class="border border-slate-600 px-2 py-1">$5,000,000+</td><td class="border border-slate-600 px-2 py-1">40%</td><td class="border border-slate-600 px-2 py-1">$278.00</td></tr>
    <tr><td class="border border-slate-600 px-2 py-1">$10,000,000+</td><td class="border border-slate-600 px-2 py-1">40%</td><td class="border border-slate-600 px-2 py-1">$555.00</td></tr>
    <tr><td class="border border-slate-600 px-2 py-1">$15,000,000+</td><td class="border border-slate-600 px-2 py-1">40%</td><td class="border border-slate-600 px-2 py-1">$648.00</td></tr>
    <tr><td class="border border-slate-600 px-2 py-1">$20,000,000+</td><td class="border border-slate-600 px-2 py-1">40%</td><td class="border border-slate-600 px-2 py-1">$833.00</td></tr>
    <tr><td class="border border-slate-600 px-2 py-1">$25,000,000+</td><td class="border border-slate-600 px-2 py-1">40%</td><td class="border border-slate-600 px-2 py-1">$1,000.00</td></tr>
  </tbody>
</table>
</div>
<p>Bonuses may be adjusted based on market, legal, or financial conditions with prior notice.</p>
<p><strong>5.4 Additional Bonuses.</strong> Performance-based bonuses for reaching milestones. <strong>5.5 Withdrawals.</strong> Funds withdrawable to external wallet subject to Software-displayed limitations. <strong>5.6 Custom Plans.</strong> Available via a separate signed order form; standard terms apply until signing.</p>
<p><strong>6. SANCTION COMPLIANCE</strong></p>
<p>You warrant you are not on EU, UN, BVI, OFAC, U.S. Commerce, or UK OFSI sanctions lists; your use does not violate sanctions applicable in the BVI; you are not from a comprehensively sanctioned country. Cboemarkets may restrict services and terminate this Agreement if you become subject to sanctions.</p>
<p><strong>7. MARKETING COMPLIANCE</strong></p>
<p>Comply with Marketing Guidelines (Annex 1). Certain materials require prior written approval. Cboemarkets may monitor activities. Breaches may result in warnings, sanctions, suspension, or termination.</p>
<p><strong>8. LIABILITY</strong></p>
<p>Cboemarkets is not liable for indirect, incidental, special, or consequential damages, or loss of revenue or profits.</p>
<p><strong>9. GOVERNING LAW AND JURISDICTION</strong></p>
<p>Governed by the laws of the British Virgin Islands. Disputes settled in the relevant court in the BVI.</p>
<p><strong>10. CONFIDENTIALITY</strong></p>
<p>Preserve Confidential Information; use only for Affiliate Program purposes; take reasonable safeguards; notify Cboemarkets of unauthorized use. All Confidential Information remains Cboemarkets property.</p>
<p><strong>11. INTELLECTUAL PROPERTY</strong></p>
<p>All IP exclusively owned by Cboemarkets. Limited, non-exclusive, revocable license for marketing only. Must not modify IP or register similar trademarks/domains without consent. Cease use upon termination. Unauthorized use must be indemnified.</p>
<p><strong>12. INDEMNIFICATION</strong></p>
<p>Affiliate indemnifies Cboemarkets against third-party claims and regulatory fines/penalties caused by Affiliate non-compliance. Does not apply to Cboemarkets&rsquo; own gross negligence or willful misconduct.</p>
<p><strong>13. TERM AND TERMINATION</strong></p>
<p>Cboemarkets may terminate at any time, effective immediately, for any reason. Fraudulent activity results in automatic termination without payment of Affiliate Bonus.</p>
<p><strong>14. PRIVACY AND PERSONAL DATA</strong></p>
<p>Neither party acts as Controller/Processor for the other. Personal data processed per Cboemarkets Privacy Notice.</p>
<p><strong>Annex 1 — Marketing Guidelines</strong></p>
<p>No false/guaranteed profit claims; no risk-free or expertise claims; no misleading performance claims; no FOMO/FUD. All advertising must be clear, lawful, and properly disclose affiliate status, risks, and advertiser identity.</p>',
    'aff_until_2024' => '<p class="text-xs text-slate-400 mb-3 italic">Cboemarkets AFFILIATE PROGRAM TERMS AND CONDITIONS — effective as of October 28, 2024. Represented by Cboemarkets Technologies OÜ (Estonia, reg. 14125515).</p>
<p><strong>BY SHARING THE AFFILIATE LINK</strong> you accept these terms. Accepting automatically terminates the Terms of Referral — simultaneous participation in Affiliate and Referral Programs is not permitted.</p>
<p><strong>1. DEFINITIONS</strong></p>
<p><strong>1.1 Affiliate / you</strong> — a natural person or legal entity with a Client Account, not banned/blocked/sanctioned, who has accepted the Affiliate Terms and intends to attract New Users via the Affiliate Link.</p>
<p><strong>1.2 Affiliate Account</strong> — a separately displayed account available upon acceptance; funds accounted in USDT equivalent and accrued per Section 5.</p>
<p><strong>1.3 Affiliate Program</strong> — the system by which the Affiliate earns a commission for referring customers via Affiliate Link.</p>
<p><strong>1.4 Affiliate Link</strong> — an auto-generated hyperlink to the Software registration scenario, personalized for the Affiliate.</p>
<p><strong>1.5 Client / New User</strong> — a person/entity who (a) registered via Affiliate Link or Tracking Code, (b) purchased and paid for a subscription for the first time, and (c) is not the Affiliate itself.</p>
<p><strong>1.6 Confidential Information</strong> — sensitive/non-public data shared by Cboemarkets (business strategies, financial data, trade secrets, etc.).</p>
<p><strong>1.7 Intellectual Property</strong> — trademarks, logos, trade names, copyrights, and other assets exclusively owned by Cboemarkets.</p>
<p><strong>1.8 Marketing Guidelines</strong> — standards for promoting Cboemarkets products/brand (Annex 1, integral to these Terms).</p>
<p><strong>1.9 Software</strong> — Cboemarkets SaaS, website at https://Cboemarketsbot.io, mobile apps, and APIs.</p>
<p><strong>1.10 Tracking Code</strong> — auto-generated code for the Affiliate to attract new Qualified Clients.</p>
<p><strong>1.11 Privacy laws</strong> — EU and Estonian regulations governing personal data.</p>
<p><strong>2. ELIGIBILITY</strong></p>
<p>Apply at https://Cboemarketsbot.io/affiliate or via the "Invite Friends" section. Applications reviewed at Cboemarkets&rsquo; discretion; Cboemarkets may reject without justification. All provided information must be true and up to date. KYC documentation required; Cboemarkets reserves the right to conduct KYC checks.</p>
<p><strong>3. ADVERTISING SCHEDULE AND GEO-TARGETING</strong></p>
<p>Advertising permitted worldwide from contract date, except comprehensively sanctioned countries per Section 6. Cboemarkets may restrict services in certain regions at its discretion.</p>
<p><strong>4. OBLIGATIONS OF THE AFFILIATE</strong></p>
<p>Comply with all applicable laws, regulations, and marketing/consumer protection rules. The Affiliate shall not: enter agreements on Cboemarkets&rsquo; behalf; present themselves as a Cboemarkets employee/partner; collude with Clients; make warranties about the Software; harm Cboemarkets&rsquo; reputation. Non-Cboemarkets materials must be submitted for approval before publication.</p>
<p><strong>5. COMPENSATION</strong></p>
<p><strong>5.1 Payment Terms.</strong> Cboemarkets pays an Affiliate Fee (sum without VAT) comprising a fixed bonus plus a commission rate, based on: (1) Subscription Fees — a % of subscription fees paid by New Users; (2) Trading Volume Fees — tiered commissions on total monthly trading volume of referred users.</p>
<p><strong>5.2 General Payment Terms.</strong> Affiliate Fee paid in USDT via TRC20 or ERC20 network. An invoice may be required before payment.</p>
<p><strong>5.3 Trading Volume Tiers (per month):</strong></p>
<div class="overflow-x-auto">
<table class="w-full text-xs border-collapse border border-slate-600 my-2">
  <thead><tr class="bg-slate-700">
    <th class="border border-slate-600 px-2 py-1">Total Referred Trading Volume</th>
    <th class="border border-slate-600 px-2 py-1">Commission on Subscription</th>
    <th class="border border-slate-600 px-2 py-1">Commission on Trading Volume</th>
  </tr></thead>
  <tbody>
    <tr><td class="border border-slate-600 px-2 py-1">&lt;$500,000</td><td class="border border-slate-600 px-2 py-1">30%</td><td class="border border-slate-600 px-2 py-1">No trading volume commissions</td></tr>
    <tr><td class="border border-slate-600 px-2 py-1">$500,000+</td><td class="border border-slate-600 px-2 py-1">30%</td><td class="border border-slate-600 px-2 py-1">$28.00</td></tr>
    <tr><td class="border border-slate-600 px-2 py-1">$1,000,000+</td><td class="border border-slate-600 px-2 py-1">30%</td><td class="border border-slate-600 px-2 py-1">$56.00</td></tr>
    <tr><td class="border border-slate-600 px-2 py-1">$2,000,000+</td><td class="border border-slate-600 px-2 py-1">40%</td><td class="border border-slate-600 px-2 py-1">$130.00</td></tr>
    <tr><td class="border border-slate-600 px-2 py-1">$5,000,000+</td><td class="border border-slate-600 px-2 py-1">40%</td><td class="border border-slate-600 px-2 py-1">$278.00</td></tr>
    <tr><td class="border border-slate-600 px-2 py-1">$10,000,000+</td><td class="border border-slate-600 px-2 py-1">40%</td><td class="border border-slate-600 px-2 py-1">$555.00</td></tr>
    <tr><td class="border border-slate-600 px-2 py-1">$15,000,000+</td><td class="border border-slate-600 px-2 py-1">40%</td><td class="border border-slate-600 px-2 py-1">$648.00</td></tr>
    <tr><td class="border border-slate-600 px-2 py-1">$20,000,000+</td><td class="border border-slate-600 px-2 py-1">40%</td><td class="border border-slate-600 px-2 py-1">$833.00</td></tr>
    <tr><td class="border border-slate-600 px-2 py-1">$25,000,000+</td><td class="border border-slate-600 px-2 py-1">40%</td><td class="border border-slate-600 px-2 py-1">$1,000.00</td></tr>
  </tbody>
</table>
</div>
<p>Commissions may be adjusted based on market, legal, or financial conditions with prior notice.</p>
<p><strong>5.4 Bonuses.</strong> Performance-based bonuses for reaching milestones. <strong>5.5 Withdrawals.</strong> Funds withdrawable to external wallet subject to Software-displayed limitations. <strong>5.6 Custom Plans.</strong> Available via a separate signed order form; standard terms apply until signing.</p>
<p><strong>6. SANCTION COMPLIANCE</strong></p>
<p>You warrant you are not on EU, UN, Estonia, OFAC, U.S. Commerce, or UK OFSI sanctions lists; your use does not violate sanctions applicable in Estonia; you are not from a comprehensively sanctioned country. Cboemarkets may restrict services and terminate this Agreement if you become subject to sanctions.</p>
<p><strong>7. MARKETING COMPLIANCE</strong></p>
<p>Comply with Marketing Guidelines (Annex 1). Certain materials require prior written approval. Cboemarkets may monitor activities. Breaches may result in warnings, sanctions, suspension, or termination.</p>
<p><strong>8. LIABILITY</strong></p>
<p>Cboemarkets is not liable for indirect, incidental, special, or consequential damages, or loss of revenue or profits.</p>
<p><strong>9. GOVERNING LAW AND JURISDICTION</strong></p>
<p>Governed by the laws of the Republic of Estonia. Disputes settled in Harju County Court (Estonia).</p>
<p><strong>10. CONFIDENTIALITY</strong></p>
<p>Preserve Confidential Information; use only for Affiliate Program purposes; take reasonable safeguards; notify Cboemarkets of unauthorized use. All Confidential Information remains Cboemarkets property.</p>
<p><strong>11. INTELLECTUAL PROPERTY</strong></p>
<p>All IP exclusively owned by Cboemarkets. Limited, non-exclusive, revocable license for marketing only. Must not modify IP or register similar trademarks/domains without consent. Cease use upon termination.</p>
<p><strong>12. INDEMNIFICATION</strong></p>
<p>Affiliate indemnifies Cboemarkets against third-party claims and regulatory fines/penalties caused by Affiliate non-compliance. Does not apply to Cboemarkets&rsquo; own gross negligence or willful misconduct.</p>
<p><strong>13. TERM AND TERMINATION</strong></p>
<p>Cboemarkets may terminate at any time, effective immediately, for any reason including law violations, fraud, or misrepresentation. Fraudulent activity results in automatic termination without payment of Affiliate Fee.</p>
<p><strong>14. PRIVACY AND PERSONAL DATA</strong></p>
<p>Neither party acts as Controller/Processor for the other. Personal data processed per Cboemarkets Privacy Notice.</p>
<p><strong>Annex 1 — Marketing Guidelines</strong></p>
<p>No false/guaranteed profit claims; no risk-free or expertise claims; no misleading performance claims; no FOMO/FUD. All advertising must be clear, lawful, and properly disclose affiliate status, risks, and advertiser identity.</p>',
    'cpa_from'       => '<p class="text-xs text-slate-400 mb-3 italic">Cboemarkets AFFILIATE CPA PROGRAM TERMS AND CONDITIONS — effective as of December 29, 2024. Represented by 3C Trade Tech Ltd (British Virgin Islands, reg. 2164568).</p>
<p><strong>By sharing the Affiliate Link</strong> you accept and are bound by these Affiliate Terms.</p>
<p><strong>1. DEFINITIONS</strong></p>
<p><strong>1.1 Affiliate</strong> — the legal entity applying to join the program. <strong>1.2 Affiliate Program</strong> — the system by which the Affiliate earns a bonus for referring customers via Affiliate Link. <strong>1.3 Affiliate Link</strong> — a hyperlink auto-generated by Service Partners to track New User registrations and subscription purchases. <strong>1.4 Service Partners</strong> — Affise (for WAP) and AppsFlyer (for MAAP), used for tracking, analytics, and link generation. <strong>1.5 New User</strong> — a person/entity who registered via Affiliate Link, purchased a subscription, and is not the Affiliate. <strong>1.6 Confidential Information</strong> — non-public data including business strategies, financial data, trade secrets. <strong>1.7 Software</strong> — Cboemarkets SaaS, website, mobile apps, and APIs. <strong>1.8 Intellectual Property</strong> — trademarks, logos, trade names, copyrights exclusively owned by Cboemarkets. <strong>1.9 WAP</strong> (Website Affiliate Program) — governs promotion of https://Cboemarketsbot.io via Affise (Annex 1). <strong>1.10 MAAP</strong> (Mobile Application Affiliate Program) — governs promotion of Cboemarkets mobile apps/APIs via AppsFlyer (Annex 1). <strong>1.11 Marketing Guidelines</strong> — standards for promoting Cboemarkets products/brand (Annex 2). <strong>1.12 Target Action</strong> — New User registration at or purchase of a new subscription from Cboemarkets Software.</p>
<p><strong>2. ACCEPTANCE</strong></p>
<p>By sharing the Affiliate Link you accept these Affiliate Terms.</p>
<p><strong>3. ADVERTISING SCHEDULE AND GEO TARGETING</strong></p>
<p>Advertising permitted worldwide from contract date except comprehensively sanctioned countries (Russia, Belarus, Burma, Cote d\'Ivoire, Crimea, Cuba, DR Congo, Donetsk, Iran, Iraq, Liberia, Libya, Luhansk, Nicaragua, North Korea, Sierra Leone, Somalia, Sudan, Syria, Venezuela, Yemen, Zimbabwe).</p>
<p><strong>Advertising is also prohibited in all EU countries:</strong> Austria, Belgium, Bulgaria, Croatia, Cyprus, Czech Republic, Denmark, Estonia, Finland, France, Germany, Greece, Hungary, Ireland, Italy, Latvia, Lithuania, Luxembourg, Malta, Netherlands, Poland, Portugal, Romania, Slovakia, Slovenia, Spain, Sweden.</p>
<p>Cboemarkets may restrict or refuse services in certain regions at its sole discretion.</p>
<p><strong>4. OBLIGATIONS OF THE AFFILIATE</strong></p>
<p>Complete KYC verification; comply with all applicable laws and platform terms; acknowledge participation is conditioned on Target Actions process. The Affiliate shall not: enter agreements on Cboemarkets&rsquo; behalf; misrepresent themselves as Cboemarkets staff; collude with Clients; make warranties about the Software; harm Cboemarkets&rsquo; reputation. Non-Cboemarkets materials require approval before publication.</p>
<p><strong>5. AML/KYC</strong></p>
<p>Provide all documentation required for AML/KYC (ID, proof of address, business registration, etc.). All information must be true and current. Cboemarkets may conduct AML/KYC checks and request additional documentation.</p>
<p><strong>6. THIRD-PARTY</strong></p>
<p>Cboemarkets uses Service Partners (Affise, AppsFlyer) for data tracking. Parties operate independently. Issues with Service Partners must be addressed directly with them; no liability attributable to Cboemarkets.</p>
<p><strong>7. COMPENSATION</strong></p>
<p>Compensation determined monthly from Service Partner raw data. Discrepancies resolved in favor of Service Partner data. Invoice must be submitted within 10 business days of each month start or fee is forfeited. Cboemarkets may verify data; Cboemarkets records prevail on discrepancy; partial settlement permitted. No interest/penalties for delayed payment. Affiliate responsible for all applicable taxes.</p>
<p><strong>8. RESTRICTIONS AND SANCTIONS COMPLIANCE</strong></p>
<p>Prohibited traffic: Brand Context ads, Incent, Toolbar, Cashback, Coupons, Rebrokering, Doorway by brand, Co-registration, SMS spam, Email spam.</p>
<p>Affiliate warrants that neither they nor New Users are on UN, BVI, OFAC, U.S. Commerce, or UK OFSI sanctions lists; use does not violate sanctions applicable in the BVI; they are not from comprehensively sanctioned countries or EU countries listed in Section 3. Cboemarkets may terminate or suspend services for sanctions compliance concerns.</p>
<p><strong>9. MARKETING COMPLIANCE</strong></p>
<p>Comply with Marketing Guidelines (Annex 2). Certain materials require prior written approval. Breaches may result in warnings, sanctions, suspension, or termination.</p>
<p><strong>10. TERMINATION</strong> — Cboemarkets may terminate for any or no reason, effective immediately upon notice.</p>
<p><strong>11. LIABILITY</strong> — Cboemarkets not liable for indirect, incidental, special, or consequential damages, or loss of revenue or profits.</p>
<p><strong>12. GOVERNING LAW</strong> — Laws of the British Virgin Islands; disputes in relevant BVI court.</p>
<p><strong>13. CONFIDENTIALITY</strong> — Preserve Confidential Information; use only for Affiliate Program purposes; all Confidential Information remains Cboemarkets property; report unauthorized disclosure immediately.</p>
<p><strong>14. INTELLECTUAL PROPERTY</strong> — All IP exclusively owned by Cboemarkets. Limited, non-exclusive, revocable license for marketing. Must not modify IP or register similar marks without consent. Cease use upon termination.</p>
<p><strong>15. INDEMNIFICATION</strong> — Affiliate indemnifies Cboemarkets against third-party claims and regulatory fines caused by Affiliate non-compliance. Does not apply to Cboemarkets&rsquo; own gross negligence/willful misconduct.</p>
<p><strong>16. MODIFICATION</strong> — Cboemarkets may modify Terms at any time. Continued participation implies acceptance. Affiliates responsible for reviewing updates. Affiliates who accepted before September 18, 2024 continue under prior terms.</p>
<p><strong>17–20. GENERAL PROVISIONS</strong> — Severability, No Waiver, Force Majeure, and Entire Agreement clauses apply.</p>
<p><strong>Annex 1 — WAP &amp; MAAP Specific Provisions</strong></p>
<p>WAP (via Affise) and MAAP (via AppsFlyer): Affiliate must register on respective platforms and agree to their T&amp;Cs/MSA. <strong>Specific Compensation:</strong> $3 per New User registration; $197 per new subscription purchase.</p>
<p><strong>Annex 2 — Marketing Guidelines:</strong> No false/guaranteed profit, risk-free, or misleading claims; no FOMO/FUD. All advertising must be clear, compliant, and properly disclose affiliate status, risks, and advertiser identity.</p>',
    'cpa_until'      => '<p class="text-xs text-slate-400 mb-3 italic">Cboemarkets AFFILIATE CPA PROGRAM TERMS AND CONDITIONS — effective as of September 18, 2024. Represented by Cboemarkets Technologies OÜ (Estonia, reg. 14125515).</p>
<p><strong>By sharing the Affiliate Link</strong> you accept and are bound by these Affiliate Terms.</p>
<p><strong>1. DEFINITIONS</strong></p>
<p><strong>1.1 Affiliate</strong> — the legal entity applying to join the program. <strong>1.2 Affiliate Program</strong> — the system by which the Affiliate earns a commission for referring customers via Affiliate Link. <strong>1.3 Affiliate Link</strong> — a hyperlink auto-generated by Service Partners to track New User registrations and subscription purchases. <strong>1.4 Service Partners</strong> — Affise (for WAP) and AppsFlyer (for MAAP). <strong>1.5 New User</strong> — a person/entity who registered via Affiliate Link, purchased a subscription, and is not the Affiliate. <strong>1.6 Confidential Information</strong> — non-public data including business strategies, financial data, trade secrets. <strong>1.7 Software</strong> — Cboemarkets SaaS, website, mobile apps, and APIs. <strong>1.8 Intellectual Property</strong> — trademarks, logos, trade names, copyrights exclusively owned by Cboemarkets. <strong>1.9 WAP</strong> (Website Affiliate Program) — governs promotion of https://Cboemarketsbot.io via Affise (Annex 1). <strong>1.10 MAAP</strong> (Mobile Application Affiliate Program) — governs promotion of Cboemarkets mobile apps/APIs via AppsFlyer (Annex 1). <strong>1.11 Marketing Guidelines</strong> — standards for promoting Cboemarkets products/brand (Annex 2). <strong>1.12 Target Action</strong> — New User registration at or purchase of a new subscription from Cboemarkets Software.</p>
<p><strong>2. ACCEPTANCE</strong></p>
<p>By sharing the Affiliate Link you accept these Affiliate Terms.</p>
<p><strong>3. ADVERTISING SCHEDULE AND GEO TARGETING</strong></p>
<p>Advertising permitted worldwide from contract date except comprehensively sanctioned countries (Russia, Belarus, Burma, Cote d\'Ivoire, Crimea, Cuba, DR Congo, Donetsk, Iran, Iraq, Liberia, Libya, Luhansk, Nicaragua, North Korea, Sierra Leone, Somalia, Sudan, Syria, Venezuela, Yemen, Zimbabwe). Cboemarkets may restrict or refuse services in certain regions at its sole discretion.</p>
<p><strong>4. OBLIGATIONS OF THE AFFILIATE</strong></p>
<p>Complete KYC verification; comply with all applicable laws and platform terms; acknowledge participation is conditioned on Target Actions process. The Affiliate shall not: enter agreements on Cboemarkets&rsquo; behalf; misrepresent themselves as Cboemarkets staff; collude with Clients; make warranties about the Software; harm Cboemarkets&rsquo; reputation. Non-Cboemarkets materials require approval before publication.</p>
<p><strong>5. AML/KYC</strong></p>
<p>Provide all documentation required for AML/KYC (ID, proof of address, business registration, etc.). All information must be true and current. Cboemarkets may conduct AML/KYC checks.</p>
<p><strong>6. THIRD-PARTY</strong></p>
<p>Cboemarkets uses Service Partners for data tracking. Parties operate independently. Issues with Service Partners must be addressed directly with them; no liability attributable to Cboemarkets.</p>
<p><strong>7. COMPENSATION</strong></p>
<p>Compensation determined monthly from Service Partner raw data. Discrepancies resolved in favor of Service Partner data. Invoice must be submitted within 10 business days of each month start or fee is forfeited. Cboemarkets may verify data; Cboemarkets records prevail on discrepancy; partial settlement permitted. No interest/penalties for delayed payment. Affiliate responsible for all applicable taxes.</p>
<p><strong>8. RESTRICTIONS AND SANCTIONS COMPLIANCE</strong></p>
<p>Prohibited traffic: Brand Context ads, Incent, Toolbar, Cashback, Coupons, Rebrokering, Doorway by brand, Co-registration, SMS spam, Email spam.</p>
<p>Affiliate warrants that neither they nor New Users are on EU, UN, Estonia, OFAC, U.S. Commerce, or UK OFSI sanctions lists; use does not violate sanctions applicable in Estonia; they are not from comprehensively sanctioned countries listed in Section 3. Cboemarkets may terminate or suspend services for sanctions compliance concerns.</p>
<p><strong>9. MARKETING COMPLIANCE</strong></p>
<p>Comply with Marketing Guidelines (Annex 2). Certain materials require prior written approval. Breaches may result in warnings, sanctions, suspension, or termination.</p>
<p><strong>10. TERMINATION</strong> — Cboemarkets may terminate for any or no reason, effective immediately upon notice.</p>
<p><strong>11. LIABILITY</strong> — Cboemarkets not liable for indirect, incidental, special, or consequential damages, or loss of revenue or profits.</p>
<p><strong>12. GOVERNING LAW</strong> — Laws of the Republic of Estonia; disputes in Harju County Court (Estonia).</p>
<p><strong>13. CONFIDENTIALITY</strong> — Preserve Confidential Information; use only for Affiliate Program purposes; all Confidential Information remains Cboemarkets property; report unauthorized disclosure immediately.</p>
<p><strong>14. INTELLECTUAL PROPERTY</strong> — All IP exclusively owned by Cboemarkets. Limited, non-exclusive, revocable license for marketing. Must not modify IP or register similar marks without consent. Cease use upon termination.</p>
<p><strong>15. INDEMNIFICATION</strong> — Affiliate indemnifies Cboemarkets against third-party claims and regulatory fines caused by Affiliate non-compliance. Does not apply to Cboemarkets&rsquo; own gross negligence/willful misconduct.</p>
<p><strong>16. MODIFICATION</strong> — Cboemarkets may modify Terms at any time. Continued participation implies acceptance. Affiliates who accepted before September 18, 2024 continue under prior terms.</p>
<p><strong>17–20. GENERAL PROVISIONS</strong> — Severability, No Waiver, Force Majeure, and Entire Agreement clauses apply.</p>
<p><strong>Annex 1 — WAP &amp; MAAP Specific Provisions</strong></p>
<p>WAP (via Affise) and MAAP (via AppsFlyer): Affiliate must register on respective platforms and agree to their T&amp;Cs/MSA. <strong>Specific Compensation:</strong> $3 per New User registration; $197 per new subscription purchase.</p>
<p><strong>Annex 2 — Marketing Guidelines:</strong> No false/guaranteed profit, risk-free, or misleading claims; no FOMO/FUD. All advertising must be clear, compliant, and properly disclose affiliate status, risks, and advertiser identity.</p>',
    'gdpr'           => '<p class="text-xs text-slate-400 mb-3 italic">Cboemarkets and GDPR — Our Commitment to Your Data Privacy</p>
<p>At Cboemarkets, safeguarding your personal data is our top priority. In line with the General Data Protection Regulation (GDPR), we have implemented robust measures to ensure your privacy and security, strengthening our platform&rsquo;s commitment to transparency and trust.</p>
<p><strong>What GDPR Compliance Means for You</strong></p>
<p>The GDPR is designed to give individuals greater control over their personal data and how it is used. For you, this means enhanced privacy protection without any changes to the Cboemarkets platform you know and trust. Our updated processes ensure your data is handled with great care and security, giving you peace of mind as you engage with our services.</p>
<p><strong>What We Have in Place</strong></p>
<ul class="list-disc list-inside space-y-1 mb-3">
  <li><strong>Data Protection Officer (DPO):</strong> Our DPO is available for inquiries at <a href="mailto:dpo@Cboemarketsbot.io" class="text-emerald-400 hover:underline">dpo@Cboemarketsbot.io</a>.</li>
  <li><strong>Updated Legal Documents:</strong> Our up-to-date legal documents include: Client Terms of Service, API Terms of Service for Developers, Affiliate Program Terms and Conditions, and Terms for Referral. Additional legal documents are available in our Legal Information section.</li>
  <li><strong>Enhanced Privacy Notices:</strong> Our updated Privacy Notice outlines your rights under the GDPR and details how we process personal data. Job candidates also receive a Recruitment Privacy Notice to ensure transparency regarding their data.</li>
  <li><strong>Improved Cookie Management:</strong> We have a GDPR-compliant cookie banner that seeks voluntary consent for non-essential cookies. Our Cookie Policy provides detailed insights into the types of cookies we use and their purpose.</li>
  <li><strong>Internal Policy Upgrades:</strong> Cboemarkets&rsquo; internal policies and business processes align with GDPR&rsquo;s data collection and handling standards.</li>
  <li><strong>Training &amp; Awareness:</strong> Our team receives ongoing GDPR-specific training, and our support staff is equipped to handle your privacy rights requests effectively.</li>
  <li><strong>Automated Privacy Rights Requests:</strong> Users can easily submit Data Subject Access Requests (DSAR) through their Cboemarkets accounts.</li>
  <li><strong>Enhanced Risk Management:</strong> We continually refine our processes to assess data risks and ensure prompt responses in the unlikely event of a data breach.</li>
</ul>
<p><strong>Ongoing Initiatives</strong></p>
<p>Our dedication to privacy doesn&rsquo;t stop here. We are actively:</p>
<ul class="list-disc list-inside space-y-1 mb-3">
  <li><strong>Pursuing Confirmation:</strong> To validate our compliance efforts, we are working to further improve controls and, where necessary, implement new ones following industry standards and confirm their effectiveness.</li>
  <li><strong>Building Transparency:</strong> We are developing a Trust Center to further improve visibility into our data protection practices.</li>
</ul>
<p><strong>Have Questions?</strong></p>
<p>If you have any questions, concerns, or suggestions regarding our GDPR compliance efforts, feel free to contact us at <a href="mailto:dpo@Cboemarketsbot.io" class="text-emerald-400 hover:underline">dpo@Cboemarketsbot.io</a> or <a href="mailto:support@Cboemarketsbot.io" class="text-emerald-400 hover:underline">support@Cboemarketsbot.io</a>.</p>
<p>At Cboemarkets, we&rsquo;re committed to upholding the highest standards of data privacy and security, ensuring your trust remains well-placed in our platform.</p>',
//     'refund'         => '<p class="text-xs text-slate-400 mb-3 italic">Refund Policy — effective as of December 18, 2025.</p>
// <p>Any capitalized term used herein shall have the meaning given to them in the Terms of Use.</p>
// <p>In case of any issues related to payment or refund processes, please refer to the resources available on the Cboemarkets Help Center. You may also reach out to Cboemarkets Support via the &ldquo;Contact Us&rdquo; form or by emailing <a href="mailto:support@Cboemarketsbot.io" class="text-emerald-400 hover:underline">support@Cboemarketsbot.io</a>.</p>
// <p><strong>1. Refund for Terminating a Plan</strong></p>
// <p><strong>1.1</strong> As a consumer, you have the right to withdraw from your first Subscription to a Plan within <strong>15 (fifteen) days</strong> from the date you successfully subscribed (including any Trial period), as described in Section 14.4.1 of the Terms of Use. You are entitled to a refund if you exercise this right within that period. After the 15-day withdrawal period, no refund for termination will be provided.</p>
// <p><strong>1.2</strong> For second and all subsequent purchases of the same Subscription Plan, a grace period of <strong>24 (twenty-four) hours</strong> is provided for refund eligibility, after which no refunds can be issued.</p>
// <p><strong>2. Refunds via Paddle.com</strong></p>
// <p><strong>2.1</strong> If payment(s) for the Subscription were made via our merchant of record — Paddle.com (applicable for credit/debit card or PayPal payments) — then the refund will also be processed by Paddle. Your relationship with Paddle is governed by the Paddle Checkout Buyer Terms and Conditions.</p>
// <p><strong>3. Limitations and Waivers</strong></p>
// <p><strong>3.1</strong> You may take advantage of a refund for each disputed payment only once. If a refund has already been made, you shall have no right to further contest a refund request, dispute, or transaction reversal with Cboemarkets or any third-party payment service provider, bank, or financial institution.</p>
// <p><strong>3.2</strong> Refunds will be issued in the same currency as the original payment. If the original payment was made in cryptocurrency, the refund will be issued in <strong>USDC</strong>. With your consent, we reserve the right to offer alternative compensation — such as a discount on future services, an extended subscription period, or an upgraded subscription — in lieu of a monetary refund. All refund rules apply equally to subscriptions paid with referral bonuses; in such cases refunds are facilitated within the applicable grace period set forth in Section 1.1 above.</p>
// <p><strong>3.3</strong> The processing time for refunds varies depending on the payment provider, method, and individual banking procedures. We advise consulting your payment provider or financial institution for specific details regarding refund processing times.</p>
// <p><strong>3.4</strong> Cboemarkets is not responsible for any fees that may be applied by your payment service provider when processing a refund.</p>',
      'annual_promo' => '<p class="text-xs text-slate-400 mb-3 italic">Annual Promotional Cycle — effective as of December 18, 2025.</p>
<p>3x promos are run once each year. Participation is required for active users and traders, and an upgrade may be needed before placement.</p>',
      'trade_timing' => '<p class="text-xs text-slate-400 mb-3 italic">Automated Trade Timing Policy — effective as of December 18, 2025.</p>
<p>Automated trades run in 11-day sections. Withdrawal requests become available after the active section has been completed.</p>',
  ];
  ?>

  <?php foreach ($legalDocs as $ld): ?>
  <div id="modal-<?= $ld['id'] ?>" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeLegalModal('<?= $ld['id'] ?>')"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[80vh] flex flex-col z-10">
      <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
        <h3 class="font-bold text-slate-900 text-sm leading-snug pr-4"><?= htmlspecialchars($ld['title']) ?></h3>
        <button onclick="closeLegalModal('<?= $ld['id'] ?>')" class="flex-shrink-0 text-slate-400 hover:text-slate-600 transition">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="overflow-y-auto px-5 py-4 text-sm text-slate-700 leading-relaxed flex-1 prose prose-sm max-w-none">
        <?= $legalContents[$ld['id']] ?: '<p class="text-slate-400 italic">Content coming soon.</p>' ?>
      </div>
      <div class="px-5 py-4 border-t border-slate-100">
        <button onclick="closeLegalModal('<?= $ld['id'] ?>')" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-semibold py-2.5 rounded-xl transition text-sm">Okay</button>
      </div>
    </div>
  </div>
  <?php endforeach; ?>  

  <script>
    function openLegalModal(id) {
      const m = document.getElementById('modal-' + id);
      if (m) { m.classList.remove('hidden'); document.body.style.overflow = 'hidden'; }
    }
    function closeLegalModal(id) {
      const m = document.getElementById('modal-' + id);
      if (m) { m.classList.add('hidden'); document.body.style.overflow = ''; }
    }
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        document.querySelectorAll('[id^="modal-"]').forEach(m => { m.classList.add('hidden'); });
        document.body.style.overflow = '';
      }
    });
  </script>

  <main class="max-w-xl mx-auto px-4 py-6">

    <!-- Legal Information Section -->
    <section class="bg-white border border-slate-200 rounded-2xl p-5 mb-6">
      <h2 class="font-bold text-slate-900 text-base mb-1">Legal Information</h2>
      <p class="text-xs text-slate-500 mb-4">This page contains a list of documents regulating the activity of Cboemarkets.</p>
      <ul class="divide-y divide-slate-100">
        <?php foreach ($legalDocs as $ld): ?>
        <li>
          <button onclick="openLegalModal('<?= $ld['id'] ?>')"
            class="w-full text-left py-2.5 text-sm text-emerald-700 hover:text-emerald-600 font-medium flex items-center justify-between gap-2 group transition">
            <span><?= htmlspecialchars($ld['title']) ?></span>
            <svg class="w-4 h-4 text-slate-300 group-hover:text-emerald-400 flex-shrink-0 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
          </button>
        </li>
        <?php endforeach; ?>
      </ul>
    </section>

    <?php if (empty($docs)): ?>
      <!-- <div class="text-center py-16">
        <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
        </div>
        <p class="text-slate-500 font-medium">No documents available yet.</p>
        <p class="text-slate-400 text-sm mt-1">Check back later for terms, policies and reports.</p>
      </div> -->
    <?php else: ?>
    <div class="space-y-3">
      <?php foreach ($docs as $doc): ?>
      <div class="bg-white border border-slate-200 rounded-2xl p-4 flex items-center justify-between gap-4 hover:border-emerald-300 transition">
        <div class="flex items-center gap-3 min-w-0">
          <?php
            $ext = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
            $iconColor = match($ext) {
                'pdf'  => 'text-red-500',
                'doc', 'docx' => 'text-emerald-500',
                'xls', 'xlsx' => 'text-emerald-500',
                default => 'text-slate-400',
            };
          ?>
          <div class="w-10 h-10 bg-slate-50 border border-slate-100 rounded-xl flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 <?= $iconColor ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
          </div>
          <div class="min-w-0">
            <p class="font-semibold text-slate-900 text-sm truncate"><?= sanitize($doc['title']) ?></p>
            <?php if (!empty($doc['description'])): ?>
              <p class="text-xs text-slate-500 truncate"><?= sanitize($doc['description']) ?></p>
            <?php endif; ?>
            <p class="text-xs text-slate-400 mt-0.5"><?= date('M j, Y', strtotime($doc['created_at'])) ?></p>
          </div>
        </div>
        <a href="../<?= sanitize($doc['file_path']) ?>"
          target="_blank" rel="noopener noreferrer"
          class="flex-shrink-0 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-semibold px-3 py-2 rounded-lg transition flex items-center gap-1.5">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
          Download
        </a>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </main>

  <!-- ══════════════════════════════════
       PLATFORM NOTES
  ══════════════════════════════════ -->
  <!-- <section class="max-w-2xl mx-auto px-4 pb-10 mt-2">
    <div class="bg-white border border-slate-200 rounded-2xl p-5">
      <div class="flex items-center gap-2 mb-4">
        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
        <h2 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Platform Notes</h2>
      </div>
      <div class="grid sm:grid-cols-2 gap-4">
        <div class="bg-slate-50 border border-slate-100 rounded-xl p-4">
          <p class="text-xs font-bold text-emerald-600 uppercase tracking-wider mb-1">Annual Promo Cycle</p>
          <p class="text-sm text-slate-600">3x promos are run once each year. Participation is required for active users and traders, and an upgrade may be needed before placement.</p>
        </div>
        <div class="bg-slate-50 border border-slate-100 rounded-xl p-4">
          <p class="text-xs font-bold text-emerald-600 uppercase tracking-wider mb-1">Automated Trade Timing</p>
          <p class="text-sm text-slate-600">Automated trades run in 11-day sections. Withdrawal requests become available after the active section has been completed.</p>
        </div>
      </div>
    </div>
  </section> -->

</body>
</html>
