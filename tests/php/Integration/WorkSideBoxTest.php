<?php

declare(strict_types=1);

namespace Sibol\Tests\Integration;

use Sibol\Controllers\Student\AssignmentController;
use Sibol\Core\Auth;
use Sibol\Core\Db;
use Sibol\Core\Request;
use Sibol\Services\Assessments;

/**
 * The side box on a student's work page says what already happened (feedback released, answers shown below),
 * and "Get a hint" is offered only while the work can still be done. A quiz offers studying before it, never hints,
 * and says the companion is paused while it is being taken.
 */
final class WorkSideBoxTest extends IntegrationTestCase
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

    private function page(string $username, string $title): string
    {
        Auth::actingAs($this->person($username));
        $id = (int) Db::value(
            "SELECT a.id FROM assignments a JOIN classes c ON c.id = a.class_id WHERE a.title = :t AND c.name LIKE '%Narra'",
            ['t' => $title]
        );
        self::assertGreaterThan(0, $id, $title);
        return (new AssignmentController(new Request('GET', '/student/assignments/' . $id)))->show($id)->body;
    }

    public function testGradedQuizSaysItWasReleasedAndOffersNoHint(): void
    {
        $body = $this->page('andrea.cruz', 'Respiratory and circulatory systems check');
        self::assertMatchesRegularExpression('/<dt>Feedback<\/dt><dd>Released [^<]+<\/dd>/', $body);
        self::assertStringContainsString('<dt>Answers</dt><dd>Shown below with your answers.</dd>', $body);
        self::assertStringNotContainsString('Shown when your teacher releases it.', $body);
        self::assertStringNotContainsString('Correct answers appear after the due date.', $body);
        self::assertStringNotContainsString('Get a hint', $body);
    }

    public function testScoredPaperTestOffersNoHint(): void
    {
        $body = $this->page('andrea.cruz', 'Short test on paper: breathing and circulation');
        self::assertMatchesRegularExpression('/<dt>Feedback<\/dt><dd>Released [^<]+<\/dd>/', $body);
        self::assertStringNotContainsString('Get a hint', $body);
    }

    public function testWorkThatCanStillBeDoneOffersAHint(): void
    {
        $journal = $this->page('andrea.cruz', 'Inference journal: three clues');
        self::assertStringContainsString('Get a hint', $journal);
        self::assertStringContainsString('<dt>Feedback</dt><dd>Shown when your teacher releases it.</dd>', $journal);
        self::assertStringContainsString('Get a hint', $this->page('andrea.cruz', 'Performance task in class: model of the lungs'), 'Before the day in class');
    }

    public function testAStudentWhoseAiIsOffIsNeverOfferedAHint(): void
    {
        $andrea = $this->person('andrea.cruz');
        Db::update('users', ['ai_enabled' => 0], ['id' => (int) $andrea['id']]);
        try {
            self::assertStringNotContainsString('Get a hint', $this->page('andrea.cruz', 'Inference journal: three clues'));
        } finally {
            Db::update('users', ['ai_enabled' => null], ['id' => (int) $andrea['id']]);
        }
    }

    public function testAnOpenQuizOffersStudyingBeforeItAndNoHints(): void
    {
        $before = $this->page('gabriel.torres', 'Heredity basics');
        self::assertStringContainsString('Study this topic', $before);
        self::assertStringContainsString('mode=explain', $before);
        self::assertStringNotContainsString('Get a hint', $before);

        $gabriel = $this->person('gabriel.torres');
        $quizId = (int) Db::value("SELECT id FROM assignments WHERE title = 'Heredity basics'");
        Auth::actingAs($gabriel);
        $sub = Assessments::startAttempt(Assessments::forStudent((int) $gabriel['id'], $quizId), (int) $gabriel['id']);
        try {
            $during = $this->page('gabriel.torres', 'Heredity basics');
            self::assertStringContainsString('Companion paused', $during);
            self::assertStringNotContainsString('Study this topic', $during);
            self::assertStringNotContainsString('Get a hint', $during);
        } finally {
            Db::delete('submissions', ['id' => (int) $sub['id']]);
        }
    }
}
