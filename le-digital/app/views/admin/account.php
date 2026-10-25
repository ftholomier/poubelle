<?php /** @var array $user @var string $csrf */ ?>
<section class="card narrow">
  <form method="post" class="form">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <label>Prénom<input name="name" value="<?= e($user['name'] ?? '') ?>"></label>
    <label>E-mail de connexion<input type="email" name="email" required value="<?= e($user['email']) ?>"></label>
    <label>Nouveau mot de passe <small class="muted">(laisser vide pour le garder)</small><input type="password" name="password" minlength="10" autocomplete="new-password"></label>
    <label>Confirme le nouveau mot de passe<input type="password" name="password2" minlength="10" autocomplete="new-password"></label>
    <label>Mot de passe actuel <small class="muted">(obligatoire)</small><input type="password" name="current" required autocomplete="current-password"></label>
    <button class="btn btn--red" type="submit">Mettre à jour</button>
  </form>
</section>
