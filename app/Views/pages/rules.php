<?php /** @var string|null $role @var array $privacy @var string $version */ ?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Rules for using Sibol')) ?></h1>
    <p class="page-lead"><?= e(t('Using Sibol honestly and safely, and what the AI companion will and will not do for you.')) ?></p>
  </div>
</div>
<?= partial('rules_of_use', ['role' => $role, 'privacy' => $privacy]) ?>
<div class="prose">
  <p class="meta"><?= e(t('Version :n', ['n' => $version])) ?> · <a href="<?= e(url('/privacy')) ?>"><?= e(t('Privacy notice')) ?></a></p>
</div>
