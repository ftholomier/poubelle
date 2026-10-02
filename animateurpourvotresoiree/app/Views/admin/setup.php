<?php /** @var bool $hasToken @var array $errors @var string $prefillToken */ $err = static fn ($k) => isset($errors[$k]) ? '<span class="field-error">' . e($errors[$k]) . '</span>' : ''; ?>
<h1 class="h3">Installation du back-office</h1>
<?php if (!$hasToken): ?>
  <div class="alert alert-warning mt-2"><div>Aucun jeton d'installation n'est défini. Ajoutez une ligne <code>SETUP_TOKEN=…</code> (une longue chaîne aléatoire) dans <code>config/.env</code>, ou créez le compte en ligne de commande : <code>php bin/admin.php create</code>.</div></div>
<?php else: ?>
<p class="small">Créez le compte <strong>super-administrateur</strong>. Le jeton d'installation figure dans le fichier <code>config/.env</code> (ligne <code>SETUP_TOKEN</code>) ; il sera effacé après usage.</p>
<form class="form mt-2" method="post" action="<?= e(App\Core\Url::admin('setup')) ?>">
  <?= csrf_field() ?>
  <div class="field"><label for="s-token">Jeton d'installation</label><input id="s-token" type="password" name="token" value="<?= e($prefillToken) ?>" required autocomplete="off"><?= $err('token') ?></div>
  <div class="field"><label for="s-name">Votre nom</label><input id="s-name" type="text" name="name" maxlength="80" required value="<?= e((string) App\Core\Request::input('name', '')) ?>"><?= $err('name') ?></div>
  <div class="field"><label for="s-email">Email</label><input id="s-email" type="email" name="email" required autocomplete="username" value="<?= e((string) App\Core\Request::input('email', '')) ?>"><?= $err('email') ?></div>
  <div class="field"><label for="s-pw">Mot de passe</label><input id="s-pw" type="password" name="password" required autocomplete="new-password" data-strength><?= $err('password') ?></div>
  <div class="field"><label for="s-pw2">Confirmation</label><input id="s-pw2" type="password" name="password2" required autocomplete="new-password"></div>
  <button class="btn btn-coral btn-block" type="submit">Créer mon compte</button>
</form>
<?php endif; ?>
