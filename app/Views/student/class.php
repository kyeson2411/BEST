<?php
/**
 * @var array $class @var string $tab @var list<array> $teachers @var list<array> $modules @var list<array> $materials
 * @var list<array> $lessons @var list<array> $announcements @var list<array> $work @var bool $aiEnabled
 */
use Sibol\Core\Dates;
use Sibol\Core\Uploads;

$base = '/student/classes/' . (int) $class['id'];
$tabs = [
    'overview' => t('Overview'),
    'materials' => t('Materials'),
    'work' => t('Work'),
    'announcements' => t('Announcements'),
];
$byModule = [];
foreach ($materials as $m) {
    $byModule[(int) ($m['module_id'] ?? 0)][] = $m;
}
$lessonsByModule = [];
foreach ($lessons as $l) {
    $lessonsByModule[(int) ($l['module_id'] ?? 0)][] = $l;
}
$teacherNames = implode(', ', array_map(static fn ($t) => user_name($t), $teachers));
$openWork = array_values(array_filter($work, static fn ($w) => in_array($w['bucket'], ['upcoming', 'overdue'], true)));

$materialRow = static function (array $m) use ($aiEnabled, $class): string {
    ob_start(); ?>
    <li class="row" id="material-<?= (int) $m['id'] ?>">
      <span class="row__lead"><?= icon($m['kind'] === 'link' ? 'link' : 'file-text', 'icon--lg') ?></span>
      <div class="row__main">
        <?php if ($m['kind'] === 'link'): ?>
          <a class="row__title" href="<?= e(url('/materials/' . (int) $m['id'] . '/file')) ?>" target="_blank" rel="noopener noreferrer"><?= e($m['title']) ?> <?= icon('external-link', 'icon--xs', t('opens a new tab')) ?></a>
        <?php else: ?>
          <a class="row__title" href="<?= e(url('/materials/' . (int) $m['id'] . '/read')) ?>"><?= e($m['title']) ?></a>
        <?php endif; ?>
        <?php if ($m['description']): ?><p class="muted" style="margin:2px 0 0; font-size:.9375rem"><?= e($m['description']) ?></p><?php endif; ?>
        <div class="row__meta">
          <?php if ($m['kind'] === 'file'): ?>
            <span><?= e(strtoupper((string) $m['extension'])) ?><?php if ($m['page_count']): ?>, <?= e(tp(':count page', ':count pages', (int) $m['page_count'])) ?><?php endif; ?>, <?= e(Uploads::humanSize((int) $m['size_bytes'])) ?></span>
          <?php else: ?>
            <span><?= e((string) parse_url((string) $m['url'], PHP_URL_HOST)) ?></span>
          <?php endif; ?>
          <span><?= e(t('Shared :when', ['when' => fmt_rel($m['release_at'] ?: $m['published_at'])])) ?></span>
          <?php if ($aiEnabled && (int) $m['ai_enabled'] && $m['processing_status'] === 'ready'): ?>
            <span class="status-line"><?= icon('quote', 'icon--xs') ?><?= e(t('The companion can cite this')) ?></span>
          <?php endif; ?>
        </div>
        <?php $readingKinds = array_values(array_intersect(\Sibol\Services\ReadingVersions::KINDS, explode(',', (string) ($m['reading_kinds'] ?? '')))); ?>
        <?php if ($readingKinds): ?>
          <div class="row__meta"><span><?= icon('languages', 'icon--xs') ?> <?= e(t('Also in:')) ?>
            <?php foreach ($readingKinds as $i => $k): ?><?= $i ? ' · ' : '' ?><a href="<?= e(url('/materials/' . (int) $m['id'] . '/read', ['v' => $k])) ?>"><?= e(\Sibol\Services\ReadingVersions::label($k)) ?></a><?php endforeach; ?>
          </span></div>
        <?php endif; ?>
      </div>
      <div class="row__aside">
        <?php if (!(int) $m['seen']): ?><?= badge(t('New'), 'leaf') ?><?php endif; ?>
        <?php if ($m['kind'] === 'file'): ?>
          <a class="btn btn--secondary btn--sm" href="<?= e(url('/materials/' . (int) $m['id'] . '/file', ['download' => 1])) ?>"><?= icon('download', 'icon--sm') ?><span class="sr-only"><?= e(t('Download :title', ['title' => $m['title']])) ?></span></a>
          <?php /* Shown by offline.js where the browser can keep files for offline reading. */ ?>
          <button type="button" class="btn btn--secondary btn--sm offline-toggle" aria-pressed="false" hidden aria-label="<?= e(t('Keep offline') . ': ' . $m['title']) ?>"
                  data-offline-save="<?= e(url('/materials/' . (int) $m['id'] . '/file')) ?>" data-offline-title="<?= e($m['title']) ?>">
            <?= icon('wifi-off', 'icon--sm offline-toggle__off') ?><?= icon('check', 'icon--sm offline-toggle__on') ?><?= e(t('Keep offline')) ?>
          </button>
        <?php endif; ?>
        <?php if ($aiEnabled && (int) $m['ai_enabled'] && $m['processing_status'] === 'ready'): ?>
          <a class="btn btn--quiet btn--sm" href="<?= e(url('/companion/new', ['mode' => 'ask_materials', 'class_id' => (int) $class['id'], 'material_id' => (int) $m['id']])) ?>"><?= e(t('Ask about it')) ?></a>
        <?php endif; ?>
      </div>
    </li>
    <?php return (string) ob_get_clean();
};
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e($class['name']) ?></h1>
    <p class="page-lead"><?= e($class['subject_name']) ?><?php if ($teacherNames): ?>, <?= e(t('with :names', ['names' => $teacherNames])) ?><?php endif; ?></p>
  </div>
  <?php if ($aiEnabled): ?>
    <div class="page-actions">
      <a class="btn btn--secondary" href="<?= e(url('/companion/new', ['class_id' => (int) $class['id'], 'mode' => 'ask_materials'])) ?>"><?= icon('messages-square') ?><?= e(t('Ask about this class')) ?></a>
    </div>
  <?php endif; ?>
