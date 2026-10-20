<?php

declare(strict_types=1);

namespace Sibol\Controllers\Teacher;

use Sibol\Controllers\Controller;
use Sibol\Core\Audit;
use Sibol\Core\Dates;
use Sibol\Core\Db;
use Sibol\Core\HttpException;
use Sibol\Core\Response;
use Sibol\Core\ValidationException;
use Sibol\Policies\Access;
use Sibol\Repositories\TeacherRepo;
use Sibol\Services\Ai\AiException;
use Sibol\Services\Ai\Generators;
use Sibol\Services\Ai\Retrieval;
use Sibol\Services\Ai\Usage;
use Sibol\Services\Assessments;
use Sibol\Services\Grades;
use Sibol\Services\Notifier;
use Sibol\Services\Overrides;
use Sibol\Services\SectionCopies;

final class AssignmentController extends Controller
{
    use CopiesToSections;

    public function index(): Response
    {
        $user = $this->user();
        $classes = TeacherRepo::classes($user, true);
        $ids = array_map(static fn ($c) => (int) $c['id'], $classes);
        $classId = $this->request->int('class_id');
        if ($classId !== null && !in_array($classId, $ids, true)) {
            $classId = null;
        }
        $kind = in_array($this->request->str('kind'), ['assignment', 'quiz'], true) ? $this->request->str('kind') : '';
        $status = in_array($this->request->str('status'), ['draft', 'published', 'closed', 'archived'], true) ? $this->request->str('status') : '';
        $rows = [];
        if ($ids) {
            [$in, $p] = Db::in($classId !== null ? [$classId] : $ids, 'c');
            $where = "a.class_id IN $in" . ($status !== '' ? ' AND a.status = :st' : " AND a.status <> 'archived'") . ($kind !== '' ? ' AND a.kind = :k' : '');
            if ($status !== '') {
                $p['st'] = $status;
            }
            if ($kind !== '') {
                $p['k'] = $kind;
            }
            $rows = Db::all(
                "SELECT a.*, c.name AS class_name,
                        " . Overrides::expectedSql('a') . ' AS enrolled, ' . Overrides::handedInSql('a') . " AS submitted,
                        (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id AND s.status = 'submitted' AND s.grading_status <> 'graded') AS ungraded,
                        (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id AND s.status = 'submitted' AND s.grading_status = 'graded' AND s.feedback_status <> 'released') AS unreleased,
                        (SELECT COUNT(*) FROM questions q WHERE q.assignment_id = a.id AND q.reviewed = 0) AS unreviewed
                 FROM assignments a JOIN classes c ON c.id = a.class_id
                 WHERE $where ORDER BY a.status = 'draft' DESC, a.due_at IS NULL, a.due_at DESC LIMIT 200",
                $p
            );
        }
        return $this->view('teacher/assignments', [
            'title' => $kind === 'quiz' ? t('Quizzes') : t('Assignments'),
            'rows' => $rows, 'classes' => $classes, 'classId' => $classId, 'kind' => $kind, 'status' => $status,
        ]);
    }

    public function assessments(): Response
    {
        return $this->redirect('/teacher/assignments?kind=quiz');
    }

    public function create(): Response
    {
        $user = $this->user();
        $classes = array_values(array_filter(TeacherRepo::classes($user), static fn ($c) => $c['status'] === 'active'));
        $kind = $this->request->str('kind') === 'quiz' ? 'quiz' : 'assignment';
        return $this->view('teacher/assignment_form', [
            'title' => $kind === 'quiz' ? t('New quiz') : t('New assignment'),
            'crumbs' => [[t('Assignments'), '/teacher/assignments'], [$kind === 'quiz' ? t('New quiz') : t('New assignment'), null]],
            'assignment' => null,
            'kind' => $kind,
            'classes' => $classes,
            'selectedClass' => $this->request->int('class_id'),
            'modules' => $this->modules(array_map(static fn ($c) => (int) $c['id'], $classes)),
            'categories' => Grades::categoryOptions((int) $user['organization_id']),
            'locked' => false,
        ]);
    }

