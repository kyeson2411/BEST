<?php

declare(strict_types=1);

namespace Sibol\Services;

use DateTimeImmutable;
use DateTimeZone;
use Sibol\Core\Db;

/**
 * The weekly pilot report for administrators: how Sibol is used, week by week (Monday to Sunday in the
 * school's time zone). Counts only: no rankings and no lists of people. Any number that comes from fewer
 * than MIN_GROUP people is hidden (null), so nobody can be picked out; a zero is shown as zero.
 */
final class PilotReport
{
    public const MIN_GROUP = 5;
    public const WEEK_CHOICES = [4, 8, 12, 26];
    /** Shares of the online work due that week; empty when nothing was due. */
    public const RATES = ['on_time', 'late', 'missing'];

    /**
     * Week boundaries, oldest first: $count weeks ending with the current one.
     * @return list<array{start:DateTimeImmutable,end:DateTimeImmutable,current:bool}> end is the next Monday 00:00
     */
    public static function weeks(int $count, DateTimeImmutable $now, DateTimeZone $zone): array
    {
        $local = $now->setTimezone($zone);
        $monday = $local->setTime(0, 0)->modify('-' . ((int) $local->format('N') - 1) . ' days');
        $weeks = [];
        for ($i = $count - 1; $i >= 0; $i--) {
            $start = $monday->modify('-' . $i . ' weeks');
            $weeks[] = ['start' => $start, 'end' => $start->modify('+1 week'), 'current' => $i === 0];
        }
        return $weeks;
    }

    /**
     * One row per week, newest first.
     * @return list<array<string,mixed>>
     */
    public static function rows(int $orgId, int $count, ?DateTimeImmutable $now = null, ?DateTimeZone $zone = null): array
    {
        $now = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $zone = $zone ?? new DateTimeZone(self::timezone($orgId));
        $weeks = self::weeks(max(1, $count), $now, $zone);
        $bounds = array_map(static fn ($w) => self::utc($w['start']), $weeks);
        $bounds[] = self::utc($weeks[count($weeks) - 1]['end']);
        $nowUtc = self::utc($now);

        $active = [];
        foreach (self::activePeople($orgId, $bounds) as $r) {
            $active[(int) $r['wk']][(string) $r['role']] = (int) $r['people'];
        }
        $materials = self::byWeek(self::published('learning_materials', 'm', $orgId, $bounds));
        $assignments = self::byWeek(self::published('assignments', 'a', $orgId, $bounds));
        $due = self::byWeek(self::dueWork($orgId, $bounds, $nowUtc));
        $companion = self::byWeek(self::aiUse($orgId, $bounds));
        $practice = self::byWeek(self::practice($orgId, $bounds));
        $feedback = [];
        foreach (self::feedback($orgId, $bounds) as $r) {
            $feedback[(int) $r['wk']][(string) $r['category']] = $r;
        }

        $rows = [];
        foreach ($weeks as $i => $week) {
            $d = $due[$i] ?? null;
            $expected = (int) ($d['expected'] ?? 0);
            $dueShown = $expected > 0 && (int) $d['students'] >= self::MIN_GROUP;
            $row = [
                'start' => $week['start']->format('Y-m-d'),
                'end' => $week['end']->modify('-1 day')->format('Y-m-d'),
                'current' => $week['current'],
                'students' => self::shown($active[$i]['student'] ?? 0, $active[$i]['student'] ?? 0),
                'teachers' => self::shown($active[$i]['teacher'] ?? 0, $active[$i]['teacher'] ?? 0),
                'materials' => self::shown((int) ($materials[$i]['items'] ?? 0), (int) ($materials[$i]['people'] ?? 0)),
                'assignments' => self::shown((int) ($assignments[$i]['items'] ?? 0), (int) ($assignments[$i]['people'] ?? 0)),
                'due' => self::shown($expected, (int) ($d['students'] ?? 0)),
                'on_time' => $dueShown ? percent((int) $d['on_time'], $expected, 1) : null,
                'late' => $dueShown ? percent((int) $d['late'], $expected, 1) : null,
                'missing' => $dueShown ? percent($expected - (int) $d['on_time'] - (int) $d['late'], $expected, 1) : null,
                'companion' => self::shown((int) ($companion[$i]['items'] ?? 0), (int) ($companion[$i]['people'] ?? 0)),
                'companion_students' => self::shown((int) ($companion[$i]['people'] ?? 0), (int) ($companion[$i]['people'] ?? 0)),
                'practice' => self::shown((int) ($practice[$i]['items'] ?? 0), (int) ($practice[$i]['people'] ?? 0)),
                'practice_students' => self::shown((int) ($practice[$i]['people'] ?? 0), (int) ($practice[$i]['people'] ?? 0)),
            ];
            foreach (SiteFeedback::CATEGORIES as $category) {
                $f = $feedback[$i][$category] ?? null;
                $row['feedback_' . $category] = self::shown((int) ($f['items'] ?? 0), (int) ($f['people'] ?? 0));
            }
            $rows[] = $row;
        }
        return array_reverse($rows);
    }

