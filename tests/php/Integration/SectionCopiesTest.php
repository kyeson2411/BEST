<?php

declare(strict_types=1);

namespace Sibol\Tests\Integration;

use Sibol\Core\Auth;
use Sibol\Core\Db;
use Sibol\Core\HttpException;
use Sibol\Services\Ai\Retrieval;
use Sibol\Services\Assessments;
use Sibol\Services\Materials;
use Sibol\Services\SectionCopies;

/**
 * "Copy to other sections": copies arrive as drafts in active classes the teacher teaches, carry their
 * questions, rubrics and linked materials, and never let one section's material reach another section.
 */
final class SectionCopiesTest extends IntegrationTestCase
{
    private const NARRA = 'Science 9 – Narra';
    private const MOLAVE = 'Science 9 – Molave';
    private const HEREDITY_QUERY = 'Mendel pea plants tall short alleles';

    protected function tearDown(): void
    {
        // The demo school has no copies of its own, so everything copied here can be removed.
        Db::exec('DELETE FROM assignments WHERE copied_from_id IS NOT NULL');
        Db::exec('DELETE FROM lessons WHERE copied_from_id IS NOT NULL');
        Db::exec('DELETE FROM learning_materials WHERE copied_from_id IS NOT NULL');
        parent::tearDown();
    }

    public function testCopiedMaterialIsADraftWithItsOwnPassagesInTheMatchingModule(): void
    {
        $ramon = $this->actingAs('ramon.dizon');
        $source = $this->materialId('Heredity notes', self::NARRA);
        $result = SectionCopies::material($ramon, $source, $this->classId(self::MOLAVE));

        $copy = Db::one('SELECT m.*, mo.title AS module_title FROM learning_materials m LEFT JOIN modules mo ON mo.id = m.module_id WHERE m.id = :id', ['id' => $result['id']]);
        self::assertSame('draft', $copy['visibility']);
        self::assertSame($this->classId(self::MOLAVE), (int) $copy['class_id']);
        self::assertSame('Module 2: Heredity and variation', $copy['module_title'], 'The module is matched by title in the other section');
        self::assertSame($source, (int) $copy['copied_from_id']);
        self::assertSame(self::MOLAVE, $result['class_name']);

        $from = Db::one('SELECT v.* FROM material_versions v JOIN learning_materials m ON m.current_version_id = v.id WHERE m.id = :m', ['m' => $source]);
        $to = Db::one('SELECT * FROM material_versions WHERE id = :v', ['v' => (int) $copy['current_version_id']]);
        self::assertNotSame((int) $from['id'], (int) $to['id']);
        self::assertSame($from['stored_path'], $to['stored_path'], 'The stored file is reused');
        self::assertSame($from['sha256'], $to['sha256']);
        self::assertSame('ready', $to['processing_status'], 'Text that was read already comes along');

        $sourceChunks = (int) Db::value('SELECT COUNT(*) FROM material_chunks WHERE material_version_id = :v', ['v' => (int) $from['id']]);
        $copyChunks = Db::all('SELECT class_id, material_id FROM material_chunks WHERE material_version_id = :v', ['v' => (int) $to['id']]);
        self::assertGreaterThan(0, $sourceChunks);
        self::assertCount($sourceChunks, $copyChunks);
        foreach ($copyChunks as $chunk) {
            self::assertSame($this->classId(self::MOLAVE), (int) $chunk['class_id']);
            self::assertSame($result['id'], (int) $chunk['material_id']);
        }
    }

    public function testACopyFollowsItsOwnSectionsVisibilityInRetrieval(): void
    {
        $ramon = $this->actingAs('ramon.dizon');
        $janelle = $this->person('janelle.ocampo');
        $andrea = $this->person('andrea.cruz');
        $source = $this->materialId('Heredity notes', self::NARRA);
        $copy = SectionCopies::material($ramon, $source, $this->classId(self::MOLAVE))['id'];

        self::assertSame([], Retrieval::search($janelle, self::HEREDITY_QUERY), 'A draft copy, and the other section\'s original, stay out of reach');

        Materials::publish($copy, false);
        $found = Retrieval::search($janelle, self::HEREDITY_QUERY);
        self::assertNotEmpty($found, 'Once published in Molave, Molave students can use the copy');
        foreach ($found as $s) {
            self::assertSame($copy, $s['material_id'], 'Molave retrieval returns only the Molave copy');
        }
        foreach (Retrieval::search($andrea, self::HEREDITY_QUERY) as $s) {
            self::assertNotSame($copy, $s['material_id'], 'Narra retrieval never returns the Molave copy');
        }
    }

