<?php

declare(strict_types=1);

namespace Sibol\Tests\Integration;

use Sibol\Core\Auth;
use Sibol\Core\Db;
use Sibol\Core\HttpException;
use Sibol\Policies\Access;
use Sibol\Services\Ai\Companion;
use Sibol\Services\Ai\DemoProvider;
use Sibol\Services\Ai\Providers;
use Sibol\Services\Ai\Retrieval;

/**
 * Permission boundaries, including the rule that retrieval filters by permission
 * before any passage can reach the model.
 */
final class AccessAndRetrievalTest extends IntegrationTestCase
{
    private const NARRA_SCI = 'Science 9 – Narra';
    private const MOLAVE_SCI = 'Science 9 – Molave';
    private const NARRA_MATH = 'Mathematics 9 – Narra';

    public function testTeacherCannotOpenAClassTheyDoNotTeach(): void
    {
        $ramon = $this->person('ramon.dizon');
        self::assertSame(self::NARRA_SCI, Access::teacherClass($ramon, $this->classId(self::NARRA_SCI))['name']);
        $this->expectException(HttpException::class);
        Access::teacherClass($ramon, $this->classId(self::NARRA_MATH));
    }

    public function testStudentSeesOnlyTheirActiveClasses(): void
    {
        $andrea = $this->person('andrea.cruz');
        $ids = Access::studentClassIds($andrea);
        self::assertContains($this->classId(self::NARRA_SCI), $ids);
        self::assertNotContains($this->classId(self::MOLAVE_SCI), $ids);

        $this->expectException(HttpException::class);
        Access::studentClass($andrea, $this->classId(self::MOLAVE_SCI));
    }

    public function testWithdrawnStudentLosesAccess(): void
    {
        self::assertSame([], Access::studentClassIds($this->person('angelica.santos')));
    }

    public function testStudentCannotOpenAnotherStudentsSubmissionOrDraftMaterial(): void
    {
        $andrea = $this->person('andrea.cruz');
        $draft = $this->materialId('Lab safety reminders', self::NARRA_SCI);
        $this->expectException(HttpException::class);
        Access::studentMaterial($andrea, $draft);
    }

    public function testStudentRetrievalExcludesScheduledDraftAndOtherClassesMaterials(): void
    {
        $andrea = $this->person('andrea.cruz');
        Auth::actingAs($andrea);
        $allowed = Access::studentClassIds($andrea);

        $scheduled = $this->materialId('Module 2 review sheet', self::NARRA_SCI);
        foreach (Retrieval::search($andrea, 'guinea pigs black fur white fur offspring') as $s) {
            self::assertNotSame($scheduled, (int) $s['material_id'], 'A material scheduled for later must not be retrievable yet');
        }
        $draft = $this->materialId('Lab safety reminders', self::NARRA_SCI);
        foreach (Retrieval::search($andrea, 'tie back long hair bags under the table') as $s) {
            self::assertNotSame($draft, (int) $s['material_id'], 'Draft materials must not be retrievable');
        }
        foreach (Retrieval::search($andrea, 'alveoli capillaries diffusion oxygen') as $s) {
            $classId = (int) Db::value('SELECT class_id FROM learning_materials WHERE id = :m', ['m' => (int) $s['material_id']]);
            self::assertContains($classId, $allowed);
        }
        self::assertNotEmpty(Retrieval::search($andrea, 'alveoli capillaries diffusion oxygen'), 'Released materials are retrievable');
    }

    public function testStudentInAnotherSectionCannotRetrieveThatSectionsMaterials(): void
    {
        $janelle = $this->person('janelle.ocampo');
        Auth::actingAs($janelle);
        $allowed = Access::studentClassIds($janelle);
        foreach (['Mara jeepney stop wristwatch inference', 'jeepney', 'clue what I know inference'] as $query) {
            foreach (Retrieval::search($janelle, $query) as $s) {
                self::assertContains((int) $s['class_id'], $allowed, 'English 9 – Narra materials belong to another section');
            }
        }
        // A single shared common word ("stop" in her science notes) is not enough to cite a passage.
        self::assertSame([], Retrieval::search($janelle, 'Mara jeepney stop wristwatch inference'));
    }

