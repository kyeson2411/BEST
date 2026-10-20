<?php
/**
 * The student's review queue, one question at a time. Choice and typed answers are checked by Sibol; flashcards are
 * self-rated. Private and never graded.
 * @var array|null $class the class when reviewing everything before a quiz @var array|null $item next review item
 * @var array|null $content its question @var int $left items left in this queue @var int $reviewedToday
 * @var int $cap daily number (0: none) @var string $today @var array|null $result the answer just checked
 * @var list<array> $upcoming quizzes coming up, with how many items each class has
 */
use Sibol\Services\SpacedReview;

$action = $item ? url('/student/review/' . (int) $item['id']) . ($class ? '?class=' . (int) $class['id'] : '') : '';
$days = static function (?string $date) use ($today): int {
    return $date === null ? 0 : (int) (new DateTimeImmutable($today))->diff(new DateTimeImmutable($date))->format('%r%a');
};
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e($class ? t('Review: :class', ['class' => $class['name']]) : t('Review today')) ?></h1>
    <p class="page-lead"><?= e($class
        ? t('Every question from this class that you have not learned yet, due or not. Reviewing early does not push a question further out; a miss still brings it back tomorrow.')
        : t('Questions you missed come back here, a little later each time you get them right. Your reviews are private and never graded.')) ?></p>
  </div>
  <?php if ($class): ?>
    <div class="page-actions"><a class="btn btn--secondary" href="<?= e(url('/student/review')) ?>"><?= e(t('Back to today\'s reviews')) ?></a></div>
  <?php endif; ?>
</div>

