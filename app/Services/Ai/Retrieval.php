<?php

declare(strict_types=1);

namespace Sibol\Services\Ai;

use Sibol\Core\Db;
use Sibol\Policies\Access;

/**
 * Permission-filtered retrieval over material_chunks (MySQL full-text search).
 *
 * The permission filter (classes the user belongs to, release rules, AI-enabled,
 * current version, finished processing) is part of the same SQL statement that ranks
 * passages, so nothing outside the user's scope can reach prompt construction.
 */
final class Retrieval
{
    public const MAX_SOURCES = 6;

    /**
     * @return list<array{n:int,chunk_id:int,material_id:int,version_id:int,class_id:int,title:string,extension:?string,page_from:?int,page_to:?int,section_title:?string,content:string,score:float}>
     */
    public static function search(array $user, string $query, ?int $classId = null, ?int $materialId = null, int $limit = self::MAX_SOURCES): array
    {
        $classIds = self::allowedClassIds($user, $classId);
        $terms = self::cleanQuery($query);
        if ($classIds === [] || $terms === '') {
            return [];
        }
        [$in, $params] = Db::in($classIds, 'cls');
        $params['q1'] = $terms;
        $params['q2'] = $terms;
        $materialSql = '';
        if ($materialId !== null) {
            $materialSql = ' AND c.material_id = :mid';
            $params['mid'] = $materialId;
        }
        $rows = Db::all(
            'SELECT c.id AS chunk_id, c.material_id, c.material_version_id AS version_id, c.class_id, c.page_from, c.page_to,
                    c.section_title, c.content, m.title, mv.extension,
                    MATCH(c.content) AGAINST (:q1 IN NATURAL LANGUAGE MODE) AS score
             FROM material_chunks c
             JOIN learning_materials m ON m.id = c.material_id AND m.current_version_id = c.material_version_id
             JOIN material_versions mv ON mv.id = c.material_version_id AND mv.processing_status = \'ready\'
             WHERE c.class_id IN ' . $in . ' AND m.ai_enabled = 1 AND ' . self::visibilitySql($user) . $materialSql . '
               AND MATCH(c.content) AGAINST (:q2 IN NATURAL LANGUAGE MODE) > 0
             ORDER BY score DESC
             LIMIT ' . (int) max(1, min(12, $limit * 2)),
            $params
        );
        if (!$rows) {
            return [];
        }
        // Keep passages that are reasonably close to the best match.
        $top = (float) $rows[0]['score'];
        $rows = array_values(array_filter($rows, static fn ($r) => (float) $r['score'] >= $top * 0.3));
        // Full-text ranking can surface a passage that shares a single common word with a long
        // question ("stop" in "jeepney stop"); for longer questions require two distinct terms.
        $keywords = self::keywords($terms);
        $need = count($keywords) >= 4 ? 2 : 1;
        $rows = array_values(array_filter($rows, static fn ($r) => self::coverage((string) $r['content'], $keywords) >= $need));
        return self::number(array_slice($rows, 0, $limit));
    }

    /** Distinct content words of a question (English and Filipino function words removed). @return list<string> */
    public static function keywords(string $query): array
    {
        static $stop = null;
        $stop = $stop ?? array_flip([
            'the', 'and', 'for', 'are', 'was', 'were', 'what', 'how', 'why', 'when', 'where', 'which', 'who', 'whom', 'does', 'did', 'can', 'could',
            'would', 'should', 'this', 'that', 'these', 'those', 'with', 'from', 'into', 'about', 'there', 'their', 'they', 'them', 'then', 'than',
            'have', 'has', 'had', 'you', 'your', 'our', 'its', 'not', 'but', 'all', 'any', 'some', 'also', 'very', 'just', 'like', 'please', 'tell',
            'explain', 'give', 'show', 'describe', 'help', 'need', 'want', 'know', 'mean', 'means', 'simple', 'simply', 'terms', 'words', 'example',
            'examples', 'easy', 'thing', 'things', 'something', 'kind', 'way', 'ways', 'again', 'really', 'much', 'many', 'between', 'will', 'been',
            'being', 'each', 'other', 'only', 'same', 'such', 'more', 'most', 'own', 'too', 'out', 'over', 'under', 'one', 'two',
            'ang', 'mga', 'ano', 'bakit', 'paano', 'kung', 'para', 'ito', 'iyan', 'yan', 'siya', 'sila', 'kami', 'tayo', 'ako', 'ikaw', 'din', 'rin',
            'lang', 'naman', 'may', 'wala', 'hindi', 'isang', 'nang', 'kay', 'nito', 'niya', 'nila', 'natin', 'namin', 'po', 'ba',
        ]);
        preg_match_all('/[\p{L}\p{N}]{3,}/u', mb_strtolower($query), $m);
        $out = [];
        foreach ($m[0] as $word) {
            if (!isset($stop[$word])) {
                $out[$word] = true;
            }
        }
        return array_keys($out);
    }

