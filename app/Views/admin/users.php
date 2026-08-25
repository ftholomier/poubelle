<div class="topbar"><h1>Utilisateurs</h1></div>

<?php foreach ($rows as $u): if (!empty($u['must_change_password'])): ?>
  <div class="flash flash--error">
    Le compte <strong><?= e($u['email']) ?></strong> utilise encore son mot de passe d’installation. Changez-le maintenant,
    puis supprimez le fichier <code>data/PREMIERE-CONNEXION.txt</code>.
  </div>
<?php endif; endforeach; ?>

<div class="panel">
  <div class="panel__head"><h2>Comptes existants</h2></div>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Nom</th><th>E-mail</th><th>Dernière connexion</th><th>Nouveau mot de passe</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $u): ?>
          <tr>
            <td><strong><?= e($u['name'] ?? '') ?></strong><?= ($u['id'] ?? '') === ($user['id'] ?? '') ? ' <span class="badge">vous</span>' : '' ?></td>
            <td style="color:var(--muted)"><?= e($u['email'] ?? '') ?></td>
            <td style="color:var(--muted)"><?= e(fr_date($u['last_login'] ?? '', true) ?: 'jamais') ?></td>
            <td>
              <form class="row" method="post" style="gap:8px">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="password">
                <input type="hidden" name="id" value="<?= e($u['id'] ?? '') ?>">
                <input class="input" style="max-width:200px" type="password" name="password" placeholder="10 caractères minimum" minlength="10" required autocomplete="new-password">
                <button class="btn btn--sm btn--ghost" type="submit">Changer</button>
              </form>
            </td>
            <td style="text-align:right">
              <?php if (($u['id'] ?? '') !== ($user['id'] ?? '')): ?>
                <form method="post" onsubmit="return confirm('Supprimer ce compte ?')">
                  <?= Csrf::field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= e($u['id'] ?? '') ?>">
                  <button class="btn btn--sm btn--danger" type="submit">Supprimer</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="panel">
  <div class="panel__head"><h2>Ajouter un utilisateur</h2></div>
  <form method="post">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="create">
    <div class="grid grid--3">
      <div class="field"><label for="n-name">Nom</label><input class="input" id="n-name" name="name" required></div>
      <div class="field"><label for="n-email">E-mail</label><input class="input" id="n-email" name="email" type="email" required autocomplete="off"></div>
      <div class="field"><label for="n-pass">Mot de passe</label><input class="input" id="n-pass" name="password" type="password" minlength="10" required autocomplete="new-password"></div>
    </div>
    <button class="btn" type="submit">Créer le compte</button>
  </form>
</div>
