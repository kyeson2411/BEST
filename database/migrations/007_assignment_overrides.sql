-- 007: one student's exceptions to an assignment, set by a teacher on the submissions page:
-- their own due date, extra minutes on a timed quiz, or excused (not counted, never "missing").
-- No reason is stored, so health or family details stay out of Sibol.

CREATE TABLE assignment_overrides (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id INT UNSIGNED NOT NULL,
  assignment_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  due_at DATETIME NULL,
  extra_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  excused TINYINT(1) NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_overrides_assignment_student (assignment_id, student_id),
  KEY idx_overrides_student (student_id),
  CONSTRAINT fk_overrides_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
  CONSTRAINT fk_overrides_assignment FOREIGN KEY (assignment_id) REFERENCES assignments (id) ON DELETE CASCADE,
  CONSTRAINT fk_overrides_student FOREIGN KEY (student_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_overrides_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT chk_overrides_minutes CHECK (extra_minutes <= 600)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
