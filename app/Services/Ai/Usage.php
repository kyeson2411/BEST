<?php

declare(strict_types=1);

namespace Sibol\Services\Ai;

use Sibol\Core\Config;
use Sibol\Core\Dates;
use Sibol\Core\Db;
use Sibol\Core\RateLimiter;
use Sibol\Services\AiAccess;
use Sibol\Services\Settings;

/**
 * Per-user daily limits, a per-school monthly token budget, a short burst limit,
 * and usage accounting (counts and token totals only, never conversation text).
 */
final class Usage
{
    /** Published list prices in USD per million tokens (input, output). Verify against current provider pricing. */
    private const PRICES = [
        'claude-opus-5-5' => [4.00, 20.00],
        'claude-opus-5' => [5.00, 25.00],
        'claude-sonnet-5-5' => [2.00, 10.00],
        'claude-sonnet-5' => [2.00, 10.00],
        'claude-haiku-5-5' => [0.10, 0.50],
        'claude-fable-5-1' => [10.00, 50.00],
    ];

    /** @return string|null a user-facing reason when the request must not run (the refusal is counted, without content) */
    public static function blockedReason(array $user, string $feature): ?string
    {
        $orgId = (int) $user['organization_id'];
        if (!Settings::get($orgId, 'ai.enabled', true)) {
            self::recordRefusal($user, $feature, 'blocked', 'ai_disabled');
            return t('AI tools are turned off at your school.');
        }
        if ($user['role'] === 'student' && !AiAccess::studentOn($user)) {
            // Switched off for this student by an adviser or administrator: nothing goes to a provider.
            self::recordRefusal($user, $feature, 'blocked', 'student_switched_off');
            return t('The companion and AI practice are switched off for your account. Everything else in Sibol works as usual. Ask your adviser if you have questions.');
        }
        if ($user['role'] === 'teacher' && !Settings::get($orgId, 'ai.teacher_tools', true)) {
            self::recordRefusal($user, $feature, 'blocked', 'teacher_tools_disabled');
            return t('AI teaching tools are turned off at your school.');
        }
        if (!RateLimiter::attempt('ai-burst', (string) $user['id'], 8, 60)) {
            self::recordRefusal($user, $feature, 'rate_limited', 'burst');
            return t('That is a lot of requests in one minute. Wait a moment, then try again.');
        }
        $limit = (int) Settings::get($orgId, $user['role'] === 'student' ? 'ai.student_daily_messages' : 'ai.teacher_daily_requests', 40);
        if ($limit > 0 && self::usedToday($user) >= $limit) {
            self::recordRefusal($user, $feature, 'rate_limited', 'daily_limit');
            return $user['role'] === 'student'
                ? t('You have used today\'s :n companion messages. The limit resets at midnight.', ['n' => $limit])
                : t('You have used today\'s :n AI requests. The limit resets at midnight.', ['n' => $limit]);
        }
        $budget = (int) Settings::get($orgId, 'ai.monthly_token_budget', 0);
        if ($budget > 0 && self::tokensThisMonth($orgId) >= $budget) {
            self::recordRefusal($user, $feature, 'rate_limited', 'monthly_budget');
            return t('Your school has used this month\'s AI budget. It resets on the first day of next month.');
        }
        return null;
    }

    /** Requests stopped by a policy or limit are counted so administrators can tune limits. */
    private static function recordRefusal(array $user, string $feature, string $status, string $code): void
    {
        $provider = (string) Config::get('ai.provider', 'demo');
        Db::insert('ai_usage_events', [
            'organization_id' => (int) $user['organization_id'],
            'user_id' => (int) $user['id'],
            'user_role' => (string) $user['role'],
            'feature' => mb_substr($feature, 0, 40),
            'provider' => mb_substr($provider, 0, 40),
            'model' => null,
            'is_demo' => $provider === 'demo' ? 1 : 0,
            'status' => $status,
            'error_code' => $code,
            'created_at' => Db::now(),
        ]);
    }

    public static function usedToday(array $user): int
    {
        return (int) Db::value(
            "SELECT COUNT(*) FROM ai_usage_events WHERE user_id = :u AND created_at >= :since AND status IN ('ok','error','cancelled')",
            ['u' => (int) $user['id'], 'since' => Dates::startOfTodayUtc()]
        );
    }

    public static function remainingToday(array $user): ?int
    {
        $limit = (int) Settings::get((int) $user['organization_id'], $user['role'] === 'student' ? 'ai.student_daily_messages' : 'ai.teacher_daily_requests', 40);
        return $limit > 0 ? max(0, $limit - self::usedToday($user)) : null;
    }

    public static function tokensThisMonth(int $orgId): int
    {
        return (int) Db::value(
            'SELECT COALESCE(SUM(input_tokens + output_tokens), 0) FROM ai_usage_events WHERE organization_id = :o AND created_at >= :since',
            ['o' => $orgId, 'since' => Dates::startOfMonthUtc()]
        );
    }

    public static function record(array $user, string $feature, AiProvider $provider, string $status, int $in = 0, int $out = 0, ?int $latencyMs = null, ?string $errorCode = null, ?string $model = null): void
    {
        $model = $model ?: $provider->model();
        Db::insert('ai_usage_events', [
            'organization_id' => (int) $user['organization_id'],
            'user_id' => (int) $user['id'],
            'user_role' => (string) $user['role'],
            'feature' => mb_substr($feature, 0, 40),
            'provider' => $provider->name(),
            'model' => $model,
            'is_demo' => $provider->isDemo() ? 1 : 0,
            'status' => $status,
            'input_tokens' => $in,
            'output_tokens' => $out,
            'est_cost_usd' => self::estimate($model, $in, $out),
            'latency_ms' => $latencyMs,
            'error_code' => $errorCode,
            'created_at' => Db::now(),
        ]);
    }

    public static function estimate(?string $model, int $in, int $out): float
    {
        $inPrice = Config::get('ai.price_input_per_mtok', '');
        $outPrice = Config::get('ai.price_output_per_mtok', '');
        if (is_numeric($inPrice) && is_numeric($outPrice)) {
            return round(($in * (float) $inPrice + $out * (float) $outPrice) / 1000000, 6);
        }
        if ($model !== null && isset(self::PRICES[$model])) {
            [$pi, $po] = self::PRICES[$model];
            return round(($in * $pi + $out * $po) / 1000000, 6);
        }
        return 0.0;
    }
}
