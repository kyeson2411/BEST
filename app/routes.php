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
use Sibol\Controllers\FeedbackController;
use Sibol\Controllers\FileController;
use Sibol\Controllers\NoticeController;
use Sibol\Controllers\NotificationController;
use Sibol\Controllers\PageController;
use Sibol\Controllers\ProfileController;
use Sibol\Controllers\SearchController;
use Sibol\Controllers\SetupController;
use Sibol\Controllers\SlipController;
use Sibol\Controllers\Student;
use Sibol\Controllers\Teacher;
use Sibol\Controllers\TwoStepController;
use Sibol\Core\Auth;
use Sibol\Core\HttpException;
use Sibol\Core\Request;
use Sibol\Core\Response;
use Sibol\Core\Session;
use Sibol\Services\AiAccess;
use Sibol\Services\Notices;

// Signed-in pages wait until the person has read and accepted the privacy notice and the rules for using
// Sibol, when the school's policy asks them to (students, by default), and again after either changes.
// After a change, work already under way (a quiz saving answers or being handed in) is not interrupted:
// people who accepted an earlier version are asked at their next page load.
$router->guard('notices', static function (Request $request): ?Response {
    $user = Auth::user();
    if (!$user || !Notices::pending($user)) {
        return null;
    }
    if (($request->method !== 'GET' || $request->wantsJson()) && Notices::acceptedBefore($user)) {
        return null;
    }
    if ($request->wantsJson()) {
        return Response::json(['error' => t('Read and accept the privacy notice and the rules for using Sibol first.')], 403);
    }
    Session::set('intended', $request->method === 'GET' ? $request->fullPath() : null);
    return Response::redirect('/welcome', 302);
});

// The companion and AI practice are not there for a student whose AI an adviser or administrator switched off.
$router->guard('ai-student', static function (): ?Response {
    $user = Auth::user();
    if ($user && $user['role'] === 'student' && !AiAccess::studentOn($user)) {
        throw HttpException::forbidden(t('The companion and AI practice are switched off for your account. Everything else in Sibol works as usual. Ask your adviser if you have questions.'));
    }
    return null;
});

