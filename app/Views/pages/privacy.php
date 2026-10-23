<?php
/** @var array|null $org @var array $privacy @var string $aiProvider */
$school = $org['name'] ?? t('your school');
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Privacy notice')) ?></h1>
    <p class="page-lead"><?= e(t('How :school uses B.E.S.T. Learning and Interactive Study System, what information it keeps about you, and what you can do about it.', ['school' => $school])) ?></p>
  </div>
</div>

<?= partial('privacy_notice', ['org' => $org, 'privacy' => $privacy, 'aiProvider' => $aiProvider]) ?>

<div class="prose">
  <p><a href="<?= e(url('/rules')) ?>"><?= e(t('Rules for using B.E.S.T. Learning and Interactive Study System')) ?></a></p>
  <p class="callout callout--amber" style="margin-top: 32px"><?= icon('info') ?><span><?= e(t('This notice describes how this installation of B.E.S.T. Learning and Interactive Study System actually works. The school must review it with its data protection officer and legal adviser before using B.E.S.T. Learning and Interactive Study System with real student information; technical safeguards alone do not make a school compliant.')) ?></span></p>
</div>
