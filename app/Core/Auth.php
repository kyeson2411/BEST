<?php

declare(strict_types=1);

namespace Sibol\Core;

/**
 * The signed-in user is re-read from the database on every request, so a
 * deactivated account, a changed role, or "sign out other sessions"
 * (session_version bump) takes effect immediately.
 */
final class Auth
{
    /** @var array<string,mixed>|null */
    private static ?array $user = null;
    private static bool $loaded = false;

    /** @return array<string,mixed>|null */
    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;
        $id = $_SESSION['uid'] ?? null;
        if (!$id) {
            return null;
        }
        $user = Db::one(
            'SELECT u.*, o.name AS org_name, o.short_name AS org_short_name, o.timezone AS org_timezone,
                    o.default_language AS org_language, o.is_demo AS org_is_demo, o.logo_path AS org_logo_path
             FROM users u JOIN organizations o ON o.id = u.organization_id
             WHERE u.id = :id',
            ['id' => (int) $id]
        );
        $idleLimit = (int) Config::get('app.idle_minutes', 120) * 60;
        $idle = isset($_SESSION['last_seen']) && (time() - (int) $_SESSION['last_seen']) > $idleLimit;
        if (!$user || $user['status'] !== 'active' || (int) $user['session_version'] !== (int) ($_SESSION['sv'] ?? -1) || $idle) {
            self::logout();
            Session::flash('info', $idle ? t('You were signed out after a period of inactivity.') : t('Your session has ended. Sign in again.'));
            return null;
        }
        $_SESSION['last_seen'] = time();
        self::$user = $user;
        return self::$user;
    }

    /** @param array<string,mixed> $user */
    public static function login(array $user): void
    {
        Session::regenerate();
        Csrf::rotate();
        $_SESSION['uid'] = (int) $user['id'];
        $_SESSION['sv'] = (int) $user['session_version'];
        $_SESSION['login_at'] = time();
        $_SESSION['last_seen'] = time();
        Db::update('users', ['last_login_at' => Db::now()], ['id' => (int) $user['id']]);
        self::$loaded = false;
        self::$user = null;
    }

    public static function logout(): void
    {
        unset($_SESSION['uid'], $_SESSION['sv'], $_SESSION['login_at'], $_SESSION['last_seen']);
        Session::regenerate();
        Csrf::rotate();
        self::$user = null;
        self::$loaded = true;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user ? (int) $user['id'] : null;
    }

    public static function role(): ?string
    {
        $user = self::user();
        return $user ? (string) $user['role'] : null;
    }

    public static function orgId(): ?int
    {
        $user = self::user();
        return $user ? (int) $user['organization_id'] : null;
    }

    /** Test helper: act as a user without a browser session. */
    public static function actingAs(?array $user): void
    {
        self::$user = $user;
        self::$loaded = true;
    }

    public static function refresh(): void
    {
        self::$loaded = false;
        self::$user = null;
    }
}
