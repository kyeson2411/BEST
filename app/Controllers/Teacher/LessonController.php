<?php

declare(strict_types=1);

namespace Sibol\Controllers\Teacher;

use Sibol\Controllers\Controller;
use Sibol\Core\Audit;
use Sibol\Core\Db;
use Sibol\Core\HttpException;
use Sibol\Core\Response;
use Sibol\Policies\Access;
use Sibol\Repositories\TeacherRepo;
use Sibol\Services\Ai\AiException;
use Sibol\Services\Ai\Generators;
use Sibol\Services\Ai\Retrieval;
use Sibol\Services\Ai\Usage;
use Sibol\Services\Notifier;

/**
 * Lesson studio. The working draft (draft_content) is always separate from what students
 * see (published_content); nothing reaches students until the teacher publishes.
 */
final class LessonController extends Controller
{
    public const SECTIONS = ['objectives', 'prior_knowledge', 'explanation', 'worked_example', 'activities', 'checks', 'exit_ticket'];

    public function index(): Response
    {
        $user = $this->user();
        $ids = Access::teacherClassIds($user, true);
        $rows = [];
        if ($ids) {
            [$in, $p] = Db::in($ids, 'c');
            $rows = Db::all("SELECT l.*, c.name AS class_name FROM lessons l JOIN classes c ON c.id = l.class_id WHERE l.class_id IN $in AND l.status <> 'archived' ORDER BY l.updated_at DESC", $p);
        }
        return $this->view('teacher/lessons', ['title' => t('Lessons'), 'rows' => $rows]);
    }

    public function create(): Response
    {
        $user = $this->user();
        $classes = array_values(array_filter(TeacherRepo::classes($user), static fn ($c) => $c['status'] === 'active'));
        $ids = array_map(static fn ($c) => (int) $c['id'], $classes);
        $materials = [];
        $modules = [];
        if ($ids) {
            [$in, $p] = Db::in($ids, 'c');
            $materials = Db::all("SELECT m.id, m.title, m.class_id FROM learning_materials m JOIN material_versions mv ON mv.id = m.current_version_id AND mv.processing_status = 'ready' WHERE m.class_id IN $in AND m.visibility <> 'archived' AND m.ai_enabled = 1 ORDER BY m.title", $p);
            $modules = Db::all("SELECT id, title, class_id FROM modules WHERE class_id IN $in ORDER BY position", $p);
        }
        return $this->view('teacher/lesson_new', [
            'title' => t('New lesson'),
            'crumbs' => [[t('Lessons'), '/teacher/lessons'], [t('New lesson'), null]],
            'classes' => $classes,
            'materials' => $materials,
            'modules' => $modules,
            'selectedClass' => $this->request->int('class_id'),
            'aiRemaining' => Usage::remainingToday($user),
        ]);
    }

