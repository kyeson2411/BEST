<?php /** @var string $heading @var string $body */ ?>
<h1 class="auth__title"><?= e($heading) ?></h1>
<p class="auth__sub"><?= e($body) ?></p>
<a class="btn btn--secondary" href="<?= e(url('/login')) ?>"><?= e(t('Go to sign in')) ?></a>
