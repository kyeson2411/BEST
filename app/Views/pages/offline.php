<?php
/**
 * What the service worker (public/sw.js) shows when a page cannot load. Stored on devices and the same for
 * everyone: the files kept on this device are listed by offline.js from the device itself.
 */
?>
<section class="offline" data-offline>
  <p class="offline__mark" aria-hidden="true"><?= icon('wifi-off', 'icon--lg') ?></p>
  <h1 class="page-title"><?= e(t('You are offline')) ?></h1>
  <p class="page-lead"><?= e(t('B.E.S.T. Learning and Interactive Study System needs a connection to open this page. Check your mobile data or Wi-Fi, then try again.')) ?></p>
  <p class="meta offline__note"><?= e(t('Quizzes from your teachers open only with a connection. Answers you already gave in a quiz stay in its tab until the connection is back.')) ?></p>
  <div class="cluster" style="margin-top: 24px">
    <a class="btn btn--primary" href="<?= e(url('/')) ?>" data-offline-retry><?= icon('refresh-cw', 'icon--sm') ?><?= e(t('Try again')) ?></a>
  </div>

  <section class="offline__kept" aria-labelledby="offline-kept-title">
    <h2 class="section__title" id="offline-kept-title"><?= e(t('Kept on this device')) ?></h2>
    <ul class="rows" data-offline-list hidden></ul>
    <p class="meta" data-offline-empty hidden><?= e(t('No files are kept on this device. On a class\'s Materials tab, choose Keep offline for the files you want to read without a connection.')) ?></p>
  </section>

  <section class="offline__kept" aria-labelledby="offline-packs-title" data-packs data-i18n="<?= json_attr(\Sibol\Services\ScriptStrings::practiceOffline()) ?>">
    <h2 class="section__title" id="offline-packs-title"><?= e(t('Practice kept on this device')) ?></h2>
    <ul class="rows" data-pack-list hidden></ul>
    <p class="meta" data-pack-empty><?= e(t('No practice is kept on this device. Open a practice set and choose Keep for offline practice.')) ?></p>
    <div data-pack-player hidden></div>
  </section>
</section>
