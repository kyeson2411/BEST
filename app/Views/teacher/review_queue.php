<?php /** @var list<array> $rows */ ?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('To review')) ?></h1>
    <p class="page-lead"><?= e($rows ? tp(':count submission is waiting, oldest first.', ':count submissions are waiting, oldest first.', count($rows)) : t('Nothing is waiting for you.')) ?></p>
  </div>
  <div class="page-actions"><a class="btn btn--secondary" href="<?= e(url('/teacher/comments')) ?>"><?= icon('message-square-text', 'icon--sm') ?><?= e(t('Comment bank')) ?></a></div>
</div>
<?php if (!$rows): ?>
  <?= partial('empty', ['title' => t('All caught up'), 'body' => t('New submissions that need marking appear here. Quizzes with only automatically marked questions do not need you.')]) ?>
<?php else: ?>
  <ul class="entries">
    <?php foreach ($rows as $r): ?>
      <li class="entry">
        <?= partial('entry_date', ['at' => $r['submitted_at']]) ?>
        <div class="entry__main">
          <a class="row__title" href="<?= e(url('/teacher/submissions/' . (int) $r['id'])) ?>"><?= e(user_name($r)) ?>: <?= e($r['title']) ?></a>
          <div class="row__meta"><span><?= e($r['class_name']) ?></span><span><?= e($r['kind'] === 'quiz' ? t('Quiz with written answers') : t('Assignment')) ?></span><span><?= e(fmt_date($r['submitted_at'], 'time')) ?></span></div>
        </div>
        <div class="entry__aside">
          <?php if ((int) $r['is_late']): ?><?= badge(t('Late'), 'amber') ?><?php endif; ?>
          <a class="btn btn--primary btn--sm" href="<?= e(url('/teacher/submissions/' . (int) $r['id'])) ?>"><?= e(t('Grade')) ?></a>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
