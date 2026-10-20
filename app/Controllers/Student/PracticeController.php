<?php

declare(strict_types=1);

namespace Sibol\Controllers\Student;

use Sibol\Controllers\Controller;
use Sibol\Core\Db;
use Sibol\Core\HttpException;
use Sibol\Core\Response;
use Sibol\Policies\Access;
use Sibol\Repositories\StudentRepo;
use Sibol\Services\Ai\AiException;
use Sibol\Services\Ai\Generators;
use Sibol\Services\Ai\Retrieval;
use Sibol\Services\Ai\Usage;
use Sibol\Services\Settings;

/** Self-study practice sets and flashcards built from released class materials. Private to the student. */
final class PracticeController extends Controller
{
    public function index(): Response
    {
        $user = $this->user();
        $sets = Db::all(
            'SELECT p.*, c.name AS class_name FROM practice_sets p LEFT JOIN classes c ON c.id = p.class_id
             WHERE p.user_id = :u ORDER BY p.created_at DESC LIMIT 40',
            ['u' => (int) $user['id']]
        );
        $classes = StudentRepo::classes($user);
        $materials = [];
        if ($classes) {
            [$in, $params] = Db::in(array_map(static fn ($c) => (int) $c['id'], $classes), 'c');
            $materials = Db::all(
                "SELECT m.id, m.title, m.class_id FROM learning_materials m
                 JOIN material_versions mv ON mv.id = m.current_version_id AND mv.processing_status = 'ready'
                 WHERE m.class_id IN $in AND m.ai_enabled = 1 AND " . Access::releasedMaterialSql('m') . ' ORDER BY m.title',
                $params
            );
        }
        $orgId = (int) $user['organization_id'];
        return $this->view('student/practice_index', [
            'title' => t('Practice'),
            'sets' => $sets,
            'classes' => $classes,
            'materials' => $materials,
            'quizOn' => (bool) Settings::get($orgId, 'features.practice_quizzes', true) && (bool) Settings::get($orgId, 'ai.enabled', true),
            'cardsOn' => (bool) Settings::get($orgId, 'features.flashcards', true) && (bool) Settings::get($orgId, 'ai.enabled', true),
        ]);
    }

    public function store(): Response
    {
        $user = $this->user();
        $orgId = (int) $user['organization_id'];
        $data = $this->validate([
            'kind' => 'required|in:quiz,flashcards',
            'class_id' => 'nullable|int',
            'material_id' => 'nullable|int',
            'topic' => 'nullable|string|max:160',
        ], ['kind' => t('Type of practice'), 'class_id' => t('Class'), 'topic' => t('Topic')], '/student/learning');
        $feature = $data['kind'] === 'quiz' ? 'practice_quizzes' : 'flashcards';
        if (!Settings::get($orgId, 'features.' . $feature, true) || !Settings::get($orgId, 'ai.enabled', true)) {
            throw HttpException::forbidden(t('Your school has turned off this kind of practice.'));
        }
        $classId = $data['class_id'] ? (int) $data['class_id'] : null;
        $materialId = $data['material_id'] ? (int) $data['material_id'] : null;
        if ($materialId !== null) {
            $material = Access::studentMaterial($user, $materialId);
            $classId = (int) $material['class_id'];
        }
        if ($classId === null) {
            $this->fail('class_id', t('Choose a class to practise.'), '/student/learning');
        }
        Access::studentClass($user, $classId);
        $blocked = Usage::blockedReason($user, 'practice');
        if ($blocked !== null) {
            $this->flash('error', $blocked);
            return $this->redirect('/student/learning');
        }
        $topic = (string) ($data['topic'] ?? '');
        try {
            $sources = Generators::sourcesFor($user, $classId, $materialId, $topic);
            $set = Generators::practiceSet($user, $data['kind'], $sources, $topic, 5);
        } catch (AiException $e) {
            $this->flash('error', $e->getMessage());
            return $this->redirect('/student/learning');
        }
        $refs = [];
        foreach ($set['sources'] as $s) {
            $refs[(int) $s['n']] = ['title' => $s['title'], 'where' => Retrieval::location($s), 'material_id' => (int) $s['material_id']];
        }
        $items = array_map(static function (array $item) use ($refs): array {
            $item['source_ref'] = $item['source'] !== null ? ($refs[(int) $item['source']] ?? null) : null;
            return $item;
        }, $set['items']);
        $className = (string) Db::value('SELECT name FROM classes WHERE id = :c', ['c' => $classId]);
        $title = $topic !== '' ? $topic : (string) ($set['sources'][0]['title'] ?? $className);
        $id = Db::insert('practice_sets', [
            'organization_id' => $orgId,
            'user_id' => (int) $user['id'],
            'class_id' => $classId,
            'material_id' => $materialId,
            'kind' => $data['kind'],
            'title' => mb_substr(($data['kind'] === 'quiz' ? t('Practice: :t', ['t' => $title]) : t('Flashcards: :t', ['t' => $title])), 0, 200),
            'topic' => $topic !== '' ? $topic : null,
            'items' => json_encode($items, JSON_UNESCAPED_UNICODE),
            'generator' => $set['generator'],
            'total' => count($items),
            'created_at' => Db::now(),
            'updated_at' => Db::now(),
        ]);
        return $this->redirect('/student/learning/' . $id);
    }

