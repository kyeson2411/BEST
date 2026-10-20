<?php /** @var array $assignment @var list<array> $questions @var array $rubric @var list<array> $resources */
$a = $assignment;
?>
<div class="callout callout--amber" style="margin-bottom: 24px"><?= icon('eye') ?><div>
  <p class="callout__title"><?= e(t('Student preview')) ?></p>
  <p><?= e(t('This is what students see. Answer keys and explanations stay hidden until your release setting allows them. Nothing you do here is saved.')) ?>
    <a href="<?= e(url('/teacher/assignments/' . (int) $a['id'] . '/edit')) ?>"><?= e(t('Back to editing')) ?></a></p>
</div></div>

<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e($a['title']) ?></h1>
    <p class="page-lead"><?= e($a['class_name']) ?>. <?= e(\Sibol\Core\Dates::due($a['due_at'])['label']) ?></p>
  </div>
</div>
<div class="layout-split">
  <div class="stack">
    <section class="section"><div class="section__head"><h2 class="section__title"><?= e(t('Instructions')) ?></h2></div>
      <div class="prose"><?= trim((string) $a['instructions']) !== '' ? markdown($a['instructions']) : '<p class="meta">' . e(t('No written instructions.')) . '</p>' ?></div></section>
    <?php if ($questions): ?>
      <section class="section"><div class="section__head"><h2 class="section__title"><?= e(t('Questions')) ?></h2></div>
        <ol class="questions">
          <?php foreach ($questions as $q): ?>
            <li class="question">
              <div class="question__head"><span class="question__no" aria-hidden="true"></span><div class="question__prompt"><?= markdown($q['prompt']) ?></div><span class="question__points"><?= e(tp(':count point', ':count points', (int) round($q['points']))) ?></span></div>
              <div class="question__body">
                <?php if ($q['qtype'] === 'multiple_choice'): ?>
                  <div class="choice-list"><?php foreach ($q['options'] as $o): ?><label class="choice"><input type="radio" disabled><span><span class="choice__title"><?= e($o['key']) ?>.</span> <?= e($o['text']) ?></span></label><?php endforeach; ?></div>
                <?php elseif ($q['qtype'] === 'true_false'): ?>
                  <div class="cluster"><label class="choice"><input type="radio" disabled><span class="choice__title"><?= e(t('True')) ?></span></label><label class="choice"><input type="radio" disabled><span class="choice__title"><?= e(t('False')) ?></span></label></div>
                <?php elseif ($q['qtype'] === 'short_answer'): ?>
                  <input class="input" disabled style="max-width:480px" aria-label="<?= e(t('Short answer')) ?>">
                <?php else: ?>
                  <textarea class="input textarea" rows="4" disabled aria-label="<?= e(t('Long answer')) ?>"></textarea>
                <?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ol>
      </section>
    <?php elseif ((int) $a['allow_text'] || (int) $a['allow_files']): ?>
      <section class="section"><div class="section__head"><h2 class="section__title"><?= e(t('Your work')) ?></h2></div>
        <?php if ((int) $a['allow_text']): ?><textarea class="input textarea" rows="6" disabled aria-label="<?= e(t('Your response')) ?>"></textarea><?php endif; ?>
        <?php if ((int) $a['allow_files']): ?><p class="meta" style="margin-top:8px"><?= e(t('Students can also attach up to 5 files.')) ?></p><?php endif; ?>
      </section>
    <?php endif; ?>
  </div>
  <aside class="stack">
    <?php if ($rubric): ?>
      <section class="panel"><h2 class="panel__title"><?= e(t('How this is graded')) ?></h2>
        <ul class="rows"><?php foreach ($rubric as $c): ?><li class="row" style="padding:8px 0"><div class="row__main"><span class="row__title"><?= e($c['title']) ?></span><div class="row__meta"><span><?= e($c['description']) ?></span></div></div><span class="row__aside num"><?= e(fmt_number($c['points'], 2)) ?></span></li><?php endforeach; ?></ul></section>
    <?php endif; ?>
    <?php if ($resources): ?>
      <section class="panel"><h2 class="panel__title"><?= e(t('Resources')) ?></h2><ul class="rows"><?php foreach ($resources as $r): ?><li class="row"><span class="row__title"><?= e($r['title']) ?></span></li><?php endforeach; ?></ul></section>
    <?php endif; ?>
  </aside>
</div>
