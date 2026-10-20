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
use Sibol\Services\Ai\Providers;
use Sibol\Services\Ai\Usage;
use Sibol\Services\Assessments;
use Sibol\Services\Notifier;

final class SubmissionController extends Controller
{
    public function queue(): Response
    {
        $user = $this->user();
        return $this->view('teacher/review_queue', [
            'title' => t('To review'),
            'rows' => TeacherRepo::reviewQueue($user),
        ]);
    }

    public function index(int $id): Response
    {
        $user = $this->user();
        $a = Access::teacherAssignment($user, $id);
        $filter = $this->request->str('filter');
        $rows = Db::all(
            "SELECT u.id AS student_id, u.given_name, u.family_name, u.preferred_name, s.id, s.status, s.submitted_at, s.is_late, s.score, s.points_possible,
                    s.grading_status, s.feedback_status, s.attempt_no,
                    (SELECT COUNT(*) FROM submissions s2 WHERE s2.assignment_id = :a1 AND s2.student_id = u.id AND s2.status = 'submitted') AS attempts
             FROM class_memberships cm JOIN users u ON u.id = cm.user_id
             LEFT JOIN submissions s ON s.id = (
                SELECT s3.id FROM submissions s3 WHERE s3.assignment_id = :a2 AND s3.student_id = u.id
                ORDER BY (s3.status = 'submitted') DESC, s3.score DESC, s3.attempt_no DESC LIMIT 1)
             WHERE cm.class_id = :c AND cm.role = 'student' AND cm.status = 'active'
             ORDER BY u.family_name, u.given_name",
            ['a1' => $id, 'a2' => $id, 'c' => (int) $a['class_id']]
        );
        foreach ($rows as &$r) {
            $r['state'] = $this->state($r, $a);
        }
        unset($r);
        $counts = array_count_values(array_column($rows, 'state'));
        if ($filter !== '') {
            $rows = array_values(array_filter($rows, static fn ($r) => $r['state'] === $filter));
        }
        return $this->view('teacher/submissions', [
            'title' => t('Submissions: :title', ['title' => $a['title']]),
            'crumbs' => [[t('Assignments'), '/teacher/assignments'], [$a['title'], '/teacher/assignments/' . $id], [t('Submissions'), null]],
            'a' => $a,
            'rows' => $rows,
            'counts' => $counts,
            'filter' => $filter,
        ]);
    }

    public function show(int $id): Response
    {
        $user = $this->user();
        [$sub, $a] = Access::teacherSubmission($user, $id);
        $student = Db::one('SELECT id, given_name, family_name, preferred_name, email, grade_level FROM users WHERE id = :u', ['u' => (int) $sub['student_id']]);
        $snapshot = Assessments::snapshotFor($sub['assignment_version_id'] !== null ? (int) $sub['assignment_version_id'] : null);
        $answers = [];
        foreach (Db::all('SELECT * FROM submission_answers WHERE submission_id = :s', ['s' => $id]) as $row) {
            $answers[(int) $row['question_id']] = $row;
        }
        $suggestion = Db::one('SELECT * FROM ai_grading_suggestions WHERE submission_id = :s ORDER BY id DESC LIMIT 1', ['s' => $id]);
        // Neighbours for previous/next navigation among submitted work.
        $ids = array_map('intval', Db::column(
            "SELECT s.id FROM submissions s JOIN users u ON u.id = s.student_id WHERE s.assignment_id = :a AND s.status IN ('submitted','returned') ORDER BY u.family_name, u.given_name, s.attempt_no",
            ['a' => (int) $a['id']]
        ));
        $pos = array_search($id, $ids, true);
        return $this->view('teacher/submission', [
            'title' => t(':name: :title', ['name' => user_name($student), 'title' => $a['title']]),
            'crumbs' => [[t('Assignments'), '/teacher/assignments'], [$a['title'], '/teacher/assignments/' . (int) $a['id']], [t('Submissions'), '/teacher/assignments/' . (int) $a['id'] . '/submissions'], [user_name($student), null]],
            'sub' => $sub,
            'a' => $a,
            'student' => $student,
            'questions' => $snapshot['questions'] ?? [],
            'answers' => $answers,
            'files' => Db::all('SELECT * FROM submission_files WHERE submission_id = :s', ['s' => $id]),
            'rubric' => json_decode((string) ($a['rubric'] ?? ''), true) ?: [],
            'rubricScores' => json_decode((string) ($sub['rubric_scores'] ?? ''), true) ?: [],
            'history' => Db::all('SELECT h.*, u.given_name, u.family_name, u.preferred_name FROM grading_history h LEFT JOIN users u ON u.id = h.actor_id WHERE h.submission_id = :s ORDER BY h.created_at DESC', ['s' => $id]),
            'suggestion' => $suggestion ? json_decode((string) $suggestion['suggestion'], true) + ['created_at' => $suggestion['created_at'], 'model' => $suggestion['model']] : null,
            'aiAvailable' => !(Providers::make()->isDemo()),
            'prevId' => $pos !== false && $pos > 0 ? $ids[$pos - 1] : null,
            'nextId' => $pos !== false && $pos < count($ids) - 1 ? $ids[$pos + 1] : null,
            'katex' => true,
            'scripts' => ['grading.js'],
        ]);
    }

