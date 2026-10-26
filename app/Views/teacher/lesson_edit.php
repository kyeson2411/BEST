<?php /** @var array $lesson @var array $content @var list<array> $modules */
// Two parts: what students read when the lesson is published, and the teacher's own plan for class time.
// Each entry: label, hint, optional.
$forStudents = [
    'objectives' => [t('Learning objectives'), t('Two or three things students will be able to do. Students see this first, as “What you will learn”.'), false],
    'prior_knowledge' => [t('What students should already know'), t('A short reminder of earlier lessons.'), true],
    'explanation' => [t('Explanation'), t('The main teaching, written to students in words they understand. You need it to publish. Citations like [1] point to the sources on the right.'), false],
    'worked_example' => [t('Worked example'), t('One problem or case solved step by step.'), true],
];
$plan = [
    'activities' => [t('Activities'), t('What the class does, step by step, with the minutes for each.')],
    'checks' => [t('Checks for understanding'), t('Questions you ask during class to see who is following.')],
    'exit_ticket' => [t('Exit ticket'), t('One short task students answer at the end of class.')],
];
$planWritten = count(array_filter(array_keys($plan), static fn ($k) => trim((string) ($content[$k] ?? '')) !== ''));
$planErrors = array_filter(array_keys($plan), static fn ($k) => field_error($k) !== null);
$base = '/teacher/lessons/' . (int) $lesson['id'];
$published = $lesson['status'] === 'published';
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e($lesson['title']) ?></h1>
    <p class="page-lead"><?= e($lesson['class_name']) ?>
      <?= $published ? badge(t('Published'), 'leaf') : badge(t('Draft'), 'neutral') ?>
      <?php if ((int) $lesson['has_unpublished_changes']): ?><?= badge(t('Unpublished changes'), 'amber') ?><?php endif; ?>
      <?php if ((int) $lesson['ai_assisted']): ?><?= badge(t('Started from an AI draft'), 'outline') ?><?php endif; ?></p>
  </div>
  <div class="page-actions">
    <a class="btn btn--secondary" href="<?= e(url($base . '/preview')) ?>"><?= icon('eye', 'icon--sm') ?><?= e(t('Preview')) ?></a>
    <a class="btn btn--secondary" href="<?= e(url($base . '/dll')) ?>"><?= icon('printer', 'icon--sm') ?><?= e(t('Daily Lesson Log')) ?></a>
    <?php if (!$published || (int) $lesson['has_unpublished_changes']): ?>
      <form method="post" action="<?= e(url($base . '/publish')) ?>" data-confirm="<?= e($published ? t('Students will see the updated version.') : t('Students in :class will see this lesson and get a notification.', ['class' => $lesson['class_name']])) ?>" data-confirm-title="<?= e($published ? t('Publish changes?') : t('Publish lesson?')) ?>" data-confirm-action="<?= e(t('Publish')) ?>">
        <?= csrf_field() ?><button class="btn btn--primary" type="submit"><?= icon('send', 'icon--sm') ?><?= e($published ? t('Publish changes') : t('Publish')) ?></button></form>
    <?php endif; ?>
  </div>
</div>
<?php if ((int) $lesson['ai_assisted'] && !$published): ?>
  <div class="callout callout--amber" style="margin-bottom: 24px"><?= icon('square-pen') ?><p><?= e(t('This draft was written with AI from your materials. Check facts, examples and reading level, and adapt it to your class before publishing.')) ?></p></div>
