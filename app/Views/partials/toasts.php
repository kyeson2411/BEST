<?php /** @var list<array{type:string,message:string}> $flashes */ ?>
<div class="toasts" id="toasts" role="status" aria-live="polite" aria-atomic="false">
  <?php foreach ($flashes as $flash):
      $type = in_array($flash['type'], ['success', 'info', 'error', 'warning'], true) ? $flash['type'] : 'info';
      $iconName = $type === 'error' ? 'circle-alert' : ($type === 'warning' ? 'triangle-alert' : ($type === 'success' ? 'circle-check' : 'info'));
  ?>
    <div class="toast toast--<?= e($type) ?>" data-toast data-persist="<?= $type === 'error' ? '1' : '0' ?>">
      <?= icon($iconName) ?>
      <p class="toast__text"><?= e($flash['message']) ?></p>
      <button type="button" class="toast__close" data-dismiss aria-label="<?= e(t('Dismiss message')) ?>"><?= icon('x', 'icon--sm') ?></button>
    </div>
  <?php endforeach; ?>
</div>
