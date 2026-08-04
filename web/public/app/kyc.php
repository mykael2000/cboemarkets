<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/csrf.php';
require_once __DIR__ . '/../../src/helpers.php';

require_login();
$user    = current_user();
$error   = get_flash('error');
$success = get_flash('success');

// Upload directory: uploads/kyc/ relative to web root (public_html)
$uploadDir = __DIR__ . '/../uploads/kyc/';

// ── Handle KYC document upload ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_kyc') {
    csrf_verify();

    // Fetch current KYC record
    $stmt = db()->prepare('SELECT * FROM kyc_submissions WHERE user_id = ? LIMIT 1');
    $stmt->execute([$user['id']]);
    $existing = $stmt->fetch() ?: null;

    // Only allow upload when unverified or rejected
    if ($existing && in_array($existing['status'], ['pending', 'verified'], true)) {
        flash('error', 'Your KYC is already ' . $existing['status'] . '. No further uploads needed.');
        redirect('kyc.php');
    }

    $allowed_exts  = ['jpg', 'jpeg', 'png', 'pdf'];
    $max_size      = 5 * 1024 * 1024; // 5 MB
    $fields        = ['id_doc' => 'id_doc_path', 'address_doc' => 'address_doc_path', 'selfie' => 'selfie_path'];
    $paths         = [];

    // Ensure upload directory and .htaccess protection exist before any file operations
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    if (!file_exists($uploadDir . '.htaccess')) {
        file_put_contents($uploadDir . '.htaccess', "Options -ExecCGI\nAddHandler cgi-script .php .pl .py .rb .sh\nRemoveHandler .php\nphp_flag engine off\n");
    }

    foreach ($fields as $field => $col) {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
            // Keep existing path if file not re-uploaded
            $paths[$col] = $existing[$col] ?? null;
            continue;
        }
        if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'Upload error for ' . $field . '. Please try again.');
            redirect('kyc.php');
        }
        if ($_FILES[$field]['size'] > $max_size) {
            flash('error', 'File ' . $field . ' exceeds the 5 MB size limit.');
            redirect('kyc.php');
        }
        $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_exts, true)) {
            flash('error', 'Invalid file type for ' . $field . '. Allowed: JPG, PNG, PDF.');
            redirect('kyc.php');
        }
        // Validate MIME type using finfo for all files
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($_FILES[$field]['tmp_name']);
        $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'];
        if (!in_array($mimeType, $allowedMimes, true)) {
            flash('error', 'File ' . $field . ' has an invalid content type. Allowed: JPG, PNG, PDF.');
            redirect('kyc.php');
        }
        // Additional image integrity check
        if (in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
            if (@getimagesize($_FILES[$field]['tmp_name']) === false) {
                flash('error', 'File ' . $field . ' does not appear to be a valid image.');
                redirect('kyc.php');
            }
        }
        $safeName = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($_FILES[$field]['tmp_name'], $uploadDir . $safeName)) {
            flash('error', 'Failed to save uploaded file. Please try again.');
            redirect('kyc.php');
        }
        // Remove old file if replacing
        if (!empty($existing[$col])) {
            $old = $uploadDir . basename($existing[$col]);
            if (file_exists($old)) {
                @unlink($old);
            }
        }
        $paths[$col] = 'uploads/kyc/' . $safeName;
    }

    // All three docs must be present
    if (empty($paths['id_doc_path']) || empty($paths['address_doc_path']) || empty($paths['selfie_path'])) {
        flash('error', 'All three documents (ID, proof of address, selfie) are required.');
        redirect('kyc.php');
    }

    try {
        if ($existing) {
            db()->prepare(
                'UPDATE kyc_submissions SET id_doc_path=?, address_doc_path=?, selfie_path=?,
                 status="pending", admin_note=NULL, submitted_at=NOW(), updated_at=NOW()
                 WHERE user_id=?'
            )->execute([$paths['id_doc_path'], $paths['address_doc_path'], $paths['selfie_path'], $user['id']]);
        } else {
            db()->prepare(
                'INSERT INTO kyc_submissions (user_id, id_doc_path, address_doc_path, selfie_path, status, submitted_at)
                 VALUES (?, ?, ?, ?, "pending", NOW())'
            )->execute([$user['id'], $paths['id_doc_path'], $paths['address_doc_path'], $paths['selfie_path']]);
        }
        flash('success', 'Documents submitted successfully! We will review your KYC within 1–3 business days.');
    } catch (Throwable) {
        flash('error', 'Failed to save your submission. Please try again.');
    }
    redirect('kyc.php');
}

// Fetch KYC record
$kyc = null;
try {
    $stmt = db()->prepare('SELECT * FROM kyc_submissions WHERE user_id = ? LIMIT 1');
    $stmt->execute([$user['id']]);
    $kyc = $stmt->fetch() ?: null;
} catch (Throwable) {}

