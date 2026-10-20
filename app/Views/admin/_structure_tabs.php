<?php /** @var string $active subjects|periods|sections|competencies */ ?>
<nav class="tabs" aria-label="<?= e(t('School structure')) ?>">
  <a class="tab" href="<?= e(url('/admin/subjects')) ?>"<?= $active === 'subjects' ? ' aria-current="page"' : '' ?>><?= e(t('Subjects')) ?></a>
  <a class="tab" href="<?= e(url('/admin/academic-periods')) ?>"<?= $active === 'periods' ? ' aria-current="page"' : '' ?>><?= e(t('Academic periods')) ?></a>
  <a class="tab" href="<?= e(url('/admin/sections')) ?>"<?= $active === 'sections' ? ' aria-current="page"' : '' ?>><?= e(t('Sections and advisers')) ?></a>
  <a class="tab" href="<?= e(url('/admin/competencies')) ?>"<?= $active === 'competencies' ? ' aria-current="page"' : '' ?>><?= e(t('Learning competencies')) ?></a>
</nav>
