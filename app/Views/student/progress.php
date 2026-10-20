<?php /** @var list<array> $books @var array $work @var list<array> $released @var list<array>|null $practice null when the student's AI is off @var list<array> $feedback */
$submitted = (int) ($work['submitted'] ?? 0);
$dueSoFar = (int) ($work['due_so_far'] ?? 0);
$onTime = (int) ($work['on_time'] ?? 0);
$avg = $released ? array_sum(array_map(static fn ($r) => (float) $r['score'] / (float) $r['points_possible'] * 100, $released)) / count($released) : null;
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Progress')) ?></h1>
    <p class="page-lead"><?= e(t('Built only from your own work and the results your teachers have released. Your teachers set your official grades.')) ?></p>
  </div>
</div>

<dl class="stat-line" aria-label="<?= e(t('Summary')) ?>">
  <div class="stat">
    <dt class="stat__label"><?= e(t('Work handed in')) ?></dt>
    <dd style="margin:0"><span class="stat__value"><?= $submitted ?><span class="muted" style="font-size:1rem"> / <?= $dueSoFar ?></span></span>
      <span class="stat__note"><?= e(t('Of the work that has reached its due date')) ?></span></dd>
  </div>
  <div class="stat">
    <dt class="stat__label"><?= e(t('Handed in on time')) ?></dt>
    <dd style="margin:0"><span class="stat__value"><?= $submitted ? e(fmt_number(percent($onTime, $submitted))) . '%' : '—' ?></span>
      <span class="stat__note"><?= e(tp(':count on-time submission', ':count on-time submissions', $onTime)) ?></span></dd>
  </div>
  <div class="stat">
    <dt class="stat__label"><?= e(t('Average on released work')) ?></dt>
    <dd style="margin:0"><span class="stat__value"><?= $avg !== null ? e(fmt_number($avg)) . '%' : '—' ?></span>
      <span class="stat__note"><?= e(tp('Across :count result', 'Across :count results', count($released))) ?></span></dd>
  </div>
  <?php if ($practice !== null): ?>
  <div class="stat">
    <dt class="stat__label"><?= e(t('Practice sets finished')) ?></dt>
    <dd style="margin:0"><span class="stat__value"><?= count($practice) ?></span><span class="stat__note"><?= e(t('Private; not part of grades')) ?></span></dd>
  </div>
  <?php endif; ?>
</dl>
<details class="how">
  <summary><?= e(t('How these numbers are calculated')) ?></summary>
  <div>
    <p><?= e(t('Work handed in counts published assignments and quizzes in your current classes whose due date has passed, and whether you submitted at least once. On time means submitted before the due date.')) ?></p>
    <p><?= e(t('The average uses only results your teachers have released, each as a percentage of its points, with every piece of work counting equally. Your class grade below can differ because your school may weight categories of work differently.')) ?></p>
  </div>
</details>

