<?php
/**
 * The privacy notice text, shown on /privacy and on the "Before you start" page.
 * @var array|null $org @var array $privacy @var string $aiProvider @var string $headingTag h2 on its own page, h3 inside another
 */
$school = $org['name'] ?? t('your school');
$days = (int) ($privacy['ai_retention_days'] ?? 365);
$h = $headingTag ?? 'h2';
?>
<div class="prose">
  <<?= $h ?>><?= e(t('Who is responsible')) ?></<?= $h ?>>
  <p><?= e(t(':school decides how B.E.S.T. Learning and Interactive Study System is used and is responsible for the information in it. B.E.S.T. Learning and Interactive Study System is the tool the school uses.', ['school' => $school])) ?>
    <?= e(t('Questions or requests go to :contact', ['contact' => $privacy['contact_name'] ?: t('the school data protection officer')])) ?><?php if (!empty($privacy['contact_email'])): ?> (<a href="mailto:<?= e($privacy['contact_email']) ?>"><?= e($privacy['contact_email']) ?></a>)<?php endif; ?>.</p>

  <<?= $h ?>><?= e(t('What B.E.S.T. Learning and Interactive Study System keeps')) ?></<?= $h ?>>
  <ul>
    <li><?= e(t('Your account: name, username, school email if you have one, role, grade level, optional photo, language and accessibility preferences.')) ?></li>
    <li><?= e(t('Your classes, the work you submit (answers and files), the scores your teachers enter for work done in class or on paper, and the grades and feedback your teachers release to you.')) ?></li>
    <li><?= e(t('Changes your teachers make to your deadlines: your own due date, extra time on a quiz, being excused, or being marked absent for work done in class. No reason is recorded.')) ?></li>
    <li><?= e(t('While you take a quiz, answers that have not reached B.E.S.T. Learning and Interactive Study System yet are kept in that browser tab only, and removed once they arrive, when you submit, or when you sign out.')) ?></li>
    <li><?= e(t('Your own study activity: which materials you opened, practice sets, flashcards and study sessions you planned.')) ?></li>
    <li><?= e(t('Practice sets you keep for offline practice, with their answers and any results not sent yet, stay in that browser on that device until you sign out.')) ?></li>
    <li><?= e(t('Your companion conversations: the questions you ask and the answers, with the sources they cite.')) ?></li>
    <li><?= e(t('When you read and accepted this notice and the rules for using B.E.S.T. Learning and Interactive Study System; whether the AI companion is switched on for you; and, if your school recorded one, your parent\'s or guardian\'s yes or no about the AI companion with its date. No signed forms are stored in B.E.S.T. Learning and Interactive Study System.')) ?></li>
    <li><?= e(t('Security records: when you signed in and important changes, with a scrambled network identifier. Passwords are stored only as one-way hashes.')) ?></li>
  </ul>

  <<?= $h ?>><?= e(t('Who can see it')) ?></<?= $h ?>>
  <ul>
    <li><?= e(t('You can see everything about your own account, work, feedback and conversations.')) ?></li>
    <li><?= e(t('Your teachers see your work, grades and progress in the classes they teach. They do not see your companion conversations or your private practice unless you choose to share an excerpt.')) ?></li>
    <li><?= e(t('Your section\'s adviser and your teachers can print a sign-in slip for you if you forget your password. Nobody at school ever sees your password.')) ?></li>
    <li><?= e(t('School administrators see accounts, classes, and school-wide totals that do not identify individual students. Normal administrator tools do not show companion conversations.')) ?></li>
    <li><?= e(t('If you report a companion answer, that answer and your note are shown to administrators so they can look into it.')) ?></li>
    <li><?= e(tp('When your class materials do not answer a question you ask the companion, its key words are counted for your class (not your question, not your name). Your teachers see a topic only after at least :count different student asked about it, so they know what to add or reteach. These counts are removed after :days days.', 'When your class materials do not answer a question you ask the companion, its key words are counted for your class (not your question, not your name). Your teachers see a topic only after at least :count different students asked about it, so they know what to add or reteach. These counts are removed after :days days.', \Sibol\Services\MaterialGaps::MIN_STUDENTS, ['days' => \Sibol\Services\MaterialGaps::KEEP_DAYS])) ?></li>
  </ul>

  <<?= $h ?>><?= e(t('How the AI companion uses your information')) ?></<?= $h ?>>
  <?php if ($aiProvider === 'demo'): ?>
    <p><?= e(t('B.E.S.T. Learning and Interactive Study System is currently in demo mode: no AI service is connected, and nothing you type leaves the school\'s server. Answers are built from passages of your class materials.')) ?></p>
  <?php else: ?>
    <p><?= e(t('When you ask the companion something, B.E.S.T. Learning and Interactive Study System sends your question, recent messages in that conversation, and the relevant passages from your released class materials to the AI service the school has configured. Your name and email are not included.')) ?></p>
    <p><?= e(t('How long that service keeps request data, and whether it may use it, depends on the school\'s agreement with the provider. Ask the contact above for details. B.E.S.T. Learning and Interactive Study System does not use your conversations to train any model.')) ?></p>
  <?php endif; ?>
  <p><?= e(t('Your adviser or a school administrator can switch the companion and AI practice off for you, for example if your parent or guardian does not agree to it. Then nothing about you is sent to an AI service, and the rest of B.E.S.T. Learning and Interactive Study System works as usual.')) ?></p>
  <p><?= e(t('AI can be wrong. Your teacher\'s instructions always take priority, and the companion never decides your grade.')) ?></p>

  <<?= $h ?>><?= e(t('How long it is kept')) ?></<?= $h ?>>
  <ul>
    <li><?= e(tp('Companion conversations are deleted :count day after the last message.', 'Companion conversations are deleted :count days after the last message.', $days)) ?>
      <?php if (!empty($privacy['students_can_delete_chats'])): ?><?= e(t('You can also delete a conversation yourself at any time.')) ?><?php endif; ?></li>
    <li><?= e(t('Grades, submissions and class records are kept for as long as the school\'s records policy requires.')) ?></li>
  </ul>

  <<?= $h ?>><?= e(t('Your rights')) ?></<?= $h ?>>
  <p><?= e(t('Under the Data Privacy Act of 2012 (Republic Act No. 10173) you have the right to be informed, to access your information, to object, to have incorrect information corrected, to ask for erasure or blocking, to receive a copy of your data, and to complain to the National Privacy Commission.')) ?></p>
  <p><?= e(t('To ask for a copy of your data, a correction, or deletion, open Account and choose a privacy request. The school reviews each request; some school records may have to be kept by law or policy, and you will be told if so.')) ?></p>

  <<?= $h ?>><?= e(t('If something worries you')) ?></<?= $h ?>>
  <p><?= e((string) $privacy['help_text']) ?></p>
</div>
