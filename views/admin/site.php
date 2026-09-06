<?php
/**
 * Coordonnées, identité et navigation.
 *
 * @var array $site
 */
use App\Core\Csrf;

/**
 * Le menu, en texte : une entrée par ligne, « Libellé | /adresse ». Une
 * sous-entrée est décalée de deux espaces.
 *
 * Un formulaire à champs répétés serait plus rigoureux, mais un menu se
 * réordonne dix fois de suite quand on le compose : le texte se réordonne au
 * copier-coller, un formulaire demande dix allers-retours.
 */
$lignes = [];
foreach ($site['menu'] ?? [] as $entree) {
    $lignes[] = ($entree['libelle'] ?? '') . ' | ' . ($entree['url'] ?? '/');
    foreach ($entree['sous_menu'] ?? [] as $sous) {
        $lignes[] = '  ' . ($sous['libelle'] ?? '') . ' | ' . ($sous['url'] ?? '/');
    }
}
$menuTexte = implode("\n", $lignes);
?>
<form class="bo-form" method="post" action="<?= url('/admin/site') ?>">
  <?= Csrf::champ() ?>

  <fieldset>
    <legend>Identité</legend>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="s-nom">Nom de la commune</label>
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
    <?php /* « Année de création » venait du socle commercial : une commune ne
             se crée pas, et la première mention écrite du village est déjà un
             des chiffres de la page d'accueil. Le champ n'était lu par aucun
             gabarit — le retirer ne fait donc rien disparaître de visible. */ ?>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="s-maire">Maire</label>
        <input id="s-maire" type="text" name="maire"
               value="<?= e($site['fondation']['maire'] ?? $site['fondation']['fondatrice'] ?? '') ?>">
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
    <legend>Bande d’appel, en bas de chaque page</legend>
    <p class="bo-aide">Le titre, la phrase et le bouton affichés sous chaque page du site.</p>
    <div class="bo-champ bo-champ--large">
      <label for="s-appel-titre">Titre de la bande</label>
      <input id="s-appel-titre" type="text" name="appel_titre"
             value="<?= e($site['appel']['titre'] ?? '') ?>"
             placeholder="Le secrétariat de mairie vous répond">
    </div>
    <div class="bo-champ bo-champ--large">
      <label for="s-appel-texte">Phrase de la bande</label>
      <textarea id="s-appel-texte" name="appel_texte" rows="3"><?= e($site['appel']['texte'] ?? '') ?></textarea>
      <p class="bo-aide">
        Deux ou trois lignes qui disent pour quoi écrire au secrétariat. Ce texte
        était écrit dans le code et parlait de « la salle des fêtes » quand le
        reste du site dit « la salle Camille » ; il se corrige désormais ici.
      </p>
    </div>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="s-cta-lib">Libellé</label>
        <input id="s-cta-lib" type="text" name="cta_libelle"
               value="<?= e($site['appel']['principal']['libelle'] ?? $site['reservation']['principal']['libelle'] ?? '') ?>">
      </div>
      <div class="bo-champ">
        <label for="s-cta-url">Adresse</label>
        <input id="s-cta-url" type="text" name="cta_url"
               value="<?= e($site['appel']['principal']['url'] ?? $site['reservation']['principal']['url'] ?? '/contact') ?>">
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
      <label for="s-proche">Communes voisines et intercommunalité</label>
      <textarea id="s-proche" name="pied_proche" rows="2"><?= e($site['pied']['proche_de']) ?></textarea>
    </div>
    <div class="bo-champ bo-champ--large">
      <label for="s-accroche">Phrase d’accroche</label>
      <input id="s-accroche" type="text" name="pied_accroche"
             value="<?= e($site['pied']['accroche'] ?? '') ?>"
             placeholder="Le secrétariat vous reçoit sans rendez-vous aux heures d’ouverture.">
      <p class="bo-aide">
        Sous le logo, au bas de chaque page. <strong>N’y annoncez que ce que la
        commune fait vraiment</strong> : la phrase livrée par le socle promettait une
        permanence des élus le samedi matin, et un administré s’y déplace.
      </p>
    </div>
    <div class="bo-champ">
      <label for="s-copy">Mention de copyright</label>
      <input id="s-copy" type="text" name="pied_copyright" value="<?= e($site['pied']['copyright']) ?>">
      <p class="bo-aide">
        Écrivez <code>[year]</code> à la place de l'année : elle sera remplacée par
        l'année en cours à chaque affichage — <?= e(date('Y')) ?> aujourd'hui. Inutile
        d'y revenir au 1<sup>er</sup> janvier.
        <?php if (!str_contains(mb_strtolower($site['pied']['copyright']), '[year]')): ?>
          <strong>L'année est actuellement écrite en dur dans ce champ.</strong>
        <?php endif; ?>
      </p>
    </div>
  </fieldset>

  <button class="bo-btn" type="submit">Enregistrer</button>
</form>
