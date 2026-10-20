<?php

declare(strict_types=1);

namespace Sibol\Services;

use DateTimeImmutable;
use DateTimeZone;
use Sibol\Core\Db;

/**
 * "Who needs a nudge": students in a class with two or more pieces of work missing or handed in late among those
 * due in the last 14 days, by each student's own due date, with excused work left out. Listed by name only: never
 * ranked or scored. A nudge is a neutral in-app reminder, at most one a day per student and class.
 */
final class Nudges
{
    public const DAYS = 14;
    public const AT_LEAST = 2;

    /**
     * @return list<array{student:array<string,mixed>,missing:list<string>,late:list<string>,nudged_on:?string}>
     */
    public static function forClass(int $classId, DateTimeImmutable $now): array
    {
        $utc = $now->setTimezone(new DateTimeZone('UTC'));
        $due = Overrides::dueSql('a');
        $rows = Db::all(
            "SELECT u.id, u.given_name, u.family_name, u.preferred_name, u.username,
                    a.title, a.kind, ov.absent, s.id AS submission_id, s.is_late
             FROM class_memberships cm
             JOIN users u ON u.id = cm.user_id AND u.status = 'active'
             JOIN assignments a ON a.class_id = cm.class_id AND a.status IN ('published','closed')
             LEFT JOIN assignment_overrides ov ON ov.assignment_id = a.id AND ov.student_id = u.id
             LEFT JOIN submissions s ON s.id = (SELECT s2.id FROM submissions s2 WHERE s2.assignment_id = a.id AND s2.student_id = u.id
                                                  AND s2.status IN ('submitted','returned') ORDER BY s2.attempt_no LIMIT 1)
             WHERE cm.class_id = :c AND cm.role = 'student' AND cm.status = 'active'
               AND " . Overrides::notExcusedSql() . " AND $due >= :from AND $due <= :to
             ORDER BY u.family_name, u.given_name, u.id, $due",
            ['c' => $classId, 'from' => $utc->modify('-' . self::DAYS . ' days')->format('Y-m-d H:i:s'), 'to' => $utc->format('Y-m-d H:i:s')]
        );
        $students = [];
        foreach ($rows as $r) {
            $sid = (int) $r['id'];
            $students[$sid] = $students[$sid] ?? ['student' => $r, 'missing' => [], 'late' => [], 'nudged_on' => null];
            if ($r['submission_id'] !== null) {
                if ((int) $r['is_late'] === 1) {
                    $students[$sid]['late'][] = (string) $r['title'];
                }
            } elseif ($r['kind'] !== 'paper' || (int) $r['absent'] === 1) {
                // Work done in class with no score yet is not missing; a student marked absent is.
                $students[$sid]['missing'][] = (string) $r['title'];
            }
        }
        $list = array_values(array_filter($students, static fn (array $s): bool => count($s['missing']) + count($s['late']) >= self::AT_LEAST));
        if ($list) {
            [$in, $params] = Db::in(array_map(static fn (array $s): int => (int) $s['student']['id'], $list), 'u');
            $last = [];
            foreach (Db::all("SELECT user_id, MAX(created_at) AS at FROM notifications WHERE user_id IN $in AND dedupe_key LIKE :k GROUP BY user_id", $params + ['k' => 'nudge:' . $classId . ':%']) as $n) {
                $last[(int) $n['user_id']] = (string) $n['at'];
            }
            foreach ($list as &$s) {
                $s['nudged_on'] = $last[(int) $s['student']['id']] ?? null;
            }
            unset($s);
        }
        return $list;
    }

    /**
     * Sends one student a neutral reminder about a class.
     * @return string 'sent', 'already' (they had one today) or 'muted' (they turned reminders off)
     */
    public static function send(array $class, array $student, DateTimeImmutable $now): string
    {
        $orgId = (int) $class['organization_id'];
        $day = $now->setTimezone(GradingPeriods::zone($orgId))->format('Y-m-d');
        $key = 'nudge:' . (int) $class['id'] . ':' . $day;
        if ((int) Db::value('SELECT COUNT(*) FROM notifications WHERE user_id = :u AND dedupe_key = :k', ['u' => (int) $student['id'], 'k' => $key]) > 0) {
            return 'already';
        }
        $sent = Notifier::send(
            $orgId,
            [(int) $student['id']],
            'reminder',
            t('A reminder about :class', ['class' => $class['name']]),
            t('Some work in this class is past its due date or was handed in late. Open your assignments to see what is left, or talk to your teacher if you need more time.'),
            '/student/assignments',
            $key
        );
        return $sent > 0 ? 'sent' : 'muted';
    }
}
