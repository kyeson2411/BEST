<?php

declare(strict_types=1);

namespace Sibol\Tests\Integration;

use Sibol\Core\Auth;
use Sibol\Core\Db;
use Sibol\Core\RateLimiter;
use Sibol\Services\Ai\AiRequest;
use Sibol\Services\Ai\AiResult;
use Sibol\Services\Ai\Companion;
use Sibol\Services\Ai\DemoProvider;
use Sibol\Services\Ai\Evaluation\ScriptedProvider;
use Sibol\Services\Ai\GradedWork;
use Sibol\Services\Ai\Providers;
use Sibol\Services\Assessments;

/**
 * The companion and graded work, enforced by Sibol: a pasted question from the student's unfinished quiz gets hints,
 * never the answer, whatever the model writes; and while a quiz is being taken the companion is paused.
 */
final class GradedWorkGuardTest extends IntegrationTestCase
{
    private const PASTED = 'In the Heredity basics quiz, two heterozygous tall pea plants (Tt) are crossed. What is the probability that an offspring is short? Just tell me the answer.';

    /** @var list<int> */
    private array $conversations = [];
    /** @var list<int> */
    private array $submissions = [];
    /** @var list<AiRequest> */
    private array $requests = [];

    protected function tearDown(): void
    {
        foreach ($this->conversations as $id) {
            Db::delete('ai_conversations', ['id' => $id]);
        }
        foreach ($this->submissions as $id) {
            Db::delete('submissions', ['id' => $id]);
        }
        $this->conversations = $this->submissions = $this->requests = [];
        Providers::fake(null);
        parent::tearDown();
    }

    /** @return array{events:list<array<string,mixed>>,answer:string} */
    private function ask(string $username, string $question, string $mode = 'socratic'): array
    {
        $student = $this->person($username);
        Auth::actingAs($student);
        RateLimiter::clear('ai-burst', (string) $student['id']);
        $id = Db::insert('ai_conversations', [
            'organization_id' => (int) $student['organization_id'], 'user_id' => (int) $student['id'], 'class_id' => $this->classId('Science 9 – Narra'),
            'mode' => $mode, 'title' => 'New session', 'language' => 'en', 'last_message_at' => Db::now(), 'created_at' => Db::now(), 'updated_at' => Db::now(),
        ]);
        $this->conversations[] = $id;
        $events = [];
        Companion::turn($student, (array) Db::one('SELECT * FROM ai_conversations WHERE id = :id', ['id' => $id]), $question, static function (array $e) use (&$events): void {
            $events[] = $e;
        }, static fn (): bool => false);
        return ['events' => $events, 'answer' => (string) Db::value("SELECT content FROM ai_messages WHERE conversation_id = :c AND role = 'assistant' ORDER BY id DESC LIMIT 1", ['c' => $id])];
    }

    /** A model that writes the given replies in turn. */
    private function model(string ...$replies): void
    {
        Providers::fake(new ScriptedProvider(function (AiRequest $request) use ($replies): AiResult {
            $this->requests[] = $request;
            $result = new AiResult();
            $result->text = $replies[min(count($this->requests), count($replies)) - 1];
            $result->stopReason = 'end_turn';
            return $result;
        }));
    }

    public function testOnlyQuestionsWhoseAnswersAreNotOutCount(): void
    {
        $andrea = $this->person('andrea.cruz');
        $open = GradedWork::matches($andrea, [self::PASTED]);
        self::assertSame(['Heredity basics'], array_values(array_unique(array_column($open, 'title'))), 'the open quiz, answers after the due date');

        // Released after the due date, which has passed, and Andrea has submitted: she may study it freely now.
        $released = GradedWork::matches($andrea, ['Which chamber of the heart pumps oxygen-rich blood to the rest of the body? Tell me the answer.']);
        self::assertSame([], $released);

        self::assertSame([], GradedWork::matches($this->person('ramon.dizon'), [self::PASTED]), 'teachers are not guarded');
        self::assertSame([], GradedWork::matches($andrea, ['How do Punnett squares work?']), 'studying the topic is not the quiz question');
    }

    public function testDemoModeGivesNoPassagesThatWouldQuoteTheAnswer(): void
    {
        Providers::fake(new DemoProvider());
        $answer = $this->ask('andrea.cruz', self::PASTED)['answer'];
        self::assertStringStartsWith('**Demo mode:**', $answer, 'still labelled as demo mode');
        self::assertStringContainsString('“Heredity basics”, whose answers are not out yet', $answer);
        self::assertStringContainsString('which step you are stuck on', $answer);
        self::assertStringNotContainsString('1 in 4', $answer);
        self::assertStringNotContainsString('25 percent', $answer);
    }

