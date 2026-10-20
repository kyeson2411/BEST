<?php

declare(strict_types=1);

namespace Sibol\Controllers\Student;

use DateTimeImmutable;
use Sibol\Controllers\Controller;
use Sibol\Core\Audit;
use Sibol\Core\Db;
use Sibol\Core\HttpException;
use Sibol\Core\Response;
use Sibol\Core\Uploads;
use Sibol\Core\ValidationException;
use Sibol\Policies\Access;
use Sibol\Repositories\StudentRepo;
use Sibol\Services\Assessments;
use Sibol\Services\Settings;
use Sibol\Services\SpacedReview;

final class AssignmentController extends Controller
{
    private const MAX_FILES = 5;
    private const MAX_FILE_MB = 10;

    public function index(): Response
    {
        $user = $this->user();
        $filters = ['upcoming', 'overdue', 'submitted', 'graded', 'excused', 'all'];
        $filter = in_array($this->request->str('filter'), $filters, true) ? $this->request->str('filter') : 'upcoming';
        $classId = $this->request->int('class_id');
        $classes = StudentRepo::classes($user);
        if ($classId !== null && !in_array($classId, array_map(static fn ($c) => (int) $c['id'], $classes), true)) {
            $classId = null;
        }
        $all = StudentRepo::assignments($user, 'all', $classId);
        $counts = array_fill_keys($filters, 0);
        foreach ($all as $row) {
            $counts[$row['bucket']]++;
            $counts['all']++;
        }
        $rows = $filter === 'all' ? $all : array_values(array_filter($all, static fn ($r) => $r['bucket'] === $filter));
        if (in_array($filter, ['submitted', 'graded'], true)) {
            usort($rows, static fn ($a, $b) => strcmp((string) $b['due_at'], (string) $a['due_at']));
        }
        return $this->view('student/assignments', [
            'title' => t('Assignments'),
            'filter' => $filter,
            'classId' => $classId,
            'classes' => $classes,
            'rows' => $rows,
            'counts' => $counts,
        ]);
    }

    public function show(int $id): Response
    {
        $user = $this->user();
        $assignment = Assessments::forStudent((int) $user['id'], $id, true);
        $crumbs = [[t('Assignments'), '/student/assignments'], [$assignment['title'], null]];
        if ($assignment['not_open']) {
            return $this->view('student/assignment_scheduled', ['title' => $assignment['title'], 'crumbs' => $crumbs, 'assignment' => $assignment]);
        }
        $state = Assessments::studentState($assignment, (int) $user['id']);
        $latest = $state['latest'];
        $reviewSub = null;
        foreach ($state['submissions'] as $s) {
            if ($s['status'] === 'submitted') {
                $reviewSub = $s;
                break;
            }
        }
        $snapshot = Assessments::snapshotFor($reviewSub['assignment_version_id'] ?? $assignment['current_version_id']);
        $answersReleased = Assessments::answersReleased($assignment, $reviewSub);
        $answers = [];
        if ($reviewSub) {
            foreach (Db::all('SELECT * FROM submission_answers WHERE submission_id = :s', ['s' => (int) $reviewSub['id']]) as $row) {
                $answers[(int) $row['question_id']] = $row;
            }
        }
        $resources = Db::all(
            'SELECT m.id, m.title, m.kind FROM assignment_resources ar JOIN learning_materials m ON m.id = ar.material_id
             WHERE ar.assignment_id = :a AND ' . Access::releasedMaterialSql('m'),
            ['a' => $id]
        );
        $files = $latest ? Db::all('SELECT * FROM submission_files WHERE submission_id = :s ORDER BY id', ['s' => (int) $latest['id']]) : [];
        // Before a quiz the student has not taken yet: how many questions from this class wait in their review queue.
        $toReview = $assignment['kind'] === 'quiz' && $state['can_start'] && !$state['submitted_count'] && Settings::get((int) $user['organization_id'], 'features.spaced_review', true)
            ? count(SpacedReview::queue($user, new DateTimeImmutable('now'), (int) $assignment['class_id']))
            : 0;

        return $this->view('student/assignment', [
            'title' => $assignment['title'],
            'crumbs' => $crumbs,
            'assignment' => $assignment,
            'state' => $state,
            'latest' => $latest,
            'reviewSub' => $reviewSub,
            'snapshot' => $snapshot,
            'answersReleased' => $answersReleased,
            'answers' => $answers,
            'resources' => $resources,
            'files' => $files,
            'rubric' => json_decode((string) ($assignment['rubric'] ?? ''), true) ?: [],
            'maxFileMb' => min(self::MAX_FILE_MB, (int) (Uploads::maxBytes() / 1048576)),
            'toReview' => $toReview,
            'katex' => true,
        ]);
    }

