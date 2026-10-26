<?php

declare(strict_types=1);

namespace Sibol\Controllers;

use Sibol\Core\Db;
use Sibol\Core\HttpException;
use Sibol\Core\Response;
use Sibol\Core\Uploads;
use Sibol\Policies\Access;
use Sibol\Services\Chunker;
use Sibol\Services\Materials;
use Sibol\Services\ReadingVersions;

/** Every stored file is served through here, after an authorisation check. */
final class FileController extends Controller
{
    /** Materials shown as a reading page, and the largest file shown that way (bigger ones open as files). */
    private const READABLE = ['md', 'txt'];
    private const READ_LIMIT = 1000000;
    /** Most characters of a PDF, Word or PowerPoint file's text shown on its reading page; the rest is in the file. */
    private const TEXT_LIMIT = 150000;

    public function material(int $id): Response
    {
        $user = $this->user();
        $material = $this->accessibleMaterial($user, $id);
        if ($user['role'] === 'student') {
            Materials::recordView((int) $user['id'], 'material', $id, (int) $material['class_id']);
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

    /**
     * A file material as a page to read inside Sibol, or one of its other reading versions (?v=simpler, ?v=filipino):
     * approved ones for students, drafts too for teachers, as a preview. A .md or .txt file is shown as it is; a PDF,
     * Word or PowerPoint file as the text Sibol read from it, with buttons to open or download the file itself, so
     * nobody has to download a file just to see what it is. Links open as before; the file stays at /file for
     * downloads and offline copies.
     */
    public function read(int $id): Response
    {
        $user = $this->user();
        $material = $this->accessibleMaterial($user, $id);
        $version = $material['kind'] === 'file'
            ? Db::one('SELECT * FROM material_versions WHERE id = :v', ['v' => (int) $material['current_version_id']])
            : null;
        $absolute = $version ? Uploads::absolute((string) $version['stored_path']) : '';
        $readableOriginal = $version && in_array($version['extension'], self::READABLE, true) && is_file($absolute) && filesize($absolute) <= self::READ_LIMIT;
        $isStudent = $user['role'] === 'student';
        $kinds = $version ? ($isStudent ? ReadingVersions::approvedKinds($id) : array_values(array_intersect(ReadingVersions::KINDS, array_keys(ReadingVersions::forMaterial($id))))) : [];
        $asked = $this->request->str('v');
        $reading = in_array($asked, $kinds, true) ? ReadingVersions::forMaterial($id)[$asked] : null;
        if (!$version) {
            return $this->redirect('/materials/' . $id . '/file');
        }
        if ($isStudent) {
            Materials::recordView((int) $user['id'], 'material', $id, (int) $material['class_id']);
        }
        $units = null;
        $cut = false;
        if ($reading === null && !$readableOriginal) {
            [$units, $cut] = $this->fileText((int) $version['id']);
            $text = implode("\n\n", array_map(static fn ($u) => $u['text'], $units));
        } else {
            $text = $reading !== null ? (string) $reading['content'] : (string) file_get_contents($absolute);
        }
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        }
        $text = str_replace(["\r\n", "\r"], "\n", preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text);
        if ($reading !== null || $version['extension'] === 'md') {
            // The page title already names the material, so a first heading that repeats it is left out.
            if (preg_match('/^\s*#\s+(.+)\n/u', $text, $m) && mb_strtolower(trim($m[1])) === mb_strtolower(trim((string) $material['title']))) {
                $text = (string) mb_substr($text, mb_strlen($m[0]));
            }
            // With no top heading left, "##" sections become the page's second-level headings.
            if (!preg_match('/^#\s/m', $text)) {
                $text = (string) preg_replace('/^#(#+\s)/m', '$1', $text);
            }
        }
        $class = Db::one('SELECT id, name FROM classes WHERE id = :c', ['c' => (int) $material['class_id']]);
        $crumbs = $user['role'] === 'student'
            ? [[t('Classes'), '/student/classes'], [$class['name'], '/student/classes/' . (int) $class['id'] . '?tab=materials'], [$material['title'], null]]
            : [[t('Materials'), '/teacher/materials'], [$material['title'], '/teacher/materials/' . $id], [t('Read'), null]];
        return $this->view('materials/read', [
            'title' => $material['title'],
            'crumbs' => $crumbs,
            'material' => $material,
            'version' => $version,
            'class' => $class,
            'text' => $text,
            'isMarkdown' => $reading !== null || $version['extension'] === 'md',
            'isStudent' => $isStudent,
            'kinds' => $kinds,
            'reading' => $reading,
            'readableOriginal' => $readableOriginal,
            'units' => $units,
            'cut' => $cut,
            'katex' => true,
        ]);
    }

    /**
     * The text read from a file when it was processed, by page or section, up to TEXT_LIMIT characters. Empty while
     * the file is still being read, or when it has no readable text (a scan, or an unsupported type).
     *
     * @return array{0:list<array{page:int|null,section:string|null,text:string}>,1:bool}
     */
    private function fileText(int $versionId): array
    {
        $rows = Db::all(
            "SELECT c.page_from AS page, c.section_title AS section, c.content AS text
             FROM material_chunks c JOIN material_versions v ON v.id = c.material_version_id
             WHERE c.material_version_id = :v AND v.processing_status = 'ready' ORDER BY c.chunk_index",
            ['v' => $versionId]
        );
        $units = [];
        $chars = 0;
        foreach (Chunker::rejoin(array_map(static fn ($r) => ['page' => $r['page'] !== null ? (int) $r['page'] : null, 'section' => $r['section'], 'text' => (string) $r['text']], $rows)) as $unit) {
            if ($chars + mb_strlen($unit['text']) > self::TEXT_LIMIT) {
                if (!$units) {
                    $unit['text'] = (string) mb_substr($unit['text'], 0, self::TEXT_LIMIT);
                    $units[] = $unit;
                }
                return [$units, true];
            }
            $units[] = $unit;
            $chars += mb_strlen($unit['text']);
        }
        return [$units, false];
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

    /** @return array<string,mixed> */
    private function accessibleMaterial(array $user, int $id): array
    {
        if ($user['role'] === 'student') {
            return Access::studentMaterial($user, $id);
        }
        if ($user['role'] === 'teacher') {
            return Access::teacherMaterial($user, $id);
        }
        // Administrators manage operations, not class content.
        throw HttpException::notFound();
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
