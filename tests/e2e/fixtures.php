<?php

declare(strict_types=1);

/*
 * Prints the ids the browser tests need from the freshly seeded demo school, as JSON.
 * Run by tests/e2e/global-setup.mjs after `bin/sibol.php db:reset`.
 */

define('BASE_PATH', dirname(__DIR__, 2));
require BASE_PATH . '/app/bootstrap.php';

use Sibol\Core\Db;

$user = static fn (string $username): int => (int) Db::value('SELECT id FROM users WHERE username = :u', ['u' => $username]);
$class = static fn (string $name): int => (int) Db::value('SELECT id FROM classes WHERE name = :n', ['n' => $name]);
$section = static fn (string $name): int => (int) Db::value('SELECT id FROM sections WHERE name = :n', ['n' => $name]);

echo json_encode([
    'users' => [
        'andrea' => $user('andrea.cruz'),
        'bea' => $user('bea.ramos'),
        'carlo' => $user('carlo.reyes'),
        'paolo' => $user('paolo.aquino'),
        'nicole' => $user('nicole.tan'),
        'ramon' => $user('ramon.dizon'),
        'carmela' => $user('carmela.reyes'),
        'admin' => $user('teresa.villanueva'),
        'janelle' => $user('janelle.ocampo'),
        'enzo' => $user('enzo.castillo'),
        'joy' => $user('joy.santiago'),
        'luis' => $user('luis.pascual'),
    ],
    'sections' => [
        'narra' => $section('Narra'),
        'molave' => $section('Molave'),
    ],
    'classes' => [
        'sciNarra' => $class('Science 9 – Narra'),
        'sciMolave' => $class('Science 9 – Molave'),
        'mathNarra' => $class('Mathematics 9 – Narra'),
        'mathMolave' => $class('Mathematics 9 – Molave'),
        'engNarra' => $class('English 9 – Narra'),
    ],
    'beaSubmission' => (int) Db::value('SELECT id FROM submissions WHERE student_id = :u ORDER BY id LIMIT 1', ['u' => $user('bea.ramos')]),
    'beaConversation' => (int) Db::value('SELECT id FROM ai_conversations WHERE user_id = :u ORDER BY id LIMIT 1', ['u' => $user('bea.ramos')]),
    'draftMaterial' => (int) Db::value("SELECT id FROM learning_materials WHERE title = 'Lab safety reminders'"),
    'scheduledMaterial' => (int) Db::value("SELECT id FROM learning_materials WHERE title = 'Module 2 review sheet'"),
    'releasedMaterial' => (int) Db::value("SELECT m.id FROM learning_materials m JOIN classes c ON c.id = m.class_id WHERE m.title = 'Breathing and circulation notes' AND c.name = 'Science 9 – Narra'"),
    'mathAssignment' => (int) Db::value("SELECT a.id FROM assignments a WHERE a.class_id = :c ORDER BY a.id LIMIT 1", ['c' => $class('Mathematics 9 – Narra')]),
    'sciPastQuiz' => (int) Db::value("SELECT a.id FROM assignments a WHERE a.class_id = :c AND a.title = 'Respiratory and circulatory systems check'", ['c' => $class('Science 9 – Narra')]),
    'paperTest' => (int) Db::value("SELECT id FROM assignments WHERE title = 'Short test on paper: breathing and circulation'"),
    'exportRequest' => (int) Db::value("SELECT id FROM privacy_requests WHERE request_type = 'export' ORDER BY id LIMIT 1"),
    'report' => (int) Db::value('SELECT id FROM ai_reports ORDER BY id LIMIT 1'),
], JSON_PRETTY_PRINT) . PHP_EOL;
