<?php

declare(strict_types=1);

namespace Sibol\Services;

use Sibol\Core\Db;

/**
 * Gradebook arithmetic driven by the school's grading settings. Nothing about the
 * scale or the weights is hard-coded: "points" uses earned/possible over all graded
 * work; "weighted" averages each category and applies the configured weights, which a subject's
 * grading group can replace with its own.
 * Work is "missing" after the student's own due date; excused work is never counted. Work done in class
 * or on paper is never missing by itself: until its score is entered it is "not recorded", and a student
 * marked absent counts as missing until they make it up.
 * With grading periods, each period has its own grade from the work that counts in it, and the final grade
 * is the average of the period grades. The initial grade (the weighted percentage) is transmuted only when
 * the school has checked its table and switched it on.
 */
final class Grades
{
    /**
     * @param bool $releasedOnly true for student-facing views
     * @param int|null $onlyStudent restrict to one student (student view)
     * @return array{assignments:list<array>,students:list<array>,cells:array<int,array<int,array>>,overall:array<int,array>,grading:array,periods:list<array>,by_period:array<int,array<int,array>>,final:array<int,array>,transmuted:bool}
     */
    public static function classBook(int $classId, int $orgId, bool $releasedOnly = false, ?int $onlyStudent = null): array
    {
        $class = Db::one('SELECT c.academic_period_id, s.grading_group FROM classes c JOIN subjects s ON s.id = c.subject_id WHERE c.id = :c', ['c' => $classId]);
        $grading = self::forSubject((array) Settings::get($orgId, 'grading'), $class['grading_group'] ?? null);
        $transmute = Transmutation::active($grading);
        $periods = GradingPeriods::forAcademicPeriod(isset($class['academic_period_id']) ? (int) $class['academic_period_id'] : null);
        $assignments = Db::all(
            "SELECT id, title, kind, category, grading_period_id, due_at, points_possible, status, late_policy
             FROM assignments WHERE class_id = :c AND status IN ('published','closed') ORDER BY COALESCE(due_at, created_at), id",
            ['c' => $classId]
        );
        if ($periods) {
            $zone = GradingPeriods::zone($orgId);
            foreach ($assignments as &$a) {
                $a['period_id'] = GradingPeriods::effective($a, $periods, $zone);
            }
            unset($a);
        }
        $params = ['c' => $classId];
        $studentFilter = '';
        if ($onlyStudent !== null) {
            $studentFilter = ' AND u.id = :only';
            $params['only'] = $onlyStudent;
        }
        $students = Db::all(
            "SELECT u.id, u.given_name, u.family_name, u.preferred_name, u.email, u.username
             FROM class_memberships cm JOIN users u ON u.id = cm.user_id
             WHERE cm.class_id = :c AND cm.role = 'student' AND cm.status = 'active'" . $studentFilter . '
             ORDER BY u.family_name, u.given_name',
            $params
        );
        $cells = [];
        if ($assignments && $students) {
            [$in, $inParams] = Db::in(array_map(static fn ($a) => (int) $a['id'], $assignments), 'a');
            $subs = Db::all(
                "SELECT id, assignment_id, student_id, status, source, score, points_possible, grading_status, feedback_status, is_late, submitted_at, attempt_no
                 FROM submissions WHERE assignment_id IN $in AND status IN ('submitted','returned')",
                $inParams
            );
            $best = [];
            foreach ($subs as $s) {
                $key = (int) $s['student_id'] . ':' . (int) $s['assignment_id'];
                $visible = !$releasedOnly || $s['feedback_status'] === 'released';
                $scored = $s['grading_status'] === 'graded' && $s['score'] !== null && $visible;
                $current = $best[$key] ?? null;
                if ($current === null || ($scored && (!$current['_scored'] || (float) $s['score'] > (float) $current['score']))) {
                    $s['_scored'] = $scored;
                    $best[$key] = $s;
                }
            }
            $now = Db::now();
            $overrides = Overrides::forAssignments(array_map(static fn ($a) => (int) $a['id'], $assignments));
            foreach ($students as $st) {
                foreach ($assignments as $a) {
                    $s = $best[(int) $st['id'] . ':' . (int) $a['id']] ?? null;
                    $ov = $overrides[(int) $st['id'] . ':' . (int) $a['id']] ?? null;
                    $due = $ov['due_at'] ?? $a['due_at'];
                    $possible = (float) $a['points_possible'];
                    $past = $due !== null && $due < $now;
                    if ($ov && (int) $ov['excused'] === 1) {
                        // Excused work is neither counted nor missing, whatever was handed in.
                        $cell = ['state' => 'excused', 'score' => null, 'possible' => $possible, 'percent' => null, 'released' => false, 'late' => false, 'submission_id' => $s ? (int) $s['id'] : null];
                    } elseif ($s && $s['_scored']) {
                        $cell = ['state' => 'graded', 'score' => (float) $s['score'], 'possible' => $possible, 'percent' => $possible > 0 ? round((float) $s['score'] / $possible * 100, 1) : null, 'released' => $s['feedback_status'] === 'released', 'late' => (bool) $s['is_late'], 'submission_id' => (int) $s['id']];
                    } elseif ($s) {
                        $cell = ['state' => $s['status'] === 'returned' ? 'returned' : 'pending', 'score' => null, 'possible' => $possible, 'percent' => null, 'released' => false, 'late' => (bool) $s['is_late'], 'submission_id' => (int) $s['id']];
                    } elseif ($ov && (int) $ov['absent'] === 1 && ($ov['due_at'] === null || $past)) {
                        // Absent from work done in class, with no make-up date still ahead.
                        $cell = ['state' => 'absent', 'score' => null, 'possible' => $possible, 'percent' => null, 'released' => false, 'late' => false, 'submission_id' => null];
                    } elseif ($past) {
                        $cell = ['state' => $a['kind'] === 'paper' ? 'unrecorded' : 'missing', 'score' => null, 'possible' => $possible, 'percent' => null, 'released' => false, 'late' => false, 'submission_id' => null];
                    } else {
                        $cell = ['state' => 'open', 'score' => null, 'possible' => $possible, 'percent' => null, 'released' => false, 'late' => false, 'submission_id' => null];
                    }
                    $cell['extended'] = $ov !== null && $ov['due_at'] !== null;
                    $cell['paper'] = $s !== null && ($s['source'] ?? 'online') === 'paper';
                    $cells[(int) $st['id']][(int) $a['id']] = $cell;
                }
            }
        }
        $overall = [];
        $byPeriod = [];
        $final = [];
        $periodOf = array_column($assignments, 'period_id', 'id');
        foreach ($students as $st) {
            $sid = (int) $st['id'];
            $mine = $cells[$sid] ?? [];
            // With periods, the year's grade is the final grade below; the overall mark covers all work as before.
            $overall[$sid] = self::overall($mine, $assignments, $grading, $periods ? null : $transmute);
            if ($periods) {
                foreach ($periods as $p) {
                    $in = array_filter($mine, static fn (int $aid): bool => ($periodOf[$aid] ?? null) === (int) $p['id'], ARRAY_FILTER_USE_KEY);
                    $byPeriod[$sid][(int) $p['id']] = self::overall($in, $assignments, $grading, $transmute);
                }
                $final[$sid] = self::finalGrade($byPeriod[$sid], $grading, $transmute !== null, count($periods));
            }
        }
        return [
            'assignments' => $assignments, 'students' => $students, 'cells' => $cells, 'overall' => $overall, 'grading' => $grading,
            'periods' => $periods, 'by_period' => $byPeriod, 'final' => $final, 'transmuted' => $transmute !== null,
        ];
    }

