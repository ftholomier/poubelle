<div class="login-wrap">
  <div class="login">
    <div class="login__brand">
      <?php partial('logo', ['class' => 'login__logo']); ?>
      <small>Back-office</small>
    </div>

    <?php if (empty($valide)): ?>
      <h1>Lien expiré</h1>
      <p class="sub">
        Ce lien de réinitialisation n’est plus valable : il a déjà servi, ou il a dépassé
        <?= (int) round(PasswordReset::DUREE / 60) ?> minutes. Votre mot de passe actuel n’a pas changé.
      </p>
      <?php if (!empty($error)): ?>
        <div class="flash flash--error"><?= e($error) ?></div>
      <?php endif; ?>
      <a class="btn" style="width:100%;margin-top:6px" href="<?= e(url('admin/mot-de-passe-oublie')) ?>">
        Demander un nouveau lien
      </a>
      <p style="margin-top:22px;font-size:.8rem;color:var(--muted);text-align:center">
        <a href="<?= e(url('admin/login')) ?>">← Revenir à la connexion</a>
      </p>
    <?php else: ?>
      <h1>Nouveau mot de passe</h1>
      <p class="sub">
        Choisissez un mot de passe d’au moins <?= (int) PasswordReset::LONGUEUR_MIN ?> caractères.
        Toutes les sessions ouvertes sur ce compte seront fermées.
      </p>

      <?php if (!empty($error)): ?>
        <div class="flash flash--error"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" action="<?= e(url('admin/nouveau-mot-de-passe')) ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="jeton" value="<?= e($jeton) ?>">
        <div class="field">
          <label for="password">Nouveau mot de passe</label>
          <small class="help">Une phrase de passe fait un excellent mot de passe.</small>
          <input class="input" id="password" name="password" type="password"
                 minlength="<?= (int) PasswordReset::LONGUEUR_MIN ?>" required autocomplete="new-password" autofocus>
        </div>
        <div class="field">
          <label for="password_confirm">Confirmation</label>
          <input class="input" id="password_confirm" name="password_confirm" type="password"
                 minlength="<?= (int) PasswordReset::LONGUEUR_MIN ?>" required autocomplete="new-password">
        </div>
        <button class="btn" style="width:100%;margin-top:6px" type="submit">Enregistrer le mot de passe</button>
      </form>
    <?php endif; ?>
  </div>
</div>
