<?php

declare(strict_types=1);

namespace Sibol\Services;

use Sibol\Core\AppKey;
use Sibol\Core\Audit;
use Sibol\Core\Db;
use Sibol\Core\Session;

/**
 * Two-step sign-in for staff, optional for administrators and teachers: after the password, a code from an
 * authenticator app (Totp) or one of ten single-use recovery codes. The app's secret is sealed with APP_KEY and
 * recovery codes are kept only as keyed hashes. A sign-in waiting for its code is held in the session (begin()),
 * and nobody is signed in until the code matches.
 */
final class TwoStep
{
    public const RECOVERY_CODES = 10;
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    private const PENDING_SECONDS = 300;
    private const SETUP_SECONDS = 900;

    /** Administrators and teachers can turn it on; students sign in with a password or a slip. */
    public static function offered(array $user): bool
    {
        return in_array($user['role'] ?? '', ['admin', 'teacher'], true);
    }

    /** It needs APP_KEY, which seals the secrets. */
    public static function available(): bool
    {
        return AppKey::available();
    }

    public static function enabled(array $user): bool
    {
        return !empty($user['totp_enabled_at']) && !empty($user['totp_secret']);
    }

    /** The secret being set up in this session: the same one until it is confirmed or 15 minutes pass. */
    public static function setupSecret(int $now): string
    {
        $setup = $_SESSION['two_step_setup'] ?? null;
        if (!is_array($setup) || (int) ($setup['until'] ?? 0) < $now) {
            $setup = ['secret' => base64_encode(Totp::newSecret()), 'until' => $now + self::SETUP_SECONDS];
            $_SESSION['two_step_setup'] = $setup;
        }
        return (string) base64_decode((string) $setup['secret']);
    }

    /**
     * Turns it on when the first code from the app matches the secret shown. Returns the new recovery codes, to show
     * once, or null when the code does not match.
     *
     * @return list<string>|null
     */
    public static function enable(array $user, string $secret, string $typed, int $now): ?array
    {
        $step = Totp::match($secret, $typed, $now);
        if ($step === null) {
            return null;
        }
        $codes = Db::transaction(static function () use ($user, $secret, $step): array {
            Db::update('users', [
                'totp_secret' => AppKey::seal($secret),
                'totp_enabled_at' => Db::now(),
                'totp_last_step' => $step,
                'updated_at' => Db::now(),
            ], ['id' => (int) $user['id']]);
            return self::replaceRecoveryCodes((int) $user['id']);
        });
        unset($_SESSION['two_step_setup']);
        Audit::log('auth.two_step_on', 'user', (int) $user['id']);
        return $codes;
    }

    /**
     * Checks a code typed at sign-in: an app code (each one works once) or an unused recovery code, which is then
     * used up. Returns 'app', 'recovery' or null.
     */
    public static function verify(array $user, string $typed, int $now): ?string
    {
        $secret = AppKey::open((string) ($user['totp_secret'] ?? ''));
        if ($secret !== null) {
            $used = $user['totp_last_step'] !== null ? (int) $user['totp_last_step'] : null;
            $step = Totp::match($secret, $typed, $now, $used);
            if ($step !== null) {
                // Only a newer step is written, so two sign-ins racing with one code cannot both pass.
                $claimed = Db::exec(
                    'UPDATE users SET totp_last_step = :step WHERE id = :id AND (totp_last_step IS NULL OR totp_last_step < :step2)',
                    ['step' => $step, 'id' => (int) $user['id'], 'step2' => $step]
                );
                return $claimed === 1 ? 'app' : null;
            }
        }
        $code = self::normalise($typed);
        if ($code !== null && AppKey::available()) {
            $spent = Db::exec(
                'UPDATE recovery_codes SET used_at = :now WHERE user_id = :u AND code_hash = :h AND used_at IS NULL',
                ['now' => Db::now(), 'u' => (int) $user['id'], 'h' => AppKey::hash($code)]
            );
            if ($spent === 1) {
                return 'recovery';
            }
        }
        return null;
    }

