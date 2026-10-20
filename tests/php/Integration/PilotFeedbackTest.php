<?php

declare(strict_types=1);

namespace Sibol\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use Sibol\Core\Db;
use Sibol\Core\HttpException;
use Sibol\Core\Uploads;
use Sibol\Policies\Access;
use Sibol\Services\AuditLabels;
use Sibol\Services\PilotReport;
use Sibol\Services\Privacy;
use Sibol\Services\SiteFeedback;

/** Item 22: feedback about Sibol with a status and one reply, and the weekly pilot report (counts only). */
final class PilotFeedbackTest extends IntegrationTestCase
{
    public function testOnlyThePathOfThePageIsKept(): void
    {
        self::assertSame('/student/assignments/12', SiteFeedback::cleanPath('/student/assignments/12?tab=answers#q3'));
        self::assertSame('/companion/5', SiteFeedback::cleanPath('/companion/5'));
        self::assertSame('/student', SiteFeedback::cleanPath('/student/'));
        foreach ([null, '', 'student', '//evil.example/x', 'https://evil.example/x', '/reset/abc123', '/invite/xyz', '/start', '/feedback', '/student/<b>', '/a/../admin', '/' . str_repeat('a', 220)] as $bad) {
            self::assertNull(SiteFeedback::cleanPath($bad), var_export($bad, true));
        }
    }

    public function testFeedbackIsStoredWithItsPageAndAuditedWithoutItsText(): void
    {
        $andrea = $this->person('andrea.cruz');
        $id = SiteFeedback::send($andrea, 'problem', "  The quiz timer froze.\nI reloaded twice.  ", '/student/assignments/9?attempt=2');
        $row = Db::one('SELECT * FROM site_feedback WHERE id = :id', ['id' => $id]);
        self::assertSame(
            ['problem', "The quiz timer froze.\nI reloaded twice.", '/student/assignments/9', 'new', 'student'],
            [$row['category'], $row['body'], $row['page_path'], $row['status'], $row['user_role']]
        );
        $log = Db::one("SELECT * FROM audit_logs WHERE action = 'feedback.sent' AND target_id = :id", ['id' => $id]);
        self::assertSame(['category' => 'problem'], json_decode((string) $log['metadata'], true));
        self::assertSame('Sent feedback about Sibol', AuditLabels::describe($log));
        self::assertSame($id, (int) SiteFeedback::mine((int) $andrea['id'])[0]['id']);

        $this->expectException(\InvalidArgumentException::class);
        SiteFeedback::send($andrea, 'rant', 'Nope.', null);
    }

    public function testAReplyReachesTheSenderOnceAsANotification(): void
    {
        $admin = $this->person('teresa.villanueva');
        $ramon = $this->person('ramon.dizon');
        $id = SiteFeedback::send($ramon, 'idea', 'Let me print the gradebook.', '/teacher/gradebook');
        $notes = static fn (): int => (int) Db::value("SELECT COUNT(*) FROM notifications WHERE user_id = :u AND type = 'site_feedback'", ['u' => (int) $ramon['id']]);
        $before = $notes();

        self::assertFalse(SiteFeedback::update($admin, Access::schoolFeedback($admin, $id), 'looking', ''), 'a status alone sends nothing');
        self::assertTrue(SiteFeedback::update($admin, Access::schoolFeedback($admin, $id), 'fixed', 'Done: use Export on the gradebook.'));
        self::assertFalse(SiteFeedback::update($admin, Access::schoolFeedback($admin, $id), 'fixed', 'Done: use Export on the gradebook.'), 'the same reply is not sent twice');
        self::assertSame($before + 1, $notes());

        $note = Db::one("SELECT * FROM notifications WHERE user_id = :u AND type = 'site_feedback' ORDER BY id DESC LIMIT 1", ['u' => (int) $ramon['id']]);
        self::assertSame(['Reply to your feedback', 'Done: use Export on the gradebook.', '/feedback#feedback-' . $id], [$note['title'], $note['body'], $note['link']]);
        $item = Access::schoolFeedback($admin, $id);
        self::assertSame(['fixed', (int) $admin['id']], [$item['status'], (int) $item['replied_by']]);

        $logs = Db::all("SELECT action, metadata FROM audit_logs WHERE action = 'feedback.updated' AND target_id = :id ORDER BY id", ['id' => $id]);
        self::assertSame(
            [['status' => 'looking', 'replied' => false], ['status' => 'fixed', 'replied' => true], ['status' => 'fixed', 'replied' => false]],
            array_map(static fn ($l) => json_decode((string) $l['metadata'], true), $logs)
        );
        self::assertSame('Replied to feedback about Sibol', AuditLabels::describe($logs[1]));
        self::assertStringNotContainsString('Export', implode(' ', array_column($logs, 'metadata')), 'the reply text is not logged');

        $this->expectException(\InvalidArgumentException::class);
        SiteFeedback::update($admin, $item, 'closed', null);
    }

