<?php
use App\Core\Settings;
$text = (string) Settings::get('privacy.cookie_text', '');
?>
<div class="cookie" data-cookie role="dialog" aria-live="polite" aria-label="<?= e(t('Gestion des cookies')) ?>">
  <h2><?= e(t('Cookies')) ?></h2>
  <div class="prose" style="font-size:16px"><?= safe_html($text) ?: '<p>' . e(t('Nous utilisons des cookies pour lire les vidéos et traiter les dons en ligne.')) . '</p>' ?></div>
  <div class="cookie__opts">
    <label class="checkbox"><input type="checkbox" checked disabled> <span><b><?= e(t('Nécessaires')) ?></b> — <?= e(t('fonctionnement du site, préférences (toujours actifs).')) ?></span></label>
    <label class="checkbox"><input type="checkbox" data-consent="video"> <span><b><?= e(t('Vidéos')) ?></b> — <?= e(t('YouTube et Dailymotion déposent des cookies à la lecture.')) ?></span></label>
    <label class="checkbox"><input type="checkbox" data-consent="social"> <span><b><?= e(t('Réseaux sociaux')) ?></b> — <?= e(t('publications X / Instagram intégrées.')) ?></span></label>
    <?php if (Settings::get('privacy.analytics_id')): ?>
    <label class="checkbox"><input type="checkbox" data-consent="stats"> <span><b><?= e(t('Mesure d’audience')) ?></b> — <?= e(t('statistiques de visite anonymisées.')) ?></span></label>
    <?php endif; ?>
  </div>
  <div class="row gap-8">
    <button type="button" class="btn btn--navy btn--sm" data-cookie-accept><?= e(t('Tout accepter')) ?></button>
    <button type="button" class="btn btn--ghost btn--sm" data-cookie-refuse><?= e(t('Tout refuser')) ?></button>
    <button type="button" class="btn btn--ghost btn--sm" data-cookie-custom><?= e(t('Personnaliser')) ?></button>
    <button type="button" class="btn btn--yellow btn--sm" data-cookie-save hidden><?= e(t('Enregistrer')) ?></button>
  </div>
</div>
