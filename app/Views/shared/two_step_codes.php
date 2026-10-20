<?php /** @var list<string> $codes */ ?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Your recovery codes')) ?></h1>
    <p class="page-lead"><?= e(t('If you cannot use your phone, type one of these instead of a code from the app. Each works once. They are shown only now: print them or write them down, and keep them somewhere safe.')) ?></p>
  </div>
</div>

<div class="stack" style="max-width: 760px">
  <pre class="recovery-codes code-text" id="recovery-codes"><?= e(implode("\n", $codes)) ?></pre>
  <div class="page-actions">
    <button type="button" class="btn btn--secondary" data-copy="#recovery-codes"><?= icon('copy', 'icon--sm') ?><?= e(t('Copy codes')) ?></button>
    <button type="button" class="btn btn--secondary" data-print><?= icon('printer', 'icon--sm') ?><?= e(t('Print')) ?></button>
    <a class="btn btn--primary" href="<?= e(url('/settings#two-step')) ?>"><?= e(t('I have kept them')) ?></a>
  </div>
</div>
