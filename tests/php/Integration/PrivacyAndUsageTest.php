<?php

declare(strict_types=1);

namespace Sibol\Tests\Integration;

use Sibol\Core\Audit;
use Sibol\Core\Auth;
use Sibol\Core\Db;
use Sibol\Core\Uploads;
use Sibol\Services\Ai\Usage;
use Sibol\Services\Privacy;
use Sibol\Services\Settings;

final class PrivacyAndUsageTest extends IntegrationTestCase
{
    public function testExportContainsOnlyThePersonsOwnData(): void
    {
        $nicole = $this->person('nicole.tan');
        $path = Privacy::export((int) $nicole['id']);
        $json = (string) file_get_contents(Uploads::absolute($path));
        $data = json_decode($json, true);

        self::assertSame('nicole.tan', $data['account']['username']);
        self::assertNull($data['account']['email'], 'students here have no email');
        self::assertStringNotContainsString('andrea.cruz', $json);
        self::assertArrayNotHasKey('password_hash', $data['account']);
        foreach ($data['submissions'] as $s) {
            if ($s['feedback_status'] !== 'released') {
                self::assertArrayNotHasKey('score', $s, 'Unreleased grades are not exported early');
            }
        }
        Uploads::delete($path);
    }

    public function testEraseAnonymisesAndKeepsSchoolRecordsUnlessAsked(): void
    {
        $bea = $this->person('bea.ramos');
        $submissions = (int) Db::value('SELECT COUNT(*) FROM submissions WHERE student_id = :u', ['u' => (int) $bea['id']]);
        self::assertGreaterThan(0, $submissions);
        self::assertSame(2, (int) Db::value('SELECT COUNT(*) FROM notice_acknowledgments WHERE user_id = :u', ['u' => (int) $bea['id']]));

        $counts = Privacy::erase((int) $bea['id'], false);

        $after = Db::one('SELECT * FROM users WHERE id = :u', ['u' => (int) $bea['id']]);
        self::assertSame('deactivated', $after['status']);
        self::assertNull($after['password_hash']);
        self::assertNull($after['email']);
        self::assertNull($after['username'], 'the username goes too, so it can be given to someone else');
        self::assertNotNull($after['anonymized_at']);
        self::assertSame(0, (int) Db::value('SELECT COUNT(*) FROM ai_conversations WHERE user_id = :u', ['u' => (int) $bea['id']]));
        self::assertSame(0, (int) Db::value("SELECT COUNT(*) FROM class_memberships WHERE user_id = :u AND status = 'active'", ['u' => (int) $bea['id']]));
        self::assertSame($submissions, (int) Db::value('SELECT COUNT(*) FROM submissions WHERE student_id = :u', ['u' => (int) $bea['id']]));
        self::assertGreaterThanOrEqual(1, $counts['conversations']);
        self::assertSame(0, (int) Db::value('SELECT COUNT(*) FROM notice_acknowledgments WHERE user_id = :u', ['u' => (int) $bea['id']]));
    }

    public function testDailyLimitStopsRequestsAndIsCountedWithoutContent(): void
    {
        $andrea = $this->person('andrea.cruz');
        $org = (int) $andrea['organization_id'];
        $before = Settings::get($org, 'ai', []);
        Settings::set($org, 'ai', array_replace((array) $before, ['student_daily_messages' => 1]));
        Db::insert('ai_usage_events', [
            'organization_id' => $org, 'user_id' => (int) $andrea['id'], 'user_role' => 'student', 'feature' => 'companion',
            'provider' => 'demo', 'is_demo' => 1, 'status' => 'ok', 'created_at' => Db::now(),
        ]);
        try {
            $reason = Usage::blockedReason($andrea, 'companion');
            self::assertNotNull($reason);
            self::assertStringContainsString('limit resets at midnight', $reason);
            $row = Db::one("SELECT * FROM ai_usage_events WHERE user_id = :u AND status = 'rate_limited' ORDER BY id DESC LIMIT 1", ['u' => (int) $andrea['id']]);
            self::assertSame('daily_limit', $row['error_code']);
            self::assertSame(0, (int) $row['input_tokens']);
        } finally {
            Settings::set($org, 'ai', $before);
        }
    }

    public function testAuditNeverStoresSecretsOrContent(): void
    {
        $admin = $this->person('teresa.villanueva');
        Auth::actingAs($admin);
        Audit::log('test.audit_hygiene', null, null, ['password' => 'x', 'api_key' => 'y', 'message_content' => 'private chat', 'count' => 2]);
        $meta = json_decode((string) Db::value("SELECT metadata FROM audit_logs WHERE action = 'test.audit_hygiene' ORDER BY id DESC LIMIT 1"), true);
        self::assertSame(['count' => 2], $meta);
    }

    public function testRetentionPurgeRemovesOnlyExpiredConversations(): void
    {
        $andrea = $this->person('andrea.cruz');
        $org = (int) $andrea['organization_id'];
        $old = Db::insert('ai_conversations', [
            'organization_id' => $org, 'user_id' => (int) $andrea['id'], 'mode' => 'explain', 'title' => 'Old chat', 'language' => 'en',
            'last_message_at' => gmdate('Y-m-d H:i:s', time() - 400 * 86400), 'created_at' => Db::now(), 'updated_at' => Db::now(),
        ]);
        $recent = (int) Db::value('SELECT COUNT(*) FROM ai_conversations WHERE user_id = :u AND id <> :o', ['u' => (int) $andrea['id'], 'o' => $old]);
        Privacy::purgeExpiredConversations();
        self::assertSame(0, (int) Db::value('SELECT COUNT(*) FROM ai_conversations WHERE id = :id', ['id' => $old]));
        self::assertSame($recent, (int) Db::value('SELECT COUNT(*) FROM ai_conversations WHERE user_id = :u', ['u' => (int) $andrea['id']]));
    }
}
