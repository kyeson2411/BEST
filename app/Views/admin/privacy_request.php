<?php /** @var array $request @var array $person @var array $counts @var list<array> $history @var bool $isSelf */
use Sibol\Services\AuditLabels;
use Sibol\Services\Privacy;

$base = '/admin/privacy/requests/' . (int) $request['id'];
$open = in_array($request['status'], ['submitted', 'in_review'], true);
$types = ['export' => t('Copy of their data'), 'correction' => t('Correction'), 'deletion' => t('Deletion')];
$statuses = ['submitted' => [t('New'), 'amber'], 'in_review' => [t('In review'), 'outline'], 'completed' => [t('Completed'), 'leaf'], 'rejected' => [t('Declined'), 'neutral']];
[$sLabel, $sTone] = $statuses[$request['status']];
$erased = $person['anonymized_at'] !== null;
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e($types[$request['request_type']]) ?></h1>
    <p class="page-lead"><?= e($erased ? t('Erased account') : user_name($person)) ?>, <?= e(lcfirst_u(role_label($person['role']))) ?>. <?= e(t('Received :date', ['date' => fmt_date($request['created_at'], 'datetime')])) ?> <?= badge($sLabel, $sTone) ?></p>
  </div>
  <?php if (!$erased): ?><div class="page-actions"><a class="btn btn--secondary" href="<?= e(url('/admin/users/' . (int) $person['id'])) ?>"><?= icon('user', 'icon--sm') ?><?= e(t('Account')) ?></a></div><?php endif; ?>
</div>

