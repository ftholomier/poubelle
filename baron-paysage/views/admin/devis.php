<?php
/**
 * Page « Demander un devis » : bandeau, étapes du parcours, formulaire.
 *
 * @var array $devis
 * @var string[] $medias
 * @var App\Core\View $view
 */
use App\Core\Csrf;
?>
<form class="bo-form" method="post" action="<?= url('/admin/devis') ?>" enctype="multipart/form-data">
  <?= Csrf::champ() ?>

  <fieldset>
    <legend>Bandeau</legend>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="d-hsur">Sur-titre</label>
        <input id="d-hsur" type="text" name="hero_surtitre" value="<?= e($devis['hero']['surtitre'] ?? '') ?>">
      </div>
      <div class="bo-champ">
        <label for="d-htit">Titre principal (H1)</label>
        <input id="d-htit" type="text" name="hero_titre" value="<?= e($devis['hero']['titre'] ?? '') ?>">
      </div>
    </div>
    <div class="bo-champ">
      <label for="d-htex">Texte d’introduction</label>
      <textarea id="d-htex" name="hero_texte" rows="3"><?= e($devis['hero']['texte'] ?? '') ?></textarea>
    </div>
    <div class="bo-champ">
      <label>Photo du bandeau</label>
      <?= $view->partial('admin/choix-photo', [
          'medias' => $medias, 'nom' => 'hero_image', 'id' => 'dhero',
          'choisie' => $devis['hero']['image'] ?? '', 'vide' => '',
      ]) ?>
    </div>
  </fieldset>

  <fieldset>
    <legend>Introduction</legend>
    <div class="bo-champ">
      <label for="d-itit">Titre</label>
      <input id="d-itit" type="text" name="intro_titre" value="<?= e($devis['introduction']['titre'] ?? '') ?>">
    </div>
    <div class="bo-champ">
      <label for="d-itex">Texte</label>
      <textarea id="d-itex" name="intro_texte" rows="3"><?= e($devis['introduction']['texte'] ?? '') ?></textarea>
    </div>
  </fieldset>

  <fieldset>
    <legend>Ce qui se passe après l’envoi</legend>
    <p class="bo-aide">
      Les quatre étapes répondent à la question qui retient la main sur le
      bouton d’envoi : « et ensuite ? ».
    </p>
    <?php foreach ((array) ($devis['etapes'] ?? []) as $i => $etape): ?>
      <div class="bo-rangee">
        <div class="bo-champ">
          <label for="d-et<?= $i ?>">Étape <?= e($etape['numero'] ?? ($i + 1)) ?></label>
          <input id="d-et<?= $i ?>" type="text" name="etape_titre_<?= $i ?>" value="<?= e($etape['titre'] ?? '') ?>">
        </div>
        <div class="bo-champ bo-champ--large">
          <label for="d-ex<?= $i ?>">Texte</label>
          <input id="d-ex<?= $i ?>" type="text" name="etape_texte_<?= $i ?>" value="<?= e($etape['texte'] ?? '') ?>">
        </div>
      </div>
    <?php endforeach; ?>
  </fieldset>

  <fieldset>
    <legend>Formulaire</legend>
    <p class="bo-aide">
      Le destinataire des demandes se règle dans
      <a href="<?= url('/admin/parametres') ?>">Paramètres</a>.
      Les champs nom, e-mail, localité et message sont obligatoires côté visiteur.
    </p>
    <div class="bo-champ">
      <label for="d-ftit">Titre</label>
      <input id="d-ftit" type="text" name="form_titre" value="<?= e($devis['formulaire']['titre'] ?? '') ?>">
    </div>
    <div class="bo-champ">
      <label for="d-ftex">Texte</label>
      <textarea id="d-ftex" name="form_texte" rows="2"><?= e($devis['formulaire']['texte'] ?? '') ?></textarea>
    </div>
    <div class="bo-champ">
      <label for="d-fobj">Objets proposés</label>
      <textarea id="d-fobj" name="form_objets" rows="10"><?= e(implode("\n", (array) ($devis['formulaire']['objets'] ?? []))) ?></textarea>
      <p class="bo-aide">Un objet par ligne. Ils alimentent la liste déroulante du formulaire.</p>
    </div>
    <div class="bo-champ">
      <label for="d-fmen">Mention sous le formulaire</label>
      <textarea id="d-fmen" name="form_mention" rows="2"><?= e($devis['formulaire']['mention'] ?? '') ?></textarea>
    </div>
  </fieldset>

  <fieldset>
    <legend>Référencement</legend>
    <div class="bo-champ">
      <label for="d-meta">Description affichée dans Google</label>
      <textarea id="d-meta" name="meta_description" rows="2"><?= e($devis['meta']['description'] ?? '') ?></textarea>
    </div>
  </fieldset>

  <button class="bo-btn" type="submit">Enregistrer</button>
</form>
