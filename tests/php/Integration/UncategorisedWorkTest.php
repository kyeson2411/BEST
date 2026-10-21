<?php

declare(strict_types=1);

namespace Sibol\Tests\Integration;

use Sibol\Controllers\Teacher\AssignmentController;
use Sibol\Controllers\Teacher\GradebookController;
use Sibol\Core\Auth;
use Sibol\Core\Db;
use Sibol\Core\Request;
use Sibol\Core\Session;
use Sibol\Services\Grades;
use Sibol\Services\Settings;

/**
 * In a school that weighs categories, work without one of the school's categories counts in no grade. The rule
 * stays as it is; the teacher is told on the work's page, when publishing, in the form and in the gradebook.
 */
final class UncategorisedWorkTest extends IntegrationTestCase
{
    private const WEIGHTED = ['model' => 'weighted', 'categories' => [
        ['key' => 'written', 'label' => 'Written work', 'weight' => 40],
        ['key' => 'performance', 'label' => 'Performance tasks', 'weight' => 60],
    ]];

    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    public function testWorkCountsOnlyWithOneOfTheSchoolsCategories(): void
    {
        self::assertTrue(Grades::counts(['category' => 'written'], self::WEIGHTED));
        self::assertFalse(Grades::counts(['category' => null], self::WEIGHTED));
        self::assertFalse(Grades::counts(['category' => ''], self::WEIGHTED));
        self::assertFalse(Grades::counts(['category' => 'periodic'], self::WEIGHTED), 'A category the school no longer has');
        self::assertTrue(Grades::counts(['category' => null], ['model' => 'points', 'categories' => []]), 'Total points count everything');
        self::assertSame('Written work', Grades::categoryLabel('written', self::WEIGHTED));
        self::assertNull(Grades::categoryLabel('', self::WEIGHTED));

        // The same rule the overall grade follows: graded work without a category is left out of it.
        $assignments = [['id' => 1, 'category' => 'written'], ['id' => 2, 'category' => null]];
        $cells = [1 => ['state' => 'graded', 'score' => 8.0, 'possible' => 10.0], 2 => ['state' => 'graded', 'score' => 0.0, 'possible' => 10.0]];
        $overall = Grades::overall($cells, $assignments, self::WEIGHTED);
        self::assertSame(80.0, $overall['percent']);
        self::assertSame(1, $overall['uncategorised']);
    }

    public function testTheTeacherIsToldOnTheWorksPageAndInTheGradebook(): void
    {
        $ramon = $this->person('ramon.dizon');
        Auth::actingAs($ramon);
        $id = (int) Db::value("SELECT a.id FROM assignments a JOIN classes c ON c.id = a.class_id WHERE a.title = 'Heredity basics' AND c.name LIKE '%Narra'");
        $category = (string) Db::value('SELECT category FROM assignments WHERE id = :id', ['id' => $id]);
        self::assertNotSame('', $category);

        $page = (new AssignmentController(new Request('GET', '/teacher/assignments/' . $id)))->show($id)->body;
        self::assertStringNotContainsString('Not counted in the gradebook yet', $page);
        $label = Grades::categoryLabel($category, (array) Settings::get((int) $ramon['organization_id'], 'grading', []));
        self::assertNotNull($label);
        self::assertStringContainsString('<dt>Category</dt><dd>' . e($label) . '</dd>', $page, 'The category by its name, not its key');

        Db::update('assignments', ['category' => null], ['id' => $id]);
        try {
            $page = (new AssignmentController(new Request('GET', '/teacher/assignments/' . $id)))->show($id)->body;
            self::assertStringContainsString('Not counted in the gradebook yet', $page);
            self::assertStringContainsString('/teacher/assignments/' . $id . '/edit#f-category', $page);
            self::assertStringContainsString('<dt>Category</dt><dd>None</dd>', $page);

            $classId = $this->classId('Science 9 – Narra');
            $book = (new GradebookController(new Request('GET', '/teacher/gradebook/' . $classId)))->show($classId)->body;
            self::assertStringContainsString('1 piece of work has no grading category, so its scores count in no grade:', $book);
            self::assertStringContainsString('>Heredity basics</a>', $book);
        } finally {
            Db::update('assignments', ['category' => $category], ['id' => $id]);
        }
        $classId = $this->classId('Science 9 – Narra');
        self::assertStringNotContainsString('no grading category', (new GradebookController(new Request('GET', '/teacher/gradebook/' . $classId)))->show($classId)->body);
    }

    public function testPublishingWorkWithoutACategorySaysItIsNotCounted(): void
    {
        $ramon = $this->person('ramon.dizon');
        Auth::actingAs($ramon);
        $source = (int) Db::value("SELECT a.id FROM assignments a JOIN classes c ON c.id = a.class_id WHERE a.title = 'Concept map: from air to cell' AND c.name LIKE '%Narra'");
        Db::exec(
            "INSERT INTO assignments (organization_id, class_id, module_id, kind, title, instructions, objectives, category, status, opens_at, due_at,
                late_policy, late_penalty_pct, max_attempts, time_limit_minutes, points_possible, allow_text, allow_files, allow_resubmission, rubric,
                feedback_release, answer_key_release, created_by, created_at, updated_at)
             SELECT organization_id, class_id, module_id, kind, 'No category yet', instructions, objectives, NULL, 'draft', NULL, due_at,
                late_policy, late_penalty_pct, max_attempts, time_limit_minutes, points_possible, allow_text, allow_files, allow_resubmission, rubric,
                feedback_release, answer_key_release, created_by, created_at, updated_at
             FROM assignments WHERE id = :s",
            ['s' => $source]
        );
        $id = (int) Db::value("SELECT id FROM assignments WHERE title = 'No category yet'");
        try {
            (new AssignmentController(new Request('POST', '/teacher/assignments/' . $id . '/publish')))->publish($id);
            $flashes = array_column(Session::takeFlashes(), 'message', 'type');
            self::assertStringStartsWith('Published.', $flashes['success'] ?? '');
            self::assertSame('It has no grading category, so its scores are not counted in the gradebook yet. Edit it to choose one.', $flashes['warning'] ?? '');
        } finally {
            Db::exec("DELETE FROM notifications WHERE link = :l", ['l' => '/student/assignments/' . $id]);
            Db::exec('DELETE FROM assignments WHERE id = :id', ['id' => $id]);
        }
    }
}