    public function testCopiesOfScheduledOrCompanionOffMaterialsStayHidden(): void
    {
        $ramon = $this->actingAs('ramon.dizon');
        $janelle = $this->person('janelle.ocampo');
        $molave = $this->classId(self::MOLAVE);

        $scheduled = SectionCopies::material($ramon, $this->materialId('Module 2 review sheet', self::NARRA), $molave)['id'];
        $labSafety = SectionCopies::material($ramon, $this->materialId('Lab safety reminders', self::NARRA), $molave)['id'];
        Materials::publish($scheduled, false);
        Materials::publish($labSafety, false);

        self::assertGreaterThan(Db::now(), (string) Db::value('SELECT release_at FROM learning_materials WHERE id = :m', ['m' => $scheduled]), 'The release date comes along');
        self::assertSame(0, (int) Db::value('SELECT ai_enabled FROM learning_materials WHERE id = :m', ['m' => $labSafety]), 'Companion use stays off');
        foreach (Retrieval::search($janelle, 'guinea pigs black fur white fur offspring') as $s) {
            self::assertNotSame($scheduled, $s['material_id']);
        }
        foreach (Retrieval::search($janelle, 'tie back long hair bags under the table') as $s) {
            self::assertNotSame($labSafety, $s['material_id']);
        }
    }

    public function testModulesWithoutAMatchFallBackToOtherMaterials(): void
    {
        $ramon = $this->actingAs('ramon.dizon');
        $narra = $this->classId(self::NARRA);
        $module = Db::insert('modules', ['class_id' => $narra, 'title' => 'Module 9: Only in Narra', 'position' => 9, 'created_at' => Db::now(), 'updated_at' => Db::now()]);
        $source = $this->materialId('Heredity notes', self::NARRA);
        $original = (int) Db::value('SELECT module_id FROM learning_materials WHERE id = :m', ['m' => $source]);
        Db::update('learning_materials', ['module_id' => $module], ['id' => $source]);
        try {
            $copy = SectionCopies::material($ramon, $source, $this->classId(self::MOLAVE))['id'];
            self::assertNull(Db::value('SELECT module_id FROM learning_materials WHERE id = :m', ['m' => $copy]));
        } finally {
            Db::update('learning_materials', ['module_id' => $original], ['id' => $source]);
            Db::delete('modules', ['id' => $module]);
        }
    }

    public function testAssignmentCopyKeepsTheRubricAndUsesTheSectionsOwnCopyOfLinkedMaterials(): void
    {
        $ramon = $this->actingAs('ramon.dizon');
        $lab = (int) Db::value("SELECT id FROM assignments WHERE title = 'Lab report: breathing rate before and after exercise'");
        $result = SectionCopies::assignment($ramon, $lab, $this->classId(self::MOLAVE));
        $copy = Db::one('SELECT * FROM assignments WHERE id = :id', ['id' => $result['id']]);

        self::assertSame('draft', $copy['status']);
        self::assertNull($copy['current_version_id']);
        self::assertSame((string) Db::value('SELECT rubric FROM assignments WHERE id = :a', ['a' => $lab]), (string) $copy['rubric']);
        self::assertSame('Module 1: Respiratory and circulatory systems', (string) Db::value('SELECT title FROM modules WHERE id = :m', ['m' => (int) $copy['module_id']]));
        // Molave already has the same notes (uploaded there separately), so nothing new is copied.
        self::assertSame([], $result['also_copied']);
        self::assertSame(
            [$this->materialId('Breathing and circulation notes', self::MOLAVE)],
            array_map('intval', Db::column('SELECT material_id FROM assignment_resources WHERE assignment_id = :a', ['a' => $result['id']]))
        );
    }

