<?php /** @var string $show @var list<array> $requests @var list<array> $reports @var int $retention */
use Sibol\Services\Settings;

$types = ['export' => t('Copy of their data'), 'correction' => t('Correction'), 'deletion' => t('Deletion')];
$statuses = ['submitted' => [t('New'), 'amber'], 'in_review' => [t('In review'), 'outline'], 'completed' => [t('Completed'), 'leaf'], 'rejected' => [t('Declined'), 'neutral']];
$reasons = ['incorrect' => t('Incorrect'), 'unsafe' => t('Unsafe or inappropriate'), 'not_helpful' => t('Not helpful'), 'privacy' => t('Privacy concern'), 'other' => t('Other')];
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Privacy and safety')) ?></h1>
    <p class="page-lead"><?= e(t('Requests from people about their data, and companion answers someone reported. Conversations are deleted after :days days without activity.', ['days' => $retention ?: '∞'])) ?>
      <a href="<?= e(url('/admin/policies#privacy')) ?>"><?= e(t('Change retention')) ?></a></p>
  </div>
</div>

<nav class="tabs" aria-label="<?= e(t('Show')) ?>">
  <a class="tab" href="<?= e(url('/admin/privacy')) ?>"<?= $show === 'open' ? ' aria-current="page"' : '' ?>><?= e(t('Open')) ?></a>
  <a class="tab" href="<?= e(url('/admin/privacy', ['show' => 'closed'])) ?>"<?= $show === 'closed' ? ' aria-current="page"' : '' ?>><?= e(t('Closed')) ?></a>
</nav>

<div class="layout-split">
  <div class="stack">
    <section class="section" aria-labelledby="req-title">
      <div class="section__head"><h2 class="section__title" id="req-title"><?= e(t('Privacy requests')) ?></h2></div>
      <?php if (!$requests): ?>
        <p class="meta"><?= e($show === 'open' ? t('No open requests. People can ask for a copy of their data, a correction or deletion from their account settings.') : t('No closed requests yet.')) ?></p>
      <?php else: ?>
        <ul class="entries">
          <?php foreach ($requests as $r): [$sLabel, $sTone] = $statuses[$r['status']]; ?>
            <li class="entry">
              <?= partial('entry_date', ['at' => $r['created_at']]) ?>
              <div class="entry__main">
                <a class="row__title" href="<?= e(url('/admin/privacy/requests/' . (int) $r['id'])) ?>"><?= e($types[$r['request_type']]) ?>: <?= e($r['anonymized_at'] ? t('erased account') : user_name($r)) ?></a>
                <div class="row__meta"><span><?= e(role_label($r['role'])) ?></span><span><?= e(t('Received :when', ['when' => fmt_rel($r['created_at'])])) ?></span></div>
              </div>
              <div class="entry__aside"><?= badge($sLabel, $sTone) ?></div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="section" id="reports" aria-labelledby="rep-title">
      <div class="section__head"><h2 class="section__title" id="rep-title"><?= e(t('Reported companion answers')) ?></h2></div>
      <?php if (!$reports): ?>
        <p class="meta"><?= e($show === 'open' ? t('No open reports. Students and teachers can report any companion answer; only the reported answer is shared with you.') : t('No closed reports yet.')) ?></p>
      <?php else: ?>
        <ul class="entries">
          <?php foreach ($reports as $r): ?>
            <li class="entry">
              <?= partial('entry_date', ['at' => $r['created_at']]) ?>
              <div class="entry__main">
                <a class="row__title" href="<?= e(url('/admin/privacy/reports/' . (int) $r['id'])) ?>"><?= e($reasons[$r['reason']] ?? $r['reason']) ?></a>
                <div class="row__meta"><span><?= e(t('Reported by a :role', ['role' => lcfirst_u(role_label((string) $r['reporter_role']))])) ?></span><?php if ($r['mode']): ?><span><?= e(Settings::modeLabel((string) $r['mode'])) ?></span><?php endif; ?></div>
              </div>
              <div class="entry__aside"><?= $r['status'] === 'open' ? badge(t('Open'), 'amber') : badge($r['status'] === 'resolved' ? t('Resolved') : t('Dismissed'), 'neutral') ?></div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>
  <aside class="panel panel--sunken">
    <h2 class="panel__title"><?= e(t('What administrators can see')) ?></h2>
    <ul class="plain-list">
      <li><?= e(t('Accounts, classes, and school-wide totals.')) ?></li>
      <li><?= e(t('A companion answer only when someone reports it, without the rest of the conversation.')) ?></li>
      <li><?= e(t('Never: students\' private conversations, practice or study plans.')) ?></li>
    </ul>
    <p class="meta"><?= e(t('Every step you take on a request is recorded in the audit log.')) ?></p>
  </aside>
</div>
