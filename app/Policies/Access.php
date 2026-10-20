<?php

declare(strict_types=1);

namespace Sibol\Policies;

use Sibol\Core\Db;
use Sibol\Core\HttpException;

/**
 * Deny-by-default access checks. MySQL has no row-level security, so every
 * class-scoped query goes through these helpers, which join on the trusted
 * class_memberships table and the user's own organisation. Records outside a
 * user's scope are reported as "not found" so their existence is not revealed.
 */
final class Access
{
    /** @return array<string,mixed> the class, if $user actively teaches it */
    public static function teacherClass(array $user, int $classId): array
    {
        $class = Db::one(
            "SELECT c.*, s.name AS subject_name, ap.name AS period_name
             FROM classes c
             JOIN subjects s ON s.id = c.subject_id
             LEFT JOIN academic_periods ap ON ap.id = c.academic_period_id
             JOIN class_memberships cm ON cm.class_id = c.id AND cm.user_id = :u AND cm.role = 'teacher' AND cm.status = 'active'
             WHERE c.id = :c AND c.organization_id = :o",
            ['u' => (int) $user['id'], 'c' => $classId, 'o' => (int) $user['organization_id']]
        );
        if (!$class) {
            throw HttpException::notFound();
        }
        return $class;
    }

    /** @return array<string,mixed> the class, if $user is an active student in it and it is active */
    public static function studentClass(array $user, int $classId): array
    {
        $class = Db::one(
            "SELECT c.*, s.name AS subject_name
             FROM classes c
             JOIN subjects s ON s.id = c.subject_id
             JOIN class_memberships cm ON cm.class_id = c.id AND cm.user_id = :u AND cm.role = 'student' AND cm.status = 'active'
             WHERE c.id = :c AND c.organization_id = :o AND c.status = 'active'",
            ['u' => (int) $user['id'], 'c' => $classId, 'o' => (int) $user['organization_id']]
        );
        if (!$class) {
            throw HttpException::notFound();
        }
        return $class;
    }

    /** @return list<int> */
    public static function teacherClassIds(array $user, bool $includeArchived = false): array
    {
        return array_map('intval', Db::column(
            "SELECT c.id FROM classes c
             JOIN class_memberships cm ON cm.class_id = c.id AND cm.user_id = :u AND cm.role = 'teacher' AND cm.status = 'active'
             WHERE c.organization_id = :o" . ($includeArchived ? '' : " AND c.status = 'active'"),
            ['u' => (int) $user['id'], 'o' => (int) $user['organization_id']]
        ));
    }

    /** @return list<int> */
    public static function studentClassIds(array $user): array
    {
        return array_map('intval', Db::column(
            "SELECT c.id FROM classes c
             JOIN class_memberships cm ON cm.class_id = c.id AND cm.user_id = :u AND cm.role = 'student' AND cm.status = 'active'
             WHERE c.organization_id = :o AND c.status = 'active'",
            ['u' => (int) $user['id'], 'o' => (int) $user['organization_id']]
        ));
    }

    public static function teaches(array $user, int $classId): bool
    {
        return in_array($classId, self::teacherClassIds($user, true), true);
    }

    /** Teacher-scoped assignment lookup. @return array<string,mixed> */
    public static function teacherAssignment(array $user, int $assignmentId): array
    {
        $row = Db::one(
            "SELECT a.*, c.name AS class_name, c.status AS class_status, s.name AS subject_name
             FROM assignments a
             JOIN classes c ON c.id = a.class_id
             JOIN subjects s ON s.id = c.subject_id
             JOIN class_memberships cm ON cm.class_id = a.class_id AND cm.user_id = :u AND cm.role = 'teacher' AND cm.status = 'active'
             WHERE a.id = :a AND a.organization_id = :o",
            ['u' => (int) $user['id'], 'a' => $assignmentId, 'o' => (int) $user['organization_id']]
        );
        if (!$row) {
            throw HttpException::notFound();
        }
        return $row;
    }

    /** Teacher-scoped submission lookup (with assignment). @return array{0:array,1:array} */
    public static function teacherSubmission(array $user, int $submissionId): array
    {
        $sub = Db::one('SELECT * FROM submissions WHERE id = :id AND organization_id = :o', ['id' => $submissionId, 'o' => (int) $user['organization_id']]);
        if (!$sub) {
            throw HttpException::notFound();
        }
        $assignment = self::teacherAssignment($user, (int) $sub['assignment_id']);
        return [$sub, $assignment];
    }

