<?php
/**
 * @var array $class @var string $tab @var string $q @var list<array> $roster @var int $dueCount @var list<array> $teachers @var list<array> $modules
 * @var list<array> $announcements @var list<array> $shares @var list<array> $work @var array $codePolicy
 */
use Sibol\Core\Db;

$base = '/teacher/classes/' . (int) $class['id'];
$activeStudents = array_values(array_filter($roster, static fn ($r) => $r['status'] === 'active'));
$unseen = count(array_filter($shares, static fn ($s) => $s['seen_at'] === null));
$codeLive = $class['join_code_enabled'] && $class['join_code'] && $class['join_code_expires_at'] > Db::now();
$tabs = [
    'overview' => t('Overview'),
    'roster' => t('Roster') . ' (' . count($activeStudents) . ')',
    'modules' => t('Modules'),
    'announcements' => t('Announcements'),
    'shared' => t('Shared with you') . ($unseen ? ' (' . $unseen . ')' : ''),
];
$archived = $class['status'] !== 'active';
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e($class['name']) ?></h1>
    <p class="page-lead"><?= e($class['subject_name']) ?><?= $class['period_name'] ? ', ' . e($class['period_name']) : '' ?><?= $archived ? ', ' . e(t('archived')) : '' ?></p>
  </div>
  <div class="page-actions">
    <a class="btn btn--secondary" href="<?= e(url('/teacher/materials', ['class_id' => (int) $class['id']])) ?>"><?= icon('folder-open', 'icon--sm') ?><?= e(t('Materials')) ?></a>
    <a class="btn btn--secondary" href="<?= e(url('/teacher/gradebook/' . (int) $class['id'])) ?>"><?= icon('table', 'icon--sm') ?><?= e(t('Gradebook')) ?></a>
    <a class="btn btn--secondary" href="<?= e(url('/teacher/insights/' . (int) $class['id'])) ?>"><?= icon('chart-column', 'icon--sm') ?><?= e(t('Insights')) ?></a>
    <?php if (!$archived): ?><a class="btn btn--primary" href="<?= e(url('/teacher/assignments/new', ['class_id' => (int) $class['id']])) ?>"><?= icon('plus', 'icon--sm') ?><?= e(t('New assignment')) ?></a><?php endif; ?>
  </div>
</div>

<nav class="tabs" aria-label="<?= e(t('Class sections')) ?>">
  <?php foreach ($tabs as $key => $label): ?>
    <a class="tab" href="<?= e(url($base, $key === 'overview' ? [] : ['tab' => $key])) ?>"<?= $tab === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>

<?php if ($tab === 'overview'): ?>
  <div class="layout-split">
    <section class="section" aria-labelledby="work-title">
      <div class="section__head"><h2 class="section__title" id="work-title"><?= e(t('Recent and upcoming work')) ?></h2><a class="section__link" href="<?= e(url('/teacher/assignments', ['class_id' => (int) $class['id']])) ?>"><?= e(t('All work')) ?></a></div>
      <?php if (!$work): ?>
        <?= partial('empty', ['compact' => true, 'title' => t('No assignments yet'), 'body' => t('Create an assignment or quiz for this class.'), 'action' => [t('New assignment'), '/teacher/assignments/new?class_id=' . (int) $class['id']]]) ?>
      <?php else: ?>
        <ul class="entries">
          <?php foreach ($work as $w): ?>
            <li class="entry">
              <?= partial('entry_date', ['at' => $w['due_at']]) ?>
              <div class="entry__main">
                <a class="row__title" href="<?= e(url('/teacher/assignments/' . (int) $w['id'])) ?>"><?= e($w['title']) ?></a>
                <div class="row__meta"><span><?= e(work_kind((string) $w['kind'])) ?></span><span><?= e(assignment_status($w)[0]) ?></span>
                  <?php if ($w['status'] !== 'draft'): ?><span><?= e(t(':n of :m submitted', ['n' => (int) $w['submitted'], 'm' => count($activeStudents)])) ?></span><?php endif; ?></div>
              </div>
              <div class="entry__aside"><?php if ((int) $w['ungraded'] > 0): ?><a class="btn btn--secondary btn--sm" href="<?= e(url('/teacher/assignments/' . (int) $w['id'] . '/submissions')) ?>"><?= e(tp('Grade :count', 'Grade :count', (int) $w['ungraded'])) ?></a><?php endif; ?></div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
    <aside class="stack">
      <section class="panel" aria-labelledby="code-title">
        <h2 class="panel__title" id="code-title"><?= e(t('Class code')) ?></h2>
        <?php if ($codeLive): ?>
          <div class="code-box"><span class="code-box__value" id="class-code"><?= e($class['join_code']) ?></span>
            <button type="button" class="btn btn--secondary btn--sm" data-copy="#class-code" data-copied="<?= e(t('Code copied')) ?>"><?= icon('copy', 'icon--sm') ?><?= e(t('Copy')) ?></button></div>
          <p class="meta" style="margin:8px 0 0"><?= e(t('Students enter it on their Classes page. Expires :date.', ['date' => fmt_date($class['join_code_expires_at'], 'datetime_short')])) ?></p>
        <?php else: ?>
          <p class="meta"><?= e(empty($codePolicy['enabled']) ? t('Your school has turned off class codes. Add students from the roster.') : t('No active code. Create one when students need to join.')) ?></p>
        <?php endif; ?>
        <a class="btn btn--quiet btn--sm" href="<?= e(url($base, ['tab' => 'roster'])) ?>"><?= e(t('Manage roster and code')) ?></a>
      </section>
      <section class="section" aria-labelledby="teachers-title">
        <div class="section__head"><h2 class="section__title" id="teachers-title"><?= e(t('Teachers')) ?></h2></div>
        <p><?= e(implode(', ', array_map(static fn ($t) => user_name($t), $teachers))) ?></p>
      </section>
    </aside>
  </div>

