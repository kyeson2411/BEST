<?php

declare(strict_types=1);

namespace Sibol\Controllers\Teacher;

use DateTimeImmutable;
use Sibol\Controllers\Controller;
use Sibol\Core\Audit;
use Sibol\Core\Db;
use Sibol\Core\HttpException;
use Sibol\Core\Response;
use Sibol\Policies\Access;
use Sibol\Repositories\TeacherRepo;
use Sibol\Services\Notifier;
use Sibol\Services\Nudges;
use Sibol\Services\Settings;
use Sibol\Services\SignInSlips;
use Sibol\Services\Usernames;

final class ClassController extends Controller
{
    public function index(): Response
    {
        $user = $this->user();
        return $this->view('teacher/classes', [
            'title' => t('Classes'),
            'classes' => TeacherRepo::classes($user, true),
        ]);
    }

    public function show(int $id): Response
    {
        $user = $this->user();
        $class = Access::teacherClass($user, $id);
        $tabs = ['overview', 'roster', 'modules', 'announcements', 'shared'];
        $tab = in_array($this->request->str('tab'), $tabs, true) ? $this->request->str('tab') : 'overview';
        $q = $this->request->str('q');
        $rosterParams = ['c' => $id];
        $rosterFilter = '';
        if ($q !== '') {
            $rosterFilter = " AND (u.given_name LIKE :q1 OR u.family_name LIKE :q2 OR u.email LIKE :q3 OR CONCAT(u.given_name, ' ', u.family_name) LIKE :q4 OR u.username LIKE :q5)";
            $like = Db::like($q);
            $rosterParams += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like, 'q5' => $like];
        }
        $roster = Db::all(
            "SELECT u.id, u.given_name, u.family_name, u.preferred_name, u.email, u.username, u.status AS account_status, u.avatar_path,
                    cm.status, cm.joined_via, cm.joined_at, cm.withdrawn_at, cm.id AS membership_id,
                    (SELECT COUNT(*) FROM submissions s JOIN assignments a ON a.id = s.assignment_id WHERE a.class_id = cm.class_id AND s.student_id = u.id AND s.status = 'submitted') AS submitted
             FROM class_memberships cm JOIN users u ON u.id = cm.user_id
             WHERE cm.class_id = :c AND cm.role = 'student'" . $rosterFilter . '
             ORDER BY cm.status, u.family_name, u.given_name',
            $rosterParams
        );
        $publishedCount = (int) Db::value("SELECT COUNT(*) FROM assignments WHERE class_id = :c AND status IN ('published','closed') AND due_at <= UTC_TIMESTAMP()", ['c' => $id]);

        return $this->view('teacher/class', [
            'title' => $class['name'],
            'crumbs' => [[t('Classes'), '/teacher/classes'], [$class['name'], null]],
            'class' => $class,
            'tab' => $tab,
            'q' => $q,
            'roster' => $roster,
            'dueCount' => $publishedCount,
            'teachers' => Db::all("SELECT u.id, u.given_name, u.family_name, u.preferred_name FROM class_memberships cm JOIN users u ON u.id = cm.user_id WHERE cm.class_id = :c AND cm.role = 'teacher' AND cm.status = 'active'", ['c' => $id]),
            'modules' => Db::all(
                'SELECT mo.*, (SELECT COUNT(*) FROM learning_materials m WHERE m.module_id = mo.id AND m.visibility <> \'archived\') AS materials,
                        (SELECT COUNT(*) FROM assignments a WHERE a.module_id = mo.id AND a.status <> \'archived\') AS assignments
                 FROM modules mo WHERE mo.class_id = :c ORDER BY mo.position, mo.id',
                ['c' => $id]
            ),
            'announcements' => Db::all('SELECT a.*, u.given_name, u.family_name, u.preferred_name FROM announcements a LEFT JOIN users u ON u.id = a.author_id WHERE a.class_id = :c ORDER BY a.created_at DESC', ['c' => $id]),
            'shares' => Db::all(
                'SELECT se.*, u.given_name, u.family_name, u.preferred_name FROM shared_excerpts se JOIN users u ON u.id = se.student_id WHERE se.class_id = :c ORDER BY se.created_at DESC LIMIT 50',
                ['c' => $id]
            ),
            'work' => Db::all(
                "SELECT a.id, a.title, a.kind, a.status, a.due_at, a.opens_at,
                        (SELECT COUNT(DISTINCT s.student_id) FROM submissions s WHERE s.assignment_id = a.id AND s.status = 'submitted') AS submitted,
                        (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id AND s.status = 'submitted' AND s.grading_status <> 'graded') AS ungraded
                 FROM assignments a WHERE a.class_id = :c AND a.status <> 'archived' ORDER BY a.due_at IS NULL, a.due_at DESC LIMIT 8",
                ['c' => $id]
            ),
            'codePolicy' => (array) Settings::get((int) $user['organization_id'], 'class_codes', []),
            'nudges' => $tab === 'overview' && $class['status'] === 'active' ? Nudges::forClass($id, new DateTimeImmutable('now')) : [],
        ]);
    }

