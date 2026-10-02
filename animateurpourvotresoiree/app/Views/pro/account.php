<?php
/** @var array $pro @var bool $push */
$s = (array) ($pro['settings'] ?? []);
$forced = !empty($pro['must_change_password']);
$min = max(8, (int) env('PASSWORD_MIN_LENGTH', 10));
?>
<div class="pro-head">
  <div><p class="mono muted small">Mon compte</p><h1 class="h2">Réglages <span class="serif c-coral">& sécurité</span></h1></div>
</div>

<div class="pro-cols mt-2">
  <div class="stack">
    <form class="box<?= $forced ? ' box-alert' : '' ?>" method="post" action="/espace-pro/compte/" id="securite">
      <?= csrf_field() ?><input type="hidden" name="form" value="password">
      <h2><?= icon('lock', 20) ?> Mot de passe</h2>
      <?php if ($forced): ?><p class="alert alert-warning small">Le nouveau site protège mieux vos données : merci de choisir un <strong>nouveau mot de passe</strong> (l'ancien était conservé sans chiffrement par l'ancien site).</p><?php endif; ?>
      <div class="form-grid">
        <div class="field"><label for="pw-cur">Mot de passe actuel</label><input id="pw-cur" type="password" name="current" autocomplete="current-password" required></div>
        <div class="field"><label for="pw-new">Nouveau mot de passe</label><input id="pw-new" type="password" name="password" autocomplete="new-password" minlength="<?= $min ?>" required data-strength><span class="hint"><?= $min ?> caractères minimum, lettres et chiffres.</span></div>
        <div class="field"><label for="pw-new2">Confirmation</label><input id="pw-new2" type="password" name="password2" autocomplete="new-password" required></div>
      </div>
      <button class="btn btn-coral btn-sm mt-2" type="submit">Changer mon mot de passe</button>
    </form>

    <form class="box" method="post" action="/espace-pro/compte/" id="preferences">
      <?= csrf_field() ?><input type="hidden" name="form" value="prefs">
      <h2><?= icon('bell', 20) ?> Préférences</h2>
      <div class="stack">
        <label class="check"><input type="checkbox" name="notify_requests" value="1"<?= ($s['notify_requests'] ?? true) ? ' checked' : '' ?>> <span>Recevoir par email les demandes de devis de mon secteur</span></label>
        <label class="check"><input type="checkbox" name="notify_messages" value="1"<?= ($s['notify_messages'] ?? true) ? ' checked' : '' ?>> <span>Recevoir par email les messages envoyés depuis ma fiche</span></label>
        <label class="check"><input type="checkbox" name="weekly_report" value="1"<?= ($s['weekly_report'] ?? true) ? ' checked' : '' ?>> <span>Recevoir le bilan hebdomadaire de ma fiche</span></label>
        <label class="check"><input type="checkbox" name="newsletter" value="1"<?= ($s['newsletter'] ?? true) ? ' checked' : '' ?>> <span>Recevoir les conseils et nouveautés du site</span></label>
        <hr class="sep">
        <label class="check"><input type="checkbox" name="vacation" value="1"<?= !empty($s['vacation']) ? ' checked' : '' ?>> <span><strong>Mode congés</strong> : je ne reçois plus de demandes groupées (ma fiche reste visible)</span></label>
        <div class="field" style="max-width:260px"><label for="vac-until">Jusqu'au (facultatif)</label><input id="vac-until" type="date" name="vacation_until" value="<?= e((string) ($s['vacation_until'] ?? '')) ?>" min="<?= date('Y-m-d') ?>"></div>
      </div>
      <button class="btn btn-ink btn-sm mt-2" type="submit">Enregistrer</button>
    </form>

    <div class="box" id="notifications">
      <h2><?= icon('bell', 20) ?> Notifications sur cet appareil</h2>
      <?php if ($push): ?>
        <p class="small">Soyez prévenu instantanément (téléphone ou ordinateur) à chaque nouvelle demande ou message.</p>
        <button type="button" class="btn btn-sm btn-lime" data-push-subscribe="/api/push/subscribe"><?= icon('bell', 16) ?> Activer les notifications</button>
        <p class="small muted mt-1">Sur iPhone : ajoutez d'abord le site à l'écran d'accueil (Partager → Sur l'écran d'accueil), puis ouvrez-le depuis l'icône.</p>
      <?php else: ?>
        <p class="small muted">Les notifications ne sont pas disponibles pour le moment.</p>
      <?php endif; ?>
    </div>
  </div>

  <aside class="stack">
    <div class="box">
      <h2><?= icon('user', 20) ?> Identifiants</h2>
      <dl class="dl">
        <dt>Email</dt><dd><?= e($pro['email'] ?? '') ?> <?= !empty($pro['email_verified']) ? '<span class="verified">✓ confirmé</span>' : '<span class="status-pill st-pending">à confirmer</span>' ?></dd>
        <?php if (!empty($pro['login']) && !str_contains((string) $pro['login'], '@')): ?><dt>Identifiant (ancien site)</dt><dd><?= e($pro['login']) ?></dd><?php endif; ?>
        <?php if (!empty($pro['email_pending'])): ?><dt>En attente</dt><dd><?= e($pro['email_pending']) ?> (lien envoyé)</dd><?php endif; ?>
        <dt>Membre depuis</dt><dd><?= e(date_fr((string) ($pro['created_at'] ?? ''), 'month')) ?></dd>
      </dl>
      <?php if (empty($pro['email_verified'])): ?>
        <form method="post" action="/espace-pro/compte/"><?= csrf_field() ?><input type="hidden" name="form" value="resend"><button class="btn btn-xs mt-1" type="submit">Renvoyer l'email de confirmation</button></form>
      <?php endif; ?>
    </div>
    <form class="box" method="post" action="/espace-pro/compte/">
      <?= csrf_field() ?><input type="hidden" name="form" value="email">
      <h2>Changer d'adresse email</h2>
      <div class="field"><label for="em-new">Nouvelle adresse</label><input id="em-new" type="email" name="email" required autocomplete="email"></div>
      <div class="field"><label for="em-pw">Mot de passe</label><input id="em-pw" type="password" name="current" required autocomplete="current-password"></div>
      <button class="btn btn-sm mt-1" type="submit">Recevoir le lien de confirmation</button>
    </form>
    <div class="box">
      <h2><?= icon('download', 20) ?> Mes données</h2>
      <p class="small">Téléchargez toutes les données de votre compte (fiche, messages, demandes, avis, statistiques).</p>
      <a class="btn btn-sm" href="/espace-pro/compte/export">Télécharger (JSON)</a>
    </div>
    <form class="box box-danger" method="post" action="/espace-pro/compte/supprimer" id="supprimer" data-confirm="Supprimer définitivement votre compte et votre fiche ? Cette action est irréversible.">
      <?= csrf_field() ?>
      <h2><?= icon('trash', 20) ?> Supprimer mon compte</h2>
      <p class="small">Votre fiche, vos photos et vos données personnelles seront effacées définitivement.</p>
      <div class="field"><label for="del-pw">Mot de passe</label><input id="del-pw" type="password" name="current" required autocomplete="current-password"></div>
      <div class="field"><label for="del-confirm">Tapez SUPPRIMER</label><input id="del-confirm" type="text" name="confirm" required autocomplete="off"></div>
      <button class="btn btn-sm mt-1" type="submit">Supprimer définitivement</button>
    </form>
  </aside>
</div>
