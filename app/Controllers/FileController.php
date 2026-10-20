<?php

declare(strict_types=1);

namespace Sibol\Controllers;

use Sibol\Core\Db;
use Sibol\Core\HttpException;
use Sibol\Core\Response;
use Sibol\Core\Uploads;
use Sibol\Policies\Access;
use Sibol\Services\Materials;

/** Every stored file is served through here, after an authorisation check. */
final class FileController extends Controller
{
    public function material(int $id): Response
    {
        $user = $this->user();
        if ($user['role'] === 'student') {
            $material = Access::studentMaterial($user, $id);
            Materials::recordView((int) $user['id'], 'material', $id, (int) $material['class_id']);
        } elseif ($user['role'] === 'teacher') {
            $material = Access::teacherMaterial($user, $id);
        } else {
            // Administrators manage operations, not class content.
            throw HttpException::notFound();
        }
        if ($material['kind'] === 'link') {
            return Response::redirect((string) $material['url'], 302);
        }
        $versionId = $this->request->int('version');
        if ($versionId !== null && $user['role'] === 'teacher') {
            $version = Db::one('SELECT * FROM material_versions WHERE id = :v AND material_id = :m', ['v' => $versionId, 'm' => $id]);
        } else {
            $version = Db::one('SELECT * FROM material_versions WHERE id = :v', ['v' => (int) $material['current_version_id']]);
        }
        if (!$version) {
            throw HttpException::notFound();
        }
        return $this->serve((string) $version['stored_path'], (string) $version['original_filename'], (string) $version['extension']);
    }

    public function submissionFile(int $id): Response
    {
        $user = $this->user();
        $file = Db::one(
            'SELECT f.*, s.student_id, s.assignment_id, s.organization_id FROM submission_files f JOIN submissions s ON s.id = f.submission_id WHERE f.id = :id',
            ['id' => $id]
        );
        if (!$file || (int) $file['organization_id'] !== (int) $user['organization_id']) {
            throw HttpException::notFound();
        }
        $allowed = false;
        if ($user['role'] === 'student') {
            $allowed = (int) $file['student_id'] === (int) $user['id'];
        } elseif ($user['role'] === 'teacher') {
            $classId = (int) Db::value('SELECT class_id FROM assignments WHERE id = :a', ['a' => (int) $file['assignment_id']]);
            $allowed = Access::teaches($user, $classId);
        }
        if (!$allowed) {
            throw HttpException::notFound();
        }
        $ext = strtolower(pathinfo((string) $file['original_filename'], PATHINFO_EXTENSION));
        return $this->serve((string) $file['stored_path'], (string) $file['original_filename'], $ext);
    }

    public function avatar(int $id): Response
    {
        $user = $this->user();
        $person = Db::one('SELECT avatar_path FROM users WHERE id = :id AND organization_id = :o', ['id' => $id, 'o' => (int) $user['organization_id']]);
        if (!$person || !$person['avatar_path']) {
            throw HttpException::notFound();
        }
        $ext = strtolower(pathinfo((string) $person['avatar_path'], PATHINFO_EXTENSION));
        return $this->serve((string) $person['avatar_path'], 'avatar.' . $ext, $ext, true)->header('Cache-Control', 'private, max-age=3600');
    }

    public function logo(): Response
    {
        $user = $this->user();
        $path = (string) Db::value('SELECT logo_path FROM organizations WHERE id = :o', ['o' => (int) $user['organization_id']]);
        if ($path === '') {
            throw HttpException::notFound();
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return $this->serve($path, 'logo.' . $ext, $ext, true)->header('Cache-Control', 'private, max-age=3600');
    }

    private function serve(string $relative, string $name, string $ext, bool $forceInline = false): Response
    {
        $absolute = Uploads::absolute($relative);
        if (!is_file($absolute)) {
            throw new HttpException(404, t('The file is missing from storage. Ask the person who uploaded it to upload it again.'));
        }
        $mime = Uploads::SERVE_MIME[$ext] ?? 'application/octet-stream';
        $inlineTypes = ['pdf', 'txt', 'md', 'jpg', 'png', 'webp'];
        $inline = $forceInline || (in_array($ext, $inlineTypes, true) && !$this->request->bool('download'));
        $response = Response::file($absolute, $name, $mime, $inline);
        if ($ext === 'pdf' && $inline) {
            // The browser's PDF viewer must be allowed to run; nosniff and the PDF type still
            // prevent the file being treated as a page, and framing stays blocked.
            $response->header('Content-Security-Policy', "frame-ancestors 'none'");
        }
        return $response;
    }
}
