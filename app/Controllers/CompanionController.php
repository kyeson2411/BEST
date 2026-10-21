<?php

declare(strict_types=1);

namespace Sibol\Controllers;

use Sibol\Core\Audit;
use Sibol\Core\Db;
use Sibol\Core\HttpException;
use Sibol\Core\Response;
use Sibol\Core\Session;
use Sibol\Policies\Access;
use Sibol\Services\Ai\Companion;
use Sibol\Services\Ai\Providers;
use Sibol\Services\Ai\Usage;
use Sibol\Services\Notifier;
use Sibol\Services\Settings;

/**
 * Private learning-companion conversations. A conversation is visible only to its
 * owner: there is no teacher or administrator route to read another person's chat.
 */
final class CompanionController extends Controller
{
    public function index(): Response
    {
        return $this->create();
    }

    public function create(): Response
    {
        $user = $this->user();
        $modes = $this->modes($user);
        if (!$modes) {
            throw HttpException::forbidden(t('AI tools are turned off at your school.'));
        }
        $classId = $this->request->int('class_id');
        $materialId = $this->request->int('material_id');
        $topic = mb_substr($this->request->str('topic'), 0, 160);
        // Flashcards are made in Practice, not in a chat, so an old link to a flashcards chat opens Practice.
        $chatModes = array_values(array_diff($modes, ['flashcards']));
        if (!$chatModes || ($this->request->str('mode') === 'flashcards' && in_array('flashcards', $modes, true))) {
            return $this->redirect($this->practiceCards($user, $classId, $materialId, $topic));
        }
        $mode = in_array($this->request->str('mode'), $chatModes, true) ? $this->request->str('mode') : $chatModes[0];
        return $this->view('companion/index', $this->shared($user) + [
            'title' => t('Companion'),
            'page_class' => 'page--flush',
            'active' => null,
            'messages' => [],
            'modes' => $modes,
            'practiceCards' => in_array('flashcards', $modes, true) ? $this->practiceCards($user, $classId, $materialId, $topic) : null,
            'defaults' => [
                'mode' => $mode,
                'class_id' => $classId,
                'material_id' => $materialId,
                'question' => $topic !== '' ? ($user['role'] === 'teacher' ? t('Help me prepare: :topic', ['topic' => $topic]) : t('Help me understand: :topic', ['topic' => $topic])) : '',
            ],
            'scripts' => ['companion.js'],
        ]);
    }

    public function store(): Response
    {
        $user = $this->user();
        $modes = $this->modes($user);
        $data = $this->validate([
            'mode' => 'required|in:' . implode(',', $modes ?: ['none']),
            'class_id' => 'nullable|int',
            'material_id' => 'nullable|int',
            'language' => 'required|in:en,fil,mixed',
            'level' => 'nullable|in:simpler,grade,challenge',
            'question' => 'nullable|string|max:' . (int) Settings::get((int) $user['organization_id'], 'ai.max_question_chars', 2000),
        ], ['mode' => t('What you want to do'), 'question' => t('Your first message')], '/companion/new');

        $classId = $data['class_id'] ? (int) $data['class_id'] : null;
        $materialId = $data['material_id'] ? (int) $data['material_id'] : null;
        if ($materialId !== null) {
            $material = $this->accessibleMaterial($user, $materialId);
            $classId = (int) $material['class_id'];
        }
        if ($classId !== null) {
            $allowed = $user['role'] === 'student' ? Access::studentClassIds($user) : Access::teacherClassIds($user, true);
            if (!in_array($classId, $allowed, true)) {
                $this->fail('class_id', t('Choose one of your classes.'), '/companion/new');
            }
        }
        if ($data['mode'] === 'flashcards') {
            // Real cards that flip and come back in Review live in Practice. What the student typed stays out of the address.
            return $this->redirect($this->practiceCards($user, $classId, $materialId));
        }
        if (in_array($data['mode'], ['summarize'], true) && $materialId === null) {
            $this->fail('material_id', t('Choose the material you want summarised.'), '/companion/new');
        }
        if ($data['mode'] === 'ask_materials' && $classId === null) {
            $this->fail('class_id', t('Choose the class whose materials you want to ask about.'), '/companion/new');
        }

        $levels = ['simpler' => t('simpler than my grade level'), 'grade' => t('my grade level'), 'challenge' => t('more challenging than my grade level')];
        $id = Db::insert('ai_conversations', [
            'organization_id' => (int) $user['organization_id'],
            'user_id' => (int) $user['id'],
            'class_id' => $classId,
            'material_id' => $materialId,
            'mode' => $data['mode'],
            'title' => t('New session'),
            'language' => $data['language'],
            'level' => $data['level'] ? $levels[$data['level']] : null,
            'last_message_at' => Db::now(),
            'created_at' => Db::now(),
            'updated_at' => Db::now(),
        ]);
        if (!empty($data['question'])) {
            // Sent automatically when the conversation opens; kept out of the URL on purpose.
            Session::set('companion_pending', ['id' => $id, 'text' => (string) $data['question']]);
        }
        return $this->redirect('/companion/' . $id);
    }

