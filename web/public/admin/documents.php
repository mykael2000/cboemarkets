<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/csrf.php';
require_once __DIR__ . '/../../src/helpers.php';

require_admin();

$error   = get_flash('error');
$success = get_flash('success');

$uploadDir = __DIR__ . '/../uploads/documents/';

// ── Upload new document ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload') {
    csrf_verify();
    $title       = trim($_POST['title']       ?? '');
    $description = trim($_POST['description'] ?? '');
    $visible     = isset($_POST['public_visible']) ? 1 : 0;

    if ($title === '') {
        flash('error', 'Title is required.');
        redirect('/admin/documents');
    }

    if (!isset($_FILES['document']) || $_FILES['document']['error'] === UPLOAD_ERR_NO_FILE) {
        flash('error', 'Please select a file to upload.');
        redirect('/admin/documents');
    }
    if ($_FILES['document']['error'] !== UPLOAD_ERR_OK) {
        flash('error', 'Upload error. Please try again.');
        redirect('/admin/documents');
    }

    $allowed_exts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'png', 'jpg', 'jpeg'];
    $max_size     = 20 * 1024 * 1024; // 20 MB
    $ext = strtolower(pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed_exts, true)) {
        flash('error', 'Invalid file type. Allowed: PDF, DOC, DOCX, XLS, XLSX, TXT, PNG, JPG.');
        redirect('/admin/documents');
    }
    if ($_FILES['document']['size'] > $max_size) {
        flash('error', 'File exceeds the 20 MB limit.');
        redirect('/admin/documents');
    }

    // Validate MIME type using finfo
    $allowedMimes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
        'image/png',
        'image/jpeg',
    ];
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($_FILES['document']['tmp_name']);
    if (!in_array($mimeType, $allowedMimes, true)) {
        flash('error', 'File content type is not allowed.');
        redirect('/admin/documents');
    }

    // Ensure upload directory and .htaccess protection exist before any file operations
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    if (!file_exists($uploadDir . '.htaccess')) {
        file_put_contents($uploadDir . '.htaccess', "Options -ExecCGI\nRemoveHandler .php\nphp_flag engine off\n");
    }

    $safeName     = bin2hex(random_bytes(16)) . '.' . $ext;
    $originalName = basename($_FILES['document']['name']);

    if (!move_uploaded_file($_FILES['document']['tmp_name'], $uploadDir . $safeName)) {
        flash('error', 'Failed to save file. Please try again.');
        redirect('/admin/documents');
    }

    try {
        db()->prepare(
            'INSERT INTO admin_documents (title, description, file_path, file_name, public_visible)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$title, $description ?: null, 'uploads/documents/' . $safeName, $originalName, $visible]);
        flash('success', 'Document uploaded successfully.');
    } catch (Throwable) {
        @unlink($uploadDir . $safeName);
        flash('error', 'Failed to save document metadata.');
    }
    redirect('/admin/documents');
}

// ── Toggle visibility ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    csrf_verify();
    $id = (int)($_POST['id'] ?? 0);
    try {
        db()->prepare('UPDATE admin_documents SET public_visible = 1 - public_visible WHERE id = ?')
             ->execute([$id]);
        flash('success', 'Visibility updated.');
    } catch (Throwable) {
        flash('error', 'Failed to update visibility.');
    }
    redirect('/admin/documents');
}

// ── Delete document ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_verify();
    $id = (int)($_POST['id'] ?? 0);
    try {
        $stmt = db()->prepare('SELECT file_path FROM admin_documents WHERE id = ?');
        $stmt->execute([$id]);
        $doc = $stmt->fetch();
        if ($doc) {
            $filePath = __DIR__ . '/../' . $doc['file_path'];
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
            db()->prepare('DELETE FROM admin_documents WHERE id = ?')->execute([$id]);
            flash('success', 'Document deleted.');
        }
    } catch (Throwable) {
        flash('error', 'Failed to delete document.');
    }
    redirect('/admin/documents');
}

$docs = [];
try {
    $docs = db()->query('SELECT * FROM admin_documents ORDER BY created_at DESC')->fetchAll();
} catch (Throwable) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/images/favicon.png">
  <title>Documents – CBOE Markets Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-800 text-white min-h-screen">
