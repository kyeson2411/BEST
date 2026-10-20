<?php
/** @var \Sibol\Core\Paginator $pager @var string $path @var array $query */
if ($pager->pages() <= 1) {
    if ($pager->total > 0) {
        echo '<p class="pagination">' . e(tp('Showing :count result', 'Showing :count results', $pager->total)) . '</p>';
    }
    return;
}
$q = $query ?? [];
?>
<nav class="pagination" aria-label="<?= e(t('Pages')) ?>">
  <span><?= e(t('Showing :from–:to of :total', ['from' => $pager->from(), 'to' => $pager->to(), 'total' => $pager->total])) ?></span>
  <span class="pagination__links">
    <?php if ($pager->page > 1): ?>
      <a class="btn btn--secondary btn--sm" href="<?= e(url($path, array_merge($q, ['page' => $pager->page - 1]))) ?>"><?= icon('chevron-left', 'icon--sm') ?><?= e(t('Previous')) ?></a>
    <?php endif; ?>
    <span class="btn btn--quiet btn--sm" aria-current="page"><?= e(t('Page :page of :pages', ['page' => $pager->page, 'pages' => $pager->pages()])) ?></span>
    <?php if ($pager->page < $pager->pages()): ?>
      <a class="btn btn--secondary btn--sm" href="<?= e(url($path, array_merge($q, ['page' => $pager->page + 1]))) ?>"><?= e(t('Next')) ?><?= icon('chevron-right', 'icon--sm') ?></a>
    <?php endif; ?>
  </span>
</nav>