    public function grade(int $id): Response
    {
        $user = $this->user();
        [$sub, $a] = Access::teacherSubmission($user, $id);
        if ($sub['status'] === 'in_progress') {
            throw new HttpException(409, t('This work has not been submitted yet.'));
        }
        $back = '/teacher/submissions/' . $id;
        $feedback = $this->request->str('feedback');
        if (mb_strlen($feedback) > 10000) {
            $this->fail('feedback', t('Keep feedback under 10,000 characters.'), $back);
        }
        $data = [
            'points' => $this->request->arr('points'),
            'comments' => $this->request->arr('comments'),
            'feedback' => $feedback,
        ];
        $rubric = $this->request->arr('rubric');
        if ($rubric) {
            $data['rubric'] = array_map(static fn ($v) => is_numeric($v) ? (float) $v : null, $rubric);
        }
        $score = $this->request->input('score');
        if (is_string($score) && $score !== '') {
            if (!is_numeric($score) || (float) $score < 0 || (float) $score > (float) $a['points_possible']) {
                $this->fail('score', t('Enter a score between 0 and :max.', ['max' => fmt_number($a['points_possible'], 2)]), $back);
            }
            $data['score'] = (float) $score;
        }
        Assessments::grade($sub, $a, $data, (int) $user['id']);
        $fresh = Db::one('SELECT * FROM submissions WHERE id = :id', ['id' => $id]);
        if ($this->request->str('action') === 'release') {
            if (!Assessments::release($fresh, $a, (int) $user['id'])) {
                $this->flash('error', t('Saved, but not released: every question needs a mark first.'));
                return $this->redirect($back);
            }
            $this->flash('success', t('Grade and feedback released to the student.'));
        } else {
            $this->flash('success', $fresh['grading_status'] === 'graded' ? t('Grade saved. The student will see it when you release it.') : t('Saved. Some questions still need a mark.'));
        }
        $next = $this->request->str('then') === 'next' ? $this->request->int('next_id') : null;
        return $this->redirect($next ? '/teacher/submissions/' . $next : $back);
    }

    public function release(int $id): Response
    {
        $user = $this->user();
        [$sub, $a] = Access::teacherSubmission($user, $id);
        if (!Assessments::release($sub, $a, (int) $user['id'])) {
            $this->flash('error', t('Grade every question before releasing.'));
        } else {
            $this->flash('success', t('Released to the student.'));
        }
        return $this->redirect('/teacher/submissions/' . $id);
    }