    public function saveDraft(int $id): Response
    {
        return $this->storeWork($id, false);
    }

    public function submit(int $id): Response
    {
        return $this->storeWork($id, true);
    }

    private function storeWork(int $id, bool $final): Response
    {
        $user = $this->user();
        $assignment = Assessments::forStudent((int) $user['id'], $id);
        if ($assignment['kind'] !== 'assignment') {
            throw HttpException::notFound();
        }
        $back = '/student/assignments/' . $id;
        $state = Assessments::studentState($assignment, (int) $user['id']);
        if (!$state['can_submit']) {
            $this->flash('error', $state['closed'] ? t('This assignment is closed.') : ($state['late_blocked'] ? t('The due date has passed and this assignment does not accept late work.') : t('This assignment cannot be changed now.')));
            return $this->redirect($back);
        }

        $body = (int) $assignment['allow_text'] ? $this->request->str('body') : '';
        if (mb_strlen($body) > 20000) {
            throw new ValidationException(['body' => t('Your response is longer than 20,000 characters. Attach longer work as a file.')], ['body' => $body], $back);
        }
        $newFiles = (int) $assignment['allow_files'] ? $this->request->fileList('files') : [];
        $maxBytes = min(self::MAX_FILE_MB, (int) (Uploads::maxBytes() / 1048576)) * 1048576;
        foreach ($newFiles as $file) {
            $problem = Uploads::validate($file, Uploads::SUBMISSION, $maxBytes);
            if ($problem !== null) {
                throw new ValidationException(['files' => $file['name'] . ': ' . $problem], ['body' => $body], $back);
            }
        }

        $sub = $state['in_progress'];
        if ($sub === null && $state['latest'] && $state['latest']['status'] === 'submitted' && (int) $assignment['allow_resubmission'] !== 1) {
            $this->flash('info', t('You have already submitted this assignment.'));
            return $this->redirect($back);
        }
        $removeIds = array_map('intval', $this->request->arr('remove_files'));

        $sub = Db::transaction(function () use ($sub, $assignment, $user, $state, $body, $newFiles, $removeIds, $back): array {
            if ($sub === null) {
                $subId = Db::insert('submissions', [
                    'organization_id' => (int) $assignment['organization_id'],
                    'assignment_id' => (int) $assignment['id'],
                    'student_id' => (int) $user['id'],
                    'assignment_version_id' => $assignment['current_version_id'] !== null ? (int) $assignment['current_version_id'] : null,
                    'attempt_no' => $state['submitted_count'] + 1,
                    'status' => 'in_progress',
                    'body' => $body !== '' ? $body : null,
                    'points_possible' => (float) $assignment['points_possible'],
                    'started_at' => Db::now(),
                    'created_at' => Db::now(),
                    'updated_at' => Db::now(),
                ]);
            } else {
                $subId = (int) $sub['id'];
                Db::update('submissions', ['body' => $body !== '' ? $body : null, 'updated_at' => Db::now()], ['id' => $subId]);
            }
            foreach ($removeIds as $fileId) {
                $file = Db::one('SELECT * FROM submission_files WHERE id = :f AND submission_id = :s', ['f' => $fileId, 's' => $subId]);
                if ($file) {
                    Db::delete('submission_files', ['id' => $fileId]);
                    Uploads::delete((string) $file['stored_path']);
                }
            }
            $existing = (int) Db::value('SELECT COUNT(*) FROM submission_files WHERE submission_id = :s', ['s' => $subId]);
            if ($existing + count($newFiles) > self::MAX_FILES) {
                throw new ValidationException(['files' => t('Attach up to :n files.', ['n' => self::MAX_FILES])], ['body' => $body], $back);
            }
            foreach ($newFiles as $file) {
                $stored = Uploads::store($file, 'submissions');
                Db::insert('submission_files', [
                    'submission_id' => $subId,
                    'original_filename' => $stored['original'],
                    'stored_path' => $stored['path'],
                    'mime_type' => $stored['mime'],
                    'size_bytes' => $stored['size'],
                    'sha256' => $stored['sha256'],
                    'created_at' => Db::now(),
                ]);
            }
            return Db::one('SELECT * FROM submissions WHERE id = :id', ['id' => $subId]);
        });

        if (!$final) {
            $this->flash('success', t('Draft saved. It is not submitted yet.'));
            return $this->redirect($back);
        }
        $hasFiles = (int) Db::value('SELECT COUNT(*) FROM submission_files WHERE submission_id = :s', ['s' => (int) $sub['id']]) > 0;
        if (trim((string) $sub['body']) === '' && !$hasFiles) {
            throw new ValidationException(['body' => t('Add your response or attach a file before submitting.')], ['body' => $body], $back);
        }
        $sub = Assessments::submitAssignment($assignment, $sub);
        Audit::log('submission.submitted', 'submission', (int) $sub['id'], ['assignment_id' => $id, 'late' => (bool) $sub['is_late']]);
        $this->flash('success', t('Submitted. Your receipt code is :code.', ['code' => $sub['receipt_code']]));
        return $this->redirect('/student/submissions/' . (int) $sub['id']);
    }

