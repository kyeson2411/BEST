<?php

declare(strict_types=1);

namespace Sibol\Controllers\Admin;

use Sibol\Controllers\Controller;
use Sibol\Core\Audit;
use Sibol\Core\Db;
use Sibol\Core\Response;
use Sibol\Core\Session;
use Sibol\Core\Uploads;
use Sibol\Services\Csv;
use Sibol\Services\Mailer;
use Sibol\Services\Sections;
use Sibol\Services\Settings;
use Sibol\Services\SignInSlips;
use Sibol\Services\Tokens;
use Sibol\Services\Usernames;

/**
 * Spreadsheet import: upload → preview with row-level problems → confirm.
 * Nothing is written until the administrator confirms the preview. Email and username are optional:
 * Sibol makes a username from the name, and new accounts get a one-time code that can be printed on
 * sign-in slips.
 */
final class ImportController extends Controller
{
    private const MAX_ROWS = 1000;
    private const MAX_BYTES = 2097152;
    private const HEADERS = ['given_name', 'family_name', 'username', 'email', 'role', 'grade_level', 'class'];
    private const ALIASES = [
        'given_name' => ['given_name', 'given name', 'first_name', 'first name', 'firstname', 'pangalan'],
        'family_name' => ['family_name', 'family name', 'last_name', 'last name', 'lastname', 'surname', 'apelyido'],
        'username' => ['username', 'user name', 'user_name', 'login'],
        'email' => ['email', 'email address', 'e-mail'],
        'role' => ['role', 'type'],
        'grade_level' => ['grade_level', 'grade level', 'grade', 'baitang'],
        'class' => ['class', 'class name', 'section', 'klase'],
    ];

    public function create(): Response
    {
        return $this->view('admin/import', [
            'title' => t('Import people'),
            'crumbs' => [[t('People'), '/admin/users'], [t('Import from CSV'), null]],
            'classes' => Db::column("SELECT name FROM classes WHERE organization_id = :o AND status = 'active' ORDER BY name", ['o' => $this->orgId()]),
            'grades' => (array) Settings::get($this->orgId(), 'grade_levels', []),
        ]);
    }

    public function template(): Response
    {
        $rows = [
            self::HEADERS,
            ['Juan', 'Dela Cruz', '', '', 'student', 'Grade 9', ''],
            ['Maria', 'Santos', '', 'maria.santos@example.org', 'teacher', '', ''],
        ];
        $out = "\xEF\xBB\xBF";
        foreach ($rows as $r) {
            $out .= implode(',', array_map(static fn ($v) => '"' . str_replace('"', '""', $v) . '"', $r)) . "\r\n";
        }
        return Response::html($out)
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="sibol-people-template.csv"');
    }

