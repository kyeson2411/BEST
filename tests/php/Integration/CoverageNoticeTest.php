<?php

declare(strict_types=1);

namespace Sibol\Tests\Integration;

use Sibol\Core\Auth;
use Sibol\Core\Db;
use Sibol\Core\RateLimiter;
use Sibol\Services\Ai\AiRequest;
use Sibol\Services\Ai\AiResult;
use Sibol\Services\Ai\Companion;
use Sibol\Services\Ai\DemoResponder;
use Sibol\Services\Ai\Evaluation\ScriptedProvider;
use Sibol\Services\Ai\Providers;
use Sibol\Services\Ai\Retrieval;
use Sibol\Services\Settings;

/**
 * With a live model, the companion itself opens the answer by saying when the class materials do not cover the
 * question, so it does not depend on the model saying so.
 */
final class CoverageNoticeTest extends IntegrationTestCase
{
    private const MOLAVE_SCI = 'Science 9 – Molave';
    private const NOT_COVERED = 'What causes earthquakes?';
    private const COVERED = 'How does gas exchange happen in the alveoli?';

    private ?AiRequest $request = null;

    protected function tearDown(): void
    {
        Providers::fake(null);
        Settings::flush();
        parent::tearDown();
    }

    /** @return array{events:list<array<string,mixed>>,answer:string} */
    private function ask(string $question, string $mode = 'explain', string $language = 'en', ?ScriptedProvider $provider = null): array
    {
        $janelle = $this->person('janelle.ocampo');
        Auth::actingAs($janelle);
        // These turns are not the per-minute limit's business.
        RateLimiter::clear('ai-burst', (string) $janelle['id']);
        Providers::fake($provider ?? new ScriptedProvider(function (AiRequest $request): AiResult {
            $this->request = $request;
            $result = new AiResult();
            $result->text = 'The model answer.';
            $result->stopReason = 'end_turn';
            return $result;
        }));
        $id = Db::insert('ai_conversations', [
            'organization_id' => (int) $janelle['organization_id'], 'user_id' => (int) $janelle['id'], 'class_id' => $this->classId(self::MOLAVE_SCI),
            'mode' => $mode, 'title' => 'New session', 'language' => $language, 'last_message_at' => Db::now(), 'created_at' => Db::now(), 'updated_at' => Db::now(),
        ]);
        $events = [];
        Companion::turn($janelle, (array) Db::one('SELECT * FROM ai_conversations WHERE id = :id', ['id' => $id]), $question, static function (array $e) use (&$events): void {
            $events[] = $e;
        }, static fn (): bool => false);
        $answer = (string) Db::value("SELECT content FROM ai_messages WHERE conversation_id = :c AND role = 'assistant'", ['c' => $id]);
        return ['events' => $events, 'answer' => $answer];
    }

    private function lastPrompt(): string
    {
        self::assertNotNull($this->request);
        return (string) $this->request->messages[count($this->request->messages) - 1]['content'];
    }

    public function testTheNoticeComesFirstWhenNoPassageMatches(): void
    {
        $turn = $this->ask(self::NOT_COVERED);
        $deltas = array_values(array_filter($turn['events'], static fn ($e) => $e['type'] === 'delta'));
        self::assertStringStartsWith("**Your class materials don't cover this.**", $deltas[0]['text'], 'Streamed before the model writes');
        self::assertStringStartsWith("**Your class materials don't cover this.** Anything below is general knowledge", $turn['answer']);
        self::assertStringEndsWith('The model answer.', $turn['answer']);
        self::assertStringContainsString('do not say that again', $this->lastPrompt(), 'The model is told the notice is already there');
    }

    public function testNoNoticeWhenTheMaterialsCoverTheQuestion(): void
    {
        $turn = $this->ask(self::COVERED);
        self::assertSame('The model answer.', $turn['answer']);
        self::assertStringNotContainsString('do not say that again', $this->lastPrompt());
    }

