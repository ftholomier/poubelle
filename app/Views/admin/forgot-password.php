<div class="login-wrap">
  <div class="login">
    <div class="login__brand">
      <?php partial('logo', ['class' => 'login__logo']); ?>
      <small>Back-office</small>
    </div>

    <?php if (!empty($envoye)): ?>
      <h1>Vérifiez votre boîte mail</h1>
      <p class="sub">
        Si un compte correspond à cette adresse, un lien de réinitialisation vient d’être envoyé.
        Il est valable <?= (int) round(PasswordReset::DUREE / 60) ?> minutes et ne peut servir qu’une fois.
      </p>
      <div class="flash flash--success">
        Pensez à regarder dans les indésirables : les messages envoyés par un site en sont souvent proches.
      </div>
      <p style="margin-top:22px;font-size:.8rem;color:var(--muted);text-align:center">
        <a href="<?= e(url('admin/login')) ?>">← Revenir à la connexion</a>
      </p>
    <?php else: ?>
      <h1>Mot de passe oublié</h1>
      <p class="sub">Indiquez l’adresse de votre compte : nous vous envoyons un lien pour en choisir un nouveau.</p>

      <?php if (!empty($error)): ?>
        <div class="flash flash--error"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post">
        <?= Csrf::field() ?>
        <div class="field">
          <label for="email">Adresse e-mail</label>
          <input class="input" id="email" name="email" type="email" required autocomplete="username" autofocus>
        </div>
        <button class="btn" style="width:100%;margin-top:6px" type="submit">Envoyer le lien</button>
      </form>

      <p style="margin-top:22px;font-size:.8rem;color:var(--muted);text-align:center">
        <a href="<?= e(url('admin/login')) ?>">← Revenir à la connexion</a>
      </p>
    <?php endif; ?>
  </div>
</div>