    public function testFeedbackStaysInsideItsSchool(): void
    {
        $id = SiteFeedback::send($this->person('bea.ramos'), 'idea', 'Dark mode, please.', null);
        $elsewhere = ['id' => 999999, 'organization_id' => 999999, 'role' => 'admin'];
        try {
            Access::schoolFeedback($elsewhere, $id);
            self::fail('Expected a 404');
        } catch (HttpException $e) {
            self::assertSame(404, $e->status);
        }
        self::assertNull(Access::schoolFeedback($this->person('teresa.villanueva'), $id)['page_path']);
    }

    public function testExportAndErasureIncludeTheirFeedback(): void
    {
        $nicole = $this->person('nicole.tan');
        SiteFeedback::send($nicole, 'confusing', 'Where do I see my study plan?', '/student/calendar');
        $path = Privacy::export((int) $nicole['id']);
        $data = json_decode((string) file_get_contents(Uploads::absolute($path)), true);
        Uploads::delete($path);
        self::assertSame(['Where do I see my study plan?'], array_column($data['feedback_sent'], 'body'));
    }

    public function testWeeksRunMondayToSundayInTheSchoolsTimeZone(): void
    {
        $zone = new DateTimeZone('Asia/Manila');
        $weeks = PilotReport::weeks(2, new DateTimeImmutable('2026-09-20 17:30:00', new DateTimeZone('UTC')), $zone); // Monday 01:30 in Manila
        self::assertSame(['2026-09-14 00:00', '2026-09-21 00:00'], array_map(static fn ($w) => $w['start']->format('Y-m-d H:i'), $weeks));
        self::assertSame('2026-09-28 00:00', $weeks[1]['end']->format('Y-m-d H:i'));
        self::assertSame([false, true], array_column($weeks, 'current'));
        self::assertSame(null, PilotReport::shown(4, 4));
        self::assertSame(0, PilotReport::shown(0, 0));
        self::assertSame(12, PilotReport::shown(12, 5));
    }

