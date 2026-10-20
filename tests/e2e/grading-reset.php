<?php

declare(strict_types=1);

/*
 * Browser-test helper: puts the demo school's grading back as the seed left it (the K to 12 style template,
 * no grading periods, subjects in no group), after a test loaded a DepEd template. Local demo data only.
 *   php tests/e2e/grading-reset.php
 */

define('BASE_PATH', dirname(__DIR__, 2));
require BASE_PATH . '/app/bootstrap.php';

use Sibol\Core\Config;
use Sibol\Core\Db;
use Sibol\Services\Settings;

if (!Config::isLocal() || PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Usage (APP_ENV=local only): php tests/e2e/grading-reset.php\n");
    exit(1);
}
foreach (Db::column('SELECT id FROM organizations WHERE is_demo = 1') as $orgId) {
    Db::exec('DELETE FROM grading_periods WHERE organization_id = :o', ['o' => (int) $orgId]);
    Db::exec('UPDATE subjects SET grading_group = NULL WHERE organization_id = :o', ['o' => (int) $orgId]);
    Settings::set((int) $orgId, 'grading', Settings::gradingPresets()['k12']['value']);
}
echo 'ok' . PHP_EOL;
