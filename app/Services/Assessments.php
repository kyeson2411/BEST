<?php

declare(strict_types=1);

namespace Sibol\Services;

use Sibol\Core\Audit;
use Sibol\Core\Db;
use Sibol\Core\Dates;
use Sibol\Core\HttpException;

/**
 * Assignments and quizzes: authoring, publishing snapshots, attempts, auto-grading,
 * manual grading and release. Answer keys never leave the server unless the
 * assignment's release rule allows it.
 */
final class Assessments
{
    public const QTYPES = ['multiple_choice', 'true_false', 'short_answer', 'essay'];

    // ------------------------------------------------------------------ authoring

    /** @return list<array<string,mixed>> */
    public static function questions(int $assignmentId): array
    {
        $rows = Db::all('SELECT * FROM questions WHERE assignment_id = :a ORDER BY position, id', ['a' => $assignmentId]);
        foreach ($rows as &$row) {
            $row['options'] = json_decode((string) ($row['options'] ?? ''), true) ?: [];
            $row['answer_key'] = json_decode((string) ($row['answer_key'] ?? ''), true) ?: [];
        }
        return $rows;
    }

    /**
     * Normalises question input from the builder form or an AI draft.
     * @return array{0:array<string,mixed>,1:array<string,string>} [row, errors]
     */
    public static function normaliseQuestion(array $in): array
    {
        $errors = [];
        $type = (string) ($in['qtype'] ?? '');
        if (!in_array($type, self::QTYPES, true)) {
            $errors['qtype'] = t('Choose a question type.');
        }
        $prompt = trim((string) ($in['prompt'] ?? ''));
        if ($prompt === '') {
            $errors['prompt'] = t('Write the question.');
        } elseif (mb_strlen($prompt) > 4000) {
            $errors['prompt'] = t('Keep the question under 4,000 characters.');
        }
        $points = is_numeric($in['points'] ?? null) ? round((float) $in['points'], 2) : 1.0;
        if ($points < 0 || $points > 100) {
            $errors['points'] = t('Points must be between 0 and 100.');
        }
        $options = [];
        $key = [];
        if ($type === 'multiple_choice') {
            $letters = range('A', 'H');
            $i = 0;
            foreach ((array) ($in['options'] ?? []) as $opt) {
                $text = trim((string) (is_array($opt) ? ($opt['text'] ?? '') : $opt));
                if ($text !== '' && $i < count($letters)) {
                    $options[] = ['key' => $letters[$i], 'text' => mb_substr($text, 0, 500)];
                    $i++;
                }
            }
            if (count($options) < 2) {
                $errors['options'] = t('Add at least two answer choices.');
            }
            $correct = strtoupper(trim((string) ($in['correct'] ?? '')));
            if (!in_array($correct, array_column($options, 'key'), true)) {
                $errors['correct'] = t('Mark which choice is correct.');
            }
            $key = ['correct' => $correct];
        } elseif ($type === 'true_false') {
            $correct = (string) ($in['correct'] ?? '');
            if (!in_array($correct, ['true', 'false'], true)) {
                $errors['correct'] = t('Choose whether the statement is true or false.');
            }
            $key = ['correct' => $correct === 'true'];
        } elseif ($type === 'short_answer') {
            $accepted = array_values(array_filter(array_map('trim', preg_split('/\r?\n|\|/', (string) ($in['accepted'] ?? '')) ?: [])));
            if (!$accepted) {
                $errors['accepted'] = t('List at least one accepted answer, one per line.');
            }
            $key = ['accepted' => array_slice($accepted, 0, 20), 'case_sensitive' => !empty($in['case_sensitive'])];
        }
        $row = [
            'qtype' => $type,
            'prompt' => $prompt,
            'points' => $points,
            'options' => $options ? json_encode($options, JSON_UNESCAPED_UNICODE) : null,
            'answer_key' => $key ? json_encode($key, JSON_UNESCAPED_UNICODE) : null,
            'explanation' => trim((string) ($in['explanation'] ?? '')) ?: null,
            'topic' => mb_substr(trim((string) ($in['topic'] ?? '')), 0, 120) ?: null,
            'source_ref' => mb_substr(trim((string) ($in['source_ref'] ?? '')), 0, 255) ?: null,
        ];
        return [$row, $errors];
    }