<div class="layout-split">
  <section aria-labelledby="review-title">
    <h2 class="sr-only" id="review-title"><?= e(t('Questions to review')) ?></h2>

    <?php if ($result): $in = $days($result['due_on']); ?>
      <div class="callout <?= $result['right'] ? 'callout--leaf' : 'callout--amber' ?> review-result" role="status">
        <?= icon($result['right'] ? 'circle-check' : 'circle-alert') ?>
        <div>
          <p class="callout__title"><?= e($result['right'] ? t('Right.') : t('Not quite.')) ?></p>
          <p style="margin:0 0 6px"><?= e(t('The answer: :answer', ['answer' => $result['answer']])) ?></p>
          <?php if (!empty($result['explanation'])): ?><div class="prose"><?= markdown((string) $result['explanation']) ?></div><?php endif; ?>
          <p class="meta" style="margin:6px 0 0">
            <?php if ($result['done']): ?><?= e(t('Learned: this question leaves your queue.')) ?>
            <?php elseif ($result['early'] && $result['right']): ?><?= e(t('Reviewed early, so it keeps its date: :date.', ['date' => fmt_date($result['due_on'] . ' 12:00:00', 'date')])) ?>
            <?php else: ?><?= e(tp('Next review in :count day, :date.', 'Next review in :count days, :date.', $in, ['date' => fmt_date($result['due_on'] . ' 12:00:00', 'date')])) ?><?php endif; ?>
            <?php if (!empty($result['source'])): ?> <?= e(t('From :source.', ['source' => $result['source']])) ?><?php endif; ?>
          </p>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($item && $content): $new = (int) $item['reviews'] === 0; ?>
      <article class="panel review-item">
        <p class="review-item__meta">
          <span><?= e($item['class_name']) ?></span>
          <?php if (!empty($content['source'])): ?><span><?= e($content['source']) ?></span><?php endif; ?>
          <?= $new ? badge(t('New'), 'leaf') : '' ?>
          <span class="review-item__left"><?= e(tp(':count left', ':count left', $left)) ?></span>
        </p>
        <form method="post" action="<?= e($action) ?>">
          <?= csrf_field() ?>
          <?php if ($content['kind'] === 'choice'): ?>
            <fieldset>
              <legend class="review-item__prompt prose"><?= markdown((string) $content['prompt']) ?></legend>
              <div class="choice-list">
                <?php foreach ((array) $content['choices'] as $i => $choice): ?>
                  <label class="choice"><input type="radio" name="choice" value="<?= (int) $i ?>" required><span><?= e((string) $choice) ?></span></label>
                <?php endforeach; ?>
              </div>
            </fieldset>
            <div class="form-actions"><button class="btn btn--primary" type="submit"><?= e(t('Check answer')) ?></button></div>
          <?php elseif ($content['kind'] === 'text'): ?>
            <div class="review-item__prompt prose" id="review-prompt"><?= markdown((string) $content['prompt']) ?></div>
            <label class="field__label" for="review-text"><?= e(t('Your answer')) ?></label>
            <input class="input" id="review-text" name="text" required maxlength="300" autocomplete="off" aria-describedby="review-prompt">
            <div class="form-actions"><button class="btn btn--primary" type="submit"><?= e(t('Check answer')) ?></button></div>
          <?php else: ?>
            <div class="review-item__prompt prose"><?= markdown((string) $content['prompt']) ?></div>
            <details class="how review-item__back">
              <summary><?= e(t('Show the answer')) ?></summary>
              <div class="prose"><?= markdown((string) $content['back']) ?></div>
            </details>
            <p class="meta"><?= e(t('Flashcards are the one kind you mark yourself. Be honest: a card you knew comes back later.')) ?></p>
            <div class="form-actions">
              <button class="btn btn--primary" type="submit" name="knew" value="1"><?= e(t('I knew it')) ?></button>
              <button class="btn btn--secondary" type="submit" name="knew" value="0"><?= e(t('Not yet')) ?></button>
            </div>
          <?php endif; ?>
        </form>
      </article>
    <?php elseif ($class): ?>
      <?= partial('empty', ['title' => t('Nothing to review in this class'), 'body' => t('Questions you miss in practice, and in quizzes once their answers are released, come back here.')]) ?>
    <?php elseif ($cap > 0 && $reviewedToday >= $cap): ?>
      <?= partial('empty', ['title' => t('Today\'s reviews are done'), 'body' => tp('You reviewed :count question today, your school\'s number for a day. More come back tomorrow.', 'You reviewed :count questions today, your school\'s number for a day. More come back tomorrow.', $reviewedToday)]) ?>
    <?php else: ?>
      <?= partial('empty', ['title' => t('Nothing to review today'), 'body' => t('Questions you miss in practice, and in quizzes once their answers are released, come back here.')]) ?>
    <?php endif; ?>
  </section>

  <aside class="stack">
    <?php if (!$class): ?>
      <section class="section" aria-labelledby="before-title">
        <div class="section__head"><h2 class="section__title" id="before-title"><?= e(t('Before a quiz')) ?></h2></div>
        <?php if (!$upcoming): ?>
          <p class="meta"><?= e(t('No quizzes due in the next two weeks.')) ?></p>
        <?php else: ?>
          <ul class="rows">
            <?php foreach ($upcoming as $q): ?>
              <li class="row">
                <div class="row__main">
                  <span class="row__title"><?= e($q['title']) ?></span>
                  <div class="row__meta"><span><?= e($q['class_name']) ?></span><span><?= e(t('Due :date', ['date' => fmt_date($q['due_at'], 'datetime_short')])) ?></span></div>
                  <?php if ((int) $q['items'] > 0): ?>
                    <a class="btn btn--secondary btn--sm" style="margin-top: 8px" href="<?= e(url('/student/review', ['class' => (int) $q['class_id']])) ?>"><?= e(t('Review everything for this class (:count)', ['count' => (int) $q['items']])) ?></a>
                  <?php else: ?>
                    <p class="meta" style="margin:4px 0 0"><?= e(t('Nothing to review in this class yet.')) ?></p>
                  <?php endif; ?>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>
    <?php endif; ?>
    <section class="section" aria-labelledby="how-title">
      <div class="section__head"><h2 class="section__title" id="how-title"><?= e(t('How reviews work')) ?></h2></div>
      <p class="meta"><?= e(t('Get a question right and it comes back after :days days, a little longer each time. Miss it and it comes back tomorrow, one step lower. After the last step it leaves your queue.', ['days' => implode(', ', SpacedReview::INTERVALS)])) ?></p>
      <?php if ($cap > 0): ?><p class="meta"><?= e(tp('Your school shows up to :count question a day; questions that are due come before new ones.', 'Your school shows up to :count questions a day; questions that are due come before new ones.', $cap)) ?></p><?php endif; ?>
      <p class="meta"><?= e(t('Reviews are never graded, and your teachers do not see them.')) ?></p>
    </section>
  </aside>
</div>
