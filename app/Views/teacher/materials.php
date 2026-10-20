<?php /** @var list<array> $rows @var list<array> $classes @var int|null $classId @var string $visibility @var string $q @var \Sibol\Core\Paginator $pager @var string $sort */
use Sibol\Core\Uploads;
use Sibol\Services\Ingestion;
use Sibol\Services\Materials;
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Materials')) ?></h1>
    <p class="page-lead"><?= e(t('Notes, files and links for your classes. Only published, released materials reach students and the companion.')) ?></p>
  </div>
  <div class="page-actions"><a class="btn btn--primary" href="<?= e(url('/teacher/materials/new', ['class_id' => $classId])) ?>"><?= icon('upload', 'icon--sm') ?><?= e(t('Add material')) ?></a></div>
</div>

<form class="toolbar" method="get" action="<?= e(url('/teacher/materials')) ?>" role="search">
  <div class="field field--grow"><label class="field__label" for="m-q"><?= e(t('Search')) ?></label><input class="input" id="m-q" type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('Title, topic or description')) ?>"></div>
  <div class="field"><label class="field__label" for="m-class"><?= e(t('Class')) ?></label>
    <select class="input select" id="m-class" name="class_id" data-autosubmit><option value=""><?= e(t('All classes')) ?></option>
      <?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected($c['id'], $classId) ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label class="field__label" for="m-vis"><?= e(t('Visibility')) ?></label>
    <select class="input select" id="m-vis" name="visibility" data-autosubmit>
      <?php foreach (['' => t('All except archived'), 'published' => t('Published'), 'scheduled' => t('Scheduled'), 'draft' => t('Drafts'), 'archived' => t('Archived')] as $v => $l): ?><option value="<?= e($v) ?>"<?= selected($v, $visibility) ?>><?= e($l) ?></option><?php endforeach; ?>
    </select></div>
  <button class="btn btn--secondary" type="submit"><?= e(t('Apply')) ?></button>
</form>

<?php if (!$rows): ?>
  <?= partial('empty', ['title' => $q !== '' || $visibility !== '' ? t('No materials match these filters') : t('No materials yet'), 'body' => t('Upload a PDF, Word, PowerPoint or text file, or add a link. Files are read so the companion can quote them with page references.'), 'action' => [t('Add material'), '/teacher/materials/new']]) ?>
<?php else: ?>
  <div class="table-frame">
    <table class="table table--stack">
      <caption class="sr-only"><?= e(t('Materials')) ?></caption>
      <thead><tr>
        <th scope="col"><a class="sort-link" href="<?= e(url('/teacher/materials', ['q' => $q, 'class_id' => $classId, 'visibility' => $visibility, 'sort' => 'title'])) ?>"><?= e(t('Title')) ?><?= $sort === 'title' ? icon('arrow-down', 'icon--xs') : '' ?></a></th>
        <th scope="col"><?= e(t('Class and module')) ?></th>
        <th scope="col"><?= e(t('Students see it')) ?></th>
        <th scope="col"><?= e(t('Companion')) ?></th>
        <th scope="col"><a class="sort-link" href="<?= e(url('/teacher/materials', ['q' => $q, 'class_id' => $classId, 'visibility' => $visibility])) ?>"><?= e(t('Updated')) ?><?= $sort !== 'title' ? icon('arrow-down', 'icon--xs') : '' ?></a></th>
      </tr></thead>
      <tbody>
        <?php foreach ($rows as $m): [$vLabel, $vTone] = Materials::visibilityLabel($m); [$pLabel, $pTone] = $m['kind'] === 'link' ? [t('Not used for links'), 'neutral'] : Ingestion::statusLabel($m['processing_status']); ?>
          <tr>
            <td class="cell-primary">
              <a class="row__title" href="<?= e(url('/teacher/materials/' . (int) $m['id'])) ?>"><?= icon($m['kind'] === 'link' ? 'link' : 'file-text', 'icon--sm') ?> <?= e($m['title']) ?></a>
              <span class="table-sub"><?= $m['kind'] === 'link' ? e((string) parse_url((string) $m['url'], PHP_URL_HOST)) : e(strtoupper((string) $m['extension']) . ', ' . Uploads::humanSize((int) $m['size_bytes']) . ((int) $m['version_no'] > 1 ? ', ' . t('version :n', ['n' => (int) $m['version_no']]) : '')) ?></span>
            </td>
            <td data-label="<?= e(t('Class and module')) ?>"><?= e($m['class_name']) ?><span class="table-sub"><?= e($m['module_title'] ?? t('No module')) ?></span></td>
            <td data-label="<?= e(t('Students see it')) ?>"><?= badge($vLabel, $vTone) ?></td>
            <td data-label="<?= e(t('Companion')) ?>"><?= (int) $m['ai_enabled'] || $m['kind'] === 'link' ? badge($pLabel, $pTone) : badge(t('Turned off'), 'neutral') ?></td>
            <td data-label="<?= e(t('Updated')) ?>"><?= time_tag($m['updated_at'], 'date', true) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= partial('pagination', ['pager' => $pager, 'path' => '/teacher/materials', 'query' => ['q' => $q, 'class_id' => $classId, 'visibility' => $visibility, 'sort' => $sort]]) ?>
<?php endif; ?>
