<?php /** @var list<array> $rows */ ?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Lessons')) ?></h1>
    <p class="page-lead"><?= e(t('Plan lessons from scratch or from an AI draft based on your materials. Students see a lesson only after you publish it.')) ?></p>
  </div>
  <div class="page-actions"><a class="btn btn--primary" href="<?= e(url('/teacher/lessons/new')) ?>"><?= icon('plus', 'icon--sm') ?><?= e(t('New lesson')) ?></a></div>
</div>
<?php if (!$rows): ?>
  <?= partial('empty', ['title' => t('No lessons yet'), 'body' => t('Start with your learning objectives. B.E.S.T. Learning and Interactive Study System can draft the structure from the materials you choose, and you edit everything before students see it.'), 'action' => [t('New lesson'), '/teacher/lessons/new']]) ?>
<?php else: ?>
  <ul class="rows" style="max-width: 900px">
    <?php foreach ($rows as $l): ?>
      <li class="row">
        <span class="row__lead"><?= icon('notebook-pen', 'icon--lg') ?></span>
        <div class="row__main">
          <a class="row__title" href="<?= e(url('/teacher/lessons/' . (int) $l['id'])) ?>"><?= e($l['title']) ?></a>
          <div class="row__meta"><span><?= e($l['class_name']) ?></span><?php if ($l['duration_minutes']): ?><span><?= e(t(':n minutes', ['n' => (int) $l['duration_minutes']])) ?></span><?php endif; ?><span><?= e(t('Edited :when', ['when' => fmt_rel($l['updated_at'])])) ?></span><?php if ((int) $l['ai_assisted']): ?><span><?= e(t('Started from an AI draft')) ?></span><?php endif; ?></div>
        </div>
        <div class="row__aside">
          <?php if ($l['status'] === 'published'): ?><?= badge(t('Published'), 'leaf') ?><?php if ((int) $l['has_unpublished_changes']): ?> <?= badge(t('Unpublished changes'), 'amber') ?><?php endif; ?>
          <?php else: ?><?= badge(t('Draft'), 'neutral') ?><?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
