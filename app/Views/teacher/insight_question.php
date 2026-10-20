<?php /** @var array $class @var array $q @var array $options @var array $key @var list<array> $rows */
$correct = count(array_filter($rows, static fn ($r) => (int) $r['is_correct'] === 1));
$optText = static function (string $k) use ($options): string { foreach ($options as $o) { if ($o['key'] === $k) { return $k . '. ' . $o['text']; } } return $k; };
$tally = [];
foreach ($rows as $r) {
    $label = $q['qtype'] === 'multiple_choice' ? $optText((string) $r['response']) : ($q['qtype'] === 'true_false' ? ((string) $r['response'] === 'true' ? t('True') : ((string) $r['response'] === 'false' ? t('False') : t('No answer'))) : mb_strtolower(trim((string) $r['response'])));
    $label = $label === '' ? t('No answer') : $label;
    $tally[$label] = ($tally[$label] ?? 0) + 1;
}
arsort($tally);
?>
<div class="page-head"><div class="page-head__text">
  <h1 class="page-title"><?= e(t('Question results')) ?></h1>
  <p class="page-lead"><?= e($q['assignment_title']) ?>. <?= e(t(':c of :n answers correct.', ['c' => $correct, 'n' => count($rows)])) ?></p>
</div></div>
<div class="layout-split">
  <div class="stack">
    <section class="section"><div class="prose"><?= markdown($q['prompt']) ?></div>
      <p class="meta"><?= e(t('Correct answer:')) ?> <?= e($q['qtype'] === 'multiple_choice' ? $optText((string) ($key['correct'] ?? '')) : ($q['qtype'] === 'true_false' ? (!empty($key['correct']) ? t('True') : t('False')) : implode(' / ', (array) ($key['accepted'] ?? [])))) ?></p></section>
    <section class="section" aria-labelledby="dist-title">
      <div class="section__head"><h2 class="section__title" id="dist-title"><?= e(t('How students answered')) ?></h2></div>
      <ul class="bar-list">
        <?php foreach ($tally as $label => $n): ?>
          <li><span class="bar-list__label"><?= e(str_limit((string) $label, 80)) ?></span><?= partial('meter', ['value' => percent($n, count($rows)), 'label' => fmt_number(percent($n, count($rows))) . '%']) ?><span class="bar-list__value"><?= e(tp(':count answer', ':count answers', $n)) ?></span></li>
        <?php endforeach; ?>
      </ul>
    </section>
    <section class="section" aria-labelledby="who-title">
      <div class="section__head"><h2 class="section__title" id="who-title"><?= e(t('Individual answers')) ?></h2></div>
      <p class="meta"><?= e(t('Visible to you because you teach this class. Use it to follow up, not to rank students.')) ?></p>
      <div class="table-frame">
        <table class="table table--stack">
          <thead><tr><th scope="col"><?= e(t('Student')) ?></th><th scope="col"><?= e(t('Answer')) ?></th><th scope="col"><?= e(t('Result')) ?></th></tr></thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td class="cell-primary"><a href="<?= e(url('/teacher/submissions/' . (int) $r['submission_id'])) ?>"><?= e(user_name($r)) ?></a><?php if ((int) $r['attempt_no'] > 1): ?><span class="table-sub"><?= e(t('attempt :n', ['n' => (int) $r['attempt_no']])) ?></span><?php endif; ?></td>
                <td data-label="<?= e(t('Answer')) ?>"><?= e(str_limit($q['qtype'] === 'multiple_choice' ? $optText((string) $r['response']) : (string) $r['response'], 80)) ?></td>
                <td data-label="<?= e(t('Result')) ?>"><?= (int) $r['is_correct'] ? badge(t('Correct'), 'leaf', 'check') : badge(t('Not correct'), 'coral', 'x') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</div>
