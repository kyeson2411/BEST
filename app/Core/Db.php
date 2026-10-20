<?php

declare(strict_types=1);

namespace Sibol\Core;

use PDO;
use PDOStatement;
use RuntimeException;

/**
 * Thin PDO wrapper. Every query that touches input uses prepared statements.
 *
 * Native prepares are on (ATTR_EMULATE_PREPARES = false), so a named placeholder
 * may appear only once per statement: use :q1, :q2 when the same value is needed twice.
 * LIMIT/OFFSET values are cast to int and interpolated by callers.
 */
final class Db
{
    private static ?PDO $pdo = null;
    private static int $depth = 0;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = self::connect((string) Config::get('db.name'));
        }
        return self::$pdo;
    }

    public static function connect(?string $database): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;%scharset=utf8mb4',
            Config::get('db.host'),
            (int) Config::get('db.port'),
            $database ? 'dbname=' . $database . ';' : ''
        );
        return new PDO($dsn, (string) Config::get('db.user'), (string) Config::get('db.password'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+00:00', "
                . "sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'",
        ]);
    }

    /** Used by tests to point at a separate database. */
    public static function setPdo(?PDO $pdo): void
    {
        self::$pdo = $pdo;
        self::$depth = 0;
    }

    /** @param array<string|int,mixed> $params */
    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            $name = is_int($key) ? $key + 1 : (str_starts_with($key, ':') ? $key : ':' . $key);
            $type = PDO::PARAM_STR;
            if (is_int($value)) {
                $type = PDO::PARAM_INT;
            } elseif (is_bool($value)) {
                $type = PDO::PARAM_INT;
                $value = $value ? 1 : 0;
            } elseif ($value === null) {
                $type = PDO::PARAM_NULL;
            }
            $stmt->bindValue($name, $value, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    /** @return list<array<string,mixed>> */
    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** @return mixed */
    public static function value(string $sql, array $params = [])
    {
        $value = self::run($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    /** @return list<mixed> */
    public static function column(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function exec(string $sql, array $params = []): int
    {
        return self::run($sql, $params)->rowCount();
    }

    /** @param array<string,mixed> $row */
    public static function insert(string $table, array $row): int
    {
        self::assertIdentifier($table);
        $cols = array_keys($row);
        array_walk($cols, [self::class, 'assertIdentifier']);
        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $table,
            implode('`, `', $cols),
            implode(', ', array_map(static fn ($c) => ':' . $c, $cols))
        );
        self::run($sql, $row);
        return (int) self::pdo()->lastInsertId();
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $where column => value pairs joined with AND
     */
    public static function update(string $table, array $row, array $where): int
    {
        self::assertIdentifier($table);
        if ($row === [] || $where === []) {
            throw new RuntimeException('Db::update needs values and a WHERE clause.');
        }
        $params = [];
        $set = [];
        foreach ($row as $col => $value) {
            self::assertIdentifier($col);
            $set[] = "`$col` = :set_$col";
            $params["set_$col"] = $value;
        }
        $conds = [];
        foreach ($where as $col => $value) {
            self::assertIdentifier($col);
            $conds[] = "`$col` = :w_$col";
            $params["w_$col"] = $value;
        }
        return self::exec(sprintf('UPDATE `%s` SET %s WHERE %s', $table, implode(', ', $set), implode(' AND ', $conds)), $params);
    }

    /** @param array<string,mixed> $where */
    public static function delete(string $table, array $where): int
    {
        self::assertIdentifier($table);
        if ($where === []) {
            throw new RuntimeException('Db::delete needs a WHERE clause.');
        }
        $conds = [];
        $params = [];
        foreach ($where as $col => $value) {
            self::assertIdentifier($col);
            $conds[] = "`$col` = :w_$col";
            $params["w_$col"] = $value;
        }
        return self::exec(sprintf('DELETE FROM `%s` WHERE %s', $table, implode(' AND ', $conds)), $params);
    }

    /**
     * Runs $fn in a transaction. Nested calls join the outer transaction.
     * @return mixed
     */
    public static function transaction(callable $fn)
    {
        $pdo = self::pdo();
        if (self::$depth > 0) {
            self::$depth++;
            try {
                return $fn();
            } finally {
                self::$depth--;
            }
        }
        $pdo->beginTransaction();
        self::$depth = 1;
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        } finally {
            self::$depth = 0;
        }
    }

    /**
     * Builds an IN (...) list of named placeholders.
     * @param list<int|string> $values
     * @return array{0:string,1:array<string,int|string>}
     */
    public static function in(array $values, string $prefix = 'in'): array
    {
        if ($values === []) {
            return ['(NULL)', []];
        }
        $names = [];
        $params = [];
        foreach (array_values($values) as $i => $value) {
            $names[] = ':' . $prefix . $i;
            $params[$prefix . $i] = $value;
        }
        return ['(' . implode(', ', $names) . ')', $params];
    }

    public static function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    public static function like(string $text): string
    {
        return '%' . addcslashes($text, '%_\\') . '%';
    }

    private static function assertIdentifier(string $name): void
    {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $name)) {
            throw new RuntimeException('Invalid SQL identifier: ' . $name);
        }
    }
}