<?php elseif ($tab === 'roster'): ?>
  <div class="layout-split">
    <section aria-labelledby="roster-title">
      <h2 class="sr-only" id="roster-title"><?= e(t('Roster')) ?></h2>
      <form class="toolbar" method="get" action="<?= e(url($base)) ?>" role="search">
        <input type="hidden" name="tab" value="roster">
        <div class="field field--grow"><label class="field__label" for="roster-q"><?= e(t('Find a student')) ?></label><input class="input" id="roster-q" type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('Name or username')) ?>"></div>
        <button class="btn btn--secondary" type="submit"><?= e(t('Search')) ?></button>
        <?php if ($q !== ''): ?><a class="btn btn--quiet" href="<?= e(url($base, ['tab' => 'roster'])) ?>"><?= e(t('Clear')) ?></a><?php endif; ?>
      </form>
      <?php if (!$roster): ?>
        <?= partial('empty', ['compact' => true, 'title' => $q !== '' ? t('No students match “:q”', ['q' => $q]) : t('No students yet'), 'body' => t('Share the class code or add students by username.')]) ?>
      <?php else: ?>
        <div class="table-frame">
          <table class="table table--stack">
            <caption class="sr-only"><?= e(t('Students in :class', ['class' => $class['name']])) ?></caption>
            <thead><tr><th scope="col"><?= e(t('Student')) ?></th><th scope="col"><?= e(t('Joined')) ?></th><th scope="col" class="num"><?= e(t('Work handed in')) ?></th><th scope="col" class="actions"><span class="sr-only"><?= e(t('Actions')) ?></span></th></tr></thead>
            <tbody>
              <?php foreach ($roster as $r): ?>
                <tr>
                  <td class="cell-primary">
                    <span class="cluster" style="flex-wrap:nowrap"><?= partial('avatar', ['person' => $r, 'size' => 'sm']) ?>
                      <span><strong><?= e(user_name($r)) ?></strong><span class="table-sub code-text"><?= e((string) $r['username']) ?></span></span></span>
                    <?php if ($r['status'] !== 'active'): ?><?= badge(t('Removed'), 'neutral') ?><?php elseif ($r['account_status'] === 'invited'): ?><?= badge(t('Not set up yet'), 'amber') ?><?php endif; ?>
                  </td>
                  <td data-label="<?= e(t('Joined')) ?>"><?= e(fmt_date($r['joined_at'])) ?> <span class="table-sub"><?= e(['code' => t('with class code'), 'teacher' => t('added by a teacher'), 'admin' => t('added by the school'), 'import' => t('school import')][$r['joined_via']] ?? '') ?></span></td>
                  <td class="num" data-label="<?= e(t('Work handed in')) ?>"><?= (int) $r['submitted'] ?> / <?= $dueCount ?></td>
                  <td class="actions">
                    <?php if ($r['status'] === 'active' && !$archived && $r['account_status'] !== 'deactivated'): $setup = $r['account_status'] === 'invited'; ?>
                      <form method="post" action="<?= e(url('/teacher/students/' . (int) $r['id'] . '/slip')) ?>" class="inline-form" data-confirm="<?= e($setup ? t('Prints a sign-in slip with a new one-time code so :name can set up their account. An earlier slip or link for them stops working.', ['name' => user_name($r)]) : t('Prints a slip with a one-time code so :name can choose a new password. Their current password keeps working until the slip is used. Slips are recorded in the audit log.', ['name' => user_name($r)])) ?>" data-confirm-title="<?= e($setup ? t('Print a sign-in slip?') : t('Print a password slip?')) ?>" data-confirm-action="<?= e(t('Print slip')) ?>">
                        <?= csrf_field() ?><input type="hidden" name="back" value="<?= e($base . '?tab=roster') ?>">
                        <button class="btn btn--quiet btn--sm" type="submit"><?= icon('printer', 'icon--sm') ?><?= e($setup ? t('Sign-in slip') : t('Password slip')) ?></button>
                      </form>
                    <?php endif; ?>
                    <?php if ($r['status'] === 'active' && !$archived): ?>
                      <form method="post" action="<?= e(url($base . '/roster/' . (int) $r['id'] . '/withdraw')) ?>" data-confirm="<?= e(t(':name will lose access to this class. Their submitted work and grades are kept.', ['name' => user_name($r)])) ?>" data-confirm-title="<?= e(t('Remove from class?')) ?>" data-confirm-action="<?= e(t('Remove')) ?>" data-confirm-tone="danger">
                        <?= csrf_field() ?><button class="btn btn--danger-quiet btn--sm" type="submit"><?= e(t('Remove')) ?></button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
    <aside class="stack">
      <?php if (!$archived): ?>
        <?php $notSetUp = count(array_filter($activeStudents, static fn ($r) => $r['account_status'] === 'invited')); ?>
        <section class="panel" aria-labelledby="slips-title">
          <h2 class="panel__title" id="slips-title"><?= e(t('Sign-in slips')) ?></h2>
          <?php if ($notSetUp): ?>
            <p class="meta"><?= e(tp(':count student has not set up their account yet. Print slips with their username and a one-time code, then hand them out in class.', ':count students have not set up their account yet. Print slips with their username and a one-time code, then hand them out in class.', $notSetUp)) ?></p>
            <form method="post" action="<?= e(url($base . '/slips')) ?>" data-confirm="<?= e(t('Each slip gets a new one-time code; earlier slips or links for these students stop working.')) ?>" data-confirm-title="<?= e(t('Print sign-in slips?')) ?>" data-confirm-action="<?= e(t('Print slips')) ?>">
              <?= csrf_field() ?><button class="btn btn--secondary" type="submit"><?= icon('printer', 'icon--sm') ?><?= e(tp('Print :count slip', 'Print :count slips', $notSetUp)) ?></button>
            </form>
          <?php else: ?>
            <p class="meta" style="margin:0"><?= e(t('Everyone has set up their account. If a student forgets their password, print them a password slip from the list.')) ?></p>
          <?php endif; ?>
        </section>
        <section class="panel" aria-labelledby="add-title">
          <h2 class="panel__title" id="add-title"><?= e(t('Add a student')) ?></h2>
          <form method="post" action="<?= e(url($base . '/roster')) ?>">
            <?= csrf_field() ?>
            <?= field('text', 'student', t('Student username or email'), ['required' => true, 'hint' => t('The student must already have a Sibol account at your school.'), 'attrs' => ['autocapitalize' => 'none', 'spellcheck' => 'false', 'maxlength' => 190]]) ?>
            <button class="btn btn--primary" type="submit"><?= e(t('Add to class')) ?></button>
          </form>
        </section>
        <section class="panel" aria-labelledby="code2-title">
          <h2 class="panel__title" id="code2-title"><?= e(t('Class code')) ?></h2>
          <?php if ($codeLive): ?>
            <div class="code-box"><span class="code-box__value" id="class-code-2"><?= e($class['join_code']) ?></span>
              <button type="button" class="btn btn--secondary btn--sm" data-copy="#class-code-2" data-copied="<?= e(t('Code copied')) ?>"><?= icon('copy', 'icon--sm') ?><?= e(t('Copy')) ?></button></div>
            <p class="meta" style="margin:8px 0 12px"><?= e(t('Expires :date. Anyone at your school with the code can join until then.', ['date' => fmt_date($class['join_code_expires_at'], 'datetime_short')])) ?></p>
          <?php endif; ?>
          <?php if (!empty($codePolicy['enabled'])): ?>
            <div class="cluster">
              <form method="post" action="<?= e(url($base . '/code')) ?>" <?= $codeLive ? 'data-confirm="' . e(t('The current code will stop working immediately.')) . '" data-confirm-title="' . e(t('Create a new code?')) . '" data-confirm-action="' . e(t('Create new code')) . '"' : '' ?>>
                <?= csrf_field() ?><input type="hidden" name="action" value="regenerate">
                <button class="btn btn--secondary btn--sm" type="submit"><?= icon('refresh-cw', 'icon--sm') ?><?= e($codeLive ? t('New code') : t('Create code')) ?></button>
              </form>
              <?php if ($codeLive): ?>
                <form method="post" action="<?= e(url($base . '/code')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="disable">
                  <button class="btn btn--danger-quiet btn--sm" type="submit"><?= e(t('Turn off code')) ?></button></form>
              <?php endif; ?>
            </div>
            <p class="meta" style="margin-top:8px"><?= e(tp('New codes last :count day (school policy).', 'New codes last :count days (school policy).', (int) ($codePolicy['ttl_days'] ?? 14))) ?></p>
          <?php else: ?>
            <p class="meta"><?= e(t('Your school has turned off class codes.')) ?></p>
          <?php endif; ?>
        </section>
      <?php endif; ?>
    </aside>
  </div>

