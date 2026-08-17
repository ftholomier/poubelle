<?php
/**
 * Galerie : envoi par lot, filtres, classement par glisser-déposer,
 * mise hors ligne.
 *
 * @var array $medias
 * @var array $categories
 * @var array $compte
 */
use App\Core\Content;
use App\Core\Csrf;
?>

<form class="bo-form" method="post" action="<?= url('/admin/galerie/ajout') ?>"
      enctype="multipart/form-data" id="ajout-galerie">
  <?= Csrf::champ() ?>
  <fieldset>
    <legend>Ajouter des photos</legend>

    <label class="bo-depot" for="g-images" data-depot>
      <span class="bo-depot__icone" aria-hidden="true">↑</span>
      <span class="bo-depot__titre">Glissez vos photos ici</span>
      <span class="bo-depot__aide">ou cliquez pour les choisir — plusieurs à la fois</span>
      <span class="bo-depot__liste" data-depot-liste></span>
      <input id="g-images" type="file" name="images[]" multiple
             accept="image/jpeg,image/png,image/webp" required>
    </label>

    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="g-cat">Catégorie</label>
        <select id="g-cat" name="categorie">
          <?php foreach ($categories as $cle => $libelle): ?>
            <option value="<?= e($cle) ?>"><?= e($libelle) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="bo-champ">
        <label for="g-alt">Légende commune (accessibilité)</label>
        <input id="g-alt" type="text" name="alt" placeholder="ex. Terrasse du lodge au coucher du soleil">
        <span class="aide">Vide : le nom de la catégorie est utilisé. Modifiable ensuite photo par photo.</span>
      </div>
    </div>

    <button class="bo-btn" type="submit">Envoyer</button>
  </fieldset>
</form>

<section class="bo-zone">
  <header class="bo-zone__tete">
    <h2>Photos de la galerie</h2>
    <p>Glissez une vignette pour la déplacer : l'ordre est celui du site.
       Une photo masquée reste ici mais disparaît de la galerie publique.</p>
  </header>

  <div class="bo-filtres" role="group" aria-label="Filtrer par catégorie">
    <button type="button" data-filtre="tout" aria-pressed="true">
      Toutes <span><?= e($compte['tout']) ?></span>
    </button>
    <?php foreach ($categories as $cle => $libelle): ?>
      <button type="button" data-filtre="<?= e($cle) ?>" aria-pressed="false">
        <?= e($libelle) ?> <span><?= e($compte[$cle] ?? 0) ?></span>
      </button>
    <?php endforeach; ?>
  </div>

  <?php if ($medias === []): ?>
    <p class="bo-zone__vide">Aucune photo pour l'instant.</p>
  <?php else: ?>
    <div class="bo-galerie" data-classable
         data-url-ordre="<?= url('/admin/galerie/ordre') ?>"
         data-csrf="<?= e(Csrf::jeton()) ?>">
      <?php foreach ($medias as $m): $visible = Content::estPublie($m); ?>
        <figure class="bo-media<?= $visible ? '' : ' bo-media--masque' ?>"
                data-cat="<?= e($m['cat'] ?? '') ?>" data-src="<?= e($m['src']) ?>" draggable="true">
          <img src="<?= image($m['src'], true) ?>" alt="<?= e($m['alt'] ?? '') ?>" loading="lazy">
          <?php if (!$visible): ?><span class="bo-media__masque">Masquée</span><?php endif; ?>

          <figcaption>
            <form method="post" action="<?= url('/admin/galerie/categorie') ?>" class="bo-media__cat">
              <?= Csrf::champ() ?>
              <input type="hidden" name="src" value="<?= e($m['src']) ?>">
              <select name="cat" onchange="this.form.submit()" aria-label="Catégorie de la photo">
                <?php foreach ($categories as $cle => $libelle): ?>
                  <option value="<?= e($cle) ?>"<?= ($m['cat'] ?? '') === $cle ? ' selected' : '' ?>>
                    <?= e($libelle) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </form>

            <div class="bo-media__actions">
              <form method="post" action="<?= url('/admin/galerie/publication') ?>">
                <?= Csrf::champ() ?>
                <input type="hidden" name="src" value="<?= e($m['src']) ?>">
                <button type="submit"><?= $visible ? 'Masquer' : 'Afficher' ?></button>
              </form>
              <form method="post" action="<?= url('/admin/galerie/retrait') ?>"
                    onsubmit="return confirm('Retirer cette image de la galerie ?')">
                <?= Csrf::champ() ?>
                <input type="hidden" name="src" value="<?= e($m['src']) ?>">
                <button type="submit" class="retirer">Retirer</button>
              </form>
            </div>
          </figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
    <p class="bo-ordre-etat" data-ordre-etat role="status"></p>
  <?php endif; ?>
</section>

<script src="<?= asset('assets/js/admin.js') ?>" defer></script>
