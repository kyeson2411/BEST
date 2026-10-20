<?php

declare(strict_types=1);

namespace Sibol\Controllers\Teacher;

use Sibol\Controllers\Controller;
use Sibol\Core\Audit;
use Sibol\Core\Db;
use Sibol\Core\HttpException;
use Sibol\Core\Response;
use Sibol\Core\Session;
use Sibol\Policies\Access;
use Sibol\Repositories\TeacherRepo;
use Sibol\Services\Ai\AiException;
use Sibol\Services\Ai\Generators;
use Sibol\Services\Ai\Usage;
use Sibol\Services\Insights;

final class InsightsController extends Controller
{
    public function index(): Response
    {
        $user = $this->user();
        $classes = TeacherRepo::classes($user);
        if (count($classes) === 1) {
            return $this->redirect('/teacher/insights/' . (int) $classes[0]['id']);
        }
        return $this->view('teacher/class_picker', [
            'title' => t('Insights'),
            'lead' => t('Choose a class to see completion, question results and topics that may need reteaching.'),
            'classes' => $classes,
            'target' => '/teacher/insights/',
            'icon' => 'chart-column',
        ]);
    }

    public function show(int $id): Response
    {
        $user = $this->user();
        $class = Access::teacherClass($user, $id);
        $ideas = Session::get('insight_ideas');
        $ideasText = (is_array($ideas) && (int) ($ideas['class'] ?? 0) === $id) ? (string) $ideas['text'] : null;
        return $this->view('teacher/insights', [
            'title' => t('Insights: :class', ['class' => $class['name']]),
            'crumbs' => [[t('Insights'), '/teacher/insights'], [$class['name'], null]],
            'class' => $class,
            'topics' => Insights::topics($id),
            'competencies' => Insights::competencies($id),
            'completion' => Insights::completion($id),
            'questions' => Insights::questions($id),
            'enrolled' => (int) Db::value("SELECT COUNT(*) FROM class_memberships WHERE class_id = :c AND role = 'student' AND status = 'active'", ['c' => $id]),
            'ideas' => $ideasText,
            'aiRemaining' => Usage::remainingToday($user),
        ]);
    }

    /** Drill-down: how each student answered one question (teacher of the class only). */
    public function question(int $classId, int $id): Response
    {
        $user = $this->user();
        $class = Access::teacherClass($user, $classId);
        $q = Db::one('SELECT q.*, a.title AS assignment_title, a.id AS assignment_id FROM questions q JOIN assignments a ON a.id = q.assignment_id WHERE q.id = :q AND a.class_id = :c', ['q' => $id, 'c' => $classId]);
        if (!$q) {
            throw HttpException::notFound();
        }
        $rows = Db::all(
            "SELECT u.given_name, u.family_name, u.preferred_name, sa.response, sa.is_correct, sa.points_awarded, s.id AS submission_id, s.attempt_no, s.submitted_at
             FROM submission_answers sa JOIN submissions s ON s.id = sa.submission_id AND s.status = 'submitted' JOIN users u ON u.id = s.student_id
             WHERE sa.question_id = :q ORDER BY sa.is_correct, u.family_name",
            ['q' => $id]
        );
        return $this->view('teacher/insight_question', [
            'title' => t('Question results'),
            'crumbs' => [[t('Insights'), '/teacher/insights'], [$class['name'], '/teacher/insights/' . $classId], [t('Question'), null]],
            'class' => $class,
            'q' => $q,
            'options' => json_decode((string) $q['options'], true) ?: [],
            'key' => json_decode((string) $q['answer_key'], true) ?: [],
            'rows' => $rows,
            'katex' => true,
        ]);
    }

    public function ideas(int $id): Response
    {
        $user = $this->user();
        $class = Access::teacherClass($user, $id);
        $blocked = Usage::blockedReason($user, 'insights');
        if ($blocked !== null) {
            $this->flash('error', $blocked);
            return $this->redirect('/teacher/insights/' . $id);
        }
        try {
            $text = Generators::interventionIdeas($user, $class, Insights::topics($id, (int) $user['organization_id']));
        } catch (AiException $e) {
            $this->flash('error', $e->getMessage());
            return $this->redirect('/teacher/insights/' . $id);
        }
        Session::set('insight_ideas', ['class' => $id, 'text' => $text]);
        Audit::log('insights.ideas_requested', 'class', $id);
        return $this->redirect('/teacher/insights/' . $id . '#ideas');
    }
}
