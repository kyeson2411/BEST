<?php

declare(strict_types=1);

namespace Sibol\Repositories;

use Sibol\Core\Db;
use Sibol\Policies\Access;

/** Read queries for teacher screens, always limited to classes the teacher actively teaches. */
final class TeacherRepo
{
    /** @return list<array<string,mixed>> */
    public static function classes(array $user, bool $includeArchived = false): array
    {
        $ids = Access::teacherClassIds($user, $includeArchived);
        if (!$ids) {
            return [];
        }
        [$in, $p] = Db::in($ids, 'c');
        return Db::all(
            "SELECT c.*, s.name AS subject_name, ap.name AS period_name,
                    (SELECT COUNT(*) FROM class_memberships m WHERE m.class_id = c.id AND m.role = 'student' AND m.status = 'active') AS students,
                    (SELECT COUNT(*) FROM assignments a WHERE a.class_id = c.id AND a.status = 'published' AND (a.due_at IS NULL OR a.due_at >= UTC_TIMESTAMP())) AS open_work,
                    (SELECT COUNT(*) FROM submissions su JOIN assignments a2 ON a2.id = su.assignment_id
                       WHERE a2.class_id = c.id AND su.status = 'submitted' AND su.grading_status <> 'graded') AS to_review,
                    (SELECT COUNT(*) FROM shared_excerpts se WHERE se.class_id = c.id AND se.seen_at IS NULL) AS unseen_shares
             FROM classes c JOIN subjects s ON s.id = c.subject_id LEFT JOIN academic_periods ap ON ap.id = c.academic_period_id
             WHERE c.id IN $in ORDER BY c.status, s.name, c.name",
            $p
        );
    }

    /** Submitted work waiting for grading, oldest first. @return list<array<string,mixed>> */
    public static function reviewQueue(array $user, ?int $classId = null, int $limit = 200): array
    {
        $ids = $classId !== null ? [$classId] : Access::teacherClassIds($user, true);
        if (!$ids) {
            return [];
        }
        [$in, $p] = Db::in($ids, 'c');
        return Db::all(
            "SELECT s.id, s.submitted_at, s.is_late, s.attempt_no, s.grading_status, a.id AS assignment_id, a.title, a.kind, c.name AS class_name, c.id AS class_id,
                    u.given_name, u.family_name, u.preferred_name
             FROM submissions s JOIN assignments a ON a.id = s.assignment_id JOIN classes c ON c.id = a.class_id JOIN users u ON u.id = s.student_id
             WHERE a.class_id IN $in AND s.status = 'submitted' AND s.grading_status <> 'graded'
             ORDER BY s.submitted_at ASC LIMIT " . (int) $limit,
            $p
        );
    }

    /** Published work due in the window, with completion counts. @return list<array<string,mixed>> */
    public static function assignmentsDue(array $user, string $fromSql, string $toSql, string $order = 'ASC'): array
    {
        $ids = Access::teacherClassIds($user);
        if (!$ids) {
            return [];
        }
        [$in, $p] = Db::in($ids, 'c');
        return Db::all(
            "SELECT a.id, a.title, a.kind, a.due_at, a.opens_at, a.status, c.name AS class_name, c.id AS class_id,
                    (SELECT COUNT(*) FROM class_memberships m WHERE m.class_id = a.class_id AND m.role = 'student' AND m.status = 'active') AS enrolled,
                    (SELECT COUNT(DISTINCT s.student_id) FROM submissions s WHERE s.assignment_id = a.id AND s.status = 'submitted') AS submitted,
                    (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id AND s.status = 'submitted' AND s.grading_status <> 'graded') AS ungraded,
                    (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id AND s.grading_status = 'graded' AND s.feedback_status <> 'released' AND s.status = 'submitted') AS unreleased
             FROM assignments a JOIN classes c ON c.id = a.class_id
             WHERE a.class_id IN $in AND a.status IN ('published','closed') AND a.due_at >= $fromSql AND a.due_at < $toSql
             ORDER BY a.due_at $order LIMIT 12",
            $p
        );
    }

    /** Things that need the teacher's action. @return array<string,mixed> */
    public static function attention(array $user): array
    {
        $ids = Access::teacherClassIds($user, true);
        if (!$ids) {
            return ['failed' => [], 'ai_drafts' => [], 'unreleased' => 0, 'shares' => 0];
        }
        [$in, $p] = Db::in($ids, 'c');
        return [
            'failed' => Db::all(
                "SELECT m.id, m.title, mv.processing_error, c.name AS class_name FROM learning_materials m
                 JOIN material_versions mv ON mv.id = m.current_version_id JOIN classes c ON c.id = m.class_id
                 WHERE m.class_id IN $in AND mv.processing_status = 'failed' AND m.visibility <> 'archived' LIMIT 5",
                $p
            ),
            'ai_drafts' => Db::all(
                "SELECT a.id, a.title, COUNT(q.id) AS waiting FROM questions q JOIN assignments a ON a.id = q.assignment_id
                 WHERE a.class_id IN $in AND q.reviewed = 0 AND a.status <> 'archived' GROUP BY a.id, a.title LIMIT 5",
                $p
            ),
            'unreleased' => (int) Db::value(
                "SELECT COUNT(*) FROM submissions s JOIN assignments a ON a.id = s.assignment_id
                 WHERE a.class_id IN $in AND s.status = 'submitted' AND s.grading_status = 'graded' AND s.feedback_status <> 'released'",
                $p
            ),
            'shares' => (int) Db::value("SELECT COUNT(*) FROM shared_excerpts WHERE class_id IN $in AND seen_at IS NULL", $p),
        ];
    }

    /**
     * Learning overview over the last 30 days of due work: completion and objective-question accuracy.
     * @return array{due:int,expected:int,submitted:int,answers:int,correct:int}
     */
    public static function overview(array $user): array
    {
        $ids = Access::teacherClassIds($user);
        if (!$ids) {
            return ['due' => 0, 'expected' => 0, 'submitted' => 0, 'answers' => 0, 'correct' => 0];
        }
        [$in, $p] = Db::in($ids, 'c');
        $row = Db::one(
            "SELECT COUNT(*) AS due,
                    COALESCE(SUM((SELECT COUNT(*) FROM class_memberships m WHERE m.class_id = a.class_id AND m.role = 'student' AND m.status = 'active')), 0) AS expected,
                    COALESCE(SUM((SELECT COUNT(DISTINCT s.student_id) FROM submissions s WHERE s.assignment_id = a.id AND s.status = 'submitted')), 0) AS submitted
             FROM assignments a WHERE a.class_id IN $in AND a.status IN ('published','closed')
               AND a.due_at < UTC_TIMESTAMP() AND a.due_at >= UTC_TIMESTAMP() - INTERVAL 30 DAY",
            $p
        );
        $acc = Db::one(
            "SELECT COUNT(*) AS answers, COALESCE(SUM(sa.is_correct), 0) AS correct
             FROM submission_answers sa JOIN submissions s ON s.id = sa.submission_id JOIN assignments a ON a.id = s.assignment_id
             WHERE a.class_id IN $in AND s.status = 'submitted' AND sa.is_correct IS NOT NULL AND s.submitted_at >= UTC_TIMESTAMP() - INTERVAL 30 DAY",
            $p
        );
        return ['due' => (int) $row['due'], 'expected' => (int) $row['expected'], 'submitted' => (int) $row['submitted'], 'answers' => (int) $acc['answers'], 'correct' => (int) $acc['correct']];
    }
}
