-- Add live trading support + OTP verification table

CREATE TABLE IF NOT EXISTS live_trades (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       BIGINT UNSIGNED NOT NULL,
    symbol        VARCHAR(20)   NOT NULL,
    side          ENUM('buy','sell') NOT NULL,
    qty           DECIMAL(18,8) NOT NULL,
    price_open    DECIMAL(18,8) NOT NULL,
    price_close   DECIMAL(18,8),
    pnl           DECIMAL(18,8),
    margin_locked DECIMAL(18,8) NOT NULL DEFAULT 0.00000000,
    status        ENUM('open','closed') NOT NULL DEFAULT 'open',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closed_at     DATETIME,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_security_otps (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    BIGINT UNSIGNED NOT NULL,
    purpose    VARCHAR(50) NOT NULL,
    code_hash  VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used       TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_purpose (user_id, purpose),
    INDEX idx_expires (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