    public function store(): Response
    {
        $user = $this->user();
        $data = $this->settings('/teacher/assignments/new');
        $class = Access::teacherClass($user, (int) $data['class_id']);
        if ($class['status'] !== 'active') {
            $this->fail('class_id', t('That class is archived.'), '/teacher/assignments/new');
        }
        $id = Db::insert('assignments', $this->settingsRow($data, (int) $class['id']) + [
            'organization_id' => (int) $user['organization_id'],
            'class_id' => (int) $class['id'],
            'kind' => $data['kind'],
            'status' => 'draft',
            'created_by' => (int) $user['id'],
            'created_at' => Db::now(),
        ]);
        Audit::log('assignment.created', 'assignment', $id, ['kind' => $data['kind']]);
        $this->flash('success', $data['kind'] === 'quiz' ? t('Quiz created as a draft. Add questions, then publish.') : t('Assignment created as a draft. Add a rubric if you use one, then publish.'));
        return $this->redirect('/teacher/assignments/' . $id . '/edit');
    }

    public function show(int $id): Response
    {
        $user = $this->user();
        $a = Access::teacherAssignment($user, $id);
        // Excused students are not expected to hand in, so they count in neither figure.
        $stats = Db::one(
            'SELECT ' . Overrides::expectedSql('a') . ' AS enrolled, ' . Overrides::handedInSql('a') . " AS submitted,
                    (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id AND s.status = 'submitted' AND s.grading_status <> 'graded') AS ungraded,
                    (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id AND s.status = 'submitted' AND s.grading_status = 'graded' AND s.feedback_status <> 'released') AS unreleased,
                    (SELECT COUNT(DISTINCT s.student_id) FROM submissions s WHERE s.assignment_id = a.id AND s.status = 'submitted' AND s.is_late = 1) AS late,
                    (SELECT AVG(s.score / NULLIF(s.points_possible, 0) * 100) FROM submissions s WHERE s.assignment_id = a.id AND s.grading_status = 'graded' AND s.status = 'submitted') AS avg_pct,
                    (SELECT COUNT(*) FROM assignment_overrides o WHERE o.assignment_id = a.id AND o.excused = 1) AS excused,
                    (SELECT COUNT(*) FROM assignment_overrides o WHERE o.assignment_id = a.id AND o.due_at IS NOT NULL) AS extended
             FROM assignments a WHERE a.id = :a",
            ['a' => $id]
        );
        return $this->view('teacher/assignment', [
            'title' => $a['title'],
            'crumbs' => [[t('Assignments'), '/teacher/assignments'], [$a['title'], null]],
            'a' => $a,
            'stats' => $stats,
            'questions' => Assessments::questions($id),
            'problems' => $a['status'] === 'draft' ? Assessments::publishProblems($a) : [],
            'rubric' => json_decode((string) ($a['rubric'] ?? ''), true) ?: [],
            'questionStats' => \Sibol\Services\Insights::questions((int) $a['class_id'], $id),
            'answersAt' => Overrides::latestDue($id, $a['due_at']),
            'runningExtensions' => (int) Db::value('SELECT COUNT(*) FROM assignment_overrides WHERE assignment_id = :a AND due_at > UTC_TIMESTAMP()', ['a' => $id]),
            'copyPanel' => SectionCopies::panel($user, 'assignment', $a),
            'katex' => true,
        ]);
    }

    /** Copies the assignment or quiz into other sections the teacher teaches, as drafts. */
    public function copy(int $id): Response
    {
        Access::teacherAssignment($this->user(), $id);
        $this->copyToSections('assignment', $id, '/teacher/assignments/' . $id);
        return $this->redirect('/teacher/assignments/' . $id);
    }

