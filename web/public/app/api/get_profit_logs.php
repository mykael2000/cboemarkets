<?php
declare(strict_types=1);

/**
 * AJAX endpoint: fetch recent profit additions for the logged-in user.
 * Called by the user dashboard via fetch() + setInterval (polling).
 * Returns JSON with profit_logs and current dashboard_today_pnl.
 */

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/helpers.php';

require_login();

$currentUser = current_user();
if (!$currentUser) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = (int)$currentUser['id'];

try {
    $pdo = db();

    // Fetch recent profit log entries for this user (last 20, newest first).
    $logsStmt = $pdo->prepare(
        'SELECT id, user_id, admin_id, amount, note, created_at
         FROM profit_logs
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT 20'
    );
    $logsStmt->execute([$user_id]);
    $logs = $logsStmt->fetchAll();

    // Reverse so newest is at the bottom (or keep newest first? We'll sort newest first in JS)
    // Actually, we want to show newest first in the UI. Keep as is (DESC) and reverse in JS if needed.

    // Fetch current dashboard metrics
    $metricsStmt = $pdo->prepare(
        'SELECT dashboard_today_pnl, dashboard_equity, dashboard_margin, dashboard_free_margin
         FROM users WHERE id = ? LIMIT 1'
    );
    $metricsStmt->execute([$user_id]);
    $metrics = $metricsStmt->fetch();

    $response = [
        'logs' => $logs,
        'today_pnl' => (float)($metrics['dashboard_today_pnl'] ?? 0),
        'equity' => (float)($metrics['dashboard_equity'] ?? 0),
        'margin' => (float)($metrics['dashboard_margin'] ?? 0),
        'free_margin' => (float)($metrics['dashboard_free_margin'] ?? 0),
        'user_id' => $user_id,
    ];

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response);

} catch (Throwable $e) {
    // Don't leak DB errors to the client
    error_log('[get_profit_logs] ' . $e->getMessage());
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
