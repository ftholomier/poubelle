<?php
/**
 * Liste des hébergements à éditer.
 * @var array $items
 */
?>
<?php
/**
 * Liste des hébergements : ajout, édition, mise en ligne, suppression.
 * @var array $items
 */
use App\Core\Content;
use App\Core\Csrf;
?>

<form class="bo-form" method="post" action="<?= url('/admin/hebergements/creer') ?>" style="margin-bottom:1.8rem;">
  <?= Csrf::champ() ?>
  <fieldset>
    <legend>Ajouter un hébergement</legend>
    <div class="bo-champ">
      <label for="h-nouveau">Nom du nouvel hébergement</label>
      <input id="h-nouveau" type="text" name="nom" required minlength="2"
             placeholder="ex. La Cabane des Saules">
      <span class="aide">Une fiche vierge est créée, puis vous la complétez. Elle apparaît
        aussitôt sur le site et dans le menu.</span>
    </div>
    <button class="bo-btn" type="submit">Créer la fiche</button>
  </fieldset>
</form>

<div class="bo-liste">
  <?php foreach ($items as $h): ?>
    <?php $enLigne = Content::estPublie($h); ?>
    <div class="bo-ligne<?= $enLigne ? '' : ' bo-ligne--hors-ligne' ?>">
      <img class="bo-ligne__vignette" src="<?= image($h['images_avant'][0] ?? $h['image'], true) ?>" alt="">
      <div class="bo-ligne__corps">
        <h2>
          <?= e($h['nom']) ?>
          <?php if (!$enLigne): ?><span class="bo-etiquette">hors ligne</span><?php endif; ?>
        </h2>
        <p>À partir de <?= e($h['prix_a_partir_de']) ?> € / nuit — <?= e($h['capacite']) ?> personnes</p>
      </div>
      <div class="bo-ligne__liens">
        <a href="<?= url('/admin/hebergements/' . rawurlencode($h['slug'])) ?>">Textes →</a>
        <a href="<?= url('/admin/hebergements/' . rawurlencode($h['slug']) . '/photos') ?>">Photos →</a>
        <form method="post" action="<?= url('/admin/hebergements/' . rawurlencode($h['slug']) . '/publication') ?>">
          <?= Csrf::champ() ?>
          <button class="bo-lien-bascule" type="submit">
            <?= $enLigne ? 'Mettre hors ligne' : 'Mettre en ligne' ?>
          </button>
        </form>
        <?php if (count($items) > 1): ?>
          <form method="post" action="<?= url('/admin/hebergements/' . rawurlencode($h['slug']) . '/supprimer') ?>"
                onsubmit="return confirm('Supprimer « <?= e($h['nom']) ?> » ? Sa fiche disparaîtra du site. Les photos restent dans la médiathèque.')">
            <?= Csrf::champ() ?>
            <button class="bo-lien-danger" type="submit">Supprimer</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