    public function edit(int $id): Response
    {
        $user = $this->user();
        $a = Access::teacherAssignment($user, $id);
        $classes = TeacherRepo::classes($user);
        return $this->view('teacher/assignment_edit', [
            'title' => t('Edit :title', ['title' => $a['title']]),
            'crumbs' => [[t('Assignments'), '/teacher/assignments'], [$a['title'], '/teacher/assignments/' . $id], [t('Edit'), null]],
            'assignment' => $a,
            'kind' => $a['kind'],
            'classes' => $classes,
            'selectedClass' => (int) $a['class_id'],
            'modules' => $this->modules([(int) $a['class_id']]),
            'categories' => Grades::categoryOptions((int) $user['organization_id']),
            'questions' => Assessments::questions($id),
            'rubric' => json_decode((string) ($a['rubric'] ?? ''), true) ?: [],
            'locked' => Assessments::hasAttempts($id),
            'problems' => Assessments::publishProblems($a),
            'materials' => Db::all(
                "SELECT m.id, m.title FROM learning_materials m JOIN material_versions mv ON mv.id = m.current_version_id AND mv.processing_status = 'ready'
                 WHERE m.class_id = :c AND m.visibility <> 'archived' AND m.ai_enabled = 1 ORDER BY m.title",
                ['c' => (int) $a['class_id']]
            ),
            'resources' => array_map('intval', Db::column('SELECT material_id FROM assignment_resources WHERE assignment_id = :a', ['a' => $id])),
            'allMaterials' => Db::all("SELECT id, title FROM learning_materials WHERE class_id = :c AND visibility <> 'archived' ORDER BY title", ['c' => (int) $a['class_id']]),
            'aiRemaining' => Usage::remainingToday($user),
            'katex' => true,
            'scripts' => ['builder.js'],
        ]);
    }

    public function update(int $id): Response
    {
        $user = $this->user();
        $a = Access::teacherAssignment($user, $id);
        $back = '/teacher/assignments/' . $id . '/edit';
        $data = $this->settings($back, $a);
        Db::update('assignments', $this->settingsRow($data, (int) $a['class_id']) + ['updated_at' => Db::now()], ['id' => $id]);
        // Resources visible to students on the assignment page.
        $resourceIds = array_map('intval', $this->request->arr('resources'));
        Db::delete('assignment_resources', ['assignment_id' => $id]);
        foreach ($resourceIds as $mid) {
            if ((int) Db::value('SELECT COUNT(*) FROM learning_materials WHERE id = :m AND class_id = :c', ['m' => $mid, 'c' => (int) $a['class_id']])) {
                Db::insert('assignment_resources', ['assignment_id' => $id, 'material_id' => $mid]);
            }
        }
        if ($a['status'] === 'published' && !Assessments::hasAttempts($id)) {
            Assessments::snapshot($id, (int) $user['id']);
        }
        Audit::log('assignment.updated', 'assignment', $id);
        $this->flash('success', t('Settings saved.'));
        return $this->redirect($back);
    }

    // ------------------------------------------------------------------ questions

    public function addQuestion(int $id): Response
    {
        $user = $this->user();
        $a = $this->editable($user, $id);
        [$row, $errors] = Assessments::normaliseQuestion($this->questionInput());
        if ($errors) {
            throw new ValidationException($errors, $this->request->all(), '/teacher/assignments/' . $id . '/edit#new-question');
        }
        Assessments::addQuestion($id, $row);
        $this->afterQuestionChange($a, (int) $user['id']);
        $this->flash('success', t('Question added.'));
        return $this->redirect('/teacher/assignments/' . $id . '/edit#questions');
    }

    public function updateQuestion(int $assignmentId, int $id): Response
    {
        $user = $this->user();
        $a = $this->editable($user, $assignmentId);
        $q = $this->question($assignmentId, $id);
        [$row, $errors] = Assessments::normaliseQuestion($this->questionInput());
        if ($errors) {
            throw new ValidationException($errors, $this->request->all(), '/teacher/assignments/' . $assignmentId . '/edit#q-' . $id);
        }
        Db::update('questions', $row + ['reviewed' => 1, 'updated_at' => Db::now()], ['id' => (int) $q['id']]);
        Assessments::recalcPoints($assignmentId);
        $this->afterQuestionChange($a, (int) $user['id']);
        $this->flash('success', t('Question saved.'));
        return $this->redirect('/teacher/assignments/' . $assignmentId . '/edit#q-' . $id);
    }

