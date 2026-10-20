<?php
/**
 * @var array $sub @var array $a @var array $student @var list<array> $questions @var array $answers @var list<array> $files @var array $rubric
 * @var array $rubricScores @var list<array> $history @var array|null $suggestion @var bool $aiAvailable @var bool $studentAiOn @var int|null $prevId @var int|null $nextId
 * @var bool $canReopen @var int $reopenMinutes
 */
use Sibol\Core\Uploads;

$released = $sub['feedback_status'] === 'released';
$base = '/teacher/submissions/' . (int) $sub['id'];
$historyLabels = ['submitted' => t('Submitted by the student'), 'graded' => t('Grade saved'), 'released' => t('Released to the student'), 'returned' => t('Returned for changes'), 'reopened' => t('Reopened for the student')];
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(user_name($student)) ?></h1>
    <p class="page-lead"><?= e($a['title']) ?>. <?= e(t('Attempt :n, submitted :when', ['n' => (int) $sub['attempt_no'], 'when' => $sub['submitted_at'] ? fmt_date($sub['submitted_at'], 'datetime') : '—'])) ?>
      <?= (int) $sub['is_late'] ? badge(t('Late'), 'amber') : '' ?> <?= $released ? badge(t('Released'), 'leaf') : ($sub['grading_status'] === 'graded' ? badge(t('Graded, not released'), 'amber') : badge(t('To grade'), 'amber')) ?></p>
  </div>
  <div class="page-actions">
    <?php if ($prevId): ?><a class="btn btn--secondary" href="<?= e(url('/teacher/submissions/' . $prevId)) ?>"><?= icon('chevron-left', 'icon--sm') ?><?= e(t('Previous')) ?></a><?php endif; ?>
    <?php if ($nextId): ?><a class="btn btn--secondary" href="<?= e(url('/teacher/submissions/' . $nextId)) ?>"><?= e(t('Next')) ?><?= icon('chevron-right', 'icon--sm') ?></a><?php endif; ?>
  </div>
</div>

