<?php
/**
 * @var array $a @var list<array> $rows @var array $counts @var string $filter
 * @var array<int,string> $students @var array<int,array> $overrides @var int|null $selected
 */
$labels = [
    'to_grade' => [t('To grade'), 'amber'], 'graded' => [t('Graded, not released'), 'amber'], 'released' => [t('Released'), 'leaf'],
    'returned' => [t('Returned'), 'neutral'], 'in_progress' => [t('Working on it'), 'neutral'], 'not_started' => [t('Not started'), 'neutral'], 'missing' => [t('Missing'), 'coral'],
    'excused' => [t('Excused'), 'neutral'],
];
$base = '/teacher/assignments/' . (int) $a['id'];
$timed = $a['kind'] === 'quiz' && (int) $a['time_limit_minutes'] > 0;
$current = $selected !== null ? ($overrides[$selected] ?? null) : null;
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

<section class="section" id="adjust" aria-labelledby="adjust-title" style="max-width: 760px; margin-top: 32px">
  <div class="section__head"><h2 class="section__title" id="adjust-title"><?= e(t('Extensions and excused work')) ?></h2></div>
  <p class="meta"><?= e($timed
      ? t('Give one student their own due date or extra minutes on the time limit, or excuse them. They see their own dates everywhere; excused work is not counted and never shows as missing. No reason is recorded.')
      : t('Give one student their own due date, or excuse them. They see their own date everywhere; excused work is not counted and never shows as missing. No reason is recorded.')) ?></p>
  <?php if ($a['answer_key_release'] === 'after_due' && $a['kind'] === 'quiz'): ?>
    <p class="meta"><?= e(t('Correct answers wait until the latest extension has passed, so no one sees them before taking the quiz.')) ?></p>
  <?php endif; ?>
  <form method="post" action="<?= e(url($base . '/overrides')) ?>" class="panel">
    <?= csrf_field() ?>
    <div class="form-grid">
      <?= field('select', 'student_id', t('Student'), ['options' => ['' => t('Choose a student')] + $students, 'value' => (string) ($selected ?? ''), 'required' => true]) ?>
      <?= field('datetime-local', 'due_at', t('Their own due date'), ['value' => $current && $current['due_at'] ? fmt_date($current['due_at'], 'input') : '', 'optional_label' => true,
          'hint' => $a['due_at'] ? t('Leave empty to keep the class due date, :date.', ['date' => fmt_date($a['due_at'], 'datetime_short')]) : t('Leave empty to keep no due date.')]) ?>
      <?php if ($timed): ?>
        <?= field('number', 'extra_minutes', t('Extra minutes'), ['value' => (string) ($current['extra_minutes'] ?? '0'), 'attrs' => ['min' => 0, 'max' => \Sibol\Services\Overrides::MAX_EXTRA_MINUTES],
            'hint' => t('Added to the :n-minute time limit, including an attempt already under way.', ['n' => (int) $a['time_limit_minutes']])]) ?>
      <?php endif; ?>
    </div>
    <?= checkbox('excused', t('Excuse this student from this work'), (bool) ($current['excused'] ?? false), t('It is not counted in their grade and never shows as missing.')) ?>
    <button class="btn btn--primary btn--sm" type="submit"><?= e(t('Save for this student')) ?></button>
  </form>
  <?php if ($overrides): ?>
    <h3 class="section__title" style="margin-top: 24px" id="current-adjust"><?= e(t('Current adjustments')) ?></h3>
    <ul class="rows" aria-labelledby="current-adjust">
      <?php foreach ($overrides as $studentId => $ov): ?>
        <li class="row">
          <div class="row__main">
            <span class="row__title"><?= e($students[$studentId]) ?></span>
            <div class="row__meta"><?php foreach ($describe($ov) as $note): ?><span><?= e($note) ?></span><?php endforeach; ?></div>
          </div>
          <div class="row__aside">
            <a class="btn btn--quiet btn--sm" href="<?= e(url($base . '/submissions', ['student' => $studentId]) . '#adjust') ?>"><?= e(t('Change')) ?><span class="sr-only">: <?= e($students[$studentId]) ?></span></a>
            <form method="post" action="<?= e(url($base . '/overrides/' . (int) $studentId . '/remove')) ?>"><?= csrf_field() ?><button class="btn btn--danger-quiet btn--sm" type="submit"><?= e(t('Remove')) ?><span class="sr-only">: <?= e($students[$studentId]) ?></span></button></form>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
