<?php
/**
 * "Send feedback" for students and teachers, with their earlier feedback, its status and any reply.
 * @var string|null $from the page they came from (path only) @var list<array> $mine
 */
use Sibol\Services\SiteFeedback;

$hints = [
    'problem' => t('A page, a button or a file does not work as it should.'),
    'confusing' => t('You were not sure what to do, or something did not make sense.'),
    'idea' => t('Something that would make B.E.S.T. Learning and Interactive Study System more useful for you or your class.'),
];
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Send feedback')) ?></h1>
    <p class="page-lead"><?= e(t('Tell your school when something in B.E.S.T. Learning and Interactive Study System is not working or is confusing, or share an idea. Your school\'s administrators read every message and may reply. It is not a chat, and it is not watched all the time.')) ?></p>
  </div>
</div>

<div class="layout-split">
  <section aria-labelledby="send-title">
    <h2 class="sr-only" id="send-title"><?= e(t('New feedback')) ?></h2>
    <form method="post" action="<?= e(url('/feedback')) ?>" style="max-width: 640px" novalidate>
      <?= csrf_field() ?>
      <?php if ($from !== null): ?><input type="hidden" name="from" value="<?= e($from) ?>"><?php endif; ?>
      <fieldset class="field" style="border:0; padding:0; margin:0 0 20px">
        <legend class="field__label"><?= e(t('What it is about')) ?></legend>
        <div class="choice-list">
          <?php foreach (SiteFeedback::CATEGORIES as $c): ?>
            <label class="choice"><input type="radio" name="category" value="<?= e($c) ?>"<?= checked(old('category') === $c) ?>>
              <span><span class="choice__title"><?= e(SiteFeedback::categoryLabel($c)) ?></span><span class="choice__desc"><?= e($hints[$c]) ?></span></span></label>
          <?php endforeach; ?>
        </div>
        <?php if (field_error('category')): ?><p class="field__error"><?= icon('circle-alert', 'icon--xs') ?><?= e(field_error('category')) ?></p><?php endif; ?>
      </fieldset>
      <?= field('textarea', 'body', t('Your feedback'), ['required' => true, 'rows' => 5, 'hint' => t('What happened, or what would help? A sentence or two is enough.'), 'attrs' => ['maxlength' => SiteFeedback::MAX_TEXT, 'data-charcount' => true]]) ?>
      <?php if ($from !== null): ?>
        <?= checkbox('include_page', t('Include the page you were on'), true, $from) ?>
      <?php endif; ?>
      <p class="meta" style="margin-top: 16px"><?= e(t('Your name is shown to the administrators with your feedback so they can reply. Do not include passwords or other people\'s personal information. Nothing from your companion conversations is attached.')) ?></p>
      <div class="form-actions">
        <button class="btn btn--primary" type="submit" data-busy="<?= e(t('Sending…')) ?>"><?= icon('send', 'icon--sm') ?><?= e(t('Send feedback')) ?></button>
      </div>
    </form>
  </section>

  <aside>
    <section class="section" aria-labelledby="mine-title">
      <div class="section__head"><h2 class="section__title" id="mine-title"><?= e(t('Feedback you sent')) ?></h2></div>
      <?php if (!$mine): ?>
        <p class="meta"><?= e(t('Feedback you send appears here with its status and any reply.')) ?></p>
      <?php else: ?>
        <ul class="entries">
          <?php foreach ($mine as $f): [$label, $tone] = SiteFeedback::statusBadge((string) $f['status']); ?>
            <li class="entry" id="feedback-<?= (int) $f['id'] ?>">
              <?= partial('entry_date', ['at' => $f['created_at']]) ?>
              <div class="entry__main">
                <span class="row__title"><?= e(SiteFeedback::categoryLabel((string) $f['category'])) ?></span>
                <p class="feedback-text"><?= e(str_limit((string) $f['body'], 280)) ?></p>
                <?php if ($f['reply'] !== null): ?>
                  <div class="quote-block feedback-reply">
                    <p class="feedback-reply__head"><?= icon('message-square-reply', 'icon--sm') ?><?= e(t('Reply from your school')) ?><?php if ($f['replied_at']): ?> · <?= time_tag($f['replied_at']) ?><?php endif; ?></p>
                    <p class="feedback-text"><?= e((string) $f['reply']) ?></p>
                  </div>
                <?php endif; ?>
              </div>
              <div class="entry__aside"><?= badge($label, $tone) ?></div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </aside>
</div>
