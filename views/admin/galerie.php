<?php
/**
 * Gestion de la galerie : upload et retrait.
 * @var array $medias
 * @var array $categories
 */
use App\Core\Csrf;
?>
<form class="bo-form" method="post" action="<?= url('/admin/galerie/ajout') ?>" enctype="multipart/form-data">
  <?= Csrf::champ() ?>
  <fieldset>
    <legend>Ajouter une photo</legend>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="g-image">Image (JPEG, PNG ou WebP — optimisée automatiquement)</label>
        <input id="g-image" type="file" name="image" accept="image/jpeg,image/png,image/webp" required>
      </div>
      <div class="bo-champ">
        <label for="g-cat">Catégorie</label>
        <select id="g-cat" name="categorie">
          <?php foreach ($categories as $cle => $libelle): ?>
            <option value="<?= e($cle) ?>"><?= e($libelle) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="bo-champ">
        <label for="g-alt">Légende (accessibilité)</label>
        <input id="g-alt" type="text" name="alt" placeholder="ex. Terrasse du lodge au coucher du soleil">
      </div>
    </div>
    <button class="bo-btn" type="submit">Ajouter</button>
  </fieldset>
</form>

<div class="bo-galerie">
  <?php foreach ($medias as $m): ?>
    <figure class="bo-media">
      <img src="<?= asset(str_replace('.jpg', '-mini.jpg', $m['src'])) ?>" alt="<?= e($m['alt']) ?>" loading="lazy">
      <figcaption>
        <span class="cat"><?= e($categories[$m['cat']] ?? $m['cat']) ?></span>
        <form method="post" action="<?= url('/admin/galerie/retrait') ?>"
              onsubmit="return confirm('Retirer cette image de la galerie ?')">
          <?= Csrf::champ() ?>
          <input type="hidden" name="src" value="<?= e($m['src']) ?>">
          <button type="submit">Retirer</button>
        </form>
      </figcaption>
    </figure>
  <?php endforeach; ?>
</div>
