<?php
/**
 * @var array $user @var array $roles @var int $classes @var int $materials @var list<array> $failed @var array $usage @var int $budget
 * @var string $provider @var list<array> $completion @var array $pending @var list<array> $checks @var list<array> $audit
 */
use Sibol\Controllers\Admin\DashboardController;
use Sibol\Services\AuditLabels;

$active = static fn (string $role): int => (int) ($roles[$role]['active'] ?? 0);
$tokens = (int) $usage['tokens'];
$budgetPct = $budget > 0 ? percent($tokens, $budget) : null;
$pendingTotal = $pending['invited'] + $pending['privacy'] + $pending['reports'] + count($failed);
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e($user['org_name']) ?></h1>
    <p class="page-lead"><?= e($pendingTotal ? tp(':count item needs your attention.', ':count items need your attention.', $pendingTotal) : t('Nothing needs your attention right now.')) ?>
      <?= e(t('Learning results here are school-wide totals; individual students\' conversations are not shown.')) ?></p>
  </div>
  <div class="page-actions">
    <a class="btn btn--primary" href="<?= e(url('/admin/users/new')) ?>"><?= icon('user-plus', 'icon--sm') ?><?= e(t('Invite someone')) ?></a>
    <a class="btn btn--secondary" href="<?= e(url('/admin/users/import')) ?>"><?= icon('file-spreadsheet', 'icon--sm') ?><?= e(t('Import from CSV')) ?></a>
    <a class="btn btn--secondary" href="<?= e(url('/admin/classes/new')) ?>"><?= icon('plus', 'icon--sm') ?><?= e(t('New class')) ?></a>
  </div>
</div>

<dl class="stat-line" style="margin-bottom: 32px">
  <div class="stat"><dt class="stat__label"><?= e(t('Students')) ?></dt><dd style="margin:0"><span class="stat__value"><?= $active('student') ?></span><span class="stat__note"><?= e(t(':n invited, :d deactivated', ['n' => (int) ($roles['student']['invited'] ?? 0), 'd' => (int) ($roles['student']['deactivated'] ?? 0)])) ?></span></dd></div>
  <div class="stat"><dt class="stat__label"><?= e(t('Teachers')) ?></dt><dd style="margin:0"><span class="stat__value"><?= $active('teacher') ?></span><span class="stat__note"><?= e(t(':n invited', ['n' => (int) ($roles['teacher']['invited'] ?? 0)])) ?></span></dd></div>
  <div class="stat"><dt class="stat__label"><?= e(t('Administrators')) ?></dt><dd style="margin:0"><span class="stat__value"><?= $active('admin') ?></span><span class="stat__note"><?= e(t('Active accounts')) ?></span></dd></div>
  <div class="stat"><dt class="stat__label"><?= e(t('Active classes')) ?></dt><dd style="margin:0"><span class="stat__value"><?= $classes ?></span><span class="stat__note"><?= e(tp(':count published material', ':count published materials', $materials)) ?></span></dd></div>
</dl>

