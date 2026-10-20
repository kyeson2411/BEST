<?php
/**
 * The teacher's comment bank on the grading page. A saved comment goes into the comment box last written in
 * (grading.js); without JavaScript the bank is a list to copy from. Only its owner sees it.
 * @var array $bank from CommentBank::forGrading @var list<array> $rubric the assignment's criteria
 */
$chip = static function (array $c): string {
    return '<li><button type="button" class="comment-chip" data-insert-comment="' . e((string) $c['body']) . '" data-comment-id="' . (int) $c['id'] . '">'
        . e((string) $c['body']) . '</button></li>';
};
?>
<details class="comment-bank" data-comment-bank data-comments-url="<?= e(url('/teacher/comments')) ?>">
  <summary><?= icon('message-square-text', 'icon--sm') ?> <?= e(t('Comment bank')) ?> <span class="count"><?= (int) $bank['count'] ?></span></summary>
  <div class="comment-bank__inner">
    <?php if ($bank['count'] > 8): ?>
      <label class="sr-only" for="bank-filter"><?= e(t('Find a saved comment')) ?></label>
      <input class="input comment-bank__filter" type="search" id="bank-filter" placeholder="<?= e(t('Find a saved comment')) ?>" autocomplete="off" data-bank-filter>
    <?php endif; ?>
    <p class="meta" data-bank-empty<?= $bank['count'] ? ' hidden' : '' ?>><?= e(t('No saved comments yet. Save the comment you are writing, or add some in your comment bank.')) ?></p>
    <?php foreach ($bank['criteria'] as $group): ?>
      <h3 class="comment-bank__group"><?= e($group['title']) ?></h3>
      <ul class="comment-bank__list"><?php foreach ($group['comments'] as $c): ?><?= $chip($c) ?><?php endforeach; ?></ul>
    <?php endforeach; ?>
    <?php if ($bank['criteria']): ?><h3 class="comment-bank__group"><?= e(t('Other comments')) ?></h3><?php endif; ?>
    <ul class="comment-bank__list" data-bank-list><?php foreach ($bank['general'] as $c): ?><?= $chip($c) ?><?php endforeach; ?></ul>
    <div class="comment-bank__save" data-bank-save hidden>
      <?php if ($rubric): ?>
        <label class="field__label" for="bank-criterion"><?= e(t('Show it next to')) ?></label>
        <select class="input select" id="bank-criterion" data-bank-criterion>
          <option value=""><?= e(t('Any work')) ?></option>
          <?php foreach ($rubric as $r): ?><option value="<?= e((string) $r['title']) ?>"><?= e((string) $r['title']) ?></option><?php endforeach; ?>
        </select>
      <?php endif; ?>
      <button type="button" class="btn btn--secondary btn--sm" data-bank-save-button><?= icon('bookmark', 'icon--sm') ?><?= e(t('Save the comment you are writing')) ?></button>
      <p class="field__hint"><?= e(t('Saves the text you selected, or all of the comment box you were last writing in.')) ?></p>
    </div>
    <p class="meta" style="margin:0"><a href="<?= e(url('/teacher/comments')) ?>"><?= e(t('Manage your comment bank')) ?></a></p>
  </div>
</details>
