<?php
/**
 * A printable copy of an assignment or quiz: blank for learners without a device, or the teacher's copy
 * with the answer key. Print styles drop the app around the sheet.
 * @var array $a @var bool $withKey @var list<array> $questions @var int $leftOut @var array $rubric
 */
$base = '/teacher/assignments/' . (int) $a['id'];
$user = auth_user();
$school = (string) ($user['org_name'] ?? '');
$total = (float) $a['points_possible'];
$typeLabels = ['multiple_choice' => t('Multiple choice'), 'true_false' => t('True or false'), 'short_answer' => t('Short answer'), 'essay' => t('Essay')];
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e($withKey ? t('Answer key') : t('Print a copy')) ?></h1>
    <p class="page-lead"><?= e($a['title']) ?>, <?= e($a['class_name']) ?>.</p>
  </div>
  <div class="page-actions">
    <nav class="segmented" aria-label="<?= e(t('Which copy')) ?>">
      <a href="<?= e(url($base . '/print')) ?>"<?= !$withKey ? ' aria-current="page"' : '' ?>><?= e(t('Blank copy')) ?></a>
      <a href="<?= e(url($base . '/print', ['copy' => 'key'])) ?>"<?= $withKey ? ' aria-current="page"' : '' ?>><?= e(t('With answer key')) ?></a>
    </nav>
    <button type="button" class="btn btn--primary" data-print><?= icon('printer', 'icon--sm') ?><?= e(t('Print')) ?></button>
  </div>
</div>

<div class="print-notes">
  <?php if ($withKey): ?>
    <div class="callout callout--amber"><?= icon('lock') ?><p><?= e(t('This copy shows the correct answers. Keep it away from students; only teachers of this class can open it.')) ?></p></div>
  <?php elseif ($leftOut > 0): ?>
    <div class="callout callout--amber"><?= icon('square-pen') ?><p><?= e(tp(':count AI-drafted question is not approved yet, so it is left out of this copy.', ':count AI-drafted questions are not approved yet, so they are left out of this copy.', $leftOut)) ?></p></div>
  <?php endif; ?>
</div>

