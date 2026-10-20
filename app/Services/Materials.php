<?php

declare(strict_types=1);

namespace Sibol\Services;

use Sibol\Core\Audit;
use Sibol\Core\Db;
use Sibol\Core\Dates;

final class Materials
{
    /**
     * @param array{class_id:int,organization_id:int,module_id?:?int,title:string,description?:?string,topic?:?string,ai_enabled?:bool,release_at?:?string} $meta
     * @param array{path:string,original:string,mime:string,size:int,sha256:string,ext:string} $stored
     * @return array{0:int,1:int} material id, version id
     */
    public static function createFile(array $meta, array $stored, int $teacherId): array
    {
        return Db::transaction(static function () use ($meta, $stored, $teacherId): array {
            $materialId = Db::insert('learning_materials', [
                'organization_id' => $meta['organization_id'],
                'class_id' => $meta['class_id'],
                'module_id' => $meta['module_id'] ?? null,
                'title' => $meta['title'],
                'description' => $meta['description'] ?? null,
                'topic' => $meta['topic'] ?? null,
                'kind' => 'file',
                'visibility' => 'draft',
                'release_at' => $meta['release_at'] ?? null,
                'ai_enabled' => !empty($meta['ai_enabled'] ?? true) ? 1 : 0,
                'created_by' => $teacherId,
                'created_at' => Db::now(),
                'updated_at' => Db::now(),
            ]);
            $versionId = self::addVersion($materialId, $stored, $teacherId);
            return [$materialId, $versionId];
        });
    }

    public static function createLink(array $meta, string $url, int $teacherId): int
    {
        return Db::insert('learning_materials', [
            'organization_id' => $meta['organization_id'],
            'class_id' => $meta['class_id'],
            'module_id' => $meta['module_id'] ?? null,
            'title' => $meta['title'],
            'description' => $meta['description'] ?? null,
            'topic' => $meta['topic'] ?? null,
            'kind' => 'link',
            'url' => $url,
            'visibility' => 'draft',
            'release_at' => $meta['release_at'] ?? null,
            'ai_enabled' => 0,
            'created_by' => $teacherId,
            'created_at' => Db::now(),
            'updated_at' => Db::now(),
        ]);
    }

    public static function addVersion(int $materialId, array $stored, int $teacherId): int
    {
        $no = (int) Db::value('SELECT COALESCE(MAX(version_no), 0) + 1 FROM material_versions WHERE material_id = :m', ['m' => $materialId]);
        $status = in_array($stored['ext'], Extractor::SUPPORTED, true) ? 'uploaded' : 'unsupported';
        $versionId = Db::insert('material_versions', [
            'material_id' => $materialId,
            'version_no' => $no,
            'original_filename' => $stored['original'],
            'stored_path' => $stored['path'],
            'mime_type' => $stored['mime'],
            'extension' => $stored['ext'],
            'size_bytes' => $stored['size'],
            'sha256' => $stored['sha256'],
            'processing_status' => $status,
            'processing_error' => $status === 'unsupported' ? t('This file type is available to download but cannot be read by the companion.') : null,
            'uploaded_by' => $teacherId,
            'created_at' => Db::now(),
        ]);
        Db::update('learning_materials', ['current_version_id' => $versionId, 'updated_at' => Db::now()], ['id' => $materialId]);
        return $versionId;
    }

    public static function publish(int $materialId, bool $notify = true): void
    {
        $material = Db::one('SELECT m.*, c.name AS class_name FROM learning_materials m JOIN classes c ON c.id = m.class_id WHERE m.id = :m', ['m' => $materialId]);
        if (!$material) {
            return;
        }
        Db::update('learning_materials', ['visibility' => 'published', 'published_at' => $material['published_at'] ?: Db::now(), 'updated_at' => Db::now()], ['id' => $materialId]);
        if ($notify) {
            $scheduled = $material['release_at'] && $material['release_at'] > Db::now();
            if (!$scheduled) {
                Notifier::send(
                    (int) $material['organization_id'],
                    Notifier::classStudents((int) $material['class_id']),
                    'material',
                    t('New material: :title', ['title' => $material['title']]),
                    $material['class_name'],
                    '/student/classes/' . (int) $material['class_id'] . '?tab=materials#material-' . $materialId,
                    'material:' . $materialId
                );
            }
        }
        Audit::log('material.published', 'material', $materialId);
    }

    /** @return array{0:string,1:string} visibility label and tone, aware of scheduled release */
    public static function visibilityLabel(array $material): array
    {
        if ($material['visibility'] === 'published') {
            if ($material['release_at'] && $material['release_at'] > Db::now()) {
                return [t('Scheduled for :date', ['date' => Dates::format((string) $material['release_at'], 'datetime_short')]), 'amber'];
            }
            return [t('Published'), 'leaf'];
        }
        return $material['visibility'] === 'archived' ? [t('Archived'), 'neutral'] : [t('Draft, teachers only'), 'neutral'];
    }

    /** Records that a student opened something (for "Continue learning"). */
    public static function recordView(int $userId, string $type, int $contentId, int $classId): void
    {
        Db::exec(
            'INSERT INTO content_views (user_id, content_type, content_id, class_id, view_count, first_viewed_at, last_viewed_at)
             VALUES (:u, :t, :c, :cl, 1, :n1, :n2)
             ON DUPLICATE KEY UPDATE view_count = view_count + 1, last_viewed_at = VALUES(last_viewed_at)',
            ['u' => $userId, 't' => $type, 'c' => $contentId, 'cl' => $classId, 'n1' => Db::now(), 'n2' => Db::now()]
        );
    }
}
