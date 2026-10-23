<?php

declare(strict_types=1);

namespace Sibol\Tests\Integration;

use Sibol\Controllers\CompanionController;
use Sibol\Core\Auth;
use Sibol\Core\Db;
use Sibol\Core\HttpException;
use Sibol\Core\Request;
use Sibol\Services\LessonDrafts;

/** A teacher's "Plan a lesson" answer in the companion can be saved as a lesson draft. */
final class LessonDraftsTest extends IntegrationTestCase
{
    private const OUTLINE = "Here is an outline for a 60-minute lesson.\n\n"
        . "### Learning objectives\n- Describe the alveoli [1]\n\n"
        . "**Prior knowledge:** Students know the parts of the lungs.\n\n"
        . "## Explanation\nGas moves across thin walls [1].\n\n"
        . "### Worked example\nOne breath, step by step.\n\n"
        . "### Activities (20 min)\n1. **Balloon lungs:** pairs build a model.\n2. **Gallery walk**\n\n"
        . "### Checks for understanding\n- Why are the walls thin?\n\n"
        . "### Exit ticket\nOne sentence on gas exchange.";

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

    public function testTheOutlinesHeadingsFillTheLessonSections(): void
    {
        $s = LessonDrafts::sections(self::OUTLINE);

        self::assertSame('- Describe the alveoli [1]', $s['objectives']);
        self::assertSame('Students know the parts of the lungs.', $s['prior_knowledge']);
        self::assertSame('Gas moves across thin walls [1].', $s['explanation']);
        self::assertSame('One breath, step by step.', $s['worked_example']);
        self::assertSame("**Activities (20 min)**\n1. **Balloon lungs:** pairs build a model.\n2. **Gallery walk**", $s['activities'],
            'a heading with details is kept, and bold labels it does not know stay in their section');
        self::assertSame('- Why are the walls thin?', $s['checks']);
        self::assertSame('One sentence on gas exchange.', $s['exit_ticket']);
        self::assertStringNotContainsString('Here is an outline', implode("\n", $s), 'the chat opening is left out');
    }

    public function testFilipinoHeadingsAndOutlinesWithoutHeadings(): void
    {
        $s = LessonDrafts::sections("## Layunin\nMailarawan ang alveoli.\n\n## Gawain\nMagtulungan ang magkapares.");
        self::assertSame('Mailarawan ang alveoli.', $s['objectives']);
        self::assertSame('Magtulungan ang magkapares.', $s['activities']);

        $s = LessonDrafts::sections("Start with a question.\n\nThen a short demo.");
        self::assertSame("Start with a question.\n\nThen a short demo.", $s['explanation'], 'nothing is lost');
        self::assertSame('', $s['objectives']);
    }

    public function testATeacherSavesAPlanAsADraftWithItsSources(): void
    {
        $ramon = $this->person('ramon.dizon');
        Auth::actingAs($ramon);
        $science = $this->classId('Science 9 – Narra');
        $material = $this->materialId('Breathing and circulation notes', 'Science 9 – Narra');
        [$chat, $message] = $this->plan($ramon, $science, 'lesson_ideas');
        Db::insert('ai_citations', [
            'message_id' => $message, 'ref_no' => 1, 'material_id' => $material, 'title' => 'Breathing and circulation notes',
            'page_from' => 2, 'page_to' => 2, 'excerpt' => 'The walls are one cell thick.', 'created_at' => Db::now(),
        ]);
        $lesson = null;
        try {
            $response = (new CompanionController(new Request('POST', '/companion/messages/' . $message . '/lesson')))->saveAsLesson($message);
            $lesson = Db::one('SELECT * FROM lessons WHERE created_by = :u ORDER BY id DESC LIMIT 1', ['u' => (int) $ramon['id']]);

            self::assertSame(303, $response->status);
            self::assertSame(url('/teacher/lessons/' . $lesson['id']), $response->headers['Location']);
            self::assertSame('draft', $lesson['status'], 'students see nothing until the teacher publishes');
            self::assertSame($science, (int) $lesson['class_id']);
            self::assertSame('Gas exchange plan', $lesson['title']);
            self::assertSame('1', (string) $lesson['ai_assisted']);
            self::assertSame('- Describe the alveoli [1]', $lesson['objectives']);
            $content = json_decode((string) $lesson['draft_content'], true);
            self::assertSame('One sentence on gas exchange.', $content['exit_ticket']);
            self::assertSame([['n' => 1, 'material_id' => $material, 'title' => 'Breathing and circulation notes', 'where' => 'p. 2']], $content['sources']);
            self::assertSame([$material], json_decode((string) $lesson['source_material_ids'], true));

            $html = (new CompanionController(new Request('GET', '/companion/' . $chat)))->show($chat)->body;
            self::assertStringContainsString('action="' . e(url('/companion/messages/' . $message . '/lesson')) . '"', $html);
        } finally {
            if ($lesson) {
                Db::delete('lessons', ['id' => (int) $lesson['id']]);
            }
            Db::delete('ai_conversations', ['id' => $chat]);
        }
    }

    public function testOnlyTheTeachersOwnPlanningAnswersCanBeSaved(): void
    {
        $ramon = $this->person('ramon.dizon');
        $science = $this->classId('Science 9 – Narra');
        [$examples, $examplesMessage] = $this->plan($ramon, $science, 'examples');
        [$plan, $planMessage] = $this->plan($ramon, $science, 'lesson_ideas');
        try {
            Auth::actingAs($ramon);
            $html = (new CompanionController(new Request('GET', '/companion/' . $examples)))->show($examples)->body;
            self::assertStringNotContainsString('Save as lesson draft', $html, 'other kinds of help have no button');
            $this->assertNotFound($examplesMessage);

            Auth::actingAs($this->person('liza.mercado'));
            $this->assertNotFound($planMessage);
            self::assertSame(0, (int) Db::value('SELECT COUNT(*) FROM lessons WHERE title = :t', ['t' => 'Gas exchange plan']));
        } finally {
            Db::delete('ai_conversations', ['id' => $examples]);
            Db::delete('ai_conversations', ['id' => $plan]);
        }
    }

    /** @return array{0:int,1:int} a conversation and its answer */
    private function plan(array $teacher, int $classId, string $mode): array
    {
        $chat = Db::insert('ai_conversations', [
            'organization_id' => (int) $teacher['organization_id'], 'user_id' => (int) $teacher['id'], 'class_id' => $classId,
            'mode' => $mode, 'title' => 'Gas exchange plan', 'language' => 'en',
            'last_message_at' => Db::now(), 'created_at' => Db::now(), 'updated_at' => Db::now(),
        ]);
        Db::insert('ai_messages', ['conversation_id' => $chat, 'role' => 'user', 'content' => 'Plan a lesson on gas exchange', 'status' => 'complete', 'created_at' => Db::now()]);
        $message = Db::insert('ai_messages', [
            'conversation_id' => $chat, 'role' => 'assistant', 'content' => self::OUTLINE, 'status' => 'complete',
            'grounding' => 'sources', 'is_demo' => 0, 'created_at' => Db::now(),
        ]);
        return [$chat, $message];
    }

    private function assertNotFound(int $message): void
    {
        try {
            (new CompanionController(new Request('POST', '/companion/messages/' . $message . '/lesson')))->saveAsLesson($message);
            self::fail('expected not found');
        } catch (HttpException $e) {
            self::assertSame(404, $e->status);
        }
    }
}
