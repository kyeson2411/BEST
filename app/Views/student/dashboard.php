<?php
/**
 * @var array $user @var list<array> $due @var list<array> $classes @var array|null $continue
 * @var list<array> $feedback @var list<array> $materials @var array|null $conversation @var list<array> $study
 * @var bool $aiEnabled @var list<string> $modes @var bool $practiceOn
 */
use Sibol\Core\Dates;

$upcoming = array_values(array_filter($due, static fn ($d) => $d['due_at'] >= \Sibol\Core\Db::now()));
$thisWeek = array_values(array_filter($upcoming, static fn ($d) => strtotime($d['due_at'] . ' UTC') <= time() + 7 * 86400));
$overdue = count($due) - count($upcoming);
$next = $upcoming[0] ?? null;
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(Dates::greeting() . ', ' . user_name($user, false) . '.') ?></h1>
    <p class="page-lead">
      <?php if ($next): ?>
        <?= e(tp('You have :count piece of work due this week.', 'You have :count pieces of work due this week.', count($thisWeek))) ?>
        <?= e(t('Next is :title, :due.', ['title' => $next['title'], 'due' => lcfirst_u(Dates::due($next['due_at'])['label'])])) ?>
      <?php else: ?>
        <?= e(t('Nothing is due in the next two weeks. A good time to review or get ahead.')) ?>
      <?php endif; ?>
      <?php if ($overdue > 0): ?>
        <?= e(tp(':count item is overdue.', ':count items are overdue.', $overdue)) ?>
      <?php endif; ?>
    </p>
  </div>
  <?php if ($aiEnabled): ?>
    <div class="page-actions">
      <a class="btn btn--secondary" href="<?= e(url('/companion/new')) ?>"><?= icon('messages-square') ?><?= e(t('Ask the companion')) ?></a>
    </div>
  <?php endif; ?>
</div>

