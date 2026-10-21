-- ─── Migration 008: Add profit_logs table for tracking admin-added profits ───
CREATE TABLE IF NOT EXISTS profit_logs (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL,
    admin_id        BIGINT UNSIGNED NULL,
    amount          DECIMAL(18,8) NOT NULL,
    note            VARCHAR(255) NULL DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_created (user_id, created_at DESC),
    INDEX idx_admin_created (admin_id, created_at DESC),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
