<?php

declare(strict_types=1);

namespace Sibol\Services;

use Sibol\Core\Audit;
use Sibol\Core\Db;

/**
 * Scores for work done in class or on paper, typed into one grid per assignment. A score is saved as a
 * submission whose source is 'paper' (graded, held until released), so the gradebook, the CSV export
 * and student progress count it exactly like online work. Absent and excused go with the student's
 * other exceptions (Overrides), so extensions and excused work behave as they do online.
 */
final class PaperScores
{
    /**
     * One row per active student: their current submission, exceptions, and whether the grid may set their
     * score (not when they handed the work in online).
     * @return list<array<string,mixed>>
     */
    public static function grid(array $assignment): array
    {
        $id = (int) $assignment['id'];
        $rows = Db::all(
            "SELECT u.id AS student_id, u.given_name, u.family_name, u.preferred_name, u.username, u.avatar_path,
                    s.id AS submission_id, s.source, s.status, s.score, s.grading_status, s.feedback_status, s.released_at
             FROM class_memberships cm JOIN users u ON u.id = cm.user_id
             LEFT JOIN submissions s ON s.id = (
                SELECT s2.id FROM submissions s2 WHERE s2.assignment_id = :a AND s2.student_id = u.id
                ORDER BY (s2.status = 'submitted') DESC, s2.attempt_no DESC LIMIT 1)
             WHERE cm.class_id = :c AND cm.role = 'student' AND cm.status = 'active'
             ORDER BY u.family_name, u.given_name",
            ['a' => $id, 'c' => (int) $assignment['class_id']]
        );
        $overrides = Overrides::forAssignment($id);
        foreach ($rows as &$row) {
            $ov = $overrides[(int) $row['student_id']] ?? null;
            $row['excused'] = (int) ($ov['excused'] ?? 0) === 1;
            $row['absent'] = (int) ($ov['absent'] ?? 0) === 1;
            $row['own_due_at'] = $ov['due_at'] ?? null;
            $row['extra_minutes'] = (int) ($ov['extra_minutes'] ?? 0);
            $row['editable'] = $row['submission_id'] === null || $row['source'] === 'paper';
        }
        unset($row);
        return $rows;
    }

    /**
     * Typed scores that cannot be saved, before anything is written.
     * @param array<int|string,mixed> $scores student id => what was typed
     * @return array<int,string> student id => problem
     */
    public static function problems(array $assignment, array $rows, array $scores): array
    {
        $max = (float) $assignment['points_possible'];
        $problems = [];
        foreach ($rows as $row) {
            $sid = (int) $row['student_id'];
            $raw = self::typed($scores[$sid] ?? '');
            if ($raw === '' || !$row['editable']) {
                continue;
            }
            if (!is_numeric($raw) || (float) $raw < 0 || (float) $raw > $max) {
                $problems[$sid] = t('Enter a score from 0 to :max.', ['max' => fmt_number($max, 2)]);
            }
        }
        return $problems;
    }

