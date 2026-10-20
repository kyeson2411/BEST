<?php /** @var string $filter @var int|null $classId @var list<array> $classes @var list<array> $rows @var array<string,int> $counts */
$labels = ['upcoming' => t('To do'), 'overdue' => t('Overdue'), 'submitted' => t('Submitted'), 'graded' => t('Graded'), 'all' => t('All')];
$empty = [
    'upcoming' => [t('Nothing to do right now'), t('New assignments and quizzes will appear here as soon as your teachers publish them.')],
    'overdue' => [t('Nothing overdue'), t('Work you have not handed in after its due date will appear here.')],
    'submitted' => [t('Nothing waiting for a grade'), t('Work you have submitted appears here until your teacher releases feedback.')],
    'graded' => [t('No graded work yet'), t('When your teachers release grades and comments, you will find them here.')],
    'all' => [t('No work yet'), t('Your classes have no published assignments or quizzes yet.')],
];
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Assignments')) ?></h1>
    <p class="page-lead"><?= e(t('Everything your teachers have given you, with due dates in your timezone.')) ?></p>
  </div>
</div>

<div class="toolbar">
  <nav class="segmented" aria-label="<?= e(t('Filter by status')) ?>">
    <?php foreach ($labels as $key => $label): ?>
      <a href="<?= e(url('/student/assignments', ['filter' => $key, 'class_id' => $classId])) ?>"<?= $filter === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?> <span class="num">(<?= (int) $counts[$key] ?>)</span></a>
    <?php endforeach; ?>
  </nav>
  <form class="toolbar__end" method="get" action="<?= e(url('/student/assignments')) ?>">
    <input type="hidden" name="filter" value="<?= e($filter) ?>">
    <label class="sr-only" for="class-filter"><?= e(t('Class')) ?></label>
    <select class="input select" id="class-filter" name="class_id" data-autosubmit>
      <option value=""><?= e(t('All classes')) ?></option>
      <?php foreach ($classes as $c): ?>
        <option value="<?= (int) $c['id'] ?>"<?= selected($c['id'], $classId) ?>><?= e($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <noscript><button class="btn btn--secondary btn--sm" type="submit"><?= e(t('Apply')) ?></button></noscript>
  </form>
</div>

<?php if (!$rows): ?>
  <?= partial('empty', ['title' => $empty[$filter][0], 'body' => $empty[$filter][1]]) ?>
<?php else: ?>
  <?= partial('student_work_list', ['work' => $rows, 'showClass' => $classId === null]) ?>
<?php endif; ?>
