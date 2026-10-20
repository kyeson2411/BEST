<?php
/**
 * The school's grading setup.
 * @var array $grading @var array $presets @var list<string> $used @var array|null $year @var list<array> $periods
 * @var list<array> $subjects @var array|null $checker
 */
use Sibol\Services\Transmutation;

$categories = (array) ($grading['categories'] ?? []);
$descriptors = (array) ($grading['descriptors'] ?? []);
$groups = (array) ($grading['groups'] ?? []);
$table = (array) ($grading['transmutation'] ?? []);
$rows = (array) ($table['rows'] ?? []);
$weighted = ($grading['model'] ?? 'points') === 'weighted';
$catRows = array_merge($categories, array_fill(0, max(1, 4 - count($categories)), ['key' => '', 'label' => '', 'weight' => '']));
$descRows = array_merge($descriptors, array_fill(0, max(1, 6 - count($descriptors)), ['min' => '', 'label' => '']));
$groupRows = array_merge($groups, array_fill(0, 2, ['key' => '', 'label' => '', 'weights' => []]));
$range = static fn (array $p): string => fmt_date($p['starts_on'] . ' 12:00:00', 'date_short') . ' – ' . fmt_date($p['ends_on'] . ' 12:00:00', 'date_short');
$on = Transmutation::active($grading) !== null;
$descriptions = [
    'points' => t('Points earned divided by points possible across all graded work.'),
    'k12' => t('Written work, performance tasks and periodic assessment at 40, 40 and 20 percent, for you to adjust.'),
    'deped2015' => t('Written work, performance tasks and quarterly assessment weighted by subject group (30/50/20, 40/40/20 and 20/60/20), and the order\'s transmutation table, which stays off until you check it.'),
    'deped2026' => t('Written or oral works, performance tasks and examinations at 20/50/30, and 20/60/20 for EPP/TLE and MAPEH, as reported when the order came out. Type its transmutation table in from the official order.'),
];
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Grading')) ?></h1>
    <p class="page-lead"><?= e(t('How grades are worked out at your school. Sibol does not assume a policy: the periods, weights and table here are your school\'s, and teachers still decide each score.')) ?></p>
  </div>
</div>

<nav class="tabs" aria-label="<?= e(t('Grading sections')) ?>">
  <a class="tab" href="#periods"><?= e(t('Grading periods')) ?></a>
  <a class="tab" href="#presets"><?= e(t('Templates')) ?></a>
  <a class="tab" href="#overall"><?= e(t('Overall grade')) ?></a>
  <a class="tab" href="#groups"><?= e(t('Subject groups')) ?></a>
  <a class="tab" href="#transmutation"><?= e(t('Transmutation')) ?></a>
</nav>

<section class="section" id="periods" aria-labelledby="periods-title">
  <div class="section__head"><h2 class="section__title" id="periods-title"><?= e(t('Grading periods')) ?></h2>
    <a class="section__link" href="<?= e(url('/admin/academic-periods')) ?>"><?= e(t('Edit on Academic periods')) ?></a></div>
  <?php if (!$year): ?>
    <p class="meta"><?= e(t('Add the school year on Academic periods first.')) ?></p>
  <?php elseif (!$periods): ?>
    <p class="section__intro"><?= e(t(':year has no grading periods, so each class has one overall grade. Split it into quarters or terms, then adjust the dates to the school calendar.', ['year' => $year['name']])) ?></p>
    <div class="cluster">
      <?php foreach ([4 => t('Make four quarters'), 3 => t('Make three terms')] as $count => $label): ?>
        <form method="post" action="<?= e(url('/admin/academic-periods/' . (int) $year['id'] . '/grading-periods/split')) ?>">
          <?= csrf_field() ?><input type="hidden" name="count" value="<?= $count ?>"><input type="hidden" name="back" value="grading">
          <button class="btn btn--secondary btn--sm" type="submit"><?= icon('calendar-days', 'icon--sm') ?><?= e($label) ?></button>
        </form>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="section__intro"><?= e(t('Work counts in the period its due date falls in, unless its teacher chooses another. Each period has its own grade, and the final grade is their average.')) ?></p>
    <ul class="rows" style="max-width: 640px">
      <?php foreach ($periods as $p): ?>
        <li class="row"><div class="row__main"><span class="row__title"><?= e($p['name']) ?></span><div class="row__meta"><span><?= e($range($p)) ?></span></div></div></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<section class="section" id="presets" aria-labelledby="presets-title">
  <div class="section__head"><h2 class="section__title" id="presets-title"><?= e(t('Start from a template')) ?></h2></div>
  <p class="section__intro"><?= e(t('Loading a template replaces the setup below. Check every number against your school\'s current policy before teachers rely on it.')) ?></p>
  <?php if (field_error('preset')): ?><p class="callout callout--coral"><?= icon('circle-alert') ?><span><?= e(field_error('preset')) ?></span></p><?php endif; ?>
  <div class="preset-list">
    <?php foreach ($presets as $key => $p): ?>
      <form method="post" action="<?= e(url('/admin/grading/preset')) ?>" class="panel panel--tight preset" data-confirm="<?= e(t('This replaces your current categories, subject groups, transmutation table and descriptors.')) ?>" data-confirm-title="<?= e(t('Load “:name”?', ['name' => $p['label']])) ?>" data-confirm-action="<?= e(t('Load template')) ?>">
        <?= csrf_field() ?><input type="hidden" name="preset" value="<?= e($key) ?>">
        <h3 class="preset__title"><?= e($p['label']) ?></h3>
        <p class="meta"><?= e($descriptions[$key] ?? '') ?></p>
        <?php if (!empty($p['periods']) && $year && !$periods): ?>
          <?= checkbox('make_periods', (int) $p['periods'] === 3 ? t('Also split :year into three terms', ['year' => $year['name']]) : t('Also split :year into four quarters', ['year' => $year['name']]), true, null, '1', 'make-periods-' . $key, true) ?>
        <?php endif; ?>
        <button class="btn btn--secondary btn--sm" type="submit"><?= e(t('Load template')) ?></button>
      </form>
    <?php endforeach; ?>
  </div>
