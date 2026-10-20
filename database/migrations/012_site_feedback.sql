-- 012: feedback about Sibol from students and teachers to the school's administrators: a category, a short
-- text and the page it was sent from (the path only), with a status and one short reply that reaches the
-- sender as a notification. It is not a chat.

CREATE TABLE site_feedback (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  user_role VARCHAR(20) NOT NULL,
  category ENUM('problem','confusing','idea') NOT NULL,
  body TEXT NOT NULL,
  page_path VARCHAR(200) NULL,
  status ENUM('new','looking','fixed','wont_fix') NOT NULL DEFAULT 'new',
  reply VARCHAR(500) NULL,
  replied_by INT UNSIGNED NULL,
  replied_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_site_feedback_org (organization_id, status, created_at),
  KEY idx_site_feedback_time (organization_id, created_at),
  KEY idx_site_feedback_user (user_id, created_at),
  CONSTRAINT fk_site_feedback_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
  CONSTRAINT fk_site_feedback_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_site_feedback_replier FOREIGN KEY (replied_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
