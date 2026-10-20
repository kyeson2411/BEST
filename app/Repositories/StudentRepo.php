<?php

declare(strict_types=1);

namespace Sibol\Repositories;

use Sibol\Core\Db;
use Sibol\Policies\Access;
use Sibol\Services\Overrides;

/**
 * Read queries for student screens. Every query is scoped to the student's active enrolments, and due
 * dates are the student's own (an extension from their teacher replaces the class date; excused work
 * drops out of to-do lists).
 */
final class StudentRepo
{
    /** Published, open work that is due soon or recently overdue and not yet submitted. @return list<array> */
    public static function dueSoon(array $user, int $aheadDays = 14, int $overdueDays = 7, int $limit = 8): array
    {
        $uid = (int) $user['id'];
        $due = Overrides::dueSql('a');
        return Db::all(
            "SELECT a.id, a.title, a.kind, $due AS due_at, a.opens_at, a.time_limit_minutes, a.late_policy, a.points_possible,
                    c.id AS class_id, c.name AS class_name,
                    (a.opens_at IS NOT NULL AND a.opens_at > UTC_TIMESTAMP()) AS not_open,
                    (SELECT s.status FROM submissions s WHERE s.assignment_id = a.id AND s.student_id = :u1 ORDER BY s.attempt_no DESC LIMIT 1) AS sub_status
             FROM assignments a
             JOIN classes c ON c.id = a.class_id AND c.status = 'active'
             JOIN class_memberships cm ON cm.class_id = a.class_id AND cm.user_id = :u2 AND cm.role = 'student' AND cm.status = 'active'"
             . Overrides::joinSql('a', 'u4') . "
             WHERE " . Overrides::openSql('a') . ' AND ' . Overrides::notExcusedSql() . "
               AND $due IS NOT NULL
               AND $due BETWEEN UTC_TIMESTAMP() - INTERVAL " . $overdueDays . " DAY AND UTC_TIMESTAMP() + INTERVAL " . $aheadDays . " DAY
               AND NOT EXISTS (SELECT 1 FROM submissions s2 WHERE s2.assignment_id = a.id AND s2.student_id = :u3 AND s2.status = 'submitted')
             ORDER BY ($due < UTC_TIMESTAMP()) DESC, $due ASC
             LIMIT " . $limit,
            ['u1' => $uid, 'u2' => $uid, 'u3' => $uid, 'u4' => $uid]
        );
    }

    /** @return list<array> enrolled classes with teachers and simple counts */
    public static function classes(array $user): array
    {
        $uid = (int) $user['id'];
        return Db::all(
            "SELECT c.id, c.name, c.section, c.grade_level, s.name AS subject_name,
                    (SELECT GROUP_CONCAT(CONCAT(COALESCE(t.preferred_name, t.given_name), ' ', t.family_name) ORDER BY t.family_name SEPARATOR ', ')
                       FROM class_memberships tm JOIN users t ON t.id = tm.user_id
                      WHERE tm.class_id = c.id AND tm.role = 'teacher' AND tm.status = 'active') AS teachers,
                    (SELECT COUNT(*) FROM assignments a" . Overrides::joinSql('a', 'u4') . "
                      WHERE a.class_id = c.id AND " . Overrides::openSql('a') . ' AND ' . Overrides::notExcusedSql() . "
                       AND (a.opens_at IS NULL OR a.opens_at <= UTC_TIMESTAMP()) AND " . Overrides::dueSql('a') . " >= UTC_TIMESTAMP()
                       AND NOT EXISTS (SELECT 1 FROM submissions sx WHERE sx.assignment_id = a.id AND sx.student_id = :u1 AND sx.status = 'submitted')) AS open_work,
                    (SELECT MAX(COALESCE(m.release_at, m.published_at)) FROM learning_materials m WHERE m.class_id = c.id AND " . Access::releasedMaterialSql('m') . ") AS last_material_at,
                    (SELECT COUNT(*) FROM learning_materials m2 WHERE m2.class_id = c.id AND " . Access::releasedMaterialSql('m2') . "
                       AND NOT EXISTS (SELECT 1 FROM content_views v WHERE v.user_id = :u2 AND v.content_type = 'material' AND v.content_id = m2.id)) AS unseen_materials
             FROM classes c
             JOIN subjects s ON s.id = c.subject_id
             JOIN class_memberships cm ON cm.class_id = c.id AND cm.user_id = :u3 AND cm.role = 'student' AND cm.status = 'active'
             WHERE c.status = 'active' AND c.organization_id = :o
             ORDER BY s.name, c.name",
            ['u1' => $uid, 'u2' => $uid, 'u3' => $uid, 'u4' => $uid, 'o' => (int) $user['organization_id']]
        );
    }

