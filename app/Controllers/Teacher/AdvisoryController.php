<?php

declare(strict_types=1);

namespace Sibol\Controllers\Teacher;

use Sibol\Controllers\Controller;
use Sibol\Core\Db;
use Sibol\Core\Response;
use Sibol\Policies\Access;
use Sibol\Services\Sections;
use Sibol\Services\SignInSlips;

/** A class adviser's section: its students' accounts, with sign-in and password slips. */
final class AdvisoryController extends Controller
{
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
                           FROM class_memberships m JOIN users u ON u.id = m.user_id WHERE m.class_id = c.id AND m.role = 'teacher' AND m.status = 'active') AS teachers
                 FROM classes c JOIN subjects s ON s.id = c.subject_id
                 WHERE c.section_id = :s AND c.status = 'active' ORDER BY s.name",
                ['s' => $id]
            ),
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
