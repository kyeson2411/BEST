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
 *                      --size=pilot loads a pilot-size fictional school instead (1,200 students, 60 teachers)
 *   db:reset           Drop all tables, migrate, and seed again (APP_ENV=local only; --size=pilot as above)
 *   schema:build       Regenerate database/schema.sql for phpMyAdmin import
 *   materials:process  Extract and index uploaded materials that are waiting
 *   privacy:purge      Delete AI conversations older than each school's retention period
 *   lang:missing [fil] List interface strings that are not translated yet
 *   school:create      Create a real school and its first administrator (prints a one-time link)
 *   user:link EMAIL    Print a new invitation or password-reset link for an account (email or username)
 *   two-step:off LOGIN Switch off two-step sign-in for an account (email or username), e.g. after a lost phone
 *   key:generate       Write a new APP_KEY into .env (only when it has none; --show prints one instead)
 *   backup             Database and uploaded files in a dated zip in storage/backups, no secrets
 *                      (--to=FOLDER, --keep=14 removes older archives beyond the newest 14)
 *   check              Show configuration problems (database, AI provider, email, folders)
 *   mail:test ADDRESS  Send a test email with the MAIL_ settings in .env
 *   mail:digest        Send the daily notification summaries people turned on (run once a day)
 *   ai:eval            Run the AI check: fixed prompts on the demo school, with a pass/fail table
 *                      and token cost (--demo forces demo mode, --case=A01,B02 runs some, --show prints answers)
 *   ai:retrieval       Measure passage search on the demo school: questions in English, Filipino and Taglish,
 *                      and whether each finds the passage it should (no AI provider is used)
 */

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

use Sibol\Services\Migrator;

