<?php
/** @var array|null $assignment @var string $kind @var list<array> $classes @var int|null $selectedClass @var list<array> $modules @var array $categories */
$a = $assignment ?? [];
$isQuiz = $kind === 'quiz';
$val = static fn (string $k, $default = '') => $a[$k] ?? $default;
?>
<input type="hidden" name="kind" value="<?= e($kind) ?>">
<div class="form-grid">
  <?php if (!$assignment): ?>
    <?= field('select', 'class_id', t('Class'), ['required' => true, 'options' => array_column($classes, 'name', 'id'), 'value' => (string) ($selectedClass ?? ($classes[0]['id'] ?? ''))]) ?>
  <?php else: ?>
    <div class="field"><span class="field__label"><?= e(t('Class')) ?></span><p style="margin:10px 0 0"><?= e($a['class_name']) ?></p></div>
  <?php endif; ?>
  <div class="field">
    <label class="field__label" for="f-module_id"><?= e(t('Module')) ?> <span class="field__optional"><?= e(t('optional')) ?></span></label>
    <select class="input select" id="f-module_id" name="module_id">
      <option value=""><?= e(t('No module')) ?></option>
      <?php foreach ($classes as $c): $items = array_filter($modules, static fn ($m) => (int) $m['class_id'] === (int) $c['id']); if (!$items) { continue; } ?>
        <optgroup label="<?= e($c['name']) ?>"><?php foreach ($items as $m): ?><option value="<?= (int) $m['id'] ?>"<?= selected($m['id'], old('module_id', (string) $val('module_id'))) ?>><?= e($m['title']) ?></option><?php endforeach; ?></optgroup>
      <?php endforeach; ?>
    </select>
  </div>
  <?= field('text', 'title', t('Title'), ['required' => true, 'value' => (string) $val('title'), 'wrap_class' => 'span-2', 'attrs' => ['maxlength' => 200]]) ?>
  <?= field('textarea', 'instructions', t('Instructions for students'), ['value' => (string) $val('instructions'), 'rows' => 7, 'wrap_class' => 'span-2', 'hint' => t('Simple formatting works: **bold**, lists, and maths like $x^2$.'), 'attrs' => ['maxlength' => 20000]]) ?>
  <?= field('text', 'objectives', t('Learning objectives'), ['value' => (string) $val('objectives'), 'wrap_class' => 'span-2', 'optional_label' => true, 'attrs' => ['maxlength' => 2000]]) ?>
  <?= field('select', 'category', t('Grading category'), ['options' => $categories, 'value' => (string) $val('category'), 'hint' => count($categories) > 1 ? t('Set by your school\'s grading policy.') : t('Your school uses points only.')]) ?>
  <?php if (!$isQuiz): ?>
    <?= field('number', 'points_possible', t('Points'), ['value' => (string) ($val('points_possible', '') === '' ? '10' : fmt_number($val('points_possible'), 2)), 'attrs' => ['min' => 0, 'max' => 1000, 'step' => '0.5'], 'hint' => t('A rubric, if you add one, sets this automatically.')]) ?>
  <?php endif; ?>
</div>

<fieldset>
  <legend><?= e(t('Dates')) ?></legend>
  <p class="fieldset-hint"><?= e(t('In your timezone, :tz.', ['tz' => \Sibol\Core\Dates::tz()])) ?></p>
  <div class="form-grid">
    <?= field('datetime-local', 'opens_at', t('Opens'), ['value' => $val('opens_at') ? fmt_date($val('opens_at'), 'input') : '', 'optional_label' => true, 'hint' => t('Leave empty to open as soon as you publish.')]) ?>
    <?= field('datetime-local', 'due_at', t('Due'), ['value' => $val('due_at') ? fmt_date($val('due_at'), 'input') : '', 'required' => true]) ?>
    <?= field('select', 'late_policy', t('Late work'), ['options' => ['accepted' => t('Accept and mark late'), 'penalty' => t('Accept with a penalty'), 'not_accepted' => t('Do not accept after the due date')], 'value' => (string) $val('late_policy', 'accepted')]) ?>
    <?= field('number', 'late_penalty_pct', t('Late penalty (%)'), ['value' => (string) $val('late_penalty_pct', 10), 'attrs' => ['min' => 0, 'max' => 100], 'hint' => t('Only used with “Accept with a penalty”.')]) ?>
  </div>
</fieldset>

<?php if ($isQuiz): ?>
  <fieldset>
    <legend><?= e(t('Quiz rules')) ?></legend>
    <div class="form-grid">
      <?= field('number', 'max_attempts', t('Attempts allowed'), ['value' => (string) $val('max_attempts', 1), 'attrs' => ['min' => 1, 'max' => 20], 'hint' => t('The best attempt counts.')]) ?>
      <?= field('number', 'time_limit_minutes', t('Time limit (minutes)'), ['value' => (string) $val('time_limit_minutes', ''), 'optional_label' => true, 'attrs' => ['min' => 1, 'max' => 600]]) ?>
    </div>
  </fieldset>
<?php else: ?>
  <fieldset>
    <legend><?= e(t('How students submit')) ?></legend>
    <?= checkbox('allow_text', t('Typed response'), (bool) $val('allow_text', 1)) ?>
    <?= checkbox('allow_files', t('File attachments'), (bool) $val('allow_files', 0), t('PDF, Word, PowerPoint, text and images, up to 5 files.')) ?>
    <?= checkbox('allow_resubmission', t('Allow resubmission before the deadline'), (bool) $val('allow_resubmission', 0)) ?>
  </fieldset>
<?php endif; ?>

<fieldset>
  <legend><?= e(t('Feedback and answers')) ?></legend>
  <div class="form-grid">
    <?= field('select', 'feedback_release', t('When students see grades'), ['options' => ['manual' => t('When I release them'), 'immediate' => $isQuiz ? t('Right after submitting (automatically marked questions only)') : t('As soon as I grade')], 'value' => (string) $val('feedback_release', 'manual')]) ?>
    <?php if ($isQuiz): ?>
      <?= field('select', 'answer_key_release', t('When students see correct answers'), ['options' => ['after_due' => t('After the due date'), 'after_submit' => t('Right after they submit'), 'manual' => t('When I release them'), 'never' => t('Never')], 'value' => (string) $val('answer_key_release', 'after_due')]) ?>
    <?php else: ?>
      <input type="hidden" name="answer_key_release" value="never">
    <?php endif; ?>
  </div>
</fieldset>
