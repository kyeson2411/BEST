<?php

declare(strict_types=1);

namespace Sibol\Services;

use Sibol\Core\Db;
use Sibol\Services\Ai\Retrieval;

/**
 * "What my materials don't cover": topics students asked the companion about that their class materials did not
 * answer. Only a question's key words are stored, with the class and the student (to count different students);
 * never the question, the answer or the conversation. A teacher sees a topic only once at least MIN_STUDENTS
 * different students asked about it, and never who asked.
 */
final class MaterialGaps
{
    public const MIN_STUDENTS = 5;
    /** How far back the report looks, and how long rows are kept. */
    public const DAYS = 30;
    public const KEEP_DAYS = 60;
    private const MAX_KEYWORDS = 6;

    /** Notes a student's question when the class materials did not cover it (the same test as the answer's notice). */
    public static function record(array $user, array $conversation, string $question, array $sources): void
    {
        if (($user['role'] ?? '') !== 'student' || $conversation['class_id'] === null
            || in_array((string) $conversation['mode'], ['study_plan', 'summarize'], true)
            || !Settings::get((int) $user['organization_id'], 'features.document_answers', true)
            || Retrieval::namesNoTopic($question)) {
            return;
        }
        if ($sources !== [] && !Retrieval::partialMatch($question, $sources)) {
            return;
        }
        $keywords = array_values(array_filter(
            Retrieval::keywords(Retrieval::cleanQuery($question)),
            static fn (string $w): bool => !ctype_digit($w) && mb_strlen($w) <= 30
        ));
        if ($keywords === []) {
            return;
        }
        Db::insert('material_gaps', [
            'organization_id' => (int) $user['organization_id'],
            'class_id' => (int) $conversation['class_id'],
            'student_id' => (int) $user['id'],
            'keywords' => mb_substr(implode(' ', array_slice($keywords, 0, self::MAX_KEYWORDS)), 0, 255),
            'created_at' => Db::now(),
        ]);
    }

    /**
     * Topics for one class over the last DAYS days, most students first. A topic is a key word asked about by at
     * least MIN_STUDENTS different students, with the words often asked together with it (each of those also asked
     * by at least MIN_STUDENTS students), so no single student's wording shows.
     * @return list<array{words:list<string>,students:int,questions:int,last_at:string}>
     */
    public static function forClass(int $classId, int $days = self::DAYS): array
    {
        $rows = Db::all(
            'SELECT student_id, keywords, created_at FROM material_gaps WHERE class_id = :c AND created_at >= :since',
            ['c' => $classId, 'since' => gmdate('Y-m-d H:i:s', time() - $days * 86400)]
        );
        $byWord = [];
        foreach ($rows as $i => $row) {
            foreach (array_unique(explode(' ', (string) $row['keywords'])) as $word) {
                if ($word !== '') {
                    $byWord[$word][] = $i;
                }
            }
        }
        $students = static function (array $rowIds) use ($rows): int {
            return count(array_unique(array_map(static fn (int $i): int => (int) $rows[$i]['student_id'], $rowIds)));
        };
        $qualifying = array_filter($byWord, static fn (array $ids): bool => $students($ids) >= self::MIN_STUDENTS);
        uksort($qualifying, static function (string $a, string $b) use ($qualifying, $students): int {
            return [$students($qualifying[$b]), count($qualifying[$b]), $a] <=> [$students($qualifying[$a]), count($qualifying[$a]), $b];
        });

        $topics = [];
        $used = [];
        foreach ($qualifying as $word => $ids) {
            if (isset($used[$word])) {
                continue;
            }
            $words = [$word];
            $used[$word] = true;
            // Words asked together with this one in at least half of its questions, by enough different students.
            foreach ($qualifying as $other => $otherIds) {
                if (isset($used[$other])) {
                    continue;
                }
                $together = array_values(array_intersect($ids, $otherIds));
                if (count($together) * 2 >= count($ids) && $students($together) >= self::MIN_STUDENTS) {
                    $words[] = $other;
                    $used[$other] = true;
                }
            }
            // In the order students wrote them ("blood types inheritance"), from the latest question with them all.
            $order = [];
            foreach ($ids as $i) {
                $asked = explode(' ', (string) $rows[$i]['keywords']);
                if (!array_diff($words, $asked) && (string) $rows[$i]['created_at'] >= (string) ($order['at'] ?? '')) {
                    $order = ['at' => (string) $rows[$i]['created_at'], 'words' => array_flip($asked)];
                }
            }
            if ($order) {
                usort($words, static fn (string $a, string $b): int => $order['words'][$a] <=> $order['words'][$b]);
            }
            $topics[] = [
                'words' => $words,
                'students' => $students($ids),
                'questions' => count($ids),
                'last_at' => max(array_map(static fn (int $i): string => (string) $rows[$i]['created_at'], $ids)),
            ];
        }
        return $topics;
    }

    /** Removes rows older than KEEP_DAYS. */
    public static function purge(): int
    {
        return Db::exec('DELETE FROM material_gaps WHERE created_at < :cut', ['cut' => gmdate('Y-m-d H:i:s', time() - self::KEEP_DAYS * 86400)]);
    }
}
