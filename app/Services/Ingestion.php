<?php

declare(strict_types=1);

namespace Sibol\Services;

use Sibol\Core\Db;
use Sibol\Core\Logger;
use Sibol\Core\Uploads;

/**
 * Upload → extract → chunk → index, with real, persisted status at each step:
 * uploaded → extracting → indexing → ready (or failed / unsupported).
 * A version is never "ready" until its chunks exist in the full-text index.
 */
final class Ingestion
{
    public const MAX_ATTEMPTS = 3;

    /** @return array{status:string,error:?string,chunks:int,pages:?int} */
    public static function process(int $versionId): array
    {
        $version = Db::one(
            'SELECT mv.*, m.class_id, m.organization_id, m.title AS material_title, m.created_by AS material_owner
             FROM material_versions mv JOIN learning_materials m ON m.id = mv.material_id WHERE mv.id = :id',
            ['id' => $versionId]
        );
        if (!$version) {
            throw new \RuntimeException('Material version not found.');
        }
        if (in_array($version['processing_status'], ['ready', 'unsupported'], true)) {
            return ['status' => (string) $version['processing_status'], 'error' => $version['processing_error'], 'chunks' => self::chunkCount($versionId), 'pages' => $version['page_count'] !== null ? (int) $version['page_count'] : null];
        }
        @set_time_limit(180);
        Db::exec(
            "UPDATE material_versions SET processing_status = 'extracting', processing_error = NULL, processing_attempts = processing_attempts + 1 WHERE id = :id",
            ['id' => $versionId]
        );

        try {
            $units = Extractor::extract(Uploads::absolute((string) $version['stored_path']), (string) $version['extension']);
            $chars = array_sum(array_map(static fn ($u) => mb_strlen($u['text']), $units));
            if ($chars < 40) {
                throw new ExtractionException(t('No readable text was found. If this is a scanned document or a picture of text, upload a text-based PDF or a Word file instead.'));
            }
            $pages = array_filter(array_map(static fn ($u) => $u['page'], $units));
            $pageCount = $pages ? (int) max($pages) : null;
            Db::update('material_versions', ['processing_status' => 'indexing', 'page_count' => $pageCount, 'char_count' => $chars], ['id' => $versionId]);

            $chunks = Chunker::chunk($units);
            Db::transaction(static function () use ($version, $versionId, $chunks): void {
                Db::delete('material_chunks', ['material_version_id' => $versionId]);
                foreach ($chunks as $i => $chunk) {
                    Db::insert('material_chunks', [
                        'organization_id' => (int) $version['organization_id'],
                        'class_id' => (int) $version['class_id'],
                        'material_id' => (int) $version['material_id'],
                        'material_version_id' => $versionId,
                        'chunk_index' => $i,
                        'page_from' => $chunk['page'],
                        'page_to' => $chunk['page'],
                        'section_title' => $chunk['section'] !== null ? mb_substr($chunk['section'], 0, 255) : null,
                        'content' => $chunk['text'],
                        'char_count' => mb_strlen($chunk['text']),
                        'created_at' => Db::now(),
                    ]);
                }
                Db::update('material_versions', ['processing_status' => 'ready', 'processed_at' => Db::now()], ['id' => $versionId]);
            });
            return ['status' => 'ready', 'error' => null, 'chunks' => count($chunks), 'pages' => $pageCount];
        } catch (ExtractionException $e) {
            $status = $e->unsupported ? 'unsupported' : 'failed';
            self::markProblem($version, $status, $e->getMessage());
            return ['status' => $status, 'error' => $e->getMessage(), 'chunks' => 0, 'pages' => null];
        } catch (\Throwable $e) {
            Logger::error('Ingestion failed', ['version' => $versionId, 'error' => $e->getMessage(), 'at' => $e->getFile() . ':' . $e->getLine()]);
            $message = t('Something went wrong while reading this file. Try again; if it keeps failing, upload it in another format.');
            self::markProblem($version, 'failed', $message);
            return ['status' => 'failed', 'error' => $message, 'chunks' => 0, 'pages' => null];
        }
    }

    /** Processes versions waiting in the queue (CLI: php bin/sibol.php materials:process). @return list<string> */
    public static function processPending(int $limit = 25): array
    {
        $rows = Db::all(
            "SELECT mv.id, mv.original_filename FROM material_versions mv
             WHERE mv.processing_status IN ('uploaded', 'extracting', 'indexing')
                OR (mv.processing_status = 'failed' AND mv.processing_attempts < " . self::MAX_ATTEMPTS . ')
             ORDER BY mv.id LIMIT ' . (int) $limit
        );
        $out = [];
        foreach ($rows as $row) {
            $result = self::process((int) $row['id']);
            $out[] = sprintf('#%d %s: %s%s', (int) $row['id'], $row['original_filename'], $result['status'], $result['error'] ? ' (' . $result['error'] . ')' : '');
        }
        return $out;
    }

    public static function chunkCount(int $versionId): int
    {
        return (int) Db::value('SELECT COUNT(*) FROM material_chunks WHERE material_version_id = :v', ['v' => $versionId]);
    }

    /** Human label + tone for a processing status. @return array{0:string,1:string} */
    public static function statusLabel(?string $status): array
    {
        switch ($status) {
            case 'ready':
                return [t('Ready for the companion'), 'leaf'];
            case 'uploaded':
                return [t('Waiting to be read'), 'amber'];
            case 'extracting':
                return [t('Reading text'), 'amber'];
            case 'indexing':
                return [t('Indexing'), 'amber'];
            case 'failed':
                return [t('Could not be read'), 'coral'];
            case 'unsupported':
                return [t('Download only'), 'neutral'];
            default:
                return [t('Link'), 'neutral'];
        }
    }

    private static function markProblem(array $version, string $status, string $message): void
    {
        Db::update('material_versions', ['processing_status' => $status, 'processing_error' => mb_substr($message, 0, 500)], ['id' => (int) $version['id']]);
        if ($status === 'failed' && !empty($version['uploaded_by'])) {
            Notifier::send(
                (int) $version['organization_id'],
                [(int) $version['uploaded_by']],
                'processing',
                t('“:title” could not be read', ['title' => $version['material_title']]),
                $message,
                '/teacher/materials/' . (int) $version['material_id'],
                'processing:' . (int) $version['id'] . ':' . ((int) $version['processing_attempts'] + 1)
            );
        }
    }
}
