<?php

declare(strict_types=1);

namespace Sibol\Core;

/**
 * Upload validation and storage. Files live in storage/uploads (blocked from the web)
 * under random names, and are only ever served through an authorising controller.
 */
final class Uploads
{
    public const MATERIAL = ['pdf', 'docx', 'pptx', 'txt', 'md'];
    public const SUBMISSION = ['pdf', 'docx', 'pptx', 'txt', 'md', 'jpg', 'jpeg', 'png'];
    public const AVATAR = ['jpg', 'jpeg', 'png', 'webp'];
    public const CSV = ['csv'];

    private const DETECTED = [
        'pdf' => ['application/pdf'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
    ];

    public const SERVE_MIME = [
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'txt' => 'text/plain; charset=utf-8',
        'md' => 'text/plain; charset=utf-8',
        'csv' => 'text/csv; charset=utf-8',
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'json' => 'application/json; charset=utf-8',
    ];

    public static function maxBytes(): int
    {
        return (int) Config::get('uploads.max_mb', 20) * 1024 * 1024;
    }

    /**
     * @param array{name:string,type:string,tmp_name:string,error:int,size:int}|null $file
     * @param list<string> $allowed extensions
     * @return string|null a user-facing error, or null when the file is acceptable
     */
    public static function validate(?array $file, array $allowed, ?int $maxBytes = null): ?string
    {
        $maxBytes = $maxBytes ?? self::maxBytes();
        $maxMb = (int) round($maxBytes / 1048576);
        if ($file === null) {
            return t('Choose a file to upload.');
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                return t('The file is larger than the :max MB limit.', ['max' => $maxMb]);
            }
            return $file['error'] === UPLOAD_ERR_PARTIAL
                ? t('The upload did not finish. Try again.')
                : t('The file could not be uploaded. Try again.');
        }
        if (!defined('SIBOL_TESTING') && !is_uploaded_file($file['tmp_name'])) {
            return t('The file could not be uploaded. Try again.');
        }
        if ($file['size'] <= 0) {
            return t('The file is empty.');
        }
        if ($file['size'] > $maxBytes) {
            return t('The file is larger than the :max MB limit.', ['max' => $maxMb]);
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            return t('This file type is not supported. Use :types.', ['types' => strtoupper(implode(', ', array_unique(array_map(static fn ($e) => $e === 'jpeg' ? 'jpg' : $e, $allowed))))]);
        }
        $detected = self::detectMime($file['tmp_name']);
        if (in_array($ext, ['txt', 'md', 'csv'], true)) {
            $head = (string) file_get_contents($file['tmp_name'], false, null, 0, 8192);
            if (!str_starts_with($detected, 'text/') && $detected !== 'application/csv' && $detected !== 'inode/x-empty') {
                return t('The file content does not match its extension.');
            }
            if (str_contains($head, "\0")) {
                return t('The file content does not match its extension.');
            }
            return null;
        }
        if (!in_array($detected, self::DETECTED[$ext] ?? [], true)) {
            return t('The file content does not match its extension.');
        }
        if (in_array($ext, ['docx', 'pptx'], true) && class_exists(\ZipArchive::class)) {
            $zip = new \ZipArchive();
            if ($zip->open($file['tmp_name']) !== true) {
                return t('The document could not be opened. Save it again and re-upload.');
            }
            $marker = $ext === 'docx' ? 'word/document.xml' : 'ppt/presentation.xml';
            $ok = $zip->locateName($marker) !== false;
            $zip->close();
            if (!$ok) {
                return t('The file content does not match its extension.');
            }
        }
        return null;
    }

    /**
     * @param array{name:string,tmp_name:string} $file
     * @return array{path:string,original:string,mime:string,size:int,sha256:string,ext:string}
     */
    public static function store(array $file, string $area): array
    {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $ext = $ext === 'jpeg' ? 'jpg' : $ext;
        $relative = self::newPath($area, $ext);
        $absolute = self::absolute($relative);
        if (!is_dir(dirname($absolute))) {
            mkdir(dirname($absolute), 0750, true);
        }
        $moved = defined('SIBOL_TESTING') ? copy($file['tmp_name'], $absolute) : move_uploaded_file($file['tmp_name'], $absolute);
        if (!$moved) {
            throw new \RuntimeException('Could not store uploaded file.');
        }
        return [
            'path' => $relative,
            'original' => self::cleanName($file['name']),
            'mime' => self::SERVE_MIME[$ext] ?? 'application/octet-stream',
            'size' => (int) filesize($absolute),
            'sha256' => (string) hash_file('sha256', $absolute),
            'ext' => $ext,
        ];
    }

    /**
     * Stores generated content (seed materials, exports) the same way as uploads.
     * @return array{path:string,original:string,mime:string,size:int,sha256:string,ext:string}
     */
    public static function storeContent(string $content, string $area, string $originalName): array
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $relative = self::newPath($area, $ext);
        $absolute = self::absolute($relative);
        if (!is_dir(dirname($absolute))) {
            mkdir(dirname($absolute), 0750, true);
        }
        file_put_contents($absolute, $content);
        return [
            'path' => $relative,
            'original' => self::cleanName($originalName),
            'mime' => self::SERVE_MIME[$ext] ?? 'application/octet-stream',
            'size' => strlen($content),
            'sha256' => hash('sha256', $content),
            'ext' => $ext,
        ];
    }

    public static function absolute(string $relative): string
    {
        $relative = str_replace('\\', '/', $relative);
        if (str_contains($relative, '..') || str_starts_with($relative, '/')) {
            throw new \RuntimeException('Invalid storage path.');
        }
        return rtrim((string) Config::get('uploads.path'), '/\\') . '/' . $relative;
    }

    public static function delete(?string $relative): void
    {
        if ($relative === null || $relative === '') {
            return;
        }
        $path = self::absolute($relative);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public static function cleanName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = (string) preg_replace('/[\x00-\x1F\x7F<>:"\/\\\\|?*]/u', '', $name);
        $name = trim($name, " .");
        if ($name === '') {
            $name = 'file';
        }
        if (mb_strlen($name) > 150) {
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $name = mb_substr(pathinfo($name, PATHINFO_FILENAME), 0, 140) . ($ext ? '.' . $ext : '');
        }
        return $name;
    }

    public static function humanSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024) . ' KB';
        }
        return number_format($bytes / 1048576, 1) . ' MB';
    }

    private static function newPath(string $area, string $ext): string
    {
        $area = preg_replace('/[^a-z0-9_-]/', '', strtolower($area)) ?: 'misc';
        return $area . '/' . gmdate('Y/m') . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
    }

    private static function detectMime(string $path): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $path);
                finfo_close($finfo);
                if (is_string($mime)) {
                    return $mime;
                }
            }
        }
        return 'application/octet-stream';
    }
}