    public static function addQuestion(int $assignmentId, array $row, bool $aiGenerated = false): int
    {
        $position = (int) Db::value('SELECT COALESCE(MAX(position), 0) + 1 FROM questions WHERE assignment_id = :a', ['a' => $assignmentId]);
        $id = Db::insert('questions', $row + [
            'assignment_id' => $assignmentId,
            'position' => $position,
            'ai_generated' => $aiGenerated ? 1 : 0,
            'reviewed' => $aiGenerated ? 0 : 1,
            'created_at' => Db::now(),
            'updated_at' => Db::now(),
        ]);
        self::recalcPoints($assignmentId);
        return $id;
    }

    public static function recalcPoints(int $assignmentId): void
    {
        $kind = (string) Db::value('SELECT kind FROM assignments WHERE id = :a', ['a' => $assignmentId]);
        $sum = (float) Db::value('SELECT COALESCE(SUM(points), 0) FROM questions WHERE assignment_id = :a', ['a' => $assignmentId]);
        if ($kind === 'quiz' || $sum > 0) {
            Db::update('assignments', ['points_possible' => $sum, 'updated_at' => Db::now()], ['id' => $assignmentId]);
        }
    }

    /** Once any student has started, questions are locked (duplicate the assignment to change them). */
    public static function hasAttempts(int $assignmentId): bool
    {
        return (int) Db::value('SELECT COUNT(*) FROM submissions WHERE assignment_id = :a', ['a' => $assignmentId]) > 0;
    }

    /** Reasons the assignment cannot be published yet. @return list<string> */
    public static function publishProblems(array $assignment): array
    {
        $problems = [];
        $questions = self::questions((int) $assignment['id']);
        if (trim((string) $assignment['title']) === '') {
            $problems[] = t('Add a title.');
        }
        if ($assignment['kind'] === 'quiz' && !$questions) {
            $problems[] = t('Add at least one question.');
        }
        if ($assignment['kind'] === 'assignment' && !$questions && !(int) $assignment['allow_text'] && !(int) $assignment['allow_files']) {
            $problems[] = t('Allow a text response or a file upload so students can submit.');
        }
        $unreviewed = count(array_filter($questions, static fn ($q) => !(int) $q['reviewed']));
        if ($unreviewed > 0) {
            $problems[] = tp('Review :count AI-drafted question before publishing.', 'Review :count AI-drafted questions before publishing.', $unreviewed);
        }
        if ($assignment['due_at'] === null) {
            $problems[] = t('Set a due date.');
        }
        if ((float) $assignment['points_possible'] <= 0 && !$questions) {
            $problems[] = t('Set the points this assignment is worth.');
        }
        return $problems;
    }

    /** Snapshot questions + keys so later edits never change an attempt already taken. */
    public static function snapshot(int $assignmentId, ?int $actorId): int
    {
        $assignment = Db::one('SELECT * FROM assignments WHERE id = :a', ['a' => $assignmentId]);
        $questions = array_map(static function (array $q): array {
            return [
                'id' => (int) $q['id'],
                'qtype' => $q['qtype'],
                'prompt' => $q['prompt'],
                'points' => (float) $q['points'],
                'options' => $q['options'],
                'answer_key' => $q['answer_key'],
                'explanation' => $q['explanation'],
                'topic' => $q['topic'],
            ];
        }, self::questions($assignmentId));
        $versionNo = (int) Db::value('SELECT COALESCE(MAX(version_no), 0) + 1 FROM assignment_versions WHERE assignment_id = :a', ['a' => $assignmentId]);
        $versionId = Db::insert('assignment_versions', [
            'assignment_id' => $assignmentId,
            'version_no' => $versionNo,
            'snapshot' => json_encode([
                'title' => $assignment['title'],
                'points_possible' => (float) $assignment['points_possible'],
                'questions' => $questions,
            ], JSON_UNESCAPED_UNICODE),
            'published_by' => $actorId,
            'created_at' => Db::now(),
        ]);
        Db::update('assignments', ['current_version_id' => $versionId], ['id' => $assignmentId]);
        return $versionId;
    }

