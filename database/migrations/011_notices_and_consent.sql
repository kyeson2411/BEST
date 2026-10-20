-- 011: the privacy notice and the rules for using Sibol are acknowledged by version (on first sign-in and
-- whenever either changes); a student's AI can be switched on or off on its own; and a guardian's answer
-- about AI is kept as a dated yes or no with who entered it. No documents are stored.

-- NULL follows the school's setting for students; 1 or 0 is an adviser's or administrator's choice.
ALTER TABLE users
  ADD COLUMN ai_enabled TINYINT(1) NULL AFTER preferences;

CREATE TABLE notice_acknowledgments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  notice ENUM('privacy','rules') NOT NULL,
  version VARCHAR(40) NOT NULL,
  acknowledged_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_notice_ack (user_id, notice, version),
  KEY idx_notice_ack_org (organization_id, notice, version),
  CONSTRAINT fk_notice_ack_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
  CONSTRAINT fk_notice_ack_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE guardian_consents (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  topic VARCHAR(40) NOT NULL DEFAULT 'ai',
  answer ENUM('yes','no') NOT NULL,
  answered_on DATE NOT NULL,
  recorded_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_consents_student (student_id, topic, answered_on),
  CONSTRAINT fk_consents_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
  CONSTRAINT fk_consents_student FOREIGN KEY (student_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_consents_recorder FOREIGN KEY (recorded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
