<?php

declare(strict_types=1);

namespace Sibol\Controllers\Admin;

use Sibol\Controllers\Controller;
use Sibol\Core\Audit;
use Sibol\Core\Db;
use Sibol\Core\HttpException;
use Sibol\Core\Paginator;
use Sibol\Core\Response;
use Sibol\Services\Notifier;
use Sibol\Services\Sections;
use Sibol\Services\Settings;
use Sibol\Services\SignInSlips;

/** Classes (sections) across the school: who teaches and who studies where. */
final class ClassController extends Controller
{
    public function index(): Response
    {
        $o = $this->orgId();
        $q = trim($this->request->str('q'));
        $subject = $this->request->int('subject_id');
        $period = $this->request->int('period_id');
        $status = $this->request->str('status') === 'archived' ? 'archived' : 'active';
        $where = ['c.organization_id = :o', 'c.status = :status'];
        $params = ['o' => $o, 'status' => $status];
        if ($q !== '') {
            $where[] = '(c.name LIKE :q1 OR c.section LIKE :q2)';
            $params += ['q1' => Db::like($q), 'q2' => Db::like($q)];
        }
        if ($subject) {
            $where[] = 'c.subject_id = :s';
            $params['s'] = $subject;
        }
        if ($period) {
            $where[] = 'c.academic_period_id = :p';
            $params['p'] = $period;
        }
        $sql = ' FROM classes c JOIN subjects s ON s.id = c.subject_id LEFT JOIN academic_periods ap ON ap.id = c.academic_period_id WHERE ' . implode(' AND ', $where);
        $pager = new Paginator((int) Db::value('SELECT COUNT(*)' . $sql, $params), $this->page(), 30);
        $rows = Db::all(
            "SELECT c.id, c.name, c.section, c.grade_level, c.status, s.name AS subject_name, ap.name AS period_name,
                    (SELECT COUNT(*) FROM class_memberships m WHERE m.class_id = c.id AND m.role = 'student' AND m.status = 'active') AS students,
                    (SELECT GROUP_CONCAT(CONCAT(COALESCE(NULLIF(u.preferred_name, ''), u.given_name), ' ', u.family_name) ORDER BY u.family_name SEPARATOR ', ')
                       FROM class_memberships m STRAIGHT_JOIN users u ON u.id = m.user_id WHERE m.class_id = c.id AND m.role = 'teacher' AND m.status = 'active') AS teachers"
            . $sql . ' ORDER BY s.name, c.name' . $pager->limitSql(),
            $params
        );
        return $this->view('admin/classes', [
            'title' => t('Classes'),
            'rows' => $rows,
            'pager' => $pager,
            'q' => $q,
            'subjectId' => $subject,
            'periodId' => $period,
            'status' => $status,
            'subjects' => $this->subjects($o),
            'periods' => $this->periods($o),
        ]);
    }

    public function create(): Response
    {
        $o = $this->orgId();
        $subjects = $this->subjects($o);
        if (!$subjects) {
            $this->flash('info', t('Add a subject first. Every class belongs to a subject.'));
            return $this->redirect('/admin/subjects');
        }
        return $this->view('admin/class_form', [
            'title' => t('New class'),
            'crumbs' => [[t('Classes'), '/admin/classes'], [t('New class'), null]],
            'subjects' => $subjects,
            'periods' => $this->periods($o),
            'currentPeriod' => Db::value('SELECT id FROM academic_periods WHERE organization_id = :o AND is_current = 1 LIMIT 1', ['o' => $o]),
            'teachers' => $this->teachers($o),
            'grades' => (array) Settings::get($o, 'grade_levels', []),
        ]);
    }

