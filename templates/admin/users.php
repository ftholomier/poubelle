<?php
/** Utilisateurs. @var array $users @var string $notice */
use App\Core\Csrf;
use App\Services\Auth;
use App\Services\I18n;

$me = Auth::user();
?>
<div class="admin-head">
  <div><h1><?= e(I18n::t('admin.users')) ?></h1><p><?= count($users) ?> compte(s)</p></div>
</div>

<?php if ($notice !== ''): ?><div class="notice notice-ok" role="status"><?= e($notice) ?></div><?php endif; ?>

<div class="admin-card">
  <h2>Inviter quelqu'un</h2>
  <p class="muted" style="font-size:14px;margin:6px 0 16px">
    Un lien de création de mot de passe, valable 30 minutes, est envoyé à l'adresse indiquée.
  </p>
  <form method="post">
    <?= Csrf::field('admin-users') ?>
    <input type="hidden" name="action" value="create">
    <div class="grid-fields">
      <label class="field"><span class="label">Nom</span>
        <input class="input" type="text" name="new_name" required></label>
      <label class="field"><span class="label">Adresse e-mail</span>
        <input class="input" type="email" name="new_email" required></label>
      <label class="field"><span class="label">Rôle</span>
        <select class="select" name="new_role">
          <option value="candidate">Candidat</option>
          <option value="employer">Employeur</option>
          <option value="admin">Administrateur</option>
        </select></label>
    </div>
    <button type="submit" class="btn btn-coral" style="margin-top:16px">Inviter</button>
  </form>
</div>

<div class="admin-card">
  <div class="table-scroll">
    <table class="admin-table">
      <thead><tr><th>Nom</th><th>Adresse</th><th>Rôle</th><th>Mot de passe</th><th>Dernière connexion</th><th></th></tr></thead>
      <tbody>
        <?php foreach (array_slice($users, 0, 300) as $user): ?>
          <tr>
            <td class="t"><?= e(str_excerpt((string) $user['display_name'], 34)) ?></td>
            <td class="s"><?= e($user['email']) ?></td>
            <td><span class="state state-neutral"><?= e($user['role']) ?></span></td>
            <td>
              <?php if (($user['password'] ?? '') !== ''): ?>
                <span class="state state-ok">Argon2id</span>
              <?php elseif (($user['password_legacy'] ?? '') !== ''): ?>
                <span class="state state-wait">hérité</span>
              <?php else: ?>
                <span class="state state-err">à définir</span>
              <?php endif; ?>
            </td>
            <td class="s"><?= e(($user['last_login_at'] ?? '') !== ''
                  ? date('d/m/Y', (int) strtotime((string) $user['last_login_at'])) : '—') ?></td>
            <td class="actions">
              <?php if ((string) $user['id'] !== (string) ($me['id'] ?? '')): ?>
                <form method="post" style="display:inline">
                  <?= Csrf::field('admin-users') ?>
                  <input type="hidden" name="id" value="<?= e((string) $user['id']) ?>">
                  <button type="submit" name="action" value="toggle" class="btn btn-ghost btn-sm">
                    <?= ($user['active'] ?? true) ? 'Désactiver' : 'Réactiver' ?>
                  </button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if (count($users) > 300): ?>
    <p class="muted" style="margin-top:14px;font-size:13px">300 premiers comptes affichés sur <?= count($users) ?>.</p>
  <?php endif; ?>
</div>
