<?php
/** Consentement : les scripts publicitaires ne partent qu'après un « oui » explicite. */
use App\Services\Ads;
use App\Services\I18n;

if (Ads::client() === '') {
    return;   // aucun compte AdSense configuré : rien à demander
}
?>
<?php // Bandeau, pas fenêtre modale : « dialog » forcerait un lecteur d'écran
      // à traiter le reste de la page comme inaccessible. ?>
<aside class="cmp" data-cmp role="region" aria-live="polite"
       aria-label="<?= e(I18n::t('cmp.title')) ?>" hidden>
  <h2 class="cmp-title"><?= e(I18n::t('cmp.title')) ?></h2>
  <p><?= e(I18n::t('cmp.body')) ?>
    <a href="<?= e(I18n::url('/confidentialite')) ?>"><?= e(I18n::t('footer.privacy')) ?></a>
  </p>
  <div class="row">
    <button type="button" class="btn btn-coral btn-sm" data-cmp-choice="all"><?= e(I18n::t('cmp.accept')) ?></button>
    <button type="button" class="btn btn-ghost btn-sm" data-cmp-choice="none"><?= e(I18n::t('cmp.refuse')) ?></button>
  </div>
</aside>
