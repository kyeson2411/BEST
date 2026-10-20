<dialog class="dialog" id="confirm-dialog" aria-labelledby="confirm-title" aria-describedby="confirm-text">
  <form method="dialog">
    <div class="dialog__body">
      <h2 class="dialog__title" id="confirm-title"><?= e(t('Are you sure?')) ?></h2>
      <p class="dialog__text" id="confirm-text"></p>
    </div>
    <div class="dialog__actions">
      <button type="submit" value="cancel" class="btn btn--secondary" data-confirm-cancel><?= e(t('Cancel')) ?></button>
      <button type="submit" value="confirm" class="btn btn--primary" data-confirm-ok><?= e(t('Confirm')) ?></button>
    </div>
  </form>
</dialog>
