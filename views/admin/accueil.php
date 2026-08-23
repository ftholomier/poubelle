<?php
/**
 * Page d'accueil.
 *
 * @var array $accueil
 * @var string[] $medias
 * @var App\Core\View $view
 */
use App\Core\Csrf;

$indicateurs = implode("\n", array_map(
    static fn(array $i): string => ($i['valeur'] ?? '') . ' | ' . ($i['unite'] ?? '') . ' | ' . ($i['libelle'] ?? ''),
    $accueil['indicateurs']['items'] ?? []
));
$points = implode("\n", array_map(
    static fn(array $p): string => ($p['titre'] ?? '') . ' | ' . ($p['texte'] ?? ''),
    $accueil['societe']['points'] ?? []
));
?>
<form class="bo-form" method="post" action="<?= url('/admin/accueil') ?>" enctype="multipart/form-data">
  <?= Csrf::champ() ?>

  <fieldset>
    <legend>Bandeau d’accueil</legend>
    <div class="bo-champ">
      <label for="a-hsur">Sur-titre</label>
      <input id="a-hsur" type="text" name="hero_surtitre" value="<?= e($accueil['hero']['surtitre']) ?>">
    </div>
    <div class="bo-champ">
      <label for="a-htit">Titre principal (H1)</label>
      <input id="a-htit" type="text" name="hero_titre" value="<?= e($accueil['hero']['titre']) ?>">
    </div>
    <div class="bo-champ">
      <label for="a-htex">Texte d’introduction</label>
      <textarea id="a-htex" name="hero_texte" rows="3"><?= e($accueil['hero']['texte']) ?></textarea>
    </div>
    <div class="bo-champ">
      <label>Photo du bandeau</label>
      <?= $view->partial('admin/choix-photo', [
          'medias' => $medias, 'nom' => 'hero_image', 'id' => 'hero',
          'choisie' => $accueil['hero']['image'], 'vide' => '',
      ]) ?>
    </div>
  </fieldset>

  <fieldset>
    <legend>Chiffres clés</legend>
    <div class="bo-champ">
      <label for="a-ind">Indicateurs</label>
      <textarea id="a-ind" name="indicateurs" rows="5"><?= e($indicateurs) ?></textarea>
      <p class="bo-aide">
        Un par ligne : <code>valeur | unité | libellé</code>.
        Par exemple <code>99 | % | Dossiers maîtrisés</code>. Laissez l’unité vide
        pour une année. Quatre indicateurs tiennent sur une ligne à l’écran.
      </p>
    </div>
  </fieldset>

  <fieldset>
    <legend>Bloc « Nos services »</legend>
    <p class="bo-aide">Les services eux-mêmes se modifient dans <a href="<?= url('/admin/services') ?>">Services</a>.</p>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="a-ssur">Sur-titre</label>
        <input id="a-ssur" type="text" name="services_surtitre" value="<?= e($accueil['services']['surtitre']) ?>">
      </div>
      <div class="bo-champ">
        <label for="a-stit">Titre</label>
        <input id="a-stit" type="text" name="services_titre" value="<?= e($accueil['services']['titre']) ?>">
      </div>
    </div>
    <div class="bo-champ">
      <label for="a-stex">Texte</label>
      <textarea id="a-stex" name="services_texte" rows="2"><?= e($accueil['services']['texte']) ?></textarea>
    </div>
  </fieldset>

  <fieldset>
    <legend>Bloc « La société »</legend>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="a-csur">Sur-titre</label>
        <input id="a-csur" type="text" name="societe_surtitre" value="<?= e($accueil['societe']['surtitre']) ?>">
      </div>
      <div class="bo-champ">
        <label for="a-ctit">Titre</label>
        <input id="a-ctit" type="text" name="societe_titre" value="<?= e($accueil['societe']['titre']) ?>">
      </div>
    </div>
    <div class="bo-champ">
      <label for="a-ctex">Texte</label>
      <textarea id="a-ctex" name="societe_texte" rows="6"><?= e(implode("\n\n", $accueil['societe']['paragraphes'])) ?></textarea>
      <p class="bo-aide">Une ligne vide sépare deux paragraphes.</p>
    </div>
    <div class="bo-champ">
      <label for="a-cpts">Points forts</label>
      <textarea id="a-cpts" name="societe_points" rows="4"><?= e($points) ?></textarea>
      <p class="bo-aide">Un par ligne : <code>titre | texte</code>. La numérotation est automatique.</p>
    </div>
    <div class="bo-champ">
      <label>Photo</label>
      <?= $view->partial('admin/choix-photo', [
          'medias' => $medias, 'nom' => 'societe_image', 'id' => 'cab',
          'choisie' => $accueil['societe']['image'], 'vide' => '',
      ]) ?>
    </div>
  </fieldset>

  <fieldset>
    <legend>Bloc « Fabriqué en France »</legend>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="a-fsur">Sur-titre</label>
        <input id="a-fsur" type="text" name="france_surtitre" value="<?= e($accueil['france']['surtitre'] ?? '') ?>">
      </div>
      <div class="bo-champ">
        <label for="a-ftit">Titre</label>
        <input id="a-ftit" type="text" name="france_titre" value="<?= e($accueil['france']['titre'] ?? '') ?>">
      </div>
    </div>
    <div class="bo-champ">
      <label for="a-ftex">Texte</label>
      <textarea id="a-ftex" name="france_texte" rows="4"><?= e($accueil['france']['texte'] ?? '') ?></textarea>
    </div>
    <div class="bo-champ">
      <label for="a-fpts">Arguments</label>
      <textarea id="a-fpts" name="france_points" rows="4"><?= e(implode("\n", $accueil['france']['points'] ?? [])) ?></textarea>
      <p class="bo-aide">Un argument par ligne. Videz les quatre champs pour retirer la bande de la page d’accueil.</p>
    </div>
  </fieldset>

  <fieldset>
    <legend>Bloc « Nos valeurs »</legend>
    <p class="bo-aide">Les valeurs elles-mêmes se modifient dans <a href="<?= url('/admin/valeurs') ?>">Valeurs</a>.</p>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="a-vsur">Sur-titre</label>
        <input id="a-vsur" type="text" name="valeurs_surtitre" value="<?= e($accueil['valeurs']['surtitre']) ?>">
      </div>
      <div class="bo-champ">
        <label for="a-vtit">Titre</label>
        <input id="a-vtit" type="text" name="valeurs_titre" value="<?= e($accueil['valeurs']['titre']) ?>">
      </div>
    </div>
    <div class="bo-champ">
      <label for="a-vtex">Texte</label>
      <textarea id="a-vtex" name="valeurs_texte" rows="2"><?= e($accueil['valeurs']['texte']) ?></textarea>
    </div>
  </fieldset>

  <fieldset>
    <legend>Bloc « Sérénité »</legend>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="a-zsur">Sur-titre</label>
        <input id="a-zsur" type="text" name="serenite_surtitre" value="<?= e($accueil['serenite']['surtitre']) ?>">
      </div>
      <div class="bo-champ">
        <label for="a-ztit">Titre</label>
        <input id="a-ztit" type="text" name="serenite_titre" value="<?= e($accueil['serenite']['titre']) ?>">
      </div>
    </div>
    <div class="bo-champ">
      <label for="a-ztex">Texte</label>
      <textarea id="a-ztex" name="serenite_texte" rows="3"><?= e($accueil['serenite']['texte']) ?></textarea>
    </div>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="a-zchi">Chiffre mis en avant</label>
        <input id="a-zchi" type="text" name="serenite_chiffre" value="<?= e($accueil['serenite']['chiffre']) ?>">
      </div>
      <div class="bo-champ">
        <label for="a-zleg">Légende</label>
        <input id="a-zleg" type="text" name="serenite_legende" value="<?= e($accueil['serenite']['legende']) ?>">
      </div>
    </div>
    <div class="bo-champ">
      <label for="a-zarg">Arguments</label>
      <textarea id="a-zarg" name="serenite_arguments" rows="4"><?= e(implode("\n", $accueil['serenite']['arguments'])) ?></textarea>
      <p class="bo-aide">Un argument par ligne.</p>
    </div>
    <div class="bo-champ">
      <label>Photo</label>
      <?= $view->partial('admin/choix-photo', [
          'medias' => $medias, 'nom' => 'serenite_image', 'id' => 'ser',
          'choisie' => $accueil['serenite']['image'], 'vide' => '',
      ]) ?>
    </div>
  </fieldset>

  <fieldset>
    <legend>Référencement</legend>
    <div class="bo-champ">
      <label for="a-meta">Description affichée dans Google</label>
      <textarea id="a-meta" name="meta_description" rows="2"><?= e($accueil['meta']['description'] ?? '') ?></textarea>
      <p class="bo-aide">Entre 120 et 160 caractères.</p>
    </div>
  </fieldset>

  <button class="bo-btn" type="submit">Enregistrer</button>
</form>
