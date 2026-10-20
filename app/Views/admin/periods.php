<?php /** @var list<array> $rows @var int|null $editing */
use Sibol\Core\View;

$range = static fn (array $p): string => fmt_date($p['starts_on'] . ' 12:00:00', 'date_short') . ' – ' . fmt_date($p['ends_on'] . ' 12:00:00', 'date_short');
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Subjects and terms')) ?></h1>
    <p class="page-lead"><?= e(t('Academic periods are school years, semesters or quarters. New classes start in the current period.')) ?></p>
  </div>
</div>
<?= View::file('admin/_structure_tabs', ['active' => 'periods']) ?>

<div class="layout-split">
  <section class="section" aria-labelledby="plist-title">
    <h2 class="section__title sr-only" id="plist-title"><?= e(t('Academic periods')) ?></h2>
    <?php if (!$rows): ?>
      <?= partial('empty', ['compact' => true, 'title' => t('No academic periods yet'), 'body' => t('Add the current school year or quarter.')]) ?>
    <?php else: ?>
      <ul class="rows">
        <?php foreach ($rows as $p): $open = (int) $editing === (int) $p['id']; ?>
          <li class="row">
            <div class="row__main">
              <span class="row__title"><?= e($p['name']) ?> <?= (int) $p['is_current'] ? badge(t('Current'), 'leaf') : '' ?></span>
              <div class="row__meta"><span><?= e($range($p)) ?></span><span><?= e(tp(':count class', ':count classes', (int) $p['classes'])) ?></span></div>
              <form method="post" action="<?= e(url('/admin/academic-periods/' . (int) $p['id'])) ?>" id="edit-period-<?= (int) $p['id'] ?>"<?= $open ? '' : ' hidden' ?> style="margin-top: 12px">
                <?= csrf_field() ?>
                <div class="form-grid">
                  <?= field('text', 'name', t('Name'), ['id' => 'pn-' . $p['id'], 'value' => $p['name'], 'required' => true, 'wrap_class' => 'span-2', 'attrs' => ['maxlength' => 100]] + ['isolated' => !$open]) ?>
                  <?= field('date', 'starts_on', t('Start date'), ['id' => 'ps-' . $p['id'], 'value' => $p['starts_on'], 'required' => true] + ['isolated' => !$open]) ?>
                  <?= field('date', 'ends_on', t('End date'), ['id' => 'pe-' . $p['id'], 'value' => $p['ends_on'], 'required' => true] + ['isolated' => !$open]) ?>
                </div>
                <?= checkbox('is_current', t('This is the current period'), (bool) $p['is_current'], null, '1', 'pc-' . $p['id'], !$open) ?>
                <div class="form-actions"><button class="btn btn--primary btn--sm" type="submit"><?= e(t('Save')) ?></button></div>
              </form>
            </div>
            <div class="row__aside"><button type="button" class="btn btn--quiet btn--sm" data-toggle="#edit-period-<?= (int) $p['id'] ?>"><?= icon('pencil', 'icon--sm') ?><?= e(t('Edit')) ?></button></div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
  <aside class="panel" aria-labelledby="new-period">
    <h2 class="panel__title" id="new-period"><?= e(t('Add a period')) ?></h2>
    <form method="post" action="<?= e(url('/admin/academic-periods')) ?>">
      <?= csrf_field() ?>
      <?= field('text', 'name', t('Name'), ['id' => 'np-name', 'required' => true, 'placeholder' => t('School year 2026–2027'), 'attrs' => ['maxlength' => 100]] + ['isolated' => (bool) $editing]) ?>
      <?= field('date', 'starts_on', t('Start date'), ['id' => 'np-start', 'required' => true] + ['isolated' => (bool) $editing]) ?>
      <?= field('date', 'ends_on', t('End date'), ['id' => 'np-end', 'required' => true] + ['isolated' => (bool) $editing]) ?>
      <?= checkbox('is_current', t('Make this the current period'), false, null, '1', 'np-current', (bool) $editing) ?>
      <button class="btn btn--primary" type="submit"><?= icon('plus', 'icon--sm') ?><?= e(t('Add period')) ?></button>
    </form>
  </aside>
</div>
