-- 014: grading periods (quarters or terms) inside an academic period. An assignment counts in the period its
-- due date falls in, unless its teacher places it in another one (grading_period_id). Subjects belong to a
-- grading group whose weights the school sets (stored with the other grading settings).

CREATE TABLE grading_periods (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id INT UNSIGNED NOT NULL,
  academic_period_id INT UNSIGNED NOT NULL,
  name VARCHAR(60) NOT NULL,
  position TINYINT UNSIGNED NOT NULL,
  starts_on DATE NOT NULL,
  ends_on DATE NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_grading_periods_academic (academic_period_id, starts_on),
  KEY idx_grading_periods_org (organization_id),
  CONSTRAINT fk_grading_periods_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
  CONSTRAINT fk_grading_periods_academic FOREIGN KEY (academic_period_id) REFERENCES academic_periods (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE assignments
  ADD COLUMN grading_period_id INT UNSIGNED NULL AFTER category,
  ADD CONSTRAINT fk_assignments_grading_period FOREIGN KEY (grading_period_id) REFERENCES grading_periods (id) ON DELETE SET NULL;

ALTER TABLE subjects
  ADD COLUMN grading_group VARCHAR(40) NULL AFTER code;
