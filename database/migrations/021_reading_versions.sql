-- Other reading versions of a class material (simpler English, Filipino). A teacher drafts one (with AI or by hand),
-- edits it and approves it; students see only approved versions, beside the original (Services/ReadingVersions.php).
CREATE TABLE reading_versions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id INT UNSIGNED NOT NULL,
  material_id INT UNSIGNED NOT NULL,
  kind VARCHAR(20) NOT NULL,
  content MEDIUMTEXT NOT NULL,
  status ENUM('draft','approved') NOT NULL DEFAULT 'draft',
  ai_assisted TINYINT(1) NOT NULL DEFAULT 0,
  source_version_id INT UNSIGNED NULL,
  created_by INT UNSIGNED NULL,
  approved_by INT UNSIGNED NULL,
  approved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reading_versions_kind (material_id, kind),
  CONSTRAINT fk_reading_versions_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
  CONSTRAINT fk_reading_versions_material FOREIGN KEY (material_id) REFERENCES learning_materials (id) ON DELETE CASCADE,
  CONSTRAINT fk_reading_versions_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_reading_versions_approver FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