<article class="print-sheet" aria-label="<?= e($withKey ? t('Answer key') : t('Blank copy')) ?>">
  <header class="print-sheet__head">
    <p class="print-sheet__school"><?= e($school) ?> · <?= e($a['class_name']) ?></p>
    <h2 class="print-sheet__title"><?= e($a['title']) ?></h2>
    <p class="print-sheet__meta"><?= e(work_kind((string) $a['kind'])) ?><?= $total > 0 ? ' · ' . e(t(':n points', ['n' => fmt_number($total, 2)])) : '' ?><?= $a['due_at'] ? ' · ' . e(fmt_date($a['due_at'], 'date_long')) : '' ?></p>
    <?php if ($withKey): ?>
      <p class="print-sheet__key"><?= icon('key-round', 'icon--sm') ?> <?= e(t('Answer key: teacher\'s copy')) ?></p>
    <?php else: ?>
      <div class="print-sheet__blanks">
        <span><?= e(t('Name')) ?> <span class="print-line print-line--long"></span></span>
        <span><?= e(t('Date')) ?> <span class="print-line"></span></span>
        <span><?= e(t('Score')) ?> <span class="print-line print-line--short"></span> / <?= e(fmt_number($total, 2)) ?></span>
      </div>
    <?php endif; ?>
  </header>

  <?php if (trim((string) $a['instructions']) !== ''): ?>
    <section class="print-sheet__section prose"><?= markdown($a['instructions']) ?></section>
  <?php endif; ?>

  <?php if ($questions): ?>
    <ol class="print-questions">
      <?php foreach ($questions as $q):
          $options = is_array($q['options']) ? $q['options'] : [];
          $key = $withKey && is_array($q['answer_key'] ?? null) ? $q['answer_key'] : [];
      ?>
        <li class="print-q">
          <div class="print-q__head">
            <div class="print-q__prompt"><?= markdown($q['prompt']) ?></div>
            <span class="print-q__points"><?= e(tp(':count pt', ':count pts', (int) round((float) $q['points']))) ?></span>
          </div>
          <?php if ($withKey): ?><p class="print-q__type"><?= e($typeLabels[$q['qtype']] ?? '') ?><?= (int) $q['ai_generated'] === 1 && (int) $q['reviewed'] !== 1 ? ' · ' . e(t('AI draft, not approved')) : '' ?></p><?php endif; ?>
          <?php if ($q['qtype'] === 'multiple_choice'): ?>
            <ul class="print-choices">
              <?php foreach ($options as $o): $correct = $withKey && ($key['correct'] ?? null) === $o['key']; ?>
                <li class="print-choice<?= $correct ? ' is-correct' : '' ?>"><span class="print-bubble" aria-hidden="true"></span><strong><?= e($o['key']) ?>.</strong> <?= e($o['text']) ?><?php if ($correct): ?> <?= icon('check', 'icon--sm', t('Correct answer')) ?><?php endif; ?></li>
              <?php endforeach; ?>
            </ul>
          <?php elseif ($q['qtype'] === 'true_false'): ?>
            <ul class="print-choices print-choices--inline">
              <?php foreach (['true' => t('True'), 'false' => t('False')] as $value => $label): $correct = $withKey && array_key_exists('correct', $key) && ($key['correct'] ? 'true' : 'false') === $value; ?>
                <li class="print-choice<?= $correct ? ' is-correct' : '' ?>"><span class="print-bubble" aria-hidden="true"></span><?= e($label) ?><?php if ($correct): ?> <?= icon('check', 'icon--sm', t('Correct answer')) ?><?php endif; ?></li>
              <?php endforeach; ?>
            </ul>
          <?php elseif ($q['qtype'] === 'short_answer'): ?>
            <?php if ($withKey): ?>
              <p class="print-answer"><strong><?= e(t('Accepted answers:')) ?></strong> <?= e(implode(' / ', (array) ($key['accepted'] ?? []))) ?></p>
            <?php else: ?>
              <span class="print-line print-line--answer"></span>
            <?php endif; ?>
          <?php else: ?>
            <?php if ($withKey): ?>
              <p class="print-answer"><?= e(t('Marked by the teacher.')) ?><?= $rubric ? ' ' . e(t('Use the rubric below.')) : '' ?></p>
            <?php else: ?>
              <?php for ($i = 0; $i < 6; $i++): ?><span class="print-line print-line--answer"></span><?php endfor; ?>
            <?php endif; ?>
          <?php endif; ?>
          <?php if ($withKey && !empty($q['explanation'])): ?><div class="print-q__explain"><?= markdown($q['explanation']) ?></div><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ol>
  <?php endif; ?>

  <?php if ($rubric): ?>
    <section class="print-sheet__section">
      <h3 class="print-sheet__subhead"><?= e(t('How this is graded')) ?></h3>
      <table class="print-rubric">
        <thead><tr><th scope="col"><?= e(t('Criterion')) ?></th><th scope="col"><?= e(t('What it looks like')) ?></th><th scope="col" class="num"><?= e(t('Points')) ?></th></tr></thead>
        <tbody>
          <?php foreach ($rubric as $c): ?>
            <tr><td><strong><?= e($c['title']) ?></strong></td><td><?= e($c['description'] ?? '') ?></td><td class="num"><?= e(fmt_number($c['points'], 2)) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  <?php endif; ?>

  <?php if (!$questions && !$rubric && trim((string) $a['instructions']) === ''): ?>
    <p class="meta"><?= e(t('Nothing to print yet: add instructions, questions or a rubric.')) ?></p>
  <?php elseif (!$questions && !$withKey): ?>
    <section class="print-sheet__section">
      <h3 class="print-sheet__subhead"><?= e(t('Your work')) ?></h3>
      <?php for ($i = 0; $i < 10; $i++): ?><span class="print-line print-line--answer"></span><?php endfor; ?>
    </section>
  <?php endif; ?>
</article>
