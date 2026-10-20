<?php

declare(strict_types=1);

namespace Sibol\Controllers\Admin;

use Sibol\Controllers\Controller;
use Sibol\Core\Audit;
use Sibol\Core\Db;
use Sibol\Core\HttpException;
use Sibol\Core\Paginator;
use Sibol\Core\Response;
use Sibol\Core\Session;
use Sibol\Services\Settings;
use Sibol\Services\SignInSlips;
use Sibol\Services\Tokens;
use Sibol\Services\Usernames;

/**
 * People in the school. Sibol does not send email: invitation and reset links are shown
 * once to the administrator, who shares them through the school's usual channel, or printed
 * on sign-in slips for people without email. Everyone gets a username; email is optional.
 */
final class UserController extends Controller
{
    public function index(): Response
    {
        $o = $this->orgId();
        $q = trim($this->request->str('q'));
        $role = $this->request->str('role');
        $status = $this->request->str('status');
        $grade = $this->request->str('grade');
        $where = ['u.organization_id = :o'];
        $params = ['o' => $o];
        if ($q !== '') {
            $where[] = "(CONCAT(u.given_name, ' ', u.family_name) LIKE :q1 OR u.preferred_name LIKE :q2 OR u.email LIKE :q3 OR u.username LIKE :q4)";
            $params += ['q1' => Db::like($q), 'q2' => Db::like($q), 'q3' => Db::like($q), 'q4' => Db::like($q)];
        }
        if (in_array($role, ['admin', 'teacher', 'student'], true)) {
            $where[] = 'u.role = :role';
            $params['role'] = $role;
        }
        if (in_array($status, ['active', 'invited', 'deactivated'], true)) {
            $where[] = 'u.status = :status';
            $params['status'] = $status;
        }
        if ($grade !== '') {
            $where[] = 'u.grade_level = :grade';
            $params['grade'] = $grade;
        }
        $sql = ' FROM users u WHERE ' . implode(' AND ', $where);
        $pager = new Paginator((int) Db::value('SELECT COUNT(*)' . $sql, $params), $this->page(), 30);
        $rows = Db::all(
            "SELECT u.id, u.role, u.status, u.email, u.username, u.given_name, u.family_name, u.preferred_name, u.grade_level, u.last_login_at, u.anonymized_at,
                    (SELECT COUNT(*) FROM class_memberships m JOIN classes c ON c.id = m.class_id AND c.status = 'active' WHERE m.user_id = u.id AND m.status = 'active') AS classes"
            . $sql . ' ORDER BY u.family_name, u.given_name' . $pager->limitSql(),
            $params
        );
        $counts = [];
        foreach (Db::all('SELECT role, COUNT(*) AS n FROM users WHERE organization_id = :o GROUP BY role', ['o' => $o]) as $r) {
            $counts[$r['role']] = (int) $r['n'];
        }
        return $this->view('admin/users', [
            'title' => t('People'),
            'rows' => $rows,
            'pager' => $pager,
            'q' => $q,
            'role' => $role,
            'status' => $status,
            'grade' => $grade,
            'counts' => $counts,
            'grades' => (array) Settings::get($o, 'grade_levels', []),
        ]);
    }

    public function create(): Response
    {
        $o = $this->orgId();
        return $this->view('admin/user_form', [
            'title' => t('Invite someone'),
            'crumbs' => [[t('People'), '/admin/users'], [t('Invite someone'), null]],
            'grades' => (array) Settings::get($o, 'grade_levels', []),
            'classes' => $this->classOptions($o),
            'role' => in_array($this->request->str('role'), ['admin', 'teacher', 'student'], true) ? $this->request->str('role') : 'student',
        ]);
    }

