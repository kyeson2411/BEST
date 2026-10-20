<?php

declare(strict_types=1);

namespace Sibol\Services;

use Sibol\Core\Db;

/** Single-use invitation and password-reset links. Only the SHA-256 of a token is stored. */
final class Tokens
{
    public const INVITE_DAYS = 14;
    public const RESET_HOURS = 48;

    /** Creates a fresh link and revokes older unused links of the same purpose. Returns the raw token. */
    public static function issue(int $userId, string $purpose, ?int $createdBy): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $expires = $purpose === 'invitation'
            ? gmdate('Y-m-d H:i:s', time() + self::INVITE_DAYS * 86400)
            : gmdate('Y-m-d H:i:s', time() + self::RESET_HOURS * 3600);
        Db::transaction(static function () use ($userId, $purpose, $createdBy, $token, $expires): void {
            Db::exec(
                'UPDATE user_tokens SET revoked_at = :now WHERE user_id = :u AND purpose = :p AND used_at IS NULL AND revoked_at IS NULL',
                ['now' => Db::now(), 'u' => $userId, 'p' => $purpose]
            );
            Db::insert('user_tokens', [
                'user_id' => $userId,
                'purpose' => $purpose,
                'token_hash' => hash('sha256', $token),
                'expires_at' => $expires,
                'created_by' => $createdBy,
                'created_at' => Db::now(),
            ]);
        });
        return $token;
    }

    /** @return array<string,mixed>|null the token row joined with its user, if still valid */
    public static function find(string $token, string $purpose): ?array
    {
        if ($token === '' || strlen($token) > 100) {
            return null;
        }
        return Db::one(
            'SELECT t.id AS token_id, t.expires_at, u.*
             FROM user_tokens t JOIN users u ON u.id = t.user_id
             WHERE t.token_hash = :h AND t.purpose = :p AND t.used_at IS NULL AND t.revoked_at IS NULL AND t.expires_at > :now',
            ['h' => hash('sha256', $token), 'p' => $purpose, 'now' => Db::now()]
        );
    }

    public static function consume(int $tokenId): void
    {
        Db::update('user_tokens', ['used_at' => Db::now()], ['id' => $tokenId]);
    }

    public static function link(string $purpose, string $token): string
    {
        return absolute_url(($purpose === 'invitation' ? '/invite/' : '/reset/') . $token);
    }

    /** @return string|null error message when the password is too weak */
    public static function passwordProblem(string $password, string $email = '', string $name = ''): ?string
    {
        if (mb_strlen($password) < 10) {
            return t('Use at least 10 characters.');
        }
        if (mb_strlen($password) > 200) {
            return t('Use 200 characters or fewer.');
        }
        $lower = mb_strtolower($password);
        $common = ['password1234', '1234567890', 'qwertyuiop', 'iloveyou12', 'sibol12345', 'password123', 'abcdefghij', '0123456789', 'qwerty1234', 'letmein123'];
        if (in_array($lower, $common, true) || count(array_unique(str_split($lower))) < 4) {
            return t('That password is too easy to guess. Try a short phrase of a few words.');
        }
        $local = mb_strtolower((string) strtok($email, '@'));
        if ($local !== '' && str_contains($lower, $local) && mb_strlen($local) >= 4) {
            return t('Do not include your email address in your password.');
        }
        if ($name !== '' && mb_strlen($name) >= 4 && str_contains($lower, mb_strtolower($name))) {
            return t('Do not include your name in your password.');
        }
        return null;
    }
}
