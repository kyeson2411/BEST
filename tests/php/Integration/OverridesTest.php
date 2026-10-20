<?php

declare(strict_types=1);

namespace Sibol\Tests\Integration;

use Sibol\Core\Auth;
use Sibol\Core\Db;
use Sibol\Core\Uploads;
use Sibol\Repositories\StudentRepo;
use Sibol\Services\Assessments;
use Sibol\Services\Grades;
use Sibol\Services\Navigation;
use Sibol\Services\Notifier;
use Sibol\Services\Overrides;
use Sibol\Services\Privacy;

/**
 * Extensions, extra time and excused work: one student's own dates drive everything that decides
 * their deadline, excused work is never counted or missing, and answers released "after the due date"
 * wait for the latest extension.
 */
final class OverridesTest extends IntegrationTestCase
{
    private const NARRA = 'Science 9 – Narra';
    private const PAST_QUIZ = 'Respiratory and circulatory systems check';

    protected function tearDown(): void
    {
        Db::exec('DELETE FROM assignment_overrides');
        Db::exec("DELETE FROM notifications WHERE dedupe_key LIKE 'override:%'");
        parent::tearDown();
    }

    public function testAnExtensionGivesTheStudentTheirOwnDueDateEverywhere(): void
    {
        $ramon = $this->teacher();
        $carlo = $this->person('carlo.reyes');
        $quiz = $this->assignment(self::PAST_QUIZ, self::NARRA);
        $classDue = (string) $quiz['due_at'];

        $before = Assessments::forStudent((int) $carlo['id'], (int) $quiz['id']);
        self::assertFalse(Assessments::studentState($before, (int) $carlo['id'])['can_start'], 'Late work is not accepted, so Carlo is locked out');
        self::assertSame('Missing', Assessments::studentState($before, (int) $carlo['id'])['status_label'][0]);
        self::assertSame('overdue', $this->bucket($carlo, (int) $quiz['id']));
        self::assertSame('missing', $this->cell($carlo, (int) $quiz['id'])['state']);
        $dueCount = $this->navCount($carlo);

        $ownDue = gmdate('Y-m-d H:i:s', time() + 3 * 86400);
        Overrides::save($ramon, $quiz, (int) $carlo['id'], $ownDue, 0, false);

        $after = Assessments::forStudent((int) $carlo['id'], (int) $quiz['id']);
        self::assertSame($ownDue, $after['due_at']);
        self::assertSame($classDue, $after['class_due_at']);
        self::assertTrue($after['extended']);
        $state = Assessments::studentState($after, (int) $carlo['id']);
        self::assertTrue($state['can_start'], 'His own date has not passed, so he can take it');
        self::assertSame('Not started', $state['status_label'][0]);
        self::assertSame('upcoming', $this->bucket($carlo, (int) $quiz['id']));
        self::assertContains((int) $quiz['id'], array_map('intval', array_column(StudentRepo::dueSoon($carlo), 'id')), 'The dashboard lists it by his own date');
        self::assertSame($dueCount + 1, $this->navCount($carlo));
        self::assertSame('open', $this->cell($carlo, (int) $quiz['id'])['state'], 'Not missing in the gradebook while his own date runs');
        self::assertSame('missing', $this->cell($this->person('paolo.aquino'), (int) $quiz['id'])['state'], 'Classmates keep the class date');
        self::assertSame(1, (int) Db::value("SELECT COUNT(*) FROM notifications WHERE user_id = :u AND title LIKE 'New due date for%'", ['u' => (int) $carlo['id']]));
    }

    public function testRemindersFollowTheStudentsOwnDate(): void
    {
        $ramon = $this->teacher();
        $carlo = $this->person('carlo.reyes');
        $quiz = $this->assignment(self::PAST_QUIZ, self::NARRA);
        $soon = gmdate('Y-m-d H:i:s', time() + 5 * 3600);
        Overrides::save($ramon, $quiz, (int) $carlo['id'], $soon, 0, false);
        Notifier::remindDueSoon($carlo);
        $key = 'due:' . (int) $quiz['id'] . ':' . $soon;
        self::assertSame(1, (int) Db::value('SELECT COUNT(*) FROM notifications WHERE user_id = :u AND dedupe_key = :k', ['u' => (int) $carlo['id'], 'k' => $key]));
        Db::exec('DELETE FROM notifications WHERE dedupe_key = :k', ['k' => $key]);
    }