    public function store(): Response
    {
        $admin = $this->user();
        $o = (int) $admin['organization_id'];
        $data = $this->validate($this->rules() + ['teacher_id' => 'nullable|int'], $this->labels(), '/admin/classes/new');
        $this->checkRefs($data, $o, '/admin/classes/new');
        if ($data['teacher_id'] && !Db::value("SELECT id FROM users WHERE id = :id AND organization_id = :o AND role = 'teacher' AND status <> 'deactivated'", ['id' => $data['teacher_id'], 'o' => $o])) {
            $this->fail('teacher_id', t('Choose a teacher from your school.'), '/admin/classes/new');
        }
        $id = Db::transaction(static function () use ($data, $o, $admin): int {
            $id = Db::insert('classes', [
                'organization_id' => $o,
                'subject_id' => $data['subject_id'],
                'academic_period_id' => $data['academic_period_id'],
                'name' => $data['name'],
                'section' => $data['section'],
                'section_id' => Sections::forClass($o, $data['academic_period_id'], $data['grade_level'], $data['section']),
                'grade_level' => $data['grade_level'],
                'description' => $data['description'],
                'status' => 'active',
                'created_by' => (int) $admin['id'],
                'created_at' => Db::now(),
                'updated_at' => Db::now(),
            ]);
            if ($data['teacher_id']) {
                Db::insert('class_memberships', ['class_id' => $id, 'user_id' => $data['teacher_id'], 'role' => 'teacher', 'status' => 'active', 'joined_via' => 'admin', 'joined_at' => Db::now()]);
            }
            return $id;
        });
        if ($data['teacher_id']) {
            Notifier::send($o, [(int) $data['teacher_id']], 'class', t('You were added to :class', ['class' => $data['name']]), t('As a teacher.'), '/teacher/classes/' . $id);
        }
        Audit::log('class.created', 'class', $id, ['name' => $data['name']]);
        $this->flash('success', t('Class created. Add students next.'));
        return $this->redirect('/admin/classes/' . $id);
    }

    public function show(int $id): Response
    {
        $o = $this->orgId();
        $class = $this->find($id, $o);
        $members = Db::all(
            'SELECT m.id AS membership_id, m.role, m.status, m.joined_via, m.joined_at, m.withdrawn_at,
                    u.id, u.given_name, u.family_name, u.preferred_name, u.email, u.username, u.avatar_path, u.status AS user_status, u.grade_level
             FROM class_memberships m JOIN users u ON u.id = m.user_id
             WHERE m.class_id = :c ORDER BY m.role DESC, m.status, u.family_name, u.given_name',
            ['c' => $id]
        );
        $memberIds = array_map('intval', array_column($members, 'id'));
        $candidateSql = "SELECT id, given_name, family_name, preferred_name, email, grade_level, status FROM users
                         WHERE organization_id = :o AND role = 'student' AND status <> 'deactivated'";
        $params = ['o' => $o];
        if ($class['grade_level']) {
            $candidateSql .= ' AND grade_level = :g';
            $params['g'] = $class['grade_level'];
        }
        $candidates = array_values(array_filter(
            Db::all($candidateSql . ' ORDER BY family_name, given_name LIMIT 300', $params),
            static fn ($u) => !in_array((int) $u['id'], $memberIds, true)
        ));
        $teachers = array_values(array_filter($this->teachers($o), static fn ($t) => !in_array((int) $t['id'], $memberIds, true)));
        $activity = Db::one(
            "SELECT (SELECT COUNT(*) FROM assignments WHERE class_id = :c1 AND status IN ('published','closed')) AS assignments,
                    (SELECT COUNT(*) FROM learning_materials WHERE class_id = :c2 AND visibility = 'published') AS materials,
                    (SELECT COUNT(*) FROM lessons WHERE class_id = :c3 AND status = 'published') AS lessons",
            ['c1' => $id, 'c2' => $id, 'c3' => $id]
        );
        return $this->view('admin/class', [
            'title' => $class['name'],
            'crumbs' => [[t('Classes'), '/admin/classes'], [$class['name'], null]],
            'class' => $class,
            'members' => $members,
            'candidates' => $candidates,
            'teachers' => $teachers,
            'subjects' => $this->subjects($o),
            'periods' => $this->periods($o),
            'grades' => (array) Settings::get($o, 'grade_levels', []),
            'activity' => $activity,
        ]);
    }

