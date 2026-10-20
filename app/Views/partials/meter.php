<?php
/** Accessible progress bar. @var float|null $value 0..100 @var string|null $label @var string|null $tone leaf|forest */
$v = $value === null ? 0 : max(0, min(100, (float) $value));
$text = $label ?? ($value === null ? '—' : fmt_number($value) . '%');
?>
<span class="meter">
  <span class="meter__track" role="img" aria-label="<?= e($text) ?>">
    <svg viewBox="0 0 100 8" preserveAspectRatio="none" aria-hidden="true" focusable="false"><rect class="meter__fill<?= ($tone ?? '') === 'leaf' ? ' meter__fill--leaf' : '' ?>" x="0" y="0" width="<?= e((string) $v) ?>" height="8" rx="4"/></svg>
  </span>
  <span class="meter__label"><?= e($text) ?></span>
</span>
