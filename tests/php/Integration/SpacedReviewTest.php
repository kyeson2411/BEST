<?php

declare(strict_types=1);

namespace Sibol\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use Sibol\Core\Db;
use Sibol\Core\HttpException;
use Sibol\Services\Assessments;
use Sibol\Services\Settings;
use Sibol\Services\SpacedReview;

/** Item 9: the review queue. The clock is passed in, so each test moves "now" day by day. */
final class SpacedReviewTest extends IntegrationTestCase
{
    public function testIntervalsGrowWhenRightAndDropBackOneStepWhenMissed(): void
    {
        $today = '2026-10-21';
        $step = 0;
        $dates = [];
        for ($i = 0; $i < 5; $i++) {
            $next = SpacedReview::next($step, true, $today);
            $dates[] = $next['due_on'];
            $step = $next['step'];
        }
        self::assertSame(['2026-10-22', '2026-10-24', '2026-10-28', '2026-11-04', '2026-11-20'], $dates, '1, 3, 7, 14 and 30 days');
        self::assertSame(['step' => 5, 'due_on' => null, 'done' => true], SpacedReview::next(5, true, $today), 'right after the last interval: learned');
        self::assertSame(['step' => 3, 'due_on' => '2026-10-22', 'done' => false], SpacedReview::next(4, false, $today), 'a miss goes back one step, not to the start, and returns tomorrow');
        self::assertSame(0, SpacedReview::next(0, false, $today)['step']);
    }

