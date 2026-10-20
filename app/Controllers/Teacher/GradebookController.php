<?php

declare(strict_types=1);

namespace Sibol\Controllers\Teacher;

use Sibol\Controllers\Controller;
use Sibol\Core\Audit;
use Sibol\Core\Response;
use Sibol\Policies\Access;
use Sibol\Repositories\TeacherRepo;
use Sibol\Services\Grades;

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
        return $this->view('teacher/gradebook', [
            'title' => t('Gradebook: :class', ['class' => $class['name']]),
            'crumbs' => [[t('Gradebook'), '/teacher/gradebook'], [$class['name'], null]],
            'class' => $class,
            'book' => $book,
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
        $header = [t('Family name'), t('Given name'), t('Email')];
        foreach ($book['assignments'] as $a) {
            $header[] = $a['title'] . ' (/' . fmt_number($a['points_possible'], 2) . ')';
        }
        $header[] = t('Overall %');
        $header[] = t('Descriptor');
        fputcsv($out, array_map([$this, 'cell'], $header));
        foreach ($book['students'] as $s) {
            $row = [$s['family_name'], $s['given_name'], $s['email']];
            foreach ($book['assignments'] as $a) {
                $cell = $book['cells'][(int) $s['id']][(int) $a['id']] ?? null;
                $row[] = $cell === null ? '' : ($cell['state'] === 'graded' ? fmt_number($cell['score'], 2) : (['missing' => t('missing'), 'pending' => t('to grade'), 'excused' => t('excused')][$cell['state']] ?? ''));
            }
            $overall = $book['overall'][(int) $s['id']] ?? null;
            $row[] = $overall && $overall['percent'] !== null ? (string) $overall['percent'] : '';
            $row[] = $overall['descriptor'] ?? '';
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
