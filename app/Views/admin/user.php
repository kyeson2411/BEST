<?php
/**
 * @var array $person @var bool $isSelf @var list<array> $memberships @var array|null $pendingLink @var array|null $reveal
 * @var list<array> $activity @var list<string> $grades @var bool $lastAdmin
 */
use Sibol\Services\AuditLabels;

$base = '/admin/users/' . (int) $person['id'];
$erased = $person['anonymized_at'] !== null;
$activeClasses = array_values(array_filter($memberships, static fn ($m) => $m['status'] === 'active'));
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(user_name($person)) ?></h1>
    <p class="page-lead"><?= e(role_label($person['role'])) ?><?= $person['grade_level'] ? ', ' . e($person['grade_level']) : '' ?>
      <?php if ($erased): ?><?= badge(t('Erased'), 'neutral') ?>
      <?php elseif ($person['status'] === 'active'): ?><?= badge(t('Active'), 'leaf', 'circle-check') ?>
      <?php elseif ($person['status'] === 'invited'): ?><?= badge(t('Invited'), 'amber', 'mail') ?>
      <?php else: ?><?= badge(t('Deactivated'), 'neutral', 'user-x') ?><?php endif; ?>
      <?php if ($isSelf): ?><?= badge(t('You'), 'outline') ?><?php endif; ?></p>
  </div>
</div>

<?php if ($reveal): ?><?= partial('link_reveal', ['reveal' => $reveal, 'person' => $person]) ?><?php endif; ?>

