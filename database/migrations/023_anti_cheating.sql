-- Anti-cheating for quizzes and online assignments, on unless the teacher switches it off. Students cannot copy or
-- paste in their answers, and during a quiz each time the student leaves the quiz page (another tab, window or app)
-- is counted on the attempt with the time away, for the teacher. A browser cannot stop a student from leaving, so
-- the attempt records it instead.
ALTER TABLE assignments ADD COLUMN anti_cheating TINYINT(1) NOT NULL DEFAULT 1 AFTER allow_resubmission;

ALTER TABLE submissions
  ADD COLUMN left_page_count INT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN left_page_seconds INT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN paste_blocked_count INT UNSIGNED NOT NULL DEFAULT 0;