</div>

<nav class="tabs" aria-label="<?= e(t('Class sections')) ?>">
  <?php foreach ($tabs as $key => $label): ?>
    <a class="tab" href="<?= e(url($base, $key === 'overview' ? [] : ['tab' => $key])) ?>"<?= $tab === $key ? ' aria-current="page"' : '' ?>>
      <?= e($label) ?>
      <?php if ($key === 'work' && $openWork): ?><span class="badge badge--amber"><?= count($openWork) ?></span><?php endif; ?>
    </a>
  <?php endforeach; ?>
</nav>

<?php if ($tab === 'overview'): ?>
  <div class="layout-split">
    <div class="stack">
      <section class="section" aria-labelledby="ov-work">
        <div class="section__head">
          <h2 class="section__title" id="ov-work"><?= e(t('Open work')) ?></h2>
          <a class="section__link" href="<?= e(url($base, ['tab' => 'work'])) ?>"><?= e(t('All work')) ?></a>
        </div>
        <?php if (!$openWork): ?>
          <p class="meta"><?= e(t('Nothing open in this class right now.')) ?></p>
        <?php else: ?>
          <ul class="entries">
            <?php foreach (array_slice($openWork, 0, 4) as $w): $d = Dates::due($w['due_at']); ?>
              <li class="entry<?= $d['overdue'] ? ' entry--alert' : '' ?>">
                <?= partial('entry_date', ['at' => $w['due_at']]) ?>
                <div class="entry__main">
                  <a class="row__title" href="<?= e(url('/student/assignments/' . (int) $w['id'])) ?>"><?= e($w['title']) ?></a>
                  <div class="row__meta"><span><?= e(work_kind((string) $w['kind'])) ?></span></div>
                </div>
                <div class="entry__aside"><?= (int) $w['not_open'] ? badge(t('Opens :when', ['when' => fmt_date($w['opens_at'], 'datetime_short')]), 'neutral') : badge($d['label'], $d['tone']) ?></div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>

      <section class="section" aria-labelledby="ov-lessons">
        <div class="section__head"><h2 class="section__title" id="ov-lessons"><?= e(t('Lessons')) ?></h2></div>
        <?php if (!$lessons): ?>
          <p class="meta"><?= e(t('Your teacher has not published any lessons here yet.')) ?></p>
        <?php else: ?>
          <ul class="rows">
            <?php foreach ($lessons as $l): ?>
              <li class="row">
                <span class="row__lead"><?= icon('notebook-pen') ?></span>
                <div class="row__main">
                  <a class="row__title" href="<?= e(url($base . '/lessons/' . (int) $l['id'])) ?>"><?= e($l['title']) ?></a>
                  <div class="row__meta"><?php if ($l['duration_minutes']): ?><span><?= e(t(':n minutes', ['n' => (int) $l['duration_minutes']])) ?></span><?php endif; ?><span><?= e(t('Published :when', ['when' => fmt_rel($l['published_at'])])) ?></span></div>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>
    </div>
    <aside class="stack">
      <section class="section" aria-labelledby="ov-news">
        <div class="section__head">
          <h2 class="section__title" id="ov-news"><?= e(t('Latest announcement')) ?></h2>
          <?php if (count($announcements) > 1): ?><a class="section__link" href="<?= e(url($base, ['tab' => 'announcements'])) ?>"><?= e(t('All')) ?></a><?php endif; ?>
        </div>
        <?php if (!$announcements): ?>
          <p class="meta"><?= e(t('No announcements yet.')) ?></p>
        <?php else: $a = $announcements[0]; ?>
          <article>
            <h3 style="font-size:1rem; margin:0 0 4px"><?= e($a['title']) ?></h3>
            <p class="meta" style="margin:0 0 8px"><?= e(user_name($a)) ?>, <?= e(fmt_rel($a['created_at'])) ?></p>
            <div class="prose"><?= markdown($a['body']) ?></div>
          </article>
        <?php endif; ?>
      </section>
      <section class="section" aria-labelledby="ov-new">
        <div class="section__head">
          <h2 class="section__title" id="ov-new"><?= e(t('Recent materials')) ?></h2>
          <a class="section__link" href="<?= e(url($base, ['tab' => 'materials'])) ?>"><?= e(t('All materials')) ?></a>
        </div>
        <?php if (!$materials): ?>
          <p class="meta"><?= e(t('No materials shared yet.')) ?></p>
        <?php else: ?>
          <ul class="rows">
            <?php foreach (array_slice($materials, 0, 3) as $m): ?>
              <li class="row">
                <div class="row__main">
                  <a class="row__title" href="<?= e(url($base, ['tab' => 'materials']) . '#material-' . (int) $m['id']) ?>"><?= e($m['title']) ?></a>
                  <div class="row__meta"><span><?= e(fmt_rel($m['release_at'] ?: $m['published_at'])) ?></span></div>
                </div>
                <?php if (!(int) $m['seen']): ?><span class="row__aside"><?= badge(t('New'), 'leaf') ?></span><?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>
    </aside>
  </div>

