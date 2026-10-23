<?php

declare(strict_types=1);

namespace Sibol\Tests\Integration;

use Sibol\Controllers\Teacher\InsightsController;
use Sibol\Core\Auth;
use Sibol\Core\Db;
use Sibol\Core\Request;
use Sibol\Services\Ai\Companion;
use Sibol\Services\Ai\DemoProvider;
use Sibol\Services\Ai\Providers;
use Sibol\Services\MaterialGaps;
use Sibol\Services\Privacy;

/**
 * "What your materials don't cover": key words of student questions the class materials did not answer, shown to
 * the class's teachers only as topics at least five different students asked about, never who asked or what.
 */
final class MaterialGapsTest extends IntegrationTestCase
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

    public function testAnUncoveredStudentQuestionKeepsOnlyItsKeyWords(): void
    {
        $andrea = $this->person('andrea.cruz');
        $science = $this->classId('Science 9 – Narra');
        $before = $this->gapIds($andrea);
        [$chat] = $this->ask($andrea, $science, 'explain', 'How do volcanoes erupt?');
        try {
            $new = array_values(array_diff($this->gapIds($andrea), $before));
            self::assertCount(1, $new);
            $row = Db::one('SELECT * FROM material_gaps WHERE id = :id', ['id' => $new[0]]);
            self::assertSame('volcanoes erupt', $row['keywords'], 'key words only, not the question');
            self::assertSame($science, (int) $row['class_id']);

            // Covered questions, greetings and summaries are not counted.
            $this->ask($andrea, $science, 'explain', 'Why are the walls of the alveoli so thin?', $chat);
            $this->ask($andrea, $science, 'explain', 'thank you!', $chat);
            self::assertCount(1, array_diff($this->gapIds($andrea), $before));
        } finally {
            Db::delete('ai_conversations', ['id' => $chat]);
            $this->deleteGaps(array_diff($this->gapIds($andrea), $before));
        }
    }

    public function testTeachersQuestionsAreNotCounted(): void
    {
        $ramon = $this->person('ramon.dizon');
        $count = static fn (): int => (int) Db::value('SELECT COUNT(*) FROM material_gaps');
        $before = $count();
        [$chat] = $this->ask($ramon, $this->classId('Science 9 – Narra'), 'explain_for_class', 'How do volcanoes erupt?');
        Db::delete('ai_conversations', ['id' => $chat]);
        self::assertSame($before, $count());
    }

    public function testATopicShowsOnlyOnceFiveDifferentStudentsAskedAndNeverWho(): void
    {
        $english = $this->classId('English 9 – Narra');
        $students = array_map('intval', Db::column("SELECT user_id FROM class_memberships WHERE class_id = :c AND role = 'student' ORDER BY user_id LIMIT 6", ['c' => $english]));
        $org = (int) Db::value('SELECT organization_id FROM classes WHERE id = :c', ['c' => $english]);
        $ids = [];
        $add = static function (int $student, string $keywords, int $daysAgo = 1) use ($org, $english, &$ids): void {
            $ids[] = Db::insert('material_gaps', ['organization_id' => $org, 'class_id' => $english, 'student_id' => $student,
                'keywords' => $keywords, 'created_at' => gmdate('Y-m-d H:i:s', time() - $daysAgo * 86400)]);
        };
        try {
            foreach (array_slice($students, 0, 5) as $s) {
                $add($s, 'figurative language simile');
            }
            $add($students[0], 'figurative language simile'); // the same student again adds a question, not a student
            foreach (array_slice($students, 0, 4) as $s) {
                $add($s, 'haiku');
            }
            $add($students[5], 'figurative grandmother'); // one student's own word never shows
            $add($students[5], 'sonnet', MaterialGaps::DAYS + 3); // too old for the report

            $topics = MaterialGaps::forClass($english);
            self::assertCount(1, $topics, 'four students are not enough');
            self::assertSame(['figurative', 'language', 'simile'], $topics[0]['words']);
            self::assertSame(6, $topics[0]['students']);
            self::assertSame(7, $topics[0]['questions']);
            self::assertSame(['words', 'students', 'questions', 'last_at'], array_keys($topics[0]), 'no student is named');

            Auth::actingAs($this->person('joy.santiago'));
            $html = (new InsightsController(new Request('GET', '/teacher/insights/' . $english)))->show($english)->body;
            self::assertStringContainsString('What your materials don&#039;t cover', $html);
            self::assertStringContainsString('figurative, language, simile', $html);
            self::assertStringContainsString('6 students asked', $html);
            self::assertStringNotContainsString('haiku', $html);
            self::assertStringNotContainsString('grandmother', $html);
            foreach ($students as $s) {
                $name = (string) Db::value('SELECT family_name FROM users WHERE id = :u', ['u' => $s]);
                self::assertStringNotContainsString('figurative, language, simile</span>' . $name, $html);
            }
        } finally {
            $this->deleteGaps($ids);
        }
    }

    public function testCountsAreErasedWithTheStudentAndRemovedWhenOld(): void
    {
        $english = $this->classId('English 9 – Narra');
        $org = (int) Db::value('SELECT organization_id FROM classes WHERE id = :c', ['c' => $english]);
        $student = (int) Db::value("SELECT user_id FROM class_memberships WHERE class_id = :c AND role = 'student' ORDER BY user_id DESC LIMIT 1", ['c' => $english]);
        $old = Db::insert('material_gaps', ['organization_id' => $org, 'class_id' => $english, 'student_id' => $student, 'keywords' => 'old topic',
            'created_at' => gmdate('Y-m-d H:i:s', time() - (MaterialGaps::KEEP_DAYS + 1) * 86400)]);
        $recent = Db::insert('material_gaps', ['organization_id' => $org, 'class_id' => $english, 'student_id' => $student, 'keywords' => 'recent topic', 'created_at' => Db::now()]);

        MaterialGaps::purge();
        self::assertNull(Db::one('SELECT id FROM material_gaps WHERE id = :id', ['id' => $old]));
        self::assertNotNull(Db::one('SELECT id FROM material_gaps WHERE id = :id', ['id' => $recent]));

        $export = json_decode((string) file_get_contents(\Sibol\Core\Uploads::absolute(Privacy::export($student))), true);
        self::assertSame('recent topic', end($export['uncovered_topic_words'])['keywords'], 'the student can see what was counted');
        Db::delete('material_gaps', ['id' => $recent]);
    }

    /** @return array{0:int} the conversation */
    private function ask(array $user, int $classId, string $mode, string $question, ?int $chat = null): array
    {
        Auth::actingAs($user);
        Providers::fake(new DemoProvider());
        $chat = $chat ?? Db::insert('ai_conversations', [
            'organization_id' => (int) $user['organization_id'], 'user_id' => (int) $user['id'], 'class_id' => $classId,
            'mode' => $mode, 'title' => 'Gap test', 'language' => 'en',
            'last_message_at' => Db::now(), 'created_at' => Db::now(), 'updated_at' => Db::now(),
        ]);
        Companion::turn($user, (array) Db::one('SELECT * FROM ai_conversations WHERE id = :id', ['id' => $chat]), $question,
            static function (array $e): void {
            }, static fn (): bool => false);
        return [$chat];
    }

    private function deleteGaps(array $ids): void
    {
        [$in, $params] = Db::in($ids, 'g');
        Db::exec('DELETE FROM material_gaps WHERE id IN ' . $in, $params);
    }

    /** @return list<int> */
    private function gapIds(array $user): array
    {
        return array_map('intval', Db::column('SELECT id FROM material_gaps WHERE student_id = :u', ['u' => (int) $user['id']]));
    }
}
