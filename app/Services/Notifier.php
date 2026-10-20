<?php

declare(strict_types=1);

namespace Sibol\Services;

use Sibol\Core\Db;
use Sibol\Core\Logger;

/**
 * In-app notifications. Respects each school's notification policy and each user's
 * muted types. A dedupe key makes reminders idempotent. No email is sent: the app
 * never claims otherwise.
 */
final class Notifier
{
    /** Types a user may mute in their preferences, with labels. */
    public static function types(): array
    {
        return [
            'material' => t('New class materials'),
            'assignment' => t('New assignments and quizzes'),
            'reminder' => t('Deadline reminders'),
            'feedback' => t('Released grades and feedback'),
            'announcement' => t('Class announcements'),
            'submission' => t('New work to review (teachers)'),
            'processing' => t('Material processing problems (teachers)'),
            'privacy' => t('Privacy request updates'),
            'class' => t('Class membership changes'),
        ];
    }

    /**
     * @param list<int> $userIds
     */
    public static function send(int $orgId, array $userIds, string $type, string $title, ?string $body = null, ?string $link = null, ?string $dedupeKey = null): int
    {
        $policyKey = ['material' => 'new_material', 'assignment' => 'new_assignment', 'feedback' => 'feedback_released', 'announcement' => 'announcements'][$type] ?? null;
        if ($policyKey !== null && !Settings::get($orgId, 'notifications.' . $policyKey, true)) {
            return 0;
        }
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if ($userIds === []) {
            return 0;
        }
        [$in, $params] = Db::in($userIds, 'u');
        $rows = Db::all("SELECT id, preferences FROM users WHERE id IN $in AND status = 'active'", $params);
        $sent = 0;
        foreach ($rows as $row) {
            $prefs = json_decode((string) ($row['preferences'] ?? ''), true) ?: [];
            if (in_array($type, (array) ($prefs['muted_notifications'] ?? []), true)) {
                continue;
            }
            try {
                Db::exec(
                    'INSERT IGNORE INTO notifications (organization_id, user_id, type, title, body, link, dedupe_key, created_at)
                     VALUES (:o, :u, :t, :ti, :b, :l, :d, :c)',
                    [
                        'o' => $orgId, 'u' => (int) $row['id'], 't' => $type,
                        'ti' => mb_substr($title, 0, 200), 'b' => $body !== null ? mb_substr($body, 0, 500) : null,
                        'l' => $link, 'd' => $dedupeKey, 'c' => Db::now(),
                    ]
                );
                $sent++;
            } catch (\Throwable $e) {
                Logger::warning('Notification insert failed', ['type' => $type, 'error' => $e->getMessage()]);
            }
        }
        return $sent;
    }

    /** Active students of a class. @return list<int> */
    public static function classStudents(int $classId): array
    {
        return array_map('intval', Db::column(
            "SELECT user_id FROM class_memberships WHERE class_id = :c AND role = 'student' AND status = 'active'",
            ['c' => $classId]
        ));
    }

    /** Active teachers of a class. @return list<int> */
    public static function classTeachers(int $classId): array
    {
        return array_map('intval', Db::column(
            "SELECT user_id FROM class_memberships WHERE class_id = :c AND role = 'teacher' AND status = 'active'",
            ['c' => $classId]
        ));
    }

    /**
     * Creates due-soon reminders for a student lazily (no cron needed under XAMPP).
     * Each assignment gets at most one reminder per student.
     */
    public static function remindDueSoon(array $student): void
    {
        $orgId = (int) $student['organization_id'];
        $hours = (int) Settings::get($orgId, 'notifications.deadline_reminder_hours', 24);
        if ($hours <= 0) {
            return;
        }
        $rows = Db::all(
            "SELECT a.id, a.title, a.due_at, a.kind FROM assignments a
             JOIN class_memberships cm ON cm.class_id = a.class_id AND cm.user_id = :u AND cm.role = 'student' AND cm.status = 'active'
             WHERE a.status = 'published' AND (a.opens_at IS NULL OR a.opens_at <= UTC_TIMESTAMP())
               AND a.due_at BETWEEN UTC_TIMESTAMP() AND UTC_TIMESTAMP() + INTERVAL " . $hours . " HOUR
               AND NOT EXISTS (SELECT 1 FROM submissions s WHERE s.assignment_id = a.id AND s.student_id = :u2 AND s.status = 'submitted')",
            ['u' => (int) $student['id'], 'u2' => (int) $student['id']]
        );
        foreach ($rows as $row) {
            self::send(
                $orgId,
                [(int) $student['id']],
                'reminder',
                t('Due soon: :title', ['title' => $row['title']]),
                \Sibol\Core\Dates::due((string) $row['due_at'])['label'],
                '/student/assignments/' . (int) $row['id'],
                'due:' . (int) $row['id']
            );
        }
    }
}
