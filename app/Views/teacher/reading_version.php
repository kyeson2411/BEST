<?php
/** @var array $material @var string $kind @var array|null $version @var int|null $aiRemaining @var string $aiLabel */
use Sibol\Services\ReadingVersions;

$base = '/teacher/materials/' . (int) $material['id'] . '/reading/' . $kind;
$approved = $version && $version['status'] === 'approved';
$outdated = $version && $version['source_version_id'] !== null && (int) $version['source_version_id'] !== (int) $material['current_version_id'];
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(ReadingVersions::label($kind)) ?></h1>
    <p class="page-lead"><?= e($material['title']) ?>. <?= e($kind === 'filipino'
        ? t('The same material in Filipino, for students who read better in Filipino. Technical terms can stay in English.')
        : t('The same material in simpler English, for students who read below grade level. Every fact of the original stays.')) ?></p>
  </div>
  <div class="page-actions">
    <?php if ($version): ?><?= badge($approved ? t('Shown to students') : t('Draft: students do not see it'), $approved ? 'leaf' : 'neutral') ?><?php endif; ?>
    <a class="btn btn--secondary" href="<?= e(url('/materials/' . (int) $material['id'] . '/read')) ?>" target="_blank" rel="noopener"><?= icon('eye', 'icon--sm') ?><?= e(t('Open the original')) ?></a>
  </div>
</div>

<?php if ($version && (int) $version['ai_assisted'] && !$approved): ?>
  <div class="callout callout--amber" style="margin-bottom: 20px"><?= icon('square-pen') ?><p><?= e(t('This draft was written with AI from the material. Check every sentence against the original, and fix anything changed or added, before you show it to students.')) ?></p></div>
<?php endif; ?>
<?php if ($outdated): ?>
  <div class="callout callout--amber" style="margin-bottom: 20px"><?= icon('triangle-alert') ?><p><?= e(t('A newer version of the material was uploaded after this text was made. Check that it still matches.')) ?></p></div>
<?php endif; ?>

<div class="layout-split">
  <div class="stack">
    <form method="post" action="<?= e(url($base)) ?>" class="panel">
      <?= csrf_field() ?>
      <?= field('textarea', 'content', t('Text'), ['value' => (string) ($version['content'] ?? ''), 'rows' => 22, 'required' => true,
          'hint' => t('Markdown works: # for headings, - for lists. Students see this beside the original, with a Listen button.'), 'attrs' => ['maxlength' => ReadingVersions::MAX_CHARS]]) ?>
      <div class="form-actions">
        <button class="btn btn--primary" type="submit" name="action" value="approve"><?= icon('eye', 'icon--sm') ?><?= e(t('Save and show to students')) ?></button>
        <button class="btn btn--secondary" type="submit" name="action" value="save"><?= e(t('Save as draft')) ?></button>
      </div>
    </form>
  </div>

  <aside class="stack">
    <section class="panel" aria-labelledby="ai-title">
      <h2 class="panel__title" id="ai-title"><?= e(t('Draft it with AI')) ?></h2>
      <p class="meta"><?= e($kind === 'filipino'
          ? t('The AI translates the material\'s own text into Filipino. Nothing new is added, and you check it before students see it.')
          : t('The AI rewrites the material\'s own text in shorter sentences and common words. Nothing new is added, and you check it before students see it.')) ?></p>
      <form method="post" action="<?= e(url($base . '/draft')) ?>"<?= $version ? ' data-confirm="' . e(t('The AI draft replaces the text in the box, and students stop seeing this version until you show it again.')) . '" data-confirm-action="' . e(t('Replace')) . '"' : '' ?>>
        <?= csrf_field() ?>
        <button class="btn btn--secondary btn--sm" type="submit" data-busy="<?= e(t('Writing…')) ?>"><?= icon('square-pen', 'icon--sm') ?><?= e($version ? t('Draft again with AI') : t('Draft with AI')) ?></button>
      </form>
      <?php if ($aiLabel !== ''): ?><p class="meta" style="margin-top:8px"><?= e($aiLabel) ?></p><?php endif; ?>
      <?php if ($aiRemaining !== null): ?><p class="meta"><?= e(tp(':count AI request left today', ':count AI requests left today', $aiRemaining)) ?></p><?php endif; ?>
    </section>
    <?php if ($version): ?>
      <section class="panel" aria-labelledby="manage-title">
        <h2 class="panel__title" id="manage-title"><?= e(t('This version')) ?></h2>
        <?php if ($approved): ?>
          <form method="post" action="<?= e(url($base . '/withdraw')) ?>" style="margin-bottom: 8px"><?= csrf_field() ?>
            <button class="btn btn--secondary btn--sm" type="submit"><?= icon('eye-off', 'icon--sm') ?><?= e(t('Stop showing to students')) ?></button>
          </form>
        <?php endif; ?>
        <form method="post" action="<?= e(url($base . '/delete')) ?>" data-confirm="<?= e(t('Students will no longer see it, and the text is removed.')) ?>" data-confirm-title="<?= e(t('Delete this version?')) ?>" data-confirm-action="<?= e(t('Delete')) ?>"><?= csrf_field() ?>
          <button class="btn btn--danger-quiet btn--sm" type="submit"><?= icon('trash-2', 'icon--sm') ?><?= e(t('Delete')) ?></button>
        </form>
      </section>
    <?php endif; ?>
  </aside>
</div>
