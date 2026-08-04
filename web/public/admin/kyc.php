<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/csrf.php';
require_once __DIR__ . '/../../src/helpers.php';

require_admin();

$error   = get_flash('error');
$success = get_flash('success');

// ── Approve / Reject KYC ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);
    $note   = trim($_POST['admin_note'] ?? '');

    if ($action === 'approve' && $id > 0) {
        try {
            db()->prepare(
                'UPDATE kyc_submissions SET status="verified", admin_note=NULL, reviewed_at=NOW() WHERE id=?'
            )->execute([$id]);
            flash('success', 'KYC approved.');
        } catch (Throwable) {
            flash('error', 'Failed to approve KYC.');
        }
    } elseif ($action === 'reject' && $id > 0) {
        if ($note === '') {
            flash('error', 'A rejection reason is required.');
            redirect('/admin/kyc');
        }
        try {
            db()->prepare(
                'UPDATE kyc_submissions SET status="rejected", admin_note=?, reviewed_at=NOW() WHERE id=?'
            )->execute([$note, $id]);
            flash('success', 'KYC rejected.');
        } catch (Throwable) {
            flash('error', 'Failed to reject KYC.');
        }
    }
    redirect('/admin/kyc');
}

// Filter
$filterStatus = $_GET['status'] ?? 'pending';
$allowed = ['pending', 'verified', 'rejected', 'unverified', 'all'];
if (!in_array($filterStatus, $allowed, true)) $filterStatus = 'pending';

$submissions = [];
try {
    if ($filterStatus === 'all') {
        $submissions = db()->query(
            'SELECT k.*, u.name AS user_name, u.email AS user_email
             FROM kyc_submissions k JOIN users u ON u.id = k.user_id
             ORDER BY k.submitted_at DESC'
        )->fetchAll();
    } else {
        $stmt = db()->prepare(
            'SELECT k.*, u.name AS user_name, u.email AS user_email
             FROM kyc_submissions k JOIN users u ON u.id = k.user_id
             WHERE k.status = ?
             ORDER BY k.submitted_at DESC'
        );
        $stmt->execute([$filterStatus]);
        $submissions = $stmt->fetchAll();
    }
} catch (Throwable) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/images/favicon.png">
  <title>KYC Review – CBOE Markets Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-800 text-white min-h-screen">
