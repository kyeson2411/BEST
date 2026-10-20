<?php /** @var list<array> $created @var int $enrolled */ ?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Import complete')) ?></h1>
    <p class="page-lead"><?= e(tp(':count account created', ':count accounts created', count($created))) ?><?= $enrolled ? ', ' . e(tp(':count existing person added to a class', ':count existing people added to classes', $enrolled)) : '' ?>.</p>
  </div>
  <?php if ($created): ?>
    <div class="page-actions">
      <a class="btn btn--primary" href="<?= e(url('/slips')) ?>"><?= icon('printer', 'icon--sm') ?><?= e(t('Print sign-in slips')) ?></a>
      <a class="btn btn--secondary" href="<?= e(url('/admin/users/import/links')) ?>"><?= icon('download', 'icon--sm') ?><?= e(t('Download links as CSV')) ?></a>
    </div>
  <?php endif; ?>
</div>

<?php if ($created): ?>
  <div class="callout callout--amber" style="margin-bottom: 20px"><?= icon('key-round') ?><p><?= e(t('Each person gets a one-time code to set a password. It works once and expires in :days days. Print the slips, cut them apart and hand each one to its owner, or share each link privately. The slips and links are available here for the next 30 minutes; after that, print new ones from a class roster or a person\'s page.', ['days' => \Sibol\Services\Tokens::INVITE_DAYS])) ?></p></div>
  <div class="table-frame">
    <table class="table table--stack">
      <caption class="sr-only"><?= e(t('Invitation links')) ?></caption>
      <thead><tr><th scope="col"><?= e(t('Name')) ?></th><th scope="col"><?= e(t('Role')) ?></th><th scope="col"><?= e(t('Code')) ?></th><th scope="col"><?= e(t('Invitation link')) ?></th></tr></thead>
      <tbody>
        <?php foreach ($created as $i => $c): ?>
          <tr>
            <td class="cell-primary"><span class="row__title"><?= e($c['name']) ?></span><span class="table-sub code-text"><?= e($c['username']) ?></span><?php if ($c['email']): ?><span class="table-sub"><?= e($c['email']) ?></span><?php endif; ?></td>
            <td data-label="<?= e(t('Role')) ?>"><?= e(role_label($c['role'])) ?><?php if ($c['class']): ?><span class="table-sub"><?= e($c['class']) ?></span><?php endif; ?></td>
            <td data-label="<?= e(t('Code')) ?>"><span class="code-text nowrap"><?= e($c['code']) ?></span></td>
            <td data-label="<?= e(t('Invitation link')) ?>">
              <div class="copy-field"><label class="sr-only" for="link-<?= $i ?>"><?= e(t('Invitation link for :name', ['name' => $c['name']])) ?></label>
                <input class="input" id="link-<?= $i ?>" type="text" readonly value="<?= e($c['url']) ?>">
                <button type="button" class="btn btn--secondary btn--sm" data-copy="#link-<?= $i ?>" data-copied="<?= e(t('Link copied')) ?>"><?= icon('copy', 'icon--sm') ?><span class="sr-only"><?= e(t('Copy')) ?></span></button></div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<p style="margin-top: 24px"><a class="btn btn--secondary" href="<?= e(url('/admin/users', ['status' => 'invited'])) ?>"><?= e(t('See invited people')) ?></a></p>
