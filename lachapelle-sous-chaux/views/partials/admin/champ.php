<?php
/**
 * Un champ de formulaire, rendu depuis sa nature.
 *
 * Les natures sont celles de App\Admin\Blocs : ligne, zone, paragraphes,
 * lignes, photo, fichier, lien, icone, case, choix:… Écrire ici la
 * correspondance nature → contrôle évite de la recopier dans chaque écran, et
 * fait qu'un champ ajouté à un bloc s'affiche sans toucher au formulaire.
 *
 * @var string $nature
 * @var string $nom      nom HTML, éventuellement en notation tableau
 * @var mixed  $valeur
 * @var string $libelle
 * @var string $id
 * @var string[] $medias      photos de la médiathèque
 * @var string[] $documents   PDF déposés
 * @var App\Core\View $view
 */
use App\Admin\Blocs;

$medias    = $medias ?? [];
$documents = $documents ?? [];
$aide      = $aide ?? '';
?>

<?php if (str_starts_with($nature, 'choix:')): ?>
  <?php
  $cle = basename(str_replace('][', '/', rtrim($nom, ']')));
  $options = Blocs::CHOIX[$cle] ?? array_combine(
      explode('|', substr($nature, 6)),
      array_map(
          static fn(string $v): string => $v === '' ? '—' : ucfirst(str_replace('-', ' ', $v)),
          explode('|', substr($nature, 6))
      )
  );
  ?>
  <div class="bo-champ">
    <label for="<?= e($id) ?>"><?= e($libelle) ?></label>
    <select id="<?= e($id) ?>" name="<?= e($nom) ?>">
      <?php foreach ($options as $val => $texte): ?>
        <option value="<?= e((string) $val) ?>"<?= (string) $valeur === (string) $val ? ' selected' : '' ?>><?= e($texte) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

<?php elseif ($nature === 'icone'): ?>
  <div class="bo-champ">
    <label for="<?= e($id) ?>"><?= e($libelle) ?></label>
    <select id="<?= e($id) ?>" name="<?= e($nom) ?>">
      <?php foreach (Blocs::ICONES as $cle => $texte): ?>
        <option value="<?= e($cle) ?>"<?= (string) $valeur === $cle ? ' selected' : '' ?>><?= e($texte) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

<?php elseif ($nature === 'fichier'): ?>
  <div class="bo-champ">
    <label for="<?= e($id) ?>"><?= e($libelle) ?></label>
    <select id="<?= e($id) ?>" name="<?= e($nom) ?>">
      <option value="">— aucun —</option>
      <?php foreach ($documents as $doc): ?>
        <option value="<?= e($doc) ?>"<?= (string) $valeur === $doc ? ' selected' : '' ?>><?= e(basename($doc)) ?></option>
      <?php endforeach; ?>
    </select>
    <p class="bo-aide">Les PDF se déposent par FTP dans <code>public/assets/doc/</code>, puis apparaissent dans cette liste.</p>
  </div>

<?php elseif ($nature === 'photo'): ?>
  <div class="bo-champ">
    <label><?= e($libelle) ?></label>
    <?= $view->partial('admin/choix-photo', [
        'medias' => $medias, 'nom' => $nom, 'id' => preg_replace('/\W+/', '', $id) ?? $id,
        'choisie' => (string) $valeur, 'vide' => 'Aucune',
    ]) ?>
  </div>

<?php elseif ($nature === 'lien'): ?>
  <fieldset class="bo-sous-champ">
    <legend><?= e($libelle) ?></legend>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="<?= e($id) ?>-lib">Libellé</label>
        <input id="<?= e($id) ?>-lib" type="text" name="<?= e($nom) ?>[libelle]"
               value="<?= e((string) (is_array($valeur) ? ($valeur['libelle'] ?? '') : '')) ?>">
      </div>
      <div class="bo-champ">
        <label for="<?= e($id) ?>-url">Adresse</label>
        <input id="<?= e($id) ?>-url" type="text" name="<?= e($nom) ?>[url]"
               value="<?= e((string) (is_array($valeur) ? ($valeur['url'] ?? '') : '')) ?>"
               placeholder="/contact ou https://…">
      </div>
    </div>
    <p class="bo-aide">Laissez l’adresse vide pour retirer le lien. Une adresse commençant par <code>http</code> s’ouvre dans un nouvel onglet, et le site l’annonce de lui-même.</p>
  </fieldset>

