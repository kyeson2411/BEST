<?php /** @var array $assignment */ ?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e($assignment['title']) ?></h1>
    <p class="page-lead"><?= e($assignment['class_name']) ?></p>
  </div>
</div>
<div class="callout" style="max-width: 640px">
  <?= icon('clock') ?>
  <div>
    <p class="callout__title"><?= e(t('Opens :when', ['when' => fmt_date($assignment['opens_at'], 'date_long') . ', ' . fmt_date($assignment['opens_at'], 'time')])) ?></p>
    <p><?= e(t('Your teacher has scheduled this :kind. The instructions and questions appear when it opens.', ['kind' => work_kind((string) $assignment['kind'], true)])) ?>
      <?php if ($assignment['due_at']): ?><?= e(\Sibol\Core\Dates::due($assignment['due_at'])['label']) ?>.<?php endif; ?></p>
  </div>
</div>
