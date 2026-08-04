<?php
declare(strict_types=1);

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function flash(string $key, string $msg): void
{
    $_SESSION['flash'][$key] = $msg;
}

function get_flash(string $key): string
{
    $msg = $_SESSION['flash'][$key] ?? '';
    unset($_SESSION['flash'][$key]);
    return $msg;
}

function sanitize(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Fetch live price from Binance public API.
 * Falls back to mock prices if the request fails.
 */
function price_for_symbol(string $symbol): float
{
    static $cache = [];
    $symbol = strtoupper(trim($symbol));

    if (isset($cache[$symbol])) {
        return $cache[$symbol];
    }

    $mock = [
        'BTCUSDT'  => 65000.0,
        'ETHUSDT'  => 3200.0,
        'BNBUSDT'  => 580.0,
        'SOLUSDT'  => 155.0,
        'ADAUSDT'  => 0.45,
        'XRPUSDT'  => 0.52,
        'DOGEUSDT' => 0.12,
    ];

    try {
        $url = 'https://api.binance.com/api/v3/ticker/price?symbol=' . urlencode($symbol);
        $ctx = stream_context_create([
            'http' => ['timeout' => 3, 'ignore_errors' => true],
            'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $raw = file_get_contents($url, false, $ctx);
        if ($raw !== false) {
            $data = json_decode($raw, true);
            if (isset($data['price'])) {
                $price = (float) $data['price'];
                $cache[$symbol] = $price;
                return $price;
            }
        }
    } catch (Throwable) {
        // fall through to mock
    }

    $price = $mock[$symbol] ?? 1.0;
    $cache[$symbol] = $price;
    return $price;
}

function format_currency(float $n, int $decimals = 2): string
{
    return number_format($n, $decimals, '.', ',');
}

function ensure_trading_feature_tables(): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $pdo = db();

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS auto_trading_allocations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            plan_id BIGINT UNSIGNED NOT NULL,
            amount DECIMAL(18,8) NOT NULL,
            roi_percent DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
            duration_days INT NOT NULL DEFAULT 0,
            status ENUM("active","completed","cancelled") NOT NULL DEFAULT "active",
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            approved_at DATETIME NULL,
            INDEX idx_user_status (user_id, status),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (plan_id) REFERENCES investment_plans(id) ON DELETE CASCADE
        ) ENGINE=InnoDB'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS copy_traders (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            win_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            roi_percent DECIMAL(8,2) NOT NULL DEFAULT 0.00,
            followers_count INT NOT NULL DEFAULT 0,
            risk_type ENUM("Low","Medium","High") NOT NULL DEFAULT "Medium",
            description TEXT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_active (active)
        ) ENGINE=InnoDB'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS copy_trade_requests (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            copy_trader_id BIGINT UNSIGNED NOT NULL,
            amount DECIMAL(18,8) NOT NULL,
            status ENUM("pending","approved","rejected") NOT NULL DEFAULT "pending",
            admin_note TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            approved_at DATETIME NULL,
            INDEX idx_user_status (user_id, status),
            INDEX idx_trader_status (copy_trader_id, status),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (copy_trader_id) REFERENCES copy_traders(id) ON DELETE CASCADE
        ) ENGINE=InnoDB'
    );

    $ensured = true;
}
