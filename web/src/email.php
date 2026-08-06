<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Safe email debug logger: attempts to write to web/logs/email_debug.log
 * Falls back to PHP error_log() if the file cannot be written to.
 */
function email_debug_log(string $msg): void
{
  $logDir = WEB_ROOT . '/logs';
  $file = $logDir . '/email_debug.log';
  // Try to ensure directory exists
  if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
  }
  // Write to file if writable, otherwise fallback to system log (silently)
  if (is_dir($logDir) && is_writable($logDir)) {
    @file_put_contents($file, $msg, FILE_APPEND | LOCK_EX);
  } else {
    @error_log($msg);
  }
}

function apply_email_branding(string $htmlBody): string
{
    // Full HTML documents (from build_admin_email) handle their own branding
    if (stripos(ltrim($htmlBody), '<!DOCTYPE') === 0) {
        return $htmlBody;
    }

    $appUrl  = rtrim((string) env('APP_URL', ''), '/');
    $logoUrl = $appUrl !== '' ? $appUrl . '/images/favicon.png' : '';

    $logoImg = $logoUrl !== ''
        ? '<img src="' . $logoUrl . '" alt="" width="32" height="32" style="display:block;border:0;width:32px;height:32px;">'
        : '';

    $brandBlock = <<<HTML
    <table align="center" cellpadding="0" cellspacing="0" border="0" style="margin:0 auto 20px;">
      <tr>
        <td style="vertical-align:middle;padding-right:10px;">{$logoImg}</td>
        <td style="vertical-align:middle;">
          <span style="font-family:Arial,sans-serif;font-size:22px;font-weight:800;color:#10b981;letter-spacing:-0.5px;">CBOE Markets</span>
        </td>
      </tr>
    </table>
    HTML;

    return $brandBlock . "\n" . $htmlBody;
}

/**
 * Send an email via AWS SES (SesV2Client).
 * Wraps in try/catch so a missing SES config won't crash the app.
 */
function send_email(string $to, string $subject, string $htmlBody, ?string &$debugError = null): bool
{
    static $sdkLoaded = null;

  $debugError = null;
  // Decide driver: 'ses' or 'smtp' (default 'ses')
  $driver = env('EMAIL_DRIVER', 'ses');

  // Always apply branding
  $finalHtmlBody = apply_email_branding($htmlBody);

  if ($driver === 'smtp') {
    return send_email_via_smtp($to, $subject, $finalHtmlBody, $debugError);
  }

  // Fallback to SES
  if ($sdkLoaded === null) {
    $vendorAutoload = WEB_ROOT . '/vendor/autoload.php';
    $sdkLoaded = file_exists($vendorAutoload);
    if ($sdkLoaded) {
      require_once $vendorAutoload;
    }
  }

  try {
    if (!$sdkLoaded) {
      // If AWS SDK isn't installed, attempt SMTP fallback when configured.
      if (env('SMTP_HOST', '') !== '') {
        return send_email_via_smtp($to, $subject, $finalHtmlBody, $debugError);
      }
      $debugError = 'AWS SDK not installed; missing vendor/autoload.php';
      error_log('[email] ' . $debugError . '; skipping email to ' . $to);
      return false;
    }

    $fromEmail = env('SES_FROM_EMAIL', '');
    $fromName  = env('SES_FROM_NAME', 'CBOE Markets');
    if ($fromEmail === '') {
      $debugError = 'SES_FROM_EMAIL not configured';
      error_log('[email] ' . $debugError . '; skipping email to ' . $to);
      return false;
    }

    $client = new \Aws\SesV2\SesV2Client([
      'version'     => 'latest',
      'region'      => env('AWS_REGION', 'us-east-1'),
      'credentials' => [
        'key'    => env('AWS_ACCESS_KEY_ID', ''),
        'secret' => env('AWS_SECRET_ACCESS_KEY', ''),
      ],
    ]);

    $client->sendEmail([
      'FromEmailAddress' => sprintf('%s <%s>', $fromName, $fromEmail),
      'Destination'      => ['ToAddresses' => [$to]],
      'Content'          => [
        'Simple' => [
          'Subject' => ['Data' => $subject, 'Charset' => 'UTF-8'],
          'Body'    => [
            'Html' => ['Data' => $finalHtmlBody, 'Charset' => 'UTF-8'],
          ],
        ],
      ],
    ]);
    return true;
  } catch (Throwable $e) {
    $debugError = $e->getMessage();
    error_log('[email] Failed to send to ' . $to . ': ' . $e->getMessage());
    return false;
  }
}


