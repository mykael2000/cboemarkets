-- Add auto trading allocations + copy trading marketplace tables

CREATE TABLE IF NOT EXISTS auto_trading_allocations (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       BIGINT UNSIGNED NOT NULL,
    plan_id       BIGINT UNSIGNED NOT NULL,
    amount        DECIMAL(18,8) NOT NULL,
    roi_percent   DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
    duration_days INT NOT NULL DEFAULT 0,
    status        ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_at   DATETIME NULL,
    INDEX idx_user_status (user_id, status),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES investment_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS copy_traders (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(120) NOT NULL,
    win_rate        DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    roi_percent     DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    followers_count INT NOT NULL DEFAULT 0,
    risk_type       ENUM('Low','Medium','High') NOT NULL DEFAULT 'Medium',
    description     TEXT NULL,
    active          TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (active)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS copy_trade_requests (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id        BIGINT UNSIGNED NOT NULL,
    copy_trader_id BIGINT UNSIGNED NOT NULL,
    amount         DECIMAL(18,8) NOT NULL,
    status         ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    admin_note     TEXT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_at    DATETIME NULL,
    INDEX idx_user_status (user_id, status),
    INDEX idx_trader_status (copy_trader_id, status),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (copy_trader_id) REFERENCES copy_traders(id) ON DELETE CASCADE
) ENGINE=InnoDB;