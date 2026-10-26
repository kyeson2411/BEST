<?php /** @var array $a @var bool $counted @var string|null $categoryLabel @var array $stats @var list<array> $questions @var list<string> $problems @var array $rubric @var list<array> $questionStats @var string|null $answersAt @var int $runningExtensions */
[$sl, $st] = assignment_status($a);
$base = '/teacher/assignments/' . (int) $a['id'];
$isQuiz = $a['kind'] === 'quiz';
$isPaper = $a['kind'] === 'paper';
$byId = [];
foreach ($questionStats as $qs) {
    $byId[(int) $qs['id']] = $qs;
}
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e($a['title']) ?></h1>
    <p class="page-lead"><?= kind_tag((string) $a['kind']) ?> <?= e($a['class_name']) ?> <?= badge($sl, $st) ?></p>
  </div>
  <div class="page-actions">
    <a class="btn btn--secondary" href="<?= e(url($base . '/edit')) ?>"><?= icon('pencil', 'icon--sm') ?><?= e(t('Edit')) ?></a>
    <a class="btn btn--secondary" href="<?= e(url($base . '/print')) ?>"><?= icon('printer', 'icon--sm') ?><?= e(t('Print')) ?></a>
    <?php if (!$isPaper): ?><a class="btn btn--secondary" href="<?= e(url($base . '/preview')) ?>"><?= icon('eye', 'icon--sm') ?><?= e(t('Student preview')) ?></a><?php endif; ?>
    <?php if ($a['status'] !== 'draft'): ?>
      <?php if ($isPaper): ?>
        <a class="btn btn--primary" href="<?= e(url($base . '/scores')) ?>"><?= icon('clipboard-pen', 'icon--sm') ?><?= e(t('Enter scores')) ?></a>
      <?php else: ?>
        <a class="btn btn--secondary" href="<?= e(url($base . '/scores')) ?>"><?= icon('clipboard-pen', 'icon--sm') ?><?= e(t('Paper scores')) ?></a>
        <a class="btn btn--primary" href="<?= e(url($base . '/submissions')) ?>"><?= icon('inbox', 'icon--sm') ?><?= e(t('Submissions')) ?></a>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<dl class="stat-line" style="margin-bottom: 24px">
  <div class="stat"><dt class="stat__label"><?= e($isPaper ? t('Scores entered') : t('Handed in')) ?></dt><dd style="margin:0"><span class="stat__value"><?= (int) $stats['submitted'] ?><span class="muted" style="font-size:1rem"> / <?= (int) $stats['enrolled'] ?></span></span><span class="stat__note"><?= e(implode(', ', array_filter([
      tp(':count late', ':count late', (int) $stats['late']),
      (int) $stats['extended'] ? tp(':count with their own due date', ':count with their own due date', (int) $stats['extended']) : null,
      (int) $stats['excused'] ? tp(':count excused', ':count excused', (int) $stats['excused']) : null,
  ]))) ?></span></dd></div>
  <div class="stat"><dt class="stat__label"><?= e(t('To grade')) ?></dt><dd style="margin:0"><span class="stat__value"><?= (int) $stats['ungraded'] ?></span><span class="stat__note"><?= e(tp(':count graded, not released', ':count graded, not released', (int) $stats['unreleased'])) ?></span></dd></div>
  <div class="stat"><dt class="stat__label"><?= e(t('Average so far')) ?></dt><dd style="margin:0"><span class="stat__value"><?= $stats['avg_pct'] !== null ? e(fmt_number($stats['avg_pct'])) . '%' : '—' ?></span><span class="stat__note"><?= e(t('Graded work only')) ?></span></dd></div>
  <div class="stat"><dt class="stat__label"><?= e($isPaper ? t('In class') : t('Due')) ?></dt><dd style="margin:0"><span class="stat__value" style="font-size:1.25rem"><?= $a['due_at'] ? e(fmt_date($a['due_at'], 'datetime_short')) : '—' ?></span><span class="stat__note"><?= $isPaper ? e(t('Done in class or on paper')) : ($a['opens_at'] ? e(t('Opens :d', ['d' => fmt_date($a['opens_at'], 'datetime_short')])) : e(t('Open when published'))) ?></span></dd></div>
