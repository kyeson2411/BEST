<?php

declare(strict_types=1);

namespace Sibol\Core;

/** Synchronizer-token CSRF protection plus an Origin check when the browser sends one. */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function rotate(): void
    {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    public static function verify(Request $request): bool
    {
        $token = $request->post['_token'] ?? $request->header('X-CSRF-Token') ?? ($request->json()['_token'] ?? '');
        if (!is_string($token) || $token === '' || !hash_equals(self::token(), $token)) {
            return false;
        }
        $origin = $request->header('Origin');
        if ($origin !== null && $origin !== '' && $origin !== 'null') {
            $host = (string) ($request->server['HTTP_HOST'] ?? '');
            $originHost = (string) parse_url($origin, PHP_URL_HOST);
            $originPort = parse_url($origin, PHP_URL_PORT);
            $originHostPort = $originHost . ($originPort ? ':' . $originPort : '');
            if (strcasecmp($originHostPort, $host) !== 0) {
                return false;
            }
        }
        return true;
    }
}
