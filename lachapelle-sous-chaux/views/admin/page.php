<?php
/**
 * Édition d'une page à blocs.
 *
 * @var string $cle
 * @var array  $contenu
 * @var string $chemin
 * @var string[] $medias
 * @var string[] $documents
 * @var App\Core\View $view
 */
use App\Core\Csrf;

$hero = $contenu['hero'] ?? [];
?>
<p class="bo-fil">
  <a href="<?= url('/admin/pages') ?>">← Toutes les pages</a>
  <a href="<?= url($chemin) ?>" target="_blank" rel="noopener">Voir la page ↗</a>
</p>

<form class="bo-form" method="post" action="<?= url('/admin/pages/' . $cle) ?>" data-form-page>
  <?= Csrf::champ() ?>

  <fieldset>
    <legend>Bandeau</legend>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="p-sur">Sur-titre</label>
        <input id="p-sur" type="text" name="hero_surtitre" value="<?= e($hero['surtitre'] ?? '') ?>">
      </div>
      <div class="bo-champ">
        <label for="p-tit">Titre principal (H1)</label>
        <input id="p-tit" type="text" name="hero_titre" value="<?= e($hero['titre'] ?? '') ?>">
      </div>
    </div>
    <div class="bo-champ bo-champ--large">
      <label for="p-htx">Phrase du bandeau</label>
      <textarea id="p-htx" name="hero_texte" rows="2"><?= e($hero['texte'] ?? '') ?></textarea>
    </div>
    <div class="bo-champ">
      <label>Photo du bandeau</label>
      <?= $view->partial('admin/choix-photo', [
          'medias' => $medias, 'nom' => 'hero_image', 'id' => 'pimg',
          'choisie' => $hero['image'] ?? '', 'vide' => 'Aucune',
      ]) ?>
    </div>
  </fieldset>

  <fieldset>
    <legend>Introduction et référencement</legend>
    <div class="bo-champ bo-champ--large">
      <label for="p-sst">Chapô</label>
      <textarea id="p-sst" name="sous_titre" rows="3"><?= e($contenu['sous_titre'] ?? '') ?></textarea>
      <p class="bo-aide">Le paragraphe d’introduction, posé juste sous le bandeau. Deux à quatre lignes.</p>
    </div>
    <div class="bo-champ">
      <label for="p-titre">Titre interne</label>
      <input id="p-titre" type="text" name="titre" value="<?= e($contenu['titre'] ?? '') ?>">
      <p class="bo-aide">Sert de titre de page dans l’onglet du navigateur et dans les résultats de recherche.</p>
    </div>
    <div class="bo-champ bo-champ--large">
      <label for="p-desc">Description pour les moteurs</label>
      <textarea id="p-desc" name="meta_description" rows="2"><?= e($contenu['meta']['description'] ?? '') ?></textarea>
      <p class="bo-aide">Environ 155 caractères. C’est le texte affiché sous le titre dans les résultats de recherche.</p>
    </div>
  </fieldset>

  <h2 class="bo-sous-titre">Les blocs de la page</h2>
  <?= $view->partial('admin/blocs', [
      'sections'  => $contenu['sections'] ?? [],
      'medias'    => $medias,
      'documents' => $documents,
  ]) ?>

  <div class="bo-barre-actions">
    <button class="bo-btn" type="submit">Enregistrer la page</button>
    <a class="bo-btn bo-btn--fantome" href="<?= url('/admin/avance?nom=pages/' . $cle) ?>">Éditeur avancé</a>
  </div>
</form>

<?= $view->partial('admin/ajout-bloc', ['action' => url('/admin/pages/' . $cle . '/bloc')]) ?>
