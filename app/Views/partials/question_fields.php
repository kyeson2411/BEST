<?php
/** @var array|null $q existing question (options/answer_key decoded) @var string $prefix unique id prefix */
$q = $q ?? null;
$type = $q['qtype'] ?? (string) old('qtype', 'multiple_choice');
$options = $q ? array_column((array) $q['options'], 'text') : (array) old('options', []);
$key = $q['answer_key'] ?? [];
$correct = $q ? (string) ($key['correct'] ?? '') : (string) old('correct', '');
if ($q && $type === 'true_false') {
    $correct = !empty($key['correct']) ? 'true' : 'false';
}
$accepted = $q ? implode("\n", (array) ($key['accepted'] ?? [])) : (string) old('accepted', '');
$letters = range('A', 'F');
$isNew = $q === null;
?>
<div class="form-grid" data-question-fields>
  <div class="field">
    <label class="field__label" for="<?= e($prefix) ?>-qtype"><?= e(t('Question type')) ?></label>
    <select class="input select" id="<?= e($prefix) ?>-qtype" name="qtype" data-qtype>
      <?php foreach (['multiple_choice' => t('Multiple choice'), 'true_false' => t('True or false'), 'short_answer' => t('Short answer'), 'essay' => t('Essay or long answer')] as $v => $l): ?>
        <option value="<?= e($v) ?>"<?= selected($v, $type) ?>><?= e($l) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label class="field__label" for="<?= e($prefix) ?>-points"><?= e(t('Points')) ?></label>
    <input class="input" id="<?= e($prefix) ?>-points" type="number" name="points" min="0" max="100" step="0.5" value="<?= e($q ? fmt_number($q['points'], 2) : (string) old('points', '1')) ?>">
  </div>
  <div class="field span-2<?= $isNew && field_error('prompt') ? ' field--error' : '' ?>">
    <label class="field__label" for="<?= e($prefix) ?>-prompt"><?= e(t('Question')) ?></label>
    <textarea class="input textarea" id="<?= e($prefix) ?>-prompt" name="prompt" rows="3" maxlength="4000" required><?= e($q['prompt'] ?? (string) old('prompt', '')) ?></textarea>
    <?php if ($isNew && field_error('prompt')): ?><p class="field__error"><?= e(field_error('prompt')) ?></p><?php endif; ?>
  </div>

  <fieldset class="span-2" data-for="multiple_choice">
    <legend class="field__label"><?= e(t('Answer choices (multiple choice)')) ?></legend>
    <p class="fieldset-hint"><?= e(t('Fill in at least two, and mark the correct one.')) ?></p>
    <?php foreach ($letters as $i => $letter): ?>
      <div class="cluster" style="flex-wrap:nowrap; margin-bottom:8px">
        <input type="radio" name="correct" value="<?= e($letter) ?>" id="<?= e($prefix) ?>-c<?= $i ?>"<?= checked($type === 'multiple_choice' && $correct === $letter) ?> class="check__input" aria-label="<?= e(t('Choice :l is correct', ['l' => $letter])) ?>">
        <label class="sr-only" for="<?= e($prefix) ?>-o<?= $i ?>"><?= e(t('Choice :l', ['l' => $letter])) ?></label>
        <span class="question__no" style="font-size:1rem; min-width:20px" aria-hidden="true"><?= e($letter) ?></span>
        <input class="input" id="<?= e($prefix) ?>-o<?= $i ?>" name="options[]" value="<?= e($options[$i] ?? '') ?>" maxlength="500">
      </div>
    <?php endforeach; ?>
    <?php if ($isNew && (field_error('options') || field_error('correct'))): ?><p class="field__error"><?= e(field_error('options') ?? field_error('correct')) ?></p><?php endif; ?>
  </fieldset>

  <fieldset class="span-2" data-for="true_false">
    <legend class="field__label"><?= e(t('Correct answer (true or false)')) ?></legend>
    <div class="cluster">
      <label class="choice"><input type="radio" name="correct" value="true"<?= checked($type === 'true_false' && $correct === 'true') ?>><span class="choice__title"><?= e(t('True')) ?></span></label>
      <label class="choice"><input type="radio" name="correct" value="false"<?= checked($type === 'true_false' && $correct === 'false') ?>><span class="choice__title"><?= e(t('False')) ?></span></label>
    </div>
  </fieldset>

  <div class="span-2" data-for="short_answer">
    <div class="field<?= $isNew && field_error('accepted') ? ' field--error' : '' ?>">
      <label class="field__label" for="<?= e($prefix) ?>-accepted"><?= e(t('Accepted answers (short answer)')) ?></label>
      <p class="field__hint"><?= e(t('One per line. Spacing and a final full stop are ignored. You can always adjust marks while grading.')) ?></p>
      <textarea class="input textarea" id="<?= e($prefix) ?>-accepted" name="accepted" rows="3" style="min-height:80px"><?= e($accepted) ?></textarea>
      <?php if ($isNew && field_error('accepted')): ?><p class="field__error"><?= e(field_error('accepted')) ?></p><?php endif; ?>
    </div>
    <?= checkbox('case_sensitive', t('Capital letters must match'), !empty($key['case_sensitive'])) ?>
  </div>

  <p class="meta span-2" data-for="essay"><?= e(t('Essay answers are marked by you. Add a rubric to keep marking consistent.')) ?></p>

  <?= field('textarea', 'explanation', t('Explanation shown with the correct answer'), ['value' => (string) ($q['explanation'] ?? old('explanation', '')), 'rows' => 2, 'wrap_class' => 'span-2', 'optional_label' => true, 'id' => $prefix . '-explanation']) ?>
  <?= field('text', 'topic', t('Topic'), ['value' => (string) ($q['topic'] ?? old('topic', '')), 'optional_label' => true, 'id' => $prefix . '-topic', 'hint' => t('Used in insights to show which topics need reteaching.'), 'attrs' => ['maxlength' => 120]]) ?>
  <?php if (!empty($q['source_ref'])): ?><p class="meta" style="margin-top:28px"><?= icon('quote', 'icon--xs') ?> <?= e(t('Drafted from :src', ['src' => $q['source_ref']])) ?></p><?php endif; ?>
</div>
