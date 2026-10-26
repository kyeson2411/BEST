<?php
/**
 * @var array $user @var list<array> $classes @var list<array> $queue @var array $attention @var list<array> $upcoming
 * @var list<array> $recent @var array $overview @var list<array> $reteach
 */
use Sibol\Core\Dates;

$oldest = $queue[0] ?? null;
$attentionCount = count($attention['failed']) + count($attention['ai_drafts']) + ($attention['unreleased'] > 0 ? 1 : 0) + ($attention['shares'] > 0 ? 1 : 0);
$completion = percent($overview['submitted'], $overview['expected']);
$accuracy = percent($overview['correct'], $overview['answers']);
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(Dates::greeting() . ', ' . user_name($user, false) . '.') ?></h1>
    <p class="page-lead">
      <?php if ($queue): ?>
        <?= e(tp(':count piece of work is waiting for you.', ':count pieces of work are waiting for you.', count($queue))) ?>
        <?= e(t('The oldest arrived :when.', ['when' => fmt_rel($oldest['submitted_at'])])) ?>
      <?php else: ?>
        <?= e(t('Nothing is waiting for review.')) ?>
      <?php endif; ?>
    </p>
  </div>
  <div class="page-actions">
    <a class="btn btn--primary" href="<?= e(url('/teacher/assignments/new', ['kind' => 'assignment'])) ?>"><?= icon('plus', 'icon--sm') ?><?= e(t('Create assignment')) ?></a>
    <a class="btn btn--secondary" href="<?= e(url('/teacher/assignments/new', ['kind' => 'quiz'])) ?>"><?= icon('list-checks', 'icon--sm') ?><?= e(t('Build quiz')) ?></a>
    <a class="btn btn--secondary" href="<?= e(url('/teacher/materials/new')) ?>"><?= icon('upload', 'icon--sm') ?><?= e(t('Upload material')) ?></a>
    <a class="btn btn--secondary" href="<?= e(url('/teacher/lessons/new')) ?>"><?= icon('notebook-pen', 'icon--sm') ?><?= e(t('Draft a lesson')) ?></a>
  </div>
</div>

