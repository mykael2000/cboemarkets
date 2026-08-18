<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/csrf.php';
require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/email.php';

require_admin();

$error   = get_flash('error');
$success = get_flash('success');

// Fetch all users for the recipient dropdown
$allUsers = [];
try {
    $allUsers = db()->query('SELECT id, name, email FROM users ORDER BY name ASC')->fetchAll();
} catch (Throwable) {}

// â”€â”€ Handle POST â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$sendResults = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $subject     = trim($_POST['subject'] ?? '');
    $heading     = trim($_POST['heading'] ?? '');
    $body_text   = trim($_POST['body_text'] ?? '');
    $cta_label   = trim($_POST['cta_label'] ?? '');
    $cta_url     = trim($_POST['cta_url'] ?? '');
    $footer_note = trim($_POST['footer_note'] ?? '');
    $recipient   = $_POST['recipient'] ?? 'all';
    $custom_email = trim($_POST['custom_email'] ?? '');

    if ($subject === '' || $heading === '' || $body_text === '') {
        flash('error', 'Subject, heading, and body are required.');
        redirect('/admin/mailer');
    }

    // Build recipient list
    $recipients = []; // [ ['name'=>â€¦, 'email'=>â€¦], â€¦ ]
    if ($recipient === 'all') {
        foreach ($allUsers as $u) {
            $recipients[] = ['name' => $u['name'], 'email' => $u['email']];
        }
    } elseif ($recipient === 'custom') {
        if (filter_var($custom_email, FILTER_VALIDATE_EMAIL)) {
            $recipients[] = ['name' => 'Valued Member', 'email' => $custom_email];
        } else {
            flash('error', 'Invalid custom email address.');
            redirect('/admin/mailer');
        }
    } else {
        // Specific user ID
        $uid = (int)$recipient;
        foreach ($allUsers as $u) {
            if ((int)$u['id'] === $uid) {
                $recipients[] = ['name' => $u['name'], 'email' => $u['email']];
                break;
            }
        }
        if (empty($recipients)) {
            flash('error', 'Selected user not found.');
            redirect('/admin/mailer');
        }
    }

    // Send to each recipient
    $sent      = 0;
    $failed    = 0;
    $lastError = '';
    foreach ($recipients as $r) {
        $html = build_admin_email(
            heading: $heading,
            body_text: nl2br(htmlspecialchars($body_text, ENT_QUOTES, 'UTF-8')),
            cta_label: $cta_label,
            cta_url: $cta_url,
            footer_note: $footer_note,
            recipient_name: $r['name']
        );
        $debugError = null;
        $ok = send_email($r['email'], $subject, $html, $debugError);
        if ($ok) {
            $sent++;
            $sendResults[] = ['email' => $r['email'], 'status' => 'sent'];
        } else {
            $failed++;
            if ($debugError !== null && $lastError === '') {
                $lastError = $debugError;
            }
            $sendResults[] = ['email' => $r['email'], 'status' => 'failed'];
        }
    }

    if ($failed === 0) {
        flash('success', "All {$sent} email(s) sent successfully.");
    } else {
        $errMsg = "{$sent} sent, {$failed} failed.";
        if ($lastError !== '') {
            $errMsg .= ' Reason: ' . $lastError;
        }
        flash('error', $errMsg);
    }
    redirect('/admin/mailer');
}

/**
 * Build the professional HTML email body.
 */
