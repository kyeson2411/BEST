<?php

declare(strict_types=1);

namespace Sibol\Controllers\Admin;

use Sibol\Controllers\Controller;
use Sibol\Core\Audit;
use Sibol\Core\Db;
use Sibol\Core\HttpException;
use Sibol\Core\Response;
use Sibol\Services\Notifier;
use Sibol\Services\Sections;

/** Subjects, academic periods (school years, quarters or terms) and sections with their advisers. */
final class StructureController extends Controller
{
    public function subjects(): Response
    {
        $o = $this->orgId();
        return $this->view('admin/subjects', [
            'title' => t('Subjects'),
            'rows' => Db::all(
                "SELECT s.*, (SELECT COUNT(*) FROM classes c WHERE c.subject_id = s.id AND c.status = 'active') AS classes
                 FROM subjects s WHERE s.organization_id = :o ORDER BY s.archived_at IS NOT NULL, s.name",
                ['o' => $o]
            ),
            'editing' => $this->request->int('edit'),
        ]);
    }

    public function storeSubject(): Response
    {
        $o = $this->orgId();
        $data = $this->validate($this->subjectRules(), $this->subjectLabels(), '/admin/subjects');
        $this->assertUniqueSubject($o, $data['name'], null, '/admin/subjects');
        $id = Db::insert('subjects', ['organization_id' => $o, 'name' => $data['name'], 'code' => $data['code'], 'description' => $data['description'], 'created_at' => Db::now(), 'updated_at' => Db::now()]);
        Audit::log('subject.created', 'subject', $id);
        $this->flash('success', t('Subject added.'));
        return $this->redirect('/admin/subjects');
    }

    public function updateSubject(int $id): Response
    {
        $o = $this->orgId();
        $subject = Db::one('SELECT * FROM subjects WHERE id = :id AND organization_id = :o', ['id' => $id, 'o' => $o]);
        if (!$subject) {
            throw HttpException::notFound();
        }
        $back = '/admin/subjects?edit=' . $id;
        $data = $this->validate($this->subjectRules() + ['archived' => 'bool'], $this->subjectLabels(), $back);
        $this->assertUniqueSubject($o, $data['name'], $id, $back);
        Db::update('subjects', [
            'name' => $data['name'],
            'code' => $data['code'],
            'description' => $data['description'],
            'archived_at' => $data['archived'] ? ($subject['archived_at'] ?? Db::now()) : null,
            'updated_at' => Db::now(),
        ], ['id' => $id]);
        Audit::log('subject.updated', 'subject', $id);
        $this->flash('success', t('Subject saved.'));
        return $this->redirect('/admin/subjects');
    }

    public function periods(): Response
    {
        $o = $this->orgId();
        return $this->view('admin/periods', [
            'title' => t('Academic periods'),
            'rows' => Db::all(
                'SELECT p.*, (SELECT COUNT(*) FROM classes c WHERE c.academic_period_id = p.id) AS classes
                 FROM academic_periods p WHERE p.organization_id = :o ORDER BY p.starts_on DESC',
                ['o' => $o]
            ),
            'editing' => $this->request->int('edit'),
        ]);
    }

    public function storePeriod(): Response
    {
        $o = $this->orgId();
        $data = $this->validate($this->periodRules(), $this->periodLabels(), '/admin/academic-periods');
        $this->checkDates($data, '/admin/academic-periods');
        $id = Db::transaction(static function () use ($o, $data): int {
            if ($data['is_current']) {
                Db::exec('UPDATE academic_periods SET is_current = 0 WHERE organization_id = :o', ['o' => $o]);
            }
            return Db::insert('academic_periods', ['organization_id' => $o, 'name' => $data['name'], 'starts_on' => $data['starts_on'], 'ends_on' => $data['ends_on'], 'is_current' => $data['is_current'] ? 1 : 0, 'created_at' => Db::now(), 'updated_at' => Db::now()]);
        });
        Audit::log('period.created', 'period', $id);
        $this->flash('success', t('Academic period added.'));
        return $this->redirect('/admin/academic-periods');
    }

    public function updatePeriod(int $id): Response
    {
        $o = $this->orgId();
        if (!Db::value('SELECT id FROM academic_periods WHERE id = :id AND organization_id = :o', ['id' => $id, 'o' => $o])) {
            throw HttpException::notFound();
        }
        $back = '/admin/academic-periods?edit=' . $id;
        $data = $this->validate($this->periodRules(), $this->periodLabels(), $back);
        $this->checkDates($data, $back);
        Db::transaction(static function () use ($o, $id, $data): void {
            if ($data['is_current']) {
                Db::exec('UPDATE academic_periods SET is_current = 0 WHERE organization_id = :o', ['o' => $o]);
            }
            Db::update('academic_periods', ['name' => $data['name'], 'starts_on' => $data['starts_on'], 'ends_on' => $data['ends_on'], 'is_current' => $data['is_current'] ? 1 : 0, 'updated_at' => Db::now()], ['id' => $id]);
        });
        Audit::log('period.updated', 'period', $id);
        $this->flash('success', t('Academic period saved.'));
        return $this->redirect('/admin/academic-periods');
    }