/**
 * Minimal SMTP sender implemented using streams.
 * Supports plain, STARTTLS and SSL connections and AUTH LOGIN.
 */
function send_email_via_smtp(string $to, string $subject, string $htmlBody, ?string &$debugError = null): bool
{
  $debugError = null;
  $host = trim((string) env('SMTP_HOST', ''));
  $port = (int) env('SMTP_PORT', 587);
  $user = trim((string) env('SMTP_USER', ''));
  $pass = (string) env('SMTP_PASS', '');
  $fromEmail = trim((string) env('SMTP_FROM_EMAIL', ''));
  $fromName  = trim((string) env('SMTP_FROM_NAME', 'CBOE Markets'));
  $secure    = strtolower(trim((string) env('SMTP_SECURE', 'tls')));

  if ($host === '' || $fromEmail === '') {
    $debugError = 'SMTP_HOST or SMTP_FROM_EMAIL not configured';
    error_log('[email] ' . $debugError . '; skipping email to ' . $to);
    return false;
  }

  $timeout = 10;
  $remote = ($secure === 'ssl') ? 'ssl://' . $host : $host;

  // Hold server responses for debugging
  $serverLog = [];

  $fp = @stream_socket_client("{$remote}:{$port}", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
  if (!$fp) {
    $debugError = "Connection failed: {$errno} {$errstr}";
    $serverTrace = implode("\n", $serverLog);
    $msg = sprintf("[%s] SMTP connect failed to %s (%s): %s\n", date('c'), $to, $remote . ':' . $port, $debugError);
    if ($serverTrace !== '') { $msg .= "Server: " . $serverTrace . "\n"; $debugError .= "\nServer trace:\n" . $serverTrace; }
    email_debug_log($msg);
    return false;
  }

  $read = function () use ($fp) {
    $data = '';
    while (($line = fgets($fp)) !== false) {
      $data .= $line;
      // last line of response doesn't have '-' after code
      if (preg_match('/^[0-9]{3} /', $line)) {
        break;
      }
    }
    return $data;
  };

  $write = function ($cmd) use ($fp) {
    fwrite($fp, $cmd . "\r\n");
  };

  // Read greeting
  $greet = $read();
  $serverLog[] = trim($greet);

  $hostName = parse_url(env('APP_URL', 'localhost'), PHP_URL_HOST) ?: 'localhost';
  $write("EHLO {$hostName}");
  $resp = $read();
  $serverLog[] = trim($resp);

  // STARTTLS if requested
  if ($secure === 'tls') {
    // Only attempt STARTTLS if the server advertised it
    if (stripos($resp, 'STARTTLS') === false) {
      $debugError = 'SMTP server does not advertise STARTTLS';
      $serverTrace = implode("\n", $serverLog);
      $msg = sprintf("[%s] SMTP STARTTLS missing for %s: %s\n", date('c'), $to, $debugError);
      if ($serverTrace !== '') { $msg .= "Server: " . $serverTrace . "\n"; $debugError .= "\nServer trace:\n" . $serverTrace; }
      email_debug_log($msg);
      fclose($fp);
      return false;
    }

    $write('STARTTLS');
    $start = $read();
    $serverLog[] = trim($start);

    // Ensure the server accepted STARTTLS (220)
    if (!preg_match('/^220/', $start)) {
      $debugError = 'STARTTLS rejected: ' . trim($start);
      $serverTrace = implode("\n", $serverLog);
      $msg = sprintf("[%s] SMTP STARTTLS rejected for %s: %s\n", date('c'), $to, $debugError);
      if ($serverTrace !== '') { $msg .= "Server: " . $serverTrace . "\n"; $debugError .= "\nServer trace:\n" . $serverTrace; }
      email_debug_log($msg);
      fclose($fp);
      return false;
    }

    // enable crypto
    if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
      $debugError = 'Failed to enable TLS (crypto negotiation failed)';
      // write debug log
      $serverTrace = implode("\n", $serverLog);
      $msg = sprintf("[%s] SMTP TLS failed for %s: %s\n", date('c'), $to, $debugError);
      if ($serverTrace !== '') { $msg .= "Server: " . $serverTrace . "\n"; $debugError .= "\nServer trace:\n" . $serverTrace; }
      email_debug_log($msg);
      fclose($fp);
      return false;
    }
    // EHLO again after STARTTLS
    $write("EHLO {$hostName}");
    $resp = $read();
    $serverLog[] = trim($resp);
  }

  // AUTH LOGIN if user provided
  if ($user !== '') {
    $write('AUTH LOGIN');
    $resp = $read(); $serverLog[] = trim($resp);
    $write(base64_encode($user));
    $resp = $read(); $serverLog[] = trim($resp);
    $write(base64_encode($pass));
    $authResp = $read(); $serverLog[] = trim($authResp);
    if (!preg_match('/^235/', trim($authResp))) {
      $debugError = 'SMTP auth failed: ' . trim($authResp);
      // write debug log
      $serverTrace = implode("\n", $serverLog);
      $msg = sprintf("[%s] SMTP auth failed for %s (user=%s): %s\n", date('c'), $to, $user, $debugError);
      if ($serverTrace !== '') { $msg .= "Server: " . $serverTrace . "\n"; $debugError .= "\nServer trace:\n" . $serverTrace; }
      email_debug_log($msg);
      fclose($fp);
      return false;
    }
  }

  $write("MAIL FROM: <{$fromEmail}>");
  $resp = $read(); $serverLog[] = trim($resp);
  $write("RCPT TO: <{$to}>");
  $rcpt = $read(); $serverLog[] = trim($rcpt);
  if (!preg_match('/^250/', $rcpt) && !preg_match('/^251/', $rcpt)) {
    $debugError = 'SMTP RCPT failed: ' . trim($rcpt);
    $serverTrace = implode("\n", $serverLog);
    $msg = sprintf("[%s] SMTP RCPT failed for %s: %s\n", date('c'), $to, $debugError);
    if ($serverTrace !== '') { $msg .= "Server: " . $serverTrace . "\n"; $debugError .= "\nServer trace:\n" . $serverTrace; }
    email_debug_log($msg);
    fclose($fp);
    return false;
  }

  $write('DATA');
  $resp = $read(); $serverLog[] = trim($resp);

  $headers = [];
  $headers[] = 'Date: ' . date('r');
  $headers[] = 'From: ' . sprintf('%s <%s>', $fromName, $fromEmail);
  $headers[] = 'To: ' . $to;
  $headers[] = 'Subject: ' . $subject;
  $headers[] = 'MIME-Version: 1.0';
  $headers[] = 'Content-Type: text/html; charset=UTF-8';
  $headers[] = 'Content-Transfer-Encoding: 8bit';

  $data = implode("\r\n", $headers) . "\r\n\r\n" . $htmlBody . "\r\n.";
  $write($data);
  $dataResp = $read(); $serverLog[] = trim($dataResp);
  // Expect 250 on success
  if (!preg_match('/^250/', $dataResp)) {
    $debugError = 'SMTP DATA failed: ' . trim($dataResp);
    $serverTrace = implode("\n", $serverLog);
    $msg = sprintf("[%s] SMTP DATA failed for %s: %s\n", date('c'), $to, $debugError);
    if ($serverTrace !== '') { $msg .= "Server: " . $serverTrace . "\n"; $debugError .= "\nServer trace:\n" . $serverTrace; }
    email_debug_log($msg);
    fclose($fp);
    return false;
  }

  $write('QUIT');
  $read();
  fclose($fp);
  return true;
}

