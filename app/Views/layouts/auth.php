<?php
/**
 * Sign-in, invitation and password pages. The school photograph sits beside the page and the school
 * seal is pinned across the seam between them (across the photo's lower edge on phones).
 * Expects $title, $content.
 */
use Sibol\Core\Config;
use Sibol\Core\Session;
use Sibol\Core\Translator;

$flashes = Session::takeFlashes();
$locale = Translator::locale();
$school = (array) Config::get('school', []);
$schoolName = trim((string) ($school['name'] ?? '')) ?: (string) Config::get('app.name', 'B.E.S.T. Learning and Interactive Study System');
$motto = trim((string) ($school['motto'] ?? ''));
$founded = trim((string) ($school['founded'] ?? ''));
$photoAlt = trim((string) ($school['photo_alt'] ?? ''))
    ?: t('Students in green and white uniforms gather in the school\'s covered court as a speaker addresses them from the podium.');
?><!doctype html>
<html lang="<?= e($locale === 'fil' ? 'fil' : 'en') ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(($title ?? t('Sign in')) . ' – ' . Config::get('app.name', 'B.E.S.T. Learning and Interactive Study System')) ?></title>
  <meta name="description" content="<?= e(t('Sign in to B.E.S.T. Learning and Interactive Study System, the learning companion of :school.', ['school' => $schoolName])) ?>">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <meta name="app-base" content="<?= e(base_path_url()) ?>">
  <meta name="theme-color" content="#1E3D31">
  <link rel="manifest" href="<?= e(url('/manifest.webmanifest')) ?>" crossorigin="use-credentials">
  <link rel="icon" href="<?= e(asset('school/favicon-32.png')) ?>" type="image/png" sizes="32x32">
  <link rel="apple-touch-icon" href="<?= e(asset('school/apple-touch-icon.png')) ?>">
  <link rel="preload" href="<?= e(url('/assets/fonts/besley-latin-wght-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body data-i18n="<?= json_attr(\Sibol\Services\ScriptStrings::common()) ?>" data-signed-out>
<a class="skip-link" href="#main"><?= e(t('Skip to content')) ?></a>
<div class="auth">
  <div class="auth__photo">
    <picture>
      <source type="image/webp"
              srcset="<?= e(asset('school/campus-800.webp')) ?> 800w, <?= e(asset('school/campus-1280.webp')) ?> 1280w, <?= e(asset('school/campus-2000.webp')) ?> 2000w"
              sizes="(max-width: 899px) 100vw, max(58vw, 150vh)">
      <img src="<?= e(asset('school/campus-1280.jpg')) ?>" alt="<?= e($photoAlt) ?>" width="2000" height="1333" fetchpriority="high">
    </picture>
    <?php if ($motto !== '' || $founded !== ''): ?>
      <div class="auth__caption">
        <?php if ($motto !== ''): ?><p class="auth__motto">“<?= e($motto) ?>”</p><?php endif; ?>
        <?php if ($founded !== ''): ?><p class="auth__founded"><?= e(t(':school, founded :year', ['school' => $schoolName, 'year' => $founded])) ?></p><?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="auth__page">
    <nav class="lang-switch" aria-label="<?= e(t('Language')) ?>">
      <?php foreach ((array) Config::get('app.locales') as $code => $label): ?>
        <a href="?lang=<?= e($code) ?>" lang="<?= e($code) ?>"<?= $locale === $code ? ' aria-current="true"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <header class="auth__school">
      <?= partial('seal', ['size' => 112, 'class' => 'auth__seal']) ?>
      <p class="auth__school-name"><?= e($schoolName) ?></p>
      <?php if (!empty($school['location'])): ?><p class="auth__school-place"><?= e((string) $school['location']) ?></p><?php endif; ?>
    </header>
    <main class="auth__main" id="main" tabindex="-1">
      <?= $content ?>
    </main>
    <footer class="auth__foot">
      <a href="<?= e(url('/privacy')) ?>"><?= e(t('Privacy notice')) ?></a>
      <a href="<?= e(url('/help')) ?>"><?= e(t('Help')) ?></a>
    </footer>
  </div>
</div>
<?= partial('toasts', ['flashes' => $flashes]) ?>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
