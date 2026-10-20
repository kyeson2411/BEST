<?php

declare(strict_types=1);

namespace Sibol\Services;

use Sibol\Core\Audit;
use Sibol\Core\Db;

/**
 * Feedback about Sibol from students and teachers to the school's administrators: something broken,
 * something confusing or an idea, with the page it was sent from (the path only, never a query string
 * or conversation text). Administrators set a status and can reply once with a short note, which reaches
 * the sender as a notification. It is not a chat.
 */
final class SiteFeedback
{
    public const CATEGORIES = ['problem', 'confusing', 'idea'];
    public const STATUSES = ['new', 'looking', 'fixed', 'wont_fix'];
    public const OPEN = ['new', 'looking'];
    public const MAX_TEXT = 1000;
    public const MAX_REPLY = 500;

    /** Pages whose address is never kept: sign-in links carry one-time codes, the rest say nothing useful. */
    private const UNKEPT = ['/invite', '/reset', '/start', '/login', '/welcome', '/feedback', '/menu'];

    public static function categoryLabel(string $category): string
    {
        return [
            'problem' => t('Something is not working'),
            'confusing' => t('Something is confusing'),
            'idea' => t('An idea'),
        ][$category] ?? $category;
    }

    /** @return array{0:string,1:string} label and badge tone */
    public static function statusBadge(string $status): array
    {
        return [
            'new' => [t('New'), 'amber'],
            'looking' => [t('Looking into it'), 'outline'],
            'fixed' => [t('Fixed'), 'leaf'],
            'wont_fix' => [t('Won\'t fix'), 'neutral'],
        ][$status] ?? [$status, 'neutral'];
    }

    /**
     * The page someone sent feedback from, as a path inside Sibol without its query string, or null when it
     * should not be kept (not a path, odd characters, or a page whose address carries a one-time code).
     */
    public static function cleanPath(?string $from): ?string
    {
        $from = trim((string) $from);
        if ($from === '' || $from[0] !== '/' || str_starts_with($from, '//')) {
            return null;
        }
        $path = (string) strtok($from, '?#');
        if (strlen($path) > 200 || !preg_match('#^/[A-Za-z0-9/_.-]*$#', $path) || str_contains($path, '..')) {
            return null;
        }
        $path = rtrim($path, '/') ?: '/';
        foreach (self::UNKEPT as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return null;
            }
        }
        return $path;
    }

    /** Saves feedback from a student or teacher. The audit log records the category only. */
    public static function send(array $user, string $category, string $text, ?string $path): int
    {
        if (!in_array($category, self::CATEGORIES, true)) {
            throw new \InvalidArgumentException('Unknown feedback category');
        }
        $id = Db::insert('site_feedback', [
            'organization_id' => (int) $user['organization_id'],
            'user_id' => (int) $user['id'],
            'user_role' => (string) $user['role'],
            'category' => $category,
            'body' => mb_substr(trim($text), 0, self::MAX_TEXT),
            'page_path' => self::cleanPath($path),
            'status' => 'new',
            'created_at' => Db::now(),
            'updated_at' => Db::now(),
        ]);
        Audit::log('feedback.sent', 'feedback', $id, ['category' => $category], $user);
        return $id;
    }

    /** @return list<array<string,mixed>> the person's own feedback, newest first */
    public static function mine(int $userId): array
    {
        return Db::all('SELECT * FROM site_feedback WHERE user_id = :u ORDER BY created_at DESC, id DESC LIMIT 50', ['u' => $userId]);
    }

    public static function newCount(int $orgId): int
    {
        return (int) Db::value("SELECT COUNT(*) FROM site_feedback WHERE organization_id = :o AND status = 'new'", ['o' => $orgId]);
    }

    /**
     * Sets the status and the reply. A new or changed reply reaches the sender as a notification (returns
     * true when one was sent); clearing it does not. The audit log records the status and whether there is
     * a new reply, never its text.
     */
    public static function update(array $admin, array $item, string $status, ?string $reply): bool
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException('Unknown feedback status');
        }
        $reply = trim((string) $reply);
        $reply = $reply === '' ? null : mb_substr($reply, 0, self::MAX_REPLY);
        $replyChanged = $reply !== null && $reply !== $item['reply'];
        Db::update('site_feedback', [
            'status' => $status,
            'reply' => $reply,
            'replied_by' => $reply === null ? null : ($replyChanged ? (int) $admin['id'] : $item['replied_by']),
            'replied_at' => $reply === null ? null : ($replyChanged ? Db::now() : $item['replied_at']),
            'updated_at' => Db::now(),
        ], ['id' => (int) $item['id']]);
        Audit::log('feedback.updated', 'feedback', (int) $item['id'], ['status' => $status, 'replied' => $replyChanged], $admin);
        if (!$replyChanged || $item['user_id'] === null) {
            return false;
        }
        return Notifier::send(
            (int) $item['organization_id'],
            [(int) $item['user_id']],
            'site_feedback',
            t('Reply to your feedback'),
            $reply,
            '/feedback#feedback-' . (int) $item['id']
        ) > 0;
    }
}
