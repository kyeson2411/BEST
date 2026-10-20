<?php

declare(strict_types=1);

namespace Sibol\Controllers\Student;

use DateTimeImmutable;
use Sibol\Controllers\Controller;
use Sibol\Core\HttpException;
use Sibol\Core\Response;
use Sibol\Core\Session;
use Sibol\Policies\Access;
use Sibol\Services\Settings;
use Sibol\Services\SpacedReview;

/**
 * "Review today": questions the student missed come back on a schedule, one at a time. Private to the student,
 * never graded, and never shown to teachers.
 */
final class ReviewController extends Controller
{
    public function index(): Response
    {
        $user = $this->user();
        $this->enabled($user);
        $now = new DateTimeImmutable('now');
        SpacedReview::sync($user, $now);
        $classId = $this->request->int('class');
        $class = $classId !== null ? Access::studentClass($user, $classId) : null;
        $queue = SpacedReview::queue($user, $now, $classId);
        $today = SpacedReview::today((int) $user['organization_id'], $now);
        $item = $queue[0] ?? null;
        return $this->view('student/review', [
            'title' => $class ? t('Review: :class', ['class' => $class['name']]) : t('Review today'),
            'crumbs' => $class ? [[t('Review today'), '/student/review'], [$class['name'], null]] : null,
            'class' => $class,
            'item' => $item,
            'content' => $item ? SpacedReview::content($item) : null,
            'left' => count($queue),
            'reviewedToday' => SpacedReview::reviewedOn($user, $today),
            'cap' => SpacedReview::dailyCap((int) $user['organization_id']),
            'today' => $today,
            'result' => Session::pull('review_result'),
            'upcoming' => $class === null ? SpacedReview::upcomingQuizzes($user, $now) : [],
            'katex' => true,
        ]);
    }

    public function answer(int $id): Response
    {
        $user = $this->user();
        $this->enabled($user);
        $classId = $this->request->int('class');
        if ($classId !== null) {
            Access::studentClass($user, $classId);
        }
        $result = SpacedReview::answer($user, $id, [
            'choice' => $this->request->str('choice'),
            'text' => $this->request->str('text'),
            'knew' => $this->request->str('knew'),
        ], new DateTimeImmutable('now'));
        Session::set('review_result', $result);
        return $this->redirect('/student/review' . ($classId !== null ? '?class=' . $classId : ''));
    }

    private function enabled(array $user): void
    {
        if (!Settings::get((int) $user['organization_id'], 'features.spaced_review', true)) {
            throw HttpException::forbidden(t('Your school has turned off the review queue.'));
        }
    }
}
