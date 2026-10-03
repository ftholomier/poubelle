<?php /** Mot de passe oublié. Variables : $sent */ ?>
<h1>Mot de passe oublié</h1>
<?php if ($sent): ?>
  <p class="alert alert--ok">Si un compte existe pour cette adresse, un lien pour choisir un nouveau mot de passe vient d’être envoyé. Il est valable 7 jours.</p>
  <a class="btn btn--navy" href="/admin/connexion" style="align-self:flex-start">← Connexion</a>
<?php else: ?>
  <p style="margin:0">Indiquez votre adresse : vous recevrez un lien pour choisir un nouveau mot de passe.</p>
  <form method="post" class="stack" style="gap:14px">
    <?= csrf_field() ?>
    <label class="f"><span class="f__k">E-mail</span><input type="email" name="email" required autocomplete="username" autofocus></label>
    <button type="submit" class="btn btn--navy btn--lg">Recevoir le lien</button>
  </form>
  <a class="linkbtn" href="/admin/connexion">← Retour à la connexion</a>
<?php endif; ?>
