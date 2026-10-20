<?php /** @var array $user @var array $prefs @var array $types @var list<array> $requests @var array $timezones */
$muted = (array) ($prefs['muted_notifications'] ?? []);
$statusTone = ['submitted' => 'amber', 'in_review' => 'amber', 'completed' => 'leaf', 'rejected' => 'neutral'];
$statusLabel = ['submitted' => t('Submitted'), 'in_review' => t('Being reviewed'), 'completed' => t('Completed'), 'rejected' => t('Declined')];
$typeLabel = ['export' => t('Copy of my data'), 'correction' => t('Correct my information'), 'deletion' => t('Delete my information')];
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Account and preferences')) ?></h1>
    <p class="page-lead"><?= e(role_label((string) $user['role'])) ?>, <?= e($user['org_name']) ?></p>
  </div>
</div>

<nav class="tabs" aria-label="<?= e(t('Settings sections')) ?>">
  <a class="tab" href="#profile"><?= e(t('Profile')) ?></a>
  <a class="tab" href="#preferences"><?= e(t('Preferences')) ?></a>
  <a class="tab" href="#password"><?= e(t('Password')) ?></a>
  <a class="tab" href="#sessions"><?= e(t('Sign-in')) ?></a>
  <a class="tab" href="#privacy"><?= e(t('Privacy requests')) ?></a>
</nav>