function send_password_reset_email(string $email, string $token, string $name): bool
{
    $appUrl   = env('APP_URL', 'http://CBOE Marketsbot.io');
    $resetUrl = $appUrl . '/reset_password.php?token=' . urlencode($token);
    $subject  = 'Reset Your Password – CBOE Markets';
    $html = <<<HTML
    <div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#0f172a;color:#fff;padding:32px;border-radius:8px;">
      <h2 style="color:#10b981;">CBOE Markets – Password Reset</h2>
      <p>Hi {$name},</p>
      <p>We received a request to reset your password. Click the button below to set a new password. This link expires in 1 hour.</p>
      <p style="text-align:center;margin:32px 0;">
        <a href="{$resetUrl}" style="background:#10b981;color:#fff;padding:14px 28px;border-radius:6px;text-decoration:none;font-weight:bold;">Reset Password</a>
      </p>
      <p>If you did not request a password reset, you can safely ignore this email.</p>
      <hr style="border-color:#334155;margin:24px 0;">
      <p style="font-size:12px;color:#64748b;">CBOE Markets Platform &middot; Automated Crypto Trading</p>
    </div>
    HTML;
    return send_email($email, $subject, $html);
}

function send_welcome_email(string $email, string $name): bool
{
    $appUrl  = env('APP_URL', 'http://CBOE Marketsbot.io');
    $dashUrl = $appUrl . '/app/index.php';
    $subject = 'Welcome to CBOE Markets!';
    $html = <<<HTML
    <div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#0f172a;color:#fff;padding:32px;border-radius:8px;">
      <h2 style="color:#10b981;">Welcome to CBOE Markets, {$name}!</h2>
      <p>Your account has been created successfully. You can now log in and start exploring automated crypto trading.</p>
      <p style="text-align:center;margin:32px 0;">
        <a href="{$dashUrl}" style="background:#10b981;color:#fff;padding:14px 28px;border-radius:6px;text-decoration:none;font-weight:bold;">Go to Dashboard</a>
      </p>
      <hr style="border-color:#334155;margin:24px 0;">
      <p style="font-size:12px;color:#64748b;">CBOE Markets Platform &middot; Automated Crypto Trading</p>
    </div>
    HTML;
    return send_email($email, $subject, $html);
}

