<?php

declare(strict_types=1);

namespace Sibol\Tests\Integration;

use Sibol\Core\AppKey;
use Sibol\Core\Config;
use Sibol\Core\Db;
use Sibol\Services\Backup;
use Sibol\Services\Totp;
use Sibol\Services\TwoStep;

/** Item 15: optional two-step sign-in for staff (app codes and recovery codes) and the backup command. */
final class TwoStepTest extends IntegrationTestCase
{
    private const T = 1792000000;

    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $joy = $this->person('joy.santiago');
        Db::update('users', ['totp_secret' => null, 'totp_enabled_at' => null, 'totp_last_step' => null], ['id' => (int) $joy['id']]);
        Db::exec('DELETE FROM recovery_codes');
        Db::exec('DELETE FROM notifications WHERE type = :t', ['t' => 'account']);
        $_SESSION = [];
        parent::tearDown();
    }

    public function testOnlyStaffAreOfferedItAndItNeedsAnAppKey(): void
    {
        self::assertTrue(TwoStep::offered($this->person('joy.santiago')));
        self::assertTrue(TwoStep::offered(['role' => 'admin']));
        self::assertFalse(TwoStep::offered($this->person('andrea.cruz')));
        self::assertTrue(TwoStep::available(), 'phpunit.xml sets APP_KEY');
        $key = Config::get('app.key');
        try {
            Config::set('app.key', '');
            self::assertFalse(TwoStep::available());
            Config::set('app.key', 'base64:' . base64_encode('too short'));
            self::assertFalse(TwoStep::available());
        } finally {
            Config::set('app.key', $key);
        }
    }

    public function testTurningItOnNeedsACodeFromTheAppAndSealsTheSecret(): void
    {
        $joy = $this->person('joy.santiago');
        $secret = TwoStep::setupSecret(self::T);
        self::assertSame($secret, TwoStep::setupSecret(self::T + 60), 'the QR code stays the same while it is set up');
        self::assertNull(TwoStep::enable($joy, $secret, '000000', self::T));
        self::assertFalse(TwoStep::enabled($this->person('joy.santiago')));

        $codes = TwoStep::enable($joy, $secret, Totp::code($secret, Totp::step(self::T)), self::T);
        self::assertCount(10, $codes);
        self::assertMatchesRegularExpression('/^[A-HJKMNP-Z2-9]{5}-[A-HJKMNP-Z2-9]{5}$/', $codes[0]);
        $row = $this->person('joy.santiago');
        self::assertTrue(TwoStep::enabled($row));
        self::assertStringNotContainsString(Totp::base32($secret), (string) $row['totp_secret'], 'sealed, not readable from the database');
        self::assertSame($secret, AppKey::open((string) $row['totp_secret']));
        self::assertArrayNotHasKey('two_step_setup', $_SESSION);
        $stored = Db::all('SELECT code_hash FROM recovery_codes WHERE user_id = :u', ['u' => (int) $joy['id']]);
        self::assertCount(10, $stored);
        foreach ($stored as $s) {
            self::assertNotContains(str_replace('-', '', $codes[0]), $s);
        }
        self::assertSame('auth.two_step_on', Db::value('SELECT action FROM audit_logs WHERE target_id = :u ORDER BY id DESC LIMIT 1', ['u' => (int) $joy['id']]));
    }

    public function testEachAppCodeAndRecoveryCodeWorksOnce(): void
    {
        $joy = $this->person('joy.santiago');
        $secret = TwoStep::setupSecret(self::T);
        $codes = TwoStep::enable($joy, $secret, Totp::code($secret, Totp::step(self::T)), self::T);
        $later = self::T + 90;

        self::assertNull(TwoStep::verify($this->person('joy.santiago'), Totp::code($secret, Totp::step(self::T)), self::T), 'the code used to turn it on');
        self::assertSame('app', TwoStep::verify($this->person('joy.santiago'), Totp::code($secret, Totp::step($later)), $later));
        self::assertNull(TwoStep::verify($this->person('joy.santiago'), Totp::code($secret, Totp::step($later)), $later), 'the same code again');
        self::assertNull(TwoStep::verify($this->person('joy.santiago'), '123456', $later + 30));

        self::assertSame('recovery', TwoStep::verify($this->person('joy.santiago'), strtolower($codes[3]), $later));
        self::assertNull(TwoStep::verify($this->person('joy.santiago'), $codes[3], $later), 'a recovery code works once');
        self::assertSame('recovery', TwoStep::verify($this->person('joy.santiago'), str_replace('-', ' ', $codes[4]), $later));
        self::assertSame(8, TwoStep::recoveryCodesLeft((int) $joy['id']));

        $fresh = TwoStep::replaceRecoveryCodes((int) $joy['id']);
        self::assertNull(TwoStep::verify($this->person('joy.santiago'), $codes[5], $later), 'old codes stop working');
        self::assertSame('recovery', TwoStep::verify($this->person('joy.santiago'), $fresh[0], $later));
    }

    public function testASignInWaitsForItsCodeAndEndsIfTheAccountChanges(): void
    {
        $joy = $this->person('joy.santiago');
        $secret = TwoStep::setupSecret(self::T);
        TwoStep::enable($joy, $secret, Totp::code($secret, Totp::step(self::T)), self::T);
        $joy = $this->person('joy.santiago');

        TwoStep::begin($joy, '/teacher');
        self::assertArrayNotHasKey('uid', $_SESSION, 'not signed in yet');
        $pending = TwoStep::pending();
        self::assertSame([(int) $joy['id'], '/teacher'], [(int) $pending['user']['id'], $pending['to']]);

        Db::exec('UPDATE users SET session_version = session_version + 1 WHERE id = :id', ['id' => (int) $joy['id']]);
        self::assertNull(TwoStep::pending(), 'signed out everywhere meanwhile');
        Db::exec('UPDATE users SET session_version = session_version - 1 WHERE id = :id', ['id' => (int) $joy['id']]);

        TwoStep::begin($joy, '/teacher');
        $_SESSION['two_step']['until'] = time() - 1;
        self::assertNull(TwoStep::pending(), 'five minutes passed');
    }

    public function testSwitchingItOffForSomeoneElseTellsThem(): void
    {
        $joy = $this->person('joy.santiago');
        $secret = TwoStep::setupSecret(self::T);
        TwoStep::enable($joy, $secret, Totp::code($secret, Totp::step(self::T)), self::T);
        $admin = $this->person('teresa.villanueva');

        TwoStep::disable($this->person('joy.santiago'), $admin);
        $row = $this->person('joy.santiago');
        self::assertFalse(TwoStep::enabled($row));
        self::assertNull($row['totp_secret']);
        self::assertSame(0, (int) Db::value('SELECT COUNT(*) FROM recovery_codes WHERE user_id = :u', ['u' => (int) $joy['id']]));
        self::assertSame(1, (int) Db::value("SELECT COUNT(*) FROM notifications WHERE user_id = :u AND type = 'account'", ['u' => (int) $joy['id']]));
        $log = Db::one('SELECT action, actor_id, metadata FROM audit_logs WHERE target_id = :u ORDER BY id DESC LIMIT 1', ['u' => (int) $joy['id']]);
        self::assertSame(['user.two_step_reset', (int) $admin['id']], [$log['action'], (int) $log['actor_id']]);
    }
}
