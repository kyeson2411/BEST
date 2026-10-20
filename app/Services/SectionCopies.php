<?php

declare(strict_types=1);

namespace Sibol\Services;

use Sibol\Core\Audit;
use Sibol\Core\Db;
use Sibol\Core\HttpException;
use Sibol\Policies\Access;

/**
 * "Copy to other sections": a teacher who teaches several sections of a subject copies a material,
 * an assignment or quiz, or a lesson into the others instead of rebuilding it. Copies always arrive
 * as drafts, only in active classes the teacher teaches.
 *
 * - Modules are matched by title in the target section; without a match the copy has no module and is
 *   listed under "Other materials".
 * - Questions keep their review state, so AI drafts that were not approved stay unapproved there too.
 * - Rubrics come along. Linked materials (assignment resources, lesson sources) point to the target
 *   section's own copy of them: an existing copy, or the same file or link uploaded there, or else a new
 *   draft copy made alongside.
 * - A copied file reuses the stored file and its extracted passages, but the passages are inserted
 *   again with the copy's class and material ids. Retrieval filters on the passage's class and the
 *   material's own visibility inside SQL, so a copy follows its own section's draft, release date and
 *   companion settings and never exposes another section's material. Because copies share stored files,
 *   anything that deletes a material file must first check that no other version still uses its path.
 */
final class SectionCopies
{
    private const MATERIAL_FIELDS = ['title', 'description', 'topic', 'kind', 'url', 'release_at', 'ai_enabled'];
    private const VERSION_FIELDS = ['original_filename', 'stored_path', 'mime_type', 'extension', 'size_bytes', 'sha256', 'page_count', 'char_count'];
    private const ASSIGNMENT_FIELDS = [
        'kind', 'title', 'instructions', 'objectives', 'category', 'opens_at', 'due_at', 'late_policy', 'late_penalty_pct',
        'max_attempts', 'time_limit_minutes', 'points_possible', 'allow_text', 'allow_files', 'allow_resubmission', 'rubric',
        'feedback_release', 'answer_key_release', 'ai_assisted',
    ];
    private const QUESTION_FIELDS = ['position', 'qtype', 'prompt', 'points', 'options', 'answer_key', 'explanation', 'topic', 'source_ref', 'ai_generated', 'reviewed'];
    private const LESSON_FIELDS = ['title', 'grade_level', 'duration_minutes', 'reading_level', 'language', 'lesson_format', 'objectives', 'ai_assisted'];

    /**
     * What the "Copy to other sections" panel needs for one record.
     * @param string $type material|assignment|lesson
     * @return array{action:string,targets:list<array{id:int,name:string,copies:int}>,copies:list<array{class_name:string,url:string,label:string,tone:string}>}
     */
    public static function panel(array $user, string $type, array $record): array
    {
        $paths = ['material' => '/teacher/materials/', 'assignment' => '/teacher/assignments/', 'lesson' => '/teacher/lessons/'];
        return [
            'action' => $paths[$type] . (int) $record['id'] . '/copy',
            'targets' => self::targets($user, $type, $record),
            'copies' => self::copies($user, $type, $record, $paths[$type]),
        ];
    }

    /**
     * Active classes the teacher may copy into (same subject first), with the number of copies already there.
     * @return list<array{id:int,name:string,copies:int}>
     */
    public static function targets(array $user, string $type, array $record): array
    {
        $sourceClassId = (int) $record['class_id'];
        $ids = array_values(array_diff(Access::teacherClassIds($user), [$sourceClassId]));
        if (!$ids) {
            return [];
        }
        [$table, $state] = self::table($type);
        [$in, $p] = Db::in($ids, 'c');
        $p['src'] = (int) $record['id'];
        $p['subject'] = (int) Db::value('SELECT subject_id FROM classes WHERE id = :c', ['c' => $sourceClassId]);
        $rows = Db::all(
            "SELECT c.id, c.name,
                    (SELECT COUNT(*) FROM $table x WHERE x.class_id = c.id AND x.copied_from_id = :src AND x.$state <> 'archived') AS copies
             FROM classes c WHERE c.id IN $in ORDER BY c.subject_id = :subject DESC, c.name",
            $p
        );
        return array_map(static fn (array $r): array => ['id' => (int) $r['id'], 'name' => (string) $r['name'], 'copies' => (int) $r['copies']], $rows);
    }

