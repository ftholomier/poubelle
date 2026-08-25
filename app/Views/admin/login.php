<div class="login-wrap">
  <div class="login">
    <div class="login__brand">
      <?php partial('logo', ['class' => 'login__logo']); ?>
      <small>Back-office</small>
    </div>

    <h1>Connexion</h1>
    <p class="sub">Accès réservé à l’équipe de recrutement.</p>

    <?php if (!empty($error)): ?>
      <div class="flash flash--error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <?= Csrf::field() ?>
      <div class="field">
        <label for="email">Adresse e-mail</label>
        <input class="input" id="email" name="email" type="email" required autocomplete="username" autofocus>
      </div>
      <div class="field">
        <label for="password">Mot de passe</label>
        <input class="input" id="password" name="password" type="password" required autocomplete="current-password">
      </div>
      <button class="btn" style="width:100%;margin-top:6px" type="submit">Se connecter</button>
    </form>

    <p style="margin-top:22px;font-size:.8rem;color:var(--muted);text-align:center">
      <a href="<?= e(url('/')) ?>">← Retour au site</a>
    </p>
  </div>
</div>
