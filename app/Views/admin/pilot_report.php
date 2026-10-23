<?php
/**
 * The weekly pilot report: one column per week, oldest on the left; counts only.
 * @var int $weeks @var list<array<string,mixed>> $rows newest first
 */
use Sibol\Core\View;
use Sibol\Services\PilotReport;

$columns = array_reverse($rows);
$groups = [
    t('People') => ['students' => t('Active students'), 'teachers' => t('Active teachers')],
    t('Class content') => ['materials' => t('Materials published'), 'assignments' => t('Assignments and quizzes published')],
    t('Online work due') => ['due' => t('Due (one per student and task)'), 'on_time' => t('Handed in on time'), 'late' => t('Handed in late'), 'missing' => t('Missing')],
    t('Companion and practice') => ['companion' => t('Questions to the companion'), 'companion_students' => t('Students using the companion'), 'practice' => t('Practice sets made'), 'practice_students' => t('Students practising')],
    t('Feedback') => ['feedback_problem' => t('Not working'), 'feedback_confusing' => t('Confusing'), 'feedback_idea' => t('Ideas')],
];
$cell = static function (array $row, string $key): string {
    if (in_array($key, PilotReport::RATES, true) && $row['due'] === 0) {
        return '<span class="muted">—</span>';
    }
    if ($row[$key] === null) {
        return '<span class="muted">' . e(t('hidden')) . '</span>';
    }
    return in_array($key, PilotReport::RATES, true) ? e(fmt_number($row[$key], 1)) . '%' : e((string) $row[$key]);
};
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Weekly pilot report')) ?></h1>
    <p class="page-lead"><?= e(t('How B.E.S.T. Learning and Interactive Study System is used across the school, week by week from Monday to Sunday. Counts only: no rankings and no lists of students. A number that comes from fewer than :n people is hidden.', ['n' => PilotReport::MIN_GROUP])) ?></p>
  </div>
  <div class="page-actions">
    <a class="btn btn--secondary" href="<?= e(url('/admin/pilot/export', ['weeks' => $weeks])) ?>"><?= icon('download', 'icon--sm') ?><?= e(t('Download CSV')) ?></a>
  </div>
</div>
<?= View::file('admin/_pilot_tabs', ['active' => 'report']) ?>

<form class="toolbar" method="get" action="<?= e(url('/admin/pilot')) ?>">
  <div class="field"><label class="field__label" for="p-weeks"><?= e(t('Weeks')) ?></label>
    <select class="input select" id="p-weeks" name="weeks" data-autosubmit>
      <?php foreach (PilotReport::WEEK_CHOICES as $n): ?><option value="<?= $n ?>"<?= selected($n, $weeks) ?>><?= e(tp('Last :count week', 'Last :count weeks', $n)) ?></option><?php endforeach; ?>
    </select></div>
  <button class="btn btn--secondary" type="submit"><?= e(t('Apply')) ?></button>
</form>

<div class="table-frame pilot-report" data-scroll-end>
  <table class="table table--compact">
    <caption class="sr-only"><?= e(tp('Pilot report for the last :count week', 'Pilot report for the last :count weeks', $weeks)) ?></caption>
    <thead>
      <tr>
        <th scope="col"><?= e(t('Week starting')) ?></th>
        <?php foreach ($columns as $row): ?>
          <th scope="col" class="num"><?= e(fmt_date($row['start'] . ' 12:00:00', 'date_short')) ?><?php if ($row['current']): ?><span class="table-sub"><?= e(t('so far')) ?></span><?php endif; ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <?php foreach ($groups as $group => $metrics): ?>
      <tbody>
        <tr class="pilot-report__group"><th scope="rowgroup" colspan="<?= count($columns) + 1 ?>"><span><?= e($group) ?></span></th></tr>
        <?php foreach ($metrics as $key => $label): ?>
          <tr>
            <th scope="row"><?= e($label) ?></th>
            <?php foreach ($columns as $row): ?><td class="num"><?= $cell($row, $key) ?></td><?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    <?php endforeach; ?>
  </table>
</div>

<details class="how" style="margin-top: 24px">
  <summary><?= e(t('How these numbers are calculated')) ?></summary>
  <div class="prose">
    <ul>
      <li><?= e(t('Active: students or teachers who did anything B.E.S.T. Learning and Interactive Study System records that week, such as signing in, opening a material, handing in work or asking the companion.')) ?></li>
      <li><?= e(t('Published: materials, assignments and quizzes first published that week, in every class (copies to other sections count separately).')) ?></li>
      <li><?= e(t('Online work due: each student and each assignment or quiz whose due date for that student (their own date if they have one) fell that week and has passed. Excused work and work done on paper are left out. Missing means not handed in yet.')) ?></li>
      <li><?= e(t('Companion and practice: questions students asked that got an answer, and practice sets they made. What they asked is never part of the report.')) ?></li>
      <li><?= e(t('Feedback: messages sent with "Send feedback", by kind.')) ?></li>
      <li><?= e(t('"hidden" means fewer than :n people are behind that number (for example, the teachers who published that week), so it is not shown. A dash means nothing was due.', ['n' => PilotReport::MIN_GROUP])) ?></li>
    </ul>
  </div>
</details>
