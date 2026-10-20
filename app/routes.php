<?php

/**
 * Route map. Every group declares its role; controllers still check class/record
 * ownership themselves (hidden links are never the security boundary).
 *
 * @var Sibol\Core\Router $router
 */

use Sibol\Controllers\Admin;
use Sibol\Controllers\AuthController;
use Sibol\Controllers\CompanionController;
use Sibol\Controllers\FileController;
use Sibol\Controllers\NotificationController;
use Sibol\Controllers\PageController;
use Sibol\Controllers\ProfileController;
use Sibol\Controllers\SearchController;
use Sibol\Controllers\SetupController;
use Sibol\Controllers\Student;
use Sibol\Controllers\Teacher;

// Public and sign-in --------------------------------------------------------------
$router->get('/', [PageController::class, 'home']);
$router->get('/login', [AuthController::class, 'showLogin'], ['guest']);
$router->post('/login', [AuthController::class, 'login'], ['guest']);
$router->post('/demo-login', [AuthController::class, 'demoLogin'], ['guest']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/invite/{token}', [AuthController::class, 'showInvite']);
$router->post('/invite/{token}', [AuthController::class, 'acceptInvite']);
$router->get('/reset/{token}', [AuthController::class, 'showReset']);
$router->post('/reset/{token}', [AuthController::class, 'reset']);
$router->get('/privacy', [PageController::class, 'privacy']);
$router->get('/help', [PageController::class, 'help']);

// First-run setup (APP_ENV=local and this computer only) ---------------------------
$router->get('/setup', [SetupController::class, 'index']);
$router->post('/setup/migrate', [SetupController::class, 'migrate']);
$router->post('/setup/seed', [SetupController::class, 'seed']);
$router->post('/setup/reset', [SetupController::class, 'reset']);

// Any signed-in user ---------------------------------------------------------------
$router->group('', ['auth'], static function ($r): void {
    $r->get('/menu', [PageController::class, 'menu']);
    $r->get('/settings', [ProfileController::class, 'edit']);
    $r->post('/settings/profile', [ProfileController::class, 'updateProfile']);
    $r->post('/settings/preferences', [ProfileController::class, 'updatePreferences']);
    $r->post('/settings/password', [ProfileController::class, 'updatePassword']);
    $r->post('/settings/avatar', [ProfileController::class, 'updateAvatar']);
    $r->post('/settings/avatar/remove', [ProfileController::class, 'removeAvatar']);
    $r->post('/settings/sessions/revoke', [ProfileController::class, 'signOutOthers']);
    $r->post('/settings/privacy-requests', [ProfileController::class, 'requestPrivacy']);
    $r->get('/settings/privacy-requests/{id}/download', [ProfileController::class, 'downloadExport']);

    $r->get('/notifications', [NotificationController::class, 'index']);
    $r->get('/notifications/panel', [NotificationController::class, 'panel']);
    $r->get('/notifications/{id}/open', [NotificationController::class, 'open']);
    $r->post('/notifications/read-all', [NotificationController::class, 'readAll']);

    $r->get('/search', [SearchController::class, 'index']);

    $r->get('/avatars/{id}', [FileController::class, 'avatar']);
    $r->get('/school-logo', [FileController::class, 'logo']);
    $r->get('/materials/{id}/file', [FileController::class, 'material']);
    $r->get('/submission-files/{id}', [FileController::class, 'submissionFile']);

    // The learning companion serves students and teachers with role-specific modes.
    $r->group('/companion', ['role:student,teacher'], static function ($c): void {
        $c->get('', [CompanionController::class, 'index']);
        $c->get('/new', [CompanionController::class, 'create']);
        $c->post('', [CompanionController::class, 'store']);
        $c->get('/{id}', [CompanionController::class, 'show']);
        $c->post('/{id}/messages', [CompanionController::class, 'send']);
        $c->post('/{id}/regenerate', [CompanionController::class, 'regenerate']);
        $c->post('/{id}/rename', [CompanionController::class, 'rename']);
        $c->post('/{id}/archive', [CompanionController::class, 'archive']);
        $c->post('/{id}/restore', [CompanionController::class, 'restore']);
        $c->post('/{id}/delete', [CompanionController::class, 'destroy']);
        $c->post('/{id}/mode', [CompanionController::class, 'changeMode']);
        $c->post('/messages/{id}/report', [CompanionController::class, 'report']);
        $c->post('/messages/{id}/share', [CompanionController::class, 'share']);
        $c->post('/messages/{id}/plan', [CompanionController::class, 'addPlanToCalendar']);
    });
});

// Students -------------------------------------------------------------------------
$router->group('/student', ['role:student'], static function ($r): void {
    $r->get('', [Student\DashboardController::class, 'index']);
    $r->get('/classes', [Student\ClassController::class, 'index']);
    $r->post('/classes/join', [Student\ClassController::class, 'join']);
    $r->get('/classes/{id}', [Student\ClassController::class, 'show']);
    $r->get('/classes/{classId}/lessons/{id}', [Student\ClassController::class, 'lesson']);

    $r->get('/assignments', [Student\AssignmentController::class, 'index']);
    $r->get('/assignments/{id}', [Student\AssignmentController::class, 'show']);
    $r->post('/assignments/{id}/draft', [Student\AssignmentController::class, 'saveDraft']);
    $r->post('/assignments/{id}/submit', [Student\AssignmentController::class, 'submit']);
    $r->post('/assignments/{id}/start', [Student\AssignmentController::class, 'startQuiz']);
    $r->get('/assignments/{id}/attempt', [Student\AssignmentController::class, 'attempt']);
    $r->post('/assignments/{id}/attempt', [Student\AssignmentController::class, 'saveAttempt']);
    $r->get('/submissions/{id}', [Student\AssignmentController::class, 'receipt']);

    $r->get('/learning', [Student\PracticeController::class, 'index']);
    $r->post('/learning', [Student\PracticeController::class, 'store']);
    $r->get('/learning/{id}', [Student\PracticeController::class, 'show']);
    $r->post('/learning/{id}/results', [Student\PracticeController::class, 'saveResults']);
    $r->post('/learning/{id}/delete', [Student\PracticeController::class, 'destroy']);

    $r->get('/progress', [Student\ProgressController::class, 'index']);

    $r->get('/calendar', [Student\CalendarController::class, 'index']);
    $r->post('/calendar/sessions', [Student\CalendarController::class, 'store']);
    $r->post('/calendar/sessions/{id}/complete', [Student\CalendarController::class, 'complete']);
    $r->post('/calendar/sessions/{id}/delete', [Student\CalendarController::class, 'destroy']);
});

// Teachers -------------------------------------------------------------------------
$router->group('/teacher', ['role:teacher'], static function ($r): void {
    $r->get('', [Teacher\DashboardController::class, 'index']);

    $r->get('/classes', [Teacher\ClassController::class, 'index']);
    $r->get('/classes/{id}', [Teacher\ClassController::class, 'show']);
    $r->post('/classes/{id}/roster', [Teacher\ClassController::class, 'addStudent']);
    $r->post('/classes/{classId}/roster/{id}/withdraw', [Teacher\ClassController::class, 'withdrawStudent']);
    $r->post('/classes/{id}/code', [Teacher\ClassController::class, 'joinCode']);
    $r->post('/classes/{id}/modules', [Teacher\ClassController::class, 'addModule']);
    $r->post('/classes/{classId}/modules/{id}', [Teacher\ClassController::class, 'updateModule']);
    $r->post('/classes/{classId}/modules/{id}/delete', [Teacher\ClassController::class, 'deleteModule']);
    $r->post('/classes/{id}/announcements', [Teacher\ClassController::class, 'postAnnouncement']);
    $r->post('/classes/{classId}/announcements/{id}/delete', [Teacher\ClassController::class, 'deleteAnnouncement']);
    $r->post('/classes/{classId}/shared/{id}/seen', [Teacher\ClassController::class, 'markShareSeen']);

    $r->get('/materials', [Teacher\MaterialController::class, 'index']);
    $r->get('/materials/new', [Teacher\MaterialController::class, 'create']);
    $r->post('/materials', [Teacher\MaterialController::class, 'store']);
    $r->get('/materials/{id}', [Teacher\MaterialController::class, 'show']);
    $r->post('/materials/{id}', [Teacher\MaterialController::class, 'update']);
    $r->post('/materials/{id}/visibility', [Teacher\MaterialController::class, 'visibility']);
    $r->post('/materials/{id}/version', [Teacher\MaterialController::class, 'uploadVersion']);
    $r->post('/materials/{id}/process', [Teacher\MaterialController::class, 'process']);
    $r->get('/materials/{id}/status', [Teacher\MaterialController::class, 'status']);

    $r->get('/assignments', [Teacher\AssignmentController::class, 'index']);
    $r->get('/assessments', [Teacher\AssignmentController::class, 'assessments']);
    $r->get('/assignments/new', [Teacher\AssignmentController::class, 'create']);
    $r->post('/assignments', [Teacher\AssignmentController::class, 'store']);
    $r->get('/assignments/{id}', [Teacher\AssignmentController::class, 'show']);
    $r->get('/assignments/{id}/edit', [Teacher\AssignmentController::class, 'edit']);
    $r->post('/assignments/{id}', [Teacher\AssignmentController::class, 'update']);
    $r->post('/assignments/{id}/questions', [Teacher\AssignmentController::class, 'addQuestion']);
    $r->post('/assignments/{id}/questions/generate', [Teacher\AssignmentController::class, 'generateQuestions']);
    $r->post('/assignments/{id}/questions/approve-all', [Teacher\AssignmentController::class, 'approveAll']);
    $r->post('/assignments/{assignmentId}/questions/{id}', [Teacher\AssignmentController::class, 'updateQuestion']);
    $r->post('/assignments/{assignmentId}/questions/{id}/delete', [Teacher\AssignmentController::class, 'deleteQuestion']);
    $r->post('/assignments/{assignmentId}/questions/{id}/approve', [Teacher\AssignmentController::class, 'approveQuestion']);
    $r->post('/assignments/{assignmentId}/questions/{id}/move', [Teacher\AssignmentController::class, 'moveQuestion']);
    $r->post('/assignments/{id}/rubric', [Teacher\AssignmentController::class, 'saveRubric']);
    $r->get('/assignments/{id}/preview', [Teacher\AssignmentController::class, 'preview']);
    $r->post('/assignments/{id}/publish', [Teacher\AssignmentController::class, 'publish']);
    $r->post('/assignments/{id}/close', [Teacher\AssignmentController::class, 'close']);
    $r->post('/assignments/{id}/reopen', [Teacher\AssignmentController::class, 'reopen']);
    $r->post('/assignments/{id}/archive', [Teacher\AssignmentController::class, 'archive']);
    $r->post('/assignments/{id}/duplicate', [Teacher\AssignmentController::class, 'duplicate']);
    $r->post('/assignments/{id}/delete', [Teacher\AssignmentController::class, 'destroy']);
    $r->post('/assignments/{id}/release-answers', [Teacher\AssignmentController::class, 'releaseAnswers']);
    $r->post('/assignments/{id}/release-all', [Teacher\SubmissionController::class, 'releaseAll']);
    $r->get('/assignments/{id}/submissions', [Teacher\SubmissionController::class, 'index']);

    $r->get('/review', [Teacher\SubmissionController::class, 'queue']);
    $r->get('/submissions/{id}', [Teacher\SubmissionController::class, 'show']);
    $r->post('/submissions/{id}/grade', [Teacher\SubmissionController::class, 'grade']);
    $r->post('/submissions/{id}/release', [Teacher\SubmissionController::class, 'release']);
    $r->post('/submissions/{id}/return', [Teacher\SubmissionController::class, 'returnForResubmission']);
    $r->post('/submissions/{id}/suggest', [Teacher\SubmissionController::class, 'suggest']);

    $r->get('/gradebook', [Teacher\GradebookController::class, 'index']);
    $r->get('/gradebook/{id}', [Teacher\GradebookController::class, 'show']);
    $r->get('/gradebook/{id}/export', [Teacher\GradebookController::class, 'export']);

    $r->get('/insights', [Teacher\InsightsController::class, 'index']);
    $r->get('/insights/{id}', [Teacher\InsightsController::class, 'show']);
    $r->get('/insights/{classId}/questions/{id}', [Teacher\InsightsController::class, 'question']);
    $r->post('/insights/{id}/ideas', [Teacher\InsightsController::class, 'ideas']);

    $r->get('/lessons', [Teacher\LessonController::class, 'index']);
    $r->get('/lessons/new', [Teacher\LessonController::class, 'create']);
    $r->post('/lessons', [Teacher\LessonController::class, 'store']);
    $r->get('/lessons/{id}', [Teacher\LessonController::class, 'edit']);
    $r->post('/lessons/{id}', [Teacher\LessonController::class, 'update']);
    $r->get('/lessons/{id}/preview', [Teacher\LessonController::class, 'preview']);
    $r->post('/lessons/{id}/publish', [Teacher\LessonController::class, 'publish']);
    $r->post('/lessons/{id}/unpublish', [Teacher\LessonController::class, 'unpublish']);
    $r->post('/lessons/{id}/duplicate', [Teacher\LessonController::class, 'duplicate']);
    $r->post('/lessons/{id}/archive', [Teacher\LessonController::class, 'archive']);
});

// School administrators --------------------------------------------------------------
$router->group('/admin', ['role:admin'], static function ($r): void {
    $r->get('', [Admin\DashboardController::class, 'index']);

    $r->get('/users', [Admin\UserController::class, 'index']);
    $r->get('/users/new', [Admin\UserController::class, 'create']);
    $r->post('/users', [Admin\UserController::class, 'store']);
    $r->get('/users/import', [Admin\ImportController::class, 'create']);
    $r->get('/users/import/template', [Admin\ImportController::class, 'template']);
    $r->get('/users/import/links', [Admin\ImportController::class, 'links']);
    $r->post('/users/import', [Admin\ImportController::class, 'preview']);
    $r->post('/users/import/confirm', [Admin\ImportController::class, 'confirm']);
    $r->get('/users/{id}', [Admin\UserController::class, 'show']);
    $r->post('/users/{id}', [Admin\UserController::class, 'update']);
    $r->post('/users/{id}/status', [Admin\UserController::class, 'status']);
    $r->post('/users/{id}/link', [Admin\UserController::class, 'link']);

    $r->get('/classes', [Admin\ClassController::class, 'index']);
    $r->get('/classes/new', [Admin\ClassController::class, 'create']);
    $r->post('/classes', [Admin\ClassController::class, 'store']);
    $r->get('/classes/{id}', [Admin\ClassController::class, 'show']);
    $r->post('/classes/{id}', [Admin\ClassController::class, 'update']);
    $r->post('/classes/{id}/members', [Admin\ClassController::class, 'addMembers']);
    $r->post('/classes/{classId}/members/{id}/remove', [Admin\ClassController::class, 'removeMember']);
    $r->post('/classes/{id}/status', [Admin\ClassController::class, 'status']);

    $r->get('/subjects', [Admin\StructureController::class, 'subjects']);
    $r->post('/subjects', [Admin\StructureController::class, 'storeSubject']);
    $r->post('/subjects/{id}', [Admin\StructureController::class, 'updateSubject']);
    $r->get('/academic-periods', [Admin\StructureController::class, 'periods']);
    $r->post('/academic-periods', [Admin\StructureController::class, 'storePeriod']);
    $r->post('/academic-periods/{id}', [Admin\StructureController::class, 'updatePeriod']);

    $r->get('/usage', [Admin\UsageController::class, 'index']);
    $r->get('/policies', [Admin\SettingsController::class, 'policies']);
    $r->post('/policies', [Admin\SettingsController::class, 'savePolicies']);
    $r->get('/settings', [Admin\SettingsController::class, 'school']);
    $r->post('/settings', [Admin\SettingsController::class, 'saveSchool']);
    $r->post('/settings/grading', [Admin\SettingsController::class, 'saveGrading']);
    $r->post('/settings/logo', [Admin\SettingsController::class, 'saveLogo']);
    $r->get('/integrations', [Admin\SettingsController::class, 'integrations']);

    $r->get('/audit', [Admin\AuditController::class, 'index']);

    $r->get('/privacy', [Admin\PrivacyController::class, 'index']);
    $r->get('/privacy/requests/{id}', [Admin\PrivacyController::class, 'show']);
    $r->post('/privacy/requests/{id}', [Admin\PrivacyController::class, 'update']);
    $r->post('/privacy/requests/{id}/export', [Admin\PrivacyController::class, 'export']);
    $r->post('/privacy/requests/{id}/erase', [Admin\PrivacyController::class, 'erase']);
    $r->get('/privacy/reports/{id}', [Admin\PrivacyController::class, 'report']);
    $r->post('/privacy/reports/{id}', [Admin\PrivacyController::class, 'resolveReport']);
});
