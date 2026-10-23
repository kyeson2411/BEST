<?php
/**
 * Audit rows as dated entries. @var list<array> $rows @var array $targets (from AuditLabels::targets) @var bool|null $showActor
 */
use Sibol\Services\AuditLabels;

$showActor = $showActor ?? true;
?>
<ul class="entries">
  <?php foreach ($rows as $a): $target = ($a['target_type'] && $a['target_id']) ? ($targets[$a['target_type'] . ':' . $a['target_id']] ?? null) : null; ?>
    <li class="entry">
      <?= partial('entry_date', ['at' => $a['created_at']]) ?>
      <div class="entry__main">
        <span class="row__title"><?= e(AuditLabels::describe($a)) ?><?php if ($target): ?>:
          <?php if ($target['url']): ?><a href="<?= e(url($target['url'])) ?>"><?= e($target['label']) ?></a><?php else: ?><?= e($target['label']) ?><?php endif; ?>
        <?php endif; ?></span>
        <div class="row__meta">
          <?php if ($showActor): ?><span><?= $a['actor_id'] ? e(user_name($a)) . ($a['actor_role'] ? ', ' . e(lcfirst_u(role_label((string) $a['actor_role']))) : '') : e(t('B.E.S.T. Learning and Interactive Study System (automatic)')) ?></span><?php endif; ?>
          <span><?= e(fmt_date($a['created_at'], 'time')) ?></span>
        </div>
      </div>
    </li>
  <?php endforeach; ?>
</ul>
