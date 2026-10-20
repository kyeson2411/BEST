<?php

declare(strict_types=1);

namespace Sibol\Core;

/** Fixed-window rate limiting stored in MySQL (works without Redis or a cron). */
final class RateLimiter
{
    /** Records a hit and returns true when the caller is still within the limit. */
    public static function attempt(string $bucket, string $subject, int $max, int $windowSeconds): bool
    {
        $window = self::window($windowSeconds);
        $hash = hash('sha256', $subject);
        Db::exec(
            'INSERT INTO rate_limits (bucket, subject_hash, window_start, hits) VALUES (:b, :s, :w, 1)
             ON DUPLICATE KEY UPDATE hits = hits + 1',
            ['b' => $bucket, 's' => $hash, 'w' => $window]
        );
        $hits = (int) Db::value(
            'SELECT hits FROM rate_limits WHERE bucket = :b AND subject_hash = :s AND window_start = :w',
            ['b' => $bucket, 's' => $hash, 'w' => $window]
        );
        if (random_int(1, 50) === 1) {
            Db::exec('DELETE FROM rate_limits WHERE window_start < :old', ['old' => gmdate('Y-m-d H:i:s', time() - 86400)]);
        }
        return $hits <= $max;
    }

    public static function tooMany(string $bucket, string $subject, int $max, int $windowSeconds): bool
    {
        $hits = (int) Db::value(
            'SELECT hits FROM rate_limits WHERE bucket = :b AND subject_hash = :s AND window_start = :w',
            ['b' => $bucket, 's' => hash('sha256', $subject), 'w' => self::window($windowSeconds)]
        );
        return $hits >= $max;
    }

    public static function clear(string $bucket, string $subject): void
    {
        Db::exec('DELETE FROM rate_limits WHERE bucket = :b AND subject_hash = :s', ['b' => $bucket, 's' => hash('sha256', $subject)]);
    }

    /** The subject's stored windows, so they can be put back exactly with restore(). @return list<array<string,mixed>> */
    public static function windows(string $bucket, string $subject): array
    {
        return Db::all('SELECT window_start, hits FROM rate_limits WHERE bucket = :b AND subject_hash = :s', ['b' => $bucket, 's' => hash('sha256', $subject)]);
    }

    /** @param list<array<string,mixed>> $windows as returned by windows() */
    public static function restore(string $bucket, string $subject, array $windows): void
    {
        self::clear($bucket, $subject);
        foreach ($windows as $window) {
            Db::exec(
                'INSERT INTO rate_limits (bucket, subject_hash, window_start, hits) VALUES (:b, :s, :w, :h)',
                ['b' => $bucket, 's' => hash('sha256', $subject), 'w' => (string) $window['window_start'], 'h' => (int) $window['hits']]
            );
        }
    }

    private static function window(int $seconds): string
    {
        $start = intdiv(time(), $seconds) * $seconds;
        return gmdate('Y-m-d H:i:s', $start);
    }
}
