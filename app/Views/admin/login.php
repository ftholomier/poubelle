<div class="login-wrap">
  <div class="login">
    <div class="login__brand">
      <svg width="40" height="40" viewBox="0 0 48 48" fill="none" aria-hidden="true">
        <rect width="48" height="48" rx="13" fill="url(#lg)"/>
        <path d="M14 30V20.5L24 13l10 7.5V30" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M19.5 35v-9h9v9" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
        <defs><linearGradient id="lg" x1="0" y1="0" x2="48" y2="48"><stop stop-color="#E62F43"/><stop offset="1" stop-color="#FF8A3D"/></linearGradient></defs>
      </svg>
      <span><b style="font-family:var(--font-display)">Suisse Immo</b><br><small style="color:var(--muted);font-size:.72rem;letter-spacing:.16em;text-transform:uppercase">Back-office</small></span>
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
