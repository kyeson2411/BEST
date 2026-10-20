<?php /** @var string $kind @var list<array> $classes */ ?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e($kind === 'quiz' ? t('New quiz') : t('New assignment')) ?></h1>
    <p class="page-lead"><?= e($kind === 'quiz' ? t('Set the rules first. You add questions, or draft them from your materials, on the next screen.') : t('Set the basics first. You can add a rubric on the next screen.')) ?></p>
  </div>
  <div class="page-actions">
    <a class="btn btn--quiet" href="<?= e(url('/teacher/assignments/new', ['kind' => $kind === 'quiz' ? 'assignment' : 'quiz'])) ?>"><?= e($kind === 'quiz' ? t('Make an assignment instead') : t('Make a quiz instead')) ?></a>
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
