<?php
/** One-time display of an invitation or reset link. @var array $reveal {purpose,url,expires} @var array $person */
$invite = $reveal['purpose'] === 'invitation';
?>
<section class="callout callout--leaf" aria-labelledby="reveal-title" style="margin-bottom: 24px; display:block">
  <h2 class="callout__title" id="reveal-title"><?= icon($invite ? 'mail' : 'key-round', 'icon--sm') ?> <?= e($invite ? t('Invitation link for :name', ['name' => user_name($person)]) : t('Password reset link for :name', ['name' => user_name($person)])) ?></h2>
  <p><?= e(t('Copy this link now and send it to them through your school\'s usual channel. Sibol does not send email, and this link will not be shown again.')) ?></p>
  <div class="copy-field">
    <label class="sr-only" for="reveal-url"><?= e(t('Link')) ?></label>
    <input class="input" id="reveal-url" type="text" readonly value="<?= e($reveal['url']) ?>">
    <button type="button" class="btn btn--primary" data-copy="#reveal-url" data-copied="<?= e(t('Link copied')) ?>"><?= icon('copy', 'icon--sm') ?><?= e(t('Copy link')) ?></button>
  </div>
  <p class="meta" style="margin-top: 8px"><?= e(t('Works once and expires in :time. Creating a new link cancels this one.', ['time' => $reveal['expires']])) ?></p>
</section>
