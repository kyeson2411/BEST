<?php

declare(strict_types=1);

namespace Sibol\Services;

use PDO;
use Sibol\Core\Config;
use Sibol\Core\Db;

/** Applies database/migrations/*.sql in order and records them in schema_migrations. */
final class Migrator
{
    public static function dir(): string
    {
        return BASE_PATH . '/database/migrations';
    }

    /** Creates the configured database if it does not exist yet (XAMPP convenience). */
    public static function ensureDatabase(): void
    {
        $name = (string) Config::get('db.name');
        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            throw new \RuntimeException('DB_NAME may contain only letters, numbers and underscores.');
        }
        $pdo = Db::connect(null);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    public static function databaseExists(): bool
    {
        try {
            Db::pdo();
            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /** @return list<string> migration file names */
    public static function all(): array
    {
        $files = glob(self::dir() . '/*.sql') ?: [];
        sort($files);
        return array_map('basename', $files);
    }

    /** @return list<string> */
    public static function applied(): array
    {
        self::ensureTable();
        return array_map('strval', Db::column('SELECT version FROM schema_migrations ORDER BY version'));
    }

    /** @return list<string> */
    public static function pending(): array
    {
        return array_values(array_diff(self::all(), self::applied()));
    }

    /** @return list<string> applied in this run */
    public static function migrate(): array
    {
        $done = [];
        foreach (self::pending() as $file) {
            foreach (self::statements((string) file_get_contents(self::dir() . '/' . $file)) as $sql) {
                Db::pdo()->exec($sql);
            }
            Db::insert('schema_migrations', ['version' => $file, 'applied_at' => Db::now()]);
            $done[] = $file;
        }
        return $done;
    }

    /** Drops every table in the configured database. Local development only. */
    public static function dropAll(): void
    {
        if (!Config::isLocal()) {
            throw new \RuntimeException('Refusing to drop tables outside APP_ENV=local.');
        }
        $pdo = Db::pdo();
        $tables = $pdo->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'')->fetchAll(PDO::FETCH_NUM);
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tables as $row) {
            $pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '', (string) $row[0]) . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /** Deletes stored uploads and exports (their database rows are gone after a reset). Local development only. */
    public static function clearUploads(): int
    {
        if (!Config::isLocal()) {
            throw new \RuntimeException('Refusing to delete uploads outside APP_ENV=local.');
        }
        $root = rtrim((string) Config::get('uploads.path'), '/\\');
        if ($root === '' || !is_dir($root)) {
            return 0;
        }
        $removed = 0;
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $path = $item->getPathname();
            if (dirname($path) === $root && in_array($item->getFilename(), ['.htaccess', '.gitkeep'], true)) {
                continue;
            }
            if ($item->isDir()) {
                @rmdir($path);
            } elseif (@unlink($path)) {
                $removed++;
            }
        }
        return $removed;
    }

    /** Builds database/schema.sql (all migrations in one file) for phpMyAdmin import. */
    public static function buildSchemaFile(): string
    {
        $out = "-- Sibol database schema, generated from database/migrations by `php bin/sibol.php schema:build`.\n"
            . "-- Import into an empty database (utf8mb4) with phpMyAdmin, or run `php bin/sibol.php migrate`.\n\n"
            . "SET NAMES utf8mb4;\nSET time_zone = '+00:00';\n\n"
            . "CREATE TABLE IF NOT EXISTS schema_migrations (\n  version VARCHAR(100) NOT NULL,\n  applied_at DATETIME NOT NULL,\n  PRIMARY KEY (version)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";
        foreach (self::all() as $file) {
            $out .= "-- ---------------------------------------------------------------- $file\n";
            $out .= trim((string) file_get_contents(self::dir() . '/' . $file)) . "\n\n";
            $out .= "INSERT INTO schema_migrations (version, applied_at) VALUES ('$file', UTC_TIMESTAMP());\n\n";
        }
        $path = BASE_PATH . '/database/schema.sql';
        file_put_contents($path, $out);
        return $path;
    }

    private static function ensureTable(): void
    {
        Db::pdo()->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
               version VARCHAR(100) NOT NULL,
               applied_at DATETIME NOT NULL,
               PRIMARY KEY (version)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /** @return list<string> */
    private static function statements(string $sql): array
    {
        $lines = array_filter(explode("\n", str_replace("\r\n", "\n", $sql)), static fn ($l) => !preg_match('/^\s*--/', $l));
        $parts = preg_split('/;\s*(?:\n|$)/', implode("\n", $lines)) ?: [];
        return array_values(array_filter(array_map('trim', $parts), static fn ($s) => $s !== ''));
    }
}