<?php elseif ($nature === 'case'): ?>
  <div class="bo-champ bo-champ--case">
    <input id="<?= e($id) ?>" type="checkbox" name="<?= e($nom) ?>" value="1"<?= $valeur ? ' checked' : '' ?>>
    <label for="<?= e($id) ?>"><?= e($libelle) ?></label>
  </div>

<?php elseif ($nature === 'riche'): ?>
  <?php
  /* Le champ reste une <textarea>, et c'est délibéré. Sans JavaScript elle
     s'affiche seule, avec le HTML dedans, et l'enregistrement fonctionne : le
     back-office d'une mairie doit rester utilisable depuis un poste ancien ou
     un réseau qui coupe. L'éditeur est monté par-dessus par editeur.js, qui
     recopie sa saisie dans la textarea — celle-ci reste donc la seule chose
     que le serveur lit.

     La barre d'outils est décrite ici plutôt que fabriquée en JavaScript pour
     que les libellés soient traduisibles et que les couleurs proposées viennent
     de TexteRiche, seule à savoir lesquelles la charte autorise. */
  $editeurId = preg_replace('/\W+/', '', $id) ?? $id;
  ?>
  <div class="bo-champ bo-champ--large">
    <label for="<?= e($id) ?>"><?= e($libelle) ?></label>

    <div class="bo-editeur" data-editeur hidden>
      <div class="bo-editeur__barre" role="toolbar" aria-label="<?= e(t('Mise en forme')) ?>">
        <button type="button" class="bo-editeur__btn" data-commande="bold" title="<?= e(t('Gras')) ?>"><strong>G</strong></button>
        <button type="button" class="bo-editeur__btn" data-commande="italic" title="<?= e(t('Italique')) ?>"><em>I</em></button>
        <span class="bo-editeur__separateur" aria-hidden="true"></span>
        <button type="button" class="bo-editeur__btn" data-commande="insertUnorderedList" title="<?= e(t('Liste à puces')) ?>">• —</button>
        <button type="button" class="bo-editeur__btn" data-commande="insertOrderedList" title="<?= e(t('Liste numérotée')) ?>">1. —</button>
        <span class="bo-editeur__separateur" aria-hidden="true"></span>
        <select class="bo-editeur__choix" data-classes="taille" aria-label="<?= e(t('Taille du texte')) ?>">
          <?php foreach (App\Core\TexteRiche::TAILLES as $classe => $nomTaille): ?>
            <option value="<?= e($classe) ?>"><?= e(t($nomTaille)) ?></option>
          <?php endforeach; ?>
        </select>
        <select class="bo-editeur__choix" data-classes="couleur" aria-label="<?= e(t('Couleur du texte')) ?>">
          <?php foreach (App\Core\TexteRiche::COULEURS as $classe => $nomCouleur): ?>
            <option value="<?= e($classe) ?>"><?= e(t($nomCouleur)) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="bo-editeur__separateur" aria-hidden="true"></span>
        <button type="button" class="bo-editeur__btn" data-lien title="<?= e(t('Lien')) ?>"><?= e(t('Lien')) ?></button>
        <button type="button" class="bo-editeur__btn" data-bouton title="<?= e(t('Bouton')) ?>"><?= e(t('Bouton')) ?></button>
        <span class="bo-editeur__separateur" aria-hidden="true"></span>
        <button type="button" class="bo-editeur__btn" data-nettoyer title="<?= e(t('Retirer la mise en forme')) ?>">✕ <?= e(t('mise en forme')) ?></button>
      </div>

      <?php /* aria-multiline et le rôle sont posés par le script : tant qu'il
               n'a pas tourné, cette zone n'est ni éditable ni annoncée. */ ?>
      <div class="bo-editeur__zone" data-editeur-zone id="<?= e($editeurId) ?>-riche"></div>

      <?php /* Le formulaire du bouton est rendu ici, replié : une invite du
               navigateur ne permet pas de demander deux valeurs, et un champ
               posé dans la page se relit avant de valider. */ ?>
      <div class="bo-editeur__bouton" data-editeur-bouton hidden>
        <div class="bo-rangee">
          <div class="bo-champ">
            <label for="<?= e($editeurId) ?>-blib"><?= e(t('Intitulé du bouton')) ?></label>
            <input id="<?= e($editeurId) ?>-blib" type="text" data-bouton-libelle
                   placeholder="<?= e(t('Faire ma démarche')) ?>">
          </div>
          <div class="bo-champ">
            <label for="<?= e($editeurId) ?>-burl"><?= e(t('Adresse du bouton')) ?></label>
            <input id="<?= e($editeurId) ?>-burl" type="text" data-bouton-url
                   placeholder="/contact ou https://…">
          </div>
        </div>
        <p class="bo-aide" data-bouton-erreur hidden></p>
        <div class="bo-editeur__actions">
          <button type="button" class="bo-btn bo-btn--petit" data-bouton-valider><?= e(t('Insérer le bouton')) ?></button>
          <button type="button" class="bo-editeur__btn" data-bouton-annuler><?= e(t('Annuler')) ?></button>
        </div>
      </div>
    </div>

    <textarea id="<?= e($id) ?>" name="<?= e($nom) ?>" rows="9"
              data-editeur-source><?= e(App\Core\TexteRiche::nettoyer($valeur)) ?></textarea>
    <p class="bo-aide" data-editeur-aide>
      Gras, italique, listes, taille et couleurs de la charte, et un bouton.
      Tout le reste — polices, couleurs libres, mise en forme collée depuis un
      traitement de texte — est retiré à l’enregistrement : c’est ce qui garde
      les pages homogènes.
    </p>
  </div>

