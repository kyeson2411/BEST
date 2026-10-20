<?php /** @var array $class @var array $book @var list<array> $classes */
$grading = $book['grading'];
$weighted = ($grading['model'] ?? 'points') === 'weighted';
$cats = [];
foreach ((array) ($grading['categories'] ?? []) as $c) {
    $cats[$c['key']] = $c['label'];
}
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e($class['name']) ?></h1>
    <p class="page-lead"><?= e($weighted ? t('Overall marks use your school\'s category weights.') : t('Overall marks are points earned out of points possible.')) ?> <?= e(t('Unreleased grades are visible only to you.')) ?></p>
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
    <a class="btn btn--secondary" href="<?= e(url('/teacher/gradebook/' . (int) $class['id'] . '/export')) ?>"><?= icon('download', 'icon--sm') ?><?= e(t('Export CSV')) ?></a>
  </div>
</div>
<?php if (!$book['students'] || !$book['assignments']): ?>
  <?= partial('empty', ['title' => !$book['students'] ? t('No students yet') : t('No published work yet'), 'body' => t('The gradebook fills in as students submit published work and you grade it.')]) ?>
<?php else: ?>
  <div class="table-frame">
    <table class="table gradebook">
      <caption class="sr-only"><?= e(t('Gradebook for :class', ['class' => $class['name']])) ?></caption>
      <thead>
        <tr>
          <th scope="col"><?= e(t('Student')) ?></th>
          <?php foreach ($book['assignments'] as $a): ?>
            <th scope="col" class="assignment-col num"><a href="<?= e(url('/teacher/assignments/' . (int) $a['id'] . '/submissions')) ?>" style="color:inherit"><?= e(str_limit($a['title'], 40)) ?></a>
              <span class="table-sub"><?= e(fmt_date($a['due_at'], 'date_short')) ?>, /<?= e(fmt_number($a['points_possible'], 2)) ?><?= $a['category'] && isset($cats[$a['category']]) ? ', ' . e($cats[$a['category']]) : '' ?></span></th>
          <?php endforeach; ?>
          <th scope="col" class="num"><?= e(t('Overall')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($book['students'] as $s): $o = $book['overall'][(int) $s['id']]; ?>
          <tr>
            <th scope="row" style="font-weight:600; text-align:left; background: var(--paper-raised)"><?= e($s['family_name'] . ', ' . (($s['preferred_name'] ?? '') ?: $s['given_name'])) ?></th>
            <?php foreach ($book['assignments'] as $a): $c = $book['cells'][(int) $s['id']][(int) $a['id']];
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
            <td class="num"><strong><?= $o['percent'] !== null ? e(fmt_number($o['percent'], 1)) . '%' : '—' ?></strong><?php if ($o['descriptor']): ?><span class="table-sub"><?= e($o['descriptor']) ?></span><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="meta" style="margin-top:12px"><?= e(t('• marks a grade you have not released yet. Missing work is shown but not counted as zero, and excused work is not counted at all. Click a score to open the submission.')) ?>
    <?= e(t('Work done in class or on paper never shows as missing: until you enter its score it shows —, and a student marked absent counts as missing.')) ?></p>
<?php endif; ?>
