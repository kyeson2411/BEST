<?php

declare(strict_types=1);

namespace Sibol\Tests\Integration;

use Sibol\Core\Db;
use Sibol\Services\Competencies;
use Sibol\Services\Insights;
use Sibol\Services\SectionCopies;

/**
 * Item 17: the school's learning competencies (imported, never made up), tags on questions and lessons, and class
 * insights by competency. The demo school has five labelled samples (DEMO-SCI9-...) on its Science 9 work.
 */
final class CompetenciesTest extends IntegrationTestCase
{
    private const HEADER = "code,description,subject,grade_level,quarter\n";

    protected function tearDown(): void
    {
        Db::exec("DELETE FROM competencies WHERE code LIKE 'TEST-%'");
        parent::tearDown();
    }

    private function org(): int
    {
        return (int) $this->person('teresa.villanueva')['organization_id'];
    }

    public function testThePreviewSaysWhatEachRowWouldDoWithoutSavingAnything(): void
    {
        $csv = self::HEADER
            . "TEST-1,Describe the water cycle,Science,Grade 9,1\n"
            . "TEST-2,Compare mixtures and solutions,SCI,,Q2\n"
            . "DEMO-SCI9-Q1-02,Identify the parts of the heart and the blood vessels and describe what each one does.,Science,Grade 9,1\n"
            . "DEMO-SCI9-Q1-03,A new wording,Science,Grade 9,1\n"
            . ",No code here,,,\n"
            . "TEST 3,Spaces are not allowed in a code,,,\n"
            . "TEST-1,The same code again,,,\n"
            . "TEST-4,,,,\n"
            . "TEST-5,Unknown subject,Astrology,,\n"
            . "TEST-6,A fifth quarter,,,5\n";
        $before = (int) Db::value('SELECT COUNT(*) FROM competencies');
        $preview = Competencies::preview($this->org(), $csv);
        self::assertSame(['new' => 2, 'changed' => 1, 'same' => 1, 'problem' => 6], $preview['counts']);
        $rows = array_column($preview['rows'], null, 'line');
        self::assertSame(['new', 'Science', '1'], [$rows[2]['status'], $rows[2]['subject'], $rows[2]['quarter']]);
        self::assertSame(['new', 'Science', '2'], [$rows[3]['status'], $rows[3]['subject'], $rows[3]['quarter']], 'subject by its code, "Q2" read as 2');
        self::assertSame('same', $rows[4]['status']);
        self::assertSame('changed', $rows[5]['status']);
        self::assertSame(['No code'], $rows[6]['problems']);
        self::assertStringContainsString('letters, numbers', $rows[7]['problems'][0]);
        self::assertSame(['The same code is on line 2'], $rows[8]['problems']);
        self::assertSame(['No description'], $rows[9]['problems']);
        self::assertSame(['No subject called "Astrology"'], $rows[10]['problems']);
        self::assertSame(['The quarter must be 1 to 4'], $rows[11]['problems']);
        self::assertSame($before, (int) Db::value('SELECT COUNT(*) FROM competencies'), 'nothing saved yet');
    }

    public function testTheFileCanUseSemicolonsAndTheSchoolsOwnColumnNames(): void
    {
        $csv = "\xEF\xBB\xBFLC Code;Learning Competency;Learning area\nTEST-7;Explain energy transfer;Science\n";
        $preview = Competencies::preview($this->org(), $csv);
        self::assertSame(['new', 'TEST-7', 'Explain energy transfer', 'Science'], [$preview['rows'][0]['status'], $preview['rows'][0]['code'], $preview['rows'][0]['description'], $preview['rows'][0]['subject']]);
        self::assertSame('The first row must name the columns. Missing: description. Download the template to see the expected layout.', Competencies::preview($this->org(), "code,subject\nX,Science\n"));
        self::assertSame('The file has a header row but no competencies.', Competencies::preview($this->org(), self::HEADER));
    }