<div class="flex min-h-screen">
  <?php include __DIR__ . '/_sidebar.php'; ?>

  <main class="flex-1 bg-slate-800 p-4 sm:p-6 lg:p-8 pt-20 lg:pt-8">
    <h1 class="text-2xl font-bold text-white mb-6">Documents &amp; Reports</h1>

    <?php if ($error): ?>
      <div class="bg-red-500/10 border border-red-500/30 text-red-400 text-sm rounded-lg px-4 py-3 mb-4"><?= sanitize($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm rounded-lg px-4 py-3 mb-4"><?= sanitize($success) ?></div>
    <?php endif; ?>

    <div class="grid lg:grid-cols-3 gap-6">
      <!-- Upload Form -->
      <div class="bg-slate-700 rounded-2xl p-5">
        <h2 class="font-bold text-white mb-4">Upload Document</h2>
        <form method="POST" action="/admin/documents" enctype="multipart/form-data" class="space-y-3">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="upload">

          <div>
            <label class="block text-xs text-slate-400 mb-1">Title</label>
            <input type="text" name="title" required placeholder="e.g. Terms of Service"
              class="w-full bg-slate-600 border border-slate-500 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 placeholder-slate-400">
          </div>
          <div>
            <label class="block text-xs text-slate-400 mb-1">Description (optional)</label>
            <textarea name="description" rows="2" placeholder="Short description"
              class="w-full bg-slate-600 border border-slate-500 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 placeholder-slate-400 resize-none"></textarea>
          </div>
          <div>
            <label class="block text-xs text-slate-400 mb-1">File (max 20 MB)</label>
            <input type="file" name="document" required
              accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.png,.jpg,.jpeg"
              class="block w-full text-sm text-slate-400 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-slate-500 file:text-white hover:file:bg-slate-400 file:text-xs cursor-pointer">
          </div>
          <label class="flex items-center gap-2 text-sm text-slate-300 cursor-pointer">
            <input type="checkbox" name="public_visible" value="1" class="accent-emerald-500" checked>
            Visible to users
          </label>
          <button type="submit" class="w-full bg-emerald-500 hover:bg-emerald-400 text-white font-bold py-2.5 rounded-xl transition text-sm">
            Upload
          </button>
        </form>
      </div>

      <!-- Documents List -->
      <div class="lg:col-span-2 bg-slate-700 rounded-2xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-600">
          <h2 class="font-bold text-white">All Documents (<?= count($docs) ?>)</h2>
        </div>
        <?php if (empty($docs)): ?>
          <p class="text-slate-400 text-sm text-center py-8">No documents uploaded yet.</p>
        <?php else: ?>
        <div class="divide-y divide-slate-600">
          <?php foreach ($docs as $doc): ?>
          <div class="px-5 py-4 flex items-start gap-3 hover:bg-slate-600/30 transition">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <p class="font-semibold text-white text-sm"><?= sanitize($doc['title']) ?></p>
                <span class="text-xs px-2 py-0.5 rounded-full <?= $doc['public_visible'] ? 'bg-emerald-500/10 text-emerald-400' : 'bg-slate-600 text-slate-400' ?>">
                  <?= $doc['public_visible'] ? 'Public' : 'Hidden' ?>
                </span>
              </div>
              <?php if (!empty($doc['description'])): ?>
                <p class="text-xs text-slate-400 mt-0.5 truncate"><?= sanitize($doc['description']) ?></p>
              <?php endif; ?>
              <p class="text-xs text-slate-500 mt-1"><?= sanitize($doc['file_name']) ?> &bull; <?= date('M j, Y', strtotime($doc['created_at'])) ?></p>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
              <a href="/<?= sanitize($doc['file_path']) ?>" target="_blank" rel="noopener"
                class="text-xs text-emerald-400 hover:text-emerald-300 transition">View</a>
              <form method="POST" action="/admin/documents" class="inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= (int)$doc['id'] ?>">
                <button type="submit" class="text-xs text-amber-400 hover:text-amber-300 transition">
                  <?= $doc['public_visible'] ? 'Hide' : 'Show' ?>
                </button>
              </form>
              <form method="POST" action="/admin/documents" class="inline"
                onsubmit="return confirm('Delete this document?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$doc['id'] ?>">
                <button type="submit" class="text-xs text-red-400 hover:text-red-300 transition">Delete</button>
              </form>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>
</body>
</html>