<?php elseif ($tab === 'modules'): ?>
  <div class="layout-split">
    <section aria-labelledby="mod-title">
      <h2 class="sr-only" id="mod-title"><?= e(t('Modules')) ?></h2>
      <?php if (!$modules): ?>
        <?= partial('empty', ['compact' => true, 'title' => t('No modules yet'), 'body' => t('Modules group materials and assignments, for example “Module 1: Quadratic equations”.')]) ?>
      <?php else: ?>
        <ol class="rows">
          <?php foreach ($modules as $i => $m): ?>
            <li class="row" style="flex-wrap: wrap">
              <div class="row__main">
                <span class="row__title"><?= e($m['title']) ?></span>
                <div class="row__meta"><span><?= e(tp(':count material', ':count materials', (int) $m['materials'])) ?></span><span><?= e(tp(':count assignment', ':count assignments', (int) $m['assignments'])) ?></span><?php if ($m['description']): ?><span><?= e($m['description']) ?></span><?php endif; ?></div>
              </div>
              <div class="row__aside">
                <form method="post" action="<?= e(url($base . '/modules/' . (int) $m['id'])) ?>"><?= csrf_field() ?><input type="hidden" name="move" value="up"><button class="btn btn--quiet btn--sm" type="submit"<?= $i === 0 ? ' disabled' : '' ?>><?= icon('arrow-up', 'icon--sm', t('Move up')) ?></button></form>
                <form method="post" action="<?= e(url($base . '/modules/' . (int) $m['id'])) ?>"><?= csrf_field() ?><input type="hidden" name="move" value="down"><button class="btn btn--quiet btn--sm" type="submit"<?= $i === count($modules) - 1 ? ' disabled' : '' ?>><?= icon('arrow-down', 'icon--sm', t('Move down')) ?></button></form>
                <button type="button" class="btn btn--quiet btn--sm" data-toggle="#edit-mod-<?= (int) $m['id'] ?>"><?= e(t('Edit')) ?></button>
                <form method="post" action="<?= e(url($base . '/modules/' . (int) $m['id'] . '/delete')) ?>" data-confirm="<?= e(t('The module is removed; its materials and assignments stay in the class.')) ?>" data-confirm-title="<?= e(t('Remove module?')) ?>" data-confirm-action="<?= e(t('Remove')) ?>" data-confirm-tone="danger">
                  <?= csrf_field() ?><button class="btn btn--danger-quiet btn--sm" type="submit"><?= e(t('Remove')) ?></button></form>
              </div>
              <form id="edit-mod-<?= (int) $m['id'] ?>" method="post" action="<?= e(url($base . '/modules/' . (int) $m['id'])) ?>" hidden style="width:100%; padding-top: 12px">
                <?= csrf_field() ?>
                <div class="form-grid">
                  <?= field('text', 'title', t('Module title'), ['value' => $m['title'], 'required' => true, 'id' => 'mt-' . (int) $m['id'], 'attrs' => ['maxlength' => 160]]) ?>
                  <?= field('text', 'description', t('Description'), ['value' => (string) $m['description'], 'id' => 'md-' . (int) $m['id'], 'optional_label' => true, 'attrs' => ['maxlength' => 500]]) ?>
                </div>
                <button class="btn btn--primary btn--sm" type="submit"><?= e(t('Save module')) ?></button>
              </form>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>
    </section>
    <aside>
      <section class="panel" aria-labelledby="newmod-title">
        <h2 class="panel__title" id="newmod-title"><?= e(t('Add a module')) ?></h2>
        <form method="post" action="<?= e(url($base . '/modules')) ?>">
          <?= csrf_field() ?>
          <?= field('text', 'title', t('Module title'), ['required' => true, 'attrs' => ['maxlength' => 160], 'id' => 'new-mod-title']) ?>
          <?= field('text', 'description', t('Description'), ['optional_label' => true, 'attrs' => ['maxlength' => 500], 'id' => 'new-mod-desc']) ?>
          <button class="btn btn--primary" type="submit"><?= e(t('Add module')) ?></button>
        </form>
      </section>
    </aside>
  </div>

