<?php

declare(strict_types=1);

namespace Sibol\Services;

use Sibol\Core\Db;
use Sibol\Core\HttpException;

/**
 * A teacher's own saved feedback comments, to insert while grading. A comment can be tied to a rubric criterion by
 * its title, so it shows next to the same criterion on any assignment. Only its owner sees or uses it.
 */
final class CommentBank
{
    public const MAX_BODY = 2000;
    public const MAX_CRITERION = 160;

    /** The teacher's comments, the most used first. @return list<array<string,mixed>> */
    public static function forTeacher(int $teacherId): array
    {
        return Db::all('SELECT * FROM comment_bank WHERE user_id = :u ORDER BY uses DESC, updated_at DESC, id DESC', ['u' => $teacherId]);
    }

    /**
     * Comments for a grading page: those tied to one of the assignment's rubric criteria, by criterion (matched by
     * title, ignoring case), then all the others.
     * @param list<array<string,mixed>> $rubric the assignment's criteria (id, title)
     * @return array{criteria:array<string,array{title:string,comments:list<array<string,mixed>>}>,general:list<array<string,mixed>>,count:int}
     */
    public static function forGrading(int $teacherId, array $rubric): array
    {
        $titles = [];
        foreach ($rubric as $c) {
            $titles[self::key((string) ($c['title'] ?? ''))] = (string) $c['title'];
        }
        $out = ['criteria' => [], 'general' => [], 'count' => 0];
        foreach (self::forTeacher($teacherId) as $comment) {
            $out['count']++;
            $key = self::key((string) ($comment['criterion'] ?? ''));
            if ($key !== '' && isset($titles[$key])) {
                $out['criteria'][$key]['title'] = $titles[$key];
                $out['criteria'][$key]['comments'][] = $comment;
            } else {
                $out['general'][] = $comment;
            }
        }
        // Criteria in the rubric's own order.
        $ordered = [];
        foreach (array_keys($titles) as $key) {
            if (isset($out['criteria'][$key])) {
                $ordered[$key] = $out['criteria'][$key];
            }
        }
        $out['criteria'] = $ordered;
        return $out;
    }

    /** A comment the teacher owns; anyone else's is not found. @return array<string,mixed> */
    public static function own(array $teacher, int $id): array
    {
        $row = Db::one('SELECT * FROM comment_bank WHERE id = :id AND user_id = :u', ['id' => $id, 'u' => (int) $teacher['id']]);
        if (!$row) {
            throw HttpException::notFound();
        }
        return $row;
    }

    /** @return array<string,string> problems by field */
    public static function problems(string $body, string $criterion): array
    {
        $problems = [];
        if (trim($body) === '') {
            $problems['body'] = t('Write the comment.');
        } elseif (mb_strlen($body) > self::MAX_BODY) {
            $problems['body'] = t('Keep a saved comment under :n characters.', ['n' => number_format(self::MAX_BODY)]);
        }
        if (mb_strlen($criterion) > self::MAX_CRITERION) {
            $problems['criterion'] = t('Keep the criterion under :n characters.', ['n' => self::MAX_CRITERION]);
        }
        return $problems;
    }

    public static function add(array $teacher, string $body, string $criterion): int
    {
        return Db::insert('comment_bank', [
            'organization_id' => (int) $teacher['organization_id'],
            'user_id' => (int) $teacher['id'],
            'body' => trim($body),
            'criterion' => trim($criterion) !== '' ? trim($criterion) : null,
            'created_at' => Db::now(),
            'updated_at' => Db::now(),
        ]);
    }

    public static function update(array $teacher, int $id, string $body, string $criterion): void
    {
        self::own($teacher, $id);
        Db::update('comment_bank', ['body' => trim($body), 'criterion' => trim($criterion) !== '' ? trim($criterion) : null, 'updated_at' => Db::now()], ['id' => $id]);
    }

    public static function delete(array $teacher, int $id): void
    {
        self::own($teacher, $id);
        Db::delete('comment_bank', ['id' => $id]);
    }

    /** Counts a use, so the comments a teacher reaches for most come first. */
    public static function used(array $teacher, int $id): void
    {
        self::own($teacher, $id);
        Db::exec('UPDATE comment_bank SET uses = uses + 1, last_used_at = :n WHERE id = :id', ['n' => Db::now(), 'id' => $id]);
    }

    /**
     * Criterion titles from the rubrics on this teacher's assignments, to suggest when tying a comment to one.
     * @return list<string>
     */
    public static function criterionTitles(array $teacher): array
    {
        $titles = [];
        $rows = Db::column(
            "SELECT a.rubric FROM assignments a
             JOIN class_memberships cm ON cm.class_id = a.class_id AND cm.user_id = :u AND cm.role = 'teacher' AND cm.status = 'active'
             WHERE a.rubric IS NOT NULL AND a.rubric <> ''",
            ['u' => (int) $teacher['id']]
        );
        foreach ($rows as $raw) {
            foreach ((array) (json_decode((string) $raw, true) ?: []) as $c) {
                $title = trim((string) ($c['title'] ?? ''));
                if ($title !== '') {
                    $titles[self::key($title)] = $title;
                }
            }
        }
        natcasesort($titles);
        return array_values($titles);
    }

    private static function key(string $title): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $title)));
    }
}