    /** Released grades and feedback, newest first. @return list<array> */
    public static function recentFeedback(array $user, int $days = 21, int $limit = 5): array
    {
        return Db::all(
            "SELECT s.id, s.score, s.points_possible, s.released_at, s.feedback, a.id AS assignment_id, a.title, a.kind, c.name AS class_name
             FROM submissions s
             JOIN assignments a ON a.id = s.assignment_id
             JOIN classes c ON c.id = a.class_id
             JOIN class_memberships cm ON cm.class_id = a.class_id AND cm.user_id = s.student_id AND cm.role = 'student' AND cm.status = 'active'
             WHERE s.student_id = :u AND s.feedback_status = 'released' AND s.released_at >= UTC_TIMESTAMP() - INTERVAL " . $days . " DAY
             ORDER BY s.released_at DESC LIMIT " . $limit,
            ['u' => (int) $user['id']]
        );
    }

    /** Materials released recently in the student's classes. @return list<array> */
    public static function newMaterials(array $user, int $days = 10, int $limit = 5): array
    {
        $uid = (int) $user['id'];
        return Db::all(
            "SELECT m.id, m.title, m.kind, m.class_id, COALESCE(m.release_at, m.published_at) AS shared_at, c.name AS class_name,
                    (SELECT COUNT(*) FROM content_views v WHERE v.user_id = :u1 AND v.content_type = 'material' AND v.content_id = m.id) AS seen
             FROM learning_materials m
             JOIN classes c ON c.id = m.class_id AND c.status = 'active'
             JOIN class_memberships cm ON cm.class_id = m.class_id AND cm.user_id = :u2 AND cm.role = 'student' AND cm.status = 'active'
             WHERE " . Access::releasedMaterialSql('m') . "
               AND COALESCE(m.release_at, m.published_at) >= UTC_TIMESTAMP() - INTERVAL " . $days . " DAY
             ORDER BY shared_at DESC LIMIT " . $limit,
            ['u1' => $uid, 'u2' => $uid]
        );
    }

    /**
     * The single most useful thing to resume: a saved draft first, else the last material opened.
     * @return array<string,mixed>|null
     */
    public static function continueLearning(array $user): ?array
    {
        $uid = (int) $user['id'];
        $due = Overrides::dueSql('a');
        $draft = Db::one(
            "SELECT s.id AS submission_id, s.updated_at, a.id, a.title, a.kind, $due AS due_at, c.name AS class_name
             FROM submissions s
             JOIN assignments a ON a.id = s.assignment_id
             JOIN classes c ON c.id = a.class_id AND c.status = 'active'
             JOIN class_memberships cm ON cm.class_id = a.class_id AND cm.user_id = s.student_id AND cm.role = 'student' AND cm.status = 'active'"
             . Overrides::joinSql('a', 'u2') . "
             WHERE s.student_id = :u AND s.status IN ('in_progress', 'returned') AND " . Overrides::openSql('a') . "
               AND ($due IS NULL OR $due >= UTC_TIMESTAMP() OR a.late_policy <> 'not_accepted')
             ORDER BY s.updated_at DESC LIMIT 1",
            ['u' => $uid, 'u2' => $uid]
        );
        if ($draft) {
            return ['type' => 'draft'] + $draft;
        }
        $view = Db::one(
            "SELECT v.content_id, v.last_viewed_at, m.title, m.kind, m.class_id, c.name AS class_name, mo.title AS module_title
             FROM content_views v
             JOIN learning_materials m ON m.id = v.content_id
             JOIN classes c ON c.id = m.class_id AND c.status = 'active'
             JOIN class_memberships cm ON cm.class_id = m.class_id AND cm.user_id = v.user_id AND cm.role = 'student' AND cm.status = 'active'
             LEFT JOIN modules mo ON mo.id = m.module_id
             WHERE v.user_id = :u AND v.content_type = 'material' AND " . Access::releasedMaterialSql('m') . '
             ORDER BY v.last_viewed_at DESC LIMIT 1',
            ['u' => $uid]
        );
        return $view ? ['type' => 'material'] + $view : null;
    }