    public function show(int $id): Response
    {
        $user = $this->user();
        $conversation = $this->conversation($user, $id);
        $pending = Session::get('companion_pending');
        $pendingText = (is_array($pending) && (int) ($pending['id'] ?? 0) === $id) ? (string) $pending['text'] : '';
        if ($pendingText !== '') {
            Session::set('companion_pending', null);
        }
        $modes = $this->modes($user);
        return $this->view('companion/index', $this->shared($user) + [
            'title' => $conversation['title'],
            'page_class' => 'page--flush',
            'active' => $conversation,
            'messages' => Companion::loadMessages($id),
            'modes' => $modes,
            'practiceCards' => in_array('flashcards', $modes, true)
                ? $this->practiceCards($user, self::optionalId($conversation['class_id']), self::optionalId($conversation['material_id']))
                : null,
            'pending' => $pendingText,
            'katex' => true,
            'scripts' => ['companion.js'],
        ]);
    }

    public function send(int $id): Response
    {
        $user = $this->user();
        $conversation = $this->conversation($user, $id);
        if ($conversation['archived_at'] !== null) {
            return $this->json(['error' => t('Restore this conversation before continuing it.')], 409);
        }
        $max = (int) Settings::get((int) $user['organization_id'], 'ai.max_question_chars', 2000);
        $question = $this->request->str('message');
        if ($question === '') {
            return $this->json(['error' => t('Type a question first.')], 422);
        }
        if (mb_strlen($question) > $max) {
            return $this->json(['error' => t('Keep your message under :n characters.', ['n' => $max])], 422);
        }
        return $this->streamTurn($user, $conversation, $question, false);
    }

    public function regenerate(int $id): Response
    {
        $user = $this->user();
        $conversation = $this->conversation($user, $id);
        $lastUser = Db::one("SELECT * FROM ai_messages WHERE conversation_id = :c AND role = 'user' ORDER BY id DESC LIMIT 1", ['c' => $id]);
        if (!$lastUser) {
            return $this->json(['error' => t('There is nothing to regenerate yet.')], 409);
        }
        Db::exec("DELETE FROM ai_messages WHERE conversation_id = :c AND role = 'assistant' AND id > :m", ['c' => $id, 'm' => (int) $lastUser['id']]);
        return $this->streamTurn($user, $conversation, (string) $lastUser['content'], true);
    }

    private function streamTurn(array $user, array $conversation, string $question, bool $regenerate): Response
    {
        Session::release();
        return Response::stream(static function () use ($user, $conversation, $question, $regenerate): void {
            ignore_user_abort(true);
            @set_time_limit(240);
            @ini_set('zlib.output_compression', '0');
            $emit = static function (array $event): void {
                echo json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . "\n";
                @ob_flush();
                flush();
            };
            $aborted = static fn (): bool => connection_aborted() === 1;
            // An initial padding line helps some proxies start streaming immediately.
            $emit(['type' => 'open']);
            Companion::turn($user, $conversation, $question, $emit, $aborted, $regenerate);
        });
    }

