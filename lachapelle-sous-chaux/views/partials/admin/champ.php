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
