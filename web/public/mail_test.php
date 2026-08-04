<?php
/**
 * TEMPORARY email debug page — DELETE before going live.
 * Access: http://localhost:8000/mail_test.php
 */
declare(strict_types=1);

// Basic protection — remove or lock this down after testing
if (!isset($_GET['secret']) || $_GET['secret'] !== 'debug1234') {
    http_response_code(403);
    exit('Forbidden. Add ?secret=debug1234 to the URL.');
}

require_once __DIR__ . '/../src/config.php';

// Show all errors on screen
ini_set('display_errors', '1');
error_reporting(E_ALL);

echo '<pre>';
echo "WEB_ROOT: " . WEB_ROOT . "\n";
echo "vendor/autoload.php exists: " . (file_exists(WEB_ROOT . '/vendor/autoload.php') ? 'YES' : 'NO') . "\n";
echo "SES_FROM_EMAIL: " . env('SES_FROM_EMAIL', '(not set)') . "\n";
echo "AWS_REGION: "      . env('AWS_REGION', '(not set)') . "\n";
echo "AWS_ACCESS_KEY_ID: " . (env('AWS_ACCESS_KEY_ID', '') !== '' ? 'SET (' . substr(env('AWS_ACCESS_KEY_ID'), 0, 4) . '...)' : '(not set)') . "\n";
echo "AWS_SECRET_ACCESS_KEY: " . (env('AWS_SECRET_ACCESS_KEY', '') !== '' ? 'SET' : '(not set)') . "\n\n";

require_once __DIR__ . '/../src/email.php';

$to = trim((string)($_GET['to'] ?? $_POST['to'] ?? ''));
if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    echo "Enter a valid recipient email below to run the test.\n\n";
    echo '</pre>';
    ?>
    <form method="post" style="max-width:520px;margin:8px auto;padding:12px;border:1px solid #ddd;border-radius:8px;font-family:Arial,sans-serif;">
      <input type="hidden" name="secret" value="<?= htmlspecialchars((string)($_GET['secret'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      <label for="to" style="display:block;margin-bottom:6px;">Recipient Email</label>
      <input id="to" name="to" type="email" required placeholder="you@example.com" style="width:100%;padding:10px;border:1px solid #ccc;border-radius:6px;margin-bottom:10px;">
      <button type="submit" style="padding:10px 14px;border:0;border-radius:6px;background:#0ea5e9;color:#fff;cursor:pointer;">Send Test Email</button>
    </form>
    <?php
    exit;
}

echo "Sending test email to: $to\n";

$debugError = null;
$result = send_email(
    $to,
    'Test Email from 3Commas',
    '<h2>It works!</h2><p>This is a test email sent at ' . date('Y-m-d H:i:s') . '.</p>',
    $debugError
);

if ($result) {
    echo "\nSUCCESS: email sent.\n";
} else {
    echo "\nFAILED: " . ($debugError ?: 'unknown error') . "\n";
}
echo '</pre>';
