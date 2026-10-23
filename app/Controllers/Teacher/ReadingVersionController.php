<?php

declare(strict_types=1);

namespace Sibol\Controllers\Teacher;

use Sibol\Controllers\Controller;
use Sibol\Core\HttpException;
use Sibol\Core\Response;
use Sibol\Policies\Access;
use Sibol\Services\Ai\AiException;
use Sibol\Services\Ai\Providers;
use Sibol\Services\Ai\Usage;
use Sibol\Services\ReadingVersions;

/** A teacher drafts, edits and approves the simpler English and Filipino versions of a material. */
final class ReadingVersionController extends Controller
{
    public function edit(int $id, string $kind): Response
    {
        $user = $this->user();
        [$material, $kind] = $this->load($user, $id, $kind);
        $version = ReadingVersions::forMaterial($id)[$kind] ?? null;
        return $this->view('teacher/reading_version', [
            'title' => ReadingVersions::label($kind) . ': ' . $material['title'],
            'crumbs' => [[t('Materials'), '/teacher/materials'], [$material['title'], '/teacher/materials/' . $id], [ReadingVersions::label($kind), null]],
            'material' => $material,
            'kind' => $kind,
            'version' => $version,
            'aiRemaining' => Usage::remainingToday($user),
            'aiLabel' => Providers::publicLabel(),
            'katex' => true,
        ]);
    }

    public function save(int $id, string $kind): Response
    {
        $user = $this->user();
        [$material, $kind] = $this->load($user, $id, $kind);
        $back = '/teacher/materials/' . $id . '/reading/' . $kind;
        $data = $this->validate(['content' => 'required|string|max:' . ReadingVersions::MAX_CHARS, 'action' => 'required|in:save,approve'],
            ['content' => t('Text')], $back);
        ReadingVersions::save($user, $material, $kind, trim((string) $data['content']), $data['action'] === 'approve', false);
        $this->flash('success', $data['action'] === 'approve'
            ? t(':version is shown to students beside the original.', ['version' => ReadingVersions::label($kind)])
            : t('Draft saved. Students do not see it until you show it to them.'));
        return $this->redirect($back);
    }

    /** The AI writes a draft from the material's own text; it is saved as a draft for the teacher to check. */
    public function draft(int $id, string $kind): Response
    {
        $user = $this->user();
        [$material, $kind] = $this->load($user, $id, $kind);
        $back = '/teacher/materials/' . $id . '/reading/' . $kind;
        $blocked = Usage::blockedReason($user, 'reading_version');
        if ($blocked !== null) {
            $this->fail('content', $blocked, $back);
        }
        try {
            $draft = ReadingVersions::draft($user, $material, $kind);
        } catch (AiException $e) {
            $this->fail('content', $e->getMessage(), $back);
        }
        ReadingVersions::save($user, $material, $kind, $draft['text'], false, $draft['ai']);
        $this->flash('success', t('Draft ready. Check every sentence against the original before you show it to students.'));
        return $this->redirect($back);
    }

    public function withdraw(int $id, string $kind): Response
    {
        [$material, $kind] = $this->load($this->user(), $id, $kind);
        ReadingVersions::withdraw($material, $kind);
        $this->flash('success', t('Students no longer see this version. It is kept as a draft.'));
        return $this->redirect('/teacher/materials/' . $id . '/reading/' . $kind);
    }

    public function destroy(int $id, string $kind): Response
    {
        [$material, $kind] = $this->load($this->user(), $id, $kind);
        ReadingVersions::delete($material, $kind);
        $this->flash('success', t(':version deleted.', ['version' => ReadingVersions::label($kind)]));
        return $this->redirect('/teacher/materials/' . $id);
    }

    /** @return array{0:array<string,mixed>,1:string} */
    private function load(array $user, int $id, string $kind): array
    {
        $material = Access::teacherMaterial($user, $id);
        if (!in_array($kind, ReadingVersions::KINDS, true) || $material['kind'] !== 'file') {
            throw HttpException::notFound();
        }
        return [$material, $kind];
    }
}
