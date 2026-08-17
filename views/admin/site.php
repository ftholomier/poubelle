<?php
/**
 * Coordonnées, identité et navigation.
 *
 * @var array $site
 */
use App\Core\Csrf;

/** Menu ramené à une ligne « Libellé | /adresse » par entrée. */
$menuTexte = implode("\n", array_map(
    static fn(array $i): string => ($i['libelle'] ?? '') . ' | ' . ($i['url'] ?? '/'),
    $site['menu'] ?? []
));
?>
<form class="bo-form" method="post" action="<?= url('/admin/site') ?>">
  <?= Csrf::champ() ?>

  <fieldset>
    <legend>Identité</legend>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="s-nom">Nom du cabinet</label>
        <input id="s-nom" type="text" name="nom" value="<?= e($site['nom']) ?>">
      </div>
      <div class="bo-champ">
        <label for="s-baseline">Baseline</label>
        <input id="s-baseline" type="text" name="baseline" value="<?= e($site['baseline']) ?>">
      </div>
    </div>
    <div class="bo-champ">
      <label for="s-accroche">Description générale</label>
      <textarea id="s-accroche" name="accroche" rows="3"><?= e($site['accroche']) ?></textarea>
      <p class="bo-aide">Reprise dans les données structurées lues par Google. Deux à trois phrases.</p>
    </div>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="s-annee">Année de création</label>
        <input id="s-annee" type="text" name="annee" value="<?= e($site['fondation']['annee'] ?? '') ?>">
      </div>
      <div class="bo-champ">
        <label for="s-fondatrice">Fondatrice</label>
        <input id="s-fondatrice" type="text" name="fondatrice" value="<?= e($site['fondation']['fondatrice'] ?? '') ?>">
      </div>
      <div class="bo-champ">
        <label for="s-qualite">Qualité</label>
        <input id="s-qualite" type="text" name="qualite" value="<?= e($site['fondation']['qualite'] ?? '') ?>">
      </div>
    </div>
  </fieldset>

  <fieldset>
    <legend>Contact</legend>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="s-tel">Téléphone</label>
        <input id="s-tel" type="text" name="telephone" value="<?= e($site['contact']['telephone']) ?>">
      </div>
      <div class="bo-champ">
        <label for="s-email">Adresse e-mail publique</label>
        <input id="s-email" type="email" name="email" value="<?= e($site['contact']['email']) ?>">
        <p class="bo-aide">Sert aussi de destinataire au formulaire, si aucun n’est réglé dans Paramètres.</p>
      </div>
    </div>
    <div class="bo-champ">
      <label for="s-horaires">Horaires</label>
      <input id="s-horaires" type="text" name="horaires" value="<?= e($site['contact']['horaires'] ?? '') ?>">
      <p class="bo-aide">Écrit en toutes lettres : « Du lundi au vendredi, de 8h30 à 18h00 ».</p>
    </div>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="s-rue">Adresse</label>
        <input id="s-rue" type="text" name="rue" value="<?= e($site['adresse']['rue']) ?>">
      </div>
      <div class="bo-champ">
        <label for="s-cp">Code postal</label>
        <input id="s-cp" type="text" name="cp" value="<?= e($site['adresse']['cp']) ?>">
      </div>
      <div class="bo-champ">
        <label for="s-ville">Ville</label>
        <input id="s-ville" type="text" name="ville" value="<?= e($site['adresse']['ville']) ?>">
      </div>
    </div>
  </fieldset>

  <fieldset>
    <legend>Bouton d’appel à l’action</legend>
    <p class="bo-aide">Affiché dans l’en-tête, le menu et le bas de chaque page.</p>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="s-cta-lib">Libellé</label>
        <input id="s-cta-lib" type="text" name="cta_libelle"
               value="<?= e($site['reservation']['principal']['libelle'] ?? '') ?>">
      </div>
      <div class="bo-champ">
        <label for="s-cta-url">Adresse</label>
        <input id="s-cta-url" type="text" name="cta_url"
               value="<?= e($site['reservation']['principal']['url'] ?? '/contact') ?>">
      </div>
    </div>
  </fieldset>

  <fieldset>
    <legend>Menu de navigation</legend>
    <div class="bo-champ">
      <label for="s-menu">Rubriques</label>
      <textarea id="s-menu" name="menu" rows="7"><?= e($menuTexte) ?></textarea>
      <p class="bo-aide">
        Une rubrique par ligne, sous la forme <code>Libellé | /adresse</code>.
        L’ordre de la liste est celui du menu. La rubrique qui pointe vers la page
        des services reçoit automatiquement le sous-menu de vos services publiés :
        rien à saisir ici quand vous en ajoutez un.
      </p>
    </div>
    <p class="bo-aide">
      La disposition du menu (burger ou barre horizontale) se règle dans
      <a href="<?= url('/admin/apparence') ?>">Apparence</a>.
    </p>
  </fieldset>

  <fieldset>
    <legend>Pied de page</legend>
    <div class="bo-champ">
      <label for="s-seo">Texte de présentation</label>
      <textarea id="s-seo" name="pied_seo" rows="3"><?= e($site['pied']['seo']) ?></textarea>
    </div>
    <div class="bo-champ">
      <label for="s-proche">Zone d’intervention</label>
      <textarea id="s-proche" name="pied_proche" rows="2"><?= e($site['pied']['proche_de']) ?></textarea>
    </div>
    <div class="bo-champ">
      <label for="s-copy">Mention de copyright</label>
      <input id="s-copy" type="text" name="pied_copyright" value="<?= e($site['pied']['copyright']) ?>">
    </div>
  </fieldset>

  <button class="bo-btn" type="submit">Enregistrer</button>
</form>