    /**
     * Saves the grid for every active student of the class. A typed score creates or updates a paper
     * submission (graded, held until released); an emptied one removes it. A student with a score is no
     * longer absent. With $release, every graded paper score of the assignment is released to its student.
     * @param array<int|string,mixed> $scores student id => what was typed
     * @param list<int|string> $absentIds
     * @param list<int|string> $excusedIds
     * @return array{scores:int,cleared:int,absent:int,excused:int,released:int}
     */
    public static function save(array $teacher, array $assignment, array $scores, array $absentIds, array $excusedIds, bool $release): array
    {
        $rows = self::grid($assignment);
        $absent = array_flip(array_map('intval', $absentIds));
        $excused = array_flip(array_map('intval', $excusedIds));
        $counts = ['scores' => 0, 'cleared' => 0, 'absent' => 0, 'excused' => 0, 'released' => 0];
        Db::transaction(static function () use ($teacher, $assignment, $rows, $scores, $absent, $excused, &$counts): void {
            foreach ($rows as $row) {
                $sid = (int) $row['student_id'];
                $raw = self::typed($scores[$sid] ?? '');
                $done = !$row['editable'];
                if ($row['editable'] && $raw !== '') {
                    $counts['scores'] += self::record($teacher, $assignment, $row, round((float) $raw, 2)) ? 1 : 0;
                    $done = true;
                } elseif ($row['editable'] && $row['submission_id'] !== null) {
                    Db::delete('submissions', ['id' => (int) $row['submission_id']]);
                    $counts['cleared']++;
                }
                $isAbsent = isset($absent[$sid]) && !$done;
                $isExcused = isset($excused[$sid]);
                if ($isAbsent !== $row['absent'] || $isExcused !== $row['excused']) {
                    Overrides::save($teacher, $assignment, $sid, $row['own_due_at'], $row['extra_minutes'], $isExcused, $isAbsent);
                }
                $counts['absent'] += $isAbsent ? 1 : 0;
                $counts['excused'] += $isExcused ? 1 : 0;
            }
        });
        if ($release) {
            $held = Db::all(
                "SELECT * FROM submissions WHERE assignment_id = :a AND source = 'paper' AND status = 'submitted' AND grading_status = 'graded' AND feedback_status <> 'released'",
                ['a' => (int) $assignment['id']]
            );
            foreach ($held as $sub) {
                $counts['released'] += Assessments::release($sub, $assignment, (int) $teacher['id']) ? 1 : 0;
            }
        }
        // Counts only: scores are school records, not something the audit log repeats.
        Audit::log('assignment.paper_scores_saved', 'assignment', (int) $assignment['id'], $counts);
        return $counts;
    }

    /** Creates or updates one student's paper score. Returns true when something changed. */
    private static function record(array $teacher, array $assignment, array $row, float $score): bool
    {
        $now = Db::now();
        $old = $row['score'] !== null ? (float) $row['score'] : null;
        if ($row['submission_id'] !== null && $old === $score && $row['grading_status'] === 'graded') {
            return false;
        }
        if ($row['submission_id'] === null) {
            // Dated to the day in class when that has passed, so lists show when the work was done.
            $doneAt = $assignment['due_at'] !== null && $assignment['due_at'] < $now ? (string) $assignment['due_at'] : $now;
            $id = Db::insert('submissions', [
                'organization_id' => (int) $assignment['organization_id'],
                'assignment_id' => (int) $assignment['id'],
                'student_id' => (int) $row['student_id'],
                'assignment_version_id' => $assignment['current_version_id'] !== null ? (int) $assignment['current_version_id'] : null,
                'attempt_no' => 1,
                'source' => 'paper',
                'status' => 'submitted',
                'started_at' => $doneAt,
                'submitted_at' => $doneAt,
                'score' => $score,
                'points_possible' => (float) $assignment['points_possible'],
                'grading_status' => 'graded',
                'feedback_status' => 'draft',
                'graded_by' => (int) $teacher['id'],
                'graded_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $id = (int) $row['submission_id'];
            Db::update('submissions', [
                'score' => $score,
                'points_possible' => (float) $assignment['points_possible'],
                'grading_status' => 'graded',
                'feedback_status' => $row['feedback_status'] === 'released' ? 'released' : 'draft',
                'graded_by' => (int) $teacher['id'],
                'graded_at' => $now,
                'updated_at' => $now,
            ], ['id' => $id]);
        }
        Db::insert('grading_history', [
            'submission_id' => $id,
            'actor_id' => (int) $teacher['id'],
            'action' => 'graded',
            'detail' => json_encode(['old_score' => $old, 'new_score' => $score, 'source' => 'paper']),
            'created_at' => $now,
        ]);
        return true;
    }

    /** What was typed, trimmed, with a decimal comma read as a point. @param mixed $value */
    private static function typed($value): string
    {
        return is_scalar($value) ? str_replace(',', '.', trim((string) $value)) : '';
    }
}
