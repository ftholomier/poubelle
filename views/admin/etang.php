<?php
/**
 * Édition d'un étang.
 * @var array $item
 */
use App\Core\Csrf;
?>
<p style="margin-bottom:1.2rem;"><a href="<?= url('/admin/peche') ?>">← Pêche</a>
   · <a href="<?= url('/admin/peche/' . rawurlencode($item['slug']) . '/photos') ?>">Gérer les photos</a>
   · <a href="<?= url('/peche/' . rawurlencode($item['slug'])) ?>" target="_blank" rel="noopener">Voir la page ↗</a></p>

<form class="bo-form" method="post" action="<?= url('/admin/peche/' . rawurlencode($item['slug'])) ?>">
  <?= Csrf::champ() ?>

  <fieldset>
    <legend>Présentation</legend>
    <div class="bo-champ">
      <label for="e-soustitre">Sous-titre</label>
      <input id="e-soustitre" type="text" name="sous_titre" value="<?= e($item['sous_titre']) ?>">
    </div>
    <div class="bo-champ">
      <label for="e-resume">Résumé</label>
      <textarea id="e-resume" name="resume" rows="3"><?= e($item['resume']) ?></textarea>
    </div>
    <div class="bo-champ">
      <label for="e-resa">Texte « Réservation » (paragraphes)</label>
      <textarea id="e-resa" name="reservation" rows="5"><?= e(implode("\n\n", $item['reservation']['paragraphes'])) ?></textarea>
    </div>
  </fieldset>

  <fieldset>
    <legend>Tarifs (pleine saison)</legend>
    <div class="bo-champ">
      <label for="e-ti">Introduction</label>
      <textarea id="e-ti" name="tarif_intro" rows="2"><?= e($item['tarification']['intro']) ?></textarea>
    </div>
    <div class="bo-tarifs">
      <?php foreach ($item['tarification']['tableau']['entetes'] as $i => $entete): ?>
        <div class="bo-champ">
          <label for="e-t-<?= $i ?>"><?= e($entete) ?></label>
          <input id="e-t-<?= $i ?>" type="text" name="tarif_<?= $i ?>" value="<?= e($item['tarification']['tableau']['valeurs'][$i] ?? '') ?>">
        </div>
      <?php endforeach; ?>
    </div>
    <div class="bo-champ">
      <label for="e-ta">Mention « Attention »</label>
      <input id="e-ta" type="text" name="tarif_attention" value="<?= e($item['tarification']['attention']) ?>">
    </div>
  </fieldset>

  <fieldset>
    <legend>Règles spécifiques</legend>
    <?php if (isset($item['droits'])): ?>
      <div class="bo-champ">
        <label for="e-droits">Droits (un par ligne)</label>
        <textarea id="e-droits" name="droits" rows="4"><?= e(implode("\n", $item['droits']['items'])) ?></textarea>
      </div>
    <?php endif; ?>
    <?php if (isset($item['interdictions'])): ?>
      <div class="bo-champ">
        <label for="e-inter">Interdictions (une par ligne)</label>
        <textarea id="e-inter" name="interdictions" rows="4"><?= e(implode("\n", $item['interdictions']['items'])) ?></textarea>
      </div>
    <?php endif; ?>
    <?php if (isset($item['specificites'])): ?>
      <div class="bo-champ">
        <label for="e-spec"><?= e($item['specificites']['titre']) ?> (une par ligne)</label>
        <textarea id="e-spec" name="specificites" rows="3"><?= e(implode("\n", $item['specificites']['items'])) ?></textarea>
      </div>
    <?php endif; ?>
    <?php if (array_key_exists('recapitulatif', $item)): ?>
      <div class="bo-champ">
        <label for="e-recap">Bandeau récapitulatif</label>
        <input id="e-recap" type="text" name="recapitulatif" value="<?= e($item['recapitulatif']) ?>">
      </div>
    <?php endif; ?>
  </fieldset>

  <button class="bo-btn" type="submit">Enregistrer</button>
</form>
