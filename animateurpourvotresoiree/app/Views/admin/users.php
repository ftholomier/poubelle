<?php
use App\Controllers\Admin\UsersController;
use App\Core\Url;
/** @var array $items */
?>
<div class="adm-head">
  <div><h1>Utilisateurs <span class="serif">du back-office</span></h1><p>Chaque membre de l'équipe a son propre compte, son rôle et sa double authentification.</p></div>
  <a class="btn btn-sm btn-ink" href="<?= e(Url::admin('utilisateurs/nouveau')) ?>"><?= icon('plus', 16) ?> Nouvel utilisateur</a>
</div>
<div class="table-wrap">
  <table class="tbl">
    <thead><tr><th>Nom</th><th>Rôle</th><th>Statut</th><th>2FA</th><th>Dernière connexion</th></tr></thead>
    <tbody>
      <?php foreach ($items as $a): ?>
        <tr>
          <td><a class="t-main" href="<?= e(Url::admin('utilisateurs/' . $a['id'])) ?>"><?= e($a['name'] ?: $a['email']) ?></a><span class="t-sub"><?= e($a['email']) ?></span></td>
          <td><?= e(UsersController::ROLES[$a['role']][0] ?? $a['role']) ?></td>
          <td><span class="status-pill st-<?= $a['status'] === 'active' ? 'active' : 'suspended' ?>"><?= $a['status'] === 'active' ? 'Actif' : 'Désactivé' ?></span></td>
          <td><?= $a['totp'] ? '<span class="verified">✓ activée</span>' : '<span class="muted">—</span>' ?></td>
          <td><?= $a['login'] ? e(date_fr($a['login'], 'datetime')) : '<span class="muted">jamais</span>' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<div class="box mt-3"><h2>Rôles</h2><ul class="list-rows"><?php foreach (UsersController::ROLES as $k => [$l, $d]): ?><li><strong><?= e($l) ?></strong><span class="muted"><?= e($d) ?></span></li><?php endforeach; ?></ul></div>
