<?php /** @var string $active subjects|periods */ ?>
<nav class="tabs" aria-label="<?= e(t('School structure')) ?>">
  <a class="tab" href="<?= e(url('/admin/subjects')) ?>"<?= $active === 'subjects' ? ' aria-current="page"' : '' ?>><?= e(t('Subjects')) ?></a>
  <a class="tab" href="<?= e(url('/admin/academic-periods')) ?>"<?= $active === 'periods' ? ' aria-current="page"' : '' ?>><?= e(t('Academic periods')) ?></a>
</nav>