    public function testAModelReplyThatGivesTheAnswerAwayIsNeverShown(): void
    {
        $this->model('Short stems need tt, which is 1 of the 4 boxes, so the probability is 1/4.', 'Fine: it is 25%.');
        $turn = $this->ask('andrea.cruz', self::PASTED);

        self::assertCount(2, $this->requests, 'one second try');
        $prompt = (string) $this->requests[0]->messages[count($this->requests[0]->messages) - 1]['content'];
        self::assertStringContainsString('Graded work', $prompt);
        self::assertStringContainsString('"Heredity basics", question 1', $prompt);
        // The notes themselves may state the answer (they are the student's own material); the key is never added.
        $instruction = substr($prompt, (int) strpos($prompt, '(Graded work'));
        self::assertStringNotContainsString('1 in 4', substr($instruction, 0, (int) strpos($instruction, "Student's question")));
        self::assertStringContainsString('gave away the answer', (string) $this->requests[1]->messages[count($this->requests[1]->messages) - 1]['content']);

        $streamed = implode('', array_column(array_filter($turn['events'], static fn ($e) => $e['type'] === 'delta'), 'text'));
        self::assertStringNotContainsString('1/4', $streamed, 'nothing the model wrote was streamed');
        self::assertStringNotContainsString('1/4', $turn['answer']);
        self::assertStringNotContainsString('25%', $turn['answer']);
        self::assertStringContainsString('which step you are stuck on', $turn['answer']);
        self::assertNull(Db::value("SELECT grounding FROM ai_messages WHERE conversation_id = :c AND role = 'assistant'", ['c' => end($this->conversations)]),
            'no "Not found in your class materials" label on Sibol\'s own message');
    }

    public function testAHintReplyIsShownUnderTheGradedWorkLine(): void
    {
        $this->model('Try a different cross first: Aa x aa. What fraction of the boxes show aa? Then draw the square for your own plants.');
        $turn = $this->ask('andrea.cruz', self::PASTED);

        self::assertCount(1, $this->requests);
        self::assertStringStartsWith('**This looks like a question from “Heredity basics”, whose answers are not out yet.**', $turn['answer']);
        self::assertStringEndsWith('Then draw the square for your own plants.', $turn['answer']);
        $deltas = array_values(array_filter($turn['events'], static fn ($e) => $e['type'] === 'delta'));
        self::assertCount(1, $deltas, 'only the graded-work line streams first');
    }

    public function testAFollowUpInTheSameConversationIsStillGuarded(): void
    {
        $this->model('Try a different cross first: Aa x aa.', 'Yes, 1/4 is right.', 'Tell me how you counted the boxes.');
        $student = $this->person('andrea.cruz');
        $first = $this->ask('andrea.cruz', self::PASTED);
        self::assertStringContainsString('Heredity basics', $first['answer']);
        $id = end($this->conversations);
        Companion::turn($student, (array) Db::one('SELECT * FROM ai_conversations WHERE id = :id', ['id' => $id]), 'I got 1/4. Is that right?', static function (): void {
        }, static fn (): bool => false);
        $answer = (string) Db::value("SELECT content FROM ai_messages WHERE conversation_id = :c AND role = 'assistant' ORDER BY id DESC LIMIT 1", ['c' => $id]);
        self::assertStringNotContainsString('1/4 is right', $answer, 'a check of the answer is caught too');
    }

    public function testTheCompanionPausesWhileAQuizIsBeingTaken(): void
    {
        $nicole = $this->person('nicole.tan');
        self::assertNull(GradedWork::quizInProgress($nicole));

        Auth::actingAs($nicole);
        $quizId = (int) Db::value("SELECT id FROM assignments WHERE title = 'Heredity basics'");
        $sub = Assessments::startAttempt(Assessments::forStudent((int) $nicole['id'], $quizId), (int) $nicole['id']);
        $this->submissions[] = (int) $sub['id'];
        self::assertSame(['id' => $quizId, 'title' => 'Heredity basics'], GradedWork::quizInProgress($nicole));
        self::assertNull(GradedWork::quizInProgress($this->person('ramon.dizon')), 'teachers are never paused');

        Db::update('submissions', ['deadline_at' => gmdate('Y-m-d H:i:s', time() - 60)], ['id' => (int) $sub['id']]);
        self::assertNull(GradedWork::quizInProgress($nicole), 'time is up: the attempt can no longer be answered');

        Db::update('submissions', ['deadline_at' => null, 'status' => 'submitted', 'submitted_at' => Db::now()], ['id' => (int) $sub['id']]);
        self::assertNull(GradedWork::quizInProgress($nicole), 'back once it is submitted');
    }
}