<?php elseif ($tab === 'announcements'): ?>
  <div class="layout-split">
    <section aria-labelledby="ann-title">
      <h2 class="sr-only" id="ann-title"><?= e(t('Announcements')) ?></h2>
      <?php if (!$announcements): ?>
        <?= partial('empty', ['compact' => true, 'title' => t('No announcements yet'), 'body' => t('Post reminders and news for the whole class. Students get a notification in Sibol.')]) ?>
      <?php else: ?>
        <ul class="entries">
          <?php foreach ($announcements as $a): ?>
            <li class="entry">
              <?= partial('entry_date', ['at' => $a['created_at']]) ?>
              <article class="entry__main">
                <h3 style="font-size:1.0625rem; margin:0 0 2px"><?= e($a['title']) ?></h3>
                <p class="meta" style="margin:0 0 8px"><?= e(user_name($a)) ?>, <?= e(fmt_date($a['created_at'], 'datetime_short')) ?></p>
                <div class="prose"><?= markdown($a['body']) ?></div>
              </article>
              <div class="entry__aside">
                <form method="post" action="<?= e(url($base . '/announcements/' . (int) $a['id'] . '/delete')) ?>" data-confirm="<?= e(t('Students will no longer see this announcement.')) ?>" data-confirm-title="<?= e(t('Delete announcement?')) ?>" data-confirm-action="<?= e(t('Delete')) ?>" data-confirm-tone="danger">
                  <?= csrf_field() ?><button class="btn btn--danger-quiet btn--sm" type="submit"><?= e(t('Delete')) ?></button></form>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
    <aside>
      <?php if (!$archived): ?>
        <section class="panel" aria-labelledby="post-title">
          <h2 class="panel__title" id="post-title"><?= e(t('Post an announcement')) ?></h2>
          <form method="post" action="<?= e(url($base . '/announcements')) ?>">
            <?= csrf_field() ?>
            <?= field('text', 'title', t('Title'), ['required' => true, 'attrs' => ['maxlength' => 200], 'id' => 'ann-title-input']) ?>
            <?= field('textarea', 'body', t('Message'), ['required' => true, 'rows' => 5, 'attrs' => ['maxlength' => 5000, 'data-charcount' => true], 'hint' => t('You can use simple formatting such as **bold** and lists.')]) ?>
            <button class="btn btn--primary" type="submit" data-busy="<?= e(t('Posting…')) ?>"><?= e(t('Post to class')) ?></button>
          </form>
        </section>
      <?php endif; ?>
    </aside>
  </div>

