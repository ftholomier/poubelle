<?php
/**
 * Coordonnées & réservation.
 * @var array $site
 */
use App\Core\Csrf;
?>
<form class="bo-form" method="post" action="<?= url('/admin/site') ?>">
  <?= Csrf::champ() ?>

  <fieldset>
    <legend>Contact</legend>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="s-tel">Téléphone</label>
        <input id="s-tel" type="text" name="telephone" value="<?= e($site['contact']['telephone']) ?>">
      </div>
      <div class="bo-champ">
        <label for="s-email">E-mail</label>
        <input id="s-email" type="email" name="email" value="<?= e($site['contact']['email']) ?>">
      </div>
    </div>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="s-rue">Adresse</label>
        <input id="s-rue" type="text" name="rue" value="<?= e($site['adresse']['rue']) ?>">
      </div>
      <div class="bo-champ">
        <label for="s-cp">Code postal</label>
        <input id="s-cp" type="text" name="cp" value="<?= e($site['adresse']['cp']) ?>">
      </div>
      <div class="bo-champ">
        <label for="s-ville">Ville</label>
        <input id="s-ville" type="text" name="ville" value="<?= e($site['adresse']['ville']) ?>">
      </div>
    </div>
  </fieldset>

  <fieldset>
    <legend>Liens de réservation (Reservit)</legend>
    <div class="bo-champ">
      <label for="s-rh">Réservation hébergement</label>
      <input id="s-rh" type="url" name="resa_hebergement" value="<?= e($site['reservation']['hebergement']['url']) ?>">
    </div>
    <div class="bo-champ">
      <label for="s-re">Réservation étang</label>
      <input id="s-re" type="url" name="resa_etang" value="<?= e($site['reservation']['etang']['url']) ?>">
    </div>
  </fieldset>

  <fieldset>
    <legend>Pied de page</legend>
    <div class="bo-champ">
      <label for="s-seo">Texte de présentation</label>
      <textarea id="s-seo" name="pied_seo" rows="3"><?= e($site['pied']['seo']) ?></textarea>
    </div>
    <div class="bo-champ">
      <label for="s-proche">Villes à proximité</label>
      <textarea id="s-proche" name="pied_proche" rows="2"><?= e($site['pied']['proche_de']) ?></textarea>
    </div>
  </fieldset>

  <button class="bo-btn" type="submit">Enregistrer</button>
</form>
