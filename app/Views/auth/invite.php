<?php /** @var array $invitee @var string $token @var string $school */ ?>
<h1 class="auth__title"><?= e(t('Set up your account')) ?></h1>
<p class="auth__sub"><?= e(t(':school invited you to Sibol as a :role.', ['school' => $school, 'role' => mb_strtolower(role_label((string) $invitee['role']))])) ?></p>

<dl class="dl" style="margin-bottom: 24px">
  <dt><?= e(t('Name')) ?></dt><dd><?= e($invitee['given_name'] . ' ' . $invitee['family_name']) ?></dd>
  <?php if (!empty($invitee['username'])): ?><dt><?= e(t('Username')) ?></dt><dd><strong class="code-text"><?= e($invitee['username']) ?></strong><span class="table-sub"><?= e(t('You will type this to sign in.')) ?></span></dd><?php endif; ?>
  <?php if (!empty($invitee['email'])): ?><dt><?= e(t('Email')) ?></dt><dd><?= e($invitee['email']) ?></dd><?php endif; ?>
</dl>

<form method="post" action="<?= e(url('/invite/' . $token)) ?>" novalidate>
  <?= csrf_field() ?>
  <?= field('text', 'preferred_name', t('Name you go by'), ['value' => (string) ($invitee['preferred_name'] ?? ''), 'hint' => t('Optional. Shown instead of your first name, for example a nickname.'), 'attrs' => ['autocomplete' => 'nickname', 'maxlength' => 80]]) ?>
  <?= field('select', 'language', t('Language'), ['value' => 'en', 'options' => ['en' => 'English', 'fil' => 'Filipino'], 'required' => true]) ?>
  <?= field('password', 'password', t('Create a password'), ['required' => true, 'hint' => t('At least 10 characters. A short phrase of a few words is easy to remember and hard to guess.'), 'attrs' => ['autocomplete' => 'new-password', 'minlength' => 10]]) ?>
  <?= field('password', 'password_confirmation', t('Type the password again'), ['required' => true, 'attrs' => ['autocomplete' => 'new-password']]) ?>
  <?php if (\Sibol\Services\Notices::required($invitee)): ?>
    <p class="meta"><?= e(t('Next, you will read the privacy notice and the rules for using Sibol and accept them.')) ?></p>
  <?php else: ?>
    <p class="meta"><?= e(t('By continuing you confirm you have read the')) ?> <a href="<?= e(url('/privacy')) ?>" target="_blank" rel="noopener"><?= e(t('privacy notice')) ?></a>.</p>
  <?php endif; ?>
  <button class="btn btn--primary btn--block" type="submit" data-busy="<?= e(t('Setting up…')) ?>"><?= e(t('Set up account')) ?></button>
</form>