    public function store(): Response
    {
        $user = $this->user();
        $data = $this->validate([
            'class_id' => 'required|int',
            'module_id' => 'nullable|int',
            'title' => 'required|string|max:200',
            'grade_level' => 'nullable|string|max:40',
            'duration_minutes' => 'nullable|int|min:5|max:600',
            'reading_level' => 'nullable|string|max:40',
            'language' => 'required|in:en,fil',
            'lesson_format' => 'nullable|string|max:40',
            'objectives' => 'nullable|string|max:3000',
            'start' => 'required|in:blank,ai',
        ], ['class_id' => t('Class'), 'title' => t('Lesson title')], '/teacher/lessons/new');
        $class = Access::teacherClass($user, (int) $data['class_id']);
        $moduleId = $data['module_id'] && (int) Db::value('SELECT COUNT(*) FROM modules WHERE id = :m AND class_id = :c', ['m' => (int) $data['module_id'], 'c' => (int) $class['id']]) ? (int) $data['module_id'] : null;
        $materialIds = array_values(array_unique(array_map('intval', $this->request->arr('materials'))));
        $content = array_fill_keys(self::SECTIONS, '') + ['sources' => []];
        $content['objectives'] = (string) ($data['objectives'] ?? '');
        $aiAssisted = false;

        if ($data['start'] === 'ai') {
            $blocked = Usage::blockedReason($user, 'lesson_draft');
            if ($blocked !== null) {
                $this->fail('start', $blocked, '/teacher/lessons/new');
            }
            $sources = [];
            foreach ($materialIds as $mid) {
                $material = Access::teacherMaterial($user, $mid);
                if ((int) $material['class_id'] !== (int) $class['id']) {
                    continue;
                }
                foreach (Retrieval::materialPassages($user, $mid, 9000) as $s) {
                    $sources[] = $s;
                }
            }
            foreach ($sources as $i => &$s) {
                $s['n'] = $i + 1;
            }
            unset($s);
            try {
                $draft = Generators::lessonDraft($user, $class, [
                    'title' => $data['title'], 'grade_level' => (string) $data['grade_level'], 'duration_minutes' => (string) $data['duration_minutes'],
                    'reading_level' => (string) $data['reading_level'], 'language' => $data['language'], 'lesson_format' => (string) $data['lesson_format'],
                    'objectives' => (string) $data['objectives'],
                ], array_slice($sources, 0, 14));
            } catch (AiException $e) {
                $this->fail('start', $e->getMessage(), '/teacher/lessons/new');
            }
            $content = $draft['content'];
            $aiAssisted = true;
        }
        $id = Db::insert('lessons', [
            'organization_id' => (int) $user['organization_id'],
            'class_id' => (int) $class['id'],
            'module_id' => $moduleId,
            'title' => $data['title'],
            'status' => 'draft',
            'grade_level' => $data['grade_level'] ?: $class['grade_level'],
            'duration_minutes' => $data['duration_minutes'],
            'reading_level' => $data['reading_level'],
            'language' => $data['language'],
            'lesson_format' => $data['lesson_format'],
            'objectives' => (string) $content['objectives'],
            'draft_content' => json_encode($content, JSON_UNESCAPED_UNICODE),
            'source_material_ids' => json_encode($materialIds),
            'ai_assisted' => $aiAssisted ? 1 : 0,
            'created_by' => (int) $user['id'],
            'created_at' => Db::now(),
            'updated_at' => Db::now(),
        ]);
        Audit::log('lesson.created', 'lesson', $id, ['ai' => $aiAssisted]);
        $this->flash('success', $aiAssisted ? t('Draft ready. Read and edit every section before publishing; students see nothing until you do.') : t('Lesson created as a draft.'));
        return $this->redirect('/teacher/lessons/' . $id);
    }

    public function edit(int $id): Response
    {
        $user = $this->user();
        $lesson = $this->lesson($user, $id);
        return $this->view('teacher/lesson_edit', [
            'title' => $lesson['title'],
            'crumbs' => [[t('Lessons'), '/teacher/lessons'], [$lesson['title'], null]],
            'lesson' => $lesson,
            'content' => json_decode((string) $lesson['draft_content'], true) ?: [],
            'modules' => Db::all('SELECT id, title FROM modules WHERE class_id = :c ORDER BY position', ['c' => (int) $lesson['class_id']]),
            'katex' => true,
        ]);
    }

    public function update(int $id): Response
    {
        $user = $this->user();
        $lesson = $this->lesson($user, $id);
        $back = '/teacher/lessons/' . $id;
        $data = $this->validate([
            'title' => 'required|string|max:200',
            'module_id' => 'nullable|int',
            'duration_minutes' => 'nullable|int|min:5|max:600',
            'reading_level' => 'nullable|string|max:40',
            'lesson_format' => 'nullable|string|max:40',
        ], ['title' => t('Lesson title')], $back);
        $content = json_decode((string) $lesson['draft_content'], true) ?: [];
        foreach (self::SECTIONS as $section) {
            $value = $this->request->str($section);
            if (mb_strlen($value) > 12000) {
                $this->fail($section, t('This section is too long (12,000 characters at most).'), $back);
            }
            $content[$section] = $value;
        }
        $moduleId = $data['module_id'] && (int) Db::value('SELECT COUNT(*) FROM modules WHERE id = :m AND class_id = :c', ['m' => (int) $data['module_id'], 'c' => (int) $lesson['class_id']]) ? (int) $data['module_id'] : null;
        Db::update('lessons', [
            'title' => $data['title'], 'module_id' => $moduleId, 'duration_minutes' => $data['duration_minutes'],
            'reading_level' => $data['reading_level'], 'lesson_format' => $data['lesson_format'],
            'objectives' => $content['objectives'], 'draft_content' => json_encode($content, JSON_UNESCAPED_UNICODE),
            'has_unpublished_changes' => $lesson['status'] === 'published' ? 1 : 0, 'updated_at' => Db::now(),
        ], ['id' => $id]);
        $this->flash('success', $lesson['status'] === 'published' ? t('Draft saved. Students still see the published version until you publish the changes.') : t('Draft saved.'));
        return $this->redirect($back);
    }

