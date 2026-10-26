<?php /** @var bool $quizzes @var string $filter @var int|null $classId @var list<array> $classes @var list<array> $rows @var array<string,int> $counts */
// The same page lists quizzes (/student/quizzes) or everything else (/student/assignments).
$path = $quizzes ? '/student/quizzes' : '/student/assignments';
$labels = ['upcoming' => t('To do'), 'overdue' => t('Overdue'), 'submitted' => t('Submitted'), 'graded' => t('Graded'), 'excused' => t('Excused'), 'all' => t('All')];
// The excused tab appears only when a teacher has excused something.
if (empty($counts['excused']) && $filter !== 'excused') {
    unset($labels['excused']);
}
$empty = $quizzes ? [
    'upcoming' => [t('No quizzes to take right now'), t('New quizzes will appear here as soon as your teachers publish them.')],
    'overdue' => [t('No missed quizzes'), t('A quiz you did not take before its due date will appear here.')],
    'submitted' => [t('Nothing waiting for a score'), t('Quizzes you have submitted appear here until your teacher releases your score.')],
    'graded' => [t('No quiz scores yet'), t('When your teachers release quiz scores, you will find them here.')],
    'excused' => [t('Nothing excused'), t('Work your teacher excuses you from appears here. It is not counted and never shows as missing.')],
    'all' => [t('No quizzes yet'), t('Your classes have no published quizzes yet.')],
] : [
    'upcoming' => [t('Nothing to do right now'), t('New assignments will appear here as soon as your teachers publish them.')],
    'overdue' => [t('Nothing overdue'), t('Work you have not handed in after its due date will appear here.')],
    'submitted' => [t('Nothing waiting for a grade'), t('Work you have submitted appears here until your teacher releases feedback.')],
    'graded' => [t('No graded work yet'), t('When your teachers release grades and comments, you will find them here.')],
    'excused' => [t('Nothing excused'), t('Work your teacher excuses you from appears here. It is not counted and never shows as missing.')],
    'all' => [t('No work yet'), t('Your classes have no published assignments yet.')],
];
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e($quizzes ? t('Quizzes') : t('Assignments')) ?></h1>
    <?php if ($quizzes): ?>
      <p class="page-lead"><?= e(t('Quizzes from your teachers, taken online. Open one to see how many questions it has and how long you get, then start it when you are ready.')) ?></p>
    <?php else: ?>
      <p class="page-lead"><?= e(t('Work you hand in here, and work your teacher scores in class or on paper.')) ?> <a href="<?= e(url('/student/quizzes')) ?>"><?= e(t('Quizzes are on their own page.')) ?></a></p>
    <?php endif; ?>
  </div>
</div>

<div class="toolbar">
  <nav class="segmented" aria-label="<?= e(t('Filter by status')) ?>">
    <?php foreach ($labels as $key => $label): ?>
      <a href="<?= e(url($path, ['filter' => $key, 'class_id' => $classId])) ?>"<?= $filter === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?> <span class="num">(<?= (int) $counts[$key] ?>)</span></a>
    <?php endforeach; ?>
  </nav>
  <form class="toolbar__end" method="get" action="<?= e(url($path)) ?>">
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
