<?php

declare(strict_types=1);

namespace Sibol\Services;

use Sibol\Core\Db;

/**
 * Gradebook arithmetic driven by the school's grading settings. Nothing about the
 * scale or the weights is hard-coded: "points" uses earned/possible over all graded
 * work; "weighted" averages each category and applies the configured weights.
 * Work is "missing" after the student's own due date; excused work is never counted. Work done in class
 * or on paper is never missing by itself: until its score is entered it is "not recorded", and a student
 * marked absent counts as missing until they make it up.
 */
final class Grades
{
    /**
     * @param bool $releasedOnly true for student-facing views
     * @param int|null $onlyStudent restrict to one student (student view)
     * @return array{assignments:list<array>,students:list<array>,cells:array<int,array<int,array>>,overall:array<int,array>,grading:array}
     */
    public static function classBook(int $classId, int $orgId, bool $releasedOnly = false, ?int $onlyStudent = null): array
    {
        $grading = (array) Settings::get($orgId, 'grading');
        $assignments = Db::all(
            "SELECT id, title, kind, category, due_at, points_possible, status, late_policy
             FROM assignments WHERE class_id = :c AND status IN ('published','closed') ORDER BY COALESCE(due_at, created_at), id",
            ['c' => $classId]
        );
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
        foreach ($students as $st) {
            $overall[(int) $st['id']] = self::overall($cells[(int) $st['id']] ?? [], $assignments, $grading);
        }
        return ['assignments' => $assignments, 'students' => $students, 'cells' => $cells, 'overall' => $overall, 'grading' => $grading];
    }

    /**
     * @param array<int,array> $cells assignment id => cell
     * @return array{percent:?float,descriptor:?string,counted:int,missing:int,by_category:array<string,array{label:string,weight:float,percent:?float}>,uncategorised:int}
     */
    public static function overall(array $cells, array $assignments, array $grading): array
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
        $percent = null;
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
            $percent = $weightUsed > 0 ? round($weighted / $weightUsed, 1) : null;
        } elseif ($possible > 0) {
            $percent = round($earned / $possible * 100, 1);
        }
        return [
            'percent' => $percent,
            'descriptor' => $percent !== null ? self::descriptor($percent, $grading) : null,
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
