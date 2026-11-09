<?php $u = Auth::user(); ?>
<form class="card bo-form" data-api="account.save" style="max-width:560px">
  <div class="f"><label class="f-label" for="a-n">Nom</label><input id="a-n" name="name" value="<?= e($u['name']) ?>"></div>
  <div class="f"><label class="f-label" for="a-e">E-mail</label><input id="a-e" type="email" name="email" value="<?= e($u['email']) ?>" required></div>
  <div class="f"><label class="f-label" for="a-p">Nouveau mot de passe (facultatif)</label><input id="a-p" type="password" name="password" autocomplete="new-password"></div>
  <div class="f"><label class="f-label" for="a-c">Mot de passe actuel (obligatoire)</label><input id="a-c" type="password" name="current" autocomplete="current-password" required></div>
  <p class="bo-muted">Rôle : <?= e(Auth::ROLES[$u['role']] ?? $u['role']) ?>. Changer de mot de passe ferme vos autres sessions.</p>
  <button class="b b--y" type="submit">Enregistrer</button>
</form>
