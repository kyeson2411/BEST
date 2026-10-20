<?php
/** Minimal page for signed-out errors and the local setup screen. Expects $title, $content. */
use Sibol\Core\Config;
use Sibol\Core\Session;
use Sibol\Core\Translator;

$flashes = session_status() === PHP_SESSION_ACTIVE ? Session::takeFlashes() : [];
?><!doctype html>
<html lang="<?= e(Translator::locale() === 'fil' ? 'fil' : 'en') ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(($title ?? 'Sibol') . ' – ' . Config::get('app.name', 'Sibol')) ?></title>
  <?php if (session_status() === PHP_SESSION_ACTIVE): ?><meta name="csrf-token" content="<?= e(csrf_token()) ?>"><?php endif; ?>
  <link rel="icon" href="<?= e(asset('school/favicon-32.png')) ?>" type="image/png" sizes="32x32">
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body data-i18n="<?= json_attr(\Sibol\Services\ScriptStrings::common()) ?>">
<a class="skip-link" href="#main"><?= e(t('Skip to content')) ?></a>
<div class="bare">
  <div class="bare__inner">
    <a class="bare__brand" href="<?= e(url('/')) ?>"><?= partial('seal', ['size' => 36, 'class' => 'brand__mark']) ?><span class="brand__name display"><?= e(Config::get('app.name', 'Sibol')) ?></span></a>
    <main id="main" tabindex="-1"><?= $content ?></main>
  </div>
</div>
<?= partial('toasts', ['flashes' => $flashes]) ?>
<?= partial('confirm_dialog') ?>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
