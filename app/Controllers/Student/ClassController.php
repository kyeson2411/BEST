<?php

declare(strict_types=1);

namespace Sibol\Controllers\Student;

use Sibol\Controllers\Controller;
use Sibol\Core\Audit;
use Sibol\Core\Db;
use Sibol\Core\HttpException;
use Sibol\Core\RateLimiter;
use Sibol\Core\Response;
use Sibol\Policies\Access;
use Sibol\Repositories\StudentRepo;
use Sibol\Services\AiAccess;
use Sibol\Services\Notifier;
use Sibol\Services\Settings;

final class ClassController extends Controller
{
    public function index(): Response
    {
        $user = $this->user();
        return $this->view('student/classes', [
            'title' => t('Classes'),
            'classes' => StudentRepo::classes($user),
            'codesEnabled' => (bool) Settings::get((int) $user['organization_id'], 'class_codes.enabled', true),
        ]);
    }

    public function join(): Response
    {
        $user = $this->user();
        if (!Settings::get((int) $user['organization_id'], 'class_codes.enabled', true)) {
            throw HttpException::forbidden(t('Your school has turned off joining classes with a code. Ask your teacher to add you.'));
        }
        if (!RateLimiter::attempt('join-code', (string) $user['id'], 10, 600)) {
            $this->fail('code', t('Too many tries. Wait 10 minutes, then check the code with your teacher.'), '/student/classes');
        }
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $this->request->str('code')) ?? '');
        if ($code === '' || strlen($code) > 12) {
            $this->fail('code', t('Enter the class code your teacher gave you.'), '/student/classes');
        }
        $class = Db::one(
            "SELECT * FROM classes WHERE join_code = :c AND organization_id = :o AND status = 'active'",
            ['c' => $code, 'o' => (int) $user['organization_id']]
        );
        if (!$class || !(int) $class['join_code_enabled'] || ($class['join_code_expires_at'] !== null && $class['join_code_expires_at'] < Db::now())) {
            $this->fail('code', t('That code is not valid or has expired. Check it with your teacher.'), '/student/classes');
        }
        $membership = Db::one('SELECT * FROM class_memberships WHERE class_id = :c AND user_id = :u', ['c' => (int) $class['id'], 'u' => (int) $user['id']]);
        if ($membership && $membership['status'] === 'active') {
            $this->flash('info', t('You are already in :class.', ['class' => $class['name']]));
            return $this->redirect('/student/classes/' . (int) $class['id']);
        }
        if ($membership && $membership['status'] === 'withdrawn') {
            $this->fail('code', t('You were removed from this class, so the code cannot add you back. Ask your teacher.'), '/student/classes');
        }
        Db::insert('class_memberships', [
            'class_id' => (int) $class['id'], 'user_id' => (int) $user['id'], 'role' => 'student',
            'status' => 'active', 'joined_via' => 'code', 'joined_at' => Db::now(),
        ]);
        Audit::log('class.joined_by_code', 'class', (int) $class['id']);
        Notifier::send((int) $user['organization_id'], Notifier::classTeachers((int) $class['id']), 'class',
            t(':name joined :class', ['name' => user_name($user), 'class' => $class['name']]), t('Joined with the class code.'),
            '/teacher/classes/' . (int) $class['id'] . '?tab=roster');
        $this->flash('success', t('You joined :class.', ['class' => $class['name']]));
        return $this->redirect('/student/classes/' . (int) $class['id']);
    }

    public function show(int $id): Response
    {
        $user = $this->user();
        $class = Access::studentClass($user, $id);
        $tab = in_array($this->request->str('tab'), ['materials', 'work', 'announcements'], true) ? $this->request->str('tab') : 'overview';

        $teachers = Db::all(
            "SELECT u.id, u.given_name, u.family_name, u.preferred_name, u.avatar_path FROM class_memberships cm JOIN users u ON u.id = cm.user_id
             WHERE cm.class_id = :c AND cm.role = 'teacher' AND cm.status = 'active' ORDER BY u.family_name",
            ['c' => $id]
        );
        $modules = Db::all('SELECT * FROM modules WHERE class_id = :c ORDER BY position, id', ['c' => $id]);
        $materials = Db::all(
            "SELECT m.*, mv.extension, mv.size_bytes, mv.processing_status, mv.page_count,
                    u.given_name AS by_given, u.family_name AS by_family, u.preferred_name AS by_preferred,
                    (SELECT COUNT(*) FROM content_views v WHERE v.user_id = :u AND v.content_type = 'material' AND v.content_id = m.id) AS seen,
                    (SELECT GROUP_CONCAT(rv.kind) FROM reading_versions rv WHERE rv.material_id = m.id AND rv.status = 'approved') AS reading_kinds
             FROM learning_materials m
             LEFT JOIN material_versions mv ON mv.id = m.current_version_id
             LEFT JOIN users u ON u.id = m.created_by
             WHERE m.class_id = :c AND " . Access::releasedMaterialSql('m') . '
             ORDER BY COALESCE(m.release_at, m.published_at) DESC',
            ['u' => (int) $user['id'], 'c' => $id]
        );
        $lessons = Db::all("SELECT id, title, module_id, duration_minutes, published_at FROM lessons WHERE class_id = :c AND status = 'published' ORDER BY published_at DESC", ['c' => $id]);
        $announcements = Db::all(
            'SELECT a.*, u.given_name, u.family_name, u.preferred_name FROM announcements a LEFT JOIN users u ON u.id = a.author_id
             WHERE a.class_id = :c ORDER BY a.created_at DESC',
            ['c' => $id]
        );
        $work = StudentRepo::assignments($user, 'all', $id);

        return $this->view('student/class', [
            'title' => $class['name'],
            'crumbs' => [[t('Classes'), '/student/classes'], [$class['name'], null]],
            'class' => $class,
            'tab' => $tab,
            'teachers' => $teachers,
            'modules' => $modules,
            'materials' => $materials,
            'lessons' => $lessons,
            'announcements' => $announcements,
            'work' => $work,
            'aiEnabled' => AiAccess::allowed($user),
            'scripts' => $tab === 'materials' ? ['offline.js'] : [],
        ]);
    }

    public function lesson(int $classId, int $id): Response
    {
        $user = $this->user();
        $class = Access::studentClass($user, $classId);
        $lesson = Db::one("SELECT * FROM lessons WHERE id = :id AND class_id = :c AND status = 'published'", ['id' => $id, 'c' => $classId]);
        if (!$lesson) {
            throw HttpException::notFound();
        }
        \Sibol\Services\Materials::recordView((int) $user['id'], 'lesson', $id, $classId);
        return $this->view('student/lesson', [
            'title' => $lesson['title'],
            'crumbs' => [[t('Classes'), '/student/classes'], [$class['name'], '/student/classes/' . $classId], [$lesson['title'], null]],
            'class' => $class,
            'lesson' => $lesson,
            'content' => \Sibol\Controllers\Teacher\LessonController::forStudents(json_decode((string) $lesson['published_content'], true) ?: []),
            'katex' => true,
        ]);
    }
}
