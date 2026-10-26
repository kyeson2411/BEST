<?php
/**
 * @var array $assignment @var array $state @var array|null $latest @var array|null $reviewSub @var array|null $snapshot
 * @var bool $answersReleased @var array $answers @var list<array> $resources @var list<array> $files @var array $rubric @var int $maxFileMb
 */
use Sibol\Core\Dates;
use Sibol\Core\Uploads;

$isQuiz = $assignment['kind'] === 'quiz';
// Done in class or on paper: nothing to hand in here; the teacher enters the score.
$isPaper = $assignment['kind'] === 'paper';
$due = Dates::due($assignment['due_at'], (string) $assignment['kind']);
[$statusLabel, $statusTone] = $state['status_label'];
$released = $reviewSub && $reviewSub['feedback_status'] === 'released';
$rubricScores = $released ? (json_decode((string) ($reviewSub['rubric_scores'] ?? ''), true) ?: []) : [];
$latePolicy = [
    'not_accepted' => t('Late work is not accepted.'),
    'accepted' => t('Late work is accepted and marked late.'),
    'penalty' => t('Late work loses :pct% of its points.', ['pct' => (int) $assignment['late_penalty_pct']]),
][$assignment['late_policy']];
$answerRule = [
    'never' => t('Correct answers are not shown for this quiz.'),
    'after_submit' => t('Correct answers appear after you submit.'),
    'after_due' => t('Correct answers appear after the due date.'),
    'manual' => t('Your teacher will release the correct answers.'),
][$assignment['answer_key_release']];
$editable = !$isQuiz && $state['can_submit'];
$returned = $latest && $latest['status'] === 'returned';
// "Stuck? Get a hint" while the work can still be done: not once it is handed in, graded, excused or past its date in class.
$stillOpen = !$state['excused'] && ($isPaper
    ? !$latest && ($assignment['due_at'] === null || $assignment['due_at'] > \Sibol\Core\Db::now())
    : ($state['can_submit'] || $state['can_start'] || $state['in_progress'] !== null));
// The student's own time: the time limit plus any extra minutes from their teacher.
$minutes = (int) $assignment['time_limit_minutes'] > 0 ? (int) $assignment['time_limit_minutes'] + (int) ($assignment['extra_minutes'] ?? 0) : 0;
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e($assignment['title']) ?></h1>
    <p class="page-lead"><?= e($assignment['class_name']) ?>, <?= e(work_kind((string) $assignment['kind'], true)) ?></p>
  </div>
</div>

