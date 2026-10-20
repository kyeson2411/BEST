<?php

declare(strict_types=1);

namespace Sibol\Controllers\Admin;

use Sibol\Controllers\Controller;
use Sibol\Core\Audit;
use Sibol\Core\Db;
use Sibol\Core\Response;
use Sibol\Services\GradingPeriods;
use Sibol\Services\Settings;
use Sibol\Services\Transmutation;

/**
 * The school's grading setup: how overall grades are worked out, weights for groups of subjects, the
 * transmutation table, and starting points taken from DepEd's orders, which the school checks before use.
 * Sibol does not assume a policy: every number here is the school's.
 */
final class GradingController extends Controller
{
    public function index(): Response
    {
        $o = $this->orgId();
        $year = $this->currentYear($o);
        $grading = (array) Settings::get($o, 'grading', []);
        $checkedBy = (int) ($grading['transmutation']['checked_by'] ?? 0);
        return $this->view('admin/grading', [
            'title' => t('Grading'),
            'grading' => $grading,
            'presets' => Settings::gradingPresets(),
            'used' => array_map('strval', Db::column("SELECT DISTINCT category FROM assignments WHERE organization_id = :o AND category IS NOT NULL AND category <> ''", ['o' => $o])),
            'year' => $year,
            'periods' => $year ? GradingPeriods::forAcademicPeriod((int) $year['id']) : [],
            'subjects' => Db::all('SELECT id, name, code, grading_group FROM subjects WHERE organization_id = :o AND archived_at IS NULL ORDER BY name', ['o' => $o]),
            'checker' => $checkedBy ? Db::one('SELECT given_name, family_name, preferred_name FROM users WHERE id = :u AND organization_id = :o', ['u' => $checkedBy, 'o' => $o]) : null,
        ]);
    }

    /** Loads a starting point. The DepEd ones can also split the current school year into its periods. */
    public function preset(): Response
    {
        $admin = $this->user();
        $o = (int) $admin['organization_id'];
        $presets = Settings::gradingPresets();
        $key = $this->request->str('preset');
        if (!isset($presets[$key])) {
            $this->fail('preset', t('Choose one of the starting points.'), '/admin/grading');
        }
        Settings::set($o, 'grading', $presets[$key]['value'], (int) $admin['id']);
        $made = 0;
        $year = $this->currentYear($o);
        if ($year && !empty($presets[$key]['periods']) && $this->request->bool('make_periods')) {
            $count = (int) $presets[$key]['periods'];
            $made = GradingPeriods::split($year, $count, $count === 3 ? t('Term') : t('Quarter'));
        }
        Audit::log('settings.grading_updated', null, null, ['preset' => $key, 'periods_made' => $made]);
        $this->flash('success', $made
            ? tp('Template loaded, with :count grading period. Check its dates, the weights and the table against the order before teachers rely on them.', 'Template loaded, with :count grading periods. Check their dates, the weights and the table against the order before teachers rely on them.', $made)
            : t('Template loaded. Check the weights and the table against your school\'s policy before teachers rely on them.'));
        return $this->redirect('/admin/grading');
    }

