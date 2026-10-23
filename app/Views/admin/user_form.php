<?php /** @var list<string> $grades @var array $classes @var string $role */ ?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Invite someone')) ?></h1>
    <p class="page-lead"><?= e(t('B.E.S.T. Learning and Interactive Study System creates the account and a private invitation link or a printed sign-in slip. The person sets their own password.')) ?></p>
  </div>
</div>
<div class="layout-split">
  <form method="post" action="<?= e(url('/admin/users')) ?>" data-unsaved-warning>
    <?= csrf_field() ?>
    <fieldset class="field" style="border:0; padding:0; margin:0 0 20px">
      <legend class="field__label"><?= e(t('Role')) ?></legend>
      <div class="choice-list">
        <?php foreach (['student' => [t('Student'), t('Joins classes, sees released work and uses the companion.')], 'teacher' => [t('Teacher'), t('Manages their own classes, materials, assignments and grades.')], 'admin' => [t('School administrator'), t('Manages people, classes and school policies. Cannot read students\' companion conversations.')]] as $value => [$label, $desc]): ?>
          <label class="choice"><input type="radio" name="role" value="<?= e($value) ?>"<?= checked(old('role', $role) === $value) ?>>
            <span><span class="choice__title"><?= e($label) ?></span><span class="choice__desc"><?= e($desc) ?></span></span></label>
        <?php endforeach; ?>
      </div>
      <?php if (field_error('role')): ?><p class="field__error"><?= icon('circle-alert', 'icon--xs') ?><?= e(field_error('role')) ?></p><?php endif; ?>
    </fieldset>
    <div class="form-grid">
      <?= field('text', 'given_name', t('Given name'), ['required' => true, 'attrs' => ['maxlength' => 80, 'autocomplete' => 'off']]) ?>
      <?= field('text', 'family_name', t('Family name'), ['required' => true, 'attrs' => ['maxlength' => 80, 'autocomplete' => 'off']]) ?>
      <?= field('text', 'preferred_name', t('Preferred name'), ['optional_label' => true, 'hint' => t('Shown instead of the given name, if set.'), 'attrs' => ['maxlength' => 80]]) ?>
      <?= field('text', 'username', t('Username'), ['optional_label' => true, 'hint' => t('Used to sign in. Leave empty and B.E.S.T. Learning and Interactive Study System makes one from the name, such as juan.delacruz.'), 'class' => 'code-text', 'attrs' => ['maxlength' => 40, 'autocomplete' => 'off', 'autocapitalize' => 'none', 'spellcheck' => 'false']]) ?>
      <?= field('email', 'email', t('Email address'), ['optional_label' => true, 'hint' => t('Optional. Teachers usually have one; students without email sign in with their username.'), 'attrs' => ['autocomplete' => 'off']]) ?>
      <?= field('select', 'grade_level', t('Grade level'), ['options' => ['' => t('Not set')] + array_combine($grades, $grades), 'hint' => t('Students only.')]) ?>
      <?= field('select', 'class_id', t('Add to a class'), ['options' => ['' => t('Not now')] + $classes, 'hint' => t('Optional. You can add classes later.')]) ?>
    </div>
    <div class="form-actions">
      <button class="btn btn--primary" type="submit" data-busy="<?= e(t('Creating…')) ?>"><?= icon('user-plus', 'icon--sm') ?><?= e(t('Create invitation')) ?></button>
      <a class="btn btn--quiet" href="<?= e(url('/admin/users')) ?>"><?= e(t('Cancel')) ?></a>
    </div>
  </form>
  <aside class="panel panel--sunken">
    <h2 class="panel__title"><?= e(t('What happens next')) ?></h2>
    <ol class="plain-steps">
      <li><?= e(t('B.E.S.T. Learning and Interactive Study System shows you a private link that works once and expires in :days days. You can print it as a sign-in slip instead.', ['days' => \Sibol\Services\Tokens::INVITE_DAYS])) ?></li>
      <li><?= e(t('You give it to the person: in person on a slip, or by email or chat.')) ?></li>
      <li><?= e(t('They open it, read the privacy notice and set a password.')) ?></li>
    </ol>
    <p class="meta"><?= e(t('Inviting a whole section? Import a spreadsheet instead.')) ?> <a href="<?= e(url('/admin/users/import')) ?>"><?= e(t('Import from CSV')) ?></a></p>
  </aside>
</div>
