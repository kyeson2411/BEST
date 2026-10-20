<?php
/**
 * Signed-in application shell.
 * Expects: $title, $content. Optional: $crumbs (list of [label, url|null]), $page_class, $scripts (list of js files), $katex (bool)
 */
use Sibol\Core\Config;
use Sibol\Core\Session;
use Sibol\Core\Translator;
use Sibol\Services\Navigation;
use Sibol\Services\ScriptStrings;

$user = auth_user();
$items = $user ? Navigation::items($user) : [];
$unread = $user ? Navigation::unreadNotifications((int) $user['id']) : 0;
$flashes = Session::takeFlashes();
$prefs = $user ? (json_decode((string) ($user['preferences'] ?? ''), true) ?: []) : [];
$htmlClass = [];
if (!empty($prefs['large_text'])) { $htmlClass[] = 'pref-large-text'; }
if (!empty($prefs['reduce_motion'])) { $htmlClass[] = 'pref-reduce-motion'; }
if (!empty($prefs['underline_links'])) { $htmlClass[] = 'pref-underline-links'; }
$isDemoOrg = $user && !empty($user['org_is_demo']);
// A logo uploaded in School settings replaces the default school seal.
$schoolLogo = $user && !empty($user['org_logo_path']) ? url('/school-logo') : null;
$aiDemo = Config::get('ai.provider', 'demo') === 'demo';
$bottomItems = array_values(array_filter($items, static fn ($i) => !empty($i['bottom'])));
$bottomItems = array_slice($bottomItems, 0, 4);
?><!doctype html>
<html lang="<?= e(Translator::locale() === 'fil' ? 'fil' : 'en') ?>" class="<?= e(implode(' ', $htmlClass)) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(($title ?? 'Sibol') . ' – ' . Config::get('app.name', 'Sibol')) ?></title>
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <meta name="app-base" content="<?= e(base_path_url()) ?>">
  <link rel="icon" href="<?= e(asset('school/favicon-32.png')) ?>" type="image/png" sizes="32x32">
  <link rel="apple-touch-icon" href="<?= e(asset('school/apple-touch-icon.png')) ?>">
  <link rel="preload" href="<?= e(url('/assets/fonts/atkinson-hyperlegible-next-latin-wght-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
  <?php if (!empty($katex)): ?>
  <link rel="stylesheet" href="<?= e(asset('vendor/katex/katex.min.css')) ?>">
  <?php endif; ?>