    public function store(): Response
    {
        $admin = $this->user();
        $o = (int) $admin['organization_id'];
        $data = $this->validate([
            'given_name' => 'required|string|max:80',
            'family_name' => 'required|string|max:80',
            'preferred_name' => 'nullable|string|max:80',
            'username' => 'nullable|string|max:40',
            'email' => 'nullable|email',
            'role' => 'required|in:student,teacher,admin',
            'grade_level' => 'nullable|string|max:40',
            'class_id' => 'nullable|int',
        ], $this->labels(), '/admin/users/new');
        $this->assertEmailFree($data['email'], null, '/admin/users/new');
        $data['username'] = $data['username'] === null
            ? Usernames::forPerson((string) $data['given_name'], (string) $data['family_name'])
            : $this->checkUsername((string) $data['username'], null, '/admin/users/new');
        $class = null;
        if ($data['class_id']) {
            $class = Db::one("SELECT id, name FROM classes WHERE id = :id AND organization_id = :o AND status = 'active'", ['id' => $data['class_id'], 'o' => $o]);
            if (!$class || $data['role'] === 'admin') {
                $this->fail('class_id', t('Choose one of your school\'s active classes, or leave this empty.'), '/admin/users/new');
            }
        }
        $id = 0;
        $token = '';
        Db::transaction(function () use ($data, $o, $admin, $class, &$id, &$token): void {
            $id = Db::insert('users', [
                'organization_id' => $o,
                'role' => $data['role'],
                'status' => 'invited',
                'email' => $data['email'],
                'username' => $data['username'],
                'given_name' => $data['given_name'],
                'family_name' => $data['family_name'],
                'preferred_name' => $data['preferred_name'],
                'grade_level' => $data['role'] === 'student' ? $data['grade_level'] : null,
                'created_at' => Db::now(),
                'updated_at' => Db::now(),
            ]);
            if ($class) {
                Db::insert('class_memberships', ['class_id' => (int) $class['id'], 'user_id' => $id, 'role' => $data['role'], 'status' => 'active', 'joined_via' => 'admin', 'joined_at' => Db::now()]);
            }
            $token = Tokens::issue($id, 'invitation', (int) $admin['id']);
        });
        Audit::log('user.invited', 'user', $id, ['role' => $data['role']]);
        $this->reveal($id, 'invitation', $token);
        $this->flash('success', t('Invitation created for :name.', ['name' => $data['given_name'] . ' ' . $data['family_name']]));
        return $this->redirect('/admin/users/' . $id);
    }

