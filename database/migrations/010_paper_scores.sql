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