<div class="layout-split">
  <div class="stack">
    <?php if ($state['excused']): ?>
      <div class="callout callout--leaf"><?= icon('circle-check') ?><div><p class="callout__title"><?= e(t('You are excused from this work')) ?></p><p><?= e(t('Your teacher excused you, so it is not counted in your grade and will not show as missing.')) ?></p></div></div>
    <?php elseif (!empty($assignment['extended']) || (int) ($assignment['extra_minutes'] ?? 0) > 0): ?>
      <div class="callout"><?= icon('clock') ?><div><p class="callout__title"><?= e(t('Your teacher gave you more time')) ?></p>
        <p><?php if (!empty($assignment['extended'])): ?><?= e(t('Your own due date is :date.', ['date' => fmt_date($assignment['due_at'], 'datetime')])) ?> <?php endif; ?><?php if ((int) ($assignment['extra_minutes'] ?? 0) > 0 && $minutes > 0): ?><?= e(tp('You have :count extra minute on the time limit.', 'You have :count extra minutes on the time limit.', (int) $assignment['extra_minutes'])) ?><?php endif; ?></p></div></div>
    <?php endif; ?>
    <?php if ($released): ?>
      <section class="section" aria-labelledby="grade-title">
        <div class="section__head"><h2 class="section__title" id="grade-title"><?= e(t('Your grade and feedback')) ?></h2></div>
        <div class="cluster" style="gap:24px; align-items:flex-end; margin-bottom: 12px">
          <p class="stat" style="border:0; padding:0">
            <span class="stat__value"><?= e(fmt_number($reviewSub['score'], 2)) ?><span class="muted" style="font-size:1.125rem"> / <?= e(fmt_number($reviewSub['points_possible'], 2)) ?></span></span>
            <span class="stat__label"><?= e(t('Released :when', ['when' => fmt_rel($reviewSub['released_at'])])) ?></span>
          </p>
          <?php if ((int) $reviewSub['is_late'] && $assignment['late_policy'] === 'penalty'): ?>
            <?= badge(t('Late: :pct% deducted', ['pct' => (int) $assignment['late_penalty_pct']]), 'amber') ?>
          <?php elseif ((int) $reviewSub['is_late']): ?>
            <?= badge(t('Submitted late'), 'amber') ?>
          <?php endif; ?>
        </div>
        <?php if ($rubric && $rubricScores): ?>
          <div class="table-frame" style="margin-bottom: 16px">
            <table class="table table--stack">
              <caption class="sr-only"><?= e(t('Rubric scores')) ?></caption>
              <thead><tr><th scope="col"><?= e(t('What you check')) ?></th><th scope="col" class="num"><?= e(t('Points')) ?></th></tr></thead>
              <tbody>
                <?php foreach ($rubric as $c): ?>
                  <tr>
                    <td class="cell-primary"><strong><?= e($c['title']) ?></strong><span class="table-sub"><?= e($c['description'] ?? '') ?></span></td>
                    <td class="num" data-label="<?= e(t('Points')) ?>"><?= e(fmt_number($rubricScores[$c['id']] ?? null, 2)) ?> / <?= e(fmt_number($c['points'], 2)) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
        <?php if (trim((string) $reviewSub['feedback']) !== ''): ?>
          <div class="callout callout--leaf">
            <?= icon('message-circle') ?>
            <div><p class="callout__title"><?= e(t('Comment from your teacher')) ?></p><div class="prose"><?= markdown($reviewSub['feedback']) ?></div></div>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <?php if ($returned): ?>
      <div class="callout callout--amber"><?= icon('rotate-ccw') ?><div><p class="callout__title"><?= e(t('Returned for changes')) ?></p><p><?= e(t('Your teacher sent this back so you can improve it. Make your changes below, then submit again.')) ?></p>
        <?php if (trim((string) $latest['feedback']) !== ''): ?><div class="prose"><?= markdown($latest['feedback']) ?></div><?php endif; ?></div></div>
    <?php endif; ?>

    <section class="section" aria-labelledby="instr-title">
      <div class="section__head"><h2 class="section__title" id="instr-title"><?= e(t('Instructions')) ?></h2></div>
      <div class="prose">
        <?= trim((string) $assignment['instructions']) !== '' ? markdown($assignment['instructions']) : '<p class="muted">' . e(t('No written instructions. Ask your teacher if anything is unclear.')) . '</p>' ?>
        <?php if (trim((string) $assignment['objectives']) !== ''): ?>
          <p><strong><?= e(t('Learning goals:')) ?></strong> <?= e($assignment['objectives']) ?></p>
        <?php endif; ?>
      </div>
    </section>

    <?php if ($rubric && !$released): ?>
      <section class="section" aria-labelledby="rubric-title">
        <div class="section__head"><h2 class="section__title" id="rubric-title"><?= e(t('How this is graded')) ?></h2></div>
        <div class="table-frame">
          <table class="table table--stack">
            <caption class="sr-only"><?= e(t('Rubric')) ?></caption>
            <thead><tr><th scope="col"><?= e(t('What you check')) ?></th><th scope="col"><?= e(t('What earns full points')) ?></th><th scope="col" class="num"><?= e(t('Points')) ?></th></tr></thead>
            <tbody>
              <?php foreach ($rubric as $c): ?>
                <tr>
                  <td class="cell-primary"><strong><?= e($c['title']) ?></strong></td>
                  <td data-label="<?= e(t('What earns full points')) ?>"><?= e($c['description'] ?? '') ?></td>
                  <td class="num" data-label="<?= e(t('Points')) ?>"><?= e(fmt_number($c['points'], 2)) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($resources): ?>
      <section class="section" aria-labelledby="res-title">
        <div class="section__head"><h2 class="section__title" id="res-title"><?= e(t('Resources from your teacher')) ?></h2></div>
        <ul class="rows">
          <?php foreach ($resources as $r): ?>
            <li class="row">
              <span class="row__lead"><?= icon($r['kind'] === 'link' ? 'link' : 'file-text') ?></span>
              <div class="row__main"><a class="row__title" href="<?= e(url('/materials/' . (int) $r['id'] . '/read')) ?>"><?= e($r['title']) ?></a></div>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>

    <?php if ($isPaper): ?>
      <section class="section" aria-labelledby="work-title" id="your-work">
        <div class="section__head"><h2 class="section__title" id="work-title"><?= e(t('In class or on paper')) ?></h2></div>
        <?php if ($state['excused']): ?>
          <p class="meta"><?= e(t('You are excused, so there is nothing to make up.')) ?></p>
        <?php elseif (!empty($assignment['absent'])): ?>
          <div class="callout callout--coral"><?= icon('circle-alert') ?><p><?= e(t('Your teacher marked you absent for this. Talk to them about making it up.')) ?></p></div>
        <?php elseif ($released): ?>
          <p class="meta"><?= e(t('Your teacher entered this score from your work in class.')) ?></p>
        <?php else: ?>
          <p><?= e($state['past_due'] ? t('You did this in class or on paper. Your teacher will enter your score here; you get a notification when it is released.') : t('You will do this in class or on paper on :date. There is nothing to hand in here.', ['date' => fmt_date($assignment['due_at'], 'date_long')])) ?></p>
        <?php endif; ?>
      </section>
    <?php elseif (!$isQuiz): ?>
      <section class="section" aria-labelledby="work-title" id="your-work">
        <div class="section__head"><h2 class="section__title" id="work-title"><?= e(t('Your work')) ?></h2></div>
        <?php if ($editable): ?>
          <form method="post" action="<?= e(url('/student/assignments/' . (int) $assignment['id'] . '/draft')) ?>" enctype="multipart/form-data" data-unsaved-warning id="work-form">
            <?= csrf_field() ?>
            <?php if ((int) $assignment['allow_text']): ?>
              <?= field('textarea', 'body', t('Your response'), ['value' => (string) ($latest['body'] ?? ''), 'rows' => 12, 'attrs' => ['maxlength' => 20000, 'data-charcount' => true], 'hint' => t('Save a draft as often as you like. Only Submit hands it in.')]) ?>
            <?php endif; ?>
            <?php if ((int) $assignment['allow_files']): ?>
              <?php if ($files): ?>
                <fieldset>
                  <legend class="field__label"><?= e(t('Attached files')) ?></legend>
                  <?php foreach ($files as $f): ?>
                    <div class="check">
                      <input class="check__input" type="checkbox" id="rm-<?= (int) $f['id'] ?>" name="remove_files[]" value="<?= (int) $f['id'] ?>">
                      <label class="check__label" for="rm-<?= (int) $f['id'] ?>"><?= e(t('Remove')) ?> <a href="<?= e(url('/submission-files/' . (int) $f['id'])) ?>"><?= e($f['original_filename']) ?></a> <span class="meta">(<?= e(Uploads::humanSize((int) $f['size_bytes'])) ?>)</span></label>
                    </div>
                  <?php endforeach; ?>
                </fieldset>
              <?php endif; ?>
              <div class="field<?= field_error('files') ? ' field--error' : '' ?>">
                <label class="field__label" for="f-files"><?= e(t('Attach files')) ?> <span class="field__optional"><?= e(t('optional')) ?></span></label>
                <p class="field__hint" id="f-files-hint"><?= e(t('PDF, Word, PowerPoint, text, JPG or PNG. Up to 5 files, :mb MB each.', ['mb' => $maxFileMb])) ?></p>
                <input class="input" id="f-files" type="file" name="files[]" multiple accept=".pdf,.docx,.pptx,.txt,.md,.jpg,.jpeg,.png" aria-describedby="f-files-hint<?= field_error('files') ? ' f-files-error' : '' ?>">
                <?php if (field_error('files')): ?><p class="field__error" id="f-files-error"><?= icon('circle-alert', 'icon--xs') ?><?= e(field_error('files')) ?></p><?php endif; ?>
              </div>
            <?php endif; ?>
            <div class="form-actions">
              <button class="btn btn--secondary" type="submit" data-busy="<?= e(t('Saving draft…')) ?>"><?= icon('save', 'icon--sm') ?><?= e(t('Save draft')) ?></button>
              <button class="btn btn--primary" type="submit" formaction="<?= e(url('/student/assignments/' . (int) $assignment['id'] . '/submit')) ?>" data-busy="<?= e(t('Submitting…')) ?>"
                      data-confirm="<?= e(t('After you submit, you cannot change your work unless your teacher returns it.')) ?>" data-confirm-title="<?= e(t('Submit your work?')) ?>" data-confirm-action="<?= e(t('Submit')) ?>"><?= icon('send', 'icon--sm') ?><?= e($returned ? t('Submit again') : t('Submit assignment')) ?></button>
              <?php if ($latest && $latest['status'] !== 'submitted'): ?><span class="meta"><?= e(t('Draft saved :when', ['when' => fmt_rel($latest['updated_at'])])) ?></span><?php endif; ?>
            </div>
          </form>
        <?php elseif ($latest && $latest['status'] === 'submitted'): ?>
          <div class="panel">
            <div class="cluster cluster--between" style="margin-bottom: 8px">
              <span class="status-line"><?= icon('circle-check', 'ok') ?><?= e(t('Submitted :when', ['when' => fmt_date($latest['submitted_at'], 'datetime')])) ?></span>
              <a href="<?= e(url('/student/submissions/' . (int) $latest['id'])) ?>"><?= e(t('Receipt')) ?> <span class="code-text"><?= e($latest['receipt_code']) ?></span></a>
            </div>
            <?php if (trim((string) $latest['body']) !== ''): ?><div class="prose" style="white-space:pre-wrap"><?= e($latest['body']) ?></div><?php endif; ?>
            <?php if ($files): ?>
              <ul class="rows">
                <?php foreach ($files as $f): ?>
                  <li class="row"><span class="row__lead"><?= icon('paperclip') ?></span><div class="row__main"><a class="row__title" href="<?= e(url('/submission-files/' . (int) $f['id'])) ?>"><?= e($f['original_filename']) ?></a></div></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
            <?php if (!$released): ?><p class="meta" style="margin:8px 0 0"><?= e(t('Your teacher will review it. You will get a notification when feedback is released.')) ?></p><?php endif; ?>
          </div>
        <?php else: ?>
          <div class="callout callout--coral"><?= icon('circle-alert') ?><p><?= e($state['closed'] ? t('This assignment is closed, so it no longer accepts work.') : t('The due date has passed and this assignment does not accept late work. Talk to your teacher if something got in the way.')) ?></p></div>
        <?php endif; ?>
      </section>
    <?php else: ?>
      <section class="section" aria-labelledby="attempts-title">
        <div class="section__head"><h2 class="section__title" id="attempts-title"><?= e(t('Your attempts')) ?></h2></div>
        <?php if ($state['in_progress']): ?>
          <div class="callout callout--amber" style="margin-bottom:16px"><?= icon('timer') ?><div>
            <p class="callout__title"><?= e(t('You have an attempt in progress')) ?></p>
            <?php if ($state['in_progress']['deadline_at']): ?><p><?= e(t('It must be submitted by :time.', ['time' => fmt_date($state['in_progress']['deadline_at'], 'datetime_short')])) ?></p><?php endif; ?>
            <a class="btn btn--primary" href="<?= e(url('/student/assignments/' . (int) $assignment['id'] . '/attempt')) ?>"><?= e(t('Resume quiz')) ?></a>
          </div></div>
        <?php elseif ($state['can_start']): ?>
          <form method="post" action="<?= e(url('/student/assignments/' . (int) $assignment['id'] . '/start')) ?>"
                data-confirm="<?= e($minutes ? t('The :n-minute timer starts now and keeps running even if you close the page.', ['n' => $minutes]) : t('Your answers save as you go. Submit when you are done.')) ?>"
                data-confirm-title="<?= e(t('Start the quiz?')) ?>" data-confirm-action="<?= e(t('Start quiz')) ?>" style="margin-bottom:16px">
            <?= csrf_field() ?>
            <button class="btn btn--primary" type="submit" data-busy="<?= e(t('Starting…')) ?>"><?= icon('play', 'icon--sm') ?><?= e($state['submitted_count'] ? t('Start another attempt') : t('Start quiz')) ?></button>
            <span class="meta" style="margin-left:8px"><?= e(tp(':count attempt left', ':count attempts left', (int) $state['attempts_left'])) ?></span>
          </form>
          <?php if (!empty($toReview)): ?>
            <p class="meta" style="margin:-4px 0 16px"><?= icon('rotate-ccw', 'icon--sm') ?> <a href="<?= e(url('/student/review', ['class' => (int) $assignment['class_id']])) ?>"><?= e(tp('Review the :count question you missed in this class first', 'Review the :count questions you missed in this class first', (int) $toReview)) ?></a></p>
          <?php endif; ?>
        <?php elseif (!$state['submissions']): ?>
          <div class="callout callout--coral"><?= icon('circle-alert') ?><p><?= e($state['closed'] ? t('This quiz is closed.') : t('The due date has passed and this quiz no longer accepts attempts.')) ?></p></div>
        <?php endif; ?>

        <?php $done = array_values(array_filter($state['submissions'], static fn ($s) => $s['status'] === 'submitted')); ?>
        <?php if ($done): ?>
          <div class="table-frame">
            <table class="table table--stack">
              <caption class="sr-only"><?= e(t('Submitted attempts')) ?></caption>
              <thead><tr><th scope="col"><?= e(t('Attempt')) ?></th><th scope="col"><?= e(t('Submitted')) ?></th><th scope="col"><?= e(t('Result')) ?></th><th scope="col" class="actions"><span class="sr-only"><?= e(t('Receipt')) ?></span></th></tr></thead>
              <tbody>
                <?php foreach ($done as $s): ?>
                  <tr>
                    <td class="cell-primary"><?= e(t('Attempt :n', ['n' => (int) $s['attempt_no']])) ?></td>
                    <td data-label="<?= e(t('Submitted')) ?>"><?= e(fmt_date($s['submitted_at'], 'datetime_short')) ?><?= (int) $s['is_late'] ? ' ' . badge(t('Late'), 'amber') : '' ?></td>
                    <td data-label="<?= e(t('Result')) ?>"><?= $s['feedback_status'] === 'released' ? score_html($s['score'], $s['points_possible']) : '<span class="meta">' . e(t('Waiting for your teacher')) . '</span>' ?></td>
                    <td class="actions"><a href="<?= e(url('/student/submissions/' . (int) $s['id'])) ?>"><?= e(t('Receipt')) ?></a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>

      <?php if ($reviewSub && $snapshot): ?>
        <section class="section" aria-labelledby="review-title">
          <div class="section__head"><h2 class="section__title" id="review-title"><?= e(t('Review your answers')) ?></h2></div>
          <?php if (!$answersReleased): ?>
            <p class="meta"><?= e($answerRule) ?></p>
          <?php else: ?>
            <ol class="questions">
              <?php foreach ($snapshot['questions'] as $q):
                  $ans = $answers[(int) $q['id']] ?? null;
                  $resp = (string) ($ans['response'] ?? '');
                  $key = $q['answer_key'] ?? [];
                  $options = $q['options'] ?? [];
                  $optText = static function (string $k) use ($options): string { foreach ($options as $o) { if ($o['key'] === $k) { return $k . '. ' . $o['text']; } } return $k; };
                  $correct = $ans['is_correct'] ?? null;
              ?>
                <li class="question">
                  <div class="question__head">
                    <span class="question__no" aria-hidden="true"></span>
                    <div class="question__prompt"><?= markdown($q['prompt']) ?></div>
                    <span class="question__points"><?= e(fmt_number($ans['points_awarded'] ?? null, 2)) ?> / <?= e(fmt_number($q['points'], 2)) ?></span>
                  </div>
                  <div class="question__body">
                    <p style="margin:0 0 4px"><strong><?= e(t('Your answer:')) ?></strong>
                      <?php if ($resp === ''): ?><span class="meta"><?= e(t('No answer')) ?></span>
                      <?php elseif ($q['qtype'] === 'multiple_choice'): ?><?= e($optText($resp)) ?>
                      <?php elseif ($q['qtype'] === 'true_false'): ?><?= e($resp === 'true' ? t('True') : t('False')) ?>
                      <?php else: ?><span style="white-space:pre-wrap"><?= e($resp) ?></span><?php endif; ?>
                      <?php if ($correct !== null): ?> <?= (int) $correct ? badge(t('Correct'), 'leaf', 'check') : badge(t('Not correct'), 'coral', 'x') ?><?php endif; ?>
                    </p>
                    <?php if ($q['qtype'] !== 'essay' && !(int) $correct): ?>
                      <p style="margin:0 0 4px"><strong><?= e(t('Correct answer:')) ?></strong>
                        <?php if ($q['qtype'] === 'multiple_choice'): ?><?= e($optText((string) ($key['correct'] ?? ''))) ?>
                        <?php elseif ($q['qtype'] === 'true_false'): ?><?= e(!empty($key['correct']) ? t('True') : t('False')) ?>
                        <?php else: ?><?= e(implode(' / ', (array) ($key['accepted'] ?? []))) ?><?php endif; ?>
                      </p>
                    <?php endif; ?>
                    <?php if (!empty($q['explanation'])): ?><div class="question__feedback"><?= markdown($q['explanation']) ?></div><?php endif; ?>
                    <?php if (!empty($ans['teacher_comment'])): ?><div class="question__feedback"><strong><?= e(t('Teacher:')) ?></strong> <?= e($ans['teacher_comment']) ?></div><?php endif; ?>
                  </div>
                </li>
              <?php endforeach; ?>
            </ol>
          <?php endif; ?>
        </section>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <aside class="stack">
    <section class="panel" aria-labelledby="glance-title">
      <h2 class="panel__title" id="glance-title"><?= e(t('At a glance')) ?></h2>
      <dl class="dl">
        <dt><?= e(t('Status')) ?></dt><dd><?= badge($statusLabel, $statusTone) ?></dd>
        <dt><?= e($isPaper ? t('In class') : t('Due')) ?></dt><dd><?= $assignment['due_at'] ? e(fmt_date($assignment['due_at'], 'date_long')) . '<br><span class="meta">' . e(fmt_date($assignment['due_at'], 'time') . ' (' . \Sibol\Core\Dates::tz() . ')') . '</span>' : e(t('No due date')) ?>
          <?php if (!empty($assignment['extended'])): ?><br><span class="meta"><?= e($assignment['class_due_at'] ? t('Your own date. The class date is :date.', ['date' => fmt_date($assignment['class_due_at'], 'datetime_short')]) : t('Your own date.')) ?></span><?php endif; ?></dd>
        <dt><?= e(t('Points')) ?></dt><dd class="num"><?= e(fmt_number($assignment['points_possible'], 2)) ?></dd>
        <?php if ($isQuiz): ?>
          <dt><?= e(t('Questions')) ?></dt><dd><?= count($snapshot['questions'] ?? []) ?></dd>
          <dt><?= e(t('Time limit')) ?></dt><dd><?= $minutes ? e(t(':n minutes', ['n' => $minutes])) . ((int) ($assignment['extra_minutes'] ?? 0) > 0 ? '<br><span class="meta">' . e(tp('Includes :count extra minute from your teacher', 'Includes :count extra minutes from your teacher', (int) $assignment['extra_minutes'])) . '</span>' : '') : e(t('None')) ?></dd>
          <dt><?= e(t('Attempts')) ?></dt><dd><?= e(t(':used of :max used', ['used' => (int) $state['submitted_count'], 'max' => (int) $assignment['max_attempts']])) ?></dd>
        <?php endif; ?>
        <?php if (!$isPaper): ?><dt><?= e(t('Late work')) ?></dt><dd><?= e($latePolicy) ?></dd><?php endif; ?>
        <dt><?= e(t('Feedback')) ?></dt><dd><?= e($released ? t('Released :when', ['when' => fmt_rel($reviewSub['released_at'])]) : ($assignment['feedback_release'] === 'immediate' ? t('Shown as soon as you submit, for automatically marked questions.') : t('Shown when your teacher releases it.'))) ?></dd>
        <?php if ($isQuiz): ?><dt><?= e(t('Answers')) ?></dt><dd><?= e($answersReleased ? t('Shown below with your answers.') : $answerRule) ?></dd><?php endif; ?>
      </dl>
    </section>
    <?php if (my_ai_on() && $stillOpen && $isQuiz): ?>
      <?php // A quiz gets no hints: the companion helps students study before it and pauses while they take it. ?>
      <section class="panel panel--sunken" aria-labelledby="help-title">
        <?php if ($state['in_progress'] !== null): ?>
          <h2 class="panel__title" id="help-title"><?= e(t('Companion paused')) ?></h2>
          <p style="font-size:.9375rem; margin:0"><?= e(t('The companion and AI practice come back when you submit this quiz.')) ?></p>
        <?php else: ?>
          <h2 class="panel__title" id="help-title"><?= e(t('Study first')) ?></h2>
          <p style="font-size:.9375rem"><?= e(t('The companion can explain the topic before you start. While you take the quiz, it is paused.')) ?></p>
          <a class="btn btn--secondary btn--sm" href="<?= e(url('/companion/new', ['mode' => 'explain', 'class_id' => (int) $assignment['class_id'], 'topic' => $assignment['title']])) ?>"><?= icon('book-open', 'icon--sm') ?><?= e(t('Study this topic')) ?></a>
        <?php endif; ?>
      </section>
    <?php elseif (my_ai_on() && $stillOpen): ?>
      <section class="panel panel--sunken" aria-labelledby="help-title">
        <h2 class="panel__title" id="help-title"><?= e(t('Stuck?')) ?></h2>
        <p style="font-size:.9375rem"><?= e(t('The companion can explain the topic or guide you with hints. It will not do the work for you.')) ?></p>
        <a class="btn btn--secondary btn--sm" href="<?= e(url('/companion/new', ['mode' => 'socratic', 'class_id' => (int) $assignment['class_id'], 'topic' => $assignment['title']])) ?>"><?= icon('lightbulb', 'icon--sm') ?><?= e(t('Get a hint')) ?></a>
      </section>
    <?php endif; ?>
  </aside>
</div>