    public function rename(int $id): Response
    {
        $user = $this->user();
        $this->conversation($user, $id);
        $data = $this->validate(['title' => 'required|string|max:160'], ['title' => t('Title')], '/companion/' . $id);
        Db::update('ai_conversations', ['title' => $data['title'], 'updated_at' => Db::now()], ['id' => $id]);
        $this->flash('success', t('Conversation renamed.'));
        return $this->redirect('/companion/' . $id);
    }

    public function archive(int $id): Response
    {
        $user = $this->user();
        $this->conversation($user, $id);
        Db::update('ai_conversations', ['archived_at' => Db::now()], ['id' => $id]);
        $this->flash('success', t('Conversation archived. You can find it under Archived.'));
        return $this->redirect('/companion');
    }

    public function restore(int $id): Response
    {
        $user = $this->user();
        $this->conversation($user, $id);
        Db::update('ai_conversations', ['archived_at' => null], ['id' => $id]);
        $this->flash('success', t('Conversation restored.'));
        return $this->redirect('/companion/' . $id);
    }

    public function destroy(int $id): Response
    {
        $user = $this->user();
        $this->conversation($user, $id);
        if ($user['role'] === 'student' && !Settings::get((int) $user['organization_id'], 'privacy.students_can_delete_chats', true)) {
            throw HttpException::forbidden(t('Your school keeps companion conversations for its retention period, so they cannot be deleted early. You can archive them instead.'));
        }
        Db::delete('ai_conversations', ['id' => $id]);
        Audit::log('ai.conversation_deleted', 'ai_conversation', $id);
        $this->flash('success', t('Conversation deleted permanently.'));
        return $this->redirect('/companion');
    }

    public function changeMode(int $id): Response
    {
        $user = $this->user();
        $conversation = $this->conversation($user, $id);
        $modes = $this->modes($user);
        $mode = $this->request->str('mode');
        if (!in_array($mode, $modes, true)) {
            $this->fail('mode', t('Choose one of the available modes.'), '/companion/' . $id);
        }
        if ($mode === 'flashcards') {
            // The chat keeps its kind of help; flashcards open in Practice for the same class.
            return $this->redirect($this->practiceCards($user, self::optionalId($conversation['class_id']), self::optionalId($conversation['material_id'])));
        }
        Db::update('ai_conversations', ['mode' => $mode, 'updated_at' => Db::now()], ['id' => $id]);
        $this->flash('success', t('Mode changed to :mode.', ['mode' => Settings::modeLabel($mode)]));
        return $this->redirect('/companion/' . $id);
    }

    public function report(int $id): Response
    {
        $user = $this->user();
        $message = $this->ownMessage($user, $id);
        $data = $this->validate([
            'reason' => 'required|in:incorrect,unsafe,not_helpful,privacy,other',
            'note' => 'nullable|string|max:1000',
        ], ['reason' => t('Reason'), 'note' => t('Note')], '/companion/' . (int) $message['conversation_id']);
        $reportId = Db::insert('ai_reports', [
            'organization_id' => (int) $user['organization_id'],
            'message_id' => $id,
            'reporter_id' => (int) $user['id'],
            'reason' => $data['reason'],
            'note' => $data['note'],
            'answer_excerpt' => mb_substr((string) $message['content'], 0, 4000),
            'status' => 'open',
            'created_at' => Db::now(),
        ]);
        $admins = array_map('intval', Db::column("SELECT id FROM users WHERE organization_id = :o AND role = 'admin' AND status = 'active'", ['o' => (int) $user['organization_id']]));
        Notifier::send((int) $user['organization_id'], $admins, 'privacy', t('A companion answer was reported'), t('Reason: :reason', ['reason' => $data['reason']]), '/admin/privacy/reports/' . $reportId);
        Audit::log('ai.answer_reported', 'ai_report', $reportId, ['reason' => $data['reason']]);
        $this->flash('success', t('Thank you. Your report was sent to the school administrators.'));
        return $this->redirect('/companion/' . (int) $message['conversation_id']);
    }

