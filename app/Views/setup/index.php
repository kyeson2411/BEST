<?php /** @var bool $dbOk @var list<string> $pending @var bool $hasData @var string|null $error @var list<array> $checks @var string $dbName */ ?>
<h1 class="page-title"><?= e(t('Set up B.E.S.T. Learning and Interactive Study System on this computer')) ?></h1>
<p class="page-lead"><?= e(t('This page appears only in local mode and only on this computer. It prepares the MySQL database and can load a fictional demo school.')) ?></p>

<section class="section" aria-labelledby="steps-title" style="margin-top: 32px">
  <div class="section__head"><h2 class="section__title" id="steps-title"><?= e(t('Steps')) ?></h2></div>
  <ol class="checklist">
    <li>
      <?= icon($dbOk && !$pending ? 'circle-check' : 'circle-dot', $dbOk && !$pending ? 'ok' : 'warn') ?>
      <div style="flex:1">
        <strong><?= e(t('Create the database tables')) ?></strong>
        <p class="meta" style="margin:2px 0 8px">
          <?php if (!$dbOk): ?>
            <?= e(t('The database ":name" does not exist yet. B.E.S.T. Learning and Interactive Study System can create it with the account in .env.', ['name' => $dbName])) ?>
          <?php elseif ($pending): ?>
            <?= e(t(':count migration file(s) are waiting.', ['count' => count($pending)])) ?>
          <?php else: ?>
            <?= e(t('All tables are in place.')) ?>
          <?php endif; ?>
        </p>
        <?php if (!$dbOk || $pending): ?>
          <form method="post" action="<?= e(url('/setup/migrate')) ?>"><?= csrf_field() ?>
            <button class="btn btn--primary" type="submit" data-busy="<?= e(t('Creating tables…')) ?>"><?= e(t('Create tables')) ?></button>
          </form>
        <?php endif; ?>
      </div>
    </li>
    <li>
      <?= icon($hasData ? 'circle-check' : 'circle-dot', $hasData ? 'ok' : 'warn') ?>
      <div style="flex:1">
        <strong><?= e(t('Load the demo school')) ?></strong>
        <p class="meta" style="margin:2px 0 8px"><?= e(t('One fictional school with teachers, students, classes, materials, assignments and feedback. No real people or records.')) ?></p>
        <?php if ($dbOk && !$pending && !$hasData): ?>
          <form method="post" action="<?= e(url('/setup/seed')) ?>"><?= csrf_field() ?>
            <button class="btn btn--primary" type="submit" data-busy="<?= e(t('Loading demo data…')) ?>"><?= e(t('Load demo data')) ?></button>
          </form>
        <?php elseif ($hasData): ?>
          <div class="cluster">
            <a class="btn btn--primary" href="<?= e(url('/login')) ?>"><?= e(t('Go to sign in')) ?></a>
            <form method="post" action="<?= e(url('/setup/reset')) ?>" data-confirm="<?= e(t('This deletes every record in the :name database and loads the demo school again.', ['name' => $dbName])) ?>" data-confirm-title="<?= e(t('Reset all data?')) ?>" data-confirm-action="<?= e(t('Reset demo data')) ?>" data-confirm-tone="danger">
              <?= csrf_field() ?>
              <button class="btn btn--danger-quiet" type="submit" data-busy="<?= e(t('Resetting…')) ?>"><?= e(t('Reset demo data')) ?></button>
            </form>
          </div>
        <?php endif; ?>
      </div>
    </li>
  </ol>
  <?php if ($error): ?>
    <div class="callout callout--coral"><?= icon('circle-alert') ?><p><?= e($error) ?></p></div>
  <?php endif; ?>
</section>

<section class="section" aria-labelledby="checks-title">
  <div class="section__head"><h2 class="section__title" id="checks-title"><?= e(t('Configuration check')) ?></h2></div>
  <ul class="checklist">
    <?php foreach ($checks as $check): ?>
      <li>
        <?= icon($check['ok'] ? 'circle-check' : 'triangle-alert', $check['ok'] ? 'ok' : 'warn') ?>
        <div><strong><?= e($check['label']) ?></strong><br><span class="meta"><?= e($check['detail']) ?></span></div>
      </li>
    <?php endforeach; ?>
  </ul>
  <p class="meta"><?= e(t('The same check runs from a terminal with: php bin/sibol.php check')) ?></p>
</section>
