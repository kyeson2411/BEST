<?php /** @var list<array<string,mixed>> $demoAccounts */ ?>
<h1 class="auth__title"><?= e(t('Sign in to Sibol')) ?></h1>
<p class="auth__sub"><?= e(t('A learning companion that helps you understand, not just answer.')) ?></p>

<form method="post" action="<?= e(url('/login')) ?>" novalidate>
  <?= csrf_field() ?>
  <?= field('text', 'login', t('Username or email'), ['required' => true, 'hint' => t('Use the username on your sign-in slip. Teachers can also use their email address.'), 'attrs' => ['autocomplete' => 'username', 'autofocus' => true, 'autocapitalize' => 'none', 'spellcheck' => 'false', 'maxlength' => 190]]) ?>
  <?= field('password', 'password', t('Password'), ['required' => true, 'attrs' => ['autocomplete' => 'current-password']]) ?>
  <button class="btn btn--primary btn--block" type="submit" data-busy="<?= e(t('Signing in…')) ?>"><?= e(t('Sign in')) ?></button>
</form>

<?php if (\Sibol\Services\GoogleSignIn::configured()): ?>
  <p class="auth__or"><span><?= e(t('or')) ?></span></p>
  <a class="btn btn--secondary btn--block" href="<?= e(url('/auth/google')) ?>"><?= icon('school', 'icon--sm') ?><?= e(t('Sign in with your school Google account')) ?></a>
<?php endif; ?>

<p class="auth__help">
  <a href="<?= e(url('/start')) ?>"><?= e(t('Have a sign-in slip? Type its code')) ?></a><br>
  <?= e(t('Forgot your password? Ask your teacher, adviser or school administrator for a new sign-in slip.')) ?>
</p>

<ul class="auth__notes">
  <li><?= icon('book-open', 'icon--sm') ?><span><?= e(t('Your teachers decide what is published and what counts as a grade.')) ?></span></li>
  <li><?= icon('quote', 'icon--sm') ?><span><?= e(t('When the companion uses class materials, it shows exactly where each point came from.')) ?></span></li>
  <li><?= icon('lock', 'icon--sm') ?><span><?= e(t('Your conversations with the companion are private to you.')) ?></span></li>
</ul>

<?php if ($demoAccounts): ?>
  <section class="demo-logins" aria-labelledby="demo-title">
    <h2 class="demo-logins__title" id="demo-title"><?= e(t('Explore the demo school')) ?></h2>
    <p class="demo-logins__note"><?= e(t('Fictional accounts, available only on this computer while the app is in local mode.')) ?></p>
    <div class="demo-logins__list">
      <?php foreach ($demoAccounts as $account): ?>
        <form method="post" action="<?= e(url('/demo-login')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="user_id" value="<?= (int) $account['id'] ?>">
          <button class="btn btn--secondary btn--block" type="submit" data-busy="<?= e(t('Opening…')) ?>">
            <?= e(role_label((string) $account['role'])) ?>
          </button>
          <span class="meta" style="display:block; text-align:center; margin-top:4px"><?= e(user_name($account)) ?></span>
        </form>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>
