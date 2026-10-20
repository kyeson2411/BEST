<?php

declare(strict_types=1);

namespace Sibol\Controllers\Teacher;

use Sibol\Controllers\Controller;
use Sibol\Core\Response;
use Sibol\Repositories\TeacherRepo;
use Sibol\Services\Insights;

final class DashboardController extends Controller
{
    public function index(): Response
    {
        $user = $this->user();
        $classes = TeacherRepo::classes($user);
        return $this->view('teacher/dashboard', [
            'title' => t('Home'),
            'user' => $user,
            'classes' => $classes,
            'queue' => TeacherRepo::reviewQueue($user, null, 50),
            'attention' => TeacherRepo::attention($user),
            'upcoming' => TeacherRepo::assignmentsDue($user, 'UTC_TIMESTAMP()', 'UTC_TIMESTAMP() + INTERVAL 14 DAY'),
            'recent' => TeacherRepo::assignmentsDue($user, 'UTC_TIMESTAMP() - INTERVAL 14 DAY', 'UTC_TIMESTAMP()', 'DESC'),
            'overview' => TeacherRepo::overview($user),
            'reteach' => Insights::reteachAcrossClasses($user, 3),
        ]);
    }
}