    /** Sends one student of the class a neutral reminder about work that is missing or late. */
    public function nudge(int $classId, int $id): Response
    {
        $user = $this->user();
        $class = Access::teacherClass($user, $classId);
        $student = Db::one(
            "SELECT u.* FROM class_memberships cm JOIN users u ON u.id = cm.user_id AND u.status = 'active'
             WHERE cm.class_id = :c AND cm.user_id = :u AND cm.role = 'student' AND cm.status = 'active'",
            ['c' => $classId, 'u' => $id]
        );
        if (!$student || $class['status'] !== 'active') {
            throw HttpException::notFound();
        }
        $result = Nudges::send($class, $student, new DateTimeImmutable('now'));
        if ($result === 'sent') {
            Audit::log('class.nudge_sent', 'class', $classId, ['student_id' => $id]);
        }
        $this->flash($result === 'sent' ? 'success' : 'info', [
            'sent' => t('Reminder sent to :name.', ['name' => user_name($student)]),
            'already' => t(':name already had a reminder about this class today.', ['name' => user_name($student)]),
            'muted' => t(':name turned these reminders off, so none was sent. Talk to them in class instead.', ['name' => user_name($student)]),
        ][$result]);
        return $this->redirect('/teacher/classes/' . $classId . '#nudges');
    }

    public function addStudent(int $id): Response
    {
        $user = $this->user();
        $class = Access::teacherClass($user, $id);
        $back = '/teacher/classes/' . $id . '?tab=roster';
        $data = $this->validate(['student' => 'required|string|max:190'], ['student' => t('Student username or email')], $back);
        $login = Usernames::clean((string) $data['student']);
        $student = Db::one('SELECT * FROM users WHERE ' . (str_contains($login, '@') ? 'email' : 'username') . ' = :l AND organization_id = :o', ['l' => $login, 'o' => (int) $user['organization_id']]);
        if (!$student || $student['role'] !== 'student') {
            $this->fail('student', t('No student at your school has that username or email. Ask your school administrator to add the student first.'), $back);
        }
        if ($student['status'] === 'deactivated') {
            $this->fail('student', t('That account is deactivated. Ask your school administrator.'), $back);
        }
        $membership = Db::one('SELECT * FROM class_memberships WHERE class_id = :c AND user_id = :u', ['c' => $id, 'u' => (int) $student['id']]);
        if ($membership && $membership['status'] === 'active') {
            $this->flash('info', t(':name is already in this class.', ['name' => user_name($student)]));
            return $this->redirect($back);
        }
        if ($membership) {
            Db::update('class_memberships', ['status' => 'active', 'withdrawn_at' => null, 'joined_via' => 'teacher', 'joined_at' => Db::now()], ['id' => (int) $membership['id']]);
        } else {
            Db::insert('class_memberships', ['class_id' => $id, 'user_id' => (int) $student['id'], 'role' => 'student', 'status' => 'active', 'joined_via' => 'teacher', 'joined_at' => Db::now()]);
        }
        Audit::log('class.student_added', 'class', $id, ['student_id' => (int) $student['id']]);
        Notifier::send((int) $user['organization_id'], [(int) $student['id']], 'class', t('You were added to :class', ['class' => $class['name']]), null, '/student/classes/' . $id);
        $this->flash('success', t(':name was added to the class.', ['name' => user_name($student)]));
        return $this->redirect($back);
    }