function send_withdrawal_status_email(
    string $email,
    string $name,
    string $status,
    string $amount,
    string $asset,
    string $note
): bool {
    $statusLabel = ucfirst($status);
    $statusColor = $status === 'approved' ? '#10b981' : '#ef4444';
    $subject     = "Withdrawal Request {$statusLabel} – CBOE Markets";
    $noteHtml    = $note ? "<p><strong>Note:</strong> " . htmlspecialchars($note, ENT_QUOTES, 'UTF-8') . "</p>" : '';
    $html = <<<HTML
    <div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#0f172a;color:#fff;padding:32px;border-radius:8px;">
      <h2 style="color:{$statusColor};">Withdrawal {$statusLabel}</h2>
      <p>Hi {$name},</p>
      <p>Your withdrawal request for <strong>{$amount} {$asset}</strong> has been <strong style="color:{$statusColor};">{$statusLabel}</strong>.</p>
      {$noteHtml}
      <hr style="border-color:#334155;margin:24px 0;">
      <p style="font-size:12px;color:#64748b;">CBOE Markets Platform &middot; Automated Crypto Trading</p>
    </div>
    HTML;
    return send_email($email, $subject, $html);
}

function send_verification_email(string $email, string $name, string $code, string $token): bool
{
    $appUrl     = env('APP_URL', 'http://CBOE Marketsbot.io');
    $confirmUrl = $appUrl . '/verify_email.php?token=' . urlencode($token);
    $subject    = 'Welcome to CBOE Markets!';
    $html = <<<HTML
    <div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#0f172a;color:#fff;padding:32px;border-radius:8px;">
      <h2 style="color:#10b981;margin-bottom:8px;">Welcome to CBOE Markets!</h2>
      <p style="color:#cbd5e1;">Use the code below to confirm your CBOE Markets registration:</p>
      <p style="text-align:center;margin:28px 0;">
        <span style="display:inline-block;background:#111827;border:1px solid #334155;color:#10b981;padding:16px 36px;border-radius:10px;font-size:36px;font-weight:bold;letter-spacing:12px;">{$code}</span>
      </p>
      <p style="text-align:center;color:#94a3b8;margin-bottom:12px;">Or confirm automatically:</p>
      <p style="text-align:center;margin:0 0 8px;">
        <a href="{$confirmUrl}" style="background:#10b981;color:#fff;padding:13px 30px;border-radius:7px;text-decoration:none;font-weight:bold;display:inline-block;font-size:15px;">Confirm email</a>
      </p>
      <p style="font-size:12px;color:#64748b;text-align:center;margin-top:10px;">Use automatic confirmation in the same browser where you plan to open the CBOE Markets website</p>
      <hr style="border-color:#334155;margin:24px 0;">
      <p style="font-size:12px;color:#64748b;"><strong style="color:#94a3b8;">Note:</strong> Your confirmation code is valid for 30 minutes. Do not share it with anybody (including CBOE Markets team members) under any circumstances.</p>
    </div>
    HTML;
    return send_email($email, $subject, $html);
}

