<?php

declare(strict_types=1);

namespace Sibol\Services;

use Sibol\Core\Db;

/**
 * The school's learning competencies (for example the MATATAG curriculum's), as the school imports them: Sibol never
 * makes up a code. A question is tagged with the one competency it assesses and a lesson with those it teaches, so
 * class insights can report by competency (Insights::competencies()).
 */
final class Competencies
{
    public const MAX_ROWS = 3000;

    /** CSV columns, with the other names a school's own list may use. */
    public const ALIASES = [
        'code' => ['code', 'lc code', 'competency code', 'learning competency code'],
        'description' => ['description', 'competency', 'learning competency', 'learning competencies'],
        'subject' => ['subject', 'learning area'],
        'grade_level' => ['grade_level', 'grade level', 'grade'],
        'quarter' => ['quarter', 'qtr', 'q'],
    ];

    /**
     * The competencies a class can use: the school's, for the class's subject (or no subject) and grade level (or
     * none), not archived, by quarter and code.
     *
     * @return list<array<string,mixed>>
     */
    public static function forClass(int $classId): array
    {
        $class = Db::one('SELECT organization_id, subject_id, grade_level FROM classes WHERE id = :c', ['c' => $classId]);
        if (!$class) {
            return [];
        }
        $grade = trim((string) ($class['grade_level'] ?? ''));
        return Db::all(
            'SELECT id, code, description, quarter, grade_level, is_sample FROM competencies
             WHERE organization_id = :o AND archived_at IS NULL AND (subject_id = :s OR subject_id IS NULL)'
            . ($grade !== '' ? ' AND (grade_level = :g OR grade_level IS NULL)' : '') . '
             ORDER BY quarter IS NULL, quarter, code',
            ['o' => (int) $class['organization_id'], 's' => (int) $class['subject_id']] + ($grade !== '' ? ['g' => $grade] : [])
        );
    }

    /** A code typed by a teacher, when it is on the class's list. */
    public static function findForClass(int $classId, string $code): ?array
    {
        $code = trim($code);
        foreach (self::forClass($classId) as $c) {
            if (strcasecmp((string) $c['code'], $code) === 0) {
                return $c;
            }
        }
        return null;
    }