    /** @return list<string> ten new recovery codes (earlier ones stop working), shown once and kept only as hashes */
    public static function replaceRecoveryCodes(int $userId): array
    {
        Db::exec('DELETE FROM recovery_codes WHERE user_id = :u', ['u' => $userId]);
        $codes = [];
        while (count($codes) < self::RECOVERY_CODES) {
            $code = '';
            for ($i = 0; $i < 10; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $codes[$code] = substr($code, 0, 5) . '-' . substr($code, 5);
        }
        foreach (array_keys($codes) as $code) {
            Db::insert('recovery_codes', ['user_id' => $userId, 'code_hash' => AppKey::hash($code), 'created_at' => Db::now()]);
        }
        return array_values($codes);
    }

    public static function recoveryCodesLeft(int $userId): int
    {
        return (int) Db::value('SELECT COUNT(*) FROM recovery_codes WHERE user_id = :u AND used_at IS NULL', ['u' => $userId]);
    }

    /**
     * Switches it off: by the person (with their password), an administrator (a lost phone) or the command line.
     * The person is told when someone else did it.
     */
    public static function disable(array $user, ?array $by): void
    {
        Db::transaction(static function () use ($user): void {
            Db::update('users', ['totp_secret' => null, 'totp_enabled_at' => null, 'totp_last_step' => null, 'updated_at' => Db::now()], ['id' => (int) $user['id']]);
            Db::exec('DELETE FROM recovery_codes WHERE user_id = :u', ['u' => (int) $user['id']]);
        });
        $self = $by !== null && (int) $by['id'] === (int) $user['id'];
        // From the command line there is no actor, so the school is named for the audit log.
        Audit::log($self ? 'auth.two_step_off' : 'user.two_step_reset', 'user', (int) $user['id'], $by === null ? ['organization_id' => (int) $user['organization_id'], 'via' => 'command line'] : [], $by);
        if (!$self) {
            Notifier::send((int) $user['organization_id'], [(int) $user['id']], 'account',
                t('Two-step sign-in was switched off for your account'),
                t('A school administrator switched it off, for example after a lost phone. If you did not ask for this, tell your school straight away. You can set it up again in Account and preferences.'),
                '/settings#two-step');
        }
    }

    /** Holds a sign-in whose password (or school account) checked out until its code is typed. */
    public static function begin(array $user, string $target): void
    {
        Session::regenerate();
        $_SESSION['two_step'] = ['uid' => (int) $user['id'], 'sv' => (int) $user['session_version'], 'until' => time() + self::PENDING_SECONDS, 'to' => $target];
    }

    /** @return array{user: array<string,mixed>, to: string}|null the sign-in waiting for its code in this session */
    public static function pending(): ?array
    {
        $pending = $_SESSION['two_step'] ?? null;
        if (!is_array($pending) || (int) ($pending['until'] ?? 0) < time()) {
            unset($_SESSION['two_step']);
            return null;
        }
        $user = Db::one('SELECT * FROM users WHERE id = :id', ['id' => (int) $pending['uid']]);
        if (!$user || $user['status'] !== 'active' || (int) $user['session_version'] !== (int) $pending['sv'] || !self::enabled($user)) {
            unset($_SESSION['two_step']);
            return null;
        }
        return ['user' => $user, 'to' => (string) $pending['to']];
    }

    public static function forget(): void
    {
        unset($_SESSION['two_step']);
    }

    /** A recovery code as typed (any case, with or without the hyphen or spaces), or null if it cannot be one. */
    private static function normalise(string $typed): ?string
    {
        $code = strtoupper((string) preg_replace('/[\s-]+/', '', $typed));
        return strlen($code) === 10 && strspn($code, self::ALPHABET) === 10 ? $code : null;
    }
}