    /** Sections come from the section names given to classes; here each one gets its adviser. */
    public function sections(): Response
    {
        $o = $this->orgId();
        return $this->view('admin/sections', [
            'title' => t('Sections and advisers'),
            'rows' => Db::all(
                "SELECT se.*, ap.name AS period_name, ap.is_current, u.given_name, u.family_name, u.preferred_name, u.status AS adviser_status,
                        (SELECT COUNT(*) FROM classes c WHERE c.section_id = se.id AND c.status = 'active') AS classes,
                        (SELECT COUNT(*) FROM classes c WHERE c.section_id = se.id) AS all_classes,
                        (SELECT COUNT(DISTINCT cm.user_id) FROM class_memberships cm JOIN classes c ON c.id = cm.class_id AND c.status = 'active'
                          WHERE c.section_id = se.id AND cm.role = 'student' AND cm.status = 'active') AS students
                 FROM sections se
                 LEFT JOIN academic_periods ap ON ap.id = se.academic_period_id
                 LEFT JOIN users u ON u.id = se.adviser_id
                 WHERE se.organization_id = :o
                 ORDER BY ap.is_current DESC, ap.starts_on DESC, se.grade_level, se.name",
                ['o' => $o]
            ),
            'teachers' => Db::all("SELECT id, given_name, family_name, preferred_name FROM users WHERE organization_id = :o AND role = 'teacher' AND status <> 'deactivated' ORDER BY family_name, given_name", ['o' => $o]),
        ]);
    }

    public function updateSection(int $id): Response
    {
        $admin = $this->user();
        $o = (int) $admin['organization_id'];
        $section = Db::one('SELECT * FROM sections WHERE id = :id AND organization_id = :o', ['id' => $id, 'o' => $o]);
        if (!$section) {
            throw HttpException::notFound();
        }
        $back = '/admin/sections#section-' . $id;
        $data = $this->validate(['adviser_id' => 'nullable|int'], ['adviser_id' => t('Adviser')], $back);
        $adviser = $data['adviser_id'] ? (int) $data['adviser_id'] : null;
        if ($adviser && !Db::value("SELECT id FROM users WHERE id = :id AND organization_id = :o AND role = 'teacher' AND status <> 'deactivated'", ['id' => $adviser, 'o' => $o])) {
            $this->fail('adviser_id', t('Choose a teacher from your school.'), $back);
        }
        if ($adviser === ($section['adviser_id'] !== null ? (int) $section['adviser_id'] : null)) {
            return $this->redirect($back);
        }
        Db::update('sections', ['adviser_id' => $adviser, 'updated_at' => Db::now()], ['id' => $id]);
        Audit::log('section.adviser_changed', 'section', $id, ['has_adviser' => $adviser !== null]);
        if ($adviser) {
            Notifier::send($o, [$adviser], 'class', t('You are the adviser of :section', ['section' => Sections::label($section)]), t('You can print sign-in slips for its students.'), '/teacher/advisory/' . $id);
        }
        $this->flash('success', $adviser ? t('Adviser saved.') : t('The section has no adviser now.'));
        return $this->redirect($back);
    }

    public function deleteSection(int $id): Response
    {
        $o = $this->orgId();
        $section = Db::one('SELECT * FROM sections WHERE id = :id AND organization_id = :o', ['id' => $id, 'o' => $o]);
        if (!$section) {
            throw HttpException::notFound();
        }
        if ((int) Db::value('SELECT COUNT(*) FROM classes WHERE section_id = :s', ['s' => $id]) > 0) {
            $this->flash('error', t('Only a section without classes can be removed. Change the section of its classes first.'));
            return $this->redirect('/admin/sections');
        }
        Db::delete('sections', ['id' => $id]);
        Audit::log('section.removed', 'section', $id);
        $this->flash('success', t('Section removed.'));
        return $this->redirect('/admin/sections');
    }

    // ------------------------------------------------------------------------------------------

    private function subjectRules(): array
    {
        return ['name' => 'required|string|max:120', 'code' => 'nullable|string|max:30', 'description' => 'nullable|string|max:500'];
    }

    private function subjectLabels(): array
    {
        return ['name' => t('Subject name'), 'code' => t('Code'), 'description' => t('Description')];
    }

    private function assertUniqueSubject(int $o, string $name, ?int $exceptId, string $back): void
    {
        $dupe = Db::value('SELECT id FROM subjects WHERE organization_id = :o AND name = :n' . ($exceptId ? ' AND id <> :id' : ''), $exceptId ? ['o' => $o, 'n' => $name, 'id' => $exceptId] : ['o' => $o, 'n' => $name]);
        if ($dupe) {
            $this->fail('name', t('A subject with this name already exists.'), $back);
        }
    }

    private function periodRules(): array
    {
        return ['name' => 'required|string|max:100', 'starts_on' => 'required|date', 'ends_on' => 'required|date', 'is_current' => 'bool'];
    }

    private function periodLabels(): array
    {
        return ['name' => t('Name'), 'starts_on' => t('Start date'), 'ends_on' => t('End date')];
    }

    private function checkDates(array $data, string $back): void
    {
        if ($data['ends_on'] < $data['starts_on']) {
            $this->fail('ends_on', t('The end date must be on or after the start date.'), $back);
        }
    }
}
