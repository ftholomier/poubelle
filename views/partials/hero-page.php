<?php
/**
 * Héros de page intérieure.
 * @var array $hero  image, surtitre, titre, texte?
 */
?>
<section class="heros heros--page">
  <?php /* même structure que le bandeau d'accueil : la photo vit dans un
           .heros__photo, seul porteur du cadrage « cover » et du lent
           mouvement d'approche. La poser sur .heros__fond la laissait se
           répéter en mosaïque. */ ?>
  <div class="heros__fond">
    <div class="heros__photo heros__photo--visible heros__photo--anime"
         style="background-image:url('<?= image($hero['image']) ?>')"></div>
  </div>
  <div class="heros__contenu">
    <?php if (!empty($hero['surtitre'])): ?>
      <p class="surtitre surtitre--centre"><?= e($hero['surtitre']) ?></p>
    <?php endif; ?>
    <h1 class="heros__titre"><?= e($hero['titre']) ?></h1>
    <?php if (!empty($hero['texte'])): ?>
      <p class="heros__texte"><?= e($hero['texte']) ?></p>
    <?php endif; ?>
  </div>
</section>
