<?php
/**
 * Empty state: a direction, not a mood.
 * @var string $title @var string|null $body @var array|null $action [label, url] @var bool|null $compact @var bool|null $art
 */
$compact = $compact ?? false;
?>
<div class="empty<?= $compact ? ' empty--compact' : '' ?>">
  <?php if (!$compact && ($art ?? true)): ?>
    <svg class="empty__art" viewBox="0 0 72 72" aria-hidden="true" focusable="false">
      <path d="M8 60h56" stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none"/>
      <path d="M36 60V36" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" fill="none"/>
      <path d="M35.5 40.5C35.5 30 29 24 17 24c0 10.5 7 16.5 18.5 16.5z" fill="none" stroke="currentColor" stroke-width="2"/>
      <path d="M36.5 35c0-9 5.5-14.5 15.5-14.5 0 9.5-6 14.5-15.5 14.5z" fill="none" stroke="currentColor" stroke-width="2"/>
      <path d="M14 60c4-5 9-7 14-6M58 60c-3-4-7-6-12-5.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" fill="none"/>
    </svg>
  <?php endif; ?>
  <h3 class="empty__title"><?= e($title) ?></h3>
  <?php if (!empty($body)): ?><p class="empty__body"><?= e($body) ?></p><?php endif; ?>
  <?php if (!empty($action)): ?>
    <a class="btn <?= $compact ? 'btn--secondary btn--sm' : 'btn--primary' ?>" href="<?= e(url($action[1])) ?>"><?= e($action[0]) ?></a>
  <?php endif; ?>
</div>
