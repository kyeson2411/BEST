<?php
/**
 * @var string $range @var DateTimeImmutable $from @var DateTimeImmutable $to @var array $totals @var list<array> $byFeature @var list<array> $byRole
 * @var list<array> $days @var int $limitHits @var int $monthTokens @var array $policy @var string $provider @var bool $customPrices
 */
use Sibol\Controllers\Admin\UsageController;

$requests = (int) $totals['requests'];
$tokens = (int) $totals['input_tokens'] + (int) $totals['output_tokens'];
$budget = (int) ($policy['monthly_token_budget'] ?? 0);
$demoOnly = $requests > 0 && (int) $totals['demo'] === $requests;
$rangeLabel = \Sibol\Core\Dates::format($from->format('Y-m-d') . ' 12:00:00', 'date_short') . ' – ' . \Sibol\Core\Dates::format($to->format('Y-m-d') . ' 12:00:00', 'date_short');

// Bar chart geometry: one series (requests per day), no legend; the heading names it.
$w = 720;
$h = 180;
$padL = 36;
$padR = 8;
$padT = 12;
$padB = 26;
$n = max(1, count($days));
$max = max(1, ...array_map(static fn ($d) => $d['requests'], $days ?: [['requests' => 0]]));
$nice = $max <= 4 ? 4 : (int) (ceil($max / 4) * 4);
$slot = ($w - $padL - $padR) / $n;
$barW = max(2, min(22, $slot - 2));
$y = static fn (float $v): float => $padT + ($h - $padT - $padB) * (1 - $v / $nice);
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('AI usage')) ?></h1>
    <p class="page-lead"><?= e($provider) ?>. <?= e(t('Counts and token totals only; conversation text is never included here.')) ?></p>
  </div>
  <div class="page-actions">
    <nav class="segmented" aria-label="<?= e(t('Time range')) ?>">
      <?php foreach (['month' => t('This month'), '30' => t('Last 30 days'), 'last_month' => t('Last month')] as $key => $label): ?>
        <a href="<?= e(url('/admin/usage', $key === 'month' ? [] : ['range' => $key])) ?>"<?= $range === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
  </div>
</div>

<?php if ($demoOnly): ?>
  <div class="callout callout--amber" style="margin-bottom: 24px"><?= icon('info') ?><p><?= e(t('Every request in this period was answered in demo mode, which uses no AI model, no tokens and costs nothing.')) ?></p></div>
<?php endif; ?>

<dl class="stat-line" style="margin-bottom: 28px">
  <div class="stat"><dt class="stat__label"><?= e(t('Requests')) ?></dt><dd style="margin:0"><span class="stat__value"><?= number_format($requests) ?></span><span class="stat__note"><?= e($rangeLabel) ?></span></dd></div>
  <div class="stat"><dt class="stat__label"><?= e(t('Tokens')) ?></dt><dd style="margin:0"><span class="stat__value"><?= number_format($tokens) ?></span><span class="stat__note"><?= e(t(':in in, :out out', ['in' => number_format((int) $totals['input_tokens']), 'out' => number_format((int) $totals['output_tokens'])])) ?></span></dd></div>
  <div class="stat"><dt class="stat__label"><?= e(t('Estimated cost')) ?></dt><dd style="margin:0"><span class="stat__value">$<?= e(number_format((float) $totals['cost'], 2)) ?></span><span class="stat__note"><?= e(t('USD, estimate')) ?></span></dd></div>
  <div class="stat"><dt class="stat__label"><?= e(t('Stopped by limits')) ?></dt><dd style="margin:0"><span class="stat__value"><?= (int) $totals['stopped'] ?></span><span class="stat__note"><?= e(tp(':count person reached a daily limit', ':count people reached a daily limit', $limitHits)) ?></span></dd></div>
</dl>