    /** Teacher-scoped material lookup. @return array<string,mixed> */
    public static function teacherMaterial(array $user, int $materialId): array
    {
        $row = Db::one(
            "SELECT m.*, c.name AS class_name, mo.title AS module_title
             FROM learning_materials m
             JOIN classes c ON c.id = m.class_id
             LEFT JOIN modules mo ON mo.id = m.module_id
             JOIN class_memberships cm ON cm.class_id = m.class_id AND cm.user_id = :u AND cm.role = 'teacher' AND cm.status = 'active'
             WHERE m.id = :m AND m.organization_id = :o",
            ['u' => (int) $user['id'], 'm' => $materialId, 'o' => (int) $user['organization_id']]
        );
        if (!$row) {
            throw HttpException::notFound();
        }
        return $row;
    }

    /**
     * A student whose sign-in $user may help with (printing a sign-in or password slip): any student of
     * the school for an administrator; for a teacher, a student actively enrolled in an active class
     * they teach, or in an active class of a section they advise.
     * @return array<string,mixed>
     */
    public static function studentAccount(array $user, int $studentId): array
    {
        $params = ['s' => $studentId, 'o' => (int) $user['organization_id']];
        $sql = "SELECT u.* FROM users u WHERE u.id = :s AND u.organization_id = :o AND u.role = 'student' AND u.anonymized_at IS NULL";
        if ($user['role'] === 'teacher') {
            $sql .= " AND EXISTS (
                SELECT 1 FROM class_memberships sm
                JOIN classes c ON c.id = sm.class_id AND c.status = 'active'
                LEFT JOIN class_memberships tm ON tm.class_id = c.id AND tm.user_id = :t1 AND tm.role = 'teacher' AND tm.status = 'active'
                LEFT JOIN sections se ON se.id = c.section_id AND se.adviser_id = :t2
                WHERE sm.user_id = u.id AND sm.role = 'student' AND sm.status = 'active' AND (tm.id IS NOT NULL OR se.id IS NOT NULL))";
            $params += ['t1' => (int) $user['id'], 't2' => (int) $user['id']];
        } elseif ($user['role'] !== 'admin') {
            throw HttpException::notFound();
        }
        $student = Db::one($sql, $params);
        if (!$student) {
            throw HttpException::notFound();
        }
        return $student;
    }

    /** A section the teacher advises. @return array<string,mixed> */
    public static function adviserSection(array $user, int $sectionId): array
    {
        $section = Db::one(
            'SELECT se.*, ap.name AS period_name FROM sections se LEFT JOIN academic_periods ap ON ap.id = se.academic_period_id
             WHERE se.id = :id AND se.organization_id = :o AND se.adviser_id = :u',
            ['id' => $sectionId, 'o' => (int) $user['organization_id'], 'u' => (int) $user['id']]
        );
        if (!$section) {
            throw HttpException::notFound();
        }
        return $section;
    }

    /** SQL condition (alias m) for materials a student may see right now. */
    public static function releasedMaterialSql(string $alias = 'm'): string
    {
        return "$alias.visibility = 'published' AND ($alias.release_at IS NULL OR $alias.release_at <= UTC_TIMESTAMP())";
    }

    /** Student-scoped material lookup: enrolled, published, released. @return array<string,mixed> */
    public static function studentMaterial(array $user, int $materialId): array
    {
        $row = Db::one(
            "SELECT m.*, c.name AS class_name
             FROM learning_materials m
             JOIN classes c ON c.id = m.class_id AND c.status = 'active'
             JOIN class_memberships cm ON cm.class_id = m.class_id AND cm.user_id = :u AND cm.role = 'student' AND cm.status = 'active'
             WHERE m.id = :m AND m.organization_id = :o AND " . self::releasedMaterialSql('m'),
            ['u' => (int) $user['id'], 'm' => $materialId, 'o' => (int) $user['organization_id']]
        );
        if (!$row) {
            throw HttpException::notFound();
        }
        return $row;
    }
}
