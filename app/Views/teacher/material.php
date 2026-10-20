<?php
/** @var array $material @var array|null $version @var list<array> $versions @var list<array> $chunks @var int $chunkCount @var list<array> $modules @var int $views @var int $students @var int $maxMb */
use Sibol\Core\Uploads;
use Sibol\Services\Ingestion;
use Sibol\Services\Materials;

[$vLabel, $vTone] = Materials::visibilityLabel($material);
$isFile = $material['kind'] === 'file';
$status = $version['processing_status'] ?? null;
[$pLabel, $pTone] = Ingestion::statusLabel($status);
$steps = ['uploaded' => t('Uploaded'), 'extracting' => t('Reading text'), 'indexing' => t('Indexing'), 'ready' => t('Ready')];
$order = array_keys($steps);
$pos = array_search($status, $order, true);
$working = in_array($status, ['uploaded', 'extracting', 'indexing'], true);
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e($material['title']) ?></h1>
    <p class="page-lead"><?= e($material['class_name']) ?><?= $material['module_title'] ? ', ' . e($material['module_title']) : '' ?></p>
  </div>
  <div class="page-actions">
    <?php if ($isFile): ?><a class="btn btn--secondary" href="<?= e(url('/materials/' . (int) $material['id'] . '/file')) ?>" target="_blank" rel="noopener"><?= icon('eye', 'icon--sm') ?><?= e(t('Open file')) ?></a>
    <?php else: ?><a class="btn btn--secondary" href="<?= e((string) $material['url']) ?>" target="_blank" rel="noopener noreferrer"><?= icon('external-link', 'icon--sm') ?><?= e(t('Open link')) ?></a><?php endif; ?>
    <form method="post" action="<?= e(url('/teacher/materials/' . (int) $material['id'] . '/visibility')) ?>">
      <?= csrf_field() ?>
      <?php if ($material['visibility'] === 'draft'): ?>
        <input type="hidden" name="action" value="publish"><button class="btn btn--primary" type="submit"><?= icon('send', 'icon--sm') ?><?= e(t('Publish')) ?></button>
      <?php elseif ($material['visibility'] === 'published'): ?>
        <input type="hidden" name="action" value="unpublish"><button class="btn btn--secondary" type="submit"><?= icon('eye-off', 'icon--sm') ?><?= e(t('Move to draft')) ?></button>
      <?php else: ?>
        <input type="hidden" name="action" value="restore"><button class="btn btn--secondary" type="submit"><?= icon('archive-restore', 'icon--sm') ?><?= e(t('Restore')) ?></button>
      <?php endif; ?>
    </form>
  </div>
</div>