    public function deleteQuestion(int $assignmentId, int $id): Response
    {
        $user = $this->user();
        $a = $this->editable($user, $assignmentId);
        $this->question($assignmentId, $id);
        Db::delete('questions', ['id' => $id]);
        Assessments::recalcPoints($assignmentId);
        $this->afterQuestionChange($a, (int) $user['id']);
        $this->flash('success', t('Question removed.'));
        return $this->redirect('/teacher/assignments/' . $assignmentId . '/edit#questions');
    }

    public function approveQuestion(int $assignmentId, int $id): Response
    {
        $user = $this->user();
        $a = $this->editable($user, $assignmentId);
        $this->question($assignmentId, $id);
        Db::update('questions', ['reviewed' => 1, 'updated_at' => Db::now()], ['id' => $id]);
        $this->afterQuestionChange($a, (int) $user['id']);
        $this->flash('success', t('Question approved.'));
        return $this->redirect('/teacher/assignments/' . $assignmentId . '/edit#q-' . $id);
    }

    public function approveAll(int $id): Response
    {
        $user = $this->user();
        $a = $this->editable($user, $id);
        $n = Db::exec('UPDATE questions SET reviewed = 1, updated_at = :n WHERE assignment_id = :a AND reviewed = 0', ['n' => Db::now(), 'a' => $id]);
        $this->afterQuestionChange($a, (int) $user['id']);
        Audit::log('assignment.ai_questions_approved', 'assignment', $id, ['count' => $n]);
        $this->flash('success', tp(':count question approved.', ':count questions approved.', $n));
        return $this->redirect('/teacher/assignments/' . $id . '/edit#questions');
    }

    public function moveQuestion(int $assignmentId, int $id): Response
    {
        $user = $this->user();
        $a = $this->editable($user, $assignmentId);
        $q = $this->question($assignmentId, $id);
        $up = $this->request->str('direction') === 'up';
        $other = Db::one(
            'SELECT * FROM questions WHERE assignment_id = :a AND (position ' . ($up ? '<' : '>') . ' :p OR (position = :p2 AND id ' . ($up ? '<' : '>') . ' :id))
             ORDER BY position ' . ($up ? 'DESC' : 'ASC') . ', id ' . ($up ? 'DESC' : 'ASC') . ' LIMIT 1',
            ['a' => $assignmentId, 'p' => (int) $q['position'], 'p2' => (int) $q['position'], 'id' => $id]
        );
        if ($other) {
            Db::transaction(static function () use ($q, $other): void {
                $a = (int) $q['position'];
                $b = (int) $other['position'];
                if ($a === $b) {
                    $b = $a + ($a > 0 ? -1 : 1);
                }
                Db::update('questions', ['position' => $b], ['id' => (int) $q['id']]);
                Db::update('questions', ['position' => $a], ['id' => (int) $other['id']]);
            });
            $this->afterQuestionChange($a, (int) $user['id']);
        }
        return $this->redirect('/teacher/assignments/' . $assignmentId . '/edit#q-' . $id);
    }