    public function testQuizMissesJoinOnceTheAnswersAreReleasedAndAreCheckedBySibol(): void
    {
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $s = $this->school();
            $now = $this->manila('2026-10-21 09:00');
            self::assertSame(0, SpacedReview::sync($s['student'], $now), 'answers not released yet');

            Db::update('assignments', ['answers_released_at' => '2026-10-20 00:00:00'], ['id' => $s['quiz']]);
            self::assertSame(2, SpacedReview::sync($s['student'], $now), 'the wrong choice and the wrong short answer; not the right one or the essay');
            self::assertSame(0, SpacedReview::sync($s['student'], $now), 'added once');

            $queue = SpacedReview::queue($s['student'], $now);
            self::assertSame(['choice', 'text'], array_column($queue, 'kind'));
            $choice = SpacedReview::content($queue[0]);
            self::assertSame(['Veins', 'Arteries', 'Capillaries'], $choice['choices']);
            self::assertFalse(SpacedReview::check($choice, ['choice' => '0']));
            self::assertTrue(SpacedReview::check($choice, ['choice' => '1']));
            self::assertFalse(SpacedReview::check($choice, ['knew' => '1']), 'choices are checked by Sibol, never self-marked');
            $text = SpacedReview::content($queue[1]);
            self::assertTrue(SpacedReview::check($text, ['text' => '  Capillaries. ']), 'spacing, a full stop and case do not matter');
            self::assertFalse(SpacedReview::check($text, ['text' => 'veins']));

            $result = SpacedReview::answer($s['student'], (int) $queue[0]['id'], ['choice' => '1'], $now);
            self::assertTrue($result['right']);
            self::assertSame('Arteries', $result['answer']);
            self::assertSame('2026-10-22', $result['due_on']);
            $result = SpacedReview::answer($s['student'], (int) $queue[1]['id'], ['text' => 'veins'], $now);
            self::assertFalse($result['right']);
            self::assertSame('capillaries', $result['answer']);
            self::assertSame('2026-10-22', $result['due_on']);
            self::assertSame([], SpacedReview::queue($s['student'], $now), 'nothing more today');
            self::assertCount(2, SpacedReview::queue($s['student'], $this->manila('2026-10-22 09:00')), 'both are back tomorrow');

            // A student who leaves the class no longer sees its questions.
            Db::exec("UPDATE class_memberships SET status = 'withdrawn' WHERE user_id = :u", ['u' => (int) $s['student']['id']]);
            self::assertSame([], SpacedReview::queue($s['student'], $this->manila('2026-10-22 09:00')));
            $this->expectException(HttpException::class);
            SpacedReview::answer($s['student'], (int) $queue[0]['id'], ['choice' => '1'], $this->manila('2026-10-22 09:00'));
        } finally {
            $pdo->rollBack();
            Settings::flush();
        }
    }

    public function testDueReviewsComeBeforeNewOnesWithinTheDailyNumber(): void
    {
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $s = $this->school();
            $now = $this->manila('2026-10-21 09:00');
            $item = function (string $key, int $reviews, string $due, ?string $reviewedOn = null) use ($s): int {
                return Db::insert('review_items', ['organization_id' => $s['org'], 'user_id' => (int) $s['student']['id'], 'class_id' => $s['class'],
                    'source' => 'practice', 'source_key' => $key, 'kind' => 'card', 'content' => json_encode(['kind' => 'card', 'prompt' => $key, 'back' => 'Back']),
                    'step' => $reviews ? 2 : 0, 'reviews' => $reviews, 'due_on' => $due, 'last_reviewed_on' => $reviewedOn]);
            };
            $newToday = $item('new today', 0, '2026-10-21');
            $dueToday = $item('due today', 2, '2026-10-21');
            $newEarlier = $item('new earlier', 0, '2026-10-19');
            $overdue = $item('overdue', 3, '2026-10-18');
            $later = $item('next week', 4, '2026-10-28');
            self::assertSame([$overdue, $dueToday, $newEarlier, $newToday], array_map('intval', array_column(SpacedReview::queue($s['student'], $now), 'id')));

            Settings::override($s['org'], 'review', ['daily_cap' => 3]);
            self::assertCount(3, SpacedReview::queue($s['student'], $now));
            Db::update('review_items', ['last_reviewed_on' => '2026-10-21'], ['id' => $later]);
            self::assertSame([$overdue, $dueToday], array_map('intval', array_column(SpacedReview::queue($s['student'], $now), 'id')), 'one already reviewed today counts');
            self::assertSame(2, SpacedReview::dueCount($s['student'], $now));

            // Before a quiz: everything in the class, due or not, without the daily number.
            self::assertCount(5, SpacedReview::queue($s['student'], $now, $s['class']));
            $early = SpacedReview::answer($s['student'], $later, ['knew' => '1'], $now);
            self::assertTrue($early['early']);
            self::assertSame('2026-10-28', $early['due_on'], 'reviewing early does not push it further out');
            $early = SpacedReview::answer($s['student'], $later, ['knew' => '0'], $now);
            self::assertSame('2026-10-22', $early['due_on'], 'a miss brings it back tomorrow even when early');
        } finally {
            $pdo->rollBack();
            Settings::flush();
        }
    }

    public function testPracticeMissesJoinAndLeaveWhileTheStudentsAiIsOff(): void
    {
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $s = $this->school();
            $now = $this->manila('2026-10-21 09:00');
            $draft = Db::insert('learning_materials', ['organization_id' => $s['org'], 'class_id' => $s['class'], 'title' => 'Draft notes', 'kind' => 'link',
                'url' => 'https://example.org/notes', 'visibility' => 'draft', 'created_by' => $s['teacher']]);
            $items = [
                ['type' => 'mcq', 'question' => 'Which vessels carry blood away from the heart?', 'choices' => ['Veins', 'Arteries'], 'answer' => 1, 'explanation' => 'Arteries.', 'source' => 1, 'source_ref' => null],
                ['type' => 'short', 'question' => 'Fill in the blank: ____ connect arteries and veins.', 'answer_text' => 'Capillaries', 'source' => 1, 'source_ref' => null],
                ['type' => 'mcq', 'question' => 'From a material that is a draft again', 'choices' => ['A', 'B'], 'answer' => 0, 'source' => 1, 'source_ref' => ['title' => 'Draft notes', 'material_id' => $draft]],
            ];
            $set = ['id' => Db::insert('practice_sets', ['organization_id' => $s['org'], 'user_id' => (int) $s['student']['id'], 'class_id' => $s['class'], 'kind' => 'quiz',
                'title' => 'Practice', 'items' => json_encode($items), 'generator' => 'demo']), 'kind' => 'quiz', 'class_id' => $s['class']];
            $results = [['correct' => false], ['correct' => true], ['correct' => false]];
            self::assertSame(2, SpacedReview::fromPractice($s['student'], $set, $items, $results, $now));
            self::assertSame(['Which vessels carry blood away from the heart?'], array_map(static fn ($r) => SpacedReview::content($r)['prompt'], SpacedReview::queue($s['student'], $now)),
                'the item from a material that is no longer released stays out');

            $off = $s['student'] + [];
            $off['ai_enabled'] = 0;
            self::assertSame([], SpacedReview::queue($off, $now), 'practice was written by AI, so it waits while the student\'s AI is off');
        } finally {
            $pdo->rollBack();
            Settings::flush();
        }
    }

    private function manila(string $local): DateTimeImmutable
    {
        return new DateTimeImmutable($local, new DateTimeZone('Asia/Manila'));
    }

    /**
     * A class with one student who took a quiz: a choice answered wrong, a true-or-false answered right, a short answer
     * answered wrong and an essay. The answers are released by hand, not yet.
     * @return array<string,mixed>
     */
    private function school(): array
    {
        $org = Db::insert('organizations', ['name' => 'Review Test School', 'slug' => 'review-test', 'timezone' => 'Asia/Manila', 'is_demo' => 1]);
        $year = Db::insert('academic_periods', ['organization_id' => $org, 'name' => 'School year 2026–2027', 'starts_on' => '2026-06-15', 'ends_on' => '2027-04-09', 'is_current' => 1]);
        $subject = Db::insert('subjects', ['organization_id' => $org, 'name' => 'Science']);
        $class = Db::insert('classes', ['organization_id' => $org, 'subject_id' => $subject, 'academic_period_id' => $year, 'name' => 'Science 9 – Review', 'status' => 'active']);
        $teacher = Db::insert('users', ['organization_id' => $org, 'role' => 'teacher', 'given_name' => 'Rita', 'family_name' => 'Teacher', 'username' => 'rita.teacher.rv', 'status' => 'active', 'is_demo' => 1]);
        $studentId = Db::insert('users', ['organization_id' => $org, 'role' => 'student', 'given_name' => 'Rhea', 'family_name' => 'Student', 'username' => 'rhea.student.rv', 'status' => 'active', 'is_demo' => 1]);
        Db::insert('class_memberships', ['class_id' => $class, 'user_id' => $teacher, 'role' => 'teacher', 'status' => 'active']);
        Db::insert('class_memberships', ['class_id' => $class, 'user_id' => $studentId, 'role' => 'student', 'status' => 'active']);
        $quiz = Db::insert('assignments', ['organization_id' => $org, 'class_id' => $class, 'kind' => 'quiz', 'title' => 'Blood vessels check', 'status' => 'published',
            'due_at' => '2026-10-15 15:00:00', 'points_possible' => 4, 'answer_key_release' => 'manual', 'created_by' => $teacher]);
        $questions = [
            ['multiple_choice', 'Which vessels carry blood away from the heart?', [['key' => 'A', 'text' => 'Veins'], ['key' => 'B', 'text' => 'Arteries'], ['key' => 'C', 'text' => 'Capillaries']], ['correct' => 'B'], 'A', 0],
            ['true_false', 'The heart has four chambers.', null, ['correct' => true], 'true', 1],
            ['short_answer', 'Name the vessels between arteries and veins.', null, ['accepted' => ['capillaries'], 'case_sensitive' => false], 'veins', 0],
            ['essay', 'Explain how blood moves through the body.', null, null, 'It moves.', null],
        ];
        $ids = [];
        foreach ($questions as $i => [$type, $prompt, $options, $key]) {
            $ids[] = Db::insert('questions', ['assignment_id' => $quiz, 'position' => $i, 'qtype' => $type, 'prompt' => $prompt, 'points' => 1,
                'options' => $options !== null ? json_encode($options) : null, 'answer_key' => $key !== null ? json_encode($key) : null, 'explanation' => null]);
        }
        $version = Assessments::snapshot($quiz, $teacher);
        $submission = Db::insert('submissions', ['organization_id' => $org, 'assignment_id' => $quiz, 'student_id' => $studentId, 'attempt_no' => 1, 'status' => 'submitted',
            'submitted_at' => '2026-10-14 01:00:00', 'assignment_version_id' => $version]);
        foreach ($questions as $i => $q) {
            Db::insert('submission_answers', ['submission_id' => $submission, 'question_id' => $ids[$i], 'response' => $q[4], 'is_correct' => $q[5], 'needs_manual' => $q[5] === null ? 1 : 0]);
        }
        return ['org' => $org, 'class' => $class, 'teacher' => $teacher, 'quiz' => $quiz, 'student' => Db::one('SELECT * FROM users WHERE id = :id', ['id' => $studentId])];
    }
}
