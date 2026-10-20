<?php

declare(strict_types=1);

namespace Sibol\Core;

/**
 * Native PHP sessions with hardened cookie settings, flash messages,
 * and one-request "old input" / validation errors.
 */
final class Session
{
    /** @var array<string,mixed> */
    private static array $current = ['old' => [], 'errors' => []];

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $dir = BASE_PATH . '/storage/sessions';
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        session_save_path($dir);
        session_name('sibol_sid');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.gc_maxlifetime', '28800');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => (base_path_url() ?: '') . '/',
            'secure' => (bool) Config::get('app.secure_cookie'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        $next = $_SESSION['_next'] ?? null;
        unset($_SESSION['_next']);
        self::$current = [
            'old' => is_array($next['old'] ?? null) ? $next['old'] : [],
            'errors' => is_array($next['errors'] ?? null) ? $next['errors'] : [],
        ];
    }

    /** @return mixed */
    public static function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    /** @param mixed $value */
    public static function set(string $key, $value): void
    {
        if ($value === null) {
            unset($_SESSION[$key]);
            return;
        }
        $_SESSION[$key] = $value;
    }

    /** @return mixed */
    public static function pull(string $key, $default = null)
    {
        $value = $_SESSION[$key] ?? $default;
        unset($_SESSION[$key]);
        return $value;
    }

    /** $type: success | error | info | warning */
    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return list<array{type:string,message:string}> */
    public static function takeFlashes(): array
    {
        $flashes = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return is_array($flashes) ? $flashes : [];
    }

    /** Keep submitted values and errors for the next request (after a redirect). */
    public static function withInput(array $old, array $errors = []): void
    {
        unset($old['_token'], $old['password'], $old['password_confirmation'], $old['current_password']);
        $_SESSION['_next'] = ['old' => $old, 'errors' => $errors];
    }

    /** @return mixed */
    public static function old(string $key, $default = null)
    {
        return self::$current['old'][$key] ?? $default;
    }

    /** @return array<string,string> */
    public static function errors(): array
    {
        return self::$current['errors'];
    }

    /** For the current request only (re-rendering a form without a redirect). */
    public static function setCurrent(array $old, array $errors): void
    {
        self::$current = ['old' => $old, 'errors' => $errors];
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /** Release the session lock before long-running work (AI streaming). */
    public static function release(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }
}