    public function share(int $id): Response
    {
        $user = $this->user();
        if ($user['role'] !== 'student' || !Settings::get((int) $user['organization_id'], 'features.share_with_teacher', true)) {
            throw HttpException::notFound();
        }
        $message = $this->ownMessage($user, $id);
        $conversation = $this->conversation($user, (int) $message['conversation_id']);
        if ($conversation['class_id'] === null) {
            $this->fail('note', t('This conversation is not linked to a class, so there is no teacher to share with.'), '/companion/' . (int) $conversation['id']);
        }
        $data = $this->validate(['note' => 'nullable|string|max:1000'], ['note' => t('Note')], '/companion/' . (int) $conversation['id']);
        $shareId = Db::insert('shared_excerpts', [
            'organization_id' => (int) $user['organization_id'],
            'student_id' => (int) $user['id'],
            'class_id' => (int) $conversation['class_id'],
            'kind' => 'excerpt',
            'content' => mb_substr((string) $message['content'], 0, 4000),
            'student_note' => $data['note'],
            'created_at' => Db::now(),
        ]);
        Notifier::send((int) $user['organization_id'], Notifier::classTeachers((int) $conversation['class_id']), 'class',
            t(':name shared a companion excerpt', ['name' => user_name($user)]), $conversation['class_name'],
            '/teacher/classes/' . (int) $conversation['class_id'] . '?tab=shared');
        Audit::log('ai.excerpt_shared', 'shared_excerpt', $shareId);
        $this->flash('success', t('Shared with your teacher. Only this answer and your note were sent.'));
        return $this->redirect('/companion/' . (int) $conversation['id']);
    }

    public function addPlanToCalendar(int $id): Response
    {
        $user = $this->user();
        if ($user['role'] !== 'student') {
            throw HttpException::notFound();
        }
        $message = $this->ownMessage($user, $id);
        $conversation = $this->conversation($user, (int) $message['conversation_id']);
        [, $sessions] = Companion::extractPlan((string) $message['content']);
        $added = 0;
        foreach ($sessions as $s) {
            if ($s['starts_at'] < Db::now()) {
                continue;
            }
            $exists = (int) Db::value('SELECT COUNT(*) FROM study_sessions WHERE user_id = :u AND starts_at = :s AND title = :t', ['u' => (int) $user['id'], 's' => $s['starts_at'], 't' => $s['title']]);
            if ($exists) {
                continue;
            }
            Db::insert('study_sessions', [
                'organization_id' => (int) $user['organization_id'], 'user_id' => (int) $user['id'],
                'class_id' => $conversation['class_id'], 'title' => $s['title'], 'starts_at' => $s['starts_at'],
                'duration_minutes' => $s['minutes'], 'source' => 'companion', 'created_at' => Db::now(),
            ]);
            $added++;
        }
        $this->flash($added ? 'success' : 'info', $added ? tp('Added :count study session to your calendar.', 'Added :count study sessions to your calendar.', $added) : t('Those sessions are already on your calendar or are in the past.'));
        return $this->redirect('/student/calendar');
    }

    // ---------------------------------------------------------------- helpers

    /**
     * Practice with flashcards first, for this class or material; without one, the class the student practised
     * last. Practice checks the ids again and ignores any that are not the student's own.
     */
    private function practiceCards(array $user, ?int $classId, ?int $materialId, string $topic = ''): string
    {
        if ($classId === null && $materialId === null) {
            $last = Db::value(
                'SELECT class_id FROM practice_sets WHERE user_id = :u AND class_id IS NOT NULL ORDER BY created_at DESC, id DESC LIMIT 1',
                ['u' => (int) $user['id']]
            );
            $classId = $last ? (int) $last : null;
        }
        $query = array_filter(['kind' => 'flashcards', 'class_id' => $classId, 'material_id' => $materialId, 'topic' => $topic], static fn ($v) => $v !== null && $v !== '');
        return '/student/learning?' . http_build_query($query);
    }