    public function withdrawStudent(int $classId, int $id): Response
    {
        $user = $this->user();
        Access::teacherClass($user, $classId);
        $membership = Db::one("SELECT cm.*, u.given_name, u.family_name, u.preferred_name FROM class_memberships cm JOIN users u ON u.id = cm.user_id WHERE cm.class_id = :c AND cm.user_id = :u AND cm.role = 'student'", ['c' => $classId, 'u' => $id]);
        if (!$membership) {
            throw HttpException::notFound();
        }
        Db::update('class_memberships', ['status' => 'withdrawn', 'withdrawn_at' => Db::now()], ['id' => (int) $membership['id']]);
        Audit::log('class.student_withdrawn', 'class', $classId, ['student_id' => $id]);
        $this->flash('success', t(':name was removed from the class. Their past work and grades are kept.', ['name' => user_name($membership)]));
        return $this->redirect('/teacher/classes/' . $classId . '?tab=roster');
    }

    /** Sign-in slips for the students of this class who have not set up their account yet. */
    public function slips(int $id): Response
    {
        $user = $this->user();
        $class = Access::teacherClass($user, $id);
        $students = $class['status'] === 'active' ? SignInSlips::invitedStudents([$id]) : [];
        if (!$students) {
            $this->flash('info', t('Every student in this class has already set up their account. Print a password slip for a student who forgot theirs.'));
            return $this->redirect('/teacher/classes/' . $id . '?tab=roster');
        }
        SignInSlips::issue($students, $user, $class['name'], '/teacher/classes/' . $id . '?tab=roster');
        return $this->redirect('/slips');
    }

    /**
     * One slip for a student in a class this teacher teaches or a section they advise: setup for an
     * account not set up yet, a new password otherwise. Audit-logged; the teacher never sees a password.
     */
    public function studentSlip(int $id): Response
    {
        $user = $this->user();
        $student = Access::studentAccount($user, $id);
        $back = SignInSlips::backPath($this->request->str('back'), 'teacher');
        if ($student['status'] === 'deactivated') {
            $this->flash('error', t('That account is deactivated. Ask your school administrator.'));
            return $this->redirect($back);
        }
        SignInSlips::issue([$student], $user, user_name($student), $back);
        return $this->redirect('/slips');
    }

    public function joinCode(int $id): Response
    {
        $user = $this->user();
        $class = Access::teacherClass($user, $id);
        $policy = (array) Settings::get((int) $user['organization_id'], 'class_codes', []);
        $action = $this->request->str('action');
        $back = '/teacher/classes/' . $id . '?tab=roster';
        if ($action === 'disable') {
            Db::update('classes', ['join_code_enabled' => 0, 'updated_at' => Db::now()], ['id' => $id]);
            Audit::log('class.code_disabled', 'class', $id);
            $this->flash('success', t('The class code is turned off. Students can no longer use it.'));
            return $this->redirect($back);
        }
        if (empty($policy['enabled'])) {
            throw HttpException::forbidden(t('Your school has turned off class codes.'));
        }
        $code = $this->newCode();
        $days = max(1, (int) ($policy['ttl_days'] ?? 14));
        Db::update('classes', [
            'join_code' => $code,
            'join_code_enabled' => 1,
            'join_code_expires_at' => gmdate('Y-m-d H:i:s', time() + $days * 86400),
            'updated_at' => Db::now(),
        ], ['id' => $id]);
        Audit::log('class.code_created', 'class', $id);
        $this->flash('success', t('New class code created. The previous code no longer works.'));
        return $this->redirect($back);
    }

    public function addModule(int $id): Response
    {
        $user = $this->user();
        Access::teacherClass($user, $id);
        $data = $this->validate(['title' => 'required|string|max:160', 'description' => 'nullable|string|max:500'], ['title' => t('Module title')], '/teacher/classes/' . $id . '?tab=modules');
        $position = (int) Db::value('SELECT COALESCE(MAX(position), 0) + 1 FROM modules WHERE class_id = :c', ['c' => $id]);
        Db::insert('modules', ['class_id' => $id, 'title' => $data['title'], 'description' => $data['description'], 'position' => $position, 'created_at' => Db::now(), 'updated_at' => Db::now()]);
        $this->flash('success', t('Module added.'));
        return $this->redirect('/teacher/classes/' . $id . '?tab=modules');
    }

