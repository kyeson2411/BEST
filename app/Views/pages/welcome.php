<?php
/**
 * "Before you start": the privacy notice and the rules for using Sibol, accepted on first sign-in and again
 * whenever either changes.
 * @var list<string> $pending @var array<string,string> $versions @var bool $renewed @var array|null $org
 * @var array $privacy @var string $aiProvider @var string $role
 */
use Sibol\Core\Config;
use Sibol\Core\Translator;

$school = $org['name'] ?? t('your school');
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Before you start')) ?></h1>
    <p class="page-lead"><?= e($renewed
        ? t(':school has updated how it uses Sibol. Read the parts below again and accept them to continue.', ['school' => $school])
        : t('Read how :school uses Sibol and the rules for using it, then accept them to continue. You only do this again when they change.', ['school' => $school])) ?></p>
  </div>
  <div class="page-actions">
    <?php foreach ((array) Config::get('app.locales') as $code => $label): if ($code === Translator::locale()) { continue; } ?>
      <form method="post" action="<?= e(url('/welcome/language')) ?>">
        <?= csrf_field() ?><input type="hidden" name="language" value="<?= e($code) ?>">
        <button class="btn btn--quiet" type="submit" lang="<?= e($code) ?>"><?= icon('languages', 'icon--sm') ?><?= e(t('Read in :language', ['language' => $label])) ?></button>
      </form>
    <?php endforeach; ?>
  </div>
</div>

<form method="post" action="<?= e(url('/welcome')) ?>" class="welcome">
  <?= csrf_field() ?>
  <?php if (in_array('privacy', $pending, true)): ?>
    <section class="welcome__part">
      <h2 class="welcome__title" id="privacy-title"><?= icon('shield-check') ?> <?= e(t('Privacy notice')) ?> <span class="meta"><?= e(t('Version :n', ['n' => $versions['privacy']])) ?></span></h2>
      <div class="welcome__text" tabindex="0" role="region" aria-labelledby="privacy-title"><?= partial('privacy_notice', ['org' => $org, 'privacy' => $privacy, 'aiProvider' => $aiProvider, 'headingTag' => 'h3']) ?></div>
      <div class="field<?= field_error('privacy') ? ' field--error' : '' ?>">
        <?= checkbox('privacy', t('I have read the privacy notice'), false, null, '1', 'accept-privacy') ?>
        <?php if (field_error('privacy')): ?><p class="field__error"><?= icon('circle-alert', 'icon--xs') ?><?= e(field_error('privacy')) ?></p><?php endif; ?>
      </div>
    </section>
  <?php endif; ?>
  <?php if (in_array('rules', $pending, true)): ?>
    <section class="welcome__part">
      <h2 class="welcome__title" id="rules-title"><?= icon('book-open') ?> <?= e(t('Rules for using Sibol')) ?> <span class="meta"><?= e(t('Version :n', ['n' => $versions['rules']])) ?></span></h2>
      <div class="welcome__text" tabindex="0" role="region" aria-labelledby="rules-title"><?= partial('rules_of_use', ['role' => $role, 'privacy' => $privacy, 'headingTag' => 'h3']) ?></div>
      <div class="field<?= field_error('rules') ? ' field--error' : '' ?>">
        <?= checkbox('rules', t('I will follow the rules for using Sibol'), false, null, '1', 'accept-rules') ?>
        <?php if (field_error('rules')): ?><p class="field__error"><?= icon('circle-alert', 'icon--xs') ?><?= e(field_error('rules')) ?></p><?php endif; ?>
      </div>
    </section>
  <?php endif; ?>
  <div class="form-actions">
    <button class="btn btn--primary" type="submit" data-busy="<?= e(t('Saving…')) ?>"><?= e(t('Accept and continue')) ?></button>
    <span class="meta"><?= e(t('Sibol records which version you accepted and when.')) ?></span>
  </div>
</form>
<form method="post" action="<?= e(url('/logout')) ?>" data-signout style="margin-top: 16px">
  <?= csrf_field() ?><button class="btn btn--quiet btn--sm" type="submit"><?= icon('log-out', 'icon--sm') ?><?= e(t('Sign out')) ?></button>
</form>
