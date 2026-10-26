<?php
/** @var list<array> $work rows from StudentRepo::assignments @var bool $showClass */
use Sibol\Core\Dates;

$labels = [
    'upcoming' => [t('To do'), 'neutral'],
    'overdue' => [t('Overdue'), 'coral'],
    'submitted' => [t('Submitted'), 'neutral'],
    'graded' => [t('Graded'), 'leaf'],
    'excused' => [t('Excused'), 'neutral'],
];
?>
<ul class="entries">
  <?php foreach ($work as $w):
      $d = Dates::due($w['due_at'], (string) $w['kind']);
      $notOpen = (int) ($w['not_open'] ?? 0) === 1;
      $paper = $w['kind'] === 'paper';
      [$label, $tone] = $labels[$w['bucket']];
      if ($w['bucket'] === 'upcoming' && in_array($w['sub_status'], ['in_progress', 'returned'], true)) {
          $label = $w['sub_status'] === 'returned' ? t('Returned for changes') : ($w['kind'] === 'quiz' ? t('In progress') : t('Draft saved'));
          $tone = 'amber';
      } elseif ($paper && $w['bucket'] === 'submitted') {
          $label = t('Waiting for your score');
      } elseif ($paper && $w['bucket'] === 'overdue') {
          $label = t('Absent');
      } elseif ($paper && $w['bucket'] === 'upcoming') {
          $label = t('In class');
      }
  ?>
    <li class="entry<?= $w['bucket'] === 'overdue' ? ' entry--alert' : '' ?>">
      <?= partial('entry_date', ['at' => $w['due_at']]) ?>
      <div class="entry__main">
        <a class="row__title" href="<?= e(url('/student/assignments/' . (int) $w['id'])) ?>"><?= e($w['title']) ?></a>
        <div class="row__meta">
          <?= kind_tag((string) $w['kind']) ?>
          <?php if ($showClass): ?><span><?= e($w['class_name']) ?></span><?php endif; ?>
          <?php if ($w['kind'] === 'quiz' && (int) $w['time_limit_minutes'] > 0): ?><span><?= e(t(':n minutes', ['n' => (int) $w['time_limit_minutes']])) ?></span><?php endif; ?>
          <?php if ($notOpen): ?>
            <span><?= e(t('Opens :when', ['when' => fmt_date($w['opens_at'], 'datetime_short')])) ?></span>
          <?php elseif ($w['submitted_at'] && !$paper): ?>
            <span><?= e(t('Submitted :when', ['when' => fmt_date($w['submitted_at'], 'datetime_short')])) ?></span>
          <?php else: ?>
            <span><?= e($d['label']) ?></span>
          <?php endif; ?>
        </div>
      </div>
      <div class="entry__aside">
        <?php if ($w['released_score'] !== null): ?>
          <?= score_html($w['released_score'], $w['points_possible']) ?>
        <?php else: ?>
          <?= badge($notOpen ? t('Not open yet') : $label, $notOpen ? 'neutral' : $tone) ?>
          <?php if ($w['kind'] === 'quiz' && $w['bucket'] === 'upcoming' && !$notOpen): ?>
            <?php /* The quiz page has the Start button and the rules; this only takes the student there. */ ?>
            <a class="btn btn--primary btn--sm" href="<?= e(url('/student/assignments/' . (int) $w['id'])) ?>"><?= e($w['sub_status'] === 'in_progress' ? t('Resume quiz') : t('Start quiz')) ?><span class="sr-only">: <?= e($w['title']) ?></span></a>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </li>
  <?php endforeach; ?>
</ul>
