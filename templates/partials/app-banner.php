<?php
/**
 * Bandeau « Installer l'appli » (Réglages › Application du musée) : caché ; site.js le montre
 * sur téléphone à partir de la 2e page vue, si le navigateur sait installer (Android) ou sur
 * iPhone/iPad (les deux gestes à faire). Jamais dans l'appli installée.
 */
$share = '<svg class="appbar__share" viewBox="0 0 24 24" width="18" height="18" aria-label="' . e(t('Partager')) . '" role="img"><path d="M12 3v12M7.5 7.5 12 3l4.5 4.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 10H6a1 1 0 0 0-1 1v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-9a1 1 0 0 0-1-1h-2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
?>
<div class="appbar" data-appbar hidden role="dialog" aria-label="<?= e(t('L’appli du musée')) ?>">
  <img src="/assets/img/app/192.png" alt="" width="44" height="44">
  <div class="appbar__txt">
    <b><?= e(t('L’appli du musée')) ?></b>
    <span data-appbar-for="prompt"><?= e(t('Sur votre écran d’accueil : plein écran, plus rapide, lisible sans réseau.')) ?></span>
    <span data-appbar-for="ios" hidden><?= str_replace('{icon}', $share, e(t('Touchez {icon} puis « Sur l’écran d’accueil ».'))) ?></span>
  </div>
  <div class="appbar__act">
    <button type="button" class="btn btn--yellow btn--sm" data-appbar-install data-appbar-for="prompt"><?= e(t('Installer')) ?></button>
    <button type="button" class="appbar__later" data-appbar-later><?= e(t('Plus tard')) ?></button>
  </div>
</div>
