<?php

declare(strict_types=1);

/*
 * Browser-test helper for the review queue, so it does not depend on what other tests did with the demo's quizzes.
 * Adds two quizzes to Science 9 – Narra: one Andrea already took and missed, with its answers released, and one due
 * in five days for "review everything before my quiz". Prints the upcoming quiz's id. --remove deletes both.
 * Local demo data only.
 *   php tests/e2e/review-fixture.php [--remove]
 */

define('BASE_PATH', dirname(__DIR__, 2));
require BASE_PATH . '/app/bootstrap.php';

use Sibol\Core\Config;
use Sibol\Core\Db;
use Sibol\Services\Assessments;

if (!Config::isLocal() || PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Usage (APP_ENV=local only): php tests/e2e/review-fixture.php [--remove]\n");
    exit(1);
}
if (in_array('--remove', $argv, true)) {
    Db::exec("DELETE FROM assignments WHERE title LIKE '%(review test)'");
    echo 'ok' . PHP_EOL;
    exit(0);
}
$class = Db::one("SELECT c.id, c.organization_id FROM classes c JOIN organizations o ON o.id = c.organization_id AND o.is_demo = 1 WHERE c.name = 'Science 9 – Narra'");
$teacher = (int) Db::value("SELECT id FROM users WHERE username = 'ramon.dizon'");
$andrea = (int) Db::value("SELECT id FROM users WHERE username = 'andrea.cruz'");

$quiz = static function (string $title, int $dueIn, string $release) use ($class, $teacher): array {
    $id = Db::insert('assignments', [
        'organization_id' => (int) $class['organization_id'], 'class_id' => (int) $class['id'], 'kind' => 'quiz', 'title' => $title, 'status' => 'draft',
        'due_at' => gmdate('Y-m-d H:i:s', time() + $dueIn * 86400), 'points_possible' => 1, 'answer_key_release' => $release, 'created_by' => $teacher,
        'created_at' => Db::now(), 'updated_at' => Db::now(),
    ]);
    [$row] = Assessments::normaliseQuestion(['qtype' => 'true_false', 'prompt' => 'Arteries carry blood away from the heart.', 'correct' => 'true', 'points' => 1]);
    $question = Assessments::addQuestion($id, $row);
    Assessments::recalcPoints($id);
    Assessments::publish($id, $teacher, false);
    return [$id, $question];
};

[$past, $question] = $quiz('Circulation check (review test)', -2, 'after_submit');
$version = (int) Db::value('SELECT current_version_id FROM assignments WHERE id = :a', ['a' => $past]);
$submission = Db::insert('submissions', [
    'organization_id' => (int) $class['organization_id'], 'assignment_id' => $past, 'student_id' => $andrea, 'attempt_no' => 1, 'status' => 'submitted',
    'submitted_at' => gmdate('Y-m-d H:i:s', time() - 3 * 86400), 'assignment_version_id' => $version, 'score' => 0, 'points_possible' => 1, 'grading_status' => 'graded',
]);
Db::insert('submission_answers', ['submission_id' => $submission, 'question_id' => $question, 'response' => 'false', 'is_correct' => 0, 'points_awarded' => 0]);

[$upcoming] = $quiz('Circulation warm-up (review test)', 5, 'after_due');
echo $upcoming . PHP_EOL;