    public static function publish(int $assignmentId, int $actorId, bool $notify = true): void
    {
        $assignment = Db::one('SELECT a.*, c.name AS class_name FROM assignments a JOIN classes c ON c.id = a.class_id WHERE a.id = :a', ['a' => $assignmentId]);
        Db::transaction(static function () use ($assignmentId, $actorId): void {
            self::snapshot($assignmentId, $actorId);
            Db::update('assignments', ['status' => 'published', 'published_at' => Db::now(), 'closed_at' => null, 'updated_at' => Db::now()], ['id' => $assignmentId]);
        });
        if ($notify && $assignment) {
            $opens = $assignment['opens_at'] && $assignment['opens_at'] > Db::now()
                ? t('Opens :date.', ['date' => Dates::format((string) $assignment['opens_at'], 'datetime_short')]) . ' '
                : '';
            Notifier::send(
                (int) $assignment['organization_id'],
                Notifier::classStudents((int) $assignment['class_id']),
                'assignment',
                ($assignment['kind'] === 'quiz' ? t('New quiz: :title', ['title' => $assignment['title']]) : t('New assignment: :title', ['title' => $assignment['title']])),
                $opens . $assignment['class_name'] . '. ' . Dates::due((string) $assignment['due_at'])['label'],
                '/student/assignments/' . $assignmentId,
                'assignment:' . $assignmentId
            );
        }
        Audit::log('assignment.published', 'assignment', $assignmentId);
    }

    /** @return array<string,mixed>|null */
    public static function snapshotFor(?int $versionId): ?array
    {
        if (!$versionId) {
            return null;
        }
        $raw = Db::value('SELECT snapshot FROM assignment_versions WHERE id = :v', ['v' => $versionId]);
        $data = json_decode((string) $raw, true);
        return is_array($data) ? $data : null;
    }

    // ------------------------------------------------------------------ student access

    /**
     * The assignment if the student may see it: enrolled (active), class active, published or closed, and open.
     * With $allowScheduled, a published assignment that opens later is returned with not_open = true
     * (title and dates only — callers must not show questions or accept work).
     * due_at is the student's own due date when their teacher set one (see Overrides::apply).
     * @return array<string,mixed>
     */
    public static function forStudent(int $studentId, int $assignmentId, bool $allowScheduled = false): array
    {
        $row = Db::one(
            "SELECT a.*, c.name AS class_name, c.id AS class_id, s.name AS subject_name
             FROM assignments a
             JOIN classes c ON c.id = a.class_id AND c.status = 'active'
             JOIN subjects s ON s.id = c.subject_id
             JOIN class_memberships cm ON cm.class_id = a.class_id AND cm.user_id = :u AND cm.role = 'student' AND cm.status = 'active'
             WHERE a.id = :a AND a.status IN ('published', 'closed')",
            ['u' => $studentId, 'a' => $assignmentId]
        );
        if (!$row) {
            throw HttpException::notFound();
        }
        $row['not_open'] = $row['opens_at'] !== null && $row['opens_at'] > Db::now();
        if ($row['not_open'] && !$allowScheduled) {
            throw new HttpException(409, t('This work opens :date.', ['date' => Dates::format((string) $row['opens_at'], 'datetime_short')]));
        }
        return Overrides::apply($row, $studentId);
    }

    /** @return list<array<string,mixed>> newest first */
    public static function studentSubmissions(int $assignmentId, int $studentId): array
    {
        return Db::all(
            'SELECT * FROM submissions WHERE assignment_id = :a AND student_id = :u ORDER BY attempt_no DESC',
            ['a' => $assignmentId, 'u' => $studentId]
        );
    }

    /**
     * Everything the student screens need to decide what to show.
     * @return array<string,mixed>
     */
    public static function studentState(array $assignment, int $studentId): array
    {
        $subs = self::studentSubmissions((int) $assignment['id'], $studentId);
        $latest = $subs[0] ?? null;
        $submittedCount = count(array_filter($subs, static fn ($s) => $s['status'] !== 'in_progress'));
        $now = Db::now();
        $pastDue = $assignment['due_at'] !== null && $assignment['due_at'] < $now;
        // Closing stops everyone except a student whose own extension is still running.
        $closed = $assignment['status'] === 'closed' && !(!empty($assignment['extended']) && !$pastDue);
        $lateBlocked = $pastDue && $assignment['late_policy'] === 'not_accepted';
        $returned = $latest && $latest['status'] === 'returned';
        $canWork = !$closed && (!$lateBlocked || $returned);

        if ($assignment['kind'] === 'quiz') {
            $inProgress = $latest && $latest['status'] === 'in_progress' ? $latest : null;
            $canStart = $canWork && !$inProgress && $submittedCount < (int) $assignment['max_attempts'];
        } else {
            $inProgress = $latest && in_array($latest['status'], ['in_progress', 'returned'], true) ? $latest : null;
            $canStart = false;
        }
        $canSubmit = $assignment['kind'] === 'assignment'
            ? $canWork && (!$latest || $latest['status'] !== 'submitted' || (int) $assignment['allow_resubmission'] === 1)
            : false;

        return [
            'submissions' => $subs,
            'latest' => $latest,
            'in_progress' => $inProgress,
            'submitted_count' => $submittedCount,
            'attempts_left' => max(0, (int) $assignment['max_attempts'] - $submittedCount),
            'past_due' => $pastDue,
            'closed' => $closed,
            'excused' => !empty($assignment['excused']),
            'late_blocked' => $lateBlocked,
            'can_start' => $canStart,
            'can_submit' => $canSubmit,
            'status_label' => self::studentStatus($assignment, $latest),
        ];
    }

