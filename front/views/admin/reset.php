<?php /** @var ?array $user */ ?>
<h1>Nouveau mot de passe</h1>
<?php if (!$user): ?>
  <p class="bo-muted">Ce lien a expiré ou a déjà été utilisé.</p>
  <p class="bo-auth__alt"><a href="/admin/mot-de-passe-oublie">Demander un nouveau lien</a></p>
<?php else: ?>
<form data-api="reset" class="bo-form">
  <input type="hidden" name="t" value="<?= e((string) ($_GET['t'] ?? '')) ?>">
  <p class="bo-muted">Compte : <?= e($user['email']) ?></p>
  <div class="f"><label class="f-label" for="r-pw">Nouveau mot de passe</label><input id="r-pw" type="password" name="password" autocomplete="new-password" required autofocus></div>
  <button class="b b--y b--full" type="submit">Enregistrer</button>
</form>
<?php endif; ?>