<div class="layout-split">
  <div class="stack">
    <?php if ($isFile): ?>
      <section class="section" aria-labelledby="proc-title" data-material-status="<?= e(url('/teacher/materials/' . (int) $material['id'] . '/status')) ?>"
               data-process-url="<?= e(url('/teacher/materials/' . (int) $material['id'] . '/process')) ?>" data-working="<?= $working ? '1' : '0' ?>"
               data-i18n="<?= json_attr(\Sibol\Services\ScriptStrings::material()) ?>">
        <div class="section__head"><h2 class="section__title" id="proc-title"><?= e(t('Companion access')) ?></h2></div>
        <p style="margin:0 0 8px"><span data-status-badge><?= badge($pLabel, $pTone) ?></span>
          <span class="meta" data-status-detail><?php if ($status === 'ready'): ?><?= e(tp(':count passage indexed', ':count passages indexed', $chunkCount)) ?><?= $version['page_count'] ? ', ' . e(tp(':count page', ':count pages', (int) $version['page_count'])) : '' ?>.<?php endif; ?></span></p>
        <ol class="steps" aria-label="<?= e(t('Processing steps')) ?>">
          <?php foreach ($steps as $key => $label):
              $i = array_search($key, $order, true);
              $cls = $status === 'failed' ? ($i === 0 ? 'is-done' : ($i === 1 ? 'is-failed' : '')) : ($pos === false ? '' : ($i < $pos || $status === 'ready' ? 'is-done' : ($i === $pos ? 'is-current' : '')));
          ?>
            <li class="<?= e($cls) ?>"><?= $cls === 'is-done' ? icon('check', 'icon--xs') : '' ?><?= e($label) ?></li>
          <?php endforeach; ?>
        </ol>
        <?php if (in_array($status, ['failed', 'unsupported'], true)): ?>
          <div class="callout callout--<?= $status === 'failed' ? 'coral' : 'amber' ?>" style="margin-top:12px"><?= icon('circle-alert') ?><div>
            <p><?= e((string) $version['processing_error']) ?></p>
            <?php if ($status === 'failed'): ?>
              <form method="post" action="<?= e(url('/teacher/materials/' . (int) $material['id'] . '/process')) ?>"><?= csrf_field() ?><button class="btn btn--secondary btn--sm" type="submit" data-busy="<?= e(t('Reading…')) ?>"><?= icon('refresh-cw', 'icon--sm') ?><?= e(t('Try again')) ?></button></form>
            <?php endif; ?>
          </div></div>
        <?php elseif ($working): ?>
          <noscript><form method="post" action="<?= e(url('/teacher/materials/' . (int) $material['id'] . '/process')) ?>"><?= csrf_field() ?><button class="btn btn--secondary btn--sm" type="submit"><?= e(t('Process now')) ?></button></form></noscript>
        <?php endif; ?>
        <p class="meta" style="margin-top:12px">
          <?php if (!(int) $material['ai_enabled']): ?><?= e(t('Companion use is turned off for this material.')) ?>
          <?php elseif ($material['visibility'] !== 'published'): ?><?= e(t('The companion will use it only after you publish it.')) ?>
          <?php elseif ($material['release_at'] && $material['release_at'] > \Sibol\Core\Db::now()): ?><?= e(t('The companion will use it from the release date.')) ?>
          <?php elseif ($status === 'ready'): ?><?= e(t('Students\' companion answers can quote this material now.')) ?><?php endif; ?>
        </p>
      </section>

      <?php if ($chunks): ?>
        <section class="section" aria-labelledby="text-title">
          <div class="section__head"><h2 class="section__title" id="text-title"><?= e(t('Text the companion can read')) ?></h2></div>
          <p class="meta"><?= e(t('Check that the text came through correctly. If pages are missing or garbled, upload a cleaner file as a new version.')) ?></p>
          <div class="extract-preview" tabindex="0" aria-label="<?= e(t('Extracted text')) ?>">
            <?php foreach ($chunks as $c): $where = \Sibol\Services\Ai\Retrieval::location($c + ['extension' => $version['extension']]); ?>
              <?php if ($where !== ''): ?><span class="extract-preview__page"><?= e($where) ?></span><?php endif; ?><?= e($c['content']) ?>

            <?php endforeach; ?>
          </div>
          <?php if ($chunkCount > count($chunks)): ?><p class="meta"><?= e(t('Showing the first :n of :total passages.', ['n' => count($chunks), 'total' => $chunkCount])) ?></p><?php endif; ?>
        </section>
      <?php endif; ?>
    <?php endif; ?>

    <section class="section" aria-labelledby="edit-title">
      <div class="section__head"><h2 class="section__title" id="edit-title"><?= e(t('Details')) ?></h2></div>
      <form method="post" action="<?= e(url('/teacher/materials/' . (int) $material['id'])) ?>">
        <?= csrf_field() ?>
        <div class="form-grid">
          <?= field('text', 'title', t('Title'), ['value' => $material['title'], 'required' => true, 'wrap_class' => 'span-2', 'attrs' => ['maxlength' => 200]]) ?>
          <?= field('textarea', 'description', t('Note for students'), ['value' => (string) $material['description'], 'rows' => 3, 'wrap_class' => 'span-2', 'optional_label' => true, 'attrs' => ['maxlength' => 2000]]) ?>
          <?= field('text', 'topic', t('Topic'), ['value' => (string) $material['topic'], 'optional_label' => true, 'attrs' => ['maxlength' => 120]]) ?>
          <?= field('select', 'module_id', t('Module'), ['value' => (string) $material['module_id'], 'options' => ['' => t('No module')] + array_column($modules, 'title', 'id')]) ?>
          <?= field('datetime-local', 'release_at', t('Release date and time'), ['value' => $material['release_at'] ? fmt_date($material['release_at'], 'input') : '', 'optional_label' => true, 'hint' => t('Leave empty to release as soon as it is published.')]) ?>
          <?php if (!$isFile): ?><?= field('url', 'url', t('Web address'), ['value' => (string) $material['url'], 'attrs' => ['maxlength' => 500]]) ?><?php endif; ?>
        </div>
        <?php if ($isFile): ?><?= checkbox('ai_enabled', t('Let the companion use this material'), (bool) $material['ai_enabled']) ?><?php endif; ?>
        <div class="form-actions"><button class="btn btn--primary" type="submit"><?= e(t('Save details')) ?></button></div>
      </form>
    </section>
  </div>

  <aside class="stack">
    <section class="panel" aria-labelledby="vis-title">
      <h2 class="panel__title" id="vis-title"><?= e(t('Visibility')) ?></h2>
      <p><?= badge($vLabel, $vTone) ?></p>
      <?php if ($material['visibility'] === 'published'): ?><p class="meta"><?= e(t('Opened by :n of :m students.', ['n' => $views, 'm' => $students])) ?></p><?php endif; ?>
      <?php if ($material['visibility'] !== 'archived'): ?>
        <form method="post" action="<?= e(url('/teacher/materials/' . (int) $material['id'] . '/visibility')) ?>" data-confirm="<?= e(t('Students and the companion will no longer see it. You can restore it later.')) ?>" data-confirm-title="<?= e(t('Archive this material?')) ?>" data-confirm-action="<?= e(t('Archive')) ?>">
          <?= csrf_field() ?><input type="hidden" name="action" value="archive"><button class="btn btn--danger-quiet btn--sm" type="submit"><?= icon('archive', 'icon--sm') ?><?= e(t('Archive')) ?></button>
        </form>
      <?php endif; ?>
    </section>
    <?= partial('copy_to_sections', $copyPanel) ?>
    <?php if ($isFile): ?>
      <section class="panel" aria-labelledby="ver-title">
        <h2 class="panel__title" id="ver-title"><?= e(t('Versions')) ?></h2>
        <ul class="rows">
          <?php foreach ($versions as $v): ?>
            <li class="row" style="padding:8px 0">
              <div class="row__main">
                <a class="row__title" href="<?= e(url('/materials/' . (int) $material['id'] . '/file', ['version' => (int) $v['id'], 'download' => 1])) ?>"><?= e(t('Version :n', ['n' => (int) $v['version_no']])) ?></a>
                <div class="row__meta"><span><?= e($v['original_filename']) ?></span><span><?= e(Uploads::humanSize((int) $v['size_bytes'])) ?></span><span><?= e(fmt_date($v['created_at'])) ?></span></div>
              </div>
              <?php if ((int) $v['id'] === (int) $material['current_version_id']): ?><span class="row__aside"><?= badge(t('Current'), 'leaf') ?></span><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
        <form method="post" action="<?= e(url('/teacher/materials/' . (int) $material['id'] . '/version')) ?>" enctype="multipart/form-data" style="margin-top: 12px">
          <?= csrf_field() ?>
          <div class="field<?= field_error('file') ? ' field--error' : '' ?>">
            <label class="field__label" for="f-version"><?= e(t('Upload a new version')) ?></label>
            <input class="input" id="f-version" type="file" name="file" accept=".pdf,.docx,.pptx,.txt,.md" required>
            <?php if (field_error('file')): ?><p class="field__error"><?= e(field_error('file')) ?></p><?php endif; ?>
          </div>
          <button class="btn btn--secondary btn--sm" type="submit" data-busy="<?= e(t('Uploading…')) ?>"><?= icon('upload', 'icon--sm') ?><?= e(t('Upload version')) ?></button>
        </form>
      </section>
    <?php endif; ?>
  </aside>
</div>
