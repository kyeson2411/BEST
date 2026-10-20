<?php /** @var list<array> $rows @var string $key @var array $summary @var string $filename */
$create = (int) ($summary['create'] ?? 0);
$enrol = (int) ($summary['enrol'] ?? 0);
$skip = (int) ($summary['skip'] ?? 0);
$errors = (int) ($summary['error'] ?? 0);
$labels = [
    'create' => [t('Will be invited'), 'leaf'],
    'enrol' => [t('Will be added to class'), 'leaf'],
    'skip' => [t('No change'), 'neutral'],
    'error' => [t('Needs fixing'), 'coral'],
];
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Check the import')) ?></h1>
    <p class="page-lead"><?= e(t('From :file. Nothing has been saved yet.', ['file' => $filename])) ?></p>
  </div>
</div>

<dl class="stat-line" style="margin-bottom: 24px">
  <div class="stat"><dt class="stat__label"><?= e(t('New accounts')) ?></dt><dd style="margin:0"><span class="stat__value"><?= $create ?></span></dd></div>
  <div class="stat"><dt class="stat__label"><?= e(t('Class enrolments only')) ?></dt><dd style="margin:0"><span class="stat__value"><?= $enrol ?></span></dd></div>
  <div class="stat"><dt class="stat__label"><?= e(t('Unchanged')) ?></dt><dd style="margin:0"><span class="stat__value"><?= $skip ?></span></dd></div>
  <div class="stat"><dt class="stat__label"><?= e(t('Rows with problems')) ?></dt><dd style="margin:0"><span class="stat__value"><?= $errors ?></span></dd></div>
</dl>

<?php if ($errors): ?>
  <div class="callout callout--coral" style="margin-bottom: 20px"><?= icon('triangle-alert') ?><p><?= e(tp(':count row has problems and will be skipped. Fix it in your spreadsheet and import it again later, or continue without it.', ':count rows have problems and will be skipped. Fix them in your spreadsheet and import them again later, or continue without them.', $errors)) ?></p></div>
<?php endif; ?>

<div class="table-frame">
  <table class="table table--stack">
    <caption class="sr-only"><?= e(t('Rows in the file')) ?></caption>
    <thead><tr>
      <th scope="col" class="num"><?= e(t('Line')) ?></th>
      <th scope="col"><?= e(t('Name')) ?></th>
      <th scope="col"><?= e(t('Role')) ?></th>
      <th scope="col"><?= e(t('Class')) ?></th>
      <th scope="col"><?= e(t('Result')) ?></th>
    </tr></thead>
    <tbody>
      <?php foreach ($rows as $r): [$label, $tone] = $labels[$r['action']]; ?>
        <tr>
          <td class="num" data-label="<?= e(t('Line')) ?>"><?= (int) $r['line'] ?></td>
          <td class="cell-primary"><span class="row__title"><?= e(trim($r['given_name'] . ' ' . $r['family_name']) ?: '—') ?></span><span class="table-sub"><?= e(implode(' · ', array_filter([$r['username'] ?? '', $r['email']])) ?: ($r['action'] === 'create' ? t('Username made from the name') : '—')) ?></span></td>
          <td data-label="<?= e(t('Role')) ?>"><?= e(in_array($r['role'], ['student', 'teacher', 'admin'], true) ? role_label($r['role']) : $r['role']) ?><?php if ($r['grade_level'] !== ''): ?><span class="table-sub"><?= e($r['grade_level']) ?></span><?php endif; ?></td>
          <td data-label="<?= e(t('Class')) ?>"><?= e($r['class_name'] ?? ($r['class_input'] ?: '—')) ?></td>
          <td data-label="<?= e(t('Result')) ?>"><?= badge($label, $tone) ?>
            <?php foreach (array_merge($r['problems'], $r['notes']) as $msg): ?><span class="table-sub"><?= e($msg) ?></span><?php endforeach; ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="form-actions" style="margin-top: 24px">
  <?php if ($create + $enrol > 0): ?>
    <form method="post" action="<?= e(url('/admin/users/import/confirm')) ?>" data-confirm="<?= e(t(':n new accounts will be created with one-time sign-in codes, and :m class enrolments added.', ['n' => $create, 'm' => $enrol + count(array_filter($rows, static fn ($r) => $r['action'] === 'create' && $r['class_id']))])) ?>" data-confirm-title="<?= e(t('Import now?')) ?>" data-confirm-action="<?= e(t('Import')) ?>">
      <?= csrf_field() ?><input type="hidden" name="key" value="<?= e($key) ?>">
      <button class="btn btn--primary" type="submit" data-busy="<?= e(t('Importing…')) ?>"><?= icon('user-plus', 'icon--sm') ?><?= e(tp('Import :count person', 'Import :count people', $create + $enrol)) ?></button>
    </form>
  <?php else: ?>
    <p class="meta"><?= e(t('There is nothing to import from this file.')) ?></p>
  <?php endif; ?>
  <a class="btn btn--quiet" href="<?= e(url('/admin/users/import')) ?>"><?= e(t('Upload a different file')) ?></a>
</div>
