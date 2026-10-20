<?php /** @var array $person @var string $token */ ?>
<h1 class="auth__title"><?= e(t('Choose a new password')) ?></h1>
<p class="auth__sub"><?= e(t('For :name. Changing it signs you out on other devices.', ['name' => user_name($person)])) ?></p>
<?php if (!empty($person['username'])): ?>
  <dl class="dl" style="margin-bottom: 24px"><dt><?= e(t('Username')) ?></dt><dd><strong class="code-text"><?= e($person['username']) ?></strong></dd></dl>
<?php endif; ?>
<form method="post" action="<?= e(url('/reset/' . $token)) ?>" novalidate>
  <?= csrf_field() ?>
  <?= field('password', 'password', t('New password'), ['required' => true, 'hint' => t('At least 10 characters.'), 'attrs' => ['autocomplete' => 'new-password', 'minlength' => 10]]) ?>
  <?= field('password', 'password_confirmation', t('Type the password again'), ['required' => true, 'attrs' => ['autocomplete' => 'new-password']]) ?>
  <button class="btn btn--primary btn--block" type="submit" data-busy="<?= e(t('Saving…')) ?>"><?= e(t('Save password')) ?></button>
</form>
