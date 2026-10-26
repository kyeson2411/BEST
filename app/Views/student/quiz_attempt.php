<?php
/** @var array $assignment @var array $submission @var list<array> $questions @var array<int,string> $saved */
$deadline = $submission['deadline_at'] ? \Sibol\Core\Dates::format($submission['deadline_at'], 'iso') : null;
$answered = count(array_filter($saved, static fn ($v) => trim((string) $v) !== ''));
// Anti-cheating (anticheat.js): copy and paste off in the quiz, and leaving the page recorded for the teacher.
// Set on the attempt when it started, so a change in the settings does not affect an attempt under way.
$guarded = (int) ($submission['anti_cheating'] ?? 0) === 1;
?>
<?php if ($guarded): ?>
<div data-anticheat data-anticheat-target="#quiz-form" data-anticheat-url="<?= e(url('/student/assignments/' . (int) $assignment['id'] . '/attempt/activity')) ?>"
     data-anticheat-left="<?= (int) $submission['left_page_count'] ?>" data-i18n="<?= json_attr(\Sibol\Services\ScriptStrings::antiCheat()) ?>">
<?php endif; ?>
<form method="post" action="<?= e(url('/student/assignments/' . (int) $assignment['id'] . '/attempt')) ?>" id="quiz-form" data-quiz
      data-attempt="<?= (int) $submission['id'] ?>" data-autosave-url="<?= e(url('/student/assignments/' . (int) $assignment['id'] . '/attempt')) ?>"
      data-assignment-url="<?= e(url('/student/assignments/' . (int) $assignment['id'])) ?>" data-no-busy
      data-i18n="<?= json_attr(\Sibol\Services\ScriptStrings::quiz()) ?>">
  <?= csrf_field() ?>
  <div class="quiz-bar">
    <div>
      <h1 class="page-title" style="font-size:1.5rem"><?= e($assignment['title']) ?></h1>
      <p class="meta" style="margin:2px 0 0"><?= e($assignment['class_name']) ?>, <?= e(t('attempt :n', ['n' => (int) $submission['attempt_no']])) ?></p>
    </div>
    <div class="cluster">
      <span class="meta" data-save-status aria-live="polite"><?= e(tp(':count of :total answered', ':count of :total answered', $answered, ['total' => count($questions)])) ?></span>
      <?php if ($deadline): ?>
        <span class="timer" data-countdown="<?= e($deadline) ?>" data-autosubmit-form="#quiz-form" role="timer" aria-label="<?= e(t('Time left')) ?>">
          <?= icon('timer', 'icon--sm') ?><span data-countdown-label></span>
        </span>
      <?php endif; ?>
    </div>
    <?php /* Connection notices from quiz.js (offline, answers restored, back online), kept in the sticky bar so
             they stay in view; the same text goes to the live region below for screen readers. */ ?>
    <div class="callout callout--amber" data-connection hidden style="flex: 1 1 100%"><?= icon('wifi-off') ?><p data-connection-text></p></div>
    <?php if ($guarded): ?>
      <p class="anticheat-line" style="flex: 1 1 100%"><?= icon('shield-check', 'icon--sm') ?><span><?= e(t('Anti-cheating is on: copy and paste are off, and leaving this page is recorded for your teacher.')) ?></span></p>
      <p class="anticheat-note" data-anticheat-note role="status" aria-live="polite" hidden style="flex: 1 1 100%"></p>
    <?php endif; ?>
  </div>
  <p class="sr-only" role="status" aria-live="polite" data-connection-live></p>

  <?php if (trim((string) $assignment['instructions']) !== ''): ?>
    <details class="how" style="margin-bottom: 16px">
      <summary><?= e(t('Instructions')) ?></summary>
      <div class="prose"><?= markdown($assignment['instructions']) ?></div>
    </details>
  <?php endif; ?>

  <ol class="questions">
    <?php foreach ($questions as $q): $qid = (int) $q['id']; $value = $saved[$qid] ?? ''; $name = 'answers[' . $qid . ']'; ?>
      <li class="question" id="q-<?= $qid ?>">
        <fieldset>
          <legend class="question__head" style="width:100%">
            <span class="question__no" aria-hidden="true"></span>
            <span class="question__prompt"><?= markdown($q['prompt']) ?></span>
            <span class="question__points"><?= e(tp(':count point', ':count points', (int) round($q['points']))) ?></span>
          </legend>
          <div class="question__body">
            <?php if ($q['qtype'] === 'multiple_choice'): ?>
              <div class="choice-list">
                <?php foreach ($q['options'] as $opt): ?>
                  <label class="choice">
                    <input type="radio" name="<?= e($name) ?>" value="<?= e($opt['key']) ?>"<?= checked($value === $opt['key']) ?>>
                    <span><span class="choice__title"><?= e($opt['key']) ?>.</span> <?= e($opt['text']) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            <?php elseif ($q['qtype'] === 'true_false'): ?>
              <div class="choice-list" style="grid-template-columns: repeat(2, minmax(0, 220px))">
                <label class="choice"><input type="radio" name="<?= e($name) ?>" value="true"<?= checked($value === 'true') ?>><span class="choice__title"><?= e(t('True')) ?></span></label>
                <label class="choice"><input type="radio" name="<?= e($name) ?>" value="false"<?= checked($value === 'false') ?>><span class="choice__title"><?= e(t('False')) ?></span></label>
              </div>
            <?php elseif ($q['qtype'] === 'short_answer'): ?>
              <label class="sr-only" for="a-<?= $qid ?>"><?= e(t('Your answer')) ?></label>
              <input class="input" id="a-<?= $qid ?>" type="text" name="<?= e($name) ?>" value="<?= e($value) ?>" maxlength="500" autocomplete="off" style="max-width: 480px">
            <?php else: ?>
              <label class="sr-only" for="a-<?= $qid ?>"><?= e(t('Your answer')) ?></label>
              <textarea class="input textarea" id="a-<?= $qid ?>" name="<?= e($name) ?>" rows="6" maxlength="10000"><?= e($value) ?></textarea>
              <p class="meta" style="margin-top:4px"><?= e(t('Your teacher marks this answer.')) ?></p>
            <?php endif; ?>
          </div>
        </fieldset>
      </li>
    <?php endforeach; ?>
  </ol>

  <div class="form-actions form-actions--sticky">
    <button class="btn btn--secondary" type="submit" name="action" value="save"><?= icon('save', 'icon--sm') ?><?= e(t('Save answers')) ?></button>
    <button class="btn btn--primary" type="submit" name="action" value="submit"
            data-confirm="<?= e(t('You cannot change your answers after submitting.')) ?>" data-confirm-title="<?= e(t('Submit this quiz?')) ?>" data-confirm-action="<?= e(t('Submit quiz')) ?>"><?= icon('send', 'icon--sm') ?><?= e(t('Submit quiz')) ?></button>
    <span class="meta"><?= e(t('Answers save as you go and stay on this device if the connection drops.')) ?></span>
  </div>
</form>
<?php if ($guarded): ?>
  <dialog class="dialog" data-anticheat-dialog aria-labelledby="anticheat-title" aria-describedby="anticheat-text">
    <form method="dialog">
      <div class="dialog__body">
        <h2 class="dialog__title" id="anticheat-title"><?= e(t('You left the quiz page')) ?></h2>
        <p class="dialog__text" id="anticheat-text" data-anticheat-count></p>
      </div>
      <div class="dialog__actions">
        <button type="submit" class="btn btn--primary" autofocus><?= e(t('Back to the quiz')) ?></button>
      </div>
    </form>
  </dialog>
</div>
<?php endif; ?>