</dl>

<div class="layout-split">
  <div class="stack">
    <?php if ($problems): ?>
      <div class="callout"><?= icon('list-checks') ?><div><p class="callout__title"><?= e(t('Before you publish')) ?></p><ul style="margin:0; padding-left:1.2em"><?php foreach ($problems as $p): ?><li><?= e($p) ?></li><?php endforeach; ?></ul></div></div>
    <?php endif; ?>
    <?php if (!$counted): ?>
      <div class="callout callout--amber"><?= icon('triangle-alert') ?><div><p class="callout__title"><?= e(t('Not counted in the gradebook yet')) ?></p><p><?= e(t('This work has no grading category, so its scores are left out of every grade in the gradebook.')) ?> <a href="<?= e(url($base . '/edit') . '#f-category') ?>"><?= e(t('Choose a category')) ?></a></p></div></div>
    <?php endif; ?>
    <?php if ($questions): ?>
      <section class="section" aria-labelledby="q-title">
        <div class="section__head"><h2 class="section__title" id="q-title"><?= e(t('Questions and results')) ?></h2><span class="meta"><?= e(t(':n points', ['n' => fmt_number($a['points_possible'], 2)])) ?></span></div>
        <ol class="questions">
          <?php foreach ($questions as $q): $s = $byId[(int) $q['id']] ?? null; ?>
            <li class="question">
              <div class="question__head">
                <span class="question__no" aria-hidden="true"></span>
                <div class="question__prompt"><?= markdown($q['prompt']) ?></div>
                <span class="question__points"><?= e(tp(':count pt', ':count pts', (int) round($q['points']))) ?></span>
              </div>
              <div class="question__body">
                <?php if ($q['qtype'] === 'essay'): ?>
                  <p class="meta"><?= e(t('Essay: marked by you.')) ?></p>
                <?php elseif ($s && (int) $s['attempts'] > 0): ?>
                  <?= partial('meter', ['value' => $s['correct_pct'], 'label' => t(':pct% correct (:n answers)', ['pct' => fmt_number($s['correct_pct']), 'n' => (int) $s['attempts']])]) ?>
                  <?php if ($s['common_wrong']): ?><p class="meta" style="margin:4px 0 0"><?= e(t('Most common wrong answer: “:a” (:n)', ['a' => str_limit($s['common_wrong']['response'], 60), 'n' => $s['common_wrong']['count']])) ?></p><?php endif; ?>
                <?php else: ?>
                  <p class="meta"><?= e(t('No answers yet.')) ?></p>
                <?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ol>
      </section>
    <?php endif; ?>
    <section class="section" aria-labelledby="i-title">
      <div class="section__head"><h2 class="section__title" id="i-title"><?= e(t('Instructions')) ?></h2></div>
      <div class="prose"><?= trim((string) $a['instructions']) !== '' ? markdown($a['instructions']) : '<p class="meta">' . e(t('No instructions written.')) . '</p>' ?></div>
    </section>
  </div>
  <aside class="stack">
    <section class="panel" aria-labelledby="life-title">
      <h2 class="panel__title" id="life-title"><?= e(t('Publishing')) ?></h2>
      <div class="cluster">
        <?php if ($a['status'] === 'draft'): ?>
          <form method="post" action="<?= e(url($base . '/publish')) ?>" data-confirm="<?= e(t('Students in :class will see it and get a notification.', ['class' => $a['class_name']])) ?>" data-confirm-title="<?= e(t('Publish?')) ?>" data-confirm-action="<?= e(t('Publish')) ?>"><?= csrf_field() ?><button class="btn btn--primary btn--sm" type="submit"><?= e(t('Publish')) ?></button></form>
        <?php elseif ($a['status'] === 'published'): ?>
          <form method="post" action="<?= e(url($base . '/close')) ?>" data-confirm="<?= e(t('Students will no longer be able to submit or start attempts.')) ?>" data-confirm-title="<?= e(t('Close submissions?')) ?>" data-confirm-action="<?= e(t('Close')) ?>"><?= csrf_field() ?><button class="btn btn--secondary btn--sm" type="submit"><?= e(t('Close submissions')) ?></button></form>
        <?php elseif ($a['status'] === 'closed'): ?>
          <form method="post" action="<?= e(url($base . '/reopen')) ?>"><?= csrf_field() ?><button class="btn btn--secondary btn--sm" type="submit"><?= e(t('Reopen')) ?></button></form>
        <?php endif; ?>
        <?php if ($isQuiz && $a['status'] !== 'draft' && $a['answer_key_release'] !== 'after_submit' && $a['answers_released_at'] === null): ?>
          <form method="post" action="<?= e(url($base . '/release-answers')) ?>" data-confirm="<?= e(t('Students who submitted will be able to see the correct answers and explanations now.') . ($runningExtensions ? ' ' . tp(':count student still has an extension and could see the answers before taking the quiz.', ':count students still have extensions and could see the answers before taking the quiz.', $runningExtensions) : '')) ?>" data-confirm-title="<?= e(t('Release correct answers?')) ?>" data-confirm-action="<?= e(t('Release answers')) ?>"><?= csrf_field() ?><button class="btn btn--secondary btn--sm" type="submit"><?= e(t('Release answers now')) ?></button></form>
        <?php endif; ?>
      </div>
      <dl class="dl" style="margin-top: 16px">
        <?php if (!$isPaper): ?><dt><?= e(t('Late work')) ?></dt><dd><?= e(['not_accepted' => t('Not accepted'), 'accepted' => t('Accepted, marked late'), 'penalty' => t(':p% penalty', ['p' => (int) $a['late_penalty_pct']])][$a['late_policy']]) ?></dd><?php endif; ?>
        <dt><?= e(t('Grades shown')) ?></dt><dd><?= e($a['feedback_release'] === 'manual' ? t('When you release them') : t('Immediately')) ?></dd>
        <?php if ($isQuiz): ?><dt><?= e(t('Answers shown')) ?></dt><dd><?= e($a['answers_released_at'] ? t('Released :d', ['d' => fmt_date($a['answers_released_at'])]) : ['never' => t('Never'), 'after_submit' => t('After submitting'), 'after_due' => t('After the due date'), 'manual' => t('When you release them')][$a['answer_key_release']]) ?>
          <?php if (!$a['answers_released_at'] && $a['answer_key_release'] === 'after_due' && $answersAt !== null && $answersAt !== $a['due_at']): ?><br><span class="meta"><?= e(t('Waiting for the latest extension: :date', ['date' => fmt_date($answersAt, 'datetime_short')])) ?></span><?php endif; ?></dd><?php endif; ?>
        <dt><?= e(t('Category')) ?></dt><dd><?= e($categoryLabel ?? t('None')) ?></dd>
      </dl>
    </section>
    <?php if ($rubric): ?>
      <section class="panel" aria-labelledby="rub-title">
        <h2 class="panel__title" id="rub-title"><?= e(t('Rubric')) ?></h2>
        <ul class="rows"><?php foreach ($rubric as $c): ?><li class="row" style="padding:8px 0"><div class="row__main"><span class="row__title"><?= e($c['title']) ?></span><div class="row__meta"><span><?= e($c['description']) ?></span></div></div><span class="row__aside num"><?= e(fmt_number($c['points'], 2)) ?></span></li><?php endforeach; ?></ul>
      </section>
    <?php endif; ?>
    <?= partial('copy_to_sections', $copyPanel) ?>
  </aside>
</div>
