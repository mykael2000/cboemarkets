<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/csrf.php';
require_once __DIR__ . '/../../src/helpers.php';

require_admin();

// Auto-create table if not exists
try {
    db()->exec("CREATE TABLE IF NOT EXISTS notices (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        subject    VARCHAR(255)  NOT NULL,
        message    TEXT          NOT NULL,
        is_active  TINYINT(1)    NOT NULL DEFAULT 1,
        created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable) {}

$error   = get_flash('error');
$success = get_flash('success');

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        if ($subject === '' || $message === '') {
            flash('error', 'Subject and message are required.');
        } else {
            try {
                db()->prepare('INSERT INTO notices (subject, message) VALUES (?, ?)')
                     ->execute([$subject, $message]);
                flash('success', 'Notice created successfully.');
            } catch (Throwable) {
                flash('error', 'Failed to create notice.');
            }
        }
        redirect('/admin/notices');
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            db()->prepare('UPDATE notices SET is_active = NOT is_active WHERE id = ?')
                 ->execute([$id]);
            flash('success', 'Notice updated.');
        } catch (Throwable) {
            flash('error', 'Failed to update notice.');
        }
        redirect('/admin/notices');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            db()->prepare('DELETE FROM notices WHERE id = ?')->execute([$id]);
            flash('success', 'Notice deleted.');
        } catch (Throwable) {
            flash('error', 'Failed to delete notice.');
        }
        redirect('/admin/notices');
    }
}

// Fetch all notices
$notices = [];
try {
    $notices = db()->query('SELECT * FROM notices ORDER BY created_at DESC')->fetchAll();
} catch (Throwable) {}

$activeAdminPage = 'notices.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/images/favicon.png">
  <title>Notices – Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-800 text-white min-h-screen">
<div class="flex min-h-screen">
  <?php include __DIR__ . '/_sidebar.php'; ?>

  <main class="flex-1 p-4 sm:p-6 lg:p-8 pt-20 lg:pt-8">
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-white">Notices</h1>
      <p class="text-slate-400 text-sm mt-1">Create announcements shown as a modal on the user dashboard.</p>
    </div>

    <?php if ($error): ?>
      <div class="bg-red-500/10 border border-red-500/30 text-red-400 text-sm rounded-xl px-4 py-3 mb-5"><?= sanitize($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm rounded-xl px-4 py-3 mb-5"><?= sanitize($success) ?></div>
    <?php endif; ?>

    <!-- Create Notice Form -->
    <div class="bg-slate-700 rounded-2xl p-6 mb-8 max-w-2xl">
      <h2 class="text-base font-bold text-white mb-4">New Notice</h2>
      <form method="POST" action="/admin/notices" class="space-y-4">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <div>
          <label class="block text-sm font-medium text-slate-300 mb-1.5">Subject</label>
          <input type="text" name="subject" required maxlength="255"
            class="w-full bg-slate-600 border border-slate-500 text-white rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 placeholder-slate-400"
            placeholder="e.g. Platform Maintenance">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-300 mb-1.5">Message</label>
          <textarea name="message" required rows="4"
            class="w-full bg-slate-600 border border-slate-500 text-white rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 placeholder-slate-400 resize-none"
            placeholder="Write the notice message for users…"></textarea>
        </div>
        <button type="submit"
          class="bg-emerald-500 hover:bg-emerald-400 text-white font-semibold px-6 py-2.5 rounded-xl transition">
          Post Notice
        </button>
      </form>
    </div>

    <!-- Notices List -->
    <?php if (empty($notices)): ?>
      <p class="text-slate-400 text-sm">No notices yet.</p>
    <?php else: ?>
    <div class="space-y-3 max-w-2xl">
      <?php foreach ($notices as $n): ?>
      <div class="bg-slate-700 rounded-2xl p-5 flex flex-col gap-3">
        <div class="flex items-start justify-between gap-4">
          <div class="min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="font-bold text-white text-sm"><?= sanitize($n['subject']) ?></span>
              <?php if ($n['is_active']): ?>
                <span class="text-xs bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 px-2 py-0.5 rounded-full">Active</span>
              <?php else: ?>
                <span class="text-xs bg-slate-600 text-slate-400 border border-slate-500 px-2 py-0.5 rounded-full">Inactive</span>
              <?php endif; ?>
            </div>
            <p class="text-slate-300 text-sm mt-1 whitespace-pre-line"><?= sanitize($n['message']) ?></p>
            <p class="text-slate-500 text-xs mt-2"><?= date('M j, Y g:i A', strtotime($n['created_at'])) ?></p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <!-- Toggle active/inactive -->
          <form method="POST" action="/admin/notices">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
            <button type="submit"
              class="text-xs px-3 py-1.5 rounded-lg font-medium transition border
                <?= $n['is_active']
                    ? 'border-slate-500 text-slate-300 hover:bg-slate-600'
                    : 'border-emerald-500/40 text-emerald-400 hover:bg-emerald-500/10' ?>">
              <?= $n['is_active'] ? 'Deactivate' : 'Activate' ?>
            </button>
          </form>
          <!-- Delete -->
          <form method="POST" action="/admin/notices"
            onsubmit="return confirm('Delete this notice?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
            <button type="submit"
              class="text-xs px-3 py-1.5 rounded-lg font-medium border border-red-500/30 text-red-400 hover:bg-red-500/10 transition">
              Delete
            </button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </main>
</div>
</body>
</html>