    /** @return array{0:string,1:string} label + tone */
    public static function studentStatus(array $assignment, ?array $latest): array
    {
        $now = Db::now();
        if (!empty($assignment['excused'])) {
            return [t('Excused'), 'neutral'];
        }
        if ($latest && $latest['status'] === 'returned') {
            return [t('Returned for changes'), 'amber'];
        }
        if ($latest && $latest['status'] === 'submitted') {
            if ($latest['feedback_status'] === 'released') {
                return [t('Graded'), 'leaf'];
            }
            return [$latest['is_late'] ? t('Submitted late') : t('Submitted'), 'neutral'];
        }
        if ($assignment['due_at'] !== null && $assignment['due_at'] < $now) {
            return [$latest ? t('Draft, overdue') : t('Missing'), 'coral'];
        }
        if ($latest && $latest['status'] === 'in_progress') {
            return [$assignment['kind'] === 'quiz' ? t('In progress') : t('Draft saved'), 'amber'];
        }
        return [t('Not started'), 'neutral'];
    }

    public static function answersReleased(array $assignment, ?array $submission): bool
    {
        switch ($assignment['answer_key_release']) {
            case 'after_submit':
                return $submission !== null && $submission['status'] === 'submitted';
            case 'after_due':
                // Wait for the latest extension in the class, not only this student's own date.
                $due = Overrides::latestDue((int) $assignment['id'], array_key_exists('class_due_at', $assignment) ? $assignment['class_due_at'] : $assignment['due_at']);
                return $submission !== null && $submission['status'] === 'submitted' && $due !== null && $due <= Db::now();
            case 'manual':
                return $assignment['answers_released_at'] !== null && $submission !== null && $submission['status'] === 'submitted';
            default:
                return false;
        }
    }

    /**
     * Question as a student may see it. Keys and explanations only when released.
     * @return array<string,mixed>
     */
    public static function publicQuestion(array $q, bool $reveal): array
    {
        $options = is_array($q['options']) ? $q['options'] : (json_decode((string) $q['options'], true) ?: []);
        $out = [
            'id' => (int) $q['id'],
            'qtype' => $q['qtype'],
            'prompt' => $q['prompt'],
            'points' => (float) $q['points'],
            'options' => $options,
        ];
        if ($reveal) {
            $out['answer_key'] = is_array($q['answer_key']) ? $q['answer_key'] : (json_decode((string) $q['answer_key'], true) ?: []);
            $out['explanation'] = $q['explanation'] ?? null;
        }
        return $out;
    }

    // ------------------------------------------------------------------ attempts

    public static function startAttempt(array $assignment, int $studentId): array
    {
        $state = self::studentState($assignment, $studentId);
        if ($state['in_progress']) {
            return $state['in_progress'];
        }
        if (!$state['can_start']) {
            throw new HttpException(409, t('You cannot start another attempt for this quiz.'));
        }
        $deadline = self::attemptDeadline($assignment, time());
        $id = Db::insert('submissions', [
            'organization_id' => (int) $assignment['organization_id'],
            'assignment_id' => (int) $assignment['id'],
            'student_id' => $studentId,
            'assignment_version_id' => $assignment['current_version_id'] !== null ? (int) $assignment['current_version_id'] : null,
            'attempt_no' => $state['submitted_count'] + 1,
            'status' => 'in_progress',
            'started_at' => Db::now(),
            'deadline_at' => $deadline !== null ? gmdate('Y-m-d H:i:s', $deadline) : null,
            'points_possible' => (float) $assignment['points_possible'],
            'created_at' => Db::now(),
            'updated_at' => Db::now(),
        ]);
        return Db::one('SELECT * FROM submissions WHERE id = :id', ['id' => $id]);
    }

