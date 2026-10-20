<?php
/**
 * Assistant answer + its margin notes. Rendered on page load and returned by the stream's "done" event.
 * @var array $message (with citations, display, plan) @var array $user @var bool $isLast @var array $conversation
 */
use Sibol\Services\Ai\Retrieval;

$id = (int) $message['id'];
$cites = [];
foreach ($message['citations'] as $c) {
    $cites[(int) $c['ref_no']] = 'cite-' . $id;
}
$grounding = [
    'sources' => [t('From your class materials'), 'leaf', 'quote'],
    'no_sources' => [t('Not found in your class materials'), 'amber', 'circle-alert'],
    'general' => [t('General explanation'), 'neutral', 'lightbulb'],
    'demo' => [t('Demo mode: passages only'), 'amber', 'info'],
][$message['grounding'] ?? ''] ?? null;
$isStudent = $user['role'] === 'student';
?>
<article class="msg msg--assistant<?= $message['status'] === 'error' ? ' msg--error' : '' ?>" id="m-<?= $id ?>" data-message-id="<?= $id ?>">
  <div class="msg__who"><?= icon('sprout', 'icon--sm') ?><span><?= e(t('Sibol')) ?></span></div>
  <div class="msg__body"><?= $message['status'] === 'error' ? '<p>' . e($message['content']) . '</p>' : markdown((string) $message['display'], ['citations' => $cites, 'heading_base' => 4]) ?></div>

  <?php if (!empty($message['plan']) && $isStudent && $message['status'] === 'complete'): ?>
    <form method="post" action="<?= e(url('/companion/messages/' . $id . '/plan')) ?>" class="panel panel--tight" style="margin-top:12px">
      <?= csrf_field() ?>
      <p style="margin:0 0 8px"><strong><?= e(tp(':count study session is ready to add to your calendar.', ':count study sessions are ready to add to your calendar.', count($message['plan']))) ?></strong></p>
      <ul class="meta" style="margin:0 0 12px; padding-left: 1.2em">
        <?php foreach (array_slice($message['plan'], 0, 6) as $s): ?>
          <li><?= e(fmt_date($s['starts_at'], 'datetime_short')) ?>, <?= e(t(':n min', ['n' => $s['minutes']])) ?>: <?= e($s['title']) ?></li>
        <?php endforeach; ?>
      </ul>
      <button class="btn btn--secondary btn--sm" type="submit"><?= icon('calendar-plus', 'icon--sm') ?><?= e(t('Add to my calendar')) ?></button>
    </form>
  <?php endif; ?>

  <div class="msg__status">
    <?php if ($grounding): ?><?= badge($grounding[0], $grounding[1], $grounding[2]) ?><?php endif; ?>
    <?php if ($message['status'] === 'cancelled'): ?><?= badge(t('Stopped'), 'neutral') ?><?php endif; ?>
    <?php if (($message['error_code'] ?? null) === 'refused'): ?><?= badge(t('Declined'), 'neutral') ?><?php endif; ?>
    <time datetime="<?= e(fmt_date($message['created_at'], 'iso')) ?>"><?= e(fmt_date($message['created_at'], 'time')) ?></time>
  </div>
  <div class="msg__actions">
    <?php if ($message['status'] !== 'error'): ?>
      <button type="button" class="btn btn--quiet" data-copy-message="#m-<?= $id ?> .msg__body"><?= icon('copy', 'icon--xs') ?><?= e(t('Copy')) ?></button>
    <?php endif; ?>
    <?php if ($isLast): ?>
      <button type="button" class="btn btn--quiet" data-regenerate><?= icon('refresh-cw', 'icon--xs') ?><?= e($message['status'] === 'complete' ? t('Try again') : t('Retry')) ?></button>
    <?php endif; ?>
    <?php if ($message['status'] === 'complete'): ?>
      <button type="button" class="btn btn--quiet" data-report="<?= $id ?>"><?= icon('flag', 'icon--xs') ?><?= e(t('Report')) ?></button>
      <?php if ($isStudent && !empty($conversation['class_id']) && (bool) setting('features.share_with_teacher', true)): ?>
        <button type="button" class="btn btn--quiet" data-share="<?= $id ?>"><?= icon('share-2', 'icon--xs') ?><?= e(t('Share with teacher')) ?></button>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</article>
<aside class="notes<?= $message['citations'] ? '' : ' notes--none' ?>" aria-label="<?= e(t('Sources for this answer')) ?>">
  <?php if ($message['citations']): ?>
    <p class="notes__label"><?= e(tp(':count source', ':count sources', count($message['citations']))) ?></p>
    <?php foreach ($message['citations'] as $c):
        $where = Retrieval::location($c);
        $fileUrl = url('/materials/' . (int) $c['material_id'] . '/file') . (!empty($c['page_from']) && ($c['extension'] ?? '') === 'pdf' ? '#page=' . (int) $c['page_from'] : '');
    ?>
      <div class="source-tag" id="cite-<?= $id ?>-<?= (int) $c['ref_no'] ?>" tabindex="-1">
        <div class="source-tag__head"><span class="source-tag__no"><?= (int) $c['ref_no'] ?></span><span class="source-tag__title"><?= e($c['title']) ?></span></div>
        <?php if ($where !== ''): ?><span class="source-tag__where"><?= e($where) ?></span><?php endif; ?>
        <p class="source-tag__quote">“<?= e($c['excerpt']) ?>”</p>
        <?php if (!empty($c['material_id'])): ?><a class="source-tag__open" href="<?= e($fileUrl) ?>" target="_blank" rel="noopener"><?= e(t('Open source')) ?></a><?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</aside>
