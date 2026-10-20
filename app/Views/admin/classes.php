<?php /** @var list<array> $rows @var \Sibol\Core\Paginator $pager @var string $q @var int|null $subjectId @var int|null $periodId @var string $status @var array $subjects @var array $periods */
$filters = ['q' => $q, 'subject_id' => $subjectId, 'period_id' => $periodId, 'status' => $status === 'archived' ? 'archived' : null];
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Classes')) ?></h1>
    <p class="page-lead"><?= e(t('Each class is one section of a subject. Teachers and students only see the classes they belong to.')) ?></p>
  </div>
  <div class="page-actions"><a class="btn btn--primary" href="<?= e(url('/admin/classes/new')) ?>"><?= icon('plus', 'icon--sm') ?><?= e(t('New class')) ?></a></div>
</div>

<nav class="tabs" aria-label="<?= e(t('Class status')) ?>">
  <a class="tab" href="<?= e(url('/admin/classes')) ?>"<?= $status === 'active' ? ' aria-current="page"' : '' ?>><?= e(t('Active')) ?></a>
  <a class="tab" href="<?= e(url('/admin/classes', ['status' => 'archived'])) ?>"<?= $status === 'archived' ? ' aria-current="page"' : '' ?>><?= e(t('Archived')) ?></a>
</nav>

<form class="toolbar" method="get" action="<?= e(url('/admin/classes')) ?>" role="search">
  <?php if ($status === 'archived'): ?><input type="hidden" name="status" value="archived"><?php endif; ?>
  <div class="field field--grow"><label class="field__label" for="c-q"><?= e(t('Search')) ?></label><input class="input" id="c-q" type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('Class or section name')) ?>"></div>
  <div class="field"><label class="field__label" for="c-subject"><?= e(t('Subject')) ?></label>
    <select class="input select" id="c-subject" name="subject_id" data-autosubmit><option value=""><?= e(t('All subjects')) ?></option>
      <?php foreach ($subjects as $id => $name): ?><option value="<?= (int) $id ?>"<?= selected($id, $subjectId) ?>><?= e($name) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label class="field__label" for="c-period"><?= e(t('Period')) ?></label>
    <select class="input select" id="c-period" name="period_id" data-autosubmit><option value=""><?= e(t('All periods')) ?></option>
      <?php foreach ($periods as $id => $name): ?><option value="<?= (int) $id ?>"<?= selected($id, $periodId) ?>><?= e($name) ?></option><?php endforeach; ?></select></div>
  <button class="btn btn--secondary" type="submit"><?= e(t('Apply')) ?></button>
</form>

<?php if (!$rows): ?>
  <?= partial('empty', ['title' => $status === 'archived' ? t('No archived classes') : ($q !== '' || $subjectId || $periodId ? t('No classes match these filters') : t('No classes yet')), 'body' => t('Create a class for each section, then add its teacher and students.'), 'action' => $status === 'archived' ? null : [t('New class'), '/admin/classes/new']]) ?>
<?php else: ?>
  <div class="table-frame">
    <table class="table table--stack">
      <caption class="sr-only"><?= e(t('Classes')) ?></caption>
      <thead><tr>
        <th scope="col"><?= e(t('Class')) ?></th>
        <th scope="col"><?= e(t('Teachers')) ?></th>
        <th scope="col" class="num"><?= e(t('Students')) ?></th>
        <th scope="col"><?= e(t('Period')) ?></th>
      </tr></thead>
      <tbody>
        <?php foreach ($rows as $c): ?>
          <tr>
            <td class="cell-primary"><a class="row__title" href="<?= e(url('/admin/classes/' . (int) $c['id'])) ?>"><?= e($c['name']) ?></a>
              <span class="table-sub"><?= e($c['subject_name']) ?><?= $c['grade_level'] ? ', ' . e($c['grade_level']) : '' ?></span></td>
            <td data-label="<?= e(t('Teachers')) ?>"><?= $c['teachers'] ? e($c['teachers']) : badge(t('No teacher yet'), 'amber') ?></td>
            <td data-label="<?= e(t('Students')) ?>" class="num"><?= (int) $c['students'] ?></td>
            <td data-label="<?= e(t('Period')) ?>"><?= e($c['period_name'] ?? '—') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= partial('pagination', ['pager' => $pager, 'path' => '/admin/classes', 'query' => $filters]) ?>
<?php endif; ?>
