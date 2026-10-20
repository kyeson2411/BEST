<?php

declare(strict_types=1);

namespace Sibol\Core;

use PDOException;

/** HTTP kernel: session, locale, CSRF, routing, error pages and security headers. */
final class App
{
    public function run(): void
    {
        $request = Request::capture();
        View::share('current_path', $request->path);
        try {
            $response = $this->handle($request);
        } catch (\Throwable $e) {
            $response = $this->renderException($e, $request);
        }
        $this->securityHeaders($response);
        $response->send();
    }

    public function handle(Request $request): Response
    {
        if ($request->isPost() && $_POST === [] && $_FILES === [] && (int) ($request->server['CONTENT_LENGTH'] ?? 0) > self::postMaxBytes()) {
            throw new HttpException(413, t('That upload is larger than the server allows (:max MB). Choose a smaller file.', ['max' => (int) round(self::postMaxBytes() / 1048576)]));
        }
        Session::start();
        // Background polls (notification badge) must not keep an unattended session alive.
        Auth::passive($request->header('X-Sibol-Poll') === '1');
        $this->applyLocale($request);
        if ($request->isPost() && !Csrf::verify($request)) {
            throw new HttpException(419, t('This form expired before it was sent. Reload the page and try again.'));
        }
        $router = new Router();
        require BASE_PATH . '/app/routes.php';
        return $router->dispatch($request);
    }

    private function applyLocale(Request $request): void
    {
        $choice = $request->query['lang'] ?? null;
        if (is_string($choice) && array_key_exists($choice, (array) Config::get('app.locales'))) {
            Session::set('guest_locale', $choice);
        }
        $locale = (string) Config::get('app.locale', 'en');
        try {
            $user = Auth::user();
            if ($user) {
                $locale = (string) (($user['language'] ?? null) ?: ($user['org_language'] ?? $locale));
            } elseif (Session::get('guest_locale')) {
                $locale = (string) Session::get('guest_locale');
            }
        } catch (PDOException $e) {
            // Database not ready yet: the setup screen handles it.
        }
        Translator::setLocale($locale);
    }

    private function renderException(\Throwable $e, Request $request): Response
    {
        if ($e instanceof ValidationException) {
            if ($request->wantsJson()) {
                return Response::json(['error' => t('Check the highlighted fields.'), 'errors' => $e->errors], 422);
            }
            Session::withInput($e->input, $e->errors);
            Session::flash('error', t('Check the highlighted fields and try again.'));
            return Response::redirect($e->redirectTo ?? self::backPath($request));
        }

        if ($e instanceof HttpException) {
            if ($request->wantsJson()) {
                return Response::json(['error' => $e->getMessage() ?: self::statusTitle($e->status), 'reason' => $e->status], self::wireStatus($e->status));
            }
            return $this->errorPage($e->status, $e->getMessage());
        }

        $dbCodes = $e instanceof PDOException ? [(int) $e->getCode(), (int) ($e->errorInfo[1] ?? 0)] : [];
        if ($e instanceof PDOException && array_intersect($dbCodes, [1049, 1146, 2002, 1045]) !== []) {
            Logger::error('Database unavailable', ['error' => $e->getMessage()]);
            if (Config::isLocal() && $request->path !== '/setup') {
                return Response::redirect('/setup', 302);
            }
            return $this->errorPage(503, t('Sibol cannot reach its database right now. An administrator needs to check the configuration.'));
        }

        $ref = strtoupper(bin2hex(random_bytes(4)));
        Logger::error('Unhandled exception', [
            'ref' => $ref,
            'type' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile() . ':' . $e->getLine(),
            'path' => $request->path,
        ]);
        if ($request->wantsJson()) {
            return Response::json(['error' => t('Something went wrong on our side. Reference :ref.', ['ref' => $ref])], 500);
        }
        $detail = Config::isLocal() ? get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n\n" . $e->getTraceAsString() : null;
        return $this->errorPage(500, t('Something went wrong on our side. Try again, and if it keeps happening, share reference :ref with your school administrator.', ['ref' => $ref]), $detail);
    }

    private function errorPage(int $status, string $message, ?string $detail = null): Response
    {
        $signedIn = false;
        try {
            $signedIn = session_status() === PHP_SESSION_ACTIVE && Auth::check();
        } catch (\Throwable $e) {
            $signedIn = false;
        }
        try {
            $html = View::render('errors/http', [
                'title' => self::statusTitle($status),
                'status' => $status,
                'message' => $message,
                'detail' => $detail,
            ], $signedIn ? 'layouts/app' : 'layouts/bare');
        } catch (\Throwable $inner) {
            Logger::error('Error page failed', ['error' => $inner->getMessage()]);
            $html = '<!doctype html><meta charset="utf-8"><title>Error</title><p>' . e(self::statusTitle($status)) . '</p>';
        }
        return Response::html($html, self::wireStatus($status));
    }

    /** 419 (expired form) is not a registered HTTP status; Apache would turn it into 500, so 403 is sent. */
    private static function wireStatus(int $status): int
    {
        return $status === 419 ? 403 : $status;
    }

    public static function statusTitle(int $status): string
    {
        switch ($status) {
            case 403:
                return t('You do not have access to this page');
            case 404:
                return t('This page could not be found');
            case 405:
                return t('That action is not available here');
            case 413:
                return t('The upload is too large');
            case 419:
                return t('This form expired');
            case 429:
                return t('Too many attempts');
            case 503:
                return t('Sibol is not available right now');
            default:
                return t('Something went wrong');
        }
    }

    private static function backPath(Request $request): string
    {
        $referer = (string) ($request->server['HTTP_REFERER'] ?? '');
        $host = (string) ($request->server['HTTP_HOST'] ?? '');
        if ($referer !== '' && strcasecmp((string) parse_url($referer, PHP_URL_HOST) . (parse_url($referer, PHP_URL_PORT) ? ':' . parse_url($referer, PHP_URL_PORT) : ''), $host) === 0) {
            $path = (string) parse_url($referer, PHP_URL_PATH);
            $base = base_path_url();
            if ($base !== '' && str_starts_with($path, $base)) {
                $path = substr($path, strlen($base));
            }
            $query = parse_url($referer, PHP_URL_QUERY);
            return ($path ?: '/') . ($query ? '?' . $query : '');
        }
        return home_path();
    }

    private function securityHeaders(Response $response): void
    {
        $defaults = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'same-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Content-Security-Policy' => "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'",
        ];
        foreach ($defaults as $name => $value) {
            if (!isset($response->headers[$name])) {
                $response->headers[$name] = $value;
            }
        }
        if (!isset($response->headers['Cache-Control']) && str_starts_with($response->headers['Content-Type'] ?? '', 'text/html')) {
            $response->headers['Cache-Control'] = 'no-store';
        }
    }

    public static function postMaxBytes(): int
    {
        $value = trim((string) ini_get('post_max_size'));
        $unit = strtolower(substr($value, -1));
        $number = (int) $value;
        switch ($unit) {
            case 'g':
                return $number * 1073741824;
            case 'm':
                return $number * 1048576;
            case 'k':
                return $number * 1024;
            default:
                return (int) $value;
        }
    }
}