    public function generateQuestions(int $id): Response
    {
        $user = $this->user();
        $a = $this->editable($user, $id);
        $back = '/teacher/assignments/' . $id . '/edit#ai-draft';
        $materialIds = array_map('intval', $this->request->arr('materials'));
        $topic = mb_substr($this->request->str('topic'), 0, 160);
        $counts = [
            'multiple_choice' => max(0, min(10, (int) $this->request->int('count_mc', 3))),
            'true_false' => max(0, min(10, (int) $this->request->int('count_tf', 1))),
            'short_answer' => max(0, min(10, (int) $this->request->int('count_sa', 1))),
        ];
        if (array_sum($counts) === 0) {
            $this->fail('count_mc', t('Ask for at least one question.'), $back);
        }
        if (!$materialIds) {
            $this->fail('materials', t('Choose at least one material to draft from.'), $back);
        }
        $blocked = Usage::blockedReason($user, 'quiz_draft');
        if ($blocked !== null) {
            $this->flash('error', $blocked);
            return $this->redirect($back);
        }
        $sources = [];
        foreach ($materialIds as $mid) {
            $material = Access::teacherMaterial($user, $mid);
            if ((int) $material['class_id'] !== (int) $a['class_id']) {
                continue;
            }
            foreach ($topic !== '' ? Retrieval::search($user, $topic, (int) $a['class_id'], $mid, 4) : Retrieval::materialPassages($user, $mid, 8000) as $s) {
                $sources[] = $s;
            }
        }
        foreach ($sources as $i => &$s) {
            $s['n'] = $i + 1;
        }
        unset($s);
        try {
            $drafts = Generators::questionDrafts($user, $a + ['grade_level' => Db::value('SELECT grade_level FROM classes WHERE id = :c', ['c' => (int) $a['class_id']])], array_slice($sources, 0, 12), $topic, $counts);
        } catch (AiException $e) {
            $this->flash('error', $e->getMessage());
            return $this->redirect($back);
        }
        $added = 0;
        foreach ($drafts as $draft) {
            [$row, $errors] = Assessments::normaliseQuestion($draft);
            if (!$errors) {
                Assessments::addQuestion($id, $row, true);
                $added++;
            }
        }
        Db::update('assignments', ['ai_assisted' => 1], ['id' => $id]);
        Audit::log('assignment.ai_questions_drafted', 'assignment', $id, ['count' => $added]);
        $this->flash($added ? 'success' : 'error', $added
            ? tp(':count question drafted. Review and approve it before publishing.', ':count questions drafted. Review and approve each one before publishing.', $added)
            : t('No usable questions came back. Try different materials or a narrower topic.'));
        return $this->redirect('/teacher/assignments/' . $id . '/edit#questions');
    }

    public function saveRubric(int $id): Response
    {
        $user = $this->user();
        $a = Access::teacherAssignment($user, $id);
        $titles = (array) $this->request->arr('criterion_title');
        $points = (array) $this->request->arr('criterion_points');
        $descs = (array) $this->request->arr('criterion_description');
        $criteria = [];
        foreach ($titles as $i => $title) {
            $title = trim((string) $title);
            if ($title === '') {
                continue;
            }
            $p = is_numeric($points[$i] ?? null) ? round((float) $points[$i], 2) : 0;
            if ($p <= 0 || $p > 100) {
                $this->fail('rubric', t('Each criterion needs points between 0.5 and 100.'), '/teacher/assignments/' . $id . '/edit#rubric');
            }
            $criteria[] = ['id' => 'c' . (count($criteria) + 1), 'title' => mb_substr($title, 0, 120), 'points' => $p, 'description' => mb_substr(trim((string) ($descs[$i] ?? '')), 0, 400)];
        }
        $update = ['rubric' => $criteria ? json_encode($criteria, JSON_UNESCAPED_UNICODE) : null, 'updated_at' => Db::now()];
        $hasQuestions = (int) Db::value('SELECT COUNT(*) FROM questions WHERE assignment_id = :a', ['a' => $id]) > 0;
        if ($criteria && !$hasQuestions) {
            $update['points_possible'] = array_sum(array_column($criteria, 'points'));
        }
        Db::update('assignments', $update, ['id' => $id]);
        if ($a['status'] === 'published' && !Assessments::hasAttempts($id)) {
            Assessments::snapshot($id, (int) $user['id']);
        }
        $this->flash('success', $criteria ? t('Rubric saved. The assignment is worth :n points.', ['n' => fmt_number(array_sum(array_column($criteria, 'points')), 2)]) : t('Rubric removed.'));
        return $this->redirect('/teacher/assignments/' . $id . '/edit#rubric');
    }

