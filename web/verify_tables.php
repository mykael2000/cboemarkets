<?php
echo "Checking database tables...\n";
require __DIR__ . '/src/config.php';
require __DIR__ . '/src/auth.php';
require __DIR__ . '/src/helpers.php';

$tables = db()->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "Tables: " . implode(', ', $tables) . "\n";

$hasProfitLogs = in_array('profit_logs', $tables);
echo "profit_logs table exists: " . ($hasProfitLogs ? 'YES' : 'NO') . "\n";

if ($hasProfitLogs) {
    $count = db()->query("SELECT COUNT(*) FROM profit_logs")->fetchColumn();
    echo "profit_logs entries: " . $count . "\n";
}
