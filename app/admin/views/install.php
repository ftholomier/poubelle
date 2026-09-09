<?php
/** Écran d'installation : création du tout premier compte administrateur. */

use App\Config;
use App\Csrf;
use App\Router;
use App\Text;
use App\View;

echo View::admin('_head', ['title' => 'Installation']);
?>
<div class="auth">
  <div class="auth__side">
    <img class="auth__logo" src="<?= Text::e(Config::basePath()) ?>/assets/img/ioio-logo.png" alt="Le iOiO">
    <div>
      <div class="auth__kicker">INSTALLATION</div>
      <h1 class="auth__title">Créons votre compte administrateur.</h1>
      <p class="auth__text">Cet écran n'apparaît qu'une fois : dès qu'un compte existe, il laisse la place à la page de connexion. Aucun identifiant n'est écrit en dur dans le code.</p>
    </div>
    <div class="auth__facts">
      <span>Argon2id</span><span>Aucun mot de passe par défaut</span><span>Fichier content/users.json</span>
    </div>
  </div>

  <div class="auth__main">
    <form class="auth__form" method="post" action="<?= Text::e(Router::adminUrl()) ?>">
      <?= Csrf::field('install') ?>
      <input type="hidden" name="action" value="install">
      <h2>Premier compte</h2>
      <p>Renseignez l'adresse de l'équipe qui gérera le site.</p>

      <?php if (!empty($error)): ?><div class="flash flash--error" style="margin:0 0 18px"><?= Text::e((string) $error) ?></div><?php endif; ?>

      <label class="label" for="iname">PRÉNOM ET NOM</label>
      <input class="field" id="iname" type="text" name="name" placeholder="Fanny">

      <label class="label label--mt" for="iemail">EMAIL</label>
      <input class="field" id="iemail" type="email" name="email" required autocomplete="username" placeholder="prenom@ioio.fr">

      <label class="label label--mt" for="ipass">MOT DE PASSE (10 CARACTÈRES MINIMUM)</label>
      <input class="field" id="ipass" type="password" name="password" required minlength="10" autocomplete="new-password">

      <label class="label label--mt" for="ipass2">CONFIRMATION</label>
      <input class="field" id="ipass2" type="password" name="password2" required minlength="10" autocomplete="new-password">

      <button class="btn btn--ink btn--block" type="submit" style="margin-top:22px">Créer le compte et entrer</button>
      <div class="auth__note">À la validation : la photothèque récupère les images déjà présentes dans /public/media et l'index de l'assistant est construit.</div>
    </form>
  </div>
</div>
</body>
</html>
