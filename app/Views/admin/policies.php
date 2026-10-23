<?php /** @var array $settings @var string $provider @var array $noticeProgress @var array $noticeVersions */
use Sibol\Services\Settings;

$ai = $settings['ai'];
$features = $settings['features'];
$review = $settings['review'];
$codes = $settings['class_codes'];
$privacy = $settings['privacy'];
$notify = $settings['notifications'];
$notices = $settings['notices'];
$modes = (array) $ai['student_modes'];
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Policies')) ?></h1>
    <p class="page-lead"><?= e(t('School-wide rules for the AI companion, features, class codes, privacy and notifications. Changes apply to everyone at once.')) ?></p>
  </div>
</div>

<nav class="tabs" aria-label="<?= e(t('Policy sections')) ?>">
  <a class="tab" href="#ai"><?= e(t('AI companion')) ?></a>
  <a class="tab" href="#features"><?= e(t('Features')) ?></a>
  <a class="tab" href="#codes"><?= e(t('Class codes')) ?></a>
  <a class="tab" href="#notices"><?= e(t('Notice and rules')) ?></a>
  <a class="tab" href="#privacy"><?= e(t('Privacy and retention')) ?></a>
  <a class="tab" href="#notifications"><?= e(t('Notifications')) ?></a>
</nav>

