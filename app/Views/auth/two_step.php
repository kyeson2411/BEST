<?php /** The code step after the password (TwoStepController::showChallenge). */ ?>
<h1 class="auth__title"><?= e(t('Enter your code')) ?></h1>
<p class="auth__sub"><?= e(t('Open the authenticator app on your phone and type the 6-digit code it shows for Sibol.')) ?></p>

<form method="post" action="<?= e(url('/login/code')) ?>" novalidate>
  <?= csrf_field() ?>
  <?= field('text', 'code', t('Code'), ['required' => true, 'hint' => t('No phone with you? Type one of your recovery codes instead.'), 'attrs' => ['autocomplete' => 'one-time-code', 'autofocus' => true, 'autocapitalize' => 'characters', 'spellcheck' => 'false', 'maxlength' => 20]]) ?>
  <button class="btn btn--primary btn--block" type="submit" data-busy="<?= e(t('Checking…')) ?>"><?= e(t('Continue')) ?></button>
</form>

<p class="auth__help">
  <?= e(t('Lost your phone and your recovery codes? Ask a school administrator to switch two-step sign-in off for your account.')) ?><br>
  <a href="<?= e(url('/login')) ?>"><?= e(t('Start again')) ?></a>
</p>