<div class="flex min-h-screen">
  <?php include __DIR__ . '/_sidebar.php'; ?>

  <main class="flex-1 bg-slate-800 p-4 sm:p-6 lg:p-8 pt-20 lg:pt-8">
    <h1 class="text-2xl font-bold text-white mb-2">KYC Review</h1>

    <?php if ($error): ?>
      <div class="bg-red-500/10 border border-red-500/30 text-red-400 text-sm rounded-lg px-4 py-3 mb-4"><?= sanitize($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm rounded-lg px-4 py-3 mb-4"><?= sanitize($success) ?></div>
    <?php endif; ?>

    <!-- Status filter -->
    <div class="flex gap-2 flex-wrap mb-6">
      <?php foreach (['pending','verified','rejected','all'] as $s): ?>
      <a href="/admin/kyc.php?status=<?= $s ?>"
        class="px-3 py-1.5 rounded-lg text-sm font-medium transition <?= $filterStatus === $s ? 'bg-emerald-500 text-white' : 'bg-slate-700 text-slate-300 hover:bg-slate-600' ?>">
        <?= ucfirst($s) ?>
      </a>
      <?php endforeach; ?>
    </div>

    <?php if (empty($submissions)): ?>
      <div class="bg-slate-700 rounded-2xl p-8 text-center text-slate-400">No <?= sanitize($filterStatus) ?> submissions found.</div>
    <?php else: ?>
    <div class="space-y-4">
      <?php foreach ($submissions as $sub): ?>
      <?php
        $statusColor = match($sub['status']) {
          'verified' => 'text-emerald-400 bg-emerald-500/10',
          'pending'  => 'text-amber-400 bg-amber-500/10',
          'rejected' => 'text-red-400 bg-red-500/10',
          default    => 'text-slate-400 bg-slate-600',
        };
      ?>
      <div class="bg-slate-700 rounded-2xl p-5">
        <div class="flex items-start justify-between gap-3 mb-4">
          <div>
            <p class="font-bold text-white"><?= sanitize($sub['user_name']) ?></p>
            <p class="text-sm text-slate-400"><?= sanitize($sub['user_email']) ?></p>
            <div class="flex items-center gap-2 mt-1.5">
              <span class="text-xs px-2 py-0.5 rounded-full font-medium <?= $statusColor ?>">
                <?= ucfirst($sub['status']) ?>
              </span>
              <?php if ($sub['submitted_at']): ?>
              <span class="text-xs text-slate-500">Submitted: <?= date('M j, Y H:i', strtotime($sub['submitted_at'])) ?></span>
              <?php endif; ?>
            </div>
            <?php if (!empty($sub['admin_note'])): ?>
            <p class="text-xs text-red-400 mt-1">Rejection note: <?= sanitize($sub['admin_note']) ?></p>
            <?php endif; ?>
          </div>
        </div>

        <!-- Document previews -->
        <div class="grid grid-cols-3 gap-3 mb-4">
          <?php
            $docLabels = ['id_doc_path' => 'ID Document', 'address_doc_path' => 'Proof of Address', 'selfie_path' => 'Selfie'];
            foreach ($docLabels as $field => $label):
              $path = $sub[$field] ?? '';
          ?>
          <div class="bg-slate-600 rounded-xl p-3 text-center">
            <p class="text-xs text-slate-400 mb-2"><?= $label ?></p>
            <?php if (!empty($path)): ?>
              <?php $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION)); ?>
              <?php if (in_array($ext, ['jpg','jpeg','png'], true)): ?>
              <a href="/<?= sanitize($path) ?>" target="_blank" rel="noopener">
                <img src="/<?= sanitize($path) ?>" alt="<?= $label ?>"
                  class="w-full h-24 object-cover rounded-lg hover:opacity-80 transition cursor-pointer">
              </a>
              <?php else: ?>
              <a href="/<?= sanitize($path) ?>" target="_blank" rel="noopener"
                class="text-xs text-emerald-400 hover:text-emerald-300 underline">View PDF</a>
              <?php endif; ?>
            <?php else: ?>
              <p class="text-xs text-slate-500 italic">Not uploaded</p>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Actions -->
        <?php if ($sub['status'] === 'pending'): ?>
        <div class="flex flex-wrap gap-3">
          <form method="POST" action="/admin/kyc" onsubmit="return confirm('Approve this KYC?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="id" value="<?= (int)$sub['id'] ?>">
            <button type="submit" class="bg-emerald-500/20 text-emerald-400 hover:bg-emerald-500/30 text-sm font-medium px-4 py-2 rounded-lg transition">
              ✓ Approve
            </button>
          </form>

          <form method="POST" action="/admin/kyc" class="flex items-center gap-2"
            onsubmit="return confirm('Reject this KYC?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="id" value="<?= (int)$sub['id'] ?>">
            <input type="text" name="admin_note" required placeholder="Reason for rejection"
              class="bg-slate-600 border border-slate-500 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-red-500 placeholder-slate-400 w-48">
            <button type="submit" class="bg-red-500/20 text-red-400 hover:bg-red-500/30 text-sm font-medium px-4 py-2 rounded-lg transition">
              ✗ Reject
            </button>
          </form>
        </div>
        <?php elseif ($sub['status'] === 'rejected'): ?>
        <form method="POST" action="/admin/kyc" onsubmit="return confirm('Re-approve this KYC?')">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="approve">
          <input type="hidden" name="id" value="<?= (int)$sub['id'] ?>">
          <button type="submit" class="bg-emerald-500/20 text-emerald-400 hover:bg-emerald-500/30 text-sm font-medium px-4 py-2 rounded-lg transition">
            ✓ Approve Anyway
          </button>
        </form>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </main>
</div>
</body>
</html>