    public function testQuizCopyKeepsQuestionsAndCopiesAMissingLinkedMaterialOnce(): void
    {
        $ramon = $this->actingAs('ramon.dizon');
        $molave = $this->classId(self::MOLAVE);
        $quiz = (int) Db::value("SELECT id FROM assignments WHERE title = 'Heredity basics'");
        $result = SectionCopies::assignment($ramon, $quiz, $molave);

        $fields = static fn (int $id): array => Db::all('SELECT qtype, prompt, points, options, answer_key, explanation, topic FROM questions WHERE assignment_id = :a ORDER BY position, id', ['a' => $id]);
        self::assertSame($fields($quiz), $fields($result['id']));
        self::assertSame(['Heredity notes'], $result['also_copied'], 'Molave had no heredity notes, so they are copied along');
        $linked = (int) Db::value('SELECT material_id FROM assignment_resources WHERE assignment_id = :a', ['a' => $result['id']]);
        $material = Db::one('SELECT * FROM learning_materials WHERE id = :m', ['m' => $linked]);
        self::assertSame($molave, (int) $material['class_id']);
        self::assertSame('draft', $material['visibility']);

        $again = SectionCopies::assignment($ramon, $quiz, $molave);
        self::assertSame([], $again['also_copied'], 'A second copy reuses the material copied the first time');
        self::assertSame($linked, (int) Db::value('SELECT material_id FROM assignment_resources WHERE assignment_id = :a', ['a' => $again['id']]));
    }

    public function testUnapprovedAiQuestionsStayUnapprovedInTheCopy(): void
    {
        $ramon = $this->actingAs('ramon.dizon');
        $draft = (int) Db::value("SELECT id FROM assignments WHERE title = 'Concept map: from air to cell'");
        $points = Db::value('SELECT points_possible FROM assignments WHERE id = :a', ['a' => $draft]);
        [$row] = Assessments::normaliseQuestion(['qtype' => 'true_false', 'prompt' => 'Oxygen moves from the alveoli into the blood.', 'correct' => 'true', 'points' => 1]);
        $question = Assessments::addQuestion($draft, $row, true);
        try {
            $copy = SectionCopies::assignment($ramon, $draft, $this->classId(self::MOLAVE))['id'];
            $copied = Db::one('SELECT ai_generated, reviewed FROM questions WHERE assignment_id = :a', ['a' => $copy]);
            self::assertSame(1, (int) $copied['ai_generated']);
            self::assertSame(0, (int) $copied['reviewed']);
            $problems = Assessments::publishProblems(Db::one('SELECT * FROM assignments WHERE id = :a', ['a' => $copy]));
            self::assertContains('Review 1 AI-drafted question before publishing.', $problems);
        } finally {
            Db::delete('questions', ['id' => $question]);
            Db::update('assignments', ['points_possible' => $points], ['id' => $draft]);
        }
    }

    public function testLessonCopyIsADraftWhoseSourcesPointToTheSectionsOwnMaterials(): void
    {
        $ramon = $this->actingAs('ramon.dizon');
        $lesson = (int) Db::value("SELECT id FROM lessons WHERE title = 'Gas exchange in the alveoli'");
        $result = SectionCopies::lesson($ramon, $lesson, $this->classId(self::MOLAVE));
        $copy = Db::one('SELECT * FROM lessons WHERE id = :id', ['id' => $result['id']]);

        self::assertSame('draft', $copy['status']);
        self::assertNull($copy['published_content'], 'Students see nothing until the teacher publishes the copy');
        $molaveNotes = $this->materialId('Breathing and circulation notes', self::MOLAVE);
        $content = json_decode((string) $copy['draft_content'], true);
        self::assertSame($molaveNotes, $content['sources'][0]['material_id']);
        self::assertSame(1, $content['sources'][0]['n'], 'Citation numbers are kept');
        self::assertSame([$molaveNotes], json_decode((string) $copy['source_material_ids'], true));
    }

