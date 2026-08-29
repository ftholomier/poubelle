<?php
/**
 * Demande d'un lien de récupération.
 *
 * On ne demande QUE l'identifiant : le lien part vers l'adresse de secours
 * réglée dans les Paramètres, jamais vers une adresse saisie ici. La réponse
 * est la même que l'identifiant soit bon ou non — ne rien laisser deviner à
 * qui tâtonne.
 *
 * @var string|null $message  confirmation neutre après envoi
 * @var string|null $erreur   problème de configuration ou verrouillage
 * @var string      $adresse  l'adresse de secours, pour la rappeler
 */
use App\Core\Csrf;
?>
<h1>Mot de passe oublié</h1>

<?php if ($message !== null): ?>
  <p class="bo-message bo-message--succes"><?= e($message) ?></p>
<?php elseif ($erreur !== null): ?>
  <p class="bo-message bo-message--erreur"><?= e($erreur) ?></p>
<?php endif; ?>

<p class="bo-aide">
  Indiquez votre identifiant. Un lien pour choisir un nouveau mot de passe
  partira vers l’adresse de secours du site<?= $adresse !== '' ? ' (' . e($adresse) . ')' : '' ?>.
  Le lien vaut une heure.
</p>

<form class="bo-form" method="post" action="<?= url('/admin/mot-de-passe-oublie') ?>">
  <?= Csrf::champ() ?>
  <div class="bo-champ">
    <label for="o-id">Identifiant</label>
    <input id="o-id" type="text" name="identifiant" required autocomplete="username" autofocus>
  </div>
  <button class="bo-btn" type="submit">Envoyer le lien</button>
</form>

<p class="bo-aide" style="margin-top:1.4rem;">
  <a href="<?= url('/admin/connexion') ?>">Retour à la connexion</a>
</p>
