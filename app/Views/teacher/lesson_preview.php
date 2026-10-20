<?php /** @var array $lesson @var array $class @var array $content */ ?>
<div class="callout callout--amber" style="margin-bottom: 24px"><?= icon('eye') ?><p><?= e(t('Preview of your current draft, as students would see it.')) ?> <a href="<?= e(url('/teacher/lessons/' . (int) $lesson['id'])) ?>"><?= e(t('Back to editing')) ?></a></p></div>
<?= \Sibol\Core\View::file('student/lesson', ['class' => $class, 'lesson' => $lesson, 'content' => $content]) ?>
