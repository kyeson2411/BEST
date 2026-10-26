<?php

declare(strict_types=1);

namespace Sibol\Tests\Integration;

use Sibol\Controllers\Student\AssignmentController as StudentWork;
use Sibol\Controllers\Student\ClassController;
use Sibol\Controllers\Teacher\AssignmentController as TeacherWork;
use Sibol\Core\Auth;
use Sibol\Core\Db;
use Sibol\Core\Request;
use Sibol\Services\Navigation;
use Sibol\Services\Overrides;

/**
 * Quizzes have their own page in the menu, for students and teachers, instead of sharing one list with assignments.
 * Every list tags each piece of work with what it is, and a quiz's own pages light up Quizzes in the menu.
 */
final class QuizzesPageTest extends IntegrationTestCase
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

    private function workId(string $title): int
    {
        $id = (int) Db::value("SELECT a.id FROM assignments a JOIN classes c ON c.id = a.class_id WHERE a.title = :t AND c.name = 'Science 9 – Narra'", ['t' => $title]);
        self::assertGreaterThan(0, $id, $title);
        return $id;
    }

    /** @return array<string,int> menu URL => count */
    private function navCounts(array $user): array
    {
        $counts = [];
        foreach (Navigation::items($user) as $item) {
            $counts[$item['url']] = (int) ($item['count'] ?? 0);
        }
        return $counts;
    }

    public function testStudentsGetAQuizzesPageAndAssignmentsNoLongerListQuizzes(): void
    {
        Auth::actingAs($this->person('andrea.cruz'));
        $quizzes = (new StudentWork(new Request('GET', '/student/quizzes', ['filter' => 'all'])))->quizzes()->body;
        self::assertStringContainsString('<h1 class="page-title">Quizzes</h1>', $quizzes);
        self::assertStringContainsString('Heredity basics', $quizzes);
        self::assertStringContainsString('Respiratory and circulatory systems check', $quizzes);
        self::assertStringNotContainsString('Inference journal: three clues', $quizzes);
        self::assertStringNotContainsString('tag--assignment', $quizzes);
        self::assertStringNotContainsString('tag--paper', $quizzes);

        $assignments = (new StudentWork(new Request('GET', '/student/assignments', ['filter' => 'all'])))->index()->body;
        self::assertStringContainsString('Inference journal: three clues', $assignments);
        self::assertStringContainsString('Short test on paper: breathing and circulation', $assignments);
        self::assertStringNotContainsString('Heredity basics', $assignments);
        self::assertStringNotContainsString('tag--quiz', $assignments);
        self::assertStringContainsString('Quizzes are on their own page.', $assignments);
    }

    public function testAnOpenQuizInTheListGoesStraightToItsStartPage(): void
    {
        Auth::actingAs($this->person('gabriel.torres'));
        $quizzes = (new StudentWork(new Request('GET', '/student/quizzes')))->quizzes()->body;
        self::assertMatchesRegularExpression('#href="[^"]*/student/assignments/' . $this->workId('Heredity basics') . '">Start quiz<span class="sr-only">: Heredity basics</span>#', $quizzes);
    }

    public function testTheQuizzesMenuCountsOnlyQuizzes(): void
    {
        $gabriel = $this->person('gabriel.torres');
        $before = $this->navCounts($gabriel);
        self::assertArrayHasKey('/student/quizzes', $before);
        self::assertGreaterThanOrEqual(1, $before['/student/quizzes'], 'Heredity basics is due within the week');

        // Excusing him from the quiz takes it off the Quizzes count and leaves the Assignments count alone.
        $ramon = $this->person('ramon.dizon');
        $quiz = Db::one('SELECT * FROM assignments WHERE id = :id', ['id' => $this->workId('Heredity basics')]);
        Overrides::save($ramon, $quiz, (int) $gabriel['id'], null, 0, true);
        try {
            $after = $this->navCounts($gabriel);
            self::assertSame($before['/student/quizzes'] - 1, $after['/student/quizzes']);
            self::assertSame($before['/student/assignments'], $after['/student/assignments']);
        } finally {
            Overrides::remove($ramon, $quiz, (int) $gabriel['id']);
        }
    }

    public function testAQuizPageLightsUpQuizzesInTheMenuAndTheBreadcrumb(): void
    {
        Auth::actingAs($this->person('andrea.cruz'));
        $id = $this->workId('Heredity basics');
        $page = (new StudentWork(new Request('GET', '/student/assignments/' . $id)))->show($id)->body;
        self::assertMatchesRegularExpression('#<a class="nav-link" href="[^"]*/student/quizzes" aria-current="page"#', $page);
        self::assertDoesNotMatchRegularExpression('#<a class="nav-link" href="[^"]*/student/assignments" aria-current#', $page);
        self::assertMatchesRegularExpression('#<nav class="crumbs"[^>]*>\s*<a href="[^"]*/student/quizzes">Quizzes</a>#', $page);
        self::assertStringContainsString('tag--quiz', $page);

        $journal = $this->workId('Lab report: breathing rate before and after exercise');
        $page = (new StudentWork(new Request('GET', '/student/assignments/' . $journal)))->show($journal)->body;
        self::assertMatchesRegularExpression('#<a class="nav-link" href="[^"]*/student/assignments" aria-current="page"#', $page);
        self::assertMatchesRegularExpression('#<nav class="crumbs"[^>]*>\s*<a href="[^"]*/student/assignments">Assignments</a>#', $page);
    }

    public function testAClassPageHasItsOwnQuizzesTab(): void
    {
        Auth::actingAs($this->person('andrea.cruz'));
        $classId = $this->classId('Science 9 – Narra');
        $quizzes = (new ClassController(new Request('GET', '/student/classes/' . $classId, ['tab' => 'quizzes'])))->show($classId)->body;
        self::assertStringContainsString('Heredity basics', $quizzes);
        self::assertStringNotContainsString('Lab report: breathing rate before and after exercise', $quizzes);

        $work = (new ClassController(new Request('GET', '/student/classes/' . $classId, ['tab' => 'work'])))->show($classId)->body;
        self::assertStringContainsString('Lab report: breathing rate before and after exercise', $work);
        self::assertStringNotContainsString('Heredity basics', $work);
    }

    public function testTeachersGetAQuizzesPageToo(): void
    {
        Auth::actingAs($this->person('ramon.dizon'));
        self::assertContains('/teacher/quizzes', array_column(Navigation::items($this->person('ramon.dizon')), 'url'));

        $quizzes = (new TeacherWork(new Request('GET', '/teacher/quizzes')))->quizzes()->body;
        self::assertStringContainsString('Heredity basics', $quizzes);
        self::assertStringContainsString('New quiz', $quizzes);
        self::assertStringNotContainsString('Lab report: breathing rate before and after exercise', $quizzes);
        self::assertStringNotContainsString('id="a-kind"', $quizzes, 'No type filter on a page of quizzes');

        $assignments = (new TeacherWork(new Request('GET', '/teacher/assignments')))->index()->body;
        self::assertStringContainsString('Lab report: breathing rate before and after exercise', $assignments);
        self::assertStringContainsString('Short test on paper: breathing and circulation', $assignments);
        self::assertStringNotContainsString('Heredity basics', $assignments);
        self::assertStringNotContainsString('value="quiz"', $assignments);

        $id = $this->workId('Heredity basics');
        $page = (new TeacherWork(new Request('GET', '/teacher/assignments/' . $id)))->show($id)->body;
        self::assertMatchesRegularExpression('#<a class="nav-link" href="[^"]*/teacher/quizzes" aria-current="page"#', $page);
        self::assertMatchesRegularExpression('#<nav class="crumbs"[^>]*>\s*<a href="[^"]*/teacher/quizzes">Quizzes</a>#', $page);
    }

    public function testTheOldQuizFilterAndQuizLinkLeadToTheQuizzesPage(): void
    {
        Auth::actingAs($this->person('ramon.dizon'));
        $classId = $this->classId('Science 9 – Narra');
        $old = (new TeacherWork(new Request('GET', '/teacher/assignments', ['kind' => 'quiz', 'class_id' => (string) $classId])))->index();
        self::assertSame(303, $old->status);
        self::assertStringEndsWith('/teacher/quizzes?class_id=' . $classId, $old->headers['Location']);

        $assessments = (new TeacherWork(new Request('GET', '/teacher/assessments')))->assessments();
        self::assertStringEndsWith('/teacher/quizzes', $assessments->headers['Location']);
    }

    public function testKindTagsSayWhatEachPieceOfWorkIs(): void
    {
        self::assertStringContainsString('#i-list-checks', kind_tag('quiz'));
        self::assertStringContainsString('>Quiz</span>', kind_tag('quiz'));
        self::assertStringContainsString('>Assignment</span>', kind_tag('assignment'));
        self::assertStringContainsString('>In class / on paper</span>', kind_tag('paper'));
        self::assertSame([t('Quizzes'), '/teacher/quizzes'], work_list('quiz', 'teacher'));
        self::assertSame([t('Assignments'), '/student/assignments'], work_list('paper', 'student'));
    }
}
