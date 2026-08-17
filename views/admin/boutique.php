<?php
/**
 * Édition de la boutique : produits, photos, mise en ligne.
 *
 * @var array $boutique
 * @var string[] $medias
 */
use App\Core\Content;
use App\Core\Csrf;

$produits = $boutique['produits'];
?>

<form class="bo-form" method="post" action="<?= url('/admin/boutique/creer') ?>" style="margin-bottom:1.8rem;">
  <?= Csrf::champ() ?>
  <fieldset>
    <legend>Ajouter un produit</legend>
    <div class="bo-champ">
      <label for="b-nouveau">Nom du nouveau produit</label>
      <input id="b-nouveau" type="text" name="nom" required minlength="2"
             placeholder="ex. Miel de nos ruches">
      <span class="aide">Une fiche vierge est créée, hors ligne. Complétez-la, ajoutez sa
        photo, puis mettez-la en ligne : elle apparaîtra aussitôt dans la boutique.</span>
    </div>
    <button class="bo-btn" type="submit">Créer le produit</button>
  </fieldset>
</form>

<form class="bo-form" method="post" action="<?= url('/admin/boutique') ?>">
  <?= Csrf::champ() ?>

  <fieldset>
    <legend>Horaires du magasin</legend>
    <div class="bo-champ">
      <label for="b-horaires">Bandeau horaires</label>
      <input id="b-horaires" type="text" name="horaires" value="<?= e($boutique['horaires']) ?>">
    </div>
  </fieldset>

  <?php foreach ($produits as $i => $produit):
      $enLigne = Content::estPublie($produit); ?>
    <fieldset id="produit-<?= $i ?>"<?= $enLigne ? '' : ' class="bo-hors-ligne"' ?>>
      <legend>
        <?= e($produit['nom']) ?>
        <?php if (!$enLigne): ?><span class="bo-etiquette">hors ligne</span><?php endif; ?>
      </legend>

      <div class="bo-produit">
        <img class="bo-produit__vignette" src="<?= image($produit['image'] ?? '', true) ?>" alt="">
        <div class="bo-produit__champs">
          <div class="bo-champ">
            <label for="b-nom-<?= $i ?>">Nom</label>
            <input id="b-nom-<?= $i ?>" type="text" name="produit_nom_<?= $i ?>" value="<?= e($produit['nom']) ?>">
          </div>
          <div class="bo-champ">
            <label for="b-texte-<?= $i ?>">Description</label>
            <textarea id="b-texte-<?= $i ?>" name="produit_texte_<?= $i ?>" rows="2"><?= e($produit['texte'] ?? '') ?></textarea>
          </div>
        </div>
      </div>

      <div class="bo-champ">
        <label for="b-det-<?= $i ?>">Détails / prix (un par ligne)</label>
        <textarea id="b-det-<?= $i ?>" name="produit_details_<?= $i ?>" rows="3"><?= e(implode("\n", $produit['details'] ?? [])) ?></textarea>
      </div>
    </fieldset>
  <?php endforeach; ?>

  <button class="bo-btn" type="submit">Enregistrer les textes</button>
</form>

<!-- Photos et mise en ligne : formulaires distincts, car un formulaire ne
     peut pas en contenir un autre. -->
<section class="bo-zone" style="margin-top:2rem;">
  <h2>Photo et mise en ligne de chaque produit</h2>
  <p class="bo-intro">Un produit hors ligne disparaît de la boutique sans être supprimé —
    pratique pour un produit de saison.</p>

  <?php foreach ($produits as $i => $produit):
      $enLigne = Content::estPublie($produit); ?>
    <div class="bo-ligne<?= $enLigne ? '' : ' bo-ligne--hors-ligne' ?>">
      <img class="bo-ligne__vignette" src="<?= image($produit['image'] ?? '', true) ?>" alt="">
      <div class="bo-ligne__corps">
        <h3>
          <a href="#produit-<?= $i ?>"><?= e($produit['nom']) ?></a>
          <?php if (!$enLigne): ?><span class="bo-etiquette">hors ligne</span><?php endif; ?>
        </h3>

        <form method="post" enctype="multipart/form-data"
              action="<?= url('/admin/boutique/' . $i . '/photo') ?>">
          <?= Csrf::champ() ?>
          <details class="bo-repli">
            <summary>Changer la photo</summary>
            <?= $view->partial('admin/choix-photo', [
                  'medias'  => $medias,
                  'nom'     => 'photo',
                  'id'      => 'prod-' . $i,
                  'choisie' => (string) ($produit['image'] ?? ''),
                ]) ?>
            <div class="bo-champ">
              <label for="b-fichier-<?= $i ?>">Ou envoyer une nouvelle photo</label>
              <input id="b-fichier-<?= $i ?>" type="file" name="photo"
                     accept="image/jpeg,image/png,image/webp">
            </div>
            <button class="bo-btn bo-btn--mince" type="submit">Enregistrer la photo</button>
          </details>
        </form>
      </div>

      <div class="bo-ligne__liens">
        <form method="post" action="<?= url('/admin/boutique/' . $i . '/ordre') ?>">
          <?= Csrf::champ() ?>
          <button class="bo-lien-bascule" name="sens" value="monter" type="submit"<?= $i === 0 ? ' disabled' : '' ?>>↑ Monter</button>
          <button class="bo-lien-bascule" name="sens" value="descendre" type="submit"<?= $i === count($produits) - 1 ? ' disabled' : '' ?>>↓ Descendre</button>
        </form>
        <form method="post" action="<?= url('/admin/boutique/' . $i . '/publication') ?>">
          <?= Csrf::champ() ?>
          <button class="bo-lien-bascule" type="submit"><?= $enLigne ? 'Mettre hors ligne' : 'Mettre en ligne' ?></button>
        </form>
        <form method="post" action="<?= url('/admin/boutique/' . $i . '/supprimer') ?>"
              onsubmit="return confirm('Supprimer « <?= e($produit['nom']) ?> » ? Sa fiche disparaîtra de la boutique. La photo reste dans la médiathèque.')">
          <?= Csrf::champ() ?>
          <button class="bo-lien-danger" type="submit">Supprimer</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
</section>
