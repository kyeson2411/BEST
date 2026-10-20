<?php

declare(strict_types=1);

namespace Sibol\Controllers\Admin;

use Sibol\Controllers\Controller;
use Sibol\Core\Dates;
use Sibol\Core\Db;
use Sibol\Core\Response;
use Sibol\Services\Ai\Providers;
use Sibol\Services\Privacy;
use Sibol\Services\Settings;
use Sibol\Services\SystemCheck;

/** School operations at a glance. Learning data appears only in aggregate. */
final class DashboardController extends Controller
{
    public const MIN_GROUP = 5;

    public function index(): Response
    {
        $user = $this->user();
        $o = (int) $user['organization_id'];
        Privacy::purgeDaily($o);

        $roles = [];
        foreach (Db::all('SELECT role, status, COUNT(*) AS n FROM users WHERE organization_id = :o GROUP BY role, status', ['o' => $o]) as $r) {
            $roles[$r['role']][$r['status']] = (int) $r['n'];
        }
        $usage = Db::one(
            "SELECT COUNT(*) AS requests, COALESCE(SUM(input_tokens + output_tokens), 0) AS tokens, COALESCE(SUM(est_cost_usd), 0) AS cost,
                    SUM(status = 'error') AS errors, SUM(status = 'rate_limited') AS limited
             FROM ai_usage_events WHERE organization_id = :o AND created_at >= :m",
            ['o' => $o, 'm' => Dates::startOfMonthUtc()]
        );
        $completion = Db::all(
            "SELECT s.name AS subject,
                    SUM((SELECT COUNT(*) FROM class_memberships m WHERE m.class_id = a.class_id AND m.role = 'student' AND m.status = 'active')) AS expected,
                    SUM((SELECT COUNT(DISTINCT su.student_id) FROM submissions su WHERE su.assignment_id = a.id AND su.status = 'submitted')) AS submitted,
                    COUNT(*) AS tasks
             FROM assignments a JOIN classes c ON c.id = a.class_id JOIN subjects s ON s.id = c.subject_id
             WHERE a.organization_id = :o AND a.status IN ('published','closed') AND a.due_at < UTC_TIMESTAMP() AND a.due_at >= UTC_TIMESTAMP() - INTERVAL 30 DAY
             GROUP BY s.id, s.name ORDER BY s.name",
            ['o' => $o]
        );
        $checks = array_values(array_filter(SystemCheck::run(), static fn ($c) => !$c['ok']));

        return $this->view('admin/dashboard', [
            'title' => t('Overview'),
            'user' => $user,
            'roles' => $roles,
            'classes' => (int) Db::value("SELECT COUNT(*) FROM classes WHERE organization_id = :o AND status = 'active'", ['o' => $o]),
            'materials' => (int) Db::value("SELECT COUNT(*) FROM learning_materials WHERE organization_id = :o AND visibility = 'published'", ['o' => $o]),
            'failed' => Db::all(
                "SELECT m.id, m.title, c.name AS class_name, mv.processing_error FROM learning_materials m JOIN classes c ON c.id = m.class_id
                 JOIN material_versions mv ON mv.id = m.current_version_id WHERE m.organization_id = :o AND mv.processing_status = 'failed' AND m.visibility <> 'archived' LIMIT 5",
                ['o' => $o]
            ),
            'usage' => $usage,
            'budget' => (int) Settings::get($o, 'ai.monthly_token_budget', 0),
            'provider' => Providers::label(),
            'completion' => $completion,
            'pending' => [
                'invited' => (int) Db::value("SELECT COUNT(*) FROM users WHERE organization_id = :o AND status = 'invited'", ['o' => $o]),
                'privacy' => (int) Db::value("SELECT COUNT(*) FROM privacy_requests WHERE organization_id = :o AND status IN ('submitted','in_review')", ['o' => $o]),
                'reports' => (int) Db::value("SELECT COUNT(*) FROM ai_reports WHERE organization_id = :o AND status = 'open'", ['o' => $o]),
            ],
            'checks' => $checks,
            'audit' => Db::all(
                'SELECT a.*, u.given_name, u.family_name, u.preferred_name FROM audit_logs a LEFT JOIN users u ON u.id = a.actor_id
                 WHERE a.organization_id = :o ORDER BY a.created_at DESC LIMIT 8',
                ['o' => $o]
            ),
        ]);
    }
}
