<?php

declare(strict_types=1);

namespace Sibol\Tests\Integration;

use Sibol\Controllers\NotificationController;
use Sibol\Core\Auth;
use Sibol\Core\Config;
use Sibol\Core\Db;
use Sibol\Core\Request;
use Sibol\Services\Notifier;

/**
 * The live notification badge: a count-only endpoint, polled as background work that never keeps an
 * unattended session alive.
 */
final class NotificationBadgeTest extends IntegrationTestCase
{
    protected function tearDown(): void
    {
        $_SESSION = [];
        Auth::passive(false);
        Auth::refresh();
        Db::exec("DELETE FROM notifications WHERE dedupe_key = 'badge-test'");
        parent::tearDown();
    }

    public function testTheCountEndpointReturnsTheUnreadCountAndNothingElse(): void
    {
        $andrea = $this->person('andrea.cruz');
        Auth::actingAs($andrea);
        $count = static function (): array {
            $response = (new NotificationController(new Request('GET', '/notifications/count')))->count();
            self::assertSame('application/json; charset=utf-8', $response->headers['Content-Type']);
            self::assertSame('no-store', $response->headers['Cache-Control']);
            return json_decode($response->body, true);
        };
        $before = $count();
        self::assertSame(['count'], array_keys($before));
        Notifier::send((int) $andrea['organization_id'], [(int) $andrea['id']], 'announcement', 'Badge test', null, '/student', 'badge-test');
        self::assertSame(['count' => $before['count'] + 1], $count());
    }

    public function testABackgroundPollDoesNotCountAsActivity(): void
    {
        $andrea = $this->person('andrea.cruz');
        $tenMinutesAgo = time() - 600;
        $this->signIn($andrea, $tenMinutesAgo);
        Auth::passive(true);
        self::assertNotNull(Auth::user(), 'The poll is still answered');
        self::assertSame($tenMinutesAgo, $_SESSION['last_seen'], 'but the idle clock keeps running');

        $this->signIn($andrea, $tenMinutesAgo);
        Auth::passive(false);
        self::assertNotNull(Auth::user());
        self::assertGreaterThanOrEqual(time() - 2, $_SESSION['last_seen'], 'A real page view counts as activity');
    }

    public function testAnUnattendedSessionStillEndsWhilePolling(): void
    {
        $andrea = $this->person('andrea.cruz');
        $this->signIn($andrea, time() - ((int) Config::get('app.idle_minutes', 120) * 60 + 60));
        Auth::passive(true);
        self::assertNull(Auth::user());
        self::assertArrayNotHasKey('uid', $_SESSION);
    }

    private function signIn(array $user, int $lastSeen): void
    {
        $_SESSION = ['uid' => (int) $user['id'], 'sv' => (int) $user['session_version'], 'last_seen' => $lastSeen];
        Auth::refresh();
    }
}
