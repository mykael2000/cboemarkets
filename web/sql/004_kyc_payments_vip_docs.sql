-- Migration: KYC, payment methods, VIP PnL updates, admin documents, user profile fields
-- Run after 003_add_bnb_sol_swaps.sql

-- ─── 1. Extended profile fields on users ───────────────────────────────────
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS phone   VARCHAR(30)  DEFAULT NULL AFTER email,
    ADD COLUMN IF NOT EXISTS country VARCHAR(100) DEFAULT NULL AFTER phone,
    ADD COLUMN IF NOT EXISTS address TEXT         DEFAULT NULL AFTER country;

-- ─── 2. KYC submissions ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS kyc_submissions (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id          BIGINT UNSIGNED NOT NULL UNIQUE,
    status           ENUM('unverified','pending','verified','rejected') NOT NULL DEFAULT 'unverified',
    id_doc_path      VARCHAR(500) DEFAULT NULL,
    address_doc_path VARCHAR(500) DEFAULT NULL,
    selfie_path      VARCHAR(500) DEFAULT NULL,
    admin_note       TEXT         DEFAULT NULL,
    submitted_at     DATETIME     DEFAULT NULL,
    reviewed_at      DATETIME     DEFAULT NULL,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user   (user_id),
    INDEX idx_status (status),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── 3. Payment methods ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS payment_methods (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    BIGINT UNSIGNED NOT NULL,
    type       ENUM('bank','crypto') NOT NULL,
    label      VARCHAR(100)  NOT NULL,
    details    TEXT          NOT NULL,
    is_default TINYINT(1)    NOT NULL DEFAULT 0,
    created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── 4. Currency column on existing user_plans ─────────────────────────────
ALTER TABLE user_plans
    ADD COLUMN IF NOT EXISTS currency VARCHAR(10) NOT NULL DEFAULT 'USDT' AFTER amount;

-- ─── 5. VIP PnL update log ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS vip_pnl_updates (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subscription_id BIGINT UNSIGNED NOT NULL,
    pnl_amount      DECIMAL(18,8)  NOT NULL,
    note            TEXT           DEFAULT NULL,
    created_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sub   (subscription_id),
    FOREIGN KEY (subscription_id) REFERENCES user_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── 6. Admin-uploaded documents ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS admin_documents (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title          VARCHAR(255)  NOT NULL,
    description    TEXT          DEFAULT NULL,
    file_path      VARCHAR(500)  NOT NULL,
    file_name      VARCHAR(255)  NOT NULL,
    public_visible TINYINT(1)    NOT NULL DEFAULT 1,
    created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
