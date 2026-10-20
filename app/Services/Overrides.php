<?php

declare(strict_types=1);

namespace Sibol\Services;

use Sibol\Core\Audit;
use Sibol\Core\Dates;
use Sibol\Core\Db;

/**
 * One student's exceptions to an assignment (illness, a typhoon, no signal, a learner who needs more
 * time): their own due date, extra minutes on a timed quiz, or excused work that is not counted and is
 * never "missing". Teachers set them on the submissions page; no reason is stored.
 *
 * Everything that decides a student's deadline uses their own date: the student's assignment view
 * (Assessments::forStudent applies it), late rules and quiz timers, the dashboard, calendar, reminders and
 * navigation counts (SQL via joinSql()/dueSql()). Answers released "after the due date" wait for the
 * latest extension in the class, so they cannot reach someone who has not taken the quiz yet.
 */
final class Overrides
{
    public const MAX_EXTRA_MINUTES = 600;

    /** SQL: LEFT JOIN of one student's override (alias $alias) onto assignments alias $a; :$param is the student id. */
    public static function joinSql(string $a, string $param, string $alias = 'ov'): string
    {
        return " LEFT JOIN assignment_overrides $alias ON $alias.assignment_id = $a.id AND $alias.student_id = :$param";
    }

    /** SQL: that student's due date, their own when one was set. Use with joinSql(). */
    public static function dueSql(string $a, string $alias = 'ov'): string
    {
        return "COALESCE($alias.due_at, $a.due_at)";
    }

    /** SQL: the work is not excused for that student. Use with joinSql(). */
    public static function notExcusedSql(string $alias = 'ov'): string
    {
        return "COALESCE($alias.excused, 0) = 0";
    }

    /** SQL: the student can still hand it in: published, or closed while their own extension runs. */
    public static function openSql(string $a, string $alias = 'ov'): string
    {
        return "($a.status = 'published' OR ($a.status = 'closed' AND $alias.due_at IS NOT NULL AND $alias.due_at > UTC_TIMESTAMP()))";
    }