    /**
     * When an attempt started at $startedAt must end (Unix time), or null for no limit: the time limit plus
     * the student's extra minutes, and never past their due date when late work is not accepted.
     * @param array<string,mixed> $assignment as one student sees it (Overrides::apply)
     */
    public static function attemptDeadline(array $assignment, int $startedAt): ?int
    {
        $deadline = null;
        if ((int) $assignment['time_limit_minutes'] > 0) {
            $deadline = $startedAt + ((int) $assignment['time_limit_minutes'] + (int) ($assignment['extra_minutes'] ?? 0)) * 60;
        }
        if ($assignment['due_at'] !== null && $assignment['late_policy'] === 'not_accepted') {
            $due = (int) strtotime((string) $assignment['due_at'] . ' UTC');
            $deadline = $deadline === null ? $due : min($deadline, $due);
        }
        return $deadline;
    }

    /** Saves in-progress answers. @param array<int|string,mixed> $answers question id => response */
    public static function saveAnswers(array $submission, array $snapshot, array $answers): void
    {
        $byId = [];
        foreach ($snapshot['questions'] ?? [] as $q) {
            $byId[(int) $q['id']] = $q;
        }
        foreach ($answers as $qid => $response) {
            $qid = (int) $qid;
            if (!isset($byId[$qid])) {
                continue;
            }
            $value = is_array($response) ? '' : mb_substr(trim((string) $response), 0, 10000);
            Db::exec(
                'INSERT INTO submission_answers (submission_id, question_id, response, created_at, updated_at)
                 VALUES (:s, :q, :r, :c, :u)
                 ON DUPLICATE KEY UPDATE response = VALUES(response), updated_at = VALUES(updated_at)',
                ['s' => (int) $submission['id'], 'q' => $qid, 'r' => $value, 'c' => Db::now(), 'u' => Db::now()]
            );
        }
        Db::update('submissions', ['updated_at' => Db::now()], ['id' => (int) $submission['id']]);
    }

    /**
     * Grades objective questions and finalises an attempt.
     * @return array<string,mixed> the updated submission
     */
    public static function submitAttempt(array $assignment, array $submission, array $snapshot): array
    {
        $answers = [];
        foreach (Db::all('SELECT * FROM submission_answers WHERE submission_id = :s', ['s' => (int) $submission['id']]) as $row) {
            $answers[(int) $row['question_id']] = $row;
        }
        $auto = 0.0;
        $needsManual = false;
        foreach ($snapshot['questions'] ?? [] as $q) {
            $response = $answers[(int) $q['id']]['response'] ?? null;
            [$correct, $points, $manual] = self::autograde($q, $response);
            $needsManual = $needsManual || $manual;
            $auto += (float) ($points ?? 0);
            Db::exec(
                'INSERT INTO submission_answers (submission_id, question_id, response, is_correct, points_awarded, needs_manual, created_at, updated_at)
                 VALUES (:s, :q, :r, :c, :p, :m, :t1, :t2)
                 ON DUPLICATE KEY UPDATE is_correct = VALUES(is_correct), points_awarded = VALUES(points_awarded), needs_manual = VALUES(needs_manual), updated_at = VALUES(updated_at)',
                ['s' => (int) $submission['id'], 'q' => (int) $q['id'], 'r' => $response, 'c' => $correct === null ? null : ($correct ? 1 : 0), 'p' => $points, 'm' => $manual ? 1 : 0, 't1' => Db::now(), 't2' => Db::now()]
            );
        }
        return self::finalise($assignment, $submission, $auto, $needsManual);
    }

    /** Submits a written assignment (text and/or files already saved). */
    public static function submitAssignment(array $assignment, array $submission): array
    {
        $questions = self::snapshotFor($submission['assignment_version_id'] !== null ? (int) $submission['assignment_version_id'] : null);
        if ($questions && !empty($questions['questions'])) {
            return self::submitAttempt($assignment, $submission, $questions);
        }
        return self::finalise($assignment, $submission, null, true);
    }

