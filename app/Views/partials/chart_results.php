<?php
/**
 * Released results over time: one series (no legend; the heading names it), 2px line,
 * filled dots with a surface ring, 24px hit targets with hover/focus tooltips,
 * solid hairline grid, and a table view twin.
 * @var list<array{score:mixed,points_possible:mixed,released_at:string,title:string}> $points
 */
$data = array_values(array_filter(array_map(static function ($r) {
    $p = percent($r['score'], $r['points_possible'], 1);
    return $p === null ? null : ['pct' => $p, 'title' => $r['title'], 'at' => $r['released_at'], 'score' => $r['score'], 'possible' => $r['points_possible']];
}, $points)));
$n = count($data);
if ($n < 2) {
    echo '<p class="meta">' . e($n === 1 ? t('One result has been released so far. The chart appears from the second result.') : t('The chart appears once two or more results have been released.')) . '</p>';
    return;
}
$w = 640;
$h = 200;
$padL = 40;
$padR = 16;
$padT = 14;
$padB = 28;
$x = static fn (int $i): float => $padL + $i * ($w - $padL - $padR) / ($n - 1);
$y = static fn (float $pct): float => $padT + (100 - $pct) * ($h - $padT - $padB) / 100;
$line = implode(' ', array_map(static fn ($d, $i) => round($x($i), 1) . ',' . round($y($d['pct']), 1), $data, array_keys($data)));
$avg = array_sum(array_column($data, 'pct')) / $n;
$last = $data[$n - 1];
?>
<figure style="margin:0">
  <svg class="chart" viewBox="0 0 <?= $w ?> <?= $h ?>" role="group" aria-labelledby="chart-results-desc">
    <?php foreach ([0, 25, 50, 75, 100] as $g): ?>
      <line class="<?= $g === 0 ? 'axis' : 'grid' ?>" x1="<?= $padL ?>" x2="<?= $w - $padR ?>" y1="<?= round($y($g), 1) ?>" y2="<?= round($y($g), 1) ?>"/>
      <text x="<?= $padL - 8 ?>" y="<?= round($y($g), 1) + 4 ?>" text-anchor="end"><?= $g ?>%</text>
    <?php endforeach; ?>
    <polyline class="line" points="<?= e($line) ?>" stroke-linejoin="round" stroke-linecap="round"/>
    <?php foreach ($data as $i => $d): $cx = round($x($i), 1); $cy = round($y($d['pct']), 1);
        $tip = $d['title'] . ': ' . fmt_number($d['pct']) . '% (' . fmt_date($d['at'], 'date_short') . ')'; ?>
      <circle class="dot" cx="<?= $cx ?>" cy="<?= $cy ?>" r="4.5"/>
      <circle class="hit" cx="<?= $cx ?>" cy="<?= $cy ?>" r="12" tabindex="0" role="img" aria-label="<?= e($tip) ?>" data-tip="<?= e($tip) ?>"/>
    <?php endforeach; ?>
    <text class="value" x="<?= round($x($n - 1), 1) - 8 ?>" y="<?= max(12, round($y($last['pct']), 1) - 12) ?>" text-anchor="end"><?= e(fmt_number($last['pct'])) ?>%</text>
    <text x="<?= $padL ?>" y="<?= $h - 8 ?>"><?= e(fmt_date($data[0]['at'], 'date_short')) ?></text>
    <text x="<?= $w - $padR ?>" y="<?= $h - 8 ?>" text-anchor="end"><?= e(fmt_date($last['at'], 'date_short')) ?></text>
  </svg>
  <figcaption class="chart-summary" id="chart-results-desc"><?= e(t('Each dot is one released result as a percentage of its points, oldest to newest. Latest :last%; average :avg% across :n results.', ['last' => fmt_number($last['pct']), 'avg' => fmt_number($avg), 'n' => $n])) ?></figcaption>
</figure>
<details class="how chart-table">
  <summary><?= e(t('Show these results as a table')) ?></summary>
  <div class="table-frame">
    <table class="table">
      <thead><tr><th scope="col"><?= e(t('Work')) ?></th><th scope="col"><?= e(t('Released')) ?></th><th scope="col" class="num"><?= e(t('Score')) ?></th><th scope="col" class="num"><?= e(t('Percent')) ?></th></tr></thead>
      <tbody>
        <?php foreach ($data as $d): ?>
          <tr><td><?= e($d['title']) ?></td><td><?= e(fmt_date($d['at'], 'date_short')) ?></td><td class="num"><?= e(fmt_number($d['score'], 2)) ?> / <?= e(fmt_number($d['possible'], 2)) ?></td><td class="num"><?= e(fmt_number($d['pct'])) ?>%</td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</details>
