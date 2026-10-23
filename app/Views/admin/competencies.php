<?php
/**
 * @var list<array> $rows @var int $total @var int $shown @var int $all @var list<array> $subjects @var list<string> $grades
 * @var array{subject:?int,grade:string,q:string} $filters
 */
use Sibol\Core\View;
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Subjects and terms')) ?></h1>
    <p class="page-lead"><?= e(t('Your school\'s list of learning competencies, for example the MATATAG curriculum\'s, with their codes. Teachers tag questions and lessons with them, and class insights report by competency.')) ?></p>
  </div>
</div>
<?= View::file('admin/_structure_tabs', ['active' => 'competencies']) ?>

<div class="layout-split">
  <section class="section" aria-labelledby="comp-list-title">
    <div class="section__head"><h2 class="section__title" id="comp-list-title"><?= e(t('Learning competencies')) ?> <span class="count"><?= (int) $all ?></span></h2></div>
    <?php if ($all === 0): ?>
      <?= partial('empty', ['compact' => true, 'title' => t('No learning competencies yet'), 'body' => t('Import your school\'s list from a spreadsheet. B.E.S.T. Learning and Interactive Study System does not come with codes of its own.')]) ?>
    <?php else: ?>
      <form class="toolbar" method="get" action="<?= e(url('/admin/competencies')) ?>" role="search">
        <div class="field"><label class="field__label" for="cf-subject"><?= e(t('Subject')) ?></label>
          <select class="input select" id="cf-subject" name="subject" data-autosubmit><option value=""><?= e(t('All subjects')) ?></option>
            <?php foreach ($subjects as $s): ?><option value="<?= (int) $s['id'] ?>"<?= selected((string) $s['id'], (string) ($filters['subject'] ?? '')) ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label class="field__label" for="cf-grade"><?= e(t('Grade level')) ?></label>
          <select class="input select" id="cf-grade" name="grade" data-autosubmit><option value=""><?= e(t('All grade levels')) ?></option>
            <?php foreach ($grades as $g): ?><option value="<?= e((string) $g) ?>"<?= selected((string) $g, $filters['grade']) ?>><?= e((string) $g) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label class="field__label" for="cf-q"><?= e(t('Code or words')) ?></label><input class="input" id="cf-q" type="search" name="q" value="<?= e($filters['q']) ?>" maxlength="100"></div>
        <button class="btn btn--secondary" type="submit"><?= e(t('Apply')) ?></button>
      </form>
      <?php if (!$rows): ?>
        <p class="meta"><?= e(t('No competencies match these filters.')) ?></p>
      <?php else: ?>
        <div class="table-frame">
          <table class="table table--stack">
            <caption class="sr-only"><?= e(t('Learning competencies')) ?></caption>
            <thead><tr>
              <th scope="col"><?= e(t('Code')) ?></th>
              <th scope="col"><?= e(t('Competency')) ?></th>
              <th scope="col"><?= e(t('Used on')) ?></th>
              <th scope="col"><span class="sr-only"><?= e(t('Actions')) ?></span></th>
            </tr></thead>
            <tbody>
              <?php foreach ($rows as $c): ?>
                <tr<?= $c['archived_at'] ? ' class="row--muted"' : '' ?>>
                  <td class="cell-primary" style="white-space:nowrap"><span class="code-text"><?= e($c['code']) ?></span><?php if ((int) $c['is_sample']): ?> <?= badge(t('Sample'), 'neutral') ?><?php endif; ?></td>
                  <td data-label="<?= e(t('Competency')) ?>"><?= e($c['description']) ?>
                    <span class="table-sub"><?= e(implode(' · ', array_filter([
                        (string) ($c['subject_name'] ?? ''),
                        (string) ($c['grade_level'] ?? ''),
                        $c['quarter'] ? t('Quarter :n', ['n' => (int) $c['quarter']]) : '',
                        $c['archived_at'] ? t('Archived') : '',
                    ]))) ?></span></td>
                  <td data-label="<?= e(t('Used on')) ?>"><?= e(tp(':count question', ':count questions', (int) $c['questions'])) ?><span class="table-sub"><?= e(tp(':count lesson', ':count lessons', (int) $c['lessons'])) ?></span></td>
                  <td>
                    <form method="post" action="<?= e(url('/admin/competencies/' . (int) $c['id'] . '/archive')) ?>">
                      <?= csrf_field() ?>
                      <button class="btn btn--quiet btn--sm" type="submit" aria-label="<?= e(($c['archived_at'] ? t('Restore') : t('Archive')) . ': ' . $c['code']) ?>"><?= e($c['archived_at'] ? t('Restore') : t('Archive')) ?></button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if ($total > $shown): ?><p class="meta"><?= e(t('Showing :shown of :total. Narrow the list with the filters.', ['shown' => $shown, 'total' => $total])) ?></p><?php endif; ?>
      <?php endif; ?>
    <?php endif; ?>
  </section>

  <aside class="stack">
    <section class="panel" aria-labelledby="comp-import-title">
      <h2 class="panel__title" id="comp-import-title"><?= e(t('Import from a spreadsheet')) ?></h2>
      <p class="meta"><?= e(t('Save your list as CSV with the columns code and description, and if you like subject, grade_level and quarter (1 to 4). Copy the codes exactly as your curriculum guide writes them.')) ?></p>
      <form method="post" action="<?= e(url('/admin/competencies/import')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="field<?= field_error('csv') ? ' field--error' : '' ?>">
          <label class="field__label" for="f-csv"><?= e(t('CSV file')) ?></label>
          <p class="field__hint" id="f-csv-hint"><?= e(t('Up to 3,000 rows and 2 MB. Save from Excel or Google Sheets as “CSV UTF-8”.')) ?></p>
          <input class="input" id="f-csv" type="file" name="csv" accept=".csv,text/csv" required aria-describedby="f-csv-hint<?= field_error('csv') ? ' f-csv-error' : '' ?>">
          <?php if (field_error('csv')): ?><p class="field__error" id="f-csv-error"><?= icon('circle-alert', 'icon--xs') ?><?= e(field_error('csv')) ?></p><?php endif; ?>
        </div>
        <button class="btn btn--primary" type="submit" data-busy="<?= e(t('Checking…')) ?>"><?= icon('upload', 'icon--sm') ?><?= e(t('Check file')) ?></button>
      </form>
      <p class="meta" style="margin-top: 12px"><a href="<?= e(url('/admin/competencies/template')) ?>"><?= icon('download', 'icon--sm') ?> <?= e(t('Download the column headings')) ?></a></p>
      <p class="meta"><?= e(t('You see every row before anything is saved. Codes already here are updated; codes missing from the file stay. Subjects are matched by name or code.')) ?></p>
    </section>
  </aside>
</div>