<form method="post" action="<?= e(url('/admin/policies')) ?>" data-unsaved-warning class="policy-form">
  <?= csrf_field() ?>

  <section class="section" id="ai" aria-labelledby="ai-title">
    <div class="section__head"><h2 class="section__title" id="ai-title"><?= e(t('AI companion')) ?></h2><span class="meta"><?= e($provider) ?></span></div>
    <div class="grid-2">
      <div>
        <?= checkbox('ai_enabled', t('AI tools are on'), (bool) $ai['enabled'], t('Turning this off hides the companion and AI drafting for everyone. Everything else keeps working.')) ?>
        <?= checkbox('teacher_tools', t('Teachers can use AI drafting tools'), (bool) $ai['teacher_tools'], t('Question drafts, lesson drafts, grading suggestions and reteaching ideas. Teachers always review before anything reaches students.')) ?>
        <?= checkbox('general_answers', t('Allow general explanations beyond class materials'), (bool) $ai['general_answers'], t('When off, the companion only answers from released class materials and says so when they do not cover a question.')) ?>
        <?= checkbox('students_start_on', t('A student\'s AI starts on'), (bool) $ai['students_start_on'], t('Advisers and administrators can switch it off for one student, for example when a guardian says no. Untick to keep it off for each student until one of them switches it on, for example after a guardian says yes.')) ?>
      </div>
      <fieldset class="fieldset-plain<?= field_error('student_modes') ? ' field--error' : '' ?>">
        <legend class="field__label"><?= e(t('Companion modes students can use')) ?></legend>
        <?php foreach (Settings::STUDENT_MODES as $mode): ?>
          <?= checkbox('student_modes[]', Settings::modeLabel($mode), in_array($mode, $modes, true), null, $mode, 'mode-' . $mode) ?>
        <?php endforeach; ?>
        <?php if (field_error('student_modes')): ?><p class="field__error"><?= icon('circle-alert', 'icon--xs') ?><?= e(field_error('student_modes')) ?></p><?php endif; ?>
      </fieldset>
    </div>
    <div class="form-grid" style="margin-top: 16px">
      <?= field('number', 'student_daily_messages', t('Messages per student per day'), ['value' => (string) $ai['student_daily_messages'], 'required' => true, 'hint' => t('0 means no daily limit.'), 'attrs' => ['min' => 0, 'max' => 1000]]) ?>
      <?= field('number', 'teacher_daily_requests', t('Requests per teacher per day'), ['value' => (string) $ai['teacher_daily_requests'], 'required' => true, 'hint' => t('0 means no daily limit.'), 'attrs' => ['min' => 0, 'max' => 2000]]) ?>
      <?= field('number', 'monthly_token_budget', t('Monthly token budget'), ['value' => (string) $ai['monthly_token_budget'], 'required' => true, 'hint' => t('For the whole school. 0 means no budget. Demo mode uses no tokens.'), 'attrs' => ['min' => 0, 'step' => 1000]]) ?>
      <?= field('number', 'max_question_chars', t('Longest question'), ['value' => (string) $ai['max_question_chars'], 'required' => true, 'hint' => t('In characters.'), 'attrs' => ['min' => 200, 'max' => 8000]]) ?>
    </div>
  </section>

  <section class="section" id="features" aria-labelledby="features-title">
    <div class="section__head"><h2 class="section__title" id="features-title"><?= e(t('Features')) ?></h2></div>
    <div class="grid-2">
      <div>
        <?= checkbox('document_answers', t('Answers from class materials'), (bool) $features['document_answers'], t('“Ask my class materials” and “Summarize my material”, with page references.')) ?>
        <?= checkbox('practice_quizzes', t('Practice quizzes'), (bool) $features['practice_quizzes'], t('Self-check questions that are never graded or seen by teachers.')) ?>
        <?= checkbox('flashcards', t('Flashcards'), (bool) $features['flashcards']) ?>
      </div>
      <div>
        <?= checkbox('study_plans', t('Study plans'), (bool) $features['study_plans'], t('The companion breaks a test into sessions students can add to their calendar.')) ?>
        <?= checkbox('share_with_teacher', t('Students can share an excerpt with their teacher'), (bool) $features['share_with_teacher'], t('Only when a student chooses to. Teachers never see conversations otherwise.')) ?>
        <?= checkbox('spaced_review', t('Students\' review of missed questions'),(bool) $features['spaced_review'], t('Questions a student missed in practice and in quizzes with released answers come back on a schedule. Private, never graded, never seen by teachers.')) ?>
        <?= field('number', 'review_daily_cap', t('Reviews per student per day'), ['value' => (string) $review['daily_cap'], 'required' => true, 'hint' => t('Questions that are due come first. 0 means no daily limit.'), 'attrs' => ['min' => 0, 'max' => 200]]) ?>
      </div>
    </div>
  </section>

  <section class="section" id="codes" aria-labelledby="codes-title">
    <div class="section__head"><h2 class="section__title" id="codes-title"><?= e(t('Class codes')) ?></h2></div>
    <div class="grid-2">
      <?= checkbox('codes_enabled', t('Teachers can create class codes'), (bool) $codes['enabled'], t('Students type a code to join a class. Turning this off disables every current code.')) ?>
      <?= field('number', 'code_ttl_days', t('Class code lifetime'), ['value' => (string) $codes['ttl_days'], 'required' => true, 'hint' => t('Days before a new code stops working. Teachers can turn a code off sooner.'), 'attrs' => ['min' => 1, 'max' => 180]]) ?>
    </div>
  </section>

  <section class="section" id="notices" aria-labelledby="notices-title">
    <div class="section__head"><h2 class="section__title" id="notices-title"><?= e(t('Privacy notice and rules')) ?></h2><a class="section__link" href="<?= e(url('/rules')) ?>"><?= e(t('See the rules')) ?></a></div>
    <p class="section__intro"><?= e(t('People you choose read and accept the privacy notice and the rules for using B.E.S.T. Learning and Interactive Study System when they first sign in and again whenever either changes. B.E.S.T. Learning and Interactive Study System records who accepted which version and when.')) ?></p>
    <div class="grid-2">
      <div>
        <?= checkbox('notices_students', t('Students must accept them'), (bool) $notices['students']) ?>
        <?= checkbox('notices_staff', t('Teachers and administrators must accept them too'), (bool) $notices['staff']) ?>
      </div>
      <dl class="dl">
        <dt><?= e(t('Current versions')) ?></dt><dd><?= e(t('Privacy notice :p, rules :r', ['p' => $noticeVersions['privacy'], 'r' => $noticeVersions['rules']])) ?></dd>
        <?php if ($noticeProgress['required'] > 0): ?>
          <dt><?= e(t('Accepted so far')) ?></dt><dd><?= e(t(':n of :m active accounts that must accept', ['n' => $noticeProgress['accepted'], 'm' => $noticeProgress['required']])) ?></dd>
        <?php endif; ?>
      </dl>
    </div>
  </section>

  <section class="section" id="privacy" aria-labelledby="privacy-title">
    <div class="section__head"><h2 class="section__title" id="privacy-title"><?= e(t('Privacy and retention')) ?></h2><a class="section__link" href="<?= e(url('/privacy')) ?>"><?= e(t('See the privacy notice')) ?></a></div>
    <div class="form-grid">
      <?= field('number', 'ai_retention_days', t('Conversation retention'), ['value' => (string) $privacy['ai_retention_days'], 'required' => true, 'hint' => t('Days of inactivity after which companion conversations are deleted automatically. 0 keeps them until the person deletes them.'), 'attrs' => ['min' => 0, 'max' => 3650]]) ?>
      <div><?= checkbox('students_can_delete_chats', t('Students can delete their conversations'), (bool) $privacy['students_can_delete_chats']) ?></div>
      <?= field('text', 'contact_name', t('Privacy contact'), ['value' => (string) $privacy['contact_name'], 'hint' => t('Shown in the privacy notice, for example your Data Protection Officer.'), 'attrs' => ['maxlength' => 120]]) ?>
      <?= field('email', 'contact_email', t('Privacy contact email'), ['value' => (string) $privacy['contact_email'], 'optional_label' => true]) ?>
      <?= field('textarea', 'help_text', t('Help message'), ['value' => (string) $privacy['help_text'], 'rows' => 3, 'wrap_class' => 'span-2', 'hint' => t('Shown to students in the companion, telling them where to get help from a person.'), 'attrs' => ['maxlength' => 1000]]) ?>
    </div>
  </section>

  <section class="section" id="notifications" aria-labelledby="notif-title">
    <div class="section__head"><h2 class="section__title" id="notif-title"><?= e(t('Notifications')) ?></h2></div>
    <div class="grid-2">
      <div>
        <?= checkbox('notify_new_assignment', t('New assignments and quizzes'), (bool) $notify['new_assignment']) ?>
        <?= checkbox('notify_new_material', t('New class materials'), (bool) $notify['new_material']) ?>
        <?= checkbox('notify_feedback_released', t('Released grades and feedback'), (bool) $notify['feedback_released']) ?>
        <?= checkbox('notify_announcements', t('Class announcements'), (bool) $notify['announcements']) ?>
      </div>
      <?= field('number', 'deadline_reminder_hours', t('Deadline reminder'), ['value' => (string) $notify['deadline_reminder_hours'], 'required' => true, 'hint' => t('Hours before a due date that students get a reminder. 0 turns reminders off.'), 'attrs' => ['min' => 0, 'max' => 168]]) ?>
    </div>
    <p class="meta"><?= e(t('Notifications appear inside B.E.S.T. Learning and Interactive Study System. Each person can also mute types they do not want.')) ?></p>
  </section>

  <div class="form-actions form-actions--sticky">
    <button class="btn btn--primary" type="submit" data-busy="<?= e(t('Saving…')) ?>"><?= e(t('Save policies')) ?></button>
    <span class="meta"><?= e(t('Saved changes are recorded in the audit log.')) ?></span>
  </div>
</form>

<section class="panel panel--sunken" aria-labelledby="renew-title" style="margin-top: 32px; max-width: 760px">
  <h2 class="panel__title" id="renew-title"><?= e(t('Ask everyone to read them again')) ?></h2>
  <p class="meta"><?= e(t('Use this after your school changes how it uses B.E.S.T. Learning and Interactive Study System, for example a new privacy contact or a decision by your data protection officer. Everyone the policy names reads and accepts the notice and the rules again before they continue.')) ?></p>
  <form method="post" action="<?= e(url('/admin/policies/notices')) ?>" data-confirm="<?= e(t('The next time they open B.E.S.T. Learning and Interactive Study System, people will have to read and accept the privacy notice and the rules again.')) ?>" data-confirm-title="<?= e(t('Ask everyone again?')) ?>" data-confirm-action="<?= e(t('Ask again')) ?>">
    <?= csrf_field() ?><button class="btn btn--secondary" type="submit"><?= icon('refresh-cw', 'icon--sm') ?><?= e(t('Ask everyone again')) ?></button>
  </form>
</section>
