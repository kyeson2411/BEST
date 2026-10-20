<?php /** @var int $status @var string $message @var string|null $detail @var string $title */ ?>
<section class="error-page">
  <p class="error-page__code" aria-hidden="true"><?= (int) $status ?></p>
  <h1 class="page-title"><?= e($title) ?></h1>
  <p class="page-lead">
    <?php if ($message !== ''): ?>
      <?= e($message) ?>
    <?php elseif ($status === 404): ?>
      <?= e(t('The page may have moved, or the address may be mistyped. If you followed a link inside Sibol, the item may have been archived or you may not have access to it.')) ?>
    <?php elseif ($status === 403): ?>
      <?= e(t('Your account does not include this area. If you think you should have access, ask your school administrator.')) ?>
    <?php else: ?>
      <?= e(t('Try again in a moment.')) ?>
    <?php endif; ?>
  </p>
  <div class="cluster" style="margin-top: 24px">
    <a class="btn btn--primary" href="<?= e(url(home_path())) ?>"><?= e(auth_user() ? t('Go to your home page') : t('Go to sign in')) ?></a>
    <?php if ($status === 419): ?>
      <button type="button" class="btn btn--secondary" data-history-back><?= e(t('Go back')) ?></button>
    <?php endif; ?>
  </div>
  <?php if (!empty($detail)): ?>
    <details class="how" open>
      <summary><?= e(t('Developer details (shown only when APP_ENV=local)')) ?></summary>
      <div><pre class="debug"><?= e($detail) ?></pre></div>
    </details>
  <?php endif; ?>
</section>
