<?php
/**
 * Bande d'appel à l'action, commune au bas de toutes les pages.
 *
 * Sur un site commercial elle mène au devis ; ici elle mène au guichet. Les
 * deux boutons répondent aux deux façons de joindre une mairie : écrire quand
 * on a une pièce à demander, appeler quand on a une question.
 *
 * @var App\Core\Content $content
 * @var App\Core\View $view
 */
$site = $content->load('site');
$resa = $site['reservation'];
$tel  = (string) ($site['contact']['telephone'] ?? '');
?>
<section class="bande-cta">
  <div class="conteneur reveler">
    <p class="surtitre surtitre--clair surtitre--centre"><?= e(t('Une question, une démarche')) ?></p>
    <h2 class="bande-cta__titre"><?= e(t('Le secrétariat de mairie vous répond')) ?></h2>
    <p class="bande-cta__texte">
      <?= e(t('Écrivez au secrétariat pour une pièce d’état civil, un dossier d’urbanisme, la réservation de la salle des fêtes ou le signalement d’un problème sur la voirie. Vous pouvez aussi passer aux heures d’ouverture, sans rendez-vous.')) ?>
    </p>
    <div class="bande-cta__actions">
      <?php if (($resa['principal']['url'] ?? '') !== ''): ?>
        <a class="btn btn--bleu" href="<?= lien($resa['principal']['url']) ?>">
          <?= e($resa['principal']['libelle']) ?>
        </a>
      <?php endif; ?>
      <?php if ($tel !== ''): ?>
        <a class="btn btn--contour-clair" href="<?= e(tel_lien($tel)) ?>">
          <?= e(t('Appeler le')) ?> <?= e($tel) ?>
        </a>
      <?php endif; ?>
    </div>
  </div>
</section>