    public function show(int $id): Response
    {
        $user = $this->user();
        $set = $this->own($user, $id);
        return $this->view('student/practice_show', [
            'title' => $set['title'],
            'crumbs' => [[t('Practice'), '/student/learning'], [$set['title'], null]],
            'set' => $set,
            'items' => json_decode((string) $set['items'], true) ?: [],
            'results' => json_decode((string) ($set['results'] ?? ''), true) ?: null,
            'katex' => true,
            'scripts' => ['practice.js'],
        ]);
    }

    public function saveResults(int $id): Response
    {
        $user = $this->user();
        $set = $this->own($user, $id);
        $items = json_decode((string) $set['items'], true) ?: [];
        $results = [];
        $score = 0;
        if ($set['kind'] === 'quiz') {
            $answers = $this->request->arr('answers');
            foreach ($items as $i => $item) {
                $given = $answers[$i] ?? null;
                if (($item['type'] ?? 'mcq') === 'mcq') {
                    $correct = $given !== null && $given !== '' && (int) $given === (int) $item['answer'];
                } else {
                    $correct = is_string($given) && mb_strtolower(trim($given)) === mb_strtolower(trim((string) $item['answer_text']));
                }
                $score += $correct ? 1 : 0;
                $results[$i] = ['given' => is_scalar($given) ? (string) $given : null, 'correct' => $correct];
            }
        } else {
            $known = array_map('intval', $this->request->arr('known'));
            foreach ($items as $i => $item) {
                $isKnown = in_array($i, $known, true);
                $score += $isKnown ? 1 : 0;
                $results[$i] = ['known' => $isKnown];
            }
        }
        Db::update('practice_sets', [
            'results' => json_encode($results),
            'score' => $score,
            'total' => count($items),
            'completed_at' => Db::now(),
            'last_reviewed_at' => Db::now(),
            'updated_at' => Db::now(),
        ], ['id' => $id]);
        $this->flash('success', $set['kind'] === 'quiz'
            ? t('You got :score of :total. Check the explanations below.', ['score' => $score, 'total' => count($items)])
            : t('You knew :score of :total cards. Review the others again later.', ['score' => $score, 'total' => count($items)]));
        return $this->redirect('/student/learning/' . $id);
    }

    public function destroy(int $id): Response
    {
        $user = $this->user();
        $this->own($user, $id);
        Db::delete('practice_sets', ['id' => $id]);
        $this->flash('success', t('Practice set deleted.'));
        return $this->redirect('/student/learning');
    }

    /** @return array<string,mixed> */
    private function own(array $user, int $id): array
    {
        $set = Db::one('SELECT p.*, c.name AS class_name FROM practice_sets p LEFT JOIN classes c ON c.id = p.class_id WHERE p.id = :id AND p.user_id = :u', ['id' => $id, 'u' => (int) $user['id']]);
        if (!$set) {
            throw HttpException::notFound();
        }
        return $set;
    }
}
