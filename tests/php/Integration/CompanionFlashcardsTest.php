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
 * The companion's Flashcards choice opens the real flip cards in Practice (cards that are saved and come back in
 * Review) with the class already picked, instead of starting a chat that writes cards as text.
 */
final class CompanionFlashcardsTest extends IntegrationTestCase
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

    public function testTheFlashcardsCardIsALinkToPracticeForTheChosenClass(): void
    {
        Auth::actingAs($this->person('andrea.cruz'));
        $science = $this->classId('Science 9 – Narra');
        $html = (new CompanionController(new Request('GET', '/companion/new', ['class_id' => (string) $science, 'topic' => 'Punnett squares'])))->create()->body;

        self::assertStringNotContainsString('value="flashcards"', $html, 'flashcards no longer start a chat');
        self::assertStringContainsString('name="mode" value="explain"', $html, 'the other kinds of help are still chats');
        self::assertSame(['kind' => 'flashcards', 'class_id' => (string) $science, 'topic' => 'Punnett squares'], $this->practiceLink($html));
        self::assertStringContainsString('Opens Practice, where you flip cards made from your class materials.', $html);
    }

    public function testWithoutAClassTheLinkUsesTheClassPractisedLast(): void
    {
        $andrea = $this->person('andrea.cruz');
        Auth::actingAs($andrea);
        self::assertSame(['kind' => 'flashcards'], $this->practiceLink($this->newSession()), 'no practice yet: Practice picks its first class');

        $math = $this->classId('Mathematics 9 – Narra');
        $set = Db::insert('practice_sets', [
            'organization_id' => (int) $andrea['organization_id'], 'user_id' => (int) $andrea['id'], 'class_id' => $math,
            'kind' => 'flashcards', 'title' => 'Flashcards: test', 'items' => '[]', 'generator' => 'demo', 'total' => 0,
            'created_at' => Db::now(), 'updated_at' => Db::now(),
        ]);
        try {
            self::assertSame(['kind' => 'flashcards', 'class_id' => (string) $math], $this->practiceLink($this->newSession()));
        } finally {
            Db::delete('practice_sets', ['id' => $set]);
        }
    }

    public function testAnOldLinkToAFlashcardsChatOpensPractice(): void
    {
        Auth::actingAs($this->person('andrea.cruz'));
        $science = $this->classId('Science 9 – Narra');
        $response = (new CompanionController(new Request('GET', '/companion/new', ['mode' => 'flashcards', 'class_id' => (string) $science])))->create();

        self::assertSame(303, $response->status);
        self::assertSame(url('/student/learning?kind=flashcards&class_id=' . $science), $response->headers['Location']);
    }

    public function testStartingAFlashcardsChatOpensPracticeAndCreatesNoChat(): void
    {
        $andrea = $this->person('andrea.cruz');
        Auth::actingAs($andrea);
        $science = $this->classId('Science 9 – Narra');
        $chats = static fn (): int => (int) Db::value('SELECT COUNT(*) FROM ai_conversations WHERE user_id = :u', ['u' => (int) $andrea['id']]);
        $before = $chats();

        $response = (new CompanionController(new Request('POST', '/companion', [], [
            'mode' => 'flashcards', 'class_id' => (string) $science, 'language' => 'en', 'question' => 'Make cards about my genes notes',
        ])))->store();

        self::assertSame(303, $response->status);
        self::assertSame(url('/student/learning?kind=flashcards&class_id=' . $science), $response->headers['Location']);
        self::assertStringNotContainsString('genes', $response->headers['Location'], 'what the student typed stays out of the address');
        self::assertSame($before, $chats());
    }

    public function testChangingAChatToFlashcardsOpensPracticeAndKeepsTheChat(): void
    {
        $andrea = $this->person('andrea.cruz');
        Auth::actingAs($andrea);
        $science = $this->classId('Science 9 – Narra');
        $chat = Db::insert('ai_conversations', [
            'organization_id' => (int) $andrea['organization_id'], 'user_id' => (int) $andrea['id'], 'class_id' => $science,
            'mode' => 'explain', 'title' => 'Test chat', 'language' => 'en',
            'last_message_at' => Db::now(), 'created_at' => Db::now(), 'updated_at' => Db::now(),
        ]);
        try {
            $html = (new CompanionController(new Request('GET', '/companion/' . $chat)))->show($chat)->body;
            self::assertStringContainsString('href="' . e(url('/student/learning?kind=flashcards&class_id=' . $science)) . '"', $html, 'the Change mode dialog links to Practice');

            $response = (new CompanionController(new Request('POST', '/companion/' . $chat . '/mode', [], ['mode' => 'flashcards'])))->changeMode($chat);
            self::assertSame(303, $response->status);
            self::assertSame(url('/student/learning?kind=flashcards&class_id=' . $science), $response->headers['Location']);
            self::assertSame('explain', Db::value('SELECT mode FROM ai_conversations WHERE id = :id', ['id' => $chat]));
        } finally {
            Db::delete('ai_conversations', ['id' => $chat]);
        }
    }

    public function testPracticePicksTheClassFromTheLinkAndPutsFlashcardsFirst(): void
    {
        Auth::actingAs($this->person('andrea.cruz'));
        $science = $this->classId('Science 9 – Narra');
        $html = $this->practice(['kind' => 'flashcards', 'class_id' => (string) $science, 'topic' => 'Punnett squares']);

        self::assertStringContainsString('<option value="' . $science . '" selected>', $html);
        self::assertStringContainsString('value="Punnett squares"', $html);
        self::assertStringContainsString('Check the class, then press Flashcards.', $html);
        self::assertMatchesRegularExpression('/<button class="btn btn--primary" type="submit" name="kind" value="flashcards"[^>]* autofocus>/', $html);
        self::assertLessThan(strpos($html, 'value="quiz"'), strpos($html, 'value="flashcards"'), 'flashcards come first');
    }

    public function testAMaterialFromTheLinkPicksItsOwnClass(): void
    {
        Auth::actingAs($this->person('andrea.cruz'));
        $science = $this->classId('Science 9 – Narra');
        $notes = $this->materialId('Breathing and circulation notes', 'Science 9 – Narra');
        $html = $this->practice(['kind' => 'flashcards', 'class_id' => (string) $this->classId('English 9 – Narra'), 'material_id' => (string) $notes]);

        self::assertStringContainsString('<option value="' . $notes . '" selected>', $html);
        self::assertStringContainsString('<option value="' . $science . '" selected>', $html);
    }

    public function testPracticeIgnoresClassesAndMaterialsThatAreNotTheStudents(): void
    {
        Auth::actingAs($this->person('andrea.cruz'));
        $molave = $this->classId('Science 9 – Molave');
        $molaveNotes = $this->materialId('Breathing and circulation notes', 'Science 9 – Molave');
        $html = $this->practice(['class_id' => (string) $molave, 'material_id' => (string) $molaveNotes]);

        self::assertStringNotContainsString('Science 9 – Molave', $html);
        self::assertSame(1, preg_match('/<select[^>]* name="class_id"[^>]*>(.*?)<\/select>/s', $html, $classes));
        self::assertStringNotContainsString('value="' . $molave . '"', $classes[1]);
        self::assertSame(1, preg_match('/<select[^>]* name="material_id"[^>]*>(.*?)<\/select>/s', $html, $materials));
        self::assertStringNotContainsString(' selected', $materials[1], 'no material picked');
        // Without kind=flashcards the page is as before: practice questions first, no hint, nothing focused.
        self::assertMatchesRegularExpression('/<button class="btn btn--primary" type="submit" name="kind" value="quiz"/', $html);
        self::assertStringNotContainsString('autofocus', $html);
        self::assertStringNotContainsString('Check the class, then press Flashcards.', $html);
    }

    public function testWhenTheSchoolTurnsFlashcardsOffNothingLeadsToThem(): void
    {
        $andrea = $this->person('andrea.cruz');
        Auth::actingAs($andrea);
        Settings::override((int) $andrea['organization_id'], 'features.flashcards', false);

        self::assertStringNotContainsString('data-practice-link', $this->newSession());
        $old = (new CompanionController(new Request('GET', '/companion/new', ['mode' => 'flashcards'])))->create();
        self::assertSame(200, $old->status, 'an old flashcards link opens the companion as usual');
        $html = $this->practice(['kind' => 'flashcards']);
        self::assertStringNotContainsString('value="flashcards"', $html);
        self::assertStringNotContainsString('Check the class, then press Flashcards.', $html);
    }

    public function testTeachersKeepTheirOwnKindsOfHelp(): void
    {
        Auth::actingAs($this->person('ramon.dizon'));
        $response = (new CompanionController(new Request('GET', '/companion/new', ['mode' => 'flashcards'])))->create();

        self::assertSame(200, $response->status);
        self::assertStringNotContainsString('data-practice-link', $response->body);
        self::assertStringNotContainsString('/student/learning', $response->body);
    }

    private function newSession(): string
    {
        return (new CompanionController(new Request('GET', '/companion/new')))->create()->body;
    }

    /** @param array<string,string> $query */
    private function practice(array $query): string
    {
        return (new PracticeController(new Request('GET', '/student/learning', $query)))->index()->body;
    }

    /** @return array<string,string> the query of the Flashcards card's link on the "new session" screen */
    private function practiceLink(string $html): array
    {
        self::assertSame(1, preg_match('/<a class="mode-option" href="([^"]+)" data-practice-link>/', $html, $m), 'one Flashcards link');
        $href = html_entity_decode($m[1], ENT_QUOTES);
        self::assertSame(url('/student/learning'), (string) strtok($href, '?'));
        parse_str((string) parse_url($href, PHP_URL_QUERY), $query);
        return $query;
    }
}