    /**
     * The school's grading settings with a subject group's weights in place of the default category weights.
     * @return array<string,mixed> grading settings, with 'group' set to the group's label when one applies
     */
    public static function forSubject(array $grading, ?string $groupKey): array
    {
        foreach ((array) ($grading['groups'] ?? []) as $group) {
            if ($groupKey !== null && (string) $group['key'] === $groupKey) {
                foreach ((array) ($grading['categories'] ?? []) as $i => $c) {
                    $grading['categories'][$i]['weight'] = (float) ($group['weights'][$c['key']] ?? 0);
                }
                $grading['group'] = (string) $group['label'];
                return $grading;
            }
        }
        $grading['group'] = null;
        return $grading;
    }

    /**
     * The final grade: the average of the period grades so far. Transmuted grades average to a whole number,
     * as report cards show them.
     * @param array<int,array> $periodGrades period id => overall() result
     * @return array{grade:?float,periods:int,complete:bool,descriptor:?string}
     */
    public static function finalGrade(array $periodGrades, array $grading, bool $transmuted, int $periodCount): array
    {
        $grades = array_values(array_filter(array_map(static fn (array $g) => $g['grade'], $periodGrades), static fn ($g) => $g !== null));
        if (!$grades) {
            return ['grade' => null, 'periods' => 0, 'complete' => false, 'descriptor' => null];
        }
        $grade = round(array_sum($grades) / count($grades), $transmuted ? 0 : 1);
        return ['grade' => $grade, 'periods' => count($grades), 'complete' => count($grades) === $periodCount, 'descriptor' => self::descriptor($grade, $grading)];
    }

