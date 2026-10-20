<?php /** @var list<array> $classes */
$active = array_values(array_filter($classes, static fn ($c) => $c['status'] === 'active'));
$archived = array_values(array_filter($classes, static fn ($c) => $c['status'] !== 'active'));
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Classes')) ?></h1>
    <p class="page-lead"><?= e(t('The classes your school has assigned to you. Administrators create classes and assign teachers.')) ?></p>
  </div>
</div>
<?php if (!$active): ?>
  <?= partial('empty', ['title' => t('No classes assigned yet'), 'body' => t('Ask your school administrator to assign you to a class.')]) ?>
<?php else: ?>
  <div class="class-grid">
    <?php foreach ($active as $c): ?>
      <article class="class-card">
        <span class="class-card__subject"><?= e($c['subject_name']) ?><?= $c['period_name'] ? ', ' . e($c['period_name']) : '' ?></span>
        <h2 class="class-card__title"><a href="<?= e(url('/teacher/classes/' . (int) $c['id'])) ?>"><?= e($c['name']) ?></a></h2>
        <p class="class-card__meta"><?= e(tp(':count student', ':count students', (int) $c['students'])) ?>, <?= e(tp(':count open task', ':count open tasks', (int) $c['open_work'])) ?></p>
        <div class="class-card__foot">
          <?php if ((int) $c['to_review'] > 0): ?><?= badge(tp(':count to review', ':count to review', (int) $c['to_review']), 'amber') ?><?php endif; ?>
          <?php if ((int) $c['unseen_shares'] > 0): ?><?= badge(tp(':count shared excerpt', ':count shared excerpts', (int) $c['unseen_shares']), 'leaf') ?><?php endif; ?>
          <?php if ($c['join_code_enabled'] && $c['join_code_expires_at'] > \Sibol\Core\Db::now()): ?><span class="tag"><?= icon('key-round', 'icon--xs') ?><span class="code-text"><?= e($c['join_code']) ?></span></span><?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php if ($archived): ?>
  <section class="section" style="margin-top: 40px">
    <div class="section__head"><h2 class="section__title"><?= e(t('Archived classes')) ?></h2></div>
    <ul class="rows">
      <?php foreach ($archived as $c): ?>
        <li class="row row--muted"><div class="row__main"><a class="row__title" href="<?= e(url('/teacher/classes/' . (int) $c['id'])) ?>"><?= e($c['name']) ?></a><div class="row__meta"><span><?= e($c['subject_name']) ?></span></div></div></li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>
