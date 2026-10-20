<?php
/** @var array|null $org @var array $privacy @var string $aiProvider */
$school = $org['name'] ?? t('your school');
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Privacy notice')) ?></h1>
    <p class="page-lead"><?= e(t('How :school uses Sibol, what information it keeps about you, and what you can do about it.', ['school' => $school])) ?></p>
  </div>
</div>

<?= partial('privacy_notice', ['org' => $org, 'privacy' => $privacy, 'aiProvider' => $aiProvider]) ?>

<div class="prose">
  <p><a href="<?= e(url('/rules')) ?>"><?= e(t('Rules for using Sibol')) ?></a></p>
  <p class="callout callout--amber" style="margin-top: 32px"><?= icon('info') ?><span><?= e(t('This notice describes how this installation of Sibol actually works. The school must review it with its data protection officer and legal adviser before using Sibol with real student information; technical safeguards alone do not make a school compliant.')) ?></span></p>
</div>
