<?php

declare(strict_types=1);

/*
 * Browser-test helper for "who needs a nudge": adds two tasks to Science 9 – Narra that were due two and four days
 * ago and that nobody handed in, so every active student there has two missing. --remove deletes them again, with
 * the reminders the test sent. Local demo data only.
 *   php tests/e2e/nudge-fixture.php [--remove]
 */

define('BASE_PATH', dirname(__DIR__, 2));
require BASE_PATH . '/app/bootstrap.php';

use Sibol\Core\Config;
use Sibol\Core\Db;

if (!Config::isLocal() || PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Usage (APP_ENV=local only): php tests/e2e/nudge-fixture.php [--remove]\n");
    exit(1);
}
$class = Db::one("SELECT c.id, c.organization_id FROM classes c JOIN organizations o ON o.id = c.organization_id AND o.is_demo = 1 WHERE c.name = 'Science 9 – Narra'");
if (in_array('--remove', $argv, true)) {
    Db::exec("DELETE FROM assignments WHERE title LIKE '%(nudge test)'");
    Db::exec('DELETE FROM notifications WHERE dedupe_key LIKE :k', ['k' => 'nudge:' . (int) $class['id'] . ':%']);
    echo 'ok' . PHP_EOL;
    exit(0);
}
$teacher = (int) Db::value("SELECT id FROM users WHERE username = 'ramon.dizon'");
foreach ([2 => 'Worksheet: gas exchange (nudge test)', 4 => 'Exit ticket: the heart (nudge test)'] as $days => $title) {
    Db::insert('assignments', [
        'organization_id' => (int) $class['organization_id'], 'class_id' => (int) $class['id'], 'kind' => 'assignment', 'title' => $title, 'status' => 'published',
        'due_at' => gmdate('Y-m-d H:i:s', time() - $days * 86400), 'points_possible' => 5, 'allow_text' => 1, 'created_by' => $teacher,
        'published_at' => Db::now(), 'created_at' => Db::now(), 'updated_at' => Db::now(),
    ]);
}
echo 'ok' . PHP_EOL;
