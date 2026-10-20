<?php
/**
 * @var array $class @var array $book @var list<array> $classes
 * @var string $show 'all', 'final' or a grading period id (only when the class's school year has grading periods)
 */
$grading = $book['grading'];
$weighted = ($grading['model'] ?? 'points') === 'weighted';
$periods = $book['periods'];
$transmuted = $book['transmuted'];
$cats = [];
foreach ((array) ($grading['categories'] ?? []) as $c) {
    $cats[$c['key']] = $c['label'];
}
$periodNames = array_column($periods, 'name', 'id');
$current = ctype_digit($show) ? (int) $show : null;
$assignments = $current !== null ? array_values(array_filter($book['assignments'], static fn (array $a): bool => ($a['period_id'] ?? null) === $current)) : $book['assignments'];
$unplaced = $periods ? array_values(array_filter($book['assignments'], static fn (array $a): bool => ($a['period_id'] ?? null) === null)) : [];
$base = '/teacher/gradebook/' . (int) $class['id'];
$gradeText = static function (?array $g) use ($transmuted): string {
    if (!$g || $g['grade'] === null) {
        return '—';
    }
    return $transmuted ? fmt_number($g['grade'], 0) : fmt_number($g['grade'], 1) . '%';
};
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e($class['name']) ?></h1>
    <p class="page-lead"><?= e($weighted ? ($grading['group'] ?? null ? t('Marks use your school\'s weights for :group.', ['group' => $grading['group']]) : t('Overall marks use your school\'s category weights.')) : t('Overall marks are points earned out of points possible.')) ?> <?= e(t('Unreleased grades are visible only to you.')) ?></p>
  </div>
  <div class="page-actions">
    <?php if (count($classes) > 1): ?>
      <div class="cluster">
        <label class="sr-only" for="gb-class"><?= e(t('Class')) ?></label>
        <select class="input select" id="gb-class" data-goto>
          <?php foreach ($classes as $c): ?><option value="<?= e(url('/teacher/gradebook/' . (int) $c['id'])) ?>"<?= selected($c['id'], $class['id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
    <?php endif; ?>
    <a class="btn btn--secondary" href="<?= e(url($base . '/export')) ?>"><?= icon('download', 'icon--sm') ?><?= e(t('Export CSV')) ?></a>
  </div>
</div>

<?php if ($periods): ?>
  <nav class="tabs" aria-label="<?= e(t('Grading periods')) ?>">
    <?php foreach ($periods as $p): ?>
      <a class="tab" href="<?= e(url($base, ['period' => (int) $p['id']])) ?>"<?= $show === (string) $p['id'] ? ' aria-current="page"' : '' ?>><?= e($p['name']) ?></a>
    <?php endforeach; ?>
    <a class="tab" href="<?= e(url($base, ['period' => 'final'])) ?>"<?= $show === 'final' ? ' aria-current="page"' : '' ?>><?= e(t('Final grades')) ?></a>
    <a class="tab" href="<?= e(url($base, ['period' => 'all'])) ?>"<?= $show === 'all' ? ' aria-current="page"' : '' ?>><?= e(t('All work')) ?></a>
  </nav>
  <?php if ($unplaced && $show !== 'final'): ?>
    <p class="callout callout--amber"><?= icon('triangle-alert') ?><span><?= e(tp(':count piece of work is due outside every grading period, so it counts in none:', ':count pieces of work are due outside every grading period, so they count in none:', count($unplaced))) ?>
      <?php foreach ($unplaced as $i => $a): ?><?= $i ? ', ' : ' ' ?><a href="<?= e(url('/teacher/assignments/' . (int) $a['id'] . '/edit')) ?>"><?= e(str_limit($a['title'], 50)) ?></a><?php endforeach; ?>.
      <?= e(t('Choose a grading period on each, or ask your administrator to adjust the periods.')) ?></span></p>
  <?php endif; ?>
<?php endif; ?>

<?php if (!$book['students'] || !$book['assignments']): ?>
  <?= partial('empty', ['title' => !$book['students'] ? t('No students yet') : t('No published work yet'), 'body' => t('The gradebook fills in as students submit published work and you grade it.')]) ?>
<?php elseif ($show === 'final'): ?>
  <div class="table-frame">
    <table class="table gradebook">
      <caption class="sr-only"><?= e(t('Final grades for :class', ['class' => $class['name']])) ?></caption>
      <thead>
        <tr>
          <th scope="col"><?= e(t('Student')) ?></th>
          <?php foreach ($periods as $p): ?><th scope="col" class="num"><?= e($p['name']) ?></th><?php endforeach; ?>
          <th scope="col" class="num"><?= e(t('Final grade')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($book['students'] as $s): $f = $book['final'][(int) $s['id']] ?? null; ?>
          <tr>
            <th scope="row" style="font-weight:600; text-align:left; background: var(--paper-raised)"><?= e($s['family_name'] . ', ' . (($s['preferred_name'] ?? '') ?: $s['given_name'])) ?></th>
            <?php foreach ($periods as $p): ?><td class="num"><?= e($gradeText($book['by_period'][(int) $s['id']][(int) $p['id']] ?? null)) ?></td><?php endforeach; ?>
            <td class="num nowrap"><strong><?= $f && $f['grade'] !== null ? e($transmuted ? fmt_number($f['grade'], 0) : fmt_number($f['grade'], 1) . '%') : '—' ?></strong>
              <?php if ($f && $f['grade'] !== null): ?><span class="table-sub"><?= e($f['complete'] ? ($f['descriptor'] ?? '') : tp('so far, from :count period', 'so far, from :count periods', (int) $f['periods'])) ?></span><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="meta" style="margin-top:12px"><?= e($transmuted
      ? t('Each period grade is transmuted with your school\'s table. The final grade is their average, as a whole number.')
      : t('The final grade is the average of the period grades. Your school has not switched a transmutation table on.')) ?></p>
<?php else: ?>
  <div class="table-frame">
    <table class="table gradebook">
      <caption class="sr-only"><?= e($current !== null ? t('Gradebook for :class, :period', ['class' => $class['name'], 'period' => $periodNames[$current] ?? '']) : t('Gradebook for :class', ['class' => $class['name']])) ?></caption>
      <thead>
        <tr>
          <th scope="col"><?= e(t('Student')) ?></th>
          <?php foreach ($assignments as $a): ?>
            <th scope="col" class="assignment-col num"><a href="<?= e(url('/teacher/assignments/' . (int) $a['id'] . '/submissions')) ?>" style="color:inherit"><?= e(str_limit($a['title'], 40)) ?></a>
              <span class="table-sub"><?= e(fmt_date($a['due_at'], 'date_short')) ?>, /<?= e(fmt_number($a['points_possible'], 2)) ?><?= $a['category'] && isset($cats[$a['category']]) ? ', ' . e($cats[$a['category']]) : '' ?><?= $current === null && $periods ? ', ' . e($periodNames[$a['period_id'] ?? 0] ?? t('no period')) : '' ?></span></th>
          <?php endforeach; ?>
          <?php if ($current !== null && $transmuted): ?><th scope="col" class="num"><?= e(t('Initial grade')) ?></th><?php endif; ?>
          <th scope="col" class="num"><?= e($current !== null ? t(':period grade', ['period' => $periodNames[$current] ?? '']) : t('Overall')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($book['students'] as $s): $o = $current !== null ? ($book['by_period'][(int) $s['id']][$current] ?? null) : $book['overall'][(int) $s['id']]; ?>
          <tr>
            <th scope="row" style="font-weight:600; text-align:left; background: var(--paper-raised)"><?= e($s['family_name'] . ', ' . (($s['preferred_name'] ?? '') ?: $s['given_name'])) ?></th>
            <?php foreach ($assignments as $a): $c = $book['cells'][(int) $s['id']][(int) $a['id']];
                // Paper scores open the score grid at the student's row; online work opens the submission.
                $cellUrl = $c['paper'] ? url('/teacher/assignments/' . (int) $a['id'] . '/scores') . '#student-' . (int) $s['id'] : url('/teacher/submissions/' . (int) $c['submission_id']); ?>
              <td class="num">
                <?php if ($c['state'] === 'graded'): ?>
                  <a href="<?= e($cellUrl) ?>" style="color:inherit; text-decoration:none"><?= e(fmt_number($c['score'], 2)) ?></a><?= !$c['released'] ? '<span class="cell-pending" title="' . e(t('Not released')) . '"> •</span>' : '' ?><?= $c['late'] ? '<span class="table-sub">' . e(t('late')) . '</span>' : '' ?><?= $c['paper'] ? '<span class="table-sub">' . e(t('on paper')) . '</span>' : '' ?>
                <?php elseif ($c['state'] === 'pending'): ?>
                  <a class="cell-pending" href="<?= e($cellUrl) ?>"><?= e(t('to grade')) ?></a>
                <?php elseif ($c['state'] === 'returned'): ?><span class="meta"><?= e(t('returned')) ?></span>
                <?php elseif ($c['state'] === 'missing'): ?><span class="cell-missing"><?= e(t('missing')) ?></span>
                <?php elseif ($c['state'] === 'absent'): ?><a class="cell-missing" href="<?= e(url('/teacher/assignments/' . (int) $a['id'] . '/scores') . '#student-' . (int) $s['id']) ?>"><?= e(t('absent')) ?></a>
                <?php elseif ($c['state'] === 'excused'): ?><span class="meta"><?= e(t('excused')) ?></span>
                <?php elseif ($c['state'] === 'unrecorded'): ?><a class="meta" href="<?= e(url('/teacher/assignments/' . (int) $a['id'] . '/scores') . '#student-' . (int) $s['id']) ?>" title="<?= e(t('No score entered yet')) ?>">—<span class="sr-only"><?= e(t('No score entered yet')) ?></span></a>
                <?php else: ?><span class="meta">—</span><?php endif; ?>
              </td>
            <?php endforeach; ?>
            <?php if ($current !== null && $transmuted): ?><td class="num"><?= $o && $o['initial'] !== null ? e(number_format((float) $o['initial'], 2)) : '—' ?></td><?php endif; ?>
            <td class="num nowrap"><strong><?= e($current !== null ? $gradeText($o) : ($o['percent'] !== null ? fmt_number($o['percent'], 1) . '%' : '—')) ?></strong><?php if ($o && $o['descriptor']): ?><span class="table-sub"><?= e($o['descriptor']) ?></span><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="meta" style="margin-top:12px"><?= e(t('• marks a grade you have not released yet. Missing work is shown but not counted as zero, and excused work is not counted at all. Click a score to open the submission.')) ?>
    <?= e(t('Work done in class or on paper never shows as missing: until you enter its score it shows —, and a student marked absent counts as missing.')) ?>
    <?php if ($current !== null): ?><?= e($transmuted ? t('The period grade is the initial grade transmuted with your school\'s table.') : t('The period grade is worked out from this period\'s work only.')) ?><?php endif; ?></p>
<?php endif; ?>
