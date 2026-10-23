<?php
/**
 * @var array $assignment @var string $kind @var list<array> $questions @var array $rubric @var bool $locked @var list<string> $problems
 * @var list<array> $materials @var list<int> $resources @var list<array> $allMaterials @var int|null $aiRemaining
 * @var list<array> $competencies the class's learning competencies @var array<int,array> $tagged competencies questions use, by id
 */
$a = $assignment;
$isQuiz = $kind === 'quiz';
$isPaper = $kind === 'paper';
[$statusLabel, $statusTone] = assignment_status($a);
$unreviewed = count(array_filter($questions, static fn ($q) => !(int) $q['reviewed']));
$typeLabels = ['multiple_choice' => t('Multiple choice'), 'true_false' => t('True or false'), 'short_answer' => t('Short answer'), 'essay' => t('Essay')];
$base = '/teacher/assignments/' . (int) $a['id'];
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e($a['title']) ?></h1>
    <p class="page-lead"><?= e($a['class_name']) ?>, <?= e(work_kind($kind, true)) ?> <?= badge($statusLabel, $statusTone) ?></p>
  </div>
  <div class="page-actions">
    <a class="btn btn--secondary" href="<?= e(url($base . '/preview')) ?>"><?= icon('eye', 'icon--sm') ?><?= e(t('Preview as a student')) ?></a>
    <a class="btn btn--secondary" href="<?= e(url($base . '/print')) ?>"><?= icon('printer', 'icon--sm') ?><?= e(t('Print')) ?></a>
    <a class="btn btn--secondary" href="<?= e(url($base)) ?>"><?= e(t('Overview')) ?></a>
    <?php if ($a['status'] === 'draft'): ?>
      <form method="post" action="<?= e(url($base . '/publish')) ?>" data-confirm="<?= e($a['opens_at'] ? t('Students will see it from the opening date and be notified now.') : t('Students in :class will see it now and get a notification.', ['class' => $a['class_name']])) ?>" data-confirm-title="<?= e(t('Publish?')) ?>" data-confirm-action="<?= e(t('Publish')) ?>">
        <?= csrf_field() ?><button class="btn btn--primary" type="submit"<?= $problems ? ' aria-describedby="publish-problems"' : '' ?>><?= icon('send', 'icon--sm') ?><?= e(t('Publish')) ?></button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php if ($locked): ?>
  <div class="callout callout--amber" style="margin-bottom: 24px"><?= icon('lock') ?><div><p class="callout__title"><?= e(t('Questions are locked')) ?></p><p><?= e(t('Students have started this work, so changing questions would make attempts unfair. You can still change dates and instructions, or duplicate it to make a new version.')) ?></p>
    <form method="post" action="<?= e(url($base . '/duplicate')) ?>"><?= csrf_field() ?><button class="btn btn--secondary btn--sm" type="submit"><?= icon('copy', 'icon--sm') ?><?= e(t('Duplicate')) ?></button></form></div></div>
<?php elseif ($problems && $a['status'] === 'draft'): ?>
  <div class="callout" id="publish-problems" style="margin-bottom: 24px"><?= icon('list-checks') ?><div><p class="callout__title"><?= e(t('Before you publish')) ?></p>
    <ul style="margin:0; padding-left: 1.2em"><?php foreach ($problems as $p): ?><li><?= e($p) ?></li><?php endforeach; ?></ul></div></div>
<?php endif; ?>