    /**
     * The report as CSV text. Hidden numbers say "hidden"; rates are empty in a week with no work due.
     * @param list<array<string,mixed>> $rows
     */
    public static function csv(array $rows): string
    {
        $columns = self::columns();
        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so spreadsheet apps read the headings correctly
        fputcsv($out, array_merge([t('Week starting'), t('Week ending')], array_values($columns)));
        foreach ($rows as $row) {
            $line = [$row['start'], $row['end']];
            foreach (array_keys($columns) as $key) {
                $value = $row[$key];
                if (in_array($key, self::RATES, true) && $row['due'] === 0) {
                    $line[] = '';
                } elseif ($value === null) {
                    $line[] = t('hidden');
                } else {
                    $line[] = is_float($value) ? number_format($value, 1, '.', '') : (string) $value;
                }
            }
            fputcsv($out, $line);
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);
        return $csv;
    }

    /** @return array<string,string> row key => heading, in report order */
    public static function columns(): array
    {
        return [
            'students' => t('Active students'),
            'teachers' => t('Active teachers'),
            'materials' => t('Materials published'),
            'assignments' => t('Assignments and quizzes published'),
            'due' => t('Online work due'),
            'on_time' => t('Handed in on time (%)'),
            'late' => t('Handed in late (%)'),
            'missing' => t('Missing (%)'),
            'companion' => t('Questions to the companion'),
            'companion_students' => t('Students using the companion'),
            'practice' => t('Practice sets made'),
            'practice_students' => t('Students practising'),
            'feedback_problem' => t('Feedback: not working'),
            'feedback_confusing' => t('Feedback: confusing'),
            'feedback_idea' => t('Feedback: ideas'),
        ];
    }

    /** A number is shown only when no people or at least MIN_GROUP people are behind it. */
    public static function shown(int $value, int $people): ?int
    {
        return $people > 0 && $people < self::MIN_GROUP ? null : $value;
    }

    /** Students and teachers who did anything recorded that week: signed in, opened work, handed in, used AI. */
    private static function activePeople(int $orgId, array $bounds): array
    {
        $parts = [];
        $params = [];
        $sources = [
            'a' => ['a.created_at', 'a.actor_id', 'audit_logs a', 'a.organization_id = :ao AND a.actor_id IS NOT NULL'],
            's' => ['s.submitted_at', 's.student_id', 'submissions s', 's.organization_id = :so'],
            'e' => ['e.created_at', 'e.user_id', 'ai_usage_events e', 'e.organization_id = :eo AND e.user_id IS NOT NULL'],
            'v' => ['v.last_viewed_at', 'v.user_id', 'content_views v JOIN users vu ON vu.id = v.user_id', 'vu.organization_id = :vo'],
        ];
        foreach ($sources as $p => [$column, $person, $from, $where]) {
            [$bucket, $bp] = self::bucket($column, $bounds, $p);
            [$range, $rp] = self::range($column, $bounds, $p);
            $parts[] = "SELECT $bucket AS wk, $person AS uid FROM $from WHERE $where AND $range";
            $params += $bp + $rp + [$p . 'o' => $orgId];
        }
        return Db::all(
            'SELECT x.wk, u.role, COUNT(DISTINCT x.uid) AS people FROM (' . implode(' UNION ALL ', $parts) . ") x
             JOIN users u ON u.id = x.uid AND u.role IN ('student','teacher')
             GROUP BY x.wk, u.role",
            $params
        );
    }

    /** Materials or assignments first published each week, and how many teachers published them. */
    private static function published(string $table, string $alias, int $orgId, array $bounds): array
    {
        [$bucket, $bp] = self::bucket("$alias.published_at", $bounds, 'p');
        [$range, $rp] = self::range("$alias.published_at", $bounds, 'p');
        return Db::all(
            "SELECT $bucket AS wk, COUNT(*) AS items, COUNT(DISTINCT $alias.created_by) AS people
             FROM $table $alias WHERE $alias.organization_id = :o AND $range GROUP BY wk",
            $bp + $rp + ['o' => $orgId]
        );
    }

