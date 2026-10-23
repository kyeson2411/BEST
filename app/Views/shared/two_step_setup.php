<?php /** @var string $qr @var string $uri @var string $key @var string $account @var string $issuer */ ?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Set up two-step sign-in')) ?></h1>
    <p class="page-lead"><?= e(t('After your password, B.E.S.T. Learning and Interactive Study System will also ask for a code from an app on your phone. Someone who learns your password still cannot sign in as you.')) ?></p>
  </div>
</div>

<div class="stack" style="max-width: 760px">
  <section class="section" aria-labelledby="ts-app">
    <div class="section__head"><h2 class="section__title" id="ts-app"><?= e(t('1. Install an authenticator app')) ?></h2></div>
    <p><?= e(t('Any authenticator app works, for example Google Authenticator or Microsoft Authenticator. They are free, and they work without mobile data.')) ?></p>
  </section>

  <section class="section" aria-labelledby="ts-scan">
    <div class="section__head"><h2 class="section__title" id="ts-scan"><?= e(t('2. Add B.E.S.T. Learning and Interactive Study System to the app')) ?></h2></div>
    <div class="two-step">
      <div class="two-step__code"><?= $qr ?></div>
      <div>
        <p><?= e(t('In the app, choose to add an account and scan this QR code.')) ?></p>
        <p class="meta"><?= e(t('Cannot scan it? Choose to enter a setup key instead, and type:')) ?></p>
        <p class="two-step__key code-text" id="two-step-key"><?= e($key) ?></p>
        <div class="cluster">
          <button type="button" class="btn btn--secondary btn--sm" data-copy="#two-step-key"><?= icon('copy', 'icon--sm') ?><?= e(t('Copy key')) ?></button>
          <?php /* Authenticator apps open otpauth: links, for people setting this up on the phone itself. */ ?>
          <a class="btn btn--quiet btn--sm" href="<?= e($uri) ?>"><?= e(t('Open in the app on this phone')) ?></a>
        </div>
        <dl class="dl" style="margin-top: 16px">
          <dt><?= e(t('Account')) ?></dt><dd><?= e($issuer) ?>: <?= e($account) ?></dd>
          <dt><?= e(t('Type')) ?></dt><dd><?= e(t('Time-based, 6 digits')) ?></dd>
        </dl>
      </div>
    </div>
  </section>

  <section class="section" aria-labelledby="ts-confirm">
    <div class="section__head"><h2 class="section__title" id="ts-confirm"><?= e(t('3. Type the code the app shows')) ?></h2></div>
    <form method="post" action="<?= e(url('/settings/two-step')) ?>" autocomplete="off" novalidate>
      <?= csrf_field() ?>
      <div class="form-grid">
        <?= field('text', 'code', t('Code from the app'), ['required' => true, 'hint' => t('6 digits. A new one appears every 30 seconds.'), 'attrs' => ['autocomplete' => 'one-time-code', 'inputmode' => 'numeric', 'maxlength' => 8]]) ?>
        <?= field('password', 'two_step_password', t('Current password'), ['required' => true, 'attrs' => ['autocomplete' => 'current-password']]) ?>
      </div>
      <button class="btn btn--primary" type="submit"><?= e(t('Turn on two-step sign-in')) ?></button>
      <a class="btn btn--quiet" href="<?= e(url('/settings#two-step')) ?>"><?= e(t('Cancel')) ?></a>
    </form>
  </section>
</div>
