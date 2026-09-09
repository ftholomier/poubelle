<?php
/** Connexion, mot de passe oublié et réinitialisation — écran unique en deux volets. */

use App\Config;
use App\Csrf;
use App\Router;
use App\Session;
use App\Text;
use App\View;

$mode = $mode ?? 'login';
$flash = Session::flash();
echo View::admin('_head', ['title' => 'Connexion']);
?>
<div class="auth">
  <div class="auth__side">
    <img class="auth__logo" src="<?= Text::e(Config::basePath()) ?>/assets/img/ioio-logo.png" alt="Le iOiO">
    <div>
      <div class="auth__kicker">BACK-OFFICE</div>
      <h1 class="auth__title">Le contenu du site, sans toucher au code.</h1>
      <p class="auth__text">Textes, photos, tarifs, disponibilités, documents de l'assistant IA. Chaque enregistrement écrit un fichier JSON versionné : le front reste debout même en cas d'erreur de saisie.</p>
    </div>
    <div class="auth__facts">
      <span>Sans base de données</span><span>Verrou d'édition</span><span>Versions restaurables</span>
    </div>
  </div>

  <div class="auth__main">
    <?php if ($mode === 'login'): ?>
      <form class="auth__form" method="post" action="<?= Text::e(Router::adminUrl()) ?><?= isset($_GET['next']) ? '?next=' . rawurlencode((string) $_GET['next']) : '' ?>">
        <?= Csrf::field('login') ?>
        <input type="hidden" name="action" value="login">
        <h2>Connexion</h2>
        <p>Accès réservé à l'équipe du iOiO.</p>

        <?php if (!empty($flash)): ?><div class="flash flash--<?= Text::e((string) $flash['type']) ?>" style="margin:0 0 18px"><?= Text::e((string) $flash['message']) ?></div><?php endif; ?>
        <?php if (!empty($error)): ?><div class="flash flash--error" style="margin:0 0 18px"><?= Text::e((string) $error) ?></div><?php endif; ?>

        <label class="label" for="email">EMAIL</label>
        <input class="field" id="email" type="email" name="email" required autocomplete="username" placeholder="prenom@ioio.fr">

        <label class="label label--mt" for="password">MOT DE PASSE</label>
        <input class="field" id="password" type="password" name="password" required autocomplete="current-password" placeholder="••••••••••">

        <div class="auth__row">
          <label class="auth__check"><input type="checkbox" name="remember" value="1"><span>Rester connecté 7 jours</span></label>
          <a class="link-underline" href="<?= Text::e(Router::adminUrl('forgot')) ?>">Mot de passe oublié ?</a>
        </div>

        <button class="btn btn--ink btn--block" type="submit">Se connecter</button>
        <div class="auth__note">Sécurité : mot de passe haché en Argon2id, blocage après 5 tentatives, session en cookie HttpOnly + SameSite=Strict, jeton CSRF sur chaque formulaire.</div>
      </form>

    <?php elseif ($mode === 'forgot'): ?>
      <form class="auth__form" method="post" action="<?= Text::e(Router::adminUrl()) ?>">
        <?= Csrf::field('forgot') ?>
        <input type="hidden" name="action" value="forgot">
        <h2>Mot de passe oublié</h2>
        <p>On vous envoie un lien de réinitialisation valable 30 minutes, utilisable une seule fois.</p>

        <label class="label" for="femail">EMAIL DU COMPTE</label>
        <input class="field" id="femail" type="email" name="email" required autocomplete="username" placeholder="prenom@ioio.fr">

        <button class="btn btn--yellow btn--block" type="submit" style="margin-top:20px">Envoyer le lien</button>
        <a class="btn btn--outline btn--block" href="<?= Text::e(Router::adminUrl()) ?>" style="margin-top:12px">Retour à la connexion</a>

        <?php if (!empty($sent)): ?>
          <div class="flash flash--ok" style="margin-top:18px">Si un compte existe pour cette adresse, le lien vient de partir. Vérifiez vos spams.</div>
        <?php endif; ?>
        <div class="hint" style="margin-top:18px">La réponse est toujours la même, qu'un compte existe ou non : impossible de deviner les emails de l'équipe.</div>
      </form>

    <?php else: ?>
      <form class="auth__form" method="post" action="<?= Text::e(Router::adminUrl()) ?>">
        <?= Csrf::field('reset') ?>
        <input type="hidden" name="action" value="reset">
        <input type="hidden" name="email" value="<?= Text::e((string) ($email ?? '')) ?>">
        <input type="hidden" name="token" value="<?= Text::e((string) ($token ?? '')) ?>">
        <h2>Nouveau mot de passe</h2>
        <p>Choisissez un mot de passe d'au moins 10 caractères. Le lien ne fonctionne qu'une fois.</p>

        <?php if (!empty($error)): ?><div class="flash flash--error" style="margin:0 0 18px"><?= Text::e((string) $error) ?></div><?php endif; ?>

        <label class="label" for="np">NOUVEAU MOT DE PASSE</label>
        <input class="field" id="np" type="password" name="password" required minlength="10" autocomplete="new-password">
        <label class="label label--mt" for="np2">CONFIRMATION</label>
        <input class="field" id="np2" type="password" name="password2" required minlength="10" autocomplete="new-password">

        <button class="btn btn--ink btn--block" type="submit" style="margin-top:20px">Enregistrer</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
