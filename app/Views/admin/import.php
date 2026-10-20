<?php /** @var list<string> $classes @var list<string> $grades */ ?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Import people from a spreadsheet')) ?></h1>
    <p class="page-lead"><?= e(t('Upload a CSV file. You will see every row and any problems before anyone is added.')) ?></p>
  </div>
</div>
<div class="layout-split">
  <section class="section" aria-labelledby="up-title">
    <h2 class="section__title" id="up-title"><?= e(t('Upload')) ?></h2>
    <form method="post" action="<?= e(url('/admin/users/import')) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="field<?= field_error('csv') ? ' field--error' : '' ?>">
        <label class="field__label" for="f-csv"><?= e(t('CSV file')) ?></label>
        <p class="field__hint" id="f-csv-hint"><?= e(t('Up to 1,000 rows and 2 MB. Save from Excel or Google Sheets as “CSV UTF-8”.')) ?></p>
        <input class="input" id="f-csv" type="file" name="csv" accept=".csv,text/csv" required aria-describedby="f-csv-hint<?= field_error('csv') ? ' f-csv-error' : '' ?>">
        <?php if (field_error('csv')): ?><p class="field__error" id="f-csv-error"><?= icon('circle-alert', 'icon--xs') ?><?= e(field_error('csv')) ?></p><?php endif; ?>
      </div>
      <div class="form-actions">
        <button class="btn btn--primary" type="submit" data-busy="<?= e(t('Checking…')) ?>"><?= icon('upload', 'icon--sm') ?><?= e(t('Check file')) ?></button>
        <a class="btn btn--secondary" href="<?= e(url('/admin/users/import/template')) ?>"><?= icon('download', 'icon--sm') ?><?= e(t('Download template')) ?></a>
      </div>
    </form>
  </section>
  <aside class="panel panel--sunken" aria-labelledby="cols-title">
    <h2 class="panel__title" id="cols-title"><?= e(t('Columns')) ?></h2>
    <dl class="dl">
      <dt>given_name</dt><dd><?= e(t('Required.')) ?></dd>
      <dt>family_name</dt><dd><?= e(t('Required.')) ?></dd>
      <dt>email</dt><dd><?= e(t('Required and unique. Used to sign in.')) ?></dd>
      <dt>role</dt><dd><?= e(t('student, teacher or admin. Blank means student.')) ?></dd>
      <dt>grade_level</dt><dd><?= e(t('Optional, students only. Your list: :grades.', ['grades' => implode(', ', $grades) ?: t('none set')])) ?></dd>
      <dt>class</dt><dd><?= e(t('Optional. The exact name of an active class, to enrol the person.')) ?></dd>
    </dl>
    <?php if ($classes): ?>
      <details style="margin-top: 12px"><summary class="meta"><?= e(tp('Show :count active class name', 'Show :count active class names', count($classes))) ?></summary>
        <ul style="margin: 8px 0 0; padding-left: 1.2em"><?php foreach ($classes as $c): ?><li><?= e($c) ?></li><?php endforeach; ?></ul></details>
    <?php endif; ?>
    <p class="meta" style="margin-top: 12px"><?= e(t('Use real school records only with your school\'s permission. Imported people receive invitation links you share yourself.')) ?></p>
  </aside>
</div>
