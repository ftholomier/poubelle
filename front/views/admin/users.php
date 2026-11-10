<?php $me = Auth::user(); ?>
<div class="cols">
  <div class="card card--flush">
    <table class="tbl">
      <thead><tr><th>Nom</th><th>E-mail</th><th>Rôle</th><th>Dernière connexion</th><th></th></tr></thead>
      <tbody>
      <?php foreach (Auth::users() as $u): ?>
        <tr>
          <td><b><?= e($u['name'] ?: '—') ?></b><?= $u['id'] === $me['id'] ? ' <small>(vous)</small>' : '' ?></td>
          <td><?= e($u['email']) ?></td>
          <td><?= e(Auth::ROLES[$u['role']] ?? $u['role']) ?></td>
          <td><?= !empty($u['last_login']) ? e(date('d/m/Y H:i', strtotime($u['last_login']))) : '—' ?></td>
          <td class="r"><?php if ($u['id'] !== $me['id']): ?><button class="b b--danger" type="button" data-action="user.delete" data-id="<?= e($u['id']) ?>" data-confirm="Supprimer ce compte ?">Supprimer</button><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <form class="card bo-form" data-api="user.save">
    <h2>Nouveau compte</h2>
    <div class="f"><label class="f-label" for="u-n">Nom</label><input id="u-n" name="name"></div>
    <div class="f"><label class="f-label" for="u-e">E-mail</label><input id="u-e" type="email" name="email" required></div>
    <div class="f"><label class="f-label" for="u-r">Rôle</label><select id="u-r" name="role"><option value="editeur">Éditeur (contenus et demandes, sans les réglages, les avis clients ni la conversion)</option><option value="admin">Administrateur (tout)</option></select></div>
    <div class="f"><label class="f-label" for="u-p">Mot de passe provisoire</label><input id="u-p" type="text" name="password" autocomplete="off" required></div>
    <button class="b b--y" type="submit">Créer le compte</button>
  </form>
</div>
