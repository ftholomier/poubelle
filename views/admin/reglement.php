<?php
/**
 * Édition du règlement général.
 * @var array $reglement
 */
use App\Core\Csrf;
?>
<form class="bo-form" method="post" action="<?= url('/admin/reglement') ?>">
  <?= Csrf::champ() ?>
  <fieldset>
    <legend>Règles de vie et consignes</legend>
    <div class="bo-champ">
      <label for="r-regles">Règles (une par ligne)</label>
      <textarea id="r-regles" name="regles" rows="18"><?= e(implode("\n", $reglement['regles'])) ?></textarea>
    </div>
    <div class="bo-champ">
      <label for="r-conclusion">Conclusion (paragraphes)</label>
      <textarea id="r-conclusion" name="conclusion" rows="4"><?= e(implode("\n\n", $reglement['conclusion'])) ?></textarea>
    </div>
  </fieldset>
  <button class="bo-btn" type="submit">Enregistrer</button>
</form>
