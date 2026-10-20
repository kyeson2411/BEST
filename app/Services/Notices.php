<?php

declare(strict_types=1);

namespace Sibol\Services;

use Sibol\Core\Audit;
use Sibol\Core\Db;

/**
 * The privacy notice and the rules for using Sibol. People the school's policy names (students by default)
 * read and accept both on first sign-in and again whenever either changes. A notice's version is its wording
 * version in the code plus the school's "edition", which an administrator raises to ask everyone again.
 * Each acknowledgment records who accepted which version and when.
 */
final class Notices
{
    /** Raise when the wording of a notice changes in the code. */
    public const VERSIONS = ['privacy' => '2026.10', 'rules' => '2026.10'];

    /** @var array<int,list<string>> user id => notices already accepted at their current version (this request) */
    private static array $cache = [];

    /** @return array<string,string> notice => current version at this school */
    public static function current(int $orgId): array
    {
        $edition = max(1, (int) Settings::get($orgId, 'notices.edition', 1));
        $out = [];
        foreach (self::VERSIONS as $notice => $version) {
            $out[$notice] = $version . '-' . $edition;
        }
        return $out;
    }

    /** Whether the school's policy asks this person to accept the notices. */
    public static function required(array $user): bool
    {
        $orgId = (int) $user['organization_id'];
        return $user['role'] === 'student'
            ? (bool) Settings::get($orgId, 'notices.students', true)
            : (bool) Settings::get($orgId, 'notices.staff', false);
    }

    /** @return list<string> notices the person still has to accept, in reading order */
    public static function pending(array $user): array
    {
        if (!self::required($user)) {
            return [];
        }
        $id = (int) $user['id'];
        if (!isset(self::$cache[$id])) {
            self::$cache[$id] = [];
            foreach (Db::all('SELECT notice, version FROM notice_acknowledgments WHERE user_id = :u', ['u' => $id]) as $row) {
                self::$cache[$id][] = $row['notice'] . ':' . $row['version'];
            }
        }
        $pending = [];
        foreach (self::current((int) $user['organization_id']) as $notice => $version) {
            if (!in_array($notice . ':' . $version, self::$cache[$id], true)) {
                $pending[] = $notice;
            }
        }
        return $pending;
    }

    /** Whether the person accepted any version before (so a pending notice means the school asked again). */
    public static function acceptedBefore(array $user): bool
    {
        return (bool) Db::value('SELECT 1 FROM notice_acknowledgments WHERE user_id = :u LIMIT 1', ['u' => (int) $user['id']]);
    }

    /** Records that the person accepted these notices at their current versions. @param list<string> $notices */
    public static function acknowledge(array $user, array $notices): void
    {
        $current = self::current((int) $user['organization_id']);
        $saved = [];
        foreach ($notices as $notice) {
            if (!isset($current[$notice])) {
                continue;
            }
            Db::exec(
                'INSERT IGNORE INTO notice_acknowledgments (organization_id, user_id, notice, version, acknowledged_at) VALUES (:o, :u, :n, :v, :t)',
                ['o' => (int) $user['organization_id'], 'u' => (int) $user['id'], 'n' => $notice, 'v' => $current[$notice], 't' => Db::now()]
            );
            $saved[$notice] = $current[$notice];
        }
        unset(self::$cache[(int) $user['id']]);
        if ($saved) {
            Audit::log('notices.acknowledged', 'user', (int) $user['id'], ['versions' => $saved], $user);
        }
    }

    /** Asks everyone the policy names to read and accept both notices again. Returns the new edition. */
    public static function askAgain(array $admin): int
    {
        $orgId = (int) $admin['organization_id'];
        $notices = (array) Settings::get($orgId, 'notices', []);
        $notices['edition'] = max(1, (int) ($notices['edition'] ?? 1)) + 1;
        Settings::set($orgId, 'notices', $notices, (int) $admin['id']);
        self::$cache = [];
        Audit::log('notices.renewed', 'organization', $orgId, ['edition' => $notices['edition']], $admin);
        return (int) $notices['edition'];
    }

    /**
     * How many of the people who must accept have accepted the current versions (counts only).
     * @return array{required:int,accepted:int}
     */
    public static function progress(int $orgId): array
    {
        $roles = [];
        if (Settings::get($orgId, 'notices.students', true)) {
            $roles[] = 'student';
        }
        if (Settings::get($orgId, 'notices.staff', false)) {
            $roles = array_merge($roles, ['teacher', 'admin']);
        }
        if (!$roles) {
            return ['required' => 0, 'accepted' => 0];
        }
        $current = self::current($orgId);
        [$in, $params] = Db::in($roles, 'r');
        $row = Db::one(
            "SELECT COUNT(*) AS required,
                    SUM((SELECT COUNT(*) FROM notice_acknowledgments n WHERE n.user_id = u.id AND n.notice = 'privacy' AND n.version = :vp) > 0
                        AND (SELECT COUNT(*) FROM notice_acknowledgments n2 WHERE n2.user_id = u.id AND n2.notice = 'rules' AND n2.version = :vr) > 0) AS accepted
             FROM users u WHERE u.organization_id = :o AND u.status = 'active' AND u.role IN $in",
            $params + ['o' => $orgId, 'vp' => $current['privacy'], 'vr' => $current['rules']]
        );
        return ['required' => (int) ($row['required'] ?? 0), 'accepted' => (int) ($row['accepted'] ?? 0)];
    }

    /** Forgets what this request has read (tests, and after a policy change). */
    public static function flush(): void
    {
        self::$cache = [];
    }
}
