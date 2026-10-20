<?php
/**
 * Progress reports for one grading period, one sheet per student, to print or save as a PDF. They show released
 * results only and say plainly that they are not the school's official report card (SF9).
 * @var string $title @var string $lead @var array $built from ProgressReports::build
 * @var list<array{student:array,section:?string,adviser:?string}> $students @var string $base page address for the period links
 */
$user = auth_user();
$school = (string) ($user['org_name'] ?? '');
$logo = !empty($user['org_logo_path']) ? url('/school-logo') : null;
$period = $built['period'];
$year = $built['year'];
$transmuted = $built['transmuted'];
$range = static fn (array $p): string => fmt_date($p['starts_on'] . ' 12:00:00', 'date_short') . ' – ' . fmt_date($p['ends_on'] . ' 12:00:00', 'date_short');
$when = $period ? $period['name'] . ' (' . $range($period) . ')' : ($year ? t(':year, so far', ['year' => $year['name']]) : t('This school year so far'));
$gradeText = static function (?array $g) use ($transmuted): string {
    if (!$g || $g['grade'] === null) {
        return '—';
    }
    return $transmuted ? fmt_number($g['grade'], 0) : fmt_number($g['grade'], 1) . '%';
};
$gradeLabel = $period ? t(':period grade', ['period' => $period['name']]) : t('Grade so far');
$printed = fmt_date(gmdate('Y-m-d H:i:s'), 'date_long');
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e($title) ?></h1>
    <p class="page-lead"><?= e($lead) ?></p>
  </div>
  <div class="page-actions">
    <button type="button" class="btn btn--primary" data-print><?= icon('printer', 'icon--sm') ?><?= e(t('Print')) ?></button>
  </div>
</div>

<?php if ($built['periods']): ?>
  <div class="print-notes">
    <nav class="tabs" aria-label="<?= e(t('Grading periods')) ?>">
      <?php foreach ($built['periods'] as $p): ?>
        <a class="tab" href="<?= e(url($base, ['period' => (int) $p['id']])) ?>"<?= $period && (int) $period['id'] === (int) $p['id'] ? ' aria-current="page"' : '' ?>><?= e($p['name']) ?></a>
      <?php endforeach; ?>
    </nav>
  </div>
<?php endif; ?>

<?php if (!$students): ?>
  <?= partial('empty', ['title' => t('No students yet'), 'body' => t('Students appear here when they are added to this section\'s classes.')]) ?>
<?php endif; ?>

<?php foreach ($students as $entry): $s = $entry['student']; $report = $built['reports'][(int) $s['id']] ?? ['rows' => [], 'comments' => []]; ?>
  <article class="print-sheet report" aria-label="<?= e(t('Progress report for :name', ['name' => user_name($s)])) ?>">
    <header class="print-sheet__head">
      <p class="report__school"><?= partial('seal', ['size' => 40, 'logo' => $logo]) ?><span><?= e($school) ?></span></p>
      <h2 class="print-sheet__title"><?= e(t('Progress report')) ?></h2>
      <p class="print-sheet__meta"><?= e($when) ?><?= $period && $year ? ', ' . e($year['name']) : '' ?></p>
      <dl class="report__who">
        <div><dt><?= e(t('Student')) ?></dt><dd><?= e(user_name($s)) ?></dd></div>
        <?php if ($entry['section']): ?><div><dt><?= e(t('Section')) ?></dt><dd><?= e($entry['section']) ?></dd></div><?php endif; ?>
        <?php if ($entry['adviser']): ?><div><dt><?= e(t('Adviser')) ?></dt><dd><?= e($entry['adviser']) ?></dd></div><?php endif; ?>
      </dl>
    </header>
    <p class="report__notice"><?= icon('info', 'icon--sm') ?><span><?= e(t('Not an official report card (SF9). It lists only the results teachers have released in Sibol up to :date; the school\'s report card is the official record.', ['date' => $printed])) ?></span></p>

    <?php if (!$report['rows']): ?>
      <p class="meta"><?= e(t('No classes to report on in this school year.')) ?></p>
    <?php else: ?>
      <div class="table-frame">
        <table class="table table--compact report__table">
          <caption class="sr-only"><?= e(t('Grades for :name, :period', ['name' => user_name($s), 'period' => $when])) ?></caption>
          <thead>
            <tr>
              <th scope="col"><?= e(t('Subject')) ?></th>
              <th scope="col"><?= e(t('Teacher')) ?></th>
              <th scope="col" class="num"><?= e($gradeLabel) ?></th>
              <th scope="col" class="num"><?= e(t('Missing')) ?></th>
              <th scope="col" class="num"><?= e(t('Late')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($report['rows'] as $row): $g = $row['grade']; $has = $g && $g['grade'] !== null; ?>
              <tr>
                <th scope="row"><?= e($row['class']['name']) ?></th>
                <td data-label="<?= e(t('Teacher')) ?>"><?= e((string) ($row['class']['teachers'] ?? '') ?: '—') ?></td>
                <td class="num nowrap" data-label="<?= e($gradeLabel) ?>"><span><strong><?= e($gradeText($g)) ?></strong><?php if ($has && $g['descriptor']): ?><span class="table-sub"><?= e($g['descriptor']) ?></span><?php elseif (!$has): ?><span class="table-sub"><?= e(t('Nothing released')) ?></span><?php endif; ?></span></td>
                <td class="num" data-label="<?= e(t('Missing')) ?>"><?= (int) $row['missing'] ?></td>
                <td class="num" data-label="<?= e(t('Late')) ?>"><?= (int) $row['late'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="meta report__key"><?= e(t('Grades use only results teachers have released.')) ?>
        <?= $transmuted ? e(t('Each grading period grade is transmuted with the school\'s table.')) : '' ?>
        <?= e(t('Missing counts work past its due date with nothing handed in, including work done in class while absent; late counts work handed in after its due date.')) ?></p>
    <?php endif; ?>

    <section class="print-sheet__section">
      <h3 class="print-sheet__subhead"><?= e(t('Comments from teachers')) ?></h3>
      <?php if (!$report['comments']): ?>
        <p class="meta"><?= e($period ? t('No comments released for this grading period.') : t('No comments released yet.')) ?></p>
      <?php else: ?>
        <ul class="report__comments">
          <?php foreach ($report['comments'] as $c): ?>
            <li>
              <p class="report__comment-head"><strong><?= e($c['class_name']) ?></strong>, <?= e($c['title']) ?> <span class="meta"><?= e(fmt_date($c['released_at'], 'date_short')) ?></span></p>
              <div class="prose"><?= markdown($c['feedback']) ?></div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
    <p class="meta report__printed"><?= e(t('Printed from Sibol on :date.', ['date' => $printed])) ?></p>
  </article>
<?php endforeach; ?>
