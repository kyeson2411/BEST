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
