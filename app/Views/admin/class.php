<?php
/**
 * @var array $class @var list<array> $members @var list<array> $candidates @var list<array> $teachers
 * @var array $subjects @var array $periods @var list<string> $grades @var array $activity
 */
$base = '/admin/classes/' . (int) $class['id'];
$archived = $class['status'] !== 'active';
$teacherRows = array_values(array_filter($members, static fn ($m) => $m['role'] === 'teacher' && $m['status'] === 'active'));
$studentRows = array_values(array_filter($members, static fn ($m) => $m['role'] === 'student' && $m['status'] === 'active'));
$withdrawn = array_values(array_filter($members, static fn ($m) => $m['status'] !== 'active'));
$memberRow = static function (array $m) use ($base, $archived): string {
    ob_start(); ?>
    <li class="row">
      <span class="row__lead"><?= partial('avatar', ['person' => $m, 'size' => 'sm']) ?></span>
      <div class="row__main">
        <a class="row__title" href="<?= e(url('/admin/users/' . (int) $m['id'])) ?>"><?= e(user_name($m)) ?></a>
        <div class="row__meta"><span><?= e($m['email']) ?></span>
          <?php if ($m['user_status'] === 'invited'): ?><span><?= e(t('Invitation not yet accepted')) ?></span><?php endif; ?>
          <span><?= e(t('Added :date', ['date' => fmt_date($m['joined_at'], 'date_short')])) ?></span></div>
      </div>
      <?php if (!$archived): ?>
        <div class="row__aside">
          <form method="post" action="<?= e(url($base . '/members/' . (int) $m['membership_id'] . '/remove')) ?>" data-confirm="<?= e($m['role'] === 'student' ? t(':name will no longer see this class. Their submitted work and grades are kept.', ['name' => user_name($m)]) : t(':name will no longer see or manage this class.', ['name' => user_name($m)])) ?>" data-confirm-title="<?= e(t('Remove from class?')) ?>" data-confirm-action="<?= e(t('Remove')) ?>" data-confirm-tone="danger">
            <?= csrf_field() ?><button class="btn btn--quiet btn--sm" type="submit"><?= e(t('Remove')) ?></button></form>
        </div>
      <?php endif; ?>
    </li>
    <?php return (string) ob_get_clean();
};
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e($class['name']) ?></h1>
    <p class="page-lead"><?= e($class['subject_name']) ?><?= $class['period_name'] ? ', ' . e($class['period_name']) : '' ?><?= $archived ? ' ' . badge(t('Archived'), 'neutral') : '' ?></p>
  </div>
  <div class="page-actions">
    <form method="post" action="<?= e(url($base . '/status')) ?>"<?= $archived ? '' : ' data-confirm="' . e(t('Teachers and students keep read access, but no new work, materials or members can be added. You can restore it later.')) . '" data-confirm-title="' . e(t('Archive this class?')) . '" data-confirm-action="' . e(t('Archive')) . '"' ?>>
      <?= csrf_field() ?><input type="hidden" name="action" value="<?= $archived ? 'restore' : 'archive' ?>">
      <button class="btn btn--secondary" type="submit"><?= icon($archived ? 'archive-restore' : 'archive', 'icon--sm') ?><?= e($archived ? t('Restore class') : t('Archive class')) ?></button>
    </form>
  </div>
</div>

<dl class="stat-line" style="margin-bottom: 28px">
  <div class="stat"><dt class="stat__label"><?= e(t('Students')) ?></dt><dd style="margin:0"><span class="stat__value"><?= count($studentRows) ?></span></dd></div>
  <div class="stat"><dt class="stat__label"><?= e(t('Teachers')) ?></dt><dd style="margin:0"><span class="stat__value"><?= count($teacherRows) ?></span></dd></div>
  <div class="stat"><dt class="stat__label"><?= e(t('Published work')) ?></dt><dd style="margin:0"><span class="stat__value"><?= (int) $activity['assignments'] ?></span><span class="stat__note"><?= e(tp(':count published material', ':count published materials', (int) $activity['materials']) . ', ' . tp(':count lesson', ':count lessons', (int) $activity['lessons'])) ?></span></dd></div>
</dl>

