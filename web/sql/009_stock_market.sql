-- Stock market and user portfolio tables

CREATE TABLE IF NOT EXISTS market_stocks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    symbol VARCHAR(20) NOT NULL UNIQUE,
    company VARCHAR(120) NOT NULL,
    sector VARCHAR(80) NOT NULL DEFAULT 'Other',
    price DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    change_pct DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_symbol (symbol),
    INDEX idx_active (active)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS stock_positions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    symbol VARCHAR(20) NOT NULL,
    company VARCHAR(120) NOT NULL,
    quantity DECIMAL(18,8) NOT NULL DEFAULT 0.00000000,
    avg_cost DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_symbol (user_id, symbol),
    INDEX idx_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Optional seed data for the default market watch list.
INSERT INTO market_stocks (symbol, company, sector, price, change_pct, active)
SELECT symbol, company, sector, price, change_pct, active
FROM (
    SELECT 'AAPL' AS symbol, 'Apple' AS company, 'Technology' AS sector, 192.84 AS price, 1.20 AS change_pct, 1 AS active
    UNION ALL
    SELECT 'MSFT', 'Microsoft', 'Technology', 416.23, 0.85, 1
    UNION ALL
    SELECT 'NVDA', 'NVIDIA', 'Semiconductors', 121.47, 2.70, 1
    UNION ALL
    SELECT 'AMZN', 'Amazon', 'E-Commerce', 184.36, 1.10, 1
    UNION ALL
    SELECT 'GOOGL', 'Alphabet', 'Technology', 177.91, 0.65, 1
    UNION ALL
    SELECT 'TSLA', 'Tesla', 'Automotive', 244.59, -1.30, 1
) AS seed
WHERE NOT EXISTS (
    SELECT 1 FROM market_stocks WHERE market_stocks.symbol = seed.symbol
);
