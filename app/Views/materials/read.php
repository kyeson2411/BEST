<?php
/** @var array $material @var array $version @var array $class @var string $text @var bool $isMarkdown @var bool $isStudent */
use Sibol\Core\Uploads;

$canAsk = $isStudent && my_ai_on() && (int) $material['ai_enabled'] && $version['processing_status'] === 'ready';
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e($material['title']) ?></h1>
    <p class="page-lead"><?= e($class['name']) ?>, <?= e(strtoupper((string) $version['extension'])) ?>, <?= e(Uploads::humanSize((int) $version['size_bytes'])) ?></p>
  </div>
  <div class="page-actions">
    <a class="btn btn--secondary" href="<?= e(url('/materials/' . (int) $material['id'] . '/file', ['download' => 1])) ?>"><?= icon('download', 'icon--sm') ?><?= e(t('Download')) ?></a>
    <?php if ($canAsk): ?>
      <a class="btn btn--secondary" href="<?= e(url('/companion/new', ['mode' => 'ask_materials', 'class_id' => (int) $class['id'], 'material_id' => (int) $material['id']])) ?>"><?= icon('messages-square', 'icon--sm') ?><?= e(t('Ask about it')) ?></a>
    <?php endif; ?>
  </div>
</div>

<article class="prose">
  <?php if ($isMarkdown): ?>
    <?= markdown($text, ['heading_base' => 2]) ?>
  <?php else: ?>
    <?php foreach (preg_split('/\n\s*\n/', trim($text)) ?: [] as $para): ?>
      <p><?= nl2br(e($para)) ?></p>
    <?php endforeach; ?>
  <?php endif; ?>
</article>
