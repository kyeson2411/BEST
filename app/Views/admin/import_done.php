<?php /** @var list<array> $created @var int $enrolled */ ?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Import complete')) ?></h1>
    <p class="page-lead"><?= e(tp(':count account created', ':count accounts created', count($created))) ?><?= $enrolled ? ', ' . e(tp(':count existing person added to a class', ':count existing people added to classes', $enrolled)) : '' ?>.</p>
  </div>
  <?php if ($created): ?>
    <div class="page-actions"><a class="btn btn--primary" href="<?= e(url('/admin/users/import/links')) ?>"><?= icon('download', 'icon--sm') ?><?= e(t('Download links as CSV')) ?></a></div>
  <?php endif; ?>
</div>

<?php if ($created): ?>
  <div class="callout callout--amber" style="margin-bottom: 20px"><?= icon('key-round') ?><p><?= e(t('Each link lets one person set a password, works once, and expires in :days days. Share each link only with its owner. You can download them for the next 30 minutes; after that, create new links from each person\'s page.', ['days' => \Sibol\Services\Tokens::INVITE_DAYS])) ?></p></div>
  <div class="table-frame">
    <table class="table table--stack">
      <caption class="sr-only"><?= e(t('Invitation links')) ?></caption>
      <thead><tr><th scope="col"><?= e(t('Name')) ?></th><th scope="col"><?= e(t('Role')) ?></th><th scope="col"><?= e(t('Invitation link')) ?></th></tr></thead>
      <tbody>
        <?php foreach ($created as $i => $c): ?>
          <tr>
            <td class="cell-primary"><span class="row__title"><?= e($c['name']) ?></span><span class="table-sub"><?= e($c['email']) ?></span></td>
            <td data-label="<?= e(t('Role')) ?>"><?= e(role_label($c['role'])) ?><?php if ($c['class']): ?><span class="table-sub"><?= e($c['class']) ?></span><?php endif; ?></td>
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