    /**
     * Copies of the record in other classes the teacher teaches (never in anyone else's classes).
     * @return list<array{class_name:string,url:string,label:string,tone:string}>
     */
    public static function copies(array $user, string $type, array $record, string $path): array
    {
        $ids = array_values(array_diff(Access::teacherClassIds($user, true), [(int) $record['class_id']]));
        if (!$ids) {
            return [];
        }
        [$table, $state] = self::table($type);
        [$in, $p] = Db::in($ids, 'c');
        $p['src'] = (int) $record['id'];
        $rows = Db::all(
            "SELECT x.*, c.name AS class_name FROM $table x JOIN classes c ON c.id = x.class_id
             WHERE x.copied_from_id = :src AND x.class_id IN $in AND x.$state <> 'archived' ORDER BY c.name, x.id",
            $p
        );
        $out = [];
        foreach ($rows as $r) {
            if ($type === 'material') {
                [$label, $tone] = Materials::visibilityLabel($r);
            } elseif ($type === 'assignment') {
                [$label, $tone] = assignment_status($r);
            } else {
                [$label, $tone] = $r['status'] === 'published' ? [t('Published'), 'leaf'] : [t('Draft'), 'neutral'];
            }
            $out[] = ['class_name' => (string) $r['class_name'], 'url' => $path . (int) $r['id'], 'label' => $label, 'tone' => $tone];
        }
        return $out;
    }

    /** @return array{id:int,class_name:string,also_copied:list<string>} */
    public static function material(array $user, int $materialId, int $classId): array
    {
        $source = Access::teacherMaterial($user, $materialId);
        $target = self::target($user, $classId, (int) $source['class_id']);
        $id = (int) Db::transaction(static fn (): int => self::copyMaterial($user, $source, $target));
        return ['id' => $id, 'class_name' => (string) $target['name'], 'also_copied' => []];
    }

    /** @return array{id:int,class_name:string,also_copied:list<string>} */
    public static function assignment(array $user, int $assignmentId, int $classId): array
    {
        $source = Access::teacherAssignment($user, $assignmentId);
        $target = self::target($user, $classId, (int) $source['class_id']);
        $also = [];
        $id = (int) Db::transaction(static function () use ($user, $source, $target, &$also): int {
            $id = Db::insert('assignments', self::only($source, self::ASSIGNMENT_FIELDS) + [
                'organization_id' => (int) $source['organization_id'],
                'class_id' => (int) $target['id'],
                'module_id' => self::module($source['module_id'], (int) $target['id']),
                'status' => 'draft',
                'created_by' => (int) $user['id'],
                'copied_from_id' => (int) $source['id'],
                'created_at' => Db::now(),
                'updated_at' => Db::now(),
            ]);
            foreach (Db::all('SELECT * FROM questions WHERE assignment_id = :a ORDER BY position, id', ['a' => (int) $source['id']]) as $q) {
                Db::insert('questions', self::only($q, self::QUESTION_FIELDS) + ['assignment_id' => $id, 'created_at' => Db::now(), 'updated_at' => Db::now()]);
            }
            foreach (Db::column('SELECT material_id FROM assignment_resources WHERE assignment_id = :a ORDER BY material_id', ['a' => (int) $source['id']]) as $mid) {
                $linked = self::linkedMaterial($user, (int) $mid, $target, $also);
                if ($linked !== null) {
                    Db::exec('INSERT IGNORE INTO assignment_resources (assignment_id, material_id) VALUES (:a, :m)', ['a' => $id, 'm' => $linked]);
                }
            }
            Audit::log('assignment.copied', 'assignment', $id, ['from' => (int) $source['id'], 'class_id' => (int) $target['id']]);
            return $id;
        });
        return ['id' => $id, 'class_name' => (string) $target['name'], 'also_copied' => $also];
    }

