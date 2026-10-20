<?php
/**
 * The rules for using Sibol, shown on /rules and on the "Before you start" page.
 * @var string|null $role @var array $privacy @var string $headingTag h2 on its own page, h3 inside another
 */
$h = $headingTag ?? 'h2';
$staff = in_array($role ?? '', ['teacher', 'admin'], true);
?>
<div class="prose">
  <<?= $h ?>><?= e(t('Your own work')) ?></<?= $h ?>>
  <ul>
    <li><?= e(t('Hand in work that is your own. You may use the companion to understand a topic, practise and plan, but not to write graded work for you.')) ?></li>
    <li><?= e(t('Answer quizzes yourself. Do not share quiz questions or answers while a quiz is open, and do not ask anyone, or the companion, to answer for you.')) ?></li>
    <li><?= e(t('If the companion helped you understand something you used in your work, say so when your teacher asks.')) ?></li>
  </ul>

  <<?= $h ?>><?= e(t('What the companion will and will not do')) ?></<?= $h ?>>
  <ul>
    <li><?= e(t('It explains topics, asks you questions, makes flashcards and study plans, and shows which class material each point comes from.')) ?></li>
    <li><?= e(t('It will not write graded assignments, give you quiz answers, or decide your grade. Your teacher does that.')) ?></li>
    <li><?= e(t('It can be wrong. Check what it says against your class materials and ask your teacher when unsure.')) ?></li>
    <li><?= e(t('Your conversations are private. Teachers see only an excerpt you choose to share; administrators see only an answer someone reports.')) ?></li>
  </ul>

  <<?= $h ?>><?= e(t('Keeping yourself and others safe')) ?></<?= $h ?>>
  <ul>
    <li><?= e(t('Keep your password and your sign-in slip to yourself. Sign out on shared computers.')) ?></li>
    <li><?= e(t('Do not type your address, phone number or other people\'s personal information into the companion.')) ?></li>
    <li><?= e(t('Be respectful in everything you write. Use Report on a companion answer that is wrong, unkind or upsetting.')) ?></li>
    <?php if (trim((string) ($privacy['help_text'] ?? '')) !== ''): ?><li><?= e((string) $privacy['help_text']) ?></li><?php endif; ?>
  </ul>

  <?php if ($staff): ?>
    <<?= $h ?>><?= e(t('For teachers and administrators')) ?></<?= $h ?>>
    <ul>
      <li><?= e(t('AI drafts of questions, lessons and feedback are suggestions. Read and correct them before students see anything.')) ?></li>
      <li><?= e(t('Students\' companion conversations are private. There is no tool to read them, and none should be asked for.')) ?></li>
      <li><?= e(t('Print sign-in and password slips only for students you teach or advise, and hand them over in person.')) ?></li>
      <li><?= e(t('Use real student information only as your school\'s data protection officer allows.')) ?></li>
    </ul>
  <?php endif; ?>
</div>
