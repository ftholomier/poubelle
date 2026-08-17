<?php
/**
 * Bande d'appel à l'action commune (bas de page).
 * @var App\Core\Content $content
 */
$site = $content->load('site');
$resa = $site['reservation'];
?>
<section class="bande-cta" style="background-image:url('<?= asset('assets/img/site/image-12-1.jpg') ?>')">
  <div class="conteneur reveler">
    <p class="surtitre surtitre--centre"><?= e(t('Réservation')) ?></p>
    <h2 class="bande-cta__titre"><?= e(t('Votre séjour nature commence ici')) ?></h2>
    <p><?= e(t('Entre étang, forêt et grand air, découvrez un lieu pensé pour se ressourcer. Des hébergements tout équipés et accessibles vous attendent, avec spa privatif et un environnement préservé, pour un moment de calme et de déconnexion.')) ?></p>
    <div class="heros__actions">
      <a class="btn btn--or" href="<?= e($resa['hebergement']['url']) ?>" target="_blank" rel="noopener"><?= e($resa['hebergement']['libelle']) ?></a>
      <a class="btn btn--contour" href="<?= e($resa['etang']['url']) ?>" target="_blank" rel="noopener"><?= e($resa['etang']['libelle']) ?></a>
    </div>
  </div>
</section>
