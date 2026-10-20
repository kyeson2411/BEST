<?php /** @var array $org @var list<string> $grades @var array $grading @var array $presets @var list<string> $used */
$zones = array_filter(DateTimeZone::listIdentifiers(), static fn ($z) => str_starts_with($z, 'Asia/') || str_starts_with($z, 'Pacific/') || $z === 'UTC');
$categories = (array) ($grading['categories'] ?? []);
$descriptors = (array) ($grading['descriptors'] ?? []);
$catRows = array_merge($categories, array_fill(0, max(1, 4 - count($categories)), ['key' => '', 'label' => '', 'weight' => '']));
$descRows = array_merge($descriptors, array_fill(0, max(1, 6 - count($descriptors)), ['min' => '', 'label' => '']));
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
  <p class="section__intro"><?= e(t('Sibol does not assume a grading policy. Choose how overall grades are calculated in gradebooks. Teachers still decide each score.')) ?></p>
  <?php if (field_error('categories') || field_error('descriptors') || field_error('preset')): ?>
    <div class="callout callout--coral" style="margin-bottom: 16px"><?= icon('circle-alert') ?><p><?= e(field_error('categories') ?? field_error('descriptors') ?? field_error('preset')) ?></p></div>
  <?php endif; ?>
  <form method="post" action="<?= e(url('/admin/settings/grading')) ?>" data-unsaved-warning>
    <?= csrf_field() ?>
    <fieldset class="fieldset-plain">
      <legend class="field__label"><?= e(t('Overall grade')) ?></legend>
      <div class="choice-list" style="max-width: 760px">
        <label class="choice"><input type="radio" name="model" value="points"<?= checked(($grading['model'] ?? 'points') === 'points') ?>><span><span class="choice__title"><?= e(t('Total points')) ?></span><span class="choice__desc"><?= e(t('Points earned divided by points possible across all graded work.')) ?></span></span></label>
        <label class="choice"><input type="radio" name="model" value="weighted"<?= checked(($grading['model'] ?? '') === 'weighted') ?>><span><span class="choice__title"><?= e(t('Weighted categories')) ?></span><span class="choice__desc"><?= e(t('Each category\'s percentage counts by its weight. Teachers pick a category for each assignment.')) ?></span></span></label>
      </div>
    </fieldset>

    <div class="grid-2" style="margin-top: 20px">
      <fieldset class="fieldset-plain">
        <legend class="field__label"><?= e(t('Categories (for weighted grading)')) ?></legend>
        <table class="table table--compact">
          <thead><tr><th scope="col"><?= e(t('Name')) ?></th><th scope="col" class="num"><?= e(t('Weight %')) ?></th></tr></thead>
          <tbody>
            <?php foreach ($catRows as $i => $c): ?>
              <tr>
                <td><input type="hidden" name="cat_key[]" value="<?= e($c['key']) ?>"><label class="sr-only" for="cat-l-<?= $i ?>"><?= e(t('Category :n name', ['n' => $i + 1])) ?></label><input class="input" id="cat-l-<?= $i ?>" name="cat_label[]" value="<?= e($c['label']) ?>" maxlength="60">
                  <?php if ($c['key'] !== '' && in_array($c['key'], $used, true)): ?><span class="table-sub"><?= e(t('Used by existing assignments')) ?></span><?php endif; ?></td>
                <td class="num"><label class="sr-only" for="cat-w-<?= $i ?>"><?= e(t('Category :n weight', ['n' => $i + 1])) ?></label><input class="input input--num" id="cat-w-<?= $i ?>" name="cat_weight[]" value="<?= e((string) $c['weight']) ?>" inputmode="decimal" size="5"></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <p class="meta"><?= e(t('Weights must add up to 100. Leave a row empty to remove it.')) ?></p>
      </fieldset>
      <fieldset class="fieldset-plain">
        <legend class="field__label"><?= e(t('Descriptors')) ?></legend>
        <table class="table table--compact">
          <thead><tr><th scope="col" class="num"><?= e(t('From %')) ?></th><th scope="col"><?= e(t('Label')) ?></th></tr></thead>
          <tbody>
            <?php foreach ($descRows as $i => $d): ?>
              <tr>
                <td class="num"><label class="sr-only" for="desc-m-<?= $i ?>"><?= e(t('Descriptor :n lowest percentage', ['n' => $i + 1])) ?></label><input class="input input--num" id="desc-m-<?= $i ?>" name="desc_min[]" value="<?= e($d['min'] === '' ? '' : fmt_number($d['min'], 2)) ?>" inputmode="decimal" size="5"></td>
                <td><label class="sr-only" for="desc-l-<?= $i ?>"><?= e(t('Descriptor :n label', ['n' => $i + 1])) ?></label><input class="input" id="desc-l-<?= $i ?>" name="desc_label[]" value="<?= e($d['label']) ?>" maxlength="60"></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <p class="meta"><?= e(t('Optional words shown next to overall percentages, such as “Very satisfactory”. Students see a descriptor only once enough work is graded.')) ?></p>
      </fieldset>
    </div>
    <div style="max-width: 280px"><?= field('number', 'passing_percent', t('Passing percentage'), ['value' => (string) ($grading['passing_percent'] ?? 75), 'required' => true, 'attrs' => ['min' => 0, 'max' => 100, 'step' => '0.5']]) ?></div>
    <div class="form-actions"><button class="btn btn--primary" type="submit"><?= e(t('Save grading setup')) ?></button></div>
  </form>

  <div class="panel panel--sunken" style="margin-top: 24px; max-width: 760px">
    <h3 class="panel__title"><?= e(t('Start from a template')) ?></h3>
    <p class="meta" style="margin-top: 0"><?= e(t('Loading a template replaces the setup above. Check every weight against your school\'s current policy before teachers rely on it.')) ?></p>
    <div class="cluster">
      <?php foreach ($presets as $key => $p): ?>
        <form method="post" action="<?= e(url('/admin/settings/grading')) ?>" data-confirm="<?= e(t('This replaces your current categories and descriptors.')) ?>" data-confirm-title="<?= e(t('Load “:name”?', ['name' => t($p['label'])])) ?>" data-confirm-action="<?= e(t('Load template')) ?>">
          <?= csrf_field() ?><input type="hidden" name="preset" value="<?= e($key) ?>">
          <button class="btn btn--secondary btn--sm" type="submit"><?= e(t($p['label'])) ?></button>
        </form>
      <?php endforeach; ?>
    </div>
  </div>
</section>
