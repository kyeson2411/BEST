<?php
/** @var array|null $person  @var string|null $size  sm|lg */
$size = $size ?? '';
$class = 'avatar' . ($size ? ' avatar--' . $size : '');
?>
<span class="<?= e($class) ?>" aria-hidden="true"><?php if (!empty($person['avatar_path']) && !empty($person['id'])): ?><img src="<?= e(url('/avatars/' . (int) $person['id'])) ?>" alt=""><?php else: ?><?= e(initials($person)) ?><?php endif; ?></span>
