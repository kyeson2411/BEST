<?php /** @var array $class @var list<array> $topics @var list<array> $competencies @var list<array> $completion @var list<array> $questions @var int $enrolled @var string|null $ideas @var int|null $aiRemaining */
use Sibol\Services\Insights;

$flagged = array_values(array_filter($topics, static fn ($t) => $t['students'] >= Insights::MIN_STUDENTS && $t['correct_pct'] < Insights::WEAK_PCT));
$past = array_values(array_filter($completion, static fn ($c) => $c['due_at'] !== null && $c['due_at'] <= \Sibol\Core\Db::now()));
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e($class['name']) ?></h1>
    <p class="page-lead"><?= e(t('From submitted work only. These describe questions and topics, not students, and small groups can swing a lot.')) ?></p>
  </div>
  <div class="page-actions"><a class="btn btn--secondary" href="<?= e(url('/teacher/gradebook/' . (int) $class['id'])) ?>"><?= icon('table', 'icon--sm') ?><?= e(t('Gradebook')) ?></a></div>
</div>

<div class="layout-split">
  <div class="stack">
    <section class="section" aria-labelledby="topics-title">
      <div class="section__head"><h2 class="section__title" id="topics-title"><?= e(t('Topics, by share of correct answers')) ?></h2></div>
      <?php if (!$topics): ?>
        <p class="meta"><?= e(t('Topics appear once students have answered automatically marked questions that have a topic set.')) ?></p>
      <?php else: ?>
        <ul class="bar-list">
          <?php foreach ($topics as $t): $small = $t['students'] < Insights::MIN_STUDENTS; $weak = !$small && $t['correct_pct'] < Insights::WEAK_PCT; ?>
            <li>
              <span class="bar-list__label"><?= e($t['topic']) ?><?php if ($weak): ?> <?= badge(t('May need reteaching'), 'amber', 'circle-alert') ?><?php endif; ?></span>
              <?= partial('meter', ['value' => $t['correct_pct'], 'label' => fmt_number($t['correct_pct']) . '%']) ?>
              <span class="bar-list__value"><?= e(tp(':count student', ':count students', $t['students'])) ?><?= $small ? ', ' . e(t('too few to judge')) : '' ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
        <details class="how"><summary><?= e(t('How this is calculated')) ?></summary><div>
          <p><?= e(t('Each bar is the share of correct answers to automatically marked questions tagged with that topic, across all submitted attempts. A topic is flagged when at least :n students answered and fewer than :pct% of answers were correct.', ['n' => Insights::MIN_STUDENTS, 'pct' => (int) Insights::WEAK_PCT])) ?></p>
        </div></details>
      <?php endif; ?>
    </section>

    <section class="section" id="competencies" aria-labelledby="lc-title">
      <?php
      $taught = count(array_filter($competencies, static fn ($c) => $c['lessons'] > 0));
      $assessed = count(array_filter($competencies, static fn ($c) => $c['attempts'] > 0));
      ?>
      <div class="section__head"><h2 class="section__title" id="lc-title"><?= e(t('Learning competencies')) ?></h2>
        <?php if ($competencies): ?><span class="meta"><?= e(t(':taught of :total taught in a lesson, :assessed with answered questions', ['taught' => $taught, 'total' => count($competencies), 'assessed' => $assessed])) ?></span><?php endif; ?></div>
      <?php if (!$competencies): ?>
        <p class="meta"><?= e(t('Your school has not added learning competencies for this subject yet. Once an administrator imports them, tag questions and lessons with their codes to see results here.')) ?></p>
      <?php else: ?>
        <div class="table-frame">
          <table class="table table--stack">
            <caption class="sr-only"><?= e(t('Results by learning competency')) ?></caption>
            <thead><tr>
              <th scope="col"><?= e(t('Competency')) ?></th>
              <th scope="col" class="num"><?= e(t('Lessons')) ?></th>
              <th scope="col" class="num"><?= e(t('Questions')) ?></th>
              <th scope="col"><?= e(t('Correct answers')) ?></th>
            </tr></thead>
            <tbody>
              <?php foreach ($competencies as $c):
                  $small = $c['students'] < Insights::MIN_STUDENTS;
                  $weak = !$small && $c['correct_pct'] !== null && $c['correct_pct'] < Insights::WEAK_PCT; ?>
                <tr>
                  <td class="cell-primary"><strong class="code-text"><?= e($c['code']) ?></strong><?php if ($c['is_sample']): ?> <?= badge(t('Sample'), 'neutral') ?><?php endif; ?><?php if ($weak): ?> <?= badge(t('May need reteaching'), 'amber', 'circle-alert') ?><?php endif; ?>
                    <span class="table-sub"><?= e(($c['quarter'] ? t('Quarter :n', ['n' => $c['quarter']]) . ' · ' : '') . $c['description']) ?></span></td>
                  <td class="num" data-label="<?= e(t('Lessons')) ?>"><?= (int) $c['lessons'] ?></td>
                  <td class="num" data-label="<?= e(t('Questions')) ?>"><?= (int) $c['questions'] ?></td>
                  <td data-label="<?= e(t('Correct answers')) ?>" style="min-width: 180px">
                    <?php if ($c['correct_pct'] === null): ?>
                      <span class="meta"><?= e($c['questions'] ? t('No answers yet') : t('Not assessed yet')) ?></span>
                    <?php else: ?>
                      <?= partial('meter', ['value' => $c['correct_pct'], 'label' => fmt_number($c['correct_pct']) . '%']) ?>
                      <span class="table-sub"><?= e(tp(':count student', ':count students', $c['students'])) ?><?= $small ? ', ' . e(t('too few to judge')) : '' ?></span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <details class="how"><summary><?= e(t('How this is calculated')) ?></summary><div>
          <p><?= e(t('Each question can be tagged with the one competency it assesses, and each lesson with those it teaches (on the question in the assignment\'s editor, and in the lesson\'s details). The share is of correct answers to automatically marked questions with that competency, in submitted work. A competency is flagged when at least :n students answered and fewer than :pct% of answers were correct.', ['n' => Insights::MIN_STUDENTS, 'pct' => (int) Insights::WEAK_PCT])) ?></p>
        </div></details>
      <?php endif; ?>
    </section>

    <section class="section" aria-labelledby="comp-title">
      <div class="section__head"><h2 class="section__title" id="comp-title"><?= e(t('Completion and results by assignment')) ?></h2></div>
      <?php if (!$completion): ?>
        <p class="meta"><?= e(t('No published work yet.')) ?></p>
      <?php else: ?>
        <div class="table-frame">
          <table class="table table--stack">
            <caption class="sr-only"><?= e(t('Completion by assignment')) ?></caption>
            <thead><tr><th scope="col"><?= e(t('Work')) ?></th><th scope="col"><?= e(t('Handed in')) ?></th><th scope="col" class="num"><?= e(t('Late')) ?></th><th scope="col" class="num"><?= e(t('Average')) ?></th></tr></thead>
            <tbody>
              <?php foreach ($completion as $c): ?>
                <tr>
                  <td class="cell-primary"><a href="<?= e(url('/teacher/assignments/' . (int) $c['id'])) ?>"><?= e($c['title']) ?></a><span class="table-sub"><?= e(t('Due :d', ['d' => fmt_date($c['due_at'])])) ?></span></td>
                  <td data-label="<?= e(t('Handed in')) ?>" style="min-width: 180px"><?= partial('meter', ['value' => percent($c['submitted'], $c['enrolled']), 'label' => (int) $c['submitted'] . ' / ' . (int) $c['enrolled']]) ?></td>
                  <td class="num" data-label="<?= e(t('Late')) ?>"><?= (int) $c['late'] ?></td>
                  <td class="num" data-label="<?= e(t('Average')) ?>"><?= $c['avg_pct'] !== null ? e(fmt_number($c['avg_pct'])) . '% <span class="table-sub">' . e(tp('of :count graded', 'of :count graded', (int) $c['graded'])) . '</span>' : '—' ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <section class="section" aria-labelledby="qs-title">
      <div class="section__head"><h2 class="section__title" id="qs-title"><?= e(t('Questions with the most wrong answers')) ?></h2></div>
      <?php $answered = array_values(array_filter($questions, static fn ($q) => (int) $q['attempts'] > 0)); usort($answered, static fn ($a, $b) => $a['correct_pct'] <=> $b['correct_pct']); ?>
      <?php if (!$answered): ?>
        <p class="meta"><?= e(t('No answers yet.')) ?></p>
      <?php else: ?>
        <ul class="rows">
          <?php foreach (array_slice($answered, 0, 8) as $q): ?>
            <li class="row">
              <div class="row__main">
                <a class="row__title" href="<?= e(url('/teacher/insights/' . (int) $class['id'] . '/questions/' . (int) $q['id'])) ?>"><?= e(str_limit($q['prompt'], 120)) ?></a>
                <div class="row__meta"><span><?= e($q['assignment_title']) ?></span><?php if ($q['topic']): ?><span><?= e($q['topic']) ?></span><?php endif; ?>
                  <?php if ($q['common_wrong']): ?><span><?= e(t('Common wrong answer: “:a”', ['a' => str_limit($q['common_wrong']['response'], 40)])) ?></span><?php endif; ?></div>
              </div>
              <div class="row__aside" style="min-width: 170px"><?= partial('meter', ['value' => $q['correct_pct'], 'label' => t(':pct% of :n', ['pct' => fmt_number($q['correct_pct']), 'n' => (int) $q['attempts']])]) ?></div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>

  <aside class="stack">
    <section class="panel" id="ideas" aria-labelledby="ideas-title">
      <h2 class="panel__title" id="ideas-title"><?= e(t('Reteaching ideas')) ?></h2>
      <?php if ($ideas): ?>
        <div class="prose" style="font-size:.9375rem"><?= markdown($ideas) ?></div>
        <p class="meta"><?= e(t('Suggestions for you to judge. They are based only on the topic totals above, never on individual students.')) ?></p>
      <?php else: ?>
        <p style="font-size:.9375rem"><?= e($flagged ? tp(':count topic is below :pct% correct.', ':count topics are below :pct% correct.', count($flagged), ['pct' => (int) Insights::WEAK_PCT]) : t('No topic is flagged right now.')) ?>
          <?= e(t('Ask for ideas on how to reteach. Only the anonymous topic totals are used.')) ?></p>
      <?php endif; ?>
      <form method="post" action="<?= e(url('/teacher/insights/' . (int) $class['id'] . '/ideas')) ?>"><?= csrf_field() ?>
        <button class="btn btn--secondary btn--sm" type="submit" data-busy="<?= e(t('Thinking…')) ?>"<?= !$topics ? ' disabled' : '' ?>><?= icon('lightbulb', 'icon--sm') ?><?= e($ideas ? t('Get new ideas') : t('Suggest reteaching ideas')) ?></button>
      </form>
      <p class="meta" style="margin-top:8px"><?= e(\Sibol\Services\Ai\Providers::label()) ?></p>
    </section>
    <section class="panel panel--sunken" aria-labelledby="limits-title">
      <h2 class="panel__title" id="limits-title"><?= e(t('Reading these numbers')) ?></h2>
      <ul class="prose" style="font-size:.9375rem; padding-left:1.1em; margin:0">
        <li><?= e(t('Based on :n enrolled students and the work they submitted.', ['n' => $enrolled])) ?></li>
        <li><?= e(t('Missing work is not counted as wrong answers.')) ?></li>
        <li><?= e(t('Essay answers are not included in topic results.')) ?></li>
        <li><?= e(t('Use them to decide what to revisit, not to label students.')) ?></li>
      </ul>
    </section>
  </aside>
</div>
