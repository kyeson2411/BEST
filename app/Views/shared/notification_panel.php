<?php /** @var list<array> $rows @var int $unread */
$icons = ['material' => 'file-text', 'assignment' => 'clipboard-list', 'reminder' => 'clock', 'feedback' => 'message-circle', 'announcement' => 'megaphone', 'submission' => 'inbox', 'processing' => 'triangle-alert', 'privacy' => 'shield-check', 'class' => 'users', 'site_feedback' => 'message-square-reply', 'account' => 'lock'];
?>
<div class="notif-panel__head">
  <strong><?= e(t('Notifications')) ?></strong>
  <?php if ($unread): ?>
    <form method="post" action="<?= e(url('/notifications/read-all')) ?>"><?= csrf_field() ?><button class="btn btn--quiet btn--sm" type="submit"><?= e(t('Mark all as read')) ?></button></form>
  <?php endif; ?>
</div>
<div class="notif-panel__list">
  <?php if (!$rows): ?>
    <p class="notif" style="display:block"><span class="notif__title"><?= e(t('You are all caught up')) ?></span><span class="notif__body"><?= e(t('Updates about your classes and work will appear here.')) ?></span></p>
  <?php endif; ?>
  <?php foreach ($rows as $n): ?>
    <a class="notif<?= $n['read_at'] === null ? ' is-unread' : '' ?>" href="<?= e(url('/notifications/' . (int) $n['id'] . '/open')) ?>">
      <span class="notif__dot" aria-hidden="true"></span>
      <span style="flex:1; min-width:0">
        <span class="notif__title"><?php if ($n['read_at'] === null): ?><span class="sr-only"><?= e(t('Unread:')) ?> </span><?php endif; ?><?= e($n['title']) ?></span>
        <?php if ($n['body']): ?><span class="notif__body"><?= e($n['body']) ?></span><?php endif; ?>
        <span class="notif__time"><?= icon($icons[$n['type']] ?? 'bell', 'icon--xs') ?> <?= e(fmt_rel($n['created_at'])) ?></span>
      </span>
    </a>
  <?php endforeach; ?>
</div>
<div class="notif-panel__foot"><a href="<?= e(url('/notifications')) ?>"><?= e(t('See all notifications')) ?></a></div>
