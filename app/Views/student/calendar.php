<?php
/** @var DateTimeImmutable $first @var DateTimeImmutable $gridStart @var array $byDay @var list<array> $events @var string $today @var list<array> $classes */
use Sibol\Core\Dates;

$prev = $first->modify('-1 month')->format('Y-m');
$next = $first->modify('+1 month')->format('Y-m');
$monthLabel = Dates::monthName((int) $first->format('n')) . ' ' . $first->format('Y');
$monthEvents = array_filter($events, static fn ($ev) => Dates::local((string) $ev['at'])->format('Y-m') === $first->format('Y-m'));
$eventLabel = static function (array $ev): string {
    $time = fmt_date($ev['at'], 'time');
    return $ev['type'] === 'due' ? t('Due :time', ['time' => $time]) : $time;
};
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e($monthLabel) ?></h1>
    <p class="page-lead"><?= e(t('Due dates and your own study sessions, in :tz time.', ['tz' => Dates::tz()])) ?></p>
  </div>
  <div class="page-actions">
    <a class="btn btn--secondary" href="<?= e(url('/student/calendar', ['month' => $prev])) ?>"><?= icon('chevron-left', 'icon--sm') ?><span class="sr-only"><?= e(t('Previous month')) ?></span></a>
    <a class="btn btn--secondary" href="<?= e(url('/student/calendar')) ?>"><?= e(t('This month')) ?></a>
    <a class="btn btn--secondary" href="<?= e(url('/student/calendar', ['month' => $next])) ?>"><span class="sr-only"><?= e(t('Next month')) ?></span><?= icon('chevron-right', 'icon--sm') ?></a>
    <button class="btn btn--primary" type="button" data-toggle="#add-session"><?= icon('calendar-plus', 'icon--sm') ?><?= e(t('Plan a study session')) ?></button>
  </div>
</div>

<section class="panel" id="add-session"<?= errors() ? '' : ' hidden' ?> style="margin-bottom: 24px; max-width: 860px" aria-labelledby="add-title">
  <h2 class="panel__title" id="add-title"><?= e(t('Plan a study session')) ?></h2>
  <form method="post" action="<?= e(url('/student/calendar/sessions')) ?>">
    <?= csrf_field() ?>
    <div class="form-grid">
      <?= field('text', 'title', t('What you will study'), ['required' => true, 'attrs' => ['maxlength' => 200], 'wrap_class' => 'span-2', 'placeholder' => t('For example: practise Punnett squares')]) ?>
      <?= field('date', 'date', t('Date'), ['required' => true, 'value' => $today]) ?>
      <?= field('time', 'time', t('Start time'), ['required' => true, 'value' => '19:00']) ?>
      <?= field('select', 'duration_minutes', t('Length'), ['options' => ['20' => t(':n minutes', ['n' => 20]), '30' => t(':n minutes', ['n' => 30]), '45' => t(':n minutes', ['n' => 45]), '60' => t('1 hour')], 'value' => '30']) ?>
      <?= field('select', 'class_id', t('Class'), ['options' => ['' => t('No class')] + array_column(array_filter($classes), 'name', 'id'), 'optional_label' => true]) ?>
      <?= field('textarea', 'notes', t('Notes'), ['rows' => 2, 'wrap_class' => 'span-2', 'optional_label' => true, 'attrs' => ['maxlength' => 1000]]) ?>
    </div>
    <div class="form-actions"><button class="btn btn--primary" type="submit"><?= e(t('Add to calendar')) ?></button></div>
  </form>
</section>

