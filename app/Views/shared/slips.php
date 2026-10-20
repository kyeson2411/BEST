<?php
/**
 * Printable sign-in slips, grouped by section. Each slip carries the person's username, a one-time
 * code and the same code as a QR code of the link. Print styles put six slips on an A4 page with cut lines.
 * @var string $heading @var array<string,list<array>> $groups @var int $count @var string $back @var string $keptUntil
 */
use Sibol\Services\Qr;

$user = auth_user();
$school = (string) ($user['org_name'] ?? '');
$logo = !empty($user['org_logo_path']) ? url('/school-logo') : null;
$startUrl = (string) preg_replace('#^https?://#', '', absolute_url('/start'));
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Sign-in slips')) ?></h1>
    <p class="page-lead"><?= e(tp(':count slip for :who.', ':count slips for :who.', $count, ['who' => $heading])) ?> <?= e(t('Print them, cut along the dashed lines and hand each slip to its owner.')) ?></p>
  </div>
  <div class="page-actions">
    <button type="button" class="btn btn--primary" data-print><?= icon('printer', 'icon--sm') ?><?= e(t('Print slips')) ?></button>
    <a class="btn btn--secondary" href="<?= e(url($back)) ?>"><?= e(t('Done')) ?></a>
  </div>
</div>

<div class="callout callout--amber slips-note">
  <?= icon('key-round') ?>
  <p><?= e(t('Each code works once, and whoever holds a slip can set the password, so hand slips out in person and keep any you could not give out. Printing new slips for someone cancels their old one. This page shows the codes until :time.', ['time' => fmt_date($keptUntil, 'time')])) ?></p>
</div>

<?php foreach ($groups as $group => $slips): ?>
  <section class="slip-group" aria-label="<?= e($group !== '' ? $group : t('Not in a class yet')) ?>">
    <h2 class="slip-group__title"><?= e($group !== '' ? $group : t('Not in a class yet')) ?> <span class="count"><?= e(tp(':count slip', ':count slips', count($slips))) ?></span></h2>
    <div class="slip-sheet">
      <?php foreach ($slips as $s): $setup = $s['purpose'] === 'invitation'; ?>
        <article class="slip" aria-label="<?= e(t('Slip for :name', ['name' => $s['name']])) ?>">
          <header class="slip__head">
            <?= partial('seal', ['size' => 28, 'class' => 'slip__seal', 'logo' => $logo]) ?>
            <span class="slip__school"><?= e($school) ?></span>
            <?= icon('scissors', 'icon--sm slip__scissors') ?>
          </header>
          <p class="slip__kind"><?= e($setup ? t('Set up your Sibol account') : t('Choose a new Sibol password')) ?></p>
          <p class="slip__name"><?= e($s['name']) ?></p>
          <div class="slip__body">
            <dl class="slip__fields">
              <div><dt><?= e(t('Username')) ?></dt><dd class="code-text slip__username"><?= e($s['username']) ?></dd></div>
              <div><dt><?= e(t('Code')) ?></dt><dd class="code-text slip__code"><?= e($s['code']) ?></dd></div>
              <div><dt><?= e(t('Use by')) ?></dt><dd><?= e(fmt_date($s['expires_at'], 'datetime_short')) ?></dd></div>
            </dl>
            <?= Qr::encode($s['url'])->svg('slip__qr') ?>
          </div>
          <p class="slip__how"><?= e(t('Scan the square with a phone camera, or go to :site and type the code. Then choose your own password.', ['site' => $startUrl])) ?></p>
          <p class="slip__note"><?= e(t('Works once. Keep this slip private. Nobody at school ever sees your password.')) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>
