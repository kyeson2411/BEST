<?php

declare(strict_types=1);

/*
 * Browser-test helper: puts the demo school's privacy notice and rules back on their first edition after a
 * test asked everyone to accept them again, so later tests sign in without the "Before you start" page.
 * Local demo data only.
 *   php tests/e2e/notices-edition.php
 */

define('BASE_PATH', dirname(__DIR__, 2));
require BASE_PATH . '/app/bootstrap.php';

use Sibol\Core\Config;
use Sibol\Core\Db;
use Sibol\Services\Settings;

if (!Config::isLocal() || PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Usage (APP_ENV=local only): php tests/e2e/notices-edition.php\n");
    exit(1);
}
foreach (Db::column('SELECT id FROM organizations WHERE is_demo = 1') as $orgId) {
    $notices = (array) Settings::get((int) $orgId, 'notices', []);
    $notices['edition'] = 1;
    Settings::set((int) $orgId, 'notices', $notices);
}
echo 'ok' . PHP_EOL;
