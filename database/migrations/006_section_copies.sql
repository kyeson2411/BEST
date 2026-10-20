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
