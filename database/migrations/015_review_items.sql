-- 015: a student's private review queue. Questions they missed in practice, and in quizzes whose answers they can
-- see, come back on a schedule. Each item keeps a copy of the question as it was missed (content, JSON as text).
-- due_on is a date in the school's time zone. Never graded and never shown to teachers.

CREATE TABLE review_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  class_id INT UNSIGNED NOT NULL,
  source ENUM('practice','quiz') NOT NULL,
  source_key VARCHAR(60) NOT NULL,
  practice_set_id INT UNSIGNED NULL,
  assignment_id INT UNSIGNED NULL,
  material_id INT UNSIGNED NULL,
  kind ENUM('choice','text','card') NOT NULL,
  content LONGTEXT NOT NULL,
  step TINYINT UNSIGNED NOT NULL DEFAULT 0,
  reviews INT UNSIGNED NOT NULL DEFAULT 0,
  lapses INT UNSIGNED NOT NULL DEFAULT 0,
  due_on DATE NOT NULL,
  last_reviewed_on DATE NULL,
  done_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_review_items_source (user_id, source_key),
  KEY idx_review_items_due (user_id, done_at, due_on),
  KEY idx_review_items_reviewed (user_id, last_reviewed_on),
  KEY idx_review_items_class (class_id),
  CONSTRAINT fk_review_items_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
  CONSTRAINT fk_review_items_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_review_items_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE,
  CONSTRAINT fk_review_items_practice FOREIGN KEY (practice_set_id) REFERENCES practice_sets (id) ON DELETE CASCADE,
  CONSTRAINT fk_review_items_assignment FOREIGN KEY (assignment_id) REFERENCES assignments (id) ON DELETE CASCADE,
  CONSTRAINT fk_review_items_material FOREIGN KEY (material_id) REFERENCES learning_materials (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
