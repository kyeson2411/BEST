<?php /** @var list<array> $sections */
use Sibol\Services\Sections;
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Advisory')) ?></h1>
    <p class="page-lead"><?= e(t('Sections you advise. You can print sign-in and password slips for their students.')) ?></p>
  </div>
</div>
<?php if (!$sections): ?>
  <?= partial('empty', ['title' => t('You do not advise a section'), 'body' => t('Your school administrator chooses each section\'s adviser in Subjects and terms.')]) ?>
<?php else: ?>
  <ul class="rows" style="max-width: 760px">
    <?php foreach ($sections as $s): ?>
      <li class="row">
        <div class="row__main">
          <a class="row__title" href="<?= e(url('/teacher/advisory/' . (int) $s['id'])) ?>"><?= e(Sections::label($s)) ?></a>
          <div class="row__meta"><?php if ($s['period_name']): ?><span><?= e($s['period_name']) ?></span><?php endif; ?>
            <span><?= e(tp(':count student', ':count students', (int) $s['students'])) ?></span><span><?= e(tp(':count class', ':count classes', (int) $s['classes'])) ?></span></div>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