    public function testTheWeeklyReportCountsRecordsAndHidesGroupsUnderFive(): void
    {
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            [$org, $rows] = $this->reportSchool();
            [$current, $week] = $rows;

            self::assertSame(['2026-09-14', '2026-09-20', false], [$week['start'], $week['end'], $week['current']]);
            self::assertSame(6, $week['students'], 'six of the seven students did something that week');
            self::assertNull($week['teachers'], 'one teacher is fewer than five people');
            self::assertNull($week['materials']);
            self::assertNull($week['assignments']);
            self::assertSame(12, $week['due'], 'two tasks for six students each, without the excused one or the moved due date');
            self::assertSame([33.3, 8.3, 58.3], [$week['on_time'], $week['late'], $week['missing']]);
            self::assertSame([7, 5], [$week['companion'], $week['companion_students']], 'answered questions only');
            self::assertSame([null, null], [$week['practice'], $week['practice_students']]);
            self::assertSame([5, 0, null], [$week['feedback_problem'], $week['feedback_confusing'], $week['feedback_idea']]);

            self::assertTrue($current['current']);
            self::assertNull($current['students'], 'one active student');
            self::assertNull($current['due'], 'one student\'s own due date');
            self::assertSame([null, null, null], [$current['on_time'], $current['late'], $current['missing']]);
            self::assertSame(0, $current['companion']);

            $csv = PilotReport::csv($rows);
            $lines = array_map('str_getcsv', explode("\n", trim(substr($csv, 3))));
            self::assertSame(['Week starting', 'Week ending', 'Active students'], array_slice($lines[0], 0, 3));
            self::assertSame(['2026-09-21', '2026-09-27', 'hidden', '0'], array_slice($lines[1], 0, 4));
            self::assertSame('33.3', $lines[2][7]);
            foreach (['Report', 'report.s'] as $name) {
                self::assertStringNotContainsString($name, $csv, 'no names or usernames');
            }
            self::assertSame($org, (int) Db::value("SELECT id FROM organizations WHERE slug = 'report-test'"));
        } finally {
            $pdo->rollBack();
        }
    }

    /**
     * A small school with one week of records, 14–20 September 2026 (Manila), and a little in the next week.
     * @return array{0:int,1:list<array<string,mixed>>}
     */
    private function reportSchool(): array
    {
        $org = Db::insert('organizations', ['name' => 'Report Test School', 'slug' => 'report-test', 'timezone' => 'Asia/Manila', 'is_demo' => 1]);
        $subject = Db::insert('subjects', ['organization_id' => $org, 'name' => 'Science']);
        $class = Db::insert('classes', ['organization_id' => $org, 'subject_id' => $subject, 'name' => 'Science 9 – Test', 'status' => 'active']);
        $person = static function (string $role, int $n) use ($org): int {
            return Db::insert('users', ['organization_id' => $org, 'role' => $role, 'given_name' => 'Report', 'family_name' => ucfirst($role) . " $n", 'username' => "report.$role$n", 'status' => 'active', 'is_demo' => 1]);
        };
        $teacher = $person('teacher', 1);
        Db::insert('class_memberships', ['class_id' => $class, 'user_id' => $teacher, 'role' => 'teacher', 'status' => 'active']);
        $s = [];
        for ($i = 1; $i <= 7; $i++) {
            $s[$i] = $person('student', $i);
            Db::insert('class_memberships', ['class_id' => $class, 'user_id' => $s[$i], 'role' => 'student', 'status' => 'active']);
        }
        $task = static fn (string $title, string $due) => Db::insert('assignments', [
            'organization_id' => $org, 'class_id' => $class, 'kind' => 'assignment', 'title' => $title, 'status' => 'published',
            'due_at' => $due, 'published_at' => '2026-09-14 01:00:00', 'created_by' => $teacher,
        ]);
        $a1 = $task('Lab report', '2026-09-16 01:00:00');
        $a3 = $task('Reflection', '2026-09-18 01:00:00');
        Db::insert('assignment_overrides', ['organization_id' => $org, 'assignment_id' => $a1, 'student_id' => $s[7], 'excused' => 1]);
        Db::insert('assignment_overrides', ['organization_id' => $org, 'assignment_id' => $a3, 'student_id' => $s[1], 'due_at' => '2026-09-22 01:00:00']);
        foreach ([1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 1] as $i => $late) {
            Db::insert('submissions', ['organization_id' => $org, 'assignment_id' => $a1, 'student_id' => $s[$i], 'status' => 'submitted',
                'submitted_at' => $late ? '2026-09-17 02:00:00' : '2026-09-15 02:00:00', 'is_late' => $late]);
        }
        Db::insert('learning_materials', ['organization_id' => $org, 'class_id' => $class, 'title' => 'Notes', 'kind' => 'link', 'url' => 'https://example.org',
            'visibility' => 'published', 'published_at' => '2026-09-15 00:00:00', 'created_by' => $teacher]);
        $event = static fn (int $user, string $status, string $at) => Db::insert('ai_usage_events', [
            'organization_id' => $org, 'user_id' => $user, 'user_role' => 'student', 'feature' => 'companion', 'provider' => 'demo', 'is_demo' => 1, 'status' => $status, 'created_at' => $at,
        ]);
        foreach ([1, 1, 1, 2, 3, 4, 5] as $i) {
            $event($s[$i], 'ok', '2026-09-16 06:00:00');
        }
        $event($s[6], 'error', '2026-09-16 06:00:00');
        foreach ([1, 2] as $i) {
            Db::insert('practice_sets', ['organization_id' => $org, 'user_id' => $s[$i], 'class_id' => $class, 'kind' => 'quiz', 'title' => 'Practice', 'items' => '[]', 'generator' => 'demo', 'created_at' => '2026-09-17 03:00:00']);
        }
        foreach ([1, 2, 3, 4, 5] as $i) {
            Db::insert('site_feedback', ['organization_id' => $org, 'user_id' => $s[$i], 'user_role' => 'student', 'category' => 'problem', 'body' => 'Slow page.', 'created_at' => '2026-09-18 03:00:00']);
        }
        Db::insert('site_feedback', ['organization_id' => $org, 'user_id' => $s[6], 'user_role' => 'student', 'category' => 'idea', 'body' => 'Pictures.', 'created_at' => '2026-09-18 03:00:00']);
        Db::insert('audit_logs', ['organization_id' => $org, 'actor_id' => $teacher, 'actor_role' => 'teacher', 'action' => 'auth.login', 'created_at' => '2026-09-14 00:30:00']);
        Db::insert('content_views', ['user_id' => $s[7], 'content_type' => 'material', 'content_id' => 1, 'class_id' => $class, 'first_viewed_at' => '2026-09-22 02:00:00', 'last_viewed_at' => '2026-09-22 02:00:00']);

        $rows = PilotReport::rows($org, 2, new DateTimeImmutable('2026-09-23 04:00:00', new DateTimeZone('UTC')));
        return [$org, $rows];
    }
}