    private static function finalise(array $assignment, array $submission, ?float $auto, bool $needsManual): array
    {
        $now = Db::now();
        $late = $assignment['due_at'] !== null && $now > $assignment['due_at'];
        $penalty = ($late && $assignment['late_policy'] === 'penalty') ? (int) $assignment['late_penalty_pct'] : 0;
        $score = $auto !== null ? round($auto * (100 - $penalty) / 100, 2) : null;
        $immediate = $assignment['feedback_release'] === 'immediate' && !$needsManual;
        $receipt = $submission['receipt_code'] ?: self::receiptCode();
        Db::update('submissions', [
            'status' => 'submitted',
            'submitted_at' => $now,
            'is_late' => $late ? 1 : 0,
            'auto_score' => $auto,
            'score' => $needsManual ? null : $score,
            'points_possible' => (float) $assignment['points_possible'],
            'grading_status' => $needsManual ? 'needs_review' : 'graded',
            'feedback_status' => $immediate ? 'released' : ($needsManual ? 'none' : 'draft'),
            'released_at' => $immediate ? $now : null,
            'graded_at' => $needsManual ? null : $now,
            'receipt_code' => $receipt,
            'updated_at' => $now,
        ], ['id' => (int) $submission['id']]);
        Db::insert('grading_history', [
            'submission_id' => (int) $submission['id'],
            'actor_id' => (int) $submission['student_id'],
            'action' => 'submitted',
            'detail' => json_encode(['late' => $late, 'auto_score' => $auto, 'penalty_pct' => $penalty]),
            'created_at' => $now,
        ]);
        Notifier::send(
            (int) $assignment['organization_id'],
            Notifier::classTeachers((int) $assignment['class_id']),
            'submission',
            t('New work to review: :title', ['title' => $assignment['title']]),
            null,
            '/teacher/assignments/' . (int) $assignment['id'] . '/submissions',
            'submitted:' . (int) $assignment['id'] . ':' . gmdate('Y-m-d')
        );
        return Db::one('SELECT * FROM submissions WHERE id = :id', ['id' => (int) $submission['id']]);
    }

    /**
     * @param array<string,mixed> $q snapshot question
     * @return array{0:?bool,1:?float,2:bool} [is_correct, points, needs_manual]
     */
    public static function autograde(array $q, ?string $response): array
    {
        $key = is_array($q['answer_key']) ? $q['answer_key'] : (json_decode((string) $q['answer_key'], true) ?: []);
        $points = (float) $q['points'];
        $response = $response === null ? '' : trim($response);
        switch ($q['qtype']) {
            case 'multiple_choice':
                $ok = $response !== '' && strtoupper($response) === strtoupper((string) ($key['correct'] ?? ''));
                return [$ok, $ok ? $points : 0.0, false];
            case 'true_false':
                $expected = !empty($key['correct']) ? 'true' : 'false';
                $ok = $response === $expected;
                return [$ok, $ok ? $points : 0.0, false];
            case 'short_answer':
                $norm = static function (string $s, bool $case): string {
                    $s = trim((string) preg_replace('/\s+/u', ' ', $s));
                    $s = rtrim($s, ".!");
                    return $case ? $s : mb_strtolower($s);
                };
                $case = !empty($key['case_sensitive']);
                $given = $norm($response, $case);
                foreach ((array) ($key['accepted'] ?? []) as $accepted) {
                    if ($given !== '' && $given === $norm((string) $accepted, $case)) {
                        return [true, $points, false];
                    }
                }
                return [false, 0.0, false];
            default:
                return [null, null, true];
        }
    }

    private static function receiptCode(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 10; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while ((int) Db::value('SELECT COUNT(*) FROM submissions WHERE receipt_code = :c', ['c' => $code]) > 0);
        return $code;
    }

    // ------------------------------------------------------------------ grading

