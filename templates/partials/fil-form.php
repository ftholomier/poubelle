<?php
/**
 * Formulaire du Fil jaune : deux joueurs à relier (liste de choix native, sans script).
 * Variables : $players (id, name, years, slug), $a (nom déjà choisi), $b, $id (préfixe des identifiants)
 */
$id = $id ?? 'fj';
?>
<form class="fjform" method="get" action="<?= e(url('/interactif/fil-jaune/')) ?>" data-fj-form>
  <div class="fjform__f">
    <label for="<?= e($id) ?>-a"><?= e(t('Premier joueur')) ?></label>
    <input id="<?= e($id) ?>-a" name="a" list="<?= e($id) ?>-list" value="<?= e($a ?? '') ?>" placeholder="<?= e(t('ex. Bernard Genghini')) ?>" autocomplete="off" required>
  </div>
  <button type="button" class="fjform__swap" data-fj-swap aria-label="<?= e(t('Inverser les deux joueurs')) ?>" title="<?= e(t('Inverser les deux joueurs')) ?>">⇄</button>
  <div class="fjform__f">
    <label for="<?= e($id) ?>-b"><?= e(t('Second joueur')) ?></label>
    <input id="<?= e($id) ?>-b" name="b" list="<?= e($id) ?>-list" value="<?= e($b ?? '') ?>" placeholder="<?= e(t('ex. Mathieu Peybernes')) ?>" autocomplete="off">
  </div>
  <div class="fjform__go">
    <button type="submit" class="qbtn qbtn--sm"><?= e(t('Relier')) ?></button>
    <button type="button" class="linkbtn" data-fj-random><?= e(t('Au hasard')) ?></button>
  </div>
  <datalist id="<?= e($id) ?>-list">
    <?php foreach ($players as $p): ?><option value="<?= e($p['name']) ?>"<?= $p['years'] !== '' ? ' label="' . e($p['years']) . '"' : '' ?> data-slug="<?= e($p['slug']) ?>"></option><?php endforeach; ?>
  </datalist>
</form>
