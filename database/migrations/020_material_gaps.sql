-- Topics students asked the companion about that their class materials did not cover. Only the question's key words
-- are kept (never the question, the answer or the conversation); teachers see a topic only once enough different
-- students asked about it (Services/MaterialGaps.php).
CREATE TABLE material_gaps (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id INT UNSIGNED NOT NULL,
  class_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  keywords VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_material_gaps_class (class_id, created_at),
  KEY idx_material_gaps_student (student_id),
  CONSTRAINT fk_material_gaps_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
  CONSTRAINT fk_material_gaps_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE,
  CONSTRAINT fk_material_gaps_student FOREIGN KEY (student_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
