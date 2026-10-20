-- 004: assignments and quizzes, questions with server-only answer keys, published snapshots,
-- submissions/attempts, answers, grading history, AI grading suggestions.

CREATE TABLE assignments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id INT UNSIGNED NOT NULL,
  class_id INT UNSIGNED NOT NULL,
  module_id INT UNSIGNED NULL,
  kind ENUM('assignment','quiz') NOT NULL,
  title VARCHAR(200) NOT NULL,
  instructions MEDIUMTEXT NULL,
  objectives TEXT NULL,
  category VARCHAR(60) NULL,
  status ENUM('draft','published','closed','archived') NOT NULL DEFAULT 'draft',
  opens_at DATETIME NULL,
  due_at DATETIME NULL,
  late_policy ENUM('not_accepted','accepted','penalty') NOT NULL DEFAULT 'accepted',
  late_penalty_pct TINYINT UNSIGNED NOT NULL DEFAULT 0,
  max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 1,
  time_limit_minutes SMALLINT UNSIGNED NULL,
  points_possible DECIMAL(7,2) NOT NULL DEFAULT 0,
  allow_text TINYINT(1) NOT NULL DEFAULT 1,
  allow_files TINYINT(1) NOT NULL DEFAULT 0,
  allow_resubmission TINYINT(1) NOT NULL DEFAULT 0,
  rubric LONGTEXT NULL,
  feedback_release ENUM('manual','immediate') NOT NULL DEFAULT 'manual',
  answer_key_release ENUM('never','after_submit','after_due','manual') NOT NULL DEFAULT 'after_due',
  answers_released_at DATETIME NULL,
  ai_assisted TINYINT(1) NOT NULL DEFAULT 0,
  current_version_id INT UNSIGNED NULL,
  created_by INT UNSIGNED NULL,
  published_at DATETIME NULL,
  closed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_assignments_class (class_id, status, due_at),
  KEY idx_assignments_org_due (organization_id, status, due_at),
  FULLTEXT KEY ft_assignments_text (title, instructions),
  CONSTRAINT fk_assignments_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
  CONSTRAINT fk_assignments_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE,
  CONSTRAINT fk_assignments_module FOREIGN KEY (module_id) REFERENCES modules (id) ON DELETE SET NULL,
  CONSTRAINT fk_assignments_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT chk_assignments_penalty CHECK (late_penalty_pct <= 100),
  CONSTRAINT chk_assignments_attempts CHECK (max_attempts BETWEEN 1 AND 20),
  CONSTRAINT chk_assignments_window CHECK (opens_at IS NULL OR due_at IS NULL OR due_at > opens_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- answer_key is JSON and never sent to a student before the release rule allows it.
CREATE TABLE questions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  assignment_id INT UNSIGNED NOT NULL,
  position INT NOT NULL DEFAULT 0,
  qtype ENUM('multiple_choice','true_false','short_answer','essay') NOT NULL,
  prompt TEXT NOT NULL,
  points DECIMAL(6,2) NOT NULL DEFAULT 1,
  options LONGTEXT NULL,
  answer_key LONGTEXT NULL,
  explanation TEXT NULL,
  topic VARCHAR(120) NULL,
  source_ref VARCHAR(255) NULL,
  ai_generated TINYINT(1) NOT NULL DEFAULT 0,
  reviewed TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_questions_assignment (assignment_id, position),
  CONSTRAINT fk_questions_assignment FOREIGN KEY (assignment_id) REFERENCES assignments (id) ON DELETE CASCADE,
  CONSTRAINT chk_questions_points CHECK (points >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Snapshot of questions and keys at publish time; attempts are graded against their snapshot.
CREATE TABLE assignment_versions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  assignment_id INT UNSIGNED NOT NULL,
  version_no INT UNSIGNED NOT NULL,
  snapshot LONGTEXT NOT NULL,
  published_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_assignment_versions_no (assignment_id, version_no),
  CONSTRAINT fk_assignment_versions_assignment FOREIGN KEY (assignment_id) REFERENCES assignments (id) ON DELETE CASCADE,
  CONSTRAINT fk_assignment_versions_publisher FOREIGN KEY (published_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE assignments
  ADD CONSTRAINT fk_assignments_current_version FOREIGN KEY (current_version_id) REFERENCES assignment_versions (id) ON DELETE SET NULL;

CREATE TABLE assignment_resources (
  assignment_id INT UNSIGNED NOT NULL,
  material_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (assignment_id, material_id),
  CONSTRAINT fk_assignment_resources_assignment FOREIGN KEY (assignment_id) REFERENCES assignments (id) ON DELETE CASCADE,
  CONSTRAINT fk_assignment_resources_material FOREIGN KEY (material_id) REFERENCES learning_materials (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per attempt. For assignments, status in_progress is a saved draft.
CREATE TABLE submissions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id INT UNSIGNED NOT NULL,
  assignment_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  assignment_version_id INT UNSIGNED NULL,
  attempt_no TINYINT UNSIGNED NOT NULL DEFAULT 1,
  status ENUM('in_progress','submitted','returned') NOT NULL DEFAULT 'in_progress',
  body MEDIUMTEXT NULL,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deadline_at DATETIME NULL,
  submitted_at DATETIME NULL,
  is_late TINYINT(1) NOT NULL DEFAULT 0,
  auto_score DECIMAL(7,2) NULL,
  score DECIMAL(7,2) NULL,
  points_possible DECIMAL(7,2) NULL,
  grading_status ENUM('ungraded','needs_review','graded') NOT NULL DEFAULT 'ungraded',
  feedback TEXT NULL,
  rubric_scores LONGTEXT NULL,
  feedback_status ENUM('none','draft','released') NOT NULL DEFAULT 'none',
  released_at DATETIME NULL,
  graded_by INT UNSIGNED NULL,
  graded_at DATETIME NULL,
  receipt_code CHAR(10) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_submissions_attempt (assignment_id, student_id, attempt_no),
  UNIQUE KEY uq_submissions_receipt (receipt_code),
  KEY idx_submissions_student (student_id, status),
  KEY idx_submissions_review (assignment_id, status, grading_status, submitted_at),
  CONSTRAINT fk_submissions_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
  CONSTRAINT fk_submissions_assignment FOREIGN KEY (assignment_id) REFERENCES assignments (id) ON DELETE CASCADE,
  CONSTRAINT fk_submissions_student FOREIGN KEY (student_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_submissions_version FOREIGN KEY (assignment_version_id) REFERENCES assignment_versions (id) ON DELETE SET NULL,
  CONSTRAINT fk_submissions_grader FOREIGN KEY (graded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE submission_files (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  submission_id INT UNSIGNED NOT NULL,
  original_filename VARCHAR(255) NOT NULL,
  stored_path VARCHAR(255) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  size_bytes INT UNSIGNED NOT NULL,
  sha256 CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_submission_files_submission (submission_id),
  CONSTRAINT fk_submission_files_submission FOREIGN KEY (submission_id) REFERENCES submissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE submission_answers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  submission_id INT UNSIGNED NOT NULL,
  question_id INT UNSIGNED NOT NULL,
  response TEXT NULL,
  is_correct TINYINT(1) NULL,
  points_awarded DECIMAL(6,2) NULL,
  needs_manual TINYINT(1) NOT NULL DEFAULT 0,
  teacher_comment TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_submission_answers (submission_id, question_id),
  KEY idx_submission_answers_question (question_id, is_correct),
  CONSTRAINT fk_submission_answers_submission FOREIGN KEY (submission_id) REFERENCES submissions (id) ON DELETE CASCADE,
  CONSTRAINT fk_submission_answers_question FOREIGN KEY (question_id) REFERENCES questions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE grading_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  submission_id INT UNSIGNED NOT NULL,
  actor_id INT UNSIGNED NULL,
  action VARCHAR(40) NOT NULL,
  detail TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_grading_history_submission (submission_id, created_at),
  CONSTRAINT fk_grading_history_submission FOREIGN KEY (submission_id) REFERENCES submissions (id) ON DELETE CASCADE,
  CONSTRAINT fk_grading_history_actor FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AI suggestions are advisory only; nothing here is ever shown to a student.
CREATE TABLE ai_grading_suggestions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  submission_id INT UNSIGNED NOT NULL,
  requested_by INT UNSIGNED NULL,
  suggestion LONGTEXT NOT NULL,
  provider VARCHAR(40) NOT NULL,
  model VARCHAR(100) NULL,
  is_demo TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ai_grading_submission (submission_id, created_at),
  CONSTRAINT fk_ai_grading_submission FOREIGN KEY (submission_id) REFERENCES submissions (id) ON DELETE CASCADE,
  CONSTRAINT fk_ai_grading_requester FOREIGN KEY (requested_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
