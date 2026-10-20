<?php
/**
 * @var list<array> $rows @var int|null $editing @var array<int,list<array>> $gradingPeriods by academic period id
 * @var int|null $gpEditing grading period whose form is open @var int|null $gpAdding academic period whose "add" form is open
 */
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
          <li class="row row--wrap">
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
            <div class="row__aside"><button type="button" class="btn btn--quiet btn--sm" data-toggle="#edit-period-<?= (int) $p['id'] ?>"><?= icon('pencil', 'icon--sm') ?><?= e(t('Edit')) ?><span class="sr-only"> <?= e($p['name']) ?></span></button></div>
            <?php $gps = $gradingPeriods[(int) $p['id']] ?? []; $adding = (int) $gpAdding === (int) $p['id']; ?>
            <div class="grading-periods" id="year-<?= (int) $p['id'] ?>">
              <h3 class="grading-periods__title"><?= e(t('Grading periods')) ?></h3>
              <?php if ($gps): ?>
                <ul class="grading-periods__list">
                  <?php foreach ($gps as $g): $gOpen = (int) $gpEditing === (int) $g['id']; ?>
                    <li>
                      <div class="cluster cluster--between">
                        <span><strong><?= e($g['name']) ?></strong> <span class="meta"><?= e($range($g)) ?></span></span>
                        <span class="cluster">
                          <button type="button" class="btn btn--quiet btn--sm" data-toggle="#edit-gp-<?= (int) $g['id'] ?>"><?= icon('pencil', 'icon--sm') ?><?= e(t('Edit')) ?><span class="sr-only"> <?= e($g['name']) ?></span></button>
                          <form method="post" action="<?= e(url('/admin/grading-periods/' . (int) $g['id'] . '/delete')) ?>" data-confirm="<?= e(t('Work a teacher placed in :name by hand will count by its due date instead.', ['name' => $g['name']])) ?>" data-confirm-title="<?= e(t('Remove :name?', ['name' => $g['name']])) ?>" data-confirm-action="<?= e(t('Remove')) ?>" data-confirm-tone="danger">
                            <?= csrf_field() ?><button class="btn btn--quiet btn--sm" type="submit"><?= icon('trash-2', 'icon--sm') ?><span class="sr-only"><?= e(t('Remove :name', ['name' => $g['name']])) ?></span></button>
                          </form>
                        </span>
                      </div>
                      <form method="post" action="<?= e(url('/admin/grading-periods/' . (int) $g['id'])) ?>" id="edit-gp-<?= (int) $g['id'] ?>"<?= $gOpen ? '' : ' hidden' ?> class="grading-periods__form">
                        <?= csrf_field() ?>
                        <div class="form-grid">
                          <?= field('text', 'gp_name', t('Name'), ['id' => 'gpn-' . $g['id'], 'value' => $g['name'], 'required' => true, 'wrap_class' => 'span-2', 'attrs' => ['maxlength' => 60], 'isolated' => !$gOpen]) ?>
                          <?= field('date', 'gp_starts_on', t('Start date'), ['id' => 'gps-' . $g['id'], 'value' => $g['starts_on'], 'required' => true, 'isolated' => !$gOpen]) ?>
                          <?= field('date', 'gp_ends_on', t('End date'), ['id' => 'gpe-' . $g['id'], 'value' => $g['ends_on'], 'required' => true, 'isolated' => !$gOpen]) ?>
                        </div>
                        <button class="btn btn--primary btn--sm" type="submit"><?= e(t('Save')) ?></button>
                      </form>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php else: ?>
                <p class="meta"><?= e(t('None: each class in this year has one overall grade. Split the year, then adjust the dates.')) ?></p>
                <div class="cluster">
                  <?php foreach ([4 => t('Make four quarters'), 3 => t('Make three terms')] as $count => $label): ?>
                    <form method="post" action="<?= e(url('/admin/academic-periods/' . (int) $p['id'] . '/grading-periods/split')) ?>">
                      <?= csrf_field() ?><input type="hidden" name="count" value="<?= $count ?>">
                      <button class="btn btn--secondary btn--sm" type="submit"><?= e($label) ?></button>
                    </form>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
              <button type="button" class="btn btn--quiet btn--sm" data-toggle="#add-gp-<?= (int) $p['id'] ?>"><?= icon('plus', 'icon--sm') ?><?= e(t('Add a grading period')) ?></button>
              <form method="post" action="<?= e(url('/admin/academic-periods/' . (int) $p['id'] . '/grading-periods')) ?>" id="add-gp-<?= (int) $p['id'] ?>"<?= $adding ? '' : ' hidden' ?> class="grading-periods__form">
                <?= csrf_field() ?>
                <div class="form-grid">
                  <?= field('text', 'gp_name', t('Name'), ['id' => 'gpn-new-' . $p['id'], 'required' => true, 'placeholder' => t('Quarter 1'), 'wrap_class' => 'span-2', 'attrs' => ['maxlength' => 60], 'isolated' => !$adding]) ?>
                  <?= field('date', 'gp_starts_on', t('Start date'), ['id' => 'gps-new-' . $p['id'], 'required' => true, 'isolated' => !$adding]) ?>
                  <?= field('date', 'gp_ends_on', t('End date'), ['id' => 'gpe-new-' . $p['id'], 'required' => true, 'isolated' => !$adding]) ?>
                </div>
                <button class="btn btn--primary btn--sm" type="submit"><?= icon('plus', 'icon--sm') ?><?= e(t('Add grading period')) ?></button>
              </form>
            </div>
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
