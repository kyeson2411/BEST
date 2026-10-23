<?php
/**
 * @var array|null $active @var list<array> $messages @var list<string> $modes @var list<array> $conversations @var bool $showArchived
 * @var list<array> $classes @var list<array> $materials @var int|null $remaining @var string $demoLabel @var bool $canDelete
 * @var bool $canShare @var int $retentionDays @var array|null $defaults @var string|null $pending @var string|null $practiceCards
 */
use Sibol\Core\Dates;
use Sibol\Services\Settings;

$user = auth_user();
$isStudent = $user['role'] === 'student';
$practiceCards = $practiceCards ?? null;
// Flashcards are made in Practice, so that card is a link there instead of a kind of chat.
$practiceCard = static function (string $path, bool $followsForm): string {
    return '<a class="mode-option" href="' . e(url($path)) . '"' . ($followsForm ? ' data-practice-link' : '') . '>'
        . '<span class="mode-option__title">' . icon('layers', 'icon--sm') . e(Settings::modeLabel('flashcards'))
        . '<span class="mode-option__go">' . icon('arrow-right', 'icon--sm') . '</span></span>'
        . '<span class="mode-option__desc">' . e(Settings::modeDescription('flashcards')) . '</span></a>';
};
$groups = [];
$todayStart = Dates::startOfTodayUtc();
$weekStart = gmdate('Y-m-d H:i:s', strtotime($todayStart . ' UTC') - 6 * 86400);
foreach ($conversations as $c) {
    $key = $showArchived ? t('Archived') : ($c['last_message_at'] >= $todayStart ? t('Today') : ($c['last_message_at'] >= $weekStart ? t('This week') : t('Earlier')));
    $groups[$key][] = $c;
}
$modeIcons = ['explain' => 'lightbulb', 'explain_back' => 'message-square-quote', 'socratic' => 'list-ordered', 'practice' => 'list-checks', 'flashcards' => 'layers', 'summarize' => 'file-text', 'study_plan' => 'calendar-plus', 'vocabulary' => 'languages', 'ask_materials' => 'quote',
    'lesson_ideas' => 'notebook-pen', 'explain_for_class' => 'school', 'examples' => 'list-ordered', 'differentiate' => 'shuffle', 'feedback_helper' => 'message-circle'];