$command = $argv[1] ?? 'help';
$seeder = static function () use ($argv) {
    return in_array('--size=pilot', $argv, true) ? new Sibol\Services\PilotSeeder() : new Sibol\Services\DemoSeeder();
};
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
            $out($seeder()->run());
            break;

        case 'db:reset':
            Migrator::ensureDatabase();
            Migrator::dropAll();
            $out('Removed ' . Migrator::clearUploads() . ' stored file(s).');
            Migrator::migrate();
            $out('Tables recreated.');
            $out($seeder()->run());
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

        case 'mail:test':
            // php bin/sibol.php mail:test you@school.edu.ph
            $to = trim($argv[2] ?? '');
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Usage: mail:test ADDRESS');
            }
            if (!Sibol\Services\Mailer::enabled()) {
                throw new RuntimeException('Email is off: set MAIL_HOST and MAIL_FROM_ADDRESS in .env.');
            }
            $ok = Sibol\Services\Mailer::send($to, 'Sibol test email', 'This test email from Sibol arrived, so email is set up correctly.');
            $out($ok ? 'Sent to ' . $to . '. Check that it arrived (and the spam folder).' : 'Not sent. The reason is in storage/logs/sibol.log.');
            break;

        case 'mail:digest':
            // Once a day, from Task Scheduler or cron. Titles only; each person at most once in 20 hours.
            $result = Sibol\Services\EmailDigest::run(new DateTimeImmutable('now'));
            $out(Sibol\Services\Mailer::enabled()
                ? sprintf('Daily summaries: %d sent, %d failed.', $result['sent'], $result['failed'])
                : 'Email is off: set MAIL_HOST and MAIL_FROM_ADDRESS in .env.');
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
            $username = Sibol\Services\Usernames::forPerson($given, $family);
            $token = Sibol\Core\Db::transaction(static function () use ($name, $slug, $tz, $email, $username, $given, $family, $now): string {
                $org = Sibol\Core\Db::insert('organizations', ['name' => $name, 'slug' => $slug, 'timezone' => $tz, 'default_language' => 'en', 'is_demo' => 0, 'created_at' => $now, 'updated_at' => $now]);
                $admin = Sibol\Core\Db::insert('users', ['organization_id' => $org, 'role' => 'admin', 'status' => 'invited', 'email' => $email, 'username' => $username, 'given_name' => $given, 'family_name' => $family, 'created_at' => $now, 'updated_at' => $now]);
                return Sibol\Services\Tokens::issue($admin, 'invitation', null);
            });
            $out('Created "' . $name . '" and an administrator account for ' . $email . ' (username ' . $username . ').');
            $out('Give this one-time invitation link to that person privately (it expires in ' . Sibol\Services\Tokens::INVITE_DAYS . ' days):');
            $out(Sibol\Services\Tokens::link('invitation', $token));
            break;

        case 'user:link':
            // php bin/sibol.php user:link person@school.edu.ph  (or a username; new invitation or password reset link)
            $login = mb_strtolower(trim($argv[2] ?? ''));
            $user = Sibol\Core\Db::one('SELECT id, status FROM users WHERE ' . (str_contains($login, '@') ? 'email' : 'username') . ' = :l', ['l' => $login]);
            if (!$user || $user['status'] === 'deactivated') {
                throw new RuntimeException('No active or invited account has that email or username.');
            }
            $purpose = $user['status'] === 'invited' ? 'invitation' : 'password_reset';
            $link = Sibol\Services\Tokens::link($purpose, Sibol\Services\Tokens::issue((int) $user['id'], $purpose, null));
            $out(($purpose === 'invitation' ? 'Invitation' : 'Password reset') . ' link (one-time, earlier links stop working):');
            $out($link);
            break;

        case 'two-step:off':
            // php bin/sibol.php two-step:off person@school.edu.ph  (or a username)
            $login = mb_strtolower(trim($argv[2] ?? ''));
            $user = Sibol\Core\Db::one('SELECT * FROM users WHERE ' . (str_contains($login, '@') ? 'email' : 'username') . ' = :l', ['l' => $login]);
            if (!$user || !Sibol\Services\TwoStep::enabled($user)) {
                throw new RuntimeException('No account with that email or username uses two-step sign-in.');
            }
            Sibol\Services\TwoStep::disable($user, null);
            $out('Two-step sign-in is off for ' . $login . '. Their password alone signs them in until they set it up again; they have been told.');
            break;

        case 'key:generate':
            // php bin/sibol.php key:generate [--show]
            $key = Sibol\Core\AppKey::generate();
            if (in_array('--show', $argv, true)) {
                $out($key);
                break;
            }
            if (Sibol\Core\AppKey::available()) {
                throw new RuntimeException('APP_KEY is already set. A new one would make the two-step sign-in secrets unreadable, so it is left as it is.');
            }
            $envFile = BASE_PATH . '/.env';
            if (!is_file($envFile)) {
                throw new RuntimeException('There is no .env file. Copy .env.example to .env first, or set APP_KEY in the server environment (key:generate --show prints one).');
            }
            $text = (string) file_get_contents($envFile);
            $eol = str_contains($text, "\r\n") ? "\r\n" : "\n";
            $text = preg_match('/^APP_KEY=.*$/m', $text)
                ? (string) preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=' . $key, $text, 1)
                : rtrim($text) . $eol . $eol . '# Seals two-step sign-in secrets. Keep a copy apart from database backups.' . $eol . 'APP_KEY=' . $key . $eol;
            file_put_contents($envFile, $text);
            $out('APP_KEY written to .env. Keep a copy of it somewhere safe, apart from database backups: without it,');
            $out('two-step sign-in codes cannot be checked and has to be switched off (two-step:off) and set up again.');
            break;

        case 'backup':
            // php bin/sibol.php backup [--to=FOLDER] [--keep=14]
            $to = null;
            $keep = 14;
            foreach (array_slice($argv, 2) as $flag) {
                if (str_starts_with($flag, '--to=')) {
                    $to = substr($flag, 5);
                } elseif (preg_match('/^--keep=(\d+)$/', $flag, $m)) {
                    $keep = max(1, (int) $m[1]);
                } else {
                    throw new RuntimeException('Usage: backup [--to=FOLDER] [--keep=14]');
                }
            }
            $result = Sibol\Services\Backup::create($to, $keep, $out);
            $size = $result['bytes'] >= 1048576 ? number_format($result['bytes'] / 1048576, 1) . ' MB' : max(1, (int) round($result['bytes'] / 1024)) . ' KB';
            $out(sprintf('Backup written: %s (%s, %d tables, %d uploaded files).', $result['path'], $size, $result['tables'], $result['files']));
            foreach ($result['removed'] as $old) {
                $out('Removed an older backup: ' . $old);
            }
            $out('It holds no .env and no APP_KEY: keep those safe separately. See README > Backups and restoring.');
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

        case 'ai:eval':
            // php bin/sibol.php ai:eval [--demo] [--case=A01,B02] [--show]
            $flags = array_slice($argv, 2);
            $only = [];
            foreach ($flags as $flag) {
                if (str_starts_with($flag, '--case=')) {
                    $only = array_values(array_filter(array_map(static fn ($id) => strtoupper(trim($id)), explode(',', substr($flag, 7)))));
                } elseif (!in_array($flag, ['--demo', '--show'], true)) {
                    throw new RuntimeException('Usage: ai:eval [--demo] [--case=A01,B02] [--show]');
                }
            }
            $demo = in_array('--demo', $flags, true);
            $show = in_array('--show', $flags, true);
            $out('AI check on the demo school with ' . ($demo ? 'demo mode (no AI model)' : Sibol\Services\Ai\Providers::label()) . '.');
            $out('Each case\'s conversation and usage are deleted when it finishes; answers are shown only with --show.');
            $out('');
            $line = static fn (string $id, string $result, string $tokens, string $cost, string $time, string $title): string
                => sprintf('%-4s %-6s %19s %9s %7s  %s', $id, $result, $tokens, $cost, $time, $title);
            $out($line('ID', 'Result', 'Tokens in / out', 'Cost US$', 'Time', 'Case'));
            $summary = (new Sibol\Services\Ai\Evaluation\Runner($demo ? new Sibol\Services\Ai\DemoProvider() : null))->run($only, static function (array $r) use ($out, $line, $show): void {
                $out($line($r['id'], $r['passed'] ? 'pass' : 'FAIL', number_format($r['input']) . ' / ' . number_format($r['output']), sprintf('%.4f', $r['cost']), sprintf('%.1fs', $r['seconds']), $r['title']));
                foreach ($r['failures'] as $failure) {
                    $out('     - ' . $failure);
                }
                if ($show && $r['answer'] !== '') {
                    foreach (explode("\n", str_replace("\r", '', $r['answer'])) as $answerLine) {
                        $out('     | ' . $answerLine);
                    }
                }
            });
            $out('');
            foreach (Sibol\Services\Ai\Evaluation\Cases::GROUPS as $group => $label) {
                $rows = array_filter($summary['results'], static fn ($r) => $r['group'] === $group);
                if ($rows) {
                    $out(sprintf('%-46s %d/%d', $label, count(array_filter($rows, static fn ($r) => $r['passed'])), count($rows)));
                }
            }
            $out('');
            $out(sprintf('%d of %d passed.', $summary['passed'], count($summary['results'])) . ' ' . ($summary['demo']
                ? 'Demo mode sends nothing to an AI provider, so there is no token cost.'
                : sprintf('%s input and %s output tokens, about US$%.4f at list prices (%s).', number_format($summary['input']), number_format($summary['output']), $summary['cost'], $summary['model'])));
            if ($summary['failed'] > 0) {
                exit(1);
            }
            break;

        case 'ai:retrieval':
            // php bin/sibol.php ai:retrieval  (on the demo school; Retrieval::search() only, nothing is sent anywhere)
            $results = Sibol\Services\Ai\Evaluation\RetrievalSet::run();
            $line = static fn (string $id, string $lang, string $result, string $question): string => sprintf('%-4s %-9s %-14s %s', $id, $lang, $result, $question);
            $out($line('ID', 'Language', 'Result', 'Question'));
            foreach ($results as $r) {
                $result = $r['lang'] === 'none'
                    ? ($r['passed'] ? 'none found' : 'FOUND ' . $r['returned'])
                    : ($r['rank'] !== null ? 'found, #' . $r['rank'] : 'MISSED');
                $out($line($r['id'], $r['lang'], $result, $r['question']));
            }
            $out('');
            foreach (Sibol\Services\Ai\Evaluation\RetrievalSet::summary($results) as $lang => $s) {
                $out(sprintf('%-22s %2d of %2d as expected, %2d first', Sibol\Services\Ai\Evaluation\RetrievalSet::LANGUAGES[$lang], $s['found'], $s['cases'], $s['first']));
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
