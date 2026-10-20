<?php

declare(strict_types=1);

namespace Sibol\Tests\Integration;

use Sibol\Core\Auth;
use Sibol\Core\Db;
use Sibol\Services\Assessments;
use Sibol\Services\Overrides;

/**
 * Attempts that survive bad connections: the receipt shows which answers reached the server, and a
 * teacher can reopen an attempt with minutes that start when the student opens it again.
 */
final class QuizAttemptsTest extends IntegrationTestCase
{
    /** @var list<int> */
    private array $submissions = [];

    protected function tearDown(): void
    {
        foreach ($this->submissions as $id) {
            Db::delete('submissions', ['id' => $id]);
        }
        $this->submissions = [];
        Db::exec('DELETE FROM assignment_overrides');
        Db::exec("DELETE FROM notifications WHERE dedupe_key LIKE 'reopened:%' OR dedupe_key LIKE 'override:%'");
        parent::tearDown();
    }

    public function testTheReceiptListsWhichAnswersReachedTheServer(): void
    {
        [$quiz, $andrea, $sub, $snapshot] = $this->startHeredityQuiz();
        Assessments::saveAnswers($sub, $snapshot, [(int) $snapshot['questions'][0]['id'] => 'B']);
        $sub = Assessments::submitAttempt($quiz, $sub, $snapshot);

        $saved = Assessments::savedAnswers($sub);
        self::assertCount(5, $saved);
        self::assertSame([1, 2, 3, 4, 5], array_column($saved, 'n'));
        self::assertSame([true, false, false, false, false], array_column($saved, 'saved'));
        self::assertStringContainsString('heterozygous', $saved[0]['prompt']);
    }

    public function testAReopenedAttemptGetsItsMinutesWhenTheStudentOpensIt(): void
    {
        [$quiz, $andrea, $sub, $snapshot] = $this->startHeredityQuiz();
        $first = (int) $snapshot['questions'][0]['id'];
        Assessments::saveAnswers($sub, $snapshot, [$first => 'B']);
        $sub = Assessments::submitAttempt($quiz, $sub, $snapshot);
        self::assertTrue(Assessments::canReopen($sub, $quiz));

        $ramon = $this->person('ramon.dizon');
        Auth::actingAs($ramon);
        Assessments::reopenAttempt($sub, $quiz, (int) $ramon['id'], 12);
        $open = $this->fresh($sub);
        self::assertSame('in_progress', $open['status']);
        self::assertNull($open['deadline_at'], 'The clock waits for the student');
        self::assertSame(12, (int) $open['resume_minutes']);
        self::assertNull($open['score']);
        self::assertSame('ungraded', $open['grading_status']);
        self::assertSame('B', Db::value('SELECT response FROM submission_answers WHERE submission_id = :s AND question_id = :q', ['s' => (int) $sub['id'], 'q' => $first]), 'Saved answers stay');
        self::assertSame(1, (int) Db::value("SELECT COUNT(*) FROM grading_history WHERE submission_id = :s AND action = 'reopened'", ['s' => (int) $sub['id']]));
        self::assertSame(1, (int) Db::value("SELECT COUNT(*) FROM notifications WHERE user_id = :u AND dedupe_key LIKE 'reopened:%'", ['u' => (int) $andrea['id']]));
        $log = Db::one("SELECT metadata FROM audit_logs WHERE action = 'submission.reopened' AND target_id = :s", ['s' => (int) $sub['id']]);
        self::assertSame(['minutes' => 12], json_decode((string) $log['metadata'], true));

        // The student opens it: twelve minutes from now, and opening it again does not move the clock.
        $resumed = Assessments::resumeReopened($open);
        $deadline = (int) strtotime($resumed['deadline_at'] . ' UTC');
        self::assertEqualsWithDelta(time() + 12 * 60, $deadline, 5);
        self::assertSame($resumed['deadline_at'], Assessments::resumeReopened($resumed)['deadline_at']);

        // Changing the student's extra minutes later does not re-time a reopened attempt.
        Overrides::save($ramon, $quiz, (int) $andrea['id'], null, 15, false);
        self::assertSame($resumed['deadline_at'], $this->fresh($sub)['deadline_at']);

        // Handing it in again marks it again.
        Auth::actingAs($andrea);
        $again = Assessments::submitAttempt(Assessments::forStudent((int) $andrea['id'], (int) $quiz['id']), $this->fresh($sub), $snapshot);
        self::assertSame('submitted', $again['status']);
        self::assertNotNull($again['auto_score']);
    }

    public function testOnlyTheLatestSubmittedQuizAttemptCanBeReopened(): void
    {
        [$quiz, $andrea, $sub] = $this->startHeredityQuiz();
        self::assertFalse(Assessments::canReopen($sub, $quiz), 'An attempt in progress is still open');

        $lab = Db::one("SELECT * FROM assignments WHERE title LIKE 'Lab report%'");
        $kyla = Db::one("SELECT s.* FROM submissions s JOIN users u ON u.id = s.student_id WHERE s.assignment_id = :a AND u.username = 'kyla.navarro'", ['a' => (int) $lab['id']]);
        self::assertFalse(Assessments::canReopen($kyla, $lab), 'Written assignments are returned for changes instead');

        $respiratory = Db::one("SELECT * FROM assignments WHERE title = 'Respiratory and circulatory systems check' AND class_id = :c", ['c' => $this->classId('Science 9 – Narra')]);
        $older = Db::one("SELECT s.* FROM submissions s JOIN users u ON u.id = s.student_id WHERE s.assignment_id = :a AND u.username = 'patricia.lim' ORDER BY s.attempt_no LIMIT 1", ['a' => (int) $respiratory['id']]);
        $copy = $older;
        unset($copy['id']);
        $copy['attempt_no'] = (int) $older['attempt_no'] + 1;
        $copy['receipt_code'] = null;
        $this->submissions[] = Db::insert('submissions', $copy);
        self::assertFalse(Assessments::canReopen($older, $respiratory), 'A newer attempt exists');
    }

    /** @return array{0:array,1:array,2:array,3:array} quiz as Andrea sees it, Andrea, her new attempt, the snapshot */
    private function startHeredityQuiz(): array
    {
        $andrea = $this->person('andrea.cruz');
        Auth::actingAs($andrea);
        $id = (int) Db::value("SELECT id FROM assignments WHERE title = 'Heredity basics'");
        $quiz = Assessments::forStudent((int) $andrea['id'], $id);
        $sub = Assessments::startAttempt($quiz, (int) $andrea['id']);
        $this->submissions[] = (int) $sub['id'];
        return [$quiz, $andrea, $sub, Assessments::snapshotFor((int) $sub['assignment_version_id'])];
    }

    private function fresh(array $sub): array
    {
        return Db::one('SELECT * FROM submissions WHERE id = :id', ['id' => (int) $sub['id']]);
    }
}