<table class="cal" aria-label="<?= e($monthLabel) ?>">
  <thead><tr><?php foreach (Dates::weekdayInitials() as $d): ?><th scope="col"><?= e($d) ?></th><?php endforeach; ?></tr></thead>
  <tbody>
    <?php for ($week = 0; $week < 6; $week++): ?>
      <tr>
        <?php for ($day = 0; $day < 7; $day++):
            $date = $gridStart->modify('+' . ($week * 7 + $day) . ' days');
            $key = $date->format('Y-m-d');
            $out = $date->format('m') !== $first->format('m');
            $classes = trim(($out ? 'cal__out ' : '') . ($key === $today ? 'cal__today' : ''));
        ?>
          <td class="<?= e($classes) ?>">
            <span class="cal__day"<?= $key === $today ? ' aria-current="date"' : '' ?>><?= (int) $date->format('j') ?></span>
            <?php foreach (array_slice($byDay[$key] ?? [], 0, 3) as $ev):
                $cls = 'cal__event' . ($ev['type'] === 'due' && !$ev['done'] ? ' cal__event--due' : '') . ($ev['type'] === 'study' ? ' cal__event--study' : '') . ($ev['done'] ? ' cal__event--done' : '');
                $text = $eventLabel($ev) . ' ' . $ev['title'];
            ?>
              <?php if ($ev['url']): ?>
                <a class="<?= e($cls) ?>" href="<?= e(url($ev['url'])) ?>" title="<?= e($text) ?>"><?= e($text) ?></a>
              <?php else: ?>
                <span class="<?= e($cls) ?>" title="<?= e($text) ?>"><?= e($text) ?></span>
              <?php endif; ?>
            <?php endforeach; ?>
            <?php if (count($byDay[$key] ?? []) > 3): ?><span class="meta" style="font-size:.75rem"><?= e(t('+:n more', ['n' => count($byDay[$key]) - 3])) ?></span><?php endif; ?>
          </td>
        <?php endfor; ?>
      </tr>
    <?php endfor; ?>
  </tbody>
</table>

<section class="section" aria-labelledby="list-title" style="margin-top: 32px">
  <div class="section__head"><h2 class="section__title" id="list-title"><?= e(t('This month, as a list')) ?></h2></div>
  <?php if (!$monthEvents): ?>
    <p class="meta"><?= e(t('Nothing scheduled this month.')) ?></p>
  <?php else: ?>
    <ul class="entries">
      <?php foreach ($monthEvents as $ev): ?>
        <li class="entry">
          <?= partial('entry_date', ['at' => $ev['at']]) ?>
          <div class="entry__main">
            <?php if ($ev['url']): ?><a class="row__title" href="<?= e(url($ev['url'])) ?>"><?= e($ev['title']) ?></a><?php else: ?><span class="row__title"><?= e($ev['title']) ?></span><?php endif; ?>
            <div class="row__meta"><?php if (!empty($ev['kind'])): ?><?= kind_tag((string) $ev['kind']) ?><?php endif; ?><span><?= e($eventLabel($ev)) ?></span><span><?= e($ev['meta']) ?></span><?php if (!empty($ev['notes'])): ?><span><?= e(str_limit($ev['notes'], 80)) ?></span><?php endif; ?></div>
          </div>
          <div class="entry__aside">
            <?php if ($ev['type'] === 'due'): ?>
              <?= $ev['done'] ? badge(t('Submitted'), 'leaf', 'check') : badge(t('Due'), 'amber') ?>
            <?php elseif ($ev['type'] === 'opens'): ?>
              <?= badge(t('Opens'), 'neutral') ?>
            <?php else: ?>
              <div class="cluster">
                <form method="post" action="<?= e(url('/student/calendar/sessions/' . (int) $ev['id'] . '/complete')) ?>"><?= csrf_field() ?>
                  <button class="btn btn--<?= $ev['done'] ? 'quiet' : 'secondary' ?> btn--sm" type="submit"><?= icon($ev['done'] ? 'rotate-ccw' : 'check', 'icon--sm') ?><?= e($ev['done'] ? t('Undo') : t('Done')) ?></button>
                </form>
                <form method="post" action="<?= e(url('/student/calendar/sessions/' . (int) $ev['id'] . '/delete')) ?>" data-confirm="<?= e(t('Remove this study session?')) ?>" data-confirm-action="<?= e(t('Remove')) ?>" data-confirm-tone="danger"><?= csrf_field() ?>
                  <button class="btn btn--danger-quiet btn--sm" type="submit"><?= icon('trash-2', 'icon--sm') ?><span class="sr-only"><?= e(t('Remove :title', ['title' => $ev['title']])) ?></span></button>
                </form>
              </div>
            <?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
