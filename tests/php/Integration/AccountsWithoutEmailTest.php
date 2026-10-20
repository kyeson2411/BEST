<?php

declare(strict_types=1);

namespace Sibol\Tests\Integration;

use Sibol\Core\Db;
use Sibol\Core\HttpException;
use Sibol\Policies\Access;
use Sibol\Services\Sections;
use Sibol\Services\SignInSlips;
use Sibol\Services\Tokens;
use Sibol\Services\Usernames;

/** Item 19: usernames instead of email, advisers per section, and printed sign-in slips. */
final class AccountsWithoutEmailTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testDemoStudentsHaveUsernamesAndNoEmail(): void
    {
        $andrea = $this->person('andrea.cruz');
        self::assertNull($andrea['email']);
        self::assertSame(0, (int) Db::value("SELECT COUNT(*) FROM users WHERE role = 'student' AND email IS NOT NULL AND is_demo = 1"));
        self::assertSame(0, (int) Db::value('SELECT COUNT(*) FROM users WHERE username IS NULL AND anonymized_at IS NULL'));
        self::assertNotNull($this->person('ramon.dizon')['email'], 'teachers keep their email');
    }

    public function testNewUsernamesSkipOnesAlreadyTaken(): void
    {
        self::assertSame('andrea.cruz2', Usernames::forPerson('Andrea', 'Cruz'));
        $reserved = [];
        self::assertSame('juan.delacruz', Usernames::forPerson('Juan', 'Dela Cruz', $reserved));
        self::assertSame('juan.delacruz2', Usernames::forPerson('Juan', 'dela Cruz', $reserved), 'a second Juan in the same import');
        self::assertFalse(Usernames::available('andrea.cruz'));
        self::assertTrue(Usernames::available('andrea.cruz', (int) $this->person('andrea.cruz')['id']), 'your own name is available to you');
    }

    public function testSectionsComeFromClassesAndHaveAdvisers(): void
    {
        $narra = (int) Db::value("SELECT section_id FROM classes WHERE name = 'Science 9 – Narra'");
        self::assertSame($narra, (int) Db::value("SELECT section_id FROM classes WHERE name = 'English 9 – Narra'"), 'one section for all of its classes');
        self::assertSame($narra, Sections::forClass((int) $this->person('ramon.dizon')['organization_id'], (int) Db::value("SELECT academic_period_id FROM classes WHERE name = 'Science 9 – Narra'"), 'Grade 9', ' Narra '));
        self::assertSame('Grade 9 – Narra', Sections::label(Db::one('SELECT * FROM sections WHERE id = :s', ['s' => $narra])));
        $students = array_column(Sections::students($narra), 'username');
        self::assertContains('andrea.cruz', $students);
        self::assertContains('rafael.domingo', $students, 'invited students are listed too');
        self::assertNotContains('angelica.santos', $students, 'withdrawn students are not');
        self::assertNotContains('janelle.ocampo', $students, 'other sections are not');
        self::assertSame(['Narra'], array_column(Sections::advisedBy($this->person('ramon.dizon')), 'name'));
    }

    public function testWhoMayPrintASlipForAStudent(): void
    {
        $andrea = (int) $this->person('andrea.cruz')['id'];   // Grade 9 Narra
        $janelle = (int) $this->person('janelle.ocampo')['id']; // Grade 9 Molave

        // Ramon teaches science in both sections; Joy teaches only English 9 – Narra.
        self::assertSame($andrea, (int) Access::studentAccount($this->person('ramon.dizon'), $andrea)['id']);
        self::assertSame($janelle, (int) Access::studentAccount($this->person('ramon.dizon'), $janelle)['id']);
        self::assertSame($andrea, (int) Access::studentAccount($this->person('joy.santiago'), $andrea)['id']);
        $this->assertNotFound(fn () => Access::studentAccount($this->person('joy.santiago'), $janelle));

        // An adviser who teaches none of the section's classes still reaches its students.
        $molave = (int) Db::value("SELECT section_id FROM classes WHERE name = 'Science 9 – Molave'");
        $before = Db::value('SELECT adviser_id FROM sections WHERE id = :s', ['s' => $molave]);
        Db::update('sections', ['adviser_id' => (int) $this->person('joy.santiago')['id']], ['id' => $molave]);
        try {
            self::assertSame($janelle, (int) Access::studentAccount($this->person('joy.santiago'), $janelle)['id']);
            self::assertSame($molave, (int) Access::adviserSection($this->person('joy.santiago'), $molave)['id']);
        } finally {
            Db::update('sections', ['adviser_id' => $before], ['id' => $molave]);
        }
        $this->assertNotFound(fn () => Access::adviserSection($this->person('ramon.dizon'), $molave));

        // Administrators reach any student of their school; nobody reaches a teacher this way.
        self::assertSame($janelle, (int) Access::studentAccount($this->person('teresa.villanueva'), $janelle)['id']);
        $this->assertNotFound(fn () => Access::studentAccount($this->person('teresa.villanueva'), (int) $this->person('joy.santiago')['id']));
        $this->assertNotFound(fn () => Access::studentAccount($this->person('andrea.cruz'), $janelle));
        // A student who left the class is out of reach of its teacher.
        $this->assertNotFound(fn () => Access::studentAccount($this->person('joy.santiago'), (int) $this->person('angelica.santos')['id']));
    }

    public function testSlipsIssueOneTimeCodesAndCancelEarlierLinks(): void
    {
        $ramon = $this->person('ramon.dizon');
        $rafael = $this->person('rafael.domingo');   // invited
        $andrea = $this->person('andrea.cruz');     // active
        $angelica = $this->person('angelica.santos'); // deactivated
        $earlier = Tokens::issue((int) $andrea['id'], 'password_reset', (int) $ramon['id']);

        $count = SignInSlips::issue([$rafael, $andrea, $angelica], $ramon, 'Grade 9 – Narra', '/teacher/advisory/1');
        self::assertSame(2, $count, 'the deactivated account gets no slip');

        $slips = SignInSlips::current()['slips'];
        self::assertSame(['invitation', 'password_reset'], array_column($slips, 'purpose'));
        self::assertSame(['rafael.domingo', 'andrea.cruz'], array_column($slips, 'username'));
        foreach ($slips as $slip) {
            self::assertTrue(Tokens::isCode($slip['code']));
            self::assertStringEndsWith('/' . Tokens::normalise($slip['code']), $slip['url'], 'the QR link carries the same code');
        }
        self::assertSame((int) $rafael['id'], (int) Tokens::find(strtolower($slips[0]['code']), 'invitation')['id'], 'typed in small letters with dashes');
        self::assertSame((int) $andrea['id'], (int) Tokens::find($slips[1]['code'], 'password_reset')['id']);
        self::assertNull(Tokens::find($earlier, 'password_reset'), 'the earlier reset link stopped working');
        self::assertNull(Tokens::find($slips[1]['code'], 'invitation'), 'a code works only for its own purpose');

        $audit = Db::one("SELECT * FROM audit_logs WHERE action = 'user.signin_slips' ORDER BY id DESC LIMIT 1");
        self::assertSame((int) $ramon['id'], (int) $audit['actor_id']);
        self::assertSame(['setup' => 1, 'reset' => 1], json_decode((string) $audit['metadata'], true));
        foreach ($slips as $slip) {
            self::assertStringNotContainsString(Tokens::normalise($slip['code']), (string) $audit['metadata']);
        }
        self::assertSame(1, (int) Db::value("SELECT COUNT(*) FROM user_tokens WHERE user_id = :u AND purpose = 'password_reset' AND used_at IS NULL AND revoked_at IS NULL", ['u' => (int) $andrea['id']]));
        self::assertSame('/teacher/advisory/1', SignInSlips::backPath('/teacher/advisory/1', 'teacher'));
        self::assertSame('/teacher', SignInSlips::backPath('https://evil.example/', 'teacher'));
        self::assertSame('/teacher', SignInSlips::backPath('/admin/users', 'teacher'), 'a teacher is never sent into the admin area');

        // Leave the demo accounts as they were.
        Db::exec('UPDATE user_tokens SET revoked_at = UTC_TIMESTAMP() WHERE user_id IN (:a, :b) AND used_at IS NULL AND created_by = :r', ['a' => (int) $andrea['id'], 'b' => (int) $rafael['id'], 'r' => (int) $ramon['id']]);
        Tokens::issue((int) $rafael['id'], 'invitation', (int) $this->person('teresa.villanueva')['id']);
    }

    public function testSlipsAreKeptForHalfAnHourOnly(): void
    {
        SignInSlips::remember([['name' => 'X']], 'Test', '/admin');
        self::assertNotNull(SignInSlips::current());
        $_SESSION['signin_slips']['at'] = time() - SignInSlips::KEEP_SECONDS - 1;
        self::assertNull(SignInSlips::current());
    }

    public function testInvitedStudentsOfAClass(): void
    {
        $names = array_column(SignInSlips::invitedStudents([$this->classId('Science 9 – Narra')]), 'username');
        self::assertSame(['rafael.domingo'], $names);
        self::assertSame([], SignInSlips::invitedStudents([$this->classId('Science 9 – Molave')]));
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