    public function testExcusedWorkIsNeitherCountedNorMissing(): void
    {
        $ramon = $this->teacher();
        $paolo = $this->person('paolo.aquino');
        $quiz = $this->assignment(self::PAST_QUIZ, self::NARRA);
        $missingBefore = $this->overall($paolo)['missing'];
        $expected = static fn (): int => (int) Db::value('SELECT ' . Overrides::expectedSql('a') . ' FROM assignments a WHERE a.id = :a', ['a' => (int) $quiz['id']]);
        $expectedBefore = $expected();

        Overrides::save($ramon, $quiz, (int) $paolo['id'], null, 0, true);

        self::assertSame('excused', $this->cell($paolo, (int) $quiz['id'])['state']);
        self::assertSame($missingBefore - 1, $this->overall($paolo)['missing']);
        self::assertSame('excused', $this->bucket($paolo, (int) $quiz['id']));
        $view = Assessments::forStudent((int) $paolo['id'], (int) $quiz['id']);
        self::assertSame('Excused', Assessments::studentState($view, (int) $paolo['id'])['status_label'][0]);
        self::assertSame($expectedBefore - 1, $expected(), 'Teachers no longer expect it from him');
    }

    public function testExtraMinutesLengthenTheTimerIncludingAnAttemptUnderWay(): void
    {
        $ramon = $this->teacher();
        $andrea = $this->person('andrea.cruz');
        $quiz = $this->assignment('Heredity basics', self::NARRA);
        Auth::actingAs($andrea);
        $sub = Assessments::startAttempt(Assessments::forStudent((int) $andrea['id'], (int) $quiz['id']), (int) $andrea['id']);
        try {
            $started = (int) strtotime($sub['started_at'] . ' UTC');
            self::assertSame($started + 20 * 60, (int) strtotime($sub['deadline_at'] . ' UTC'));

            Overrides::save($ramon, $quiz, (int) $andrea['id'], null, 15, false);
            $deadline = (string) Db::value('SELECT deadline_at FROM submissions WHERE id = :s', ['s' => (int) $sub['id']]);
            self::assertSame($started + 35 * 60, (int) strtotime($deadline . ' UTC'), 'The attempt under way gets the extra minutes');
            self::assertSame(15, Assessments::forStudent((int) $andrea['id'], (int) $quiz['id'])['extra_minutes']);
            self::assertSame($started + 35 * 60, Assessments::attemptDeadline(Assessments::forStudent((int) $andrea['id'], (int) $quiz['id']), $started));
        } finally {
            Db::delete('submissions', ['id' => (int) $sub['id']]);
        }
    }

    public function testExtraMinutesApplyOnlyToTimedQuizzes(): void
    {
        $lab = $this->assignment('Lab report: breathing rate before and after exercise', self::NARRA);
        Overrides::save($this->teacher(), $lab, (int) $this->person('joshua.garcia')['id'], null, 30, false);
        self::assertNull(Overrides::find((int) $lab['id'], (int) $this->person('joshua.garcia')['id']), 'Nothing differs from the class, so nothing is stored');
    }

    public function testAnswersAfterTheDueDateWaitForTheLatestExtension(): void
    {
        $ramon = $this->teacher();
        $andrea = $this->person('andrea.cruz');
        $quiz = $this->assignment(self::PAST_QUIZ, self::NARRA);
        $view = Assessments::forStudent((int) $andrea['id'], (int) $quiz['id']);
        $submitted = Db::one("SELECT * FROM submissions WHERE assignment_id = :a AND student_id = :u AND status = 'submitted' LIMIT 1", ['a' => (int) $quiz['id'], 'u' => (int) $andrea['id']]);
        self::assertTrue(Assessments::answersReleased($view, $submitted), 'Past the class due date, answers show');

        Overrides::save($ramon, $quiz, (int) $this->person('carlo.reyes')['id'], gmdate('Y-m-d H:i:s', time() + 86400), 0, false);
        self::assertFalse(Assessments::answersReleased($view, $submitted), 'Carlo has not taken it yet, so answers wait for his date');
        self::assertFalse(Assessments::answersReleased($quiz, $submitted), 'Also when checked against the class row');

        Overrides::remove($ramon, $quiz, (int) $this->person('carlo.reyes')['id']);
        self::assertTrue(Assessments::answersReleased($view, $submitted));
    }

    public function testLateRulesUseTheStudentsOwnDate(): void
    {
        $joy = $this->person('joy.santiago');
        Auth::actingAs($joy);
        $paolo = $this->person('paolo.aquino');
        $response = $this->assignment('Reading response: The jeepney stop', 'English 9 – Narra');
        Overrides::save($joy, $response, (int) $paolo['id'], gmdate('Y-m-d H:i:s', time() + 2 * 86400), 0, false);
        $view = Assessments::forStudent((int) $paolo['id'], (int) $response['id']);
        $id = Db::insert('submissions', [
            'organization_id' => (int) $paolo['organization_id'], 'assignment_id' => (int) $response['id'], 'student_id' => (int) $paolo['id'],
            'assignment_version_id' => (int) $response['current_version_id'], 'attempt_no' => 1, 'status' => 'in_progress',
            'body' => 'Mara is in a hurry: she checks her watch and taps her foot.', 'points_possible' => (float) $response['points_possible'],
            'started_at' => Db::now(), 'created_at' => Db::now(), 'updated_at' => Db::now(),
        ]);
        try {
            $sub = Assessments::submitAssignment($view, Db::one('SELECT * FROM submissions WHERE id = :s', ['s' => $id]));
            self::assertSame(0, (int) $sub['is_late'], 'Handed in before his own date, so it is on time');
        } finally {
            Db::delete('submissions', ['id' => $id]);
        }
    }