<div class="layout-split">
  <div class="stack">
    <?php if ($isQuiz || $isPaper || $questions): ?>
      <section class="section" id="questions" aria-labelledby="q-title">
        <div class="section__head">
          <h2 class="section__title" id="q-title"><?= e(t('Questions')) ?> <span class="count"><?= count($questions) ?></span></h2>
          <span class="meta"><?= e(t(':n points in total', ['n' => fmt_number($a['points_possible'], 2)])) ?></span>
        </div>
        <?php if ($isPaper): ?><p class="section__intro"><?= e(t('Optional. Questions here are printed on the blank copy and the answer key; students do not answer them online.')) ?></p><?php endif; ?>
        <?php if ($unreviewed > 0 && !$locked): ?>
          <div class="callout callout--amber" style="margin-bottom:12px"><?= icon('square-pen') ?><div>
            <p><?= e(tp(':count AI-drafted question needs your review. Read it, correct anything, then approve it.', ':count AI-drafted questions need your review. Read each one, correct anything, then approve it.', $unreviewed)) ?></p>
            <form method="post" action="<?= e(url($base . '/questions/approve-all')) ?>" data-confirm="<?= e(t('Only do this after reading every drafted question and its answer.')) ?>" data-confirm-title="<?= e(t('Approve all drafted questions?')) ?>" data-confirm-action="<?= e(t('Approve all')) ?>">
              <?= csrf_field() ?><button class="btn btn--secondary btn--sm" type="submit"><?= e(t('I have checked them all: approve all')) ?></button></form>
          </div></div>
        <?php endif; ?>
        <?php if (!$questions): ?>
          <?= partial('empty', ['compact' => true, 'title' => t('No questions yet'), 'body' => t('Write questions below, or draft them from your class materials with the AI panel.')]) ?>
        <?php endif; ?>
        <?php if ($competencies): ?>
          <datalist id="competency-codes">
            <?php foreach ($competencies as $c): ?><option value="<?= e($c['code']) ?>"><?= e(($c['quarter'] ? t('Q:n', ['n' => (int) $c['quarter']]) . ' · ' : '') . str_limit($c['description'], 120)) ?></option><?php endforeach; ?>
          </datalist>
        <?php endif; ?>
        <ol class="questions">
          <?php foreach ($questions as $i => $q): ?>
            <li class="question" id="q-<?= (int) $q['id'] ?>">
              <details<?= !(int) $q['reviewed'] ? ' open' : '' ?>>
                <summary class="question__head" style="cursor:pointer; list-style:none">
                  <span class="question__no" aria-hidden="true"></span>
                  <span class="question__prompt"><?= e(str_limit($q['prompt'], 160)) ?>
                    <span class="row__meta" style="font-weight:400"><span><?= e($typeLabels[$q['qtype']]) ?></span><?php if ($q['topic']): ?><span><?= e($q['topic']) ?></span><?php endif; ?><?php if (isset($tagged[(int) $q['competency_id']])): ?><span class="code-text"><?= e($tagged[(int) $q['competency_id']]['code']) ?></span><?php endif; ?></span>
                  </span>
                  <span class="cluster" style="flex-shrink:0">
                    <?php if (!(int) $q['reviewed']): ?><?= badge(t('AI draft: needs review'), 'amber') ?><?php elseif ((int) $q['ai_generated']): ?><?= badge(t('AI draft, reviewed'), 'neutral') ?><?php endif; ?>
                    <span class="question__points"><?= e(tp(':count pt', ':count pts', (int) round($q['points']))) ?></span>
                  </span>
                </summary>
                <div class="question__body" style="padding-top: 12px">
                  <?php if ($locked): ?>
                    <div class="prose"><?= markdown($q['prompt']) ?></div>
                  <?php else: ?>
                    <form method="post" action="<?= e(url($base . '/questions/' . (int) $q['id'])) ?>">
                      <?= csrf_field() ?>
                      <?= partial('question_fields', ['q' => $q, 'prefix' => 'q' . (int) $q['id']]) ?>
                      <div class="cluster" style="margin-top: 8px">
                        <button class="btn btn--primary btn--sm" type="submit"><?= e((int) $q['reviewed'] ? t('Save question') : t('Save and approve')) ?></button>
                      </div>
                    </form>
                    <div class="cluster" style="margin-top: 8px">
                      <?php if (!(int) $q['reviewed']): ?>
                        <form method="post" action="<?= e(url($base . '/questions/' . (int) $q['id'] . '/approve')) ?>"><?= csrf_field() ?><button class="btn btn--secondary btn--sm" type="submit"><?= icon('check', 'icon--sm') ?><?= e(t('Approve as is')) ?></button></form>
                      <?php endif; ?>
                      <form method="post" action="<?= e(url($base . '/questions/' . (int) $q['id'] . '/move')) ?>"><?= csrf_field() ?><input type="hidden" name="direction" value="up"><button class="btn btn--quiet btn--sm" type="submit"<?= $i === 0 ? ' disabled' : '' ?>><?= icon('arrow-up', 'icon--sm') ?><?= e(t('Move up')) ?></button></form>
                      <form method="post" action="<?= e(url($base . '/questions/' . (int) $q['id'] . '/move')) ?>"><?= csrf_field() ?><input type="hidden" name="direction" value="down"><button class="btn btn--quiet btn--sm" type="submit"<?= $i === count($questions) - 1 ? ' disabled' : '' ?>><?= icon('arrow-down', 'icon--sm') ?><?= e(t('Move down')) ?></button></form>
                      <form method="post" action="<?= e(url($base . '/questions/' . (int) $q['id'] . '/delete')) ?>" data-confirm="<?= e(t('This question will be removed.')) ?>" data-confirm-title="<?= e(t('Remove question?')) ?>" data-confirm-action="<?= e(t('Remove')) ?>" data-confirm-tone="danger"><?= csrf_field() ?><button class="btn btn--danger-quiet btn--sm" type="submit"><?= e(t('Remove')) ?></button></form>
                    </div>
                  <?php endif; ?>
                  <?php if ($competencies || isset($tagged[(int) $q['competency_id']])): $tag = $tagged[(int) $q['competency_id']] ?? null; ?>
                    <?php /* The competency the question assesses: a tag, so it can change after students have answered. */ ?>
                    <form class="competency-tag" method="post" action="<?= e(url($base . '/questions/' . (int) $q['id'] . '/competency')) ?>">
                      <?= csrf_field() ?>
                      <label class="field__label" for="qc-<?= (int) $q['id'] ?>"><?= e(t('Learning competency')) ?></label>
                      <div class="cluster">
                        <input class="input competency-tag__code code-text" id="qc-<?= (int) $q['id'] ?>" name="code" list="competency-codes" value="<?= e($tag['code'] ?? '') ?>" maxlength="60" autocomplete="off" aria-describedby="qc-<?= (int) $q['id'] ?>-hint">
                        <button class="btn btn--secondary btn--sm" type="submit"><?= e(t('Save competency')) ?></button>
                      </div>
                      <p class="field__hint" id="qc-<?= (int) $q['id'] ?>-hint"><?= e($tag ? $tag['description'] : t('Type or choose a code from your school\'s list. Leave it empty for none.')) ?></p>
                    </form>
                  <?php endif; ?>
                </div>
              </details>
            </li>
          <?php endforeach; ?>
        </ol>
        <?php if (!$locked): ?>
          <details class="panel" id="new-question" style="margin-top: 16px"<?= errors() && field_error('prompt') !== null ? ' open' : '' ?>>
            <summary style="cursor:pointer; font-weight:700"><?= icon('plus', 'icon--sm') ?> <?= e(t('Add a question')) ?></summary>
            <form method="post" action="<?= e(url($base . '/questions')) ?>" style="margin-top: 16px">
              <?= csrf_field() ?>
              <?= partial('question_fields', ['q' => null, 'prefix' => 'new']) ?>
              <button class="btn btn--primary" type="submit"><?= e(t('Add question')) ?></button>
            </form>
          </details>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <section class="section" id="rubric" aria-labelledby="r-title">
      <div class="section__head"><h2 class="section__title" id="r-title"><?= e(t('Rubric')) ?></h2><span class="meta"><?= e($isQuiz ? t('Optional; used when marking essay answers.') : t('Optional; sets the points and guides marking.')) ?></span></div>
      <form method="post" action="<?= e(url($base . '/rubric')) ?>" data-rubric>
        <?= csrf_field() ?>
        <?php if (field_error('rubric')): ?><p class="field__error"><?= e(field_error('rubric')) ?></p><?php endif; ?>
        <div data-rubric-rows>
          <?php foreach ($rubric ?: [['title' => '', 'points' => '', 'description' => '']] as $i => $c): ?>
            <div class="form-grid" data-rubric-row style="grid-template-columns: 2fr 90px 3fr auto; align-items:end; border-bottom: 1px solid var(--rule); padding-bottom: 8px; margin-bottom: 8px">
              <div class="field" style="margin:0"><label class="field__label" for="rc-t-<?= $i ?>"><?= e(t('Criterion')) ?></label><input class="input" id="rc-t-<?= $i ?>" name="criterion_title[]" value="<?= e($c['title']) ?>" maxlength="120"></div>
              <div class="field" style="margin:0"><label class="field__label" for="rc-p-<?= $i ?>"><?= e(t('Points')) ?></label><input class="input" id="rc-p-<?= $i ?>" type="number" name="criterion_points[]" min="0.5" max="100" step="0.5" value="<?= e($c['points'] === '' ? '' : fmt_number($c['points'], 2)) ?>"></div>
              <div class="field" style="margin:0"><label class="field__label" for="rc-d-<?= $i ?>"><?= e(t('What it looks like')) ?></label><input class="input" id="rc-d-<?= $i ?>" name="criterion_description[]" value="<?= e($c['description'] ?? '') ?>" maxlength="400"></div>
              <button type="button" class="btn btn--danger-quiet btn--sm" data-rubric-remove aria-label="<?= e(t('Remove criterion')) ?>"><?= icon('x', 'icon--sm') ?></button>
            </div>
          <?php endforeach; ?>
        </div>
        <template>
          <div class="form-grid" data-rubric-row style="grid-template-columns: 2fr 90px 3fr auto; align-items:end; border-bottom: 1px solid var(--rule); padding-bottom: 8px; margin-bottom: 8px">
            <div class="field" style="margin:0"><label class="field__label"><?= e(t('Criterion')) ?><input class="input" name="criterion_title[]" maxlength="120"></label></div>
            <div class="field" style="margin:0"><label class="field__label"><?= e(t('Points')) ?><input class="input" type="number" name="criterion_points[]" min="0.5" max="100" step="0.5"></label></div>
            <div class="field" style="margin:0"><label class="field__label"><?= e(t('What it looks like')) ?><input class="input" name="criterion_description[]" maxlength="400"></label></div>
            <button type="button" class="btn btn--danger-quiet btn--sm" data-rubric-remove aria-label="<?= e(t('Remove criterion')) ?>"><?= icon('x', 'icon--sm') ?></button>
          </div>
        </template>
        <div class="cluster" style="margin-top: 8px">
          <button type="button" class="btn btn--quiet btn--sm" data-rubric-add><?= icon('plus', 'icon--sm') ?><?= e(t('Add criterion')) ?></button>
          <span class="meta"><?= e(t('Total:')) ?> <strong data-rubric-total><?= e(fmt_number(array_sum(array_column($rubric, 'points')), 2)) ?></strong> <?= e(t('points')) ?></span>
          <button class="btn btn--primary btn--sm" type="submit" style="margin-left:auto"><?= e(t('Save rubric')) ?></button>
        </div>
      </form>
    </section>

    <section class="section" id="settings" aria-labelledby="s-title">
      <div class="section__head"><h2 class="section__title" id="s-title"><?= e(t('Settings')) ?></h2></div>
      <form method="post" action="<?= e(url($base)) ?>" data-unsaved-warning>
        <?= csrf_field() ?>
        <?= partial('assignment_settings', ['assignment' => $a, 'kind' => $kind, 'classes' => $classes, 'selectedClass' => $selectedClass, 'modules' => $modules, 'categories' => $categories, 'gradingPeriods' => $gradingPeriods ?? []]) ?>
        <?php if ($allMaterials): ?>
          <fieldset>
            <legend><?= e(t('Resources shown with this work')) ?></legend>
            <p class="fieldset-hint"><?= e(t('Students see links to these materials once the materials are released.')) ?></p>
            <?php foreach ($allMaterials as $m): ?><?= checkbox('resources[]', $m['title'], in_array((int) $m['id'], $resources, true), null, (string) $m['id']) ?><?php endforeach; ?>
          </fieldset>
        <?php endif; ?>
        <div class="form-actions"><button class="btn btn--primary" type="submit" data-busy="<?= e(t('Saving…')) ?>"><?= e(t('Save settings')) ?></button></div>
      </form>
    </section>
  </div>

  <aside class="stack">
    <?php if (!$locked): ?>
      <section class="panel" id="ai-draft" aria-labelledby="ai-title">
        <h2 class="panel__title" id="ai-title"><?= icon('square-pen', 'icon--sm') ?> <?= e(t('Draft questions from materials')) ?></h2>
        <p style="font-size:.9375rem"><?= e(t('B.E.S.T. Learning and Interactive Study System drafts questions from the materials you choose. Every drafted question waits for your review and cannot be published until you approve it.')) ?></p>
        <?php if (!$materials): ?>
          <p class="meta"><?= e(t('No readable materials in this class yet. Upload notes first.')) ?></p>
        <?php else: ?>
          <form method="post" action="<?= e(url($base . '/questions/generate')) ?>">
            <?= csrf_field() ?>
            <fieldset>
              <legend class="field__label"><?= e(t('Materials to use')) ?></legend>
              <?php foreach ($materials as $m): ?><?= checkbox('materials[]', $m['title'], in_array((int) $m['id'], $resources, true), null, (string) $m['id']) ?><?php endforeach; ?>
              <?php if (field_error('materials')): ?><p class="field__error"><?= e(field_error('materials')) ?></p><?php endif; ?>
            </fieldset>
            <?= field('text', 'topic', t('Focus on'), ['optional_label' => true, 'id' => 'ai-topic', 'placeholder' => t('For example: gas exchange'), 'attrs' => ['maxlength' => 160]]) ?>
            <div class="form-grid" style="grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0 8px">
              <?= field('number', 'count_mc', t('Multiple choice'), ['value' => '3', 'attrs' => ['min' => 0, 'max' => 10]]) ?>
              <?= field('number', 'count_tf', t('True or false'), ['value' => '1', 'attrs' => ['min' => 0, 'max' => 10]]) ?>
              <?= field('number', 'count_sa', t('Short answer'), ['value' => '1', 'attrs' => ['min' => 0, 'max' => 10]]) ?>
            </div>
            <button class="btn btn--secondary btn--block" type="submit" data-busy="<?= e(t('Drafting…')) ?>"><?= e(t('Draft questions')) ?></button>
            <?php $aiNote = implode('. ', array_filter([\Sibol\Services\Ai\Providers::publicLabel(), $aiRemaining !== null ? tp(':count AI request left today', ':count AI requests left today', $aiRemaining) : ''])); ?>
            <?php if ($aiNote !== ''): ?><p class="meta" style="margin-top:8px"><?= e($aiNote) ?></p><?php endif; ?>
          </form>
        <?php endif; ?>
      </section>
    <?php endif; ?>
    <section class="panel panel--sunken" aria-labelledby="danger-title">
      <h2 class="panel__title" id="danger-title"><?= e(t('Other actions')) ?></h2>
      <div class="cluster">
        <form method="post" action="<?= e(url($base . '/duplicate')) ?>"><?= csrf_field() ?><button class="btn btn--secondary btn--sm" type="submit"><?= icon('copy', 'icon--sm') ?><?= e(t('Duplicate')) ?></button></form>
        <?php if ($a['status'] === 'draft' && !$locked): ?>
          <form method="post" action="<?= e(url($base . '/delete')) ?>" data-confirm="<?= e(t('The draft and its questions are deleted permanently.')) ?>" data-confirm-title="<?= e(t('Delete this draft?')) ?>" data-confirm-action="<?= e(t('Delete')) ?>" data-confirm-tone="danger"><?= csrf_field() ?><button class="btn btn--danger-quiet btn--sm" type="submit"><?= e(t('Delete draft')) ?></button></form>
        <?php else: ?>
          <form method="post" action="<?= e(url($base . '/archive')) ?>" data-confirm="<?= e(t('Students will no longer see it. Released grades stay on record.')) ?>" data-confirm-title="<?= e(t('Archive?')) ?>" data-confirm-action="<?= e(t('Archive')) ?>"><?= csrf_field() ?><button class="btn btn--danger-quiet btn--sm" type="submit"><?= e(t('Archive')) ?></button></form>
        <?php endif; ?>
      </div>
    </section>
  </aside>
</div>
