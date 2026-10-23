<?php

declare(strict_types=1);

namespace Sibol\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use Sibol\Core\Db;
use Sibol\Core\Translator;
use Sibol\Services\EmailDigest;
use Sibol\Services\Mailer;
use Sibol\Services\Notifier;
use Sibol\Services\SystemCheck;

/** Item 12: optional email. Messages are kept in memory (Mailer::fake), so nothing leaves the computer. */
final class EmailTest extends IntegrationTestCase
{
    protected function tearDown(): void
    {
        Mailer::unfake();
        parent::tearDown();
    }

    public function testEmailIsOffUntilConfiguredAndNeverShowsThePassword(): void
    {
        self::assertFalse(Mailer::enabled(), 'the test .env has no MAIL_HOST');
        self::assertFalse(Mailer::send('someone@school.test', 'Subject', 'Text'));
        self::assertFalse(EmailDigest::available($this->person('teresa.villanueva')));
        $status = Mailer::status();
        self::assertArrayNotHasKey('password', $status);
        self::assertIsBool($status['has_password']);
        $row = array_values(array_filter(SystemCheck::run(), static fn (array $r): bool => $r['label'] === 'Email delivery'))[0];
        self::assertFalse($row['ok']);
        self::assertStringContainsString('never claims an email was sent', $row['detail']);
    }

    public function testInvitationAndResetLinksAreEmailedInThePersonsLanguage(): void
    {
        Mailer::fake();
        $person = ['email' => 'maria.santos@school.test', 'given_name' => 'Maria', 'family_name' => 'Santos', 'preferred_name' => null, 'username' => 'maria.santos', 'language' => 'en'];
        self::assertTrue(Mailer::accountLink($person, 'invitation', 'https://sibol.school.test/invite/abc', '14 days', 'Test School'));
        $mail = Mailer::sent()[0];
        self::assertSame('maria.santos@school.test', $mail['to']);
        self::assertSame('Your B.E.S.T. Learning and Interactive Study System account at Test School', $mail['subject']);
        self::assertStringContainsString('https://sibol.school.test/invite/abc', $mail['text']);
        self::assertStringContainsString('Your username is maria.santos.', $mail['text']);

        $before = Translator::locale();
        $person['language'] = 'fil';
        self::assertTrue(Mailer::accountLink($person, 'password_reset', 'https://sibol.school.test/reset/xyz', '24 hours', 'Test School'));
        $reset = Mailer::sent()[1];
        self::assertStringContainsString('https://sibol.school.test/reset/xyz', $reset['text']);
        self::assertNotSame('Choose a new B.E.S.T. Learning and Interactive Study System password', $reset['subject'], 'written in Filipino for this person');
        self::assertSame($before, Translator::locale(), 'the page\'s own language comes back afterwards');

        unset($person['email']);
        self::assertFalse(Mailer::accountLink($person, 'invitation', 'https://x.test/invite/1', '14 days', 'Test School'), 'no address, no email: the link is copied instead');
        self::assertCount(2, Mailer::sent());
    }

    public function testTheDailySummaryListsUnreadTitlesOnlyAndAtMostOnceADay(): void
    {
        Mailer::fake();
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $teresa = $this->person('teresa.villanueva');
            $carmela = $this->person('carmela.reyes');
            Db::update('users', ['preferences' => json_encode(['email_digest' => true])], ['id' => (int) $teresa['id']]);
            $org = (int) $teresa['organization_id'];
            Notifier::send($org, [(int) $teresa['id']], 'announcement', 'Weekly pilot report is ready', 'A body that must not be emailed', '/admin/pilot', 'digest-1');
            Notifier::send($org, [(int) $teresa['id']], 'announcement', 'Already read', 'Read body', '/admin', 'digest-2');
            Db::exec("UPDATE notifications SET read_at = created_at WHERE user_id = :u AND dedupe_key = 'digest-2'", ['u' => (int) $teresa['id']]);
            Notifier::send($org, [(int) $carmela['id']], 'announcement', 'For someone who did not turn it on', null, '/teacher', 'digest-3');

            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            self::assertSame(['sent' => 1, 'failed' => 0], EmailDigest::run($now));
            $mail = Mailer::sent()[0];
            self::assertSame((string) $teresa['email'], $mail['to']);
            self::assertStringContainsString('- Weekly pilot report is ready', $mail['text']);
            self::assertStringNotContainsString('Already read', $mail['text']);
            self::assertStringNotContainsString('must not be emailed', $mail['text'], 'titles only, never bodies');
            self::assertSame(['sent' => 0, 'failed' => 0], EmailDigest::run($now->modify('+2 hours')), 'at most one a day');

            Notifier::send($org, [(int) $teresa['id']], 'announcement', 'The next day', null, '/admin', 'digest-4');
            Db::exec("UPDATE notifications SET created_at = :t WHERE dedupe_key = 'digest-4'", ['t' => $now->modify('+21 hours')->format('Y-m-d H:i:s')]);
            self::assertSame(['sent' => 1, 'failed' => 0], EmailDigest::run($now->modify('+22 hours')));
            self::assertStringContainsString('- The next day', Mailer::sent()[1]['text']);
            self::assertStringNotContainsString('Weekly pilot report', Mailer::sent()[1]['text'], 'only what is new since the last summary');
        } finally {
            $pdo->rollBack();
        }
    }
}
