-- 3Commas Web Platform Schema
-- MySQL 8.0+

-- CREATE DATABASE IF NOT EXISTS commas_web CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE commas_web;

CREATE TABLE IF NOT EXISTS users (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100)  NOT NULL,
    email         VARCHAR(255)  NOT NULL UNIQUE,
    password      VARCHAR(255)  NOT NULL,
    role          ENUM('user','admin') NOT NULL DEFAULT 'user',
    status        ENUM('active','disabled') NOT NULL DEFAULT 'active',
    balance       DECIMAL(18,8) NOT NULL DEFAULT 0.00000000,
    dashboard_today_pnl  DECIMAL(18,8) NOT NULL DEFAULT 0.00000000,
    dashboard_equity     DECIMAL(18,8) NOT NULL DEFAULT 0.00000000,
    dashboard_margin     DECIMAL(18,8) NOT NULL DEFAULT 0.00000000,
    dashboard_free_margin DECIMAL(18,8) NOT NULL DEFAULT 0.00000000,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role  (role),
    INDEX idx_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_resets (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(255) NOT NULL,
    token      VARCHAR(128) NOT NULL UNIQUE,
    expires_at DATETIME     NOT NULL,
    used       TINYINT(1)   NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS investment_plans (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100)   NOT NULL,
    description   TEXT,
    min_deposit   DECIMAL(18,8)  NOT NULL DEFAULT 0.00000000,
    max_deposit   DECIMAL(18,8)  NOT NULL DEFAULT 0.00000000,
    duration_days INT            NOT NULL DEFAULT 30,
    roi_percent   DECIMAL(8,4)   NOT NULL DEFAULT 0.0000,
    active        TINYINT(1)     NOT NULL DEFAULT 1,
    created_at    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_plans (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    BIGINT UNSIGNED NOT NULL,
    plan_id    BIGINT UNSIGNED NOT NULL,
    amount     DECIMAL(18,8)  NOT NULL,
    start_date DATE           NOT NULL,
    end_date   DATE           NOT NULL,
    status     ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active',
    created_at DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_plan (plan_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES investment_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS deposit_addresses (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asset_ticker VARCHAR(20)  NOT NULL,
    address      VARCHAR(255) NOT NULL,
    network      VARCHAR(50)  NOT NULL,
    active       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS deposit_requests (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      BIGINT UNSIGNED NOT NULL,
    asset_ticker VARCHAR(20)  NOT NULL,
    amount       DECIMAL(18,8) NOT NULL,
    txid         VARCHAR(255),
    address      VARCHAR(255),
    status       ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    admin_note   TEXT,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user   (user_id),
    INDEX idx_status (status),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS withdrawal_requests (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      BIGINT UNSIGNED NOT NULL,
    asset_ticker VARCHAR(20)  NOT NULL,
    amount       DECIMAL(18,8) NOT NULL,
    address      VARCHAR(255) NOT NULL,
    status       ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    admin_note   TEXT,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user   (user_id),
    INDEX idx_status (status),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS demo_trades (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT UNSIGNED NOT NULL,
    symbol      VARCHAR(20)   NOT NULL,
    side        ENUM('buy','sell') NOT NULL,
    qty         DECIMAL(18,8) NOT NULL,
    price_open  DECIMAL(18,8) NOT NULL,
    price_close DECIMAL(18,8),
    pnl         DECIMAL(18,8),
    status      ENUM('open','closed') NOT NULL DEFAULT 'open',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closed_at   DATETIME,
    INDEX idx_user   (user_id),
    INDEX idx_status (status),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

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

CREATE TABLE IF NOT EXISTS watchlist (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    BIGINT UNSIGNED NOT NULL,
    symbol     VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_symbol (user_id, symbol),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Seed default investment plans
INSERT INTO investment_plans (name, description, min_deposit, max_deposit, duration_days, roi_percent, active) VALUES
('Starter',    'Perfect for beginners. Low minimum deposit with steady returns.',  100.00,   999.99,  30, 5.00,  1),
('Growth',     'Balanced plan for growing your portfolio over 60 days.',           1000.00,  4999.99, 60, 12.00, 1),
('Pro Trader', 'High-yield plan for serious investors.',                           5000.00,  99999.99,90, 25.00, 1);

-- =============================================================================
-- WARNING: Seed deposit addresses below are PLACEHOLDERS for development only.
-- These MUST be replaced with your own real wallet addresses before production
-- deployment. Using placeholder addresses will result in LOST USER FUNDS.
-- =============================================================================
INSERT INTO deposit_addresses (asset_ticker, address, network, active) VALUES
('BTC',  'REPLACE_WITH_YOUR_BTC_ADDRESS', 'Bitcoin',   1),
('ETH',  'REPLACE_WITH_YOUR_ETH_ADDRESS', 'Ethereum',  1),
('USDT', 'REPLACE_WITH_YOUR_USDT_TRC20_ADDRESS', 'TRC20', 1);