    public function preview(int $id): Response
    {
        $user = $this->user();
        $lesson = $this->lesson($user, $id);
        $class = Db::one('SELECT * FROM classes WHERE id = :c', ['c' => (int) $lesson['class_id']]);
        return $this->view('teacher/lesson_preview', [
            'title' => t('Preview: :title', ['title' => $lesson['title']]),
            'lesson' => $lesson,
            'class' => $class,
            'content' => json_decode((string) $lesson['draft_content'], true) ?: [],
            'katex' => true,
        ]);
    }

    public function publish(int $id): Response
    {
        $user = $this->user();
        $lesson = $this->lesson($user, $id);
        $content = json_decode((string) $lesson['draft_content'], true) ?: [];
        if (trim((string) ($content['explanation'] ?? '')) === '' && trim((string) ($content['activities'] ?? '')) === '') {
            $this->flash('error', t('Write at least the explanation or the activities before publishing.'));
            return $this->redirect('/teacher/lessons/' . $id);
        }
        $first = $lesson['status'] !== 'published';
        Db::update('lessons', [
            'status' => 'published', 'published_content' => $lesson['draft_content'], 'published_by' => (int) $user['id'],
            'published_at' => Db::now(), 'has_unpublished_changes' => 0, 'updated_at' => Db::now(),
        ], ['id' => $id]);
        if ($first) {
            Notifier::send((int) $user['organization_id'], Notifier::classStudents((int) $lesson['class_id']), 'material', t('New lesson: :title', ['title' => $lesson['title']]), $lesson['class_name'], '/student/classes/' . (int) $lesson['class_id'] . '/lessons/' . $id, 'lesson:' . $id);
        }
        Audit::log('lesson.published', 'lesson', $id, ['ai_assisted' => (bool) $lesson['ai_assisted']]);
        $this->flash('success', $first ? t('Published. Students in :class can open it now.', ['class' => $lesson['class_name']]) : t('Changes published.'));
        return $this->redirect('/teacher/lessons/' . $id);
    }

    public function unpublish(int $id): Response
    {
        $user = $this->user();
        $this->lesson($user, $id);
        Db::update('lessons', ['status' => 'draft', 'published_content' => null, 'has_unpublished_changes' => 0, 'updated_at' => Db::now()], ['id' => $id]);
        Audit::log('lesson.unpublished', 'lesson', $id);
        $this->flash('success', t('Unpublished. Students can no longer see this lesson.'));
        return $this->redirect('/teacher/lessons/' . $id);
    }

    public function duplicate(int $id): Response
    {
        $user = $this->user();
        $lesson = $this->lesson($user, $id);
        $copy = $lesson;
        foreach (['id', 'class_name', 'published_content', 'published_by', 'published_at'] as $k) {
            unset($copy[$k]);
        }
        $copy['title'] = mb_substr(t(':title (copy)', ['title' => $lesson['title']]), 0, 200);
        $copy['status'] = 'draft';
        $copy['has_unpublished_changes'] = 0;
        $copy['created_by'] = (int) $user['id'];
        $copy['created_at'] = Db::now();
        $copy['updated_at'] = Db::now();
        $newId = Db::insert('lessons', $copy);
        $this->flash('success', t('Copy created as a draft.'));
        return $this->redirect('/teacher/lessons/' . $newId);
    }

    public function archive(int $id): Response
    {
        $user = $this->user();
        $this->lesson($user, $id);
        Db::update('lessons', ['status' => 'archived', 'updated_at' => Db::now()], ['id' => $id]);
        $this->flash('success', t('Lesson archived.'));
        return $this->redirect('/teacher/lessons');
    }

    /** @return array<string,mixed> */
    private function lesson(array $user, int $id): array
    {
        $lesson = Db::one('SELECT l.*, c.name AS class_name FROM lessons l JOIN classes c ON c.id = l.class_id WHERE l.id = :id', ['id' => $id]);
        if (!$lesson || !Access::teaches($user, (int) $lesson['class_id'])) {
            throw HttpException::notFound();
        }
        return $lesson;
    }
}