<form method="post" action="<?= e(url($base . '/grade')) ?>" id="grade-form" data-unsaved-warning data-i18n="<?= json_attr(\Sibol\Services\ScriptStrings::grading()) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="next_id" value="<?= (int) $nextId ?>">
  <div class="layout-split layout-split--wide-aside">
    <div class="stack">
      <?php if (trim((string) $sub['body']) !== '' || $files): ?>
        <section class="section" aria-labelledby="work-title">
          <div class="section__head"><h2 class="section__title" id="work-title"><?= e(t('Student work')) ?></h2></div>
          <?php if (trim((string) $sub['body']) !== ''): ?><div class="panel prose" style="white-space: pre-wrap; max-width: none"><?= e($sub['body']) ?></div><?php endif; ?>
          <?php if ($files): ?>
            <ul class="rows"><?php foreach ($files as $f): ?><li class="row"><span class="row__lead"><?= icon('paperclip') ?></span><div class="row__main"><a class="row__title" href="<?= e(url('/submission-files/' . (int) $f['id'])) ?>" target="_blank" rel="noopener"><?= e($f['original_filename']) ?></a><div class="row__meta"><span><?= e(Uploads::humanSize((int) $f['size_bytes'])) ?></span></div></div></li><?php endforeach; ?></ul>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <?php if ($questions): ?>
        <section class="section" aria-labelledby="ans-title">
          <div class="section__head"><h2 class="section__title" id="ans-title"><?= e(t('Answers')) ?></h2></div>
          <ol class="questions">
            <?php foreach ($questions as $q):
                $ans = $answers[(int) $q['id']] ?? null;
                $resp = (string) ($ans['response'] ?? '');
                $key = is_array($q['answer_key']) ? $q['answer_key'] : [];
                $opts = is_array($q['options']) ? $q['options'] : [];
                $text = static function (string $k) use ($opts): string { foreach ($opts as $o) { if ($o['key'] === $k) { return $k . '. ' . $o['text']; } } return $k; };
            ?>
              <li class="question">
                <div class="question__head"><span class="question__no" aria-hidden="true"></span><div class="question__prompt"><?= markdown($q['prompt']) ?></div></div>
                <div class="question__body">
                  <p style="margin:0 0 4px"><strong><?= e(t('Answer:')) ?></strong>
                    <?php if ($resp === ''): ?><span class="meta"><?= e(t('No answer')) ?></span>
                    <?php elseif ($q['qtype'] === 'multiple_choice'): ?><?= e($text($resp)) ?>
                    <?php elseif ($q['qtype'] === 'true_false'): ?><?= e($resp === 'true' ? t('True') : t('False')) ?>
                    <?php else: ?><span style="white-space:pre-wrap"><?= e($resp) ?></span><?php endif; ?>
                    <?php if (($ans['is_correct'] ?? null) !== null): ?> <?= (int) $ans['is_correct'] ? badge(t('Correct'), 'leaf', 'check') : badge(t('Not correct'), 'coral', 'x') ?><?php endif; ?>
                  </p>
                  <?php if ($q['qtype'] === 'multiple_choice'): ?><p class="meta" style="margin:0"><?= e(t('Key: :k', ['k' => $text((string) ($key['correct'] ?? ''))])) ?></p>
                  <?php elseif ($q['qtype'] === 'true_false'): ?><p class="meta" style="margin:0"><?= e(t('Key: :k', ['k' => !empty($key['correct']) ? t('True') : t('False')])) ?></p>
                  <?php elseif ($q['qtype'] === 'short_answer'): ?><p class="meta" style="margin:0"><?= e(t('Accepted: :k', ['k' => implode(' / ', (array) ($key['accepted'] ?? []))])) ?></p><?php endif; ?>
                  <div class="form-grid" style="grid-template-columns: 140px 1fr; margin-top: 10px">
                    <div class="field" style="margin:0"><label class="field__label" for="pts-<?= (int) $q['id'] ?>"><?= e(t('Points (of :n)', ['n' => fmt_number($q['points'], 2)])) ?></label>
                      <input class="input" id="pts-<?= (int) $q['id'] ?>" type="number" name="points[<?= (int) $q['id'] ?>]" min="0" max="<?= e((string) $q['points']) ?>" step="0.5" value="<?= e($ans !== null && $ans['points_awarded'] !== null ? fmt_number($ans['points_awarded'], 2) : '') ?>"<?= $q['qtype'] === 'essay' ? ' required' : '' ?>></div>
                    <div class="field" style="margin:0"><label class="field__label" for="cm-<?= (int) $q['id'] ?>"><?= e(t('Comment')) ?> <span class="field__optional"><?= e(t('optional')) ?></span></label>
                      <input class="input" id="cm-<?= (int) $q['id'] ?>" name="comments[<?= (int) $q['id'] ?>]" value="<?= e((string) ($ans['teacher_comment'] ?? '')) ?>" maxlength="2000" data-comment-target></div>
                  </div>
                </div>
              </li>
            <?php endforeach; ?>
          </ol>
        </section>
      <?php endif; ?>

      <section class="section" aria-labelledby="fb-title">
        <div class="section__head"><h2 class="section__title" id="fb-title"><?= e(t('Feedback for the student')) ?></h2></div>
        <?= field('textarea', 'feedback', t('Overall comment'), ['value' => (string) $sub['feedback'], 'rows' => 5, 'attrs' => ['maxlength' => 10000, 'data-charcount' => true, 'data-suggest-target' => 'comment', 'data-comment-target' => 'overall'], 'hint' => t('Specific, kind, and focused on the work. The student sees this when you release it.')]) ?>
        <?= partial('comment_bank', ['bank' => $bank, 'rubric' => $rubric]) ?>
      </section>
    </div>

    <aside class="stack">
      <section class="panel" aria-labelledby="score-title">
        <h2 class="panel__title" id="score-title"><?= e(t('Grade')) ?></h2>
        <?php if ($rubric && !$questions): ?>
          <?php foreach ($rubric as $c): ?>
            <div class="field">
              <label class="field__label" for="rb-<?= e($c['id']) ?>"><?= e($c['title']) ?> <span class="field__optional"><?= e(t('of :n', ['n' => fmt_number($c['points'], 2)])) ?></span></label>
              <?php if (!empty($c['description'])): ?><p class="field__hint"><?= e($c['description']) ?></p><?php endif; ?>
              <input class="input" id="rb-<?= e($c['id']) ?>" type="number" name="rubric[<?= e($c['id']) ?>]" min="0" max="<?= e((string) $c['points']) ?>" step="0.5" value="<?= e(isset($rubricScores[$c['id']]) ? fmt_number($rubricScores[$c['id']], 2) : '') ?>" data-rubric-input="<?= e($c['id']) ?>" required>
            </div>
          <?php endforeach; ?>
          <p class="meta"><?= e(t('Total')) ?>: <strong data-rubric-sum>—</strong> / <?= e(fmt_number($a['points_possible'], 2)) ?></p>
        <?php elseif (!$questions): ?>
          <?= field('number', 'score', t('Score (of :n)', ['n' => fmt_number($a['points_possible'], 2)]), ['value' => $sub['score'] !== null ? fmt_number($sub['score'], 2) : '', 'required' => true, 'attrs' => ['min' => 0, 'max' => (string) $a['points_possible'], 'step' => '0.5']]) ?>
        <?php else: ?>
          <p><?= e(t('Score so far:')) ?> <strong><?= $sub['score'] !== null ? e(fmt_number($sub['score'], 2)) : '—' ?></strong> / <?= e(fmt_number($a['points_possible'], 2)) ?></p>
          <p class="meta"><?= e(t('Automatically marked answers are pre-filled; you can change any of them.')) ?></p>
        <?php endif; ?>
        <?php if ((int) $sub['is_late'] && $a['late_policy'] === 'penalty'): ?><p class="meta"><?= e(t('A :p% late penalty is applied when you save.', ['p' => (int) $a['late_penalty_pct']])) ?></p><?php endif; ?>
        <div class="stack-sm" style="margin-top: 12px">
          <button class="btn btn--primary btn--block" type="submit" name="action" value="release" data-busy="<?= e(t('Releasing…')) ?>"><?= icon('send', 'icon--sm') ?><?= e($released ? t('Save and update release') : t('Save and release')) ?></button>
          <button class="btn btn--secondary btn--block" type="submit" name="action" value="save" data-busy="<?= e(t('Saving…')) ?>"><?= e(t('Save without releasing')) ?></button>
          <?php if ($nextId): ?><button class="btn btn--quiet btn--block" type="submit" name="then" value="next"><?= e(t('Save and open next student')) ?></button><?php endif; ?>
        </div>
      </section>

      <?php if ($rubric && trim((string) $sub['body']) !== ''): ?>
        <section class="panel panel--sunken" id="suggestion" aria-labelledby="sug-title">
          <h2 class="panel__title" id="sug-title"><?= e(t('Rubric suggestion')) ?></h2>
          <?php if ($suggestion): ?>
            <p class="meta"><?= e(t('Suggested :when by :model. It is advice only; the grade is yours.', ['when' => fmt_rel($suggestion['created_at']), 'model' => $suggestion['model']])) ?></p>
            <ul class="rows">
              <?php foreach ($suggestion['criteria'] as $c): $title = ''; foreach ($rubric as $r) { if ($r['id'] === $c['id']) { $title = $r['title']; } } ?>
                <li class="row" style="display:block; padding:8px 0" data-suggest-criterion="<?= e($c['id']) ?>" data-points="<?= e((string) $c['points']) ?>">
                  <strong><?= e($title) ?>: <?= e(fmt_number($c['points'], 2)) ?></strong>
                  <span class="meta" style="display:block"><?= e($c['reason']) ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
            <?php if ($suggestion['comment']): ?><p style="font-size:.9375rem" data-suggest-comment><?= e($suggestion['comment']) ?></p><?php endif; ?>
            <button type="button" class="btn btn--secondary btn--sm" data-apply-suggestion><?= e(t('Copy into the form')) ?></button>
          <?php elseif (!$studentAiOn): ?>
            <p style="font-size:.9375rem"><?= e(t('AI is switched off for this student, so their work is not sent to an AI service.')) ?></p>
          <?php else: ?>
            <p style="font-size:.9375rem"><?= e($aiAvailable ? t('Get a suggested score for each rubric criterion, with reasons. The student\'s typed answer is sent to the AI service without their name.') : t('Connect an AI provider to get rubric suggestions. Demo mode cannot evaluate writing.')) ?></p>
          <?php endif; ?>
          <?php if ($aiAvailable && $studentAiOn): ?>
            <button class="btn btn--secondary btn--sm" type="submit" form="suggest-form" data-busy="<?= e(t('Reading…')) ?>"><?= e($suggestion ? t('Ask again') : t('Suggest scores')) ?></button>
          <?php endif; ?>
        </section>
      <?php endif; ?>
    </aside>
  </div>