</section>

<section class="section" id="overall" aria-labelledby="overall-title">
  <div class="section__head"><h2 class="section__title" id="overall-title"><?= e(t('Overall grade')) ?></h2></div>
  <?php if (field_error('categories') || field_error('descriptors')): ?>
    <p class="callout callout--coral"><?= icon('circle-alert') ?><span><?= e(field_error('categories') ?? field_error('descriptors')) ?></span></p>
  <?php endif; ?>
  <form method="post" action="<?= e(url('/admin/grading')) ?>" data-unsaved-warning>
    <?= csrf_field() ?>
    <fieldset class="fieldset-plain">
      <legend class="field__label"><?= e(t('How a grade is worked out')) ?></legend>
      <div class="choice-list" style="max-width: 760px">
        <label class="choice"><input type="radio" name="model" value="points"<?= checked(!$weighted) ?>><span><span class="choice__title"><?= e(t('Total points')) ?></span><span class="choice__desc"><?= e(t('Points earned divided by points possible across all graded work.')) ?></span></span></label>
        <label class="choice"><input type="radio" name="model" value="weighted"<?= checked($weighted) ?>><span><span class="choice__title"><?= e(t('Weighted categories')) ?></span><span class="choice__desc"><?= e(t('Each category\'s percentage counts by its weight. Teachers pick a category for each assignment.')) ?></span></span></label>
      </div>
    </fieldset>
    <div class="grid-2" style="margin-top: 20px">
      <fieldset class="fieldset-plain">
        <legend class="field__label"><?= e(t('Categories and their default weights')) ?></legend>
        <table class="table table--compact">
          <thead><tr><th scope="col"><?= e(t('Name')) ?></th><th scope="col" class="num"><?= e(t('Weight %')) ?></th></tr></thead>
          <tbody>
            <?php foreach ($catRows as $i => $c): ?>
              <tr>
                <td><input type="hidden" name="cat_key[]" value="<?= e($c['key']) ?>"><label class="sr-only" for="cat-l-<?= $i ?>"><?= e(t('Category :n name', ['n' => $i + 1])) ?></label><input class="input" id="cat-l-<?= $i ?>" name="cat_label[]" value="<?= e($c['label']) ?>" maxlength="60">
                  <?php if ($c['key'] !== '' && in_array($c['key'], $used, true)): ?><span class="table-sub"><?= e(t('Used by existing assignments')) ?></span><?php endif; ?></td>
                <td class="num"><label class="sr-only" for="cat-w-<?= $i ?>"><?= e(t('Category :n weight', ['n' => $i + 1])) ?></label><input class="input input--num" id="cat-w-<?= $i ?>" name="cat_weight[]" value="<?= e((string) $c['weight']) ?>" inputmode="decimal" size="5"></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <p class="meta"><?= e(t('Weights must add up to 100. Subjects in a group below use the group\'s weights instead. Leave a row empty to remove it.')) ?></p>
      </fieldset>
      <fieldset class="fieldset-plain">
        <legend class="field__label"><?= e(t('Descriptors')) ?></legend>
        <table class="table table--compact">
          <thead><tr><th scope="col" class="num"><?= e(t('From grade')) ?></th><th scope="col"><?= e(t('Label')) ?></th></tr></thead>
          <tbody>
            <?php foreach ($descRows as $i => $d): ?>
              <tr>
                <td class="num"><label class="sr-only" for="desc-m-<?= $i ?>"><?= e(t('Descriptor :n lowest grade', ['n' => $i + 1])) ?></label><input class="input input--num" id="desc-m-<?= $i ?>" name="desc_min[]" value="<?= e($d['min'] === '' ? '' : fmt_number($d['min'], 2)) ?>" inputmode="decimal" size="5"></td>
                <td><label class="sr-only" for="desc-l-<?= $i ?>"><?= e(t('Descriptor :n label', ['n' => $i + 1])) ?></label><input class="input" id="desc-l-<?= $i ?>" name="desc_label[]" value="<?= e($d['label']) ?>" maxlength="60"></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <p class="meta"><?= e(t('Optional words shown next to grades, such as “Very satisfactory”. With transmutation on, they follow the transmuted grade.')) ?></p>
      </fieldset>
    </div>
    <div style="max-width: 280px"><?= field('number', 'passing_percent', t('Passing grade'), ['value' => (string) ($grading['passing_percent'] ?? 75), 'required' => true, 'attrs' => ['min' => 0, 'max' => 100, 'step' => '0.5']]) ?></div>
    <div class="form-actions"><button class="btn btn--primary" type="submit"><?= e(t('Save grading setup')) ?></button></div>
  </form>