function build_admin_email(
    string $heading,
    string $body_text,
    string $cta_label,
    string $cta_url,
    string $footer_note,
    string $recipient_name
): string {
    $appUrl   = rtrim((string) env('APP_URL', 'https://cboemarkets.com'), '/');
    $logoUrl  = $appUrl . '/images/favicon.png';
    $year     = date('Y');
    $cta_block = '';
    if ($cta_label !== '' && filter_var($cta_url, FILTER_VALIDATE_URL)) {
        $safe_url   = htmlspecialchars($cta_url, ENT_QUOTES, 'UTF-8');
        $safe_label = htmlspecialchars($cta_label, ENT_QUOTES, 'UTF-8');
        $cta_block  = <<<HTML
        <tr>
          <td align="center" style="padding:8px 32px 32px;">
            <a href="{$safe_url}"
               style="display:inline-block;background:linear-gradient(135deg,#10b981,#059669);color:#ffffff;text-decoration:none;font-family:Arial,sans-serif;font-size:15px;font-weight:700;padding:14px 36px;border-radius:8px;letter-spacing:0.3px;">
              {$safe_label}
            </a>
          </td>
        </tr>
        HTML;
    }

    $footer_block = '';
    if ($footer_note !== '') {
        $safe_footer = htmlspecialchars($footer_note, ENT_QUOTES, 'UTF-8');
        $footer_block = <<<HTML
        <tr>
          <td style="padding:0 32px 16px;">
            <p style="font-family:Arial,sans-serif;font-size:13px;color:#94a3b8;margin:0;line-height:1.6;border-top:1px solid #1e293b;padding-top:16px;">{$safe_footer}</p>
          </td>
        </tr>
        HTML;
    }

    $safe_heading = htmlspecialchars($heading, ENT_QUOTES, 'UTF-8');
    $safe_name    = htmlspecialchars($recipient_name, ENT_QUOTES, 'UTF-8');

    return <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width,initial-scale=1">
      <meta http-equiv="X-UA-Compatible" content="IE=edge">
      <title>{$safe_heading}</title>
    </head>
    <body style="margin:0;padding:0;background-color:#f1f5f9;">
      <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f1f5f9;padding:40px 16px;">
        <tr>
          <td align="center">
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;">

              <!-- Header brand bar -->
              <tr>
                <td style="background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%);border-radius:16px 16px 0 0;padding:28px 32px;text-align:center;">
                  <table align="center" cellpadding="0" cellspacing="0" border="0" style="margin:0 auto 6px;">
                    <tr>
                      <td style="vertical-align:middle;padding-right:10px;">
                        <img src="{$logoUrl}" alt="" width="36" height="36" style="display:block;border:0;border-radius:6px;width:36px;height:36px;">
                      </td>
                      <td style="vertical-align:middle;">
                        <span style="font-family:Arial,sans-serif;font-size:24px;font-weight:800;color:#10b981;letter-spacing:-0.5px;">Cboemarkets</span>
                      </td>
                    </tr>
                  </table>
                  <span style="font-family:Arial,sans-serif;font-size:12px;color:#64748b;display:block;margin-top:2px;letter-spacing:1px;text-transform:uppercase;">Automated Crypto Trading</span>
                </td>
              </tr>

              <!-- Accent line -->
              <tr>
                <td style="height:3px;background:linear-gradient(90deg,#10b981,#3b82f6,#8b5cf6);font-size:0;line-height:0;">&nbsp;</td>
              </tr>

              <!-- Main card -->
              <tr>
                <td style="background:#ffffff;padding:0;border-radius:0 0 16px 16px;box-shadow:0 4px 32px rgba(0,0,0,0.08);">
                  <table width="100%" cellpadding="0" cellspacing="0" border="0">

                    <!-- Greeting -->
                    <tr>
                      <td style="padding:36px 32px 4px;">
                        <p style="font-family:Arial,sans-serif;font-size:14px;color:#64748b;margin:0 0 4px;">Hello, {$safe_name}</p>
                        <h1 style="font-family:Arial,sans-serif;font-size:24px;font-weight:800;color:#0f172a;margin:0;line-height:1.3;">{$safe_heading}</h1>
                      </td>
                    </tr>

                    <!-- Divider -->
                    <tr>
                      <td style="padding:16px 32px 0;">
                        <div style="height:2px;background:linear-gradient(90deg,#10b981,transparent);border-radius:2px;"></div>
                      </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                      <td style="padding:24px 32px;">
                        <p style="font-family:Arial,sans-serif;font-size:15px;color:#334155;margin:0;line-height:1.8;">{$body_text}</p>
                      </td>
                    </tr>

                    <!-- CTA button -->
                    {$cta_block}

                    <!-- Footer note inside card -->
                    {$footer_block}

                  </table>
                </td>
              </tr>

              <!-- Bottom footer -->
              <tr>
                <td style="padding:24px 16px;text-align:center;">
                  <p style="font-family:Arial,sans-serif;font-size:12px;color:#94a3b8;margin:0 0 6px;">&copy; {$year} Cboemarkets &middot; Automated Crypto Trading Platform</p>
                  <p style="font-family:Arial,sans-serif;font-size:11px;color:#cbd5e1;margin:0;">
                    <a href="{$appUrl}/app/index" style="color:#10b981;text-decoration:none;">Dashboard</a>
                    &nbsp;&middot;&nbsp;
                    <a href="{$appUrl}/app/settings" style="color:#10b981;text-decoration:none;">Settings</a>
                    &nbsp;&middot;&nbsp;
                    <a href="{$appUrl}/app/contact_us" style="color:#10b981;text-decoration:none;">Support</a>
                  </p>
                </td>
              </tr>

            </table>
          </td>
        </tr>
      </table>
    </body>
    </html>
    HTML;
}

