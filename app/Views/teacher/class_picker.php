<?php /** @var string $lead @var list<array> $classes @var string $target @var string $icon */ ?>
<div class="page-head"><div class="page-head__text"><h1 class="page-title"><?= e($title) ?></h1><p class="page-lead"><?= e($lead) ?></p></div></div>
<?php if (!$classes): ?>
  <?= partial('empty', ['title' => t('You have no classes yet'), 'body' => t('Ask your school administrator to assign you a class.')]) ?>
<?php else: ?>
  <ul class="rows" style="max-width: 720px">
    <?php foreach ($classes as $c): ?>
      <li class="row"><span class="row__lead"><?= icon($icon) ?></span>
        <div class="row__main"><a class="row__title" href="<?= e(url($target . (int) $c['id'])) ?>"><?= e($c['name']) ?></a><div class="row__meta"><span><?= e($c['subject_name']) ?></span><span><?= e(tp(':count student', ':count students', (int) $c['students'])) ?></span></div></div>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