<div class="layout-split layout-split--wide-aside">
  <div class="stack">
    <section class="section" aria-labelledby="attn-title">
      <div class="section__head">
        <h2 class="section__title" id="attn-title"><?= e(t('Needs attention')) ?></h2>
        <a class="section__link" href="<?= e(url('/teacher/review')) ?>"><?= e(t('Review queue')) ?></a>
      </div>
      <?php if (!$queue && !$attentionCount): ?>
        <?= partial('empty', ['compact' => true, 'title' => t('All clear'), 'body' => t('Submissions, AI drafts awaiting your review, and processing problems will show up here.')]) ?>
      <?php else: ?>
        <ul class="rows">
          <?php if ($queue): ?>
            <li class="row">
              <span class="row__lead"><?= icon('inbox', 'icon--lg') ?></span>
              <div class="row__main">
                <a class="row__title" href="<?= e(url('/teacher/review')) ?>"><?= e(tp(':count submission to review', ':count submissions to review', count($queue))) ?></a>
                <div class="row__meta"><span><?= e(t('Oldest: :title by :name, :when', ['title' => $oldest['title'], 'name' => user_name($oldest), 'when' => fmt_rel($oldest['submitted_at'])])) ?></span></div>
              </div>
              <span class="row__aside"><a class="btn btn--primary btn--sm" href="<?= e(url('/teacher/submissions/' . (int) $oldest['id'])) ?>"><?= e(t('Start with the oldest')) ?></a></span>
            </li>
          <?php endif; ?>
          <?php if ($attention['unreleased'] > 0): ?>
            <li class="row">
              <span class="row__lead"><?= icon('send', 'icon--lg') ?></span>
              <div class="row__main">
                <span class="row__title"><?= e(tp(':count graded result has not been released', ':count graded results have not been released', $attention['unreleased'])) ?></span>
                <div class="row__meta"><span><?= e(t('Students see grades only after you release them.')) ?></span></div>
              </div>
              <span class="row__aside"><a class="btn btn--secondary btn--sm" href="<?= e(url('/teacher/assignments', ['status' => 'published'])) ?>"><?= e(t('Find them')) ?></a></span>
            </li>
          <?php endif; ?>
          <?php foreach ($attention['ai_drafts'] as $d): ?>
            <li class="row">
              <span class="row__lead"><?= icon('square-pen', 'icon--lg') ?></span>
              <div class="row__main">
                <a class="row__title" href="<?= e(url('/teacher/assignments/' . (int) $d['id'] . '/edit#questions')) ?>"><?= e(tp(':count AI-drafted question to review', ':count AI-drafted questions to review', (int) $d['waiting'])) ?></a>
                <div class="row__meta"><span><?= e($d['title']) ?></span></div>
              </div>
            </li>
          <?php endforeach; ?>
          <?php foreach ($attention['failed'] as $f): ?>
            <li class="row">
              <span class="row__lead bad"><?= icon('triangle-alert', 'icon--lg') ?></span>
              <div class="row__main">
                <a class="row__title" href="<?= e(url('/teacher/materials/' . (int) $f['id'])) ?>"><?= e(t('“:title” could not be read', ['title' => $f['title']])) ?></a>
                <div class="row__meta"><span><?= e($f['class_name']) ?></span><span><?= e(str_limit((string) $f['processing_error'], 90)) ?></span></div>
              </div>
            </li>
          <?php endforeach; ?>
          <?php if ($attention['shares'] > 0): ?>
            <li class="row">
              <span class="row__lead"><?= icon('share-2', 'icon--lg') ?></span>
              <div class="row__main">
                <span class="row__title"><?= e(tp(':count companion excerpt shared by a student', ':count companion excerpts shared by students', $attention['shares'])) ?></span>
                <div class="row__meta"><span><?= e(t('Students chose to share these with you. Open the class to read them.')) ?></span></div>
              </div>
            </li>
          <?php endif; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="section" aria-labelledby="up-title">
      <div class="section__head">
        <h2 class="section__title" id="up-title"><?= e(t('Upcoming')) ?></h2>
        <span class="section__links">
          <a class="section__link" href="<?= e(url('/teacher/assignments')) ?>"><?= e(t('All assignments')) ?></a>
          <a class="section__link" href="<?= e(url('/teacher/quizzes')) ?>"><?= e(t('All quizzes')) ?></a>
        </span>
      </div>
      <?php if (!$upcoming): ?>
        <p class="meta"><?= e(t('Nothing is due in the next two weeks.')) ?></p>
      <?php else: ?>
        <ul class="entries">
          <?php foreach ($upcoming as $a): ?>
            <li class="entry">
              <?= partial('entry_date', ['at' => $a['due_at']]) ?>
              <div class="entry__main">
                <a class="row__title" href="<?= e(url('/teacher/assignments/' . (int) $a['id'])) ?>"><?= e($a['title']) ?></a>
                <div class="row__meta"><?= kind_tag((string) $a['kind']) ?><span><?= e($a['class_name']) ?></span><span><?= e(fmt_date($a['due_at'], 'time')) ?></span></div>
              </div>
              <div class="entry__aside" style="min-width: 160px"><?= partial('meter', ['value' => percent($a['submitted'], $a['enrolled']), 'label' => t(':n of :m in', ['n' => (int) $a['submitted'], 'm' => (int) $a['enrolled']])]) ?></div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="section" aria-labelledby="recent-title">
      <div class="section__head"><h2 class="section__title" id="recent-title"><?= e(t('Recently completed')) ?></h2></div>
      <?php if (!$recent): ?>
        <p class="meta"><?= e(t('Work that reached its due date in the last two weeks appears here.')) ?></p>
      <?php else: ?>
        <ul class="rows">
          <?php foreach ($recent as $a): ?>
            <li class="row">
              <div class="row__main">
                <a class="row__title" href="<?= e(url('/teacher/assignments/' . (int) $a['id'] . '/submissions')) ?>"><?= e($a['title']) ?></a>
                <div class="row__meta"><?= kind_tag((string) $a['kind']) ?><span><?= e($a['class_name']) ?></span><span><?= e(t('Due :date', ['date' => fmt_date($a['due_at'])])) ?></span>
                  <span><?= e(t(':n of :m submitted', ['n' => (int) $a['submitted'], 'm' => (int) $a['enrolled']])) ?></span></div>
              </div>
              <div class="row__aside">
                <?php if ((int) $a['ungraded'] > 0): ?><?= badge(tp(':count to grade', ':count to grade', (int) $a['ungraded']), 'amber') ?>
                <?php elseif ((int) $a['unreleased'] > 0): ?><?= badge(tp(':count not released', ':count not released', (int) $a['unreleased']), 'amber') ?>
                <?php else: ?><?= badge(t('Done'), 'leaf', 'check') ?><?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>

  <aside class="stack">
    <section class="section" aria-labelledby="classes-title">
      <div class="section__head"><h2 class="section__title" id="classes-title"><?= e(t('Your classes')) ?></h2><a class="section__link" href="<?= e(url('/teacher/classes')) ?>"><?= e(t('All classes')) ?></a></div>
      <?php if (!$classes): ?>
        <p class="meta"><?= e(t('You have no classes yet. Your school administrator assigns classes to teachers.')) ?></p>
      <?php else: ?>
        <ul class="rows">
          <?php foreach ($classes as $c): ?>
            <li class="row">
              <div class="row__main">
                <a class="row__title" href="<?= e(url('/teacher/classes/' . (int) $c['id'])) ?>"><?= e($c['name']) ?></a>
                <div class="row__meta"><span><?= e(tp(':count student', ':count students', (int) $c['students'])) ?></span><span><?= e(tp(':count open task', ':count open tasks', (int) $c['open_work'])) ?></span></div>
              </div>
              <?php if ((int) $c['to_review'] > 0): ?><span class="row__aside"><?= badge(tp(':count to review', ':count to review', (int) $c['to_review']), 'amber') ?></span><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="section" aria-labelledby="overview-title">
      <div class="section__head"><h2 class="section__title" id="overview-title"><?= e(t('Last 30 days')) ?></h2><a class="section__link" href="<?= e(url('/teacher/insights')) ?>"><?= e(t('Insights')) ?></a></div>
      <?php if ($overview['due'] === 0 && $overview['answers'] === 0): ?>
        <p class="meta"><?= e(t('Once work reaches its due date, completion and question results appear here.')) ?></p>
      <?php else: ?>
        <dl class="stat-line" style="grid-template-columns: repeat(2, minmax(0, 1fr))">
          <div class="stat">
            <dt class="stat__label"><?= e(t('Work handed in')) ?></dt>
            <dd style="margin:0"><span class="stat__value"><?= $completion !== null ? e(fmt_number($completion)) . '%' : '—' ?></span>
              <span class="stat__note"><?= e(t(':n of :m expected, :d tasks', ['n' => $overview['submitted'], 'm' => $overview['expected'], 'd' => $overview['due']])) ?></span></dd>
          </div>
          <div class="stat">
            <dt class="stat__label"><?= e(t('Quiz answers correct')) ?></dt>
            <dd style="margin:0"><span class="stat__value"><?= $accuracy !== null ? e(fmt_number($accuracy)) . '%' : '—' ?></span>
              <span class="stat__note"><?= e(tp('Across :count marked answer', 'Across :count marked answers', $overview['answers'])) ?></span></dd>
          </div>
        </dl>
      <?php endif; ?>
      <?php if ($reteach): ?>
        <h3 style="font-size:.9375rem; margin: 16px 0 4px"><?= e(t('Topics that may need reteaching')) ?></h3>
        <ul class="rows">
          <?php foreach ($reteach as $r): ?>
            <li class="row" style="padding:8px 0">
              <div class="row__main"><a class="row__title" href="<?= e(url('/teacher/insights/' . (int) $r['class_id'])) ?>"><?= e($r['topic']) ?></a>
                <div class="row__meta"><span><?= e($r['class_name']) ?></span><span><?= e(t(':pct% correct, :n students', ['pct' => fmt_number($r['correct_pct']), 'n' => $r['students']])) ?></span></div></div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </aside>
</div>
