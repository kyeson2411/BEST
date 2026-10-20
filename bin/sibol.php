<?php

declare(strict_types=1);

/*
 * Sibol command line.  Run from the project folder:
 *   C:\xampp\php\php.exe bin\sibol.php <command>
 *
 * Commands:
 *   db:create          Create the database named in .env (if missing)
 *   migrate            Apply pending migrations
 *   migrate:status     List applied and pending migrations
 *   db:seed            Load the fictional demo school (only into an empty database)
 *   db:reset           Drop all tables, migrate, and seed again (APP_ENV=local only)
 *   schema:build       Regenerate database/schema.sql for phpMyAdmin import
 *   materials:process  Extract and index uploaded materials that are waiting
 *   privacy:purge      Delete AI conversations older than each school's retention period
 *   lang:missing [fil] List interface strings that are not translated yet
 *   school:create      Create a real school and its first administrator (prints a one-time link)
 *   user:link EMAIL    Print a new invitation or password-reset link for an account
 *   check              Show configuration problems (database, AI provider, folders)
 */

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

use Sibol\Services\Migrator;

$command = $argv[1] ?? 'help';
$out = static function (string $line): void {
    fwrite(STDOUT, $line . PHP_EOL);
};

try {
    switch ($command) {
        case 'db:create':
            Migrator::ensureDatabase();
            $out('Database "' . Sibol\Core\Config::get('db.name') . '" is ready.');
            break;

        case 'migrate':
            Migrator::ensureDatabase();
            $applied = Migrator::migrate();
            $out($applied ? 'Applied: ' . implode(', ', $applied) : 'Nothing to migrate.');
            break;

        case 'migrate:status':
            foreach (Migrator::all() as $file) {
                $out((in_array($file, Migrator::applied(), true) ? '[x] ' : '[ ] ') . $file);
            }
            break;

        case 'db:seed':
            $summary = (new Sibol\Services\DemoSeeder())->run();
            $out($summary);
            break;

        case 'db:reset':
            Migrator::ensureDatabase();
            Migrator::dropAll();
            $out('Removed ' . Migrator::clearUploads() . ' stored file(s).');
            Migrator::migrate();
            $out('Tables recreated.');
            $out((new Sibol\Services\DemoSeeder())->run());
            break;

        case 'schema:build':
            $out('Wrote ' . Migrator::buildSchemaFile());
            break;

        case 'materials:process':
            $results = Sibol\Services\Ingestion::processPending();
            $out($results ? implode(PHP_EOL, $results) : 'No materials are waiting.');
            break;

        case 'privacy:purge':
            $out(Sibol\Services\Privacy::purgeExpiredConversations());
            break;

        case 'school:create':
            // php bin/sibol.php school:create "School name" admin@school.edu.ph Given Family [Asia/Manila]
            [$name, $email, $given, $family] = [trim($argv[2] ?? ''), mb_strtolower(trim($argv[3] ?? '')), trim($argv[4] ?? ''), trim($argv[5] ?? '')];
            $tz = $argv[6] ?? 'Asia/Manila';
            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $given === '' || $family === '' || !in_array($tz, DateTimeZone::listIdentifiers(), true)) {
                throw new RuntimeException('Usage: school:create "School name" admin-email Given Family [timezone]');
            }
            if (Sibol\Core\Db::value('SELECT id FROM users WHERE email = :e', ['e' => $email])) {
                throw new RuntimeException('An account with that email already exists.');
            }
            $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($name)), '-') ?: 'school';
            if (Sibol\Core\Db::value('SELECT id FROM organizations WHERE slug = :s', ['s' => $slug])) {
                $slug .= '-' . bin2hex(random_bytes(2));
            }
            $now = Sibol\Core\Db::now();
            $token = Sibol\Core\Db::transaction(static function () use ($name, $slug, $tz, $email, $given, $family, $now): string {
                $org = Sibol\Core\Db::insert('organizations', ['name' => $name, 'slug' => $slug, 'timezone' => $tz, 'default_language' => 'en', 'is_demo' => 0, 'created_at' => $now, 'updated_at' => $now]);
                $admin = Sibol\Core\Db::insert('users', ['organization_id' => $org, 'role' => 'admin', 'status' => 'invited', 'email' => $email, 'given_name' => $given, 'family_name' => $family, 'created_at' => $now, 'updated_at' => $now]);
                return Sibol\Services\Tokens::issue($admin, 'invitation', null);
            });
            $out('Created "' . $name . '" and an administrator account for ' . $email . '.');
            $out('Give this one-time invitation link to that person privately (it expires in ' . Sibol\Services\Tokens::INVITE_DAYS . ' days):');
            $out(Sibol\Services\Tokens::link('invitation', $token));
            break;

        case 'user:link':
            // php bin/sibol.php user:link person@school.edu.ph  (new invitation or password reset link)
            $email = mb_strtolower(trim($argv[2] ?? ''));
            $user = Sibol\Core\Db::one('SELECT id, status FROM users WHERE email = :e', ['e' => $email]);
            if (!$user || $user['status'] === 'deactivated') {
                throw new RuntimeException('No active or invited account has that email.');
            }
            $purpose = $user['status'] === 'invited' ? 'invitation' : 'password_reset';
            $link = Sibol\Services\Tokens::link($purpose, Sibol\Services\Tokens::issue((int) $user['id'], $purpose, null));
            $out(($purpose === 'invitation' ? 'Invitation' : 'Password reset') . ' link (one-time, earlier links stop working):');
            $out($link);
            break;

        case 'lang:missing':
            $locale = $argv[2] ?? 'fil';
            $source = Sibol\Services\TranslationAudit::sourceStrings();
            $done = Sibol\Services\TranslationAudit::translations($locale);
            $missing = array_diff_key($source, $done);
            $file = BASE_PATH . '/storage/cache/lang-missing-' . preg_replace('/[^a-z]/', '', $locale) . '.php';
            $stub = "<?php\n\n// Untranslated strings for lang/$locale.php. Translate the values, then move the lines across.\n\nreturn [\n";
            foreach ($missing as $text => $files) {
                $stub .= '    // ' . implode(', ', array_slice($files, 0, 3)) . "\n    " . var_export($text, true) . ' => ' . var_export($text, true) . ",\n";
            }
            file_put_contents($file, $stub . "];\n");
            $out(sprintf('%d of %d strings translated into "%s"; %d missing. Stubs written to %s', count($source) - count($missing), count($source), $locale, count($missing), $file));
            break;

        case 'check':
            foreach (Sibol\Services\SystemCheck::run() as $row) {
                $out(sprintf('[%s] %s: %s', $row['ok'] ? ' ok ' : 'warn', $row['label'], $row['detail']));
            }
            break;

        default:
            $out((string) preg_replace('/^.*?\*\n|\*\/.*$/s', '', (string) file_get_contents(__FILE__)));
            $out('Usage: php bin/sibol.php <command>');
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
