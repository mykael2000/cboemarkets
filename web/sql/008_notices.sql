-- Notice board for admin-to-user announcements
CREATE TABLE IF NOT EXISTS notices (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subject    VARCHAR(255)  NOT NULL,
    message    TEXT          NOT NULL,
    is_active  TINYINT(1)    NOT NULL DEFAULT 1,
    created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