    public function testAClosedAssignmentStaysOpenOnlyForAStudentWithARunningExtension(): void
    {
        $ramon = $this->teacher();
        $lab = $this->assignment('Lab report: breathing rate before and after exercise', self::NARRA);
        $andrea = $this->person('andrea.cruz');
        $joshua = $this->person('joshua.garcia');
        Db::update('assignments', ['status' => 'closed'], ['id' => (int) $lab['id']]);
        try {
            $state = static fn (array $student): array => Assessments::studentState(Assessments::forStudent((int) $student['id'], (int) $lab['id']), (int) $student['id']);
            self::assertFalse($state($andrea)['can_submit']);
            Overrides::save($ramon, $lab, (int) $andrea['id'], gmdate('Y-m-d H:i:s', time() + 4 * 86400), 0, false);
            self::assertTrue($state($andrea)['can_submit'], 'Her own extension keeps it open for her');
            self::assertFalse($state($joshua)['can_submit'], 'Closed for everyone else');
        } finally {
            Db::update('assignments', ['status' => 'published'], ['id' => (int) $lab['id']]);
        }
    }

    public function testSavingTheClassSettingsRemovesTheOverride(): void
    {
        $ramon = $this->teacher();
        $quiz = $this->assignment(self::PAST_QUIZ, self::NARRA);
        $carlo = (int) $this->person('carlo.reyes')['id'];
        Overrides::save($ramon, $quiz, $carlo, null, 0, true);
        self::assertNotNull(Overrides::find((int) $quiz['id'], $carlo));
        Overrides::save($ramon, $quiz, $carlo, (string) $quiz['due_at'], 0, false);
        self::assertNull(Overrides::find((int) $quiz['id'], $carlo), 'The class date and nothing else: back to the class settings');
    }

    public function testChangesAreAuditedWithoutReasonsAndIncludedInTheStudentsExport(): void
    {
        $ramon = $this->teacher();
        $quiz = $this->assignment(self::PAST_QUIZ, self::NARRA);
        $carlo = $this->person('carlo.reyes');
        Overrides::save($ramon, $quiz, (int) $carlo['id'], gmdate('Y-m-d H:i:s', time() + 86400), 0, false);
        $log = Db::one("SELECT * FROM audit_logs WHERE action = 'assignment.override_saved' AND target_id = :a ORDER BY id DESC LIMIT 1", ['a' => (int) $quiz['id']]);
        self::assertSame(['student_id' => (int) $carlo['id'], 'own_due_date' => true, 'extra_minutes' => 0, 'excused' => false, 'absent' => false], json_decode((string) $log['metadata'], true));

        $path = Privacy::export((int) $carlo['id']);
        try {
            $data = json_decode((string) file_get_contents(Uploads::absolute($path)), true);
            self::assertSame(self::PAST_QUIZ, $data['due_date_changes'][0]['assignment']);
        } finally {
            Uploads::delete($path);
        }
    }

    // ------------------------------------------------------------------ helpers

    private function teacher(): array
    {
        $ramon = $this->person('ramon.dizon');
        Auth::actingAs($ramon);
        return $ramon;
    }

    private function assignment(string $title, string $className): array
    {
        $row = Db::one('SELECT a.* FROM assignments a JOIN classes c ON c.id = a.class_id WHERE a.title = :t AND c.name = :c', ['t' => $title, 'c' => $className]);
        self::assertNotNull($row, 'Missing demo assignment ' . $title);
        return $row;
    }

    private function bucket(array $student, int $assignmentId): string
    {
        foreach (StudentRepo::assignments($student, 'all') as $row) {
            if ((int) $row['id'] === $assignmentId) {
                return (string) $row['bucket'];
            }
        }
        self::fail('Assignment not listed for the student');
    }

    private function cell(array $student, int $assignmentId): array
    {
        $classId = (int) Db::value('SELECT class_id FROM assignments WHERE id = :a', ['a' => $assignmentId]);
        return Grades::classBook($classId, (int) $student['organization_id'])['cells'][(int) $student['id']][$assignmentId];
    }

    private function overall(array $student): array
    {
        return Grades::classBook($this->classId(self::NARRA), (int) $student['organization_id'])['overall'][(int) $student['id']];
    }

    private function navCount(array $student): int
    {
        foreach (Navigation::items($student) as $item) {
            if ($item['url'] === '/student/assignments') {
                return (int) ($item['count'] ?? 0);
            }
        }
        return 0;
    }
}
