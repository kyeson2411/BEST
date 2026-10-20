<?php
/**
 * A teacher's comment bank: saved feedback comments to insert while grading, optionally tied to a rubric criterion.
 * @var list<array> $comments @var list<string> $criteria rubric criterion titles from the teacher's assignments
 * @var int|null $editing comment whose form is open
 */
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Comment bank')) ?></h1>
    <p class="page-lead"><?= e(t('Feedback you write often, saved once. Insert them while grading, then make each one fit the student\'s work. Only you see your comment bank.')) ?></p>
  </div>
</div>

<datalist id="criterion-titles">
  <?php foreach ($criteria as $title): ?><option value="<?= e($title) ?>"></option><?php endforeach; ?>
</datalist>

<div class="layout-split">
  <section class="section" aria-labelledby="bank-title">
    <h2 class="section__title sr-only" id="bank-title"><?= e(t('Saved comments')) ?></h2>
    <?php if (!$comments): ?>
      <?= partial('empty', ['compact' => true, 'title' => t('No saved comments yet'), 'body' => t('Add one here, or save a comment from the grading page as you write it.')]) ?>
    <?php else: ?>
      <ul class="rows">
        <?php foreach ($comments as $c): $open = (int) $editing === (int) $c['id']; ?>
          <li class="row row--wrap">
            <div class="row__main">
              <p class="comment-bank__body"><?= e($c['body']) ?></p>
              <div class="row__meta">
                <?php if ($c['criterion']): ?><span><?= e(t('Criterion: :c', ['c' => $c['criterion']])) ?></span><?php else: ?><span><?= e(t('Any work')) ?></span><?php endif; ?>
                <span><?= e((int) $c['uses'] ? tp('Used :count time', 'Used :count times', (int) $c['uses']) : t('Not used yet')) ?></span>
              </div>
            </div>
            <div class="row__aside">
              <a class="btn btn--quiet btn--sm" href="<?= e(url('/teacher/comments', ['edit' => (int) $c['id']])) ?>#comment-<?= (int) $c['id'] ?>"><?= icon('pencil', 'icon--sm') ?><?= e(t('Edit')) ?><span class="sr-only"> <?= e(str_limit($c['body'], 40)) ?></span></a>
              <form method="post" action="<?= e(url('/teacher/comments/' . (int) $c['id'] . '/delete')) ?>" data-confirm="<?= e(t('Feedback you already gave students keeps this text.')) ?>" data-confirm-title="<?= e(t('Remove this comment from your bank?')) ?>" data-confirm-action="<?= e(t('Remove')) ?>" data-confirm-tone="danger">
                <?= csrf_field() ?><button class="btn btn--quiet btn--sm" type="submit"><?= icon('trash-2', 'icon--sm') ?><span class="sr-only"><?= e(t('Remove :name', ['name' => str_limit($c['body'], 40)])) ?></span></button>
              </form>
            </div>
            <?php if ($open): ?>
              <form method="post" action="<?= e(url('/teacher/comments/' . (int) $c['id'])) ?>" id="comment-<?= (int) $c['id'] ?>" class="comment-bank__edit">
                <?= csrf_field() ?>
                <?= field('textarea', 'body', t('Comment'), ['id' => 'cb-body-' . $c['id'], 'value' => $c['body'], 'required' => true, 'rows' => 3, 'attrs' => ['maxlength' => \Sibol\Services\CommentBank::MAX_BODY]]) ?>
                <?= field('text', 'criterion', t('Rubric criterion'), ['id' => 'cb-crit-' . $c['id'], 'value' => (string) $c['criterion'], 'optional_label' => true, 'hint' => t('Shown next to this criterion when you grade with a rubric. Leave empty for any work.'), 'attrs' => ['maxlength' => \Sibol\Services\CommentBank::MAX_CRITERION, 'list' => 'criterion-titles']]) ?>
                <div class="form-actions"><button class="btn btn--primary btn--sm" type="submit"><?= e(t('Save')) ?></button> <a class="btn btn--quiet btn--sm" href="<?= e(url('/teacher/comments')) ?>"><?= e(t('Cancel')) ?></a></div>
              </form>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <aside class="panel" aria-labelledby="add-title">
    <h2 class="panel__title" id="add-title"><?= e(t('Add a comment')) ?></h2>
    <form method="post" action="<?= e(url('/teacher/comments')) ?>">
      <?= csrf_field() ?>
      <?= field('textarea', 'body', t('Comment'), ['id' => 'cb-body', 'required' => true, 'rows' => 4, 'isolated' => $editing !== null, 'attrs' => ['maxlength' => \Sibol\Services\CommentBank::MAX_BODY]]) ?>
      <?= field('text', 'criterion', t('Rubric criterion'), ['id' => 'cb-crit', 'optional_label' => true, 'isolated' => $editing !== null, 'hint' => t('Shown next to this criterion when you grade with a rubric. Leave empty for any work.'), 'attrs' => ['maxlength' => \Sibol\Services\CommentBank::MAX_CRITERION, 'list' => 'criterion-titles']]) ?>
      <button class="btn btn--primary" type="submit"><?= icon('plus', 'icon--sm') ?><?= e(t('Save comment')) ?></button>
    </form>
  </aside>
</div>
