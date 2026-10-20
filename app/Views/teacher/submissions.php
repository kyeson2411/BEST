<?php
/**
 * @var array $a @var list<array> $rows @var array $counts @var string $filter
 * @var array<int,string> $students @var array<int,array> $overrides @var int|null $selected
 */
$labels = [
    'to_grade' => [t('To grade'), 'amber'], 'graded' => [t('Graded, not released'), 'amber'], 'released' => [t('Released'), 'leaf'],
    'returned' => [t('Returned'), 'neutral'], 'in_progress' => [t('Working on it'), 'neutral'], 'not_started' => [t('Not started'), 'neutral'], 'missing' => [t('Missing'), 'coral'],
    'absent' => [t('Absent'), 'coral'], 'excused' => [t('Excused'), 'neutral'],
];
$base = '/teacher/assignments/' . (int) $a['id'];
$timed = $a['kind'] === 'quiz' && (int) $a['time_limit_minutes'] > 0;
/** Short description of one student's adjustments, for badges and the list. @return list<string> */
$describe = static function (array $ov, bool $withExcused = true) use ($timed): array {
    $out = [];
    if ($withExcused && (int) $ov['excused'] === 1) {
        $out[] = t('Excused');
    }
    if ($ov['due_at'] !== null) {
        $out[] = t('Due :date', ['date' => fmt_date($ov['due_at'], 'datetime_short')]);
    }
    if ($timed && (int) $ov['extra_minutes'] > 0) {
        $out[] = tp('+:count minute', '+:count minutes', (int) $ov['extra_minutes']);
    }
    return $out;
};
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Submissions')) ?></h1>
    <p class="page-lead"><?= e($a['title']) ?>, <?= e($a['class_name']) ?>. <?= e(\Sibol\Core\Dates::due($a['due_at'])['label']) ?>.</p>
  </div>
  <div class="page-actions">
    <?php if (!empty($counts['graded'])): ?>
      <form method="post" action="<?= e(url($base . '/release-all')) ?>" data-confirm="<?= e(tp(':count student will see their grade and feedback now.', ':count students will see their grades and feedback now.', (int) $counts['graded'])) ?>" data-confirm-title="<?= e(t('Release all graded work?')) ?>" data-confirm-action="<?= e(t('Release')) ?>">
        <?= csrf_field() ?><button class="btn btn--primary" type="submit"><?= icon('send', 'icon--sm') ?><?= e(tp('Release :count graded', 'Release :count graded', (int) $counts['graded'])) ?></button></form>
    <?php endif; ?>
    <a class="btn btn--secondary" href="<?= e(url('/teacher/gradebook/' . (int) $a['class_id'])) ?>"><?= icon('table', 'icon--sm') ?><?= e(t('Gradebook')) ?></a>
  </div>
</div>
<nav class="segmented" aria-label="<?= e(t('Filter by status')) ?>" style="margin-bottom: 16px">
  <a href="<?= e(url($base . '/submissions')) ?>"<?= $filter === '' ? ' aria-current="page"' : '' ?>><?= e(t('All')) ?> (<?= array_sum($counts) ?>)</a>
  <?php foreach ($labels as $key => [$label]): if (empty($counts[$key])) { continue; } ?>
    <a href="<?= e(url($base . '/submissions', ['filter' => $key])) ?>"<?= $filter === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?> (<?= (int) $counts[$key] ?>)</a>
  <?php endforeach; ?>
</nav>
<?php if (!$rows): ?>
  <?= partial('empty', ['compact' => true, 'title' => t('No students in this view'), 'body' => t('Choose another filter.')]) ?>
<?php else: ?>
  <div class="table-frame">
    <table class="table table--stack">
      <caption class="sr-only"><?= e(t('Submissions for :title', ['title' => $a['title']])) ?></caption>
      <thead><tr><th scope="col"><?= e(t('Student')) ?></th><th scope="col"><?= e(t('Status')) ?></th><th scope="col"><?= e(t('Submitted')) ?></th><th scope="col" class="num"><?= e(t('Score')) ?></th><th scope="col" class="actions"><span class="sr-only"><?= e(t('Open')) ?></span></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): [$l, $tone] = $labels[$r['state']]; ?>
          <tr>
            <td class="cell-primary"><?= e(user_name($r)) ?><?php if ((int) $r['attempts'] > 1): ?><span class="table-sub"><?= e(t(':n attempts, best shown', ['n' => (int) $r['attempts']])) ?></span><?php endif; ?></td>
            <td data-label="<?= e(t('Status')) ?>"><span class="cluster" style="gap: 4px"><?= badge($l, $tone) ?>
              <?php if ($r['override']): foreach ($describe($r['override'], false) as $note): ?><?= badge($note, 'outline') ?><?php endforeach; endif; ?></span></td>
            <td data-label="<?= e(t('Submitted')) ?>"><?= $r['submitted_at'] ? e(fmt_date($r['submitted_at'], 'datetime_short')) . ((int) $r['is_late'] ? ' ' . badge(t('Late'), 'amber') : '') : '<span class="meta">—</span>' ?></td>
            <td class="num" data-label="<?= e(t('Score')) ?>"><?= $r['score'] !== null ? e(fmt_number($r['score'], 2)) . ' / ' . e(fmt_number($r['points_possible'], 2)) : '<span class="meta">—</span>' ?></td>
            <td class="actions">
              <?php if ($r['id'] !== null && $r['status'] !== 'in_progress'): ?><a class="btn btn--<?= $r['state'] === 'to_grade' ? 'primary' : 'secondary' ?> btn--sm" href="<?= e(url('/teacher/submissions/' . (int) $r['id'])) ?>"><?= e($r['state'] === 'to_grade' ? t('Grade') : t('Open')) ?><span class="sr-only">: <?= e(user_name($r)) ?></span></a><?php endif; ?>
              <a class="btn btn--quiet btn--sm" href="<?= e(url($base . '/submissions', ['student' => (int) $r['student_id'], 'filter' => $filter]) . '#adjust') ?>"><?= e(t('Adjust')) ?><span class="sr-only">: <?= e(user_name($r)) ?></span></a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?= partial('override_form', ['a' => $a, 'students' => $students, 'overrides' => $overrides, 'selected' => $selected, 'page' => 'submissions']) ?>
