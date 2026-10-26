<?php

declare(strict_types=1);

namespace Sibol\Services;

use Sibol\Core\AppKey;
use Sibol\Core\Config;
use Sibol\Core\Db;

/** Configuration health checks shared by `bin/sibol.php check`, the setup page and the admin integrations page. */
final class SystemCheck
{
    /** @return list<array{label:string,ok:bool,detail:string}> */
    public static function run(): array
    {
        $rows = [];
        $rows[] = [
            'label' => 'PHP version',
            'ok' => version_compare(PHP_VERSION, '8.2.0', '>='),
            'detail' => version_compare(PHP_VERSION, '8.2.0', '>=')
                ? PHP_VERSION
                : PHP_VERSION . ' works, but PHP 8.0/8.1 no longer receive security fixes. Use PHP 8.2+ (a newer XAMPP) before real student data.',
        ];
        foreach (['pdo_mysql' => 'database access', 'mbstring' => 'multilingual text', 'fileinfo' => 'upload type checks', 'curl' => 'live AI providers', 'zip' => 'DOCX and PPTX materials', 'openssl' => 'HTTPS calls'] as $ext => $why) {
            $rows[] = ['label' => 'PHP extension ' . $ext, 'ok' => extension_loaded($ext), 'detail' => extension_loaded($ext) ? 'Loaded' : 'Missing, needed for ' . $why];
        }
        $rows[] = [
            'label' => 'PDF text extraction',
            'ok' => class_exists(\Smalot\PdfParser\Parser::class),
            'detail' => class_exists(\Smalot\PdfParser\Parser::class) ? 'smalot/pdfparser installed' : 'Run composer install to read PDF materials',
        ];
        try {
            Db::pdo();
            $pending = Migrator::pending();
            $rows[] = ['label' => 'Database', 'ok' => $pending === [], 'detail' => $pending === [] ? 'Connected to "' . Config::get('db.name') . '", schema up to date' : count($pending) . ' migration(s) waiting'];
            $packet = (int) Db::value('SELECT @@max_allowed_packet');
            $rows[] = ['label' => 'MySQL max_allowed_packet', 'ok' => $packet >= 4194304, 'detail' => round($packet / 1048576, 1) . ' MB' . ($packet < 4194304 ? ' (works; 16 MB recommended in my.ini)' : '')];
        } catch (\Throwable $e) {
            $rows[] = ['label' => 'Database', 'ok' => false, 'detail' => 'Not reachable: ' . $e->getMessage()];
        }
        foreach (['storage/uploads', 'storage/exports', 'storage/logs', 'storage/cache'] as $dir) {
            $path = BASE_PATH . '/' . $dir;
            $rows[] = ['label' => 'Writable ' . $dir, 'ok' => is_dir($path) && is_writable($path), 'detail' => is_dir($path) && is_writable($path) ? 'OK' : 'Create the folder and allow the web server to write to it'];
        }
        $provider = (string) Config::get('ai.provider', 'demo');
        $hasKey = (string) Config::get('ai.api_key', '') !== '';
        if ($provider === 'demo') {
            $rows[] = ['label' => 'AI provider', 'ok' => false, 'detail' => 'Demo mode: extractive answers from class materials, no AI model. Set AI_PROVIDER and AI_API_KEY in .env for live AI.'];
        } else {
            $rows[] = ['label' => 'AI provider', 'ok' => $hasKey || $provider === 'openai_compatible', 'detail' => $provider . ($hasKey ? ' configured (key stored server-side)' : ' selected but AI_API_KEY is empty')];
        }
        $local = Config::isLocal();
        $rows[] = ['label' => 'Environment', 'ok' => !$local, 'detail' => $local
            ? 'APP_ENV=local: developer error details ' . (Config::get('app.demo_login') ? 'and demo sign-in are enabled' : 'are shown') . '. Use APP_ENV=production for real use.'
            : 'production'];
        $secure = (bool) Config::get('app.secure_cookie');
        $rows[] = ['label' => 'Secure session cookie', 'ok' => $secure, 'detail' => $secure ? 'Enabled' : 'Off. Serve the site over HTTPS and set SESSION_SECURE_COOKIE=true.'];
        $dbUser = (string) Config::get('db.user');
        $rows[] = ['label' => 'Database account', 'ok' => $dbUser !== 'root', 'detail' => $dbUser === 'root' ? 'Using root. Create a least-privilege user for any shared deployment (see README).' : 'Dedicated user "' . $dbUser . '"'];
        $mail = Mailer::status();
        $rows[] = $mail['enabled']
            ? ['label' => 'Email delivery', 'ok' => true, 'detail' => 'SMTP ' . $mail['host'] . ':' . $mail['port'] . ' (' . $mail['encryption'] . '), from ' . $mail['from']
                . ($mail['has_password'] ? ', password set (hidden)' : ', no password') . '. Links are still shown to copy. Run "php bin/sibol.php mail:test you@school" to try it.']
            : ['label' => 'Email delivery', 'ok' => false, 'detail' => 'Not configured (MAIL_HOST and MAIL_FROM_ADDRESS in .env). The app shows invitation and password links to administrators to share; it never claims an email was sent.'];
        $rows[] = AppKey::available()
            ? ['label' => 'App key (APP_KEY)', 'ok' => true, 'detail' => 'Set (hidden). Staff can turn on two-step sign-in. Keep a copy apart from database backups.']
            : ['label' => 'App key (APP_KEY)', 'ok' => false, 'detail' => 'Missing, so two-step sign-in cannot be turned on. Run "php bin/sibol.php key:generate".'];
        $google = GoogleSignIn::status();
        $rows[] = $google['configured']
            ? ['label' => 'Google sign-in', 'ok' => true, 'detail' => 'On for ' . implode(', ', $google['domains']) . ' (client secret hidden). Redirect URI: ' . $google['redirect_uri']]
            : ['label' => 'Google sign-in', 'ok' => true, 'detail' => 'Off (optional). Set GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET and GOOGLE_WORKSPACE_DOMAINS to offer it (see README).'];
        $backups = glob(BASE_PATH . '/storage/backups/' . Backup::PREFIX . '*.zip') ?: [];
        rsort($backups);
        $latest = $backups[0] ?? null;
        $age = $latest !== null ? (time() - (int) filemtime($latest)) / 86400 : null;
        $rows[] = [
            'label' => 'Backups',
            'ok' => $age !== null && $age < 8,
            'detail' => $latest === null
                ? 'None in storage/backups yet. Run "php bin/sibol.php backup" every day (Task Scheduler or cron), and copy the archives to another place.'
                : sprintf('Newest: %s, %.0f day(s) old, %s MB.', basename($latest), floor($age), number_format((int) filesize($latest) / 1048576, 1)),
        ];
        return $rows;
    }
}