    /** How overall grades are worked out: total points or weighted categories, descriptors and the passing mark. */
    public function save(): Response
    {
        $admin = $this->user();
        $o = (int) $admin['organization_id'];
        $back = '/admin/grading#overall';
        $data = $this->validate([
            'model' => 'required|in:points,weighted',
            'passing_percent' => 'required|numeric|min:0|max:100',
        ], ['model' => t('Grading model'), 'passing_percent' => t('Passing percentage')], $back);

        $categories = [];
        $keys = $this->request->arr('cat_key');
        $weights = $this->request->arr('cat_weight');
        foreach ($this->request->arr('cat_label') as $i => $label) {
            $label = mb_substr(trim((string) $label), 0, 60);
            $weight = trim((string) ($weights[$i] ?? ''));
            if ($label === '' && $weight === '') {
                continue;
            }
            if ($label === '' || !is_numeric($weight) || (float) $weight < 0 || (float) $weight > 100) {
                $this->fail('categories', t('Each category needs a name and a weight between 0 and 100.'), $back);
            }
            $key = preg_replace('/[^a-z0-9_]/', '', (string) ($keys[$i] ?? '')) ?: trim((string) preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($label)), '_');
            if ($key === '' || isset($categories[$key])) {
                $key = 'cat' . (count($categories) + 1);
            }
            $categories[$key] = ['key' => $key, 'label' => $label, 'weight' => round((float) $weight, 2)];
        }
        if ($data['model'] === 'weighted') {
            if (!$categories) {
                $this->fail('categories', t('Weighted grading needs at least one category.'), $back);
            }
            $sum = array_sum(array_column($categories, 'weight'));
            if (abs($sum - 100) > 0.01) {
                $this->fail('categories', t('Category weights add up to :sum%. They must add up to 100%.', ['sum' => fmt_number($sum, 2)]), $back);
            }
        }
        $descriptors = [];
        $mins = $this->request->arr('desc_min');
        foreach ($this->request->arr('desc_label') as $i => $label) {
            $label = mb_substr(trim((string) $label), 0, 60);
            $min = trim((string) ($mins[$i] ?? ''));
            if ($label === '' && $min === '') {
                continue;
            }
            if ($label === '' || !is_numeric($min) || (float) $min < 0 || (float) $min > 100) {
                $this->fail('descriptors', t('Each descriptor needs a label and a lowest grade between 0 and 100.'), $back);
            }
            $descriptors[] = ['min' => (float) $min, 'label' => $label];
        }
        usort($descriptors, static fn ($a, $b) => $b['min'] <=> $a['min']);
        // Group weights and the transmutation table have their own forms and are kept as they are.
        $current = (array) Settings::get($o, 'grading', []);
        Settings::set($o, 'grading', [
            'model' => $data['model'],
            'categories' => array_values($categories),
            'groups' => (array) ($current['groups'] ?? []),
            'transmutation' => (array) ($current['transmutation'] ?? []),
            'passing_percent' => $data['passing_percent'],
            'descriptors' => $descriptors,
        ], (int) $admin['id']);
        Audit::log('settings.grading_updated', null, null, ['model' => $data['model']]);
        $this->flash('success', t('Grading setup saved. Gradebooks use it right away.'));
        return $this->redirect('/admin/grading#overall');
    }

    /** Weights for groups of subjects (for example DepEd's subject groups), and which group each subject is in. */
    public function groups(): Response
    {
        $admin = $this->user();
        $o = (int) $admin['organization_id'];
        $back = '/admin/grading#groups';
        $grading = (array) Settings::get($o, 'grading', []);
        $categories = (array) ($grading['categories'] ?? []);
        $keys = $this->request->arr('group_key');
        $weights = $this->request->arr('group_weight');
        $groups = [];
        foreach ($this->request->arr('group_label') as $i => $label) {
            $label = mb_substr(trim((string) $label), 0, 80);
            $row = (array) ($weights[$i] ?? []);
            $filled = array_filter($row, static fn ($v) => trim((string) $v) !== '');
            if ($label === '' && !$filled) {
                continue;
            }
            if ($label === '') {
                $this->fail('groups', t('Each group needs a name.'), $back);
            }
            $w = [];
            foreach ($categories as $c) {
                $v = trim((string) ($row[$c['key']] ?? ''));
                if (!is_numeric($v) || (float) $v < 0 || (float) $v > 100) {
                    $this->fail('groups', t('Give :group a weight between 0 and 100 for every category.', ['group' => $label]), $back);
                }
                $w[(string) $c['key']] = round((float) $v, 2);
            }
            $sum = array_sum($w);
            if (abs($sum - 100) > 0.01) {
                $this->fail('groups', t('The weights for :group add up to :sum%. They must add up to 100%.', ['group' => $label, 'sum' => fmt_number($sum, 2)]), $back);
            }
            $key = preg_replace('/[^a-z0-9_]/', '', (string) ($keys[$i] ?? '')) ?: trim((string) preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($label)), '_');
            if ($key === '' || isset($groups[$key])) {
                $key = 'group' . (count($groups) + 1);
            }
            $groups[$key] = ['key' => $key, 'label' => $label, 'weights' => $w];
        }
        $grading['groups'] = array_values($groups);
        $chosen = $this->request->arr('subject_group');
        Db::transaction(static function () use ($o, $admin, $grading, $groups, $chosen): void {
            foreach (Db::column('SELECT id FROM subjects WHERE organization_id = :o', ['o' => $o]) as $sid) {
                $key = (string) ($chosen[(string) $sid] ?? '');
                Db::update('subjects', ['grading_group' => isset($groups[$key]) ? $key : null, 'updated_at' => Db::now()], ['id' => (int) $sid]);
            }
            Settings::set($o, 'grading', $grading, (int) $admin['id']);
        });
        Audit::log('settings.grading_groups_updated', null, null, ['groups' => count($groups)]);
        $this->flash('success', t('Subject groups saved. Gradebooks use their weights right away.'));
        return $this->redirect($back);
    }

    /**
     * The transmutation table: typed or pasted in, checked line by line against the order, then switched on.
     * A changed table must be checked again before it is used.
     */
    public function transmutation(): Response
    {
        $admin = $this->user();
        $o = (int) $admin['organization_id'];
        $back = '/admin/grading#transmutation';
        $grading = (array) Settings::get($o, 'grading', []);
        $current = (array) ($grading['transmutation'] ?? []);
        $rows = Transmutation::parse($this->request->str('rows'));
        if ($rows && ($problems = Transmutation::problems($rows))) {
            $this->fail('rows', implode(' ', $problems), $back);
        }
        $same = $rows === array_map(static fn (array $r): array => ['min' => (float) $r['min'], 'grade' => (float) $r['grade']], (array) ($current['rows'] ?? []));
        $ticked = $this->request->bool('confirmed');
        // An unchanged table that was already checked stays checked; any change needs a new check.
        $kept = $same && !empty($current['confirmed']) && !$ticked;
        $confirmed = $rows !== [] && ($ticked || $kept);
        $enabled = $this->request->bool('enabled');
        if ($enabled && !$rows) {
            $this->fail('rows', t('Type in the table before switching it on.'), $back);
        }
        if ($enabled && !$confirmed) {
            $this->fail('confirmed', t('Tick that you have checked every line against the official order before switching the table on.'), $back);
        }
        $grading['transmutation'] = [
            'enabled' => $enabled,
            'confirmed' => $confirmed,
            'rows' => $rows,
            'checked_by' => $confirmed ? ($kept ? ($current['checked_by'] ?? null) : (int) $admin['id']) : null,
            'checked_at' => $confirmed ? ($kept ? ($current['checked_at'] ?? null) : Db::now()) : null,
        ];
        Settings::set($o, 'grading', $grading, (int) $admin['id']);
        Audit::log('settings.transmutation_updated', null, null, ['rows' => count($rows), 'enabled' => $enabled, 'confirmed' => $confirmed]);
        $this->flash('success', $enabled
            ? t('Table saved and switched on. Grading periods now report transmuted grades.')
            : t('Table saved. It is not used until it is checked and switched on.'));
        return $this->redirect($back);
    }

    /** @return array<string,mixed>|null the current school year, else the latest */
    private function currentYear(int $o): ?array
    {
        return Db::one('SELECT * FROM academic_periods WHERE organization_id = :o ORDER BY is_current DESC, starts_on DESC LIMIT 1', ['o' => $o]);
    }
}