</form>
<form method="post" action="<?= e(url($base . '/suggest')) ?>" id="suggest-form" hidden><?= csrf_field() ?></form>

<div class="layout-split" style="margin-top: 32px">
  <section class="section" aria-labelledby="hist-title">
    <div class="section__head"><h2 class="section__title" id="hist-title"><?= e(t('History')) ?></h2></div>
    <?php if (!$history): ?><p class="meta"><?= e(t('No grading activity yet.')) ?></p><?php else: ?>
      <ul class="entries">
        <?php foreach ($history as $h): $d = json_decode((string) $h['detail'], true) ?: []; ?>
          <li class="entry">
            <?= partial('entry_date', ['at' => $h['created_at']]) ?>
            <div class="entry__main"><span class="row__title"><?= e($historyLabels[$h['action']] ?? $h['action']) ?></span>
              <div class="row__meta"><span><?= e(user_name($h)) ?></span><span><?= e(fmt_date($h['created_at'], 'time')) ?></span>
                <?php if (array_key_exists('new_score', $d) && $d['new_score'] !== null): ?><span><?= e(t('Score :s', ['s' => fmt_number($d['new_score'], 2)])) ?></span><?php endif; ?></div></div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
  <?php if ($a['kind'] === 'assignment' && $sub['status'] === 'submitted'): ?>
    <section class="panel" aria-labelledby="ret-title">
      <h2 class="panel__title" id="ret-title"><?= e(t('Return for changes')) ?></h2>
      <form method="post" action="<?= e(url($base . '/return')) ?>" data-confirm="<?= e(t('The student can edit and submit again. Any score you entered is cleared.')) ?>" data-confirm-title="<?= e(t('Return this work?')) ?>" data-confirm-action="<?= e(t('Return')) ?>">
        <?= csrf_field() ?>
        <?= field('textarea', 'note', t('What should the student change?'), ['rows' => 3, 'required' => true, 'attrs' => ['maxlength' => 2000]]) ?>
        <button class="btn btn--secondary" type="submit"><?= icon('rotate-ccw', 'icon--sm') ?><?= e(t('Return to student')) ?></button>
      </form>
    </section>
  <?php endif; ?>
  <?php if ($canReopen): ?>
    <section class="panel" aria-labelledby="reopen-title">
      <h2 class="panel__title" id="reopen-title"><?= e(t('Reopen this attempt')) ?></h2>
      <p class="meta"><?= e(t('For example when time ran out while the student was offline. Their saved answers stay; marks are cleared until they submit again. The time starts when they open it.')) ?></p>
      <form method="post" action="<?= e(url($base . '/reopen')) ?>" data-confirm="<?= e(t('The student can change their answers and submit again. Marks for this attempt are cleared until then.')) ?>" data-confirm-title="<?= e(t('Reopen this attempt?')) ?>" data-confirm-action="<?= e(t('Reopen')) ?>">
        <?= csrf_field() ?>
        <?= field('number', 'minutes', t('Minutes to finish'), ['value' => (string) $reopenMinutes, 'required' => true, 'attrs' => ['min' => 1, 'max' => \Sibol\Services\Overrides::MAX_EXTRA_MINUTES]]) ?>
        <button class="btn btn--secondary" type="submit"><?= icon('timer', 'icon--sm') ?><?= e(t('Reopen for the student')) ?></button>
      </form>
    </section>
  <?php endif; ?>
</div>