<div class="stack" style="max-width: 760px">
  <section class="section" id="profile" aria-labelledby="profile-title">
    <div class="section__head"><h2 class="section__title" id="profile-title"><?= e(t('Profile')) ?></h2></div>
    <div class="cluster" style="gap:16px; margin: 8px 0 20px; align-items:center">
      <?= partial('avatar', ['person' => $user, 'size' => 'lg']) ?>
      <div>
        <form method="post" action="<?= e(url('/settings/avatar')) ?>" enctype="multipart/form-data" class="cluster">
          <?= csrf_field() ?>
          <label class="sr-only" for="avatar"><?= e(t('Profile photo')) ?></label>
          <input class="input" id="avatar" type="file" name="avatar" accept=".jpg,.jpeg,.png" required style="max-width: 300px" aria-describedby="avatar-hint">
          <button class="btn btn--secondary btn--sm" type="submit"><?= e(t('Upload photo')) ?></button>
        </form>
        <p class="field__hint" id="avatar-hint" style="margin-top:6px"><?= e(t('Optional. JPG or PNG up to 2 MB. Location and camera details are removed.')) ?></p>
        <?php if (field_error('avatar')): ?><p class="field__error"><?= e(field_error('avatar')) ?></p><?php endif; ?>
        <?php if ($user['avatar_path']): ?>
          <form method="post" action="<?= e(url('/settings/avatar/remove')) ?>"><?= csrf_field() ?><button class="btn btn--danger-quiet btn--sm" type="submit"><?= e(t('Remove photo')) ?></button></form>
        <?php endif; ?>
      </div>
    </div>
    <dl class="dl" style="margin-bottom: 20px">
      <dt><?= e(t('Name')) ?></dt><dd><?= e($user['given_name'] . ' ' . $user['family_name']) ?></dd>
      <?php if (!empty($user['username'])): ?><dt><?= e(t('Username')) ?></dt><dd class="code-text"><?= e($user['username']) ?></dd><?php endif; ?>
      <?php if (!empty($user['email'])): ?><dt><?= e(t('Email')) ?></dt><dd><?= e($user['email']) ?></dd><?php endif; ?>
      <?php if ($user['grade_level']): ?><dt><?= e(t('Grade level')) ?></dt><dd><?= e($user['grade_level']) ?></dd><?php endif; ?>
      <?php if ($user['role'] === 'student'): ?><dt><?= e(t('AI companion')) ?></dt><dd><?= e(\Sibol\Services\AiAccess::studentOn($user) ? t('On') : t('Switched off for your account by your school')) ?></dd><?php endif; ?>
    </dl>
    <p class="meta"><?= e(t('Your name, username and email come from school records. To correct them, send a correction request below.')) ?></p>
    <form method="post" action="<?= e(url('/settings/profile')) ?>">
      <?= csrf_field() ?>
      <?= field('text', 'preferred_name', t('Name you go by'), ['value' => (string) ($user['preferred_name'] ?? ''), 'optional_label' => true, 'hint' => t('Shown instead of your first name, for example a nickname.'), 'attrs' => ['maxlength' => 80]]) ?>
      <button class="btn btn--primary" type="submit"><?= e(t('Save profile')) ?></button>
    </form>
  </section>

  <section class="section" id="preferences" aria-labelledby="prefs-title">
    <div class="section__head"><h2 class="section__title" id="prefs-title"><?= e(t('Preferences')) ?></h2></div>
    <form method="post" action="<?= e(url('/settings/preferences')) ?>">
      <?= csrf_field() ?>
      <div class="form-grid">
        <?= field('select', 'language', t('Language'), ['options' => (array) \Sibol\Core\Config::get('app.locales'), 'value' => (string) ($user['language'] ?: $user['org_language'])]) ?>
        <?= field('select', 'timezone', t('Timezone'), ['options' => $timezones, 'value' => (string) ($user['timezone'] ?? '')]) ?>
      </div>
      <fieldset>
        <legend><?= e(t('Reading and motion')) ?></legend>
        <?= checkbox('large_text', t('Larger text'), !empty($prefs['large_text']), t('Makes all text about 12% bigger.')) ?>
        <?= checkbox('reduce_motion', t('Reduce motion'), !empty($prefs['reduce_motion']), t('Turns off animations, in addition to your device setting.')) ?>
        <?= checkbox('underline_links', t('Always underline links'), !empty($prefs['underline_links'])) ?>
      </fieldset>
      <fieldset>
        <legend><?= e(t('Notifications')) ?></legend>
        <p class="fieldset-hint"><?= e(t('Notifications appear inside Sibol. Choose which ones you want.')) ?></p>
        <?php foreach ($types as $key => $label): ?>
          <?= checkbox('notify[]', $label, !in_array($key, $muted, true), null, $key) ?>
        <?php endforeach; ?>
        <?php if (\Sibol\Services\EmailDigest::available($user)): ?>
          <?= checkbox('email_digest', t('Email me a daily summary of new notifications'), !empty($prefs['email_digest']), t('At most one email a day to :address, with notification titles only: never grades, comments or conversations.', ['address' => $user['email']])) ?>
        <?php elseif (\Sibol\Services\Mailer::enabled()): ?>
          <p class="fieldset-hint"><?= e(t('A daily summary by email needs an email address on your account. Ask your school\'s administrator to add one.')) ?></p>
        <?php endif; ?>
      </fieldset>
      <button class="btn btn--primary" type="submit"><?= e(t('Save preferences')) ?></button>
    </form>
  </section>

  <section class="section" id="password" aria-labelledby="pw-title">
    <div class="section__head"><h2 class="section__title" id="pw-title"><?= e(t('Password')) ?></h2></div>
    <p class="meta"><?= $user['password_changed_at'] ? e(t('Last changed :when.', ['when' => fmt_date($user['password_changed_at'], 'date_long')])) : '' ?></p>
    <form method="post" action="<?= e(url('/settings/password')) ?>" autocomplete="off">
      <?= csrf_field() ?>
      <?= field('password', 'current_password', t('Current password'), ['required' => true, 'attrs' => ['autocomplete' => 'current-password']]) ?>
      <div class="form-grid">
        <?= field('password', 'password', t('New password'), ['required' => true, 'hint' => t('At least 10 characters.'), 'attrs' => ['autocomplete' => 'new-password', 'minlength' => 10]]) ?>
        <?= field('password', 'password_confirmation', t('Type the new password again'), ['required' => true, 'attrs' => ['autocomplete' => 'new-password']]) ?>
      </div>
      <button class="btn btn--primary" type="submit"><?= e(t('Change password')) ?></button>
    </form>
  </section>

  <section class="section" id="sessions" aria-labelledby="sessions-title">
    <div class="section__head"><h2 class="section__title" id="sessions-title"><?= e(t('Sign-in')) ?></h2></div>
    <p><?= e(t('Last sign-in: :when.', ['when' => $user['last_login_at'] ? fmt_date($user['last_login_at'], 'datetime') : t('never')])) ?>
      <?= e(t('You are signed out automatically after two hours without activity.')) ?></p>
    <form method="post" action="<?= e(url('/settings/sessions/revoke')) ?>" data-confirm="<?= e(t('Any other browser or device signed in to your account will be signed out. You stay signed in here.')) ?>" data-confirm-title="<?= e(t('Sign out everywhere else?')) ?>" data-confirm-action="<?= e(t('Sign out others')) ?>">
      <?= csrf_field() ?>
      <button class="btn btn--secondary" type="submit"><?= icon('log-out', 'icon--sm') ?><?= e(t('Sign out of other devices')) ?></button>
    </form>
  </section>

  <section class="section" id="privacy" aria-labelledby="privacy-title">
    <div class="section__head"><h2 class="section__title" id="privacy-title"><?= e(t('Privacy requests')) ?></h2><a class="section__link" href="<?= e(url('/privacy')) ?>"><?= e(t('Privacy notice')) ?></a></div>
    <p class="muted"><?= e(t('You can ask the school for a copy of your information, to correct it, or to delete it. A school administrator reviews each request. Some records, such as grades, may have to be kept by law or school policy; you will be told if so.')) ?></p>
    <?php if ($requests): ?>
      <ul class="rows" style="margin-bottom: 20px">
        <?php foreach ($requests as $r): ?>
          <li class="row">
            <div class="row__main">
              <span class="row__title"><?= e($typeLabel[$r['request_type']]) ?></span>
              <div class="row__meta"><span><?= e(t('Sent :when', ['when' => fmt_date($r['created_at'])])) ?></span><?php if ($r['resolution_note']): ?><span><?= e($r['resolution_note']) ?></span><?php endif; ?></div>
            </div>
            <div class="row__aside">
              <?= badge($statusLabel[$r['status']], $statusTone[$r['status']]) ?>
              <?php if ($r['request_type'] === 'export' && $r['status'] === 'completed' && $r['export_path'] && (!$r['export_expires_at'] || $r['export_expires_at'] > \Sibol\Core\Db::now())): ?>
                <a class="btn btn--secondary btn--sm" href="<?= e(url('/settings/privacy-requests/' . (int) $r['id'] . '/download')) ?>"><?= icon('download', 'icon--sm') ?><?= e(t('Download')) ?></a>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <form method="post" action="<?= e(url('/settings/privacy-requests')) ?>">
      <?= csrf_field() ?>
      <?= field('select', 'request_type', t('Type of request'), ['options' => $typeLabel, 'required' => true]) ?>
      <?= field('textarea', 'details', t('Details'), ['rows' => 3, 'hint' => t('Needed for corrections and deletions: say what should change.'), 'attrs' => ['maxlength' => 2000]]) ?>
      <button class="btn btn--primary" type="submit"><?= e(t('Send request')) ?></button>
    </form>
  </section>
</div>
