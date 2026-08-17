<?php
/**
 * Médiathèque : les photos disponibles pour illustrer le site.
 *
 * @var string[] $medias
 * @var array<string, string[]> $usages
 */
use App\Core\Csrf;
?>
<div class="bo-bloc">
  <h2>Ajouter des photos</h2>
  <form class="bo-form" method="post" action="<?= url('/admin/photos/ajout') ?>" enctype="multipart/form-data">
    <?= Csrf::champ() ?>
    <div class="bo-champ">
      <label for="m-fichiers">Fichiers</label>
      <input id="m-fichiers" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple required>
      <p class="bo-aide">
        Plusieurs fichiers à la fois. Chaque photo est automatiquement
        redimensionnée et allégée, et une vignette est créée : inutile de les
        préparer avant l’envoi.
      </p>
    </div>
    <button class="bo-btn" type="submit">Envoyer</button>
  </form>
</div>

<div class="bo-bloc">
  <h2><?= count($medias) ?> photo<?= count($medias) > 1 ? 's' : '' ?> dans la médiathèque</h2>

  <?php if ($medias === []): ?>
    <p class="bo-vide">Aucune photo pour l’instant.</p>
  <?php else: ?>
    <div class="bo-galerie">
      <?php foreach ($medias as $media):
          $usage = $usages[$media] ?? []; ?>
        <figure class="bo-media">
          <img src="<?= image($media, true) ?>" alt="" loading="lazy">
          <figcaption>
            <span class="bo-media__nom"><?= e(basename($media)) ?></span>
            <?php if ($usage !== []): ?>
              <span class="bo-media__usage">Utilisée : <?= e(implode(', ', $usage)) ?></span>
            <?php else: ?>
              <span class="bo-media__usage bo-media__usage--libre">Non utilisée</span>
            <?php endif; ?>

            <form method="post" action="<?= url('/admin/photos/retrait') ?>"
                  data-confirmer="Supprimer définitivement <?= e(basename($media)) ?> ?">
              <?= Csrf::champ() ?>
              <input type="hidden" name="src" value="<?= e($media) ?>">
              <?php if ($usage !== []): ?>
                <label class="bo-case bo-case--petite">
                  <input type="checkbox" name="confirme" value="1">
                  Supprimer quand même
                </label>
              <?php endif; ?>
              <button class="bo-btn bo-btn--petit bo-btn--danger" type="submit">Supprimer</button>
            </form>
          </figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
