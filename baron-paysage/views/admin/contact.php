<?php
/**
 * Page « Contact » : textes et implantations.
 *
 * Le formulaire a son propre écran, comme il a sa propre page : voir
 * « Demander un devis ».
 *
 * @var array $contact
 * @var string[] $medias
 * @var App\Core\View $view
 */
use App\Core\Csrf;
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

  <?php foreach ((array) ($contact['implantations'] ?? []) as $i => $imp): ?>
  <fieldset>
    <legend>Implantation <?= $i + 1 ?></legend>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="k-inom<?= $i ?>">Ville affichée</label>
        <input id="k-inom<?= $i ?>" type="text" name="imp_nom_<?= $i ?>" value="<?= e($imp['nom'] ?? '') ?>">
      </div>
      <div class="bo-champ">
        <label for="k-irol<?= $i ?>">Sur-titre</label>
        <input id="k-irol<?= $i ?>" type="text" name="imp_role_<?= $i ?>" value="<?= e($imp['role'] ?? '') ?>">
      </div>
    </div>
    <div class="bo-rangee">
      <div class="bo-champ bo-champ--large">
        <label for="k-irue<?= $i ?>">Rue</label>
        <input id="k-irue<?= $i ?>" type="text" name="imp_rue_<?= $i ?>" value="<?= e($imp['rue'] ?? '') ?>">
      </div>
      <div class="bo-champ">
        <label for="k-icp<?= $i ?>">Code postal</label>
        <input id="k-icp<?= $i ?>" type="text" name="imp_cp_<?= $i ?>" value="<?= e($imp['cp'] ?? '') ?>">
      </div>
      <div class="bo-champ">
        <label for="k-ivil<?= $i ?>">Commune</label>
        <input id="k-ivil<?= $i ?>" type="text" name="imp_ville_<?= $i ?>" value="<?= e($imp['ville'] ?? '') ?>">
      </div>
    </div>
    <div class="bo-champ">
      <label for="k-iemb<?= $i ?>">Adresse du plan intégré</label>
      <input id="k-iemb<?= $i ?>" type="url" name="imp_embed_<?= $i ?>" value="<?= e($imp['carte']['embed'] ?? '') ?>">
      <p class="bo-aide">
        Le plan reste masqué tant que le visiteur n’a pas accepté les
        « contenus externes » : rien n’est chargé chez Google avant son accord.
        Videz ce champ pour retirer le plan et ne garder que l’adresse.
      </p>
    </div>
    <div class="bo-champ">
      <label for="k-ilie<?= $i ?>">Lien « Itinéraire »</label>
      <input id="k-ilie<?= $i ?>" type="url" name="imp_lien_<?= $i ?>" value="<?= e($imp['carte']['lien'] ?? '') ?>">
      <p class="bo-aide">Un lien sortant : il ne dépose rien et fonctionne même sans accord.</p>
    </div>
  </fieldset>
  <?php endforeach; ?>

  <fieldset>
    <legend>Formulaire de contact</legend>
    <div class="bo-champ">
      <label for="k-ftit">Titre</label>
      <input id="k-ftit" type="text" name="form_titre" value="<?= e($contact['formulaire']['titre'] ?? '') ?>">
    </div>
    <div class="bo-champ">
      <label for="k-ftex">Texte d'introduction</label>
      <textarea id="k-ftex" name="form_texte" rows="3"><?= e($contact['formulaire']['texte'] ?? '') ?></textarea>
      <p class="bo-aide">L'endroit où rappeler la différence avec la demande de devis.</p>
    </div>
    <div class="bo-champ">
      <label for="k-fmen">Mention sous le formulaire</label>
      <textarea id="k-fmen" name="form_mention" rows="2"><?= e($contact['formulaire']['mention'] ?? '') ?></textarea>
      <p class="bo-aide">Ce que deviennent les coordonnées saisies. Videz le champ pour la retirer.</p>
    </div>
  </fieldset>

  <fieldset>
    <legend>Encadré de relance</legend>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="k-rtit">Titre</label>
        <input id="k-rtit" type="text" name="relance_titre" value="<?= e($contact['relance']['titre'] ?? '') ?>">
      </div>
      <div class="bo-champ">
        <label for="k-rlib">Libellé du bouton</label>
        <input id="k-rlib" type="text" name="relance_libelle" value="<?= e($contact['relance']['lien']['libelle'] ?? '') ?>">
      </div>
    </div>
    <div class="bo-champ">
      <label for="k-rtex">Texte</label>
      <textarea id="k-rtex" name="relance_texte" rows="2"><?= e($contact['relance']['texte'] ?? '') ?></textarea>
      <p class="bo-aide">Videz le titre pour retirer l’encadré de la page.</p>
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