    /** How many of the keywords appear in the passage (prefix match, so "chambers" finds "chamber"). */
    private static function coverage(string $content, array $keywords): int
    {
        $content = mb_strtolower($content);
        $found = 0;
        foreach ($keywords as $word) {
            $stem = mb_strlen($word) >= 6 ? mb_substr($word, 0, 5) : $word;
            if (preg_match('/(?<![\p{L}\p{N}])' . preg_quote($stem, '/') . '/u', $content)) {
                $found++;
            }
        }
        return $found;
    }

    /**
     * Ordered passages of one material (for summaries), within a character budget.
     * @return list<array<string,mixed>>
     */
    public static function materialPassages(array $user, int $materialId, int $maxChars = 14000): array
    {
        $classIds = self::allowedClassIds($user, null);
        if ($classIds === []) {
            return [];
        }
        [$in, $params] = Db::in($classIds, 'cls');
        $params['mid'] = $materialId;
        $rows = Db::all(
            'SELECT c.id AS chunk_id, c.material_id, c.material_version_id AS version_id, c.class_id, c.page_from, c.page_to,
                    c.section_title, c.content, m.title, mv.extension, 1 AS score
             FROM material_chunks c
             JOIN learning_materials m ON m.id = c.material_id AND m.current_version_id = c.material_version_id
             JOIN material_versions mv ON mv.id = c.material_version_id AND mv.processing_status = \'ready\'
             WHERE c.material_id = :mid AND c.class_id IN ' . $in . ' AND m.ai_enabled = 1 AND ' . self::visibilitySql($user) . '
             ORDER BY c.chunk_index',
            $params
        );
        $out = [];
        $used = 0;
        foreach ($rows as $row) {
            $len = mb_strlen((string) $row['content']);
            if ($used + $len > $maxChars && $out) {
                break;
            }
            $used += $len;
            $out[] = $row;
        }
        return self::number($out);
    }

    /** Human location for a passage: "p. 3", "slide 2" or the section heading. */
    public static function location(array $source): string
    {
        if (!empty($source['page_from'])) {
            if (($source['extension'] ?? '') === 'pptx') {
                return t('slide :n', ['n' => (int) $source['page_from']]);
            }
            return (int) $source['page_from'] === (int) ($source['page_to'] ?? $source['page_from'])
                ? t('p. :n', ['n' => (int) $source['page_from']])
                : t('pp. :a–:b', ['a' => (int) $source['page_from'], 'b' => (int) $source['page_to']]);
        }
        return (string) ($source['section_title'] ?? '');
    }

    /** @return list<int> */
    private static function allowedClassIds(array $user, ?int $classId): array
    {
        if ($user['role'] === 'student') {
            $ids = Access::studentClassIds($user);
        } elseif ($user['role'] === 'teacher') {
            $ids = Access::teacherClassIds($user, true);
        } else {
            return [];
        }
        if ($classId !== null) {
            return in_array($classId, $ids, true) ? [$classId] : [];
        }
        return $ids;
    }

    private static function visibilitySql(array $user): string
    {
        // Students: only published and released. Teachers: their drafts too, never archived.
        return $user['role'] === 'student' ? Access::releasedMaterialSql('m') : "m.visibility <> 'archived'";
    }

    /** Natural-language terms only; boolean operators and markup are stripped. */
    public static function cleanQuery(string $query): string
    {
        $query = mb_substr($query, 0, 400);
        $query = (string) preg_replace('/[+\-><()~*"@\\\\\[\]{}|^$]/u', ' ', $query);
        return trim((string) preg_replace('/\s+/u', ' ', $query));
    }

    /** @param list<array<string,mixed>> $rows */
    private static function number(array $rows): array
    {
        $out = [];
        foreach ($rows as $i => $row) {
            $out[] = [
                'n' => $i + 1,
                'chunk_id' => (int) $row['chunk_id'],
                'material_id' => (int) $row['material_id'],
                'version_id' => (int) $row['version_id'],
                'class_id' => (int) $row['class_id'],
                'title' => (string) $row['title'],
                'extension' => $row['extension'] !== null ? (string) $row['extension'] : null,
                'page_from' => $row['page_from'] !== null ? (int) $row['page_from'] : null,
                'page_to' => $row['page_to'] !== null ? (int) $row['page_to'] : null,
                'section_title' => $row['section_title'] !== null ? (string) $row['section_title'] : null,
                'content' => (string) $row['content'],
                'score' => (float) $row['score'],
            ];
        }
        return $out;
    }
}
