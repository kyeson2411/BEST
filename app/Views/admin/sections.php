<?php
/** @var list<array> $rows @var list<array> $teachers */
use Sibol\Core\View;
use Sibol\Services\Sections;

$options = ['' => t('No adviser')];
foreach ($teachers as $teacher) {
    $options[(string) $teacher['id']] = user_name($teacher);
}
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Subjects and terms')) ?></h1>
    <p class="page-lead"><?= e(t('A section is a grade level\'s group of students, such as Grade 9 Narra. It appears when classes are given that section name. Its adviser can print sign-in and password slips for its students.')) ?></p>
  </div>
</div>
<?= View::file('admin/_structure_tabs', ['active' => 'sections']) ?>

<?php if (!$rows): ?>
  <?= partial('empty', ['compact' => true, 'title' => t('No sections yet'), 'body' => t('Give your classes a section name, such as Narra, on each class page. The section appears here so you can choose its adviser.'), 'action' => [t('Go to classes'), '/admin/classes']]) ?>
<?php else: ?>
  <ul class="rows" style="max-width: 920px">
    <?php foreach ($rows as $s): $label = Sections::label($s); ?>
      <li class="row" id="section-<?= (int) $s['id'] ?>">
        <div class="row__main">
          <span class="row__title"><?= e($label) ?></span>
          <div class="row__meta">
            <?php if ($s['period_name']): ?><span><?= e($s['period_name']) ?><?= (int) $s['is_current'] ? ' ' . badge(t('Current'), 'leaf') : '' ?></span><?php endif; ?>
            <span><?= e(tp(':count active class', ':count active classes', (int) $s['classes'])) ?></span>
            <span><?= e(tp(':count student', ':count students', (int) $s['students'])) ?></span>
          </div>
        </div>
        <div class="row__aside">
          <form method="post" action="<?= e(url('/admin/sections/' . (int) $s['id'])) ?>" class="cluster" style="flex-wrap: nowrap">
            <?= csrf_field() ?>
            <label class="sr-only" for="adviser-<?= (int) $s['id'] ?>"><?= e(t('Adviser of :section', ['section' => $label])) ?></label>
            <select class="input select" id="adviser-<?= (int) $s['id'] ?>" name="adviser_id" style="min-width: 200px">
              <?php foreach ($options as $value => $name): ?>
                <option value="<?= e($value) ?>"<?= selected($value, (string) $s['adviser_id']) ?>><?= e($name) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn--secondary btn--sm" type="submit"><?= e(t('Save')) ?></button>
          </form>
          <?php if ((int) $s['all_classes'] === 0): ?>
            <form method="post" action="<?= e(url('/admin/sections/' . (int) $s['id'] . '/delete')) ?>" data-confirm="<?= e(t('No classes use this section any more.')) ?>" data-confirm-title="<?= e(t('Remove section?')) ?>" data-confirm-action="<?= e(t('Remove')) ?>" data-confirm-tone="danger">
              <?= csrf_field() ?><button class="btn btn--danger-quiet btn--sm" type="submit"><?= e(t('Remove')) ?></button>
            </form>
          <?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
