<div class="login-wrap">
  <div class="login">
    <div class="login__brand">
      <?php partial('logo', ['class' => 'login__logo']); ?>
      <small>Back-office</small>
    </div>

    <h1>Connexion</h1>
    <p class="sub">Accès réservé à l’équipe de recrutement.</p>

    <?php // Messages venus d'un autre écran : mot de passe réinitialisé, session expirée… ?>
    <?php foreach (Session::flash() as $f): ?>
      <div class="flash flash--<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>

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

    <p style="margin-top:18px;font-size:.85rem;text-align:center">
      <a href="<?= e(url('admin/mot-de-passe-oublie')) ?>" class="souligne">Mot de passe oublié ?</a>
    </p>

    <p style="margin-top:14px;font-size:.8rem;color:var(--muted);text-align:center">
      <a href="<?= e(url('/')) ?>">← Retour au site</a>
    </p>
  </div>
</div>
