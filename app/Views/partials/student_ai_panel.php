<?php
/**
 * A student's AI companion setting and their guardian's recorded answers, for an administrator or adviser.
 * @var array $student user row @var list<array> $answers from AiAccess::guardianAnswers @var string $action base path for the forms
 */
use Sibol\Core\Dates;
use Sibol\Services\AiAccess;
use Sibol\Services\Settings;

$explicit = AiAccess::explicit($student);
$schoolOn = (bool) Settings::get((int) $student['organization_id'], 'ai.students_start_on', true);
$on = AiAccess::studentOn($student);
$setting = $explicit === null ? 'school' : ($explicit ? 'on' : 'off');
$sid = (int) $student['id'];
$today = Dates::now()->format('Y-m-d');
?>
<section class="panel" aria-labelledby="ai-panel-title-<?= $sid ?>" id="ai-companion">
  <h2 class="panel__title" id="ai-panel-title-<?= $sid ?>"><?= e(t('AI companion')) ?></h2>
  <p style="margin: 0 0 12px"><?= $on ? badge(t('On'), 'leaf', 'circle-check') : badge(t('Off'), 'neutral', 'circle-x') ?>
    <span class="meta"><?= e($on ? t('The companion and AI practice are available to this student.') : t('The companion and AI practice are hidden for this student, and nothing about them is sent to an AI service.')) ?></span></p>
  <?php if ($on && ($answers[0]['answer'] ?? null) === 'no'): ?>
    <p class="callout callout--amber" style="margin: 0 0 12px"><?= icon('triangle-alert') ?><span><?= e(t('The latest answer recorded from the guardian is no, but the companion is on for this student.')) ?></span></p>
  <?php endif; ?>
  <form method="post" action="<?= e(url($action . '/ai')) ?>">
    <?= csrf_field() ?>
    <fieldset class="fieldset-plain">
      <legend class="field__label"><?= e(t('Setting for this student')) ?></legend>
      <div class="choice-list">
        <label class="choice"><input type="radio" name="setting" value="school"<?= checked($setting === 'school') ?>><span><span class="choice__title"><?= e(t('Follow the school')) ?></span><span class="choice__desc"><?= e($schoolOn ? t('The school starts every student\'s AI on.') : t('The school starts every student\'s AI off.')) ?></span></span></label>
        <label class="choice"><input type="radio" name="setting" value="on"<?= checked($setting === 'on') ?>><span><span class="choice__title"><?= e(t('On for this student')) ?></span></span></label>
        <label class="choice"><input type="radio" name="setting" value="off"<?= checked($setting === 'off') ?>><span><span class="choice__title"><?= e(t('Off for this student')) ?></span><span class="choice__desc"><?= e(t('The rest of their work is unaffected.')) ?></span></span></label>
      </div>
    </fieldset>
    <button class="btn btn--secondary btn--sm" type="submit" style="margin-top: 12px"><?= e(t('Save setting')) ?></button>
  </form>

  <h3 class="section__title" style="margin-top: 20px"><?= e(t('Guardian\'s answer')) ?></h3>
  <p class="meta"><?= e(t('Record what the parent or guardian said about the AI companion. A no switches it off; a yes switches it on. Keep any signed form in the school\'s own files; B.E.S.T. Learning and Interactive Study System stores only the answer, its date and who entered it.')) ?></p>
  <form method="post" action="<?= e(url($action . '/guardian')) ?>" class="stack-sm">
    <?= csrf_field() ?>
    <fieldset class="fieldset-plain<?= field_error('answer') ? ' field--error' : '' ?>">
      <legend class="field__label"><?= e(t('Answer')) ?></legend>
      <div class="cluster">
        <label class="check-row"><input type="radio" name="answer" value="yes"> <span><?= e(t('Yes, they agree')) ?></span></label>
        <label class="check-row"><input type="radio" name="answer" value="no"> <span><?= e(t('No, they do not agree')) ?></span></label>
      </div>
      <?php if (field_error('answer')): ?><p class="field__error"><?= icon('circle-alert', 'icon--xs') ?><?= e(field_error('answer')) ?></p><?php endif; ?>
    </fieldset>
    <?= field('date', 'answered_on', t('Date of the answer'), ['value' => $today, 'required' => true, 'attrs' => ['max' => $today]]) ?>
    <button class="btn btn--secondary btn--sm" type="submit"><?= e(t('Record answer')) ?></button>
  </form>
  <?php if ($answers): ?>
    <ul class="rows" aria-label="<?= e(t('Recorded answers')) ?>" style="margin-top: 12px">
      <?php foreach ($answers as $a): ?>
        <li class="row" style="padding: 8px 0">
          <div class="row__main">
            <span class="row__title"><?= e($a['answer'] === 'yes' ? t('Yes') : t('No')) ?>, <?= e(fmt_date($a['answered_on'] . ' 12:00:00', 'date_short')) ?></span>
            <div class="row__meta"><span><?= e($a['given_name'] !== null ? t('Entered by :name (:role)', ['name' => user_name($a), 'role' => lcfirst_u(role_label((string) $a['role']))]) : t('Entered by a former staff member')) ?></span><span><?= e(fmt_date($a['created_at'], 'date_short')) ?></span></div>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
