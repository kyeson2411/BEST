<?php /** @var array $section @var array $student @var list<array> $answers @var string $action */
use Sibol\Services\Sections;
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(user_name($student)) ?></h1>
    <p class="page-lead"><?= e(Sections::label($section)) ?> · <span class="code-text"><?= e((string) $student['username']) ?></span></p>
  </div>
</div>
<div style="max-width: 640px">
  <?= partial('student_ai_panel', ['student' => $student, 'answers' => $answers, 'action' => $action]) ?>
  <p class="meta" style="margin-top: 16px"><?= e(t('Changes are recorded in the audit log. Nothing about a student whose AI is off is sent to an AI service; the rest of their work in Sibol is unaffected.')) ?></p>
</div>
