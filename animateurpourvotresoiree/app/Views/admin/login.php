<h1 class="h3">Connexion</h1>
<form class="form mt-2" method="post" action="<?= e(App\Core\Url::admin('login')) ?>">
  <?= csrf_field() ?>
  <div class="field"><label for="a-email">Email</label><input id="a-email" type="email" name="email" value="<?= e(old('email')) ?>" autocomplete="username" required autofocus></div>
  <div class="field"><label for="a-pw">Mot de passe</label><input id="a-pw" type="password" name="password" autocomplete="current-password" required></div>
  <button class="btn btn-ink btn-block" type="submit">Se connecter</button>
</form>
<p class="small muted mt-2">Accès réservé à l'équipe. Toutes les connexions sont journalisées.</p>
