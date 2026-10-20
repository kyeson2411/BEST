<?php
/** Dated-notebook margin. @var string|null $at UTC datetime */
$p = \Sibol\Core\Dates::parts($at ?? null);
?>
<time class="entry__date" datetime="<?= e($p['iso']) ?>">
  <span class="entry__weekday"><?= e($p['weekday']) ?></span>
  <span class="entry__day"><?= e($p['day']) ?></span>
  <span class="entry__month"><?= e($p['month']) ?></span>
</time>
