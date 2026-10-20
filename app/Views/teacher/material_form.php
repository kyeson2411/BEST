<?php /** @var list<array> $classes @var list<array> $modules @var int|null $selectedClass @var int $maxMb */ ?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Add material')) ?></h1>
    <p class="page-lead"><?= e(t('PDF, Word, PowerPoint, text or Markdown files are read so the companion can quote them with page references. Scanned pages without real text cannot be read.')) ?></p>
  </div>
</div>
<?php if (!$classes): ?>
  <?= partial('empty', ['title' => t('You have no active classes'), 'body' => t('Materials belong to a class. Ask your administrator to assign you one.')]) ?>
<?php else: ?>
<form method="post" action="<?= e(url('/teacher/materials')) ?>" enctype="multipart/form-data" style="max-width: 820px">
  <?= csrf_field() ?>
  <div class="form-grid">
    <?= field('select', 'class_id', t('Class'), ['required' => true, 'options' => array_column($classes, 'name', 'id'), 'value' => (string) ($selectedClass ?? $classes[0]['id'])]) ?>
    <div class="field<?= field_error('module_id') ? ' field--error' : '' ?>">
      <label class="field__label" for="f-module_id"><?= e(t('Module')) ?> <span class="field__optional"><?= e(t('optional')) ?></span></label>
      <select class="input select" id="f-module_id" name="module_id">
        <option value=""><?= e(t('No module')) ?></option>
        <?php foreach ($classes as $c): $items = array_filter($modules, static fn ($m) => (int) $m['class_id'] === (int) $c['id']); if (!$items) { continue; } ?>
          <optgroup label="<?= e($c['name']) ?>"><?php foreach ($items as $m): ?><option value="<?= (int) $m['id'] ?>"<?= selected($m['id'], old('module_id')) ?>><?= e($m['title']) ?></option><?php endforeach; ?></optgroup>
        <?php endforeach; ?>
      </select>
      <?php if (field_error('module_id')): ?><p class="field__error"><?= e(field_error('module_id')) ?></p><?php endif; ?>
    </div>
    <?= field('text', 'title', t('Title'), ['required' => true, 'wrap_class' => 'span-2', 'attrs' => ['maxlength' => 200]]) ?>
    <?= field('textarea', 'description', t('Note for students'), ['rows' => 3, 'wrap_class' => 'span-2', 'optional_label' => true, 'attrs' => ['maxlength' => 2000], 'hint' => t('For example, which pages matter for the next quiz.')]) ?>
    <?= field('text', 'topic', t('Topic'), ['optional_label' => true, 'attrs' => ['maxlength' => 120], 'hint' => t('Used for search and filters.')]) ?>
  </div>

  <fieldset>
    <legend><?= e(t('What are you adding?')) ?></legend>
    <div class="choice-list" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr))">
      <label class="choice"><input type="radio" name="kind" value="file"<?= checked(old('kind', 'file') === 'file') ?>><span><span class="choice__title"><?= e(t('A file')) ?></span><span class="choice__desc"><?= e(t('PDF, DOCX, PPTX, TXT or MD, up to :mb MB.', ['mb' => $maxMb])) ?></span></span></label>
      <label class="choice"><input type="radio" name="kind" value="link"<?= checked(old('kind') === 'link') ?>><span><span class="choice__title"><?= e(t('A link')) ?></span><span class="choice__desc"><?= e(t('A web page or video. The companion does not read links.')) ?></span></span></label>
    </div>
  </fieldset>
  <div class="form-grid">
    <div class="field<?= field_error('file') ? ' field--error' : '' ?>">
      <label class="field__label" for="f-file"><?= e(t('File')) ?></label>
      <input class="input" id="f-file" type="file" name="file" accept=".pdf,.docx,.pptx,.txt,.md"<?= field_error('file') ? ' aria-invalid="true" aria-describedby="f-file-error"' : '' ?>>
      <?php if (field_error('file')): ?><p class="field__error" id="f-file-error"><?= icon('circle-alert', 'icon--xs') ?><?= e(field_error('file')) ?></p><?php endif; ?>
    </div>
    <?= field('url', 'url', t('Web address'), ['placeholder' => 'https://', 'attrs' => ['maxlength' => 500]]) ?>
  </div>

  <fieldset>
    <legend><?= e(t('When should students see it?')) ?></legend>
    <div class="choice-list">
      <label class="choice"><input type="radio" name="release" value="now"<?= checked(old('release', 'now') === 'now') ?>><span><span class="choice__title"><?= e(t('Publish now')) ?></span><span class="choice__desc"><?= e(t('Students are notified in Sibol.')) ?></span></span></label>
      <label class="choice"><input type="radio" name="release" value="schedule"<?= checked(old('release') === 'schedule') ?>><span><span class="choice__title"><?= e(t('Schedule')) ?></span><span class="choice__desc"><?= e(t('Hidden from students and the companion until the date below.')) ?></span></span></label>
      <label class="choice"><input type="radio" name="release" value="draft"<?= checked(old('release') === 'draft') ?>><span><span class="choice__title"><?= e(t('Save as draft')) ?></span><span class="choice__desc"><?= e(t('Only teachers of the class can see it.')) ?></span></span></label>
    </div>
  </fieldset>
  <div class="form-grid">
    <?= field('datetime-local', 'release_at', t('Release date and time'), ['hint' => t('Only for scheduled materials. Your timezone: :tz.', ['tz' => \Sibol\Core\Dates::tz()])]) ?>
  </div>
  <?= checkbox('ai_enabled', t('Let the companion use this material'), old('ai_enabled', '1') === '1' || old('ai_enabled', '1') === 1, t('Students can then ask questions about it and get answers that quote it, once it is released.')) ?>
  <div class="form-actions">
    <button class="btn btn--primary" type="submit" data-busy="<?= e(t('Uploading…')) ?>"><?= e(t('Add material')) ?></button>
    <a class="btn btn--quiet" href="<?= e(url('/teacher/materials')) ?>"><?= e(t('Cancel')) ?></a>
  </div>
</form>
<?php endif; ?>
