<?php

declare(strict_types=1);

namespace Sibol\Tests\Integration;

use Sibol\Core\Config;
use Sibol\Core\Db;
use Sibol\Services\Backup;

/** Item 15: bin/sibol.php backup writes the database and the uploaded files to a dated zip, with no secrets. */
final class BackupTest extends IntegrationTestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/sibol-backup-test-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    public function testTheArchiveHoldsTheDatabaseAndUploadsButNoSecrets(): void
    {
        $result = Backup::create($this->dir, 14);
        self::assertMatchesRegularExpression('/sibol-backup-\d{4}-\d{2}-\d{2}-\d{6}\.zip$/', $result['path']);
        self::assertSame([basename($result['path'])], array_map('basename', glob($this->dir . '/*') ?: []), 'only the zip is left behind');

        $zip = new \ZipArchive();
        self::assertTrue($zip->open($result['path']) === true);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = (string) $zip->getNameIndex($i);
        }
        foreach (['database.sql', 'backup.json', 'RESTORE.txt'] as $expected) {
            self::assertContains($expected, $names);
        }
        $uploads = array_filter($names, static fn (string $name): bool => str_starts_with($name, 'uploads/'));
        self::assertGreaterThan(0, $result['files'], 'the demo school has uploaded materials');
        self::assertCount($result['files'], $uploads);
        foreach ($names as $name) {
            self::assertStringNotContainsString('.env', $name);
        }

        $sql = (string) $zip->getFromName('database.sql');
        self::assertStringContainsString('CREATE TABLE `users`', $sql);
        self::assertStringContainsString('andrea.cruz', $sql);
        $manifest = json_decode((string) $zip->getFromName('backup.json'), true);
        self::assertSame(['sibol_test', $result['tables'], $result['files']], [$manifest['database'], $manifest['tables'], $manifest['uploaded_files']]);
        self::assertContains('017_two_step.sql', $manifest['migrations']);
        $text = $sql . $zip->getFromName('backup.json') . $zip->getFromName('RESTORE.txt');
        self::assertStringNotContainsString(substr((string) Config::get('app.key'), 7), $text, 'APP_KEY stays out');
        $zip->close();

        self::assertSame(count(Db::all('SHOW TABLES')), $result['tables']);
        $log = Db::one("SELECT metadata FROM audit_logs WHERE action = 'backup.created' ORDER BY id DESC LIMIT 1");
        self::assertSame(['archive', 'tables', 'files', 'bytes'], array_keys(json_decode((string) $log['metadata'], true)));
    }

    public function testOnlyOlderSibolArchivesBeyondTheNumberKeptAreRemoved(): void
    {
        mkdir($this->dir, 0700, true);
        foreach (['2026-01-01-010000', '2026-01-02-010000', '2026-01-03-010000'] as $stamp) {
            touch($this->dir . '/' . Backup::PREFIX . $stamp . '.zip');
        }
        touch($this->dir . '/notes.zip');
        self::assertSame(['sibol-backup-2026-01-01-010000.zip', 'sibol-backup-2026-01-02-010000.zip'], Backup::prune($this->dir, 1));
        self::assertFileExists($this->dir . '/' . Backup::PREFIX . '2026-01-03-010000.zip');
        self::assertFileExists($this->dir . '/notes.zip');
        self::assertSame([], Backup::prune($this->dir, 14));
    }
}
