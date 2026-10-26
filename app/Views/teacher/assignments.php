<?php /** @var bool $quizzes @var list<array> $rows @var list<array> $classes @var int|null $classId @var string $kind @var string $status */
// The same page lists quizzes (/teacher/quizzes) or assignments and work in class (/teacher/assignments).
$path = $quizzes ? '/teacher/quizzes' : '/teacher/assignments';
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e($quizzes ? t('Quizzes') : t('Assignments')) ?></h1>
    <?php if ($quizzes): ?>
      <p class="page-lead"><?= e(t('Quizzes students take online. Drafts stay private until you publish. Students see a published quiz from its opening date.')) ?></p>
    <?php else: ?>
      <p class="page-lead"><?= e(t('Work students hand in online, and work done in class or on paper. Drafts stay private until you publish.')) ?> <a href="<?= e(url('/teacher/quizzes', ['class_id' => $classId])) ?>"><?= e(t('Quizzes are on their own page.')) ?></a></p>
    <?php endif; ?>
  </div>
  <div class="page-actions">
    <?php if ($quizzes): ?>
      <a class="btn btn--primary" href="<?= e(url('/teacher/assignments/new', ['kind' => 'quiz', 'class_id' => $classId])) ?>"><?= icon('plus', 'icon--sm') ?><?= e(t('New quiz')) ?></a>
    <?php else: ?>
      <a class="btn btn--primary" href="<?= e(url('/teacher/assignments/new', ['kind' => 'assignment', 'class_id' => $classId])) ?>"><?= icon('plus', 'icon--sm') ?><?= e(t('New assignment')) ?></a>
      <a class="btn btn--secondary" href="<?= e(url('/teacher/assignments/new', ['kind' => 'paper', 'class_id' => $classId])) ?>"><?= icon('clipboard-pen', 'icon--sm') ?><?= e(t('In class or on paper')) ?></a>
    <?php endif; ?>
  </div>
</div>
<form class="toolbar" method="get" action="<?= e(url($path)) ?>">
  <div class="field"><label class="field__label" for="a-class"><?= e(t('Class')) ?></label>
    <select class="input select" id="a-class" name="class_id" data-autosubmit><option value=""><?= e(t('All classes')) ?></option><?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected($c['id'], $classId) ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
  <?php if (!$quizzes): ?>
    <div class="field"><label class="field__label" for="a-kind"><?= e(t('Type')) ?></label>
      <select class="input select" id="a-kind" name="kind" data-autosubmit><option value=""><?= e(t('All types')) ?></option><option value="assignment"<?= selected('assignment', $kind) ?>><?= e(t('Assignments')) ?></option><option value="paper"<?= selected('paper', $kind) ?>><?= e(t('In class and on paper')) ?></option></select></div>
  <?php endif; ?>
  <div class="field"><label class="field__label" for="a-status"><?= e(t('Status')) ?></label>
    <select class="input select" id="a-status" name="status" data-autosubmit><option value=""><?= e(t('All except archived')) ?></option><?php foreach (['draft' => t('Drafts'), 'published' => t('Published'), 'closed' => t('Closed'), 'archived' => t('Archived')] as $v => $l): ?><option value="<?= e($v) ?>"<?= selected($v, $status) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <noscript><button class="btn btn--secondary" type="submit"><?= e(t('Apply')) ?></button></noscript>
</form>
<?php if (!$rows): ?>
  <?php if ($quizzes): ?>
    <?= partial('empty', ['title' => t('No quizzes yet'), 'body' => t('Create a quiz. You can write the questions yourself or draft them from your class materials.'), 'action' => [t('New quiz'), '/teacher/assignments/new?kind=quiz']]) ?>
  <?php else: ?>
    <?= partial('empty', ['title' => t('Nothing here yet'), 'body' => t('Create an assignment, or record work done in class or on paper.'), 'action' => [t('New assignment'), '/teacher/assignments/new']]) ?>
  <?php endif; ?>
<?php else: ?>
  <div class="table-frame">
    <table class="table table--stack">
      <caption class="sr-only"><?= e($quizzes ? t('Quizzes') : t('Assignments and work in class')) ?></caption>
      <thead><tr><th scope="col"><?= e(t('Title')) ?></th><th scope="col"><?= e(t('Status')) ?></th><th scope="col"><?= e(t('Due')) ?></th><th scope="col"><?= e(t('Handed in')) ?></th><th scope="col"><?= e(t('To do')) ?></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): [$sl, $st] = assignment_status($r); ?>
          <tr>
            <td class="cell-primary"><a class="row__title" href="<?= e(url('/teacher/assignments/' . (int) $r['id'] . ($r['status'] === 'draft' ? '/edit' : ''))) ?>"><?= e($r['title']) ?></a><span class="table-sub"><?= kind_tag((string) $r['kind']) ?> <?= e($r['class_name']) ?></span></td>
            <td data-label="<?= e(t('Status')) ?>"><?= badge($sl, $st) ?></td>
            <td data-label="<?= e(t('Due')) ?>"><?= $r['due_at'] ? e(fmt_date($r['due_at'], 'datetime_short')) : '<span class="meta">' . e(t('Not set')) . '</span>' ?></td>
            <td data-label="<?= e(t('Handed in')) ?>"><?= $r['status'] === 'draft' ? '<span class="meta">—</span>' : partial('meter', ['value' => percent($r['submitted'], $r['enrolled']), 'label' => (int) $r['submitted'] . ' / ' . (int) $r['enrolled']]) ?></td>
            <td data-label="<?= e(t('To do')) ?>">
              <?php if ($r['kind'] === 'paper' && $r['status'] !== 'draft' && (int) $r['submitted'] < (int) $r['enrolled'] && $r['due_at'] !== null && $r['due_at'] < \Sibol\Core\Db::now()): ?><a href="<?= e(url('/teacher/assignments/' . (int) $r['id'] . '/scores')) ?>"><?= badge(tp(':count score to enter', ':count scores to enter', (int) $r['enrolled'] - (int) $r['submitted']), 'amber') ?></a>
              <?php elseif ((int) $r['ungraded'] > 0): ?><a href="<?= e(url('/teacher/assignments/' . (int) $r['id'] . '/submissions')) ?>"><?= badge(tp(':count to grade', ':count to grade', (int) $r['ungraded']), 'amber') ?></a>
              <?php elseif ((int) $r['unreleased'] > 0): ?><a href="<?= e(url('/teacher/assignments/' . (int) $r['id'] . '/submissions')) ?>"><?= badge(tp(':count to release', ':count to release', (int) $r['unreleased']), 'amber') ?></a>
              <?php elseif ((int) $r['unreviewed'] > 0): ?><?= badge(tp(':count draft question to review', ':count draft questions to review', (int) $r['unreviewed']), 'amber') ?>
              <?php else: ?><span class="meta">—</span><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
