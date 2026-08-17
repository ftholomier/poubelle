<?php
/**
 * Édition de la page d'accueil.
 *
 * @var array $accueil
 * @var array $photos  photos du bandeau : [{src, actif}]
 * @var string[] $medias
 */
use App\Core\Csrf;
?>

<!-- ------------------------------------------------- diaporama du bandeau -->
<section id="bandeau" class="bo-zone">
  <h2>Photos du bandeau</h2>
  <p class="bo-intro">Au-delà d’une photo, le bandeau devient un diaporama : chaque
    visuel apparaît en fondu puis s’approche lentement. Masquez une photo pour la
    retirer du site sans la perdre.</p>

  <?php if ($photos === []): ?>
    <p class="bo-vide">Aucune photo pour l’instant : le bandeau affiche un visuel d’attente.</p>
  <?php endif; ?>

  <div class="bo-diapos">
    <?php foreach ($photos as $i => $photo): ?>
      <figure class="bo-diapo<?= $photo['actif'] ? '' : ' bo-diapo--masquee' ?>">
        <img src="<?= image($photo['src'], true) ?>" alt="" loading="lazy">
        <?php if (!$photo['actif']): ?><span class="bo-media__masque">masquée</span><?php endif; ?>
        <figcaption>
          <span class="bo-diapo__rang"><?= $i + 1 ?><?= $i === 0 && $photo['actif'] ? ' — première' : '' ?></span>
          <div class="bo-diapo__actions">
            <form method="post" action="<?= url('/admin/accueil/hero/' . $i . '/ordre') ?>">
              <?= Csrf::champ() ?>
              <button name="sens" value="monter" type="submit" title="Monter"<?= $i === 0 ? ' disabled' : '' ?>>↑</button>
              <button name="sens" value="descendre" type="submit" title="Descendre"<?= $i === count($photos) - 1 ? ' disabled' : '' ?>>↓</button>
            </form>
            <form method="post" action="<?= url('/admin/accueil/hero/' . $i . '/publication') ?>">
              <?= Csrf::champ() ?>
              <button class="bo-lien-bascule" type="submit"><?= $photo['actif'] ? 'Masquer' : 'Afficher' ?></button>
            </form>
            <form method="post" action="<?= url('/admin/accueil/hero/' . $i . '/supprimer') ?>"
                  onsubmit="return confirm('Retirer cette photo du diaporama ? Elle reste dans la médiathèque.')">
              <?= Csrf::champ() ?>
              <button class="bo-lien-danger" type="submit">Retirer</button>
            </form>
          </div>
        </figcaption>
      </figure>
    <?php endforeach; ?>
  </div>

  <form class="bo-form" method="post" action="<?= url('/admin/accueil/hero/ajout') ?>"
        enctype="multipart/form-data" style="margin-top:1.6rem;">
    <?= Csrf::champ() ?>
    <fieldset>
      <legend>Ajouter une photo</legend>
      <div class="bo-champ">
        <span class="bo-etiquette-champ">Depuis la médiathèque</span>
        <?= $view->partial('admin/choix-photo', [
              'medias' => $medias, 'nom' => 'photo', 'id' => 'hero', 'choisie' => '',
            ]) ?>
      </div>
      <div class="bo-champ">
        <label for="hero-fichier">Ou envoyer une nouvelle photo</label>
        <input id="hero-fichier" type="file" name="photo" accept="image/jpeg,image/png,image/webp">
        <span class="aide">Format paysage, 1920 px de large au minimum.</span>
      </div>
      <button class="bo-btn" type="submit">Ajouter au diaporama</button>
    </fieldset>
  </form>
</section>

<form class="bo-form" method="post" action="<?= url('/admin/accueil') ?>">
  <?= Csrf::champ() ?>

  <fieldset>
    <legend>Bandeau principal</legend>
    <div class="bo-champ">
      <label for="a-surtitre">Surtitre</label>
      <input id="a-surtitre" type="text" name="hero_surtitre" value="<?= e($accueil['hero']['surtitre']) ?>">
    </div>
    <div class="bo-champ">
      <label for="a-titre">Titre</label>
      <input id="a-titre" type="text" name="hero_titre" value="<?= e($accueil['hero']['titre']) ?>">
    </div>
    <div class="bo-champ">
      <label for="a-texte">Texte</label>
      <textarea id="a-texte" name="hero_texte" rows="4"><?= e($accueil['hero']['texte']) ?></textarea>
    </div>
  </fieldset>

  <fieldset>
    <legend>Les trois piliers</legend>
    <?php foreach ($accueil['piliers']['items'] as $i => $pilier): ?>
      <div class="bo-champ">
        <label for="a-pilier-<?= $i ?>">Pilier <?= $i + 1 ?></label>
        <textarea id="a-pilier-<?= $i ?>" name="pilier_<?= $i ?>" rows="3"><?= e($pilier['texte']) ?></textarea>
      </div>
    <?php endforeach; ?>
    <div class="bo-champ">
      <label for="a-cta">Phrase d'appel</label>
      <input id="a-cta" type="text" name="piliers_cta" value="<?= e($accueil['piliers']['cta']) ?>">
    </div>
  </fieldset>

  <fieldset>
    <legend>Citation</legend>
    <div class="bo-champ">
      <label for="a-ctitre">Citation</label>
      <textarea id="a-ctitre" name="citation_titre" rows="3"><?= e($accueil['citation']['titre']) ?></textarea>
    </div>
    <div class="bo-champ">
      <label for="a-ctexte">Texte sous la citation</label>
      <textarea id="a-ctexte" name="citation_texte" rows="2"><?= e($accueil['citation']['texte']) ?></textarea>
    </div>
  </fieldset>

  <fieldset>
    <legend>Référencement</legend>
    <div class="bo-champ">
      <label for="a-meta">Description (moteurs de recherche)</label>
      <textarea id="a-meta" name="meta" rows="2"><?= e($accueil['meta']['description']) ?></textarea>
    </div>
  </fieldset>

  <button class="bo-btn" type="submit">Enregistrer</button>
</form>
