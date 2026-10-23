<?php
/**
 * Minimal page for signed-out errors, the local setup screen and the offline page. Expects $title, $content.
 * Optional: $scripts. With $offline_page the page is stored on devices for everyone, so it carries no messages
 * meant for one person and no form token.
 */
use Sibol\Core\Config;
use Sibol\Core\Session;
use Sibol\Core\Translator;

$personal = empty($offline_page) && session_status() === PHP_SESSION_ACTIVE;
$flashes = $personal ? Session::takeFlashes() : [];
?><!doctype html>
<html lang="<?= e(Translator::locale() === 'fil' ? 'fil' : 'en') ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(($title ?? Config::get('app.short_name', 'B.E.S.T.')) . ' – ' . Config::get('app.name', 'B.E.S.T. Learning and Interactive Study System')) ?></title>
  <?php if ($personal): ?><meta name="csrf-token" content="<?= e(csrf_token()) ?>"><?php endif; ?>
  <meta name="app-base" content="<?= e(base_path_url()) ?>">
  <meta name="theme-color" content="#1E3D31">
  <link rel="manifest" href="<?= e(url('/manifest.webmanifest')) ?>" crossorigin="use-credentials">
  <link rel="icon" href="<?= e(asset('school/favicon-32.png')) ?>" type="image/png" sizes="32x32">
  <?php /* The two faces these pages use; the service worker also stores them with the offline page. */ ?>
  <link rel="preload" href="<?= e(url('/assets/fonts/atkinson-hyperlegible-next-latin-wght-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
  <link rel="preload" href="<?= e(url('/assets/fonts/besley-latin-wght-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body data-i18n="<?= json_attr(\Sibol\Services\ScriptStrings::common()) ?>">
<a class="skip-link" href="#main"><?= e(t('Skip to content')) ?></a>
<div class="bare">
  <div class="bare__inner">
    <a class="bare__brand" href="<?= e(url('/')) ?>"><?= partial('seal', ['size' => 36, 'class' => 'brand__mark', 'single' => !empty($offline_page)]) ?><span class="brand__name display"><?= e(Config::get('app.short_name', 'B.E.S.T.')) ?></span></a>
    <main id="main" tabindex="-1"><?= $content ?></main>
  </div>
</div>
<?= partial('toasts', ['flashes' => $flashes]) ?>
<?= partial('confirm_dialog') ?>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<?php foreach (($scripts ?? []) as $script): ?>
<script src="<?= e(asset('js/' . $script)) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
