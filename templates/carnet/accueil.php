<?php
/** Carnet du supporter : présentation (pas encore de carnet sur cet appareil). Variables : $seasons */
use App\Core\View;
?>
<?= View::partial('carnet/_head', ['title' => t('Mon carnet du supporter'), 'intro' => t('Cochez les matchs du FCSM que vous avez vus au stade, à Bonal ou en déplacement : le musée calcule votre bilan, vos badges, votre « porte-bonheur » et une carte à partager.'), 'crumb' => t('Mon carnet')]) ?>
<div class="wrap cnwrap">
  <div class="cnintro">
    <section class="cncard cncard--navy">
      <h2 class="cncard__t"><?= e(t('Comment ça marche')) ?></h2>
      <ol class="cnsteps">
        <li><b><?= e(t('Sur la fiche d’un match')) ?></b> <?= e(t('touchez « J’y étais ! » : le match rejoint votre carnet.')) ?></li>
        <li><b><?= e(t('Ou saison par saison')) ?></b> <?= e(t('cochez d’un coup tous les matchs que vous avez vus.')) ?></li>
        <li><b><?= e(t('Votre bilan se calcule tout seul')) ?></b> <?= e(t('victoires, buts, joueurs vus, badges et porte-bonheur.')) ?></li>
      </ol>
      <a class="btn btn--yellow" href="<?= e(url('/carnet/saisons/')) ?>"><?= e(t('Commencer par une saison')) ?></a>
    </section>
    <section class="cncard">
      <h2 class="cncard__t"><?= e(t('Créer mon carnet')) ?></h2>
      <?= View::partial('carnet/_email', ['mode' => 'creer', 'mid' => 0]) ?>
      <h2 class="cncard__t" style="margin-top:28px"><?= e(t('J’ai déjà un carnet')) ?></h2>
      <p class="cnnote"><?= e(t('Sur un autre téléphone ou ordinateur, ou lien perdu : recevez un nouveau lien.')) ?></p>
      <?= View::partial('carnet/_email', ['mode' => 'renvoyer', 'mid' => 0]) ?>
    </section>
  </div>
</div>
