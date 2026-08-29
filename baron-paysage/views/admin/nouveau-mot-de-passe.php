<?php
/**
 * Choix d'un nouveau mot de passe, une fois le lien de récupération suivi.
 *
 * Le jeton voyage en champ caché : il a déjà été validé pour afficher cette
 * page, il l'est de nouveau à l'envoi, car rien n'empêche d'ouvrir ce
 * formulaire, de laisser passer l'heure, puis de le soumettre.
 *
 * @var string      $jeton
 * @var string|null $erreur
 */
use App\Core\Csrf;
?>
<h1>Nouveau mot de passe</h1>

<?php if ($erreur !== null): ?>
  <p class="bo-message bo-message--erreur"><?= e($erreur) ?></p>
<?php endif; ?>

<form class="bo-form" method="post" action="<?= url('/admin/nouveau-mot-de-passe') ?>">
  <?= Csrf::champ() ?>
  <input type="hidden" name="jeton" value="<?= e($jeton) ?>">
  <div class="bo-champ">
    <label for="n-mdp">Nouveau mot de passe</label>
    <input id="n-mdp" type="password" name="mot_de_passe" required minlength="8"
           autocomplete="new-password" autofocus>
    <p class="bo-aide">Au moins 8 caractères.</p>
  </div>
  <div class="bo-champ">
    <label for="n-conf">Confirmer</label>
    <input id="n-conf" type="password" name="confirmation" required minlength="8"
           autocomplete="new-password">
  </div>
  <button class="bo-btn" type="submit">Changer le mot de passe</button>
</form>