    public function preview(int $id): Response
    {
        $user = $this->user();
        $a = Access::teacherAssignment($user, $id);
        return $this->view('teacher/assignment_preview', [
            'title' => t('Preview: :title', ['title' => $a['title']]),
            'crumbs' => [[t('Assignments'), '/teacher/assignments'], [$a['title'], '/teacher/assignments/' . $id], [t('Student preview'), null]],
            'assignment' => $a,
            'questions' => array_map(static fn ($q) => Assessments::publicQuestion($q, false), Assessments::questions($id)),
            'rubric' => json_decode((string) ($a['rubric'] ?? ''), true) ?: [],
            'resources' => Db::all('SELECT m.id, m.title, m.kind FROM assignment_resources ar JOIN learning_materials m ON m.id = ar.material_id WHERE ar.assignment_id = :a', ['a' => $id]),
            'katex' => true,
        ]);
    }

    // ------------------------------------------------------------------ lifecycle

    public function publish(int $id): Response
    {
        $user = $this->user();
        $a = Access::teacherAssignment($user, $id);
        if ($a['class_status'] !== 'active') {
            throw HttpException::forbidden(t('This class is archived.'));
        }
        $problems = Assessments::publishProblems($a);
        if ($problems) {
            $this->flash('error', t('Not published yet: :problems', ['problems' => implode(' ', $problems)]));
            return $this->redirect('/teacher/assignments/' . $id . '/edit');
        }
        Assessments::publish($id, (int) $user['id']);
        $opens = $a['opens_at'] && $a['opens_at'] > Db::now();
        $this->flash('success', $opens
            ? t('Published. Students will be able to open it from :date.', ['date' => fmt_date($a['opens_at'], 'datetime_short')])
            : t('Published. Students in :class have been notified.', ['class' => $a['class_name']]));
        return $this->redirect('/teacher/assignments/' . $id);
    }

    public function close(int $id): Response
    {
        $user = $this->user();
        Access::teacherAssignment($user, $id);
        Db::update('assignments', ['status' => 'closed', 'closed_at' => Db::now(), 'updated_at' => Db::now()], ['id' => $id]);
        Audit::log('assignment.closed', 'assignment', $id);
        $this->flash('success', t('Closed. Students can no longer submit or start attempts.'));
        return $this->redirect('/teacher/assignments/' . $id);
    }

    public function reopen(int $id): Response
    {
        $user = $this->user();
        Access::teacherAssignment($user, $id);
        Db::update('assignments', ['status' => 'published', 'closed_at' => null, 'updated_at' => Db::now()], ['id' => $id]);
        Audit::log('assignment.reopened', 'assignment', $id);
        $this->flash('success', t('Reopened. Students can submit again until the due date and late-work rules allow.'));
        return $this->redirect('/teacher/assignments/' . $id);
    }

    public function archive(int $id): Response
    {
        $user = $this->user();
        Access::teacherAssignment($user, $id);
        Db::update('assignments', ['status' => 'archived', 'updated_at' => Db::now()], ['id' => $id]);
        Audit::log('assignment.archived', 'assignment', $id);
        $this->flash('success', t('Archived. Students no longer see it; grades already released stay on record.'));
        return $this->redirect('/teacher/assignments');
    }

    public function duplicate(int $id): Response
    {
        $user = $this->user();
        $a = Access::teacherAssignment($user, $id);
        $newId = Db::transaction(static function () use ($a, $user, $id): int {
            $copy = $a;
            foreach (['id', 'class_name', 'class_status', 'subject_name', 'current_version_id', 'published_at', 'closed_at', 'answers_released_at'] as $k) {
                unset($copy[$k]);
            }
            $copy['title'] = mb_substr(t(':title (copy)', ['title' => $a['title']]), 0, 200);
            $copy['status'] = 'draft';
            $copy['copied_from_id'] = null;
            $copy['created_by'] = (int) $user['id'];
            $copy['created_at'] = Db::now();
            $copy['updated_at'] = Db::now();
            $newId = Db::insert('assignments', $copy);
            foreach (Db::all('SELECT * FROM questions WHERE assignment_id = :a ORDER BY position', ['a' => $id]) as $q) {
                unset($q['id']);
                $q['assignment_id'] = $newId;
                $q['created_at'] = Db::now();
                $q['updated_at'] = Db::now();
                Db::insert('questions', $q);
            }
            foreach (Db::column('SELECT material_id FROM assignment_resources WHERE assignment_id = :a', ['a' => $id]) as $mid) {
                Db::insert('assignment_resources', ['assignment_id' => $newId, 'material_id' => (int) $mid]);
            }
            return $newId;
        });
        Audit::log('assignment.duplicated', 'assignment', $newId, ['from' => $id]);
        $this->flash('success', t('Copy created as a draft. Check the dates before publishing.'));
        return $this->redirect('/teacher/assignments/' . $newId . '/edit');
    }

