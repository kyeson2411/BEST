<?php
/** @var array $item site_feedback row with the sender's name */
use Sibol\Services\SiteFeedback;

$id = (int) $item['id'];
[$sLabel, $sTone] = SiteFeedback::statusBadge((string) $item['status']);
$known = $item['given_name'] !== null && $item['anonymized_at'] === null;
$statusHints = [
    'new' => t('Nobody has looked at it yet.'),
    'looking' => t('Someone at school is checking it.'),
    'fixed' => t('The problem is solved or the idea was used.'),
    'wont_fix' => t('It will not be changed, for now or at all. Say why in the reply.'),
];
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(SiteFeedback::categoryLabel((string) $item['category'])) ?></h1>
    <p class="page-lead"><?= e($known ? user_name($item) : t('Former account')) ?>, <?= e(lcfirst_u(role_label((string) $item['user_role']))) ?><?= $known && $item['grade_level'] ? ', ' . e((string) $item['grade_level']) : '' ?>. <?= e(t('Sent :date', ['date' => fmt_date($item['created_at'], 'datetime')])) ?> <?= badge($sLabel, $sTone) ?></p>
  </div>
  <?php if ($known): ?><div class="page-actions"><a class="btn btn--secondary" href="<?= e(url('/admin/users/' . (int) $item['user_id'])) ?>"><?= icon('user', 'icon--sm') ?><?= e(t('Account')) ?></a></div><?php endif; ?>
</div>

<div class="layout-split">
  <section class="stack" aria-labelledby="said-title">
    <h2 class="section__title" id="said-title"><?= e(t('What they wrote')) ?></h2>
    <blockquote class="quote-block feedback-text"><?= e((string) $item['body']) ?></blockquote>
    <dl class="dl">
      <dt><?= e(t('Page')) ?></dt><dd><?= $item['page_path'] ? '<span class="code-text">' . e((string) $item['page_path']) . '</span>' : e(t('Not included')) ?></dd>
      <?php if ($item['replied_at']): ?><dt><?= e(t('Replied')) ?></dt><dd><?= time_tag($item['replied_at'], 'datetime') ?></dd><?php endif; ?>
    </dl>
    <p class="meta"><?= e(t('The page is where they were when they opened "Send feedback". B.E.S.T. Learning and Interactive Study System keeps only its address, never what was on it.')) ?></p>
  </section>

  <aside>
    <form method="post" action="<?= e(url('/admin/feedback/' . $id)) ?>" class="panel" aria-labelledby="status-title" data-unsaved-warning>
      <?= csrf_field() ?>
      <h2 class="panel__title" id="status-title"><?= e(t('Status and reply')) ?></h2>
      <fieldset class="field" style="border:0; padding:0; margin:0 0 16px">
        <legend class="field__label"><?= e(t('Status')) ?></legend>
        <div class="choice-list">
          <?php foreach (SiteFeedback::STATUSES as $s): [$label] = SiteFeedback::statusBadge($s); ?>
            <label class="choice"><input type="radio" name="status" value="<?= e($s) ?>"<?= checked(old('status', (string) $item['status']) === $s) ?>>
              <span><span class="choice__title"><?= e($label) ?></span><span class="choice__desc"><?= e($statusHints[$s]) ?></span></span></label>
          <?php endforeach; ?>
        </div>
        <?php if (field_error('status')): ?><p class="field__error"><?= icon('circle-alert', 'icon--xs') ?><?= e(field_error('status')) ?></p><?php endif; ?>
      </fieldset>
      <?= field('textarea', 'reply', t('Reply'), ['value' => (string) ($item['reply'] ?? ''), 'rows' => 4, 'optional_label' => true, 'hint' => $known ? t('A short note, sent to them as a notification when you save a new or changed reply. They cannot answer it; this is not a chat.') : t('This account no longer exists, so a reply cannot reach anyone.'), 'attrs' => ['maxlength' => SiteFeedback::MAX_REPLY, 'data-charcount' => true]]) ?>
      <button class="btn btn--primary" type="submit" data-busy="<?= e(t('Saving…')) ?>"><?= e(t('Save')) ?></button>
    </form>
  </aside>
</div>
