<?php /** Premier accès. Variables : $error, $old */ ?>
<h1>Premier accès</h1>
<p style="margin:0">Créez le compte administrateur du back-office. Pour prouver que vous gérez le serveur, ouvrez le fichier <code>storage/premier-acces.txt</code> (gestionnaire de fichiers d’o2switch ou FTP) et recopiez le code qu’il contient.</p>
<?php if ($error): ?><p class="alert alert--error" role="alert"><?= e($error) ?></p><?php endif; ?>
<form method="post" action="/admin/premier-acces" class="stack" style="gap:14px">
  <?= csrf_field() ?>
  <label class="f"><span class="f__k">Code de premier accès</span><input name="code" required autocomplete="off" style="font-family:ui-monospace,monospace;letter-spacing:.1em;text-transform:uppercase"></label>
  <label class="f"><span class="f__k">Votre nom</span><input name="name" value="<?= e($old['name']) ?>" required autocomplete="name"></label>
  <label class="f"><span class="f__k">E-mail</span><input type="email" name="email" value="<?= e($old['email']) ?>" required autocomplete="username"></label>
  <label class="f"><span class="f__k">Mot de passe <i>10 caractères au moins</i></span><input type="password" name="password" minlength="10" required autocomplete="new-password"></label>
  <label class="f"><span class="f__k">Confirmation</span><input type="password" name="password2" minlength="10" required autocomplete="new-password"></label>
  <button type="submit" class="btn btn--navy btn--lg">Créer le compte administrateur</button>
</form>