    /** @return array{id:int,class_name:string,also_copied:list<string>} */
    public static function lesson(array $user, int $lessonId, int $classId): array
    {
        $source = Db::one('SELECT * FROM lessons WHERE id = :id AND organization_id = :o', ['id' => $lessonId, 'o' => (int) $user['organization_id']]);
        if (!$source || !Access::teaches($user, (int) $source['class_id'])) {
            throw HttpException::notFound();
        }
        $target = self::target($user, $classId, (int) $source['class_id']);
        $also = [];
        $id = (int) Db::transaction(static function () use ($user, $source, $target, &$also): int {
            $content = json_decode((string) $source['draft_content'], true) ?: [];
            $map = [];
            $find = static function (int $materialId) use (&$map, $user, $target, &$also): ?int {
                if (!array_key_exists($materialId, $map)) {
                    $map[$materialId] = $materialId > 0 ? self::linkedMaterial($user, $materialId, $target, $also) : null;
                }
                return $map[$materialId];
            };
            // Sources keep their numbers so citations like [1] still match; one that cannot be carried
            // over keeps its title without a link.
            foreach ((array) ($content['sources'] ?? []) as $i => $s) {
                $content['sources'][$i]['material_id'] = $find((int) ($s['material_id'] ?? 0));
            }
            $materialIds = [];
            foreach ((array) (json_decode((string) $source['source_material_ids'], true) ?: []) as $mid) {
                $copy = $find((int) $mid);
                if ($copy !== null) {
                    $materialIds[] = $copy;
                }
            }
            $id = Db::insert('lessons', self::only($source, self::LESSON_FIELDS) + [
                'organization_id' => (int) $source['organization_id'],
                'class_id' => (int) $target['id'],
                'module_id' => self::module($source['module_id'], (int) $target['id']),
                'status' => 'draft',
                'draft_content' => json_encode($content, JSON_UNESCAPED_UNICODE),
                'source_material_ids' => json_encode(array_values(array_unique($materialIds))),
                'has_unpublished_changes' => 0,
                'created_by' => (int) $user['id'],
                'copied_from_id' => (int) $source['id'],
                'created_at' => Db::now(),
                'updated_at' => Db::now(),
            ]);
            Audit::log('lesson.copied', 'lesson', $id, ['from' => (int) $source['id'], 'class_id' => (int) $target['id']]);
            return $id;
        });
        return ['id' => $id, 'class_name' => (string) $target['name'], 'also_copied' => $also];
    }

    // ------------------------------------------------------------------ helpers

    /** An active class the teacher teaches, other than the record's own; anything else is not found. @return array<string,mixed> */
    private static function target(array $user, int $classId, int $sourceClassId): array
    {
        $class = Access::teacherClass($user, $classId);
        if ($class['status'] !== 'active' || (int) $class['id'] === $sourceClassId) {
            throw HttpException::notFound();
        }
        return $class;
    }

    private static function copyMaterial(array $user, array $source, array $target): int
    {
        $classId = (int) $target['id'];
        $id = Db::insert('learning_materials', self::only($source, self::MATERIAL_FIELDS) + [
            'organization_id' => (int) $source['organization_id'],
            'class_id' => $classId,
            'module_id' => self::module($source['module_id'], $classId),
            'visibility' => 'draft',
            'created_by' => (int) $user['id'],
            'copied_from_id' => (int) $source['id'],
            'created_at' => Db::now(),
            'updated_at' => Db::now(),
        ]);
        $version = $source['kind'] === 'file' && $source['current_version_id']
            ? Db::one('SELECT * FROM material_versions WHERE id = :v', ['v' => (int) $source['current_version_id']])
            : null;
        if ($version) {
            // Text that was read already comes along; anything unfinished is read again for the copy.
            $status = in_array($version['processing_status'], ['ready', 'unsupported'], true) ? (string) $version['processing_status'] : 'uploaded';
            $versionId = Db::insert('material_versions', self::only($version, self::VERSION_FIELDS) + [
                'material_id' => $id,
                'version_no' => 1,
                'processing_status' => $status,
                'processing_error' => $status === 'unsupported' ? $version['processing_error'] : null,
                'processed_at' => $status === 'ready' ? $version['processed_at'] : null,
                'uploaded_by' => (int) $user['id'],
                'created_at' => Db::now(),
            ]);
            if ($status === 'ready') {
                Db::exec(
                    'INSERT INTO material_chunks (organization_id, class_id, material_id, material_version_id, chunk_index, page_from, page_to, section_title, content, char_count, created_at)
                     SELECT organization_id, :class, :material, :version, chunk_index, page_from, page_to, section_title, content, char_count, :now
                     FROM material_chunks WHERE material_version_id = :source ORDER BY chunk_index',
                    ['class' => $classId, 'material' => $id, 'version' => $versionId, 'now' => Db::now(), 'source' => (int) $version['id']]
                );
            }
            Db::update('learning_materials', ['current_version_id' => $versionId], ['id' => $id]);
        }
        Audit::log('material.copied', 'material', $id, ['from' => (int) $source['id'], 'class_id' => $classId]);
        return $id;
    }

