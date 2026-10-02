<h1 class="h3">Double authentification</h1>
<p class="small">Saisissez le code à 6 chiffres affiché par votre application d'authentification (ou un code de secours).</p>
<form class="form mt-2" method="post" action="<?= e(App\Core\Url::admin('2fa')) ?>">
  <?= csrf_field() ?>
  <div class="field"><label for="a-code">Code</label><input id="a-code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="20" required autofocus class="code-input"></div>
  <button class="btn btn-ink btn-block" type="submit">Valider</button>
</form>
<form method="post" action="<?= e(App\Core\Url::admin('logout')) ?>" class="mt-2"><?= csrf_field() ?><button class="link small" type="submit">Annuler</button></form>