<div class="layout-split">
  <div class="stack">
    <section class="section" aria-labelledby="daily-title">
      <div class="section__head"><h2 class="section__title" id="daily-title"><?= e(t('Requests per day')) ?></h2></div>
      <?php if ($requests === 0): ?>
        <p class="meta"><?= e(t('No AI requests in this period.')) ?></p>
      <?php else: ?>
        <figure style="margin:0">
          <svg class="chart" viewBox="0 0 <?= $w ?> <?= $h ?>" role="group" aria-labelledby="daily-desc">
            <?php foreach ([0, $nice / 2, $nice] as $g): ?>
              <line class="<?= $g == 0 ? 'axis' : 'grid' ?>" x1="<?= $padL ?>" x2="<?= $w - $padR ?>" y1="<?= round($y($g), 1) ?>" y2="<?= round($y($g), 1) ?>"/>
              <text x="<?= $padL - 6 ?>" y="<?= round($y($g), 1) + 4 ?>" text-anchor="end"><?= e(fmt_number($g)) ?></text>
            <?php endforeach; ?>
            <?php foreach ($days as $i => $d): $bx = $padL + $i * $slot + ($slot - $barW) / 2; $by = $y($d['requests']); $bh = max(0, $h - $padB - $by);
                $tip = fmt_date($d['day'] . ' 12:00:00', 'date') . ': ' . tp(':count request', ':count requests', $d['requests']) . ($d['stopped'] ? ', ' . t(':n stopped by limits', ['n' => $d['stopped']]) : ''); ?>
              <?php if ($d['requests'] > 0): ?><rect class="bar" x="<?= round($bx, 1) ?>" y="<?= round($by, 1) ?>" width="<?= round($barW, 1) ?>" height="<?= round($bh, 1) ?>" rx="<?= $barW >= 8 ? 3 : 1 ?>"/><?php endif; ?>
              <rect class="hit" x="<?= round($padL + $i * $slot, 1) ?>" y="<?= $padT ?>" width="<?= round(max($slot, 1), 1) ?>" height="<?= $h - $padT - $padB ?>" tabindex="0" role="img" aria-label="<?= e($tip) ?>" data-tip="<?= e($tip) ?>"/>
            <?php endforeach; ?>
            <text x="<?= $padL ?>" y="<?= $h - 6 ?>"><?= e(fmt_date($days[0]['day'] . ' 12:00:00', 'date_short')) ?></text>
            <text x="<?= $w - $padR ?>" y="<?= $h - 6 ?>" text-anchor="end"><?= e(fmt_date($days[count($days) - 1]['day'] . ' 12:00:00', 'date_short')) ?></text>
          </svg>
          <figcaption class="chart-summary" id="daily-desc"><?= e(t('Bars show AI requests per day, :range. Busiest day: :max requests.', ['range' => $rangeLabel, 'max' => $max])) ?></figcaption>
        </figure>
        <details class="how chart-table">
          <summary><?= e(t('Show daily totals as a table')) ?></summary>
          <div class="table-frame"><table class="table">
            <thead><tr><th scope="col"><?= e(t('Day')) ?></th><th scope="col" class="num"><?= e(t('Requests')) ?></th><th scope="col" class="num"><?= e(t('Stopped by limits')) ?></th></tr></thead>
            <tbody><?php foreach (array_reverse($days) as $d): if (!$d['requests']) { continue; } ?><tr><td><?= e(fmt_date($d['day'] . ' 12:00:00', 'date')) ?></td><td class="num"><?= $d['requests'] ?></td><td class="num"><?= $d['stopped'] ?></td></tr><?php endforeach; ?></tbody>
          </table></div>
        </details>
      <?php endif; ?>
    </section>

    <section class="section" aria-labelledby="feat-title">
      <div class="section__head"><h2 class="section__title" id="feat-title"><?= e(t('By feature')) ?></h2></div>
      <?php if (!$byFeature): ?><p class="meta"><?= e(t('No AI requests in this period.')) ?></p><?php else: ?>
        <div class="table-frame"><table class="table table--stack">
          <thead><tr><th scope="col"><?= e(t('Feature')) ?></th><th scope="col" class="num"><?= e(t('Requests')) ?></th><th scope="col" class="num"><?= e(t('Failed')) ?></th><th scope="col" class="num"><?= e(t('Stopped')) ?></th><th scope="col" class="num"><?= e(t('Tokens')) ?></th><th scope="col" class="num"><?= e(t('Cost (USD)')) ?></th></tr></thead>
          <tbody>
            <?php foreach ($byFeature as $f): ?>
              <tr><td class="cell-primary"><?= e(UsageController::featureLabel((string) $f['feature'])) ?></td>
                <td class="num" data-label="<?= e(t('Requests')) ?>"><?= number_format((int) $f['requests']) ?></td>
                <td class="num" data-label="<?= e(t('Failed')) ?>"><?= (int) $f['errors'] ?></td>
                <td class="num" data-label="<?= e(t('Stopped')) ?>"><?= (int) $f['stopped'] ?></td>
                <td class="num" data-label="<?= e(t('Tokens')) ?>"><?= number_format((int) $f['input_tokens'] + (int) $f['output_tokens']) ?></td>
                <td class="num" data-label="<?= e(t('Cost (USD)')) ?>"><?= e(number_format((float) $f['cost'], 2)) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table></div>
      <?php endif; ?>
    </section>

    <section class="section" aria-labelledby="role-title">
      <div class="section__head"><h2 class="section__title" id="role-title"><?= e(t('By role')) ?></h2></div>
      <?php if (!$byRole): ?><p class="meta"><?= e(t('No AI requests in this period.')) ?></p><?php else: ?>
        <ul class="bar-list">
          <?php foreach ($byRole as $r): $share = percent($r['requests'], $requests); ?>
            <li><span class="bar-list__label"><?= e($r['user_role'] ? role_label((string) $r['user_role']) : t('Unknown')) ?></span>
              <?= partial('meter', ['value' => $share, 'label' => fmt_number($share) . '%']) ?>
              <span class="bar-list__value"><?= e(tp(':count request', ':count requests', (int) $r['requests'])) ?></span></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>

  <aside class="stack">
    <section class="panel" aria-labelledby="budget-title">
      <h2 class="panel__title" id="budget-title"><?= e(t('Monthly token budget')) ?></h2>
      <?php if ($budget > 0): $pct = percent($monthTokens, $budget); ?>
        <?= partial('meter', ['value' => $pct, 'label' => t(':pct% used', ['pct' => fmt_number($pct)])]) ?>
        <p class="meta"><?= e(t(':used of :budget tokens this calendar month. When the budget runs out, AI features pause until the 1st.', ['used' => number_format($monthTokens), 'budget' => number_format($budget)])) ?></p>
      <?php else: ?>
        <p class="meta"><?= e(t('No monthly budget is set.')) ?></p>
      <?php endif; ?>
      <h3 class="panel__title" style="margin-top: 16px; font-size: 1rem"><?= e(t('Current limits')) ?></h3>
      <dl class="dl">
        <dt><?= e(t('Per student, per day')) ?></dt><dd class="num"><?= (int) ($policy['student_daily_messages'] ?? 0) ?: e(t('No limit')) ?></dd>
        <dt><?= e(t('Per teacher, per day')) ?></dt><dd class="num"><?= (int) ($policy['teacher_daily_requests'] ?? 0) ?: e(t('No limit')) ?></dd>
        <dt><?= e(t('Burst limit')) ?></dt><dd><?= e(t('8 per minute per person')) ?></dd>
      </dl>
      <a class="btn btn--quiet btn--sm" href="<?= e(url('/admin/policies#ai')) ?>"><?= icon('sliders-horizontal', 'icon--sm') ?><?= e(t('Change limits')) ?></a>
    </section>
    <section class="panel panel--sunken">
      <h2 class="panel__title"><?= e(t('About the cost estimate')) ?></h2>
      <p class="meta" style="margin:0"><?= e($customPrices
          ? t('Calculated from the prices set in AI_PRICE_INPUT_PER_MTOK and AI_PRICE_OUTPUT_PER_MTOK. Your provider\'s invoice is the authority.')
          : t('Calculated from the list prices built into B.E.S.T. Learning and Interactive Study System for known models. Set AI_PRICE_INPUT_PER_MTOK and AI_PRICE_OUTPUT_PER_MTOK in .env to use your contract prices. Your provider\'s invoice is the authority.')) ?></p>
    </section>
  </aside>
</div>
