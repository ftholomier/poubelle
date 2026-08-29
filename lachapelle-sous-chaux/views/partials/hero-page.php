<?php
/**
 * Bandeau de page intérieure.
 *
 * L'image est posée sur un calque séparé plutôt que sur la section : c'est
 * lui seul qui porte le cadrage « cover » et le lent mouvement d'approche.
 * Sur la section, l'image se répétait en mosaïque dès que le bloc dépassait
 * sa hauteur naturelle.
 *
 * @var array $hero  image, surtitre, titre, texte?
 */
?>
<section class="heros heros--page">
  <div class="heros__fond">
    <div class="heros__photo heros__photo--anime"
         style="background-image:url('<?= image($hero['image'] ?? '') ?>')"></div>
  </div>
  <div class="heros__contenu conteneur">
    <?php if (!empty($hero['surtitre'])): ?>
      <p class="surtitre surtitre--clair"><?= e($hero['surtitre']) ?></p>
    <?php endif; ?>
    <h1 class="heros__titre"><?= e($hero['titre'] ?? '') ?></h1>
    <?php if (!empty($hero['texte'])): ?>
      <p class="heros__texte"><?= e($hero['texte']) ?></p>
    <?php endif; ?>
  </div>
</section>
<?php /* « En ce moment » suit le bandeau de chaque page. Il est posé ici, dans
         le bandeau partagé, plutôt que dans les seize vues qui l'utilisent :
         une page ajoutée demain l'aura sans qu'on y pense, et il n'y a aucun
         risque qu'une vue l'oublie. L'accueil a son propre bandeau et n'en
         hérite donc pas — c'est voulu, sa section « La vie du village » fait
         déjà le travail, en plus grand. */ ?>
<?= $view->partial('en-ce-moment') ?>
