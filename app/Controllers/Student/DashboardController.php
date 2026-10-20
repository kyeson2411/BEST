<?php

declare(strict_types=1);

namespace Sibol\Controllers\Student;

use Sibol\Controllers\Controller;
use Sibol\Core\Response;
use Sibol\Repositories\StudentRepo;
use Sibol\Services\AiAccess;
use Sibol\Services\Notifier;
use Sibol\Services\Settings;

final class DashboardController extends Controller
{
    public function index(): Response
    {
        $user = $this->user();
        Notifier::remindDueSoon($user);
        $due = StudentRepo::dueSoon($user);
        $classes = StudentRepo::classes($user);
        $orgId = (int) $user['organization_id'];

        return $this->view('student/dashboard', [
            'title' => t('Home'),
            'user' => $user,
            'due' => $due,
            'classes' => $classes,
            'continue' => StudentRepo::continueLearning($user),
            'feedback' => StudentRepo::recentFeedback($user),
            'materials' => StudentRepo::newMaterials($user),
            'conversation' => StudentRepo::lastConversation($user),
            'study' => StudentRepo::upcomingStudy($user),
            'aiEnabled' => AiAccess::allowed($user),
            'modes' => (array) Settings::get($orgId, 'ai.student_modes', Settings::STUDENT_MODES),
            'practiceOn' => AiAccess::allowed($user) && ((bool) Settings::get($orgId, 'features.practice_quizzes', true) || (bool) Settings::get($orgId, 'features.flashcards', true)),
        ]);
    }
}
