<?php

declare(strict_types=1);

namespace Sibol\Tests\Integration;

use Sibol\Core\Auth;
use Sibol\Core\Db;
use Sibol\Core\HttpException;
use Sibol\Policies\Access;
use Sibol\Repositories\StudentRepo;
use Sibol\Services\Assessments;
use Sibol\Services\Grades;
use Sibol\Services\Overrides;
use Sibol\Services\PaperScores;

/** Item 20: scores for work done in class or on paper, and printed copies. */
final class PaperScoresTest extends IntegrationTestCase
{
    private const NARRA = 'Science 9 – Narra';

    /** @var list<int> */
    private array $created = [];

    protected function tearDown(): void
    {
        foreach ($this->created as $id) {
            Db::delete('assignments', ['id' => $id]);
        }
        parent::tearDown();
    }

    public function testScoresCountLikeOnlineWorkOnceReleased(): void
    {
        [$teacher, $paper] = $this->paperWork('-2 days', 20);
        $bea = (int) $this->person('bea.ramos')['id'];
        $nicole = (int) $this->person('nicole.tan')['id'];

        $counts = PaperScores::save($teacher, $paper, [$bea => '18', $nicole => '15,5'], [], [], false);
        self::assertSame(2, $counts['scores']);
        $sub = Db::one("SELECT * FROM submissions WHERE assignment_id = :a AND student_id = :u", ['a' => (int) $paper['id'], 'u' => $nicole]);
        self::assertSame('paper', $sub['source']);
        self::assertSame('15.50', (string) $sub['score'], 'a decimal comma is read as a point');
        self::assertSame('draft', $sub['feedback_status'], 'held until released');
        self::assertSame($paper['due_at'], $sub['submitted_at'], 'dated to the day in class');

        $teacherView = Grades::classBook((int) $paper['class_id'], (int) $paper['organization_id']);
        self::assertSame('graded', $teacherView['cells'][$bea][(int) $paper['id']]['state']);
        self::assertTrue($teacherView['cells'][$bea][(int) $paper['id']]['paper']);
        $studentView = Grades::classBook((int) $paper['class_id'], (int) $paper['organization_id'], true, $bea);
        self::assertSame('pending', $studentView['cells'][$bea][(int) $paper['id']]['state'], 'the student does not see a held score');

        $counts = PaperScores::save($teacher, $paper, [$bea => '18', $nicole => '15.5'], [], [], true);
        self::assertSame(0, $counts['scores'], 'unchanged scores are not saved again');
        self::assertSame(2, $counts['released']);
        $studentView = Grades::classBook((int) $paper['class_id'], (int) $paper['organization_id'], true, $bea);
        self::assertSame(18.0, $studentView['cells'][$bea][(int) $paper['id']]['score']);
        self::assertSame(1, (int) Db::value("SELECT COUNT(*) FROM notifications WHERE user_id = :u AND type = 'feedback' AND title LIKE :t", ['u' => $bea, 't' => '%' . $paper['title']]));

        $audit = Db::one("SELECT * FROM audit_logs WHERE action = 'assignment.paper_scores_saved' AND target_id = :a ORDER BY id DESC LIMIT 1", ['a' => (int) $paper['id']]);
        self::assertSame(['scores' => 0, 'cleared' => 0, 'absent' => 0, 'excused' => 0, 'released' => 2], json_decode((string) $audit['metadata'], true), 'counts only, never the scores');

        // Emptying a score removes it.
        $counts = PaperScores::save($teacher, $paper, [$bea => '', $nicole => '15.5'], [], [], false);
        self::assertSame(1, $counts['cleared']);
        self::assertNull(Db::value('SELECT id FROM submissions WHERE assignment_id = :a AND student_id = :u', ['a' => (int) $paper['id'], 'u' => $bea]));
    }

