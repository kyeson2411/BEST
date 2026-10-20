<?php
/** @var array|null $org @var array $privacy @var string $aiProvider */
$school = $org['name'] ?? t('your school');
$days = (int) ($privacy['ai_retention_days'] ?? 365);
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Privacy notice')) ?></h1>
    <p class="page-lead"><?= e(t('How :school uses Sibol, what information it keeps about you, and what you can do about it.', ['school' => $school])) ?></p>
  </div>
</div>

<div class="prose">
  <h2><?= e(t('Who is responsible')) ?></h2>
  <p><?= e(t(':school decides how Sibol is used and is responsible for the information in it. Sibol is the tool the school uses.', ['school' => $school])) ?>
    <?= e(t('Questions or requests go to :contact', ['contact' => $privacy['contact_name'] ?: t('the school data protection officer')])) ?><?php if (!empty($privacy['contact_email'])): ?> (<a href="mailto:<?= e($privacy['contact_email']) ?>"><?= e($privacy['contact_email']) ?></a>)<?php endif; ?>.</p>

  <h2><?= e(t('What Sibol keeps')) ?></h2>
  <ul>
    <li><?= e(t('Your account: name, school email, role, grade level, optional photo, language and accessibility preferences.')) ?></li>
    <li><?= e(t('Your classes, the work you submit (answers and files), and the grades and feedback your teachers release to you.')) ?></li>
    <li><?= e(t('Your own study activity: which materials you opened, practice sets, flashcards and study sessions you planned.')) ?></li>
    <li><?= e(t('Your companion conversations: the questions you ask and the answers, with the sources they cite.')) ?></li>
    <li><?= e(t('Security records: when you signed in and important changes, with a scrambled network identifier. Passwords are stored only as one-way hashes.')) ?></li>
  </ul>

  <h2><?= e(t('Who can see it')) ?></h2>
  <ul>
    <li><?= e(t('You can see everything about your own account, work, feedback and conversations.')) ?></li>
    <li><?= e(t('Your teachers see your work, grades and progress in the classes they teach. They do not see your companion conversations or your private practice unless you choose to share an excerpt.')) ?></li>
    <li><?= e(t('School administrators see accounts, classes, and school-wide totals that do not identify individual students. Normal administrator tools do not show companion conversations.')) ?></li>
    <li><?= e(t('If you report a companion answer, that answer and your note are shown to administrators so they can look into it.')) ?></li>
  </ul>

  <h2><?= e(t('How the AI companion uses your information')) ?></h2>
  <?php if ($aiProvider === 'demo'): ?>
    <p><?= e(t('Sibol is currently in demo mode: no AI service is connected, and nothing you type leaves the school\'s server. Answers are built from passages of your class materials.')) ?></p>
  <?php else: ?>
    <p><?= e(t('When you ask the companion something, Sibol sends your question, recent messages in that conversation, and the relevant passages from your released class materials to the AI service the school has configured. Your name and email are not included.')) ?></p>
    <p><?= e(t('How long that service keeps request data, and whether it may use it, depends on the school\'s agreement with the provider. Ask the contact above for details. Sibol does not use your conversations to train any model.')) ?></p>
  <?php endif; ?>
  <p><?= e(t('AI can be wrong. Your teacher\'s instructions always take priority, and the companion never decides your grade.')) ?></p>

  <h2><?= e(t('How long it is kept')) ?></h2>
  <ul>
    <li><?= e(tp('Companion conversations are deleted :count day after the last message.', 'Companion conversations are deleted :count days after the last message.', $days)) ?>
      <?php if (!empty($privacy['students_can_delete_chats'])): ?><?= e(t('You can also delete a conversation yourself at any time.')) ?><?php endif; ?></li>
    <li><?= e(t('Grades, submissions and class records are kept for as long as the school\'s records policy requires.')) ?></li>
  </ul>

  <h2><?= e(t('Your rights')) ?></h2>
  <p><?= e(t('Under the Data Privacy Act of 2012 (Republic Act No. 10173) you have the right to be informed, to access your information, to object, to have incorrect information corrected, to ask for erasure or blocking, to receive a copy of your data, and to complain to the National Privacy Commission.')) ?></p>
  <p><?= e(t('To ask for a copy of your data, a correction, or deletion, open Account and choose a privacy request. The school reviews each request; some school records may have to be kept by law or policy, and you will be told if so.')) ?></p>

  <h2><?= e(t('If something worries you')) ?></h2>
  <p><?= e((string) $privacy['help_text']) ?></p>

  <p class="callout callout--amber" style="margin-top: 32px"><?= icon('info') ?><span><?= e(t('This notice describes how this installation of Sibol actually works. The school must review it with its data protection officer and legal adviser before using Sibol with real student information; technical safeguards alone do not make a school compliant.')) ?></span></p>
</div>
