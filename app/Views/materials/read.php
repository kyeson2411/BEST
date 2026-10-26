<?php
/** @var array $material @var array $version @var array $class @var string $text @var bool $isMarkdown @var bool $isStudent
 *  @var list<string> $kinds @var array|null $reading @var bool $readableOriginal
 *  @var list<array{page:int|null,section:string|null,text:string}>|null $units the text read from a PDF, Word or PowerPoint file
 *  @var bool $cut */
use Sibol\Core\Uploads;
use Sibol\Services\ReadingVersions;

$canAsk = $isStudent && my_ai_on() && (int) $material['ai_enabled'] && $version['processing_status'] === 'ready';
$ext = (string) $version['extension'];
$fileUrl = '/materials/' . (int) $material['id'] . '/file';
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e($material['title']) ?></h1>
    <p class="page-lead"><?= e($class['name']) ?>, <?= e(strtoupper((string) $version['extension'])) ?>, <?= e(Uploads::humanSize((int) $version['size_bytes'])) ?></p>
  </div>
  <div class="page-actions">
    <?php if (trim($text) !== ''): ?><?= listen_button('#reading', $text, 'btn btn--secondary') ?><?php endif; ?>
    <?php if ($units !== null && $ext === 'pdf'): ?>
      <a class="btn btn--secondary" href="<?= e(url($fileUrl)) ?>" target="_blank" rel="noopener noreferrer"><?= icon('external-link', 'icon--sm') ?><?= e(t('Open the PDF')) ?></a>
    <?php endif; ?>
    <a class="btn btn--secondary" href="<?= e(url($fileUrl, ['download' => 1])) ?>"><?= icon('download', 'icon--sm') ?><?= e(t('Download')) ?></a>
    <?php if ($canAsk): ?>
      <a class="btn btn--secondary" href="<?= e(url('/companion/new', ['mode' => 'ask_materials', 'class_id' => (int) $class['id'], 'material_id' => (int) $material['id']])) ?>"><?= icon('messages-square', 'icon--sm') ?><?= e(t('Ask about it')) ?></a>
      <a class="btn btn--secondary" href="<?= e(url('/companion/new', ['mode' => 'explain_back', 'class_id' => (int) $class['id'], 'material_id' => (int) $material['id']])) ?>"><?= icon('message-square-quote', 'icon--sm') ?><?= e(t('Explain it back')) ?></a>
    <?php endif; ?>
  </div>
</div>

<?php if ($kinds): $readBase = '/materials/' . (int) $material['id'] . '/read'; ?>
  <nav class="tabs" aria-label="<?= e(t('Reading versions')) ?>">
    <a class="tab" href="<?= e(url($readBase)) ?>"<?= $reading === null ? ' aria-current="page"' : '' ?>><?= e(t('Original')) ?></a>
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

<?php if ($units === []): ?>
  <div class="callout"><?= icon('info') ?><p><?= e($version['processing_status'] === 'ready' || in_array($version['processing_status'], ['failed', 'unsupported'], true)
      ? t('This file cannot be shown here. Download it to open it on your device.')
      : t('Sibol is still reading this file. Come back in a minute, or download it to open it now.')) ?></p></div>
<?php elseif ($units !== null): ?>
  <div class="callout" style="margin-bottom: 20px"><?= icon('info') ?><p><?= e($ext === 'pdf'
      ? t('This is the text of the file, without its pictures or layout. To see it as your teacher made it, open the PDF or download it.')
      : t('This is the text of the file, without its pictures or layout. To see it as your teacher made it, download it.')) ?></p></div>
<?php endif; ?>

<article class="prose" id="reading">
  <?php if ($units !== null): ?>
    <?php foreach ($units as $u):
        $body = (string) $u['text'];
        $heading = $u['section'] !== null ? (string) $u['section'] : null;
        if ($ext === 'pptx' && $heading !== null) {
            // A slide's heading already holds its first line, the slide title.
            $lines = explode("\n", $body);
            if ($lines && $heading === t('Slide :n', ['n' => (int) $u['page']]) . ': ' . trim($lines[0])) {
                $body = implode("\n", array_slice($lines, 1));
            }
        } elseif ($heading === null && $ext === 'pdf' && $u['page'] !== null && count($units) > 1) {
            $heading = t('Page :n', ['n' => (int) $u['page']]);
        } ?>
      <?php if ($heading !== null): ?><h2 class="md-h"><?= e($heading) ?></h2><?php endif; ?>
      <?php if ($ext === 'docx'): ?>
        <?= markdown($body) ?>
      <?php else: ?>
        <?php foreach (preg_split('/\n\s*\n/', trim($body)) ?: [] as $para): ?>
          <?php if (trim($para) !== ''): ?><p><?= nl2br(e($para)) ?></p><?php endif; ?>
        <?php endforeach; ?>
      <?php endif; ?>
    <?php endforeach; ?>
    <?php if ($cut): ?><p class="meta"><?= e(t('The rest of this file is too long to show here. Download it to read all of it.')) ?></p><?php endif; ?>
  <?php elseif ($isMarkdown): ?>
    <?= markdown($text, ['heading_base' => 2]) ?>
  <?php else: ?>
    <?php foreach (preg_split('/\n\s*\n/', trim($text)) ?: [] as $para): ?>
      <p><?= nl2br(e($para)) ?></p>
    <?php endforeach; ?>
  <?php endif; ?>
</article>
