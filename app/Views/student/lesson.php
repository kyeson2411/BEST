<?php
/** @var array $class @var array $lesson @var array $content */
$sections = [
    'objectives' => t('What you will learn'),
    'prior_knowledge' => t('What you already know'),
    'explanation' => t('Explanation'),
    'worked_example' => t('Worked example'),
    'activities' => t('Activities'),
    'checks' => t('Check your understanding'),
    'exit_ticket' => t('Exit ticket'),
];
$cites = [];
foreach ((array) ($content['sources'] ?? []) as $s) {
    $cites[(int) $s['n']] = 'lesson-source';
}
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e($lesson['title']) ?></h1>
    <p class="page-lead"><?= e($class['name']) ?><?php if ($lesson['duration_minutes']): ?>, <?= e(t(':n minutes', ['n' => (int) $lesson['duration_minutes']])) ?><?php endif; ?></p>
  </div>
  <div class="page-actions">
    <a class="btn btn--secondary" href="<?= e(url('/companion/new', ['class_id' => (int) $class['id'], 'mode' => 'explain', 'topic' => $lesson['title']])) ?>"><?= icon('messages-square') ?><?= e(t('Ask the companion about this lesson')) ?></a>
  </div>
</div>

<article class="prose">
  <?php foreach ($sections as $key => $heading): if (trim((string) ($content[$key] ?? '')) === '') { continue; } ?>
    <section>
      <h2><?= e($heading) ?></h2>
      <?= markdown((string) $content[$key], ['citations' => $cites, 'heading_base' => 3]) ?>
    </section>
  <?php endforeach; ?>

  <?php if (!empty($content['sources'])): ?>
    <section>
      <h2><?= e(t('Sources')) ?></h2>
      <ol>
        <?php foreach ($content['sources'] as $s): ?>
          <li id="lesson-source-<?= (int) $s['n'] ?>">
            <?php if (!empty($s['material_id'])): ?><a href="<?= e(url('/materials/' . (int) $s['material_id'] . '/file')) ?>"><?= e($s['title']) ?></a><?php else: ?><?= e($s['title']) ?><?php endif; ?><?php if (!empty($s['where'])): ?>, <?= e($s['where']) ?><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ol>
    </section>
  <?php endif; ?>
</article>