    public function preview(): Response
    {
        $admin = $this->user();
        $o = (int) $admin['organization_id'];
        $file = $this->request->file('csv');
        $error = Uploads::validate($file, ['csv', 'txt'], self::MAX_BYTES);
        if ($error !== null) {
            $this->fail('csv', $error, '/admin/users/import');
        }
        $content = (string) file_get_contents((string) $file['tmp_name']);
        $parsed = $this->parse($content);
        if (is_string($parsed)) {
            $this->fail('csv', $parsed, '/admin/users/import');
        }
        [$header, $lines] = $parsed;

        $classes = [];
        foreach (Db::all("SELECT id, name FROM classes WHERE organization_id = :o AND status = 'active'", ['o' => $o]) as $c) {
            $classes[self::normalise((string) $c['name'])] = ['id' => (int) $c['id'], 'name' => (string) $c['name']];
        }
        $grades = (array) Settings::get($o, 'grade_levels', []);
        $column = static fn (string $key, callable $clean): array => isset($header[$key])
            ? array_values(array_unique(array_filter(array_map(static fn ($l) => $clean(trim((string) ($l[$header[$key]] ?? ''))), $lines))))
            : [];
        $byEmail = self::existing('email', $column('email', 'mb_strtolower'));
        $byUsername = self::existing('username', $column('username', [Usernames::class, 'clean']));
        // Without an email or a username, someone already in the school is recognised by name, role and grade.
        $byName = [];
        foreach (Db::all('SELECT id, role, given_name, family_name, preferred_name, grade_level, username FROM users WHERE organization_id = :o AND anonymized_at IS NULL', ['o' => $o]) as $u) {
            $byName[self::nameKey((string) $u['role'], (string) $u['given_name'], (string) $u['family_name'], (string) $u['grade_level'])] = $u;
        }

        $rows = [];
        $seenEmail = [];
        $seenUsername = [];
        foreach ($lines as $i => $line) {
            $get = static fn (string $key): string => isset($header[$key]) ? trim((string) ($line[$header[$key]] ?? '')) : '';
            $row = [
                'line' => $i + 2,
                'given_name' => mb_substr($get('given_name'), 0, 80),
                'family_name' => mb_substr($get('family_name'), 0, 80),
                'username' => Usernames::clean($get('username')),
                'email' => mb_strtolower($get('email')),
                'role' => mb_strtolower($get('role')) ?: 'student',
                'grade_level' => $get('grade_level'),
                'class_input' => $get('class'),
                'class_id' => null,
                'class_name' => null,
                'action' => 'create',
                'problems' => [],
                'notes' => [],
            ];
            if ($row['given_name'] === '' || $row['family_name'] === '') {
                $row['problems'][] = t('Given and family names are required.');
            }
            if ($row['email'] !== '' && (!filter_var($row['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($row['email']) > 190)) {
                $row['problems'][] = t('The email address is not valid.');
            }
            if ($row['username'] !== '' && !Usernames::valid($row['username'])) {
                $row['problems'][] = t('The username can have 3 to 40 small letters, digits, dots, hyphens or underscores.');
            }
            if (in_array($row['role'], ['teacher', 'guro'], true)) {
                $row['role'] = 'teacher';
            } elseif (in_array($row['role'], ['student', 'mag-aaral', 'learner'], true)) {
                $row['role'] = 'student';
            } elseif (in_array($row['role'], ['admin', 'administrator'], true)) {
                $row['role'] = 'admin';
            } else {
                $row['problems'][] = t('Role must be student, teacher or admin.');
            }
            if ($row['grade_level'] !== '' && $row['role'] === 'student' && !in_array($row['grade_level'], $grades, true)) {
                $row['notes'][] = t('Grade level “:grade” is not in your school\'s list; it will be saved as written.', ['grade' => $row['grade_level']]);
            }
            if ($row['role'] !== 'student') {
                $row['grade_level'] = '';
            }
            if ($row['class_input'] !== '') {
                $match = $classes[self::normalise($row['class_input'])] ?? null;
                if (!$match) {
                    $row['problems'][] = t('No active class is named “:class”.', ['class' => $row['class_input']]);
                } elseif ($row['role'] === 'admin') {
                    $row['problems'][] = t('Administrators cannot be added to classes.');
                } else {
                    $row['class_id'] = $match['id'];
                    $row['class_name'] = $match['name'];
                }
            }
            if ($row['email'] !== '' && isset($seenEmail[$row['email']])) {
                $row['problems'][] = t('The same email appears on line :line.', ['line' => $seenEmail[$row['email']]]);
            }
            if ($row['username'] !== '' && isset($seenUsername[$row['username']])) {
                $row['problems'][] = t('The same username appears on line :line.', ['line' => $seenUsername[$row['username']]]);
            }
            if ($row['email'] !== '') {
                $seenEmail[$row['email']] = $seenEmail[$row['email']] ?? $row['line'];
            }
            if ($row['username'] !== '') {
                $seenUsername[$row['username']] = $seenUsername[$row['username']] ?? $row['line'];
            }

            $match = ($row['email'] !== '' ? ($byEmail[$row['email']] ?? null) : null)
                ?? ($row['username'] !== '' ? ($byUsername[$row['username']] ?? null) : null);
            $byNameOnly = false;
            if (!$match && $row['email'] === '' && $row['username'] === '') {
                $match = $byName[self::nameKey($row['role'], $row['given_name'], $row['family_name'], $row['grade_level'])] ?? null;
                $byNameOnly = $match !== null;
            }
            if ($match && !$row['problems']) {
                if (isset($match['organization_id']) && (int) $match['organization_id'] !== $o) {
                    $row['problems'][] = $row['email'] !== '' && isset($byEmail[$row['email']])
                        ? t('This email belongs to an account outside your school.')
                        : t('This username belongs to an account outside your school.');
                } else {
                    if ($byNameOnly) {
                        $row['notes'][] = t('Treated as :name (:username), who already has an account with this name. To add a different person with the same name, give them a username in the file.', ['name' => user_name($match), 'username' => (string) $match['username']]);
                    }
                    if ($row['class_id'] && $match['role'] === $row['role']) {
                        $row['action'] = 'enrol';
                        $row['user_id'] = (int) $match['id'];
                        $row['notes'][] = t('Already has an account; will only be added to the class.');
                    } else {
                        $row['action'] = 'skip';
                        $row['notes'][] = t('Already has an account; nothing to do.');
                    }
                }
            }
            if ($row['problems']) {
                $row['action'] = 'error';
            }
            $rows[] = $row;
        }

        $key = bin2hex(random_bytes(8));
        Session::set('import_preview', ['key' => $key, 'org' => $o, 'rows' => $rows, 'at' => time()]);
        $summary = array_count_values(array_column($rows, 'action'));
        return $this->view('admin/import_preview', [
            'title' => t('Check the import'),
            'crumbs' => [[t('People'), '/admin/users'], [t('Import from CSV'), '/admin/users/import'], [t('Check'), null]],
            'rows' => $rows,
            'key' => $key,
            'summary' => $summary,
            'filename' => Uploads::cleanName((string) $file['name']),
        ]);
    }

    public function confirm(): Response
    {
        $admin = $this->user();
        $o = (int) $admin['organization_id'];
        $preview = Session::get('import_preview');
        if (!is_array($preview) || ($preview['key'] ?? '') !== $this->request->str('key') || (int) $preview['org'] !== $o || time() - (int) $preview['at'] > 1800) {
            $this->flash('error', t('This preview has expired or was already imported. Upload the file again.'));
            return $this->redirect('/admin/users/import');
        }
        Session::set('import_preview', null);
        $created = [];
        $slips = [];
        $enrolled = 0;
        Db::transaction(function () use ($preview, $o, $admin, &$created, &$slips, &$enrolled): void {
            $reserved = [];
            foreach ($preview['rows'] as $row) {
                if ($row['action'] === 'create') {
                    // Taken by someone else since the preview.
                    if (($row['email'] !== '' && Db::value('SELECT id FROM users WHERE email = :e', ['e' => $row['email']]))
                        || ($row['username'] !== '' && !Usernames::available($row['username']))) {
                        continue;
                    }
                    $username = $row['username'] !== '' ? $row['username'] : Usernames::forPerson($row['given_name'], $row['family_name'], $reserved);
                    $reserved[$username] = true;
                    $id = Db::insert('users', [
                        'organization_id' => $o,
                        'role' => $row['role'],
                        'status' => 'invited',
                        'email' => $row['email'] !== '' ? $row['email'] : null,
                        'username' => $username,
                        'given_name' => $row['given_name'],
                        'family_name' => $row['family_name'],
                        'grade_level' => $row['grade_level'] !== '' ? $row['grade_level'] : null,
                        'created_at' => Db::now(),
                        'updated_at' => Db::now(),
                    ]);
                    $code = Tokens::issue($id, 'invitation', (int) $admin['id'], true);
                    if ($row['class_id']) {
                        $this->enrol((int) $row['class_id'], $id, $row['role']);
                    }
                    $group = $row['role'] !== 'student' ? t('Staff') : ($row['class_id'] ? Sections::groupFor($id) : '');
                    $slip = SignInSlips::slip(['given_name' => $row['given_name'], 'family_name' => $row['family_name'], 'username' => $username], 'invitation', $code, $group);
                    $slips[] = $slip;
                    $created[] = ['name' => $row['given_name'] . ' ' . $row['family_name'], 'username' => $username, 'email' => $row['email'], 'role' => $row['role'], 'class' => $row['class_name'], 'url' => $slip['url'], 'code' => $slip['code']];
                } elseif ($row['action'] === 'enrol' && $row['class_id']) {
                    $enrolled += $this->enrol((int) $row['class_id'], (int) $row['user_id'], $row['role']) ? 1 : 0;
                }
            }
        });
        Audit::log('users.imported', null, null, ['created' => count($created), 'enrolled' => $enrolled]);
        // Links are kept briefly so the administrator can download them or print them as slips.
        Session::set('import_links', ['at' => time(), 'rows' => $created]);
        if ($slips) {
            usort($slips, static fn ($a, $b) => [$a['group'], $a['name']] <=> [$b['group'], $b['name']]);
            SignInSlips::remember($slips, t('Imported people'), '/admin/users?status=invited');
        }
        return $this->view('admin/import_done', [
            'title' => t('Import complete'),
            'crumbs' => [[t('People'), '/admin/users'], [t('Import complete'), null]],
            'created' => $created,
            'enrolled' => $enrolled,
        ]);
    }

    /** Emails the invitation links from the import that just finished to the people who have an address. */
    public function emailLinks(): Response
    {
        $admin = $this->user();
        $data = Session::get('import_links');
        if (!is_array($data) || time() - (int) $data['at'] > 1800) {
            $this->flash('error', t('The invitation links are no longer available here. Create new links or slips from each person\'s page.'));
            return $this->redirect('/admin/users');
        }
        if (!Mailer::enabled()) {
            $this->flash('error', t('Email is not set up for this copy of B.E.S.T. Learning and Interactive Study System. Print the slips or download the links instead.'));
            return $this->redirect('/admin/users?status=invited');
        }
        $sent = 0;
        $failed = 0;
        $expires = tp(':count day', ':count days', Tokens::INVITE_DAYS);
        foreach ($data['rows'] as $r) {
            if ((string) ($r['email'] ?? '') === '') {
                continue;
            }
            $person = Db::one(
                "SELECT u.*, o.name AS org_name FROM users u JOIN organizations o ON o.id = u.organization_id
                 WHERE u.username = :u AND u.organization_id = :o AND u.status = 'invited'",
                ['u' => (string) $r['username'], 'o' => (int) $admin['organization_id']]
            );
            if ($person) {
                Mailer::accountLink($person, 'invitation', (string) $r['url'], $expires, (string) $person['org_name']) ? $sent++ : $failed++;
            }
        }
        Audit::log('users.invitations_emailed', null, null, ['sent' => $sent, 'failed' => $failed]);
        $this->flash($failed ? 'error' : 'success', $failed
            ? tp('Emailed :count invitation. Some could not be sent: copy their links from each person\'s page.', 'Emailed :count invitations. Some could not be sent: copy their links from each person\'s page.', $sent)
            : tp('Emailed :count invitation.', 'Emailed :count invitations.', $sent));
        return $this->redirect('/admin/users?status=invited');
    }

    /** CSV of the invitation links from the import that just finished (this session only, 30 minutes). */
    public function links(): Response
    {
        $data = Session::get('import_links');
        if (!is_array($data) || time() - (int) $data['at'] > 1800) {
            $this->flash('error', t('The invitation links are no longer available here. Create new links or slips from each person\'s page.'));
            return $this->redirect('/admin/users');
        }
        $out = "\xEF\xBB\xBF" . "name,username,email,role,class,code,invitation_link\r\n";
        foreach ($data['rows'] as $r) {
            $out .= implode(',', array_map(static function ($v): string {
                $v = (string) $v;
                if ($v !== '' && strpbrk($v[0], '=+-@') !== false) {
                    $v = "'" . $v;
                }
                return '"' . str_replace('"', '""', $v) . '"';
            }, [$r['name'], $r['username'] ?? '', $r['email'], $r['role'], (string) $r['class'], $r['code'] ?? '', $r['url']])) . "\r\n";
        }
        return Response::html($out)
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="sibol-invitation-links.csv"')
            ->header('Cache-Control', 'no-store');
    }

    // ------------------------------------------------------------------------------------------

    /** @return array{0:array<string,int>,1:list<array<int,string>>}|string */
    private function parse(string $content)
    {
        return Csv::read($content, self::ALIASES, ['given_name', 'family_name'], self::MAX_ROWS, t('The file has a header row but no people.'));
    }

    /**
     * Accounts that already use these emails or usernames, in any school (the import refuses another school's).
     * @param list<string> $values
     * @return array<string,array<string,mixed>>
     */
    private static function existing(string $column, array $values): array
    {
        $found = [];
        foreach (array_chunk($values, 400) as $chunk) {
            [$in, $params] = Db::in($chunk, 'v');
            foreach (Db::all("SELECT id, email, username, organization_id, role, given_name, family_name, preferred_name FROM users WHERE $column IN " . $in, $params) as $u) {
                $found[mb_strtolower((string) $u[$column])] = $u;
            }
        }
        return $found;
    }

    private static function nameKey(string $role, string $given, string $family, string $grade): string
    {
        return mb_strtolower($role . '|' . trim($given) . '|' . trim($family) . '|' . ($role === 'student' ? trim($grade) : ''));
    }

    private function enrol(int $classId, int $userId, string $role): bool
    {
        $existing = Db::one('SELECT id, status FROM class_memberships WHERE class_id = :c AND user_id = :u', ['c' => $classId, 'u' => $userId]);
        if ($existing && $existing['status'] === 'active') {
            return false;
        }
        if ($existing) {
            Db::update('class_memberships', ['status' => 'active', 'withdrawn_at' => null, 'joined_at' => Db::now(), 'joined_via' => 'import'], ['id' => (int) $existing['id']]);
        } else {
            Db::insert('class_memberships', ['class_id' => $classId, 'user_id' => $userId, 'role' => $role, 'status' => 'active', 'joined_via' => 'import', 'joined_at' => Db::now()]);
        }
        return true;
    }

    /** Case- and dash-insensitive class name key ("Science 9 - Narra" matches "Science 9 – Narra"). */
    private static function normalise(string $name): string
    {
        $name = str_replace(['–', '—', '‐', '−'], '-', mb_strtolower($name));
        return (string) preg_replace('/\s+/', ' ', preg_replace('/\s*-\s*/', ' - ', trim($name)) ?? '');
    }
}