    public function update(int $id): Response
    {
        $o = $this->orgId();
        $this->find($id, $o);
        $back = '/admin/classes/' . $id;
        $data = $this->validate($this->rules(), $this->labels(), $back);
        $this->checkRefs($data, $o, $back);
        Db::update('classes', [
            'subject_id' => $data['subject_id'],
            'academic_period_id' => $data['academic_period_id'],
            'name' => $data['name'],
            'section' => $data['section'],
            'section_id' => Sections::forClass($o, $data['academic_period_id'], $data['grade_level'], $data['section']),
            'grade_level' => $data['grade_level'],
            'description' => $data['description'],
            'updated_at' => Db::now(),
        ], ['id' => $id]);
        Audit::log('class.updated', 'class', $id);
        $this->flash('success', t('Changes saved.'));
        return $this->redirect($back);
    }

    public function addMembers(int $id): Response
    {
        $o = $this->orgId();
        $class = $this->find($id, $o);
        $back = '/admin/classes/' . $id;
        if ($class['status'] !== 'active') {
            $this->flash('error', t('Restore the class before changing its members.'));
            return $this->redirect($back);
        }
        $role = $this->request->str('role') === 'teacher' ? 'teacher' : 'student';
        $ids = array_map('intval', $this->request->arr('user_ids'));
        if ($this->request->int('user_id')) {
            $ids[] = (int) $this->request->int('user_id');
        }
        $missing = [];
        $names = preg_split('/[\s,;]+/', mb_strtolower($this->request->str('emails')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach (array_unique($names) as $name) {
            // An email address or a username.
            $uid = Db::value('SELECT id FROM users WHERE organization_id = :o AND ' . (str_contains($name, '@') ? 'email' : 'username') . ' = :e AND role = :r', ['o' => $o, 'e' => $name, 'r' => $role]);
            if ($uid) {
                $ids[] = (int) $uid;
            } else {
                $missing[] = $name;
            }
        }
        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids) {
            [$in, $params] = Db::in($ids, 'u');
            $ids = array_map('intval', Db::column("SELECT id FROM users WHERE organization_id = :o AND role = :r AND status <> 'deactivated' AND id IN " . $in, $params + ['o' => $o, 'r' => $role]));
        }
        $added = [];
        Db::transaction(static function () use ($ids, $id, $role, &$added): void {
            foreach ($ids as $uid) {
                $existing = Db::one('SELECT id, status FROM class_memberships WHERE class_id = :c AND user_id = :u', ['c' => $id, 'u' => $uid]);
                if ($existing && $existing['status'] === 'active') {
                    continue;
                }
                if ($existing) {
                    Db::update('class_memberships', ['status' => 'active', 'withdrawn_at' => null, 'joined_at' => Db::now(), 'joined_via' => 'admin'], ['id' => (int) $existing['id']]);
                } else {
                    Db::insert('class_memberships', ['class_id' => $id, 'user_id' => $uid, 'role' => $role, 'status' => 'active', 'joined_via' => 'admin', 'joined_at' => Db::now()]);
                }
                $added[] = $uid;
            }
        });
        if ($added) {
            Notifier::send($o, $added, 'class', t('You were added to :class', ['class' => $class['name']]), $role === 'teacher' ? t('As a teacher.') : null, ($role === 'teacher' ? '/teacher/classes/' : '/student/classes/') . $id);
            Audit::log('class.member_added', 'class', $id, ['count' => count($added), 'role' => $role]);
        }
        if ($missing) {
            $this->flash('warning', t('No :role account was found for: :names. Invite them first.', ['role' => lcfirst_u(role_label($role)), 'names' => implode(', ', array_slice($missing, 0, 10))]));
        }
        $this->flash($added ? 'success' : 'info', $added ? tp(':count person added.', ':count people added.', count($added)) : t('No one new was added.'));
        return $this->redirect($back . '#members');
    }

    public function removeMember(int $classId, int $id): Response
    {
        $o = $this->orgId();
        $this->find($classId, $o);
        $membership = Db::one('SELECT * FROM class_memberships WHERE id = :id AND class_id = :c', ['id' => $id, 'c' => $classId]);
        if (!$membership) {
            throw HttpException::notFound();
        }
        Db::update('class_memberships', ['status' => 'withdrawn', 'withdrawn_at' => Db::now()], ['id' => $id]);
        Audit::log('class.member_removed', 'class', $classId, ['role' => $membership['role']]);
        $this->flash('success', $membership['role'] === 'student'
            ? t('Removed from the class. Their past work and grades are kept.')
            : t('Removed from the class. They no longer see it.'));
        return $this->redirect('/admin/classes/' . $classId . '#members');
    }

    /** Sign-in slips for the class's students who have not set up their account yet. */
    public function slips(int $id): Response
    {
        $admin = $this->user();
        $class = $this->find($id, (int) $admin['organization_id']);
        $students = SignInSlips::invitedStudents([$id]);
        if (!$students || $class['status'] !== 'active') {
            $this->flash('info', t('Every student in this class has already set up their account. Print a password slip from a student\'s page if someone forgot theirs.'));
            return $this->redirect('/admin/classes/' . $id . '#members');
        }
        SignInSlips::issue($students, $admin, $class['name'], '/admin/classes/' . $id);
        return $this->redirect('/slips');
    }

    public function status(int $id): Response
    {
        $o = $this->orgId();
        $class = $this->find($id, $o);
        $archive = $this->request->str('action') === 'archive';
        Db::update('classes', ['status' => $archive ? 'archived' : 'active', 'join_code_enabled' => 0, 'updated_at' => Db::now()], ['id' => $id]);
        Audit::log($archive ? 'class.archived' : 'class.restored', 'class', $id);
        $this->flash('success', $archive ? t(':class is archived. Teachers and students can still read it, but nothing new can be added.', ['class' => $class['name']]) : t(':class is active again.', ['class' => $class['name']]));
        return $this->redirect('/admin/classes/' . $id);
    }

    // ------------------------------------------------------------------------------------------

    private function find(int $id, int $orgId): array
    {
        $class = Db::one(
            'SELECT c.*, s.name AS subject_name, ap.name AS period_name FROM classes c JOIN subjects s ON s.id = c.subject_id
             LEFT JOIN academic_periods ap ON ap.id = c.academic_period_id WHERE c.id = :id AND c.organization_id = :o',
            ['id' => $id, 'o' => $orgId]
        );
        if (!$class) {
            throw HttpException::notFound();
        }
        return $class;
    }

    private function rules(): array
    {
        return [
            'name' => 'required|string|max:140',
            'subject_id' => 'required|int',
            'academic_period_id' => 'nullable|int',
            'section' => 'nullable|string|max:60',
            'grade_level' => 'nullable|string|max:40',
            'description' => 'nullable|string|max:2000',
        ];
    }

    private function labels(): array
    {
        return [
            'name' => t('Class name'),
            'subject_id' => t('Subject'),
            'academic_period_id' => t('Academic period'),
            'section' => t('Section'),
            'grade_level' => t('Grade level'),
            'description' => t('Description'),
            'teacher_id' => t('Teacher'),
        ];
    }

    private function checkRefs(array $data, int $o, string $back): void
    {
        if (!Db::value('SELECT id FROM subjects WHERE id = :id AND organization_id = :o AND archived_at IS NULL', ['id' => $data['subject_id'], 'o' => $o])) {
            $this->fail('subject_id', t('Choose one of your school\'s subjects.'), $back);
        }
        if ($data['academic_period_id'] && !Db::value('SELECT id FROM academic_periods WHERE id = :id AND organization_id = :o', ['id' => $data['academic_period_id'], 'o' => $o])) {
            $this->fail('academic_period_id', t('Choose one of your school\'s academic periods.'), $back);
        }
    }

    private function subjects(int $o): array
    {
        return array_column(Db::all('SELECT id, name FROM subjects WHERE organization_id = :o AND archived_at IS NULL ORDER BY name', ['o' => $o]), 'name', 'id');
    }

    private function periods(int $o): array
    {
        return array_column(Db::all('SELECT id, name FROM academic_periods WHERE organization_id = :o ORDER BY starts_on DESC', ['o' => $o]), 'name', 'id');
    }

    private function teachers(int $o): array
    {
        return Db::all("SELECT id, given_name, family_name, preferred_name, email, status FROM users WHERE organization_id = :o AND role = 'teacher' AND status <> 'deactivated' ORDER BY family_name, given_name", ['o' => $o]);
    }
}