<?php elseif ($tab === 'materials'): ?>
  <?php if (!$materials && !$lessons): ?>
    <?= partial('empty', ['title' => t('No materials yet'), 'body' => t('When your teacher shares notes, files or links, they will appear here, organised by module.')]) ?>
  <?php else: ?>
    <div data-offline data-offline-class="<?= e($class['name']) ?>" data-i18n="<?= json_attr(\Sibol\Services\ScriptStrings::offline()) ?>">
    <p class="meta offline-hint" data-offline-hint hidden><?= icon('wifi-off', 'icon--sm') ?> <?= e(t('Files you keep offline open on this device without a connection, until you sign out.')) ?></p>
    <?php
    $groups = [];
    foreach ($modules as $mod) {
        $groups[] = ['title' => $mod['title'], 'id' => (int) $mod['id']];
    }
    $groups[] = ['title' => t('Other materials'), 'id' => 0];
    foreach ($groups as $group):
        $items = $byModule[$group['id']] ?? [];
        $groupLessons = $lessonsByModule[$group['id']] ?? [];
        if (!$items && !$groupLessons) { continue; }
    ?>
      <section class="section" aria-labelledby="mod-<?= (int) $group['id'] ?>">
        <div class="section__head"><h2 class="section__title" id="mod-<?= (int) $group['id'] ?>"><?= e($group['title']) ?></h2></div>
        <ul class="rows">
          <?php foreach ($groupLessons as $l): ?>
            <li class="row">
              <span class="row__lead"><?= icon('notebook-pen', 'icon--lg') ?></span>
              <div class="row__main">
                <a class="row__title" href="<?= e(url($base . '/lessons/' . (int) $l['id'])) ?>"><?= e($l['title']) ?></a>
                <div class="row__meta"><span><?= e(t('Lesson')) ?></span><?php if ($l['duration_minutes']): ?><span><?= e(t(':n minutes', ['n' => (int) $l['duration_minutes']])) ?></span><?php endif; ?></div>
              </div>
            </li>
          <?php endforeach; ?>
          <?php foreach ($items as $m): ?><?= $materialRow($m) ?><?php endforeach; ?>
        </ul>
      </section>
    <?php endforeach; ?>
    </div>
  <?php endif; ?>

<?php elseif ($tab === 'work'): ?>
  <?php if (!$work): ?>
    <?= partial('empty', ['title' => t('No work assigned yet'), 'body' => t('Assignments and quizzes for this class will appear here.')]) ?>
  <?php else: ?>
    <?= partial('student_work_list', ['work' => $work, 'showClass' => false]) ?>
  <?php endif; ?>

<?php else: ?>
  <?php if (!$announcements): ?>
    <?= partial('empty', ['title' => t('No announcements yet'), 'body' => t('Your teacher\'s class announcements will appear here, newest first.')]) ?>
  <?php else: ?>
    <ul class="entries">
      <?php foreach ($announcements as $a): ?>
        <li class="entry">
          <?= partial('entry_date', ['at' => $a['created_at']]) ?>
          <article class="entry__main">
            <h2 style="font-size:1.0625rem; margin:0 0 2px"><?= e($a['title']) ?></h2>
            <p class="meta" style="margin:0 0 8px"><?= e(user_name($a)) ?>, <?= e(fmt_date($a['created_at'], 'datetime_short')) ?></p>
            <div class="prose"><?= markdown($a['body']) ?></div>
          </article>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
<?php endif; ?>