    /** @param mixed $value */
    private static function optionalId($value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    /** @return list<string> */
    private function modes(array $user): array
    {
        $orgId = (int) $user['organization_id'];
        if (!Settings::get($orgId, 'ai.enabled', true)) {
            return [];
        }
        if ($user['role'] === 'teacher') {
            return Settings::get($orgId, 'ai.teacher_tools', true) ? Settings::TEACHER_MODES : [];
        }
        $allowed = array_values(array_intersect(Settings::STUDENT_MODES, (array) Settings::get($orgId, 'ai.student_modes', Settings::STUDENT_MODES)));
        $features = (array) Settings::get($orgId, 'features', []);
        $off = [];
        if (empty($features['flashcards'])) {
            $off[] = 'flashcards';
        }
        if (empty($features['practice_quizzes'])) {
            $off[] = 'practice';
        }
        if (empty($features['study_plans'])) {
            $off[] = 'study_plan';
        }
        if (empty($features['document_answers'])) {
            $off = array_merge($off, ['ask_materials', 'summarize']);
        }
        return array_values(array_diff($allowed, $off));
    }

    /** @return array<string,mixed> */
    private function conversation(array $user, int $id): array
    {
        $row = Db::one(
            'SELECT c.*, cl.name AS class_name, m.title AS material_title
             FROM ai_conversations c
             LEFT JOIN classes cl ON cl.id = c.class_id
             LEFT JOIN learning_materials m ON m.id = c.material_id
             WHERE c.id = :id AND c.user_id = :u',
            ['id' => $id, 'u' => (int) $user['id']]
        );
        if (!$row) {
            throw HttpException::notFound();
        }
        return $row;
    }

    /** @return array<string,mixed> an assistant message in one of the user's own conversations */
    private function ownMessage(array $user, int $messageId): array
    {
        $row = Db::one(
            "SELECT m.* FROM ai_messages m JOIN ai_conversations c ON c.id = m.conversation_id
             WHERE m.id = :id AND c.user_id = :u AND m.role = 'assistant'",
            ['id' => $messageId, 'u' => (int) $user['id']]
        );
        if (!$row) {
            throw HttpException::notFound();
        }
        return $row;
    }

    /** @return array<string,mixed> */
    private function accessibleMaterial(array $user, int $materialId): array
    {
        $material = $user['role'] === 'student' ? Access::studentMaterial($user, $materialId) : Access::teacherMaterial($user, $materialId);
        if (!(int) $material['ai_enabled']) {
            $this->fail('material_id', t('That material is not available to the companion.'), '/companion/new');
        }
        return $material;
    }

    /** Sessions list, classes and AI-ready materials for the sidebar and forms. */
    private function shared(array $user): array
    {
        $showArchived = $this->request->bool('archived');
        $conversations = Db::all(
            'SELECT id, title, mode, last_message_at, archived_at FROM ai_conversations
             WHERE user_id = :u AND archived_at IS ' . ($showArchived ? 'NOT ' : '') . 'NULL ORDER BY last_message_at DESC LIMIT 60',
            ['u' => (int) $user['id']]
        );
        $classIds = $user['role'] === 'student' ? Access::studentClassIds($user) : Access::teacherClassIds($user);
        $classes = [];
        $materials = [];
        if ($classIds) {
            [$in, $params] = Db::in($classIds, 'c');
            $classes = Db::all("SELECT id, name FROM classes WHERE id IN $in ORDER BY name", $params);
            $visibility = $user['role'] === 'student' ? Access::releasedMaterialSql('m') : "m.visibility <> 'archived'";
            $materials = Db::all(
                "SELECT m.id, m.title, m.class_id FROM learning_materials m
                 JOIN material_versions mv ON mv.id = m.current_version_id AND mv.processing_status = 'ready'
                 WHERE m.class_id IN $in AND m.ai_enabled = 1 AND $visibility ORDER BY m.title",
                $params
            );
        }
        return [
            'conversations' => $conversations,
            'showArchived' => $showArchived,
            'classes' => $classes,
            'materials' => $materials,
            'remaining' => Usage::remainingToday($user),
            'providerLabel' => Providers::label(),
            'canDelete' => $user['role'] !== 'student' || (bool) Settings::get((int) $user['organization_id'], 'privacy.students_can_delete_chats', true),
            'canShare' => $user['role'] === 'student' && (bool) Settings::get((int) $user['organization_id'], 'features.share_with_teacher', true),
            'retentionDays' => (int) Settings::get((int) $user['organization_id'], 'privacy.ai_retention_days', 365),
        ];
    }
}