    /**
     * Saves a teacher's grade as a draft (not visible to the student until released).
     * @param array{points?:array<int,mixed>,comments?:array<int,string>,rubric?:array<string,mixed>,score?:mixed,feedback?:string} $data
     */
    public static function grade(array $submission, array $assignment, array $data, int $teacherId): void
    {
        $snapshot = self::snapshotFor($submission['assignment_version_id'] !== null ? (int) $submission['assignment_version_id'] : null);
        $questions = $snapshot['questions'] ?? [];
        $old = $submission['score'];
        $total = null;
        if ($questions) {
            $total = 0.0;
            foreach ($questions as $q) {
                $qid = (int) $q['id'];
                $max = (float) $q['points'];
                $given = $data['points'][$qid] ?? null;
                $existing = Db::one('SELECT * FROM submission_answers WHERE submission_id = :s AND question_id = :q', ['s' => (int) $submission['id'], 'q' => $qid]);
                $points = is_numeric($given) ? max(0.0, min($max, round((float) $given, 2))) : ($existing['points_awarded'] ?? null);
                $comment = isset($data['comments'][$qid]) ? mb_substr(trim((string) $data['comments'][$qid]), 0, 2000) : ($existing['teacher_comment'] ?? null);
                if ($existing) {
                    Db::update('submission_answers', [
                        'points_awarded' => $points,
                        'needs_manual' => $points === null ? 1 : 0,
                        'teacher_comment' => $comment ?: null,
                        'is_correct' => $points === null ? null : ((float) $points >= $max ? 1 : ((float) $points > 0 ? null : 0)),
                        'updated_at' => Db::now(),
                    ], ['id' => (int) $existing['id']]);
                } else {
                    Db::insert('submission_answers', [
                        'submission_id' => (int) $submission['id'], 'question_id' => $qid, 'response' => null,
                        'points_awarded' => $points, 'needs_manual' => $points === null ? 1 : 0, 'teacher_comment' => $comment ?: null,
                        'created_at' => Db::now(), 'updated_at' => Db::now(),
                    ]);
                }
                $total += (float) ($points ?? 0);
            }
        }
        if (array_key_exists('score', $data) && is_numeric($data['score']) && !$questions) {
            $total = max(0.0, min((float) $assignment['points_possible'], round((float) $data['score'], 2)));
        }
        $rubricScores = $data['rubric'] ?? null;
        if (is_array($rubricScores) && $rubricScores && !$questions) {
            $total = array_sum(array_map('floatval', array_filter($rubricScores, 'is_numeric')));
            $total = max(0.0, min((float) $assignment['points_possible'], $total));
        }
        $pending = $questions ? (int) Db::value('SELECT COUNT(*) FROM submission_answers WHERE submission_id = :s AND needs_manual = 1', ['s' => (int) $submission['id']]) : ($total === null ? 1 : 0);
        $penalty = ((int) $submission['is_late'] === 1 && $assignment['late_policy'] === 'penalty') ? (int) $assignment['late_penalty_pct'] : 0;
        $final = $total !== null ? round($total * (100 - $penalty) / 100, 2) : null;

        Db::update('submissions', [
            'score' => $pending ? null : $final,
            'grading_status' => $pending ? 'needs_review' : 'graded',
            'feedback' => isset($data['feedback']) ? (mb_substr(trim((string) $data['feedback']), 0, 10000) ?: null) : $submission['feedback'],
            'rubric_scores' => is_array($rubricScores) ? json_encode($rubricScores) : $submission['rubric_scores'],
            'feedback_status' => $submission['feedback_status'] === 'released' ? 'released' : 'draft',
            'graded_by' => $teacherId,
            'graded_at' => $pending ? null : Db::now(),
            'updated_at' => Db::now(),
        ], ['id' => (int) $submission['id']]);

        Db::insert('grading_history', [
            'submission_id' => (int) $submission['id'],
            'actor_id' => $teacherId,
            'action' => 'graded',
            'detail' => json_encode(['old_score' => $old, 'new_score' => $pending ? null : $final, 'penalty_pct' => $penalty]),
            'created_at' => Db::now(),
        ]);
        Audit::log('submission.graded', 'submission', (int) $submission['id'], ['assignment_id' => (int) $assignment['id']]);
    }

    public static function release(array $submission, array $assignment, int $teacherId): bool
    {
        if ($submission['grading_status'] !== 'graded') {
            return false;
        }
        Db::update('submissions', ['feedback_status' => 'released', 'released_at' => Db::now(), 'updated_at' => Db::now()], ['id' => (int) $submission['id']]);
        Db::insert('grading_history', [
            'submission_id' => (int) $submission['id'], 'actor_id' => $teacherId, 'action' => 'released',
            'detail' => null, 'created_at' => Db::now(),
        ]);
        Notifier::send(
            (int) $assignment['organization_id'],
            [(int) $submission['student_id']],
            'feedback',
            t('Feedback released: :title', ['title' => $assignment['title']]),
            t('Your teacher has returned your grade and comments.'),
            '/student/assignments/' . (int) $assignment['id'],
            'released:' . (int) $submission['id'] . ':' . time()
        );
        Audit::log('submission.feedback_released', 'submission', (int) $submission['id']);
        return true;
    }
}
