<?php
use App\Core\Url;
/** @var ?array $user @var array $roles @var array $devices */
$u = $user ?? [];
$id = (int) ($u['id'] ?? 0);
?>
<p><a class="link small" href="<?= e(Url::admin('utilisateurs')) ?>">← Utilisateurs</a></p>
<div class="adm-head"><div><h1><?= e($user ? ($u['name'] ?: $u['email']) : 'Nouvel utilisateur') ?></h1><?php if ($user): ?><p>Créé <?= e(ago((string) ($u['created_at'] ?? ''))) ?><?= !empty($u['last_login_at']) ? ' · dernière connexion ' . e(ago((string) $u['last_login_at'])) . ' (' . e((string) ($u['last_login_ip'] ?? '')) . ')' : '' ?></p><?php endif; ?></div></div>
<div class="adm-cols">
  <form class="box form" method="post" action="<?= e(Url::admin($user ? 'utilisateurs/' . $id : 'utilisateurs/nouveau')) ?>">
    <?= csrf_field() ?><input type="hidden" name="action" value="save">
    <div class="form-grid">
      <div class="field"><label for="u-name">Nom</label><input id="u-name" type="text" name="name" maxlength="80" required value="<?= e((string) ($u['name'] ?? '')) ?>"></div>
      <div class="field"><label for="u-email">Email</label><input id="u-email" type="email" name="email" required value="<?= e((string) ($u['email'] ?? '')) ?>"></div>
    </div>
    <fieldset class="field"><legend class="label">Rôle</legend>
      <div class="stack"><?php foreach ($roles as $k => [$l, $d]): ?><label class="check"><input type="radio" name="role" value="<?= e($k) ?>"<?= ($u['role'] ?? 'moderator') === $k ? ' checked' : '' ?>> <span><strong><?= e($l) ?></strong> — <span class="muted"><?= e($d) ?></span></span></label><?php endforeach; ?></div>
    </fieldset>
    <label class="switch"><input type="checkbox" name="status" value="disabled"<?= ($u['status'] ?? 'active') === 'disabled' ? ' checked' : '' ?>> Compte désactivé</label>
    <div class="form-actions"><button class="btn btn-coral" type="submit"><?= $user ? 'Enregistrer' : 'Créer le compte' ?></button></div>
    <?php if (!$user): ?><p class="small muted">Un mot de passe provisoire sera généré et affiché une seule fois ; la personne devra le changer à sa première connexion.</p><?php endif; ?>
  </form>
  <?php if ($user): ?>
  <aside>
    <div class="box">
      <h2>Sécurité</h2>
      <p class="small">Double authentification : <?= !empty($u['totp_enabled']) ? '<span class="verified">✓ activée</span>' : '<strong>non activée</strong>' ?></p>
      <?php if (!empty($u['locked_until']) && strtotime((string) $u['locked_until']) > time()): ?><p class="alert alert-warning small">Verrouillé jusqu'à <?= e(date_fr((string) $u['locked_until'], 'time')) ?></p><?php endif; ?>
      <div class="stack">
        <?php foreach (['reset-password' => ['Réinitialiser le mot de passe', 'Générer un nouveau mot de passe provisoire ?'], 'disable-2fa' => ['Désactiver la 2FA', 'Désactiver la double authentification de ce compte ?'], 'logout' => ['Fermer toutes ses sessions', ''], 'unlock' => ['Déverrouiller', ''], 'delete' => ['Supprimer le compte', 'Supprimer définitivement ce compte ?']] as $act => [$label, $confirm]): ?>
          <form method="post" action="<?= e(Url::admin('utilisateurs/' . $id)) ?>"<?= $confirm ? ' data-confirm="' . e($confirm) . '"' : '' ?>><?= csrf_field() ?><input type="hidden" name="action" value="<?= e($act) ?>"><button class="btn btn-sm btn-block" type="submit"><?= e($label) ?></button></form>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="box"><h2>Appareils (notifications push)</h2><?php if (!$devices): ?><p class="muted small">Aucun appareil.</p><?php else: ?><ul class="list-rows"><?php foreach ($devices as $d): ?><li><span class="small"><?= e(App\Core\Str::limit((string) ($d['ua'] ?? ''), 60)) ?></span><span class="muted"><?= e(ago((string) ($d['created_at'] ?? ''))) ?></span></li><?php endforeach; ?></ul><?php endif; ?></div>
  </aside>
  <?php endif; ?>
</div>
