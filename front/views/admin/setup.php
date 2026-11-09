<h1>Installation du back-office</h1>
<p class="bo-muted">Créez le compte administrateur. La clé d'installation se trouve dans <code>config/config.local.php</code> (<code>setup_key</code>) ou, à défaut, dans le fichier <code>storage/data/setup-key.txt</code> du serveur (à lire par FTP).</p>
<form data-api="setup" class="bo-form">
  <div class="f"><label class="f-label" for="s-key">Clé d'installation</label><input id="s-key" name="key" required autocomplete="off"></div>
  <div class="f"><label class="f-label" for="s-name">Nom</label><input id="s-name" name="name" autocomplete="name"></div>
  <div class="f"><label class="f-label" for="s-email">E-mail</label><input id="s-email" type="email" name="email" autocomplete="username" required></div>
  <div class="f"><label class="f-label" for="s-pw">Mot de passe (10 caractères minimum, lettres et chiffres)</label><input id="s-pw" type="password" name="password" autocomplete="new-password" required></div>
  <button class="b b--y b--full" type="submit">Créer le compte</button>
</form>
