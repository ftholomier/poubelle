<?php
/** Utilisateurs du back-office (administrateurs seulement). Variables : $users, $me, $counts, $link */
use App\Admin\Base;
use App\Core\Auth;

$stLabel = ['active' => ['Actif', 'ok'], 'invited' => ['Invitation envoyée', 'warn'], 'disabled' => ['Désactivé', 'ko']];
?>
<?php if ($link): ?>
  <div class="alert">
    Lien d’activation pour <b><?= e($link['name']) ?></b> (valable 7 jours, à transmettre de façon sûre) :
    <div class="row" style="margin-top:6px"><code class="small" style="overflow-wrap:anywhere;flex:1"><?= e($link['url']) ?></code><button type="button" class="btn btn--sm" data-copy="<?= e($link['url']) ?>">Copier</button></div>
  </div>
<?php endif; ?>
<div class="cols cols--wide">
  <div class="card">
    <div class="card__head"><h2 class="card__t">Équipe du back-office</h2><span class="card__note"><?= count($users) ?> compte<?= count($users) > 1 ? 's' : '' ?></span></div>
    <?php foreach ($users as $u): [$sl, $sc] = $stLabel[$u['status']] ?? [$u['status'], 'warn']; $self = $u['id'] === $me['id']; ?>
      <div class="card__row" style="grid-template-columns:40px minmax(0,1fr) auto">
        <span class="avatar"<?= $u['status'] === 'disabled' ? ' style="opacity:.4"' : '' ?>><?= e(Base::initials($u['name'])) ?></span>
        <span><b><?= e($u['name']) ?></b><?= $self ? ' <span class="xs muted">(vous)</span>' : '' ?><br><span class="small"><?= e($u['email']) ?></span><br>
          <span class="pill pill--<?= $u['role'] === 'admin' ? 'navy' : 'brouillon' ?>"><?= e(Auth::ROLES[$u['role']] ?? $u['role']) ?></span> <span class="pill pill--<?= $sc ?>"><?= e($sl) ?></span>
          <span class="xs muted"><?= $u['last_login'] ? 'dernière connexion ' . e(Base::ago($u['last_login'])) : 'jamais connecté' ?><?= !empty($counts[$u['id']]) ? ' · ' . (int) $counts[$u['id']] . ' action(s) récentes' : '' ?></span>
        </span>
        <div class="me" data-dropdown>
          <button type="button" class="btn btn--sm" data-dropdown-toggle aria-expanded="false">Gérer ▾</button>
          <div class="menu" hidden style="right:0;left:auto;min-width:240px">
            <?php if (!$self): ?>
              <form method="post" action="/admin/utilisateurs"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($u['id']) ?>"><input type="hidden" name="role" value="<?= $u['role'] === 'admin' ? 'user' : 'admin' ?>"><button type="submit" name="action" value="role" style="width:100%"><?= $u['role'] === 'admin' ? 'Passer en utilisateur' : 'Passer en administrateur' ?></button></form>
            <?php endif; ?>
            <?php if ($u['status'] !== 'disabled'): ?>
              <form method="post" action="/admin/utilisateurs"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($u['id']) ?>"><button type="submit" name="action" value="<?= $u['status'] === 'active' ? 'reinitialiser' : 'renvoyer' ?>" style="width:100%"><?= $u['status'] === 'active' ? 'Envoyer un lien de nouveau mot de passe' : 'Renvoyer l’invitation' ?></button></form>
            <?php endif; ?>
            <?php if (!$self): ?>
              <form method="post" action="/admin/utilisateurs"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($u['id']) ?>"><button type="submit" name="action" value="<?= $u['status'] === 'disabled' ? 'reactiver' : 'desactiver' ?>" style="width:100%"><?= $u['status'] === 'disabled' ? 'Réactiver le compte' : 'Désactiver le compte' ?></button></form>
              <form method="post" action="/admin/utilisateurs" data-confirm="Supprimer le compte de <?= e($u['name']) ?> ?|Son nom reste dans l’historique des fiches.|Supprimer|danger"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($u['id']) ?>"><button type="submit" name="action" value="supprimer" style="width:100%;color:var(--red)">Supprimer le compte</button></form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="stack">
    <form class="card card--pad" method="post" action="/admin/utilisateurs" id="inviter">
      <?= csrf_field() ?><input type="hidden" name="action" value="inviter">
      <h2 class="card__t card__t--sm">Inviter une personne</h2>
      <label class="f"><span class="f__k">Nom</span><input type="text" name="name" required maxlength="80" placeholder="Prénom Nom"></label>
      <label class="f"><span class="f__k">E-mail</span><input type="email" name="email" required maxlength="160"></label>
      <div class="seg" style="--n:2" role="radiogroup" aria-label="Niveau d’accès">
        <label><input type="radio" name="role" value="user" checked><span>Utilisateur</span></label>
        <label><input type="radio" name="role" value="admin"><span>Administrateur</span></label>
      </div>
      <button type="submit" class="btn btn--navy" style="align-self:flex-start">Envoyer l’invitation</button>
      <span class="f__help">La personne reçoit un lien (valable 7 jours) pour choisir son mot de passe.</span>
    </form>
    <div class="card card--pad">
      <h2 class="card__t card__t--sm">Deux niveaux d’accès</h2>
      <p class="small" style="margin:0"><b>Utilisateur</b> : toutes les fiches, médias, rubriques, collections, contributions, messages, newsletter, dons, traductions, page d’attente.</p>
      <p class="small" style="margin:0"><b>Administrateur</b> : en plus, les utilisateurs et invitations, les réglages (clés API, paiements, e-mail…), la suppression définitive, la restauration d’anciennes versions, les sauvegardes et le lancement manuel des tâches.</p>
    </div>
  </div>
</div>