<?php else: ?>
  <section aria-labelledby="shared-title" style="max-width: 820px">
    <h2 class="sr-only" id="shared-title"><?= e(t('Shared with you')) ?></h2>
    <p class="muted"><?= e(t('Students can choose to share a single companion answer, with a note, so you can see what they are working through. You never see the rest of their conversations.')) ?></p>
    <?php if (!$shares): ?>
      <?= partial('empty', ['compact' => true, 'title' => t('Nothing shared yet'), 'body' => t('Excerpts appear here only when a student decides to share one.')]) ?>
    <?php else: ?>
      <ul class="entries">
        <?php foreach ($shares as $s): ?>
          <li class="entry">
            <?= partial('entry_date', ['at' => $s['created_at']]) ?>
            <article class="entry__main">
              <p style="margin:0 0 6px"><strong><?= e(user_name($s)) ?></strong><?php if ($s['seen_at'] === null): ?> <?= badge(t('New'), 'leaf') ?><?php endif; ?></p>
              <?php if ($s['student_note']): ?><p class="callout" style="margin-bottom:8px"><?= icon('message-circle', 'icon--sm') ?><span><?= e($s['student_note']) ?></span></p><?php endif; ?>
              <details class="how"><summary><?= e(t('Shared companion answer')) ?></summary><div class="prose"><?= markdown($s['content']) ?></div></details>
            </article>
            <div class="entry__aside">
              <?php if ($s['seen_at'] === null): ?>
                <form method="post" action="<?= e(url($base . '/shared/' . (int) $s['id'] . '/seen')) ?>"><?= csrf_field() ?><button class="btn btn--secondary btn--sm" type="submit"><?= e(t('Mark as read')) ?></button></form>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
<?php endif; ?>
