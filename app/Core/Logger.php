<?php

declare(strict_types=1);

namespace Sibol\Core;

/** Append-only file logger. Context must not contain secrets or private content. */
final class Logger
{
    private const LEVELS = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];

    public static function debug(string $message, array $context = []): void
    {
        self::write('debug', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }

    private static function write(string $level, string $message, array $context): void
    {
        $min = self::LEVELS[(string) Config::get('log.level', 'info')] ?? 1;
        if (self::LEVELS[$level] < $min) {
            return;
        }
        $path = (string) Config::get('log.path', BASE_PATH . '/storage/logs/sibol.log');
        $line = sprintf(
            "[%s] %s: %s %s\n",
            gmdate('Y-m-d\TH:i:s\Z'),
            strtoupper($level),
            $message,
            $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) : ''
        );
        @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
    }
}
