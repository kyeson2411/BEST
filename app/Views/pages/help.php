<?php /** @var string|null $role @var array $privacy @var bool $aiDemo */ ?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Help')) ?></h1>
    <p class="page-lead"><?= e(t('How B.E.S.T. Learning and Interactive Study System works and where to get help.')) ?></p>
  </div>
</div>

<div class="prose">
  <?php if ($role === 'student' || $role === null): ?>
    <h2><?= e(t('For students')) ?></h2>
    <ul>
      <li><?= e(t('Home shows what is due soon and where you left off. Assignments lists everything, with filters for upcoming, submitted, graded and overdue work.')) ?></li>
      <li><?= e(t('To join a class, open Classes and enter the code your teacher gives you.')) ?></li>
      <li><?= e(t('Save a draft as often as you like. Your work counts only after you choose Submit, and you get a receipt with a code.')) ?></li>
      <li><?= e(t('Grades and feedback appear when your teacher releases them.')) ?></li>
    </ul>
  <?php endif; ?>
  <?php if ($role === 'teacher' || $role === null): ?>
    <h2><?= e(t('For teachers')) ?></h2>
    <ul>
      <li><?= e(t('Upload materials to a class, then publish them. Only published, released materials are visible to students and to the companion.')) ?></li>
      <li><?= e(t('AI drafts of lessons and questions stay drafts until you review and publish them. AI grading help is a suggestion you can accept, change or ignore.')) ?></li>
      <li><?= e(t('Feedback reaches students only when you release it.')) ?></li>
    </ul>
  <?php endif; ?>
  <?php if ($role === 'admin' || $role === null): ?>
    <h2><?= e(t('For school administrators')) ?></h2>
    <ul>
      <li><?= e(t('Invite people one at a time or import a CSV. B.E.S.T. Learning and Interactive Study System shows each invitation link for you to share; no email is sent unless an email service is configured.')) ?></li>
      <li><?= e(t('Policies control which companion modes are available, daily limits, and how long conversations are kept.')) ?></li>
    </ul>
  <?php endif; ?>

  <h2><?= e(t('The learning companion')) ?></h2>
  <ul>
    <li><?= e(t('Choose a mode: explain, step-by-step guidance, practice, flashcards, summaries, study plans, vocabulary, or questions about your class materials.')) ?></li>
    <li><?= e(t('When an answer uses your class materials, numbered sources appear beside it. Select a number to see the exact passage and page.')) ?></li>
    <li><?= e(t('If the materials do not cover your question, the companion says so. Any general explanation is labelled as not coming from your class.')) ?></li>
    <li><?= e(t('If an answer seems wrong or unsafe, use Report. Your report and that answer go to school administrators.')) ?></li>
  </ul>

  <h2 id="demo"><?= e(t('Demo mode')) ?></h2>
  <p><?= e(t('A demo school contains only fictional people and records, so you can try every role safely.')) ?></p>
  <?php if ($aiDemo): ?>
    <p><?= e(t('AI demo mode means no AI service is connected. The companion finds the most relevant passages in your released class materials and shows them with their real sources, and practice items are made from those passages. It does not write new explanations. A school administrator can connect an AI provider in the server configuration.')) ?></p>
  <?php else: ?>
    <p><?= e(t('An AI provider is connected, so the companion writes new answers. They are still checked against your class materials when you ask about them.')) ?></p>
  <?php endif; ?>

  <h2 id="offline"><?= e(t('Installing B.E.S.T. Learning and Interactive Study System and reading offline')) ?></h2>
  <ul>
    <li><?= e(t('On a phone or computer, choose Install app or Add to Home screen in the browser\'s menu. B.E.S.T. Learning and Interactive Study System then opens like an app, with the school\'s seal as its icon.')) ?></li>
    <?php if ($role === 'student' || $role === null): ?>
      <li><?= e(t('On a class\'s Materials tab, choose Keep offline beside a file to read it on that device without a connection. Kept files are removed when you sign out.')) ?></li>
    <?php endif; ?>
    <li><?= e(t('Other pages, quizzes and the companion need a connection. Without one, B.E.S.T. Learning and Interactive Study System says so and lists the files kept on that device.')) ?></li>
  </ul>

  <h2><?= e(t('Keyboard and accessibility')) ?></h2>
  <ul>
    <li><?= e(t('Every action works with a keyboard. Use Tab to move, Enter to activate, and Escape to close menus and dialogs.')) ?></li>
    <li><?= e(t('In the companion, press Enter to send and Shift+Enter for a new line.')) ?></li>
    <li><?= e(t('Account and preferences lets you choose larger text, reduced motion, underlined links, and your language.')) ?></li>
  </ul>

  <h2><?= e(t('Getting help')) ?></h2>
  <p><?= e((string) $privacy['help_text']) ?></p>
</div>