// Public and sign-in --------------------------------------------------------------
$router->get('/', [PageController::class, 'home']);
$router->get('/login', [AuthController::class, 'showLogin'], ['guest']);
$router->post('/login', [AuthController::class, 'login'], ['guest']);
$router->post('/demo-login', [AuthController::class, 'demoLogin'], ['guest']);
// The code step for people who turned on two-step sign-in: the password alone signs nobody in.
$router->get('/login/code', [TwoStepController::class, 'showChallenge'], ['guest']);
$router->post('/login/code', [TwoStepController::class, 'challenge'], ['guest']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/invite/{token}', [AuthController::class, 'showInvite']);
$router->post('/invite/{token}', [AuthController::class, 'acceptInvite']);
$router->get('/reset/{token}', [AuthController::class, 'showReset']);
$router->post('/reset/{token}', [AuthController::class, 'reset']);
$router->get('/start', [AuthController::class, 'showStart']);
$router->post('/start', [AuthController::class, 'start']);
$router->get('/privacy', [PageController::class, 'privacy']);
$router->get('/rules', [PageController::class, 'rules']);
$router->get('/help', [PageController::class, 'help']);
// The installable app: its manifest, and the page the service worker (public/sw.js) shows without a connection.
$router->get('/manifest.webmanifest', [PageController::class, 'manifest']);
$router->get('/offline', [PageController::class, 'offline']);

// Reading and accepting the privacy notice and the rules (the "notices" guard sends people here).
$router->get('/welcome', [NoticeController::class, 'show'], ['auth']);
$router->post('/welcome', [NoticeController::class, 'accept'], ['auth']);
$router->post('/welcome/language', [NoticeController::class, 'language'], ['auth']);

// First-run setup (APP_ENV=local and this computer only) ---------------------------
$router->get('/setup', [SetupController::class, 'index']);
$router->post('/setup/migrate', [SetupController::class, 'migrate']);
$router->post('/setup/seed', [SetupController::class, 'seed']);
$router->post('/setup/reset', [SetupController::class, 'reset']);

// Any signed-in user ---------------------------------------------------------------
$router->group('', ['auth', 'notices'], static function ($r): void {
    $r->get('/menu', [PageController::class, 'menu']);
    $r->get('/settings', [ProfileController::class, 'edit']);
    $r->post('/settings/profile', [ProfileController::class, 'updateProfile']);
    $r->post('/settings/preferences', [ProfileController::class, 'updatePreferences']);
    $r->post('/settings/password', [ProfileController::class, 'updatePassword']);
    $r->post('/settings/avatar', [ProfileController::class, 'updateAvatar']);
    $r->post('/settings/avatar/remove', [ProfileController::class, 'removeAvatar']);
    $r->post('/settings/sessions/revoke', [ProfileController::class, 'signOutOthers']);
    // Two-step sign-in, optional for administrators and teachers.
    $r->get('/settings/two-step', [TwoStepController::class, 'setup'], ['role:admin,teacher']);
    $r->post('/settings/two-step', [TwoStepController::class, 'enable'], ['role:admin,teacher']);
    $r->get('/settings/two-step/codes', [TwoStepController::class, 'codes'], ['role:admin,teacher']);
    $r->post('/settings/two-step/codes', [TwoStepController::class, 'renewCodes'], ['role:admin,teacher']);
    $r->post('/settings/two-step/off', [TwoStepController::class, 'disable'], ['role:admin,teacher']);
    $r->post('/settings/privacy-requests', [ProfileController::class, 'requestPrivacy']);
    $r->get('/settings/privacy-requests/{id}/download', [ProfileController::class, 'downloadExport']);

    $r->get('/notifications', [NotificationController::class, 'index']);
    $r->get('/notifications/panel', [NotificationController::class, 'panel']);
    $r->get('/notifications/count', [NotificationController::class, 'count']);
    $r->get('/notifications/{id}/open', [NotificationController::class, 'open']);
    $r->post('/notifications/read-all', [NotificationController::class, 'readAll']);

    $r->get('/search', [SearchController::class, 'index']);

    // Printable sign-in slips that this person just issued (teachers, advisers and administrators).
    $r->get('/slips', [SlipController::class, 'show'], ['role:admin,teacher']);

    $r->get('/avatars/{id}', [FileController::class, 'avatar']);
    $r->get('/school-logo', [FileController::class, 'logo']);
    $r->get('/materials/{id}/file', [FileController::class, 'material']);
    $r->get('/submission-files/{id}', [FileController::class, 'submissionFile']);

    // The learning companion serves students and teachers with role-specific modes.
    // Feedback about Sibol from students and teachers to the school's administrators (not a chat).
    $r->group('/feedback', ['role:student,teacher'], static function ($f): void {
        $f->get('', [FeedbackController::class, 'create']);
        $f->post('', [FeedbackController::class, 'store']);
    });

    $r->group('/companion', ['role:student,teacher', 'ai-student'], static function ($c): void {
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
$router->group('/student', ['role:student', 'notices'], static function ($r): void {
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

    $r->group('/learning', ['ai-student'], static function ($l): void {
        $l->get('', [Student\PracticeController::class, 'index']);
        $l->post('', [Student\PracticeController::class, 'store']);
        $l->get('/{id}', [Student\PracticeController::class, 'show']);
        $l->post('/{id}/results', [Student\PracticeController::class, 'saveResults']);
        $l->post('/{id}/delete', [Student\PracticeController::class, 'destroy']);
    });

    // Missed questions come back on a schedule. Not behind the AI switch: quiz questions are the teacher's own.
    $r->get('/review', [Student\ReviewController::class, 'index']);
    $r->post('/review/{id}', [Student\ReviewController::class, 'answer']);

    $r->get('/progress', [Student\ProgressController::class, 'index']);
    $r->get('/progress/report', [Student\ProgressController::class, 'report']);

    $r->get('/calendar', [Student\CalendarController::class, 'index']);
    $r->post('/calendar/sessions', [Student\CalendarController::class, 'store']);
    $r->post('/calendar/sessions/{id}/complete', [Student\CalendarController::class, 'complete']);
    $r->post('/calendar/sessions/{id}/delete', [Student\CalendarController::class, 'destroy']);
});

// Teachers -------------------------------------------------------------------------
$router->group('/teacher', ['role:teacher', 'notices'], static function ($r): void {
    $r->get('', [Teacher\DashboardController::class, 'index']);

    $r->get('/classes', [Teacher\ClassController::class, 'index']);
    $r->get('/classes/{id}', [Teacher\ClassController::class, 'show']);
    $r->post('/classes/{id}/roster', [Teacher\ClassController::class, 'addStudent']);
    $r->post('/classes/{classId}/roster/{id}/withdraw', [Teacher\ClassController::class, 'withdrawStudent']);
    $r->post('/classes/{classId}/nudge/{id}', [Teacher\ClassController::class, 'nudge']);
    $r->post('/classes/{id}/slips', [Teacher\ClassController::class, 'slips']);
    $r->post('/students/{id}/slip', [Teacher\ClassController::class, 'studentSlip']);

    $r->get('/advisory', [Teacher\AdvisoryController::class, 'index']);
    $r->get('/advisory/{id}', [Teacher\AdvisoryController::class, 'show']);
    $r->post('/advisory/{id}/slips', [Teacher\AdvisoryController::class, 'slips']);
    $r->get('/advisory/{id}/reports', [Teacher\AdvisoryController::class, 'reports']);
    $r->get('/advisory/{sectionId}/students/{id}', [Teacher\AdvisoryController::class, 'student']);
    $r->get('/advisory/{sectionId}/students/{id}/report', [Teacher\AdvisoryController::class, 'report']);
    $r->post('/advisory/{sectionId}/students/{id}/ai', [Teacher\AdvisoryController::class, 'studentAi']);
    $r->post('/advisory/{sectionId}/students/{id}/guardian', [Teacher\AdvisoryController::class, 'studentGuardian']);
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
    $r->post('/materials/{id}/copy', [Teacher\MaterialController::class, 'copy']);
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
    $r->get('/assignments/{id}/print', [Teacher\AssignmentController::class, 'printView']);
    $r->get('/assignments/{id}/scores', [Teacher\SubmissionController::class, 'scores']);
    $r->post('/assignments/{id}/scores', [Teacher\SubmissionController::class, 'saveScores']);
    $r->post('/assignments/{id}/publish', [Teacher\AssignmentController::class, 'publish']);
    $r->post('/assignments/{id}/close', [Teacher\AssignmentController::class, 'close']);
    $r->post('/assignments/{id}/reopen', [Teacher\AssignmentController::class, 'reopen']);
    $r->post('/assignments/{id}/archive', [Teacher\AssignmentController::class, 'archive']);
    $r->post('/assignments/{id}/duplicate', [Teacher\AssignmentController::class, 'duplicate']);
    $r->post('/assignments/{id}/copy', [Teacher\AssignmentController::class, 'copy']);
    $r->post('/assignments/{id}/delete', [Teacher\AssignmentController::class, 'destroy']);
    $r->post('/assignments/{id}/release-answers', [Teacher\AssignmentController::class, 'releaseAnswers']);
    $r->post('/assignments/{id}/release-all', [Teacher\SubmissionController::class, 'releaseAll']);
    $r->get('/assignments/{id}/submissions', [Teacher\SubmissionController::class, 'index']);
    $r->post('/assignments/{id}/overrides', [Teacher\SubmissionController::class, 'saveOverride']);
    $r->post('/assignments/{assignmentId}/overrides/{id}/remove', [Teacher\SubmissionController::class, 'removeOverride']);

    $r->get('/review', [Teacher\SubmissionController::class, 'queue']);
    // A teacher's own saved feedback comments; the grading page inserts them.
    $r->get('/comments', [Teacher\CommentBankController::class, 'index']);
    $r->post('/comments', [Teacher\CommentBankController::class, 'store']);
    $r->post('/comments/{id}', [Teacher\CommentBankController::class, 'update']);
    $r->post('/comments/{id}/delete', [Teacher\CommentBankController::class, 'destroy']);
    $r->post('/comments/{id}/used', [Teacher\CommentBankController::class, 'used']);
    $r->get('/submissions/{id}', [Teacher\SubmissionController::class, 'show']);
    $r->post('/submissions/{id}/grade', [Teacher\SubmissionController::class, 'grade']);
    $r->post('/submissions/{id}/release', [Teacher\SubmissionController::class, 'release']);
    $r->post('/submissions/{id}/return', [Teacher\SubmissionController::class, 'returnForResubmission']);
    $r->post('/submissions/{id}/reopen', [Teacher\SubmissionController::class, 'reopen']);
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
    $r->get('/lessons/{id}/dll', [Teacher\LessonController::class, 'dll']);
    $r->post('/lessons/{id}/publish', [Teacher\LessonController::class, 'publish']);
    $r->post('/lessons/{id}/unpublish', [Teacher\LessonController::class, 'unpublish']);
    $r->post('/lessons/{id}/duplicate', [Teacher\LessonController::class, 'duplicate']);
    $r->post('/lessons/{id}/copy', [Teacher\LessonController::class, 'copy']);
    $r->post('/lessons/{id}/archive', [Teacher\LessonController::class, 'archive']);
});

// School administrators --------------------------------------------------------------
$router->group('/admin', ['role:admin', 'notices'], static function ($r): void {
    $r->get('', [Admin\DashboardController::class, 'index']);

    $r->get('/users', [Admin\UserController::class, 'index']);
    $r->get('/users/new', [Admin\UserController::class, 'create']);
    $r->post('/users', [Admin\UserController::class, 'store']);
    $r->get('/users/import', [Admin\ImportController::class, 'create']);
    $r->get('/users/import/template', [Admin\ImportController::class, 'template']);
    $r->get('/users/import/links', [Admin\ImportController::class, 'links']);
    $r->post('/users/import/email', [Admin\ImportController::class, 'emailLinks']);
    $r->post('/users/import', [Admin\ImportController::class, 'preview']);
    $r->post('/users/import/confirm', [Admin\ImportController::class, 'confirm']);
    $r->get('/users/{id}', [Admin\UserController::class, 'show']);
    $r->post('/users/{id}', [Admin\UserController::class, 'update']);
    $r->post('/users/{id}/status', [Admin\UserController::class, 'status']);
    $r->post('/users/{id}/link', [Admin\UserController::class, 'link']);
    $r->post('/users/{id}/slip', [Admin\UserController::class, 'slip']);
    $r->post('/users/{id}/ai', [Admin\UserController::class, 'ai']);
    $r->post('/users/{id}/guardian', [Admin\UserController::class, 'guardian']);
    $r->post('/users/{id}/two-step', [Admin\UserController::class, 'twoStepOff']);

    $r->get('/classes', [Admin\ClassController::class, 'index']);
    $r->get('/classes/new', [Admin\ClassController::class, 'create']);
    $r->post('/classes', [Admin\ClassController::class, 'store']);
    $r->get('/classes/{id}', [Admin\ClassController::class, 'show']);
    $r->post('/classes/{id}', [Admin\ClassController::class, 'update']);
    $r->post('/classes/{id}/members', [Admin\ClassController::class, 'addMembers']);
    $r->post('/classes/{classId}/members/{id}/remove', [Admin\ClassController::class, 'removeMember']);
    $r->post('/classes/{id}/status', [Admin\ClassController::class, 'status']);
    $r->post('/classes/{id}/slips', [Admin\ClassController::class, 'slips']);

    $r->get('/subjects', [Admin\StructureController::class, 'subjects']);
    $r->post('/subjects', [Admin\StructureController::class, 'storeSubject']);
    $r->post('/subjects/{id}', [Admin\StructureController::class, 'updateSubject']);
    $r->get('/academic-periods', [Admin\StructureController::class, 'periods']);
    $r->post('/academic-periods', [Admin\StructureController::class, 'storePeriod']);
    $r->post('/academic-periods/{id}', [Admin\StructureController::class, 'updatePeriod']);
    $r->post('/academic-periods/{id}/grading-periods', [Admin\StructureController::class, 'storeGradingPeriod']);
    $r->post('/academic-periods/{id}/grading-periods/split', [Admin\StructureController::class, 'splitGradingPeriods']);
    $r->post('/grading-periods/{id}', [Admin\StructureController::class, 'updateGradingPeriod']);
    $r->post('/grading-periods/{id}/delete', [Admin\StructureController::class, 'deleteGradingPeriod']);
    $r->get('/grading', [Admin\GradingController::class, 'index']);
    $r->post('/grading', [Admin\GradingController::class, 'save']);
    $r->post('/grading/preset', [Admin\GradingController::class, 'preset']);
    $r->post('/grading/groups', [Admin\GradingController::class, 'groups']);
    $r->post('/grading/transmutation', [Admin\GradingController::class, 'transmutation']);
    $r->get('/sections', [Admin\StructureController::class, 'sections']);
    $r->post('/sections/{id}', [Admin\StructureController::class, 'updateSection']);
    $r->post('/sections/{id}/delete', [Admin\StructureController::class, 'deleteSection']);

    $r->get('/usage', [Admin\UsageController::class, 'index']);
    $r->get('/feedback', [Admin\FeedbackController::class, 'index']);
    $r->get('/feedback/{id}', [Admin\FeedbackController::class, 'show']);
    $r->post('/feedback/{id}', [Admin\FeedbackController::class, 'update']);
    $r->get('/pilot', [Admin\PilotController::class, 'index']);
    $r->get('/pilot/export', [Admin\PilotController::class, 'export']);
    $r->get('/policies', [Admin\SettingsController::class, 'policies']);
    $r->post('/policies', [Admin\SettingsController::class, 'savePolicies']);
    $r->post('/policies/notices', [Admin\SettingsController::class, 'renewNotices']);
    $r->get('/settings', [Admin\SettingsController::class, 'school']);
    $r->post('/settings', [Admin\SettingsController::class, 'saveSchool']);
    $r->post('/settings/logo', [Admin\SettingsController::class, 'saveLogo']);
    $r->get('/integrations', [Admin\SettingsController::class, 'integrations']);
    $r->post('/integrations/test-email', [Admin\SettingsController::class, 'testEmail']);

    $r->get('/audit', [Admin\AuditController::class, 'index']);

    $r->get('/privacy', [Admin\PrivacyController::class, 'index']);
    $r->get('/privacy/requests/{id}', [Admin\PrivacyController::class, 'show']);
    $r->post('/privacy/requests/{id}', [Admin\PrivacyController::class, 'update']);
    $r->post('/privacy/requests/{id}/export', [Admin\PrivacyController::class, 'export']);
    $r->post('/privacy/requests/{id}/erase', [Admin\PrivacyController::class, 'erase']);
    $r->get('/privacy/reports/{id}', [Admin\PrivacyController::class, 'report']);
    $r->post('/privacy/reports/{id}', [Admin\PrivacyController::class, 'resolveReport']);
});
