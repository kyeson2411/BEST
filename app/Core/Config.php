<?php

declare(strict_types=1);

namespace Sibol\Core;

final class Config
{
    /** @var array<string,mixed> */
    private static array $items = [];

    public static function load(string $file): void
    {
        self::$items = require $file;
    }

    /**
     * Dot-notation lookup, e.g. Config::get('db.host').
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        $value = self::$items;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    /** @param mixed $value */
    public static function set(string $key, $value): void
    {
        $ref = &self::$items;
        foreach (explode('.', $key) as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }
        $ref = $value;
    }

    public static function isLocal(): bool
    {
        return self::get('app.env') === 'local';
    }
}
