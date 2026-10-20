<?php

declare(strict_types=1);

namespace Sibol\Controllers\Student;

use Sibol\Controllers\Controller;
use Sibol\Core\Db;
use Sibol\Core\Response;
use Sibol\Repositories\StudentRepo;
use Sibol\Services\AiAccess;
use Sibol\Services\Grades;
use Sibol\Services\Overrides;

/**
 * Personal progress from the student's own records only: released grades, submissions
 * and private practice. Every figure explains how it is calculated. No rankings.
 */
final class ProgressController extends Controller
{
    public function index(): Response
    {
        $user = $this->user();
        $uid = (int) $user['id'];
        $classes = StudentRepo::classes($user);

        $books = [];
        foreach ($classes as $class) {
            $book = Grades::classBook((int) $class['id'], (int) $user['organization_id'], true, $uid);
            $books[] = [
                'class' => $class, 'assignments' => $book['assignments'], 'cells' => $book['cells'][$uid] ?? [], 'overall' => $book['overall'][$uid] ?? null,
                'grading' => $book['grading'], 'periods' => $book['periods'], 'by_period' => $book['by_period'][$uid] ?? [], 'final' => $book['final'][$uid] ?? null,
                'transmuted' => $book['transmuted'],
            ];
        }

        // Work due so far by the student's own dates; excused work is not counted.
        $due = Overrides::dueSql('a');
        $work = Db::one(
            "SELECT
               SUM(CASE WHEN s.id IS NOT NULL THEN 1 ELSE 0 END) AS submitted,
               SUM(CASE WHEN s.id IS NOT NULL AND s.is_late = 0 THEN 1 ELSE 0 END) AS on_time,
               COUNT(*) AS due_so_far
             FROM assignments a
             JOIN class_memberships cm ON cm.class_id = a.class_id AND cm.user_id = :u1 AND cm.role = 'student' AND cm.status = 'active'
             LEFT JOIN submissions s ON s.id = (SELECT s2.id FROM submissions s2 WHERE s2.assignment_id = a.id AND s2.student_id = :u2 AND s2.status = 'submitted' ORDER BY s2.attempt_no LIMIT 1)"
             . Overrides::joinSql('a', 'u3') . "
             WHERE a.status IN ('published','closed') AND " . Overrides::notExcusedSql() . " AND $due IS NOT NULL AND $due <= UTC_TIMESTAMP()",
            ['u1' => $uid, 'u2' => $uid, 'u3' => $uid]
        );
        $released = Db::all(
            "SELECT s.score, s.points_possible, s.released_at, a.id AS assignment_id, a.title, a.kind, c.name AS class_name
             FROM submissions s JOIN assignments a ON a.id = s.assignment_id JOIN classes c ON c.id = a.class_id
             WHERE s.student_id = :u AND s.feedback_status = 'released' AND s.score IS NOT NULL AND s.points_possible > 0
             ORDER BY s.released_at",
            ['u' => $uid]
        );
        $practice = Db::all(
            "SELECT p.id, p.title, p.kind, p.topic, p.score, p.total, p.completed_at, c.name AS class_name
             FROM practice_sets p LEFT JOIN classes c ON c.id = p.class_id
             WHERE p.user_id = :u AND p.completed_at IS NOT NULL ORDER BY p.completed_at DESC LIMIT 12",
            ['u' => $uid]
        );
        $feedback = Db::all(
            "SELECT s.feedback, s.released_at, a.id AS assignment_id, a.title FROM submissions s JOIN assignments a ON a.id = s.assignment_id
             WHERE s.student_id = :u AND s.feedback_status = 'released' AND s.feedback IS NOT NULL AND s.feedback <> ''
             ORDER BY s.released_at DESC LIMIT 6",
            ['u' => $uid]
        );

        return $this->view('student/progress', [
            'title' => t('Progress'),
            'books' => $books,
            'work' => $work,
            'released' => $released,
            // Practice sets open only while the student's AI is on, so the list is left out when it is off.
            'practice' => AiAccess::studentOn($this->user()) ? $practice : null,
            'feedback' => $feedback,
        ]);
    }
}
