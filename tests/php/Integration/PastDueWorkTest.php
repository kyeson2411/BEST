<?php

declare(strict_types=1);

namespace Sibol\Tests\Integration;

use Sibol\Controllers\Teacher\AssignmentController;
use Sibol\Core\Auth;
use Sibol\Core\Db;
use Sibol\Core\Request;
use Sibol\Core\Session;
use Sibol\Services\Assessments;

/**
 * Online work (an assignment or a quiz) is not published with a due date that has already passed: students would get
 * it already missing, or late for a deadline they never had. Work done in class or on paper keeps past dates, since
 * the teacher enters its scores afterwards.
 */
final class PastDueWorkTest extends IntegrationTestCase
{
    private const PAST_DUE = 'The due date has already passed. Choose a new due date so students can hand it in on time.';

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

    /** A draft like Ramon's "Concept map" in Science 9 – Narra, of this kind and due this many days from now. */
    private function draft(string $kind, int $dueInDays): int
    {
        $source = (int) Db::value("SELECT a.id FROM assignments a JOIN classes c ON c.id = a.class_id WHERE a.title = 'Concept map: from air to cell' AND c.name LIKE '%Narra'");
        Db::exec(
            "INSERT INTO assignments (organization_id, class_id, module_id, kind, title, instructions, objectives, category, status, opens_at, due_at,
                late_policy, late_penalty_pct, max_attempts, time_limit_minutes, points_possible, allow_text, allow_files, allow_resubmission, rubric,
                feedback_release, answer_key_release, created_by, created_at, updated_at)
             SELECT organization_id, class_id, module_id, :k, 'Due date test', instructions, objectives, category, 'draft', NULL, :d,
                late_policy, late_penalty_pct, max_attempts, time_limit_minutes, points_possible, allow_text, allow_files, allow_resubmission, rubric,
                feedback_release, answer_key_release, created_by, created_at, updated_at
             FROM assignments WHERE id = :s",
            ['k' => $kind, 'd' => gmdate('Y-m-d H:i:s', time() + $dueInDays * 86400), 's' => $source]
        );
        return (int) Db::value("SELECT id FROM assignments WHERE title = 'Due date test'");
    }

    private function problems(int $id): array
    {
        return Assessments::publishProblems((array) Db::one('SELECT * FROM assignments WHERE id = :id', ['id' => $id]));
    }

    private function remove(int $id): void
    {
        Db::exec('DELETE FROM notifications WHERE link = :l', ['l' => '/student/assignments/' . $id]);
        Db::exec('DELETE FROM assignments WHERE id = :id', ['id' => $id]);
    }

    public function testOnlineWorkPastItsDueDateCannotBePublished(): void
    {
        Auth::actingAs($this->person('ramon.dizon'));
        $id = $this->draft('assignment', -1);
        try {
            self::assertSame([self::PAST_DUE], $this->problems($id));
            (new AssignmentController(new Request('POST', '/teacher/assignments/' . $id . '/publish')))->publish($id);
            $flashes = array_column(Session::takeFlashes(), 'message', 'type');
            self::assertSame('Not published yet: ' . self::PAST_DUE, $flashes['error'] ?? '');
            self::assertSame('draft', Db::value('SELECT status FROM assignments WHERE id = :id', ['id' => $id]));
            self::assertSame(0, (int) Db::value('SELECT COUNT(*) FROM notifications WHERE link = :l', ['l' => '/student/assignments/' . $id]), 'Nobody was told');

            // A new date in the future, and it publishes.
            Db::update('assignments', ['due_at' => gmdate('Y-m-d H:i:s', time() + 3 * 86400)], ['id' => $id]);
            self::assertSame([], $this->problems($id));
            (new AssignmentController(new Request('POST', '/teacher/assignments/' . $id . '/publish')))->publish($id);
            self::assertSame('published', Db::value('SELECT status FROM assignments WHERE id = :id', ['id' => $id]));
        } finally {
            $this->remove($id);
        }
    }

    public function testAQuizPastItsDueDateIsRefusedToo(): void
    {
        $id = $this->draft('quiz', -2);
        try {
            self::assertContains(self::PAST_DUE, $this->problems($id));
        } finally {
            $this->remove($id);
        }
    }

    public function testWorkDoneInClassOrOnPaperKeepsAPastDate(): void
    {
        $id = $this->draft('paper', -1);
        try {
            self::assertNotContains(self::PAST_DUE, $this->problems($id));
        } finally {
            $this->remove($id);
        }
    }
}
