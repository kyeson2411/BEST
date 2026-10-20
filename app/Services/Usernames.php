<?php

declare(strict_types=1);

namespace Sibol\Services;

use Sibol\Core\Db;

/**
 * Usernames that Sibol issues itself, so people without an email address can sign in. They are made
 * from the name ("andrea.cruz", then "andrea.cruz2" when taken) and are unique across the database,
 * so the sign-in page needs no school picker. Administrators can change them.
 */
final class Usernames
{
    public const MAX = 40;
    private const PATTERN = '/^[a-z0-9][a-z0-9._-]{1,38}[a-z0-9]$/';
    private const FOLD = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ñ' => 'n', 'ç' => 'c', 'ý' => 'y', 'ÿ' => 'y',
    ];

    /** Lower case, as typed on the sign-in page or entered by an administrator. */
    public static function clean(string $username): string
    {
        return mb_strtolower(trim($username));
    }

    /** 3 to 40 characters: letters, digits, dots, hyphens and underscores, starting and ending with a letter or digit. */
    public static function valid(string $username): bool
    {
        return (bool) preg_match(self::PATTERN, $username);
    }

    /** "Ma. Teresa" "Dela Cruz" → "materesa.delacruz"; "Andrea" "Cruz" → "andrea.cruz". */
    public static function base(string $given, string $family): string
    {
        $given = self::words($given);
        $first = $given[0] ?? '';
        if (strlen($first) < 3 && isset($given[1])) {
            $first .= $given[1];
        }
        $last = implode('', self::words($family));
        $base = trim($first . '.' . $last, '.');
        if (strlen($base) < 3) {
            $base = 'user' . $base;
        }
        return rtrim(substr($base, 0, 32), '.');
    }

    /**
     * The first free username built from $base: the base itself, then base2, base3 and so on.
     * $reserved holds names already handed out in the same batch (an import) and is updated.
     * @param array<string,bool> $reserved
     */
    public static function unique(string $base, array &$reserved = []): string
    {
        $taken = $reserved;
        $pattern = addcslashes($base, '%_\\') . '%';
        foreach (Db::column('SELECT username FROM users WHERE username LIKE :p', ['p' => $pattern]) as $name) {
            $taken[(string) $name] = true;
        }
        $candidate = $base;
        for ($n = 2; isset($taken[$candidate]); $n++) {
            $candidate = $base . $n;
        }
        $reserved[$candidate] = true;
        return $candidate;
    }

    /** @param array<string,bool> $reserved */
    public static function forPerson(string $given, string $family, array &$reserved = []): string
    {
        return self::unique(self::base($given, $family), $reserved);
    }

    public static function available(string $username, ?int $exceptUserId = null): bool
    {
        $id = Db::value('SELECT id FROM users WHERE username = :u', ['u' => $username]);
        return !$id || ($exceptUserId !== null && (int) $id === $exceptUserId);
    }

    /** @return list<string> ASCII words of a name, without accents, apostrophes or dots */
    private static function words(string $name): array
    {
        $name = strtr(mb_strtolower(trim($name)), self::FOLD);
        $name = (string) preg_replace('/[^a-z0-9\s.-]/', '', $name);
        return array_values(array_filter(preg_split('/[\s.-]+/', $name) ?: [], static fn ($w) => $w !== ''));
    }
}
