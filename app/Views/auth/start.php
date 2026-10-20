<h1 class="auth__title"><?= e(t('Use a sign-in slip')) ?></h1>
<p class="auth__sub"><?= e(t('Your teacher or adviser gave you a slip with your username and a code. Scan its square code with a phone camera, or type the code here.')) ?></p>

<form method="post" action="<?= e(url('/start')) ?>" novalidate>
  <?= csrf_field() ?>
  <?= field('text', 'code', t('Code from your slip'), ['required' => true, 'hint' => t('12 letters and numbers, for example ABCD-EFGH-JKMN. Capital or small letters both work.'), 'class' => 'input--code', 'attrs' => ['autocomplete' => 'one-time-code', 'autofocus' => true, 'autocapitalize' => 'characters', 'spellcheck' => 'false', 'maxlength' => 20]]) ?>
  <button class="btn btn--primary btn--block" type="submit" data-busy="<?= e(t('Checking…')) ?>"><?= e(t('Continue')) ?></button>
</form>

<p class="auth__help"><?= e(t('Each code works once. If yours does not work, ask for a new slip; the old one stops working.')) ?></p>
<p class="auth__help"><a href="<?= e(url('/login')) ?>"><?= e(t('Back to sign in')) ?></a></p>
