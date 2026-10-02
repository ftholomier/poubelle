<section class="auth-wrap">
  <div class="auth-card form-card">
    <div class="badge badge-tilt">● Espace pro</div>
    <h1 class="h2 mt-2">Bon retour <span class="serif c-coral">parmi nous</span></h1>
    <p class="muted">Connectez-vous avec votre <strong>email</strong> ou votre <strong>identifiant de l'ancien site</strong>.</p>
    <form class="form mt-2" method="post" action="/connexion/">
      <?= csrf_field() ?>
      <div class="field"><label for="lg-id">Email ou identifiant</label><input id="lg-id" type="text" name="identifier" value="<?= e(old('identifier')) ?>" autocomplete="username" required autofocus></div>
      <div class="field"><label for="lg-pw">Mot de passe</label><input id="lg-pw" type="password" name="password" autocomplete="current-password" required>
        <span class="hint"><a href="/mot-de-passe-oublie/">Mot de passe oublié ?</a></span></div>
      <button class="btn btn-coral btn-block" type="submit">Me connecter →</button>
    </form>
    <div class="auth-alt">Pas encore inscrit ? <a class="link" href="/inscription-pro/">Créer ma fiche gratuitement</a></div>
  </div>
  <div class="auth-aside">
    <h2 class="h3">Le site fait <span class="serif c-coral">peau neuve</span> 🎉</h2>
    <ul class="list-check">
      <li>Inscription et demandes de devis <strong>100 % gratuites</strong>, sans commission</li>
      <li>Vos demandes et messages centralisés dans votre espace</li>
      <li>Statistiques de votre fiche, avis clients vérifiés</li>
      <li>Notifications sur votre téléphone (application installable)</li>
    </ul>
    <p class="small muted mt-2">Vous étiez inscrit sur l'ancien site ? Vos identifiants fonctionnent toujours. Par sécurité, il vous sera demandé de choisir un nouveau mot de passe à la première connexion.</p>
  </div>
</section>
