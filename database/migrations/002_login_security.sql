-- Login security (BUILD_SPEC 13.3): stay signed in for up to 30 days, two-step sign-in with an
-- authenticator app, and members added directly by a school administrator with a temporary password.

ALTER TABLE users
  ADD COLUMN totp_secret VARCHAR(255) NULL AFTER password_hash,          -- encrypted with the server key (App\Core\Crypto)
  ADD COLUMN totp_enabled_at DATETIME NULL AFTER totp_secret,           -- two-step sign-in is on when set
  ADD COLUMN totp_last_step BIGINT NULL AFTER totp_enabled_at,          -- last 30-second step used; a code works only once
  ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER totp_last_step;  -- set for members added with a temporary password

CREATE TABLE remember_tokens (                -- "Keep me signed in": one row per browser
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  selector CHAR(24) NOT NULL UNIQUE,          -- looked up directly; not secret
  validator_hash CHAR(64) NOT NULL,           -- sha256 of the secret half of the cookie
  previous_hash CHAR(64) NULL,                -- the hash before the last rotation, accepted for 2 minutes (parallel requests)
  rotated_at DATETIME NULL,
  expires_at DATETIME NOT NULL,               -- fixed at sign-in: never extended
  user_agent VARCHAR(200) NOT NULL DEFAULT '',
  ip VARCHAR(45) NOT NULL DEFAULT '',
  last_used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_rt_user (user_id),
  CONSTRAINT fk_rt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE recovery_codes (                 -- one-time codes for two-step sign-in when the phone is lost
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  code_hash CHAR(64) NOT NULL,                -- sha256 of the code; the code is shown once
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_rc_user (user_id),
  CONSTRAINT fk_rc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