    public function startQuiz(int $id): Response
    {
        $user = $this->user();
        $assignment = Assessments::forStudent((int) $user['id'], $id);
        if ($assignment['kind'] !== 'quiz') {
            throw HttpException::notFound();
        }
        try {
            $sub = Assessments::startAttempt($assignment, (int) $user['id']);
        } catch (HttpException $e) {
            $this->flash('error', $e->getMessage());
            return $this->redirect('/student/assignments/' . $id);
        }
        Audit::log('quiz.started', 'submission', (int) $sub['id'], ['assignment_id' => $id]);
        return $this->redirect('/student/assignments/' . $id . '/attempt');
    }

    public function attempt(int $id): Response
    {
        $user = $this->user();
        $assignment = Assessments::forStudent((int) $user['id'], $id);
        $state = Assessments::studentState($assignment, (int) $user['id']);
        $sub = $assignment['kind'] === 'quiz' ? $state['in_progress'] : null;
        if (!$sub) {
            return $this->redirect('/student/assignments/' . $id);
        }
        $snapshot = Assessments::snapshotFor($sub['assignment_version_id'] !== null ? (int) $sub['assignment_version_id'] : (int) $assignment['current_version_id']);
        if (!$snapshot) {
            throw new HttpException(409, t('This quiz has no published questions yet.'));
        }
        $sub = Assessments::resumeReopened($sub);
        if ($this->expired($sub, 0)) {
            $sub = Assessments::submitAttempt($assignment, $sub, $snapshot);
            $this->flash('warning', t('Time was up, so your saved answers were submitted.'));
            return $this->redirect('/student/submissions/' . (int) $sub['id']);
        }
        $saved = [];
        foreach (Db::all('SELECT question_id, response FROM submission_answers WHERE submission_id = :s', ['s' => (int) $sub['id']]) as $row) {
            $saved[(int) $row['question_id']] = (string) $row['response'];
        }
        $questions = array_map(static fn ($q) => Assessments::publicQuestion($q, false), $snapshot['questions'] ?? []);
        return $this->view('student/quiz_attempt', [
            'title' => $assignment['title'],
            'assignment' => $assignment,
            'submission' => $sub,
            'questions' => $questions,
            'saved' => $saved,
            'katex' => true,
            'scripts' => ['quiz.js'],
        ]);
    }

