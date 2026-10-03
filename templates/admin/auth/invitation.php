<?php /** Invitation / nouveau mot de passe. Variables : $u, $error */ ?>
<h1><?= $u['status'] === 'invited' ? 'Bienvenue !' : 'Nouveau mot de passe' ?></h1>
<p style="margin:0"><?= $u['status'] === 'invited' ? 'Choisissez votre mot de passe pour activer votre compte <b>' . e($u['email']) . '</b>.' : 'Choisissez un nouveau mot de passe pour <b>' . e($u['email']) . '</b>.' ?></p>
<?php if ($error): ?><p class="alert alert--error" role="alert"><?= e($error) ?></p><?php endif; ?>
<form method="post" class="stack" style="gap:14px">
  <?= csrf_field() ?>
  <?php if ($u['status'] === 'invited'): ?><label class="f"><span class="f__k">Votre nom (affiché dans le journal)</span><input name="name" value="<?= e($u['name']) ?>" required autocomplete="name"></label><?php endif; ?>
  <label class="f"><span class="f__k">Mot de passe <i>10 caractères au moins</i></span><input type="password" name="password" minlength="10" required autocomplete="new-password" autofocus></label>
  <label class="f"><span class="f__k">Confirmation</span><input type="password" name="password2" minlength="10" required autocomplete="new-password"></label>
  <button type="submit" class="btn btn--navy btn--lg">Enregistrer et entrer</button>
</form>