</section>

<section class="section" id="groups" aria-labelledby="groups-title">
  <div class="section__head"><h2 class="section__title" id="groups-title"><?= e(t('Weights by subject group')) ?></h2></div>
  <?php if (!$weighted || !$categories): ?>
    <p class="meta"><?= e(t('Subject groups need weighted categories. Choose “Weighted categories” above first.')) ?></p>
  <?php else: ?>
    <p class="section__intro"><?= e(t('Groups of subjects can weigh the categories differently, as DepEd\'s orders do. A subject without a group uses the default weights above.')) ?></p>
    <?php if (field_error('groups')): ?><p class="callout callout--coral"><?= icon('circle-alert') ?><span><?= e(field_error('groups')) ?></span></p><?php endif; ?>
    <?php foreach ($groups as $g): $sum = 0.0; foreach ($categories as $c) { $sum += (float) ($g['weights'][$c['key']] ?? 0); } ?>
      <?php if (abs($sum - 100) > 0.01): ?><p class="callout callout--amber"><?= icon('triangle-alert') ?><span><?= e(t('The weights for :group add up to :sum% with the current categories. Check them below.', ['group' => $g['label'], 'sum' => fmt_number($sum, 2)])) ?></span></p><?php endif; ?>
    <?php endforeach; ?>
    <form method="post" action="<?= e(url('/admin/grading/groups')) ?>" data-unsaved-warning>
      <?= csrf_field() ?>
      <div class="table-frame" style="max-width: 860px">
        <table class="table table--compact">
          <caption class="sr-only"><?= e(t('Subject groups and their weights')) ?></caption>
          <thead><tr><th scope="col"><?= e(t('Group')) ?></th><?php foreach ($categories as $c): ?><th scope="col" class="num" style="white-space: normal"><?= e($c['label']) ?> %</th><?php endforeach; ?></tr></thead>
          <tbody>
            <?php foreach ($groupRows as $i => $g): ?>
              <tr>
                <td><input type="hidden" name="group_key[<?= $i ?>]" value="<?= e((string) $g['key']) ?>"><label class="sr-only" for="grp-l-<?= $i ?>"><?= e(t('Group :n name', ['n' => $i + 1])) ?></label><input class="input input--name" id="grp-l-<?= $i ?>" name="group_label[<?= $i ?>]" value="<?= e((string) $g['label']) ?>" maxlength="80"></td>
                <?php foreach ($categories as $c): $w = $g['weights'][$c['key']] ?? ''; ?>
                  <td class="num"><label class="sr-only" for="grp-w-<?= $i ?>-<?= e($c['key']) ?>"><?= e(t(':category weight for group :n', ['category' => $c['label'], 'n' => $i + 1])) ?></label><input class="input input--num" id="grp-w-<?= $i ?>-<?= e($c['key']) ?>" name="group_weight[<?= $i ?>][<?= e($c['key']) ?>]" value="<?= e($w === '' ? '' : fmt_number((float) $w, 2)) ?>" inputmode="decimal" size="5"></td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="meta"><?= e(t('Each group\'s weights must add up to 100. Leave a row empty to remove it.')) ?></p>
      <?php if ($subjects): ?>
        <fieldset class="fieldset-plain" style="margin-top: 20px; max-width: 640px">
          <legend class="field__label"><?= e(t('Group of each subject')) ?></legend>
          <table class="table table--compact">
            <tbody>
              <?php foreach ($subjects as $s): ?>
                <tr>
                  <th scope="row" style="background: none; white-space: normal"><label for="sg-<?= (int) $s['id'] ?>"><?= e($s['name']) ?></label></th>
                  <td><select class="input select" id="sg-<?= (int) $s['id'] ?>" name="subject_group[<?= (int) $s['id'] ?>]">
                    <option value=""><?= e(t('Default weights')) ?></option>
                    <?php foreach ($groups as $g): ?><option value="<?= e($g['key']) ?>"<?= selected($g['key'], (string) $s['grading_group']) ?>><?= e($g['label']) ?></option><?php endforeach; ?>
                  </select></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <p class="meta"><?= e(t('Save new groups first; they then appear in these lists.')) ?></p>
        </fieldset>
      <?php endif; ?>
      <div class="form-actions"><button class="btn btn--primary" type="submit"><?= e(t('Save subject groups')) ?></button></div>
    </form>
  <?php endif; ?>
