<?php /** @var list<array> $classes @var list<array> $materials @var list<array> $modules @var int|null $selectedClass @var int|null $aiRemaining */ ?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('New lesson')) ?></h1>
    <p class="page-lead"><?= e(t('Describe the lesson. An AI draft uses only the materials you tick and cites them; you review and edit every section.')) ?></p>
  </div>
</div>
<?php if (!$classes): ?>
  <?= partial('empty', ['title' => t('You have no active classes'), 'body' => t('Ask your school administrator to assign you a class.')]) ?>
<?php else: ?>
<form method="post" action="<?= e(url('/teacher/lessons')) ?>" style="max-width: 860px">
  <?= csrf_field() ?>
  <div class="form-grid">
    <?= field('select', 'class_id', t('Class'), ['required' => true, 'options' => array_column($classes, 'name', 'id'), 'value' => (string) ($selectedClass ?? $classes[0]['id'])]) ?>
    <div class="field">
      <label class="field__label" for="f-module_id"><?= e(t('Module')) ?> <span class="field__optional"><?= e(t('optional')) ?></span></label>
      <select class="input select" id="f-module_id" name="module_id"><option value=""><?= e(t('No module')) ?></option>
        <?php foreach ($classes as $c): $items = array_filter($modules, static fn ($m) => (int) $m['class_id'] === (int) $c['id']); if (!$items) { continue; } ?>
          <optgroup label="<?= e($c['name']) ?>"><?php foreach ($items as $m): ?><option value="<?= (int) $m['id'] ?>"><?= e($m['title']) ?></option><?php endforeach; ?></optgroup>
        <?php endforeach; ?></select>
    </div>
    <?= field('text', 'title', t('Lesson title'), ['required' => true, 'wrap_class' => 'span-2', 'attrs' => ['maxlength' => 200]]) ?>
    <?= field('textarea', 'objectives', t('Learning objectives'), ['rows' => 3, 'wrap_class' => 'span-2', 'hint' => t('What should students be able to do by the end? One per line works well.'), 'attrs' => ['maxlength' => 3000]]) ?>
    <?= field('text', 'grade_level', t('Grade level'), ['optional_label' => true, 'placeholder' => t('For example: Grade 9'), 'attrs' => ['maxlength' => 40]]) ?>
    <?= field('number', 'duration_minutes', t('Length (minutes)'), ['value' => '50', 'attrs' => ['min' => 5, 'max' => 600]]) ?>
    <?= field('text', 'reading_level', t('Reading level'), ['optional_label' => true, 'placeholder' => t('For example: at grade level'), 'attrs' => ['maxlength' => 40]]) ?>
    <?= field('select', 'language', t('Language'), ['options' => ['en' => 'English', 'fil' => 'Filipino'], 'value' => 'en']) ?>
    <?= field('text', 'lesson_format', t('Format'), ['optional_label' => true, 'placeholder' => t('For example: discussion and pair work'), 'attrs' => ['maxlength' => 40]]) ?>
  </div>
  <fieldset>
    <legend><?= e(t('Start from')) ?></legend>
    <div class="choice-list" style="grid-template-columns: repeat(auto-fit, minmax(260px, 1fr))">
      <label class="choice"><input type="radio" name="start" value="blank"<?= checked(old('start', 'ai') === 'blank') ?>><span><span class="choice__title"><?= e(t('A blank plan')) ?></span><span class="choice__desc"><?= e(t('Empty sections for you to fill in.')) ?></span></span></label>
      <label class="choice"><input type="radio" name="start" value="ai"<?= checked(old('start', 'ai') === 'ai') ?>><span><span class="choice__title"><?= e(t('An AI draft')) ?></span><span class="choice__desc"><?= e(t('Objectives, explanation, worked example, activities, checks and an exit ticket, citing your materials.')) ?></span></span></label>
    </div>
    <?php if (field_error('start')): ?><p class="field__error"><?= e(field_error('start')) ?></p><?php endif; ?>
  </fieldset>
  <fieldset>
    <legend><?= e(t('Materials to draw on')) ?></legend>
    <?php if (!$materials): ?>
      <p class="meta"><?= e(t('No readable materials yet. The draft will use only your objectives.')) ?></p>
    <?php else: ?>
      <?php foreach ($classes as $c): $items = array_filter($materials, static fn ($m) => (int) $m['class_id'] === (int) $c['id']); if (!$items) { continue; } ?>
        <p class="field__label" style="margin-top:8px"><?= e($c['name']) ?></p>
        <?php foreach ($items as $m): ?><?= checkbox('materials[]', $m['title'], false, null, (string) $m['id']) ?><?php endforeach; ?>
      <?php endforeach; ?>
    <?php endif; ?>
  </fieldset>
  <div class="form-actions">
    <button class="btn btn--primary" type="submit" data-busy="<?= e(t('Preparing the draft…')) ?>"><?= e(t('Create lesson draft')) ?></button>
    <span class="meta"><?= e(\Sibol\Services\Ai\Providers::label()) ?><?php if ($aiRemaining !== null): ?>. <?= e(tp(':count AI request left today', ':count AI requests left today', $aiRemaining)) ?><?php endif; ?></span>
  </div>
</form>
<?php endif; ?>
