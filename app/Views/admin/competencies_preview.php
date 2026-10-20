<?php /** @var list<array> $rows @var array{new:int,changed:int,same:int,problem:int} $counts @var string $key @var string $filename */
$labels = [
    'new' => [t('Will be added'), 'leaf'],
    'changed' => [t('Will be updated'), 'leaf'],
    'same' => [t('No change'), 'neutral'],
    'problem' => [t('Needs fixing'), 'coral'],
];
$importing = $counts['new'] + $counts['changed'];
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Check the list')) ?></h1>
    <p class="page-lead"><?= e(t('From :file. Nothing has been saved yet.', ['file' => $filename])) ?></p>
  </div>
</div>

<dl class="stat-line" style="margin-bottom: 24px">
  <div class="stat"><dt class="stat__label"><?= e(t('New')) ?></dt><dd style="margin:0"><span class="stat__value"><?= (int) $counts['new'] ?></span></dd></div>
  <div class="stat"><dt class="stat__label"><?= e(t('Changed')) ?></dt><dd style="margin:0"><span class="stat__value"><?= (int) $counts['changed'] ?></span></dd></div>
  <div class="stat"><dt class="stat__label"><?= e(t('Unchanged')) ?></dt><dd style="margin:0"><span class="stat__value"><?= (int) $counts['same'] ?></span></dd></div>
  <div class="stat"><dt class="stat__label"><?= e(t('Rows with problems')) ?></dt><dd style="margin:0"><span class="stat__value"><?= (int) $counts['problem'] ?></span></dd></div>
</dl>

<?php if ($counts['problem']): ?>
  <div class="callout callout--coral" style="margin-bottom: 20px"><?= icon('triangle-alert') ?><p><?= e(tp(':count row has problems and will be skipped. Fix it in your spreadsheet and import it again later, or continue without it.', ':count rows have problems and will be skipped. Fix them in your spreadsheet and import them again later, or continue without them.', (int) $counts['problem'])) ?></p></div>
<?php endif; ?>

<div class="table-frame">
  <table class="table table--stack">
    <caption class="sr-only"><?= e(t('Rows in the file')) ?></caption>
    <thead><tr>
      <th scope="col" class="num"><?= e(t('Line')) ?></th>
      <th scope="col"><?= e(t('Code')) ?></th>
      <th scope="col"><?= e(t('Competency')) ?></th>
      <th scope="col"><?= e(t('Result')) ?></th>
    </tr></thead>
    <tbody>
      <?php foreach ($rows as $r): [$label, $tone] = $labels[$r['status']]; ?>
        <tr>
          <td class="num" data-label="<?= e(t('Line')) ?>"><?= (int) $r['line'] ?></td>
          <td class="cell-primary code-text"><?= e($r['code'] !== '' ? $r['code'] : '—') ?></td>
          <td data-label="<?= e(t('Competency')) ?>"><?= e($r['description'] !== '' ? str_limit($r['description'], 200) : '—') ?>
            <span class="table-sub"><?= e(implode(' · ', array_filter([$r['subject'], $r['grade_level'], $r['quarter'] !== '' && ctype_digit($r['quarter']) ? t('Quarter :n', ['n' => (int) $r['quarter']]) : '']))) ?></span></td>
          <td data-label="<?= e(t('Result')) ?>"><?= badge($label, $tone) ?>
            <?php foreach ($r['problems'] as $msg): ?><span class="table-sub"><?= e($msg) ?></span><?php endforeach; ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="form-actions" style="margin-top: 24px">
  <?php if ($importing > 0): ?>
    <form method="post" action="<?= e(url('/admin/competencies/import/confirm')) ?>">
      <?= csrf_field() ?><input type="hidden" name="key" value="<?= e($key) ?>">
      <button class="btn btn--primary" type="submit" data-busy="<?= e(t('Importing…')) ?>"><?= icon('upload', 'icon--sm') ?><?= e(tp('Import :count competency', 'Import :count competencies', $importing)) ?></button>
    </form>
  <?php else: ?>
    <p class="meta"><?= e(t('There is nothing to import from this file.')) ?></p>
  <?php endif; ?>
  <a class="btn btn--quiet" href="<?= e(url('/admin/competencies')) ?>"><?= e(t('Upload a different file')) ?></a>
</div>
