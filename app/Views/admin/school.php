<?php /** @var array $org @var list<string> $grades */
$zones = array_filter(DateTimeZone::listIdentifiers(), static fn ($z) => str_starts_with($z, 'Asia/') || str_starts_with($z, 'Pacific/') || $z === 'UTC');
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('School settings')) ?></h1>
    <p class="page-lead"><?= e(t('Your school\'s details, grade levels, logo and grading setup.')) ?></p>
  </div>
  <div class="page-actions"><a class="btn btn--secondary" href="<?= e(url('/admin/integrations')) ?>"><?= icon('plug', 'icon--sm') ?><?= e(t('Integrations and system checks')) ?></a></div>
</div>

<nav class="tabs" aria-label="<?= e(t('Settings sections')) ?>">
  <a class="tab" href="#details"><?= e(t('Details')) ?></a>
  <a class="tab" href="#logo"><?= e(t('Logo')) ?></a>
  <a class="tab" href="#grading"><?= e(t('Grading')) ?></a>
</nav>

<section class="section" id="details" aria-labelledby="det-title">
  <div class="section__head"><h2 class="section__title" id="det-title"><?= e(t('Details')) ?></h2></div>
  <form method="post" action="<?= e(url('/admin/settings')) ?>" style="max-width: 760px" data-unsaved-warning>
    <?= csrf_field() ?>
    <div class="form-grid">
      <?= field('text', 'name', t('School name'), ['value' => $org['name'], 'required' => true, 'wrap_class' => 'span-2', 'attrs' => ['maxlength' => 160]]) ?>
      <?= field('text', 'short_name', t('Short name'), ['value' => (string) $org['short_name'], 'optional_label' => true, 'attrs' => ['maxlength' => 60]]) ?>
      <?= field('select', 'default_language', t('Default language'), ['value' => $org['default_language'], 'options' => ['en' => 'English', 'fil' => 'Filipino'], 'hint' => t('People can choose their own language in their settings.')]) ?>
      <?= field('select', 'timezone', t('Timezone'), ['value' => $org['timezone'], 'options' => array_combine($zones, $zones), 'hint' => t('Due dates and reminders use this unless a person sets their own.')]) ?>
      <?= field('textarea', 'grade_levels', t('Grade levels'), ['value' => implode("\n", $grades), 'rows' => 6, 'hint' => t('One per line, in order.'), 'attrs' => ['maxlength' => 2000]]) ?>
    </div>
    <div class="form-actions"><button class="btn btn--primary" type="submit"><?= e(t('Save details')) ?></button></div>
  </form>
</section>

<section class="section" id="logo" aria-labelledby="logo-title">
  <div class="section__head"><h2 class="section__title" id="logo-title"><?= e(t('Logo')) ?></h2></div>
  <div class="cluster" style="align-items: flex-start; gap: 24px">
    <div class="logo-preview"><?php if ($org['logo_path']): ?><img src="<?= e(url('/school-logo')) ?>" alt="<?= e(t('Current school logo')) ?>"><?php else: ?><span class="meta"><?= e(t('No logo')) ?></span><?php endif; ?></div>
    <div class="stack-sm" style="max-width: 460px">
      <form method="post" action="<?= e(url('/admin/settings/logo')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="field<?= field_error('logo') ? ' field--error' : '' ?>">
          <label class="field__label" for="f-logo"><?= e(t('Upload a logo')) ?></label>
          <p class="field__hint" id="f-logo-hint"><?= e(t('PNG or JPG, up to 1 MB. A square image works best. Shown beside the school name.')) ?></p>
          <input class="input" id="f-logo" type="file" name="logo" accept="image/png,image/jpeg" required aria-describedby="f-logo-hint">
          <?php if (field_error('logo')): ?><p class="field__error"><?= icon('circle-alert', 'icon--xs') ?><?= e(field_error('logo')) ?></p><?php endif; ?>
        </div>
        <button class="btn btn--secondary" type="submit"><?= icon('upload', 'icon--sm') ?><?= e(t('Upload logo')) ?></button>
      </form>
      <?php if ($org['logo_path']): ?>
        <form method="post" action="<?= e(url('/admin/settings/logo')) ?>"><?= csrf_field() ?><input type="hidden" name="remove" value="1"><button class="btn btn--quiet btn--sm" type="submit"><?= e(t('Remove logo')) ?></button></form>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="section" id="grading" aria-labelledby="grading-title">
  <div class="section__head"><h2 class="section__title" id="grading-title"><?= e(t('Grading')) ?></h2></div>
  <p class="section__intro"><?= e(t('Grading periods, category weights, weights by subject group and the transmutation table have their own page.')) ?></p>
  <a class="btn btn--secondary" href="<?= e(url('/admin/grading')) ?>"><?= icon('sliders-horizontal', 'icon--sm') ?><?= e(t('Open the grading setup')) ?></a>
</section>
