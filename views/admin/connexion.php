<?php
/**
 * Connexion au back-office.
 * @var string|null $erreur
 * @var int|null $verrou secondes restantes de verrouillage
 */
use App\Core\Csrf;
?>
<h1>Back-office</h1>
<?php if ($verrou !== null): ?>
  <p class="bo-message bo-message--erreur">Trop de tentatives. Réessayez dans <?= e((int) ceil($verrou / 60)) ?> min.</p>
<?php elseif ($erreur): ?>
  <p class="bo-message bo-message--erreur"><?= e($erreur) ?></p>
<?php endif; ?>
<form class="bo-form" method="post" action="<?= url('/admin/connexion') ?>">
  <?= Csrf::champ() ?>
  <div class="bo-champ">
    <label for="l-id">Identifiant</label>
    <input id="l-id" type="text" name="identifiant" required autocomplete="username" autofocus>
  </div>
  <div class="bo-champ">
    <label for="l-mdp">Mot de passe</label>
    <input id="l-mdp" type="password" name="mot_de_passe" required autocomplete="current-password">
  </div>
  <button class="bo-btn" type="submit" <?= $verrou !== null ? 'disabled' : '' ?>>Se connecter</button>
</form>
