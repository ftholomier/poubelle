<?php
/**
 * Édition d'un hébergement.
 * @var array $item
 */
use App\Core\Csrf;
?>
<p style="margin-bottom:1.2rem;"><a href="<?= url('/admin/hebergements') ?>">← Tous les hébergements</a>
   · <a href="<?= url('/admin/hebergements/' . rawurlencode($item['slug']) . '/photos') ?>">Gérer les photos</a>
   · <a href="<?= url('/hebergements/' . rawurlencode($item['slug'])) ?>" target="_blank" rel="noopener">Voir la page ↗</a></p>

<form class="bo-form" method="post" action="<?= url('/admin/hebergements/' . rawurlencode($item['slug'])) ?>">
  <?= Csrf::champ() ?>

  <fieldset>
    <legend>Général</legend>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="h-nom">Nom</label>
        <input id="h-nom" type="text" name="nom" value="<?= e($item['nom']) ?>">
      </div>
      <div class="bo-champ">
        <label for="h-prix">Prix « à partir de » (€)</label>
        <input id="h-prix" type="number" name="prix" min="0" value="<?= e($item['prix_a_partir_de']) ?>">
      </div>
      <div class="bo-champ">
        <label for="h-cap">Capacité (personnes)</label>
        <input id="h-cap" type="number" name="capacite" min="1" value="<?= e($item['capacite']) ?>">
      </div>
    </div>
    <div class="bo-champ">
      <label for="h-soustitre">Sous-titre</label>
      <input id="h-soustitre" type="text" name="sous_titre" value="<?= e($item['sous_titre']) ?>">
    </div>
    <div class="bo-champ">
      <label for="h-resume">Résumé (listes et page hébergements)</label>
      <textarea id="h-resume" name="resume" rows="4"><?= e($item['resume']) ?></textarea>
    </div>
  </fieldset>

  <fieldset>
    <legend>Sections de la page</legend>
    <?php foreach ($item['sections'] as $s => $section): ?>
      <div class="bo-champ">
        <label for="h-st-<?= $s ?>">Titre de section <?= $s + 1 ?></label>
        <input id="h-st-<?= $s ?>" type="text" name="section_titre_<?= $s ?>" value="<?= e($section['titre']) ?>">
      </div>
      <div class="bo-champ">
        <label for="h-sx-<?= $s ?>">Texte (une ligne vide sépare les paragraphes)</label>
        <textarea id="h-sx-<?= $s ?>" name="section_texte_<?= $s ?>" rows="5"><?= e(implode("\n\n", $section['paragraphes'])) ?></textarea>
      </div>
    <?php endforeach; ?>
  </fieldset>

  <fieldset>
    <legend>Prestations</legend>
    <?php foreach ($item['prestations']['groupes'] as $g => $groupe): ?>
      <div class="bo-champ">
        <label for="h-p-<?= $g ?>"><?= e($groupe['titre'] ?: 'Équipements') ?> (un par ligne)</label>
        <textarea id="h-p-<?= $g ?>" name="prestations_<?= $g ?>" rows="6"><?= e(implode("\n", $groupe['items'])) ?></textarea>
      </div>
    <?php endforeach; ?>
    <div class="bo-champ">
      <label for="h-pc">Compléments (paragraphes)</label>
      <textarea id="h-pc" name="prestations_complement" rows="4"><?= e(implode("\n\n", $item['prestations']['complement'])) ?></textarea>
    </div>
    <div class="bo-champ">
      <label for="h-sup">Prestations en supplément (une par ligne)</label>
      <textarea id="h-sup" name="supplements" rows="3"><?= e(implode("\n", $item['supplements']['items'])) ?></textarea>
    </div>
    <div class="bo-champ">
      <label for="h-act">Activités (une par ligne)</label>
      <textarea id="h-act" name="activites" rows="5"><?= e(implode("\n", $item['activites']['items'])) ?></textarea>
    </div>
  </fieldset>

  <fieldset>
    <legend>Tarification &amp; référencement</legend>
    <div class="bo-champ">
      <label for="h-tt">Texte tarif</label>
      <input id="h-tt" type="text" name="tarif_texte" value="<?= e($item['tarification']['texte']) ?>">
    </div>
    <div class="bo-champ">
      <label for="h-tn">Note tarif</label>
      <input id="h-tn" type="text" name="tarif_note" value="<?= e($item['tarification']['note']) ?>">
    </div>
    <div class="bo-champ">
      <label for="h-meta">Description (moteurs de recherche)</label>
      <textarea id="h-meta" name="meta" rows="2"><?= e($item['meta']['description']) ?></textarea>
    </div>
  </fieldset>

  <button class="bo-btn" type="submit">Enregistrer</button>
</form>
