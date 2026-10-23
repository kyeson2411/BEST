<?php
/** @var array $material @var array $version @var array $class @var string $text @var bool $isMarkdown @var bool $isStudent
 *  @var list<string> $kinds @var array|null $reading @var bool $readableOriginal */
use Sibol\Core\Uploads;
use Sibol\Services\ReadingVersions;

$canAsk = $isStudent && my_ai_on() && (int) $material['ai_enabled'] && $version['processing_status'] === 'ready';
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e($material['title']) ?></h1>
    <p class="page-lead"><?= e($class['name']) ?>, <?= e(strtoupper((string) $version['extension'])) ?>, <?= e(Uploads::humanSize((int) $version['size_bytes'])) ?></p>
  </div>
  <div class="page-actions">
    <?= listen_button('#reading', $text, 'btn btn--secondary') ?>
    <a class="btn btn--secondary" href="<?= e(url('/materials/' . (int) $material['id'] . '/file', ['download' => 1])) ?>"><?= icon('download', 'icon--sm') ?><?= e(t('Download')) ?></a>
    <?php if ($canAsk): ?>
      <a class="btn btn--secondary" href="<?= e(url('/companion/new', ['mode' => 'ask_materials', 'class_id' => (int) $class['id'], 'material_id' => (int) $material['id']])) ?>"><?= icon('messages-square', 'icon--sm') ?><?= e(t('Ask about it')) ?></a>
      <a class="btn btn--secondary" href="<?= e(url('/companion/new', ['mode' => 'explain_back', 'class_id' => (int) $class['id'], 'material_id' => (int) $material['id']])) ?>"><?= icon('message-square-quote', 'icon--sm') ?><?= e(t('Explain it back')) ?></a>
    <?php endif; ?>
  </div>
</div>

<?php if ($kinds): $readBase = '/materials/' . (int) $material['id'] . '/read'; ?>
  <nav class="tabs" aria-label="<?= e(t('Reading versions')) ?>">
    <a class="tab" href="<?= e(url($readableOriginal ? $readBase : '/materials/' . (int) $material['id'] . '/file')) ?>"<?= $reading === null ? ' aria-current="page"' : '' ?>><?= e(t('Original')) ?></a>
    <?php foreach ($kinds as $k): ?>
      <a class="tab" href="<?= e(url($readBase, ['v' => $k])) ?>"<?= $reading !== null && $reading['kind'] === $k ? ' aria-current="page"' : '' ?>><?= e(ReadingVersions::label($k)) ?></a>
    <?php endforeach; ?>
  </nav>
<?php endif; ?>
<?php if ($reading !== null && $reading['status'] !== 'approved'): ?>
  <div class="callout callout--amber" style="margin-bottom: 20px"><?= icon('eye-off') ?><p><?= e(t('Preview of a draft. Students do not see this version until you show it to them.')) ?></p></div>
<?php elseif ($reading !== null): ?>
  <p class="meta"><?= e(t(':version of the material, checked by your teacher. The original is in the first tab.', ['version' => ReadingVersions::label((string) $reading['kind'])])) ?></p>
<?php endif; ?>

<article class="prose" id="reading">
  <?php if ($isMarkdown): ?>
    <?= markdown($text, ['heading_base' => 2]) ?>
  <?php else: ?>
    <?php foreach (preg_split('/\n\s*\n/', trim($text)) ?: [] as $para): ?>
      <p><?= nl2br(e($para)) ?></p>
    <?php endforeach; ?>
  <?php endif; ?>
</article>
