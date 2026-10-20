-- Sibol database schema, generated from database/migrations by `php bin/sibol.php schema:build`.
-- Import into an empty database (utf8mb4) with phpMyAdmin, or run `php bin/sibol.php migrate`.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS schema_migrations (
  version VARCHAR(100) NOT NULL,
  applied_at DATETIME NOT NULL,
  PRIMARY KEY (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- 001_core.sql
-- 001: schools, accounts, tokens, rate limits, notifications, audit, privacy requests.
-- Compatible with MySQL 8 and MariaDB 10.4+. All DATETIME values are UTC.

CREATE TABLE organizations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(160) NOT NULL,
  short_name VARCHAR(60) NULL,
  slug VARCHAR(80) NOT NULL,
  logo_path VARCHAR(255) NULL,
  timezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Manila',
  default_language VARCHAR(8) NOT NULL DEFAULT 'en',
  is_demo TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_organizations_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Policies and preferences as JSON values per key; defaults live in app/Services/Settings.php.
CREATE TABLE organization_settings (
  organization_id INT UNSIGNED NOT NULL,
  setting_key VARCHAR(80) NOT NULL,
  setting_value TEXT NOT NULL,
  updated_by INT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (organization_id, setting_key),
  CONSTRAINT fk_orgsettings_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One role per account. Class-level roles live in class_memberships.
CREATE TABLE users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id INT UNSIGNED NOT NULL,
  role ENUM('admin','teacher','student') NOT NULL,
  status ENUM('invited','active','deactivated') NOT NULL DEFAULT 'invited',
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NULL,
  given_name VARCHAR(80) NOT NULL,
  family_name VARCHAR(80) NOT NULL,
  preferred_name VARCHAR(80) NULL,
  grade_level VARCHAR(40) NULL,
  avatar_path VARCHAR(255) NULL,
  language VARCHAR(8) NULL,
  timezone VARCHAR(64) NULL,
  preferences TEXT NULL,
  session_version INT UNSIGNED NOT NULL DEFAULT 1,
  is_demo TINYINT(1) NOT NULL DEFAULT 0,
  last_login_at DATETIME NULL,
  password_changed_at DATETIME NULL,
  deactivated_at DATETIME NULL,
  anonymized_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_org_role (organization_id, role, status),
  KEY idx_users_org_name (organization_id, family_name, given_name),
  CONSTRAINT fk_users_org FOREIGN KEY (organization_id) REFERENCES organizations (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Invitation and password-reset links. Only a SHA-256 hash of the token is stored.
CREATE TABLE user_tokens (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  purpose ENUM('invitation','password_reset') NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  revoked_at DATETIME NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_tokens_hash (token_hash),
  KEY idx_user_tokens_user (user_id, purpose),
  CONSTRAINT fk_user_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_user_tokens_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limits (
  bucket VARCHAR(40) NOT NULL,
  subject_hash CHAR(64) NOT NULL,
  window_start DATETIME NOT NULL,
  hits INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (bucket, subject_hash, window_start),
  KEY idx_rate_limits_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  type VARCHAR(40) NOT NULL,
  title VARCHAR(200) NOT NULL,
  body VARCHAR(500) NULL,
  link VARCHAR(255) NULL,
  dedupe_key VARCHAR(100) NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_notifications_dedupe (user_id, dedupe_key),
  KEY idx_notifications_user (user_id, read_at, created_at),
  CONSTRAINT fk_notifications_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Minimal audit trail. Never stores passwords, tokens, keys, or AI conversation text.
CREATE TABLE audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id INT UNSIGNED NULL,
  actor_id INT UNSIGNED NULL,
  actor_role VARCHAR(20) NULL,
  action VARCHAR(60) NOT NULL,
  target_type VARCHAR(40) NULL,
  target_id BIGINT UNSIGNED NULL,
  metadata TEXT NULL,
  ip_hash CHAR(64) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_org_time (organization_id, created_at),
  KEY idx_audit_actor (actor_id, created_at),
  KEY idx_audit_action (action),
  CONSTRAINT fk_audit_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE privacy_requests (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  request_type ENUM('export','correction','deletion') NOT NULL,
  details TEXT NULL,
  status ENUM('submitted','in_review','completed','rejected') NOT NULL DEFAULT 'submitted',
  reviewer_id INT UNSIGNED NULL,
  resolution_note TEXT NULL,
  export_path VARCHAR(255) NULL,
  export_expires_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_privacy_org_status (organization_id, status, created_at),
  KEY idx_privacy_user (user_id),
  CONSTRAINT fk_privacy_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
  CONSTRAINT fk_privacy_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_privacy_reviewer FOREIGN KEY (reviewer_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (version, applied_at) VALUES ('001_core.sql', UTC_TIMESTAMP());

-- ---------------------------------------------------------------- 002_academics.sql
-- 002: academic periods, subjects, classes (sections), memberships, modules, announcements.

CREATE TABLE academic_periods (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id INT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  starts_on DATE NOT NULL,
  ends_on DATE NOT NULL,
  is_current TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_periods_org (organization_id, starts_on),
  CONSTRAINT fk_periods_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
  CONSTRAINT chk_periods_dates CHECK (ends_on >= starts_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subjects (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  code VARCHAR(30) NULL,
  description VARCHAR(500) NULL,
  archived_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_subjects_name (organization_id, name),
  CONSTRAINT fk_subjects_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A class is one section of a subject in a period, e.g. "Science 9 - Narra".
CREATE TABLE classes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id INT UNSIGNED NOT NULL,
  subject_id INT UNSIGNED NOT NULL,
  academic_period_id INT UNSIGNED NULL,
  name VARCHAR(140) NOT NULL,
  section VARCHAR(60) NULL,
  grade_level VARCHAR(40) NULL,
  description TEXT NULL,
  status ENUM('active','archived') NOT NULL DEFAULT 'active',
  join_code VARCHAR(12) NULL,
  join_code_enabled TINYINT(1) NOT NULL DEFAULT 0,
  join_code_expires_at DATETIME NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_classes_join_code (join_code),
  KEY idx_classes_org (organization_id, status),
  KEY idx_classes_subject (subject_id),
  CONSTRAINT fk_classes_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
  CONSTRAINT fk_classes_subject FOREIGN KEY (subject_id) REFERENCES subjects (id),
  CONSTRAINT fk_classes_period FOREIGN KEY (academic_period_id) REFERENCES academic_periods (id) ON DELETE SET NULL,
  CONSTRAINT fk_classes_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The trusted mapping of who teaches and who studies in each class.
CREATE TABLE class_memberships (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  class_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  role ENUM('teacher','student') NOT NULL,
  status ENUM('active','withdrawn') NOT NULL DEFAULT 'active',
  joined_via ENUM('admin','teacher','code','import') NOT NULL DEFAULT 'admin',
  joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  withdrawn_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_memberships_class_user (class_id, user_id),
  KEY idx_memberships_user (user_id, status, role),
  KEY idx_memberships_class_role (class_id, role, status),
  CONSTRAINT fk_memberships_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE,
  CONSTRAINT fk_memberships_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE modules (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  class_id INT UNSIGNED NOT NULL,
  title VARCHAR(160) NOT NULL,
  description VARCHAR(500) NULL,
  position INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_modules_class (class_id, position),
  CONSTRAINT fk_modules_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE announcements (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id INT UNSIGNED NOT NULL,
  class_id INT UNSIGNED NOT NULL,
  author_id INT UNSIGNED NULL,
  title VARCHAR(200) NOT NULL,
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_announcements_class (class_id, created_at),
  CONSTRAINT fk_announcements_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
  CONSTRAINT fk_announcements_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE,
  CONSTRAINT fk_announcements_author FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (version, applied_at) VALUES ('002_academics.sql', UTC_TIMESTAMP());

-- ---------------------------------------------------------------- 003_materials.sql
-- 003: learning materials, versions, retrieval chunks, view tracking, lessons.

CREATE TABLE learning_materials (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id INT UNSIGNED NOT NULL,
  class_id INT UNSIGNED NOT NULL,
  module_id INT UNSIGNED NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT NULL,
  topic VARCHAR(120) NULL,
  kind ENUM('file','link') NOT NULL,
  url VARCHAR(500) NULL,
  visibility ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  release_at DATETIME NULL,
  ai_enabled TINYINT(1) NOT NULL DEFAULT 1,
  current_version_id INT UNSIGNED NULL,
  created_by INT UNSIGNED NULL,
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_materials_class (class_id, visibility, release_at),
  KEY idx_materials_module (module_id),
  KEY idx_materials_org (organization_id, created_at),
  FULLTEXT KEY ft_materials_text (title, description, topic),
  CONSTRAINT fk_materials_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
  CONSTRAINT fk_materials_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE,
  CONSTRAINT fk_materials_module FOREIGN KEY (module_id) REFERENCES modules (id) ON DELETE SET NULL,
  CONSTRAINT fk_materials_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT chk_materials_link CHECK (kind <> 'link' OR url IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Each upload is a version. Processing status is real: a version is "ready" only after its chunks exist.
CREATE TABLE material_versions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  material_id INT UNSIGNED NOT NULL,
  version_no INT UNSIGNED NOT NULL,
  original_filename VARCHAR(255) NOT NULL,
  stored_path VARCHAR(255) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  extension VARCHAR(10) NOT NULL,
  size_bytes INT UNSIGNED NOT NULL,
  sha256 CHAR(64) NOT NULL,
  page_count INT UNSIGNED NULL,
  char_count INT UNSIGNED NULL,
  processing_status ENUM('uploaded','extracting','indexing','ready','failed','unsupported') NOT NULL DEFAULT 'uploaded',
  processing_error VARCHAR(500) NULL,
  processing_attempts INT UNSIGNED NOT NULL DEFAULT 0,
  processed_at DATETIME NULL,
  uploaded_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_material_versions_no (material_id, version_no),
  KEY idx_material_versions_status (processing_status),
  CONSTRAINT fk_material_versions_material FOREIGN KEY (material_id) REFERENCES learning_materials (id) ON DELETE CASCADE,
  CONSTRAINT fk_material_versions_uploader FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE learning_materials
  ADD CONSTRAINT fk_materials_current_version FOREIGN KEY (current_version_id) REFERENCES material_versions (id) ON DELETE SET NULL;

-- Retrieval units with page/section metadata. class_id and material_id are denormalised so
-- permission filters run in the same query as ranking (before any AI context is built).
CREATE TABLE material_chunks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id INT UNSIGNED NOT NULL,
  class_id INT UNSIGNED NOT NULL,
  material_id INT UNSIGNED NOT NULL,
  material_version_id INT UNSIGNED NOT NULL,
  chunk_index INT UNSIGNED NOT NULL,
  page_from INT UNSIGNED NULL,
  page_to INT UNSIGNED NULL,
  section_title VARCHAR(255) NULL,
  content TEXT NOT NULL,
  char_count INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_chunks_version (material_version_id, chunk_index),
  KEY idx_chunks_class (class_id, material_id),
  FULLTEXT KEY ft_chunks_content (content),
  CONSTRAINT fk_chunks_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
  CONSTRAINT fk_chunks_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE,
  CONSTRAINT fk_chunks_material FOREIGN KEY (material_id) REFERENCES learning_materials (id) ON DELETE CASCADE,
  CONSTRAINT fk_chunks_version FOREIGN KEY (material_version_id) REFERENCES material_versions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lessons: the AI/teacher draft is kept separate from what students see until the teacher publishes.
CREATE TABLE lessons (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id INT UNSIGNED NOT NULL,
  class_id INT UNSIGNED NOT NULL,
  module_id INT UNSIGNED NULL,
  title VARCHAR(200) NOT NULL,
  status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  grade_level VARCHAR(40) NULL,
  duration_minutes SMALLINT UNSIGNED NULL,
  reading_level VARCHAR(40) NULL,
  language VARCHAR(8) NOT NULL DEFAULT 'en',
  lesson_format VARCHAR(40) NULL,
  objectives TEXT NULL,
  draft_content LONGTEXT NULL,
  published_content LONGTEXT NULL,
  source_material_ids TEXT NULL,
  ai_assisted TINYINT(1) NOT NULL DEFAULT 0,
  has_unpublished_changes TINYINT(1) NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NULL,
  published_by INT UNSIGNED NULL,
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_lessons_class (class_id, status),
  CONSTRAINT fk_lessons_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
  CONSTRAINT fk_lessons_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE,
  CONSTRAINT fk_lessons_module FOREIGN KEY (module_id) REFERENCES modules (id) ON DELETE SET NULL,
  CONSTRAINT fk_lessons_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_lessons_publisher FOREIGN KEY (published_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- What a student last opened, for "Continue learning" and "new since you last looked".
CREATE TABLE content_views (
  user_id INT UNSIGNED NOT NULL,
  content_type ENUM('material','lesson','assignment') NOT NULL,
  content_id INT UNSIGNED NOT NULL,
  class_id INT UNSIGNED NOT NULL,
  view_count INT UNSIGNED NOT NULL DEFAULT 1,
  first_viewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_viewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, content_type, content_id),
  KEY idx_content_views_recent (user_id, last_viewed_at),
  CONSTRAINT fk_content_views_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_content_views_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (version, applied_at) VALUES ('003_materials.sql', UTC_TIMESTAMP());

-- ---------------------------------------------------------------- 004_assessments.sql
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

INSERT INTO schema_migrations (version, applied_at) VALUES ('004_assessments.sql', UTC_TIMESTAMP());

-- ---------------------------------------------------------------- 005_ai_and_practice.sql
-- 005: private AI conversations, citations, usage accounting, answer reports,
-- opt-in shares with teachers, practice sets/flashcards, personal study sessions.

CREATE TABLE ai_conversations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  class_id INT UNSIGNED NULL,
  material_id INT UNSIGNED NULL,
  mode VARCHAR(30) NOT NULL,
  title VARCHAR(160) NOT NULL,
  language VARCHAR(8) NOT NULL DEFAULT 'en',
  level VARCHAR(30) NULL,
  archived_at DATETIME NULL,
  last_message_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ai_conversations_user (user_id, archived_at, last_message_at),
  KEY idx_ai_conversations_retention (organization_id, last_message_at),
  CONSTRAINT fk_ai_conversations_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
  CONSTRAINT fk_ai_conversations_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_ai_conversations_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE SET NULL,
  CONSTRAINT fk_ai_conversations_material FOREIGN KEY (material_id) REFERENCES learning_materials (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ai_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  conversation_id INT UNSIGNED NOT NULL,
  role ENUM('user','assistant') NOT NULL,
  content MEDIUMTEXT NOT NULL,
  status ENUM('complete','streaming','error','cancelled') NOT NULL DEFAULT 'complete',
  grounding ENUM('sources','general','no_sources','demo') NULL,
  provider VARCHAR(40) NULL,
  model VARCHAR(100) NULL,
  is_demo TINYINT(1) NOT NULL DEFAULT 0,
  input_tokens INT UNSIGNED NULL,
  output_tokens INT UNSIGNED NULL,
  latency_ms INT UNSIGNED NULL,
  error_code VARCHAR(60) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ai_messages_conversation (conversation_id, id),
  CONSTRAINT fk_ai_messages_conversation FOREIGN KEY (conversation_id) REFERENCES ai_conversations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Only sources that were actually retrieved and cited are stored here.
CREATE TABLE ai_citations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  message_id BIGINT UNSIGNED NOT NULL,
  ref_no TINYINT UNSIGNED NOT NULL,
  material_id INT UNSIGNED NULL,
  material_version_id INT UNSIGNED NULL,
  chunk_id BIGINT UNSIGNED NULL,
  title VARCHAR(200) NOT NULL,
  page_from INT UNSIGNED NULL,
  page_to INT UNSIGNED NULL,
  section_title VARCHAR(255) NULL,
  excerpt TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ai_citations_message (message_id, ref_no),
  CONSTRAINT fk_ai_citations_message FOREIGN KEY (message_id) REFERENCES ai_messages (id) ON DELETE CASCADE,
  CONSTRAINT fk_ai_citations_material FOREIGN KEY (material_id) REFERENCES learning_materials (id) ON DELETE SET NULL,
  CONSTRAINT fk_ai_citations_chunk FOREIGN KEY (chunk_id) REFERENCES material_chunks (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usage accounting without conversation content.
CREATE TABLE ai_usage_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  user_role VARCHAR(20) NULL,
  feature VARCHAR(40) NOT NULL,
  provider VARCHAR(40) NOT NULL,
  model VARCHAR(100) NULL,
  is_demo TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('ok','error','rate_limited','cancelled','blocked') NOT NULL,
  input_tokens INT UNSIGNED NOT NULL DEFAULT 0,
  output_tokens INT UNSIGNED NOT NULL DEFAULT 0,
  est_cost_usd DECIMAL(12,6) NOT NULL DEFAULT 0,
  latency_ms INT UNSIGNED NULL,
  error_code VARCHAR(60) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ai_usage_org_time (organization_id, created_at),
  KEY idx_ai_usage_user_time (user_id, created_at),
  CONSTRAINT fk_ai_usage_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
  CONSTRAINT fk_ai_usage_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "Report this answer": the reported answer becomes visible to administrators (students are told this).
CREATE TABLE ai_reports (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id INT UNSIGNED NOT NULL,
  message_id BIGINT UNSIGNED NULL,
  reporter_id INT UNSIGNED NULL,
  reason ENUM('incorrect','unsafe','not_helpful','privacy','other') NOT NULL,
  note VARCHAR(1000) NULL,
  answer_excerpt TEXT NULL,
  status ENUM('open','resolved','dismissed') NOT NULL DEFAULT 'open',
  reviewed_by INT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  resolution_note VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ai_reports_org_status (organization_id, status, created_at),
  CONSTRAINT fk_ai_reports_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
  CONSTRAINT fk_ai_reports_message FOREIGN KEY (message_id) REFERENCES ai_messages (id) ON DELETE SET NULL,
  CONSTRAINT fk_ai_reports_reporter FOREIGN KEY (reporter_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_ai_reports_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Student-initiated, explicit shares with the class teacher (never automatic).
CREATE TABLE shared_excerpts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  class_id INT UNSIGNED NOT NULL,
  kind ENUM('excerpt','reflection') NOT NULL,
  content TEXT NOT NULL,
  student_note VARCHAR(1000) NULL,
  seen_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_shared_excerpts_class (class_id, created_at),
  KEY idx_shared_excerpts_student (student_id),
  CONSTRAINT fk_shared_excerpts_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
  CONSTRAINT fk_shared_excerpts_student FOREIGN KEY (student_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_shared_excerpts_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Self-study practice (quizzes and flashcards) generated from approved materials.
CREATE TABLE practice_sets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  class_id INT UNSIGNED NULL,
  material_id INT UNSIGNED NULL,
  kind ENUM('quiz','flashcards') NOT NULL,
  title VARCHAR(200) NOT NULL,
  topic VARCHAR(160) NULL,
  items LONGTEXT NOT NULL,
  generator ENUM('ai','demo') NOT NULL,
  results LONGTEXT NULL,
  score INT UNSIGNED NULL,
  total INT UNSIGNED NULL,
  completed_at DATETIME NULL,
  last_reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_practice_sets_user (user_id, created_at),
  CONSTRAINT fk_practice_sets_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
  CONSTRAINT fk_practice_sets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_practice_sets_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE SET NULL,
  CONSTRAINT fk_practice_sets_material FOREIGN KEY (material_id) REFERENCES learning_materials (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE study_sessions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  class_id INT UNSIGNED NULL,
  title VARCHAR(200) NOT NULL,
  notes TEXT NULL,
  starts_at DATETIME NOT NULL,
  duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  completed_at DATETIME NULL,
  source ENUM('manual','companion') NOT NULL DEFAULT 'manual',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_study_sessions_user (user_id, starts_at),
  CONSTRAINT fk_study_sessions_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
  CONSTRAINT fk_study_sessions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_study_sessions_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE SET NULL,
  CONSTRAINT chk_study_sessions_duration CHECK (duration_minutes BETWEEN 5 AND 480)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (version, applied_at) VALUES ('005_ai_and_practice.sql', UTC_TIMESTAMP());

-- ---------------------------------------------------------------- 006_section_copies.sql
-- 006: "Copy to other sections". A copy remembers the record it was copied from, so a second copy
-- can reuse what is already in a section (for example the material a quiz links to) and teachers
-- can see where something has been copied. Copies are always separate rows in their own class.

ALTER TABLE learning_materials
  ADD COLUMN copied_from_id INT UNSIGNED NULL AFTER created_by,
  ADD KEY idx_materials_copied_from (copied_from_id),
  ADD CONSTRAINT fk_materials_copied_from FOREIGN KEY (copied_from_id) REFERENCES learning_materials (id) ON DELETE SET NULL;

ALTER TABLE assignments
  ADD COLUMN copied_from_id INT UNSIGNED NULL AFTER created_by,
  ADD KEY idx_assignments_copied_from (copied_from_id),
  ADD CONSTRAINT fk_assignments_copied_from FOREIGN KEY (copied_from_id) REFERENCES assignments (id) ON DELETE SET NULL;

ALTER TABLE lessons
  ADD COLUMN copied_from_id INT UNSIGNED NULL AFTER created_by,
  ADD KEY idx_lessons_copied_from (copied_from_id),
  ADD CONSTRAINT fk_lessons_copied_from FOREIGN KEY (copied_from_id) REFERENCES lessons (id) ON DELETE SET NULL;

INSERT INTO schema_migrations (version, applied_at) VALUES ('006_section_copies.sql', UTC_TIMESTAMP());

-- ---------------------------------------------------------------- 007_assignment_overrides.sql
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

INSERT INTO schema_migrations (version, applied_at) VALUES ('007_assignment_overrides.sql', UTC_TIMESTAMP());

-- ---------------------------------------------------------------- 008_attempt_reopen.sql
-- 008: a teacher can reopen a submitted quiz attempt (for example when time ran out while the
-- student was offline). The student gets resume_minutes from the moment they open it again.

ALTER TABLE submissions
  ADD COLUMN resume_minutes SMALLINT UNSIGNED NULL AFTER deadline_at;

INSERT INTO schema_migrations (version, applied_at) VALUES ('008_attempt_reopen.sql', UTC_TIMESTAMP());

-- ---------------------------------------------------------------- 009_accounts_without_email.sql
-- 009: accounts without email. Every account gets a username, which Sibol makes from the person's name;
-- students sign in with it, and email becomes optional. Sections group a grade level's classes in a
-- period (for example Grade 9 Narra) and can have an adviser, who may print sign-in and password slips
-- for the section's students.

ALTER TABLE users
  MODIFY email VARCHAR(190) NULL,
  ADD COLUMN username VARCHAR(40) NULL AFTER email;

-- Existing accounts take the part of their email before the @, keeping letters, digits and dots.
-- A name that is too short becomes user-<id>; a name two accounts would share gets -<id> added.
-- Hyphens never come from an email here, so the added endings cannot clash with another name.
UPDATE users
  SET username = LEFT(TRIM(BOTH '.' FROM LOWER(REGEXP_REPLACE(SUBSTRING_INDEX(email, '@', 1), '[^A-Za-z0-9.]', ''))), 28)
  WHERE anonymized_at IS NULL;

UPDATE users SET username = CONCAT('user-', id) WHERE anonymized_at IS NULL AND CHAR_LENGTH(username) < 3;

UPDATE users u
  JOIN (SELECT username FROM users WHERE username IS NOT NULL GROUP BY username HAVING COUNT(*) > 1) dup ON dup.username = u.username
  SET u.username = CONCAT(u.username, '-', u.id);

ALTER TABLE users
  ADD UNIQUE KEY uq_users_username (username);

CREATE TABLE sections (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id INT UNSIGNED NOT NULL,
  academic_period_id INT UNSIGNED NULL,
  grade_level VARCHAR(40) NULL,
  name VARCHAR(60) NOT NULL,
  adviser_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sections_name (organization_id, academic_period_id, grade_level, name),
  KEY idx_sections_adviser (adviser_id),
  CONSTRAINT fk_sections_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
  CONSTRAINT fk_sections_period FOREIGN KEY (academic_period_id) REFERENCES academic_periods (id) ON DELETE SET NULL,
  CONSTRAINT fk_sections_adviser FOREIGN KEY (adviser_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE classes
  ADD COLUMN section_id INT UNSIGNED NULL AFTER section,
  ADD KEY idx_classes_section (section_id),
  ADD CONSTRAINT fk_classes_section FOREIGN KEY (section_id) REFERENCES sections (id) ON DELETE SET NULL;

-- Classes that already name a section are linked to it.
INSERT INTO sections (organization_id, academic_period_id, grade_level, name, created_at, updated_at)
  SELECT organization_id, academic_period_id, grade_level, TRIM(section), MIN(created_at), MAX(updated_at)
  FROM classes WHERE section IS NOT NULL AND TRIM(section) <> ''
  GROUP BY organization_id, academic_period_id, grade_level, TRIM(section);

UPDATE classes c
  JOIN sections s ON s.organization_id = c.organization_id AND s.name = TRIM(c.section)
   AND s.academic_period_id <=> c.academic_period_id AND s.grade_level <=> c.grade_level
  SET c.section_id = s.id;

INSERT INTO schema_migrations (version, applied_at) VALUES ('009_accounts_without_email.sql', UTC_TIMESTAMP());

-- ---------------------------------------------------------------- 010_paper_scores.sql
-- 010: work done in class or on paper. A "paper" assignment has no online submission: its teacher types
-- each student's score into a grid. A score is stored as a submission whose source is 'paper', so it counts
-- in the gradebook, the CSV export and progress exactly like online work. The same grid records a student
-- who was absent, kept with that student's other exceptions to the assignment (own date, excused).

ALTER TABLE assignments
  MODIFY kind ENUM('assignment','quiz','paper') NOT NULL;

ALTER TABLE submissions
  ADD COLUMN source ENUM('online','paper') NOT NULL DEFAULT 'online' AFTER attempt_no;

ALTER TABLE assignment_overrides
  ADD COLUMN absent TINYINT(1) NOT NULL DEFAULT 0 AFTER excused;

INSERT INTO schema_migrations (version, applied_at) VALUES ('010_paper_scores.sql', UTC_TIMESTAMP());

-- ---------------------------------------------------------------- 011_notices_and_consent.sql
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

INSERT INTO schema_migrations (version, applied_at) VALUES ('011_notices_and_consent.sql', UTC_TIMESTAMP());

-- ---------------------------------------------------------------- 012_site_feedback.sql
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

INSERT INTO schema_migrations (version, applied_at) VALUES ('012_site_feedback.sql', UTC_TIMESTAMP());

-- ---------------------------------------------------------------- 013_pilot_indexes.sql
-- 013: indexes for pilot-size schools, found by timing pages on db:reset --size=pilot.
-- The weekly pilot report counts who handed in work each week (submission times for one school) and,
-- for each student and task, whether and when it was handed in; both can now be read from an index alone.

ALTER TABLE submissions
  ADD KEY idx_submissions_org_time (organization_id, submitted_at, student_id),
  ADD KEY idx_submissions_pair_status (assignment_id, student_id, status, is_late);

INSERT INTO schema_migrations (version, applied_at) VALUES ('013_pilot_indexes.sql', UTC_TIMESTAMP());

-- ---------------------------------------------------------------- 014_grading_periods.sql
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

INSERT INTO schema_migrations (version, applied_at) VALUES ('014_grading_periods.sql', UTC_TIMESTAMP());

-- ---------------------------------------------------------------- 015_review_items.sql
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

INSERT INTO schema_migrations (version, applied_at) VALUES ('015_review_items.sql', UTC_TIMESTAMP());

-- ---------------------------------------------------------------- 016_comment_bank.sql
-- 016: a teacher's own comment bank: saved feedback comments to insert while grading. criterion holds a rubric
-- criterion's title, so a comment shows next to that criterion on any assignment. Only its owner sees it.

CREATE TABLE comment_bank (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  criterion VARCHAR(160) NULL,
  uses INT UNSIGNED NOT NULL DEFAULT 0,
  last_used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_comment_bank_user (user_id, uses),
  CONSTRAINT fk_comment_bank_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
  CONSTRAINT fk_comment_bank_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (version, applied_at) VALUES ('016_comment_bank.sql', UTC_TIMESTAMP());

-- ---------------------------------------------------------------- 017_two_step.sql
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

INSERT INTO schema_migrations (version, applied_at) VALUES ('017_two_step.sql', UTC_TIMESTAMP());

-- ---------------------------------------------------------------- 018_competencies.sql
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

INSERT INTO schema_migrations (version, applied_at) VALUES ('018_competencies.sql', UTC_TIMESTAMP());

-- ---------------------------------------------------------------- 019_heading_search.sql
-- Phase 3, item 18: a passage is also found by its heading ("Common mistakes"), not only by its text.
ALTER TABLE material_chunks DROP INDEX ft_chunks_content, ADD FULLTEXT KEY ft_chunks_text (section_title, content);

INSERT INTO schema_migrations (version, applied_at) VALUES ('019_heading_search.sql', UTC_TIMESTAMP());

