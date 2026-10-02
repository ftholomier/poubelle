<?php /** @var string $token @var array $pro */ ?>
<section class="auth-wrap single">
  <div class="auth-card form-card">
    <h1 class="h2">Nouveau <span class="serif c-coral">mot de passe</span></h1>
    <p class="muted">Compte : <strong><?= e(App\Services\Pros::displayName($pro)) ?></strong></p>
    <form class="form mt-2" method="post" action="/reinitialiser/<?= e($token) ?>/">
      <?= csrf_field() ?>
      <div class="field"><label for="rs-pw">Nouveau mot de passe</label><input id="rs-pw" type="password" name="password" autocomplete="new-password" minlength="<?= max(8, (int) env('PASSWORD_MIN_LENGTH', 10)) ?>" required data-strength>
        <span class="hint"><?= max(8, (int) env('PASSWORD_MIN_LENGTH', 10)) ?> caractères minimum, lettres et chiffres.</span></div>
      <div class="field"><label for="rs-pw2">Confirmation</label><input id="rs-pw2" type="password" name="password2" autocomplete="new-password" required></div>
      <button class="btn btn-coral btn-block" type="submit">Enregistrer</button>
    </form>
  </div>
</section>