<div class="layout-split">
  <div class="stack" id="members">
    <section class="section" aria-labelledby="teachers-title">
      <div class="section__head"><h2 class="section__title" id="teachers-title"><?= e(t('Teachers')) ?></h2></div>
      <?php if (!$teacherRows): ?><p class="meta"><?= e(t('No teacher yet. Add one so the class can be run.')) ?></p>
      <?php else: ?><ul class="rows"><?php foreach ($teacherRows as $m): ?><?= $memberRow($m) ?><?php endforeach; ?></ul><?php endif; ?>
      <?php if (!$archived && $teachers): ?>
        <form class="toolbar" method="post" action="<?= e(url($base . '/members')) ?>" style="margin-top: 12px">
          <?= csrf_field() ?><input type="hidden" name="role" value="teacher">
          <div class="field field--grow"><label class="field__label" for="add-teacher"><?= e(t('Add a teacher')) ?></label>
            <select class="input select" id="add-teacher" name="user_id" required><option value=""><?= e(t('Choose a teacher')) ?></option>
              <?php foreach ($teachers as $t): ?><option value="<?= (int) $t['id'] ?>"><?= e(user_name($t)) ?><?= $t['status'] === 'invited' ? ' (' . e(t('invited')) . ')' : '' ?></option><?php endforeach; ?></select></div>
          <button class="btn btn--secondary" type="submit"><?= e(t('Add')) ?></button>
        </form>
      <?php endif; ?>
    </section>

    <section class="section" aria-labelledby="students-title">
      <div class="section__head"><h2 class="section__title" id="students-title"><?= e(t('Students')) ?></h2></div>
      <?php if (!$studentRows): ?><p class="meta"><?= e(t('No students yet. Add them below, or let the teacher share a class code.')) ?></p>
      <?php else: ?><ul class="rows"><?php foreach ($studentRows as $m): ?><?= $memberRow($m) ?><?php endforeach; ?></ul><?php endif; ?>
      <?php if ($withdrawn): ?>
        <details style="margin-top: 12px"><summary class="meta"><?= e(tp(':count former member', ':count former members', count($withdrawn))) ?></summary>
          <ul class="rows"><?php foreach ($withdrawn as $m): ?><li class="row row--muted"><div class="row__main"><a class="row__title" href="<?= e(url('/admin/users/' . (int) $m['id'])) ?>"><?= e(user_name($m)) ?></a><div class="row__meta"><span><?= e($m['role'] === 'teacher' ? t('Teacher') : t('Student')) ?></span><span><?= e(t('Removed :date', ['date' => fmt_date($m['withdrawn_at'], 'date_short')])) ?></span></div></div></li><?php endforeach; ?></ul>
        </details>
      <?php endif; ?>
    </section>

    <?php if (!$archived): ?>
      <section class="section" aria-labelledby="add-title">
        <h2 class="section__title" id="add-title"><?= e(t('Add students')) ?></h2>
        <form method="post" action="<?= e(url($base . '/members')) ?>">
          <?= csrf_field() ?><input type="hidden" name="role" value="student">
          <?php if ($candidates): ?>
            <fieldset style="border:0; padding:0; margin:0 0 16px">
              <legend class="field__label"><?= e($class['grade_level'] ? t(':grade students not in this class', ['grade' => $class['grade_level']]) : t('Students not in this class')) ?></legend>
              <div class="checklist-grid">
                <?php foreach ($candidates as $s): ?>
                  <label class="check-row"><input type="checkbox" name="user_ids[]" value="<?= (int) $s['id'] ?>"> <span><?= e(user_name($s)) ?><?= $s['status'] === 'invited' ? ' <span class="meta">(' . e(t('invited')) . ')</span>' : '' ?></span></label>
                <?php endforeach; ?>
              </div>
            </fieldset>
          <?php endif; ?>
          <?= field('textarea', 'emails', t('Or add by email'), ['rows' => 3, 'hint' => t('One or more student emails, separated by spaces, commas or new lines.'), 'attrs' => ['maxlength' => 5000]]) ?>
          <div class="form-actions"><button class="btn btn--primary" type="submit"><?= icon('user-plus', 'icon--sm') ?><?= e(t('Add students')) ?></button></div>
        </form>
      </section>
    <?php endif; ?>
  </div>

  <aside class="stack">
    <section class="panel" aria-labelledby="det-title">
      <h2 class="panel__title" id="det-title"><?= e(t('Class details')) ?></h2>
      <form method="post" action="<?= e(url($base)) ?>">
        <?= csrf_field() ?>
        <?= field('text', 'name', t('Class name'), ['value' => $class['name'], 'required' => true, 'attrs' => ['maxlength' => 140]]) ?>
        <?= field('select', 'subject_id', t('Subject'), ['value' => (string) $class['subject_id'], 'required' => true, 'options' => $subjects + (isset($subjects[$class['subject_id']]) ? [] : [$class['subject_id'] => $class['subject_name']])]) ?>
        <?= field('select', 'academic_period_id', t('Academic period'), ['value' => (string) $class['academic_period_id'], 'options' => ['' => t('No period')] + $periods]) ?>
        <?= field('text', 'section', t('Section'), ['value' => (string) $class['section'], 'attrs' => ['maxlength' => 60]]) ?>
        <?= field('select', 'grade_level', t('Grade level'), ['value' => (string) $class['grade_level'], 'options' => ['' => t('Not set')] + array_combine($grades, $grades)]) ?>
        <?= field('textarea', 'description', t('Description'), ['value' => (string) $class['description'], 'rows' => 3, 'attrs' => ['maxlength' => 2000]]) ?>
        <button class="btn btn--secondary" type="submit"><?= e(t('Save details')) ?></button>
      </form>
    </section>
  </aside>
</div>