    public function saveAttempt(int $id): Response
    {
        $user = $this->user();
        $assignment = Assessments::forStudent((int) $user['id'], $id);
        $state = Assessments::studentState($assignment, (int) $user['id']);
        $sub = $assignment['kind'] === 'quiz' ? $state['in_progress'] : null;
        if (!$sub) {
            if ($this->request->wantsJson()) {
                return $this->json(['error' => t('This attempt is no longer open.')], 409);
            }
            $this->flash('info', t('This attempt has already been submitted.'));
            return $this->redirect('/student/assignments/' . $id);
        }
        $snapshot = Assessments::snapshotFor($sub['assignment_version_id'] !== null ? (int) $sub['assignment_version_id'] : (int) $assignment['current_version_id']);
        // Answers sent more than a minute after the deadline are not accepted; earlier saved answers are.
        if (!$this->expired($sub, 60)) {
            Assessments::saveAnswers($sub, $snapshot ?? [], $this->request->arr('answers'));
        }
        $expired = $this->expired($sub, 0);
        if ($this->request->str('action') === 'submit' || $expired) {
            $sub = Assessments::submitAttempt($assignment, $sub, $snapshot ?? []);
            Audit::log('quiz.submitted', 'submission', (int) $sub['id'], ['assignment_id' => $id]);
            if ($this->request->wantsJson()) {
                return $this->json(['submitted' => true, 'redirect' => url('/student/submissions/' . (int) $sub['id'])]);
            }
            $this->flash($expired ? 'warning' : 'success', $expired ? t('Time was up, so your saved answers were submitted.') : t('Quiz submitted. Your receipt code is :code.', ['code' => $sub['receipt_code']]));
            return $this->redirect('/student/submissions/' . (int) $sub['id']);
        }
        if ($this->request->wantsJson()) {
            return $this->json(['saved' => true, 'saved_at' => fmt_date(Db::now(), 'time')]);
        }
        $this->flash('success', t('Answers saved.'));
        return $this->redirect('/student/assignments/' . $id . '/attempt');
    }

    public function receipt(int $id): Response
    {
        $user = $this->user();
        $sub = Db::one('SELECT * FROM submissions WHERE id = :id AND student_id = :u', ['id' => $id, 'u' => (int) $user['id']]);
        if (!$sub || $sub['status'] === 'in_progress') {
            throw HttpException::notFound();
        }
        $assignment = Assessments::forStudent((int) $user['id'], (int) $sub['assignment_id']);
        return $this->view('student/receipt', [
            'title' => t('Submission receipt'),
            'crumbs' => [[t('Assignments'), '/student/assignments'], [$assignment['title'], '/student/assignments/' . (int) $assignment['id']], [t('Receipt'), null]],
            'assignment' => $assignment,
            'submission' => $sub,
            'files' => Db::all('SELECT * FROM submission_files WHERE submission_id = :s ORDER BY id', ['s' => $id]),
            'answered' => (int) Db::value("SELECT COUNT(*) FROM submission_answers WHERE submission_id = :s AND response IS NOT NULL AND response <> ''", ['s' => $id]),
            'questionCount' => count((Assessments::snapshotFor($sub['assignment_version_id'] !== null ? (int) $sub['assignment_version_id'] : null) ?? [])['questions'] ?? []),
            'savedAnswers' => $assignment['kind'] === 'quiz' ? Assessments::savedAnswers($sub) : [],
        ]);
    }

    private function expired(array $sub, int $graceSeconds): bool
    {
        return $sub['deadline_at'] !== null && time() > strtotime($sub['deadline_at'] . ' UTC') + $graceSeconds;
    }
}