    /** @return array<string,mixed>|null */
    public static function lastConversation(array $user): ?array
    {
        return Db::one(
            'SELECT id, title, mode, last_message_at FROM ai_conversations WHERE user_id = :u AND archived_at IS NULL ORDER BY last_message_at DESC LIMIT 1',
            ['u' => (int) $user['id']]
        );
    }

    /** @return list<array> upcoming personal study sessions */
    public static function upcomingStudy(array $user, int $limit = 3): array
    {
        return Db::all(
            'SELECT ss.*, c.name AS class_name FROM study_sessions ss LEFT JOIN classes c ON c.id = ss.class_id
             WHERE ss.user_id = :u AND ss.completed_at IS NULL AND ss.starts_at >= UTC_TIMESTAMP() - INTERVAL 2 HOUR
             ORDER BY ss.starts_at LIMIT ' . $limit,
            ['u' => (int) $user['id']]
        );
    }

    /**
     * All work for the assignments page with derived status.
     * @param string $filter upcoming|submitted|graded|overdue|excused|all
     * @return list<array>
     */
    public static function assignments(array $user, string $filter, ?int $classId = null): array
    {
        $uid = (int) $user['id'];
        $params = ['u1' => $uid, 'u2' => $uid, 'u3' => $uid, 'u4' => $uid, 'u5' => $uid];
        $classSql = '';
        if ($classId !== null) {
            $classSql = ' AND a.class_id = :cls';
            $params['cls'] = $classId;
        }
        $due = Overrides::dueSql('a');
        $rows = Db::all(
            "SELECT a.id, a.title, a.kind, $due AS due_at, a.due_at AS class_due_at, a.opens_at, a.status, a.points_possible, a.late_policy, a.category, a.time_limit_minutes,
                    c.id AS class_id, c.name AS class_name, COALESCE(ov.excused, 0) AS excused,
                    (a.opens_at IS NOT NULL AND a.opens_at > UTC_TIMESTAMP()) AS not_open,
                    (SELECT s.status FROM submissions s WHERE s.assignment_id = a.id AND s.student_id = :u1 ORDER BY s.attempt_no DESC LIMIT 1) AS sub_status,
                    (SELECT s.submitted_at FROM submissions s WHERE s.assignment_id = a.id AND s.student_id = :u2 AND s.status = 'submitted' ORDER BY s.attempt_no DESC LIMIT 1) AS submitted_at,
                    (SELECT MAX(s.score) FROM submissions s WHERE s.assignment_id = a.id AND s.student_id = :u3 AND s.feedback_status = 'released') AS released_score
             FROM assignments a
             JOIN classes c ON c.id = a.class_id AND c.status = 'active'
             JOIN class_memberships cm ON cm.class_id = a.class_id AND cm.user_id = :u4 AND cm.role = 'student' AND cm.status = 'active'"
             . Overrides::joinSql('a', 'u5') . "
             WHERE a.status IN ('published', 'closed')" . $classSql . "
             ORDER BY $due IS NULL, $due",
            $params
        );
        $now = Db::now();
        $out = [];
        foreach ($rows as $row) {
            $submitted = $row['submitted_at'] !== null;
            $graded = $row['released_score'] !== null;
            $overdue = !$submitted && $row['due_at'] !== null && $row['due_at'] < $now;
            $row['bucket'] = (int) $row['excused'] === 1 ? 'excused' : ($graded ? 'graded' : ($submitted ? 'submitted' : ($overdue ? 'overdue' : 'upcoming')));
            if ($filter === 'all' || $filter === $row['bucket']) {
                $out[] = $row;
            }
        }
        if ($filter === 'graded' || $filter === 'submitted') {
            usort($out, static fn ($a, $b) => strcmp((string) $b['due_at'], (string) $a['due_at']));
        }
        return $out;
    }
}