    public function testWorkInClassIsNeverMissingUnlessTheStudentWasAbsent(): void
    {
        [$teacher, $paper] = $this->paperWork('-1 days', 10);
        $id = (int) $paper['id'];
        $andrea = $this->person('andrea.cruz');
        $paolo = $this->person('paolo.aquino');

        $book = Grades::classBook((int) $paper['class_id'], (int) $paper['organization_id']);
        self::assertSame('unrecorded', $book['cells'][(int) $andrea['id']][$id]['state'], 'no score yet is not missing');
        $this->assertBucket($andrea, $id, 'submitted');
        self::assertNotContains($id, array_map('intval', array_column(StudentRepo::dueSoon($andrea), 'id')), 'not on the to-do list after the day');
        self::assertSame([t('Waiting for your score'), 'neutral'], Assessments::studentState(Assessments::forStudent((int) $andrea['id'], $id), (int) $andrea['id'])['status_label']);

        PaperScores::save($teacher, $paper, [], [(int) $paolo['id']], [], false);
        $book = Grades::classBook((int) $paper['class_id'], (int) $paper['organization_id']);
        self::assertSame('absent', $book['cells'][(int) $paolo['id']][$id]['state']);
        self::assertSame(1, Grades::overall([$id => $book['cells'][(int) $paolo['id']][$id]], $book['assignments'], $book['grading'])['missing'], 'absent counts as missing');
        $this->assertBucket($paolo, $id, 'overdue');
        self::assertContains($id, array_map('intval', array_column(StudentRepo::dueSoon($paolo), 'id')));
        self::assertSame([t('Absent'), 'coral'], Assessments::studentState(Assessments::forStudent((int) $paolo['id'], $id), (int) $paolo['id'])['status_label']);

        // A make-up date ahead: not absent until then.
        Overrides::save($teacher, $paper, (int) $paolo['id'], gmdate('Y-m-d H:i:s', time() + 3 * 86400), 0, false);
        self::assertSame(1, (int) Overrides::find($id, (int) $paolo['id'])['absent'], 'the per-student form keeps the absent mark');
        $book = Grades::classBook((int) $paper['class_id'], (int) $paper['organization_id']);
        self::assertSame('open', $book['cells'][(int) $paolo['id']][$id]['state']);
        $this->assertBucket($paolo, $id, 'upcoming');

        // The make-up score clears the absent mark.
        PaperScores::save($teacher, $paper, [(int) $paolo['id'] => '7'], [(int) $paolo['id']], [], false);
        self::assertSame(0, (int) Overrides::find($id, (int) $paolo['id'])['absent']);
        $book = Grades::classBook((int) $paper['class_id'], (int) $paper['organization_id']);
        self::assertSame('graded', $book['cells'][(int) $paolo['id']][$id]['state']);
    }

    public function testExcusedThroughTheGridIsNotCountedAndTellsTheStudent(): void
    {
        [$teacher, $paper] = $this->paperWork('-1 days', 10);
        $kyla = (int) $this->person('kyla.navarro')['id'];
        PaperScores::save($teacher, $paper, [], [], [$kyla], false);
        self::assertSame(1, (int) Overrides::find((int) $paper['id'], $kyla)['excused']);
        $book = Grades::classBook((int) $paper['class_id'], (int) $paper['organization_id']);
        self::assertSame('excused', $book['cells'][$kyla][(int) $paper['id']]['state']);
        self::assertSame(1, (int) Db::value('SELECT COUNT(*) FROM notifications WHERE user_id = :u AND title = :t', ['u' => $kyla, 't' => t('Excused: :title', ['title' => $paper['title']])]));

        PaperScores::save($teacher, $paper, [], [], [], false);
        self::assertNull(Overrides::find((int) $paper['id'], $kyla), 'unticked: back to the class settings');
    }

    public function testScoresAreCheckedBeforeAnythingIsSaved(): void
    {
        [, $paper] = $this->paperWork('-1 days', 10);
        $rows = PaperScores::grid($paper);
        $bea = (int) $this->person('bea.ramos')['id'];
        $nicole = (int) $this->person('nicole.tan')['id'];
        $problems = PaperScores::problems($paper, $rows, [$bea => '11', $nicole => 'ten', (string) $this->person('carlo.reyes')['id'] => '-1']);
        self::assertEqualsCanonicalizing([$bea, $nicole, (int) $this->person('carlo.reyes')['id']], array_keys($problems));
        self::assertSame([], PaperScores::problems($paper, $rows, [$bea => '10', $nicole => '0', (string) $this->person('carlo.reyes')['id'] => '']));
    }

