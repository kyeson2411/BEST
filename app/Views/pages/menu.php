<?php /** @var list<array> $items @var list<array> $footer */ ?>
<div class="page-head"><div class="page-head__text"><h1 class="page-title"><?= e(t('Menu')) ?></h1></div></div>
<ul class="rows">
  <?php foreach (array_merge($items, $footer) as $item): ?>
    <li class="row">
      <span class="row__lead"><?= icon($item['icon']) ?></span>
      <div class="row__main"><a class="row__title" href="<?= e(url($item['url'])) ?>"><?= e($item['label']) ?></a></div>
      <?php if (!empty($item['count'])): ?><span class="row__aside"><?= badge((string) (int) $item['count'], 'amber') ?></span><?php endif; ?>
    </li>
  <?php endforeach; ?>
  <li class="row">
    <span class="row__lead"><?= icon('log-out') ?></span>
    <div class="row__main">
      <form method="post" action="<?= e(url('/logout')) ?>" data-signout><?= csrf_field() ?><button class="link-btn" type="submit"><?= e(t('Sign out')) ?></button></form>
    </div>
  </li>
</ul>
