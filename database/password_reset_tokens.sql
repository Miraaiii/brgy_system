-- ============================================================
-- Barangay Sta. Rosa 1 Management System
-- Password Reset Tokens Table
-- Run this in phpMyAdmin → barangay_bms database → SQL tab
-- ============================================================

USE barangay_bms;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
  id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED  NOT NULL,
  token_hash CHAR(64)      NOT NULL COMMENT 'User-scoped HMAC SHA-256 hash of the raw token',
  expires_at DATETIME      NOT NULL,
  used       TINYINT(1)    NOT NULL DEFAULT 0,
  attempts   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_prt_token  (token_hash),
  KEY idx_prt_user         (user_id),
  KEY idx_prt_expires      (expires_at),
  CONSTRAINT fk_prt_user FOREIGN KEY (user_id)
    REFERENCES users (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
