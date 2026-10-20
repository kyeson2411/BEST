<?php

declare(strict_types=1);

namespace Sibol\Controllers\Admin;

use Sibol\Controllers\Controller;
use Sibol\Core\Audit;
use Sibol\Core\Db;
use Sibol\Core\Response;
use Sibol\Core\Session;
use Sibol\Core\Uploads;
use Sibol\Services\Settings;
use Sibol\Services\Tokens;

/**
 * Spreadsheet import: upload → preview with row-level problems → confirm.
 * Nothing is written until the administrator confirms the preview.
 */
final class ImportController extends Controller
{
    private const MAX_ROWS = 1000;
    private const MAX_BYTES = 2097152;
    private const HEADERS = ['given_name', 'family_name', 'email', 'role', 'grade_level', 'class'];
    private const ALIASES = [
        'given_name' => ['given_name', 'given name', 'first_name', 'first name', 'firstname', 'pangalan'],
        'family_name' => ['family_name', 'family name', 'last_name', 'last name', 'lastname', 'surname', 'apelyido'],
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
            ['Juan', 'Dela Cruz', 'juan.delacruz@example.org', 'student', 'Grade 9', ''],
            ['Maria', 'Santos', 'maria.santos@example.org', 'teacher', '', ''],
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
        $emails = array_values(array_filter(array_map(static fn ($l) => mb_strtolower(trim((string) ($l[$header['email']] ?? ''))), $lines)));
        $existing = [];
        if ($emails) {
            foreach (array_chunk(array_unique($emails), 400) as $chunk) {
                [$in, $params] = Db::in($chunk, 'e');
                foreach (Db::all('SELECT id, email, organization_id, role, given_name, family_name, preferred_name FROM users WHERE email IN ' . $in, $params) as $u) {
                    $existing[mb_strtolower((string) $u['email'])] = $u;
                }
            }
        }

        $rows = [];
        $seen = [];
        foreach ($lines as $i => $line) {
            $get = static fn (string $key): string => isset($header[$key]) ? trim((string) ($line[$header[$key]] ?? '')) : '';
            $row = [
                'line' => $i + 2,
                'given_name' => mb_substr($get('given_name'), 0, 80),
                'family_name' => mb_substr($get('family_name'), 0, 80),
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
            if (!filter_var($row['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($row['email']) > 190) {
                $row['problems'][] = t('The email address is not valid.');
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
            if ($row['email'] !== '' && isset($seen[$row['email']])) {
                $row['problems'][] = t('The same email appears on line :line.', ['line' => $seen[$row['email']]]);
            }
            $seen[$row['email']] = $seen[$row['email']] ?? $row['line'];
            $match = $existing[$row['email']] ?? null;
            if ($match && !$row['problems']) {
                if ((int) $match['organization_id'] !== $o) {
                    $row['problems'][] = t('This email belongs to an account outside your school.');
                } elseif ($row['class_id'] && $match['role'] === $row['role']) {
                    $row['action'] = 'enrol';
                    $row['user_id'] = (int) $match['id'];
                    $row['notes'][] = t('Already has an account; will only be added to the class.');
                } else {
                    $row['action'] = 'skip';
                    $row['notes'][] = t('Already has an account; nothing to do.');
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
        $enrolled = 0;
        Db::transaction(function () use ($preview, $o, $admin, &$created, &$enrolled): void {
            foreach ($preview['rows'] as $row) {
                if ($row['action'] === 'create') {
                    if (Db::value('SELECT id FROM users WHERE email = :e', ['e' => $row['email']])) {
                        continue; // created by someone else since the preview
                    }
                    $id = Db::insert('users', [
                        'organization_id' => $o,
                        'role' => $row['role'],
                        'status' => 'invited',
                        'email' => $row['email'],
                        'given_name' => $row['given_name'],
                        'family_name' => $row['family_name'],
                        'grade_level' => $row['grade_level'] !== '' ? $row['grade_level'] : null,
                        'created_at' => Db::now(),
                        'updated_at' => Db::now(),
                    ]);
                    $token = Tokens::issue($id, 'invitation', (int) $admin['id']);
                    $created[] = ['name' => $row['given_name'] . ' ' . $row['family_name'], 'email' => $row['email'], 'role' => $row['role'], 'class' => $row['class_name'], 'url' => Tokens::link('invitation', $token)];
                    if ($row['class_id']) {
                        $this->enrol((int) $row['class_id'], $id, $row['role']);
                    }
                } elseif ($row['action'] === 'enrol' && $row['class_id']) {
                    $enrolled += $this->enrol((int) $row['class_id'], (int) $row['user_id'], $row['role']) ? 1 : 0;
                }
            }
        });
        Audit::log('users.imported', null, null, ['created' => count($created), 'enrolled' => $enrolled]);
        // Links are kept briefly so the administrator can download them as a file.
        Session::set('import_links', ['at' => time(), 'rows' => $created]);
        return $this->view('admin/import_done', [
            'title' => t('Import complete'),
            'crumbs' => [[t('People'), '/admin/users'], [t('Import complete'), null]],
            'created' => $created,
            'enrolled' => $enrolled,
        ]);
    }

    /** CSV of the invitation links from the import that just finished (this session only, 30 minutes). */
    public function links(): Response
    {
        $data = Session::get('import_links');
        if (!is_array($data) || time() - (int) $data['at'] > 1800) {
            $this->flash('error', t('The invitation links are no longer available here. Create new links from each person\'s page.'));
            return $this->redirect('/admin/users');
        }
        $out = "\xEF\xBB\xBF" . "name,email,role,class,invitation_link\r\n";
        foreach ($data['rows'] as $r) {
            $out .= implode(',', array_map(static function ($v): string {
                $v = (string) $v;
                if ($v !== '' && strpbrk($v[0], '=+-@') !== false) {
                    $v = "'" . $v;
                }
                return '"' . str_replace('"', '""', $v) . '"';
            }, [$r['name'], $r['email'], $r['role'], (string) $r['class'], $r['url']])) . "\r\n";
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
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? '';
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $firstLine = strtok($content, "\n") ?: '';
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        $headerRow = fgetcsv($handle, 0, $delimiter);
        if (!$headerRow) {
            return t('The file is empty.');
        }
        $header = [];
        foreach ($headerRow as $i => $name) {
            $name = mb_strtolower(trim((string) $name));
            foreach (self::ALIASES as $key => $aliases) {
                if (in_array($name, $aliases, true) && !isset($header[$key])) {
                    $header[$key] = $i;
                }
            }
        }
        $missing = array_diff(['given_name', 'family_name', 'email'], array_keys($header));
        if ($missing) {
            return t('The first row must name the columns. Missing: :cols. Download the template to see the expected layout.', ['cols' => implode(', ', $missing)]);
        }
        $lines = [];
        while (($line = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($line === [null] || implode('', array_map('trim', array_map('strval', $line))) === '') {
                continue;
            }
            $lines[] = $line;
            if (count($lines) > self::MAX_ROWS) {
                return t('The file has more than :n rows. Split it into smaller files.', ['n' => self::MAX_ROWS]);
            }
        }
        fclose($handle);
        if (!$lines) {
            return t('The file has a header row but no people.');
        }
        return [$header, $lines];
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
