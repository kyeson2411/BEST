<?php
/**
 * The score grid for work done in class or on paper: one row per student with score, absent and excused.
 * @var array $a @var list<array> $rows @var array<int,string> $students @var array<int,array> $overrides @var int|null $selected
 */
$base = '/teacher/assignments/' . (int) $a['id'];
$paper = $a['kind'] === 'paper';
$max = (float) $a['points_possible'];
$oldScores = (array) old('scores', []);
$oldAbsent = array_map('intval', (array) old('absent', []));
$oldExcused = array_map('intval', (array) old('excused', []));
$hasOld = errors() !== [];
$held = count(array_filter($rows, static fn ($r) => $r['source'] === 'paper' && $r['grading_status'] === 'graded' && $r['feedback_status'] !== 'released'));
$entered = count(array_filter($rows, static fn ($r) => $r['submission_id'] !== null && $r['status'] === 'submitted'));
$draft = $a['status'] === 'draft';
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Scores')) ?></h1>
    <p class="page-lead"><?= e($a['title']) ?>, <?= e($a['class_name']) ?>. <?= e(\Sibol\Core\Dates::due($a['due_at'], (string) $a['kind'])['label']) ?>. <?= e(t('Out of :n points.', ['n' => fmt_number($max, 2)])) ?></p>
  </div>
  <div class="page-actions">
    <a class="btn btn--secondary" href="<?= e(url($base . '/print')) ?>"><?= icon('printer', 'icon--sm') ?><?= e(t('Print a blank copy')) ?></a>
    <?php if (!$paper): ?><a class="btn btn--secondary" href="<?= e(url($base . '/submissions')) ?>"><?= icon('inbox', 'icon--sm') ?><?= e(t('Online submissions')) ?></a><?php endif; ?>
    <a class="btn btn--secondary" href="<?= e(url('/teacher/gradebook/' . (int) $a['class_id'])) ?>"><?= icon('table', 'icon--sm') ?><?= e(t('Gradebook')) ?></a>
  </div>
</div>

<?php if ($draft): ?>
  <div class="callout callout--amber" style="margin-bottom: 20px"><?= icon('info') ?><p><?= e(t('Publish this work before entering scores, so students can see it and its date.')) ?></p></div>
<?php endif; ?>
<p class="section__intro"><?= e($paper
    ? t('Type each student\'s score. Leave it empty until you have marked their paper. Students see scores only after you release them.')
    : t('For students who did this on a printed copy instead of online. Students who handed it in online keep their online grading.')) ?>
  <?= e(tp(':count score entered', ':count scores entered', $entered)) ?><?= $held ? ', ' . e(tp(':count not released yet', ':count not released yet', $held)) : '' ?>.</p>

<?php if (!$rows): ?>
  <?= partial('empty', ['compact' => true, 'title' => t('No students yet'), 'body' => t('Students appear here when they join the class.')]) ?>