<?php endif; ?>
<form method="post" action="<?= e(url($base)) ?>" data-unsaved-warning>
  <?= csrf_field() ?>
  <div class="layout-split">
    <div>
      <?= field('text', 'title', t('Lesson title'), ['value' => $lesson['title'], 'required' => true, 'attrs' => ['maxlength' => 200]]) ?>
      <fieldset class="lesson-part">
        <legend><?= e(t('What students read')) ?></legend>
        <p class="fieldset-hint"><?= icon('eye', 'icon--xs') ?><?= e(t('Students see these parts on the lesson page once you publish. Write to them directly.')) ?></p>
        <?php foreach ($forStudents as $key => [$label, $hint, $optional]): ?>
          <?= field('textarea', $key, $label, ['value' => (string) ($content[$key] ?? ''), 'rows' => $key === 'explanation' ? 8 : 3, 'hint' => $hint, 'optional_label' => $optional, 'attrs' => ['maxlength' => 12000, 'data-autogrow' => true]]) ?>
        <?php endforeach; ?>
      </fieldset>
      <details class="lesson-plan"<?= $planErrors ? ' open' : '' ?>>
        <summary>
          <?= icon('chevron-right', 'icon--sm lesson-plan__chevron') ?>
          <span class="lesson-plan__title"><?= e(t('Your teaching plan')) ?></span>
          <?= badge(t('Only you see this'), 'outline') ?>
          <span class="meta"><?= e($planWritten ? t(':count of 3 parts written', ['count' => $planWritten]) : t('Optional')) ?></span>
        </summary>
        <div class="lesson-plan__inner">
          <p class="fieldset-hint"><?= e(t('Notes for running the class. Only you see them; they print in the Daily Lesson Log. Students never see this part.')) ?></p>
          <?php foreach ($plan as $key => [$label, $hint]): ?>
            <?= field('textarea', $key, $label, ['value' => (string) ($content[$key] ?? ''), 'rows' => 3, 'hint' => $hint, 'optional_label' => true, 'attrs' => ['maxlength' => 12000, 'data-autogrow' => true]]) ?>
          <?php endforeach; ?>
        </div>
      </details>
      <div class="form-actions form-actions--sticky"><button class="btn btn--primary" type="submit" data-busy="<?= e(t('Saving…')) ?>"><?= e(t('Save draft')) ?></button><span class="meta"><?= e(t('Saving never changes what students see; publishing does.')) ?></span></div>
    </div>
    <aside class="stack">
      <section class="panel" aria-labelledby="meta-title">
        <h2 class="panel__title" id="meta-title"><?= e(t('Details')) ?></h2>
        <?= field('select', 'module_id', t('Module'), ['value' => (string) $lesson['module_id'], 'options' => ['' => t('No module')] + array_column($modules, 'title', 'id')]) ?>
        <?= field('number', 'duration_minutes', t('Length (minutes)'), ['value' => (string) $lesson['duration_minutes'], 'attrs' => ['min' => 5, 'max' => 600]]) ?>
        <?= field('text', 'reading_level', t('Reading level'), ['value' => (string) $lesson['reading_level'], 'placeholder' => t('For example, Grade 9'), 'optional_label' => true, 'attrs' => ['maxlength' => 40]]) ?>
        <?= field('text', 'lesson_format', t('Format'), ['value' => (string) $lesson['lesson_format'], 'placeholder' => t('For example, group work'), 'optional_label' => true, 'attrs' => ['maxlength' => 40]]) ?>
      </section>
      <section class="panel" aria-labelledby="lc-title">
        <h2 class="panel__title" id="lc-title"><?= e(t('Learning competencies')) ?></h2>
        <?php if (!$competencies): ?>
          <p class="meta" style="margin:0"><?= e(t('Your school has not added learning competencies for this class\'s subject yet. An administrator imports them under Subjects and terms.')) ?></p>
        <?php else: ?>
          <input type="hidden" name="competencies_shown" value="1">
          <?php $chosenCodes = array_column(array_filter($competencies, static fn ($c) => in_array((int) $c['id'], $chosen, true)), 'code'); ?>
          <?php if ($chosenCodes): ?><p class="code-text" style="margin:0 0 8px"><?= e(implode(', ', $chosenCodes)) ?></p><?php endif; ?>
          <details class="competency-pick"<?= $chosen ? '' : ' open' ?>>
            <summary><?= e($chosenCodes ? t('Change') : t('Choose')) ?></summary>
            <p class="fieldset-hint"><?= e(t('What this lesson teaches. Saved with the draft; students do not see it. The Daily Lesson Log lists them with their codes.')) ?></p>
            <?php $quarter = false; foreach ($competencies as $c): ?>
              <?php if ($c['quarter'] !== $quarter): $quarter = $c['quarter']; ?><p class="competency-pick__quarter"><?= e($quarter ? t('Quarter :n', ['n' => (int) $quarter]) : t('Any quarter')) ?></p><?php endif; ?>
              <?= checkbox('competencies[]', $c['code'], in_array((int) $c['id'], $chosen, true), $c['description'], (string) $c['id'], 'lc-' . (int) $c['id']) ?>
            <?php endforeach; ?>
          </details>
        <?php endif; ?>
      </section>
      <section class="panel" aria-labelledby="src-title">
        <h2 class="panel__title" id="src-title"><?= e(t('Sources')) ?></h2>
        <?php if (empty($content['sources'])): ?>
          <p class="meta"><?= e(t('No cited sources.')) ?></p>
        <?php else: ?>
          <ol style="padding-left:1.2em; margin:0">
            <?php foreach ($content['sources'] as $s): ?><li><?php if (!empty($s['material_id'])): ?><a href="<?= e(url('/materials/' . (int) $s['material_id'] . '/read')) ?>" target="_blank" rel="noopener"><?= e($s['title']) ?></a><?php else: ?><?= e($s['title']) ?><?php endif; ?><?= !empty($s['where']) ? ', ' . e($s['where']) : '' ?> <span class="meta">[<?= (int) $s['n'] ?>]</span></li><?php endforeach; ?>
          </ol>
        <?php endif; ?>
      </section>
    </aside>
  </div>
</form>
<div style="max-width: 420px; margin-top: 24px"><?= partial('copy_to_sections', $copyPanel) ?></div>
<div class="cluster" style="margin-top: 24px">
  <form method="post" action="<?= e(url($base . '/duplicate')) ?>"><?= csrf_field() ?><button class="btn btn--quiet btn--sm" type="submit"><?= icon('copy', 'icon--sm') ?><?= e(t('Duplicate')) ?></button></form>
  <?php if ($published): ?>
    <form method="post" action="<?= e(url($base . '/unpublish')) ?>" data-confirm="<?= e(t('Students will no longer see this lesson.')) ?>" data-confirm-title="<?= e(t('Unpublish?')) ?>" data-confirm-action="<?= e(t('Unpublish')) ?>"><?= csrf_field() ?><button class="btn btn--quiet btn--sm" type="submit"><?= e(t('Unpublish')) ?></button></form>
  <?php endif; ?>
  <form method="post" action="<?= e(url($base . '/archive')) ?>" data-confirm="<?= e(t('It will be hidden from your lesson list and from students.')) ?>" data-confirm-title="<?= e(t('Archive lesson?')) ?>" data-confirm-action="<?= e(t('Archive')) ?>" data-confirm-tone="danger"><?= csrf_field() ?><button class="btn btn--danger-quiet btn--sm" type="submit"><?= e(t('Archive')) ?></button></form>
</div>