$status = $kyc['status'] ?? 'unverified';
$canUpload = in_array($status, ['unverified', 'rejected'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/images/favicon.png">
  <title>KYC Verification – CBOE Markets</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen pb-24 md:pb-6">

  <header class="sticky top-0 z-40 bg-white/95 backdrop-blur border-b border-slate-200 px-4 py-3 flex items-center gap-3 md:hidden">
    <a href="profile.php" class="text-slate-500 hover:text-slate-700 transition">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    </a>
    <span class="text-lg font-extrabold text-emerald-400">KYC Verification</span>
  </header>

  <?php $activePage = 'profile.php'; include '_nav.php'; ?>

  <main class="max-w-xl mx-auto px-4 py-6 space-y-6">

    <?php if ($error): ?>
      <div class="bg-red-500/10 border border-red-500/30 text-red-600 text-sm rounded-lg px-4 py-3"><?= sanitize($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 text-sm rounded-lg px-4 py-3"><?= sanitize($success) ?></div>
    <?php endif; ?>

    <!-- Status Banner -->
    <?php
      $bannerClass = match($status) {
        'verified' => 'bg-emerald-50 border-emerald-200 text-emerald-800',
        'pending'  => 'bg-amber-50 border-amber-200 text-amber-800',
        'rejected' => 'bg-red-50 border-red-200 text-red-800',
        default    => 'bg-slate-100 border-slate-200 text-slate-700',
      };
      $bannerIcon = match($status) {
        'verified' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        'pending'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        'rejected' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        default    => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
      };
      $statusLabel = match($status) {
        'verified' => 'Verified – Your identity has been confirmed.',
        'pending'  => 'Under Review – We are reviewing your documents.',
        'rejected' => 'Rejected – Please re-submit with correct documents.',
        default    => 'Unverified – Please complete identity verification.',
      };
    ?>
    <div class="border rounded-2xl p-5 flex items-start gap-3 <?= $bannerClass ?>">
      <svg class="w-6 h-6 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><?= $bannerIcon ?></svg>
      <div>
        <p class="font-bold text-base capitalize"><?= sanitize(ucfirst($status)) ?></p>
        <p class="text-sm mt-0.5"><?= sanitize($statusLabel) ?></p>
        <?php if ($status === 'rejected' && !empty($kyc['admin_note'])): ?>
          <p class="text-sm mt-2 font-medium">Reason: <?= sanitize($kyc['admin_note']) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($canUpload): ?>
    <!-- Upload Form -->
    <div class="bg-white border border-slate-200 rounded-2xl p-6">
      <h3 class="font-bold text-slate-900 text-lg mb-1">Submit Documents</h3>
      <p class="text-slate-500 text-sm mb-5">Upload clear photos or PDFs (max 5 MB each). Accepted: JPG, PNG, PDF.</p>

      <form method="POST" action="kyc.php" enctype="multipart/form-data" class="space-y-5">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="upload_kyc">

        <!-- ID Document -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">
            <span class="inline-flex items-center gap-1.5">
              <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/></svg>
              Government-Issued ID
            </span>
          </label>
          <?php if (!empty($kyc['id_doc_path'])): ?>
            <p class="text-xs text-emerald-600 mb-1">✓ Previously uploaded. Upload a new file to replace.</p>
          <?php endif; ?>
          <input type="file" name="id_doc" accept=".jpg,.jpeg,.png,.pdf"
            class="block w-full text-sm text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 file:font-medium file:text-sm transition cursor-pointer">
          <p class="text-xs text-slate-400 mt-1">Passport, national ID or driver's licence (front page)</p>
        </div>

        <!-- Proof of Address -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">
            <span class="inline-flex items-center gap-1.5">
              <svg class="w-4 h-4 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
              Proof of Address
            </span>
          </label>
          <?php if (!empty($kyc['address_doc_path'])): ?>
            <p class="text-xs text-emerald-600 mb-1">✓ Previously uploaded. Upload a new file to replace.</p>
          <?php endif; ?>
          <input type="file" name="address_doc" accept=".jpg,.jpeg,.png,.pdf"
            class="block w-full text-sm text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-violet-50 file:text-violet-700 hover:file:bg-violet-100 file:font-medium file:text-sm transition cursor-pointer">
          <p class="text-xs text-slate-400 mt-1">Utility bill or bank statement (dated within 3 months)</p>
        </div>

        <!-- Selfie -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">
            <span class="inline-flex items-center gap-1.5">
              <svg class="w-4 h-4 text-pink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
              Selfie with ID
            </span>
          </label>
          <?php if (!empty($kyc['selfie_path'])): ?>
            <p class="text-xs text-emerald-600 mb-1">✓ Previously uploaded. Upload a new file to replace.</p>
          <?php endif; ?>
          <input type="file" name="selfie" accept=".jpg,.jpeg,.png"
            class="block w-full text-sm text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-pink-50 file:text-pink-700 hover:file:bg-pink-100 file:font-medium file:text-sm transition cursor-pointer">
          <p class="text-xs text-slate-400 mt-1">Photo of yourself holding your ID document (JPG/PNG only)</p>
        </div>

        <button type="submit"
          class="w-full bg-emerald-500 hover:bg-emerald-400 text-white font-bold py-3 rounded-xl transition">
          Submit for Review
        </button>
      </form>
    </div>

    <?php elseif ($status === 'pending'): ?>
    <div class="bg-white border border-slate-200 rounded-2xl p-6 text-center text-slate-500 text-sm">
      <p>Your documents are under review. We will notify you once the process is complete.</p>
      <p class="mt-2 text-xs">Submitted: <?= $kyc['submitted_at'] ? date('M j, Y H:i', strtotime($kyc['submitted_at'])) : '—' ?></p>
    </div>

    <?php elseif ($status === 'verified'): ?>
    <div class="bg-white border border-slate-200 rounded-2xl p-6 text-center">
      <div class="w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      </div>
      <p class="font-bold text-slate-900">Identity Verified</p>
      <p class="text-slate-500 text-sm mt-1">Your account has full access to all features.</p>
    </div>
    <?php endif; ?>

    <!-- Info box -->
    <div class="bg-blue-50 border border-blue-100 rounded-2xl p-5 text-sm text-blue-700">
      <p class="font-semibold mb-1">Why verify your identity?</p>
      <ul class="list-disc list-inside space-y-0.5 text-blue-600">
        <li>Unlock higher withdrawal limits</li>
        <li>Access VIP investment plans</li>
        <li>Required for bank withdrawals</li>
        <li>Platform security &amp; compliance</li>
      </ul>
    </div>

  </main>

</body>
</html>
