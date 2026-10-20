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
