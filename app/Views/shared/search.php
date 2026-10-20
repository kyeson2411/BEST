<?php /** @var string $q @var array $groups @var bool $tooShort */ ?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Search')) ?></h1>
    <p class="page-lead"><?= e(t('Results only include what your account can open.')) ?></p>
  </div>
</div>
<form method="get" action="<?= e(url('/search')) ?>" class="toolbar" role="search" style="max-width: 640px">
  <div class="field field--grow" style="flex:1">
    <label class="field__label" for="search-q"><?= e(t('Search for')) ?></label>
    <input class="input" id="search-q" type="search" name="q" value="<?= e($q) ?>" minlength="2" maxlength="100" required>
  </div>
  <button class="btn btn--primary" type="submit"><?= icon('search', 'icon--sm') ?><?= e(t('Search')) ?></button>
</form>

<?php if ($tooShort): ?>
  <p class="meta"><?= e(t('Type at least two characters.')) ?></p>
<?php elseif ($q !== '' && !$groups): ?>
  <?= partial('empty', ['title' => t('No matches for “:q”', ['q' => $q]), 'body' => t('Try a shorter word, a class name, or the title of a material.')]) ?>
<?php else: ?>
  <?php foreach ($groups as $group): ?>
    <section class="section" style="max-width: 820px">
      <div class="section__head"><h2 class="section__title"><?= e($group['label']) ?> <span class="count"><?= count($group['items']) ?></span></h2></div>
      <ul class="rows">
        <?php foreach ($group['items'] as $item): ?>
          <li class="row">
            <span class="row__lead"><?= icon($group['icon']) ?></span>
            <div class="row__main">
              <a class="row__title" href="<?= e(url($item['url'])) ?>"><?= e($item['title']) ?></a>
              <?php if ($item['meta'] !== ''): ?><div class="row__meta"><span><?= e($item['meta']) ?></span></div><?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endforeach; ?>
<?php endif; ?>