    public function testTheNoticeFollowsTheAnswerLanguage(): void
    {
        self::assertStringStartsWith('**Hindi ito saklaw ng iyong mga materyal ng klase.**', $this->ask(self::NOT_COVERED, 'explain', 'fil')['answer']);
    }

    public function testWithoutGeneralAnswersTheNoticeDoesNotPromiseThem(): void
    {
        $answer = $this->ask(self::NOT_COVERED, 'ask_materials')['answer'];
        self::assertStringStartsWith("**Your class materials don't cover this.** Try different key words, or ask your teacher.", $answer);

        $orgId = (int) $this->person('janelle.ocampo')['organization_id'];
        Settings::override($orgId, 'ai.general_answers', false);
        self::assertStringNotContainsString('general knowledge', $this->ask(self::NOT_COVERED)['answer']);
    }

    public function testPassagesThatOnlyPartlyMatchGetTheSofterNotice(): void
    {
        $janelle = $this->person('janelle.ocampo');
        $conversation = ['mode' => 'explain', 'language' => 'en'];
        $sources = [['n' => 1, 'section_title' => 'Breathing', 'content' => 'The alveoli are tiny air sacs where gas exchange happens.']];
        self::assertStringStartsWith('**Your class materials may not cover this directly.**', (string) Companion::coverageNotice($janelle, $conversation, 'How do volcanoes and tectonic plates form islands?', $sources));
        self::assertNull(Companion::coverageNotice($janelle, $conversation, 'Where does gas exchange happen in the alveoli?', $sources));
        self::assertNull(Companion::coverageNotice($janelle, ['mode' => 'study_plan', 'language' => 'en'], 'Plan my week', []), 'Study plans are not questions about the materials');
    }

    public function testAGreetingThanksOrASumGetsNoNotice(): void
    {
        foreach (['hi', 'Thank you po!', '1+1'] as $message) {
            $turn = $this->ask($message);
            self::assertSame('The model answer.', $turn['answer'], $message);
            self::assertStringContainsString('names no topic from the class materials', $this->lastPrompt(), $message);
            self::assertStringContainsString('Do not say that the class materials do not cover it', $this->lastPrompt(), $message);
            self::assertStringNotContainsString('No class material passages matched', $this->lastPrompt(), $message);
        }
        // A greeting with a question in it is still a question.
        self::assertStringStartsWith("**Your class materials don't cover this.**", $this->ask('Hello! What causes earthquakes?')['answer']);
    }

    public function testMessagesThatNameNoTopic(): void
    {
        foreach (['hi', 'Hello!', 'thank you', 'Good morning po', 'Salamat po', 'Magandang umaga po', 'ok', '1+1', 'How are you?',
            'Who are you?', 'I don\'t understand', '???', 'test'] as $message) {
            self::assertTrue(Retrieval::namesNoTopic($message), $message);
        }
        foreach (['What causes earthquakes?', 'Hello, what is diffusion?', 'Good morning, ano ang alveoli?', 'Thanks! Now explain osmosis.',
            'Ilan ang silid ng puso?'] as $message) {
            self::assertFalse(Retrieval::namesNoTopic($message), $message);
        }
    }

    public function testDemoModeAnswersAGreetingWithoutSayingTheMaterialsMissIt(): void
    {
        $janelle = $this->person('janelle.ocampo');
        $answer = DemoResponder::respond($janelle, ['mode' => 'explain', 'language' => 'en'], 'hi', []);
        self::assertStringContainsString('Ask me about a topic from your class materials', $answer);
        self::assertStringNotContainsString('matched', $answer);
        self::assertStringContainsString('Demo mode', $answer, 'Still labelled');
        self::assertStringContainsString('None of the released class materials matched', DemoResponder::respond($janelle, ['mode' => 'explain', 'language' => 'en'], 'What causes earthquakes?', []));
    }

    public function testARefusalIsStoredWithoutTheNotice(): void
    {
        $answer = $this->ask(self::NOT_COVERED, 'explain', 'en', ScriptedProvider::refusing(''))['answer'];
        self::assertStringStartsWith('I can\'t help with that request.', $answer);
    }
}
