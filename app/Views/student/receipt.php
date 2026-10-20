<?php /** @var array $assignment @var array $submission @var list<array> $files @var int $answered @var int $questionCount */
use Sibol\Core\Uploads;
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Submission receipt')) ?></h1>
    <p class="page-lead"><?= e(t('Keep this code in case you need to show that your work was handed in.')) ?></p>
  </div>
</div>

<section class="receipt" aria-labelledby="receipt-code">
  <p class="meta" style="margin:0"><?= e(t('Receipt code')) ?></p>
  <p class="receipt__code" id="receipt-code"><?= e($submission['receipt_code']) ?></p>
  <dl class="dl" style="margin-top: 16px">
    <dt><?= e(t('Work')) ?></dt><dd><a href="<?= e(url('/student/assignments/' . (int) $assignment['id'])) ?>"><?= e($assignment['title']) ?></a></dd>
    <dt><?= e(t('Class')) ?></dt><dd><?= e($assignment['class_name']) ?></dd>
    <dt><?= e(t('Submitted')) ?></dt><dd><?= e(fmt_date($submission['submitted_at'], 'date_long')) ?>, <?= e(fmt_date($submission['submitted_at'], 'time')) ?>
      <?php if ((int) $submission['is_late']): ?> <?= badge(t('Late'), 'amber') ?><?php else: ?> <?= badge(t('On time'), 'leaf') ?><?php endif; ?></dd>
    <?php if ($assignment['kind'] === 'quiz'): ?>
      <dt><?= e(t('Attempt')) ?></dt><dd><?= (int) $submission['attempt_no'] ?></dd>
      <dt><?= e(t('Answered')) ?></dt><dd><?= e(t(':n of :total questions', ['n' => $answered, 'total' => $questionCount])) ?></dd>
    <?php endif; ?>
    <?php if ($files): ?>
      <dt><?= e(t('Files')) ?></dt>
      <dd><?php foreach ($files as $f): ?><a href="<?= e(url('/submission-files/' . (int) $f['id'])) ?>"><?= e($f['original_filename']) ?></a> <span class="meta">(<?= e(Uploads::humanSize((int) $f['size_bytes'])) ?>)</span><br><?php endforeach; ?></dd>
    <?php endif; ?>
  </dl>
</section>

<section class="section" aria-labelledby="next-title" style="max-width: 640px">
  <div class="section__head"><h2 class="section__title" id="next-title"><?= e(t('What happens next')) ?></h2></div>
  <ul class="prose">
    <?php if ($submission['feedback_status'] === 'released'): ?>
      <li><?= e(t('Your result is already available on the assignment page.')) ?></li>
    <?php elseif ($submission['grading_status'] === 'needs_review'): ?>
      <li><?= e(t('Your teacher will read and mark your work.')) ?></li>
    <?php else: ?>
      <li><?= e(t('Your answers have been marked, and your teacher will release the results.')) ?></li>
    <?php endif; ?>
    <li><?= e(t('You will get a notification when feedback is released.')) ?></li>
  </ul>
  <div class="cluster" style="margin-top: 16px">
    <a class="btn btn--primary" href="<?= e(url('/student/assignments/' . (int) $assignment['id'])) ?>"><?= e(t('Back to the assignment')) ?></a>
    <a class="btn btn--secondary" href="<?= e(url('/student')) ?>"><?= e(t('Home')) ?></a>
  </div>
</section>
