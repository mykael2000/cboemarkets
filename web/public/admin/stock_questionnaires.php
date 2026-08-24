<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/helpers.php';

require_admin();

$questionnaires = [];
try {
    $questionnaires = db()->query(
        'SELECT q.payload_json, q.completed_at, u.id AS user_id, u.name, u.email
         FROM stock_access_questionnaires q
         JOIN users u ON u.id = q.user_id
         ORDER BY q.completed_at DESC'
    )->fetchAll();
} catch (Throwable) {}

function questionnaire_answer(array $answers, string $key): string
{
    $value = $answers[$key] ?? '';
    return is_array($value) ? implode(', ', array_map('strval', $value)) : (string) $value;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/images/favicon.png">
  <title>Stocks Questionnaires - CBOE Markets Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-800 text-white min-h-screen">
<div class="flex min-h-screen">
  <?php $activeAdminPage = 'stock_questionnaires.php'; include __DIR__ . '/_sidebar.php'; ?>

  <main class="flex-1 bg-slate-800 p-4 sm:p-6 lg:p-8 pt-20 lg:pt-8">
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-white">Stocks Questionnaires</h1>
      <p class="mt-1 text-sm text-slate-400"><?= count($questionnaires) ?> completed suitability questionnaire<?= count($questionnaires) === 1 ? '' : 's' ?></p>
    </div>

    <?php if (empty($questionnaires)): ?>
      <div class="rounded-2xl bg-slate-700 p-8 text-center text-sm text-slate-400">No completed stocks questionnaires yet.</div>
    <?php else: ?>
      <div class="space-y-5">
      <?php foreach ($questionnaires as $questionnaire): ?>
        <?php
          $payload = json_decode((string) $questionnaire['payload_json'], true);
          $answers = is_array($payload['answers'] ?? null) ? $payload['answers'] : [];
          $name = trim(implode(' ', array_filter([
              questionnaire_answer($answers, 'first_name'),
              questionnaire_answer($answers, 'middle_name'),
              questionnaire_answer($answers, 'last_name'),
          ])));
          $fields = [
              'Date of birth' => questionnaire_answer($answers, 'date_of_birth'),
              'Residential address' => questionnaire_answer($answers, 'residential_address'),
              'Mailing address' => questionnaire_answer($answers, 'mailing_address'),
              'Phone number' => questionnaire_answer($answers, 'phone_number'),
              'Email address' => questionnaire_answer($answers, 'email_address'),
              'SSN or Tax ID' => questionnaire_answer($answers, 'tax_id'),
              'Citizenship / residency' => questionnaire_answer($answers, 'citizenship_residency'),
              'Government ID type' => questionnaire_answer($answers, 'government_id_type'),
              'Employment status' => questionnaire_answer($answers, 'employment_status'),
              'Occupation' => questionnaire_answer($answers, 'occupation'),
              'Employer name' => questionnaire_answer($answers, 'employer_name'),
              'Employer address' => questionnaire_answer($answers, 'employer_address'),
              'Annual income range' => questionnaire_answer($answers, 'annual_income_range'),
              'Net worth range' => questionnaire_answer($answers, 'net_worth_range'),
              'Liquid / investable assets' => questionnaire_answer($answers, 'liquid_assets_range'),
              'Source of funds' => questionnaire_answer($answers, 'source_of_funds'),
              'Investment objective' => questionnaire_answer($answers, 'investment_objective'),
              'Investment experience' => questionnaire_answer($answers, 'investment_experience'),
              'Risk tolerance' => questionnaire_answer($answers, 'risk_tolerance'),
              'Time horizon' => questionnaire_answer($answers, 'time_horizon'),
              'Investment products' => questionnaire_answer($answers, 'investment_products'),
              'Account type' => questionnaire_answer($answers, 'account_type'),
              'Options trading' => questionnaire_answer($answers, 'options_trading'),
              'Funding methods' => questionnaire_answer($answers, 'funding_methods'),
              'Other trading features' => questionnaire_answer($answers, 'other_trading_features'),
          ];
        ?>
        <section class="overflow-hidden rounded-2xl bg-slate-700">
          <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-600 px-5 py-4">
            <div>
              <h2 class="font-bold text-white"><?= sanitize($name !== '' ? $name : $questionnaire['name']) ?></h2>
              <p class="mt-0.5 text-sm text-slate-400"><?= sanitize($questionnaire['email']) ?> <span class="mx-1">|</span> User #<?= (int) $questionnaire['user_id'] ?></p>
            </div>
            <span class="rounded-full bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-400">Submitted <?= date('M j, Y H:i', strtotime($questionnaire['completed_at'])) ?></span>
          </div>
          <dl class="grid gap-x-6 divide-y divide-slate-600 px-5 sm:grid-cols-2 sm:divide-y-0">
            <?php foreach ($fields as $label => $value): ?>
              <?php if ($value !== ''): ?>
              <div class="border-b border-slate-600 py-3 last:border-b-0 sm:[&:nth-last-child(2)]:border-b-0">
                <dt class="text-xs font-medium text-slate-400"><?= sanitize($label) ?></dt>
                <dd class="mt-1 whitespace-pre-line break-words text-sm text-slate-100"><?= sanitize($value) ?></dd>
              </div>
              <?php endif; ?>
            <?php endforeach; ?>
          </dl>
        </section>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>
</div>
</body>
</html>
