<?php /** @var array $subjects @var array $periods @var int|string|null $currentPeriod @var list<array> $teachers @var list<string> $grades */ ?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('New class')) ?></h1>
    <p class="page-lead"><?= e(t('One section of a subject in one period, for example “Science 9 – Narra”.')) ?></p>
  </div>
</div>
<form method="post" action="<?= e(url('/admin/classes')) ?>" data-unsaved-warning style="max-width: 760px">
  <?= csrf_field() ?>
  <div class="form-grid">
    <?= field('text', 'name', t('Class name'), ['required' => true, 'wrap_class' => 'span-2', 'hint' => t('What teachers and students will see.'), 'attrs' => ['maxlength' => 140]]) ?>
    <?= field('select', 'subject_id', t('Subject'), ['required' => true, 'options' => ['' => t('Choose a subject')] + $subjects]) ?>
    <?= field('select', 'academic_period_id', t('Academic period'), ['value' => (string) $currentPeriod, 'options' => ['' => t('No period')] + $periods]) ?>
    <?= field('text', 'section', t('Section'), ['optional_label' => true, 'attrs' => ['maxlength' => 60]]) ?>
    <?= field('select', 'grade_level', t('Grade level'), ['options' => ['' => t('Not set')] + array_combine($grades, $grades), 'hint' => t('Used to suggest students when adding members.')]) ?>
    <?= field('select', 'teacher_id', t('Teacher'), ['options' => ['' => t('Add later')] + array_combine(array_map(static fn ($t) => (string) $t['id'], $teachers), array_map(static fn ($t) => user_name($t) . ($t['status'] === 'invited' ? ' (' . t('invited') . ')' : ''), $teachers))]) ?>
    <?= field('textarea', 'description', t('Description'), ['optional_label' => true, 'rows' => 3, 'wrap_class' => 'span-2', 'attrs' => ['maxlength' => 2000]]) ?>
  </div>
  <div class="form-actions">
    <button class="btn btn--primary" type="submit" data-busy="<?= e(t('Creating…')) ?>"><?= e(t('Create class')) ?></button>
    <a class="btn btn--quiet" href="<?= e(url('/admin/classes')) ?>"><?= e(t('Cancel')) ?></a>
  </div>
</form>
