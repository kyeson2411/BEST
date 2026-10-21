<?php

declare(strict_types=1);

namespace Sibol\Tests\Integration;

use Sibol\Controllers\Admin\UsageController;
use Sibol\Controllers\CompanionController;
use Sibol\Controllers\Teacher\AssignmentController;
use Sibol\Controllers\Teacher\InsightsController;
use Sibol\Controllers\Teacher\LessonController;
use Sibol\Core\Auth;
use Sibol\Core\Db;
use Sibol\Core\Request;
use Sibol\Services\Ai\AiResult;
use Sibol\Services\Ai\DemoProvider;
use Sibol\Services\Ai\Evaluation\ScriptedProvider;
use Sibol\Services\Ai\Providers;

/**
 * Students and teachers do not see the AI provider or model name next to AI features: only how many requests are
 * left today, and "Demo mode (no AI model)" while Sibol runs without a model. Administrators still see the
 * provider and model on their AI pages.
 */
final class AiLabelTest extends IntegrationTestCase
{
    private const MODEL = 'test-model-x1';

    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        Providers::fake(null);
        $_SESSION = [];
        parent::tearDown();
    }

    public function testStudentsSeeHowManyMessagesAreLeftButNotTheProvider(): void
    {
        $this->liveModel();
        $andrea = $this->person('andrea.cruz');
        Auth::actingAs($andrea);

        $start = (new CompanionController(new Request('GET', '/companion/new')))->create()->body;
        $this->assertNoProvider($start, 'new session');
        self::assertMatchesRegularExpression('/<span class="meta">\d+ messages? left today<\/span>/', $start);

        $chat = Db::insert('ai_conversations', [
            'organization_id' => (int) $andrea['organization_id'], 'user_id' => (int) $andrea['id'],
            'mode' => 'explain', 'title' => 'Test chat', 'language' => 'en',
            'last_message_at' => Db::now(), 'created_at' => Db::now(), 'updated_at' => Db::now(),
        ]);
        try {
            $thread = (new CompanionController(new Request('GET', '/companion/' . $chat)))->show($chat)->body;
            $this->assertNoProvider($thread, 'conversation');
            self::assertMatchesRegularExpression('/<span><span data-remaining>\d+ messages? left today<\/span><\/span>/', $thread, 'companion.js updates this count');
        } finally {
            Db::delete('ai_conversations', ['id' => $chat]);
        }
    }

    public function testTeachersDoNotSeeTheProvider(): void
    {
        $this->liveModel();
        Auth::actingAs($this->person('liza.mercado'));
        // A quiz nobody has taken yet, in a class with a ready material, so its builder offers AI drafts.
        $math = $this->classId('Mathematics 9 – Molave');
        $quiz = (int) Db::value("SELECT id FROM assignments WHERE kind = 'quiz' AND class_id = :c AND id NOT IN (SELECT assignment_id FROM submissions) ORDER BY id LIMIT 1", ['c' => $math]);

        $pages = [
            'quiz builder' => (new AssignmentController(new Request('GET', '/teacher/assignments/' . $quiz . '/edit')))->edit($quiz)->body,
            'new lesson' => (new LessonController(new Request('GET', '/teacher/lessons/new')))->create()->body,
            'class results' => (new InsightsController(new Request('GET', '/teacher/insights/' . $math)))->show($math)->body,
            'companion' => (new CompanionController(new Request('GET', '/companion/new')))->create()->body,
        ];
        self::assertStringContainsString('Draft questions', $pages['quiz builder'], 'the AI panel is on the page');
        foreach ($pages as $page => $html) {
            $this->assertNoProvider($html, $page);
        }
    }

    public function testDemoModeStaysLabelledForStudentsAndTeachers(): void
    {
        Providers::fake(new DemoProvider());
        Auth::actingAs($this->person('andrea.cruz'));
        self::assertMatchesRegularExpression(
            '/<span class="meta">Demo mode \(no AI model\)\. \d+ messages? left today<\/span>/',
            (new CompanionController(new Request('GET', '/companion/new')))->create()->body
        );

        Auth::actingAs($this->person('ramon.dizon'));
        $science = $this->classId('Science 9 – Narra');
        self::assertStringContainsString('Demo mode (no AI model)', (new LessonController(new Request('GET', '/teacher/lessons/new')))->create()->body);
        self::assertStringContainsString('Demo mode (no AI model)', (new InsightsController(new Request('GET', '/teacher/insights/' . $science)))->show($science)->body);
    }

    public function testAdministratorsStillSeeTheProviderAndModel(): void
    {
        $this->liveModel();
        Auth::actingAs($this->person('teresa.villanueva'));
        $html = (new UsageController(new Request('GET', '/admin/usage')))->index()->body;

        self::assertStringContainsString('Configured provider (' . self::MODEL . ')', $html);
    }

    private function liveModel(): void
    {
        Providers::fake(new ScriptedProvider(static function (): AiResult {
            throw new \LogicException('No AI call is expected while a page is shown.');
        }, self::MODEL));
    }

    private function assertNoProvider(string $html, string $where): void
    {
        self::assertStringNotContainsString(self::MODEL, $html, $where);
        self::assertStringNotContainsString('Configured provider', $html, $where);
        self::assertStringNotContainsString('Demo mode (no AI model)', $html, $where);
    }
}
