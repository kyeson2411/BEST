<?php
/**
 * One student's own due date (a make-up date for work done in class), extra quiz minutes or excused status,
 * with the list of current adjustments. Shown on the submissions page and on the score grid.
 * @var array $a @var array<int,string> $students @var array<int,array> $overrides @var int|null $selected
 * @var string $page submissions|scores (where "Change" links lead)
 */
$base = '/teacher/assignments/' . (int) $a['id'];
$timed = $a['kind'] === 'quiz' && (int) $a['time_limit_minutes'] > 0;
$paper = $a['kind'] === 'paper';
$current = $selected !== null ? ($overrides[$selected] ?? null) : null;
$describe = static function (array $ov) use ($timed, $paper): array {
    $out = [];
    if ((int) $ov['excused'] === 1) {
        $out[] = t('Excused');
    }
    if ((int) ($ov['absent'] ?? 0) === 1) {
        $out[] = t('Absent');
    }
    if ($ov['due_at'] !== null) {
        $out[] = $paper ? t('Make-up :date', ['date' => fmt_date($ov['due_at'], 'datetime_short')]) : t('Due :date', ['date' => fmt_date($ov['due_at'], 'datetime_short')]);
    }
    if ($timed && (int) $ov['extra_minutes'] > 0) {
        $out[] = tp('+:count minute', '+:count minutes', (int) $ov['extra_minutes']);
    }
    return $out;
};
?>
<section class="section" id="adjust" aria-labelledby="adjust-title" style="max-width: 760px; margin-top: 32px">
  <div class="section__head"><h2 class="section__title" id="adjust-title"><?= e($paper ? t('Make-up dates and excused work') : t('Extensions and excused work')) ?></h2></div>
  <p class="meta"><?php if ($paper): ?><?= e(t('Give a student who was absent their own make-up date, or excuse them. Until the make-up date they are not counted as absent. Excused work is not counted and never shows as missing. No reason is recorded.')) ?>
    <?php else: ?><?= e($timed
      ? t('Give one student their own due date or extra minutes on the time limit, or excuse them. They see their own dates everywhere; excused work is not counted and never shows as missing. No reason is recorded.')
      : t('Give one student their own due date, or excuse them. They see their own date everywhere; excused work is not counted and never shows as missing. No reason is recorded.')) ?><?php endif; ?></p>
  <?php if ($a['answer_key_release'] === 'after_due' && $a['kind'] === 'quiz'): ?>
    <p class="meta"><?= e(t('Correct answers wait until the latest extension has passed, so no one sees them before taking the quiz.')) ?></p>
  <?php endif; ?>
  <form method="post" action="<?= e(url($base . '/overrides')) ?>" class="panel">
    <?= csrf_field() ?>
    <div class="form-grid">
      <?= field('select', 'student_id', t('Student'), ['options' => ['' => t('Choose a student')] + $students, 'value' => (string) ($selected ?? ''), 'required' => true]) ?>
      <?= field('datetime-local', 'due_at', $paper ? t('Make-up date') : t('Their own due date'), ['value' => $current && $current['due_at'] ? fmt_date($current['due_at'], 'input') : '', 'optional_label' => true,
          'hint' => $a['due_at'] ? ($paper ? t('Leave empty if there is no make-up. The class did it on :date.', ['date' => fmt_date($a['due_at'], 'datetime_short')]) : t('Leave empty to keep the class due date, :date.', ['date' => fmt_date($a['due_at'], 'datetime_short')])) : t('Leave empty to keep no due date.')]) ?>
      <?php if ($timed): ?>
        <?= field('number', 'extra_minutes', t('Extra minutes'), ['value' => (string) ($current['extra_minutes'] ?? '0'), 'attrs' => ['min' => 0, 'max' => \Sibol\Services\Overrides::MAX_EXTRA_MINUTES],
            'hint' => t('Added to the :n-minute time limit, including an attempt already under way.', ['n' => (int) $a['time_limit_minutes']])]) ?>
      <?php endif; ?>
    </div>
    <?= checkbox('excused', t('Excuse this student from this work'), (bool) ($current['excused'] ?? false), t('It is not counted in their grade and never shows as missing.')) ?>
    <button class="btn btn--primary btn--sm" type="submit"><?= e(t('Save for this student')) ?></button>
  </form>
  <?php if ($overrides): ?>
    <h3 class="section__title" style="margin-top: 24px" id="current-adjust"><?= e(t('Current adjustments')) ?></h3>
    <ul class="rows" aria-labelledby="current-adjust">
      <?php foreach ($overrides as $studentId => $ov): ?>
        <li class="row">
          <div class="row__main">
            <span class="row__title"><?= e($students[$studentId]) ?></span>
            <div class="row__meta"><?php foreach ($describe($ov) as $note): ?><span><?= e($note) ?></span><?php endforeach; ?></div>
          </div>
          <div class="row__aside">
            <a class="btn btn--quiet btn--sm" href="<?= e(url($base . '/' . $page, ['student' => $studentId]) . '#adjust') ?>"><?= e(t('Change')) ?><span class="sr-only">: <?= e($students[$studentId]) ?></span></a>
            <form method="post" action="<?= e(url($base . '/overrides/' . (int) $studentId . '/remove')) ?>"><?= csrf_field() ?><button class="btn btn--danger-quiet btn--sm" type="submit"><?= e(t('Remove')) ?><span class="sr-only">: <?= e($students[$studentId]) ?></span></button></form>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
