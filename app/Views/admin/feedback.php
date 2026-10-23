<?php
/** @var string $show open|closed @var string $category @var list<array> $items @var list<array> $counts */
use Sibol\Core\View;
use Sibol\Services\SiteFeedback;

$byStatus = array_column($counts, 'n', 'status');
$open = (int) ($byStatus['new'] ?? 0) + (int) ($byStatus['looking'] ?? 0);
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e(t('Feedback')) ?></h1>
    <p class="page-lead"><?= e(t('What students and teachers tell the school about B.E.S.T. Learning and Interactive Study System: problems, confusing parts and ideas. Give each a status; a short reply reaches the sender as a notification.')) ?></p>
  </div>
</div>
<?= View::file('admin/_pilot_tabs', ['active' => 'feedback']) ?>

<form class="toolbar" method="get" action="<?= e(url('/admin/feedback')) ?>">
  <div class="field"><label class="field__label" for="f-show"><?= e(t('Show')) ?></label>
    <select class="input select" id="f-show" name="show" data-autosubmit>
      <option value="open"<?= selected('open', $show) ?>><?= e(t('Open (:n)', ['n' => $open])) ?></option>
      <option value="closed"<?= selected('closed', $show) ?>><?= e(t('Fixed or won\'t fix')) ?></option>
    </select></div>
  <div class="field"><label class="field__label" for="f-category"><?= e(t('Kind')) ?></label>
    <select class="input select" id="f-category" name="category" data-autosubmit><option value=""><?= e(t('All kinds')) ?></option>
      <?php foreach (SiteFeedback::CATEGORIES as $c): ?><option value="<?= e($c) ?>"<?= selected($c, $category) ?>><?= e(SiteFeedback::categoryLabel($c)) ?></option><?php endforeach; ?></select></div>
  <button class="btn btn--secondary" type="submit"><?= e(t('Apply')) ?></button>
</form>

<?php if (!$items): ?>
  <?= partial('empty', ['compact' => true, 'title' => $show === 'open' ? t('No open feedback') : t('No closed feedback yet'), 'body' => t('Students and teachers send feedback from "Send feedback" in their menu.')]) ?>
<?php else: ?>
  <ul class="entries">
    <?php foreach ($items as $f): [$label, $tone] = SiteFeedback::statusBadge((string) $f['status']); ?>
      <li class="entry">
        <?= partial('entry_date', ['at' => $f['created_at']]) ?>
        <div class="entry__main">
          <a class="row__title" href="<?= e(url('/admin/feedback/' . (int) $f['id'])) ?>"><?= e(SiteFeedback::categoryLabel((string) $f['category'])) ?></a>
          <p class="feedback-text"><?= e(str_limit((string) $f['body'], 220)) ?></p>
          <div class="row__meta">
            <span><?= e($f['given_name'] !== null && $f['anonymized_at'] === null ? user_name($f) : t('Former account')) ?>, <?= e(lcfirst_u(role_label((string) $f['user_role']))) ?></span>
            <?php if ($f['page_path']): ?><span class="code-text"><?= e((string) $f['page_path']) ?></span><?php endif; ?>
            <?php if ($f['reply'] !== null): ?><span><?= icon('message-square-reply', 'icon--xs') ?> <?= e(t('Replied')) ?></span><?php endif; ?>
          </div>
        </div>
        <div class="entry__aside"><?= badge($label, $tone) ?></div>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