<div class="layout-split">
  <div class="stack">
    <section class="section" aria-labelledby="ask-title">
      <h2 class="section__title" id="ask-title"><?= e(t('What they asked')) ?></h2>
      <?php if ($request['details']): ?><blockquote class="quote-block"><?= nl2br(e($request['details'])) ?></blockquote><?php else: ?><p class="meta"><?= e(t('No details given.')) ?></p><?php endif; ?>
      <?php if ($request['resolution_note']): ?><p><strong><?= e(t('Note to the requester:')) ?></strong> <?= e($request['resolution_note']) ?></p><?php endif; ?>
    </section>

    <?php if ($open): ?>
      <?php if ($request['request_type'] === 'export'): ?>
        <section class="section" aria-labelledby="exp-title">
          <h2 class="section__title" id="exp-title"><?= e(t('Prepare their data')) ?></h2>
          <p><?= e(t('B.E.S.T. Learning and Interactive Study System builds a JSON file with their account, classes, submitted work, released grades, companion conversations, practice and study plans. Only the requester can download it, for :days days.', ['days' => Privacy::EXPORT_DAYS])) ?></p>
          <form method="post" action="<?= e(url($base . '/export')) ?>" data-confirm="<?= e(t('The file is created now and the requester is notified.')) ?>" data-confirm-title="<?= e(t('Prepare export?')) ?>" data-confirm-action="<?= e(t('Prepare export')) ?>">
            <?= csrf_field() ?><button class="btn btn--primary" type="submit" data-busy="<?= e(t('Preparing…')) ?>"><?= icon('download', 'icon--sm') ?><?= e(t('Prepare export and complete')) ?></button></form>
        </section>
      <?php elseif ($request['request_type'] === 'correction'): ?>
        <section class="section" aria-labelledby="cor-title">
          <h2 class="section__title" id="cor-title"><?= e(t('Make the correction')) ?></h2>
          <p><?= e(t('Account details can be changed on the person\'s account page. Grades and feedback are corrected by the class teacher; contact them if the request is about a grade.')) ?></p>
          <a class="btn btn--secondary" href="<?= e(url('/admin/users/' . (int) $person['id'])) ?>"><?= icon('pencil', 'icon--sm') ?><?= e(t('Edit account details')) ?></a>
        </section>
      <?php elseif ($request['request_type'] === 'deletion'): ?>
        <section class="section" aria-labelledby="del-title">
          <h2 class="section__title" id="del-title"><?= e(t('Erase personal data')) ?></h2>
          <p><?= e(t('Check your school\'s record-keeping rules first: schools often must keep grades for a set period. Erasing cannot be undone.')) ?></p>
          <?php if ($isSelf): ?>
            <p class="callout callout--amber"><?= icon('info') ?><span><?= e(t('This request is about your own account, so another administrator must handle it.')) ?></span></p>
          <?php else: ?>
            <form method="post" action="<?= e(url($base . '/erase')) ?>" data-confirm="<?= e(t('This permanently removes their personal data and cannot be undone.')) ?>" data-confirm-title="<?= e(t('Erase personal data?')) ?>" data-confirm-action="<?= e(t('Erase')) ?>" data-confirm-tone="danger">
              <?= csrf_field() ?>
              <ul class="plain-list">
                <li><?= e(tp(':count companion conversation', ':count companion conversations', (int) $counts['conversations'])) ?>, <?= e(tp(':count practice set', ':count practice sets', (int) $counts['practice'])) ?>, <?= e(tp(':count study session', ':count study sessions', (int) $counts['sessions'])) ?> <?= e(t('and :n shared excerpts are deleted.', ['n' => (int) $counts['shares']])) ?></li>
                <li><?= e(t('Name, username, email, photo and preferences are removed; the account becomes “Former :role” and can no longer sign in.', ['role' => lcfirst_u(role_label($person['role']))])) ?></li>
              </ul>
              <?= checkbox('include_records', tp('Also delete :count submission with its grades and files', 'Also delete :count submissions with their grades and files', (int) $counts['submissions']), false, t('Leave unticked to keep school records, which then no longer show who submitted them.')) ?>
              <?= field('textarea', 'resolution_note', t('Note for the record'), ['rows' => 2, 'optional_label' => true, 'attrs' => ['maxlength' => 2000]]) ?>
              <?= field('text', 'confirm_name', t('Type “:name” to confirm', ['name' => user_name($person)]), ['required' => true, 'attrs' => ['autocomplete' => 'off']]) ?>
              <button class="btn btn--danger" type="submit"><?= icon('trash-2', 'icon--sm') ?><?= e(t('Erase personal data')) ?></button>
            </form>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <section class="section" aria-labelledby="status-title">
        <h2 class="section__title" id="status-title"><?= e(t('Update status')) ?></h2>
        <form method="post" action="<?= e(url($base)) ?>" style="max-width: 560px">
          <?= csrf_field() ?>
          <?= field('select', 'status', t('Status'), ['value' => $request['status'] === 'submitted' ? 'in_review' : $request['status'], 'options' => ['in_review' => t('In review')] + ($request['request_type'] === 'export' ? [] : ['completed' => t('Completed')]) + ['rejected' => t('Declined')]]) ?>
          <?= field('textarea', 'resolution_note', t('Note to the requester'), ['value' => (string) $request['resolution_note'], 'rows' => 3, 'hint' => t('They see this in their account settings. Required when declining.'), 'attrs' => ['maxlength' => 2000]]) ?>
          <button class="btn btn--secondary" type="submit"><?= e(t('Save status')) ?></button>
        </form>
      </section>
    <?php elseif ($request['request_type'] === 'export' && $request['status'] === 'completed'): ?>
      <p class="meta"><?= $request['export_path'] ? e(t('Export available to the requester until :date.', ['date' => fmt_date($request['export_expires_at'], 'datetime')])) : e(t('The export file has expired and was removed.')) ?></p>
    <?php endif; ?>
  </div>

  <aside class="panel" aria-labelledby="hist-title">
    <h2 class="panel__title" id="hist-title"><?= e(t('History')) ?></h2>
    <?php if (!$history): ?><p class="meta"><?= e(t('No actions yet.')) ?></p><?php else: ?>
      <?= partial('audit_entries', ['rows' => $history, 'targets' => []]) ?>
    <?php endif; ?>
  </aside>
</div>
