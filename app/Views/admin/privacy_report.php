<?php /** @var array $report */
use Sibol\Services\Settings;

$reasons = ['incorrect' => t('Incorrect'), 'unsafe' => t('Unsafe or inappropriate'), 'not_helpful' => t('Not helpful'), 'privacy' => t('Privacy concern'), 'other' => t('Other')];
$open = $report['status'] === 'open';
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Reported answer: :reason', ['reason' => lcfirst_u($reasons[$report['reason']] ?? $report['reason'])])) ?></h1>
    <p class="page-lead"><?= e(t('Reported :date by :name (:role).', ['date' => fmt_date($report['created_at'], 'datetime'), 'name' => $report['reporter_id'] ? user_name($report) : t('a former user'), 'role' => lcfirst_u(role_label((string) $report['reporter_role']))])) ?></p>
  </div>
</div>

<div class="layout-split">
  <div class="stack">
    <section class="section" aria-labelledby="ans-title">
      <div class="section__head"><h2 class="section__title" id="ans-title"><?= e(t('The reported answer')) ?></h2>
        <span class="meta"><?= $report['mode'] ? e(Settings::modeLabel((string) $report['mode'])) : '' ?><?= $report['class_name'] ? ', ' . e($report['class_name']) : '' ?></span></div>
      <?php if ($report['answer_excerpt']): ?>
        <div class="report-answer prose"><?= markdown((string) $report['answer_excerpt']) ?></div>
      <?php else: ?>
        <p class="meta"><?= e(t('The answer is no longer available.')) ?></p>
      <?php endif; ?>
      <?php if ($report['citations']): ?>
        <p class="meta"><?= e(t('Sources cited:')) ?> <?php foreach ($report['citations'] as $i => $c): ?><?= $i ? '; ' : '' ?>[<?= (int) $c['ref_no'] ?>] <?= e($c['title']) ?><?= $c['page_from'] ? ', ' . e(t('p. :n', ['n' => (int) $c['page_from']])) : '' ?><?php endforeach; ?></p>
      <?php endif; ?>
      <p class="meta"><?= e($report['is_demo'] ? t('Answered in demo mode (no AI model).') : t('Answered by :model.', ['model' => (string) $report['model']])) ?>
        <?= e(t('Only the reported answer is shown here; the student\'s question and the rest of the conversation stay private.')) ?></p>
    </section>
    <?php if ($report['note']): ?>
      <section class="section" aria-labelledby="note-title">
        <h2 class="section__title" id="note-title"><?= e(t('Reporter\'s note')) ?></h2>
        <blockquote class="quote-block"><?= nl2br(e($report['note'])) ?></blockquote>
      </section>
    <?php endif; ?>
  </div>
  <aside class="panel" aria-labelledby="out-title">
    <h2 class="panel__title" id="out-title"><?= e(t('Outcome')) ?></h2>
    <?php if ($open): ?>
      <form method="post" action="<?= e(url('/admin/privacy/reports/' . (int) $report['id'])) ?>">
        <?= csrf_field() ?>
        <div class="choice-list" style="margin-bottom: 16px">
          <label class="choice"><input type="radio" name="status" value="resolved" checked><span><span class="choice__title"><?= e(t('Resolved')) ?></span><span class="choice__desc"><?= e(t('You acted on it, for example by asking a teacher to correct a material or tightening a policy.')) ?></span></span></label>
          <label class="choice"><input type="radio" name="status" value="dismissed"><span><span class="choice__title"><?= e(t('Dismissed')) ?></span><span class="choice__desc"><?= e(t('The answer was acceptable on review.')) ?></span></span></label>
        </div>
        <?= field('textarea', 'resolution_note', t('Message to the reporter'), ['rows' => 3, 'optional_label' => true, 'hint' => t('Sent as a notification.'), 'attrs' => ['maxlength' => 500]]) ?>
        <button class="btn btn--primary" type="submit"><?= e(t('Close report')) ?></button>
      </form>
    <?php else: ?>
      <p><?= badge($report['status'] === 'resolved' ? t('Resolved') : t('Dismissed'), 'neutral') ?></p>
      <p class="meta"><?= e(t('Closed :date by :name.', ['date' => fmt_date($report['reviewed_at'], 'datetime'), 'name' => $report['reviewed_by'] ? trim(($report['rv_preferred'] ?: $report['rv_given']) . ' ' . $report['rv_family']) : t('an administrator')])) ?></p>
      <?php if ($report['resolution_note']): ?><p><?= e($report['resolution_note']) ?></p><?php endif; ?>
    <?php endif; ?>
  </aside>
</div>
