<?php

declare(strict_types=1);

namespace Sibol\Services;

use DateTimeImmutable;
use DateTimeZone;
use Sibol\Core\Db;

/**
 * An opt-in daily email listing the titles of a person's unread notifications. Titles only: never notification
 * bodies, grades or conversation text. Sent by `bin/sibol.php mail:digest`, at most once in 20 hours per person.
 */
final class EmailDigest
{
    public const MAX_TITLES = 30;

    /** Whether this person can turn the digest on: email is set up and they have an address. */
    public static function available(array $user): bool
    {
        return Mailer::enabled() && filter_var((string) ($user['email'] ?? ''), FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Sends the digests that are due.
     * @return array{sent:int,failed:int}
     */
    public static function run(DateTimeImmutable $now): array
    {
        $out = ['sent' => 0, 'failed' => 0];
        if (!Mailer::enabled()) {
            return $out;
        }
        $utc = $now->setTimezone(new DateTimeZone('UTC'));
        $people = Db::all(
            "SELECT u.id, u.organization_id, u.email, u.given_name, u.family_name, u.preferred_name, u.language, u.preferences, o.name AS org_name
             FROM users u JOIN organizations o ON o.id = u.organization_id
             WHERE u.status = 'active' AND u.anonymized_at IS NULL AND u.email IS NOT NULL AND u.email <> '' AND u.preferences LIKE :p",
            ['p' => '%email_digest%']
        );
        foreach ($people as $person) {
            $prefs = json_decode((string) $person['preferences'], true) ?: [];
            if (empty($prefs['email_digest'])) {
                continue;
            }
            $last = isset($prefs['digest_sent_at']) ? new DateTimeImmutable((string) $prefs['digest_sent_at'], new DateTimeZone('UTC')) : null;
            if ($last !== null && $last > $utc->modify('-20 hours')) {
                continue;
            }
            $since = $last ?? $utc->modify('-1 day');
            $titles = Db::column(
                'SELECT title FROM notifications WHERE user_id = :u AND read_at IS NULL AND created_at > :s ORDER BY created_at DESC LIMIT ' . self::MAX_TITLES,
                ['u' => (int) $person['id'], 's' => $since->format('Y-m-d H:i:s')]
            );
            if (!$titles) {
                continue;
            }
            $ok = Mailer::inLanguage((string) ($person['language'] ?? ''), static function () use ($person, $titles): bool {
                $text = t('Hello :name,', ['name' => user_name($person)]) . "\n\n"
                    . tp('You have :count new notification in Sibol:', 'You have :count new notifications in Sibol:', count($titles)) . "\n\n"
                    . implode("\n", array_map(static fn ($t): string => '- ' . $t, $titles)) . "\n\n"
                    . t('Open them in Sibol: :url', ['url' => absolute_url('/notifications')]) . "\n\n"
                    . t('You get this summary because you turned it on in your Sibol account settings. You can turn it off there.');
                return Mailer::send((string) $person['email'], tp(':count new notification in Sibol', ':count new notifications in Sibol', count($titles)), $text);
            });
            if ($ok) {
                $prefs['digest_sent_at'] = $utc->format('Y-m-d H:i:s');
                Db::update('users', ['preferences' => json_encode($prefs)], ['id' => (int) $person['id']]);
                $out['sent']++;
            } else {
                $out['failed']++;
            }
        }
        return $out;
    }
}