    public function releaseAll(int $id): Response
    {
        $user = $this->user();
        $a = Access::teacherAssignment($user, $id);
        $rows = Db::all("SELECT * FROM submissions WHERE assignment_id = :a AND status = 'submitted' AND grading_status = 'graded' AND feedback_status <> 'released'", ['a' => $id]);
        $count = 0;
        foreach ($rows as $sub) {
            if (Assessments::release($sub, $a, (int) $user['id'])) {
                $count++;
            }
        }
        Audit::log('submission.feedback_released', 'assignment', $id, ['count' => $count]);
        $this->flash('success', $count ? tp('Released :count graded submission.', 'Released :count graded submissions.', $count) : t('There was nothing graded waiting to be released.'));
        return $this->redirect('/teacher/assignments/' . $id . '/submissions');
    }

    public function returnForResubmission(int $id): Response
    {
        $user = $this->user();
        [$sub, $a] = Access::teacherSubmission($user, $id);
        if ($a['kind'] !== 'assignment' || $sub['status'] !== 'submitted') {
            throw new HttpException(409, t('Only submitted assignments can be returned.'));
        }
        $note = $this->request->str('note');
        if ($note === '' || mb_strlen($note) > 2000) {
            $this->fail('note', t('Tell the student what to change (up to 2,000 characters).'), '/teacher/submissions/' . $id);
        }
        Db::update('submissions', ['status' => 'returned', 'feedback' => $note, 'feedback_status' => 'released', 'released_at' => Db::now(), 'grading_status' => 'ungraded', 'score' => null, 'updated_at' => Db::now()], ['id' => $id]);
        Db::insert('grading_history', ['submission_id' => $id, 'actor_id' => (int) $user['id'], 'action' => 'returned', 'detail' => null, 'created_at' => Db::now()]);
        Notifier::send((int) $user['organization_id'], [(int) $sub['student_id']], 'feedback', t('Returned for changes: :title', ['title' => $a['title']]), t('Your teacher asked you to revise and resubmit.'), '/student/assignments/' . (int) $a['id']);
        Audit::log('submission.returned', 'submission', $id);
        $this->flash('success', t('Returned to the student with your note.'));
        return $this->redirect('/teacher/assignments/' . (int) $a['id'] . '/submissions');
    }

    public function suggest(int $id): Response
    {
        $user = $this->user();
        [$sub, $a] = Access::teacherSubmission($user, $id);
        $back = '/teacher/submissions/' . $id . '#suggestion';
        $blocked = Usage::blockedReason($user, 'grading_suggestion');
        if ($blocked !== null) {
            $this->flash('error', $blocked);
            return $this->redirect($back);
        }
        if (trim((string) $sub['body']) === '') {
            $this->flash('error', t('There is no typed response to suggest a grade for. Attached files are not sent to the AI service.'));
            return $this->redirect($back);
        }
        try {
            $suggestion = Generators::gradingSuggestion($user, $a, $sub, json_decode((string) ($a['rubric'] ?? ''), true) ?: []);
        } catch (AiException $e) {
            $this->flash('error', $e->getMessage());
            return $this->redirect($back);
        }
        $provider = Providers::make();
        Db::insert('ai_grading_suggestions', [
            'submission_id' => $id, 'requested_by' => (int) $user['id'], 'suggestion' => json_encode($suggestion, JSON_UNESCAPED_UNICODE),
            'provider' => $provider->name(), 'model' => $provider->model(), 'is_demo' => $provider->isDemo() ? 1 : 0, 'created_at' => Db::now(),
        ]);
        Audit::log('submission.ai_suggestion', 'submission', $id);
        $this->flash('success', t('Suggestion ready. Review it; nothing changes until you save.'));
        return $this->redirect($back);
    }

    private function state(array $r, array $a): string
    {
        if ($r['id'] === null) {
            return ($a['due_at'] !== null && $a['due_at'] < Db::now()) ? 'missing' : 'not_started';
        }
        if ($r['status'] === 'in_progress') {
            return 'in_progress';
        }
        if ($r['status'] === 'returned') {
            return 'returned';
        }
        if ($r['grading_status'] !== 'graded') {
            return 'to_grade';
        }
        return $r['feedback_status'] === 'released' ? 'released' : 'graded';
    }
}
