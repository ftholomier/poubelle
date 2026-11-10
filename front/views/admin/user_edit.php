<?php
/** Fiche d'un compte du back-office (administrateurs) : nom, e-mail, rôle, nouveau mot de passe, lien de réinitialisation. */
/** @var array $edit */
$me = Auth::user();
$self = $edit['id'] === $me['id'];
$when = fn($d): string => !empty($d) ? date('d/m/Y à H:i', strtotime((string) $d)) : '—';
$who = $edit['name'] ?: $edit['email'];
?>
<div class="bo-actions" style="margin:0 0 16px"><a class="b b--l" href="/admin/utilisateurs">← Tous les comptes</a></div>
<div class="cols">
  <form class="card bo-form" data-api="user.save" autocomplete="off">
    <input type="hidden" name="id" value="<?= e($edit['id']) ?>">
    <h2>Fiche du compte</h2>
    <div class="f"><label class="f-label" for="ue-n">Nom</label><input id="ue-n" name="name" value="<?= e($edit['name']) ?>" maxlength="80"></div>
    <div class="f"><label class="f-label" for="ue-e">E-mail (identifiant de connexion)</label><input id="ue-e" type="email" name="email" value="<?= e($edit['email']) ?>" required></div>
    <div class="f">
      <label class="f-label" for="ue-r">Rôle</label>
      <?php if ($self): ?>
        <input type="hidden" name="role" value="<?= e($edit['role']) ?>">
        <select id="ue-r" disabled><option><?= e(Auth::ROLES[$edit['role']] ?? $edit['role']) ?></option></select>
        <div class="f-help">Vous ne pouvez pas changer votre propre rôle.</div>
      <?php else: ?>
        <select id="ue-r" name="role">
          <option value="editeur"<?= $edit['role'] === 'editeur' ? ' selected' : '' ?>>Éditeur (contenus et demandes)</option>
          <option value="admin"<?= $edit['role'] === 'admin' ? ' selected' : '' ?>>Administrateur (tout)</option>
        </select>
        <div class="f-help">L'éditeur ne voit ni les réglages, ni les avis clients, ni la page Conversion.</div>
      <?php endif; ?>
    </div>

    <h2 style="margin-top:24px">Mot de passe</h2>
    <?php if ($self): ?>
      <p class="bo-muted">Pour changer votre propre mot de passe, passez par <a href="/admin/compte">Mon compte</a> : votre mot de passe actuel vous sera demandé.</p>
    <?php else: ?>
      <div class="f">
        <label class="f-label" for="ue-p">Nouveau mot de passe</label>
        <div class="pw" data-pw>
          <input id="ue-p" type="password" name="password" autocomplete="new-password" placeholder="Laisser vide pour ne pas le changer" spellcheck="false">
          <button class="b b--l" type="button" data-pw-toggle>Afficher</button>
          <button class="b b--l" type="button" data-pw-gen>Générer</button>
          <button class="b b--l" type="button" data-pw-copy>Copier</button>
        </div>
        <div class="f-help">Au moins 10 caractères, avec des lettres et des chiffres. « Générer » en propose un, facile à dicter au téléphone. Laissez vide pour garder le mot de passe actuel.</div>
      </div>
      <div class="pw-note">
        <b>Pourquoi le mot de passe actuel n'est-il pas affiché ?</b>
        Les mots de passe sont chiffrés de façon irréversible dès leur enregistrement : personne ne peut les relire, ni un administrateur, ni l'hébergeur, ni le développeur. C'est ce qui protège les comptes si le serveur était un jour piraté. Vous pouvez en revanche en définir un nouveau ici, ou envoyer à la personne un lien pour qu'elle le choisisse elle-même.
      </div>
    <?php endif; ?>
    <div class="bo-actions"><button class="b b--y" type="submit">Enregistrer</button></div>
  </form>

  <div>
    <div class="card">
      <h2>Informations</h2>
      <dl class="dl">
        <dt>Compte créé le</dt><dd><?= e($when($edit['created'] ?? '')) ?></dd>
        <dt>Dernière connexion</dt><dd><?= e($when($edit['last_login'] ?? '')) ?></dd>
        <dt>Mot de passe changé le</dt><dd><?= e($when($edit['pw_at'] ?? '')) ?></dd>
      </dl>
    </div>
    <?php if (!$self): ?>
      <div class="card">
        <h2>Lien pour choisir son mot de passe</h2>
        <p class="bo-muted">Envoie un e-mail à <b><?= e($edit['email']) ?></b> avec un lien pour que la personne choisisse elle-même un nouveau mot de passe. Le lien est valable 48 heures et ne sert qu'une fois. C'est la solution la plus sûre : aucun mot de passe ne circule.</p>
        <button class="b b--l" type="button" data-action="user.reset" data-id="<?= e($edit['id']) ?>" data-busy="Envoi…" data-confirm="Envoyer à <?= e($edit['email']) ?> un lien pour choisir un nouveau mot de passe ?">Envoyer le lien par e-mail</button>
      </div>
      <div class="card">
        <h2>Supprimer le compte</h2>
        <p class="bo-muted">La personne ne pourra plus se connecter. Ses notes sur les demandes restent visibles.</p>
        <button class="b b--danger" type="button" data-action="user.delete" data-id="<?= e($edit['id']) ?>" data-confirm="Supprimer définitivement le compte de <?= e($who) ?> ?">Supprimer ce compte</button>
      </div>
    <?php endif; ?>
  </div>
</div>
