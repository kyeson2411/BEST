<?php

declare(strict_types=1);

namespace Sibol\Services;

use DateTimeImmutable;
use DateTimeZone;
use Sibol\Core\Db;

/**
 * Grading periods (quarters or terms) inside an academic period. An assignment counts in the period its due
 * date falls in, in the school's time zone, unless its teacher placed it in another period of the same
 * academic period. Work dated outside every period counts in none, and the gradebook says so.
 * Without grading periods everything works as before: one overall grade per class.
 */
final class GradingPeriods
{
    public const MAX = 6;

    /** @return list<array<string,mixed>> in date order */
    public static function forAcademicPeriod(?int $academicPeriodId): array
    {
        if (!$academicPeriodId) {
            return [];
        }
        return Db::all('SELECT * FROM grading_periods WHERE academic_period_id = :p ORDER BY starts_on, position', ['p' => $academicPeriodId]);
    }

    /** @return list<array<string,mixed>> the grading periods of a class's academic period */
    public static function forClass(int $classId): array
    {
        return self::forAcademicPeriod((int) Db::value('SELECT academic_period_id FROM classes WHERE id = :c', ['c' => $classId]) ?: null);
    }

    /** All of a school's grading periods, keyed by academic period id. @return array<int,list<array<string,mixed>>> */
    public static function forOrganization(int $orgId): array
    {
        $out = [];
        foreach (Db::all('SELECT * FROM grading_periods WHERE organization_id = :o ORDER BY starts_on, position', ['o' => $orgId]) as $p) {
            $out[(int) $p['academic_period_id']][] = $p;
        }
        return $out;
    }

    public static function zone(int $orgId): DateTimeZone
    {
        $tz = (string) Db::value('SELECT timezone FROM organizations WHERE id = :o', ['o' => $orgId]);
        return new DateTimeZone(in_array($tz, DateTimeZone::listIdentifiers(), true) ? $tz : 'Asia/Manila');
    }

    /**
     * The period an assignment counts in: the one its teacher chose (if it belongs to these periods), else the
     * one containing its class due date. Students' own due dates do not move work between periods.
     * @param list<array<string,mixed>> $periods
     */
    public static function effective(array $assignment, array $periods, DateTimeZone $zone): ?int
    {
        $chosen = (int) ($assignment['grading_period_id'] ?? 0);
        foreach ($periods as $p) {
            if ((int) $p['id'] === $chosen) {
                return $chosen;
            }
        }
        $date = self::localDate($assignment['due_at'] ?? null, $zone);
        return $date === null ? null : self::containing($date, $periods);
    }

    /** @param list<array<string,mixed>> $periods */
    public static function containing(string $localDate, array $periods): ?int
    {
        foreach ($periods as $p) {
            if ($localDate >= (string) $p['starts_on'] && $localDate <= (string) $p['ends_on']) {
                return (int) $p['id'];
            }
        }
        return null;
    }

    /**
     * The period to show first: the one today falls in, else the last one that has ended, else the first.
     * @param list<array<string,mixed>> $periods
     */
    public static function current(array $periods, DateTimeZone $zone, ?DateTimeImmutable $now = null): ?int
    {
        if (!$periods) {
            return null;
        }
        $today = ($now ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimezone($zone)->format('Y-m-d');
        $found = self::containing($today, $periods);
        if ($found !== null) {
            return $found;
        }
        $ended = array_values(array_filter($periods, static fn (array $p): bool => (string) $p['ends_on'] < $today));
        return (int) ($ended ? $ended[count($ended) - 1]['id'] : $periods[0]['id']);
    }

    public static function localDate(?string $utc, DateTimeZone $zone): ?string
    {
        if ($utc === null || $utc === '') {
            return null;
        }
        return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone($zone)->format('Y-m-d');
    }

    /**
     * What is wrong with a period about to be saved: its dates must fall inside the academic period, start
     * before they end, and not overlap another period of the same academic period.
     * @param array<string,mixed> $academic academic_periods row
     * @param list<array<string,mixed>> $others the academic period's other grading periods
     * @return list<string>
     */
    public static function problems(array $academic, string $start, string $end, array $others): array
    {
        $problems = [];
        if ($end < $start) {
            $problems[] = t('The end date is before the start date.');
        }
        if ($start < (string) $academic['starts_on'] || $end > (string) $academic['ends_on']) {
            $problems[] = t('The dates must fall within :period (:from to :to).', [
                'period' => $academic['name'],
                'from' => fmt_date($academic['starts_on'] . ' 12:00:00', 'date_short'),
                'to' => fmt_date($academic['ends_on'] . ' 12:00:00', 'date_short'),
            ]);
        }
        foreach ($others as $o) {
            if (!($end < (string) $o['starts_on'] || $start > (string) $o['ends_on'])) {
                $problems[] = t('The dates overlap :name.', ['name' => $o['name']]);
            }
        }
        return $problems;
    }

    /**
     * Splits an academic period into $count periods of about equal length named "<prefix> 1" and so on, for
     * the administrator to adjust to the school calendar. Only when it has no grading periods yet.
     * @param array<string,mixed> $academic academic_periods row
     * @return int periods created
     */
    public static function split(array $academic, int $count, string $prefix): int
    {
        if (self::forAcademicPeriod((int) $academic['id']) || $count < 1 || $count > self::MAX) {
            return 0;
        }
        $utc = new DateTimeZone('UTC');
        $start = new DateTimeImmutable((string) $academic['starts_on'], $utc);
        $end = new DateTimeImmutable((string) $academic['ends_on'], $utc);
        $days = (int) $start->diff($end)->days + 1;
        $length = intdiv($days, $count);
        if ($length < 1) {
            return 0;
        }
        for ($i = 0; $i < $count; $i++) {
            $from = $start->modify('+' . ($i * $length) . ' days');
            $to = $i === $count - 1 ? $end : $from->modify('+' . ($length - 1) . ' days');
            Db::insert('grading_periods', [
                'organization_id' => (int) $academic['organization_id'],
                'academic_period_id' => (int) $academic['id'],
                'name' => $prefix . ' ' . ($i + 1),
                'position' => $i + 1,
                'starts_on' => $from->format('Y-m-d'),
                'ends_on' => $to->format('Y-m-d'),
                'created_at' => Db::now(),
                'updated_at' => Db::now(),
            ]);
        }
        return $count;
    }
}