function send_login_notification_email(string $email, string $name, string $ip, string $userAgent, string $loginTime): bool
{
    $safeIp        = htmlspecialchars($ip,        ENT_QUOTES, 'UTF-8');
    $safeUa        = htmlspecialchars($userAgent, ENT_QUOTES, 'UTF-8');
    $safeTime      = htmlspecialchars($loginTime, ENT_QUOTES, 'UTF-8');
    $subject       = 'CBOE Markets Login Notification';
    $html = <<<HTML
    <div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#0f172a;color:#fff;padding:32px;border-radius:8px;">
      <h2 style="color:#10b981;">CBOE Markets Login</h2>
      <p>Dear {$name},</p>
      <p>This is to notify you of a successful login to your account.</p>
      <table style="width:100%;border-collapse:collapse;margin:20px 0;font-size:14px;">
        <tr style="border-bottom:1px solid #334155;">
          <td style="padding:10px 0;color:#94a3b8;width:130px;">Login Time</td>
          <td style="padding:10px 0;color:#f1f5f9;">{$safeTime} UTC</td>
        </tr>
        <tr style="border-bottom:1px solid #334155;">
          <td style="padding:10px 0;color:#94a3b8;">IP Address</td>
          <td style="padding:10px 0;color:#f1f5f9;">{$safeIp}</td>
        </tr>
        <tr>
          <td style="padding:10px 0;color:#94a3b8;vertical-align:top;">User Agent</td>
          <td style="padding:10px 0;color:#f1f5f9;word-break:break-all;">{$safeUa}</td>
        </tr>
      </table>
      <p style="font-size:14px;color:#cbd5e1;">If you were not the one to initiate this action or suspect there may be suspicious activity, please disable your account and contact our support at <a href="mailto:support@CBOE Marketsbot.io" style="color:#10b981;">support@CBOE Marketsbot.io</a> immediately. In this case your account may be blocked for security reasons, for 48 hours or more from the moment you contact our support team.</p>
      <hr style="border-color:#334155;margin:24px 0;">
      <p style="font-size:12px;color:#64748b;">CBOE Markets Platform &middot; Automated Crypto Trading</p>
    </div>
    HTML;
    return send_email($email, $subject, $html);
}

function send_security_otp_email(string $email, string $name, string $otpCode, string $actionLabel = 'security action', ?string &$debugError = null): bool
{
    $safeAction = htmlspecialchars($actionLabel, ENT_QUOTES, 'UTF-8');
    $subject = 'Your Security OTP Code - CBOE Markets';
    $html = <<<HTML
        <div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#0f172a;color:#fff;padding:32px;border-radius:8px;">
            <h2 style="color:#10b981;">Security Verification Code</h2>
            <p>Hi {$name},</p>
            <p>Use this one-time code to confirm your {$safeAction}:</p>
            <p style="text-align:center;margin:24px 0;">
                <span style="display:inline-block;background:#111827;border:1px solid #334155;color:#10b981;padding:12px 22px;border-radius:8px;font-size:28px;font-weight:bold;letter-spacing:6px;">{$otpCode}</span>
            </p>
            <p>This code expires in 10 minutes. If you did not initiate this request, secure your account immediately.</p>
            <hr style="border-color:#334155;margin:24px 0;">
            <p style="font-size:12px;color:#64748b;">CBOE Markets Platform - Automated Crypto Trading</p>
        </div>
        HTML;
    $debugError = null;
    $ok = send_email($email, $subject, $html, $debugError);
    if (!$ok) {
      $logDir = WEB_ROOT . '/logs';
      if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
      $msg = sprintf("[%s] OTP send failed to %s (action=%s): %s\n", date('c'), $email, $actionLabel, $debugError ?? 'no debug');
      error_log($msg, 3, $logDir . '/email_debug.log');
    }
    return $ok;
}