</section>

<section class="section" id="transmutation" aria-labelledby="tr-title">
  <div class="section__head"><h2 class="section__title" id="tr-title"><?= e(t('Transmutation table')) ?></h2>
    <span><?= $on ? badge(t('On'), 'leaf', 'circle-check') : (!empty($table['confirmed']) ? badge(t('Checked, switched off'), 'outline') : badge(t('Off'), 'neutral')) ?></span></div>
  <p class="section__intro"><?= e(t('A transmutation table turns the initial grade of a grading period into the grade reported for it. Sibol uses a table only after someone has checked every line against the official order and switched it on. The final grade is then the average of the period grades, as a whole number.')) ?></p>
  <?php if (!empty($table['confirmed']) && $checker): ?>
    <p class="meta"><?= e(t('Checked by :name on :date.', ['name' => user_name($checker), 'date' => fmt_date((string) ($table['checked_at'] ?? ''), 'date')])) ?></p>
  <?php endif; ?>
  <div class="layout-split">
    <form method="post" action="<?= e(url('/admin/grading/transmutation')) ?>" data-unsaved-warning>
      <?= csrf_field() ?>
      <?= field('textarea', 'rows', t('Table'), ['value' => Transmutation::format($rows), 'rows' => 14, 'class' => 'code-text', 'hint' => t('One row per line: the lowest initial grade of the row, then the grade to report, for example “60.00 75”. You can paste two columns from a spreadsheet or the ranges from the order, such as “60.00 – 61.59 75”.'), 'attrs' => ['spellcheck' => 'false']]) ?>
      <?= checkbox('confirmed', t('I have checked every line against the official order'), false, !empty($table['confirmed']) ? t('Already checked. Tick again only after changing the table.') : t('Needed before the table can be switched on, and again after any change.')) ?>
      <?php if (field_error('confirmed')): ?><p class="field__error"><?= icon('circle-alert', 'icon--xs') ?><?= e(field_error('confirmed')) ?></p><?php endif; ?>
      <?= checkbox('enabled', t('Use this table for grading period grades'), !empty($table['enabled'])) ?>
      <div class="form-actions"><button class="btn btn--primary" type="submit"><?= e(t('Save table')) ?></button></div>
    </form>
    <aside>
      <?php if ($rows): ?>
        <div class="table-frame">
          <table class="table table--compact">
            <caption><?= e(t('As Sibol reads it')) ?></caption>
            <thead><tr><th scope="col"><?= e(t('Initial grade')) ?></th><th scope="col" class="num"><?= e(t('Reported grade')) ?></th></tr></thead>
            <tbody>
              <?php // Two decimals, as the orders print them, so each line can be compared with the official table.
              $two = static fn (float $n): string => number_format($n, 2, '.', '');
              foreach ($rows as $i => $r): $upper = $i > 0 ? round((float) $rows[$i - 1]['min'] - 0.01, 2) : null; ?>
                <tr><td class="num" style="text-align:left"><?= e($two((float) $r['min'])) ?><?= $upper !== null && $upper > (float) $r['min'] ? ' – ' . e($two($upper)) : ($upper === null ? ' ' . e(t('and above')) : '') ?></td><td class="num"><?= e(fmt_number((float) $r['grade'], 2)) ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="meta"><?= e(t('No table yet. Grades are reported as initial grades.')) ?></p>
      <?php endif; ?>
    </aside>
  </div>
</section>
