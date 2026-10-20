<?php /** @var array $set @var list<array> $items @var array|null $results */
$done = $results !== null;
$sourceLine = static function (?array $ref): string {
    if (!$ref) {
        return '';
    }
    return '<span class="meta">' . icon('quote', 'icon--xs') . ' ' . e(t('From :title', ['title' => $ref['title']])) . ($ref['where'] !== '' ? ', ' . e($ref['where']) : '') . '</span>';
};
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e($set['title']) ?></h1>
    <p class="page-lead">
      <?= e($set['class_name'] ?? '') ?>.
      <?php if ($set['generator'] === 'demo'): ?><?= e(t('Made in demo mode from sentences in your materials, without an AI model.')) ?><?php else: ?><?= e(t('Made by AI from your class materials. If something looks wrong, check the source.')) ?><?php endif; ?>
    </p>
  </div>
  <div class="page-actions">
    <form method="post" action="<?= e(url('/student/learning/' . (int) $set['id'] . '/delete')) ?>" data-confirm="<?= e(t('This practice set and its results will be removed.')) ?>" data-confirm-title="<?= e(t('Delete practice set?')) ?>" data-confirm-action="<?= e(t('Delete')) ?>" data-confirm-tone="danger">
      <?= csrf_field() ?><button class="btn btn--danger-quiet" type="submit"><?= icon('trash-2', 'icon--sm') ?><?= e(t('Delete')) ?></button>
    </form>
  </div>
</div>

<?php if ($set['kind'] === 'quiz'): ?>
  <form method="post" action="<?= e(url('/student/learning/' . (int) $set['id'] . '/results')) ?>" style="max-width: 820px">
    <?= csrf_field() ?>
    <ol class="questions">
      <?php foreach ($items as $i => $item): $r = $results[$i] ?? null; ?>
        <li class="question">
          <fieldset>
            <legend class="question__head" style="width:100%">
              <span class="question__no" aria-hidden="true"></span>
              <span class="question__prompt"><?= markdown($item['question']) ?></span>
            </legend>
            <div class="question__body">
              <?php if (($item['type'] ?? 'mcq') === 'mcq'): ?>
                <div class="choice-list">
                  <?php foreach ($item['choices'] as $ci => $choice):
                      $state = '';
                      if ($done) {
                          $state = (int) $item['answer'] === $ci ? ' is-correct' : ((string) ($r['given'] ?? '') === (string) $ci ? ' is-wrong' : '');
                      }
                  ?>
                    <label class="choice<?= $state ?>">
                      <input type="radio" name="answers[<?= (int) $i ?>]" value="<?= (int) $ci ?>"<?= checked($done && (string) ($r['given'] ?? '') === (string) $ci) ?><?= $done ? ' disabled' : '' ?>>
                      <span><?= e($choice) ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <label class="sr-only" for="p-<?= (int) $i ?>"><?= e(t('Your answer')) ?></label>
                <input class="input" id="p-<?= (int) $i ?>" name="answers[<?= (int) $i ?>]" value="<?= e($r['given'] ?? '') ?>"<?= $done ? ' readonly' : '' ?> style="max-width: 360px">
              <?php endif; ?>
              <?php if ($done): ?>
                <div class="question__feedback">
                  <p style="margin:0 0 4px"><?= !empty($r['correct']) ? badge(t('Correct'), 'leaf', 'check') : badge(t('Not quite'), 'coral', 'x') ?>
                    <?php if (($item['type'] ?? 'mcq') !== 'mcq' && empty($r['correct'])): ?> <?= e(t('Answer: :a', ['a' => $item['answer_text']])) ?><?php endif; ?></p>
                  <?php if (!empty($item['explanation'])): ?><div><?= markdown($item['explanation']) ?></div><?php endif; ?>
                  <?= $sourceLine($item['source_ref'] ?? null) ?>
                </div>
              <?php endif; ?>
            </div>
          </fieldset>
        </li>
      <?php endforeach; ?>
    </ol>
    <div class="form-actions">
      <?php if (!$done): ?>
        <button class="btn btn--primary" type="submit" data-busy="<?= e(t('Checking…')) ?>"><?= e(t('Check my answers')) ?></button>
      <?php else: ?>
        <span class="score"><strong><?= (int) $set['score'] ?></strong><span class="score__of">/<?= (int) $set['total'] ?></span></span>
        <a class="btn btn--secondary" href="<?= e(url('/student/learning')) ?>"><?= e(t('Back to practice')) ?></a>
        <a class="btn btn--quiet" href="<?= e(url('/companion/new', ['mode' => 'explain', 'class_id' => (int) $set['class_id'], 'topic' => $set['topic'] ?: $set['title']])) ?>"><?= e(t('Ask the companion to explain')) ?></a>
      <?php endif; ?>
    </div>
  </form>
<?php else: ?>
  <form method="post" action="<?= e(url('/student/learning/' . (int) $set['id'] . '/results')) ?>" data-flashcards style="max-width: 720px"
        data-i18n="<?= json_attr(\Sibol\Services\ScriptStrings::flashcards()) ?>">
    <?= csrf_field() ?>
    <p class="meta" data-card-progress><?= e(t('Card :n of :total', ['n' => 1, 'total' => count($items)])) ?></p>
    <?php foreach ($items as $i => $card): ?>
      <div class="flashcard-wrap" data-card="<?= (int) $i ?>" style="margin-bottom: 24px">
        <button type="button" class="flashcard" data-flip aria-live="polite">
          <span><span class="flashcard__side" data-side><?= e(t('Front')) ?></span><span data-front><?= e($card['front']) ?></span><span data-back hidden><?= e($card['back']) ?></span></span>
        </button>
        <p style="margin:8px 0 0"><?= $sourceLine($card['source_ref'] ?? null) ?></p>
        <div class="cluster" style="margin-top: 12px">
          <button type="button" class="btn btn--secondary" data-again hidden><?= icon('rotate-ccw', 'icon--sm') ?><?= e(t('Not yet')) ?></button>
          <button type="button" class="btn btn--primary" data-known hidden><?= icon('check', 'icon--sm') ?><?= e(t('I knew this')) ?></button>
          <label class="check" data-known-label><input class="check__input" type="checkbox" name="known[]" value="<?= (int) $i ?>" data-known-box<?= checked($done && !empty($results[$i]['known'])) ?>><span class="check__label"><?= e(t('I knew this card')) ?></span></label>
        </div>
      </div>
    <?php endforeach; ?>
    <div data-cards-done hidden>
      <p class="callout callout--leaf"><?= icon('circle-check') ?><span data-done-text><?= e(t('You reached the end of the deck.')) ?></span></p>
      <button class="btn btn--primary" type="submit"><?= e(t('Save my results')) ?></button>
    </div>
    <noscript>
      <p class="meta"><?= e(t('Tick the cards you knew, then save.')) ?></p>
      <button class="btn btn--primary" type="submit"><?= e(t('Save my results')) ?></button>
    </noscript>
    <?php if ($done): ?><p class="meta" style="margin-top:16px"><?= e(t('Last time you knew :n of :total cards.', ['n' => (int) $set['score'], 'total' => (int) $set['total']])) ?></p><?php endif; ?>
  </form>
<?php endif; ?>
