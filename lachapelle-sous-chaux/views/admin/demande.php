<?php
/**
 * Page « Écrire à la mairie » : bandeau, étapes du parcours, formulaire.
 *
 * @var array $demande
 * @var string[] $medias
 * @var App\Core\View $view
 */
use App\Core\Csrf;
?>
<form class="bo-form" method="post" action="<?= url('/admin/demande') ?>" enctype="multipart/form-data">
  <?= Csrf::champ() ?>

  <fieldset>
    <legend>Bandeau</legend>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="d-hsur">Sur-titre</label>
        <input id="d-hsur" type="text" name="hero_surtitre" value="<?= e($demande['hero']['surtitre'] ?? '') ?>">
      </div>
      <div class="bo-champ">
        <label for="d-htit">Titre principal (H1)</label>
        <input id="d-htit" type="text" name="hero_titre" value="<?= e($demande['hero']['titre'] ?? '') ?>">
      </div>
    </div>
    <div class="bo-champ">
      <label for="d-htex">Texte d’introduction</label>
      <textarea id="d-htex" name="hero_texte" rows="3"><?= e($demande['hero']['texte'] ?? '') ?></textarea>
    </div>
    <div class="bo-champ">
      <label>Photo du bandeau</label>
      <?= $view->partial('admin/choix-photo', [
          'medias' => $medias, 'nom' => 'hero_image', 'id' => 'dhero',
          'choisie' => $demande['hero']['image'] ?? '', 'vide' => '',
      ]) ?>
    </div>
  </fieldset>

  <fieldset>
    <legend>Introduction</legend>
    <div class="bo-champ">
      <label for="d-itit">Titre</label>
      <input id="d-itit" type="text" name="intro_titre" value="<?= e($demande['introduction']['titre'] ?? '') ?>">
    </div>
    <div class="bo-champ">
      <label for="d-itex">Texte</label>
      <textarea id="d-itex" name="intro_texte" rows="3"><?= e($demande['introduction']['texte'] ?? '') ?></textarea>
    </div>
  </fieldset>

  <fieldset>
    <legend>Ce qui se passe après l’envoi</legend>
    <p class="bo-aide">
      Les quatre étapes répondent à la question qui retient la main sur le
      bouton d’envoi : « et ensuite ? ».
    </p>
    <?php /* En texte plutôt qu'en champs répétés : une étape s'ajoute, se
             retire et se réordonne au copier-coller, là où des champs figés
             obligeraient à en prévoir un nombre d'avance. */ ?>
    <?php
    $etapesTexte = implode("\n\n", array_map(
        static fn(array $e): string => ($e['titre'] ?? '') . ' || ' . ($e['texte'] ?? ''),
        (array) ($demande['etapes'] ?? [])
    ));
    ?>
    <div class="bo-champ bo-champ--large">
      <label for="d-etapes">Les étapes</label>
      <textarea id="d-etapes" name="etapes" rows="12"><?= e($etapesTexte) ?></textarea>
      <p class="bo-aide">
        Une étape par bloc, sous la forme <code>Titre || Texte</code>, les blocs
        séparés par une ligne vide. Les numéros sont posés automatiquement.
      </p>
    </div>
  </fieldset>

  <fieldset>
    <legend>Formulaire</legend>
    <p class="bo-aide">
      Le destinataire des demandes se règle dans
      <a href="<?= url('/admin/parametres') ?>">Paramètres</a> ; à défaut, elles
      partent vers l’adresse de la mairie saisie dans Coordonnées.
      Les champs objet, nom, adresse électronique et détail sont obligatoires
      côté visiteur.
    </p>
    <div class="bo-champ">
      <label for="d-ftit">Titre</label>
      <input id="d-ftit" type="text" name="form_titre" value="<?= e($demande['formulaire']['titre'] ?? '') ?>">
    </div>
    <div class="bo-champ">
      <label for="d-ftex">Texte</label>
      <textarea id="d-ftex" name="form_texte" rows="2"><?= e($demande['formulaire']['texte'] ?? '') ?></textarea>
    </div>
    <div class="bo-champ bo-champ--large">
      <label for="d-fobj">Objets proposés</label>
      <textarea id="d-fobj" name="sujets" rows="14"><?= e(implode("\n", (array) ($demande['sujets'] ?? []))) ?></textarea>
      <p class="bo-aide">
        Un objet par ligne. C’est lui qui décide du service qui traitera la
        demande, et il est repris dans l’objet du courriel reçu au secrétariat :
        formulez-le comme vous voulez le lire dans votre boîte.
      </p>
    </div>
    <div class="bo-champ">
      <label for="d-fmen">Mention sous le formulaire</label>
      <textarea id="d-fmen" name="form_mention" rows="2"><?= e($demande['formulaire']['mention'] ?? '') ?></textarea>
    </div>
  </fieldset>

  <fieldset>
    <legend>Référencement</legend>
    <div class="bo-champ">
      <label for="d-meta">Description affichée dans Google</label>
      <textarea id="d-meta" name="meta_description" rows="2"><?= e($demande['meta']['description'] ?? '') ?></textarea>
    </div>
  </fieldset>

  <button class="bo-btn" type="submit">Enregistrer</button>
</form>