<div class="layout-split" style="margin-top: 32px">
  <div class="stack">
    <section class="section" aria-labelledby="trend-title">
      <div class="section__head"><h2 class="section__title" id="trend-title"><?= e(t('Your released results')) ?></h2></div>
      <?= partial('chart_results', ['points' => $released]) ?>
    </section>

    <section class="section" aria-labelledby="classes-title">
      <div class="section__head"><h2 class="section__title" id="classes-title"><?= e(t('By class')) ?></h2></div>
      <?php if (!$books): ?>
        <p class="meta"><?= e(t('Join a class to see progress here.')) ?></p>
      <?php endif; ?>
      <?php foreach ($books as $b): $o = $b['overall']; ?>
        <article style="margin-bottom: 28px">
          <div class="cluster cluster--between">
            <h3 style="margin:0; font-size:1.0625rem"><a href="<?= e(url('/student/classes/' . (int) $b['class']['id'])) ?>"><?= e($b['class']['name']) ?></a></h3>
            <span class="cluster">
              <?php if ($o && $o['percent'] !== null): ?>
                <?php if (!$b['periods']): // With grading periods, the period and final grades below are the grades. ?>
                  <strong class="num" style="font-size:1.125rem"><?= e(fmt_number($o['percent'], 1)) ?>%</strong>
                  <?php if ($o['descriptor'] && $o['counted'] >= 3): ?><?= badge(t(':descriptor so far', ['descriptor' => $o['descriptor']]), 'neutral') ?><?php endif; ?>
                <?php endif; ?>
              <?php else: ?>
                <span class="meta"><?= e(t('No released results yet')) ?></span>
              <?php endif; ?>
              <?php if ($o && $o['missing'] > 0): ?><?= badge(tp(':count missing', ':count missing', (int) $o['missing']), 'coral') ?><?php endif; ?>
            </span>
          </div>
          <?php if ($b['periods']): $f = $b['final']; ?>
            <dl class="period-grades">
              <?php foreach ($b['periods'] as $p): $g = $b['by_period'][(int) $p['id']] ?? null; ?>
                <div><dt><?= e($p['name']) ?></dt><dd class="num"><?= $g && $g['grade'] !== null ? e($b['transmuted'] ? fmt_number($g['grade'], 0) : fmt_number($g['grade'], 1) . '%') : '—' ?></dd></div>
              <?php endforeach; ?>
              <div class="period-grades__final"><dt><?= e($f && $f['grade'] !== null && !$f['complete'] ? t('Final grade so far') : t('Final grade')) ?></dt>
                <dd class="num"><?= $f && $f['grade'] !== null ? e($b['transmuted'] ? fmt_number($f['grade'], 0) : fmt_number($f['grade'], 1) . '%') : '—' ?><?php if ($f && $f['complete'] && $f['descriptor']): ?> <span class="meta"><?= e($f['descriptor']) ?></span><?php endif; ?></dd></div>
            </dl>
          <?php endif; ?>
          <?php if ($o && $o['by_category']): ?>
            <ul class="rows" style="margin-top:6px">
              <?php foreach ($o['by_category'] as $cat): ?>
                <li class="row" style="padding:8px 0">
                  <div class="row__main"><span><?= e($cat['label']) ?></span> <span class="meta">(<?= e(t('weight :w%', ['w' => fmt_number($cat['weight'])])) ?>)</span></div>
                  <div class="row__aside" style="min-width: 200px"><?= partial('meter', ['value' => $cat['percent'], 'label' => $cat['percent'] === null ? t('Nothing released') : fmt_number($cat['percent'], 1) . '%']) ?></div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
          <?php if ($o && $o['percent'] !== null): ?>
            <p class="meta" style="margin:6px 0 0"><?php if ($b['periods']): ?><?= e(t('Each grading period\'s grade uses only the released work from that period.')) ?>
                <?= $b['transmuted'] ? e(t('Your school turns it into the grade shown with its transmutation table.')) : '' ?>
                <?= $o['by_category'] ? e(t('The bars cover your released work from the whole year.')) : '' ?>
              <?php else: ?><?= e(($b['grading']['model'] ?? 'points') === 'weighted'
                ? tp('Calculated with your school\'s category weights from :count released result. Categories with no released results are left out.', 'Calculated with your school\'s category weights from :count released results. Categories with no released results are left out.', (int) $o['counted'])
                : tp('Points earned divided by points possible across :count released result.', 'Points earned divided by points possible across :count released results.', (int) $o['counted'])) ?><?php endif; ?>
              <?php if ($o['counted'] < 3): ?><?= e(t('With so few results this can change a lot.')) ?><?php endif; ?></p>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </section>
  </div>

  <aside class="stack">
    <section class="section" aria-labelledby="fb-title">
      <div class="section__head"><h2 class="section__title" id="fb-title"><?= e(t('Teacher comments')) ?></h2></div>
      <?php if (!$feedback): ?>
        <p class="meta"><?= e(t('Comments appear here when your teachers release them.')) ?></p>
      <?php else: ?>
        <ul class="rows">
          <?php foreach ($feedback as $f): ?>
            <li class="row" style="display:block">
              <a class="row__title" href="<?= e(url('/student/assignments/' . (int) $f['assignment_id'])) ?>"><?= e($f['title']) ?></a>
              <p style="margin:4px 0 0; font-size:.9375rem"><?= e(str_limit($f['feedback'], 180)) ?></p>
              <span class="meta"><?= e(fmt_rel($f['released_at'])) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
    <?php if ($practice !== null): ?>
    <section class="section" aria-labelledby="pr-title">
      <div class="section__head">
        <h2 class="section__title" id="pr-title"><?= e(t('Recent practice')) ?></h2>
        <a class="section__link" href="<?= e(url('/student/learning')) ?>"><?= e(t('Practice')) ?></a>
      </div>
      <?php if (!$practice): ?>
        <p class="meta"><?= e(t('Practice sets you finish are listed here. They are for you only.')) ?></p>
      <?php else: ?>
        <ul class="rows">
          <?php foreach ($practice as $p): ?>
            <li class="row">
              <div class="row__main">
                <a class="row__title" href="<?= e(url('/student/learning/' . (int) $p['id'])) ?>"><?= e($p['title']) ?></a>
                <div class="row__meta"><span><?= e($p['class_name'] ?? '') ?></span><span><?= e(fmt_rel($p['completed_at'])) ?></span></div>
              </div>
              <span class="row__aside score"><strong><?= (int) $p['score'] ?></strong><span class="score__of">/<?= (int) $p['total'] ?></span></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
    <?php endif; ?>
  </aside>
</div>
