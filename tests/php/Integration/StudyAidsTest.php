<?php

declare(strict_types=1);

namespace Sibol\Tests\Integration;

use Sibol\Controllers\FileController;
use Sibol\Controllers\Student\ClassController;
use Sibol\Controllers\Student\PracticeController;
use Sibol\Controllers\Teacher\ReadingVersionController;
use Sibol\Core\Auth;
use Sibol\Core\Db;
use Sibol\Core\HttpException;
use Sibol\Core\Request;
use Sibol\Services\Ai\Companion;
use Sibol\Services\Ai\DemoProvider;
use Sibol\Services\Ai\Providers;
use Sibol\Services\ReadingVersions;
use Sibol\Services\Settings;

/** Explain it back, read-aloud, simpler and Filipino versions of materials, and practice kept for offline use. */
final class StudyAidsTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        Providers::fake(null);
        parent::tearDown();
    }

    public function testExplainItBackComparesTheExplanationWithTheMaterials(): void
    {
        self::assertContains('explain_back', Settings::STUDENT_MODES);
        self::assertSame('Explain it back', Settings::modeLabel('explain_back'));

        $andrea = $this->person('andrea.cruz');
        $chat = $this->chat($andrea, 'explain_back');
        try {
            $answer = $this->turn($andrea, $chat, 'Gas exchange in the lungs: the alveoli have thin walls so oxygen goes into the blood quickly.');
            self::assertStringContainsString('Demo mode', $answer);
            self::assertStringContainsString('Matches your class materials:', $answer);
            self::assertStringContainsString('Not in your explanation yet:', $answer);
            self::assertMatchesRegularExpression('/\[\d+\]/', $answer, 'each missing point shows its source');
            self::assertStringContainsString('?', $answer, 'it asks the student to try again');
            self::assertStringContainsString('cannot tell whether your sentences are correct', $answer, 'demo mode says what it cannot judge');

            $short = $this->turn($andrea, $chat, 'alveoli');
            self::assertStringContainsString('Now explain it in your own words', $short, 'a topic alone is not an explanation');
        } finally {
            Db::delete('ai_conversations', ['id' => $chat]);
        }
    }

    public function testAnswersAndReadingPagesHaveAListenButtonInTheTextsLanguage(): void
    {
        self::assertStringContainsString('data-lang="fil"', listen_button('#x', 'Ang mga alveoli ay may manipis na dingding kaya mabilis ang palitan ng gas sa baga.'));
        self::assertStringContainsString('data-lang="en"', listen_button('#x', 'The alveoli have thin walls, so gas exchange in the lungs is fast.'));

        Auth::actingAs($this->person('andrea.cruz'));
        $id = $this->materialId('Making inferences while reading', 'English 9 – Narra');
        $html = (new FileController(new Request('GET', '/materials/' . $id . '/read')))->read($id)->body;
        self::assertStringContainsString('data-read-aloud="#reading"', $html);
        self::assertStringContainsString('<article class="prose" id="reading">', $html);
    }

    public function testATeacherMakesAVersionAndStudentsSeeItOnlyOnceApproved(): void
    {
        $ramon = $this->person('ramon.dizon');
        $andrea = $this->person('andrea.cruz');
        $id = $this->materialId('Breathing and circulation notes', 'Science 9 – Narra');
        try {
            Auth::actingAs($ramon);
            Providers::fake(new DemoProvider());
            $response = (new ReadingVersionController(new Request('POST', '/teacher/materials/' . $id . '/reading/simpler')))->draft($id, 'simpler');
            self::assertSame(303, $response->status);
            $draft = ReadingVersions::forMaterial($id)['simpler'];
            self::assertSame('draft', $draft['status']);
            self::assertSame(0, (int) $draft['ai_assisted'], 'demo mode rewrites nothing and does not claim to');
            self::assertStringStartsWith('**Demo draft:**', $draft['content']);

            // A draft never reaches students: the original opens, with no tabs.
            Auth::actingAs($andrea);
            $html = $this->read($id, 'simpler');
            self::assertStringNotContainsString('Reading versions', $html);
            self::assertStringNotContainsString('Demo draft', $html);

            Auth::actingAs($ramon);
            (new ReadingVersionController(new Request('POST', '/teacher/materials/' . $id . '/reading/simpler', [], [
                'content' => "## Breathing in\nYour lungs take in air. The air goes to tiny sacs called alveoli.", 'action' => 'approve',
            ])))->save($id, 'simpler');
            self::assertSame('approved', ReadingVersions::forMaterial($id)['simpler']['status']);
            self::assertSame(['simpler'], ReadingVersions::approvedKinds($id));

            Auth::actingAs($andrea);
            $html = $this->read($id, 'simpler');
            self::assertStringContainsString('Reading versions', $html);
            self::assertStringContainsString('tiny sacs called alveoli', $html);
            self::assertStringContainsString('checked by your teacher', $html);
            $classPage = (new ClassController(new Request('GET', '/student/classes/1', ['tab' => 'materials'])))->show($this->classId('Science 9 – Narra'))->body;
            self::assertStringContainsString(e(url('/materials/' . $id . '/read', ['v' => 'simpler'])), $classPage, 'the class list links to it');

            // Another class's teacher cannot reach it, and only the two kinds exist.
            Auth::actingAs($this->person('liza.mercado'));
            $this->assertNotFound(fn () => (new ReadingVersionController(new Request('GET', '/x')))->edit($id, 'simpler'));
            Auth::actingAs($ramon);
            $this->assertNotFound(fn () => (new ReadingVersionController(new Request('GET', '/x')))->edit($id, 'klingon'));
        } finally {
            Db::exec('DELETE FROM reading_versions WHERE material_id = :m', ['m' => $id]);
        }
    }

    public function testAnApprovedVersionOfAPdfOpensAsAPage(): void
    {
        $id = $this->materialId('Heredity notes', 'Science 9 – Narra');
        $material = (array) Db::one('SELECT * FROM learning_materials WHERE id = :id', ['id' => $id]);
        try {
            ReadingVersions::save($this->person('ramon.dizon'), $material, 'filipino', "## Pagmamana\nAng mga katangian ay namamana mula sa mga magulang.", true, false);
            Auth::actingAs($this->person('andrea.cruz'));
            $html = $this->read($id, 'filipino');
            self::assertStringContainsString('Ang mga katangian ay namamana', $html);
            self::assertStringContainsString('data-lang="fil"', $html, 'the Filipino version is read with a Filipino voice');
            self::assertStringContainsString('href="' . e(url('/materials/' . $id . '/file')) . '"', $html, 'the Original tab opens the PDF');
            $original = (new FileController(new Request('GET', '/materials/' . $id . '/read')))->read($id);
            self::assertSame(303, $original->status, 'the PDF itself still opens as a file');
        } finally {
            Db::exec('DELETE FROM reading_versions WHERE material_id = :m', ['m' => $id]);
        }
    }

    public function testAPracticePackHoldsTheSetForItsOwnerOnly(): void
    {
        $andrea = $this->person('andrea.cruz');
        $set = Db::insert('practice_sets', [
            'organization_id' => (int) $andrea['organization_id'], 'user_id' => (int) $andrea['id'], 'class_id' => $this->classId('Science 9 – Narra'),
            'kind' => 'quiz', 'title' => 'Pack test', 'generator' => 'demo', 'created_at' => Db::now(), 'updated_at' => Db::now(),
            'items' => json_encode([
                ['type' => 'mcq', 'question' => 'Where does gas exchange happen?', 'choices' => ['Alveoli', 'Trachea'], 'answer' => 0, 'explanation' => 'In the alveoli.', 'source_ref' => 1],
                ['type' => 'short', 'question' => 'Name the muscle under the lungs.', 'answer_text' => 'diaphragm'],
            ]),
        ]);
        try {
            Auth::actingAs($andrea);
            $response = (new PracticeController(new Request('GET', '/student/learning/' . $set . '/pack')))->pack($set);
            $pack = json_decode($response->body, true);
            self::assertSame('no-store', $response->headers['Cache-Control']);
            self::assertSame(['id', 'kind', 'title', 'place', 'items'], array_keys($pack));
            self::assertSame(0, $pack['items'][0]['answer'], 'answers travel with the pack so it can be checked offline');
            self::assertSame('diaphragm', $pack['items'][1]['answer_text']);
            self::assertArrayNotHasKey('source_ref', $pack['items'][0]);

            // Results practised offline come back through the usual form.
            (new PracticeController(new Request('POST', '/student/learning/' . $set . '/results', [], ['answers' => ['0', 'Diaphragm']])))->saveResults($set);
            self::assertSame(2, (int) Db::value('SELECT score FROM practice_sets WHERE id = :id', ['id' => $set]));

            Auth::actingAs($this->person((string) Db::value("SELECT username FROM users WHERE role = 'student' AND status = 'active' AND username IS NOT NULL AND id <> :u LIMIT 1", ['u' => (int) $andrea['id']])));
            $this->assertNotFound(fn () => (new PracticeController(new Request('GET', '/x')))->pack($set));
        } finally {
            Db::delete('practice_sets', ['id' => $set]);
            Db::exec("DELETE FROM review_items WHERE source LIKE 'practice:%' AND user_id = :u", ['u' => (int) $andrea['id']]);
        }
    }

    private function read(int $id, string $kind): string
    {
        return (new FileController(new Request('GET', '/materials/' . $id . '/read', ['v' => $kind])))->read($id)->body;
    }

    private function chat(array $user, string $mode): int
    {
        return Db::insert('ai_conversations', [
            'organization_id' => (int) $user['organization_id'], 'user_id' => (int) $user['id'], 'class_id' => $this->classId('Science 9 – Narra'),
            'mode' => $mode, 'title' => 'Study aids test', 'language' => 'en',
            'last_message_at' => Db::now(), 'created_at' => Db::now(), 'updated_at' => Db::now(),
        ]);
    }

    private function turn(array $user, int $chat, string $message): string
    {
        Auth::actingAs($user);
        Providers::fake(new DemoProvider());
        Companion::turn($user, (array) Db::one('SELECT * FROM ai_conversations WHERE id = :id', ['id' => $chat]), $message,
            static function (array $e): void {
            }, static fn (): bool => false);
        return (string) Db::value("SELECT content FROM ai_messages WHERE conversation_id = :c AND role = 'assistant' ORDER BY id DESC LIMIT 1", ['c' => $chat]);
    }

    private function assertNotFound(callable $call): void
    {
        try {
            $call();
            self::fail('expected not found');
        } catch (HttpException $e) {
            self::assertSame(404, $e->status);
        }
    }
}
