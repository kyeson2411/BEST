<?php /** @var list<array> $rows @var \Sibol\Core\Paginator $pager @var string $q @var string $role @var string $status @var string $grade @var array $counts @var list<string> $grades */
$filters = ['q' => $q, 'role' => $role, 'status' => $status, 'grade' => $grade];
$statusBadge = static function (array $u): string {
    if ($u['anonymized_at']) {
        return badge(t('Erased'), 'neutral');
    }
    switch ($u['status']) {
        case 'active': return badge(t('Active'), 'leaf', 'circle-check');
        case 'invited': return badge(t('Invited'), 'amber', 'mail');
        default: return badge(t('Deactivated'), 'neutral', 'user-x');
    }
};
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('People')) ?></h1>
    <p class="page-lead"><?= e(t(':students, :teachers and :admins, including invited and deactivated accounts.', [
        'students' => tp(':count student', ':count students', (int) ($counts['student'] ?? 0)),
        'teachers' => tp(':count teacher', ':count teachers', (int) ($counts['teacher'] ?? 0)),
        'admins' => tp(':count administrator', ':count administrators', (int) ($counts['admin'] ?? 0)),
    ])) ?></p>
  </div>
  <div class="page-actions">
    <a class="btn btn--secondary" href="<?= e(url('/admin/users/import')) ?>"><?= icon('file-spreadsheet', 'icon--sm') ?><?= e(t('Import from CSV')) ?></a>
    <a class="btn btn--primary" href="<?= e(url('/admin/users/new')) ?>"><?= icon('user-plus', 'icon--sm') ?><?= e(t('Invite someone')) ?></a>
  </div>
</div>

<form class="toolbar" method="get" action="<?= e(url('/admin/users')) ?>" role="search">
  <div class="field field--grow"><label class="field__label" for="u-q"><?= e(t('Search')) ?></label><input class="input" id="u-q" type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('Name or email')) ?>"></div>
  <div class="field"><label class="field__label" for="u-role"><?= e(t('Role')) ?></label>
    <select class="input select" id="u-role" name="role" data-autosubmit>
      <?php foreach (['' => t('Everyone'), 'student' => t('Students'), 'teacher' => t('Teachers'), 'admin' => t('Administrators')] as $v => $l): ?><option value="<?= e($v) ?>"<?= selected($v, $role) ?>><?= e($l) ?></option><?php endforeach; ?>
    </select></div>
  <div class="field"><label class="field__label" for="u-status"><?= e(t('Status')) ?></label>
    <select class="input select" id="u-status" name="status" data-autosubmit>
      <?php foreach (['' => t('Any status'), 'active' => t('Active'), 'invited' => t('Invited'), 'deactivated' => t('Deactivated')] as $v => $l): ?><option value="<?= e($v) ?>"<?= selected($v, $status) ?>><?= e($l) ?></option><?php endforeach; ?>
    </select></div>
  <div class="field"><label class="field__label" for="u-grade"><?= e(t('Grade level')) ?></label>
    <select class="input select" id="u-grade" name="grade" data-autosubmit>
      <option value=""><?= e(t('Any grade')) ?></option>
      <?php foreach ($grades as $g): ?><option value="<?= e($g) ?>"<?= selected($g, $grade) ?>><?= e($g) ?></option><?php endforeach; ?>
    </select></div>
  <button class="btn btn--secondary" type="submit"><?= e(t('Apply')) ?></button>
</form>

<?php if (!$rows): ?>
  <?= partial('empty', ['title' => array_filter($filters) ? t('No one matches these filters') : t('No people yet'), 'body' => t('Invite people one at a time or import a class list from a spreadsheet.'), 'action' => [t('Invite someone'), '/admin/users/new']]) ?>
<?php else: ?>
  <div class="table-frame">
    <table class="table table--stack table--compact-mobile">
      <caption class="sr-only"><?= e(t('People')) ?></caption>
      <thead><tr>
        <th scope="col"><?= e(t('Name')) ?></th>
        <th scope="col"><?= e(t('Role')) ?></th>
        <th scope="col"><?= e(t('Status')) ?></th>
        <th scope="col" class="num"><?= e(t('Classes')) ?></th>
        <th scope="col"><?= e(t('Last sign-in')) ?></th>
      </tr></thead>
      <tbody>
        <?php foreach ($rows as $u): ?>
          <tr>
            <td class="cell-primary">
              <a class="row__title" href="<?= e(url('/admin/users/' . (int) $u['id'])) ?>"><?= e(user_name($u)) ?></a>
              <span class="table-sub"><?= e(implode(' · ', array_filter([(string) $u['username'], (string) $u['email']]))) ?></span>
              <span class="summary-line only-mobile"><?= $statusBadge($u) ?><span><?= e(role_label($u['role'])) ?><?= $u['grade_level'] ? ', ' . e($u['grade_level']) : '' ?></span><span><?= e(tp(':count class', ':count classes', (int) $u['classes'])) ?></span></span>
            </td>
            <td data-label="<?= e(t('Role')) ?>"><?= e(role_label($u['role'])) ?><?php if ($u['grade_level']): ?><span class="table-sub"><?= e($u['grade_level']) ?></span><?php endif; ?></td>
            <td data-label="<?= e(t('Status')) ?>"><?= $statusBadge($u) ?></td>
            <td data-label="<?= e(t('Classes')) ?>" class="num"><?= (int) $u['classes'] ?></td>
            <td data-label="<?= e(t('Last sign-in')) ?>"><?= $u['last_login_at'] ? time_tag($u['last_login_at'], 'date', true) : '<span class="meta">' . e(t('Never')) . '</span>' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= partial('pagination', ['pager' => $pager, 'path' => '/admin/users', 'query' => $filters]) ?>
<?php endif; ?>
