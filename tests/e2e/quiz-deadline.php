<?php

declare(strict_types=1);

/*
 * Browser-test helper: moves the deadline of a student's quiz attempt in progress, so a test can let
 * time run out without waiting. Local demo data only.
 *   php tests/e2e/quiz-deadline.php <email> "<assignment title>" <seconds from now, may be negative>
 */

define('BASE_PATH', dirname(__DIR__, 2));
require BASE_PATH . '/app/bootstrap.php';

use Sibol\Core\Config;
use Sibol\Core\Db;

if (!Config::isLocal() || PHP_SAPI !== 'cli' || $argc < 4) {
    fwrite(STDERR, "Usage (APP_ENV=local only): php tests/e2e/quiz-deadline.php <email> <assignment title> <seconds>\n");
    exit(1);
}
$changed = Db::exec(
    "UPDATE submissions s JOIN users u ON u.id = s.student_id JOIN assignments a ON a.id = s.assignment_id
     SET s.deadline_at = :d WHERE u.email = :e AND u.is_demo = 1 AND a.title = :t AND s.status = 'in_progress'",
    ['d' => gmdate('Y-m-d H:i:s', time() + (int) $argv[3]), 'e' => $argv[1], 't' => $argv[2]]
);
echo $changed . PHP_EOL;
