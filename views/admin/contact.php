<?php
/**
 * Page « Contact » : textes, formulaire et questions fréquentes.
 *
 * @var array $contact
 * @var string[] $medias
 * @var App\Core\View $view
 */
use App\Core\Csrf;

$faq = implode("\n\n", array_map(
    static fn(array $q): string => ($q['question'] ?? '') . ' || ' . ($q['reponse'] ?? ''),
    $contact['faq']['items'] ?? []
));
?>
<form class="bo-form" method="post" action="<?= url('/admin/contact') ?>" enctype="multipart/form-data">
  <?= Csrf::champ() ?>

  <fieldset>
    <legend>Bandeau</legend>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="k-hsur">Sur-titre</label>
        <input id="k-hsur" type="text" name="hero_surtitre" value="<?= e($contact['hero']['surtitre']) ?>">
      </div>
      <div class="bo-champ">
        <label for="k-htit">Titre principal (H1)</label>
        <input id="k-htit" type="text" name="hero_titre" value="<?= e($contact['hero']['titre']) ?>">
      </div>
    </div>
    <div class="bo-champ">
      <label for="k-htex">Texte d’introduction</label>
      <textarea id="k-htex" name="hero_texte" rows="3"><?= e($contact['hero']['texte']) ?></textarea>
    </div>
    <div class="bo-champ">
      <label>Photo du bandeau</label>
      <?= $view->partial('admin/choix-photo', [
          'medias' => $medias, 'nom' => 'hero_image', 'id' => 'khero',
          'choisie' => $contact['hero']['image'], 'vide' => '',
      ]) ?>
    </div>
  </fieldset>

  <fieldset>
    <legend>Introduction</legend>
    <div class="bo-champ">
      <label for="k-itit">Titre</label>
      <input id="k-itit" type="text" name="intro_titre" value="<?= e($contact['introduction']['titre']) ?>">
    </div>
    <div class="bo-champ">
      <label for="k-itex">Texte</label>
      <textarea id="k-itex" name="intro_texte" rows="3"><?= e($contact['introduction']['texte']) ?></textarea>
    </div>
  </fieldset>

  <fieldset>
    <legend>Accès</legend>
    <p class="bo-aide">
      L’adresse et les horaires affichés viennent de
      <a href="<?= url('/admin/site') ?>">Coordonnées &amp; menu</a>.
    </p>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="k-atit">Titre du bloc</label>
        <input id="k-atit" type="text" name="adresse_titre" value="<?= e($contact['adresse']['titre']) ?>">
      </div>
      <div class="bo-champ bo-champ--large">
        <label for="k-acom">Précision d’accès</label>
        <input id="k-acom" type="text" name="adresse_complement" value="<?= e($contact['adresse']['complement'] ?? '') ?>">
      </div>
    </div>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="k-clib">Libellé du lien vers le plan</label>
        <input id="k-clib" type="text" name="carte_libelle" value="<?= e($contact['adresse']['carte']['libelle'] ?? '') ?>">
      </div>
      <div class="bo-champ bo-champ--large">
        <label for="k-curl">Adresse du plan</label>
        <input id="k-curl" type="url" name="carte_url" value="<?= e($contact['adresse']['carte']['url'] ?? '') ?>">
        <p class="bo-aide">
          Un lien plutôt qu’une carte intégrée : une carte Google chargée d’office
          dépose des traceurs chez tous vos visiteurs, y compris ceux qui les refusent.
        </p>
      </div>
    </div>
  </fieldset>

  <fieldset>
    <legend>Formulaire</legend>
    <p class="bo-aide">
      Le destinataire des demandes se règle dans
      <a href="<?= url('/admin/parametres') ?>">Paramètres</a>.
    </p>
    <div class="bo-champ">
      <label for="k-ftit">Titre</label>
      <input id="k-ftit" type="text" name="form_titre" value="<?= e($contact['formulaire']['titre']) ?>">
    </div>
    <div class="bo-champ">
      <label for="k-ftex">Texte</label>
      <textarea id="k-ftex" name="form_texte" rows="2"><?= e($contact['formulaire']['texte']) ?></textarea>
    </div>
    <div class="bo-champ">
      <label for="k-fobj">Objets proposés</label>
      <textarea id="k-fobj" name="form_objets" rows="7"><?= e(implode("\n", $contact['formulaire']['objets'])) ?></textarea>
      <p class="bo-aide">Un objet par ligne. Ils alimentent la liste déroulante du formulaire.</p>
    </div>
    <div class="bo-champ">
      <label for="k-fmen">Mention sous le formulaire</label>
      <textarea id="k-fmen" name="form_mention" rows="2"><?= e($contact['formulaire']['mention']) ?></textarea>
    </div>
  </fieldset>

  <fieldset>
    <legend>Questions fréquentes</legend>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="k-qsur">Sur-titre</label>
        <input id="k-qsur" type="text" name="faq_surtitre" value="<?= e($contact['faq']['surtitre']) ?>">
      </div>
      <div class="bo-champ">
        <label for="k-qtit">Titre</label>
        <input id="k-qtit" type="text" name="faq_titre" value="<?= e($contact['faq']['titre']) ?>">
      </div>
    </div>
    <div class="bo-champ">
      <label for="k-qitems">Questions et réponses</label>
      <textarea id="k-qitems" name="faq_items" rows="14"><?= e($faq) ?></textarea>
      <p class="bo-aide">
        Une question par bloc, sous la forme <code>Question || Réponse</code>
        (deux barres verticales). Séparez les blocs par une ligne vide.
      </p>
    </div>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="k-qrtit">Titre de relance</label>
        <input id="k-qrtit" type="text" name="faq_relance_titre" value="<?= e($contact['faq']['relance']['titre'] ?? '') ?>">
      </div>
      <div class="bo-champ bo-champ--large">
        <label for="k-qrtex">Texte de relance</label>
        <input id="k-qrtex" type="text" name="faq_relance_texte" value="<?= e($contact['faq']['relance']['texte'] ?? '') ?>">
      </div>
    </div>
  </fieldset>

  <fieldset>
    <legend>Référencement</legend>
    <div class="bo-champ">
      <label for="k-meta">Description affichée dans Google</label>
      <textarea id="k-meta" name="meta_description" rows="2"><?= e($contact['meta']['description'] ?? '') ?></textarea>
    </div>
  </fieldset>

  <button class="bo-btn" type="submit">Enregistrer</button>
</form>
