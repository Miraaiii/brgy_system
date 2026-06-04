-- ============================================================
-- Barangay Sta. Rosa 1 Management System
-- User Tokens Table (For Remember Me functionality)
-- ============================================================

USE barangay_bms;

CREATE TABLE IF NOT EXISTS user_tokens (
  id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  selector         CHAR(24)      NOT NULL,
  hashed_validator CHAR(64)      NOT NULL,
  user_id          INT UNSIGNED  NOT NULL,
  expiry           DATETIME      NOT NULL,
  PRIMARY KEY (id),
  KEY idx_selector (selector),
  CONSTRAINT fk_ut_user FOREIGN KEY (user_id)
    REFERENCES users (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
