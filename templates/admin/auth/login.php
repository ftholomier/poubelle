<?php /** Connexion. Variables : $error, $email, $r */ ?>
<h1>Connexion</h1>
<?php if ($error): ?><p class="alert alert--error" role="alert"><?= e($error) ?></p><?php endif; ?>
<?php foreach ($flash ?? [] as $f): ?><p class="alert alert--<?= $f['type'] === 'error' ? 'error' : 'ok' ?>"><?= e($f['message']) ?></p><?php endforeach; ?>
<form method="post" action="/admin/connexion?r=<?= e(rawurlencode($r)) ?>" class="stack" style="gap:14px">
  <?= csrf_field() ?>
  <label class="f"><span class="f__k">E-mail</span><input type="email" name="email" value="<?= e($email) ?>" required autocomplete="username" autofocus></label>
  <label class="f"><span class="f__k">Mot de passe</span><input type="password" name="password" required autocomplete="current-password"></label>
  <button type="submit" class="btn btn--navy btn--lg">Se connecter</button>
</form>
<a class="linkbtn" href="/admin/mot-de-passe-oublie">Mot de passe oublié ?</a>
