<?php

declare(strict_types=1);

namespace Sibol\Services;

use Sibol\Core\Db;

/**
 * Sections group a grade level's classes in one period, for example Grade 9 Narra, whose science,
 * mathematics and English classes are separate classes in Sibol. A section is found or created from
 * the section name an administrator gives a class, and can have one adviser (a teacher).
 */
final class Sections
{
    /** The section for a class's period, grade level and section name, created when new; null without a name. */
    public static function forClass(int $orgId, ?int $periodId, ?string $gradeLevel, ?string $name): ?int
    {
        $name = mb_substr(trim((string) $name), 0, 60);
        if ($name === '') {
            return null;
        }
        $grade = trim((string) $gradeLevel) !== '' ? trim((string) $gradeLevel) : null;
        $find = static fn () => Db::value(
            'SELECT id FROM sections WHERE organization_id = :o AND name = :n AND academic_period_id <=> :p AND grade_level <=> :g ORDER BY id LIMIT 1',
            ['o' => $orgId, 'n' => $name, 'p' => $periodId, 'g' => $grade]
        );
        $id = $find();
        if ($id) {
            return (int) $id;
        }
        try {
            return Db::insert('sections', [
                'organization_id' => $orgId,
                'academic_period_id' => $periodId,
                'grade_level' => $grade,
                'name' => $name,
                'created_at' => Db::now(),
                'updated_at' => Db::now(),
            ]);
        } catch (\PDOException $e) {
            // Created by someone else at the same moment.
            $id = $find();
            if (!$id) {
                throw $e;
            }
            return (int) $id;
        }
    }

    /** "Grade 9 – Narra", or just the section name when it has no grade level. */
    public static function label(array $section): string
    {
        $grade = trim((string) ($section['grade_level'] ?? ''));
        $name = (string) ($section['name'] ?? $section['section_name'] ?? '');
        return $grade !== '' ? $grade . ' – ' . $name : $name;
    }

    /**
     * Students actively enrolled in any active class of the section, with their account details.
     * @return list<array<string,mixed>>
     */
    public static function students(int $sectionId): array
    {
        return Db::all(
            "SELECT DISTINCT u.id, u.given_name, u.family_name, u.preferred_name, u.username, u.email, u.status, u.avatar_path, u.last_login_at
             FROM class_memberships cm
             JOIN classes c ON c.id = cm.class_id AND c.status = 'active' AND c.section_id = :s
             JOIN users u ON u.id = cm.user_id
             WHERE cm.role = 'student' AND cm.status = 'active'
             ORDER BY u.family_name, u.given_name",
            ['s' => $sectionId]
        );
    }

    /**
     * Sections the teacher advises, with class and student counts.
     * @return list<array<string,mixed>>
     */
    public static function advisedBy(array $teacher): array
    {
        return Db::all(
            "SELECT se.*, ap.name AS period_name,
                    (SELECT COUNT(*) FROM classes c WHERE c.section_id = se.id AND c.status = 'active') AS classes,
                    (SELECT COUNT(DISTINCT cm.user_id) FROM class_memberships cm JOIN classes c ON c.id = cm.class_id AND c.status = 'active'
                      WHERE c.section_id = se.id AND cm.role = 'student' AND cm.status = 'active') AS students
             FROM sections se LEFT JOIN academic_periods ap ON ap.id = se.academic_period_id
             WHERE se.organization_id = :o AND se.adviser_id = :u
             ORDER BY se.grade_level, se.name",
            ['o' => (int) $teacher['organization_id'], 'u' => (int) $teacher['id']]
        );
    }

    /** Group label for a student on a printed slip: their section, else their first class. */
    public static function groupFor(int $studentId): string
    {
        $row = Db::one(
            "SELECT se.name, se.grade_level, c.name AS class_name
             FROM class_memberships cm JOIN classes c ON c.id = cm.class_id AND c.status = 'active'
             LEFT JOIN sections se ON se.id = c.section_id
             WHERE cm.user_id = :u AND cm.role = 'student' AND cm.status = 'active'
             ORDER BY se.id IS NULL, c.name LIMIT 1",
            ['u' => $studentId]
        );
        if (!$row) {
            return '';
        }
        return $row['name'] !== null ? self::label($row) : (string) $row['class_name'];
    }
}
