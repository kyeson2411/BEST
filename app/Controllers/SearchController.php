<?php

declare(strict_types=1);

namespace Sibol\Controllers;

use Sibol\Core\Db;
use Sibol\Core\Response;
use Sibol\Policies\Access;

/** Global search, constrained to what the signed-in user may see. */
final class SearchController extends Controller
{
    private const PER_GROUP = 8;

    public function index(): Response
    {
        $user = $this->user();
        $q = mb_substr($this->request->str('q'), 0, 100);
        $groups = [];
        if (mb_strlen($q) >= 2) {
            $like = Db::like($q);
            switch ($user['role']) {
                case 'student':
                    $groups = $this->student($user, $like);
                    break;
                case 'teacher':
                    $groups = $this->teacher($user, $like);
                    break;
                case 'admin':
                    $groups = $this->admin($user, $like);
                    break;
            }
        }
        return $this->view('shared/search', [
            'title' => $q !== '' ? t('Search: :q', ['q' => $q]) : t('Search'),
            'q' => $q,
            'groups' => array_filter($groups, static fn ($g) => $g['items'] !== []),
            'tooShort' => $q !== '' && mb_strlen($q) < 2,
        ]);
    }

    private function student(array $user, string $like): array
    {
        $ids = Access::studentClassIds($user);
        if (!$ids) {
            return [];
        }
        [$in, $p] = Db::in($ids, 'c');
        $groups = [];
        $groups[] = ['label' => t('Classes'), 'icon' => 'school', 'items' => array_map(static fn ($r) => ['title' => $r['name'], 'meta' => $r['subject'], 'url' => '/student/classes/' . (int) $r['id']],
            Db::all("SELECT c.id, c.name, s.name AS subject FROM classes c JOIN subjects s ON s.id = c.subject_id WHERE c.id IN $in AND (c.name LIKE :q1 OR s.name LIKE :q2) LIMIT " . self::PER_GROUP, $p + ['q1' => $like, 'q2' => $like]))];
        $groups[] = ['label' => t('Assignments and quizzes'), 'icon' => 'clipboard-list', 'items' => array_map(static fn ($r) => ['title' => $r['title'], 'meta' => $r['class_name'] . ($r['due_at'] ? ', ' . \Sibol\Core\Dates::due($r['due_at'])['label'] : ''), 'url' => '/student/assignments/' . (int) $r['id']],
            Db::all("SELECT a.id, a.title, a.due_at, c.name AS class_name FROM assignments a JOIN classes c ON c.id = a.class_id
                     WHERE a.class_id IN $in AND a.status IN ('published','closed') AND (a.title LIKE :q1 OR a.instructions LIKE :q2) ORDER BY a.due_at DESC LIMIT " . self::PER_GROUP, $p + ['q1' => $like, 'q2' => $like]))];
        $groups[] = ['label' => t('Materials'), 'icon' => 'file-text', 'items' => array_map(static fn ($r) => ['title' => $r['title'], 'meta' => $r['class_name'], 'url' => '/student/classes/' . (int) $r['class_id'] . '?tab=materials#material-' . (int) $r['id']],
            Db::all("SELECT m.id, m.title, m.class_id, c.name AS class_name FROM learning_materials m JOIN classes c ON c.id = m.class_id
                     WHERE m.class_id IN $in AND " . Access::releasedMaterialSql('m') . ' AND (m.title LIKE :q1 OR m.description LIKE :q2 OR m.topic LIKE :q3) LIMIT ' . self::PER_GROUP, $p + ['q1' => $like, 'q2' => $like, 'q3' => $like]))];
        $groups[] = ['label' => t('Lessons'), 'icon' => 'notebook-pen', 'items' => array_map(static fn ($r) => ['title' => $r['title'], 'meta' => $r['class_name'], 'url' => '/student/classes/' . (int) $r['class_id'] . '/lessons/' . (int) $r['id']],
            Db::all("SELECT l.id, l.title, l.class_id, c.name AS class_name FROM lessons l JOIN classes c ON c.id = l.class_id WHERE l.class_id IN $in AND l.status = 'published' AND l.title LIKE :q1 LIMIT " . self::PER_GROUP, $p + ['q1' => $like]))];
        $groups[] = ['label' => t('Announcements'), 'icon' => 'megaphone', 'items' => array_map(static fn ($r) => ['title' => $r['title'], 'meta' => $r['class_name'] . ', ' . fmt_date($r['created_at']), 'url' => '/student/classes/' . (int) $r['class_id'] . '?tab=announcements'],
            Db::all("SELECT a.title, a.class_id, a.created_at, c.name AS class_name FROM announcements a JOIN classes c ON c.id = a.class_id WHERE a.class_id IN $in AND (a.title LIKE :q1 OR a.body LIKE :q2) ORDER BY a.created_at DESC LIMIT " . self::PER_GROUP, $p + ['q1' => $like, 'q2' => $like]))];
        $groups[] = $this->conversations($user, $like);
        return $groups;
    }

    private function teacher(array $user, string $like): array
    {
        $ids = Access::teacherClassIds($user, true);
        if (!$ids) {
            return [$this->conversations($user, $like)];
        }
        [$in, $p] = Db::in($ids, 'c');
        $groups = [];
        $groups[] = ['label' => t('Classes'), 'icon' => 'school', 'items' => array_map(static fn ($r) => ['title' => $r['name'], 'meta' => $r['subject'] . ($r['status'] === 'archived' ? ', ' . t('archived') : ''), 'url' => '/teacher/classes/' . (int) $r['id']],
            Db::all("SELECT c.id, c.name, c.status, s.name AS subject FROM classes c JOIN subjects s ON s.id = c.subject_id WHERE c.id IN $in AND (c.name LIKE :q1 OR s.name LIKE :q2) LIMIT " . self::PER_GROUP, $p + ['q1' => $like, 'q2' => $like]))];
        $groups[] = ['label' => t('Students'), 'icon' => 'users', 'items' => array_map(static fn ($r) => ['title' => user_name($r), 'meta' => $r['class_name'], 'url' => '/teacher/classes/' . (int) $r['class_id'] . '?tab=roster'],
            Db::all("SELECT DISTINCT u.given_name, u.family_name, u.preferred_name, cm.class_id, c.name AS class_name FROM class_memberships cm JOIN users u ON u.id = cm.user_id JOIN classes c ON c.id = cm.class_id
                     WHERE cm.class_id IN $in AND cm.role = 'student' AND cm.status = 'active' AND (u.given_name LIKE :q1 OR u.family_name LIKE :q2 OR u.preferred_name LIKE :q3 OR CONCAT(u.given_name, ' ', u.family_name) LIKE :q4) LIMIT " . self::PER_GROUP,
                     $p + ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like]))];
        $groups[] = ['label' => t('Assignments and quizzes'), 'icon' => 'clipboard-list', 'items' => array_map(static fn ($r) => ['title' => $r['title'], 'meta' => $r['class_name'] . ', ' . $r['status'], 'url' => '/teacher/assignments/' . (int) $r['id']],
            Db::all("SELECT a.id, a.title, a.status, c.name AS class_name FROM assignments a JOIN classes c ON c.id = a.class_id WHERE a.class_id IN $in AND a.status <> 'archived' AND a.title LIKE :q1 ORDER BY a.updated_at DESC LIMIT " . self::PER_GROUP, $p + ['q1' => $like]))];
        $groups[] = ['label' => t('Materials'), 'icon' => 'file-text', 'items' => array_map(static fn ($r) => ['title' => $r['title'], 'meta' => $r['class_name'] . ', ' . $r['visibility'], 'url' => '/teacher/materials/' . (int) $r['id']],
            Db::all("SELECT m.id, m.title, m.visibility, c.name AS class_name FROM learning_materials m JOIN classes c ON c.id = m.class_id WHERE m.class_id IN $in AND (m.title LIKE :q1 OR m.topic LIKE :q2) LIMIT " . self::PER_GROUP, $p + ['q1' => $like, 'q2' => $like]))];
        $groups[] = ['label' => t('Lessons'), 'icon' => 'notebook-pen', 'items' => array_map(static fn ($r) => ['title' => $r['title'], 'meta' => $r['class_name'] . ', ' . $r['status'], 'url' => '/teacher/lessons/' . (int) $r['id']],
            Db::all("SELECT l.id, l.title, l.status, c.name AS class_name FROM lessons l JOIN classes c ON c.id = l.class_id WHERE l.class_id IN $in AND l.title LIKE :q1 LIMIT " . self::PER_GROUP, $p + ['q1' => $like]))];
        $groups[] = $this->conversations($user, $like);
        return $groups;
    }

    private function admin(array $user, string $like): array
    {
        $o = (int) $user['organization_id'];
        return [
            ['label' => t('People'), 'icon' => 'users', 'items' => array_map(static fn ($r) => ['title' => user_name($r), 'meta' => role_label((string) $r['role']) . ', ' . $r['email'] . ($r['status'] !== 'active' ? ', ' . $r['status'] : ''), 'url' => '/admin/users/' . (int) $r['id']],
                Db::all("SELECT id, given_name, family_name, preferred_name, email, role, status FROM users WHERE organization_id = :o AND (given_name LIKE :q1 OR family_name LIKE :q2 OR email LIKE :q3 OR CONCAT(given_name, ' ', family_name) LIKE :q4) ORDER BY family_name LIMIT 12",
                    ['o' => $o, 'q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like]))],
            ['label' => t('Classes'), 'icon' => 'school', 'items' => array_map(static fn ($r) => ['title' => $r['name'], 'meta' => $r['subject'] . ', ' . $r['status'], 'url' => '/admin/classes/' . (int) $r['id']],
                Db::all('SELECT c.id, c.name, c.status, s.name AS subject FROM classes c JOIN subjects s ON s.id = c.subject_id WHERE c.organization_id = :o AND (c.name LIKE :q1 OR s.name LIKE :q2) LIMIT 12', ['o' => $o, 'q1' => $like, 'q2' => $like]))],
            ['label' => t('Subjects'), 'icon' => 'layers', 'items' => array_map(static fn ($r) => ['title' => $r['name'], 'meta' => (string) $r['code'], 'url' => '/admin/subjects'],
                Db::all('SELECT name, code FROM subjects WHERE organization_id = :o AND (name LIKE :q1 OR code LIKE :q2) LIMIT 8', ['o' => $o, 'q1' => $like, 'q2' => $like]))],
        ];
    }

    private function conversations(array $user, string $like): array
    {
        return ['label' => t('Your companion conversations'), 'icon' => 'messages-square', 'items' => array_map(static fn ($r) => ['title' => $r['title'], 'meta' => fmt_rel($r['last_message_at']), 'url' => '/companion/' . (int) $r['id']],
            Db::all('SELECT id, title, last_message_at FROM ai_conversations WHERE user_id = :u AND title LIKE :q1 ORDER BY last_message_at DESC LIMIT ' . self::PER_GROUP, ['u' => (int) $user['id'], 'q1' => $like]))];
    }
}
