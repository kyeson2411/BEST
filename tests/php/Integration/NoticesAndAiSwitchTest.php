<?php

declare(strict_types=1);

namespace Sibol\Tests\Integration;

use Sibol\Core\Db;
use Sibol\Core\HttpException;
use Sibol\Core\Uploads;
use Sibol\Policies\Access;
use Sibol\Services\Ai\Usage;
use Sibol\Services\AiAccess;
use Sibol\Services\AuditLabels;
use Sibol\Services\Insights;
use Sibol\Services\Navigation;
use Sibol\Services\Notices;
use Sibol\Services\Privacy;
use Sibol\Services\Settings;

/** Item 21: the privacy notice and rules students accept, and the AI companion switched off per student. */
final class NoticesAndAiSwitchTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        Notices::flush();
    }

    protected function tearDown(): void
    {
        Notices::flush();
        parent::tearDown();
    }

    public function testStudentsAcceptBothNoticesAndStaffDoNotUnlessThePolicySays(): void
    {
        $rafael = $this->person('rafael.domingo'); // invited, has not signed in yet
        $andrea = $this->person('andrea.cruz');
        $ramon = $this->person('ramon.dizon');
        self::assertSame(['privacy', 'rules'], Notices::pending($rafael));
        self::assertSame([], Notices::pending($andrea), 'demo students accepted the current versions');
        self::assertFalse(Notices::required($ramon));
        self::assertSame([], Notices::pending($ramon));

        Settings::override((int) $ramon['organization_id'], 'notices.staff', true);
        Notices::flush();
        self::assertSame(['privacy', 'rules'], Notices::pending($ramon));
        Settings::override((int) $ramon['organization_id'], 'notices.students', false);
        self::assertSame([], Notices::pending($rafael));
    }

    public function testAcceptingRecordsEachVersionAndAuditsNoText(): void
    {
        $rafael = $this->person('rafael.domingo');
        $id = (int) $rafael['id'];
        try {
            self::assertFalse(Notices::acceptedBefore($rafael));
            Notices::acknowledge($rafael, ['privacy']);
            self::assertTrue(Notices::acceptedBefore($rafael));
            self::assertSame(['rules'], Notices::pending($rafael));
            Notices::acknowledge($rafael, ['rules', 'something-else']);
            self::assertSame([], Notices::pending($rafael));
            Notices::acknowledge($rafael, ['rules']); // a second click changes nothing

            $current = Notices::current((int) $rafael['organization_id']);
            self::assertSame(
                [['notice' => 'privacy', 'version' => $current['privacy']], ['notice' => 'rules', 'version' => $current['rules']]],
                Db::all('SELECT notice, version FROM notice_acknowledgments WHERE user_id = :u ORDER BY notice', ['u' => $id])
            );
            $log = Db::one("SELECT * FROM audit_logs WHERE action = 'notices.acknowledged' AND target_id = :u ORDER BY id DESC LIMIT 1", ['u' => $id]);
            self::assertSame(['versions' => ['rules' => $current['rules']]], json_decode((string) $log['metadata'], true));
            self::assertSame('Accepted the privacy notice and the rules', AuditLabels::describe($log));
        } finally {
            Db::exec('DELETE FROM notice_acknowledgments WHERE user_id = :u', ['u' => $id]);
        }
    }

    public function testAskingAgainMakesEveryoneAcceptTheNewEdition(): void
    {
        $admin = $this->person('teresa.villanueva');
        $andrea = $this->person('andrea.cruz');
        $org = (int) $admin['organization_id'];
        $stored = Db::value("SELECT setting_value FROM organization_settings WHERE organization_id = :o AND setting_key = 'notices'", ['o' => $org]);
        $before = Notices::progress($org);
        self::assertGreaterThan(0, $before['required']);
        self::assertSame($before['required'], $before['accepted'], 'every active demo student has accepted');
        try {
            self::assertSame(2, Notices::askAgain($admin));
            self::assertSame(['privacy' => Notices::VERSIONS['privacy'] . '-2', 'rules' => Notices::VERSIONS['rules'] . '-2'], Notices::current($org));
            self::assertSame(['privacy', 'rules'], Notices::pending($andrea));
            self::assertTrue(Notices::acceptedBefore($andrea), 'so work already under way is not interrupted');
            self::assertSame(['required' => $before['required'], 'accepted' => 0], Notices::progress($org));

            Notices::acknowledge($andrea, ['privacy', 'rules']);
            self::assertSame([], Notices::pending($andrea));
            self::assertSame(1, Notices::progress($org)['accepted']);
            self::assertSame(2, (int) Db::value("SELECT COUNT(*) FROM notice_acknowledgments WHERE user_id = :u AND version LIKE '%-1'", ['u' => (int) $andrea['id']]), 'earlier acceptances are kept');
            $log = Db::one("SELECT * FROM audit_logs WHERE action = 'notices.renewed' ORDER BY id DESC LIMIT 1");
            self::assertSame((int) $admin['id'], (int) $log['actor_id']);
            self::assertSame(['edition' => 2], json_decode((string) $log['metadata'], true));
        } finally {
            Db::exec("DELETE FROM notice_acknowledgments WHERE user_id = :u AND version LIKE '%-2'", ['u' => (int) $andrea['id']]);
            if ($stored === null) {
                Db::exec("DELETE FROM organization_settings WHERE organization_id = :o AND setting_key = 'notices'", ['o' => $org]);
            } else {
                Settings::set($org, 'notices', json_decode((string) $stored, true));
            }
            Settings::flush();
        }
    }

    public function testAStudentsOwnSettingWinsOverTheSchoolsStartingValue(): void
    {
        $luis = $this->person('luis.pascual');     // switched off after his guardian said no
        $trisha = $this->person('trisha.mendoza'); // switched on after her guardian said yes
        $andrea = $this->person('andrea.cruz');    // follows the school
        $ramon = $this->person('ramon.dizon');
        $org = (int) $luis['organization_id'];

        self::assertFalse(AiAccess::allowed($luis));
        self::assertFalse(AiAccess::explicit($luis));
        self::assertTrue(AiAccess::allowed($trisha));
        self::assertTrue(AiAccess::allowed($andrea));
        self::assertNull(AiAccess::explicit($andrea));

        Settings::override($org, 'ai.students_start_on', false);
        self::assertFalse(AiAccess::allowed($andrea), 'students without their own setting start off');
        self::assertTrue(AiAccess::allowed($trisha), 'a guardian\'s yes keeps it on');
        self::assertTrue(AiAccess::allowed($ramon), 'teachers are not affected');
        self::assertSame(
            ['trisha.mendoza'],
            Db::column('SELECT u.username FROM users u WHERE u.organization_id = :o AND u.role = \'student\' AND ' . AiAccess::studentSql('u', $org), ['o' => $org])
        );

        Settings::flush();
        Settings::override($org, 'ai.enabled', false);
        self::assertFalse(AiAccess::allowed($trisha), 'the school switch still turns everything off');
    }

    public function testCompanionAndPracticeDisappearAndRequestsAreRefusedForAStudentWithAiOff(): void
    {
        $luis = $this->person('luis.pascual');
        $urls = array_column(Navigation::items($luis), 'url');
        self::assertNotContains('/companion', $urls);
        self::assertNotContains('/student/learning', $urls);
        self::assertContains('/student/assignments', $urls);
        self::assertContains('/companion', array_column(Navigation::items($this->person('trisha.mendoza')), 'url'));

        self::assertStringContainsString('switched off for your account', (string) Usage::blockedReason($luis, 'companion'));
        $event = Db::one('SELECT * FROM ai_usage_events WHERE user_id = :u ORDER BY id DESC LIMIT 1', ['u' => (int) $luis['id']]);
        self::assertSame(['blocked', 'student_switched_off'], [$event['status'], $event['error_code']]);
    }

    public function testReteachingIdeasLeaveOutTheAnswersOfStudentsWithAiOff(): void
    {
        $luis = $this->person('luis.pascual');
        $class = $this->classId('Science 9 – Molave');
        $his = (int) Db::value(
            "SELECT COUNT(*) FROM submission_answers sa JOIN submissions s ON s.id = sa.submission_id AND s.status = 'submitted'
             JOIN questions q ON q.id = sa.question_id JOIN assignments a ON a.id = q.assignment_id
             WHERE s.student_id = :u AND a.class_id = :c AND sa.is_correct IS NOT NULL",
            ['u' => (int) $luis['id'], 'c' => $class]
        );
        self::assertGreaterThan(0, $his, 'Luis has answered quiz questions in this class');
        $all = array_sum(array_column(Insights::topics($class), 'attempts'));
        $forAi = array_sum(array_column(Insights::topics($class, (int) $luis['organization_id']), 'attempts'));
        self::assertSame($all - $his, $forAi);
    }

    public function testAGuardiansAnswerIsRecordedAndSwitchesTheCompanion(): void
    {
        $liza = $this->person('liza.mercado');
        $bianca = $this->person('bianca.flores');
        $id = (int) $bianca['id'];
        try {
            AiAccess::recordGuardian($liza, $bianca, false, '2026-10-01');
            $bianca = $this->person('bianca.flores');
            self::assertFalse(AiAccess::allowed($bianca));
            self::assertSame([['answer' => 'no', 'answered_on' => '2026-10-01']], array_map(
                static fn ($r) => ['answer' => $r['answer'], 'answered_on' => $r['answered_on']],
                AiAccess::guardianAnswers($id)
            ));
            self::assertSame('Liza', AiAccess::guardianAnswers($id)[0]['given_name'], 'who entered it is shown');

            AiAccess::recordGuardian($liza, $bianca, true, '2026-10-05');
            $bianca = $this->person('bianca.flores');
            self::assertTrue(AiAccess::allowed($bianca));
            self::assertSame(['yes', 'no'], array_column(AiAccess::guardianAnswers($id), 'answer'), 'newest first, earlier answers kept');

            $logs = Db::all("SELECT * FROM audit_logs WHERE target_type = 'user' AND target_id = :u AND action IN ('user.guardian_answer', 'user.ai_switched') ORDER BY id", ['u' => $id]);
            self::assertSame(
                ['user.guardian_answer', 'user.ai_switched', 'user.guardian_answer', 'user.ai_switched'],
                array_column($logs, 'action')
            );
            self::assertSame(['topic' => 'ai', 'answer' => 'no'], json_decode((string) $logs[0]['metadata'], true));
            self::assertSame(['setting' => 'off'], json_decode((string) $logs[1]['metadata'], true));
            self::assertSame('Recorded a guardian\'s no to the AI companion', AuditLabels::describe($logs[0]));
            self::assertSame('Switched the AI companion on for a student', AuditLabels::describe($logs[3]));

            AiAccess::set($liza, $bianca, true); // already on: nothing changes, nothing logged
            AiAccess::set($liza, $bianca, null);
            self::assertNull(AiAccess::explicit($this->person('bianca.flores')));
            self::assertSame(5, (int) Db::value("SELECT COUNT(*) FROM audit_logs WHERE target_type = 'user' AND target_id = :u AND action IN ('user.guardian_answer', 'user.ai_switched')", ['u' => $id]));
        } finally {
            Db::exec('DELETE FROM guardian_consents WHERE student_id = :u', ['u' => $id]);
            Db::update('users', ['ai_enabled' => null], ['id' => $id]);
        }
    }

    public function testOnlyTheSectionsAdviserMayChangeAStudentsSetting(): void
    {
        $luis = (int) $this->person('luis.pascual')['id'];
        $andrea = (int) $this->person('andrea.cruz')['id'];
        $molave = (int) Db::value("SELECT section_id FROM classes WHERE name = 'Science 9 – Molave'");
        $narra = (int) Db::value("SELECT section_id FROM classes WHERE name = 'Science 9 – Narra'");
        $liza = $this->person('liza.mercado');
        $ramon = $this->person('ramon.dizon');

        [, $student] = Access::advisedStudent($liza, $molave, $luis);
        self::assertSame($luis, (int) $student['id']);
        [, $student] = Access::advisedStudent($ramon, $narra, $andrea);
        self::assertSame($andrea, (int) $student['id']);

        $this->assertNotFound(fn () => Access::advisedStudent($ramon, $molave, $luis)); // teaches Luis, does not advise him
        $this->assertNotFound(fn () => Access::advisedStudent($liza, $molave, $andrea)); // another section's student
        $this->assertNotFound(fn () => Access::advisedStudent($liza, $narra, $andrea));  // another adviser's section
        self::assertSame($luis, (int) Access::studentAccount($ramon, $luis)['id'], 'class teachers can still print his slips');
    }

    public function testExportShowsAcceptedNoticesAndTheAiSetting(): void
    {
        $luis = $this->person('luis.pascual');
        $path = Privacy::export((int) $luis['id']);
        $data = json_decode((string) file_get_contents(Uploads::absolute($path)), true);
        Uploads::delete($path);

        self::assertSame(['privacy', 'rules'], array_column($data['notices_accepted'], 'notice'));
        self::assertSame('off', $data['ai_companion']['setting']);
        self::assertSame(['no'], array_column($data['ai_companion']['guardian_answers'], 'answer'));
        self::assertArrayNotHasKey('recorded_by', $data['ai_companion']['guardian_answers'][0], 'staff are not named in a student\'s export');
    }

    private function assertNotFound(callable $fn): void
    {
        try {
            $fn();
            self::fail('Expected a 404');
        } catch (HttpException $e) {
            self::assertSame(404, $e->status);
        }
    }
}
