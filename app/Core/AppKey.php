<?php

declare(strict_types=1);

namespace Sibol\Core;

/**
 * The application key (APP_KEY in .env, made by bin/sibol.php key:generate): 32 random bytes that seal secrets
 * kept in the database, such as two-step sign-in secrets, so a copy of the database alone (a backup, say) does not
 * reveal them. Keep it with .env, apart from database backups: without it, sealed values cannot be read.
 */
final class AppKey
{
    private const PREFIX = 'base64:';
    private const VERSION = 'v1:';

    public static function available(): bool
    {
        return self::key() !== null;
    }

    /** A new key, written as it goes in .env. */
    public static function generate(): string
    {
        return self::PREFIX . base64_encode(random_bytes(32));
    }

    /** AES-256-GCM: a version mark, then nonce, tag and ciphertext in base64. */
    public static function seal(string $plain): string
    {
        $nonce = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::required(), OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($cipher === false) {
            throw new \RuntimeException('Encryption failed.');
        }
        return self::VERSION . base64_encode($nonce . $tag . $cipher);
    }

    /** The sealed value, or null when it was changed, sealed with another key, or there is no key. */
    public static function open(string $sealed): ?string
    {
        $key = self::key();
        $raw = str_starts_with($sealed, self::VERSION) ? base64_decode(substr($sealed, strlen(self::VERSION)), true) : false;
        if ($key === null || $raw === false || strlen($raw) < 28) {
            return null;
        }
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $plain === false ? null : $plain;
    }

    /** A keyed hash (HMAC-SHA256) for values that are checked but never read back, such as recovery codes. */
    public static function hash(string $value): string
    {
        return hash_hmac('sha256', $value, self::required());
    }

    private static function key(): ?string
    {
        $value = trim((string) Config::get('app.key', ''));
        if (str_starts_with($value, self::PREFIX)) {
            $value = substr($value, strlen(self::PREFIX));
        }
        $bytes = base64_decode($value, true);
        return $bytes !== false && strlen($bytes) === 32 ? $bytes : null;
    }

    private static function required(): string
    {
        $key = self::key();
        if ($key === null) {
            throw new \RuntimeException('APP_KEY is not set. Run: php bin/sibol.php key:generate');
        }
        return $key;
    }
}
