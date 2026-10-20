<?php

declare(strict_types=1);

namespace Sibol\Core;

/**
 * Minimal audit trail: actor, action, target, time, and small metadata.
 * Never pass passwords, tokens, API keys or AI conversation content here.
 */
final class Audit
{
    private const FORBIDDEN_KEYS = ['password', 'token', 'api_key', 'secret', 'content', 'body', 'message'];

    /** @param array<string,mixed> $meta */
    public static function log(string $action, ?string $targetType = null, ?int $targetId = null, array $meta = [], ?array $actor = null): void
    {
        try {
            $actor = $actor ?? Auth::user();
            foreach (array_keys($meta) as $key) {
                foreach (self::FORBIDDEN_KEYS as $bad) {
                    if (stripos((string) $key, $bad) !== false) {
                        unset($meta[$key]);
                    }
                }
            }
            $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
            Db::insert('audit_logs', [
                'organization_id' => $actor ? (int) $actor['organization_id'] : ($meta['organization_id'] ?? null),
                'actor_id' => $actor ? (int) $actor['id'] : null,
                'actor_role' => $actor ? (string) $actor['role'] : null,
                'action' => substr($action, 0, 60),
                'target_type' => $targetType,
                'target_id' => $targetId,
                'metadata' => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
                'ip_hash' => $ip !== '' ? hash('sha256', $ip . '|' . (string) Config::get('db.name')) : null,
                'created_at' => Db::now(),
            ]);
        } catch (\Throwable $e) {
            Logger::error('Audit write failed', ['action' => $action, 'error' => $e->getMessage()]);
        }
    }
}
