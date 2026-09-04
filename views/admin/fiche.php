<?php
/**
 * Édition d'une fiche de collection : démarche ou actualité.
 *
 * Les deux collections partagent le corps — un bandeau, un résumé, des blocs —
 * et se distinguent par leur en-tête : une démarche porte des repères pratiques
 * (guichet, délai, coût, pièces), une actualité une date.
 *
 * @var string $collection
 * @var array  $reglages
 * @var array  $item
 * @var string[] $medias
 * @var string[] $documents
 * @var App\Core\View $view
 */
use App\Admin\Blocs;
use App\Core\Csrf;

$cleTitre = $reglages['titre'];
$estDemarche = $collection === 'demarches';
$familles = [
    'etat-civil' => 'État civil et identité',
    'elections'  => 'Élections',
    'urbanisme'  => 'Urbanisme et travaux',
];
$liens = (array) ($item['liens'] ?? []);
?>
<p class="bo-fil">
  <a href="<?= url('/admin/' . $collection) ?>">← Toutes les <?= e(mb_strtolower($reglages['nom'])) ?></a>
  <a href="<?= route($reglages['cleSeo'], $item['slug']) ?>" target="_blank" rel="noopener">Voir la page ↗</a>
</p>

<form class="bo-form" method="post" action="<?= url('/admin/' . $collection . '/' . $item['slug']) ?>" data-form-page>
  <?= Csrf::champ() ?>

  <fieldset>
    <legend>Présentation</legend>
    <div class="bo-champ bo-champ--large">
      <label for="f-int">Titre</label>
      <input id="f-int" type="text" name="intitule" value="<?= e((string) ($item[$cleTitre] ?? '')) ?>">
    </div>
    <div class="bo-champ bo-champ--large">
      <label for="f-res">Résumé</label>
      <textarea id="f-res" name="resume" rows="3"><?= e($item['resume'] ?? '') ?></textarea>
      <p class="bo-aide">Affiché sur la carte de la liste et en tête de la fiche. Une à deux phrases.</p>
    </div>

    <?php if ($estDemarche): ?>
      <div class="bo-rangee">
        <div class="bo-champ">
          <label for="f-fam">Famille</label>
          <select id="f-fam" name="famille">
            <?php foreach ($familles as $cle => $libelle): ?>
              <option value="<?= e($cle) ?>"<?= ($item['famille'] ?? '') === $cle ? ' selected' : '' ?>><?= e($libelle) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="bo-champ">
          <label for="f-ico">Pictogramme</label>
          <select id="f-ico" name="icone">
            <?php foreach (Blocs::ICONES as $cle => $libelle): ?>
              <option value="<?= e($cle) ?>"<?= ($item['icone'] ?? '') === $cle ? ' selected' : '' ?>><?= e($libelle) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    <?php else: ?>
      <div class="bo-champ">
        <label for="f-date">Date de publication</label>
        <input id="f-date" type="date" name="date" value="<?= e((string) ($item['date'] ?? '')) ?>">
        <p class="bo-aide">C’est elle qui décide de l’ordre : la plus récente passe en tête.</p>
      </div>
    <?php endif; ?>

    <div class="bo-champ">
      <label>Photo</label>
      <?= $view->partial('admin/choix-photo', [
          'medias' => $medias, 'nom' => 'image', 'id' => 'fimg',
          'choisie' => $item['image'] ?? '', 'vide' => 'Aucune',
      ]) ?>
    </div>
    <div class="bo-champ bo-champ--large">
      <label for="f-alt">Description de la photo</label>
      <input id="f-alt" type="text" name="image_alt" value="<?= e($item['image_alt'] ?? '') ?>">
      <p class="bo-aide">Décrivez la scène, pas le fichier. C’est ce que lit une personne aveugle, et ce qui s’affiche si la photo ne charge pas.</p>
    </div>
    <div class="bo-champ bo-champ--large">
      <label for="f-desc">Description pour les moteurs</label>
      <textarea id="f-desc" name="meta_description" rows="2"><?= e($item['meta']['description'] ?? '') ?></textarea>
    </div>
  </fieldset>

  <?php if ($estDemarche): ?>
    <fieldset>
      <legend>Repères pratiques</legend>
      <p class="bo-aide bo-aide-bloc">Affichés dans l’encart « En bref », à droite de la fiche. Un champ vide n’apparaît pas.</p>
      <div class="bo-rangee">
        <div class="bo-champ">
          <label for="f-gui">Où s’adresser</label>
          <input id="f-gui" type="text" name="guichet" value="<?= e($item['guichet'] ?? '') ?>">
        </div>
        <div class="bo-champ">
          <label for="f-del">Délai</label>
          <input id="f-del" type="text" name="delai" value="<?= e($item['delai'] ?? '') ?>">
        </div>
      </div>
      <div class="bo-rangee">
        <div class="bo-champ">
          <label for="f-cou">Coût</label>
          <input id="f-cou" type="text" name="cout" value="<?= e($item['cout'] ?? '') ?>">
        </div>
        <div class="bo-champ">
          <label for="f-val">Validité</label>
          <input id="f-val" type="text" name="validite" value="<?= e($item['validite'] ?? '') ?>">
        </div>
      </div>
      <div class="bo-champ bo-champ--large">
        <label for="f-pie">Pièces à fournir</label>
        <textarea id="f-pie" name="pieces" rows="6"><?= e(implode("\n", (array) ($item['pieces'] ?? []))) ?></textarea>
        <p class="bo-aide">Une pièce par ligne. C’est l’encart le plus consulté de la fiche.</p>
      </div>
    </fieldset>

    <fieldset>
      <legend>Faire en ligne</legend>
      <p class="bo-aide bo-aide-bloc">Les téléservices officiels, affichés dans l’encart bleu. Laissez l’adresse vide pour retirer une ligne.</p>
      <?php for ($i = 0; $i < count($liens) + 2; $i++): ?>
        <?php $lien = $liens[$i] ?? []; ?>
        <div class="bo-rangee">
          <div class="bo-champ">
            <label for="f-lib-<?= $i ?>">Libellé</label>
            <input id="f-lib-<?= $i ?>" type="text" name="liens[<?= $i ?>][libelle]" value="<?= e($lien['libelle'] ?? '') ?>">
          </div>
          <div class="bo-champ">
            <label for="f-url-<?= $i ?>">Adresse</label>
            <input id="f-url-<?= $i ?>" type="text" name="liens[<?= $i ?>][url]" value="<?= e($lien['url'] ?? '') ?>">
          </div>
        </div>
      <?php endfor; ?>
    </fieldset>
  <?php endif; ?>

  <h2 class="bo-sous-titre">Le corps de la fiche</h2>
  <?= $view->partial('admin/blocs', [
      'sections'  => $item['sections'] ?? [],
      'medias'    => $medias,
      'documents' => $documents,
  ]) ?>

  <div class="bo-barre-actions">
    <button class="bo-btn" type="submit">Enregistrer la fiche</button>
    <a class="bo-btn bo-btn--fantome" href="<?= url('/admin/avance?nom=' . $collection) ?>">Éditeur avancé</a>
  </div>
</form>

<?= $view->partial('admin/ajout-bloc', ['action' => url('/admin/' . $collection . '/' . $item['slug'] . '/bloc')]) ?>
