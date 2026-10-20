<?php
/**
 * The school seal (public/assets/school). Decorative by default: it always sits next to the school's name.
 * @var int|null $size CSS pixels @var string|null $class @var string|null $alt
 * @var string|null $logo URL of a logo uploaded in School settings; used instead of the seal when present
 */
$size = (int) ($size ?? 32);
$alt = (string) ($alt ?? '');
$class = trim('seal ' . ($class ?? ''));
?>
<picture class="<?= e($class) ?>">
<?php if (!empty($logo)): ?>
  <img src="<?= e($logo) ?>" alt="<?= e($alt) ?>" width="<?= $size ?>" height="<?= $size ?>">
<?php elseif ($size > 96): ?>
  <source type="image/webp" srcset="<?= e(asset('school/seal-192.webp')) ?> 192w, <?= e(asset('school/seal-384.webp')) ?> 384w" sizes="<?= $size ?>px">
  <img src="<?= e(asset('school/seal-192.png')) ?>" alt="<?= e($alt) ?>" width="<?= $size ?>" height="<?= $size ?>">
<?php else: ?>
  <img src="<?= e(asset('school/seal-96.png')) ?>" srcset="<?= e(asset('school/seal-48.png')) ?> 48w, <?= e(asset('school/seal-96.png')) ?> 96w, <?= e(asset('school/seal-192.png')) ?> 192w" sizes="<?= $size ?>px" alt="<?= e($alt) ?>" width="<?= $size ?>" height="<?= $size ?>">
<?php endif; ?>
</picture>
