<?php
/**
 * Édition de la boutique.
 * @var array $boutique
 */
use App\Core\Csrf;
?>
<form class="bo-form" method="post" action="<?= url('/admin/boutique') ?>">
  <?= Csrf::champ() ?>

  <fieldset>
    <legend>Horaires du magasin</legend>
    <div class="bo-champ">
      <label for="b-horaires">Bandeau horaires</label>
      <input id="b-horaires" type="text" name="horaires" value="<?= e($boutique['horaires']) ?>">
    </div>
  </fieldset>

  <?php foreach ($boutique['produits'] as $i => $produit): ?>
    <fieldset>
      <legend><?= e($produit['nom']) ?></legend>
      <div class="bo-rangee">
        <div class="bo-champ">
          <label for="b-nom-<?= $i ?>">Nom</label>
          <input id="b-nom-<?= $i ?>" type="text" name="produit_nom_<?= $i ?>" value="<?= e($produit['nom']) ?>">
        </div>
      </div>
      <div class="bo-champ">
        <label for="b-texte-<?= $i ?>">Description</label>
        <textarea id="b-texte-<?= $i ?>" name="produit_texte_<?= $i ?>" rows="2"><?= e($produit['texte']) ?></textarea>
      </div>
      <div class="bo-champ">
        <label for="b-det-<?= $i ?>">Détails / prix (un par ligne)</label>
        <textarea id="b-det-<?= $i ?>" name="produit_details_<?= $i ?>" rows="3"><?= e(implode("\n", $produit['details'])) ?></textarea>
      </div>
    </fieldset>
  <?php endforeach; ?>

  <button class="bo-btn" type="submit">Enregistrer</button>
</form>
