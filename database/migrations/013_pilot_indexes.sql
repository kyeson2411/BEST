-- 013: indexes for pilot-size schools, found by timing pages on db:reset --size=pilot.
-- The weekly pilot report counts who handed in work each week (submission times for one school) and,
-- for each student and task, whether and when it was handed in; both can now be read from an index alone.

ALTER TABLE submissions
  ADD KEY idx_submissions_org_time (organization_id, submitted_at, student_id),
  ADD KEY idx_submissions_pair_status (assignment_id, student_id, status, is_late);