    public function destroy(int $id): Response
    {
        $user = $this->user();
        $a = Access::teacherAssignment($user, $id);
        if ($a['status'] !== 'draft' || Assessments::hasAttempts($id)) {
            throw HttpException::forbidden(t('Only drafts that no student has started can be deleted. Archive it instead.'));
        }
        Db::delete('assignments', ['id' => $id]);
        Audit::log('assignment.deleted', 'assignment', $id, ['title' => mb_substr((string) $a['title'], 0, 80)]);
        $this->flash('success', t('Draft deleted.'));
        return $this->redirect('/teacher/assignments');
    }

    public function releaseAnswers(int $id): Response
    {
        $user = $this->user();
        $a = Access::teacherAssignment($user, $id);
        Db::update('assignments', ['answers_released_at' => Db::now(), 'answer_key_release' => 'manual'], ['id' => $id]);
        $students = array_map('intval', Db::column("SELECT DISTINCT student_id FROM submissions WHERE assignment_id = :a AND status = 'submitted'", ['a' => $id]));
        Notifier::send((int) $user['organization_id'], $students, 'feedback', t('Answers released: :title', ['title' => $a['title']]), t('You can now review the correct answers.'), '/student/assignments/' . $id);
        Audit::log('assignment.answers_released', 'assignment', $id);
        $this->flash('success', t('Correct answers released to students who submitted.'));
        return $this->redirect('/teacher/assignments/' . $id);
    }

    // ------------------------------------------------------------------ helpers

    /** @return array<string,mixed> */
    private function settings(string $back, ?array $existing = null): array
    {
        $data = $this->validate([
            'class_id' => $existing ? 'nullable|int' : 'required|int',
            'kind' => $existing ? 'nullable|in:assignment,quiz' : 'required|in:assignment,quiz',
            'module_id' => 'nullable|int',
            'title' => 'required|string|max:200',
            'instructions' => 'nullable|string|max:20000',
            'objectives' => 'nullable|string|max:2000',
            'category' => 'nullable|string|max:60',
            'opens_at' => 'nullable|datetime',
            'due_at' => 'nullable|datetime',
            'late_policy' => 'required|in:not_accepted,accepted,penalty',
            'late_penalty_pct' => 'nullable|int|min:0|max:100',
            'max_attempts' => 'nullable|int|min:1|max:20',
            'time_limit_minutes' => 'nullable|int|min:1|max:600',
            'points_possible' => 'nullable|numeric|min:0|max:1000',
            'allow_text' => 'bool',
            'allow_files' => 'bool',
            'allow_resubmission' => 'bool',
            'feedback_release' => 'required|in:manual,immediate',
            'answer_key_release' => 'required|in:never,after_submit,after_due,manual',
        ], [
            'class_id' => t('Class'), 'title' => t('Title'), 'opens_at' => t('Opens'), 'due_at' => t('Due'),
            'late_penalty_pct' => t('Late penalty'), 'max_attempts' => t('Attempts allowed'), 'time_limit_minutes' => t('Time limit'), 'points_possible' => t('Points'),
        ], $back);
        $data['opens_at_utc'] = $data['opens_at'] ? Dates::toUtc((string) $data['opens_at']) : null;
        $data['due_at_utc'] = $data['due_at'] ? Dates::toUtc((string) $data['due_at']) : null;
        if ($data['opens_at_utc'] && $data['due_at_utc'] && $data['due_at_utc'] <= $data['opens_at_utc']) {
            $this->fail('due_at', t('The due date must be after the opening date.'), $back);
        }
        if ($data['category'] !== null && $data['category'] !== '' && !array_key_exists($data['category'], Grades::categoryOptions((int) $this->user()['organization_id']))) {
            $this->fail('category', t('Choose one of your school\'s grading categories.'), $back);
        }
        $data['kind'] = $existing ? $existing['kind'] : $data['kind'];
        return $data;
    }

