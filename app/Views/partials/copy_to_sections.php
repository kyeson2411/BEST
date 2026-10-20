<?php
/**
 * "Copy to other sections" panel for a material, assignment or lesson (see Services/SectionCopies).
 * @var string $action @var list<array{id:int,name:string,copies:int}> $targets
 * @var list<array{class_name:string,url:string,label:string,tone:string}> $copies
 */
if (!$targets && !$copies) {
    return;
}
$error = field_error('class_ids');
?>
<section class="panel" aria-labelledby="copy-title">
  <h2 class="panel__title" id="copy-title"><?= e(t('Copy to other sections')) ?></h2>
  <?php if ($copies): ?>
    <p class="meta" id="copy-list-title" style="margin: 0"><?= e(t('Copies in other sections')) ?></p>
    <ul class="rows" aria-labelledby="copy-list-title" style="margin-bottom: 12px">
      <?php foreach ($copies as $c): ?>
        <li class="row" style="padding:8px 0">
          <div class="row__main"><a class="row__title" href="<?= e(url($c['url'])) ?>"><?= e($c['class_name']) ?></a></div>
          <span class="row__aside"><?= badge($c['label'], $c['tone']) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <?php if ($targets): ?>
    <form method="post" action="<?= e(url($action)) ?>">
      <?= csrf_field() ?>
      <fieldset style="margin-bottom: 12px"<?= $error ? ' aria-describedby="copy-error"' : '' ?>>
        <legend class="sr-only"><?= e(t('Sections to copy to')) ?></legend>
        <p class="fieldset-hint" style="margin-top: 0"><?= e(t('Copies arrive as drafts. Modules are matched by name, and linked materials come along.')) ?></p>
        <?php foreach ($targets as $c): ?>
          <?= checkbox('class_ids[]', $c['name'], false, $c['copies'] ? tp('Already has :count copy', 'Already has :count copies', $c['copies']) : null, (string) $c['id'], 'copy-class-' . $c['id']) ?>
        <?php endforeach; ?>
        <?php if ($error): ?><p class="field__error" id="copy-error"><?= icon('circle-alert', 'icon--xs') ?><?= e($error) ?></p><?php endif; ?>
      </fieldset>
      <button class="btn btn--secondary btn--sm" type="submit" data-busy="<?= e(t('Copying…')) ?>"><?= icon('copy', 'icon--sm') ?><?= e(t('Copy as drafts')) ?></button>
    </form>
  <?php endif; ?>
</section>
