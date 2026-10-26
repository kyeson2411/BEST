<?php

declare(strict_types=1);

namespace Sibol\Tests\Integration;

use Sibol\Controllers\Teacher\LessonController;
use Sibol\Core\Auth;
use Sibol\Core\Db;
use Sibol\Core\Request;

/** Students read the lesson itself; the teacher's plan for class time (activities, checks, exit ticket) stays with the teacher. */
final class LessonStudentViewTest extends IntegrationTestCase
{
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

    public function testOnlyTheStudentSectionsAndSourcesReachStudents(): void
    {
        $content = array_fill_keys(LessonController::SECTIONS, 'text') + ['sources' => [['n' => 1]], 'extra' => 'x'];
        self::assertSame(
            ['objectives', 'prior_knowledge', 'explanation', 'worked_example', 'sources'],
            array_keys(LessonController::forStudents($content))
        );
        self::assertSame(LessonController::SECTIONS, array_merge(LessonController::STUDENT_SECTIONS, LessonController::PLAN_SECTIONS));
    }

    public function testThePreviewShowsTheLessonWithoutTheTeachingPlan(): void
    {
        Auth::actingAs($this->person('ramon.dizon'));
        $id = (int) Db::value("SELECT id FROM lessons WHERE title = 'Gas exchange in the alveoli'");
        $html = (new LessonController(new Request('GET', '/teacher/lessons/' . $id . '/preview')))->preview($id)->body;

        self::assertStringContainsString('Each alveolus has a wall one cell thick', $html);
        self::assertStringContainsString('What you will learn', $html);
        self::assertStringNotContainsString('Pairs sketch an alveolus', $html, 'activities are the teacher\'s');
        self::assertStringNotContainsString('Name two features that make gas exchange fast', $html, 'checks are the teacher\'s');
        self::assertStringNotContainsString('explain why the alveolar wall is only one cell thick', $html, 'the exit ticket is the teacher\'s');

        $editor = (new LessonController(new Request('GET', '/teacher/lessons/' . $id)))->edit($id)->body;
        self::assertStringContainsString('Pairs sketch an alveolus', $editor, 'the teacher still writes and edits the plan');
        self::assertStringContainsString('Only you see this', $editor);
    }

    public function testALessonWithOnlyATeachingPlanCannotBePublished(): void
    {
        $ramon = $this->person('ramon.dizon');
        Auth::actingAs($ramon);
        $content = array_fill_keys(LessonController::SECTIONS, '') + ['sources' => []];
        $content['activities'] = 'Pairs build a balloon lung.';
        $id = Db::insert('lessons', [
            'organization_id' => (int) $ramon['organization_id'], 'class_id' => $this->classId('Science 9 – Narra'), 'title' => 'Plan only',
            'status' => 'draft', 'language' => 'en', 'objectives' => '', 'draft_content' => json_encode($content),
            'created_by' => (int) $ramon['id'], 'created_at' => Db::now(), 'updated_at' => Db::now(),
        ]);
        try {
            $response = (new LessonController(new Request('POST', '/teacher/lessons/' . $id . '/publish')))->publish($id);
            self::assertSame(303, $response->status);
            self::assertSame('draft', Db::value('SELECT status FROM lessons WHERE id = :id', ['id' => $id]), 'students would see an empty lesson');
        } finally {
            Db::delete('lessons', ['id' => $id]);
        }
    }
}
