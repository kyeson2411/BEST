<?php

declare(strict_types=1);

namespace Sibol\Services;

use Sibol\Core\Db;

/** Role-aware navigation. Hiding items is for clarity only; routes enforce access. */
final class Navigation
{
    /** @return list<array{label:string,icon:string,url:string,exact?:bool,count?:int,bottom?:bool}> */
    public static function items(array $user): array
    {
        $id = (int) $user['id'];
        switch ($user['role']) {
            case 'student':
                return [
                    ['label' => t('Home'), 'icon' => 'house', 'url' => '/student', 'exact' => true, 'bottom' => true],
                    ['label' => t('Classes'), 'icon' => 'school', 'url' => '/student/classes'],
                    ['label' => t('Assignments'), 'icon' => 'clipboard-list', 'url' => '/student/assignments', 'count' => self::studentDueCount($id), 'bottom' => true],
                    ['label' => t('Companion'), 'icon' => 'messages-square', 'url' => '/companion', 'bottom' => true],
                    ['label' => t('Practice'), 'icon' => 'repeat', 'url' => '/student/learning'],
                    ['label' => t('Progress'), 'icon' => 'chart-line', 'url' => '/student/progress'],
                    ['label' => t('Calendar'), 'icon' => 'calendar-days', 'url' => '/student/calendar', 'bottom' => true],
                ];
            case 'teacher':
                return [
                    ['label' => t('Home'), 'icon' => 'house', 'url' => '/teacher', 'exact' => true, 'bottom' => true],
                    ['label' => t('Classes'), 'icon' => 'school', 'url' => '/teacher/classes', 'bottom' => true],
                    ['label' => t('To review'), 'icon' => 'inbox', 'url' => '/teacher/review', 'count' => self::teacherReviewCount($id), 'bottom' => true],
                    ['label' => t('Assignments'), 'icon' => 'clipboard-list', 'url' => '/teacher/assignments'],
                    ['label' => t('Materials'), 'icon' => 'folder-open', 'url' => '/teacher/materials'],
                    ['label' => t('Lessons'), 'icon' => 'notebook-pen', 'url' => '/teacher/lessons'],
                    ['label' => t('Gradebook'), 'icon' => 'table', 'url' => '/teacher/gradebook'],
                    ['label' => t('Insights'), 'icon' => 'chart-column', 'url' => '/teacher/insights'],
                    ['label' => t('Companion'), 'icon' => 'messages-square', 'url' => '/companion', 'bottom' => true],
                ];
            case 'admin':
                return [
                    ['label' => t('Overview'), 'icon' => 'layout-dashboard', 'url' => '/admin', 'exact' => true, 'bottom' => true],
                    ['label' => t('People'), 'icon' => 'users', 'url' => '/admin/users', 'bottom' => true],
                    ['label' => t('Classes'), 'icon' => 'school', 'url' => '/admin/classes', 'bottom' => true],
                    ['label' => t('Subjects and terms'), 'icon' => 'layers', 'url' => '/admin/subjects', 'also' => ['/admin/academic-periods']],
                    ['label' => t('AI usage'), 'icon' => 'gauge', 'url' => '/admin/usage'],
                    ['label' => t('Policies'), 'icon' => 'sliders-horizontal', 'url' => '/admin/policies'],
                    ['label' => t('Privacy and safety'), 'short' => t('Privacy'), 'icon' => 'shield-check', 'url' => '/admin/privacy', 'count' => self::adminOpenItems((int) $user['organization_id']), 'bottom' => true],
                    ['label' => t('Audit log'), 'icon' => 'scroll-text', 'url' => '/admin/audit'],
                    ['label' => t('School settings'), 'icon' => 'settings', 'url' => '/admin/settings', 'also' => ['/admin/integrations']],
                ];
        }
        return [];
    }

    /** @return list<array{label:string,icon:string,url:string}> */
    public static function footer(): array
    {
        return [
            ['label' => t('Account'), 'icon' => 'user', 'url' => '/settings'],
            ['label' => t('Help'), 'icon' => 'circle-help', 'url' => '/help'],
        ];
    }

    public static function isActive(array $item): bool
    {
        if (!empty($item['exact'])) {
            return path_is($item['url'], true);
        }
        if (path_is($item['url'])) {
            return true;
        }
        foreach ($item['also'] ?? [] as $alt) {
            if (path_is($alt)) {
                return true;
            }
        }
        return false;
    }

    public static function unreadNotifications(int $userId): int
    {
        return (int) Db::value('SELECT COUNT(*) FROM notifications WHERE user_id = :u AND read_at IS NULL', ['u' => $userId]);
    }

    private static function studentDueCount(int $studentId): int
    {
        return (int) Db::value(
            "SELECT COUNT(*) FROM assignments a
             JOIN class_memberships cm ON cm.class_id = a.class_id AND cm.user_id = :u AND cm.role = 'student' AND cm.status = 'active'
             JOIN classes c ON c.id = a.class_id AND c.status = 'active'
             WHERE a.status = 'published' AND (a.opens_at IS NULL OR a.opens_at <= UTC_TIMESTAMP())
               AND a.due_at IS NOT NULL AND a.due_at BETWEEN UTC_TIMESTAMP() AND UTC_TIMESTAMP() + INTERVAL 7 DAY
               AND NOT EXISTS (SELECT 1 FROM submissions s WHERE s.assignment_id = a.id AND s.student_id = :u2 AND s.status = 'submitted')",
            ['u' => $studentId, 'u2' => $studentId]
        );
    }

    private static function teacherReviewCount(int $teacherId): int
    {
        return (int) Db::value(
            "SELECT COUNT(*) FROM submissions s
             JOIN assignments a ON a.id = s.assignment_id
             JOIN class_memberships cm ON cm.class_id = a.class_id AND cm.user_id = :u AND cm.role = 'teacher' AND cm.status = 'active'
             WHERE s.status = 'submitted' AND s.grading_status <> 'graded'",
            ['u' => $teacherId]
        );
    }

    private static function adminOpenItems(int $orgId): int
    {
        return (int) Db::value(
            "SELECT (SELECT COUNT(*) FROM privacy_requests WHERE organization_id = :o1 AND status IN ('submitted','in_review'))
                  + (SELECT COUNT(*) FROM ai_reports WHERE organization_id = :o2 AND status = 'open')",
            ['o1' => $orgId, 'o2' => $orgId]
        );
    }
}
