-- Run once on an existing HOTELGO database (adds the table the API needs for sign-in tokens).
CREATE TABLE IF NOT EXISTS refresh_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  jti VARCHAR(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_refresh_token ON refresh_tokens (token);
CREATE INDEX idx_refresh_user ON refresh_tokens (user_id);
