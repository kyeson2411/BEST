<?php
/** @var array $section @var string $label @var list<array> $students @var list<array> $classes */
$base = '/teacher/advisory/' . (int) $section['id'];
$notSetUp = count(array_filter($students, static fn ($s) => $s['status'] === 'invited'));
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e($label) ?></h1>
    <p class="page-lead"><?= e(t('You are this section\'s adviser.')) ?> <?= e(tp(':count student', ':count students', count($students))) ?><?= $section['period_name'] ? ', ' . e($section['period_name']) : '' ?>.</p>
  </div>
</div>

<div class="layout-split">
  <section aria-labelledby="students-title">
    <h2 class="sr-only" id="students-title"><?= e(t('Students')) ?></h2>
    <?php if (!$students): ?>
      <?= partial('empty', ['compact' => true, 'title' => t('No students yet'), 'body' => t('Students appear here when they are added to this section\'s classes.')]) ?>
    <?php else: ?>
      <div class="table-frame">
        <table class="table table--stack">
          <caption class="sr-only"><?= e(t('Students in :section', ['section' => $label])) ?></caption>
          <thead><tr><th scope="col"><?= e(t('Student')) ?></th><th scope="col"><?= e(t('Account')) ?></th><th scope="col" class="actions"><span class="sr-only"><?= e(t('Actions')) ?></span></th></tr></thead>
          <tbody>
            <?php foreach ($students as $s): $setup = $s['status'] === 'invited'; ?>
              <tr>
                <td class="cell-primary"><span class="cluster" style="flex-wrap:nowrap"><?= partial('avatar', ['person' => $s, 'size' => 'sm']) ?>
                  <span><strong><?= e(user_name($s)) ?></strong><span class="table-sub code-text"><?= e((string) $s['username']) ?></span></span></span></td>
                <td data-label="<?= e(t('Account')) ?>">
                  <?php if ($s['status'] === 'deactivated'): ?><?= badge(t('Deactivated'), 'neutral') ?>
                  <?php elseif ($setup): ?><?= badge(t('Not set up yet'), 'amber') ?>
                  <?php else: ?><?= badge(t('Active'), 'leaf') ?><span class="table-sub"><?= $s['last_login_at'] ? e(t('Last sign-in :date', ['date' => fmt_date($s['last_login_at'], 'date_short')])) : e(t('Never signed in')) ?></span><?php endif; ?>
                </td>
                <td class="actions">
                  <?php if ($s['status'] !== 'deactivated'): ?>
                    <form method="post" action="<?= e(url('/teacher/students/' . (int) $s['id'] . '/slip')) ?>" data-confirm="<?= e($setup ? t('Prints a sign-in slip with a new one-time code so :name can set up their account. An earlier slip or link for them stops working.', ['name' => user_name($s)]) : t('Prints a slip with a one-time code so :name can choose a new password. Their current password keeps working until the slip is used. Slips are recorded in the audit log.', ['name' => user_name($s)])) ?>" data-confirm-title="<?= e($setup ? t('Print a sign-in slip?') : t('Print a password slip?')) ?>" data-confirm-action="<?= e(t('Print slip')) ?>">
                      <?= csrf_field() ?><input type="hidden" name="back" value="<?= e($base) ?>">
                      <button class="btn btn--quiet btn--sm" type="submit"><?= icon('printer', 'icon--sm') ?><?= e($setup ? t('Sign-in slip') : t('Password slip')) ?></button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <aside class="stack">
    <section class="panel" aria-labelledby="slips-title">
      <h2 class="panel__title" id="slips-title"><?= e(t('Sign-in slips')) ?></h2>
      <?php if ($notSetUp): ?>
        <p class="meta"><?= e(tp(':count student has not set up their account yet. Print slips with their username and a one-time code, then hand them out in class.', ':count students have not set up their account yet. Print slips with their username and a one-time code, then hand them out in class.', $notSetUp)) ?></p>
        <form method="post" action="<?= e(url($base . '/slips')) ?>" data-confirm="<?= e(t('Each slip gets a new one-time code; earlier slips or links for these students stop working.')) ?>" data-confirm-title="<?= e(t('Print sign-in slips?')) ?>" data-confirm-action="<?= e(t('Print slips')) ?>">
          <?= csrf_field() ?><button class="btn btn--secondary" type="submit"><?= icon('printer', 'icon--sm') ?><?= e(tp('Print :count slip', 'Print :count slips', $notSetUp)) ?></button>
        </form>
      <?php else: ?>
        <p class="meta" style="margin:0"><?= e(t('Everyone has set up their account. If a student forgets their password, print them a password slip from the list.')) ?></p>
      <?php endif; ?>
    </section>
    <section class="section" aria-labelledby="classes-title">
      <div class="section__head"><h2 class="section__title" id="classes-title"><?= e(t('Classes in this section')) ?></h2></div>
      <?php if (!$classes): ?><p class="meta"><?= e(t('No active classes yet.')) ?></p>
      <?php else: ?>
        <ul class="rows">
          <?php foreach ($classes as $c): ?>
            <li class="row"><div class="row__main"><span class="row__title"><?= e($c['name']) ?></span><div class="row__meta"><span><?= e($c['subject_name']) ?></span><?php if ($c['teachers']): ?><span><?= e($c['teachers']) ?></span><?php endif; ?></div></div></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </aside>
</div>