<div class="layout-split">
  <div class="stack">
    <section class="section" aria-labelledby="pending-title">
      <div class="section__head"><h2 class="section__title" id="pending-title"><?= e(t('Needs attention')) ?></h2></div>
      <?php if (!$pendingTotal): ?>
        <p class="meta"><?= e(t('Pending invitations, privacy requests, reported answers and failed uploads appear here.')) ?></p>
      <?php else: ?>
        <ul class="rows">
          <?php if ($pending['privacy']): ?><li class="row"><span class="row__lead"><?= icon('shield-check', 'icon--lg') ?></span><div class="row__main"><a class="row__title" href="<?= e(url('/admin/privacy')) ?>"><?= e(tp(':count privacy request to review', ':count privacy requests to review', $pending['privacy'])) ?></a><div class="row__meta"><span><?= e(t('Export, correction or deletion requests from your school\'s users.')) ?></span></div></div></li><?php endif; ?>
          <?php if ($pending['reports']): ?><li class="row"><span class="row__lead"><?= icon('flag', 'icon--lg') ?></span><div class="row__main"><a class="row__title" href="<?= e(url('/admin/privacy#reports')) ?>"><?= e(tp(':count reported companion answer', ':count reported companion answers', $pending['reports'])) ?></a><div class="row__meta"><span><?= e(t('Answers students or teachers flagged as wrong or unsafe.')) ?></span></div></div></li><?php endif; ?>
          <?php if ($pending['invited']): ?><li class="row"><span class="row__lead"><?= icon('mail', 'icon--lg') ?></span><div class="row__main"><a class="row__title" href="<?= e(url('/admin/users', ['status' => 'invited'])) ?>"><?= e(tp(':count invitation not yet accepted', ':count invitations not yet accepted', $pending['invited'])) ?></a><div class="row__meta"><span><?= e(t('Links expire after 14 days; you can create a new one.')) ?></span></div></div></li><?php endif; ?>
          <?php foreach ($failed as $f): ?><li class="row"><span class="row__lead bad"><?= icon('triangle-alert', 'icon--lg') ?></span><div class="row__main"><span class="row__title"><?= e(t('“:title” could not be read', ['title' => $f['title']])) ?></span><div class="row__meta"><span><?= e($f['class_name']) ?></span><span><?= e(str_limit((string) $f['processing_error'], 80)) ?></span></div></div></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="section" aria-labelledby="comp-title">
      <div class="section__head"><h2 class="section__title" id="comp-title"><?= e(t('Work handed in, last 30 days')) ?></h2></div>
      <?php if (!$completion): ?>
        <p class="meta"><?= e(t('No work has reached its due date in the last 30 days.')) ?></p>
      <?php else: ?>
        <ul class="bar-list">
          <?php foreach ($completion as $c): $small = (int) $c['expected'] < DashboardController::MIN_GROUP; ?>
            <li>
              <span class="bar-list__label"><?= e($c['subject']) ?></span>
              <?= $small ? '<span class="meta">' . e(t('Hidden: fewer than :n expected submissions', ['n' => DashboardController::MIN_GROUP])) . '</span>' : partial('meter', ['value' => percent($c['submitted'], $c['expected']), 'label' => fmt_number(percent($c['submitted'], $c['expected'])) . '%']) ?>
              <span class="bar-list__value"><?= $small ? '' : e(t(':n of :m, :t tasks', ['n' => (int) $c['submitted'], 'm' => (int) $c['expected'], 't' => (int) $c['tasks']])) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
        <p class="meta"><?= e(t('Totals by subject across all classes. Groups smaller than :n are hidden so individual students cannot be identified.', ['n' => DashboardController::MIN_GROUP])) ?></p>
      <?php endif; ?>
    </section>

    <section class="section" aria-labelledby="audit-title">
      <div class="section__head"><h2 class="section__title" id="audit-title"><?= e(t('Recent activity')) ?></h2><a class="section__link" href="<?= e(url('/admin/audit')) ?>"><?= e(t('Audit log')) ?></a></div>
      <?php if (!$audit): ?><p class="meta"><?= e(t('No activity recorded yet.')) ?></p><?php else: ?>
        <?= partial('audit_entries', ['rows' => $audit, 'targets' => AuditLabels::targets($audit, (int) $user['organization_id'])]) ?>
      <?php endif; ?>
    </section>
  </div>

  <aside class="stack">
    <section class="panel" aria-labelledby="ai-title">
      <h2 class="panel__title" id="ai-title"><?= e(t('AI this month')) ?></h2>
      <p class="meta" style="margin-top:-4px"><?= e($provider) ?></p>
      <dl class="dl">
        <dt><?= e(t('Requests')) ?></dt><dd class="num"><?= number_format((int) $usage['requests']) ?></dd>
        <dt><?= e(t('Tokens')) ?></dt><dd class="num"><?= number_format($tokens) ?></dd>
        <dt><?= e(t('Estimated cost')) ?></dt><dd class="num">$<?= e(number_format((float) $usage['cost'], 2)) ?></dd>
        <dt><?= e(t('Failed')) ?></dt><dd class="num"><?= (int) $usage['errors'] ?></dd>
      </dl>
      <?php if ($budget > 0): ?>
        <div style="margin-top: 12px"><?= partial('meter', ['value' => $budgetPct, 'label' => t(':pct% of the monthly budget', ['pct' => fmt_number($budgetPct ?? 0)])]) ?></div>
      <?php endif; ?>
      <a class="btn btn--quiet btn--sm" href="<?= e(url('/admin/usage')) ?>"><?= e(t('Usage details')) ?></a>
    </section>
    <section class="panel" aria-labelledby="cfg-title">
      <h2 class="panel__title" id="cfg-title"><?= e(t('Configuration to review')) ?></h2>
      <?php if (!$checks): ?><p class="meta"><?= e(t('No configuration warnings.')) ?></p><?php else: ?>
        <ul class="rows">
          <?php foreach (array_slice($checks, 0, 5) as $c): ?><li class="row" style="padding:8px 0"><span class="row__lead warn"><?= icon('triangle-alert', 'icon--sm') ?></span><div class="row__main"><span class="row__title" style="font-size:.9375rem"><?= e($c['label']) ?></span><div class="row__meta"><span><?= e($c['detail']) ?></span></div></div></li><?php endforeach; ?>
        </ul>
        <a class="btn btn--quiet btn--sm" href="<?= e(url('/admin/integrations')) ?>"><?= e(t('All checks and integrations')) ?></a>
      <?php endif; ?>
    </section>
  </aside>
</div>
