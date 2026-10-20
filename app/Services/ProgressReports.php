<?php

declare(strict_types=1);

namespace Sibol\Services;

use Sibol\Core\Db;

/**
 * Progress reports for one grading period, to print or save as PDF: released grades by subject, missing and late
 * work, and released teacher comments. They are for the student and their section's adviser and are not the
 * school's official report card (SF9). Only released results count, as on the student's own pages.
 */
final class ProgressReports
{
    /**
     * @param list<array<string,mixed>> $students the students to report on
     * @param list<int> $classIds classes the viewer may report on, already checked by the caller; each student's report
     *                            lists the ones they are enrolled in
     * @param string|null $period a grading period id from the request; the current period when empty or not in the year
     * @return array{year:?array<string,mixed>,periods:list<array<string,mixed>>,period:?array<string,mixed>,transmuted:bool,reports:array<int,array{rows:list<array<string,mixed>>,comments:list<array<string,mixed>>}>}
     */
    public static function build(int $orgId, array $students, array $classIds, ?string $period): array
    {
        $out = ['year' => null, 'periods' => [], 'period' => null, 'transmuted' => false, 'reports' => []];
        foreach ($students as $s) {
            $out['reports'][(int) $s['id']] = ['rows' => [], 'comments' => []];
        }
        if (!$classIds || !$students) {
            return $out;
        }
        [$in, $params] = Db::in($classIds, 'c');
        $classes = Db::all(
            "SELECT c.id, c.name, c.academic_period_id, s.name AS subject_name,
                    (SELECT GROUP_CONCAT(CONCAT(COALESCE(NULLIF(t.preferred_name, ''), t.given_name), ' ', t.family_name) ORDER BY t.family_name SEPARATOR ', ')
                       FROM class_memberships tm STRAIGHT_JOIN users t ON t.id = tm.user_id
                      WHERE tm.class_id = c.id AND tm.role = 'teacher' AND tm.status = 'active') AS teachers
             FROM classes c JOIN subjects s ON s.id = c.subject_id
             WHERE c.id IN $in AND c.organization_id = :o ORDER BY s.name, c.name",
            $params + ['o' => $orgId]
        );
        // One school year per report: the current one when there is a class in it, else the latest.
        $year = self::year($classes);
        if ($year !== null) {
            $classes = array_values(array_filter($classes, static fn (array $c): bool => (int) $c['academic_period_id'] === (int) $year['id']));
        }
        $periods = GradingPeriods::forAcademicPeriod($year !== null ? (int) $year['id'] : null);
        $chosen = null;
        if ($periods) {
            $ids = array_map(static fn (array $p): int => (int) $p['id'], $periods);
            $chosen = $period !== null && ctype_digit($period) && in_array((int) $period, $ids, true)
                ? (int) $period
                : GradingPeriods::current($periods, GradingPeriods::zone($orgId));
        }
        $out['year'] = $year;
        $out['periods'] = $periods;
        $out['period'] = $chosen !== null ? (array_column($periods, null, 'id')[$chosen] ?? null) : null;

        $periodOf = [];
        $only = count($students) === 1 ? (int) $students[0]['id'] : null;
        foreach ($classes as $c) {
            $book = Grades::classBook((int) $c['id'], $orgId, true, $only);
            $out['transmuted'] = $out['transmuted'] || $book['transmuted'];
            foreach ($book['assignments'] as $a) {
                $periodOf[(int) $a['id']] = $a['period_id'] ?? null;
            }
            foreach ($book['students'] as $st) {
                $sid = (int) $st['id'];
                if (!isset($out['reports'][$sid])) {
                    continue;
                }
                $mine = [];
                foreach ($book['cells'][$sid] ?? [] as $aid => $cell) {
                    if ($chosen === null || $periodOf[$aid] === $chosen) {
                        $mine[$aid] = $cell;
                    }
                }
                // Without grading periods the report covers the whole year so far.
                $grade = $chosen !== null ? ($book['by_period'][$sid][$chosen] ?? null) : ($book['overall'][$sid] ?? null);
                $out['reports'][$sid]['rows'][] = [
                    'class' => $c,
                    'grade' => $grade,
                    'work' => count($mine),
                    'missing' => count(array_filter($mine, static fn (array $cell): bool => $cell['state'] === 'missing' || $cell['state'] === 'absent')),
                    'late' => count(array_filter($mine, static fn (array $cell): bool => $cell['late'])),
                ];
            }
        }

        if ($classes) {
            [$cin, $params] = Db::in(array_map(static fn (array $c): int => (int) $c['id'], $classes), 'k');
            [$sin, $sparams] = Db::in(array_keys($out['reports']), 'u');
            $names = array_column($classes, 'name', 'id');
            $found = Db::all(
                "SELECT s.student_id, s.assignment_id, s.feedback, s.released_at, a.title, a.class_id
                 FROM submissions s JOIN assignments a ON a.id = s.assignment_id
                 WHERE s.student_id IN $sin AND a.class_id IN $cin AND a.status IN ('published','closed')
                   AND s.feedback_status = 'released' AND s.feedback IS NOT NULL AND s.feedback <> ''
                 ORDER BY s.released_at, s.attempt_no",
                $params + $sparams
            );
            $comments = [];
            foreach ($found as $f) {
                $aid = (int) $f['assignment_id'];
                if (!array_key_exists($aid, $periodOf) || ($chosen !== null && $periodOf[$aid] !== $chosen)) {
                    continue;
                }
                $f['class_name'] = (string) ($names[(int) $f['class_id']] ?? '');
                $comments[(int) $f['student_id']][$aid] = $f; // the latest released comment on each piece of work
            }
            foreach ($comments as $sid => $list) {
                $out['reports'][$sid]['comments'] = array_values($list);
            }
        }
        return $out;
    }

    /**
     * The student's section for the report's heading, preferring one in the report's school year, with its adviser.
     * @return array{label:string,adviser:?string}|null
     */
    public static function sectionOf(int $studentId, ?int $yearId): ?array
    {
        $row = Db::one(
            "SELECT se.name, se.grade_level, a.given_name, a.family_name, a.preferred_name, se.adviser_id
             FROM class_memberships cm
             JOIN classes c ON c.id = cm.class_id AND c.status = 'active'
             JOIN sections se ON se.id = c.section_id
             LEFT JOIN users a ON a.id = se.adviser_id
             WHERE cm.user_id = :u AND cm.role = 'student' AND cm.status = 'active'
             ORDER BY (se.academic_period_id <=> :y) DESC, se.id DESC LIMIT 1",
            ['u' => $studentId, 'y' => $yearId]
        );
        if (!$row) {
            return null;
        }
        return ['label' => Sections::label($row), 'adviser' => $row['adviser_id'] !== null ? user_name($row) : null];
    }

    /** The section's active classes, which its adviser reports on. @return list<int> */
    public static function sectionClassIds(int $sectionId): array
    {
        return array_map('intval', Db::column("SELECT id FROM classes WHERE section_id = :s AND status = 'active'", ['s' => $sectionId]));
    }

    /** @param list<array<string,mixed>> $classes @return array<string,mixed>|null */
    private static function year(array $classes): ?array
    {
        $ids = array_values(array_unique(array_filter(array_map(static fn (array $c): int => (int) $c['academic_period_id'], $classes))));
        if (!$ids) {
            return null;
        }
        [$in, $params] = Db::in($ids, 'y');
        return Db::one("SELECT * FROM academic_periods WHERE id IN $in ORDER BY is_current DESC, starts_on DESC LIMIT 1", $params);
    }
}