<?php elseif ($nature === 'paragraphes'): ?>
  <div class="bo-champ bo-champ--large">
    <label for="<?= e($id) ?>"><?= e($libelle) ?></label>
    <textarea id="<?= e($id) ?>" name="<?= e($nom) ?>" rows="7"><?= e(implode("\n\n", (array) $valeur)) ?></textarea>
    <p class="bo-aide">Séparez les paragraphes par une ligne vide.</p>
  </div>

<?php elseif ($nature === 'lignes'): ?>
  <div class="bo-champ bo-champ--large">
    <label for="<?= e($id) ?>"><?= e($libelle) ?></label>
    <textarea id="<?= e($id) ?>" name="<?= e($nom) ?>" rows="5"><?= e(implode("\n", (array) $valeur)) ?></textarea>
    <p class="bo-aide">Une entrée par ligne.</p>
  </div>

<?php elseif ($nature === 'zone'): ?>
  <div class="bo-champ bo-champ--large">
    <label for="<?= e($id) ?>"><?= e($libelle) ?></label>
    <textarea id="<?= e($id) ?>" name="<?= e($nom) ?>" rows="3"><?= e((string) $valeur) ?></textarea>
    <?php if ($aide !== ''): ?><p class="bo-aide"><?= e($aide) ?></p><?php endif; ?>
  </div>

<?php else: ?>
  <div class="bo-champ">
    <label for="<?= e($id) ?>"><?= e($libelle) ?></label>
    <input id="<?= e($id) ?>" type="text" name="<?= e($nom) ?>" value="<?= e((string) $valeur) ?>">
    <?php if ($aide !== ''): ?><p class="bo-aide"><?= e($aide) ?></p><?php endif; ?>
  </div>
<?php endif; ?>
