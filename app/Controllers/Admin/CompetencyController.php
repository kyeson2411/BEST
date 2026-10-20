<?php

declare(strict_types=1);

namespace Sibol\Controllers\Admin;

use Sibol\Controllers\Controller;
use Sibol\Core\Audit;
use Sibol\Core\Db;
use Sibol\Core\HttpException;
use Sibol\Core\Response;
use Sibol\Core\Session;
use Sibol\Core\Uploads;
use Sibol\Services\Competencies;
use Sibol\Services\Settings;

/** The school's learning competencies: imported from the school's own list (CSV), never typed in by Sibol. */
final class CompetencyController extends Controller
{
    private const MAX_BYTES = 2097152;
    private const SHOWN = 300;

    public function index(): Response
    {
        $o = $this->orgId();
        $subject = $this->request->int('subject');
        $grade = $this->request->str('grade');
        $q = trim($this->request->str('q'));
        $where = ['c.organization_id = :o'];
        $params = ['o' => $o];
        if ($subject !== null) {
            $where[] = 'c.subject_id = :s';
            $params['s'] = $subject;
        }
        if ($grade !== '') {
            $where[] = 'c.grade_level = :g';
            $params['g'] = $grade;
        }
        if ($q !== '') {
            $where[] = '(c.code LIKE :q1 OR c.description LIKE :q2)';
            $params['q1'] = '%' . $q . '%';
            $params['q2'] = '%' . $q . '%';
        }
        $sql = ' FROM competencies c LEFT JOIN subjects s ON s.id = c.subject_id WHERE ' . implode(' AND ', $where);
        $total = (int) Db::value('SELECT COUNT(*)' . $sql, $params);
        $rows = Db::all(
            'SELECT c.*, s.name AS subject_name,
                    (SELECT COUNT(*) FROM questions qq WHERE qq.competency_id = c.id) AS questions,
                    (SELECT COUNT(*) FROM lesson_competencies lc WHERE lc.competency_id = c.id) AS lessons'
            . $sql . ' ORDER BY c.archived_at IS NOT NULL, s.name, c.grade_level, c.quarter IS NULL, c.quarter, c.code LIMIT ' . self::SHOWN,
            $params
        );
        return $this->view('admin/competencies', [
            'title' => t('Learning competencies'),
            'crumbs' => [[t('Subjects and terms'), '/admin/subjects'], [t('Learning competencies'), null]],
            'rows' => $rows,
            'total' => $total,
            'shown' => self::SHOWN,
            'all' => (int) Db::value('SELECT COUNT(*) FROM competencies WHERE organization_id = :o', ['o' => $o]),
            'subjects' => Db::all('SELECT id, name FROM subjects WHERE organization_id = :o AND archived_at IS NULL ORDER BY name', ['o' => $o]),
            'grades' => (array) Settings::get($o, 'grade_levels', []),
            'filters' => ['subject' => $subject, 'grade' => $grade, 'q' => $q],
        ]);
    }

    /** The columns to use, with no rows: the codes come from the school's own list. */
    public function template(): Response
    {
        return Response::html("\xEF\xBB\xBF" . 'code,description,subject,grade_level,quarter' . "\r\n")
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="sibol-competencies-template.csv"');
    }

    /** Reads the uploaded list and shows what importing it would do; nothing is saved yet. */
    public function preview(): Response
    {
        $o = $this->orgId();
        $file = $this->request->file('csv');
        $error = Uploads::validate($file, ['csv', 'txt'], self::MAX_BYTES);
        if ($error !== null) {
            $this->fail('csv', $error, '/admin/competencies');
        }
        $result = Competencies::preview($o, (string) file_get_contents((string) $file['tmp_name']));
        if (is_string($result)) {
            $this->fail('csv', $result, '/admin/competencies');
        }
        $key = bin2hex(random_bytes(8));
        Session::set('competency_import', ['key' => $key, 'org' => $o, 'rows' => $result['rows'], 'at' => time()]);
        return $this->view('admin/competencies_preview', [
            'title' => t('Check the list'),
            'crumbs' => [[t('Subjects and terms'), '/admin/subjects'], [t('Learning competencies'), '/admin/competencies'], [t('Check'), null]],
            'rows' => $result['rows'],
            'counts' => $result['counts'],
            'key' => $key,
            'filename' => Uploads::cleanName((string) $file['name']),
        ]);
    }

    public function confirm(): Response
    {
        $o = $this->orgId();
        $preview = Session::get('competency_import');
        if (!is_array($preview) || ($preview['key'] ?? '') !== $this->request->str('key') || (int) $preview['org'] !== $o || time() - (int) $preview['at'] > 1800) {
            $this->flash('error', t('This preview has expired or was already imported. Upload the file again.'));
            return $this->redirect('/admin/competencies');
        }
        Session::set('competency_import', null);
        $done = Competencies::import($o, (array) $preview['rows']);
        Audit::log('competencies.imported', null, null, $done);
        $this->flash('success', t('Learning competencies imported: :added added, :updated updated.', $done));
        return $this->redirect('/admin/competencies');
    }

    /** Archived competencies stay on questions and lessons that use them, but cannot be chosen again. */
    public function archive(int $id): Response
    {
        $o = $this->orgId();
        $row = Db::one('SELECT id, code, archived_at FROM competencies WHERE id = :id AND organization_id = :o', ['id' => $id, 'o' => $o]);
        if (!$row) {
            throw HttpException::notFound();
        }
        $archive = $row['archived_at'] === null;
        Db::update('competencies', ['archived_at' => $archive ? Db::now() : null, 'updated_at' => Db::now()], ['id' => $id]);
        Audit::log($archive ? 'competency.archived' : 'competency.restored', 'competency', $id);
        $this->flash('success', $archive ? t(':code archived.', ['code' => $row['code']]) : t(':code can be chosen again.', ['code' => $row['code']]));
        return $this->redirect('/admin/competencies');
    }
}