</head>
<body class="<?= e(trim(($page_class ?? '') . ($isDemoOrg || $aiDemo ? ' has-demo-band' : ''))) ?>" data-role="<?= e($user['role'] ?? '') ?>" data-i18n="<?= json_attr(ScriptStrings::common()) ?>">
<a class="skip-link" href="#main"><?= e(t('Skip to content')) ?></a>
<div class="shell">
  <aside class="spine" id="spine" aria-label="<?= e(t('Main navigation')) ?>">
    <a class="brand" href="<?= e(url(home_path())) ?>">
      <?= partial('seal', ['size' => 40, 'class' => 'brand__mark', 'logo' => $schoolLogo]) ?>
      <span class="brand__name"><?= e(Config::get('app.name', 'Sibol')) ?></span>
    </a>
    <?php if ($user): ?>
      <p class="spine__school"><?= e($user['org_name']) ?></p>
    <?php endif; ?>
    <nav aria-label="<?= e(t('Main')) ?>">
      <ul class="nav">
        <?php foreach ($items as $item): $active = Navigation::isActive($item); ?>
          <li>
            <a class="nav-link" href="<?= e(url($item['url'])) ?>"<?= $active ? ' aria-current="page"' : '' ?> title="<?= e($item['label']) ?>">
              <?= icon($item['icon']) ?>
              <span><?= e($item['label']) ?></span>
              <?php if (!empty($item['count'])): ?>
                <em class="nav-link__count" aria-label="<?= e(tp(':count item', ':count items', (int) $item['count'])) ?>"><?= (int) $item['count'] ?></em>
              <?php endif; ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </nav>
    <div class="spine__foot">
      <ul class="nav">
        <?php foreach (Navigation::footer() as $item): $active = Navigation::isActive($item); ?>
          <li>
            <a class="nav-link" href="<?= e(url($item['url'])) ?>"<?= $active ? ' aria-current="page"' : '' ?> title="<?= e($item['label']) ?>">
              <?= icon($item['icon']) ?><span><?= e($item['label']) ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </aside>

  <div class="workspace">
    <header class="topbar">
      <button type="button" class="icon-btn topbar__menu" data-drawer-toggle aria-controls="spine" aria-expanded="false">
        <?= icon('menu') ?><span class="sr-only"><?= e(t('Open menu')) ?></span>
      </button>
      <a class="topbar__brand" href="<?= e(url(home_path())) ?>">
        <?= partial('seal', ['size' => 30, 'class' => 'brand__mark', 'logo' => $schoolLogo]) ?><span class="brand__name display"><?= e(Config::get('app.name', 'Sibol')) ?></span>
      </a>
      <form class="topbar__search" role="search" action="<?= e(url('/search')) ?>" method="get" id="global-search">
        <label class="sr-only" for="global-search-input"><?= e(t('Search')) ?></label>
        <?= icon('search') ?>
        <input id="global-search-input" type="search" name="q" value="<?= e((string) ($_GET['q'] ?? '')) ?>"
               placeholder="<?= e($user && $user['role'] === 'admin' ? t('Search people, classes, subjects') : t('Search classes, work and materials')) ?>" autocomplete="off">
      </form>
      <div class="topbar__actions">
        <button type="button" class="icon-btn topbar__search-toggle" data-search-toggle aria-controls="global-search" aria-expanded="false">
          <?= icon('search') ?><span class="sr-only"><?= e(t('Search')) ?></span>
        </button>
        <details class="menu" data-menu data-notifications>
          <summary class="icon-btn" aria-label="<?= e($unread ? tp('Notifications, :count unread', 'Notifications, :count unread', $unread) : t('Notifications')) ?>">
            <?= icon('bell') ?>
            <?php if ($unread): ?><span class="icon-btn__dot" aria-hidden="true"><?= $unread > 99 ? '99+' : (int) $unread ?></span><?php endif; ?>
          </summary>
          <div class="menu__panel notif-panel" data-panel-src="<?= e(url('/notifications/panel')) ?>">
            <div class="notif-panel__head"><strong><?= e(t('Notifications')) ?></strong></div>
            <div class="notif-panel__list" aria-busy="true">
              <div class="notif"><span class="skeleton" style="width:80%"></span></div>
              <div class="notif"><span class="skeleton" style="width:60%"></span></div>
            </div>
            <div class="notif-panel__foot"><a href="<?= e(url('/notifications')) ?>"><?= e(t('See all notifications')) ?></a></div>
          </div>
        </details>
        <details class="menu" data-menu>
          <summary class="icon-btn" aria-label="<?= e(t('Account menu')) ?>">
            <?= partial('avatar', ['person' => $user, 'size' => 'sm']) ?>
          </summary>
          <div class="menu__panel" role="menu">
            <div class="menu__head">
              <span class="menu__name"><?= e(user_name($user)) ?></span>
              <span class="menu__sub"><?= e($user['email'] ?? '') ?></span>
              <span class="menu__sub"><?= e(role_label((string) ($user['role'] ?? ''))) ?></span>
            </div>
            <a class="menu__item" role="menuitem" href="<?= e(url('/settings')) ?>"><?= icon('user', 'icon--sm') ?><?= e(t('Account and preferences')) ?></a>
            <a class="menu__item" role="menuitem" href="<?= e(url('/privacy')) ?>"><?= icon('shield-check', 'icon--sm') ?><?= e(t('Privacy notice')) ?></a>
            <a class="menu__item" role="menuitem" href="<?= e(url('/help')) ?>"><?= icon('circle-help', 'icon--sm') ?><?= e(t('Help')) ?></a>
            <div class="menu__sep" role="separator"></div>
            <form method="post" action="<?= e(url('/logout')) ?>">
              <?= csrf_field() ?>
              <button class="menu__item" role="menuitem" type="submit"><?= icon('log-out', 'icon--sm') ?><?= e(t('Sign out')) ?></button>
            </form>
          </div>
        </details>
      </div>
    </header>

    <?php if ($isDemoOrg || $aiDemo): ?>
      <div class="demo-band" role="note">
        <?= icon('info', 'icon--sm') ?>
        <span>
          <?php if ($isDemoOrg): ?><?= e(t('Demo school: every person and record here is fictional.')) ?><?php endif; ?>
          <?php if ($aiDemo): ?> <?= e(t('AI is in demo mode, so answers are built from class materials without an AI model.')) ?><?php endif; ?>
        </span>
        <a href="<?= e(url('/help#demo')) ?>"><?= e(t('What this means')) ?></a>
      </div>
    <?php endif; ?>

    <main class="main" id="main" tabindex="-1">
      <?php if (!empty($crumbs)): ?>
        <nav class="crumbs" aria-label="<?= e(t('Breadcrumb')) ?>">
          <?php foreach ($crumbs as $i => $crumb): ?>
            <?php if ($i > 0): ?><?= icon('chevron-right') ?><?php endif; ?>
            <?php if (!empty($crumb[1])): ?>
              <a href="<?= e(url($crumb[1])) ?>"><?= e($crumb[0]) ?></a>
            <?php else: ?>
              <span aria-current="page"><?= e($crumb[0]) ?></span>
            <?php endif; ?>
          <?php endforeach; ?>
        </nav>
      <?php endif; ?>
      <?= $content ?>
    </main>
  </div>
</div>

<?php if ($bottomItems): ?>
<nav class="bottombar" aria-label="<?= e(t('Quick navigation')) ?>">
  <?php foreach ($bottomItems as $item): $active = Navigation::isActive($item); ?>
    <a href="<?= e(url($item['url'])) ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= icon($item['icon']) ?><span><?= e($item['short'] ?? $item['label']) ?></span></a>
  <?php endforeach; ?>
  <a href="<?= e(url('/menu')) ?>" data-drawer-toggle aria-controls="spine" aria-expanded="false"><?= icon('menu') ?><span><?= e(t('More')) ?></span></a>
</nav>
<?php endif; ?>

<?= partial('toasts', ['flashes' => $flashes]) ?>
<?= partial('confirm_dialog') ?>

<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<?php if (!empty($katex)): ?>
<script src="<?= e(asset('vendor/katex/katex.min.js')) ?>" defer></script>
<script src="<?= e(asset('vendor/katex/auto-render.min.js')) ?>" defer></script>
<?php endif; ?>
<?php foreach (($scripts ?? []) as $script): ?>
<script src="<?= e(asset('js/' . $script)) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
