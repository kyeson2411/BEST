<?php
/**
 * A lesson in the DepEd Daily Lesson Log layout, to print or save as a PDF. Filled from the lesson's current draft;
 * rows Sibol has nothing for stay blank to write in by hand. The log's labels follow the lesson's language.
 * @var array $lesson @var array $content @var array $dll from LessonPlans::dll
 * @var array{school:string,grade:string,teacher:string,area:string,quarter:string} $info
 * @var list<array> $competencies the lesson's learning competencies, listed with their codes under I.C
 */
$cites = [];
foreach ((array) ($content['sources'] ?? []) as $s) {
    $cites[(int) $s['n']] = 'dll-source';
}
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Daily Lesson Log')) ?></h1>
    <p class="page-lead"><?= e($lesson['title']) ?>. <?= e(t('The rows of the Daily Lesson Log in DepEd Order No. 42, s. 2016, filled from this lesson\'s current draft. Rows B.E.S.T. Learning and Interactive Study System has nothing for are left blank to write in.')) ?></p>
  </div>
  <div class="page-actions">
    <a class="btn btn--secondary" href="<?= e(url('/teacher/lessons/' . (int) $lesson['id'])) ?>"><?= e(t('Back to the lesson')) ?></a>
    <button type="button" class="btn btn--primary" data-print><?= icon('printer', 'icon--sm') ?><?= e(t('Print')) ?></button>
  </div>
</div>

<div class="print-notes">
  <div class="callout callout--amber" style="margin-bottom: 20px"><?= icon('triangle-alert') ?><p><?= e(t('Check this layout against your school\'s own DLL template before you hand it in. B.E.S.T. Learning and Interactive Study System has not been given that file yet, so the columns for each day of the week are not here: this is one lesson.')) ?></p></div>
</div>

<article class="print-sheet dll" aria-labelledby="dll-title">
  <header>
    <h2 class="print-sheet__title" id="dll-title"><?= e($dll['title']) ?></h2>
    <p class="print-sheet__meta"><?= e(t('Prepared in B.E.S.T. Learning and Interactive Study System from the lesson “:title”.', ['title' => $lesson['title']])) ?></p>
    <table class="dll__head">
      <tr>
        <th scope="row"><?= e($dll['header']['school']) ?></th><td><?= e($info['school']) ?></td>
        <th scope="row"><?= e($dll['header']['grade']) ?></th><td><?= e($info['grade']) ?></td>
      </tr>
      <tr>
        <th scope="row"><?= e($dll['header']['teacher']) ?></th><td><?= e($info['teacher']) ?></td>
        <th scope="row"><?= e($dll['header']['area']) ?></th><td><?= e($info['area']) ?></td>
      </tr>
      <tr>
        <th scope="row"><?= e($dll['header']['dates']) ?></th><td></td>
        <th scope="row"><?= e($dll['header']['quarter']) ?></th><td><?= e($info['quarter']) ?></td>
      </tr>
    </table>
  </header>

  <table class="dll__table">
    <caption class="sr-only"><?= e(t('Daily Lesson Log for :title', ['title' => $lesson['title']])) ?></caption>
    <?php foreach ($dll['parts'] as $part): ?>
      <tbody>
        <tr class="dll__part"><th colspan="2" scope="colgroup"><?= e($part['heading']) ?></th></tr>
        <?php foreach ($part['rows'] as $row): ?>
          <tr>
            <?php if ($row['label'] !== ''): ?><th scope="row"><?= e($row['label']) ?></th><?php endif; ?>
            <td<?= $row['label'] === '' ? ' colspan="2"' : '' ?>>
              <?php if ($row['key'] === 'sources' && !empty($content['sources'])): ?>
                <ol class="dll__sources">
                  <?php foreach ($content['sources'] as $s): ?>
                    <li id="dll-source-<?= (int) $s['n'] ?>"><?= e((string) $s['title']) ?><?php if (!empty($s['where'])): ?>, <?= e((string) $s['where']) ?><?php endif; ?></li>
                  <?php endforeach; ?>
                </ol>
              <?php elseif ($row['key'] === 'objectives' && $competencies): ?>
                <ul class="dll__codes">
                  <?php foreach ($competencies as $c): ?><li><strong class="code-text"><?= e($c['code']) ?></strong> <?= e($c['description']) ?></li><?php endforeach; ?>
                </ul>
                <?php if ($row['text'] !== ''): ?><div class="prose"><?= markdown($row['text'], ['citations' => $cites, 'heading_base' => 4]) ?></div><?php endif; ?>
              <?php elseif ($row['text'] !== ''): ?>
                <div class="prose"><?= markdown($row['text'], ['citations' => $cites, 'heading_base' => 4]) ?></div>
              <?php else: ?>
                <span class="dll__blank"></span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    <?php endforeach; ?>
  </table>
</article>
