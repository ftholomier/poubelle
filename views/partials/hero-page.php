<?php
/**
 * Héros de page intérieure.
 * @var array $hero  image, surtitre, titre, texte?
 */
?>
<section class="heros heros--page">
  <div class="heros__fond" style="background-image:url('<?= asset($hero['image']) ?>')"></div>
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