    /**
     * @param array<int,array> $cells assignment id => cell
     * @param list<array{min:float,grade:float}>|null $transmute the school's checked transmutation table, if on
     * @return array{percent:?float,initial:?float,grade:?float,descriptor:?string,counted:int,missing:int,by_category:array<string,array{label:string,weight:float,percent:?float}>,uncategorised:int}
     */
    public static function overall(array $cells, array $assignments, array $grading, ?array $transmute = null): array
    {
        $byId = [];
        foreach ($assignments as $a) {
            $byId[(int) $a['id']] = $a;
        }
        $counted = 0;
        $missing = 0;
        $uncategorised = 0;
        $earned = 0.0;
        $possible = 0.0;
        $cats = [];
        $categoryDefs = [];
        foreach ((array) ($grading['categories'] ?? []) as $c) {
            $categoryDefs[(string) $c['key']] = $c;
            $cats[(string) $c['key']] = ['earned' => 0.0, 'possible' => 0.0];
        }
        foreach ($cells as $aid => $cell) {
            if ($cell['state'] === 'missing' || $cell['state'] === 'absent') {
                $missing++;
            }
            if ($cell['state'] !== 'graded' || $cell['possible'] <= 0) {
                continue;
            }
            $counted++;
            $earned += $cell['score'];
            $possible += $cell['possible'];
            $cat = (string) ($byId[$aid]['category'] ?? '');
            if (isset($cats[$cat])) {
                $cats[$cat]['earned'] += $cell['score'];
                $cats[$cat]['possible'] += $cell['possible'];
            } else {
                $uncategorised++;
            }
        }
        $exact = null;
        $byCategory = [];
        if (($grading['model'] ?? 'points') === 'weighted' && $categoryDefs) {
            $weighted = 0.0;
            $weightUsed = 0.0;
            foreach ($categoryDefs as $key => $def) {
                $pct = $cats[$key]['possible'] > 0 ? $cats[$key]['earned'] / $cats[$key]['possible'] * 100 : null;
                $byCategory[$key] = ['label' => (string) $def['label'], 'weight' => (float) $def['weight'], 'percent' => $pct !== null ? round($pct, 1) : null];
                if ($pct !== null) {
                    $weighted += $pct * (float) $def['weight'];
                    $weightUsed += (float) $def['weight'];
                }
            }
            $exact = $weightUsed > 0 ? $weighted / $weightUsed : null;
        } elseif ($possible > 0) {
            $exact = $earned / $possible * 100;
        }
        $percent = $exact !== null ? round($exact, 1) : null;
        // The initial grade keeps two decimals, as class records do, so transmutation lands on the right row.
        $initial = $exact !== null ? round($exact, 2) : null;
        $grade = $initial !== null && $transmute !== null ? Transmutation::apply($initial, $transmute) : $percent;
        return [
            'percent' => $percent,
            'initial' => $initial,
            'grade' => $grade,
            'descriptor' => $grade !== null ? self::descriptor($grade, $grading) : null,
            'counted' => $counted,
            'missing' => $missing,
            'by_category' => $byCategory,
            'uncategorised' => $uncategorised,
        ];
    }

    public static function descriptor(float $percent, array $grading): ?string
    {
        $descriptors = (array) ($grading['descriptors'] ?? []);
        usort($descriptors, static fn ($a, $b) => (float) $b['min'] <=> (float) $a['min']);
        foreach ($descriptors as $d) {
            if ($percent >= (float) $d['min']) {
                return (string) $d['label'];
            }
        }
        return null;
    }

    /** Category options for assignment forms. @return array<string,string> */
    public static function categoryOptions(int $orgId): array
    {
        $out = ['' => t('No category')];
        foreach ((array) Settings::get($orgId, 'grading.categories', []) as $c) {
            $out[(string) $c['key']] = (string) $c['label'] . ' (' . (float) $c['weight'] . '%)';
        }
        return $out;
    }
}
