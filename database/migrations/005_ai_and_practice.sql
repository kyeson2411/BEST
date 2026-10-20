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