    public function testOnlineWorkKeepsItsOnlineGradingInTheGrid(): void
    {
        $quiz = Db::one("SELECT * FROM assignments WHERE title = 'Respiratory and circulatory systems check' AND class_id = :c", ['c' => $this->classId(self::NARRA)]);
        $ramon = $this->teacher();
        $rows = PaperScores::grid($quiz);
        $byUser = array_column($rows, null, 'username');
        self::assertFalse($byUser['andrea.cruz']['editable'], 'handed in online');
        self::assertSame('online', $byUser['andrea.cruz']['source']);
        $offline = array_values(array_filter($rows, static fn ($r) => $r['submission_id'] === null));
        self::assertNotEmpty($offline);
        self::assertTrue($offline[0]['editable'], 'did it on a printed copy instead');

        $before = Db::one("SELECT score FROM submissions WHERE id = :s", ['s' => (int) $byUser['andrea.cruz']['submission_id']]);
        // Keep everyone's current absent and excused marks; only the typed score is new.
        $absent = array_column(array_filter($rows, static fn ($r) => $r['absent']), 'student_id');
        $excused = array_column(array_filter($rows, static fn ($r) => $r['excused']), 'student_id');
        PaperScores::save($ramon, $quiz, [(int) $byUser['andrea.cruz']['student_id'] => '0'], $absent, $excused, false);
        self::assertSame($before, Db::one("SELECT score FROM submissions WHERE id = :s", ['s' => (int) $byUser['andrea.cruz']['submission_id']]), 'an online submission is never overwritten');
    }

    public function testOnlyTeachersOfTheClassReachTheGridOrThePrintedKey(): void
    {
        [, $paper] = $this->paperWork('-1 days', 10);
        try {
            Access::teacherAssignment($this->person('liza.mercado'), (int) $paper['id']);
            self::fail('Expected a 404');
        } catch (HttpException $e) {
            self::assertSame(404, $e->status);
        }
    }

    public function testTheBlankCopyHasNoAnswersAndNoUnapprovedDrafts(): void
    {
        [$teacher, $paper] = $this->paperWork('+3 days', 0);
        [$approved] = Assessments::normaliseQuestion(['qtype' => 'multiple_choice', 'prompt' => 'Which organ pumps blood?', 'options' => ['Heart', 'Lung'], 'correct' => 'A', 'points' => 2, 'explanation' => 'The heart is a muscle.']);
        [$drafted] = Assessments::normaliseQuestion(['qtype' => 'short_answer', 'prompt' => 'Name the windpipe.', 'accepted' => 'trachea', 'points' => 1]);
        Assessments::addQuestion((int) $paper['id'], $approved);
        Assessments::addQuestion((int) $paper['id'], $drafted, true);

        $blank = Assessments::printable((int) $paper['id'], false);
        self::assertSame(1, $blank['left_out'], 'the AI draft waits for review');
        self::assertCount(1, $blank['questions']);
        self::assertArrayNotHasKey('answer_key', $blank['questions'][0]);
        self::assertArrayNotHasKey('explanation', $blank['questions'][0]);

        $key = Assessments::printable((int) $paper['id'], true);
        self::assertCount(2, $key['questions']);
        self::assertSame('A', $key['questions'][0]['answer_key']['correct']);
        self::assertSame(3.0, (float) Db::value('SELECT points_possible FROM assignments WHERE id = :a', ['a' => (int) $paper['id']]), 'questions set the points');
        unset($teacher);
    }

    // ------------------------------------------------------------------ helpers

    /** A published paper assignment in Science 9 – Narra, done in class at $when. @return array{0:array,1:array} */
    private function paperWork(string $when, float $points): array
    {
        $teacher = $this->teacher();
        $id = Db::insert('assignments', [
            'organization_id' => (int) $teacher['organization_id'],
            'class_id' => $this->classId(self::NARRA),
            'kind' => 'paper',
            'title' => 'Paper test ' . bin2hex(random_bytes(3)),
            'status' => 'draft',
            'due_at' => gmdate('Y-m-d H:i:s', (int) strtotime($when)),
            'points_possible' => $points,
            'allow_text' => 0,
            'feedback_release' => 'manual',
            'answer_key_release' => 'never',
            'created_by' => (int) $teacher['id'],
            'created_at' => Db::now(),
            'updated_at' => Db::now(),
        ]);
        $this->created[] = $id;
        Assessments::publish($id, (int) $teacher['id'], false);
        return [$teacher, Access::teacherAssignment($teacher, $id)];
    }

    private function teacher(): array
    {
        $ramon = $this->person('ramon.dizon');
        Auth::actingAs($ramon);
        return $ramon;
    }

    private function assertBucket(array $student, int $assignmentId, string $bucket): void
    {
        $rows = array_column(StudentRepo::assignments($student, 'all'), null, 'id');
        self::assertSame($bucket, $rows[$assignmentId]['bucket'] ?? null);
    }
}
