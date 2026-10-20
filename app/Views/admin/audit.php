<?php /** @var list<array> $rows @var array $targets @var \Sibol\Core\Paginator $pager @var string $group @var array|null $person @var string $from @var string $to */
use Sibol\Services\AuditLabels;

$query = ['group' => $group, 'user' => $person ? (int) $person['id'] : null, 'from' => $from, 'to' => $to];
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Audit log')) ?></h1>
    <p class="page-lead"><?= e(t('Important actions in your school: who did what, and when. Entries cannot be edited. Conversation text, passwords and keys are never recorded.')) ?></p>
  </div>
</div>

<form class="toolbar" method="get" action="<?= e(url('/admin/audit')) ?>">
  <?php if ($person): ?><input type="hidden" name="user" value="<?= (int) $person['id'] ?>"><?php endif; ?>
  <div class="field"><label class="field__label" for="a-group"><?= e(t('Type')) ?></label>
    <select class="input select" id="a-group" name="group" data-autosubmit><option value=""><?= e(t('All activity')) ?></option>
      <?php foreach (AuditLabels::groups() as $key => $label): ?><option value="<?= e($key) ?>"<?= selected($key, $group) ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label class="field__label" for="a-from"><?= e(t('From')) ?></label><input class="input" id="a-from" type="date" name="from" value="<?= e($from) ?>"></div>
  <div class="field"><label class="field__label" for="a-to"><?= e(t('To')) ?></label><input class="input" id="a-to" type="date" name="to" value="<?= e($to) ?>"></div>
  <button class="btn btn--secondary" type="submit"><?= e(t('Apply')) ?></button>
</form>

<?php if ($person): ?>
  <p class="cluster" style="margin: -8px 0 16px"><?= badge(t('Only :name', ['name' => user_name($person)]), 'outline') ?><a href="<?= e(url('/admin/audit', array_merge($query, ['user' => null]))) ?>"><?= e(t('Show everyone')) ?></a></p>
<?php endif; ?>

<?php if (!$rows): ?>
  <?= partial('empty', ['compact' => true, 'title' => t('No activity matches these filters'), 'body' => t('Try a wider date range or another type.')]) ?>
<?php else: ?>
  <?= partial('audit_entries', ['rows' => $rows, 'targets' => $targets]) ?>
  <?= partial('pagination', ['pager' => $pager, 'path' => '/admin/audit', 'query' => $query]) ?>
<?php endif; ?>
