<?php

declare(strict_types=1);

namespace Sibol\Controllers\Admin;

use Sibol\Controllers\Controller;
use Sibol\Core\Audit;
use Sibol\Core\Db;
use Sibol\Core\HttpException;
use Sibol\Core\Response;
use Sibol\Services\GradingPeriods;
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
            'gradingPeriods' => GradingPeriods::forOrganization($o),
            'gpEditing' => $this->request->int('gp'),
            'gpAdding' => $this->request->int('gpnew'),
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

    // ------------------------------------------------------------------ grading periods (quarters or terms)

    public function storeGradingPeriod(int $id): Response
    {
        $o = $this->orgId();
        $year = $this->year($id, $o);
        $back = '/admin/academic-periods?gpnew=' . $id . '#year-' . $id;
        $data = $this->validate($this->gradingPeriodRules(), $this->gradingPeriodLabels(), $back);
        $others = GradingPeriods::forAcademicPeriod($id);
        if (count($others) >= GradingPeriods::MAX) {
            $this->fail('gp_name', t('A school year can have at most :n grading periods.', ['n' => GradingPeriods::MAX]), $back);
        }
        $problems = GradingPeriods::problems($year, (string) $data['gp_starts_on'], (string) $data['gp_ends_on'], $others);
        if ($problems) {
            $this->fail('gp_starts_on', $problems[0], $back);
        }
        $gid = Db::insert('grading_periods', [
            'organization_id' => $o, 'academic_period_id' => $id, 'name' => $data['gp_name'], 'position' => count($others) + 1,
            'starts_on' => $data['gp_starts_on'], 'ends_on' => $data['gp_ends_on'], 'created_at' => Db::now(), 'updated_at' => Db::now(),
        ]);
        Audit::log('grading_period.created', 'period', $id, ['grading_period' => $gid]);
        $this->flash('success', t('Grading period added.'));
        return $this->redirect('/admin/academic-periods#year-' . $id);
    }

    public function updateGradingPeriod(int $id): Response
    {
        $o = $this->orgId();
        $gp = $this->gradingPeriod($id, $o);
        $yearId = (int) $gp['academic_period_id'];
        $back = '/admin/academic-periods?gp=' . $id . '#year-' . $yearId;
        $data = $this->validate($this->gradingPeriodRules(), $this->gradingPeriodLabels(), $back);
        $others = array_values(array_filter(GradingPeriods::forAcademicPeriod($yearId), static fn (array $g): bool => (int) $g['id'] !== $id));
        $problems = GradingPeriods::problems($this->year($yearId, $o), (string) $data['gp_starts_on'], (string) $data['gp_ends_on'], $others);
        if ($problems) {
            $this->fail('gp_starts_on', $problems[0], $back);
        }
        Db::update('grading_periods', ['name' => $data['gp_name'], 'starts_on' => $data['gp_starts_on'], 'ends_on' => $data['gp_ends_on'], 'updated_at' => Db::now()], ['id' => $id]);
        Audit::log('grading_period.updated', 'period', $yearId, ['grading_period' => $id]);
        $this->flash('success', t('Grading period saved. Work moves to the period its due date now falls in.'));
        return $this->redirect('/admin/academic-periods#year-' . $yearId);
    }

    public function deleteGradingPeriod(int $id): Response
    {
        $o = $this->orgId();
        $gp = $this->gradingPeriod($id, $o);
        Db::delete('grading_periods', ['id' => $id]);
        Audit::log('grading_period.deleted', 'period', (int) $gp['academic_period_id'], ['grading_period' => $id]);
        $this->flash('success', t('Grading period removed. Work a teacher had placed in it now counts by its due date.'));
        return $this->redirect('/admin/academic-periods#year-' . (int) $gp['academic_period_id']);
    }

    /** Splits a school year into four quarters or three terms of about equal length, to adjust afterwards. */
    public function splitGradingPeriods(int $id): Response
    {
        $o = $this->orgId();
        $year = $this->year($id, $o);
        $count = (int) $this->request->int('count');
        $back = $this->request->str('back') === 'grading' ? '/admin/grading#periods' : '/admin/academic-periods#year-' . $id;
        if (!in_array($count, [3, 4], true)) {
            throw HttpException::notFound();
        }
        $made = GradingPeriods::split($year, $count, $count === 3 ? t('Term') : t('Quarter'));
        if ($made) {
            Audit::log('grading_period.split', 'period', $id, ['count' => $made]);
            $this->flash('success', tp('Made :count grading period of about equal length. Adjust the dates to the school calendar.', 'Made :count grading periods of about equal length. Adjust the dates to the school calendar.', $made));
        } else {
            $this->flash('error', t('This school year already has grading periods, or is too short to split.'));
        }
        return $this->redirect($back);
    }

    // ------------------------------------------------------------------------------------------

    /** @return array<string,mixed> */
    private function year(int $id, int $o): array
    {
        $year = Db::one('SELECT * FROM academic_periods WHERE id = :id AND organization_id = :o', ['id' => $id, 'o' => $o]);
        if (!$year) {
            throw HttpException::notFound();
        }
        return $year;
    }

    /** @return array<string,mixed> */
    private function gradingPeriod(int $id, int $o): array
    {
        $gp = Db::one('SELECT * FROM grading_periods WHERE id = :id AND organization_id = :o', ['id' => $id, 'o' => $o]);
        if (!$gp) {
            throw HttpException::notFound();
        }
        return $gp;
    }

    private function gradingPeriodRules(): array
    {
        return ['gp_name' => 'required|string|max:60', 'gp_starts_on' => 'required|date', 'gp_ends_on' => 'required|date'];
    }

    private function gradingPeriodLabels(): array
    {
        return ['gp_name' => t('Name'), 'gp_starts_on' => t('Start date'), 'gp_ends_on' => t('End date')];
    }

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
