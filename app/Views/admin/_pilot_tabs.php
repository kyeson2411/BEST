<?php /** @var string $active feedback|report */ ?>
<nav class="tabs" aria-label="<?= e(t('Feedback and pilot report')) ?>">
  <a class="tab" href="<?= e(url('/admin/feedback')) ?>"<?= $active === 'feedback' ? ' aria-current="page"' : '' ?>><?= e(t('Feedback')) ?></a>
  <a class="tab" href="<?= e(url('/admin/pilot')) ?>"<?= $active === 'report' ? ' aria-current="page"' : '' ?>><?= e(t('Weekly pilot report')) ?></a>
</nav>
