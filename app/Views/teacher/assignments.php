<?php /** @var list<array> $rows @var list<array> $classes @var int|null $classId @var string $kind @var string $status */ ?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e($kind === 'quiz' ? t('Quizzes') : t('Assignments')) ?></h1>
    <p class="page-lead"><?= e(t('Drafts stay private until you publish. Students see published work from its opening date.')) ?></p>
  </div>
  <div class="page-actions">
    <a class="btn btn--primary" href="<?= e(url('/teacher/assignments/new', ['kind' => 'assignment', 'class_id' => $classId])) ?>"><?= icon('plus', 'icon--sm') ?><?= e(t('New assignment')) ?></a>
    <a class="btn btn--secondary" href="<?= e(url('/teacher/assignments/new', ['kind' => 'quiz', 'class_id' => $classId])) ?>"><?= icon('list-checks', 'icon--sm') ?><?= e(t('New quiz')) ?></a>
  </div>
</div>
<form class="toolbar" method="get" action="<?= e(url('/teacher/assignments')) ?>">
  <div class="field"><label class="field__label" for="a-class"><?= e(t('Class')) ?></label>
    <select class="input select" id="a-class" name="class_id" data-autosubmit><option value=""><?= e(t('All classes')) ?></option><?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected($c['id'], $classId) ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label class="field__label" for="a-kind"><?= e(t('Type')) ?></label>
    <select class="input select" id="a-kind" name="kind" data-autosubmit><option value=""><?= e(t('All types')) ?></option><option value="assignment"<?= selected('assignment', $kind) ?>><?= e(t('Assignments')) ?></option><option value="quiz"<?= selected('quiz', $kind) ?>><?= e(t('Quizzes')) ?></option></select></div>
  <div class="field"><label class="field__label" for="a-status"><?= e(t('Status')) ?></label>
    <select class="input select" id="a-status" name="status" data-autosubmit><option value=""><?= e(t('All except archived')) ?></option><?php foreach (['draft' => t('Drafts'), 'published' => t('Published'), 'closed' => t('Closed'), 'archived' => t('Archived')] as $v => $l): ?><option value="<?= e($v) ?>"<?= selected($v, $status) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <noscript><button class="btn btn--secondary" type="submit"><?= e(t('Apply')) ?></button></noscript>
</form>
<?php if (!$rows): ?>
  <?= partial('empty', ['title' => t('Nothing here yet'), 'body' => t('Create an assignment or a quiz. You can draft quiz questions from your class materials.'), 'action' => [t('New assignment'), '/teacher/assignments/new']]) ?>
<?php else: ?>
  <div class="table-frame">
    <table class="table table--stack">
      <caption class="sr-only"><?= e(t('Assignments and quizzes')) ?></caption>
      <thead><tr><th scope="col"><?= e(t('Title')) ?></th><th scope="col"><?= e(t('Status')) ?></th><th scope="col"><?= e(t('Due')) ?></th><th scope="col"><?= e(t('Handed in')) ?></th><th scope="col"><?= e(t('To do')) ?></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): [$sl, $st] = assignment_status($r); ?>
          <tr>
            <td class="cell-primary"><a class="row__title" href="<?= e(url('/teacher/assignments/' . (int) $r['id'] . ($r['status'] === 'draft' ? '/edit' : ''))) ?>"><?= icon($r['kind'] === 'quiz' ? 'list-checks' : 'clipboard-list', 'icon--sm') ?> <?= e($r['title']) ?></a><span class="table-sub"><?= e($r['class_name']) ?></span></td>
            <td data-label="<?= e(t('Status')) ?>"><?= badge($sl, $st) ?></td>
            <td data-label="<?= e(t('Due')) ?>"><?= $r['due_at'] ? e(fmt_date($r['due_at'], 'datetime_short')) : '<span class="meta">' . e(t('Not set')) . '</span>' ?></td>
            <td data-label="<?= e(t('Handed in')) ?>"><?= $r['status'] === 'draft' ? '<span class="meta">—</span>' : partial('meter', ['value' => percent($r['submitted'], $r['enrolled']), 'label' => (int) $r['submitted'] . ' / ' . (int) $r['enrolled']]) ?></td>
            <td data-label="<?= e(t('To do')) ?>">
              <?php if ((int) $r['ungraded'] > 0): ?><a href="<?= e(url('/teacher/assignments/' . (int) $r['id'] . '/submissions')) ?>"><?= badge(tp(':count to grade', ':count to grade', (int) $r['ungraded']), 'amber') ?></a>
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
