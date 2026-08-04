-- Migration: add BNB and SOL balance columns and swaps table
-- Run this against your existing database to enable all 5-coin balances and swap history.

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS bnb_balance DECIMAL(18,8) NOT NULL DEFAULT 0.00000000,
    ADD COLUMN IF NOT EXISTS sol_balance DECIMAL(18,8) NOT NULL DEFAULT 0.00000000;

CREATE TABLE IF NOT EXISTS swaps (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      BIGINT UNSIGNED NOT NULL,
    from_coin    VARCHAR(10)   NOT NULL,
    to_coin      VARCHAR(10)   NOT NULL,
    from_amount  DECIMAL(18,8) NOT NULL,
    to_amount    DECIMAL(18,8) NOT NULL,
    rate_used    DECIMAL(18,8) NOT NULL COMMENT 'Exchange rate: from_coin_usd_price / to_coin_usd_price (units of to_coin per 1 unit of from_coin at swap time)',
    created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
