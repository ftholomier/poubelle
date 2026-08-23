<?php
/**
 * Fiche d'un service.
 *
 * @var array $item
 * @var string[] $medias
 * @var App\Core\View $view
 */
use App\Core\Csrf;

$icones = [
    'pergola-bioclimatique' => 'Pergola bioclimatique (lames)',
    'pergola-toile'         => 'Pergola à toile (store)',
    'toiture-fixe'          => 'Toiture fixe (panneau)',
    'carport'               => 'Carport (abri voiture)',
    'fermetures'            => 'Fermetures et options',
];
$sections = $item['sections'] ?? [];
?>
<p class="bo-fil">
  <a href="<?= url('/admin/services') ?>">← Tous les services</a>
  <a href="<?= route('nos-services', $item['slug']) ?>" target="_blank" rel="noopener">Voir la page ↗</a>
</p>

<form class="bo-form" method="post" action="<?= url('/admin/services/' . $item['slug']) ?>" enctype="multipart/form-data">
  <?= Csrf::champ() ?>

  <fieldset>
    <legend>Présentation</legend>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="s-nom">Nom du service</label>
        <input id="s-nom" type="text" name="nom" value="<?= e($item['nom']) ?>">
      </div>
      <div class="bo-champ">
        <label for="s-icone">Icône</label>
        <select id="s-icone" name="icone">
          <?php foreach ($icones as $cle => $libelle): ?>
            <option value="<?= e($cle) ?>"<?= ($item['icone'] ?? '') === $cle ? ' selected' : '' ?>>
              <?= e($libelle) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="bo-champ">
      <label for="s-res">Résumé</label>
      <textarea id="s-res" name="resume" rows="3"><?= e($item['resume'] ?? '') ?></textarea>
      <p class="bo-aide">Affiché sur la carte du service, en page d’accueil et dans la liste. Une à deux phrases.</p>
    </div>
    <div class="bo-champ">
      <label>Photo</label>
      <?= $view->partial('admin/choix-photo', [
          'medias' => $medias, 'nom' => 'image', 'id' => 'simg',
          'choisie' => $item['image'] ?? '', 'vide' => 'Aucune',
      ]) ?>
    </div>
  </fieldset>

  <fieldset>
    <legend>Bandeau de la page</legend>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="s-hsur">Sur-titre</label>
        <input id="s-hsur" type="text" name="hero_surtitre" value="<?= e($item['hero']['surtitre'] ?? '') ?>">
      </div>
      <div class="bo-champ">
        <label for="s-htit">Titre principal (H1)</label>
        <input id="s-htit" type="text" name="hero_titre" value="<?= e($item['hero']['titre'] ?? '') ?>">
      </div>
    </div>
    <p class="bo-aide">La photo choisie ci-dessus sert aussi de fond au bandeau.</p>
  </fieldset>

  <?php for ($i = 0; $i < 3; $i++):
      $sec = $sections[$i] ?? ['titre' => '', 'paragraphes' => [], 'liste' => []]; ?>
    <fieldset>
      <legend>Section <?= $i + 1 ?><?= $i > 0 ? ' (facultative)' : '' ?></legend>
      <div class="bo-champ">
        <label for="s-st<?= $i ?>">Titre</label>
        <input id="s-st<?= $i ?>" type="text" name="section_titre_<?= $i ?>" value="<?= e($sec['titre'] ?? '') ?>">
      </div>
      <div class="bo-champ">
        <label for="s-sx<?= $i ?>">Paragraphes</label>
        <textarea id="s-sx<?= $i ?>" name="section_texte_<?= $i ?>" rows="5"><?= e(implode("\n\n", $sec['paragraphes'] ?? [])) ?></textarea>
        <p class="bo-aide">Une ligne vide sépare deux paragraphes.</p>
      </div>
      <div class="bo-champ">
        <label for="s-sl<?= $i ?>">Liste à puces</label>
        <textarea id="s-sl<?= $i ?>" name="section_liste_<?= $i ?>" rows="5"><?= e(implode("\n", $sec['liste'] ?? [])) ?></textarea>
        <p class="bo-aide">Une entrée par ligne. Laissez vide si la section n’en comporte pas.</p>
      </div>
    </fieldset>
  <?php endfor; ?>

  <fieldset>
    <legend>Référencement</legend>
    <div class="bo-champ">
      <label for="s-meta">Description affichée dans Google</label>
      <textarea id="s-meta" name="meta_description" rows="2"><?= e($item['meta']['description'] ?? '') ?></textarea>
    </div>
    <p class="bo-aide">
      L’adresse de la page (<code>/<?= e($item['slug']) ?></code>) se modifie dans
      <a href="<?= url('/admin/referencement') ?>">Référencement</a>, qui met en place
      la redirection automatiquement.
    </p>
  </fieldset>

  <button class="bo-btn" type="submit">Enregistrer</button>
</form>