<div class="layout-split">
  <div class="stack">
    <section class="section" aria-labelledby="details-title">
      <div class="section__head"><h2 class="section__title" id="details-title"><?= e(t('Account details')) ?></h2></div>
      <?php if ($erased): ?>
        <p class="meta"><?= e(t('This account was erased on :date after a deletion request. Personal details were removed; the record is kept so past class activity stays consistent.', ['date' => fmt_date($person['anonymized_at'])])) ?></p>
      <?php else: ?>
        <form method="post" action="<?= e(url($base)) ?>" data-unsaved-warning>
          <?= csrf_field() ?>
          <div class="form-grid">
            <?= field('text', 'given_name', t('Given name'), ['value' => $person['given_name'], 'required' => true, 'attrs' => ['maxlength' => 80]]) ?>
            <?= field('text', 'family_name', t('Family name'), ['value' => $person['family_name'], 'required' => true, 'attrs' => ['maxlength' => 80]]) ?>
            <?= field('text', 'preferred_name', t('Preferred name'), ['value' => (string) $person['preferred_name'], 'optional_label' => true, 'attrs' => ['maxlength' => 80]]) ?>
            <?= field('text', 'username', t('Username'), ['value' => (string) $person['username'], 'required' => true, 'hint' => t('Used to sign in. Changing it takes effect at their next sign-in.'), 'class' => 'code-text', 'attrs' => ['maxlength' => 40, 'autocapitalize' => 'none', 'spellcheck' => 'false']]) ?>
            <?= field('email', 'email', t('Email address'), ['value' => (string) $person['email'], 'optional_label' => true]) ?>
            <?= field('select', 'role', t('Role'), [
                'value' => $person['role'],
                'options' => ['student' => t('Student'), 'teacher' => t('Teacher'), 'admin' => t('School administrator')],
                'hint' => $isSelf ? t('You cannot change your own role.') : ($activeClasses ? t('Remove them from their classes before changing the role.') : null),
                'attrs' => ['disabled' => $isSelf ?: null],
            ]) ?>
            <?php if ($isSelf): ?><input type="hidden" name="role" value="<?= e($person['role']) ?>"><?php endif; ?>
            <?= field('select', 'grade_level', t('Grade level'), ['value' => (string) $person['grade_level'], 'options' => ['' => t('Not set')] + array_combine($grades, $grades), 'hint' => t('Students only.')]) ?>
          </div>
          <div class="form-actions"><button class="btn btn--primary" type="submit" data-busy="<?= e(t('Saving…')) ?>"><?= e(t('Save changes')) ?></button></div>
        </form>
      <?php endif; ?>
    </section>

    <section class="section" aria-labelledby="classes-title">
      <div class="section__head"><h2 class="section__title" id="classes-title"><?= e(t('Classes')) ?></h2></div>
      <?php if (!$memberships): ?>
        <p class="meta"><?= e($person['role'] === 'admin' ? t('Administrators do not belong to classes.') : t('Not in any class yet. Add them from a class page.')) ?></p>
      <?php else: ?>
        <ul class="rows">
          <?php foreach ($memberships as $m): ?>
            <li class="row<?= $m['status'] !== 'active' ? ' row--muted' : '' ?>">
              <div class="row__main">
                <a class="row__title" href="<?= e(url('/admin/classes/' . (int) $m['class_id'])) ?>"><?= e($m['class_name']) ?></a>
                <div class="row__meta"><span><?= e($m['role'] === 'teacher' ? t('Teacher') : t('Student')) ?></span>
                  <span><?= $m['status'] === 'active' ? e(t('Since :date', ['date' => fmt_date($m['joined_at'])])) : e(t('Withdrawn :date', ['date' => fmt_date($m['withdrawn_at'])])) ?></span>
                  <?php if ($m['class_status'] !== 'active'): ?><span><?= e(t('Class archived')) ?></span><?php endif; ?></div>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="section" aria-labelledby="activity-title">
      <div class="section__head"><h2 class="section__title" id="activity-title"><?= e(t('Recent account activity')) ?></h2><a class="section__link" href="<?= e(url('/admin/audit', ['user' => (int) $person['id']])) ?>"><?= e(t('Full history')) ?></a></div>
      <?php if (!$activity): ?><p class="meta"><?= e(t('Nothing recorded yet.')) ?></p>
      <?php else: ?><?= partial('audit_entries', ['rows' => $activity, 'targets' => AuditLabels::targets($activity, (int) $person['organization_id'])]) ?><?php endif; ?>
      <p class="meta"><?= e(t('Activity shows actions such as sign-ins and submissions. Companion conversations are private and are not shown here.')) ?></p>
    </section>
  </div>

  <aside class="stack">
    <?php if (!$erased): ?>
      <section class="panel" aria-labelledby="access-title">
        <h2 class="panel__title" id="access-title"><?= e(t('Access')) ?></h2>
        <dl class="dl">
          <dt><?= e(t('Username')) ?></dt><dd class="code-text" style="overflow-wrap:anywhere"><?= e((string) $person['username']) ?></dd>
          <?php if ($person['email']): ?><dt><?= e(t('Email')) ?></dt><dd style="overflow-wrap:anywhere"><?= e($person['email']) ?></dd><?php endif; ?>
          <dt><?= e(t('Last sign-in')) ?></dt><dd><?= $person['last_login_at'] ? time_tag($person['last_login_at'], 'datetime') : e(t('Never')) ?></dd>
          <dt><?= e(t('Account created')) ?></dt><dd><?= time_tag($person['created_at']) ?></dd>
          <?php if ($pendingLink): ?><dt><?= e($pendingLink['purpose'] === 'invitation' ? t('Invitation link') : t('Reset link')) ?></dt><dd><?= e(t('Unused, expires :date', ['date' => fmt_date($pendingLink['expires_at'], 'datetime_short')])) ?></dd><?php endif; ?>
          <?php if (\Sibol\Services\TwoStep::offered($person)): ?>
            <dt><?= e(t('Two-step sign-in')) ?></dt><dd><?= e(\Sibol\Services\TwoStep::enabled($person) ? t('On since :date', ['date' => fmt_date($person['totp_enabled_at'])]) : t('Off')) ?></dd>
          <?php endif; ?>
        </dl>
        <div class="stack-sm" style="margin-top: 16px">
          <?php if ($person['status'] !== 'deactivated'): ?>
            <form method="post" action="<?= e(url($base . '/link')) ?>"<?= $pendingLink ? ' data-confirm="' . e(t('The link you created earlier will stop working.')) . '" data-confirm-title="' . e(t('Create a new link?')) . '" data-confirm-action="' . e(t('Create link')) . '"' : '' ?>>
              <?= csrf_field() ?>
              <button class="btn btn--secondary btn--block" type="submit"><?= icon($person['status'] === 'invited' ? 'mail' : 'key-round', 'icon--sm') ?><?= e($person['status'] === 'invited' ? t('Create invitation link') : t('Create password reset link')) ?></button>
            </form>
            <form method="post" action="<?= e(url($base . '/slip')) ?>" data-confirm="<?= e($pendingLink ? t('A printed slip replaces the link you created earlier, which stops working.') : t('The slip has a one-time code. Hand it to the person yourself.')) ?>" data-confirm-title="<?= e(t('Print a sign-in slip?')) ?>" data-confirm-action="<?= e(t('Print slip')) ?>">
              <?= csrf_field() ?>
              <button class="btn btn--secondary btn--block" type="submit"><?= icon('printer', 'icon--sm') ?><?= e($person['status'] === 'invited' ? t('Print sign-in slip') : t('Print password slip')) ?></button>
            </form>
            <?php if (!$isSelf && \Sibol\Services\TwoStep::enabled($person)): ?>
              <form method="post" action="<?= e(url($base . '/two-step')) ?>" data-confirm="<?= e(t('Do this only when :name cannot use their phone or recovery codes, and you are sure it is them asking. They will be told.', ['name' => user_name($person)])) ?>" data-confirm-title="<?= e(t('Switch off two-step sign-in?')) ?>" data-confirm-action="<?= e(t('Switch off')) ?>" data-confirm-tone="danger">
                <?= csrf_field() ?>
                <button class="btn btn--secondary btn--block" type="submit"><?= icon('lock', 'icon--sm') ?><?= e(t('Switch off two-step sign-in')) ?></button>
              </form>
            <?php endif; ?>
          <?php endif; ?>
          <?php if ($person['status'] === 'deactivated'): ?>
            <form method="post" action="<?= e(url($base . '/status')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="reactivate">
              <button class="btn btn--secondary btn--block" type="submit"><?= icon('user-check', 'icon--sm') ?><?= e(t('Reactivate account')) ?></button></form>
          <?php elseif (!$isSelf && !$lastAdmin): ?>
            <form method="post" action="<?= e(url($base . '/status')) ?>" data-confirm="<?= e(t(':name will be signed out and cannot sign in. Their work, grades and class history are kept, and you can reactivate the account later.', ['name' => user_name($person)])) ?>" data-confirm-title="<?= e(t('Deactivate account?')) ?>" data-confirm-action="<?= e(t('Deactivate')) ?>" data-confirm-tone="danger">
              <?= csrf_field() ?><input type="hidden" name="action" value="deactivate">
              <button class="btn btn--danger-quiet btn--block" type="submit"><?= icon('user-x', 'icon--sm') ?><?= e(t('Deactivate account')) ?></button></form>
          <?php endif; ?>
        </div>
        <?php if ($lastAdmin && !$isSelf): ?><p class="meta" style="margin-top: 8px"><?= e(t('This is the only active administrator, so it cannot be deactivated.')) ?></p><?php endif; ?>
      </section>
    <?php endif; ?>
    <?php if ($person['role'] === 'student' && !$erased): ?>
      <?= partial('student_ai_panel', ['student' => $person, 'answers' => $guardianAnswers, 'action' => $base]) ?>
    <?php endif; ?>
    <section class="panel panel--sunken" aria-labelledby="priv-title">
      <h2 class="panel__title" id="priv-title"><?= e(t('Privacy')) ?></h2>
      <p class="meta" style="margin:0"><?= e(t('Requests to export, correct or delete this person\'s data are handled in Privacy and safety, where every step is recorded.')) ?></p>
      <a class="btn btn--quiet btn--sm" href="<?= e(url('/admin/privacy')) ?>"><?= e(t('Privacy and safety')) ?></a>
    </section>
  </aside>
</div>