    public function testImportingAddsAndUpdatesButNeverDeletes(): void
    {
        $o = $this->org();
        $preview = Competencies::preview($o, self::HEADER . "TEST-1,First wording,Science,Grade 9,1\nTEST-2,Second,,,\n,broken,,,\n");
        self::assertSame(['added' => 2, 'updated' => 0], Competencies::import($o, $preview['rows']));
        Db::exec("UPDATE competencies SET archived_at = NOW() WHERE code = 'TEST-2'");

        $preview = Competencies::preview($o, self::HEADER . "TEST-1,Better wording,Science,Grade 9,1\nTEST-2,Second,,,\n");
        self::assertSame(['changed', 'changed'], array_column($preview['rows'], 'status'), 'an archived code in the file comes back');
        self::assertSame(['added' => 0, 'updated' => 2], Competencies::import($o, $preview['rows']));
        $rows = Db::all("SELECT code, description, archived_at FROM competencies WHERE code LIKE 'TEST-%' ORDER BY code");
        self::assertSame([['TEST-1', 'Better wording', null], ['TEST-2', 'Second', null]], array_map('array_values', $rows));
        self::assertSame(5, (int) Db::value("SELECT COUNT(*) FROM competencies WHERE code LIKE 'DEMO-%'"), 'codes missing from the file stay');
    }

    public function testAClassSeesItsSubjectsAndGradesCompetenciesOnly(): void
    {
        $o = $this->org();
        Competencies::import($o, Competencies::preview($o, self::HEADER
            . "TEST-ALL,Any subject and grade,,,\n"
            . "TEST-MATH,Mathematics only,Mathematics,Grade 9,1\n"
            . "TEST-G10,Science in Grade 10,Science,Grade 10,1\n")['rows']);
        $science = array_column(Competencies::forClass($this->classId('Science 9 – Narra')), 'code');
        self::assertContains('DEMO-SCI9-Q1-01', $science);
        self::assertContains('TEST-ALL', $science);
        self::assertNotContains('TEST-MATH', $science);
        self::assertNotContains('TEST-G10', $science);
        self::assertSame('DEMO-SCI9-Q1-01', Competencies::findForClass($this->classId('Science 9 – Narra'), ' demo-sci9-q1-01 ')['code']);
        self::assertNull(Competencies::findForClass($this->classId('Science 9 – Narra'), 'TEST-MATH'));
        self::assertNull(Competencies::findForClass($this->classId('Mathematics 9 – Narra'), 'DEMO-SCI9-Q1-01'));

        Db::exec("UPDATE competencies SET archived_at = NOW() WHERE code = 'TEST-ALL'");
        self::assertNotContains('TEST-ALL', array_column(Competencies::forClass($this->classId('Science 9 – Narra')), 'code'), 'archived ones cannot be chosen');
    }

    public function testALessonKeepsOnlyItsClasssCompetenciesAndArchivedTagsStay(): void
    {
        $o = $this->org();
        $classId = $this->classId('Science 9 – Narra');
        $lessonId = (int) Db::value("SELECT id FROM lessons WHERE title = 'Punnett squares in practice' AND class_id = :c", ['c' => $classId]);
        $ids = array_column(Db::all("SELECT id, code FROM competencies WHERE code LIKE 'DEMO-%'"), 'id', 'code');
        Competencies::import($o, Competencies::preview($o, self::HEADER . "TEST-MATH,Mathematics only,Mathematics,,\n")['rows']);
        $math = (int) Db::value("SELECT id FROM competencies WHERE code = 'TEST-MATH'");

        Competencies::tagLesson($lessonId, $classId, [$ids['DEMO-SCI9-Q2-01'], (string) $ids['DEMO-SCI9-Q2-02'], $math, 999999]);
        self::assertSame(['DEMO-SCI9-Q2-01', 'DEMO-SCI9-Q2-02'], array_column(Competencies::ofLesson($lessonId), 'code'));

        Db::update('competencies', ['archived_at' => Db::now()], ['id' => $ids['DEMO-SCI9-Q2-01']]);
        try {
            Competencies::tagLesson($lessonId, $classId, [$ids['DEMO-SCI9-Q2-02']]);
            self::assertSame(['DEMO-SCI9-Q2-01', 'DEMO-SCI9-Q2-02'], array_column(Competencies::ofLesson($lessonId), 'code'), 'not offered, so not removed');
        } finally {
            Db::update('competencies', ['archived_at' => null], ['id' => $ids['DEMO-SCI9-Q2-01']]);
            Competencies::tagLesson($lessonId, $classId, [$ids['DEMO-SCI9-Q2-02']]);
        }
    }