    public function testKeywordsDropFunctionWords(): void
    {
        self::assertSame(['photosynthesis'], Retrieval::keywords('Can you explain photosynthesis in simple terms?'));
        self::assertSame(['mahalaga', 'alveoli'], Retrieval::keywords('Bakit mahalaga ang alveoli?'));
        // Request words ("write", "practice items", "gumawa ng tatlong paraan") are not the subject.
        self::assertSame(['punnett', 'square'], Retrieval::keywords('Write three Punnett square practice items with answers.'));
        self::assertSame(['punnett', 'square'], Retrieval::keywords('Gumawa ng tatlong paraan ng pagpapaliwanag ng Punnett square para sa Grade 9.'));
        // Numbers stay strings (array keys would turn them into integers).
        self::assertSame(['150', 'mara', 'jeepney', 'stop'], Retrieval::keywords('Make it 150 words about Mara at the jeepney stop'));
    }

    public function testQuestionsWithNumbersHeadingsOrFilipinoFindTheNotes(): void
    {
        $andrea = $this->person('andrea.cruz');
        Auth::actingAs($andrea);
        $science = $this->classId(self::NARRA_SCI);

        // A question with a three-digit number used to fail with a type error.
        $found = Retrieval::search($andrea, 'Write my reading response about Mara at the jeepney stop. Make it 150 words.');
        self::assertNotEmpty($found);
        self::assertStringContainsString('Mara', $found[0]['content']);

        // A Filipino question about English notes is judged by its subject terms.
        $found = Retrieval::search($andrea, 'Ano ang ginagawa ng diaphragm kapag humihinga tayo?', $science);
        self::assertNotEmpty($found);
        self::assertStringContainsString('diaphragm', $found[0]['content']);

        // A passage's heading counts towards the match, and passages with more of the question's terms rank first.
        $found = Retrieval::search($andrea, 'What steps should I follow to make an inference?', $this->classId('English 9 – Narra'));
        self::assertSame('Steps for making an inference', $found[0]['section_title'] ?? null);
    }

    public function testTeacherRetrievalCoversOwnScheduledMaterialsOnly(): void
    {
        $ramon = $this->person('ramon.dizon');
        Auth::actingAs($ramon);
        $scheduled = $this->materialId('Module 2 review sheet', self::NARRA_SCI);
        $ids = array_map(static fn ($s) => (int) $s['material_id'], Retrieval::search($ramon, 'guinea pigs black fur white fur offspring'));
        self::assertContains($scheduled, $ids, 'Teachers can ground drafts in their own scheduled materials');
        self::assertSame([], Retrieval::search($ramon, 'discriminant nature of the roots trinomial'), 'Mathematics materials belong to another teacher');
    }

    public function testDemoCompanionAnswerCitesOnlyPermittedSources(): void
    {
        $janelle = $this->person('janelle.ocampo');
        Auth::actingAs($janelle);
        Providers::fake(new DemoProvider());
        try {
            $id = Db::insert('ai_conversations', [
                'organization_id' => (int) $janelle['organization_id'], 'user_id' => (int) $janelle['id'], 'class_id' => $this->classId(self::MOLAVE_SCI),
                'mode' => 'ask_materials', 'title' => 'New session', 'language' => 'en', 'last_message_at' => Db::now(), 'created_at' => Db::now(), 'updated_at' => Db::now(),
            ]);
            $events = [];
            Companion::turn($janelle, (array) Db::one('SELECT * FROM ai_conversations WHERE id = :id', ['id' => $id]), 'How does gas exchange happen in the alveoli?', static function (array $e) use (&$events): void {
                $events[] = $e;
            }, static fn (): bool => false);
        } finally {
            Providers::fake(null);
        }
        $done = array_values(array_filter($events, static fn ($e) => $e['type'] === 'done'));
        self::assertCount(1, $done, 'The turn finishes with a done event');
        $cited = Db::column(
            "SELECT DISTINCT m.class_id FROM ai_citations c JOIN ai_messages msg ON msg.id = c.message_id JOIN learning_materials m ON m.id = c.material_id WHERE msg.conversation_id = :c",
            ['c' => $id]
        );
        self::assertNotEmpty($cited);
        self::assertSame([$this->classId(self::MOLAVE_SCI)], array_map('intval', $cited));
        $answer = (string) Db::value("SELECT content FROM ai_messages WHERE conversation_id = :c AND role = 'assistant'", ['c' => $id]);
        self::assertStringContainsString('Demo mode', $answer, 'Demo answers are labelled as such');
    }
}
