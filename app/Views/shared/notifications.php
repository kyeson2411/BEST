<?php /** @var list<array> $rows @var \Sibol\Core\Paginator $pager @var string $filter @var int $unread */
$icons = ['material' => 'file-text', 'assignment' => 'clipboard-list', 'reminder' => 'clock', 'feedback' => 'message-circle', 'announcement' => 'megaphone', 'submission' => 'inbox', 'processing' => 'triangle-alert', 'privacy' => 'shield-check', 'class' => 'users', 'site_feedback' => 'message-square-reply', 'account' => 'lock'];
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Notifications')) ?></h1>
    <p class="page-lead"><?= e($unread ? tp('You have :count unread notification.', 'You have :count unread notifications.', $unread) : t('You are all caught up.')) ?>
      <?= e(t('Choose which kinds you receive in Account and preferences.')) ?></p>
  </div>
  <div class="page-actions">
    <?php if ($unread): ?>
      <form method="post" action="<?= e(url('/notifications/read-all')) ?>"><?= csrf_field() ?><button class="btn btn--secondary" type="submit"><?= icon('check', 'icon--sm') ?><?= e(t('Mark all as read')) ?></button></form>
    <?php endif; ?>
  </div>
</div>
<nav class="segmented" aria-label="<?= e(t('Filter')) ?>" style="margin-bottom: 16px">
  <a href="<?= e(url('/notifications')) ?>"<?= $filter === 'all' ? ' aria-current="page"' : '' ?>><?= e(t('All')) ?></a>
  <a href="<?= e(url('/notifications', ['filter' => 'unread'])) ?>"<?= $filter === 'unread' ? ' aria-current="page"' : '' ?>><?= e(t('Unread')) ?> (<?= (int) $unread ?>)</a>
</nav>
<?php if (!$rows): ?>
  <?= partial('empty', ['title' => $filter === 'unread' ? t('No unread notifications') : t('No notifications yet'), 'body' => t('New materials, assignments, feedback and reminders will appear here.')]) ?>
<?php else: ?>
  <ul class="rows" style="max-width: 820px">
    <?php foreach ($rows as $n): ?>
      <li class="row<?= $n['read_at'] !== null ? ' row--muted' : '' ?>">
        <span class="row__lead"><?= icon($icons[$n['type']] ?? 'bell') ?></span>
        <div class="row__main">
          <a class="row__title" href="<?= e(url('/notifications/' . (int) $n['id'] . '/open')) ?>"><?php if ($n['read_at'] === null): ?><span class="sr-only"><?= e(t('Unread:')) ?> </span><?php endif; ?><?= e($n['title']) ?></a>
          <div class="row__meta"><?php if ($n['body']): ?><span><?= e($n['body']) ?></span><?php endif; ?><span><?= time_tag($n['created_at'], 'datetime', true) ?></span></div>
        </div>
        <?php if ($n['read_at'] === null): ?><span class="row__aside"><?= badge(t('New'), 'leaf') ?></span><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
  <?= partial('pagination', ['pager' => $pager, 'path' => '/notifications', 'query' => ['filter' => $filter === 'unread' ? 'unread' : null]]) ?>
<?php endif; ?>
