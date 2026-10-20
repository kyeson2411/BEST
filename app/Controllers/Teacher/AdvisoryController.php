<?php

declare(strict_types=1);

namespace Sibol\Controllers\Teacher;

use Sibol\Controllers\Controller;
use Sibol\Controllers\SetsStudentAi;
use Sibol\Core\Audit;
use Sibol\Core\Db;
use Sibol\Core\Response;
use Sibol\Policies\Access;
use Sibol\Services\AiAccess;
use Sibol\Services\ProgressReports;
use Sibol\Services\Sections;
use Sibol\Services\SignInSlips;

/**
 * A class adviser's section: its students' accounts, with sign-in and password slips, each student's AI
 * companion setting and guardian answers, and progress reports across the section's subjects.
 */
final class AdvisoryController extends Controller
{
    use SetsStudentAi;

    public function index(): Response
    {
        $user = $this->user();
        $sections = Sections::advisedBy($user);
        if (count($sections) === 1) {
            return $this->redirect('/teacher/advisory/' . (int) $sections[0]['id']);
        }
        return $this->view('teacher/advisory_index', [
            'title' => t('Advisory'),
            'sections' => $sections,
        ]);
    }

    public function show(int $id): Response
    {
        $user = $this->user();
        $section = Access::adviserSection($user, $id);
        $label = Sections::label($section);
        return $this->view('teacher/advisory', [
            'title' => $label,
            'crumbs' => [[t('Advisory'), '/teacher/advisory'], [$label, null]],
            'section' => $section,
            'label' => $label,
            'students' => Sections::students($id),
            'classes' => Db::all(
                "SELECT c.id, c.name, s.name AS subject_name,
                        (SELECT GROUP_CONCAT(CONCAT(COALESCE(NULLIF(u.preferred_name, ''), u.given_name), ' ', u.family_name) ORDER BY u.family_name SEPARATOR ', ')
                           FROM class_memberships m STRAIGHT_JOIN users u ON u.id = m.user_id WHERE m.class_id = c.id AND m.role = 'teacher' AND m.status = 'active') AS teachers
                 FROM classes c JOIN subjects s ON s.id = c.subject_id
                 WHERE c.section_id = :s AND c.status = 'active' ORDER BY s.name",
                ['s' => $id]
            ),
        ]);
    }

    /** One student of the section: their AI companion setting and their guardian's answers. */
    public function student(int $sectionId, int $id): Response
    {
        $user = $this->user();
        [$section, $student] = Access::advisedStudent($user, $sectionId, $id);
        $label = Sections::label($section);
        return $this->view('teacher/advisory_student', [
            'title' => user_name($student),
            'crumbs' => [[t('Advisory'), '/teacher/advisory'], [$label, '/teacher/advisory/' . $sectionId], [user_name($student), null]],
            'section' => $section,
            'student' => $student,
            'answers' => AiAccess::guardianAnswers($id),
            'action' => '/teacher/advisory/' . $sectionId . '/students/' . $id,
        ]);
    }

    public function studentAi(int $sectionId, int $id): Response
    {
        $user = $this->user();
        [, $student] = Access::advisedStudent($user, $sectionId, $id);
        $back = '/teacher/advisory/' . $sectionId . '/students/' . $id;
        $this->saveAiSetting($user, $student, $back);
        return $this->redirect($back);
    }

    public function studentGuardian(int $sectionId, int $id): Response
    {
        $user = $this->user();
        [, $student] = Access::advisedStudent($user, $sectionId, $id);
        $back = '/teacher/advisory/' . $sectionId . '/students/' . $id;
        $this->saveGuardianAnswer($user, $student, $back);
        return $this->redirect($back);
    }

    /** Progress reports for every student of the section, one printed page each. */
    public function reports(int $id): Response
    {
        $user = $this->user();
        $section = Access::adviserSection($user, $id);
        $students = array_values(array_filter(Sections::students($id), static fn (array $s): bool => $s['status'] !== 'deactivated'));
        return $this->reportView($user, $section, $students, '/teacher/advisory/' . $id . '/reports', t('Progress reports'));
    }

    /** One student's progress report: the section's subjects they take. */
    public function report(int $sectionId, int $id): Response
    {
        $user = $this->user();
        [$section, $student] = Access::advisedStudent($user, $sectionId, $id);
        return $this->reportView($user, $section, [$student], '/teacher/advisory/' . $sectionId . '/students/' . $id . '/report', user_name($student));
    }

    /** @param list<array<string,mixed>> $students */
    private function reportView(array $user, array $section, array $students, string $base, string $title): Response
    {
        $label = Sections::label($section);
        $built = ProgressReports::build((int) $user['organization_id'], $students, ProgressReports::sectionClassIds((int) $section['id']), $this->request->str('period') ?: null);
        // Grades across every subject of the section: who opened them is recorded, never what they show.
        Audit::log('progress_report.opened', 'section', (int) $section['id'], ['students' => count($students), 'period' => isset($built['period']['id']) ? (int) $built['period']['id'] : null]);
        return $this->view('shared/progress_reports', [
            'title' => $title,
            'crumbs' => [[t('Advisory'), '/teacher/advisory'], [$label, '/teacher/advisory/' . (int) $section['id']], [$title, null]],
            'lead' => t('Released grades, missing and late work, and teachers\' comments in this section\'s subjects for one grading period. Each student prints on their own page.'),
            'built' => $built,
            'students' => array_map(static fn (array $s): array => ['student' => $s, 'section' => $label, 'adviser' => user_name($user)], $students),
            'base' => $base,
            'page_class' => 'page--print',
        ]);
    }

    public function slips(int $id): Response
    {
        $user = $this->user();
        $section = Access::adviserSection($user, $id);
        $classIds = array_map('intval', Db::column("SELECT id FROM classes WHERE section_id = :s AND status = 'active'", ['s' => $id]));
        $students = SignInSlips::invitedStudents($classIds);
        if (!$students) {
            $this->flash('info', t('Every student in this section has already set up their account.'));
            return $this->redirect('/teacher/advisory/' . $id);
        }
        $label = Sections::label($section);
        foreach ($students as &$student) {
            $student['group'] = $label;
        }
        unset($student);
        SignInSlips::issue($students, $user, $label, '/teacher/advisory/' . $id);
        return $this->redirect('/slips');
    }
}
