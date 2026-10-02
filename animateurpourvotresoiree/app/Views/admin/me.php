<?php
use App\Core\Url;
/** @var array $me @var string $pending @var string $otpUri @var ?array $recovery @var array $devices @var array $roles */
?>
<div class="adm-head"><div><h1>Mon compte</h1><p><?= e($me['email']) ?> · <?= e($roles[$me['role']][0] ?? $me['role']) ?></p></div></div>
<?php if (!empty($me['must_change_password'])): ?><div class="alert alert-warning mb-2">Vous utilisez un mot de passe provisoire : choisissez votre mot de passe personnel ci-dessous.</div><?php endif; ?>
<div class="adm-grid">
  <div>
    <form class="box form" method="post" action="<?= e(Url::admin('mon-compte')) ?>">
      <?= csrf_field() ?><input type="hidden" name="form" value="profile">
      <h2>Profil</h2>
      <div class="field"><label for="me-name">Nom affiché</label><input id="me-name" type="text" name="name" maxlength="80" value="<?= e((string) ($me['name'] ?? '')) ?>"></div>
      <div><button class="btn btn-sm btn-ink" type="submit">Enregistrer</button></div>
    </form>
    <form class="box form" method="post" action="<?= e(Url::admin('mon-compte')) ?>" id="securite">
      <?= csrf_field() ?><input type="hidden" name="form" value="password">
      <h2>Mot de passe</h2>
      <div class="field"><label for="me-cur">Mot de passe actuel</label><input id="me-cur" type="password" name="current" required autocomplete="current-password"></div>
      <div class="form-grid">
        <div class="field"><label for="me-pw">Nouveau mot de passe</label><input id="me-pw" type="password" name="password" required autocomplete="new-password" data-strength></div>
        <div class="field"><label for="me-pw2">Confirmation</label><input id="me-pw2" type="password" name="password2" required autocomplete="new-password"></div>
      </div>
      <div><button class="btn btn-sm btn-coral" type="submit">Changer le mot de passe</button></div>
    </form>
  </div>
  <div>
    <div class="box" id="2fa">
      <h2><?= icon('lock', 18) ?> Double authentification (2FA)</h2>
      <?php if ($recovery): ?>
        <div class="alert alert-warning small mb-2"><div><strong>Codes de secours</strong> (affichés une seule fois) : notez-les en lieu sûr. Chacun permet une connexion si vous perdez votre téléphone.</div></div>
        <div class="recovery"><?php foreach ($recovery as $c): ?><div><?= e($c) ?></div><?php endforeach; ?></div>
      <?php endif; ?>
      <?php if (!empty($me['totp_enabled'])): ?>
        <p><span class="verified">✓ Activée</span> — un code de votre application vous est demandé à chaque connexion.</p>
        <div class="row-wrap mt-2">
          <form method="post" action="<?= e(Url::admin('mon-compte')) ?>" data-confirm="Générer de nouveaux codes de secours ? Les anciens ne fonctionneront plus."><?= csrf_field() ?><input type="hidden" name="form" value="recovery"><button class="btn btn-sm" type="submit">Nouveaux codes de secours</button></form>
        </div>
        <form class="form mt-2" method="post" action="<?= e(Url::admin('mon-compte')) ?>" data-confirm="Désactiver la double authentification ?">
          <?= csrf_field() ?><input type="hidden" name="form" value="2fa-disable">
          <div class="field"><label for="me-dis">Mot de passe (pour désactiver)</label><input id="me-dis" type="password" name="current" required autocomplete="current-password"></div>
          <div><button class="btn btn-sm" type="submit">Désactiver la 2FA</button></div>
        </form>
      <?php elseif ($pending !== ''): ?>
        <p class="small">1. Scannez ce QR code avec Google Authenticator, Microsoft Authenticator, Authy, 1Password…</p>
        <div class="qr" data-qr="<?= e($otpUri) ?>"></div>
        <p class="small mt-1">Ou saisissez la clé : <code class="mono" style="text-transform:none;word-break:break-all"><?= e(trim(chunk_split($pending, 4, ' '))) ?></code></p>
        <form class="form mt-2" method="post" action="<?= e(Url::admin('mon-compte')) ?>">
          <?= csrf_field() ?><input type="hidden" name="form" value="2fa-confirm">
          <div class="field"><label for="me-code">2. Code affiché par l'application</label><input id="me-code" class="code-input" type="text" name="code" inputmode="numeric" maxlength="6" required autocomplete="one-time-code"></div>
          <div><button class="btn btn-sm btn-coral" type="submit">Activer la 2FA</button></div>
        </form>
      <?php else: ?>
        <p class="small">Protégez le back-office : même avec votre mot de passe, personne ne pourra se connecter sans votre téléphone.</p>
        <form method="post" action="<?= e(Url::admin('mon-compte')) ?>"><?= csrf_field() ?><input type="hidden" name="form" value="2fa-start"><button class="btn btn-sm btn-coral" type="submit">Configurer la 2FA</button></form>
      <?php endif; ?>
    </div>
    <div class="box" id="appareils">
      <h2><?= icon('bell', 18) ?> Alertes sur mes appareils</h2>
      <p class="small">Recevez les alertes (nouvelle inscription, devis à modérer…) en notification sur ce téléphone ou cet ordinateur.</p>
      <button type="button" class="btn btn-sm btn-lime" data-push-subscribe="<?= e(Url::admin('push/subscribe')) ?>"><?= icon('bell', 16) ?> Activer sur cet appareil</button>
      <?php if ($devices): ?>
        <ul class="list-rows mt-2"><?php foreach ($devices as $d): ?><li><span class="small"><?= e(App\Core\Str::limit((string) ($d['ua'] ?? 'Appareil'), 70)) ?><br><span class="muted">ajouté <?= e(ago((string) ($d['created_at'] ?? ''))) ?></span></span>
          <form method="post" action="<?= e(Url::admin('mon-compte')) ?>"><?= csrf_field() ?><input type="hidden" name="form" value="device-delete"><input type="hidden" name="device" value="<?= (int) $d['id'] ?>"><button class="btn btn-xs" type="submit">Retirer</button></form></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </div>
  </div>
</div>
