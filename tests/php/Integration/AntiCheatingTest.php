<?php

declare(strict_types=1);

namespace Sibol\Tests\Integration;

use Sibol\Controllers\Student\AssignmentController as StudentWork;
use Sibol\Controllers\Teacher\AssignmentController as TeacherWork;
use Sibol\Controllers\Teacher\SubmissionController;
use Sibol\Core\Auth;
use Sibol\Core\Db;
use Sibol\Core\HttpException;
use Sibol\Core\Request;
use Sibol\Services\Assessments;
use Sibol\Services\Privacy;

/**
 * Anti-cheating, on unless the teacher switches it off: copy and paste are blocked in answers (anticheat.js), and
 * during a quiz each time the student leaves the page is counted on the attempt with the time away, for the
 * teacher and the student to see. Only the student's own attempt in progress can be counted.
 */
final class AntiCheatingTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    private function quizId(): int
    {
        return (int) Db::value("SELECT a.id FROM assignments a JOIN classes c ON c.id = a.class_id WHERE a.title = 'Heredity basics' AND c.name = 'Science 9 – Narra'");
    }

    /** Gabriel's attempt at Heredity basics, started for the test. @return array<string,mixed> */
    private function startAttempt(): array
    {
        $gabriel = $this->person('gabriel.torres');
        Auth::actingAs($gabriel);
        return Assessments::startAttempt(Assessments::forStudent((int) $gabriel['id'], $this->quizId()), (int) $gabriel['id']);
    }

    private function event(string $event, ?int $seconds = null): \Sibol\Core\Response
    {
        $post = ['event' => $event] + ($seconds !== null ? ['seconds' => (string) $seconds] : []);
        $id = $this->quizId();
        return (new StudentWork(new Request('POST', '/student/assignments/' . $id . '/attempt/activity', [], $post)))->activity($id);
    }

    public function testNewWorkHasItOnAndATeacherCanSwitchItOff(): void
    {
        Auth::actingAs($this->person('ramon.dizon'));
        $ids = [];
        try {
            foreach (['quiz' => '1', 'assignment' => null] as $kind => $ticked) {
                $post = ['kind' => $kind, 'class_id' => (string) $this->classId('Science 9 – Narra'), 'title' => 'Anti-cheating ' . $kind,
                    'due_at' => date('Y-m-d\TH:i', strtotime('+10 days')), 'late_policy' => 'accepted', 'feedback_release' => 'manual',
                    'answer_key_release' => 'after_due', 'max_attempts' => '1', 'allow_text' => '1'] + ($ticked !== null ? ['anti_cheating' => $ticked] : []);
                $response = (new TeacherWork(new Request('POST', '/teacher/assignments', [], $post)))->store();
                self::assertSame(1, preg_match('#/teacher/assignments/(\d+)/edit$#', $response->headers['Location'], $m), $response->headers['Location']);
                $ids[$kind] = (int) $m[1];
            }
            self::assertSame(1, (int) Db::value('SELECT anti_cheating FROM assignments WHERE id = :id', ['id' => $ids['quiz']]), 'Ticked');
            self::assertSame(0, (int) Db::value('SELECT anti_cheating FROM assignments WHERE id = :id', ['id' => $ids['assignment']]), 'Left unticked');

            $form = (new TeacherWork(new Request('GET', '/teacher/assignments/new', ['kind' => 'quiz'])))->create()->body;
            self::assertMatchesRegularExpression('/name="anti_cheating"[^>]*checked/', $form, 'On by default for new work');
        } finally {
            foreach ($ids as $id) {
                Db::delete('assignments', ['id' => $id]);
            }
        }
    }

    public function testLeavingTheQuizAndBlockedPastesAreCountedOnTheAttempt(): void
    {
        $sub = $this->startAttempt();
        try {
            self::assertSame(['left' => 1], json_decode($this->event('left')->body, true));
            $this->event('back', 45);
            $this->event('left');
            $this->event('back', 999999);
            $this->event('paste');
            self::assertSame(422, $this->event('nonsense')->status);

            $row = Db::one('SELECT left_page_count, left_page_seconds, paste_blocked_count FROM submissions WHERE id = :id', ['id' => (int) $sub['id']]);
            self::assertSame(2, (int) $row['left_page_count']);
            self::assertSame(45 + 86400, (int) $row['left_page_seconds'], 'One absence counts at most a day');
            self::assertSame(1, (int) $row['paste_blocked_count']);

            $page = (new StudentWork(new Request('GET', '/student/assignments/' . $this->quizId() . '/attempt')))->attempt($this->quizId())->body;
            self::assertStringContainsString('data-anticheat-url=', $page);
            self::assertStringContainsString('data-anticheat-left="2"', $page);
            self::assertStringContainsString('anticheat.js', $page);
            self::assertStringContainsString('Anti-cheating is on', $page);
        } finally {
            Db::delete('submissions', ['id' => (int) $sub['id']]);
        }
    }

    public function testNothingIsCountedOnceTheAttemptIsHandedIn(): void
    {
        $sub = $this->startAttempt();
        try {
            Db::update('submissions', ['status' => 'submitted', 'submitted_at' => Db::now()], ['id' => (int) $sub['id']]);
            self::assertSame(409, $this->event('left')->status);
            self::assertSame(0, (int) Db::value('SELECT left_page_count FROM submissions WHERE id = :id', ['id' => (int) $sub['id']]));
        } finally {
            Db::delete('submissions', ['id' => (int) $sub['id']]);
        }
    }

    public function testWhenSwitchedOffTheQuizHasNoGuardAndNothingCanBeCounted(): void
    {
        Db::update('assignments', ['anti_cheating' => 0], ['id' => $this->quizId()]);
        $sub = $this->startAttempt();
        try {
            $page = (new StudentWork(new Request('GET', '/student/assignments/' . $this->quizId() . '/attempt')))->attempt($this->quizId())->body;
            self::assertStringNotContainsString('data-anticheat', $page);
            self::assertStringNotContainsString('anticheat.js', $page);
            try {
                $this->event('left');
                self::fail('Counting is refused when anti-cheating is off');
            } catch (HttpException $e) {
                self::assertSame(404, $e->status);
            }
        } finally {
            Db::delete('submissions', ['id' => (int) $sub['id']]);
            Db::update('assignments', ['anti_cheating' => 1], ['id' => $this->quizId()]);
        }
    }

    public function testAnAttemptKeepsTheSettingItStartedWith(): void
    {
        $sub = $this->startAttempt();
        self::assertSame(1, (int) $sub['anti_cheating']);
        Db::update('assignments', ['anti_cheating' => 0], ['id' => $this->quizId()]);
        try {
            $this->event('left');
            self::assertSame(1, (int) Db::value('SELECT left_page_count FROM submissions WHERE id = :id', ['id' => (int) $sub['id']]), 'Switching it off later does not stop an attempt under way');
        } finally {
            Db::delete('submissions', ['id' => (int) $sub['id']]);
            Db::update('assignments', ['anti_cheating' => 1], ['id' => $this->quizId()]);
        }
    }

    public function testAttemptsFromBeforeAntiCheatingSayNothingAboutIt(): void
    {
        Auth::actingAs($this->person('ramon.dizon'));
        $old = Db::one(
            "SELECT s.id, s.anti_cheating FROM submissions s JOIN assignments a ON a.id = s.assignment_id
             WHERE a.kind = 'quiz' AND s.status = 'submitted' AND a.title = 'Respiratory and circulatory systems check' LIMIT 1"
        );
        self::assertNotNull($old);
        // As every attempt saved before anti-cheating existed: no setting recorded on it.
        Db::update('submissions', ['anti_cheating' => null], ['id' => (int) $old['id']]);
        try {
            $page = (new SubmissionController(new Request('GET', '/teacher/submissions/' . (int) $old['id'])))->show((int) $old['id'])->body;
            self::assertStringNotContainsString('Nothing was recorded', $page);
            self::assertStringNotContainsString('callout__title">Anti-cheating', $page);
        } finally {
            Db::update('submissions', ['anti_cheating' => $old['anti_cheating']], ['id' => (int) $old['id']]);
        }
    }

    public function testAnAssignmentGuardsItsTypedResponse(): void
    {
        Auth::actingAs($this->person('andrea.cruz'));
        $id = (int) Db::value("SELECT id FROM assignments WHERE title = 'Inference journal: three clues'");
        $page = (new StudentWork(new Request('GET', '/student/assignments/' . $id)))->show($id)->body;
        self::assertStringContainsString('data-anticheat-target="#f-body"', $page);
        self::assertStringContainsString('anticheat.js', $page);
        self::assertStringNotContainsString('data-anticheat-url', $page, 'Leaving an assignment page is not counted');
    }

    public function testTheTeacherAndTheStudentSeeWhatWasRecorded(): void
    {
        $sub = $this->startAttempt();
        $gabriel = $this->person('gabriel.torres');
        try {
            $this->event('left');
            $this->event('back', 80);
            $this->event('left');
            $this->event('back', 10);
            $this->event('paste');
            $submitted = Assessments::submitAttempt(Assessments::forStudent((int) $gabriel['id'], $this->quizId()), Db::one('SELECT * FROM submissions WHERE id = :id', ['id' => (int) $sub['id']]), Assessments::snapshotFor((int) $sub['assignment_version_id']) ?? []);

            $receipt = (new StudentWork(new Request('GET', '/student/submissions/' . (int) $submitted['id'])))->receipt((int) $submitted['id'])->body;
            self::assertStringContainsString('Left the quiz page 2 times, about 2 minutes away in total.', $receipt);
            self::assertStringContainsString('Tried to paste 1 time. It was blocked.', $receipt);
            $export = json_decode((string) file_get_contents(\Sibol\Core\Uploads::absolute(Privacy::export((int) $gabriel['id']))), true);
            $mine = array_values(array_filter($export['submissions'], static fn ($s) => $s['receipt_code'] === $submitted['receipt_code']));
            self::assertSame(2, (int) $mine[0]['left_page_count'], 'In the student\'s own data export');

            Auth::actingAs($this->person('ramon.dizon'));
            $list = (new SubmissionController(new Request('GET', '/teacher/assignments/' . $this->quizId() . '/submissions')))->index($this->quizId())->body;
            self::assertStringContainsString('Left the quiz 2 times', $list);
            $page = (new SubmissionController(new Request('GET', '/teacher/submissions/' . (int) $submitted['id'])))->show((int) $submitted['id'])->body;
            self::assertStringContainsString('Left the quiz page 2 times, about 2 minutes away in total.', $page);
            self::assertStringContainsString('Talk with the student before deciding anything.', $page);
        } finally {
            Db::exec('DELETE FROM submission_answers WHERE submission_id = :s', ['s' => (int) $sub['id']]);
            Db::delete('submissions', ['id' => (int) $sub['id']]);
        }
    }

    public function testRecordsReadInPlainWords(): void
    {
        self::assertSame([], anticheat_record(['left_page_count' => 0, 'left_page_seconds' => 0, 'paste_blocked_count' => 0]));
        self::assertSame(['Left the quiz page 1 time, about 40 seconds away in total.'], anticheat_record(['left_page_count' => 1, 'left_page_seconds' => 40, 'paste_blocked_count' => 0]));
        self::assertSame(['Tried to paste 3 times. Each was blocked.'], anticheat_record(['left_page_count' => 0, 'left_page_seconds' => 0, 'paste_blocked_count' => 3]));
    }
}
