<?php

declare(strict_types=1);

namespace Sibol\Services;

use Sibol\Core\Audit;
use Sibol\Core\Config;
use Sibol\Core\Db;

/**
 * bin/sibol.php backup: the database (dumped by mysqldump) and the uploaded files, in one dated zip with a short
 * note on restoring it. No secrets go in: not .env, not APP_KEY; the database password reaches mysqldump in a
 * temporary option file that only this user can read, never on its command line. Older archives beyond $keep
 * are removed.
 */
final class Backup
{
    public const PREFIX = 'sibol-backup-';

    /**
     * @param callable(string):void|null $say progress lines for the command line
     * @return array{path: string, bytes: int, tables: int, files: int, removed: list<string>}
     */
    public static function create(?string $dir = null, int $keep = 14, ?callable $say = null): array
    {
        $say = $say ?? static function (string $line): void {
        };
        $dir = rtrim($dir ?? BASE_PATH . '/storage/backups', '/\\');
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create the folder ' . $dir);
        }
        $local = new \DateTimeImmutable('now', new \DateTimeZone((string) Config::get('app.timezone', 'Asia/Manila')));
        $name = self::PREFIX . $local->format('Y-m-d-His');
        $sql = $dir . '/' . $name . '.sql';
        $zipPath = $dir . '/' . $name . '.zip';

        $say('Dumping the database "' . Config::get('db.name') . '" with ' . self::dumpBinary() . ' …');
        self::dump($sql);
        $tables = count(Db::all('SHOW TABLES'));

        $say('Adding the uploaded files …');
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::EXCL) !== true) {
            @unlink($sql);
            throw new \RuntimeException('Cannot create ' . $zipPath);
        }
        $zip->addFile($sql, 'database.sql');
        $files = 0;
        $uploads = rtrim((string) Config::get('uploads.path'), '/\\');
        if (is_dir($uploads)) {
            $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($uploads, \FilesystemIterator::SKIP_DOTS));
            foreach ($items as $file) {
                /** @var \SplFileInfo $file */
                if ($file->isFile() && !in_array($file->getFilename(), ['.gitkeep', '.htaccess'], true)) {
                    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($uploads) + 1));
                    $zip->addFile($file->getPathname(), 'uploads/' . $relative);
                    $files++;
                }
            }
        }
        $zip->addFromString('backup.json', (string) json_encode([
            'made_at' => $local->format(DATE_ATOM),
            'database' => Config::get('db.name'),
            'server' => Db::value('SELECT VERSION()'),
            'tables' => $tables,
            'uploaded_files' => $files,
            'migrations' => array_values(Migrator::applied()),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->addFromString('RESTORE.txt', self::restoreNote());
        $zip->close();
        @unlink($sql);

        $removed = self::prune($dir, $keep);
        $bytes = (int) filesize($zipPath);
        Audit::log('backup.created', null, null, ['archive' => basename($zipPath), 'tables' => $tables, 'files' => $files, 'bytes' => $bytes]);
        return ['path' => $zipPath, 'bytes' => $bytes, 'tables' => $tables, 'files' => $files, 'removed' => $removed];
    }

    /** The mysqldump (or mariadb-dump) to use: DB_DUMP_PATH, XAMPP's, or one on the PATH. */
    public static function dumpBinary(): string
    {
        $configured = trim((string) Config::get('db.dump_path', ''));
        if ($configured !== '') {
            if (!is_file($configured)) {
                throw new \RuntimeException('DB_DUMP_PATH does not point to a file: ' . $configured);
            }
            return $configured;
        }
        if (PHP_OS_FAMILY === 'Windows') {
            foreach ([dirname(PHP_BINARY, 2) . '\\mysql\\bin\\mysqldump.exe', 'C:\\xampp\\mysql\\bin\\mysqldump.exe'] as $path) {
                if (is_file($path)) {
                    return $path;
                }
            }
        }
        foreach (['mariadb-dump', 'mysqldump'] as $command) {
            if (self::runs([$command, '--version'])) {
                return $command;
            }
        }
        throw new \RuntimeException('mysqldump was not found. Set DB_DUMP_PATH in .env to its full path.');
    }

    /** @return list<string> archives removed, oldest first */
    public static function prune(string $dir, int $keep): array
    {
        $archives = glob($dir . '/' . self::PREFIX . '*.zip') ?: [];
        sort($archives);
        $removed = [];
        foreach (array_slice($archives, 0, max(0, count($archives) - max(1, $keep))) as $old) {
            if (@unlink($old)) {
                $removed[] = basename($old);
            }
        }
        return $removed;
    }

    private static function dump(string $target): void
    {
        $options = tempnam(sys_get_temp_dir(), 'sibol-dump-');
        if ($options === false) {
            throw new \RuntimeException('Cannot create a temporary file for mysqldump.');
        }
        try {
            @chmod($options, 0600);
            $quote = static fn ($v): string => '"' . addcslashes((string) $v, "\\\"") . '"';
            file_put_contents($options, "[client]\n"
                . 'host=' . $quote(Config::get('db.host')) . "\n"
                . 'port=' . (int) Config::get('db.port') . "\n"
                . 'user=' . $quote(Config::get('db.user')) . "\n"
                . 'password=' . $quote(Config::get('db.password')) . "\n");
            $command = [self::dumpBinary(), '--defaults-extra-file=' . $options, '--single-transaction', '--quick', '--no-tablespaces',
                '--default-character-set=utf8mb4', '--hex-blob', '--result-file=' . $target, (string) Config::get('db.name')];
            $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (!is_resource($process)) {
                throw new \RuntimeException('Cannot start mysqldump.');
            }
            stream_get_contents($pipes[1]);
            $errors = trim((string) stream_get_contents($pipes[2]));
            fclose($pipes[1]);
            fclose($pipes[2]);
            if (proc_close($process) !== 0 || !is_file($target) || filesize($target) === 0) {
                @unlink($target);
                throw new \RuntimeException('mysqldump failed' . ($errors !== '' ? ': ' . $errors : '.'));
            }
        } finally {
            @unlink($options);
        }
    }

    /** @param list<string> $command */
    private static function runs(array $command): bool
    {
        $process = @proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            return false;
        }
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return proc_close($process) === 0;
    }

    private static function restoreNote(): string
    {
        return <<<'TXT'
Sibol backup: database.sql (the whole database) and uploads/ (files people uploaded).
It does not contain .env or APP_KEY. Keep those somewhere safe, apart from these archives:
without the same APP_KEY, two-step sign-in has to be switched off and set up again.

To restore (see README.md, "Backups and restoring"):
1. Install the same or a newer version of Sibol and copy your .env (with APP_KEY) into it.
2. Create an empty database, then load the dump, for example
     C:\xampp\mysql\bin\mysql.exe -u root sibol < database.sql
3. Copy the uploads folder's contents into storage/uploads.
4. Run php bin/sibol.php migrate (for a newer version), then php bin/sibol.php check.
TXT;
    }
}
