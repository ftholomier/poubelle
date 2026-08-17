<?php
/**
 * Sélecteur de photo en mosaïque.
 *
 * Une liste déroulante de noms de fichiers ne dit rien de la photo :
 * on choisit ici sur la vignette. Tout repose sur des boutons radio,
 * donc sans JavaScript le choix fonctionne quand même — le champ de
 * filtre n'est qu'un confort quand la médiathèque s'allonge.
 *
 * @var string[] $medias   chemins relatifs à /public
 * @var string $nom        nom du champ envoyé
 * @var string $id         préfixe d'identifiant, unique dans la page
 * @var string $choisie    chemin actuellement retenu
 * @var string $vide       libellé du choix « aucune », '' pour le masquer
 */
$nom     = $nom     ?? 'photo';
$choisie = $choisie ?? '';
$vide    = $vide    ?? '';
?>
<div class="bo-choix" data-choix>
  <div class="bo-choix__barre">
    <input type="search" data-choix-filtre placeholder="Filtrer par nom de fichier…"
           aria-label="Filtrer les photos" autocomplete="off">
    <span class="bo-choix__compte" data-choix-compte aria-live="polite"></span>
  </div>

  <div class="bo-mosaique">
    <?php if ($vide !== ''): ?>
      <label class="bo-tuile bo-tuile--vide" data-nom="">
        <input type="radio" name="<?= e($nom) ?>" value=""<?= $choisie === '' ? ' checked' : '' ?>>
        <span class="bo-tuile__vide"><?= e($vide) ?></span>
      </label>
    <?php endif; ?>

    <?php foreach ($medias as $rang => $media): ?>
      <label class="bo-tuile" data-nom="<?= e(basename($media)) ?>">
        <input type="radio" name="<?= e($nom) ?>" value="<?= e($media) ?>"
               id="<?= e($id ?? 'photo') ?>-<?= $rang ?>"<?= $choisie === $media ? ' checked' : '' ?>>
        <img src="<?= image($media, true) ?>" alt="" loading="lazy">
        <span class="bo-tuile__nom"><?= e(basename($media)) ?></span>
      </label>
    <?php endforeach; ?>
  </div>

  <?php if ($medias === []): ?>
    <p class="bo-vide">La médiathèque est vide. Envoyez vos photos depuis l’écran
      <a href="<?= url('/admin/photos') ?>">Photos</a>, elles apparaîtront ici.</p>
  <?php endif; ?>
</div>