$activeAdminPage = 'mailer.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Email Users â€“ Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-800 text-white min-h-screen">
<div class="flex min-h-screen">
  <?php include __DIR__ . '/_sidebar.php'; ?>

  <main class="flex-1 p-4 sm:p-6 lg:p-8 pt-20 lg:pt-8">

    <div class="mb-6">
      <h1 class="text-2xl font-bold text-white">Email Users</h1>
      <p class="text-slate-400 text-sm mt-1">Compose and send a professionally styled email to one or all users.</p>
    </div>

    <?php if ($error): ?>
      <div class="bg-red-500/10 border border-red-500/30 text-red-400 text-sm rounded-xl px-4 py-3 mb-5"><?= sanitize($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm rounded-xl px-4 py-3 mb-5"><?= sanitize($success) ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 items-start">

      <!-- â”€â”€ Compose Form â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
      <div class="bg-slate-700 rounded-2xl p-6">
        <h2 class="text-base font-bold text-white mb-5 flex items-center gap-2">
          <span class="w-6 h-6 bg-emerald-500/20 rounded-lg flex items-center justify-center">
            <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
          </span>
          Compose Email
        </h2>

        <form method="POST" action="/admin/mailer" id="mailForm" class="space-y-4">
          <?= csrf_field() ?>

          <!-- Recipient -->
          <div>
            <label class="block text-sm font-medium text-slate-300 mb-1.5">Recipients</label>
            <select name="recipient" id="recipientSelect"
              class="w-full bg-slate-600 border border-slate-500 text-white rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500">
              <option value="all">All Users (<?= count($allUsers) ?>)</option>
              <option value="custom">Custom Email Address</option>
              <?php foreach ($allUsers as $u): ?>
                <option value="<?= (int)$u['id'] ?>"><?= sanitize($u['name']) ?> â€” <?= sanitize($u['email']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Custom email (shown only when 'custom' selected) -->
          <div id="customEmailRow" class="hidden">
            <label class="block text-sm font-medium text-slate-300 mb-1.5">Email Address</label>
            <input type="email" name="custom_email" placeholder="user@example.com"
              class="w-full bg-slate-600 border border-slate-500 text-white rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 placeholder-slate-400">
          </div>

          <!-- Subject -->
          <div>
            <label class="block text-sm font-medium text-slate-300 mb-1.5">Email Subject <span class="text-red-400">*</span></label>
            <input type="text" name="subject" id="f_subject" required maxlength="255"
              class="w-full bg-slate-600 border border-slate-500 text-white rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 placeholder-slate-400"
              placeholder="e.g. Important Account Update">
          </div>

          <!-- Heading (shown inside the email) -->
          <div>
            <label class="block text-sm font-medium text-slate-300 mb-1.5">Email Heading <span class="text-red-400">*</span></label>
            <input type="text" name="heading" id="f_heading" required maxlength="255"
              class="w-full bg-slate-600 border border-slate-500 text-white rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 placeholder-slate-400"
              placeholder="e.g. We've updated our terms">
          </div>

          <!-- Body -->
          <div>
            <label class="block text-sm font-medium text-slate-300 mb-1.5">Message Body <span class="text-red-400">*</span></label>
            <textarea name="body_text" id="f_body" required rows="6"
              class="w-full bg-slate-600 border border-slate-500 text-white rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 placeholder-slate-400 resize-none"
              placeholder="Write your message hereâ€¦ (line breaks are preserved)"></textarea>
          </div>

          <!-- CTA Button (optional) -->
          <div class="border border-slate-600 rounded-xl p-4 space-y-3">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Call-to-Action Button <span class="text-slate-500 font-normal normal-case">(optional)</span></p>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs text-slate-400 mb-1">Button Label</label>
                <input type="text" name="cta_label" id="f_cta_label" maxlength="80"
                  class="w-full bg-slate-600 border border-slate-500 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 placeholder-slate-400"
                  placeholder="e.g. View Dashboard">
              </div>
              <div>
                <label class="block text-xs text-slate-400 mb-1">Button URL</label>
                <input type="url" name="cta_url" id="f_cta_url"
                  class="w-full bg-slate-600 border border-slate-500 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 placeholder-slate-400"
                  placeholder="https://â€¦">
              </div>
            </div>
          </div>

          <!-- Footer note (optional) -->
          <div>
            <label class="block text-sm font-medium text-slate-300 mb-1.5">Footer Note <span class="text-slate-500 font-normal">(optional)</span></label>
            <input type="text" name="footer_note" id="f_footer" maxlength="400"
              class="w-full bg-slate-600 border border-slate-500 text-white rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 placeholder-slate-400"
              placeholder="e.g. If you have questions, contact support@cboemarkets.com">
          </div>

          <div class="flex items-center gap-3 pt-2">
            <button type="submit"
              class="flex-1 bg-emerald-500 hover:bg-emerald-400 text-white font-semibold py-2.5 rounded-xl transition text-sm">
              Send Email
            </button>
            <button type="button" onclick="updatePreview()"
              class="px-4 py-2.5 rounded-xl border border-slate-500 text-slate-300 hover:text-white hover:border-slate-400 transition text-sm">
              Preview
            </button>
          </div>
        </form>
      </div>

      <!-- â”€â”€ Live Preview â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
      <div class="bg-slate-700 rounded-2xl p-6 xl:sticky xl:top-8">
        <h2 class="text-base font-bold text-white mb-4 flex items-center gap-2">
          <span class="w-6 h-6 bg-blue-500/20 rounded-lg flex items-center justify-center">
            <svg class="w-3.5 h-3.5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
          </span>
          Email Preview
        </h2>

        <!-- Simulated email client chrome -->
        <div class="bg-slate-800 rounded-xl overflow-hidden border border-slate-600">
          <!-- Fake email header bar -->
          <div class="bg-slate-900 px-4 py-3 border-b border-slate-700 space-y-1">
            <div class="flex items-center gap-2">
              <span class="text-xs text-slate-500 w-10 flex-shrink-0">From:</span>
              <span class="text-xs text-slate-300">CBOE Markets &lt;noreply@cboemarkets.com&gt;</span>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-xs text-slate-500 w-10 flex-shrink-0">Subject:</span>
              <span id="previewSubject" class="text-xs text-white font-medium">â€”</span>
            </div>
          </div>
          <!-- Iframe preview -->
          <div class="bg-white" style="height:480px;overflow:auto;">
            <iframe id="previewFrame" style="width:100%;height:100%;border:none;" title="Email preview"></iframe>
          </div>
        </div>

        <p class="text-xs text-slate-500 mt-3 text-center">This is a visual approximation. Rendering may vary by email client.</p>
      </div>

    </div>
  </main>
</div>

<script>
  // Show/hide custom email input
  document.getElementById('recipientSelect').addEventListener('change', function() {
    document.getElementById('customEmailRow').classList.toggle('hidden', this.value !== 'custom');
  });

  // Build preview HTML to match the server-side template
  function updatePreview() {
    const heading    = document.getElementById('f_heading').value || '(Your heading here)';
    const body       = document.getElementById('f_body').value || '(Your message here)';
    const ctaLabel   = document.getElementById('f_cta_label').value;
    const ctaUrl     = document.getElementById('f_cta_url').value;
    const footerNote = document.getElementById('f_footer').value;
    const subject    = document.getElementById('f_subject').value || 'â€”';

    document.getElementById('previewSubject').textContent = subject;

    const esc = s => s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    const nl2br = s => esc(s).replace(/\n/g,'<br>');

    let ctaBlock = '';
    if (ctaLabel && ctaUrl) {
      ctaBlock = `<tr><td align="center" style="padding:8px 32px 32px;">
        <a href="${esc(ctaUrl)}" style="display:inline-block;background:linear-gradient(135deg,#10b981,#059669);color:#ffffff;text-decoration:none;font-family:Arial,sans-serif;font-size:15px;font-weight:700;padding:14px 36px;border-radius:8px;letter-spacing:0.3px;">${esc(ctaLabel)}</a>
      </td></tr>`;
    }

    let footerBlock = '';
    if (footerNote) {
      footerBlock = `<tr><td style="padding:0 32px 16px;">
        <p style="font-family:Arial,sans-serif;font-size:13px;color:#94a3b8;margin:0;line-height:1.6;border-top:1px solid #1e293b;padding-top:16px;">${esc(footerNote)}</p>
      </td></tr>`;
    }

    const year = new Date().getFullYear();
    const html = `<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
    <body style="margin:0;padding:0;background:#f1f5f9;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:40px 16px;">
      <tr><td align="center">
        <table width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;">
          <tr><td style="background:linear-gradient(135deg,#0f172a,#1e293b);border-radius:16px 16px 0 0;padding:28px 32px;text-align:center;">
            <span style="font-family:Arial,sans-serif;font-size:22px;font-weight:800;color:#10b981;letter-spacing:-0.5px;">Cboemarkets</span>
            <span style="font-family:Arial,sans-serif;font-size:12px;color:#64748b;display:block;margin-top:4px;letter-spacing:1px;text-transform:uppercase;">Automated Crypto Trading</span>
          </td></tr>
          <tr><td style="height:3px;background:linear-gradient(90deg,#10b981,#3b82f6,#8b5cf6);font-size:0;line-height:0;">&nbsp;</td></tr>
          <tr><td style="background:#fff;border-radius:0 0 16px 16px;box-shadow:0 4px 32px rgba(0,0,0,.08);">
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr><td style="padding:36px 32px 4px;">
                <p style="font-family:Arial,sans-serif;font-size:14px;color:#64748b;margin:0 0 4px;">Hello, Valued Member</p>
                <h1 style="font-family:Arial,sans-serif;font-size:24px;font-weight:800;color:#0f172a;margin:0;line-height:1.3;">${esc(heading)}</h1>
              </td></tr>
              <tr><td style="padding:16px 32px 0;"><div style="height:2px;background:linear-gradient(90deg,#10b981,transparent);border-radius:2px;"></div></td></tr>
              <tr><td style="padding:24px 32px;">
                <p style="font-family:Arial,sans-serif;font-size:15px;color:#334155;margin:0;line-height:1.8;">${nl2br(body)}</p>
              </td></tr>
              ${ctaBlock}
              ${footerBlock}
            </table>
          </td></tr>
          <tr><td style="padding:24px 16px;text-align:center;">
            <p style="font-family:Arial,sans-serif;font-size:12px;color:#94a3b8;margin:0 0 6px;">&copy; ${year} Cboemarkets &middot; Automated Crypto Trading Platform</p>
          </td></tr>
        </table>
      </td></tr>
    </table>
    </body></html>`;

    const frame = document.getElementById('previewFrame');
    frame.srcdoc = html;
  }

  // Auto-update preview on input
  ['f_subject','f_heading','f_body','f_cta_label','f_cta_url','f_footer'].forEach(id => {
    document.getElementById(id).addEventListener('input', updatePreview);
  });

  // Initial blank render
  updatePreview();
</script>
</body>
</html>
