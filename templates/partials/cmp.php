<?php
/** Consentement : les scripts publicitaires ne partent qu'après un « oui » explicite. */
use App\Services\Ads;
use App\Services\I18n;

if (Ads::client() === '') {
    return;   // aucun compte AdSense configuré : rien à demander
}
?>
<aside class="cmp" data-cmp role="dialog" aria-label="<?= e(I18n::t('cmp.title')) ?>" hidden>
  <h4><?= e(I18n::t('cmp.title')) ?></h4>
  <p><?= e(I18n::t('cmp.body')) ?></p>
  <div class="row">
    <button type="button" class="btn btn-coral btn-sm" data-cmp-choice="all"><?= e(I18n::t('cmp.accept')) ?></button>
    <button type="button" class="btn btn-ghost btn-sm" data-cmp-choice="none"><?= e(I18n::t('cmp.refuse')) ?></button>
  </div>
</aside>