    public function show(int $id): Response
    {
        $admin = $this->user();
        $o = (int) $admin['organization_id'];
        $person = $this->find($id, $o);
        $reveal = Session::get('reveal_link');
        if (is_array($reveal) && (int) ($reveal['user_id'] ?? 0) === $id) {
            Session::set('reveal_link', null);
        } else {
            $reveal = null;
        }
        $memberships = Db::all(
            'SELECT m.id, m.role, m.status, m.joined_via, m.joined_at, m.withdrawn_at, c.id AS class_id, c.name AS class_name, c.status AS class_status, s.name AS subject_name
             FROM class_memberships m JOIN classes c ON c.id = m.class_id JOIN subjects s ON s.id = c.subject_id
             WHERE m.user_id = :u AND c.organization_id = :o ORDER BY m.status, c.name',
            ['u' => $id, 'o' => $o]
        );
        $pendingLink = Db::one(
            'SELECT purpose, expires_at, created_at FROM user_tokens WHERE user_id = :u AND used_at IS NULL AND revoked_at IS NULL AND expires_at > :now ORDER BY id DESC LIMIT 1',
            ['u' => $id, 'now' => Db::now()]
        );
        $activity = Db::all(
            'SELECT a.*, u.given_name, u.family_name, u.preferred_name FROM audit_logs a LEFT JOIN users u ON u.id = a.actor_id
             WHERE a.organization_id = :o AND (a.actor_id = :u1 OR (a.target_type = \'user\' AND a.target_id = :u2))
             ORDER BY a.created_at DESC LIMIT 12',
            ['o' => $o, 'u1' => $id, 'u2' => $id]
        );
        return $this->view('admin/user', [
            'title' => user_name($person),
            'crumbs' => [[t('People'), '/admin/users'], [user_name($person), null]],
            'person' => $person,
            'isSelf' => (int) $admin['id'] === $id,
            'memberships' => $memberships,
            'pendingLink' => $pendingLink,
            'reveal' => $reveal,
            'activity' => $activity,
            'grades' => (array) Settings::get($o, 'grade_levels', []),
            'lastAdmin' => $person['role'] === 'admin' && $this->activeAdmins($o) <= 1,
        ]);
    }

    public function update(int $id): Response
    {
        $admin = $this->user();
        $o = (int) $admin['organization_id'];
        $person = $this->find($id, $o);
        $back = '/admin/users/' . $id;
        if ($person['anonymized_at']) {
            $this->flash('error', t('This account was erased and can no longer be edited.'));
            return $this->redirect($back);
        }
        $data = $this->validate([
            'given_name' => 'required|string|max:80',
            'family_name' => 'required|string|max:80',
            'preferred_name' => 'nullable|string|max:80',
            'username' => 'required|string|max:40',
            'email' => 'nullable|email',
            'role' => 'required|in:student,teacher,admin',
            'grade_level' => 'nullable|string|max:40',
        ], $this->labels(), $back);
        $this->assertEmailFree($data['email'], $id, $back);
        $data['username'] = $this->checkUsername((string) $data['username'], $id, $back);
        if ($data['role'] !== $person['role']) {
            if ((int) $admin['id'] === $id) {
                $this->fail('role', t('You cannot change your own role. Ask another administrator.'), $back);
            }
            if ($person['role'] === 'admin' && $this->activeAdmins($o) <= 1) {
                $this->fail('role', t('The school needs at least one active administrator.'), $back);
            }
            $active = (int) Db::value("SELECT COUNT(*) FROM class_memberships WHERE user_id = :u AND status = 'active'", ['u' => $id]);
            if ($active > 0) {
                $this->fail('role', t('Remove this person from their classes before changing their role, so class records stay correct.'), $back);
            }
        }
        $changes = [
            'given_name' => $data['given_name'],
            'family_name' => $data['family_name'],
            'preferred_name' => $data['preferred_name'],
            'email' => $data['email'],
            'username' => $data['username'],
            'role' => $data['role'],
            'grade_level' => $data['role'] === 'student' ? $data['grade_level'] : null,
            'updated_at' => Db::now(),
        ];
        if ($data['role'] !== $person['role']) {
            // A role change signs the person out everywhere so new permissions apply at once.
            $changes['session_version'] = (int) $person['session_version'] + 1;
        }
        Db::update('users', $changes, ['id' => $id]);
        Audit::log($data['role'] !== $person['role'] ? 'user.role_changed' : 'user.updated', 'user', $id, $data['role'] !== $person['role'] ? ['role' => $data['role']] : []);
        $this->flash('success', t('Changes saved.'));
        return $this->redirect($back);
    }

    public function status(int $id): Response
    {
        $admin = $this->user();
        $o = (int) $admin['organization_id'];
        $person = $this->find($id, $o);
        $back = '/admin/users/' . $id;
        $action = $this->request->str('action');
        if ($action === 'deactivate') {
            if ((int) $admin['id'] === $id) {
                $this->flash('error', t('You cannot deactivate your own account.'));
                return $this->redirect($back);
            }
            if ($person['role'] === 'admin' && $person['status'] === 'active' && $this->activeAdmins($o) <= 1) {
                $this->flash('error', t('The school needs at least one active administrator.'));
                return $this->redirect($back);
            }
            Db::transaction(static function () use ($id, $person): void {
                Db::update('users', ['status' => 'deactivated', 'deactivated_at' => Db::now(), 'session_version' => (int) $person['session_version'] + 1, 'updated_at' => Db::now()], ['id' => $id]);
                Db::exec('UPDATE user_tokens SET revoked_at = :n WHERE user_id = :u AND used_at IS NULL AND revoked_at IS NULL', ['n' => Db::now(), 'u' => $id]);
            });
            Audit::log('user.deactivated', 'user', $id);
            $this->flash('success', t(':name can no longer sign in. Their work and grades are kept.', ['name' => user_name($person)]));
        } elseif ($action === 'reactivate') {
            if ($person['anonymized_at']) {
                $this->flash('error', t('This account was erased and cannot be reactivated. Invite the person again with a new account.'));
                return $this->redirect($back);
            }
            $hasPassword = $person['password_hash'] !== null;
            Db::update('users', ['status' => $hasPassword ? 'active' : 'invited', 'deactivated_at' => null, 'updated_at' => Db::now()], ['id' => $id]);
            Audit::log('user.reactivated', 'user', $id);
            $this->flash('success', $hasPassword ? t(':name can sign in again.', ['name' => user_name($person)]) : t('Reactivated. Create an invitation link so :name can set a password.', ['name' => user_name($person)]));
        }
        return $this->redirect($back);
    }

    /** Issues a fresh invitation link (not yet accepted) or a password-reset link (active accounts). */
    public function link(int $id): Response
    {
        $admin = $this->user();
        $person = $this->find($id, (int) $admin['organization_id']);
        $back = '/admin/users/' . $id;
        if ($person['status'] === 'deactivated') {
            $this->flash('error', t('Reactivate the account first.'));
            return $this->redirect($back);
        }
        $purpose = $person['status'] === 'invited' ? 'invitation' : 'password_reset';
        $token = Tokens::issue($id, $purpose, (int) $admin['id']);
        Audit::log($purpose === 'invitation' ? 'user.invitation_link' : 'user.reset_link', 'user', $id);
        $this->reveal($id, $purpose, $token);
        return $this->redirect($back);
    }

    /** A printed sign-in slip instead of a link: setup for an invited account, a new password otherwise. */
    public function slip(int $id): Response
    {
        $admin = $this->user();
        $person = $this->find($id, (int) $admin['organization_id']);
        $back = '/admin/users/' . $id;
        if ($person['status'] === 'deactivated') {
            $this->flash('error', t('Reactivate the account first.'));
            return $this->redirect($back);
        }
        SignInSlips::issue([$person], $admin, user_name($person), $back);
        return $this->redirect('/slips');
    }

    // ------------------------------------------------------------------------------------------

    private function find(int $id, int $orgId): array
    {
        $person = Db::one('SELECT * FROM users WHERE id = :id AND organization_id = :o', ['id' => $id, 'o' => $orgId]);
        if (!$person) {
            throw HttpException::notFound();
        }
        return $person;
    }

    private function activeAdmins(int $orgId): int
    {
        return (int) Db::value("SELECT COUNT(*) FROM users WHERE organization_id = :o AND role = 'admin' AND status = 'active'", ['o' => $orgId]);
    }

    private function assertEmailFree(?string $email, ?int $exceptId, string $back): void
    {
        if ($email === null) {
            return;
        }
        $taken = Db::value('SELECT id FROM users WHERE email = :e' . ($exceptId ? ' AND id <> :id' : ''), $exceptId ? ['e' => $email, 'id' => $exceptId] : ['e' => $email]);
        if ($taken) {
            $this->fail('email', t('Another account already uses this email address.'), $back);
        }
    }

    private function checkUsername(string $username, ?int $exceptId, string $back): string
    {
        $username = Usernames::clean($username);
        if (!Usernames::valid($username)) {
            $this->fail('username', t('Use 3 to 40 small letters, digits, dots, hyphens or underscores, starting and ending with a letter or digit.'), $back);
        }
        if (!Usernames::available($username, $exceptId)) {
            $this->fail('username', t('Another account already uses this username.'), $back);
        }
        return $username;
    }

    /** The raw link is kept in the session only until the next page shows it once. */
    private function reveal(int $userId, string $purpose, string $token): void
    {
        Session::set('reveal_link', [
            'user_id' => $userId,
            'purpose' => $purpose,
            'url' => Tokens::link($purpose, $token),
            'expires' => $purpose === 'invitation' ? tp(':count day', ':count days', Tokens::INVITE_DAYS) : tp(':count hour', ':count hours', Tokens::RESET_HOURS),
        ]);
    }

    /** @return array<int|string,string> */
    private function classOptions(int $orgId): array
    {
        return array_column(Db::all("SELECT id, name FROM classes WHERE organization_id = :o AND status = 'active' ORDER BY name", ['o' => $orgId]), 'name', 'id');
    }

    private function labels(): array
    {
        return [
            'given_name' => t('Given name'),
            'family_name' => t('Family name'),
            'preferred_name' => t('Preferred name'),
            'username' => t('Username'),
            'email' => t('Email address'),
            'role' => t('Role'),
            'grade_level' => t('Grade level'),
            'class_id' => t('Class'),
        ];
    }
}
