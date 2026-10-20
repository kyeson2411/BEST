<?php

declare(strict_types=1);

namespace Sibol\Services;

/**
 * Transmutation tables turn an initial grade (the weighted percentage) into the grade a school reports, as
 * DepEd's grading orders do. Each row means "from this initial grade upward, report this grade". A table is
 * used only once an administrator has checked it against the order and switched it on, and Sibol never makes
 * up values: the 2015 table below is the published one, and newer tables are typed in by the school.
 */
final class Transmutation
{
    /**
     * The table published with DepEd Order No. 8, s. 2015, for the school to check line by line.
     * @return list<array{min:float,grade:float}> highest first
     */
    public static function deped2015(): array
    {
        $rows = [
            [100.00, 100], [98.40, 99], [96.80, 98], [95.20, 97], [93.60, 96], [92.00, 95], [90.40, 94], [88.80, 93],
            [87.20, 92], [85.60, 91], [84.00, 90], [82.40, 89], [80.80, 88], [79.20, 87], [77.60, 86], [76.00, 85],
            [74.40, 84], [72.80, 83], [71.20, 82], [69.60, 81], [68.00, 80], [66.40, 79], [64.80, 78], [63.20, 77],
            [61.60, 76], [60.00, 75], [56.00, 74], [52.00, 73], [48.00, 72], [44.00, 71], [40.00, 70], [36.00, 69],
            [32.00, 68], [28.00, 67], [24.00, 66], [20.00, 65], [16.00, 64], [12.00, 63], [8.00, 62], [4.00, 61],
            [0.00, 60],
        ];
        return array_map(static fn (array $r): array => ['min' => (float) $r[0], 'grade' => (float) $r[1]], $rows);
    }

    /**
     * Reads a table typed or pasted one row per line: the lowest initial grade of the row and the grade to
     * report, for example "60.00 75" or a pasted range "60.00 – 61.59 75" (the first and last numbers count).
     * @return list<array{min:float,grade:float}> highest first
     */
    public static function parse(string $text): array
    {
        $rows = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            if (!preg_match_all('/\d+(?:[.,]\d+)?/', $line, $m) || count($m[0]) < 2) {
                continue;
            }
            $numbers = array_map(static fn (string $n): float => (float) str_replace(',', '.', $n), $m[0]);
            $rows[] = ['min' => round($numbers[0], 2), 'grade' => round($numbers[count($numbers) - 1], 2)];
        }
        usort($rows, static fn (array $a, array $b): int => $b['min'] <=> $a['min']);
        return $rows;
    }

    /** One row per line, as parse() reads it back. @param list<array{min:float,grade:float}> $rows */
    public static function format(array $rows): string
    {
        return implode("\n", array_map(static fn (array $r): string => number_format((float) $r['min'], 2, '.', '') . "\t" . fmt_number((float) $r['grade'], 2), $rows));
    }

    /**
     * What is wrong with a table, if anything: it must start at 0, use each initial grade once, stay between
     * 0 and 100, and never report a lower grade for a higher initial grade.
     * @param list<array{min:float,grade:float}> $rows highest first
     * @return list<string>
     */
    public static function problems(array $rows): array
    {
        if (!$rows) {
            return [t('The table is empty.')];
        }
        $problems = [];
        $seen = [];
        foreach ($rows as $r) {
            if ($r['min'] < 0 || $r['min'] > 100 || $r['grade'] < 0 || $r['grade'] > 100) {
                $problems[] = t('Every number must be between 0 and 100 (line with :min).', ['min' => fmt_number($r['min'], 2)]);
            }
            if (isset($seen[(string) $r['min']])) {
                $problems[] = t('The initial grade :min appears more than once.', ['min' => fmt_number($r['min'], 2)]);
            }
            $seen[(string) $r['min']] = true;
        }
        if ((float) $rows[count($rows) - 1]['min'] !== 0.0) {
            $problems[] = t('The lowest row must start at an initial grade of 0, so every grade has a row.');
        }
        for ($i = 1; $i < count($rows); $i++) {
            if ($rows[$i]['grade'] > $rows[$i - 1]['grade']) {
                $problems[] = t('A lower initial grade cannot give a higher grade (:low gives :g1, :high gives :g2).', [
                    'low' => fmt_number($rows[$i]['min'], 2), 'g1' => fmt_number($rows[$i]['grade'], 2),
                    'high' => fmt_number($rows[$i - 1]['min'], 2), 'g2' => fmt_number($rows[$i - 1]['grade'], 2),
                ]);
                break;
            }
        }
        return array_values(array_unique($problems));
    }

    /** The grade a table reports for an initial grade (rounded to two decimals first, as class records do). */
    public static function apply(float $initial, array $rows): float
    {
        $initial = round($initial, 2);
        foreach ($rows as $r) {
            if ($initial >= (float) $r['min']) {
                return (float) $r['grade'];
            }
        }
        return (float) $rows[count($rows) - 1]['grade'];
    }

    /** The school's table when it is checked and switched on, else null. @return list<array{min:float,grade:float}>|null */
    public static function active(array $grading): ?array
    {
        $t = (array) ($grading['transmutation'] ?? []);
        $rows = (array) ($t['rows'] ?? []);
        if (empty($t['enabled']) || empty($t['confirmed']) || !$rows || self::problems($rows)) {
            return null;
        }
        return array_map(static fn (array $r): array => ['min' => (float) $r['min'], 'grade' => (float) $r['grade']], $rows);
    }
}
