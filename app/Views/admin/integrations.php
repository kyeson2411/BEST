<?php /** @var list<array> $checks @var array $ai @var array $mail @var array $google @var array $user */
$problems = count(array_filter($checks, static fn ($c) => !$c['ok']));
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Integrations and system checks')) ?></h1>
    <p class="page-lead"><?= e(t('How this copy of B.E.S.T. Learning and Interactive Study System is configured. Secrets such as API keys and database passwords are never shown; change them in the server\'s .env file.')) ?></p>
  </div>
</div>

<div class="layout-split">
  <section class="section" aria-labelledby="checks-title">
    <div class="section__head"><h2 class="section__title" id="checks-title"><?= e(t('System checks')) ?></h2><span class="meta"><?= e($problems ? tp(':count item to review', ':count items to review', $problems) : t('All checks passed')) ?></span></div>
    <ul class="checklist">
      <?php foreach ($checks as $c): ?>
        <li><span class="<?= $c['ok'] ? 'ok' : 'warn' ?>"><?= icon($c['ok'] ? 'circle-check' : 'triangle-alert', 'icon--sm', $c['ok'] ? t('OK') : t('Needs attention')) ?></span>
          <span><strong><?= e($c['label']) ?></strong><br><span class="meta"><?= e($c['detail']) ?></span></span></li>
      <?php endforeach; ?>
    </ul>
    <p class="meta"><?= e(t('The same checks run from the command line with: php bin/sibol.php check')) ?></p>
  </section>

  <aside class="stack">
    <section class="panel" aria-labelledby="ai-int-title">
      <h2 class="panel__title" id="ai-int-title"><?= e(t('AI provider')) ?></h2>
      <dl class="dl">
        <dt><?= e(t('Status')) ?></dt><dd><?= e($ai['label']) ?></dd>
        <dt>AI_PROVIDER</dt><dd><code><?= e($ai['provider']) ?></code></dd>
        <?php if ($ai['provider'] !== 'demo'): ?>
          <dt>AI_MODEL</dt><dd><code><?= e($ai['model'] ?: t('(provider default)')) ?></code></dd>
          <dt>AI_API_KEY</dt><dd><?= e($ai['has_key'] ? t('Set (hidden)') : t('Not set')) ?></dd>
          <?php if ($ai['provider'] === 'anthropic'): ?>
            <dt>AI_EFFORT</dt><dd><code><?= e($ai['effort']) ?></code></dd>
            <dt>AI_FALLBACKS</dt><dd><code><?= e($ai['fallbacks']) ?></code></dd>
          <?php endif; ?>
          <?php if ($ai['base_url_host']): ?><dt>AI_BASE_URL</dt><dd><?= e($ai['base_url_host']) ?></dd><?php endif; ?>
          <dt>AI_TIMEOUT_SECONDS</dt><dd><?= (int) $ai['timeout'] ?></dd>
        <?php endif; ?>
      </dl>
      <p class="meta"><?= e(t('Requests to the AI provider are made only from the server. Students\' names and emails are never sent.')) ?></p>
    </section>
    <section class="panel" aria-labelledby="mail-title">
      <h2 class="panel__title" id="mail-title"><?= e(t('Email')) ?></h2>
      <?php if (!$mail['enabled']): ?>
        <p class="meta" style="margin:0"><?= e(t('Not connected. Invitation and password links are shown to administrators to share. In-app notifications work without email. To send email, set MAIL_HOST and the other MAIL_ settings in .env.')) ?></p>
      <?php else: ?>
        <dl class="dl">
          <dt><?= e(t('Status')) ?></dt><dd><?= e(t('Connected: invitation and password links are emailed, and people can turn on a daily summary.')) ?></dd>
          <dt>MAIL_HOST</dt><dd><code><?= e($mail['host'] . ':' . $mail['port']) ?></code> (<?= e($mail['encryption']) ?>)</dd>
          <dt>MAIL_USERNAME</dt><dd><?= e($mail['username'] !== '' ? $mail['username'] : t('Not set')) ?></dd>
          <dt>MAIL_PASSWORD</dt><dd><?= e($mail['has_password'] ? t('Set (hidden)') : t('Not set')) ?></dd>
          <dt><?= e(t('Sender')) ?></dt><dd><?= e($mail['from']) ?></dd>
        </dl>
        <?php if ((string) ($user['email'] ?? '') !== ''): ?>
          <form method="post" action="<?= e(url('/admin/integrations/test-email')) ?>">
            <?= csrf_field() ?><button class="btn btn--secondary btn--sm" type="submit"><?= icon('mail', 'icon--sm') ?><?= e(t('Send a test email to :address', ['address' => $user['email']])) ?></button>
          </form>
        <?php endif; ?>
        <p class="meta"><?= e(t('Links are still shown to copy, in case an email does not arrive. The password is never shown here or written to the logs.')) ?></p>
      <?php endif; ?>
    </section>
    <section class="panel" aria-labelledby="google-title">
      <h2 class="panel__title" id="google-title"><?= e(t('Google sign-in')) ?></h2>
      <dl class="dl dl--stack">
        <dt><?= e(t('Status')) ?></dt><dd><?= e($google['configured'] ? t('On: people with a B.E.S.T. Learning and Interactive Study System account on :domains can sign in with Google.', ['domains' => implode(', ', $google['domains'])]) : t('Off')) ?></dd>
        <dt>GOOGLE_CLIENT_ID</dt><dd style="overflow-wrap:anywhere"><?= $google['client_id'] !== '' ? '<code>' . e($google['client_id']) . '</code>' : e(t('Not set')) ?></dd>
        <dt>GOOGLE_CLIENT_SECRET</dt><dd><?= e($google['has_secret'] ? t('Set (hidden)') : t('Not set')) ?></dd>
        <dt>GOOGLE_WORKSPACE_DOMAINS</dt><dd><?= e($google['domains'] ? implode(', ', $google['domains']) : t('Not set')) ?></dd>
      </dl>
      <p class="meta" style="margin-bottom:4px"><?= e(t('Authorized redirect URI to enter in Google Cloud:')) ?></p>
      <p class="code-text" id="google-redirect" style="overflow-wrap:anywhere; margin:0 0 8px"><?= e($google['redirect_uri']) ?></p>
      <button type="button" class="btn btn--secondary btn--sm" data-copy="#google-redirect"><?= icon('copy', 'icon--sm') ?><?= e(t('Copy address')) ?></button>
      <p class="meta"><?= e(t('Only existing accounts can sign in this way, found by their email; roles stay as they are here, and two-step sign-in still asks for its code. The README explains how to create the client in Google Cloud.')) ?></p>
    </section>
  </aside>
</div>
