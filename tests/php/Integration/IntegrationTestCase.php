<?php

declare(strict_types=1);

namespace Sibol\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Sibol\Core\Auth;
use Sibol\Core\Db;
use Sibol\Services\DemoSeeder;
use Sibol\Services\Migrator;
use Sibol\Services\Settings;

/**
 * Integration tests run against a fresh copy of the fictional demo school in the
 * sibol_test database, built once per test run.
 */
abstract class IntegrationTestCase extends TestCase
{
    private static bool $seeded = false;

    public static function setUpBeforeClass(): void
    {
        if (!self::$seeded) {
            Migrator::ensureDatabase();
            Migrator::dropAll();
            Migrator::clearUploads();
            Migrator::migrate();
            (new DemoSeeder())->run();
            self::$seeded = true;
        }
    }

    protected function tearDown(): void
    {
        Auth::actingAs(null);
        Settings::flush();
    }

    /** A demo account as the app loads it for a signed-in request. */
    protected function person(string $emailLocal): array
    {
        $user = Db::one(
            'SELECT u.*, o.name AS org_name, o.timezone AS org_timezone FROM users u JOIN organizations o ON o.id = u.organization_id WHERE u.email = :e',
            ['e' => $emailLocal . '@demo.sibol.test']
        );
        self::assertNotNull($user, 'Missing demo account ' . $emailLocal);
        return $user;
    }

    protected function classId(string $name): int
    {
        return (int) Db::value('SELECT id FROM classes WHERE name = :n', ['n' => $name]);
    }

    protected function materialId(string $title, string $className): int
    {
        return (int) Db::value(
            'SELECT m.id FROM learning_materials m JOIN classes c ON c.id = m.class_id WHERE m.title = :t AND c.name = :c',
            ['t' => $title, 'c' => $className]
        );
    }
}
