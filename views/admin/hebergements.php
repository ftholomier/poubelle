<?php
/**
 * Liste des hébergements à éditer.
 * @var array $items
 */
?>
<?php use App\Core\Csrf; ?>

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
    <div class="bo-ligne">
      <img class="bo-ligne__vignette" src="<?= asset(str_replace('.jpg', '-mini.jpg', $h['images_avant'][0] ?? $h['image'])) ?>" alt="">
      <div class="bo-ligne__corps">
        <h2><?= e($h['nom']) ?></h2>
        <p>À partir de <?= e($h['prix_a_partir_de']) ?> € / nuit — <?= e($h['capacite']) ?> personnes</p>
      </div>
      <div class="bo-ligne__liens">
        <a href="<?= url('/admin/hebergements/' . rawurlencode($h['slug'])) ?>">Textes →</a>
        <a href="<?= url('/admin/hebergements/' . rawurlencode($h['slug']) . '/photos') ?>">Photos →</a>
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