<?php else: ?>
  <form method="post" action="<?= e(url($base . '/scores')) ?>" data-unsaved-warning>
    <?= csrf_field() ?>
    <div class="table-frame">
      <table class="table table--stack table--compact score-grid">
        <caption class="sr-only"><?= e(t('Scores for :title', ['title' => $a['title']])) ?></caption>
        <thead><tr>
          <th scope="col"><?= e(t('Student')) ?></th>
          <th scope="col"><?= e(t('Score (out of :n)', ['n' => fmt_number($max, 2)])) ?></th>
          <th scope="col"><?= e(t('Absent')) ?></th>
          <th scope="col"><?= e(t('Excused')) ?></th>
          <th scope="col"><?= e(t('Status')) ?></th>
        </tr></thead>
        <tbody>
          <?php foreach ($rows as $r):
              $sid = (int) $r['student_id'];
              $name = user_name($r);
              $error = field_error('scores[' . $sid . ']');
              $value = $hasOld ? (string) ($oldScores[$sid] ?? '') : ($r['source'] === 'paper' && $r['score'] !== null ? fmt_number($r['score'], 2) : '');
              $absent = $hasOld ? in_array($sid, $oldAbsent, true) : $r['absent'];
              $excused = $hasOld ? in_array($sid, $oldExcused, true) : $r['excused'];
          ?>
            <tr id="student-<?= $sid ?>">
              <td class="cell-primary"><strong><?= e($name) ?></strong><span class="table-sub code-text"><?= e((string) $r['username']) ?></span></td>
              <td data-label="<?= e(t('Score')) ?>">
                <?php if ($r['editable']): ?>
                  <label class="sr-only" for="score-<?= $sid ?>"><?= e(t('Score for :name', ['name' => $name])) ?></label>
                  <input class="input input--num" id="score-<?= $sid ?>" name="scores[<?= $sid ?>]" type="text" inputmode="decimal" autocomplete="off" value="<?= e($value) ?>"
                         <?= $error ? 'aria-invalid="true" aria-describedby="score-' . $sid . '-error"' : '' ?><?= $draft ? ' disabled' : '' ?>>
                  <?php if ($error): ?><p class="field__error" id="score-<?= $sid ?>-error"><?= icon('circle-alert', 'icon--xs') ?><?= e($error) ?></p><?php endif; ?>
                <?php else: ?>
                  <a href="<?= e(url('/teacher/submissions/' . (int) $r['submission_id'])) ?>"><?= e($r['score'] !== null ? fmt_number($r['score'], 2) : t('Handed in online')) ?></a>
                <?php endif; ?>
              </td>
              <td data-label="<?= e(t('Absent')) ?>">
                <?php if ($r['editable']): ?>
                  <label class="check-row"><input type="checkbox" name="absent[]" value="<?= $sid ?>"<?= checked($absent) ?><?= $draft ? ' disabled' : '' ?>><span class="sr-only"><?= e(t('Absent: :name', ['name' => $name])) ?></span></label>
                <?php else: ?><span class="meta">—</span><?php endif; ?>
              </td>
              <td data-label="<?= e(t('Excused')) ?>">
                <label class="check-row"><input type="checkbox" name="excused[]" value="<?= $sid ?>"<?= checked($excused) ?><?= $draft ? ' disabled' : '' ?>><span class="sr-only"><?= e(t('Excused: :name', ['name' => $name])) ?></span></label>
              </td>
              <td data-label="<?= e(t('Status')) ?>">
                <?php if (!$r['editable']): ?><?= badge(t('Online'), 'neutral') ?>
                <?php elseif ($r['source'] === 'paper' && $r['feedback_status'] === 'released'): ?><?= badge(t('Released'), 'leaf') ?>
                <?php elseif ($r['source'] === 'paper'): ?><?= badge(t('Held'), 'amber') ?>
                <?php elseif ($r['excused']): ?><span class="meta"><?= e(t('Not counted')) ?></span>
                <?php elseif ($r['absent']): ?><?= badge($r['own_due_at'] && $r['own_due_at'] > \Sibol\Core\Db::now() ? t('Make-up :date', ['date' => fmt_date($r['own_due_at'], 'date_short')]) : t('Absent'), 'coral') ?>
                <?php else: ?><span class="meta"><?= e(t('No score yet')) ?></span><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if (!$draft): ?>
      <div class="form-actions">
        <button class="btn btn--primary" type="submit" name="action" value="save" data-busy="<?= e(t('Saving…')) ?>"><?= e(t('Save scores')) ?></button>
        <button class="btn btn--secondary" type="submit" name="action" value="release" data-busy="<?= e(t('Releasing…')) ?>"
                data-confirm="<?= e(t('Every saved score on this sheet becomes visible to its student, and each student is notified.')) ?>" data-confirm-title="<?= e(t('Save and release scores?')) ?>" data-confirm-action="<?= e(t('Release')) ?>"><?= icon('send', 'icon--sm') ?><?= e(t('Save and release')) ?></button>
        <span class="meta"><?= e(t('An empty score removes a score saved earlier. A student with a score is no longer marked absent.')) ?></span>
      </div>
    <?php endif; ?>
  </form>
<?php endif; ?>

<?= partial('override_form', ['a' => $a, 'students' => $students, 'overrides' => $overrides, 'selected' => $selected, 'page' => 'scores']) ?>
