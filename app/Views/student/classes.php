<?php /** @var list<array> $classes @var bool $codesEnabled */ ?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Classes')) ?></h1>
    <p class="page-lead"><?= e(tp('You are in :count class this term.', 'You are in :count classes this term.', count($classes))) ?></p>
  </div>
</div>

<div class="layout-split">
  <section aria-labelledby="my-classes">
    <h2 class="sr-only" id="my-classes"><?= e(t('My classes')) ?></h2>
    <?php if (!$classes): ?>
      <?= partial('empty', ['title' => t('No classes yet'), 'body' => t('Enter the class code your teacher gave you, or ask your teacher to add you.')]) ?>
    <?php else: ?>
      <ul class="rows">
        <?php foreach ($classes as $class): ?>
          <li class="row">
            <span class="row__lead"><?= icon('school', 'icon--lg') ?></span>
            <div class="row__main">
              <a class="row__title" href="<?= e(url('/student/classes/' . (int) $class['id'])) ?>"><?= e($class['name']) ?></a>
              <div class="row__meta">
                <span><?= e($class['subject_name']) ?></span>
                <span><?= e($class['teachers'] ?: t('Teacher to be assigned')) ?></span>
                <?php if ($class['last_material_at']): ?><span><?= e(t('Last material :when', ['when' => fmt_rel($class['last_material_at'])])) ?></span><?php endif; ?>
              </div>
            </div>
            <div class="row__aside">
              <?php if ((int) $class['open_work'] > 0): ?><?= badge(tp(':count open task', ':count open tasks', (int) $class['open_work']), 'amber') ?><?php endif; ?>
              <?php if ((int) $class['unseen_materials'] > 0): ?><?= badge(tp(':count new', ':count new', (int) $class['unseen_materials']), 'leaf') ?><?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <aside>
    <section class="panel" aria-labelledby="join-title">
      <h2 class="panel__title" id="join-title"><?= e(t('Join a class')) ?></h2>
      <?php if ($codesEnabled): ?>
        <p class="muted" style="font-size:.9375rem"><?= e(t('Your teacher can give you a class code. Codes expire, so use it soon after you get it.')) ?></p>
        <form method="post" action="<?= e(url('/student/classes/join')) ?>">
          <?= csrf_field() ?>
          <?= field('text', 'code', t('Class code'), ['required' => true, 'class' => 'code-text', 'attrs' => ['autocomplete' => 'off', 'autocapitalize' => 'characters', 'spellcheck' => 'false', 'maxlength' => 12, 'inputmode' => 'text']]) ?>
          <button class="btn btn--primary" type="submit" data-busy="<?= e(t('Joining…')) ?>"><?= e(t('Join class')) ?></button>
        </form>
      <?php else: ?>
        <p class="muted"><?= e(t('Your school adds students to classes directly. Ask your teacher or adviser if a class is missing.')) ?></p>
      <?php endif; ?>
    </section>
  </aside>
</div>
