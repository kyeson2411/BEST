<?php

declare(strict_types=1);

namespace Sibol\Services;

/**
 * Time-based one-time passwords (RFC 6238, with RFC 4226's truncation): HMAC-SHA1, 6 digits and 30-second
 * steps, the codes authenticator apps show. A secret is 20 random bytes, given to the app in base32.
 */
final class Totp
{
    public const DIGITS = 6;
    public const PERIOD = 30;
    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function newSecret(): string
    {
        return random_bytes(20);
    }

    /** The time step a moment falls in. */
    public static function step(int $unixTime): int
    {
        return intdiv($unixTime, self::PERIOD);
    }

    public static function code(string $secret, int $step, int $digits = self::DIGITS): string
    {
        $hash = hash_hmac('sha1', pack('J', $step), $secret, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);
        return str_pad((string) ($value % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }

    /**
     * The step a typed code belongs to, allowing one step either side for a phone's clock, or null. Steps up to
     * $usedStep were already used to sign in and are refused, so an overheard code cannot be used again.
     */
    public static function match(string $secret, string $typed, int $unixTime, ?int $usedStep = null): ?int
    {
        $digits = (string) preg_replace('/\s+/', '', $typed);
        if (strlen($digits) !== self::DIGITS || !ctype_digit($digits)) {
            return null;
        }
        $now = self::step($unixTime);
        foreach ([$now, $now - 1, $now + 1] as $step) {
            if (($usedStep === null || $step > $usedStep) && hash_equals(self::code($secret, $step), $digits)) {
                return $step;
            }
        }
        return null;
    }

    /** RFC 4648 base32 without padding, as authenticator apps expect. */
    public static function base32(string $bytes): string
    {
        if ($bytes === '') {
            return '';
        }
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::BASE32[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }
        return $out;
    }

    /** The otpauth:// address an authenticator app reads from the QR code. */
    public static function uri(string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($account) . '?' . http_build_query([
            'secret' => self::base32($secret),
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
