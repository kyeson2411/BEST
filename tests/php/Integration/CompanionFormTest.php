<?php

declare(strict_types=1);

namespace Sibol\Tests\Integration;

use Sibol\Controllers\CompanionController;
use Sibol\Controllers\Student\PracticeController;
use Sibol\Core\Auth;
use Sibol\Core\Db;
use Sibol\Core\Request;
use Sibol\Services\Settings;

/**
 * The companion's "new session" form follows the chosen kind of help (its own question and example above the
 * message box), and material lists follow the chosen class, so the modes no longer look alike.
 */
final class CompanionFormTest extends IntegrationTestCase
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

    public function testEveryChatModeAsksItsOwnQuestionWithAnExample(): void
    {
        $modes = array_merge(array_diff(Settings::STUDENT_MODES, ['flashcards']), Settings::TEACHER_MODES);
        $questions = array_map([Settings::class, 'modeQuestion'], $modes);
        $examples = array_map([Settings::class, 'modeExample'], $modes);
        foreach ($modes as $i => $mode) {
            self::assertNotSame('', $questions[$i], $mode);
            self::assertStringStartsWith('For example: ', $examples[$i], $mode);
        }
        self::assertCount(count($modes), array_unique($questions), 'no two kinds of help ask the same thing');
        self::assertCount(count($modes), array_unique($examples));
    }

    public function testEachModeCardCarriesItsQuestionAndTheBoxStartsWithTheChosenOne(): void
    {
        Auth::actingAs($this->person('andrea.cruz'));
        $html = $this->newSession(['mode' => 'study_plan']);

        foreach (array_diff(Settings::STUDENT_MODES, ['flashcards']) as $mode) {
            self::assertStringContainsString(
                'value="' . $mode . '"' . ($mode === 'study_plan' ? ' checked' : '') . ' data-ask="' . e(Settings::modeQuestion($mode)) . '" data-example="' . e(Settings::modeExample($mode)) . '"',
                $html,
                $mode
            );
        }
        // Without JS the message box already matches the chosen kind of help.
        self::assertStringContainsString('<p class="field__hint" id="f-question-hint">' . e(Settings::modeQuestion('study_plan')) . '</p>', $html);
        self::assertStringContainsString('placeholder="' . e(Settings::modeExample('study_plan')) . '"', $html);
        self::assertStringContainsString('<label class="field__label" for="f-question">Your first message', $html, 'the label stays the same');
    }

    public function testTeachersSeeTheirOwnQuestions(): void
    {
        Auth::actingAs($this->person('ramon.dizon'));
        $html = $this->newSession([]);

        foreach (Settings::TEACHER_MODES as $mode) {
            self::assertStringContainsString('data-ask="' . e(Settings::modeQuestion($mode)) . '"', $html, $mode);
        }
        self::assertStringContainsString('<p class="field__hint" id="f-question-hint">' . e(Settings::modeQuestion(Settings::TEACHER_MODES[0])) . '</p>', $html);
        self::assertStringNotContainsString('f-level', $html, 'teachers have no explanation level');
    }

    public function testMaterialListsAreGroupedByClassAndFollowTheClassList(): void
    {
        Auth::actingAs($this->person('andrea.cruz'));
        $science = $this->classId('Science 9 – Narra');

        $companion = $this->newSession([]);
        self::assertStringContainsString('data-material-select data-follows="#f-class_id"', $companion);
        self::assertStringContainsString('data-any="Any material in the class" data-none="Choose a class first"', $companion);
        self::assertStringContainsString('<optgroup label="Science 9 – Narra" data-class="' . $science . '">', $companion);

        $practice = (new PracticeController(new Request('GET', '/student/learning')))->index()->body;
        self::assertStringContainsString('name="material_id" data-follows="#f-class_id"', $practice);
        self::assertStringContainsString('<optgroup label="Science 9 – Narra" data-class="' . $science . '">', $practice);
    }

    public function testAnEmptyChatShowsWhatToAskWithAnExample(): void
    {
        $andrea = $this->person('andrea.cruz');
        Auth::actingAs($andrea);
        $chat = Db::insert('ai_conversations', [
            'organization_id' => (int) $andrea['organization_id'], 'user_id' => (int) $andrea['id'],
            'mode' => 'vocabulary', 'title' => 'Test chat', 'language' => 'en',
            'last_message_at' => Db::now(), 'created_at' => Db::now(), 'updated_at' => Db::now(),
        ]);
        try {
            $html = (new CompanionController(new Request('GET', '/companion/' . $chat)))->show($chat)->body;
            self::assertStringContainsString(e(Settings::modeQuestion('vocabulary')) . ' ' . e(Settings::modeExample('vocabulary')), $html);
        } finally {
            Db::delete('ai_conversations', ['id' => $chat]);
        }
    }

    /** @param array<string,string> $query */
    private function newSession(array $query): string
    {
        return (new CompanionController(new Request('GET', '/companion/new', $query)))->create()->body;
    }
}
