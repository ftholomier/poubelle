<?php
/**
 * Édition de la page d'accueil.
 * @var array $accueil
 */
use App\Core\Csrf;
?>
<form class="bo-form" method="post" action="<?= url('/admin/accueil') ?>">
  <?= Csrf::champ() ?>

  <fieldset>
    <legend>Bandeau principal</legend>
    <div class="bo-champ">
      <label for="a-surtitre">Surtitre</label>
      <input id="a-surtitre" type="text" name="hero_surtitre" value="<?= e($accueil['hero']['surtitre']) ?>">
    </div>
    <div class="bo-champ">
      <label for="a-titre">Titre</label>
      <input id="a-titre" type="text" name="hero_titre" value="<?= e($accueil['hero']['titre']) ?>">
    </div>
    <div class="bo-champ">
      <label for="a-texte">Texte</label>
      <textarea id="a-texte" name="hero_texte" rows="4"><?= e($accueil['hero']['texte']) ?></textarea>
    </div>
  </fieldset>

  <fieldset>
    <legend>Les trois piliers</legend>
    <?php foreach ($accueil['piliers']['items'] as $i => $pilier): ?>
      <div class="bo-champ">
        <label for="a-pilier-<?= $i ?>">Pilier <?= $i + 1 ?></label>
        <textarea id="a-pilier-<?= $i ?>" name="pilier_<?= $i ?>" rows="3"><?= e($pilier['texte']) ?></textarea>
      </div>
    <?php endforeach; ?>
    <div class="bo-champ">
      <label for="a-cta">Phrase d'appel</label>
      <input id="a-cta" type="text" name="piliers_cta" value="<?= e($accueil['piliers']['cta']) ?>">
    </div>
  </fieldset>

  <fieldset>
    <legend>Citation</legend>
    <div class="bo-champ">
      <label for="a-ctitre">Citation</label>
      <textarea id="a-ctitre" name="citation_titre" rows="3"><?= e($accueil['citation']['titre']) ?></textarea>
    </div>
    <div class="bo-champ">
      <label for="a-ctexte">Texte sous la citation</label>
      <textarea id="a-ctexte" name="citation_texte" rows="2"><?= e($accueil['citation']['texte']) ?></textarea>
    </div>
  </fieldset>

  <fieldset>
    <legend>Référencement</legend>
    <div class="bo-champ">
      <label for="a-meta">Description (moteurs de recherche)</label>
      <textarea id="a-meta" name="meta" rows="2"><?= e($accueil['meta']['description']) ?></textarea>
    </div>
  </fieldset>

  <button class="bo-btn" type="submit">Enregistrer</button>
</form>
