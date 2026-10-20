-- The school's list of learning competencies (for example the MATATAG curriculum's, with its codes), imported by an
-- administrator from a CSV (Phase 3, item 17). Sibol never makes up codes: the demo school's are labelled samples.
CREATE TABLE competencies (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id INT UNSIGNED NOT NULL,
  code VARCHAR(60) NOT NULL,
  description VARCHAR(1000) NOT NULL,
  subject_id INT UNSIGNED NULL,
  grade_level VARCHAR(40) NULL,
  quarter TINYINT UNSIGNED NULL,
  is_sample TINYINT(1) NOT NULL DEFAULT 0,
  archived_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_competencies_code (organization_id, code),
  KEY idx_competencies_subject (organization_id, subject_id, grade_level),
  CONSTRAINT fk_competencies_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
  CONSTRAINT fk_competencies_subject FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A question assesses one competency, as in a table of specifications.
ALTER TABLE questions
  ADD COLUMN competency_id INT UNSIGNED NULL AFTER topic,
  ADD KEY idx_questions_competency (competency_id),
  ADD CONSTRAINT fk_questions_competency FOREIGN KEY (competency_id) REFERENCES competencies (id) ON DELETE SET NULL;

-- A lesson can teach several.
CREATE TABLE lesson_competencies (
  lesson_id INT UNSIGNED NOT NULL,
  competency_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (lesson_id, competency_id),
  KEY idx_lesson_competencies_competency (competency_id),
  CONSTRAINT fk_lesson_competencies_lesson FOREIGN KEY (lesson_id) REFERENCES lessons (id) ON DELETE CASCADE,
  CONSTRAINT fk_lesson_competencies_competency FOREIGN KEY (competency_id) REFERENCES competencies (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