<div class="layout-split">
  <div class="stack">
    <?php if ($continue): ?>
      <section class="section" aria-labelledby="continue-title">
        <div class="section__head"><h2 class="section__title" id="continue-title"><?= e(t('Continue where you left off')) ?></h2></div>
        <div class="row">
          <span class="row__lead"><?= icon($continue['type'] === 'draft' ? 'square-pen' : 'book-open', 'icon--lg') ?></span>
          <div class="row__main">
            <?php if ($continue['type'] === 'draft'): ?>
              <a class="row__title" href="<?= e(url('/student/assignments/' . (int) $continue['id'])) ?>"><?= e($continue['title']) ?></a>
              <div class="row__meta">
                <span><?= e($continue['class_name']) ?></span>
                <span><?= e(t('Draft saved :when', ['when' => fmt_rel($continue['updated_at'])])) ?></span>
                <?php if ($continue['due_at']): ?><span><?= e(Dates::due($continue['due_at'])['label']) ?></span><?php endif; ?>
              </div>
            <?php else: ?>
              <a class="row__title" href="<?= e(url('/student/classes/' . (int) $continue['class_id'] . '?tab=materials#material-' . (int) $continue['content_id'])) ?>"><?= e($continue['title']) ?></a>
              <div class="row__meta">
                <span><?= e($continue['class_name']) ?></span>
                <?php if ($continue['module_title']): ?><span><?= e($continue['module_title']) ?></span><?php endif; ?>
                <span><?= e(t('Opened :when', ['when' => fmt_rel($continue['last_viewed_at'])])) ?></span>
              </div>
            <?php endif; ?>
          </div>
          <div class="row__aside">
            <?php if ($continue['type'] === 'draft'): ?>
              <a class="btn btn--primary btn--sm" href="<?= e(url('/student/assignments/' . (int) $continue['id'])) ?>"><?= e(t('Continue draft')) ?></a>
            <?php else: ?>
              <a class="btn btn--secondary btn--sm" href="<?= e(url('/materials/' . (int) $continue['content_id'] . '/file')) ?>"><?= e(t('Open')) ?><span class="sr-only">: <?= e($continue['title']) ?></span></a>
              <?php if ($aiEnabled): ?>
                <a class="btn btn--quiet btn--sm" href="<?= e(url('/companion/new', ['mode' => 'ask_materials', 'class_id' => (int) $continue['class_id'], 'material_id' => (int) $continue['content_id']])) ?>"><?= e(t('Ask about it')) ?></a>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
      </section>
    <?php endif; ?>

    <section class="section" aria-labelledby="due-title">
      <div class="section__head">
        <h2 class="section__title" id="due-title"><?= e(t('Due soon')) ?><?php if ($due): ?><span class="count"><?= count($due) ?></span><?php endif; ?></h2>
        <a class="section__link" href="<?= e(url('/student/assignments')) ?>"><?= e(t('All assignments')) ?></a>
      </div>
      <?php if (!$due): ?>
        <?= partial('empty', ['compact' => true, 'title' => t('You are all caught up'), 'body' => t('New work from your teachers will appear here with its due date.')]) ?>
      <?php else: ?>
        <ul class="entries">
          <?php foreach ($due as $item):
              $d = Dates::due($item['due_at'], (string) $item['kind']);
              $quiz = $item['kind'] === 'quiz';
              $started = in_array($item['sub_status'], ['in_progress', 'returned'], true);
              $action = $quiz ? ($started ? t('Resume quiz') : t('Start quiz')) : ($started ? t('Continue') : t('Open'));
              $closed = $d['overdue'] && $item['late_policy'] === 'not_accepted';
              $notOpen = (int) $item['not_open'] === 1;
          ?>
            <li class="entry<?= $d['overdue'] ? ' entry--alert' : '' ?>">
              <?= partial('entry_date', ['at' => $item['due_at']]) ?>
              <div class="entry__main">
                <a class="row__title" href="<?= e(url('/student/assignments/' . (int) $item['id'])) ?>"><?= e($item['title']) ?></a>
                <div class="row__meta">
                  <span><?= e($item['class_name']) ?></span>
                  <span><?= e(work_kind((string) $item['kind'])) ?><?php if ($quiz && $item['time_limit_minutes']): ?>, <?= e(t(':n minutes', ['n' => (int) $item['time_limit_minutes']])) ?><?php endif; ?></span>
                </div>
              </div>
              <div class="entry__aside">
                <?php if ($notOpen): ?>
                  <?= badge(t('Opens :when', ['when' => fmt_date($item['opens_at'], 'datetime_short')]), 'neutral', 'clock') ?>
                  <a class="btn btn--secondary btn--sm" href="<?= e(url('/student/assignments/' . (int) $item['id'])) ?>"><?= e(t('View')) ?></a>
                <?php else: ?>
                  <?= badge($closed ? t('Missed, closed') : $d['label'], $d['tone']) ?>
                  <?php if (!$closed): ?>
                    <a class="btn btn--<?= $d['tone'] === 'neutral' ? 'secondary' : 'primary' ?> btn--sm" href="<?= e(url('/student/assignments/' . (int) $item['id'])) ?>"><?= e($action) ?><span class="sr-only">: <?= e($item['title']) ?></span></a>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="section" aria-labelledby="classes-title">
      <div class="section__head">
        <h2 class="section__title" id="classes-title"><?= e(t('My classes')) ?></h2>
        <a class="section__link" href="<?= e(url('/student/classes')) ?>"><?= e(t('Join a class')) ?></a>
      </div>
      <?php if (!$classes): ?>
        <?= partial('empty', ['title' => t('You are not in any classes yet'), 'body' => t('Ask your teacher for a class code, then enter it on the Classes page.'), 'action' => [t('Enter a class code'), '/student/classes']]) ?>
      <?php else: ?>
        <div class="class-grid">
          <?php foreach ($classes as $class): ?>
            <article class="class-card">
              <span class="class-card__subject"><?= e(trim(($class['grade_level'] ?? '') . ($class['section'] ? ', ' . t(':section section', ['section' => $class['section']]) : ''), ', ')) ?></span>
              <h3 class="class-card__title"><a href="<?= e(url('/student/classes/' . (int) $class['id'])) ?>"><?= e($class['name']) ?></a></h3>
              <p class="class-card__meta"><?= e($class['teachers'] ?: t('Teacher to be assigned')) ?></p>
              <div class="class-card__foot">
                <?php if ((int) $class['open_work'] > 0): ?><?= badge(tp(':count open task', ':count open tasks', (int) $class['open_work']), 'amber') ?><?php endif; ?>
                <?php if ((int) $class['unseen_materials'] > 0): ?><?= badge(tp(':count new material', ':count new materials', (int) $class['unseen_materials']), 'leaf') ?><?php endif; ?>
                <?php if ((int) $class['open_work'] === 0 && (int) $class['unseen_materials'] === 0): ?><span class="meta"><?= e(t('Nothing new')) ?></span><?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <aside class="stack" aria-label="<?= e(t('Study tools and updates')) ?>">
    <?php if ($aiEnabled): ?>
      <section class="section" aria-labelledby="companion-title">
        <div class="section__head"><h2 class="section__title" id="companion-title"><?= e(t('Learning companion')) ?></h2></div>
        <?php if ($conversation): ?>
          <p style="margin:0 0 12px">
            <a class="row__title" href="<?= e(url('/companion/' . (int) $conversation['id'])) ?>"><?= icon('history', 'icon--sm') ?> <?= e(t('Resume “:title”', ['title' => str_limit($conversation['title'], 48)])) ?></a>
            <span class="meta" style="display:block"><?= e(\Sibol\Services\Settings::modeLabel((string) $conversation['mode'])) ?>, <?= e(fmt_rel($conversation['last_message_at'])) ?></span>
          </p>
        <?php else: ?>
          <p class="muted" style="font-size:.9375rem"><?= e(t('Ask questions, get hints, or quiz yourself on your class materials. Your conversations are private.')) ?></p>
        <?php endif; ?>
        <div class="cluster">
          <?php
          $starters = [
              ['explain', t('Explain this simply')],
              ['socratic', t('Give me a hint')],
              ['practice', t('Quiz me')],
              ['ask_materials', t('Use my class notes')],
              ['study_plan', t('Help me make a study plan')],
          ];
          foreach ($starters as [$mode, $label]):
              if (!in_array($mode, $modes, true)) { continue; }
          ?>
            <a class="chip-btn" href="<?= e(url('/companion/new', ['mode' => $mode])) ?>"><?= e($label) ?></a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($practiceOn && $classes): ?>
      <section class="section" aria-labelledby="practice-title">
        <div class="section__head">
          <h2 class="section__title" id="practice-title"><?= e(t('Quick practice')) ?></h2>
          <a class="section__link" href="<?= e(url('/student/learning')) ?>"><?= e(t('Practice history')) ?></a>
        </div>
        <form method="post" action="<?= e(url('/student/learning')) ?>">
          <?= csrf_field() ?>
          <?= field('select', 'class_id', t('Class'), ['options' => array_column($classes, 'name', 'id'), 'value' => (string) ($classes[0]['id'] ?? '')]) ?>
          <div class="cluster">
            <button class="btn btn--secondary btn--sm" type="submit" name="kind" value="quiz" data-busy="<?= e(t('Preparing…')) ?>"><?= icon('list-checks', 'icon--sm') ?><?= e(t('5 practice questions')) ?></button>
            <button class="btn btn--secondary btn--sm" type="submit" name="kind" value="flashcards" data-busy="<?= e(t('Preparing…')) ?>"><?= icon('layers', 'icon--sm') ?><?= e(t('Flashcards')) ?></button>
          </div>
        </form>
      </section>
    <?php endif; ?>

    <section class="section" aria-labelledby="feedback-title">
      <div class="section__head">
        <h2 class="section__title" id="feedback-title"><?= e(t('Recent feedback')) ?></h2>
        <a class="section__link" href="<?= e(url('/student/progress')) ?>"><?= e(t('Progress')) ?></a>
      </div>
      <?php if (!$feedback): ?>
        <p class="meta"><?= e(t('Grades and comments appear here when your teachers release them.')) ?></p>
      <?php else: ?>
        <ul class="rows">
          <?php foreach ($feedback as $f): ?>
            <li class="row">
              <div class="row__main">
                <a class="row__title" href="<?= e(url('/student/assignments/' . (int) $f['assignment_id'])) ?>"><?= e($f['title']) ?></a>
                <div class="row__meta"><span><?= e($f['class_name']) ?></span><span><?= e(fmt_rel($f['released_at'])) ?></span></div>
              </div>
              <span class="row__aside"><?= score_html($f['score'], $f['points_possible']) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="section" aria-labelledby="materials-title">
      <div class="section__head"><h2 class="section__title" id="materials-title"><?= e(t('New in your classes')) ?></h2></div>
      <?php if (!$materials): ?>
        <p class="meta"><?= e(t('Materials your teachers share in the last 10 days appear here.')) ?></p>
      <?php else: ?>
        <ul class="rows">
          <?php foreach ($materials as $m): ?>
            <li class="row">
              <span class="row__lead"><?= icon($m['kind'] === 'link' ? 'link' : 'file-text') ?></span>
              <div class="row__main">
                <a class="row__title" href="<?= e(url('/student/classes/' . (int) $m['class_id'] . '?tab=materials#material-' . (int) $m['id'])) ?>"><?= e($m['title']) ?></a>
                <div class="row__meta"><span><?= e($m['class_name']) ?></span><span><?= e(fmt_rel($m['shared_at'])) ?></span></div>
              </div>
              <?php if (!(int) $m['seen']): ?><span class="row__aside"><?= badge(t('New'), 'leaf') ?></span><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="section" aria-labelledby="study-title">
      <div class="section__head">
        <h2 class="section__title" id="study-title"><?= e(t('Your study plan')) ?></h2>
        <a class="section__link" href="<?= e(url('/student/calendar')) ?>"><?= e(t('Calendar')) ?></a>
      </div>
      <?php if (!$study): ?>
        <p class="meta"><?= e(t('Plan short study sessions on the calendar, or ask the companion to help you make a plan.')) ?></p>
      <?php else: ?>
        <ul class="rows">
          <?php foreach ($study as $s): ?>
            <li class="row">
              <span class="row__lead"><?= icon('calendar') ?></span>
              <div class="row__main">
                <span class="row__title"><?= e($s['title']) ?></span>
                <div class="row__meta"><span><?= e(fmt_date($s['starts_at'], 'datetime_short')) ?></span><span><?= e(t(':n minutes', ['n' => (int) $s['duration_minutes']])) ?></span></div>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </aside>
</div>
