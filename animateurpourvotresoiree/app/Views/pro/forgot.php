<section class="auth-wrap single">
  <div class="auth-card form-card">
    <h1 class="h2">Mot de passe <span class="serif c-coral">oublié</span></h1>
    <p class="muted">Indiquez votre email ou votre identifiant : nous vous envoyons un lien pour choisir un nouveau mot de passe.</p>
    <form class="form mt-2" method="post" action="/mot-de-passe-oublie/">
      <?= csrf_field() ?>
      <div class="field"><label for="fg-id">Email ou identifiant</label><input id="fg-id" type="text" name="identifier" autocomplete="username" required autofocus></div>
      <button class="btn btn-coral btn-block" type="submit">Recevoir le lien</button>
    </form>
    <div class="auth-alt"><a class="link" href="/connexion/">← Retour à la connexion</a></div>
    <p class="small muted mt-2">Vous n'avez plus accès à l'adresse email de votre compte ? <a href="/contact/?sujet=acces">Contactez-nous</a>.</p>
  </div>
</section>
