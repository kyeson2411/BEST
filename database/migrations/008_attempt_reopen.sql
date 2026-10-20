-- 008: a teacher can reopen a submitted quiz attempt (for example when time ran out while the
-- student was offline). The student gets resume_minutes from the moment they open it again.

ALTER TABLE submissions
  ADD COLUMN resume_minutes SMALLINT UNSIGNED NULL AFTER deadline_at;
