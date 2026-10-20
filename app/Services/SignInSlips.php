<?php

declare(strict_types=1);

namespace Sibol\Services;

use Sibol\Core\Audit;
use Sibol\Core\Db;
use Sibol\Core\Session;

/**
 * Printed sign-in slips: a person's name, username and a one-time code (also as a QR code of the
 * link) to set up an account or choose a new password. Issuing a slip cancels the person's earlier
 * unused link of the same kind. The codes are kept in the issuer's session for 30 minutes so the
 * page can be printed; nobody, including the issuer, ever sees a password.
 */
final class SignInSlips
{
    public const KEEP_SECONDS = 1800;
    private const SESSION_KEY = 'signin_slips';

    /**
     * Issues a slip for each person: a setup slip for an invited account, a password slip for an
     * active one. Deactivated and erased accounts are skipped. Returns the number of slips.
     * @param list<array<string,mixed>> $people user rows; an optional 'group' heads their part of the page
     */
    public static function issue(array $people, array $actor, string $title, string $back): int
    {
        $slips = [];
        foreach ($people as $person) {
            if ($person['status'] === 'deactivated' || !empty($person['anonymized_at'])) {
                continue;
            }
            $purpose = $person['status'] === 'invited' ? 'invitation' : 'password_reset';
            $code = Tokens::issue((int) $person['id'], $purpose, (int) $actor['id'], true);
            $group = $person['group'] ?? ($person['role'] === 'student' ? Sections::groupFor((int) $person['id']) : t('Staff'));
            $slips[] = self::slip($person, $purpose, $code, (string) $group);
        }
        if (!$slips) {
            return 0;
        }
        $setup = count(array_filter($slips, static fn ($s) => $s['purpose'] === 'invitation'));
        if (count($slips) === 1) {
            Audit::log($setup ? 'user.setup_slip' : 'user.reset_slip', 'user', (int) $people[0]['id'], [], $actor);
        } else {
            Audit::log('user.signin_slips', null, null, ['setup' => $setup, 'reset' => count($slips) - $setup], $actor);
        }
        self::remember($slips, $title, $back);
        return count($slips);
    }

    /** One slip; $code is the raw code from Tokens::issue(). @return array<string,mixed> */
    public static function slip(array $person, string $purpose, string $code, string $group): array
    {
        return [
            'name' => user_name($person),
            'username' => (string) ($person['username'] ?? ''),
            'group' => $group,
            'purpose' => $purpose,
            'code' => Tokens::displayCode($code),
            'url' => Tokens::link($purpose, Tokens::normalise($code)),
            'expires_at' => $purpose === 'invitation'
                ? gmdate('Y-m-d H:i:s', time() + Tokens::INVITE_DAYS * 86400)
                : gmdate('Y-m-d H:i:s', time() + Tokens::RESET_HOURS * 3600),
        ];
    }

    /** @param list<array<string,mixed>> $slips */
    public static function remember(array $slips, string $title, string $back): void
    {
        Session::set(self::SESSION_KEY, ['at' => time(), 'title' => $title, 'back' => $back, 'slips' => $slips]);
    }

    /** The slips issued in this session in the last 30 minutes. @return array<string,mixed>|null */
    public static function current(): ?array
    {
        $data = Session::get(self::SESSION_KEY);
        if (!is_array($data) || time() - (int) ($data['at'] ?? 0) > self::KEEP_SECONDS) {
            return null;
        }
        return $data;
    }

    /**
     * Invited students of these classes (who have not set a password yet), each with their group.
     * @param list<int> $classIds
     * @return list<array<string,mixed>>
     */
    public static function invitedStudents(array $classIds): array
    {
        if (!$classIds) {
            return [];
        }
        [$in, $params] = Db::in($classIds, 'c');
        return Db::all(
            "SELECT DISTINCT u.* FROM class_memberships cm
             JOIN classes c ON c.id = cm.class_id AND c.status = 'active'
             JOIN users u ON u.id = cm.user_id
             WHERE cm.class_id IN $in AND cm.role = 'student' AND cm.status = 'active' AND u.status = 'invited' AND u.anonymized_at IS NULL
             ORDER BY u.family_name, u.given_name",
            $params
        );
    }

    /** A safe place to return to after printing: an app path for the issuer's own area. */
    public static function backPath(string $path, string $role): string
    {
        $prefix = $role === 'admin' ? '/admin' : '/teacher';
        return preg_match('#^' . $prefix . '(/[A-Za-z0-9_-]+)*(\?[A-Za-z0-9=&_-]*)?(\#[a-z0-9-]+)?$#', $path) ? $path : $prefix;
    }
}