    /** SQL: active students expected to hand in assignment alias $a, leaving out anyone excused. */
    public static function expectedSql(string $a): string
    {
        return "(SELECT COUNT(*) FROM class_memberships xm WHERE xm.class_id = $a.class_id AND xm.role = 'student' AND xm.status = 'active'
                   AND NOT EXISTS (SELECT 1 FROM assignment_overrides xo WHERE xo.assignment_id = $a.id AND xo.student_id = xm.user_id AND xo.excused = 1))";
    }

    /** SQL: students who handed in assignment alias $a, leaving out anyone excused. */
    public static function handedInSql(string $a): string
    {
        return "(SELECT COUNT(DISTINCT xs.student_id) FROM submissions xs WHERE xs.assignment_id = $a.id AND xs.status = 'submitted'
                   AND NOT EXISTS (SELECT 1 FROM assignment_overrides xo2 WHERE xo2.assignment_id = $a.id AND xo2.student_id = xs.student_id AND xo2.excused = 1))";
    }

    /** @return array<string,mixed>|null */
    public static function find(int $assignmentId, int $studentId): ?array
    {
        return Db::one('SELECT * FROM assignment_overrides WHERE assignment_id = :a AND student_id = :u', ['a' => $assignmentId, 'u' => $studentId]);
    }

    /** @return array<int,array<string,mixed>> student id => override */
    public static function forAssignment(int $assignmentId): array
    {
        $out = [];
        foreach (Db::all('SELECT * FROM assignment_overrides WHERE assignment_id = :a', ['a' => $assignmentId]) as $row) {
            $out[(int) $row['student_id']] = $row;
        }
        return $out;
    }

    /**
     * @param list<int> $assignmentIds
     * @return array<string,array<string,mixed>> "studentId:assignmentId" => override
     */
    public static function forAssignments(array $assignmentIds): array
    {
        if (!$assignmentIds) {
            return [];
        }
        [$in, $p] = Db::in($assignmentIds, 'a');
        $out = [];
        foreach (Db::all("SELECT * FROM assignment_overrides WHERE assignment_id IN $in", $p) as $row) {
            $out[(int) $row['student_id'] . ':' . (int) $row['assignment_id']] = $row;
        }
        return $out;
    }

    /**
     * The assignment as one student sees it: their own due date in due_at (the class's stays in
     * class_due_at), with extended, extra_minutes and excused. Applying twice changes nothing.
     * @return array<string,mixed>
     */
    public static function apply(array $assignment, int $studentId, ?array $override = null): array
    {
        if (array_key_exists('class_due_at', $assignment)) {
            return $assignment;
        }
        $ov = $override ?? self::find((int) $assignment['id'], $studentId);
        $classDue = $assignment['due_at'] ?? null;
        $own = $ov['due_at'] ?? null;
        $assignment['class_due_at'] = $classDue;
        $assignment['due_at'] = $own ?? $classDue;
        $assignment['extended'] = $own !== null && $own !== $classDue;
        $assignment['extra_minutes'] = (int) ($ov['extra_minutes'] ?? 0);
        $assignment['excused'] = (int) ($ov['excused'] ?? 0) === 1;
        // Marked absent for work done in class, unless a make-up date (their own due date) is still ahead.
        $assignment['absent'] = (int) ($ov['absent'] ?? 0) === 1 && ($own === null || $own <= Db::now());
        return $assignment;
    }

    /** When answers set to appear "after the due date" may appear: the class date or the latest extension, whichever is later. */
    public static function latestDue(int $assignmentId, ?string $classDueAt): ?string
    {
        if ($classDueAt === null) {
            return null;
        }
        $latest = Db::value('SELECT MAX(due_at) FROM assignment_overrides WHERE assignment_id = :a', ['a' => $assignmentId]);
        return $latest !== null && (string) $latest > $classDueAt ? (string) $latest : $classDueAt;
    }

    /**
     * Saves a teacher's exceptions for one student, or removes them when nothing differs from the class.
     * The student is told in Sibol. Attempts already in progress are re-timed. $absent marks a student who
     * missed work done in class (null keeps what is stored); a make-up date is their own due date.
     * @param array<string,mixed> $assignment as loaded by Access::teacherAssignment
     */
    public static function save(array $teacher, array $assignment, int $studentId, ?string $dueAtUtc, int $extraMinutes, bool $excused, ?bool $absent = null): void
    {
        $assignmentId = (int) $assignment['id'];
        $timed = $assignment['kind'] === 'quiz' && (int) $assignment['time_limit_minutes'] > 0;
        $extraMinutes = $timed ? max(0, min(self::MAX_EXTRA_MINUTES, $extraMinutes)) : 0;
        if ($dueAtUtc === $assignment['due_at']) {
            $dueAtUtc = null;
        }
        $before = self::find($assignmentId, $studentId);
        $absent = $absent ?? (int) ($before['absent'] ?? 0) === 1;
        if ($dueAtUtc === null && $extraMinutes === 0 && !$excused && !$absent) {
            self::remove($teacher, $assignment, $studentId);
            return;
        }
        Db::exec(
            'INSERT INTO assignment_overrides (organization_id, assignment_id, student_id, due_at, extra_minutes, excused, absent, created_by, created_at, updated_at)
             VALUES (:o, :a, :u, :d, :m, :x, :ab, :by, :c, :t)
             ON DUPLICATE KEY UPDATE due_at = VALUES(due_at), extra_minutes = VALUES(extra_minutes), excused = VALUES(excused), absent = VALUES(absent), updated_at = VALUES(updated_at)',
            ['o' => (int) $assignment['organization_id'], 'a' => $assignmentId, 'u' => $studentId, 'd' => $dueAtUtc, 'm' => $extraMinutes,
             'x' => $excused ? 1 : 0, 'ab' => $absent ? 1 : 0, 'by' => (int) $teacher['id'], 'c' => Db::now(), 't' => Db::now()]
        );
        self::retimeOpenAttempts($assignment, $studentId);
        Audit::log('assignment.override_saved', 'assignment', $assignmentId, [
            'student_id' => $studentId, 'own_due_date' => $dueAtUtc !== null, 'extra_minutes' => $extraMinutes, 'excused' => $excused, 'absent' => $absent,
        ]);
        self::tellStudent($assignment, $studentId, $before, $dueAtUtc, $extraMinutes, $excused);
    }

    public static function remove(array $teacher, array $assignment, int $studentId): bool
    {
        $removed = Db::delete('assignment_overrides', ['assignment_id' => (int) $assignment['id'], 'student_id' => $studentId]) > 0;
        if ($removed) {
            self::retimeOpenAttempts($assignment, $studentId);
            Audit::log('assignment.override_removed', 'assignment', (int) $assignment['id'], ['student_id' => $studentId]);
            Notifier::send(
                (int) $assignment['organization_id'],
                [$studentId],
                'reminder',
                t('Back to the class due date: :title', ['title' => $assignment['title']]),
                Dates::due($assignment['due_at'])['label'],
                '/student/assignments/' . (int) $assignment['id'],
                'override:' . (int) $assignment['id'] . ':' . $studentId . ':' . time()
            );
        }
        return $removed;
    }

    /**
     * Recomputes the deadline of the student's attempts in progress after their time or due date changed.
     * @param array<string,mixed> $assignment the class's assignment row (not one already applied to a student)
     */
    private static function retimeOpenAttempts(array $assignment, int $studentId): void
    {
        if ($assignment['kind'] !== 'quiz') {
            return;
        }
        $view = self::apply($assignment, $studentId);
        // A reopened attempt keeps the minutes its teacher gave it.
        foreach (Db::all("SELECT id, started_at FROM submissions WHERE assignment_id = :a AND student_id = :u AND status = 'in_progress' AND resume_minutes IS NULL", ['a' => (int) $assignment['id'], 'u' => $studentId]) as $sub) {
            $deadline = Assessments::attemptDeadline($view, (int) strtotime($sub['started_at'] . ' UTC'));
            Db::update('submissions', ['deadline_at' => $deadline !== null ? gmdate('Y-m-d H:i:s', $deadline) : null, 'updated_at' => Db::now()], ['id' => (int) $sub['id']]);
        }
    }

    private static function tellStudent(array $assignment, int $studentId, ?array $before, ?string $dueAtUtc, int $extraMinutes, bool $excused): void
    {
        $title = (string) $assignment['title'];
        if ($excused && (int) ($before['excused'] ?? 0) !== 1) {
            [$subject, $body] = [t('Excused: :title', ['title' => $title]), t('Your teacher excused you from this work. It is not counted and will not show as missing.')];
        } elseif ($dueAtUtc !== null && $dueAtUtc !== ($before['due_at'] ?? null)) {
            [$subject, $body] = [$assignment['kind'] === 'paper' ? t('Make-up date for :title', ['title' => $title]) : t('New due date for :title', ['title' => $title]), Dates::due($dueAtUtc, (string) $assignment['kind'])['label']];
        } elseif ($extraMinutes !== (int) ($before['extra_minutes'] ?? 0)) {
            [$subject, $body] = [t('More time for :title', ['title' => $title]), tp('You have :count extra minute on the time limit.', 'You have :count extra minutes on the time limit.', $extraMinutes)];
        } else {
            return;
        }
        Notifier::send((int) $assignment['organization_id'], [$studentId], 'reminder', $subject, $body,
            '/student/assignments/' . (int) $assignment['id'], 'override:' . (int) $assignment['id'] . ':' . $studentId . ':' . time());
    }
}
