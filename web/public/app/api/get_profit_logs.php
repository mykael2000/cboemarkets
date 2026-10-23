<?php
declare(strict_types=1);

/**
 * AJAX endpoint: fetch recent profit additions for the logged-in user.
 * Called by the user dashboard via fetch() + setInterval (polling).
 * Returns JSON with profit_logs and current dashboard_today_pnl.
 */

require_once __DIR__ . '/../../../src/config.php';
require_once __DIR__ . '/../../../src/auth.php';
require_once __DIR__ . '/../../../src/helpers.php';

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

    // Fetch real-time prices for all coins (USDT is always $1)
    $symbols = ['BTCUSDT', 'ETHUSDT', 'BNBUSDT', 'SOLUSDT'];
    $prices  = [];
    foreach ($symbols as $sym) {
        $prices[str_replace('USDT', '', $sym)] = price_for_symbol($sym);
    }
    $prices['USDT'] = 1.0;

    // Fetch recent profit log entries for this user (last 20, newest first).
    $logsStmt = $pdo->prepare(
        'SELECT id, user_id, admin_id, type, amount, note, created_at
         FROM profit_logs
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT 20'
    );
    $logsStmt->execute([$user_id]);
    $logs = $logsStmt->fetchAll();

    // Fetch current dashboard metrics + coin balances
    $metricsStmt = $pdo->prepare(
        'SELECT dashboard_today_pnl, dashboard_equity, dashboard_margin, dashboard_free_margin,
                balance, btc_balance, eth_balance, bnb_balance, sol_balance
         FROM users WHERE id = ? LIMIT 1'
    );
    $metricsStmt->execute([$user_id]);
    $metrics = $metricsStmt->fetch();

    $todayPnl = (float)($metrics['dashboard_today_pnl'] ?? 0);
    $equity   = (float)($metrics['dashboard_equity'] ?? 0);
    $margin   = (float)($metrics['dashboard_margin'] ?? 0);
    $freeMargin = (float)($metrics['dashboard_free_margin'] ?? 0);
    $usdtBal  = (float)($metrics['balance'] ?? 0);
    $btcBal   = (float)($metrics['btc_balance'] ?? 0);
    $ethBal   = (float)($metrics['eth_balance'] ?? 0);
    $bnbBal   = (float)($metrics['bnb_balance'] ?? 0);
    $solBal   = (float)($metrics['sol_balance'] ?? 0);

    // Total balance = sum of all coin balances valued in USDT
    $totalBalance = $usdtBal * $prices['USDT']
                  + $btcBal  * $prices['BTC']
                  + $ethBal  * $prices['ETH']
                  + $bnbBal  * $prices['BNB']
                  + $solBal  * $prices['SOL'];

    $response = [
        'logs' => $logs,
        'today_pnl' => $todayPnl,
        'equity' => $equity,
        'margin' => $margin,
        'free_margin' => $freeMargin,
        'total_balance' => $totalBalance,
        'coin_balances' => [
            'USDT' => $usdtBal,
            'BTC'  => $btcBal,
            'ETH'  => $ethBal,
            'BNB'  => $bnbBal,
            'SOL'  => $solBal,
        ],
        'user_id' => $user_id,
    ];

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response);

} catch (Throwable $e) {
    // Don't leak DB errors to the client
    error_log('[get_profit_logs] ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