    /**
     * Code and description by id, archived ones too (a question keeps its tag when its competency is archived).
     *
     * @param list<int|string|null> $ids
     * @return array<int,array<string,mixed>>
     */
    public static function byIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return [];
        }
        [$in, $params] = Db::in($ids, 'c');
        $out = [];
        foreach (Db::all('SELECT id, code, description, quarter, is_sample, archived_at FROM competencies WHERE id IN ' . $in, $params) as $c) {
            $out[(int) $c['id']] = $c;
        }
        return $out;
    }

    /** Tags a question with the competency it assesses, or clears it. Allowed after students have answered. */
    public static function tagQuestion(int $questionId, ?int $competencyId): void
    {
        Db::update('questions', ['competency_id' => $competencyId, 'updated_at' => Db::now()], ['id' => $questionId]);
    }

    /**
     * Sets a lesson's competencies to those chosen from its class's list (anything else is ignored). Competencies it
     * already had that are no longer on the list, such as archived ones, are kept.
     *
     * @param list<int|string> $chosen
     */
    public static function tagLesson(int $lessonId, int $classId, array $chosen): void
    {
        $offered = array_map('intval', array_column(self::forClass($classId), 'id'));
        $ids = array_values(array_intersect($offered, array_map('intval', $chosen)));
        Db::transaction(static function () use ($lessonId, $offered, $ids): void {
            if ($offered) {
                [$in, $params] = Db::in($offered, 'c');
                Db::exec('DELETE FROM lesson_competencies WHERE lesson_id = :l AND competency_id IN ' . $in, $params + ['l' => $lessonId]);
            }
            foreach ($ids as $id) {
                Db::insert('lesson_competencies', ['lesson_id' => $lessonId, 'competency_id' => $id]);
            }
        });
    }

    /** @return list<array<string,mixed>> a lesson's competencies, by quarter and code */
    public static function ofLesson(int $lessonId): array
    {
        return Db::all(
            'SELECT c.id, c.code, c.description, c.quarter, c.is_sample FROM lesson_competencies lc JOIN competencies c ON c.id = lc.competency_id
             WHERE lc.lesson_id = :l ORDER BY c.quarter IS NULL, c.quarter, c.code',
            ['l' => $lessonId]
        );
    }

    /**
     * Reads an uploaded list and says what importing it would do, row by row, without changing anything.
     *
     * @return array{rows: list<array<string,mixed>>, counts: array<string,int>}|string a message when the file cannot be read
     */
    public static function preview(int $orgId, string $content)
    {
        $parsed = Csv::read($content, self::ALIASES, ['code', 'description'], self::MAX_ROWS, t('The file has a header row but no competencies.'));
        if (is_string($parsed)) {
            return $parsed;
        }
        [$header, $lines] = $parsed;
        $subjects = [];
        foreach (Db::all('SELECT id, name, code FROM subjects WHERE organization_id = :o AND archived_at IS NULL', ['o' => $orgId]) as $s) {
            $subjects[mb_strtolower(trim((string) $s['name']))] = $s;
            if ((string) $s['code'] !== '') {
                $subjects[mb_strtolower(trim((string) $s['code']))] = $s;
            }
        }
        $existing = [];
        foreach (Db::all('SELECT * FROM competencies WHERE organization_id = :o', ['o' => $orgId]) as $c) {
            $existing[mb_strtolower((string) $c['code'])] = $c;
        }
        $rows = [];
        $seen = [];
        $counts = ['new' => 0, 'changed' => 0, 'same' => 0, 'problem' => 0];
        foreach ($lines as $i => $line) {
            $get = static fn (string $key): string => isset($header[$key]) ? trim((string) ($line[$header[$key]] ?? '')) : '';
            $row = [
                'line' => $i + 2,
                'code' => $get('code'),
                'description' => preg_replace('/\s+/u', ' ', $get('description')) ?? '',
                'subject' => $get('subject'),
                'grade_level' => $get('grade_level'),
                'quarter' => $get('quarter'),
                'subject_id' => null,
                'problems' => [],
            ];
            if ($row['code'] === '') {
                $row['problems'][] = t('No code');
            } elseif (mb_strlen($row['code']) > 60 || !preg_match('/^[\p{L}\p{N}][\p{L}\p{N}._\-\/()]*$/u', $row['code'])) {
                $row['problems'][] = t('The code can have letters, numbers and . _ - / ( ), up to 60 characters');
            } elseif (isset($seen[mb_strtolower($row['code'])])) {
                $row['problems'][] = t('The same code is on line :n', ['n' => $seen[mb_strtolower($row['code'])]]);
            }
            if ($row['description'] === '') {
                $row['problems'][] = t('No description');
            } elseif (mb_strlen($row['description']) > 1000) {
                $row['problems'][] = t('The description is longer than 1,000 characters');
            }
            if ($row['subject'] !== '') {
                $subject = $subjects[mb_strtolower($row['subject'])] ?? null;
                if ($subject === null) {
                    $row['problems'][] = t('No subject called ":subject"', ['subject' => $row['subject']]);
                } else {
                    $row['subject_id'] = (int) $subject['id'];
                    $row['subject'] = (string) $subject['name'];
                }
            }
            if ($row['quarter'] !== '') {
                $quarter = (int) preg_replace('/\D/', '', $row['quarter']);
                if ($quarter < 1 || $quarter > 4) {
                    $row['problems'][] = t('The quarter must be 1 to 4');
                } else {
                    $row['quarter'] = (string) $quarter;
                }
            }
            if (mb_strlen($row['grade_level']) > 40) {
                $row['problems'][] = t('The grade level is longer than 40 characters');
            }
            if ($row['code'] !== '' && !isset($seen[mb_strtolower($row['code'])])) {
                $seen[mb_strtolower($row['code'])] = $row['line'];
            }
            $old = $existing[mb_strtolower($row['code'])] ?? null;
            if ($row['problems']) {
                $row['status'] = 'problem';
            } elseif ($old === null) {
                $row['status'] = 'new';
            } else {
                $same = (string) $old['description'] === $row['description'] && (int) $old['subject_id'] === (int) $row['subject_id']
                    && (string) $old['grade_level'] === $row['grade_level'] && (string) $old['quarter'] === $row['quarter'] && $old['archived_at'] === null;
                $row['status'] = $same ? 'same' : 'changed';
                $row['id'] = (int) $old['id'];
            }
            $counts[$row['status']]++;
            $rows[] = $row;
        }
        return ['rows' => $rows, 'counts' => $counts];
    }

    /**
     * Adds the new rows of a preview and updates the changed ones (an archived code comes back). Rows with problems
     * are left out. Nothing is ever deleted: codes missing from the file stay.
     *
     * @param list<array<string,mixed>> $rows
     * @return array{added: int, updated: int}
     */
    public static function import(int $orgId, array $rows): array
    {
        $added = 0;
        $updated = 0;
        Db::transaction(static function () use ($orgId, $rows, &$added, &$updated): void {
            foreach ($rows as $row) {
                $values = [
                    'description' => $row['description'],
                    'subject_id' => $row['subject_id'],
                    'grade_level' => $row['grade_level'] !== '' ? $row['grade_level'] : null,
                    'quarter' => $row['quarter'] !== '' ? (int) $row['quarter'] : null,
                    'updated_at' => Db::now(),
                ];
                if ($row['status'] === 'new') {
                    Db::insert('competencies', $values + ['organization_id' => $orgId, 'code' => $row['code'], 'created_at' => Db::now()]);
                    $added++;
                } elseif ($row['status'] === 'changed') {
                    Db::update('competencies', $values + ['archived_at' => null], ['id' => (int) $row['id'], 'organization_id' => $orgId]);
                    $updated++;
                }
            }
        });
        return ['added' => $added, 'updated' => $updated];
    }
}
