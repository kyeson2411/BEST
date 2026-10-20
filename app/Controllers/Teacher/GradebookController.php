<?php

declare(strict_types=1);

namespace Sibol\Controllers\Teacher;

use Sibol\Controllers\Controller;
use Sibol\Core\Audit;
use Sibol\Core\Response;
use Sibol\Policies\Access;
use Sibol\Repositories\TeacherRepo;
use Sibol\Services\Grades;
use Sibol\Services\GradingPeriods;

final class GradebookController extends Controller
{
    public function index(): Response
    {
        $user = $this->user();
        $classes = TeacherRepo::classes($user);
        if (count($classes) === 1) {
            return $this->redirect('/teacher/gradebook/' . (int) $classes[0]['id']);
        }
        return $this->view('teacher/class_picker', [
            'title' => t('Gradebook'),
            'lead' => t('Choose a class to see its gradebook.'),
            'classes' => $classes,
            'target' => '/teacher/gradebook/',
            'icon' => 'table',
        ]);
    }

    public function show(int $id): Response
    {
        $user = $this->user();
        $class = Access::teacherClass($user, $id);
        $book = Grades::classBook($id, (int) $user['organization_id']);
        // With grading periods: one period's work and grade, the final grades, or all work as before.
        $view = 'all';
        if ($book['periods']) {
            $asked = $this->request->str('period');
            $ids = array_map(static fn (array $p): string => (string) $p['id'], $book['periods']);
            $view = in_array($asked, array_merge($ids, ['final', 'all']), true)
                ? $asked
                : (string) GradingPeriods::current($book['periods'], GradingPeriods::zone((int) $user['organization_id']));
        }
        return $this->view('teacher/gradebook', [
            'title' => t('Gradebook: :class', ['class' => $class['name']]),
            'crumbs' => [[t('Gradebook'), '/teacher/gradebook'], [$class['name'], null]],
            'class' => $class,
            'book' => $book,
            'show' => $view,
            'classes' => TeacherRepo::classes($user),
        ]);
    }

    public function export(int $id): Response
    {
        $user = $this->user();
        $class = Access::teacherClass($user, $id);
        $book = Grades::classBook($id, (int) $user['organization_id']);
        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so spreadsheet apps read names correctly
        $header = [t('Family name'), t('Given name'), t('Username'), t('Email')];
        foreach ($book['assignments'] as $a) {
            $header[] = $a['title'] . ' (/' . fmt_number($a['points_possible'], 2) . ')';
        }
        if ($book['periods']) {
            // Per grading period: the initial grade and, when the school's table is on, the transmuted grade.
            foreach ($book['periods'] as $p) {
                $header[] = t(':period initial grade', ['period' => $p['name']]);
                if ($book['transmuted']) {
                    $header[] = t(':period grade', ['period' => $p['name']]);
                }
            }
            $header[] = t('Final grade');
        } else {
            $header[] = t('Overall %');
        }
        $header[] = t('Descriptor');
        fputcsv($out, array_map([$this, 'cell'], $header));
        foreach ($book['students'] as $s) {
            $sid = (int) $s['id'];
            $row = [$s['family_name'], $s['given_name'], (string) $s['username'], (string) $s['email']];
            foreach ($book['assignments'] as $a) {
                $cell = $book['cells'][$sid][(int) $a['id']] ?? null;
                $row[] = $cell === null ? '' : ($cell['state'] === 'graded' ? fmt_number($cell['score'], 2) : (['missing' => t('missing'), 'absent' => t('absent'), 'pending' => t('to grade'), 'excused' => t('excused')][$cell['state']] ?? ''));
            }
            if ($book['periods']) {
                foreach ($book['periods'] as $p) {
                    $g = $book['by_period'][$sid][(int) $p['id']] ?? null;
                    $row[] = $g && $g['initial'] !== null ? number_format((float) $g['initial'], 2, '.', '') : '';
                    if ($book['transmuted']) {
                        $row[] = $g && $g['grade'] !== null ? fmt_number($g['grade'], 0) : '';
                    }
                }
                $final = $book['final'][$sid] ?? null;
                $row[] = $final && $final['grade'] !== null ? (string) $final['grade'] : '';
                $row[] = $final['descriptor'] ?? '';
            } else {
                $overall = $book['overall'][$sid] ?? null;
                $row[] = $overall && $overall['percent'] !== null ? (string) $overall['percent'] : '';
                $row[] = $overall['descriptor'] ?? '';
            }
            fputcsv($out, array_map([$this, 'cell'], $row));
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);
        Audit::log('gradebook.exported', 'class', $id, ['format' => 'csv', 'rows' => count($book['students'])]);
        $name = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $class['name']) . '-gradebook-' . gmdate('Y-m-d') . '.csv';
        return Response::html($csv)
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="' . $name . '"')
            ->header('Cache-Control', 'private, no-store');
    }

    /** Neutralises spreadsheet formula injection (cells starting with = + - @). */
    private function cell($value): string
    {
        $value = (string) $value;
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }
}