    /**
     * Online work (assignments and quizzes) whose due date for each student fell that week and has passed:
     * pairs of student and work expected (excused work left out), handed in on time, handed in late.
     */
    private static function dueWork(int $orgId, array $bounds, string $nowUtc): array
    {
        $due = 'COALESCE(ov.due_at, a.due_at)';
        [$bucket, $bp] = self::bucket($due, $bounds, 'd');
        [$range, $rp] = self::range($due, $bounds, 'd');
        return Db::all(
            "SELECT $bucket AS wk, COUNT(*) AS expected, COUNT(DISTINCT cm.user_id) AS students,
                    SUM(sub.late IS NOT NULL AND sub.late = 0) AS on_time, SUM(sub.late IS NOT NULL AND sub.late = 1) AS late
             FROM assignments a
             JOIN classes c ON c.id = a.class_id AND c.status = 'active'
             JOIN class_memberships cm ON cm.class_id = a.class_id AND cm.role = 'student' AND cm.status = 'active'
             LEFT JOIN assignment_overrides ov ON ov.assignment_id = a.id AND ov.student_id = cm.user_id
             LEFT JOIN (SELECT assignment_id, student_id, MIN(is_late) AS late FROM submissions
                        WHERE organization_id = :so AND status = 'submitted' GROUP BY assignment_id, student_id) sub
                    ON sub.assignment_id = a.id AND sub.student_id = cm.user_id
             WHERE a.organization_id = :o AND a.status IN ('published','closed') AND a.kind IN ('assignment','quiz')
               AND COALESCE(ov.excused, 0) = 0 AND $range AND $due < :now
             GROUP BY wk",
            $bp + $rp + ['o' => $orgId, 'so' => $orgId, 'now' => $nowUtc]
        );
    }

    /** Questions students asked the companion that got an answer. */
    private static function aiUse(int $orgId, array $bounds): array
    {
        [$bucket, $bp] = self::bucket('e.created_at', $bounds, 'c');
        [$range, $rp] = self::range('e.created_at', $bounds, 'c');
        return Db::all(
            "SELECT $bucket AS wk, COUNT(*) AS items, COUNT(DISTINCT e.user_id) AS people FROM ai_usage_events e
             WHERE e.organization_id = :o AND e.feature = 'companion' AND e.user_role = 'student' AND e.status = 'ok' AND $range
             GROUP BY wk",
            $bp + $rp + ['o' => $orgId]
        );
    }

    /** Practice sets students made. */
    private static function practice(int $orgId, array $bounds): array
    {
        [$bucket, $bp] = self::bucket('p.created_at', $bounds, 'q');
        [$range, $rp] = self::range('p.created_at', $bounds, 'q');
        return Db::all(
            "SELECT $bucket AS wk, COUNT(*) AS items, COUNT(DISTINCT p.user_id) AS people FROM practice_sets p
             JOIN users u ON u.id = p.user_id AND u.role = 'student'
             WHERE p.organization_id = :o AND $range GROUP BY wk",
            $bp + $rp + ['o' => $orgId]
        );
    }

    private static function feedback(int $orgId, array $bounds): array
    {
        [$bucket, $bp] = self::bucket('f.created_at', $bounds, 'f');
        [$range, $rp] = self::range('f.created_at', $bounds, 'f');
        return Db::all(
            "SELECT $bucket AS wk, f.category, COUNT(*) AS items, COUNT(DISTINCT f.user_id) AS people FROM site_feedback f
             WHERE f.organization_id = :o AND $range GROUP BY wk, f.category",
            $bp + $rp + ['o' => $orgId]
        );
    }

    /**
     * SQL giving the week number (0 = oldest) of a UTC time column, with its parameters (prefix $p, so
     * several can share one query).
     * @param list<string> $bounds week starts in UTC, then the end of the last week
     * @return array{0:string,1:array<string,string>}
     */
    private static function bucket(string $column, array $bounds, string $p): array
    {
        $last = count($bounds) - 2;
        if ($last === 0) {
            return ['0', []];
        }
        $sql = 'CASE';
        $params = [];
        for ($i = 1; $i <= $last; $i++) {
            $sql .= " WHEN $column < :{$p}b$i THEN " . ($i - 1);
            $params[$p . 'b' . $i] = $bounds[$i];
        }
        return [$sql . " ELSE $last END", $params];
    }

    /** @return array{0:string,1:array<string,string>} */
    private static function range(string $column, array $bounds, string $p): array
    {
        return ["$column >= :{$p}from AND $column < :{$p}to", [$p . 'from' => $bounds[0], $p . 'to' => $bounds[count($bounds) - 1]]];
    }

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> week number => row */
    private static function byWeek(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['wk']] = $r;
        }
        return $out;
    }

    private static function utc(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private static function timezone(int $orgId): string
    {
        $tz = (string) Db::value('SELECT timezone FROM organizations WHERE id = :o', ['o' => $orgId]);
        return in_array($tz, DateTimeZone::listIdentifiers(), true) ? $tz : 'Asia/Manila';
    }
}
