<div class="login-wrap">
  <div class="login">
    <div class="login__brand">
      <?php partial('logo', ['class' => 'login__logo']); ?>
      <small>Back-office</small>
    </div>

    <h1>Choisissez votre mot de passe</h1>
    <p class="sub">
      Le compte <strong><?= e($user['email'] ?? '') ?></strong> utilise encore le mot de passe
      généré à l’installation, écrit en clair dans <code>data/PREMIERE-CONNEXION.txt</code>.
      Ce fichier sera supprimé dès l’enregistrement.
    </p>

    <?php if (!empty($error)): ?>
      <div class="flash flash--error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <?= Csrf::field() ?>
      <div class="field">
        <label for="password">Nouveau mot de passe</label>
        <small class="help">12 caractères minimum. Une phrase de passe fait un excellent mot de passe.</small>
        <input class="input" id="password" name="password" type="password" minlength="12" required autocomplete="new-password" autofocus>
      </div>
      <div class="field">
        <label for="password_confirm">Confirmation</label>
        <input class="input" id="password_confirm" name="password_confirm" type="password" minlength="12" required autocomplete="new-password">
      </div>
      <button class="btn" style="width:100%;margin-top:6px" type="submit">Enregistrer et accéder au back-office</button>
    </form>
  </div>
</div>
