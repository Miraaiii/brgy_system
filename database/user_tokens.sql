CREATE TABLE IF NOT EXISTS user_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    selector CHAR(24) NOT NULL,
    hashed_validator CHAR(64) NOT NULL,
    user_id INT NOT NULL,
    expiry DATETIME NOT NULL,
    KEY idx_selector (selector)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
