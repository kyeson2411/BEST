-- Two-step sign-in for staff (Phase 3, item 15). The app secret is sealed with APP_KEY (Core\AppKey), and
-- totp_last_step is the last 30-second step used to sign in, so a code cannot be used twice.
ALTER TABLE users
  ADD COLUMN totp_secret VARCHAR(255) NULL AFTER password_changed_at,
  ADD COLUMN totp_enabled_at DATETIME NULL AFTER totp_secret,
  ADD COLUMN totp_last_step BIGINT UNSIGNED NULL AFTER totp_enabled_at;

-- Single-use recovery codes, kept only as keyed hashes (HMAC-SHA256 with APP_KEY).
CREATE TABLE recovery_codes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  code_hash CHAR(64) NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_recovery_codes_hash (user_id, code_hash),
  CONSTRAINT fk_recovery_codes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
