<?php /** @var list<array> $rows @var int|null $editing */
use Sibol\Core\View;
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Subjects and terms')) ?></h1>
    <p class="page-lead"><?= e(t('Subjects group classes across sections. Archived subjects stay on existing classes but cannot be used for new ones.')) ?></p>
  </div>
</div>
<?= View::file('admin/_structure_tabs', ['active' => 'subjects']) ?>

<div class="layout-split">
  <section class="section" aria-labelledby="list-title">
    <h2 class="section__title sr-only" id="list-title"><?= e(t('Subjects')) ?></h2>
    <?php if (!$rows): ?>
      <?= partial('empty', ['compact' => true, 'title' => t('No subjects yet'), 'body' => t('Add the subjects your school teaches, such as Science, Mathematics and Filipino.')]) ?>
    <?php else: ?>
      <ul class="rows">
        <?php foreach ($rows as $s): $open = (int) $editing === (int) $s['id']; ?>
          <li class="row<?= $s['archived_at'] ? ' row--muted' : '' ?>" id="subject-<?= (int) $s['id'] ?>">
            <div class="row__main">
              <span class="row__title"><?= e($s['name']) ?><?= $s['code'] ? ' <span class="meta">' . e($s['code']) . '</span>' : '' ?></span>
              <div class="row__meta"><span><?= e(tp(':count active class', ':count active classes', (int) $s['classes'])) ?></span><?php if ($s['archived_at']): ?><span><?= e(t('Archived')) ?></span><?php endif; ?><?php if ($s['description']): ?><span><?= e(str_limit($s['description'], 90)) ?></span><?php endif; ?></div>
              <form method="post" action="<?= e(url('/admin/subjects/' . (int) $s['id'])) ?>" id="edit-subject-<?= (int) $s['id'] ?>"<?= $open ? '' : ' hidden' ?> style="margin-top: 12px">
                <?= csrf_field() ?>
                <div class="form-grid">
                  <?= field('text', 'name', t('Subject name'), ['id' => 'sn-' . $s['id'], 'value' => $s['name'], 'required' => true, 'attrs' => ['maxlength' => 120]] + ['isolated' => !$open]) ?>
                  <?= field('text', 'code', t('Code'), ['id' => 'sc-' . $s['id'], 'value' => (string) $s['code'], 'attrs' => ['maxlength' => 30]] + ['isolated' => !$open]) ?>
                  <?= field('text', 'description', t('Description'), ['id' => 'sd-' . $s['id'], 'value' => (string) $s['description'], 'wrap_class' => 'span-2', 'attrs' => ['maxlength' => 500]] + ['isolated' => !$open]) ?>
                </div>
                <?= checkbox('archived', t('Archived'), $s['archived_at'] !== null, t('Hide from the subject list for new classes.'), '1', 'sa-' . $s['id'], !$open) ?>
                <div class="form-actions"><button class="btn btn--primary btn--sm" type="submit"><?= e(t('Save')) ?></button></div>
              </form>
            </div>
            <div class="row__aside"><button type="button" class="btn btn--quiet btn--sm" data-toggle="#edit-subject-<?= (int) $s['id'] ?>"><?= icon('pencil', 'icon--sm') ?><?= e(t('Edit')) ?></button></div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
  <aside class="panel" aria-labelledby="new-subject">
    <h2 class="panel__title" id="new-subject"><?= e(t('Add a subject')) ?></h2>
    <form method="post" action="<?= e(url('/admin/subjects')) ?>">
      <?= csrf_field() ?>
      <?= field('text', 'name', t('Subject name'), ['id' => 'new-subject-name', 'required' => true, 'attrs' => ['maxlength' => 120]] + ['isolated' => (bool) $editing]) ?>
      <?= field('text', 'code', t('Code'), ['id' => 'new-subject-code', 'optional_label' => true, 'hint' => t('A short code, for example SCI9.'), 'attrs' => ['maxlength' => 30]] + ['isolated' => (bool) $editing]) ?>
      <?= field('textarea', 'description', t('Description'), ['id' => 'new-subject-desc', 'optional_label' => true, 'rows' => 2, 'attrs' => ['maxlength' => 500]] + ['isolated' => (bool) $editing]) ?>
      <button class="btn btn--primary" type="submit"><?= icon('plus', 'icon--sm') ?><?= e(t('Add subject')) ?></button>
    </form>
  </aside>
</div>