    /** @return array<string,mixed> */
    private function settingsRow(array $data, int $classId): array
    {
        $moduleId = $data['module_id'] ? (int) $data['module_id'] : null;
        if ($moduleId !== null && !(int) Db::value('SELECT COUNT(*) FROM modules WHERE id = :m AND class_id = :c', ['m' => $moduleId, 'c' => $classId])) {
            $moduleId = null;
        }
        $isQuiz = $data['kind'] === 'quiz';
        $row = [
            'module_id' => $moduleId,
            'title' => $data['title'],
            'instructions' => $data['instructions'],
            'objectives' => $data['objectives'],
            'category' => $data['category'] ?: null,
            'opens_at' => $data['opens_at_utc'],
            'due_at' => $data['due_at_utc'],
            'late_policy' => $data['late_policy'],
            'late_penalty_pct' => $data['late_policy'] === 'penalty' ? (int) ($data['late_penalty_pct'] ?? 10) : 0,
            'max_attempts' => $isQuiz ? (int) ($data['max_attempts'] ?? 1) : 1,
            'time_limit_minutes' => $isQuiz && $data['time_limit_minutes'] ? (int) $data['time_limit_minutes'] : null,
            'allow_text' => $isQuiz ? 0 : ((bool) $data['allow_text'] ? 1 : 0),
            'allow_files' => $isQuiz ? 0 : ((bool) $data['allow_files'] ? 1 : 0),
            'allow_resubmission' => $isQuiz ? 0 : ((bool) $data['allow_resubmission'] ? 1 : 0),
            'feedback_release' => $data['feedback_release'],
            'answer_key_release' => $data['answer_key_release'],
        ];
        if (!$isQuiz && $data['points_possible'] !== null) {
            $row['points_possible'] = (float) $data['points_possible'];
        }
        return $row;
    }

    /** @return array<string,mixed> */
    private function questionInput(): array
    {
        return [
            'qtype' => $this->request->str('qtype'),
            'prompt' => $this->request->str('prompt'),
            'points' => $this->request->str('points'),
            'options' => $this->request->arr('options'),
            'correct' => $this->request->str('correct'),
            'accepted' => $this->request->str('accepted'),
            'case_sensitive' => $this->request->bool('case_sensitive'),
            'explanation' => $this->request->str('explanation'),
            'topic' => $this->request->str('topic'),
        ];
    }

    /** Questions can change until a student starts; after that, duplicate instead. @return array<string,mixed> */
    private function editable(array $user, int $id): array
    {
        $a = Access::teacherAssignment($user, $id);
        if (Assessments::hasAttempts($id)) {
            throw new HttpException(409, t('Students have already started this work, so its questions are locked. Duplicate it to make a changed version.'));
        }
        return $a;
    }

    private function afterQuestionChange(array $a, int $actorId): void
    {
        if ($a['status'] === 'published') {
            Assessments::snapshot((int) $a['id'], $actorId);
        }
    }

    /** @return array<string,mixed> */
    private function question(int $assignmentId, int $id): array
    {
        $q = Db::one('SELECT * FROM questions WHERE id = :id AND assignment_id = :a', ['id' => $id, 'a' => $assignmentId]);
        if (!$q) {
            throw HttpException::notFound();
        }
        return $q;
    }

    /** @param list<int> $classIds @return list<array<string,mixed>> */
    private function modules(array $classIds): array
    {
        if (!$classIds) {
            return [];
        }
        [$in, $p] = Db::in($classIds, 'c');
        return Db::all("SELECT id, title, class_id FROM modules WHERE class_id IN $in ORDER BY position", $p);
    }
}
