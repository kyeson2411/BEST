<?php /** @var list<array> $sets @var list<array> $classes @var list<array> $materials @var bool $quizOn @var bool $cardsOn */ ?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Practice')) ?></h1>
    <p class="page-lead"><?= e(t('Short practice sets and flashcards made from your class materials. Practice is private and never affects your grades.')) ?></p>
  </div>
</div>

<div class="layout-split">
  <section class="section" aria-labelledby="history-title">
    <div class="section__head"><h2 class="section__title" id="history-title"><?= e(t('Your practice')) ?></h2></div>
    <?php if (!$sets): ?>
      <?= partial('empty', ['title' => t('No practice yet'), 'body' => t('Start a set on the right. Five questions take about five minutes.')]) ?>
    <?php else: ?>
      <ul class="entries">
        <?php foreach ($sets as $s): ?>
          <li class="entry">
            <?= partial('entry_date', ['at' => $s['created_at']]) ?>
            <div class="entry__main">
              <a class="row__title" href="<?= e(url('/student/learning/' . (int) $s['id'])) ?>"><?= e($s['title']) ?></a>
              <div class="row__meta">
                <span><?= e($s['kind'] === 'quiz' ? t('Practice questions') : t('Flashcards')) ?></span>
                <?php if ($s['class_name']): ?><span><?= e($s['class_name']) ?></span><?php endif; ?>
                <?php if ($s['generator'] === 'demo'): ?><span><?= e(t('Made in demo mode')) ?></span><?php endif; ?>
              </div>
            </div>
            <div class="entry__aside">
              <?php if ($s['completed_at']): ?>
                <span class="score"><strong><?= (int) $s['score'] ?></strong><span class="score__of">/<?= (int) $s['total'] ?></span><span class="score__pct"><?= e($s['kind'] === 'quiz' ? t('correct') : t('known')) ?></span></span>
              <?php else: ?>
                <?= badge(t('Not finished'), 'amber') ?>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <aside>
    <section class="panel" aria-labelledby="new-title">
      <h2 class="panel__title" id="new-title"><?= e(t('Start practice')) ?></h2>
      <?php if (!$quizOn && !$cardsOn): ?>
        <p class="muted"><?= e(t('Your school has turned off practice sets.')) ?></p>
      <?php elseif (!$classes): ?>
        <p class="muted"><?= e(t('Join a class first. Practice is made from your classes\' materials.')) ?></p>
      <?php else: ?>
        <form method="post" action="<?= e(url('/student/learning')) ?>">
          <?= csrf_field() ?>
          <?= field('select', 'class_id', t('Class'), ['options' => array_column($classes, 'name', 'id'), 'value' => (string) $classes[0]['id']]) ?>
          <div class="field">
            <label class="field__label" for="f-material_id"><?= e(t('Material')) ?> <span class="field__optional"><?= e(t('optional')) ?></span></label>
            <select class="input select" id="f-material_id" name="material_id">
              <option value=""><?= e(t('Latest material in the class')) ?></option>
              <?php foreach ($classes as $c): $items = array_filter($materials, static fn ($m) => (int) $m['class_id'] === (int) $c['id']); if (!$items) { continue; } ?>
                <optgroup label="<?= e($c['name']) ?>">
                  <?php foreach ($items as $m): ?><option value="<?= (int) $m['id'] ?>"><?= e($m['title']) ?></option><?php endforeach; ?>
                </optgroup>
              <?php endforeach; ?>
            </select>
          </div>
          <?= field('text', 'topic', t('Topic'), ['optional_label' => true, 'placeholder' => t('For example: Punnett squares'), 'attrs' => ['maxlength' => 160]]) ?>
          <div class="cluster">
            <?php if ($quizOn): ?><button class="btn btn--primary" type="submit" name="kind" value="quiz" data-busy="<?= e(t('Preparing…')) ?>"><?= icon('list-checks', 'icon--sm') ?><?= e(t('Practice questions')) ?></button><?php endif; ?>
            <?php if ($cardsOn): ?><button class="btn btn--secondary" type="submit" name="kind" value="flashcards" data-busy="<?= e(t('Preparing…')) ?>"><?= icon('layers', 'icon--sm') ?><?= e(t('Flashcards')) ?></button><?php endif; ?>
          </div>
        </form>
      <?php endif; ?>
    </section>
  </aside>
</div>
