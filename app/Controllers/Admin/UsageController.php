<?php

declare(strict_types=1);

namespace Sibol\Controllers\Admin;

use DateTimeZone;
use Sibol\Controllers\Controller;
use Sibol\Core\Config;
use Sibol\Core\Dates;
use Sibol\Core\Db;
use Sibol\Core\Response;
use Sibol\Services\Ai\Providers;
use Sibol\Services\Settings;

/** AI usage in aggregate: requests, tokens and estimated cost by feature, role and day. No conversation content. */
final class UsageController extends Controller
{
    public function index(): Response
    {
        $o = $this->orgId();
        $range = in_array($this->request->str('range'), ['30', 'last_month'], true) ? $this->request->str('range') : 'month';
        $now = Dates::now();
        $utc = new DateTimeZone('UTC');
        if ($range === '30') {
            $from = $now->setTime(0, 0)->modify('-29 days');
            $to = $now->setTime(0, 0)->modify('+1 day');
        } elseif ($range === 'last_month') {
            $from = $now->modify('first day of last month')->setTime(0, 0);
            $to = $now->modify('first day of this month')->setTime(0, 0);
        } else {
            $from = $now->modify('first day of this month')->setTime(0, 0);
            $to = $now->modify('first day of next month')->setTime(0, 0);
        }
        $params = ['o' => $o, 'f' => $from->setTimezone($utc)->format('Y-m-d H:i:s'), 't' => $to->setTimezone($utc)->format('Y-m-d H:i:s')];
        $where = ' FROM ai_usage_events WHERE organization_id = :o AND created_at >= :f AND created_at < :t';
        $sum = "COUNT(*) AS requests, SUM(status = 'ok') AS ok, SUM(status = 'error') AS errors, SUM(status IN ('rate_limited','blocked')) AS stopped,
                SUM(status = 'cancelled') AS cancelled, COALESCE(SUM(input_tokens), 0) AS input_tokens, COALESCE(SUM(output_tokens), 0) AS output_tokens,
                COALESCE(SUM(est_cost_usd), 0) AS cost, SUM(is_demo) AS demo";
        $totals = Db::one('SELECT ' . $sum . $where, $params);
        $byFeature = Db::all('SELECT feature, ' . $sum . $where . ' GROUP BY feature ORDER BY requests DESC', $params);
        $byRole = Db::all('SELECT user_role, ' . $sum . $where . ' GROUP BY user_role ORDER BY requests DESC', $params);
        $offset = $now->format('P');
        $daily = [];
        foreach (Db::all("SELECT DATE(CONVERT_TZ(created_at, '+00:00', :tz)) AS day, COUNT(*) AS requests, SUM(status IN ('rate_limited','blocked')) AS stopped" . $where . ' GROUP BY day', $params + ['tz' => $offset]) as $r) {
            $daily[(string) $r['day']] = $r;
        }
        $days = [];
        for ($d = $from; $d < $to && $d <= $now; $d = $d->modify('+1 day')) {
            $key = $d->format('Y-m-d');
            $days[] = ['day' => $key, 'requests' => (int) ($daily[$key]['requests'] ?? 0), 'stopped' => (int) ($daily[$key]['stopped'] ?? 0)];
        }
        $limitHits = (int) Db::value("SELECT COUNT(DISTINCT user_id)" . $where . " AND error_code = 'daily_limit'", $params);

        return $this->view('admin/usage', [
            'title' => t('AI usage'),
            'range' => $range,
            'from' => $from,
            'to' => min($to->modify('-1 day'), $now),
            'totals' => $totals,
            'byFeature' => $byFeature,
            'byRole' => $byRole,
            'days' => $days,
            'limitHits' => $limitHits,
            'monthTokens' => (int) Db::value('SELECT COALESCE(SUM(input_tokens + output_tokens), 0) FROM ai_usage_events WHERE organization_id = :o AND created_at >= :m', ['o' => $o, 'm' => Dates::startOfMonthUtc()]),
            'policy' => (array) Settings::get($o, 'ai', []),
            'provider' => Providers::label(),
            'customPrices' => is_numeric(Config::get('ai.price_input_per_mtok', '')) && is_numeric(Config::get('ai.price_output_per_mtok', '')),
        ]);
    }

    public static function featureLabel(string $feature): string
    {
        $labels = [
            'companion' => t('Learning companion'),
            'practice' => t('Practice sets and flashcards'),
            'quiz_draft' => t('Question drafts for teachers'),
            'lesson_draft' => t('Lesson drafts'),
            'grading_suggestion' => t('Grading suggestions'),
            'insights' => t('Reteaching ideas'),
            'reading_version' => t('Simpler and Filipino versions of materials'),
        ];
        return $labels[$feature] ?? $feature;
    }
}
