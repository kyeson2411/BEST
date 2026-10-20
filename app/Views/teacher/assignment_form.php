<?php /** @var string $kind @var list<array> $classes */
$headings = ['quiz' => t('New quiz'), 'paper' => t('New work in class or on paper')];
$leads = [
    'quiz' => t('Set the rules first. You add questions, or draft them from your materials, on the next screen.'),
    'paper' => t('A test, task or activity done in class or on paper. Students see it with its date; you type the scores into a grid afterwards.'),
];
$others = array_diff_key(['assignment' => t('Make an assignment instead'), 'quiz' => t('Make a quiz instead'), 'paper' => t('Done in class or on paper?')], [$kind => true]);
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e($headings[$kind] ?? t('New assignment')) ?></h1>
    <p class="page-lead"><?= e($leads[$kind] ?? t('Set the basics first. You can add a rubric on the next screen.')) ?></p>
  </div>
  <div class="page-actions">
    <?php foreach ($others as $other => $label): ?>
      <a class="btn btn--quiet" href="<?= e(url('/teacher/assignments/new', ['kind' => $other, 'class_id' => $selectedClass ?? null])) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>
</div>
<?php if (!$classes): ?>
  <?= partial('empty', ['title' => t('You have no active classes'), 'body' => t('Ask your school administrator to assign you a class.')]) ?>
<?php else: ?>
  <form method="post" action="<?= e(url('/teacher/assignments')) ?>" style="max-width: 860px" data-unsaved-warning>
    <?= csrf_field() ?>
    <?= partial('assignment_settings', get_defined_vars()) ?>
    <div class="form-actions">
      <button class="btn btn--primary" type="submit" data-busy="<?= e(t('Creating…')) ?>"><?= e(t('Create draft')) ?></button>
      <a class="btn btn--quiet" href="<?= e(url('/teacher/assignments')) ?>"><?= e(t('Cancel')) ?></a>
    </div>
  </form>
<?php endif; ?>
