<?php
/**
 * Première configuration : création du compte administrateur.
 * @var array $erreurs
 * @var array $valeurs
 */
use App\Core\Csrf;
?>
<h1>Première configuration</h1>
<p class="intro">Aucun compte n'existe encore. Créez l'accès administrateur du back-office.</p>
<form class="bo-form" method="post" action="<?= url('/admin/configuration') ?>">
  <?= Csrf::champ() ?>
  <div class="bo-champ">
    <label for="c-id">Identifiant</label>
    <input id="c-id" type="text" name="identifiant" required minlength="3" autocomplete="username"
           value="<?= e($valeurs['identifiant'] ?? '') ?>">
    <?php if (!empty($erreurs['identifiant'])): ?><span class="erreur"><?= e($erreurs['identifiant']) ?></span><?php endif; ?>
  </div>
  <div class="bo-champ">
    <label for="c-mdp">Mot de passe</label>
    <input id="c-mdp" type="password" name="mot_de_passe" required minlength="12" autocomplete="new-password">
    <span class="aide">12 caractères minimum.</span>
    <?php if (!empty($erreurs['mot_de_passe'])): ?><span class="erreur"><?= e($erreurs['mot_de_passe']) ?></span><?php endif; ?>
  </div>
  <div class="bo-champ">
    <label for="c-conf">Confirmation</label>
    <input id="c-conf" type="password" name="confirmation" required autocomplete="new-password">
    <?php if (!empty($erreurs['confirmation'])): ?><span class="erreur"><?= e($erreurs['confirmation']) ?></span><?php endif; ?>
  </div>
  <button class="bo-btn" type="submit">Créer le compte</button>
</form>