    public function testOnlyActiveClassesTheTeacherTeachesCanReceiveCopies(): void
    {
        $ramon = $this->actingAs('ramon.dizon');
        $source = $this->materialId('Heredity notes', self::NARRA);
        $archived = Db::insert('classes', [
            'organization_id' => (int) $ramon['organization_id'], 'subject_id' => (int) Db::value('SELECT subject_id FROM classes WHERE id = :c', ['c' => $this->classId(self::NARRA)]),
            'name' => 'Science 9 – Last year', 'status' => 'archived', 'created_at' => Db::now(), 'updated_at' => Db::now(),
        ]);
        Db::insert('class_memberships', ['class_id' => $archived, 'user_id' => (int) $ramon['id'], 'role' => 'teacher', 'joined_at' => Db::now()]);
        try {
            $targets = array_column(SectionCopies::targets($ramon, 'material', ['id' => $source, 'class_id' => $this->classId(self::NARRA)]), 'name');
            self::assertSame([self::MOLAVE], $targets, 'Only his other active section is offered');
            foreach ([$this->classId('Mathematics 9 – Molave'), $this->classId(self::NARRA), $archived] as $classId) {
                try {
                    SectionCopies::material($ramon, $source, $classId);
                    self::fail('Copying into class ' . $classId . ' should be refused');
                } catch (HttpException $e) {
                    self::assertSame(404, $e->status);
                }
            }
        } finally {
            Db::delete('classes', ['id' => $archived]);
        }
        self::assertSame(0, (int) Db::value('SELECT COUNT(*) FROM learning_materials WHERE copied_from_id IS NOT NULL'));
    }

    public function testAnotherTeacherCannotCopySomeoneElsesMaterial(): void
    {
        $liza = $this->actingAs('liza.mercado');
        $this->expectException(HttpException::class);
        SectionCopies::material($liza, $this->materialId('Heredity notes', self::NARRA), $this->classId('Mathematics 9 – Molave'));
    }

    public function testThePanelListsCopiesOnlyInTheTeachersOwnClasses(): void
    {
        $ramon = $this->actingAs('ramon.dizon');
        $source = $this->materialId('Heredity notes', self::NARRA);
        SectionCopies::material($ramon, $source, $this->classId(self::MOLAVE));
        // A copy someone made in a class Ramon does not teach must not be listed to him.
        Db::insert('learning_materials', [
            'organization_id' => (int) $ramon['organization_id'], 'class_id' => $this->classId('Mathematics 9 – Narra'), 'title' => 'Heredity notes',
            'kind' => 'link', 'url' => 'https://example.org/notes', 'visibility' => 'draft', 'copied_from_id' => $source, 'created_at' => Db::now(), 'updated_at' => Db::now(),
        ]);
        $panel = SectionCopies::panel($ramon, 'material', ['id' => $source, 'class_id' => $this->classId(self::NARRA)]);
        self::assertSame([self::MOLAVE], array_column($panel['copies'], 'class_name'));
        self::assertSame(1, $panel['targets'][0]['copies']);
        self::assertSame('/teacher/materials/' . $source . '/copy', $panel['action']);
    }

    public function testCopiesAreAuditedWithoutContent(): void
    {
        $ramon = $this->actingAs('ramon.dizon');
        $source = $this->materialId('Heredity notes', self::NARRA);
        $copy = SectionCopies::material($ramon, $source, $this->classId(self::MOLAVE))['id'];
        $log = Db::one("SELECT * FROM audit_logs WHERE action = 'material.copied' AND target_id = :t", ['t' => $copy]);
        self::assertNotNull($log);
        self::assertSame((int) $ramon['id'], (int) $log['actor_id']);
        self::assertSame(['from' => $source, 'class_id' => $this->classId(self::MOLAVE)], json_decode((string) $log['metadata'], true));
    }

    private function actingAs(string $emailLocal): array
    {
        $user = $this->person($emailLocal);
        Auth::actingAs($user);
        return $user;
    }
}
