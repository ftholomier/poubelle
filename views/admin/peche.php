<?php
/**
 * Pêche : liste des étangs + règles communes.
 * @var array $donnees
 */
use App\Core\Csrf;
?>
<div class="bo-liste">
  <?php foreach ($donnees['items'] as $et): ?>
    <div class="bo-ligne">
      <img class="bo-ligne__vignette" src="<?= asset(str_replace('.jpg', '-mini.jpg', $et['image'])) ?>" alt="">
      <div class="bo-ligne__corps">
        <h2><?= e($et['nom']) ?></h2>
        <p><?= e($et['resume']) ?></p>
      </div>
      <div class="bo-ligne__liens">
        <a href="<?= url('/admin/peche/' . rawurlencode($et['slug'])) ?>">Textes →</a>
        <a href="<?= url('/admin/peche/' . rawurlencode($et['slug']) . '/photos') ?>">Photos →</a>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<form class="bo-form" method="post" action="<?= url('/admin/peche/regles') ?>" style="margin-top:2rem;">
  <?= Csrf::champ() ?>
  <fieldset>
    <legend>Règles communes aux deux étangs</legend>
    <div class="bo-champ">
      <label for="p-regles">Consignes (une ligne vide sépare les paragraphes)</label>
      <textarea id="p-regles" name="regles" rows="10"><?= e(implode("\n\n", $donnees['regles_communes']['paragraphes'])) ?></textarea>
    </div>
    <div class="bo-champ">
      <label for="p-attention">Encadré « Attention »</label>
      <textarea id="p-attention" name="attention" rows="3"><?= e($donnees['regles_communes']['attention']['texte']) ?></textarea>
    </div>
  </fieldset>
  <button class="bo-btn" type="submit">Enregistrer les règles</button>
</form>