    public function testInsightsByCompetencyMatchTheTopicsTheyWereTaggedFrom(): void
    {
        $classId = $this->classId('Science 9 – Narra');
        $byCode = array_column(Insights::competencies($classId), null, 'code');
        $byTopic = array_column(Insights::topics($classId), null, 'topic');
        // The samples were tagged by topic: Breathing alone is DEMO-SCI9-Q1-03, Punnett squares alone DEMO-SCI9-Q2-02.
        foreach (['DEMO-SCI9-Q1-03' => 'Breathing', 'DEMO-SCI9-Q2-02' => 'Punnett squares'] as $code => $topic) {
            self::assertSame(
                [$byTopic[$topic]['attempts'], $byTopic[$topic]['students'], $byTopic[$topic]['correct'], $byTopic[$topic]['correct_pct']],
                [$byCode[$code]['attempts'], $byCode[$code]['students'], $byCode[$code]['correct'], $byCode[$code]['correct_pct']],
                $code
            );
        }
        // The heart and Blood vessels share DEMO-SCI9-Q1-02.
        self::assertSame($byTopic['The heart']['attempts'] + $byTopic['Blood vessels']['attempts'], $byCode['DEMO-SCI9-Q1-02']['attempts']);
        self::assertSame([1, 1], [$byCode['DEMO-SCI9-Q1-01']['lessons'], $byCode['DEMO-SCI9-Q2-02']['lessons']]);
        self::assertTrue($byCode['DEMO-SCI9-Q1-01']['is_sample']);
        self::assertLessThan(Insights::MIN_STUDENTS, $byCode['DEMO-SCI9-Q2-02']['students'], 'two students: too few to judge');

        // A competency on the list that nothing uses yet is listed without results.
        $o = $this->org();
        Competencies::import($o, Competencies::preview($o, self::HEADER . "TEST-NEW,Not taught yet,Science,Grade 9,3\n")['rows']);
        $new = array_column(Insights::competencies($classId), null, 'code')['TEST-NEW'];
        self::assertSame([0, 0, 0, null], [$new['lessons'], $new['questions'], $new['attempts'], $new['correct_pct']]);
        self::assertSame([], Insights::competencies($this->classId('Mathematics 9 – Narra')), 'no list for Mathematics');
    }

    public function testCopiesToAnotherSectionKeepTheTags(): void
    {
        $ramon = $this->person('ramon.dizon');
        $narra = $this->classId('Science 9 – Narra');
        $molave = $this->classId('Science 9 – Molave');
        $lessonId = (int) Db::value("SELECT id FROM lessons WHERE title = 'Gas exchange in the alveoli' AND class_id = :c", ['c' => $narra]);
        $quizId = (int) Db::value("SELECT id FROM assignments WHERE title = 'Heredity basics' AND class_id = :c", ['c' => $narra]);
        $copy = SectionCopies::lesson($ramon, $lessonId, $molave);
        $quizCopy = SectionCopies::assignment($ramon, $quizId, $molave);
        try {
            self::assertSame(['DEMO-SCI9-Q1-01'], array_column(Competencies::ofLesson((int) $copy['id']), 'code'));
            $tags = Db::column('SELECT competency_id FROM questions WHERE assignment_id = :a ORDER BY position', ['a' => $quizId]);
            self::assertNotContains(null, $tags);
            self::assertSame($tags, Db::column('SELECT competency_id FROM questions WHERE assignment_id = :a ORDER BY position', ['a' => (int) $quizCopy['id']]));
        } finally {
            // The copies are drafts in Molave that later tests do not expect.
            Db::exec('DELETE FROM lessons WHERE id = :l', ['l' => (int) $copy['id']]);
            Db::exec('DELETE FROM assignments WHERE id = :a', ['a' => (int) $quizCopy['id']]);
        }
    }
}