    /**
     * The target section's version of a material that an assignment or lesson links to. Archived
     * materials and materials the teacher cannot open are not carried over (null).
     * @param list<string> $also titles of materials copied along, for the teacher's message
     */
    private static function linkedMaterial(array $user, int $materialId, array $target, array &$also): ?int
    {
        $source = Db::one('SELECT * FROM learning_materials WHERE id = :m AND organization_id = :o', ['m' => $materialId, 'o' => (int) $user['organization_id']]);
        if (!$source || $source['visibility'] === 'archived' || !Access::teaches($user, (int) $source['class_id'])) {
            return null;
        }
        $classId = (int) $target['id'];
        if ((int) $source['class_id'] === $classId) {
            return $materialId;
        }
        // A copy of it, or the original it was copied from, or another copy of that original.
        $origin = (int) ($source['copied_from_id'] ?? 0);
        $found = Db::value(
            "SELECT id FROM learning_materials WHERE class_id = :c AND visibility <> 'archived'
               AND (copied_from_id = :s OR (:o1 > 0 AND (id = :o2 OR copied_from_id = :o3)))
             ORDER BY id LIMIT 1",
            ['c' => $classId, 's' => $materialId, 'o1' => $origin, 'o2' => $origin, 'o3' => $origin]
        );
        // The same file or link, uploaded to that section separately.
        if ($found === null && $source['kind'] === 'file' && $source['current_version_id']) {
            $found = Db::value(
                "SELECT m.id FROM learning_materials m JOIN material_versions mv ON mv.id = m.current_version_id
                 WHERE m.class_id = :c AND m.visibility <> 'archived' AND m.kind = 'file'
                   AND mv.sha256 = (SELECT sha256 FROM material_versions WHERE id = :v)
                 ORDER BY m.id LIMIT 1",
                ['c' => $classId, 'v' => (int) $source['current_version_id']]
            );
        } elseif ($found === null && $source['kind'] === 'link') {
            $found = Db::value(
                "SELECT id FROM learning_materials WHERE class_id = :c AND visibility <> 'archived' AND kind = 'link' AND url = :u ORDER BY id LIMIT 1",
                ['c' => $classId, 'u' => (string) $source['url']]
            );
        }
        if ($found !== null) {
            return (int) $found;
        }
        $also[] = (string) $source['title'];
        return self::copyMaterial($user, $source, $target);
    }

    /** The module with the same title in the target class, or none. */
    private static function module($sourceModuleId, int $classId): ?int
    {
        if (!$sourceModuleId) {
            return null;
        }
        $title = Db::value('SELECT title FROM modules WHERE id = :m', ['m' => (int) $sourceModuleId]);
        if ($title === null) {
            return null;
        }
        $key = mb_strtolower(trim((string) $title));
        foreach (Db::all('SELECT id, title FROM modules WHERE class_id = :c ORDER BY position, id', ['c' => $classId]) as $m) {
            if (mb_strtolower(trim((string) $m['title'])) === $key) {
                return (int) $m['id'];
            }
        }
        return null;
    }

    /** @return array{0:string,1:string} table and the column that marks a record archived */
    private static function table(string $type): array
    {
        switch ($type) {
            case 'material':
                return ['learning_materials', 'visibility'];
            case 'assignment':
                return ['assignments', 'status'];
            case 'lesson':
                return ['lessons', 'status'];
        }
        throw new \InvalidArgumentException('Unknown copy type: ' . $type);
    }

    /** @param list<string> $fields @return array<string,mixed> */
    private static function only(array $row, array $fields): array
    {
        return array_intersect_key($row, array_flip($fields));
    }
}