    public function updateModule(int $classId, int $id): Response
    {
        $user = $this->user();
        Access::teacherClass($user, $classId);
        $module = Db::one('SELECT * FROM modules WHERE id = :id AND class_id = :c', ['id' => $id, 'c' => $classId]);
        if (!$module) {
            throw HttpException::notFound();
        }
        $back = '/teacher/classes/' . $classId . '?tab=modules';
        $direction = $this->request->str('move');
        if ($direction === 'up' || $direction === 'down') {
            $other = Db::one(
                'SELECT * FROM modules WHERE class_id = :c AND position ' . ($direction === 'up' ? '<' : '>') . ' :p ORDER BY position ' . ($direction === 'up' ? 'DESC' : 'ASC') . ' LIMIT 1',
                ['c' => $classId, 'p' => (int) $module['position']]
            );
            if ($other) {
                Db::transaction(static function () use ($module, $other): void {
                    Db::update('modules', ['position' => (int) $other['position']], ['id' => (int) $module['id']]);
                    Db::update('modules', ['position' => (int) $module['position']], ['id' => (int) $other['id']]);
                });
            }
            return $this->redirect($back);
        }
        $data = $this->validate(['title' => 'required|string|max:160', 'description' => 'nullable|string|max:500'], ['title' => t('Module title')], $back);
        Db::update('modules', ['title' => $data['title'], 'description' => $data['description'], 'updated_at' => Db::now()], ['id' => $id]);
        $this->flash('success', t('Module saved.'));
        return $this->redirect($back);
    }

    public function deleteModule(int $classId, int $id): Response
    {
        $user = $this->user();
        Access::teacherClass($user, $classId);
        $deleted = Db::delete('modules', ['id' => $id, 'class_id' => $classId]);
        if (!$deleted) {
            throw HttpException::notFound();
        }
        $this->flash('success', t('Module removed. Its materials and assignments were kept and are now under “Other”.'));
        return $this->redirect('/teacher/classes/' . $classId . '?tab=modules');
    }

    public function postAnnouncement(int $id): Response
    {
        $user = $this->user();
        $class = Access::teacherClass($user, $id);
        $data = $this->validate(['title' => 'required|string|max:200', 'body' => 'required|string|max:5000'], ['title' => t('Title'), 'body' => t('Message')], '/teacher/classes/' . $id . '?tab=announcements');
        $annId = Db::insert('announcements', ['organization_id' => (int) $user['organization_id'], 'class_id' => $id, 'author_id' => (int) $user['id'], 'title' => $data['title'], 'body' => $data['body'], 'created_at' => Db::now(), 'updated_at' => Db::now()]);
        Notifier::send((int) $user['organization_id'], Notifier::classStudents($id), 'announcement', $data['title'], $class['name'], '/student/classes/' . $id . '?tab=announcements', 'announcement:' . $annId);
        Audit::log('announcement.posted', 'class', $id);
        $this->flash('success', t('Announcement posted. Students have been notified in B.E.S.T. Learning and Interactive Study System.'));
        return $this->redirect('/teacher/classes/' . $id . '?tab=announcements');
    }

    public function deleteAnnouncement(int $classId, int $id): Response
    {
        $user = $this->user();
        Access::teacherClass($user, $classId);
        if (!Db::delete('announcements', ['id' => $id, 'class_id' => $classId])) {
            throw HttpException::notFound();
        }
        $this->flash('success', t('Announcement deleted.'));
        return $this->redirect('/teacher/classes/' . $classId . '?tab=announcements');
    }

    public function markShareSeen(int $classId, int $id): Response
    {
        $user = $this->user();
        Access::teacherClass($user, $classId);
        Db::exec('UPDATE shared_excerpts SET seen_at = :n WHERE id = :id AND class_id = :c AND seen_at IS NULL', ['n' => Db::now(), 'id' => $id, 'c' => $classId]);
        return $this->redirect('/teacher/classes/' . $classId . '?tab=shared');
    }

    private function newCode(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while ((int) Db::value('SELECT COUNT(*) FROM classes WHERE join_code = :c', ['c' => $code]) > 0);
        return $code;
    }
}