?>
<div class="companion" data-companion data-i18n="<?= json_attr(\Sibol\Services\ScriptStrings::companion()) ?>">
  <aside class="companion__sessions" id="sessions" aria-label="<?= e(t('Your conversations')) ?>">
    <div class="companion__sessions-head">
      <div class="cluster cluster--between" style="margin-bottom: 12px">
        <h1 class="display" style="font-size:1.375rem; margin:0"><?= e(t('Companion')) ?></h1>
        <button type="button" class="icon-btn sessions-toggle" data-sessions-close aria-label="<?= e(t('Close conversations')) ?>"><?= icon('x') ?></button>
      </div>
      <a class="btn btn--primary btn--block" href="<?= e(url('/companion/new')) ?>"><?= icon('plus', 'icon--sm') ?><?= e(t('New session')) ?></a>
    </div>
    <nav class="companion__sessions-list" aria-label="<?= e(t('Conversations')) ?>">
      <?php if (!$conversations): ?>
        <p class="meta" style="padding: 8px 10px"><?= e($showArchived ? t('No archived conversations.') : t('Your conversations will be listed here. Only you can see them.')) ?></p>
      <?php endif; ?>
      <?php foreach ($groups as $label => $items): ?>
        <p class="session-group"><?= e($label) ?></p>
        <?php foreach ($items as $c): ?>
          <a class="session-link" href="<?= e(url('/companion/' . (int) $c['id'])) ?>"<?= $active && (int) $active['id'] === (int) $c['id'] ? ' aria-current="page"' : '' ?>>
            <span class="session-link__title"><?= e($c['title']) ?></span>
            <span class="session-link__meta"><?= e(Settings::modeLabel((string) $c['mode'])) ?>, <?= e(fmt_rel($c['last_message_at'])) ?></span>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
      <p style="padding: 12px 10px 0; margin: 0">
        <a class="meta" href="<?= e(url('/companion', $showArchived ? [] : ['archived' => 1])) ?>"><?= e($showArchived ? t('Show active conversations') : t('Show archived conversations')) ?></a>
      </p>
    </nav>
  </aside>

  <section class="thread" aria-labelledby="thread-title">
    <?php if ($active): ?>
      <div class="thread__context">
        <button type="button" class="btn btn--quiet btn--sm sessions-toggle" data-sessions-open aria-controls="sessions"><?= icon('history', 'icon--sm') ?><?= e(t('Sessions')) ?></button>
        <h2 class="thread__title" id="thread-title"><?= e($active['title']) ?></h2>
        <div class="cluster">
          <?= badge(Settings::modeLabel((string) $active['mode']), 'outline', $modeIcons[$active['mode']] ?? null) ?>
          <?php if ($active['class_name']): ?><?= badge($active['class_name'], 'neutral', 'school') ?><?php endif; ?>
          <?php if ($active['material_title']): ?><?= badge($active['material_title'], 'neutral', 'file-text') ?><?php endif; ?>
          <?php if ($active['archived_at']): ?><?= badge(t('Archived'), 'amber') ?><?php endif; ?>
          <details class="menu" data-menu>
            <summary class="icon-btn" aria-label="<?= e(t('Conversation options')) ?>"><?= icon('ellipsis') ?></summary>
            <div class="menu__panel">
              <button type="button" class="menu__item" data-dialog-open="#rename-dialog"><?= icon('pencil', 'icon--sm') ?><?= e(t('Rename')) ?></button>
              <button type="button" class="menu__item" data-dialog-open="#mode-dialog"><?= icon('sliders-horizontal', 'icon--sm') ?><?= e(t('Change mode')) ?></button>
              <?php if ($active['archived_at']): ?>
                <form method="post" action="<?= e(url('/companion/' . (int) $active['id'] . '/restore')) ?>"><?= csrf_field() ?><button class="menu__item" type="submit"><?= icon('archive-restore', 'icon--sm') ?><?= e(t('Restore')) ?></button></form>
              <?php else: ?>
                <form method="post" action="<?= e(url('/companion/' . (int) $active['id'] . '/archive')) ?>"><?= csrf_field() ?><button class="menu__item" type="submit"><?= icon('archive', 'icon--sm') ?><?= e(t('Archive')) ?></button></form>
              <?php endif; ?>
              <?php if ($canDelete): ?>
                <div class="menu__sep"></div>
                <form method="post" action="<?= e(url('/companion/' . (int) $active['id'] . '/delete')) ?>" data-confirm="<?= e(t('The whole conversation is deleted permanently. This cannot be undone.')) ?>" data-confirm-title="<?= e(t('Delete this conversation?')) ?>" data-confirm-action="<?= e(t('Delete')) ?>" data-confirm-tone="danger">
                  <?= csrf_field() ?><button class="menu__item menu__item--danger" type="submit"><?= icon('trash-2', 'icon--sm') ?><?= e(t('Delete permanently')) ?></button>
                </form>
              <?php endif; ?>
            </div>
          </details>
        </div>
      </div>

      <div class="thread__scroll" id="thread-scroll">
        <p class="thread__notice"><?= icon('info', 'icon--sm') ?><span>
          <?= e(t('AI can make mistakes. Your teacher\'s instructions come first.')) ?>
          <?= e($isStudent ? t('This conversation is private to you and is deleted after :days days.', ['days' => $retentionDays]) : t('This conversation is private to you.')) ?>
        </span></p>
        <div id="exchanges">
          <?php
          $lastAssistantId = 0;
          foreach ($messages as $m) {
              if ($m['role'] === 'assistant') {
                  $lastAssistantId = (int) $m['id'];
              }
          }
          $open = false;
          foreach ($messages as $m):
              if ($m['role'] === 'user'):
                  if ($open) { echo '</div>'; }
                  $open = true; ?>
              <div class="exchange">
                <div class="msg msg--user">
                  <div class="msg__who"><?= partial('avatar', ['person' => $user, 'size' => 'sm']) ?><span><?= e(t('You')) ?></span></div>
                  <div class="msg__body"><?= e($m['content']) ?></div>
                </div>
              <?php else:
                  if (!$open) { echo '<div class="exchange">'; $open = true; } ?>
                <?= \Sibol\Core\View::file('companion/_exchange_answer', ['message' => $m, 'user' => $user, 'isLast' => (int) $m['id'] === $lastAssistantId, 'conversation' => $active]) ?>
              <?php endif;
          endforeach;
          if ($open) { echo '</div>'; } ?>
        </div>
        <?php if (!$messages): ?>
          <div class="empty" id="thread-empty">
            <h3 class="empty__title"><?= e(Settings::modeLabel((string) $active['mode'])) ?></h3>
            <p class="empty__body"><?= e(Settings::modeDescription((string) $active['mode'])) ?></p>
            <?php if (Settings::modeQuestion((string) $active['mode']) !== ''): ?>
              <p class="meta"><?= e(Settings::modeQuestion((string) $active['mode'])) ?> <?= e(Settings::modeExample((string) $active['mode'])) ?></p>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>

      <?php if (!$active['archived_at']): ?>
        <form class="composer" id="composer" method="post" action="<?= e(url('/companion/' . (int) $active['id'] . '/messages')) ?>"
              data-regenerate-url="<?= e(url('/companion/' . (int) $active['id'] . '/regenerate')) ?>" data-pending="<?= e($pending ?? '') ?>" data-no-busy>
          <?= csrf_field() ?>
          <div class="composer__box">
            <label class="sr-only" for="composer-input"><?= e(t('Message')) ?></label>
            <textarea class="input composer__input" id="composer-input" name="message" rows="1" maxlength="<?= (int) setting('ai.max_question_chars', 2000) ?>"
                      placeholder="<?= e($isStudent ? t('Ask a question or describe what you are stuck on…') : t('Describe what you need…')) ?>" required></textarea>
            <button class="btn btn--primary" type="submit" data-send><?= icon('send-horizontal', 'icon--sm') ?><span class="sr-only"><?= e(t('Send')) ?></span></button>
            <button class="btn btn--secondary" type="button" data-stop hidden><?= icon('square', 'icon--sm') ?><?= e(t('Stop')) ?></button>
          </div>
          <div class="composer__foot">
            <span><?= e(t('Enter to send, Shift+Enter for a new line.')) ?></span>
            <span><?php if ($demoLabel !== ''): ?><?= e($demoLabel) ?><?= $remaining !== null ? '. ' : '' ?><?php endif; ?><?php if ($remaining !== null): ?><span data-remaining><?= e(tp(':count message left today', ':count messages left today', $remaining)) ?></span><?php endif; ?></span>
          </div>
          <p class="sr-only" role="status" aria-live="polite" data-composer-status></p>
        </form>
      <?php else: ?>
        <div class="composer"><p class="meta" style="margin:0"><?= e(t('This conversation is archived. Restore it to continue.')) ?></p></div>
      <?php endif; ?>

      <dialog class="dialog" id="rename-dialog" aria-labelledby="rename-title">
        <form method="post" action="<?= e(url('/companion/' . (int) $active['id'] . '/rename')) ?>">
          <?= csrf_field() ?>
          <div class="dialog__body">
            <h2 class="dialog__title" id="rename-title"><?= e(t('Rename conversation')) ?></h2>
            <?= field('text', 'title', t('Title'), ['value' => $active['title'], 'required' => true, 'attrs' => ['maxlength' => 160]]) ?>
          </div>
          <div class="dialog__actions">
            <button type="button" class="btn btn--secondary" data-dialog-close><?= e(t('Cancel')) ?></button>
            <button type="submit" class="btn btn--primary"><?= e(t('Save name')) ?></button>
          </div>
        </form>
      </dialog>

      <dialog class="dialog dialog--wide" id="mode-dialog" aria-labelledby="mode-title">
        <form method="post" action="<?= e(url('/companion/' . (int) $active['id'] . '/mode')) ?>">
          <?= csrf_field() ?>
          <div class="dialog__body">
            <h2 class="dialog__title" id="mode-title"><?= e(t('Change mode')) ?></h2>
            <div class="mode-grid">
              <?php foreach ($modes as $mode): ?>
                <?php if ($mode === 'flashcards'): echo $practiceCards !== null ? $practiceCard($practiceCards, false) : ''; continue; endif; ?>
                <label class="mode-option">
                  <input type="radio" name="mode" value="<?= e($mode) ?>"<?= checked($mode === $active['mode']) ?>>
                  <span class="mode-option__title"><?= icon($modeIcons[$mode] ?? 'circle-dot', 'icon--sm') ?><?= e(Settings::modeLabel($mode)) ?></span>
                  <span class="mode-option__desc"><?= e(Settings::modeDescription($mode)) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="dialog__actions">
            <button type="button" class="btn btn--secondary" data-dialog-close><?= e(t('Cancel')) ?></button>
            <button type="submit" class="btn btn--primary"><?= e(t('Use this mode')) ?></button>
          </div>
        </form>
      </dialog>

      <dialog class="dialog" id="report-dialog" aria-labelledby="report-title">
        <form method="post" action="" data-report-form>
          <?= csrf_field() ?>
          <div class="dialog__body">
            <h2 class="dialog__title" id="report-title"><?= e(t('Report this answer')) ?></h2>
            <p class="dialog__text"><?= e(t('This answer and your note will be sent to your school\'s administrators so they can look into it. The rest of the conversation is not sent.')) ?></p>
            <fieldset style="margin-top:16px">
              <legend class="field__label"><?= e(t('What is wrong?')) ?></legend>
              <?php foreach (['incorrect' => t('It is incorrect'), 'unsafe' => t('It is unsafe or inappropriate'), 'not_helpful' => t('It is not helpful'), 'privacy' => t('It reveals private information'), 'other' => t('Something else')] as $value => $label): ?>
                <div class="check"><input class="check__input" type="radio" id="reason-<?= e($value) ?>" name="reason" value="<?= e($value) ?>"<?= checked($value === 'incorrect') ?>><label class="check__label" for="reason-<?= e($value) ?>"><?= e($label) ?></label></div>
              <?php endforeach; ?>
            </fieldset>
            <?= field('textarea', 'note', t('Anything else we should know?'), ['rows' => 3, 'attrs' => ['maxlength' => 1000], 'optional_label' => true]) ?>
          </div>
          <div class="dialog__actions">
            <button type="button" class="btn btn--secondary" data-dialog-close><?= e(t('Cancel')) ?></button>
            <button type="submit" class="btn btn--primary"><?= e(t('Send report')) ?></button>
          </div>
        </form>
      </dialog>

      <?php if ($canShare && $active['class_id']): ?>
        <dialog class="dialog" id="share-dialog" aria-labelledby="share-title">
          <form method="post" action="" data-share-form>
            <?= csrf_field() ?>
            <div class="dialog__body">
              <h2 class="dialog__title" id="share-title"><?= e(t('Share with your teacher')) ?></h2>
              <p class="dialog__text"><?= e(t('Your teacher in :class will see this answer and your note. Nothing else from the conversation is shared.', ['class' => $active['class_name']])) ?></p>
              <?= field('textarea', 'note', t('Note for your teacher'), ['rows' => 3, 'attrs' => ['maxlength' => 1000], 'optional_label' => true, 'hint' => t('For example, what you still find confusing.')]) ?>
            </div>
            <div class="dialog__actions">
              <button type="button" class="btn btn--secondary" data-dialog-close><?= e(t('Cancel')) ?></button>
              <button type="submit" class="btn btn--primary"><?= e(t('Share')) ?></button>
            </div>
          </form>
        </dialog>
      <?php endif; ?>

    <?php else: ?>
      <div class="thread__scroll">
        <div class="cluster cluster--between" style="margin-bottom: 8px">
          <button type="button" class="btn btn--quiet btn--sm sessions-toggle" data-sessions-open aria-controls="sessions"><?= icon('history', 'icon--sm') ?><?= e(t('Sessions')) ?></button>
        </div>
        <h2 class="page-title" id="thread-title"><?= e($isStudent ? t('What would you like to work on?') : t('What are you preparing?')) ?></h2>
        <p class="page-lead" style="margin-bottom: 24px"><?= e($isStudent ? t('Choose how the companion should help. It explains and guides; it will not do graded work for you.') : t('Drafts are for you to review and adapt. Nothing is shared with students.')) ?></p>
        <?php $startMode = (string) (old('mode') ?: $defaults['mode']); ?>
        <form method="post" action="<?= e(url('/companion')) ?>" style="max-width: 920px">
          <?= csrf_field() ?>
          <fieldset>
            <legend><?= e(t('Mode')) ?></legend>
            <div class="mode-grid">
              <?php foreach ($modes as $mode): ?>
                <?php if ($mode === 'flashcards'): echo $practiceCards !== null ? $practiceCard($practiceCards, true) : ''; continue; endif; ?>
                <label class="mode-option">
                  <input type="radio" name="mode" value="<?= e($mode) ?>"<?= checked($mode === $startMode) ?> data-ask="<?= e(Settings::modeQuestion($mode)) ?>" data-example="<?= e(Settings::modeExample($mode)) ?>">
                  <span class="mode-option__title"><?= icon($modeIcons[$mode] ?? 'circle-dot', 'icon--sm') ?><?= e(Settings::modeLabel($mode)) ?></span>
                  <span class="mode-option__desc"><?= e(Settings::modeDescription($mode)) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
            <?php if (field_error('mode')): ?><p class="field__error"><?= e(field_error('mode')) ?></p><?php endif; ?>
          </fieldset>
          <div class="form-grid">
            <?php
            $classOptions = ['' => $isStudent ? t('No class: general help') : t('No class')];
            foreach ($classes as $c) { $classOptions[(string) $c['id']] = $c['name']; }
            echo field('select', 'class_id', t('Class'), ['options' => $classOptions, 'value' => (string) ($defaults['class_id'] ?? ''), 'hint' => t('Answers can then use this class\'s released materials, with sources.')]);
            ?>
            <div class="field<?= field_error('material_id') ? ' field--error' : '' ?>">
              <label class="field__label" for="f-material_id"><?= e(t('Focus on one material')) ?> <span class="field__optional"><?= e(t('optional')) ?></span></label>
              <p class="field__hint" id="f-material_id-hint"><?= e(t('Needed for summaries.')) ?></p>
              <select class="input select" id="f-material_id" name="material_id" aria-describedby="f-material_id-hint" data-material-select data-follows="#f-class_id">
                <option value="" data-any="<?= e(t('Any material in the class')) ?>" data-none="<?= e(t('Choose a class first')) ?>"><?= e(t('Any material in the class')) ?></option>
                <?php foreach ($classes as $c): $items = array_filter($materials, static fn ($m) => (int) $m['class_id'] === (int) $c['id']); if (!$items) { continue; } ?>
                  <optgroup label="<?= e($c['name']) ?>" data-class="<?= (int) $c['id'] ?>">
                    <?php foreach ($items as $m): ?>
                      <option value="<?= (int) $m['id'] ?>"<?= selected($m['id'], old('material_id', (string) ($defaults['material_id'] ?? ''))) ?>><?= e($m['title']) ?></option>
                    <?php endforeach; ?>
                  </optgroup>
                <?php endforeach; ?>
              </select>
              <?php if (field_error('material_id')): ?><p class="field__error"><?= e(field_error('material_id')) ?></p><?php endif; ?>
            </div>
            <?= field('select', 'language', t('Language'), ['options' => ['en' => 'English', 'fil' => 'Filipino', 'mixed' => t('Match my language')], 'value' => (string) ($user['language'] ?? 'en')]) ?>
            <?php if ($isStudent): ?>
              <?= field('select', 'level', t('Explanation level'), ['options' => ['' => t('Not set'), 'simpler' => t('Simpler'), 'grade' => t('At my grade level'), 'challenge' => t('More challenging')], 'value' => 'grade']) ?>
            <?php endif; ?>
            <?= field('textarea', 'question', t('Your first message'), [
                'value' => (string) ($defaults['question'] ?? ''), 'rows' => 3, 'wrap_class' => 'span-2', 'optional_label' => true,
                'hint' => Settings::modeQuestion($startMode), 'placeholder' => Settings::modeExample($startMode),
                'attrs' => ['maxlength' => (int) setting('ai.max_question_chars', 2000)],
            ]) ?>
          </div>
          <div class="form-actions">
            <button class="btn btn--primary" type="submit" data-busy="<?= e(t('Starting…')) ?>"><?= e(t('Start session')) ?></button>
            <span class="meta"><?= e(implode('. ', array_filter([$demoLabel, $remaining !== null ? tp(':count message left today', ':count messages left today', $remaining) : '']))) ?></span>
          </div>
        </form>
      </div>
    <?php endif; ?>
  </section>
</div>
