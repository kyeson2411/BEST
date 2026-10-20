<?php

declare(strict_types=1);

namespace Sibol\Services;

use Sibol\Core\Db;
use Sibol\Policies\Access;

/**
 * Class insights computed from actual submissions. Small samples are flagged, and
 * nothing here labels individual students; it describes questions, topics and work.
 */
final class Insights
{
    public const MIN_STUDENTS = 3;
    public const WEAK_PCT = 60.0;

    /** @return list<array{topic:string,attempts:int,students:int,correct:int,correct_pct:float,questions:int}> */
    public static function topics(int $classId): array
    {
        $rows = Db::all(
            "SELECT COALESCE(NULLIF(q.topic, ''), :untagged) AS topic, COUNT(*) AS attempts, COUNT(DISTINCT s.student_id) AS students,
                    SUM(sa.is_correct) AS correct, COUNT(DISTINCT q.id) AS questions
             FROM submission_answers sa
             JOIN submissions s ON s.id = sa.submission_id AND s.status = 'submitted'
             JOIN questions q ON q.id = sa.question_id
             JOIN assignments a ON a.id = q.assignment_id
             WHERE a.class_id = :c AND sa.is_correct IS NOT NULL
             GROUP BY topic",
            ['c' => $classId, 'untagged' => t('No topic set')]
        );
        $out = array_map(static fn ($r) => [
            'topic' => (string) $r['topic'],
            'attempts' => (int) $r['attempts'],
            'students' => (int) $r['students'],
            'correct' => (int) $r['correct'],
            'correct_pct' => (int) $r['attempts'] > 0 ? round((int) $r['correct'] / (int) $r['attempts'] * 100, 1) : 0.0,
            'questions' => (int) $r['questions'],
        ], $rows);
        usort($out, static fn ($a, $b) => $a['correct_pct'] <=> $b['correct_pct']);
        return $out;
    }

    /** @return list<array<string,mixed>> per objective question */
    public static function questions(int $classId, ?int $assignmentId = null): array
    {
        $params = ['c' => $classId];
        $filter = '';
        if ($assignmentId !== null) {
            $filter = ' AND a.id = :a';
            $params['a'] = $assignmentId;
        }
        $rows = Db::all(
            "SELECT q.id, q.prompt, q.qtype, q.topic, q.options, q.answer_key, a.id AS assignment_id, a.title AS assignment_title,
                    COUNT(sa.id) AS attempts, COALESCE(SUM(sa.is_correct), 0) AS correct, COUNT(DISTINCT s.student_id) AS students
             FROM questions q
             JOIN assignments a ON a.id = q.assignment_id
             LEFT JOIN submission_answers sa ON sa.question_id = q.id AND sa.is_correct IS NOT NULL
             LEFT JOIN submissions s ON s.id = sa.submission_id AND s.status = 'submitted'
             WHERE a.class_id = :c AND q.qtype <> 'essay' AND a.status IN ('published','closed')" . $filter . '
             GROUP BY q.id ORDER BY a.due_at DESC, q.position',
            $params
        );
        foreach ($rows as &$r) {
            $r['correct_pct'] = (int) $r['attempts'] > 0 ? round((int) $r['correct'] / (int) $r['attempts'] * 100, 1) : null;
            $wrong = Db::one(
                "SELECT sa.response, COUNT(*) AS n FROM submission_answers sa JOIN submissions s ON s.id = sa.submission_id AND s.status = 'submitted'
                 WHERE sa.question_id = :q AND sa.is_correct = 0 AND sa.response IS NOT NULL AND sa.response <> ''
                 GROUP BY sa.response ORDER BY n DESC LIMIT 1",
                ['q' => (int) $r['id']]
            );
            $r['common_wrong'] = $wrong ? ['response' => (string) $wrong['response'], 'count' => (int) $wrong['n']] : null;
        }
        return $rows;
    }

    /** Completion and results per assignment. @return list<array<string,mixed>> */
    public static function completion(int $classId): array
    {
        return Db::all(
            "SELECT a.id, a.title, a.kind, a.due_at, a.points_possible,
                    " . Overrides::expectedSql('a') . ' AS enrolled, ' . Overrides::handedInSql('a') . " AS submitted,
                    (SELECT COUNT(DISTINCT s.student_id) FROM submissions s WHERE s.assignment_id = a.id AND s.status = 'submitted' AND s.is_late = 1) AS late,
                    (SELECT AVG(s.score / NULLIF(s.points_possible, 0) * 100) FROM submissions s WHERE s.assignment_id = a.id AND s.status = 'submitted' AND s.grading_status = 'graded') AS avg_pct,
                    (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id AND s.status = 'submitted' AND s.grading_status = 'graded') AS graded
             FROM assignments a WHERE a.class_id = :c AND a.status IN ('published','closed') ORDER BY a.due_at",
            ['c' => $classId]
        );
    }

    /** Weak topics across the teacher's classes, for the dashboard. @return list<array<string,mixed>> */
    public static function reteachAcrossClasses(array $user, int $limit = 3): array
    {
        $out = [];
        foreach (Access::teacherClassIds($user) as $classId) {
            $name = (string) Db::value('SELECT name FROM classes WHERE id = :c', ['c' => $classId]);
            foreach (self::topics($classId) as $t) {
                if ($t['students'] >= self::MIN_STUDENTS && $t['correct_pct'] < self::WEAK_PCT) {
                    $out[] = $t + ['class_id' => $classId, 'class_name' => $name];
                }
            }
        }
        usort($out, static fn ($a, $b) => $a['correct_pct'] <=> $b['correct_pct']);
        return array_slice($out, 0, $limit);
    }
}
