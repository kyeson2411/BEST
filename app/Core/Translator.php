<?php

declare(strict_types=1);

namespace Sibol\Core;

/**
 * English strings in the code are the keys; lang/fil.php maps them to Filipino.
 * Anything not yet translated falls back to English, so coverage can grow safely.
 */
final class Translator
{
    private static string $locale = 'en';
    /** @var array<string,string> */
    private static array $lines = [];

    public static function setLocale(string $locale): void
    {
        $available = array_keys((array) Config::get('app.locales', ['en' => 'English']));
        self::$locale = in_array($locale, $available, true) ? $locale : 'en';
        self::$lines = [];
        if (self::$locale !== 'en') {
            $file = BASE_PATH . '/lang/' . self::$locale . '.php';
            if (is_file($file)) {
                $lines = require $file;
                self::$lines = is_array($lines) ? $lines : [];
            }
        }
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    /**
     * Runs $fn in another language (an email to someone, a document in its own language), then puts the current one
     * back. An empty or unknown language keeps the current one.
     * @return mixed what $fn returns
     */
    public static function with(string $locale, callable $fn)
    {
        $current = self::$locale;
        $switch = $locale !== '' && $locale !== $current && isset(((array) Config::get('app.locales', []))[$locale]);
        if ($switch) {
            self::setLocale($locale);
        }
        try {
            return $fn();
        } finally {
            if ($switch) {
                self::setLocale($current);
            }
        }
    }

    /** @param array<string,string|int|float> $params */
    public static function get(string $text, array $params = []): string
    {
        $line = self::$lines[$text] ?? $text;
        if ($params) {
            // Longest keys first so :count does not clobber :count_total.
            uksort($params, static fn ($a, $b) => strlen((string) $b) <=> strlen((string) $a));
            foreach ($params as $key => $value) {
                $line = str_replace(':' . $key, (string) $value, $line);
            }
        }
        return $line;
    }

    /** Picks the singular or plural English source before translating. */
    public static function plural(string $singular, string $plural, int $count, array $params = []): string
    {
        return self::get($count === 1 ? $singular : $plural, ['count' => $count] + $params);
    }
}
